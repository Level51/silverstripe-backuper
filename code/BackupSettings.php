<?php
/**
 * Created by PhpStorm.
 * User: Dipl.-Ing. (FH) Julian Scheuchenzuber M.Eng. <js@querdenker-design.com>
 * Date: 24.05.2015 01:19
 */

class BackupSettings extends Extension {
    private static $db = array(
        'MySQLDumpExe' => 'Varchar(255)'
    );

    public function updateCMSFields(FieldList $fields) {
        $fields->addFieldToTab('Root.Main', TextField::create('MySQLDumpExe', _t('BackupSettings.MYSQL_DUMP_EXE', 'Absolute path to mysqldump executable')));
        Requirements::javascript('backuper/javascript/settings.js');
    }

    public function updateCMSActions(FieldList $actions) {
        if($this->owner->MySQLDumpExe)
            $actions->push(FormAction::create('create_backup', _t('BackupSettings.CREATE_BACKUP', 'Create backup')));
    }
}