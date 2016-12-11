<?php
// Define the root of the project
define('PROJECT_DIR' , dirname(__DIR__) . '/../');

// Define the root of sspak package
define('PACKAGE_ROOT' , PROJECT_DIR . 'vendor/silverstripe/sspak/');

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

    private static $allowed_actions = array('createBackup');

    private static $url_handlers = array(
        'create-backup' => 'createBackup'
    );

    /**
     * Gets a dump of the database and archives it together with the assets.
     * @param $data
     * @param $form
     */
    public function createBackup(SS_HTTPRequest $request) {

        // Check for missing commands
        if(!self::checkCommands(REQUIRED_COMMANDS)) {
            return;
        }

        // Get DB name
        $db = defined('SS_DATABASE_PREFIX') ? SS_DATABASE_PREFIX : '';
        if(isset($GLOBALS['database']))
            $db .= $GLOBALS['database'];
        else if(isset($GLOBALS['databaseConfig']))
            $db .= $GLOBALS['databaseConfig']['database'];
        $db .= defined('SS_DATABASE_SUFFIX') ? SS_DATABASE_SUFFIX : '';

        // Create a SsPak instance
        $ssPak = new SSPak(new Executor());

        $filename = $db . '-' . date('YmdHis') . '-backup.zip';
        $tmpName = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;

        // Create command line args used for ssPak save command
        $args = new Args(array('', 'save', PROJECT_DIR, $tmpName));

        try {
            // Starting backup
            $ssPak->save($args);
        } catch (Exception $e) {
            echo 'Backup failed: ',  $e->getMessage(), "\n";
            return;
        }

        // Return archive as download
        header("Content-type: application/zip");
        header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
        header("Content-Length: " . filesize($tmpName));
        return readfile($tmpName);
    }

    /*
     * Checks if the commands in the array given are existing in the system
     */
    private function checkCommands($cmds) {

        foreach($cmds as $cmd) {
            if (!self::isCommandExisting($cmd)) {
                echo 'Backup not possible. Required command ' . $cmd . ' is missing!';
                return 0;
            }
        }
        return 1;
    }

    private function isCommandExisting($cmd) {
        $returnVal = shell_exec(sprintf("which %s", escapeshellarg($cmd)));
        return !empty($returnVal);
    }
}