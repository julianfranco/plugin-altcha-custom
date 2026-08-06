<?php

if (!defined('ABSPATH')) exit;

// Report our own verifications to the Altcha analytics dashboard.
function altcha_custom_log(string $event, string $plugin_name, ?string $form_id = null, ?string $reason = null): void {
  if (!class_exists('AltchaPlugin')) {
    return;
  }
  \AltchaPlugin::$instance->log_event($event, null, $form_id, $reason, null, false, $plugin_name);
}
