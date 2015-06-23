<?php
/**
 * Created by PhpStorm.
 * User: Dipl.-Ing. (FH) Julian Scheuchenzuber M.Eng. <js@querdenker-design.com>
 * Date: 24.05.2015 01:19
 */

class BackupSettings extends Extension {
    public function updateCMSActions(FieldList $actions) {
        Requirements::javascript('backuper/javascript/settings.js');
        $actions->push(FormAction::create('create_backup', _t('BackupSettings.CREATE_BACKUP', 'Create backup')));
    }
}