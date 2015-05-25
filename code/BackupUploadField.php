<?php
/**
 * Created by PhpStorm.
 * User: Dipl.-Ing. (FH) Julian Scheuchenzuber M.Eng. <js@querdenker-design.com>
 * Date: 24.05.2015 20:25
 */

class BackupUploadField extends UploadField {
    /**
     * @var array
     */
    private static $allowed_actions = array(
        'upload',
        'attach',
        'handleItem',
        'handleSelect',
        'fileexists'
    );

    /**
     * @var array
     */
    private static $url_handlers = array(
        'item/$ID' => 'handleItem',
        'select' => 'handleSelect',
        '$Action!' => '$Action',
    );

    public function upload(SS_HTTPRequest $request) {
        // Protect against CSRF on destructive action
        $token = $this->getForm()->getSecurityToken();
        if(!$token->checkRequest($request)) return $this->httpError(400);

        // Get form details
        $name = $this->getName();
        $postVars = $request->postVar($name);

        // Fetch the temporary file
        $uploadedFiles = $this->extractUploadedFileData($postVars);
        $firstFile = reset($uploadedFiles);

        // Unzip archive to root
        $arch = new ZipArchive();
        $arch->open($firstFile['tmp_name']);
        $arch->extractTo(sys_get_temp_dir());
        $arch->close();

        // Remove assets and copy backup assets
        exec('rm -r ..' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . '*');
        exec('mv ' . sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . '* ..' . DIRECTORY_SEPARATOR . 'assets');
        exec('php ..' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'cli-script.php dev/tasks/FilesystemSyncTask');

        // Fetch binary of mysql
        $binPath = SiteConfig::current_site_config()->MySQLExe;

        // Load database dump
        exec($binPath . ' -u ' . SS_DATABASE_USERNAME . ' -p' . SS_DATABASE_PASSWORD . ' ' . $GLOBALS['database'] . ' < ' . sys_get_temp_dir() . DIRECTORY_SEPARATOR . $GLOBALS['database'] . '-dump.sql');

        // Format response with json
        $response = new SS_HTTPResponse(Convert::raw2json(array('message' => 'Loaded backup')));
        $response->addHeader('Content-Type', 'text/plain');
        if (!empty($return['error'])) $response->setStatusCode(403);
        return $response;
    }
}