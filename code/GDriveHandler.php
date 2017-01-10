<?php

class GDriveHandler {

    // Name of the backup directory at GDrive
    public static  $GDRIVE_BACKUP_FOLDER = 'Backuper';

    // File type of the backup file, must be either tgz, tar.gz, sspan
    public static  $BACKUP_FILE_TYPE = 'tgz';

    // Mime file type of the backup file
    public static  $GDRIVE_MIME_BACKUP = 'application/tgz';

    // Mime type of a GDrive directory
    public static  $GDRIVE_MIME_FOLDER = 'application/vnd.google-apps.folder';

    private $client;
    private $service;

    function __construct() {
        $this->client = self::createGoogleClient();
        $this->service = new Google_Service_Drive($this->client);
    }

    /**
     * Gets a new Google_Client object initialized with our client credentials
     * @return bool|Google_Client
     */
    public static function createGoogleClient()
    {
        // Get client creds from backup settings
        $backupSettings = BackupAdminSettings::get()->first();

        $client_id = $backupSettings->GDriveClientId;
        $client_secret = $backupSettings->GDriveClientSecret;

        // Check for missing client secrets
        if (strpos($client_id, "googleusercontent") == false || !$client_secret) {
            return false;
        }

        $client = new Google_Client();
        $client->setClientId($client_id);
        $client->setClientSecret($client_secret);
        $client->addScope("https://www.googleapis.com/auth/drive");
        $client->setAccessType("offline");  //$client->setAccessType("refresh_token");
        $client->setApprovalPrompt("force");


        if (file_exists(self::getAccessTokenFileURI())) {

            $token = file_get_contents(self::getAccessTokenFileURI());

            $client->setAccessToken($token);

            if ($client->isAccessTokenExpired()) {

                try {
                    $refreshtoken = json_decode($token)->refresh_token;
                    $client->refreshToken($refreshtoken);
                } catch (Google_Auth_Exception $e) {
                    echo 'Can not refresh token!';
                }

                $newToken = $client->getAccessToken();

                if ($newToken)
                    self::saveAccessToken($newToken);
            }
        } else {
            echo 'Token is not stored!';
        }

        return $client;
    }

    public function getGoogleClient() {
        return $this->client;
    }

    /**
     * Checks if we are authenticated for GDrive service
     * @param $client
     * @return bool
     */
    public function isGDriveAuthenticated()
    {
        return $this->client->getAccessToken() && !$this->client->isAccessTokenExpired();
    }


    /**
     * Get a list of backups that are stored in Backuper folder in GDrive
     * @param $client
     * @return array|bool
     */
    public function getGDriveBackups()
    {
        if (!$this->isGDriveAuthenticated())
            return false;

        if (!($folderId = $this->getGDriveBackupFolderId()))
            return false;

        $results = $this->service->files->listFiles(array('q' => "'" . $folderId . "' in parents"));

        $backups = array();
        foreach ($results as $file) {
            $backups[] = array(
                'Filename' => $file['title'],
                'Id' => $file['id']);
        }

        return $backups;
    }

    /**
     * Get the folder id of the GDrive backup folder
     * @param $service
     * @return bool
     */
    public function getGDriveBackupFolderId()
    {
        $results = $this->service->files->listFiles();

        foreach ($results as $file) {
            // Check for folder mimeType
            if ($file['mimeType'] == self::$GDRIVE_MIME_FOLDER)
                if ($file['title'] == self::$GDRIVE_BACKUP_FOLDER)
                    return $file['id'];
        }

        return false;
    }

    /**
     * Create backup folder in GDrive
     * @param $service
     * @return bool
     */
    public function createGDriveBackupFolder()
    {
        $file = new Google_Service_Drive_DriveFile();
        $file->title = self::$GDRIVE_BACKUP_FOLDER;
        $file->mimeType = self::$GDRIVE_MIME_FOLDER;

        $respond = $this->service->files->insert($file);

        return $respond ? $respond->id : false;
    }

    public static function getAccessTokenFileURI() {
        return realpath(dirname(__FILE__)) . '/../_config/accesstoken.txt';
    }

    /**
     * Stores the Gdrive access token to a file in _config dir
     * @param $token
     */
    public static function saveAccessToken($token) {
        file_put_contents(self::getAccessTokenFileURI(), $token);
    }


    /**
     * Uploads a file to GDrive
     * @param $filePath
     * @param $filename
     * @return bool|false|mixed
     */
    public function uploadBackupGDrive($filePath, $filename)
    {
        // Get client and check if valid
        if (!$this->client) {
            return false;
        }

        // Check for valid access token
        if (!GDriveHandler::isGDriveAuthenticated($this->client)) {
            return false;
        }

        $service = new Google_Service_Drive($this->client);

        $file = new Google_Service_Drive_DriveFile();
        $file->title = $filename;

        $parent = new Google_Service_Drive_ParentReference();

        if (!($folderId = GDriveHandler::getGDriveBackupFolderId($service))) {
            $folderId = GDriveHandler::createGDriveBackupFolder($service);
        }
        $parent->setId($folderId);

        $file->parents = array($parent);

        // Call the API with the media upload defer so it doesn't immediately return
        $this->client->setDefer(true);

        $request = $service->files->insert($file);

        $chunkSizeBytes = 1 * 1024 * 1024;

        // Create a media file upload to represent our upload process.
        $media = new Google_Http_MediaFileUpload(
            $this->client,
            $request,
            GDriveHandler::$GDRIVE_MIME_BACKUP,
            null,
            true,
            $chunkSizeBytes
        );
        $media->setFileSize(filesize($filePath));

        // Upload the various chunks. $status will be false until the process is complete
        $status = false;
        $handle = fopen($filePath, "rb");
        while (!$status && !feof($handle)) {
            // read until you get $chunkSizeBytes from file
            // fread will never return more than 8192 bytes if the stream is read buffered and
            // it does not represent a plain file
            // An example of a read buffered file is when reading from a URL
            $chunk = self::readChunk($handle, $chunkSizeBytes);
            $status = $media->nextChunk($chunk);
        }

        fclose($handle);

        return true;
    }

    /**
     * Helper function to read a file chunk
     * @param $handle
     * @param $chunkSize
     * @return string
     */
    private function readChunk($handle, $chunkSize)
    {
        $byteCount = 0;
        $giantChunk = "";
        while (!feof($handle)) {
            // fread will never return more than 8192 bytes if the stream is read buffered and
            // it does not represent a plain file
            $chunk = fread($handle, 8192);
            $byteCount += strlen($chunk);
            $giantChunk .= $chunk;
            if ($byteCount >= $chunkSize) {
                return $giantChunk;
            }
        }
        return $giantChunk;
    }

}