<?php

// Max backup file size given in MB
const BACKUP_MAX_UPLOAD_FILE_SIZE = 2048; // in MB

const BACKUP_UPLOAD_FILE_EXTENSIONS = array('tar', 'tar.gz', 'zip', 'sspak');


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

    public function getCMSFields()
    {
        // Create an empty FieldList for pushing fields tabs into
        $fields = new FieldList(new TabSet('Root'));

        $fields->addFieldsToTab(
            'Root.Backup',
            array(
                new HeaderField('backup-heading', 'Backup'),
                new LiteralField('backup-explanation', '<p>This module backs up the database and assets, and optionally copies them to Google Drive.</p>'),
                new CheckboxField('BackupDatabaseEnabled', 'Back up the database?'),
                new CheckboxField('BackupAssetsEnabled', 'Back up the assets folder?'),
                new CheckboxField('BackupTransferEnabled', 'Transfer to Google Drive? *'),
                new LiteralField('backup-ssh', '<p>* Google drive transfer requires a Google OAuth2 client configuration.</p>'),
                new HeaderField('backup-latest-heading', 'Latest backups', 4),
                //new LiteralField('backup-latest-links', $links),
                new FormAction (
                // doAction has to be a defined controller member
                    $action = "BackupNow",
                    $title = "Backup now"
                ),
            )
        );

        $fields->addFieldsToTab(
            'Root.Restore',
            array(
                new HeaderField('restore-heading', 'Restore by file upload'),
                new LiteralField('restore-explanation', '<p>Restore the database and assets from an uploaded backup file.</p>'),
                $uploadField = new UploadField(
                    $name = 'RestoreFile',
                    $title = 'Upload and restore backup file'
                ),
                new FormAction (
                // doAction has to be a defined controller member
                    $action = "RestoreNow",
                    $title = "Restore now"
                ),
                new HeaderField('gdrive-heading', 'Restore by Google Drive'),
                new LiteralField('gdrive-explanation', '<p>This module restores the database and assets from an backup stored at Google Drive.</p>'),
                new HeaderField('gdrive-latest-heading', 'Latest backups', 4),
                //new LiteralField('gdrive-latest-links', $gdrivelinks),
            )
        );

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
            'Root.ApiKeys',
            array(
                new TextField('GDriveClientId', 'GDrive Client ID'),
                new TextField('GDriveClientSecret', 'GDrive Client Secret'),
            )
        );


        return $fields;
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
