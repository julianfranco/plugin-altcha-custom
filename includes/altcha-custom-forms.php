<?php

if (!defined('ABSPATH')) exit;

// Render the ALTCHA widget inside a hand-coded form.
function altcha_custom_widget(): string {
  if (!class_exists('AltchaPlugin')) {
    return '';
  }
  $plugin = \AltchaPlugin::$instance;
  return $plugin->get_widget_html($plugin->get_widget_attrs());
}

// Verify the ALTCHA payload of a hand-coded form submission.
function altcha_custom_verify(?string $payload = null): bool {
  if (!class_exists('AltchaPlugin')) {
    return false;
  }
  if ($payload === null) {
    $payload = isset($_POST['altcha']) ? wp_unslash($_POST['altcha']) : '';
  }
  if (!is_string($payload) || $payload === '') {
    altcha_custom_log('bot', 'custom', null, 'missing_payload');
    return false;
  }
  // verify() fatals if $params is null (Altcha 3.2.0 bug).
  $params = [];
  $verified = \AltchaPlugin::$instance->verify($payload, $params);
  altcha_custom_log($verified ? 'verified' : 'failed', 'custom', null, $verified ? null : 'invalid_payload');
  return $verified;
}
