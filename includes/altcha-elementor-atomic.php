<?php

if (!defined('ABSPATH')) exit;

// Render the ALTCHA widget before the submit button of atomic forms (Editor V4).
add_filter('elementor/widget/render_content', function ($content, $widget) {
  if ($widget->get_name() !== 'e-form-submit-button' || !class_exists('AltchaPlugin')) {
    return $content;
  }
  $plugin = \AltchaPlugin::$instance;
  // The atomic collector only posts inputs tagged with data-interaction-id.
  $html = $plugin->get_widget_html($plugin->get_widget_attrs())
    . '<input type="hidden" name="altcha" data-interaction-id="altcha" value="">';
  static $js_added = false;
  if (!$js_added) {
    $js_added = true;
    wp_add_inline_script('altcha-widget', <<<'JS'
document.querySelectorAll('[data-element_type="e-form"] altcha-widget').forEach((w) => {
  w.addEventListener('statechange', (e) => {
    if (e.detail.state !== 'verified') return;
    w.closest('form').querySelector('input[data-interaction-id="altcha"]').setAttribute('value', e.detail.payload);
  });
});
JS);
  }
  return $html . $content;
}, 10, 2);

// Verify the ALTCHA payload before the atomic form handler runs (priority 1).
$altcha_atomic_gate = function () {
  if (!class_exists('AltchaPlugin')) {
    return;
  }
  $payload = '';
  $fields = isset($_POST['form_fields']) && is_array($_POST['form_fields']) ? $_POST['form_fields'] : [];
  foreach ($fields as $i => $field) {
    if (($field['id'] ?? '') === 'altcha') {
      $payload = wp_unslash($field['value'] ?? '');
      // Keep the payload out of emails and submissions.
      unset($_POST['form_fields'][$i]);
      break;
    }
  }
  $form_id = isset($_POST['form_id']) ? sanitize_text_field(wp_unslash($_POST['form_id'])) : null;
  if (empty($payload)) {
    altcha_custom_log('bot', 'elementor-atomic', $form_id, 'missing_payload');
    wp_send_json_error(['message' => __('Please verify that you are human.', 'plugin-altcha-custom')]);
  }
  // verify() fatals if $params is null (Altcha 3.2.0 bug).
  $params = [];
  if (!\AltchaPlugin::$instance->verify($payload, $params)) {
    altcha_custom_log('failed', 'elementor-atomic', $form_id, 'invalid_payload');
    wp_send_json_error(['message' => __('Captcha verification failed, please try again.', 'plugin-altcha-custom')]);
  }
  altcha_custom_log('verified', 'elementor-atomic', $form_id);
};
add_action('wp_ajax_elementor_pro_atomic_forms_send_form', $altcha_atomic_gate, 1);
add_action('wp_ajax_nopriv_elementor_pro_atomic_forms_send_form', $altcha_atomic_gate, 1);
