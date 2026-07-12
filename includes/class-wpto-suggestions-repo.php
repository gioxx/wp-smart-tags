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

	/**
	 * Clears leftover unreviewed suggestions and the batch log before a
	 * fresh analysis run, so results always reflect the current tag set
	 * instead of piling up on top of a stale previous run. Rejected and
	 * applied suggestions are historical records and are left untouched.
	 */
	public static function clear_for_new_analysis() {
		global $wpdb;

		$wpdb->delete( self::suggestions_table(), array( 'status' => 'pending' ), array( '%s' ) );
		$wpdb->query( 'DELETE FROM ' . self::batches_table() ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedTableName
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

	/**
	 * Inserts suggestions, skipping any that duplicate an already-pending
	 * one (same type/sources/target), and any that pair up two tags the
	 * user has already rejected together, so a rejected pairing is never
	 * re-proposed by a later analysis.
	 */
	public static function insert_suggestions( $batch_id, array $suggestions ) {
		global $wpdb;

		$seen           = self::get_pending_signatures();
		$rejected_pairs = self::get_rejected_pairs();

		foreach ( $suggestions as $suggestion ) {
			$signature = self::suggestion_signature( $suggestion['type'], $suggestion['source_term_ids'], $suggestion['target_term_id'] );

			if ( isset( $seen[ $signature ] ) ) {
				continue;
			}

			if ( self::has_rejected_pair( $suggestion['source_term_ids'], $suggestion['target_term_id'], $rejected_pairs ) ) {
				continue;
			}

			$seen[ $signature ] = true;

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

	private static function get_pending_signatures() {
		global $wpdb;

		$table = self::suggestions_table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT type, source_term_ids, target_term_id FROM {$table} WHERE status = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedTableName
				'pending'
			),
			ARRAY_A
		);

		$signatures = array();
		foreach ( (array) $rows as $row ) {
			$signatures[ self::suggestion_signature( $row['type'], json_decode( $row['source_term_ids'], true ), $row['target_term_id'] ) ] = true;
		}

		return $signatures;
	}

	private static function suggestion_signature( $type, $source_term_ids, $target_term_id ) {
		$source_ids = array_map( 'intval', (array) $source_term_ids );
		sort( $source_ids );

		return $type . ':' . implode( ',', $source_ids ) . '>' . (int) $target_term_id;
	}

	/**
	 * Every tag-pair (unordered) that appears in a currently rejected
	 * suggestion, e.g. rejecting "Wii U" -> "Nintendo Wii U" remembers
	 * that pair regardless of which one is source or target next time.
	 *
	 * @return array<string,bool>
	 */
	private static function get_rejected_pairs() {
		global $wpdb;

		$table = self::suggestions_table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT source_term_ids, target_term_id FROM {$table} WHERE status = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedTableName
				'rejected'
			),
			ARRAY_A
		);

		$pairs = array();
		foreach ( (array) $rows as $row ) {
			$target_id = (int) $row['target_term_id'];
			foreach ( (array) json_decode( $row['source_term_ids'], true ) as $source_id ) {
				$pairs[ self::pair_key( (int) $source_id, $target_id ) ] = true;
			}
		}

		return $pairs;
	}

	private static function has_rejected_pair( $source_term_ids, $target_term_id, array $rejected_pairs ) {
		$target_id = (int) $target_term_id;

		foreach ( (array) $source_term_ids as $source_id ) {
			if ( isset( $rejected_pairs[ self::pair_key( (int) $source_id, $target_id ) ] ) ) {
				return true;
			}
		}

		return false;
	}

	private static function pair_key( $a, $b ) {
		return min( $a, $b ) . '-' . max( $a, $b );
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
