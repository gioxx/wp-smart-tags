<?php
/**
 * Plugin Name: AI Tags Optimizer for WordPress
 * Description: Analyzes WordPress tags with the help of the Claude API (Anthropic) to suggest merges for duplicates/synonyms and flag unused tags. Always requires manual approval before any change.
 * Version: 0.6.0
 * Author: Gioxx
 * Author URI: https://gioxx.org
 * Plugin URI: https://github.com/gioxx/ai-tags-optimizer
 * License: GPL-2.0-or-later
 * Text Domain: ai-tags-optimizer
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPTO_VERSION', '0.6.0' );
define( 'WPTO_PLUGIN_FILE', __FILE__ );
define( 'WPTO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPTO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once WPTO_PLUGIN_DIR . 'includes/class-wpto-activator.php';
require_once WPTO_PLUGIN_DIR . 'includes/class-wpto-settings.php';
require_once WPTO_PLUGIN_DIR . 'includes/class-wpto-unused-tags.php';
require_once WPTO_PLUGIN_DIR . 'includes/class-wpto-api-client.php';
require_once WPTO_PLUGIN_DIR . 'includes/class-wpto-suggestions-repo.php';
require_once WPTO_PLUGIN_DIR . 'includes/class-wpto-queue.php';
require_once WPTO_PLUGIN_DIR . 'includes/class-wpto-merge-handler.php';
require_once WPTO_PLUGIN_DIR . 'includes/class-wpto-admin-page.php';

register_activation_hook( __FILE__, array( 'WPTO_Activator', 'activate' ) );
register_deactivation_hook(
	__FILE__,
	function () {
		wp_clear_scheduled_hook( 'wpto_process_batch' );
	}
);

add_action(
	'plugins_loaded',
	function () {
		load_plugin_textdomain( 'ai-tags-optimizer', false, dirname( plugin_basename( WPTO_PLUGIN_FILE ) ) . '/languages' );

		WPTO_Settings::init();
		WPTO_Queue::init();
		WPTO_Admin_Page::init();
	}
);
