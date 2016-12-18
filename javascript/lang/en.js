if(typeof(ss) == 'undefined' || typeof(ss.i18n) == 'undefined') {
  console.error('Class ss.i18n not defined');
} else {
  ss.i18n.addDictionary('en', {
  'BUTTONS.RESTORE_FILE' : "Are you sure you want to restore file %s?",
  'BUTTONS.BACKINGUP' : "Backing up, please wait.",
  'BUTTONS.RESTORING' : "Restoring, please wait.",
  'BUTTONS.RESTORING_FILE' : "Restoring file %s, please wait.",
  });
}
