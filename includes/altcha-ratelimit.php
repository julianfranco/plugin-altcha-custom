<?php

if (!defined('ABSPATH')) exit;

// Throttle a bucket per IP, reusing the official (license-free) rate_limit method.
function altcha_custom_rate_limit(string $bucket, int $limit, int $window): bool {
  if (!class_exists('AltchaPlugin')) {
    return true;
  }
  $plugin = \AltchaPlugin::$instance;
  $result = $plugin->rate_limit($limit, $window, $bucket . '|' . $plugin->get_ip_address());
  if (!headers_sent()) {
    header('X-RateLimit-Limit: ' . $limit);
    header('X-RateLimit-Remaining: ' . $result['remaining']);
    header('X-RateLimit-Reset-In: ' . $result['reset_in']);
  }
  if ($result['exceeded']) {
    $plugin->log_event('blocked', null, null, 'rate_limit_' . $bucket, null, false, 'ratelimit');
    return false;
  }
  return true;
}

// Throttle form submissions to 10 per minute and per IP.
$altcha_submit_limit = function () {
  if (!altcha_custom_rate_limit('submit', 10, MINUTE_IN_SECONDS)) {
    wp_send_json_error(['message' => __('Too many attempts, please try again in a minute.', 'plugin-altcha-custom')], 429);
  }
};
foreach (['elementor_pro_forms_send_form', 'elementor_pro_atomic_forms_send_form'] as $action) {
  add_action('wp_ajax_' . $action, $altcha_submit_limit, 1);
  add_action('wp_ajax_nopriv_' . $action, $altcha_submit_limit, 1);
}

// Throttle challenge requests to 30 per minute and per IP.
add_filter('rest_pre_dispatch', function ($result, $server, $request) {
  if (str_contains($request->get_route(), '/altcha/v1/challenge')
    && !altcha_custom_rate_limit('challenge', 30, MINUTE_IN_SECONDS)) {
    return new WP_Error('altcha_rate_limited', __('Too many requests.', 'plugin-altcha-custom'), ['status' => 429]);
  }
  return $result;
}, 10, 3);
