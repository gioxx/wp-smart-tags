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
					return 0 === (int) $term->count;
				}
			)
		);
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

			if ( 0 !== (int) $term->count ) {
				// Safety: never delete a tag that has posts, even if requested.
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
