<?php
/**
 * Created by PhpStorm.
 * User: Julian Scheuchenzuber <js@lvl51.de>
 * Date: 24.05.2015 01:43
 */
use Ifsnop\Mysqldump as IMysqldump;

class BackupActionController extends Controller {
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

        // Generate a database dump
        $dump = new IMysqldump\Mysqldump($db, SS_DATABASE_USERNAME, SS_DATABASE_PASSWORD);
        $dump->start(sys_get_temp_dir() . DIRECTORY_SEPARATOR . $db . '-dump.sql');

        // Archive assets together with dump
        $arch = new FlxZipArchive();
        $tmpName = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $db . '-' . date('YmdHis') . '-backup.zip';

        // Create archive: add dump and assets
        $arch->open($tmpName, ZIPARCHIVE::CREATE);
        $arch->addFile(sys_get_temp_dir() . DIRECTORY_SEPARATOR . $db . '-dump.sql', $db . '-dump.sql');
        $arch->addDir(ASSETS_PATH, 'assets');
        $arch->close();

        // Return archive as download
        header("Content-type: application/zip");
        header("Content-Disposition: attachment; filename=\"" . $db . '-backup.zip' . "\"");
        header("Content-Length: " . filesize($tmpName));
        return readfile($tmpName);
    }
}