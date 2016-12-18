<?php

// Set allowed locales
Translatable::set_allowed_locales(array('de_DE', 'en_US'));

// Setting the locale
i18n::set_locale('de_DE');
Translatable::set_current_locale('de_DE');
