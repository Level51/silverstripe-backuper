<?php
// Define the root of the Silverstripe project
define('PROJECT_DIR' , dirname(__DIR__) . '/../');

// Define the root of sspak package
define('PACKAGE_ROOT' , PROJECT_DIR . 'vendor/silverstripe/sspak/');

const BACKUP_FILE_TYPE = 'zip';

/* Setting STDIN and STDERR for SSPak commands */
// STDIN is a pipe, which reads from the child process
const STDIN = array("pipe", "r");

// STDERR is a pipe, which writes to the child process
const STDERR = array("pipe", "w");

/*
 * Required commands for SSPak according to
 * https://github.com/silverstripe/sspak#how-it-works
*/
const REQUIRED_COMMANDS = array('mysql', 'mysqldump', 'tar', 'gzip', 'sudo');

class BackupActionController extends Controller {

    private static $host = 'localhost';

    private static $allowed_actions = array(
        'createBackup' => 'ADMIN',
        'restoreBackup' => 'ADMIN',
        'getBackup' => 'ADMIN'
    );

    private static $url_handlers = array(
        'create-backup' => 'createBackup',
        'restore-backup' => 'restoreBackup',
        'get-backup/$Timestamp' => 'getBackup'
    );

    /**
     * Gets a dump of the database and archives it together with the assets.
     * @param $data
     * @param $form
     */
    public function createBackup(SS_HTTPRequest $request) {

        // Check for missing commands
        if($cmds = self::areCommandsMissing(REQUIRED_COMMANDS)) {
            return self::errorMsg('Required commands ' . join(' ', $cmds) . ' are missing!');
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
        if(!$backupDB && !$backupAssets) {
            return self::errorMsg('At least one backup option must be selected!');
        }

        // Create command line args used for ssPak save command
        $args = new Args(
            array(
                '',
                'save', // Command
                PROJECT_DIR, // Silverstripe root directory
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
            return self::errorMsg('Backup failed: ' .  $e->getMessage());
        }

        return json_encode(
            array(
                'Success' => 1,
                'Message' => 'Backup complete!',
                'Timestamp' => intval($timestamp),
            )
        );
    }

    public function getBackup(SS_HTTPRequest $request) {

        if(!$request->param('Timestamp'))
            return 0;

        $timestamp = intval($request->param('Timestamp'));

        $filename = self::getBackupFilename($timestamp);
        $fileURI = self::getBackupFileURI($filename);

        // Return archive as download
        header("Content-type: application/zip");
        header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
        header("Content-Length: " . filesize($fileURI));
        return readfile($fileURI);
        //return $fileURI;
    }

    public function restoreBackup(SS_HTTPRequest $request) {

        // Check for missing commands
        if($cmds = self::areCommandsMissing(REQUIRED_COMMANDS)) {
            return self::errorMsg('Required commands ' . join(' ', $cmds) . ' are missing!');
        }

        // Create a SsPak instance
        $ssPak = new SSPak(new Executor());

        // Get backup settings
        $backupSettings = BackupAdminSettings::get()->first();

        if(!$backupSettings || !($restoreFile = $backupSettings->RestoreFile()) || !$restoreFile->Name){
            return self::errorMsg('Backup file has not been uploaded yet!');
        }

        // Get path to upload in assets dir
        $restoreFileURI = PROJECT_DIR . $restoreFile->Filename;

        // Check if a backup file still exists at disk
        if(!file_exists($restoreFileURI)) {
            return self::errorMsg('Backup file does not exist!');
        }

        // Get filename and create a new path to move file into backup dir
        $restoreFilename = $restoreFile->Name;
        $newRestorefileURI = self::getBackupFileURI($restoreFilename);

        // Move file from assets into backup dir (usually tmp)
        rename($restoreFileURI, $newRestorefileURI);

        // Create command line args used for ssPak save command
        $args = new Args(
            array(
                '',
                'load', // Command
                $newRestorefileURI, // Backup file path,
                PROJECT_DIR // Silverstripe root directory
            )
        );

        try {
            // Starting backup
            $ssPak->load($args);
        } catch (Exception $e) {
            return self::errorMsg('Restore failed: ' .  $e->getMessage());
        }

        // Delete uploaded backup file after a successful restore
        unlink($newRestorefileURI);

        return json_encode(
            array(
                'Success' => 1,
                'Message' => 'Restore complete!'
            )
        );
    }

    /**
     * Returns a filename of a backup based on a timestamp
     */
    private function getBackupFilename($timestamp){
        // Get DB name // TODO: Use different parameter for backup names?
        $db = defined('SS_DATABASE_PREFIX') ? SS_DATABASE_PREFIX : '';
        if(isset($GLOBALS['database']))
            $db .= $GLOBALS['database'];
        else if(isset($GLOBALS['databaseConfig']))
            $db .= $GLOBALS['databaseConfig']['database'];
        $db .= defined('SS_DATABASE_SUFFIX') ? SS_DATABASE_SUFFIX : '';

        return $db . '-' . $timestamp . '-backup' . '.' . BACKUP_FILE_TYPE;
    }

    /**
     * Returns a URI path pointing to the backup file
     */
    private function getBackupFileURI($filename){
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;
    }
    /**
     * Returns a error message, which will be shown in the interface
     */
    private function errorMsg($msg){
        return json_encode(
            array(
                'Success' => 0,
                'Message' => $msg
            )
        );
    }

    /*
     * Checks if the commands in the array given are missing in the system
     * If true, returns a list of missing commands.
     */
    private function areCommandsMissing($cmds) {

        $missingCmds = array();

        foreach($cmds as $cmd) {
            if (!self::isCommandExisting($cmd)) {
                //echo 'Backup not possible. Required command ' . $cmd . ' is missing!';
                array_push($missingCmds, $cmd);
            }
        }
        return empty($missingCmds) ? 0 : $missingCmds;
    }

    private function isCommandExisting($cmd) {
        $returnVal = shell_exec(sprintf("which %s", escapeshellarg($cmd)));
        return !empty($returnVal);
    }
}