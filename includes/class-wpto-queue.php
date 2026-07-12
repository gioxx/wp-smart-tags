<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPTO_Queue {

	const CRON_HOOK = 'wpto_process_batch';
	const LOCK_KEY = 'wpto_batch_processing_lock';

	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'process_next_batch' ) );
		add_action( 'wp_ajax_wpto_start_analysis', array( __CLASS__, 'ajax_start_analysis' ) );
		add_action( 'wp_ajax_wpto_process_tick', array( __CLASS__, 'ajax_process_tick' ) );
		add_action( 'wp_ajax_wpto_stop_analysis', array( __CLASS__, 'ajax_stop_analysis' ) );
		add_action( 'wp_ajax_wpto_retry_batch', array( __CLASS__, 'ajax_retry_batch' ) );
	}

	public static function ajax_start_analysis() {
		check_ajax_referer( 'wpto_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'wp-tags-optimizer' ) ), 403 );
		}

		$progress = WPTO_Suggestions_Repo::get_batch_progress();

		// A run is already in progress (or waiting to be resumed): don't
		// re-enqueue every tag again, just let the caller resume ticking it.
		if ( $progress['pending'] > 0 ) {
			wp_send_json_success( self::build_status_payload() );
		}

		self::enqueue_all_tags();

		wp_send_json_success( self::build_status_payload() );
	}

	public static function ajax_process_tick() {
		check_ajax_referer( 'wpto_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'wp-tags-optimizer' ) ), 403 );
		}

		self::process_next_batch();

		wp_send_json_success( self::build_status_payload() );
	}

	public static function ajax_stop_analysis() {
		check_ajax_referer( 'wpto_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'wp-tags-optimizer' ) ), 403 );
		}

		WPTO_Suggestions_Repo::cancel_pending_batches();
		wp_clear_scheduled_hook( self::CRON_HOOK );

		wp_send_json_success( self::build_status_payload() );
	}

	public static function ajax_retry_batch() {
		check_ajax_referer( 'wpto_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'wp-tags-optimizer' ) ), 403 );
		}

		$batch_id = isset( $_POST['batch_id'] ) ? absint( $_POST['batch_id'] ) : 0;

		if ( ! $batch_id ) {
			wp_send_json_error( array( 'message' => __( 'Batch non valido.', 'wp-tags-optimizer' ) ) );
		}

		WPTO_Suggestions_Repo::retry_batch( $batch_id );
		self::schedule_next_run();

		wp_send_json_success( self::build_status_payload() );
	}

	private static function build_status_payload() {
		return array(
			'progress' => WPTO_Suggestions_Repo::get_batch_progress(),
			'log'      => WPTO_Suggestions_Repo::get_recent_batches( 10 ),
		);
	}

	private static function enqueue_all_tags() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'post_tag',
				'hide_empty' => true,
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return;
		}

		$tags = array();
		foreach ( $terms as $term ) {
			$tags[] = array(
				'id'    => (int) $term->term_id,
				'name'  => $term->name,
				'count' => (int) $term->count,
			);
		}

		$batch_size = WPTO_Settings::get_batch_size();
		$chunks     = array_chunk( $tags, $batch_size );

		foreach ( $chunks as $chunk ) {
			$term_ids = wp_list_pluck( $chunk, 'id' );
			WPTO_Suggestions_Repo::create_batch( $term_ids );
		}

		self::schedule_next_run();
	}

	private static function schedule_next_run() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + 5, self::CRON_HOOK );
		}
	}

	/**
	 * Process a single pending batch. Guarded by a transient lock so the
	 * WP-Cron fallback and the browser-driven "tick" requests never process
	 * the same batch twice at once.
	 */
	public static function process_next_batch() {
		if ( get_transient( self::LOCK_KEY ) ) {
			return;
		}

		set_transient( self::LOCK_KEY, 1, 5 * MINUTE_IN_SECONDS );

		try {
			self::do_process_next_batch();
		} finally {
			delete_transient( self::LOCK_KEY );
		}
	}

	private static function do_process_next_batch() {
		$batch = WPTO_Suggestions_Repo::get_next_pending_batch();

		if ( ! $batch ) {
			return;
		}

		$term_ids = json_decode( $batch['term_ids'], true );

		if ( ! is_array( $term_ids ) || empty( $term_ids ) ) {
			WPTO_Suggestions_Repo::mark_batch_failed( $batch['id'], __( 'Elenco tag del batch vuoto o non valido.', 'wp-tags-optimizer' ) );
			self::maybe_reschedule();
			return;
		}

		$tags = array();
		foreach ( $term_ids as $term_id ) {
			$term = get_term( $term_id, 'post_tag' );
			if ( $term && ! is_wp_error( $term ) ) {
				$tags[] = array(
					'id'    => (int) $term->term_id,
					'name'  => $term->name,
					'count' => (int) $term->count,
				);
			}
		}

		if ( empty( $tags ) ) {
			WPTO_Suggestions_Repo::mark_batch_failed( $batch['id'], __( 'Nessun tag valido trovato per questo batch.', 'wp-tags-optimizer' ) );
			self::maybe_reschedule();
			return;
		}

		$client = new WPTO_Api_Client();
		$result = $client->analyze_batch( $tags );

		if ( is_wp_error( $result ) ) {
			WPTO_Suggestions_Repo::mark_batch_failed( $batch['id'], $result->get_error_message() );
			self::maybe_reschedule();
			return;
		}

		WPTO_Suggestions_Repo::insert_suggestions( $batch['id'], $result );
		WPTO_Suggestions_Repo::mark_batch_done( $batch['id'] );

		self::maybe_reschedule();
	}

	private static function maybe_reschedule() {
		$next = WPTO_Suggestions_Repo::get_next_pending_batch();
		if ( $next ) {
			wp_schedule_single_event( time() + 5, self::CRON_HOOK );
		}
	}
}
