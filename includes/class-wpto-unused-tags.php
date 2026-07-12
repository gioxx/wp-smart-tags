<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPTO_Unused_Tags {

	public static function get_unused_terms() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'post_tag',
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		return array_values(
			array_filter(
				$terms,
				function ( $term ) {
					// term->count is a cached column (wp_term_taxonomy.count) that can
					// drift out of sync with the actual wp_term_relationships rows
					// (stale post_status-based counting, imports, cache desync...).
					// Confirm there are truly no associated objects before treating
					// a tag as unused.
					return self::has_no_objects( $term->term_id );
				}
			)
		);
	}

	private static function has_no_objects( $term_id ) {
		$object_ids = get_objects_in_term( $term_id, 'post_tag' );

		if ( is_wp_error( $object_ids ) ) {
			return false;
		}

		return empty( $object_ids );
	}

	public static function delete_terms( array $term_ids ) {
		$deleted = array();
		$errors  = array();

		foreach ( $term_ids as $term_id ) {
			$term_id = absint( $term_id );
			$term    = get_term( $term_id, 'post_tag' );

			if ( ! $term || is_wp_error( $term ) ) {
				$errors[] = $term_id;
				continue;
			}

			if ( ! self::has_no_objects( $term_id ) ) {
				// Safety: never delete a tag that has posts, even if requested,
				// and even if the cached count column says otherwise.
				$errors[] = $term_id;
				continue;
			}

			$result = wp_delete_term( $term_id, 'post_tag' );

			if ( is_wp_error( $result ) || false === $result ) {
				$errors[] = $term_id;
			} else {
				$deleted[] = $term_id;
			}
		}

		return array(
			'deleted' => $deleted,
			'errors'  => $errors,
		);
	}

	/**
	 * Force-recalculate wp_term_taxonomy.count for every post_tag term, the
	 * same fix WP-CLI's `wp term recount post_tag` applies. Useful when the
	 * cached count has drifted out of sync with the real post associations.
	 *
	 * @return int Number of terms recounted.
	 */
	public static function recount_all() {
		global $wpdb;

		$tt_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedTableName
				'post_tag'
			)
		);

		if ( empty( $tt_ids ) ) {
			return 0;
		}

		wp_update_term_count_now( array_map( 'intval', $tt_ids ), 'post_tag' );

		return count( $tt_ids );
	}
}
