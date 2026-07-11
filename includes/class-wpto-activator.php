<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPTO_Activator {

	public static function activate() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$batches_table = $wpdb->prefix . 'wpto_batches';
		$sql_batches   = "CREATE TABLE {$batches_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			term_ids LONGTEXT NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			error_message TEXT NULL,
			created_at DATETIME NOT NULL,
			processed_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY status (status)
		) {$charset_collate};";

		$suggestions_table = $wpdb->prefix . 'wpto_suggestions';
		$sql_suggestions   = "CREATE TABLE {$suggestions_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			batch_id BIGINT UNSIGNED NOT NULL,
			type VARCHAR(30) NOT NULL,
			source_term_ids LONGTEXT NOT NULL,
			target_term_id BIGINT UNSIGNED NULL,
			reason TEXT NULL,
			confidence FLOAT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY batch_id (batch_id),
			KEY status (status),
			KEY type (type)
		) {$charset_collate};";

		dbDelta( $sql_batches );
		dbDelta( $sql_suggestions );

		add_option( 'wpto_api_key', '' );
		add_option( 'wpto_model', 'claude-haiku-4-5' );
		add_option( 'wpto_batch_size', 150 );
	}
}
