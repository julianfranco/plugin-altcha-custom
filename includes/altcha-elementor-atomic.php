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
    // Capture phase: statechange does not bubble, and widgets may appear later (popups).
    wp_add_inline_script('altcha-widget', <<<'JS'
document.addEventListener('statechange', (e) => {
  const w = e.target;
  if (w.tagName !== 'ALTCHA-WIDGET' || e.detail.state !== 'verified') return;
  const mirror = w.parentElement?.querySelector('input[data-interaction-id="altcha"]')
    ?? w.closest('form')?.querySelector('input[data-interaction-id="altcha"]');
  // setAttribute survives the form.reset() Elementor runs after success.
  if (mirror) mirror.setAttribute('value', e.detail.payload);
}, true);
JS);
  }
  return $html . $content;
}, 10, 2);

// Verify the ALTCHA payload before the atomic form handler, after the rate limit.
$altcha_atomic_gate = function () {
  if (!class_exists('AltchaPlugin')) {
    return;
  }
  $payload = '';
  $fields = isset($_POST['form_fields']) && is_array($_POST['form_fields']) ? $_POST['form_fields'] : [];
  foreach ($fields as $i => $field) {
    if (($field['id'] ?? '') === 'altcha') {
      $raw = $field['value'] ?? '';
      $payload = is_string($raw) ? wp_unslash($raw) : '';
      // Keep the payload out of emails and submissions. Disable this filter if another
      // consumer of elementor_pro/atomic_forms/spam_check needs to read the field.
      if (apply_filters('altcha_custom_strip_payload', true)) {
        unset($_POST['form_fields'][$i]);
      }
      break;
    }
  }
  $raw_form_id = $_POST['form_id'] ?? null;
  $form_id = is_string($raw_form_id) ? sanitize_text_field(wp_unslash($raw_form_id)) : null;
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
add_action('wp_ajax_elementor_pro_atomic_forms_send_form', $altcha_atomic_gate, 5);
add_action('wp_ajax_nopriv_elementor_pro_atomic_forms_send_form', $altcha_atomic_gate, 5);
