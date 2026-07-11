<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPTO_Merge_Handler {

	/**
	 * Apply an approved suggestion: reassign posts from source tags to the
	 * target tag, then delete the source tags. Posts are always given the
	 * target tag before a source tag is removed, so no post ever loses its
	 * tagging even if this fails partway through.
	 *
	 * @param array $suggestion Row from wpto_suggestions table.
	 * @return true|WP_Error
	 */
	public static function apply( array $suggestion ) {
		$target_id = (int) $suggestion['target_term_id'];
		$target    = get_term( $target_id, 'post_tag' );

		if ( ! $target || is_wp_error( $target ) ) {
			return new WP_Error( 'wpto_missing_target', __( 'Il tag di destinazione non esiste piu\'.', 'wp-tags-optimizer' ) );
		}

		$source_ids = json_decode( $suggestion['source_term_ids'], true );

		if ( ! is_array( $source_ids ) || empty( $source_ids ) ) {
			return new WP_Error( 'wpto_missing_source', __( 'Nessun tag sorgente valido nel suggerimento.', 'wp-tags-optimizer' ) );
		}

		foreach ( $source_ids as $source_id ) {
			$source_id = (int) $source_id;

			if ( $source_id === $target_id ) {
				continue;
			}

			$source_term = get_term( $source_id, 'post_tag' );

			if ( ! $source_term || is_wp_error( $source_term ) ) {
				// Already gone (e.g. merged by an earlier suggestion); nothing to do.
				continue;
			}

			$post_ids = get_objects_in_term( $source_id, 'post_tag' );

			if ( is_wp_error( $post_ids ) ) {
				return $post_ids;
			}

			foreach ( $post_ids as $post_id ) {
				$result = wp_set_object_terms( (int) $post_id, $target_id, 'post_tag', true );

				if ( is_wp_error( $result ) ) {
					return $result;
				}
			}

			$delete_result = wp_delete_term( $source_id, 'post_tag' );

			if ( is_wp_error( $delete_result ) ) {
				return $delete_result;
			}
		}

		return true;
	}
}
