<?php
require_once realpath(BASE_PATH . '/vendor/google/apiclient/src/Google/autoload.php');


/* Setting STDIN and STDERR for SSPak commands */
// STDIN is a pipe, which reads from the child process
const STDIN = array("pipe", "r");

// STDERR is a pipe, which writes to the child process
const STDERR = array("pipe", "w");


class BackupActionController extends Controller
{

    private static $host = 'localhost';

    private static $allowed_actions = array(
        'createBackup' => 'ADMIN',
        'restoreBackup' => 'ADMIN',
        'getBackup' => 'ADMIN',
        'authenticateGDrive' => 'ADMIN',
        'getStatusUpdate' => 'ADMIN'
    );

    private static $url_handlers = array(
        'create-backup' => 'createBackup',
        'restore-upload' => 'restoreBackup',
        'restore-gdrive/$GDriveFileId' => 'restoreBackup',
        'get-backup/$Filename' => 'getBackup',
        'authenticate-gdrive' => 'authenticateGDrive',
        'backup-status' => 'getStatusUpdate',
        'restore-status' => 'getStatusUpdate',
        'backup-output' => 'getStatusUpdate',
        'restore-output' => 'getStatusUpdate'
    );

    /**
     * Gets a dump of the database and archives it together with the assets by using the ssPak tool.
     * @param SS_HTTPRequest $request
     * @return string
     */
    public function createBackup(SS_HTTPRequest $request)
    {
        // Check for missing commands
        if ($cmds = BackupTask::areCommandsMissing()) {
            return self::errorMsg(sprintf(_t('BackupActionController.BACKUP_CMDS_MISSING'), join(' ', $cmds)));
        }

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

        // Create a cli command to run the BackupTask
        $command = sprintf(
            'php %s/framework/cli-script.php %s %s %s %s',
            BASE_PATH,
            'dev/tasks/BackupTask',
            $backupDB ? '--db' : '',
            $backupAssets ? '--assets' : '',
            $transferBackup ? '--gdrive' : ''
        );

        $outputFile = BackupTask::getBackupOutputFileURI();

        // Run task in background with STDOUT and STDERR directed to a file.
        // nohup and setsid flags allows the process to continue even though the process that launched
        // may terminate first.
        exec('bash -c "exec nohup setsid ' . $command . ' > "' . $outputFile . '" 2>&1 &"');

        // Preparing info that has to be returned
        $resultInfo = array(
            'Success' => 1,
            'Message' => _t('BackupActionController.STARTED_BACKUP_TASK')
        );

        return json_encode($resultInfo);
    }

    /**
     * Provides a file download for a backup file
     * @param SS_HTTPRequest $request
     * @return int
     */
    public function getBackup(SS_HTTPRequest $request)
    {
        $request->param('Filename');
        if (!$request->param('Filename'))
            return 0;

        $filename = $request->param('Filename') . '.' . BackupTask::$BACKUP_FILE_TYPE;
        $fileURI = BackupTask::getBackupFileURI($filename);

        // Return archive as download
        header("Content-type: " . BackupTask::$BACKUP_MIME);
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
        if ($cmds = BackupTask::areCommandsMissing()) {
            return self::errorMsg(sprintf(_t('BackupActionController.BACKUP_CMDS_MISSING'), join(' ', $cmds)));
        }

        // Get request url
        $url = $request['url'];

        $uploadRestoreMode = strpos($url, 'restore-upload') !== false;
        $gDriveRestoreMode = strpos($url, 'restore-gdrive') !== false;

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

        } else if ($gDriveRestoreMode) {

            if (!($gDriveFileId = $request->param('GDriveFileId')))
                return self::errorMsg(_t('BackupActionController.NO_GDRIVE_FILEID'));

            $handler = new GDriveHandler();

            if (!$client = $handler->getGoogleClient()) {
                return self::errorMsg(_t('BackupActionController.COULD_NOT_CREATE_GDRIVE'));
            }

            if (!$handler->isGDriveAuthenticated()) {
                return self::errorMsg(_t('BackupActionController.GDRIVE_NOT_AUTHENTICATED'));
            }

        } else {
            return self::errorMsg(_t('BackupActionController.NOT_A_VALID_RESTORE_MODE'));
        }

        // Create a cli command to run the RestoreTask
        $command = sprintf(
            'php %s/framework/cli-script.php %s %s %s %s',
            BASE_PATH,
            'dev/tasks/RestoreTask',
            $uploadRestoreMode ? '--upload' : '',
            $gDriveRestoreMode ? '--gdrive' : '',
            $gDriveRestoreMode ? $gDriveFileId : ''
        );

        // Run task in background with STDOUT and STDERR directed to a file.
        // nohup and setsid flags allows the process to continue even though the process that launched
        // may terminate first.
        exec('bash -c "exec nohup setsid ' . $command . ' > "' . RestoreTask::getRestoreOutputFileURI() . '" 2>&1 &"');

        // Preparing info that has to be returned
        $resultInfo = array(
            'Success' => 1,
            'Message' => _t('BackupActionController.STARTED_RESTORE_TASK')
        );

        return json_encode($resultInfo);
    }

    /**
     * Endpoint to authenticate the backend to client's Google account
     * @param SS_HTTPRequest $request
     * @return bool|string
     */
    public function authenticateGDrive(SS_HTTPRequest $request)
    {
        $handler = new GDriveHandler();

        if (!$client = $handler->getGoogleClient()) {
            return _t('BackupActionController.COULD_NOT_CREATE_GDRIVE');
        }

        if (isset($_REQUEST['logout'])) {
            $client->revokeToken();

            // Remove token file
            unlink(GDriveHandler::getAccessTokenFileURI());

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

            GDriveHandler::saveAccessToken($token);

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
     * Get the current status of a running backup or restore task
     * @param SS_HTTPRequest $request
     * @return string
     */
    public function getStatusUpdate(SS_HTTPRequest $request)
    {
        // Get request url
        $url = $request['url'];

        if (strpos($url, 'backup-status') !== false) {
            $statusFile = BackupTask::getBackupStatusFileURI();
        } else if (strpos($url, 'restore-status') !== false) {
            $statusFile = RestoreTask::getRestoreStatusFileURI();
        } else if (strpos($url, 'backup-output') !== false) {
            $statusFile = BackupTask::getBackupOutputFileURI();
        } else if (strpos($url, 'restore-output') !== false) {
            $statusFile = RestoreTask::getRestoreOutputFileURI();
        } else {
            return 'No mode set!';
        }

        if(file_exists($statusFile)) {
            return file_get_contents($statusFile);
        } else {
            return 'Nothing to show.';
        }
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

}