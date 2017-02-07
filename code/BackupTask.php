<?php
/**
 * A task that can back up the database and assets and send them to Google Drive.
 *
 * @package backup
 */

// Define the root of sspak package
define('PACKAGE_ROOT', BASE_PATH . '/vendor/silverstripe/sspak/');


class BackupTask extends BuildTask {

    protected $title = 'SilverStripe Backup task';

    protected $description = 'Creates a backup.';

    protected $enabled = true;

    // File type of the backup file, must be either tgz, tar.gz, sspan
    public static $BACKUP_FILE_TYPE = 'tgz';

    // Mime file type of the backup file
    public static $BACKUP_MIME = 'application/tgz';

    // URL to backup download interface
    public static $BACKUP_GET_URL = 'backuper/get-backup';

    /*
     * Required commands for SSPak according to
     * https://github.com/silverstripe/sspak#how-it-works
    */
    public static $REQUIRED_COMMANDS = array('mysql', 'mysqldump', 'tar', 'gzip', 'sudo');


    function run($request) {

        $args = $request['args'];

        $backupDB = false;
        $backupAssets = false;
        $gDriveUpload = false;


        // Check args if we should backup DB and/or assets, and transfer to Google Drive
        if(count($args) > 0) {
            for($i=0; $i < count($args); $i++) {
                switch ($args[$i]) {
                    case '--db':
                        $backupDB = true;
                        break;
                    case '--assets':
                        $backupAssets = true;
                        break;
                    case '--gdrive':
                        $gDriveUpload = true;
                        break;
                }
            }
        }

        // Delete old status file
        $statusFile = self::getBackupStatusFileURI();
        if(file_exists($statusFile)) {
            unlink($statusFile);
        }

        // Run backup method
        $status = self::createBackup($backupDB, $backupAssets, $gDriveUpload);

        // Print to console
        //printf("%s\n", $status);

        // Write json encoded output to status file
        file_put_contents(self::getBackupStatusFileURI(), $status);
    }


    /**
     * Gets a dump of the database and archives it together with the assets by using the ssPak tool.
     * @param SS_HTTPRequest $request
     * @return string
     */
    public function createBackup($backupDB, $backupAssets, $transferBackup)
    {
        // Check for missing commands
        if ($cmds = self::areCommandsMissing()) {
            return sprintf(_t('BackupActionController.BACKUP_CMDS_MISSING'), join(' ', $cmds));
        }

        $timestamp = date('YmdHis');

        // Get a proper formatted backup filename using the current timestamp
        $filename = self::getBackupFilename($timestamp);

        // Get file path to backup directory
        $fileURI = self::getBackupFileURI($filename);


        // Check for missing backup option
        if (!$backupDB && !$backupAssets) {
            return _t('BackupActionController.BACKUP_OPTION_MISSING');
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
            return _t('BackupActionController.BACKUP_FAILED') . ' ' . $e->getMessage();
        }

        $resultInfo = array(
            'Success' => 1,
            'Message' => _t('BackupActionController.BACKUP_COMPLETE'),
            'FilePath' => $fileURI,
            'DownloadLink' => self::getBackupDownloadLink($filename),
            'Timestamp' => intval($timestamp)
        );

        if ($transferBackup) {
            echo _t('BackupActionController.GDRIVE_UPLOADING') . PHP_EOL;

            $handler = new GDriveHandler();
            $gDriveUploadStatus = $handler->uploadBackupGDrive($fileURI, $filename);

            $statusMsg = $gDriveUploadStatus ?
                _t('BackupActionController.UPLOADED') : _t('BackupActionController.NOT_UPLOADED');

            $resultInfo['GDriveUploadStatus'] = array(
                'Success' => $gDriveUploadStatus ? 1 : 0,
                'Message' => _t('BackupActionController.GDRIVE_STATUS') . ' ' . $statusMsg
            );
        }


        return json_encode($resultInfo);
    }

    /**
     * Returns a valid filename of a backup based on a timestamp
     * @param $timestamp
     * @return string
     */
    public static function getBackupFilename($timestamp)
    {
        // Get DB name // TODO: Use different parameter for backup names?
        $db = self::getDatabaseName();

        return $db . '-' . $timestamp . '-backup' . '.' . self::$BACKUP_FILE_TYPE;
    }

    /**
     * Get the project's database name
     * @return string
     */
    public static function getDatabaseName() {
        $db = defined('SS_DATABASE_PREFIX') ? SS_DATABASE_PREFIX : '';
        if (isset($GLOBALS['database']))
            $db .= $GLOBALS['database'];
        else if (isset($GLOBALS['databaseConfig']))
            $db .= $GLOBALS['databaseConfig']['database'];
        $db .= defined('SS_DATABASE_SUFFIX') ? SS_DATABASE_SUFFIX : '';

        return $db;
    }

    /**
     * Returns a URI path pointing to the backup file
     * @param $filename
     * @return string
     */
    public static function getBackupFileURI($filename)
    {
        return self::getBackupDir() . DIRECTORY_SEPARATOR . $filename;
    }

    /**
     * Return the path to the backup directory
     * @return string
     */
    public static function getBackupDir() {
        return sys_get_temp_dir();
    }

    /**
     * Return the path to the backup status file
     * @return string
     */
    public static function getBackupStatusFileURI() {
        return self::getBackupDir() . DIRECTORY_SEPARATOR . self::getDatabaseName() .'-backupstatus';
    }

    /**
     * Return the path to the backup task's STDOUT file
     * @return string
     */
    public static function getBackupOutputFileURI() {
        return self::getBackupDir() . DIRECTORY_SEPARATOR . self::getDatabaseName() .'-backupoutput';
    }

    /**
     * Checks if the commands in the array given are missing in the system
     * @param $cmds
     * @return array|int Returns a list of missing commands
     */
    public static function areCommandsMissing()
    {
        $cmds = self::$REQUIRED_COMMANDS;
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
    private static function isCommandExisting($cmd)
    {
        $returnVal = shell_exec(sprintf("which %s", escapeshellarg($cmd)));
        return !empty($returnVal);
    }

    /**
     * Returns a download link to the backup file
     * @param $filename
     * @return string
     */
    public static function getBackupDownloadLink($filename){
        return self::$BACKUP_GET_URL . DIRECTORY_SEPARATOR . $filename;
    }

    /**
     * Returns a error message, which will be shown in the interface
     * @param $msg
     * @return string
     */
    public static function errorMsg($msg)
    {
        return json_encode(
            array(
                'Success' => 0,
                'Message' => $msg
            )
        );
    }

    /**
     * Returns a success message, which will be shown in the interface
     * @param $msg
     * @return string
     */
    public static function successMsg($msg)
    {
        return json_encode(
            array(
                'Success' => 1,
                'Message' => $msg
            )
        );
    }

    /**
     * Check if the given backup filename is valid
     * @param $filename
     * @return bool
     */
    public static function isBackupFilenameValid($filename){

        // Check if filename starts with db name
        $db = self::getDatabaseName();
        $startsWithDb = strpos($filename, $db) === 0;

        // Check if filename end with correct extension
        $ext = '.' . self::$BACKUP_FILE_TYPE;
        $endsWithExt = substr($filename, -strlen($ext)) === $ext;

        return $startsWithDb && $endsWithExt;
    }

}
