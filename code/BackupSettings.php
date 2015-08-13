<?php
/**
 * Created by PhpStorm.
 * User: Julian Scheuchenzuber <js@lvl51.de>
 * Date: 24.05.2015 01:19
 */

class BackupSettings extends Extension {
    public function updateCMSActions(FieldList $actions) {
        Requirements::javascript(basename(dirname(__DIR__)) . '/javascript/settings.js');
        $actions->push(
            FormAction::create('create_backup', _t('BackupSettings.CREATE_BACKUP', 'Create backup'))->setAttribute('data-icon', 'accept')
        );
    }
}