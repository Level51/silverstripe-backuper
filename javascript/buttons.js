/**
 * Created by Julian on 24.05.2015.
 */
var statusRefreshRate = 2000;

(function ($) {
    $(document).ready(function () {
        // Backup button
        $(document).on('click', '#Form_EditForm_action_BackupNow', function () {

            backupMsg(ss.i18n._t('BUTTONS.BACKINGUP'), 'notice');

            $('html').css('cursor', 'wait');

            $.ajax('backuper/create-backup', {
                'async': 'false',
                'success': function (json) {
                    $('html').css('cursor', 'default');
                    data = JSON.parse(json);

                    $("#waiter").removeClass('notice');

                    var classToAdd = data.Success ? 'good' : 'bad';
                    $("#waiter").addClass(classToAdd);

                    $("#waiter").html(data.Message);

                    if (data.Success) {
                        //$("#backup-latest-links").html(data.Data);
                        listenForBackupStatusUpdates();
                    }
                }
            });
        });

        // Restore button
        $(document).on('click', '#Form_EditForm_action_RestoreNow', function () {

            restoreMsg(ss.i18n._t('BUTTONS.RESTORING'));

            $('html').css('cursor', 'wait');

            $.ajax('backuper/restore-upload', {
                'async': 'false',
                'success': function (json) {
                    $('html').css('cursor', 'default');
                    data = JSON.parse(json);

                    var mode = data.Success ? 'good' : 'bad';

                    restoreMsg(data.Message, mode, true);

                    if(data.Success)
                        listenForRestoreStatusUpdates();
                }
            });
        });

        // Authenticate button
        $(document).on('click', '#Form_EditForm_action_AuthenticateNow', function () {
            window.location.href = 'backuper/authenticate-gdrive';
        });

        // Logout button
        $(document).on('click', '#Form_EditForm_action_LogoutNow', function () {
            window.location.href = 'backuper/authenticate-gdrive?logout';
        });
    });


})
(jQuery);

var restoreGDriveBackup = function (id, filename) {
    (function ($) {
        if (confirm(ss.i18n.sprintf(ss.i18n._t('BUTTONS.RESTORE_FILE'), filename))) {

            restoreMsg(ss.i18n.sprintf(ss.i18n._t('BUTTONS.RESTORING_FILE'), filename));

            $('html').css('cursor', 'wait');

            $.ajax('backuper/restore-gdrive/' + id, {
                'async': 'false',
                'success': function (json) {
                    $('html').css('cursor', 'default');
                    data = JSON.parse(json);

                    var mode = data.Success ? 'good' : 'bad';

                    restoreMsg(data.Message, mode, true);

                    if(data.Success)
                        listenForRestoreStatusUpdates();
                }
            });
        }
    })
    (jQuery);

    return false;
}

var listenForBackupStatusUpdates = function () {

    backupMsg("<textarea id='backupStatus' rows='4' cols='100'>"
        + 'Loading console output'
        + "</textarea>");

    var refreshIntervalId = window.setInterval(function () {
        jQuery.ajax('backuper/backup-status', {
            'async': 'false',
            'success': function (data) {

                var json = undefined;
                try {
                    json = JSON.parse(data);
                } catch (err) {
                }

                if (json != undefined) {
                    var state = json.Success ? 'good' : 'bad';
                    backupMsg(json.Message, state);

                    if ('GDriveUploadStatus' in json) {

                        var success = json.GDriveUploadStatus.Success;
                        var gDriveMsg = json.GDriveUploadStatus.Message;

                        if (gDriveMsg) {
                            var gState = success ? 'good' : 'bad';
                            backupMsg(gDriveMsg, gState);
                        }
                    }
                    
                    if(json.Success) {

                        // Redirect to download if defined
                        if(json.DownloadLink != undefined) {
                            window.location.href = json.DownloadLink;
                        }
                    }

                    clearInterval(refreshIntervalId);
                }
            }
        });

        // Load console output updates
        jQuery.ajax('backuper/backup-output', {
            'async': 'false',
            'success': function (data) {
                jQuery("#backupStatus").html(data);
            }
        });
    }, statusRefreshRate);
}

var listenForRestoreStatusUpdates = function () {

    restoreMsg("<textarea id='restoreStatus' rows='4' cols='100'>"
        + 'Loading console output'
        + "</textarea>");

    var refreshIntervalId = window.setInterval(function () {

        // Load status updates
        jQuery.ajax('backuper/restore-status', {
            'async': 'false',
            'success': function (data) {

                var json = undefined;
                try {
                    json = JSON.parse(data);
                } catch (err) {
                }

                if (json != undefined) {
                    var state = json.Success ? 'good' : 'bad';
                    restoreMsg(json.Message, state);

                    clearInterval(refreshIntervalId);
                }
            }
        });

        // Load console output updates
        jQuery.ajax('backuper/restore-output', {
            'async': 'false',
            'success': function (data) {
                jQuery("#restoreStatus").html(data);
            }
        });
    }, statusRefreshRate);
}

var backupMsg = function (text, mode, overwriteOld) {
    var msgid = 'waiter';
    var insertAfterId = 'Form_EditForm_backup-heading';

    createMsg(msgid, insertAfterId, text, mode, overwriteOld);
}

var restoreMsg = function (text, mode, overwriteOld) {
    var msgid = 'waiterRestore';
    var insertAfterId = 'Form_EditForm_restore-heading';

    createMsg(msgid, insertAfterId, text, mode, overwriteOld);
}

var createMsg = function (msgid, insertAfterId, text, mode, overwriteOld) {
    if(!overwriteOld) {
        jQuery("<p id='" + msgid + "' class='message'>"
            + text
            + "</p>")
            .insertAfter(jQuery("#" + insertAfterId));

        if (mode != undefined)
            jQuery("#" + msgid).addClass(mode);
    } else {
        // Recycle old msg and overwrite with new message

        var waiterR = jQuery("#" + msgid);

        // Remove status classes
        waiterR.removeClass('notice');
        waiterR.removeClass('good');
        waiterR.removeClass('bad');

        waiterR.addClass(mode);

        waiterR.html(text);
    }
}










