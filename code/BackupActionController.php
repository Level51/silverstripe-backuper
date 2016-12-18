<?php
require_once realpath(BASE_PATH . '/vendor/google/apiclient/src/Google/autoload.php');

// Define the root of sspak package
define('PACKAGE_ROOT', BASE_PATH . '/vendor/silverstripe/sspak/');


/* Setting STDIN and STDERR for SSPak commands */
// STDIN is a pipe, which reads from the child process
const STDIN = array("pipe", "r");

// STDERR is a pipe, which writes to the child process
const STDERR = array("pipe", "w");

// Name of the backup controller's cache
const CACHE_NAME = 'BackupCache';

// Cache key for storing the GDrive access token in the cache
const CACHE_KEY_GDRIVE_TOKEN = 'GDriveToken';

// Name of the backup directory at GDrive
const GDRIVE_BACKUP_FOLDER = 'Backuper';

// File type of the backup file, must be either tgz, tar.gz, sspan
const BACKUP_FILE_TYPE = 'tgz';

// Mime file type of the backup file
const GDRIVE_MIME_BACKUP = 'application/' . BACKUP_FILE_TYPE;

// Mime type of a GDrive directory
const GDRIVE_MIME_FOLDER = 'application/vnd.google-apps.folder';

/*
 * Required commands for SSPak according to
 * https://github.com/silverstripe/sspak#how-it-works
*/
const REQUIRED_COMMANDS = array('mysql', 'mysqldump', 'tar', 'gzip', 'sudo');

class BackupActionController extends Controller
{

    private static $host = 'localhost';

    private static $allowed_actions = array(
        'createBackup' => 'ADMIN',
        'restoreBackup' => 'ADMIN',
        'getBackup' => 'ADMIN',
        'authenticateGDrive' => 'ADMIN'
    );

    private static $url_handlers = array(
        'create-backup' => 'createBackup',
        'restore-upload' => 'restoreBackup',
        'restore-gdrive/$GDriveFileId' => 'restoreBackup',
        'get-backup/$Timestamp' => 'getBackup',
        'authenticate-gdrive' => 'authenticateGDrive'
    );

    /**
     * Gets a dump of the database and archives it together with the assets by using the ssPak tool.
     * @param SS_HTTPRequest $request
     * @return string
     */
    public function createBackup(SS_HTTPRequest $request)
    {
        // Check for missing commands
        if ($cmds = self::areCommandsMissing(REQUIRED_COMMANDS)) {
            return self::errorMsg(sprintf(_t('BackupActionController.BACKUP_CMDS_MISSING'), join(' ', $cmds)));
        }

        $timestamp = date('YmdHis');

        // Get a proper formatted backup filename using the current timestamp
        $filename = self::getBackupFilename($timestamp);

        // Get file path to backup directory
        $fileURI = self::getBackupFileURI($filename);

        // Get backup settings
        $backupSettings = BackupAdminSettings::get()->first();

        // Check if we should backup DB and/or assets, and transfer to Google Drive
        $backupDB = $backupSettings->BackupDatabaseEnabled;
        $backupAssets = $backupSettings->BackupAssetsEnabled;
        $transferBackup = $backupSettings->BackupTransferEnabled;

        // Check for missing backup option
        if (!$backupDB && !$backupAssets) {
            return self::errorMsg(_t('BackupActionController.BACKUP_OPTION_MISSING'));
        }

        // Create command line args used for ssPak save command
        $args = new Args(
            array(
                '',
                'save', // Command
                BASE_PATH, // Silverstripe root directory
                $fileURI, // Backup file path,
                $backupDB ? '--db' : '', // Backup database setting
                $backupAssets ? '--assets' : '' // Backup assets setting
            )
        );

        // Create a SsPak instance
        $ssPak = new SSPak(new Executor());

        try {
            // Starting backup
            $ssPak->save($args);
        } catch (Exception $e) {
            return self::errorMsg(_t('BackupActionController.BACKUP_FAILED') . ' ' . $e->getMessage());
        }


        $message = 'Backup complete!';

        // Preparing info that has to be returned
        $resultInfo = array(
            'Success' => 1,
            'Message' => $message,
            'Timestamp' => intval($timestamp)
        );

        if ($transferBackup) {
            $gDriveUploadStatus = $this->uploadBackupGDrive($fileURI, $filename);

            $statusMsg = $gDriveUploadStatus ?
                _t('BackupActionController.UPLOADED') : _t('BackupActionController.NOT_UPLOADED');

            $resultInfo['GDriveUploadStatus'] = array(
                'Success' => $gDriveUploadStatus ? 1 : 0,
                'Message' => _t('BackupActionController.GDRIVE_STATUS') . ' ' . $statusMsg
            );
        }

        // TODO: Delete backup file here?


        return json_encode($resultInfo);
    }

    /**
     * @param SS_HTTPRequest $request
     * @return int
     */
    public function getBackup(SS_HTTPRequest $request)
    {
        if (!$request->param('Timestamp'))
            return 0;

        $timestamp = intval($request->param('Timestamp'));

        $filename = self::getBackupFilename($timestamp);
        $fileURI = self::getBackupFileURI($filename);

        // Return archive as download
        header("Content-type: " . GDRIVE_MIME_BACKUP);
        header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
        header("Content-Length: " . filesize($fileURI));
        return readfile($fileURI);
    }

    /**
     * Restores a backup by using ssPak.
     * @param SS_HTTPRequest $request
     * @return string
     */
    public function restoreBackup(SS_HTTPRequest $request)
    {
        // Check for missing commands
        if ($cmds = self::areCommandsMissing(REQUIRED_COMMANDS)) {
            return self::errorMsg(sprintf(_t('BackupActionController.BACKUP_CMDS_MISSING'), join(' ', $cmds)));
        }

        // Get request url
        $url = $request['url'];

        $uploadRestoreMode = strpos($url, 'restore-upload') !== false;
        $uploadGDriveMode = strpos($url, 'restore-gdrive') !== false;

        if ($uploadRestoreMode) {

            // Get backup settings
            $backupSettings = BackupAdminSettings::get()->first();

            if (!$backupSettings || !($restoreFile = $backupSettings->RestoreFile()) || !$restoreFile->Name) {
                return self::errorMsg(_t('BackupActionController.BACKUP_NOT_UPLOADED_YET'));
            }

            // Get path to upload in assets dir
            $uploadFilePath = BASE_PATH . DIRECTORY_SEPARATOR . $restoreFile->Filename;

            // Check if a backup file still exists at disk
            if (!file_exists($uploadFilePath)) {
                return self::errorMsg(_t('BackupActionController.BACKUP_DOES_NOT_EXIST'));
            }

            // Get filename and create a new path to move file into backup dir
            $restoreFilename = $restoreFile->Name;
            $restoreFilePath = self::getBackupFileURI($restoreFilename);

            // Move file from assets into backup dir (usually tmp)
            rename($uploadFilePath, $restoreFilePath);

        } else if ($uploadGDriveMode) {

            if(!($fileId = $request->param('GDriveFileId')))
                return self::errorMsg(_t('BackupActionController.NO_GDRIVE_FILEID'));

            if (!$client = $this->getGoogleClient()) {
                return self::errorMsg(_t('BackupActionController.COULD_NOT_CREATE_GDRIVE'));
            }

            if (!$this->isGDriveAuthenticated($client)) {
                return self::errorMsg(_t('BackupActionController.GDRIVE_NOT_AUTHENTICATED'));
            }

            $service = new Google_Service_Drive($client);

            $content = $service->files->get($fileId, array(
                'alt' => 'media' ));

            $restoreFilename = self::getBackupFilename($fileId);
            $restoreFilePath = self::getBackupFileURI($restoreFilename);

            file_put_contents($restoreFilePath, $content);

            //return 1;
        }
        else {
            return self::errorMsg(_t('BackupActionController.NOT_A_VALID_RESTORE_MODE'));
        }

        // Create a SsPak instance
        $ssPak = new SSPak(new Executor());

        // Create command line args used for ssPak save command
        $args = new Args(
            array(
                '',
                'load', // Command
                $restoreFilePath, // Backup file path,
                BASE_PATH // Silverstripe root directory
            )
        );

        try {
            // Starting backup
            $ssPak->load($args);
        } catch (Exception $e) {
            return self::errorMsg(_t('BackupActionController.RESTORE_FAILED') . ' ' . $e->getMessage());
        }

        // Delete uploaded backup file after a successful restore
        unlink($restoreFilePath);

        return json_encode(
            array(
                'Success' => 1,
                'Message' => _t('BackupActionController.RESTORE_COMPLETE')
            )
        );
    }

    /**
     * Endpoint to authenticate the backend to client's Google account
     * @param SS_HTTPRequest $request
     * @return bool|string
     */
    public function authenticateGDrive(SS_HTTPRequest $request)
    {
        if (!$client = $this->getGoogleClient()) {
            return _t('BackupActionController.COULD_NOT_CREATE_GDRIVE');
        }

        $cache = SS_Cache::factory(CACHE_NAME);

        if (isset($_REQUEST['logout'])) {
            $cache->remove(CACHE_KEY_GDRIVE_TOKEN);
            $client->revokeToken();
            return _t('BackupActionController.LOGGED_OUT');
        }

        // Set redirect URI that is browsed after auth
        $redirect_uri = Director::absoluteBaseURL() . substr($_GET['url'], 1);
        $client->setRedirectUri($redirect_uri);

        // Check for code GET variable, which will be set by Google's redirect
        if (isset($_GET['code'])) {

            // Authenticate with given code of the redirect
            $client->authenticate($_GET['code']);

            $token = $client->getAccessToken();

            // Set cache lifetime for one year
            SS_Cache::set_cache_lifetime(CACHE_NAME, 365*24*3600);

            $cache->save($token, CACHE_KEY_GDRIVE_TOKEN);

            // Redirect to admin
            $admin_redirect_uri = Director::absoluteBaseURL() . 'admin/' . BackupAdmin::getUrlSegment();
            header('Location: ' . filter_var($admin_redirect_uri, FILTER_SANITIZE_URL));
            return true;
        }

        // Check if token is set
        if ($client->getAccessToken()) {

            if ($client->isAccessTokenExpired()) {
                return _t('BackupActionController.TOKED_EXPIRED');
            }
        } else {
            // Create new auth URL
            $authUrl = $client->createAuthUrl();

            // Redirect to auth URL
            header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
        }

        return $client->getAccessToken() ?
            _t('BackupActionController.ALREADY_AUTHENTICATED') : _t('BackupActionController.NOT_AUTHENTICATED');
    }

    /**
     * Gets a new Google_Client object initialized with our client credentials
     * @return bool|Google_Client
     */
    public static function getGoogleClient()
    {
        // Get backup settings
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
        $client->setAccessType("offline");
        $client->setApprovalPrompt ("force");

        $cache = SS_Cache::factory(CACHE_NAME);

        if ($token = $cache->load(CACHE_KEY_GDRIVE_TOKEN)) {
            $client->setAccessToken($token);

            if($client->isAccessTokenExpired()){

                $client->refreshToken($token['refresh_token']);
                $newToken = $client->getAccessToken();

                if($newToken)
                    $cache->save($newToken, CACHE_KEY_GDRIVE_TOKEN);
            }
        } else {
            //echo 'Token is not in cache!';
        }

        return $client;
    }

    /**
     * Checks if we are authenticated for GDrive service
     * @param $client
     * @return bool
     */
    public static function isGDriveAuthenticated($client)
    {
        return $client->getAccessToken() && !$client->isAccessTokenExpired();
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
        if (!($client = $this->getGoogleClient())) {
            return false;
        }

        // Check for valid access token
        if (!$this->isGDriveAuthenticated($client)) {
            return false;
        }

        $service = new Google_Service_Drive($client);

        $file = new Google_Service_Drive_DriveFile();
        $file->title = $filename;

        $parent = new Google_Service_Drive_ParentReference();

        if (!($folderId = $this->getGDriveBackupFolderId($service))) {
            $folderId = $this->createGDriveBackupFolder($service);
        }
        $parent->setId($folderId);

        $file->parents = array($parent);

        // Call the API with the media upload defer so it doesn't immediately return
        $client->setDefer(true);

        $request = $service->files->insert($file);

        $chunkSizeBytes = 1 * 1024 * 1024;

        // Create a media file upload to represent our upload process.
        $media = new Google_Http_MediaFileUpload(
            $client,
            $request,
            GDRIVE_MIME_BACKUP,
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

        return $status;
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

    /**
     * Get a list of backups that are stored in Backuper folder in GDrive
     * @param $client
     * @return array|bool
     */
    public function getGDriveBackups($client)
    {
        if(!$this->isGDriveAuthenticated($client))
            return false;

        $service = new Google_Service_Drive($client);

        if (!($folderId = $this->getGDriveBackupFolderId($service)))
            return false;

        $results = $service->files->listFiles(array('q' => "'" . $folderId . "' in parents"));

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
    public function getGDriveBackupFolderId($service)
    {
        $results = $service->files->listFiles();

        foreach ($results as $file) {
            // Check for folder mimeType
            if ($file['mimeType'] == GDRIVE_MIME_FOLDER)
                if ($file['title'] == GDRIVE_BACKUP_FOLDER)
                    return $file['id'];
        }

        return false;
    }

    /**
     * Create backup folder in GDrive
     * @param $service
     * @return bool
     */
    public function createGDriveBackupFolder($service)
    {
        $file = new Google_Service_Drive_DriveFile();
        $file->title = GDRIVE_BACKUP_FOLDER;
        $file->mimeType = GDRIVE_MIME_FOLDER;

        $respond = $service->files->insert($file);

        return $respond ? $respond->id : false;
    }


    /**
     * Returns a valid filename of a backup based on a timestamp
     * @param $timestamp
     * @return string
     */
    private function getBackupFilename($timestamp)
    {
        // Get DB name // TODO: Use different parameter for backup names?
        $db = defined('SS_DATABASE_PREFIX') ? SS_DATABASE_PREFIX : '';
        if (isset($GLOBALS['database']))
            $db .= $GLOBALS['database'];
        else if (isset($GLOBALS['databaseConfig']))
            $db .= $GLOBALS['databaseConfig']['database'];
        $db .= defined('SS_DATABASE_SUFFIX') ? SS_DATABASE_SUFFIX : '';

        return $db . '-' . $timestamp . '-backup' . '.' . BACKUP_FILE_TYPE;
    }

    /**
     * Returns a URI path pointing to the backup file
     * @param $filename
     * @return string
     */
    private function getBackupFileURI($filename)
    {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;
    }

    /**
     * Returns a error message, which will be shown in the interface
     * @param $msg
     * @return string
     */
    private function errorMsg($msg)
    {
        return json_encode(
            array(
                'Success' => 0,
                'Message' => $msg
            )
        );
    }

    /**
     * Checks if the commands in the array given are missing in the system
     * @param $cmds
     * @return array|int Returns a list of missing commands
     */
    private function areCommandsMissing($cmds)
    {

        $missingCmds = array();

        foreach ($cmds as $cmd) {
            if (!self::isCommandExisting($cmd)) {
                //echo 'Backup not possible. Required command ' . $cmd . ' is missing!';
                array_push($missingCmds, $cmd);
            }
        }
        return empty($missingCmds) ? 0 : $missingCmds;
    }

    /**
     * Checks if the given command is missing in the system
     * @param $cmd
     * @return bool
     */
    private function isCommandExisting($cmd)
    {
        $returnVal = shell_exec(sprintf("which %s", escapeshellarg($cmd)));
        return !empty($returnVal);
    }

}