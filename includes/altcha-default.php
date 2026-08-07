<?php

if (!defined('ABSPATH')) exit;

// Verify that the Altcha "WordPress" options are enabled by default.
add_filter('altcha_get_settings', function ($settings) {
  foreach (['protectLogin', 'protectRegistration', 'protectPasswordReset', 'protectComments'] as $key) {
    $settings[$key] = true;
  }
  // 3.5x more work than the "low" default: ~1.8s in browser, ~18s for a PHP bot.
  $settings['powComplexity'] = 'high';
  return $settings;
});

// Set the widget language explicitly, instead of relying on <html lang>.
add_filter('altcha_get_widget_attrs', function ($attrs) {
  // Bundle keys are lowercase with a hyphen (fr-fr); unknown codes fall back to English.
  $attrs['language'] ??= strtolower(str_replace('_', '-', get_locale()));
  return $attrs;
});
