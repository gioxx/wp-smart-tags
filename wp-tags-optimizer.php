<?php
/**
 * Plugin Name: WP Tags Optimizer
 * Description: Analizza i tag di WordPress con l'aiuto dell'API Claude (Anthropic) per suggerire merge di duplicati/sinonimi e individuare tag inutilizzati. Richiede sempre approvazione manuale prima di ogni modifica.
 * Version: 0.3.0
 * Author: Gioxx
 * Author URI: https://gioxx.org
 * Plugin URI: https://github.com/gioxx/wp-tags-optimizer
 * License: GPL-2.0-or-later
 * Text Domain: wp-tags-optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPTO_VERSION', '0.3.0' );
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
		WPTO_Settings::init();
		WPTO_Queue::init();
		WPTO_Admin_Page::init();
	}
);
