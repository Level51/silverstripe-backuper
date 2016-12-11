<?php
/**
 * Created by PhpStorm.
 * User: Julian Scheuchenzuber <js@lvl51.de>
 * Date: 24.05.2015 01:43
 */
use Ifsnop\Mysqldump as IMysqldump;

// Define the root of the project
define('PROJECT_DIR' , dirname(__DIR__) . '/../');

// Define the root of sspak package
define('PACKAGE_ROOT' , PROJECT_DIR . 'vendor/silverstripe/sspak/');

// STDIN is a pipe, which reads from the child process
const STDIN = array("pipe", "r");

// STDERR is a pipe, which writes to the child process
const STDERR = array("pipe", "w");

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

        // Get DB name
        $db = defined('SS_DATABASE_PREFIX') ? SS_DATABASE_PREFIX : '';
        if(isset($GLOBALS['database']))
            $db .= $GLOBALS['database'];
        else if(isset($GLOBALS['databaseConfig']))
            $db .= $GLOBALS['databaseConfig']['database'];
        $db .= defined('SS_DATABASE_SUFFIX') ? SS_DATABASE_SUFFIX : '';


        // Create a SsPak instance
        $ssPak = new SSPak(new Executor());

        $tmpName = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $db . '-' . date('YmdHis') . '-backup.zip';

        $args = new Args(array('', 'save', PROJECT_DIR, $tmpName));
        try {
            $ssPak->save($args);
        } catch (Exception $e) {
            echo 'Backup failed: ',  $e->getMessage(), "\n";
        }

        // Return archive as download
        header("Content-type: application/zip");
        header("Content-Disposition: attachment; filename=\"" . $tmpName . "\"");
        header("Content-Length: " . filesize($tmpName));
        return readfile($tmpName);
    }
}