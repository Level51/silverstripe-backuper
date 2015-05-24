<?php
/**
 * Created by PhpStorm.
 * User: Dipl.-Ing. (FH) Julian Scheuchenzuber M.Eng. <js@querdenker-design.com>
 * Date: 24.05.2015 01:43
 */

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
        // Fetch binary of mysqldump tool
        $binPath = SiteConfig::current_site_config()->MySQLDumpExe;

        // Execute mysqldump and save output to tmp dir
        exec($binPath . ' -u ' . SS_DATABASE_USERNAME . ' -p' . SS_DATABASE_PASSWORD . ' ' . $GLOBALS['database'] . ' > ' . sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dump.sql');

        // Archive assets together with dump
        $arch = new FlxZipArchive();
        $tmpName = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid() . '-dump.zip';
        $arch->open($tmpName, ZIPARCHIVE::CREATE);
        $arch->addFile(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dump.sql', 'database-dump.sql');
        $arch->addDir(ASSETS_PATH, 'assets');
        $arch->close();

        // Return archive as download
        header("Content-type: application/zip");
        header("Content-Disposition: attachment; filename=\"" . $tmpName . "\"");
        header("Content-Length: " . filesize($tmpName));
        return readfile($tmpName);
    }
}