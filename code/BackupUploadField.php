<?php
/**
 * Created by PhpStorm.
 * User: Dipl.-Ing. (FH) Julian Scheuchenzuber M.Eng. <js@querdenker-design.com>
 * Date: 24.05.2015 20:25
 */

class BackupUploadField extends UploadField {
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
        $arch->open($firstFile);
        $arch->extractTo(BASE_PATH);
        $arch->close();

        // Fetch binary of mysql
        $binPath = SiteConfig::current_site_config()->MySQLExe;

        // Load database dump
        exec($binPath . ' -u ' . SS_DATABASE_USERNAME . ' -p' . SS_DATABASE_PASSWORD . ' ' . $GLOBALS['database'] . ' < ' . BASE_PATH . DIRECTORY_SEPARATOR . $GLOBALS['database'] . '-dump.sql');

        // Format response with json
        $response = new SS_HTTPResponse(Convert::raw2json(array($return)));
        $response->addHeader('Content-Type', 'text/plain');
        if (!empty($return['error'])) $response->setStatusCode(403);
        return $response;
    }
}