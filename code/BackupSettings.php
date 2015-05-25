<?php
/**
 * Created by PhpStorm.
 * User: Dipl.-Ing. (FH) Julian Scheuchenzuber M.Eng. <js@querdenker-design.com>
 * Date: 24.05.2015 01:19
 */

class BackupSettings extends Extension {
    private static $db = array(
        'MySQLExe' => 'Varchar(255)',
        'MySQLDumpExe' => 'Varchar(255)'
    );

    public function updateCMSFields(FieldList $fields) {
        Requirements::javascript('backuper/javascript/settings.js');
        $fields->addFieldToTab('Root.Main', TextField::create('MySQLDumpExe', _t('BackupSettings.MYSQL_DUMP_EXE', 'Absolute path to mysqldump executable')));
        $fields->addFieldToTab('Root.Main', TextField::create('MySQLExe', _t('BackupSettings.MYSQL_EXE', 'Absolute path to mysql executable')));
        if($this->owner->MySQLExe)
            $fields->addFieldToTab('Root.Main', UploadField::create());
    }

    public function updateCMSActions(FieldList $actions) {
        if($this->owner->MySQLDumpExe)
            $actions->push(FormAction::create('create_backup', _t('BackupSettings.CREATE_BACKUP', 'Create backup')));
    }
}