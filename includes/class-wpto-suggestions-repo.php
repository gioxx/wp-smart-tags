<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPTO_Suggestions_Repo {

	public static function batches_table() {
		global $wpdb;
		return $wpdb->prefix . 'wpto_batches';
	}

	public static function suggestions_table() {
		global $wpdb;
		return $wpdb->prefix . 'wpto_suggestions';
	}

	public static function create_batch( array $term_ids ) {
		global $wpdb;

		$wpdb->insert(
			self::batches_table(),
			array(
				'term_ids'   => wp_json_encode( array_map( 'intval', $term_ids ) ),
				'status'     => 'pending',
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	public static function get_next_pending_batch() {
		global $wpdb;

		$table = self::batches_table();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = %s ORDER BY id ASC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedTableName
				'pending'
			),
			ARRAY_A
		);

		return $row;
	}

	public static function mark_batch_done( $batch_id ) {
		global $wpdb;

		$wpdb->update(
			self::batches_table(),
			array(
				'status'       => 'done',
				'processed_at' => current_time( 'mysql' ),
			),
			array( 'id' => (int) $batch_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	public static function mark_batch_failed( $batch_id, $error_message ) {
		global $wpdb;

		$wpdb->update(
			self::batches_table(),
			array(
				'status'        => 'failed',
				'error_message' => $error_message,
				'processed_at'  => current_time( 'mysql' ),
			),
			array( 'id' => (int) $batch_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	public static function retry_batch( $batch_id ) {
		global $wpdb;

		$wpdb->update(
			self::batches_table(),
			array(
				'status'        => 'pending',
				'error_message'  => null,
				'processed_at'  => null,
			),
			array( 'id' => (int) $batch_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	public static function get_batch_progress() {
		global $wpdb;

		$table = self::batches_table();

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedTableName
		$done  = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE status IN (%s, %s, %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedTableName
				'done',
				'failed',
				'cancelled'
			)
		);
		$pending = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE status = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedTableName
				'pending'
			)
		);

		return array(
			'total'   => $total,
			'done'    => $done,
			'pending' => $pending,
		);
	}

	public static function get_recent_batches( $limit = 10 ) {
		global $wpdb;

		$table = self::batches_table();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, status, error_message, created_at, processed_at FROM {$table} ORDER BY id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedTableName
				absint( $limit )
			),
			ARRAY_A
		);
	}

	public static function cancel_pending_batches() {
		global $wpdb;

		$wpdb->update(
			self::batches_table(),
			array(
				'status'       => 'cancelled',
				'processed_at' => current_time( 'mysql' ),
			),
			array( 'status' => 'pending' ),
			array( '%s', '%s' ),
			array( '%s' )
		);
	}

	public static function get_failed_batches() {
		global $wpdb;

		$table = self::batches_table();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = %s ORDER BY id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedTableName
				'failed'
			),
			ARRAY_A
		);
	}

	public static function insert_suggestions( $batch_id, array $suggestions ) {
		global $wpdb;

		foreach ( $suggestions as $suggestion ) {
			$wpdb->insert(
				self::suggestions_table(),
				array(
					'batch_id'        => (int) $batch_id,
					'type'            => $suggestion['type'],
					'source_term_ids' => wp_json_encode( $suggestion['source_term_ids'] ),
					'target_term_id'  => (int) $suggestion['target_term_id'],
					'reason'          => $suggestion['reason'],
					'confidence'      => $suggestion['confidence'],
					'status'          => 'pending',
					'created_at'      => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%s', '%d', '%s', '%f', '%s', '%s' )
			);
		}
	}

	public static function get_suggestions( $status = 'pending' ) {
		global $wpdb;

		$table = self::suggestions_table();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = %s ORDER BY type ASC, id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedTableName
				$status
			),
			ARRAY_A
		);
	}

	public static function get_suggestion( $id ) {
		global $wpdb;

		$table = self::suggestions_table();

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedTableName
				(int) $id
			),
			ARRAY_A
		);
	}

	public static function set_suggestion_status( $id, $status ) {
		global $wpdb;

		$wpdb->update(
			self::suggestions_table(),
			array( 'status' => $status ),
			array( 'id' => (int) $id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Marks a suggestion as applied, snapshotting the tag names since the
	 * source tags are deleted by the merge and would no longer resolve.
	 *
	 * @param int      $id           Suggestion ID.
	 * @param string[] $source_names Names of the merged source tags.
	 * @param string   $target_name  Name of the target tag.
	 */
	public static function mark_applied( $id, array $source_names, $target_name ) {
		global $wpdb;

		$wpdb->update(
			self::suggestions_table(),
			array(
				'status'        => 'applied',
				'applied_at'    => current_time( 'mysql' ),
				'source_names'  => wp_json_encode( $source_names ),
				'target_name'   => $target_name,
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Returns suggestion counts per status, e.g. ['pending'=>3,'applied'=>12,'rejected'=>2].
	 *
	 * @return array<string,int>
	 */
	public static function get_status_counts() {
		global $wpdb;

		$table = self::suggestions_table();

		$rows = $wpdb->get_results(
			"SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedTableName
			ARRAY_A
		);

		$counts = array(
			'pending'  => 0,
			'applied'  => 0,
			'rejected' => 0,
		);

		foreach ( (array) $rows as $row ) {
			$counts[ $row['status'] ] = (int) $row['total'];
		}

		return $counts;
	}

	public static function get_applied_suggestions( $limit = 50 ) {
		global $wpdb;

		$table = self::suggestions_table();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = 'applied' ORDER BY applied_at DESC, id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedTableName
				(int) $limit
			),
			ARRAY_A
		);
	}
}
