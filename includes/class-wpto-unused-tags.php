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
					// term->count only tallies posts with post_status = 'publish',
					// so it can read 0 for a tag that's still attached to drafts,
					// scheduled, pending, or private posts. Confirm there are truly
					// no associated objects before treating it as unused.
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
}
