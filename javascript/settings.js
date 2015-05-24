/**
 * Created by Julian on 24.05.2015.
 */
(function($) {
  $(document).ready(function() {
    // Requesting parcel label
    $(document).on('click', '#Form_EditForm_action_create_backup', function() {
      window.location.href = 'backuper/create-backup';
      return false;
    });
  });
})(jQuery);