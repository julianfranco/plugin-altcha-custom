<?php
/*
 * Plugin Name:      (Evan) - Altcha Custom Plugin
 * Description:      Adds free, customizable features based on the Altcha plugin.
 * Version:          0.0.1
 * Author:           Evan BOMBART
 * Requires Plugins: altcha
 */

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/includes/altcha-default.php';
require_once __DIR__ . '/includes/altcha-events.php';
require_once __DIR__ . '/includes/altcha-elementor.php';
require_once __DIR__ . '/includes/altcha-elementor-atomic.php';
require_once __DIR__ . '/includes/altcha-custom-forms.php';
