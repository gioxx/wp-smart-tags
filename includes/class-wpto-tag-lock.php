<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Per-tag lock flag, stored as term meta. Locked tags are protected from
 * checkbox selection, bulk/AI merges, and deletion in the plugin's UI.
 */
class WPTO_Tag_Lock {

	const META_KEY = 'wpto_locked';

	public static function is_locked( $term_id ) {
		return (bool) get_term_meta( (int) $term_id, self::META_KEY, true );
	}

	public static function set_locked( $term_id, $locked ) {
		$term_id = (int) $term_id;

		if ( $locked ) {
			update_term_meta( $term_id, self::META_KEY, '1' );
		} else {
			delete_term_meta( $term_id, self::META_KEY );
		}
	}

	/**
	 * @param int[] $term_ids
	 * @return int[] Only the ids that are not locked.
	 */
	public static function filter_unlocked( array $term_ids ) {
		return array_values(
			array_filter(
				$term_ids,
				function ( $term_id ) {
					return ! self::is_locked( $term_id );
				}
			)
		);
	}
}
