<?php

if (!defined('ABSPATH')) exit;

// Load our widget overrides right after the official stylesheet.
add_action('wp_enqueue_scripts', function () {
  $path = plugin_dir_path(__DIR__) . 'assets/altcha-custom.css';
  // The dependency also gates loading: without the official style, there is no widget.
  wp_enqueue_style(
    'altcha-custom',
    plugin_dir_url(__DIR__) . 'assets/altcha-custom.css',
    ['altcha-widget-styles'],
    file_exists($path) ? filemtime($path) : false
  );
}, 20);
