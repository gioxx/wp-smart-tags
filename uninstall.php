<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

wp_clear_scheduled_hook( 'wpto_process_batch' );

if ( ! get_option( 'wpto_cleanup_on_uninstall', true ) ) {
	return;
}

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wpto_suggestions" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wpto_batches" );

delete_option( 'wpto_api_key' );
delete_option( 'wpto_model' );
delete_option( 'wpto_batch_size' );
delete_option( 'wpto_ai_language' );
delete_option( 'wpto_cleanup_on_uninstall' );
