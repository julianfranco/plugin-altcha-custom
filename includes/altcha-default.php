<?php

if (!defined('ABSPATH')) exit;

// Verify that the Altcha "WordPress" options are enabled by default.
add_filter('altcha_get_settings', function ($settings) {
  foreach (['protectLogin', 'protectRegistration', 'protectPasswordReset', 'protectComments'] as $key) {
    $settings[$key] = true;
  }
  return $settings;
});
