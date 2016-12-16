/**
 * Created by Julian on 24.05.2015.
 */
(function ($) {
    $(document).ready(function () {
        $(document).on('click', '#Form_EditForm_action_BackupNow', function () {
            $("<p id='waiter' class='message notice'>Backing up, please wait.</p>")
                .insertAfter(jQuery("#Form_EditForm_backup-heading"));

            $('html').css('cursor', 'wait');

            $.ajax('backuper/create-backup', {
                'async': 'false',
                'success': function (json) {
                    $('html').css('cursor', 'default');
                    data = JSON.parse(json);

                    $("#waiter").removeClass('notice');

                    if (data.Success) {
                        //$("#backup-latest-links").html(data.Data);
                        $("#waiter").addClass('good');
                        window.location.href = 'backuper/get-backup/' + data.Timestamp;
                    } else {
                        $("#waiter").addClass('bad');
                    }

                    $("#waiter").html(data.Message);
                }
            });
        });

        $(document).on('click', '#Form_EditForm_action_RestoreNow', function () {
            $("<p id='waiterRestore' class='message notice'>Restoring, please wait.</p>")
                .insertAfter(jQuery("#Form_EditForm_restore-heading"));

            $('html').css('cursor', 'wait');

            $.ajax('backuper/restore-backup', {
                'async': 'false',
                'success': function (json) {
                    $('html').css('cursor', 'default');
                    data = JSON.parse(json);

                    $("#waiterRestore").removeClass('notice');

                    if (data.Success) {
                        $("#waiterRestore").addClass('good');
                    } else {
                        $("#waiterRestore").addClass('bad');
                    }

                    $("#waiterRestore").html(data.Message);
                }
            });
        });
    });



})
(jQuery);






