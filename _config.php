<?php

// Setting the locale
i18n::set_locale('de_DE');

// Set allowed locales
if (class_exists('Translatable')) {
	Translatable::set_allowed_locales(array('de_DE', 'en_US'));
	Translatable::set_current_locale('de_DE');
}
