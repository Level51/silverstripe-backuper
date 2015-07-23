<?php
/**
 * Created by PhpStorm.
 * User: Dipl.-Ing. (FH) Julian Scheuchenzuber M.Eng. <js@querdenker-design.com>
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
        $db .= $GLOBALS['database'];
        $db .= defined('SS_DATABASE_SUFFIX') ? SS_DATABASE_SUFFIX : '';

        // Generate a database dump
        $dump = new IMysqldump\Mysqldump($db, SS_DATABASE_USERNAME, SS_DATABASE_PASSWORD);
        $dump->start(sys_get_temp_dir() . DIRECTORY_SEPARATOR . $GLOBALS['database'] . '-dump.sql');

        // Archive assets together with dump
        $arch = new FlxZipArchive();
        $tmpName = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $GLOBALS['database'] . '-backup.zip';

        // Check if file exists already and delete in case
        if(is_file($tmpName))
            unlink($tmpName);

        $arch->open($tmpName, ZIPARCHIVE::CREATE);
        $arch->addFile(sys_get_temp_dir() . DIRECTORY_SEPARATOR . $GLOBALS['database'] . '-dump.sql', $GLOBALS['database'] . '-dump.sql');
        $arch->addDir(ASSETS_PATH, 'assets');
        $arch->close();

        // Return archive as download
        header("Content-type: application/zip");
        header("Content-Disposition: attachment; filename=\"" . $GLOBALS['database'] . '-backup.zip' . "\"");
        header("Content-Length: " . filesize($tmpName));
        return readfile($tmpName);
    }
}