<?php

if (!defined('ABSPATH')) exit;

// Add an ALTCHA captcha field to the Elementor Pro form builder.
add_action('elementor_pro/forms/fields/register', function ($registrar) {
  if (!class_exists('AltchaPlugin')) {
    return;
  }

  $registrar->register(new class extends \ElementorPro\Modules\Forms\Fields\Field_Base {

    public function get_type()
    {
      return 'altcha';
    }

    public function get_name()
    {
      return 'ALTCHA';
    }

    public function render($item, $item_index, $form)
    {
      $plugin = \AltchaPlugin::$instance;
      $attrs = $plugin->get_widget_attrs([
        'name' => 'form_fields[' . $item['custom_id'] . ']',
      ]);
      echo '<div class="elementor-field" id="form-field-' . esc_attr($item['custom_id']) . '">'
        . $plugin->get_widget_html($attrs)
        . '</div>';
    }

    public function validation($field, $record, $ajax_handler)
    {
      if (empty($field['value'])) {
        $ajax_handler->add_error($field['id'], __('Please verify that you are human.', 'plugin-altcha-custom'));
        return;
      }
      // verify() fatals if $params is null (Altcha 3.2.0 bug).
      $params = [];
      if (!\AltchaPlugin::$instance->verify($field['value'], $params)) {
        $ajax_handler->add_error($field['id'], __('Captcha verification failed, please try again.', 'plugin-altcha-custom'));
        return;
      }
      $record->remove_field($field['id']);
    }
  });
});

// Hide the field label, like Elementor does for its own reCAPTCHA field.
add_filter('elementor_pro/forms/render/item', function ($item) {
  if (isset($item['field_type']) && $item['field_type'] === 'altcha') {
    $item['field_label'] = false;
  }
  return $item;
});
