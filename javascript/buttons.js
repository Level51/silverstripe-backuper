/**
 * Created by Julian on 24.05.2015.
 */
(function ($) {
    $(document).ready(function () {
        $(document).on('click', '#Form_EditForm_action_BackupNow', function () {
            $("<p id='waiter' class='message notice'>"
                + ss.i18n._t('BUTTONS.BACKINGUP')
                + "</p>")
                .insertAfter(jQuery("#Form_EditForm_backup-heading"));

            $('html').css('cursor', 'wait');

            $.ajax('backuper/create-backup', {
                'async': 'false',
                'success': function (json) {
                    $('html').css('cursor', 'default');
                    data = JSON.parse(json);

                    $("#waiter").removeClass('notice');

                    var classToAdd = data.Success ? 'good' : 'bad';
                    $("#waiter").addClass(classToAdd);

                    if ('GDriveUploadStatus' in data) {

                        var success = data.GDriveUploadStatus.Success;
                        var gDriveMsg = data.GDriveUploadStatus.Message;

                        if (gDriveMsg) {
                            $("<p id='gWaiter' class='message notice'>" + gDriveMsg + "</p>")
                                .insertAfter(jQuery("#Form_EditForm_backup-heading"));
                        }

                        var classToAdd = success ? 'good' : 'bad';
                        $("#gWaiter").addClass(classToAdd);
                    }

                    $("#waiter").html(data.Message);

                    if (data.Success) {
                        //$("#backup-latest-links").html(data.Data);
                        window.location.href = 'backuper/get-backup/' + data.Timestamp;
                    }
                }
            });
        });

        $(document).on('click', '#Form_EditForm_action_RestoreNow', function () {
            $("<p id='waiterRestore' class='message notice'>" + ss.i18n._t('BUTTONS.RESTORING') + "</p>")
                .insertAfter(jQuery("#Form_EditForm_restore-heading"));

            $('html').css('cursor', 'wait');

            $.ajax('backuper/restore-upload', {
                'async': 'false',
                'success': function (json) {
                    $('html').css('cursor', 'default');
                    data = JSON.parse(json);

                    $("#waiterRestore").removeClass('notice');

                    var classToAdd = data.Success ? 'good' : 'bad';
                    $("#waiterRestore").addClass(classToAdd);

                    $("#waiterRestore").html(data.Message);
                }
            });
        });

        $(document).on('click', '#Form_EditForm_action_AuthenticateNow', function () {
            $("<p id='waiterRestore' class='message notice'>"
                + ss.i18n._t('BUTTONS.RESTORING')
                + "</p>")
                .insertAfter(jQuery("#Form_EditForm_restore-heading"));

            window.location.href = 'backuper/authenticate-gdrive';
        });
    });


})
(jQuery);

var restoreGDriveBackup = function (id, filename) {
    (function ($) {
        if (confirm(ss.i18n.sprintf(ss.i18n._t('BUTTONS.RESTORE_FILE'), filename))) {

            $("<p id='waiterRestore' class='message notice'>"
                + ss.i18n.sprintf(ss.i18n._t('BUTTONS.RESTORING_FILE'), filename)
                + "</p>")
                .insertAfter(jQuery("#Form_EditForm_restore-heading"));

            $('html').css('cursor', 'wait');

            $.ajax('backuper/restore-gdrive/' + id, {
                'async': 'false',
                'success': function (json) {
                    $('html').css('cursor', 'default');
                    data = JSON.parse(json);

                    $("#waiterRestore").removeClass('notice');

                    var classToAdd = data.Success ? 'good' : 'bad';
                    $("#waiterRestore").addClass(classToAdd);

                    $("#waiterRestore").html(data.Message);
                }
            });
        }
    })
    (jQuery);

    return false;
}









