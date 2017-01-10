<?php
/**
 * A task that can restore the database and assets from a local or GDrive dump file..
 *
 * @package backup
 */

class RestoreTask extends BuildTask
{

    protected $title = 'SilverStripe restore task';

    protected $description = 'Restores a backup.';

    protected $enabled = true;


    function run($request)
    {
        $args = $request['args'];

        $uploadRestoreMode = false;
        $gDriveRestoreMode = false;
        $gDriveFileId = null;

        // Check args if we should backup DB and/or assets, and transfer to Google Drive
        if(count($args) > 0) {
            for($i=0; $i < count($args); $i++) {
                switch ($args[$i]) {
                    case '--upload':
                        $uploadRestoreMode = true;
                        break;
                    case '--gdrive':
                        $gDriveRestoreMode = true;

                        if(count($args) == 2) {
                            $gDriveFileId = $args[1];
                        } else {
                            echo 'GDrive FileId is missing!';
                        }

                        break;
                }
            }
        }

        // Delete old status file
        $statusFile = self::getRestoreStatusFileURI();
        if(file_exists($statusFile)) {
            unlink($statusFile);
        }

        // Run restore method
        $status = self::restoreBackup($uploadRestoreMode, $gDriveRestoreMode, $gDriveFileId);

        // Print status to console
        //printf("%s\n", $status);

        // Write json encoded output to status file
        file_put_contents(self::getRestoreStatusFileURI(), $status);
    }

    /**
     * Restores a backup by using Sspak.
     * @param $fileRestoreMode
     * @param $gDriveRestoreMode
     * @return string
     */
    public static function restoreBackup($uploadRestoreMode, $gDriveRestoreMode, $gDriveFileId)
    {
        // Check for missing commands
        if ($cmds = BackupTask::areCommandsMissing()) {
            return sprintf(_t('BackupActionController.BACKUP_CMDS_MISSING'), join(' ', $cmds));
        }

        if ($uploadRestoreMode) {

            // Get backup settings
            $backupSettings = BackupAdminSettings::get()->first();

            if (!$backupSettings || !($restoreFile = $backupSettings->RestoreFile()) || !$restoreFile->Name) {
                return BackupTask::errorMsg(_t('BackupActionController.BACKUP_NOT_UPLOADED_YET'));
            }

            // Get path to upload in assets dir
            $uploadFilePath = BASE_PATH . DIRECTORY_SEPARATOR . $restoreFile->Filename;

            // Check if a backup file still exists at disk
            if (!file_exists($uploadFilePath)) {
                return BackupTask::errorMsg(_t('BackupActionController.BACKUP_DOES_NOT_EXIST'));
            }

            // Get filename and create a new path to move file into backup dir
            $restoreFilename = $restoreFile->Name;
            $restoreFilePath = BackupTask::getBackupFileURI($restoreFilename);

            // Move file from assets into backup dir (usually tmp)
            rename($uploadFilePath, $restoreFilePath);

        } else if ($gDriveRestoreMode) {

            if (!$gDriveFileId)
                return BackupTask::errorMsg(_t('BackupActionController.NO_GDRIVE_FILEID'));

            $handler = new GDriveHandler();

            if (!$client = $handler->getGoogleClient()) {
                return BackupTask::errorMsg(_t('BackupActionController.COULD_NOT_CREATE_GDRIVE'));
            }

            if (!$handler->isGDriveAuthenticated()) {
                return BackupTask::errorMsg(_t('BackupActionController.GDRIVE_NOT_AUTHENTICATED'));
            }

            $service = new Google_Service_Drive($client);

            $restoreFilename = BackupTask::getBackupFilename($gDriveFileId);
            $restoreFilePath = BackupTask::getBackupFileURI($restoreFilename);

            echo _t('RestoreTask.DOWNLOADING_GDRIVE_DUMP') . PHP_EOL;

            try {
                $content = $service->files->get($gDriveFileId, array(
                    'alt' => 'media'));

                file_put_contents($restoreFilePath, $content);

            } catch (Exception $e){
                echo 'Error while downloading from GDrive: ' . $e;
            }

            echo _t('RestoreTask.DOWNLOAD_GDRIVE_FINISHED') . PHP_EOL;

            //return 1;
        } else {
            return BackupTask::errorMsg(_t('BackupActionController.NOT_A_VALID_RESTORE_MODE'));
        }

        echo _t('RestoreTask.RESTORING') . PHP_EOL;

        // Create a SsPak instance
        $ssPak = new SSPak(new Executor());

        // Create command line args used for ssPak save command
        $args = new Args(
            array(
                '',
                'load', // Command
                $restoreFilePath, // Backup file path,
                BASE_PATH // Silverstripe root directory
            )
        );

        try {
            // Starting backup
            $ssPak->load($args);
        } catch (Exception $e) {
            return BackupTask::errorMsg(_t('BackupActionController.RESTORE_FAILED') . ' ' . $e->getMessage());
        }

        // Delete uploaded backup file after a successful restore
        unlink($restoreFilePath);

        return json_encode(
            array(
                'Success' => 1,
                'Message' => _t('BackupActionController.RESTORE_COMPLETE')
            )
        );
    }

    /**
     * Return the path to the restore status file
     * @return string
     */
    public static function getRestoreStatusFileURI() {
        return BackupTask::getBackupDir() . DIRECTORY_SEPARATOR . BackupTask::getDatabaseName() .'-restorestatus';
    }

    /**
     * Return the path to the restore task's STDOUT file
     * @return string
     */
    public static function getRestoreOutputFileURI() {
        return BackupTask::getBackupDir() . DIRECTORY_SEPARATOR . BackupTask::getDatabaseName() .'-restoreoutput';
    }

}
