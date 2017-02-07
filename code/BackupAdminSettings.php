<?php

// Max backup file size given in MB
const BACKUP_MAX_UPLOAD_FILE_SIZE = 2048; // in MB

const BACKUP_UPLOAD_FILE_EXTENSIONS = array('tar', 'tar.gz', 'tgz', 'zip', 'sspak');

const BACKUP_GET_URL = 'backuper/get-backup';

class BackupAdminSettings extends DataObject implements TemplateGlobalProvider
{
    /**
     * Set DB fields added for this class
     * @var array
     */
    private static $db = array(
        'BackupDatabaseEnabled' => 'Boolean',
        'BackupAssetsEnabled' => 'Boolean',
        'BackupTransferEnabled' => 'Boolean',
        'GDriveClientId' => 'Varchar(255)',
        'GDriveClientSecret' => 'Varchar(255)',
    );

    private static $has_one = array(
        'RestoreFile' => 'File'
    );

    /**
     * Returns the CMS fields for managing the backup settings.
     * @return FieldList
     */
    public function getCMSFields()
    {
        // Create an empty FieldList for pushing fields tabs into
        $fields = new FieldList(new TabSet('Root'));

        $fields->addFieldsToTab(
            _t('BackupAdminSettings.BACKUP_TAB', 'Root.Backup'),
            array(
                new HeaderField('backup-heading', _t('BackupAdminSettings.BACKUP_HEADING')),
                new LiteralField('backup-explanation', '<p>' . _t('BackupAdminSettings.BACKUP_TEXT') . '</p>'),
                new CheckboxField('BackupDatabaseEnabled', _t('BackupAdminSettings.BACKUP_DB')),
                new CheckboxField('BackupAssetsEnabled', _t('BackupAdminSettings.BACKUP_ASSETS')),
                new CheckboxField('BackupTransferEnabled', _t('BackupAdminSettings.BACKUP_TRANSFER')),
                new LiteralField('backup-gdrive-creds', '<p>' . _t('BackupAdminSettings.BACKUP_GDRIVE_CREDS_TEXT') . '</p>'),
                new FormAction (
                // doAction has to be a defined controller member
                    $action = "BackupNow",
                    $title = _t('BackupAdminSettings.BACKUP_NOW')
                ),
                new HeaderField('backup-latest-heading', _t('BackupAdminSettings.BACKUP_LATEST'), 4),
                new LiteralField('backup-latest-links', self::getBackupsLinks()),
            )
        );

        $gDriveHandler = new GDriveHandler();

        $client = $gDriveHandler->getGoogleClient();

        $gdrivelinks = '<div id="backup-latest-links">';

        if ($client && $gDriveHandler->isGDriveAuthenticated()) {
            $authMsg = _t('BackupAdminSettings.AUTHENTICATED') ;
            $backups = $gDriveHandler->getGDriveBackups($client);

            if(!empty($backups)) {
                foreach ($backups as $backup) {
                    // Check if valid filename
                    if (!BackupTask::isBackupFilenameValid($backup['Filename']) ) {
                        continue;
                    }

                    $gdrivelinks .= sprintf(
                        '<p><a href="javascript:void(0)" onclick="restoreGDriveBackup(\'%s\', \'%s\')">%s</a></p>' . "\n",
                        $backup['Id'],
                        $backup['Filename'],
                        $backup['Filename']
                    );
                }
            }
        } else {
            $authMsg = _t('BackupAdminSettings.NOT_AUTHENTICATED');
        }
        $gdrivelinks .= '</div>';


        $fields->addFieldsToTab(
            _t('BackupAdminSettings.RESTORE_TAB', 'Root.Restore'),
            array(
                new HeaderField('restore-heading', _t('BackupAdminSettings.RESTORE_HEADING')),
                new LiteralField('restore-explanation', '<p>' . _t('BackupAdminSettings.RESTORE_TEXT') . '</p>'),
                $uploadField = new UploadField(
                    $name = 'RestoreFile',
                    $title = _t('BackupAdminSettings.RESTORE_UPLOAD_FIELD')
                ),
                new FormAction (
                // doAction has to be a defined controller member
                    $action = "RestoreNow",
                    $title = _t('BackupAdminSettings.RESTORE_NOW_BTN')
                ),
                new HeaderField('gdrive-heading', _t('BackupAdminSettings.RESTORE_GDRIVE_HEADING')),
                new LiteralField('gdrive-explanation', '<p>' . _t('BackupAdminSettings.RESTORE_GDRIVE_TEXT') . '</p>'),
                new HeaderField('gdrive-latest-heading', _t('BackupAdminSettings.RESTORE_GDRIVE_LIST_HEADING'), 4),
                new LiteralField('gdrive-latest-links', $gdrivelinks)
            )
        );

        if (!$client || !$gDriveHandler->isGDriveAuthenticated($client)) {
            $fields->addFieldToTab(
                _t('BackupAdminSettings.RESTORE_TAB', 'Root.Restore'),
                new LiteralField('auth-explanation', '<p>' . $authMsg . '</p>'));
        }

        // Set allowed file extensions
        $uploadField->setAllowedExtensions(BACKUP_UPLOAD_FILE_EXTENSIONS); //TODO

        // Set max file size
        $size = BACKUP_MAX_UPLOAD_FILE_SIZE * 1024 * 1024; // in bytes
        $uploadField->getValidator()->setAllowedMaxFileSize($size);

        // Disable attachment of existing files
        $uploadField->setCanAttachExisting(false);

        // Set custom upload folder (stored in assets folder!)
        $uploadField->setFolderName('tmp');

        $fields->addFieldsToTab(
            _t('BackupAdminSettings.APIKEYS_TAB', 'Root.ApiKeys'),
            array(
                new TextField('GDriveClientId', _t('BackupAdminSettings.API_GDRIVE_CLIENT_ID')),
                new TextField('GDriveClientSecret', _t('BackupAdminSettings.API_GDRIVE_CLIENT_SECRET')),
            )
        );

        if (!($client && $gDriveHandler->isGDriveAuthenticated())) {
            $fields->addFieldsToTab(
                _t('BackupAdminSettings.APIKEYS_TAB', 'Root.ApiKeys'),
                array(
                    $authNowBtn = new FormAction (
                        $action = "AuthenticateNow",
                        $title = _t('BackupAdminSettings.AUTHENTICATE_BTN')
                    ),
                )
            );
        } else {
            $fields->addFieldsToTab(
                _t('BackupAdminSettings.APIKEYS_TAB', 'Root.ApiKeys'),
                array(
                    $authNowBtn = new FormAction (
                        $action = "LogoutNow",
                        $title = _t('BackupAdminSettings.LOGOUT_BTN')
                    ),
                )
            );
        }


        $fields->addFieldToTab(
            _t('BackupAdminSettings.APIKEYS_TAB', 'Root.ApiKeys'),
            new LiteralField('auth-explanation', '<p>' . $authMsg . '</p>'));


        return $fields;
    }

    /**
     *	Gets download links to the latest backups
     *
     *	@return String
     */
    private function getBackupsLinks(){
        $links = '';

        $dir = BackupTask::getBackupDir();
        $files = scandir($dir);
        foreach($files as $file) {

            // Check if valid filename
            if (!BackupTask::isBackupFilenameValid($file) ) {
                continue;
            }

            $links .= sprintf(
                '<p><a href="%s">%s</a></p>'."\n",
                BackupTask::getBackupDownloadLink($file),
                $file
            );
        }
        if ( $links == '' ) {
            $links = '<p>' . _t('BackupAdminSettings.NO_BACKUPS') . '</p>';
        }
        return $links;
    }

    /**
     * Add $BackupAdminSettings to all SSViewers
     * Exposes this DataObject's propertise to global scope
     * @return array
     */
    public static function get_template_global_variables()
    {
        return array(
            'BackupAdminSettings' => 'current_config',
        );
    }

    /**
     * Get the actions that are sent to the CMS.
     *
     * @return Fieldset
     */
    public function getCMSActions()
    {
        // Create a FieldList with a save action
        $actionsFieldList = new FieldList();

        // Create an action to save the form
        $saveAction = FormAction::create('save_settings', _t('CMSMain.SAVE', 'Save'))->addExtraClass('ss-ui-action-constructive')->setAttribute('data-icon', 'accept');

        // Push save action onto actionsFieldList
        $actionsFieldList->push($saveAction);

        return $actionsFieldList;
    }

    /**
     * Get the current sites BackupAdminSettings, and creates a new one
     * through {@link make_current_config()} if none is found.
     *
     * @return BackupAdminSettings
     */
    static public function current_config()
    {

        // Add button functionality
        Requirements::javascript(basename(dirname(__DIR__)) . '/javascript/buttons.js');

        // Check if a DataObject already exists
        if ($config = DataObject::get_one('BackupAdminSettings')) {
            return $config;
        }

        // Create a DataObject
        return self::make_current_config();
    }

    /**
     * Create BackupAdminSettings with defaults from language file.
     *
     * @return BackupAdminSettings
     */
    static public function make_current_config()
    {
        // Create the BackupAdminSettings DataObject
        $config = BackupAdminSettings::create();
        // Build the BackupAdminSettings table
        $config->write();
        return $config;
    }
}
