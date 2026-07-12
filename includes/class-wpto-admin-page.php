<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPTO_Admin_Page {

	const MAIN_SLUG = 'wpto-tags-optimizer';
	const SETTINGS_SLUG = 'wpto-settings';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_ajax_wpto_delete_unused', array( __CLASS__, 'ajax_delete_unused' ) );
		add_action( 'wp_ajax_wpto_suggestion_action', array( __CLASS__, 'ajax_suggestion_action' ) );
		add_action( 'wp_ajax_wpto_recount_tags', array( __CLASS__, 'ajax_recount_tags' ) );
	}

	public static function register_menu() {
		add_management_page(
			__( 'AI Tags Optimizer', 'ai-tags-optimizer' ),
			__( 'AI Tags Optimizer', 'ai-tags-optimizer' ),
			'manage_options',
			self::MAIN_SLUG,
			array( __CLASS__, 'render_main_page' )
		);

		add_management_page(
			__( 'AI Tags Optimizer - Settings', 'ai-tags-optimizer' ),
			__( 'AI Tags Optimizer - Settings', 'ai-tags-optimizer' ),
			'manage_options',
			self::SETTINGS_SLUG,
			array( 'WPTO_Settings', 'render_page' )
		);
	}

	public static function enqueue_assets( $hook ) {
		if ( 'tools_page_' . self::MAIN_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style( 'wpto-admin', WPTO_PLUGIN_URL . 'assets/admin.css', array(), WPTO_VERSION );
		wp_enqueue_script( 'wpto-admin', WPTO_PLUGIN_URL . 'assets/admin.js', array( 'jquery' ), WPTO_VERSION, true );

		wp_localize_script(
			'wpto-admin',
			'wptoData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wpto_admin_action' ),
				'i18n'    => array(
					'confirmDelete' => __( 'Delete the selected tags?', 'ai-tags-optimizer' ),
					'confirmMerge'  => __( 'Confirm this merge? This action cannot be undone.', 'ai-tags-optimizer' ),
					'error'         => __( 'Something went wrong.', 'ai-tags-optimizer' ),
					'processing'    => __( 'Processing batch, this can take up to two minutes...', 'ai-tags-optimizer' ),
				),
			)
		);
	}

	public static function render_main_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$unused_terms = WPTO_Unused_Tags::get_unused_terms();
		$suggestions  = WPTO_Suggestions_Repo::get_suggestions( 'pending' );
		$rejected     = WPTO_Suggestions_Repo::get_suggestions( 'rejected' );
		$progress     = WPTO_Suggestions_Repo::get_batch_progress();
		$failed       = WPTO_Suggestions_Repo::get_failed_batches();

		$grouped = array(
			'near_duplicate'   => array(),
			'semantic_overlap' => array(),
			'low_usage_merge'  => array(),
		);
		foreach ( $suggestions as $suggestion ) {
			if ( isset( $grouped[ $suggestion['type'] ] ) ) {
				$grouped[ $suggestion['type'] ][] = $suggestion;
			}
		}

		$type_labels = array(
			'near_duplicate'   => __( 'Near-duplicates', 'ai-tags-optimizer' ),
			'semantic_overlap' => __( 'Semantic overlaps', 'ai-tags-optimizer' ),
			'low_usage_merge'  => __( 'Low-usage tags', 'ai-tags-optimizer' ),
		);

		?>
		<div class="wrap wpto-wrap">
			<h1><?php esc_html_e( 'AI Tags Optimizer', 'ai-tags-optimizer' ); ?></h1>

			<h2><?php esc_html_e( 'Unused tags (0 posts)', 'ai-tags-optimizer' ); ?></h2>
			<p>
				<button type="button" class="button" id="wpto-recount-tags"><?php esc_html_e( 'Recount tag counts', 'ai-tags-optimizer' ); ?></button>
				<span class="description"><?php esc_html_e( 'Fixes the per-tag post count if it has drifted out of sync with the actual associations (e.g. after an import).', 'ai-tags-optimizer' ); ?></span>
			</p>
			<?php if ( empty( $unused_terms ) ) : ?>
				<p><?php esc_html_e( 'No unused tags found.', 'ai-tags-optimizer' ); ?></p>
			<?php else : ?>
				<form id="wpto-unused-form">
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<td class="check-column"><input type="checkbox" id="wpto-select-all-unused" /></td>
								<th><?php esc_html_e( 'Name', 'ai-tags-optimizer' ); ?></th>
								<th><?php esc_html_e( 'Slug', 'ai-tags-optimizer' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $unused_terms as $term ) : ?>
								<tr>
									<th class="check-column"><input type="checkbox" class="wpto-unused-checkbox" value="<?php echo esc_attr( $term->term_id ); ?>" /></th>
									<td><?php echo esc_html( $term->name ); ?></td>
									<td><?php echo esc_html( $term->slug ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p>
						<button type="button" class="button button-secondary" id="wpto-delete-unused"><?php esc_html_e( 'Delete selected', 'ai-tags-optimizer' ); ?></button>
					</p>
				</form>
			<?php endif; ?>

			<hr />

			<h2><?php esc_html_e( 'AI Analysis', 'ai-tags-optimizer' ); ?></h2>
			<p>
				<button type="button" class="button button-primary" id="wpto-start-analysis" <?php disabled( $progress['pending'] > 0 ); ?>><?php esc_html_e( 'Start analysis', 'ai-tags-optimizer' ); ?></button>
				<button type="button" class="button" id="wpto-stop-analysis" <?php disabled( 0 === $progress['pending'] ); ?>><?php esc_html_e( 'Stop analysis', 'ai-tags-optimizer' ); ?></button>
			</p>
			<div id="wpto-progress" data-total="<?php echo esc_attr( $progress['total'] ); ?>" data-done="<?php echo esc_attr( $progress['done'] ); ?>" data-pending="<?php echo esc_attr( $progress['pending'] ); ?>">
				<?php if ( $progress['total'] > 0 ) : ?>
					<p><?php
						printf(
							/* translators: 1: batches done, 2: total batches */
							esc_html__( 'Batches completed: %1$d / %2$d', 'ai-tags-optimizer' ),
							(int) $progress['done'],
							(int) $progress['total']
						);
					?></p>
				<?php endif; ?>
			</div>
			<div id="wpto-current-status"></div>

			<h3><?php esc_html_e( 'Processing log', 'ai-tags-optimizer' ); ?></h3>
			<ul id="wpto-log">
				<?php foreach ( WPTO_Suggestions_Repo::get_recent_batches( 10 ) as $batch_row ) : ?>
					<li data-batch-id="<?php echo esc_attr( $batch_row['id'] ); ?>">
						<?php
						printf(
							/* translators: 1: batch id, 2: status, 3: timestamp */
							esc_html__( 'Batch #%1$d - %2$s (%3$s)', 'ai-tags-optimizer' ),
							(int) $batch_row['id'],
							esc_html( $batch_row['status'] ),
							esc_html( $batch_row['processed_at'] ? $batch_row['processed_at'] : $batch_row['created_at'] )
						);
						if ( ! empty( $batch_row['error_message'] ) ) {
							echo ' - ' . esc_html( $batch_row['error_message'] );
						}
						?>
					</li>
				<?php endforeach; ?>
			</ul>

			<?php if ( ! empty( $failed ) ) : ?>
				<h3><?php esc_html_e( 'Failed batches', 'ai-tags-optimizer' ); ?></h3>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Batch ID', 'ai-tags-optimizer' ); ?></th>
							<th><?php esc_html_e( 'Error', 'ai-tags-optimizer' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $failed as $batch ) : ?>
							<tr>
								<td><?php echo esc_html( $batch['id'] ); ?></td>
								<td><?php echo esc_html( $batch['error_message'] ); ?></td>
								<td><button type="button" class="button wpto-retry-batch" data-batch-id="<?php echo esc_attr( $batch['id'] ); ?>"><?php esc_html_e( 'Retry', 'ai-tags-optimizer' ); ?></button></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<hr />

			<h2><?php esc_html_e( 'Suggestions', 'ai-tags-optimizer' ); ?></h2>

			<?php foreach ( $grouped as $type => $rows ) : ?>
				<?php if ( empty( $rows ) ) { continue; } ?>
				<h3><?php echo esc_html( $type_labels[ $type ] ); ?></h3>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Source tag(s)', 'ai-tags-optimizer' ); ?></th>
							<th><?php esc_html_e( 'Target tag', 'ai-tags-optimizer' ); ?></th>
							<th><?php esc_html_e( 'Reason', 'ai-tags-optimizer' ); ?></th>
							<th><?php esc_html_e( 'Confidence', 'ai-tags-optimizer' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'ai-tags-optimizer' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<?php self::render_suggestion_row( $row ); ?>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endforeach; ?>

			<?php if ( ! empty( $rejected ) ) : ?>
				<h3><?php esc_html_e( 'Rejected suggestions', 'ai-tags-optimizer' ); ?></h3>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Source tag(s)', 'ai-tags-optimizer' ); ?></th>
							<th><?php esc_html_e( 'Target tag', 'ai-tags-optimizer' ); ?></th>
							<th><?php esc_html_e( 'Reason', 'ai-tags-optimizer' ); ?></th>
							<th><?php esc_html_e( 'Confidence', 'ai-tags-optimizer' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'ai-tags-optimizer' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rejected as $row ) : ?>
							<?php self::render_suggestion_row( $row, true ); ?>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function render_suggestion_row( array $row, $rejected = false ) {
		$source_ids   = json_decode( $row['source_term_ids'], true );
		$source_names = array();
		foreach ( (array) $source_ids as $sid ) {
			$t = get_term( $sid, 'post_tag' );
			if ( $t && ! is_wp_error( $t ) ) {
				$source_names[] = $t->name;
			}
		}
		$target_term = get_term( $row['target_term_id'], 'post_tag' );
		$target_name = ( $target_term && ! is_wp_error( $target_term ) ) ? $target_term->name : '';
		?>
		<tr>
			<td><?php echo esc_html( implode( ', ', $source_names ) ); ?></td>
			<td><?php echo esc_html( $target_name ); ?></td>
			<td><?php echo esc_html( $row['reason'] ); ?></td>
			<td><?php echo esc_html( round( $row['confidence'] * 100 ) . '%' ); ?></td>
			<td>
				<?php if ( $rejected ) : ?>
					<button type="button" class="button wpto-restore" data-id="<?php echo esc_attr( $row['id'] ); ?>"><?php esc_html_e( 'Restore', 'ai-tags-optimizer' ); ?></button>
				<?php else : ?>
					<button type="button" class="button button-primary wpto-approve" data-id="<?php echo esc_attr( $row['id'] ); ?>"><?php esc_html_e( 'Approve', 'ai-tags-optimizer' ); ?></button>
					<button type="button" class="button wpto-reject" data-id="<?php echo esc_attr( $row['id'] ); ?>"><?php esc_html_e( 'Reject', 'ai-tags-optimizer' ); ?></button>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	public static function ajax_delete_unused() {
		check_ajax_referer( 'wpto_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ai-tags-optimizer' ) ), 403 );
		}

		$term_ids = isset( $_POST['term_ids'] ) ? array_map( 'absint', (array) $_POST['term_ids'] ) : array();

		if ( empty( $term_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No tags selected.', 'ai-tags-optimizer' ) ) );
		}

		$result = WPTO_Unused_Tags::delete_terms( $term_ids );

		wp_send_json_success( $result );
	}

	public static function ajax_recount_tags() {
		check_ajax_referer( 'wpto_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ai-tags-optimizer' ) ), 403 );
		}

		$count = WPTO_Unused_Tags::recount_all();

		wp_send_json_success( array( 'count' => $count ) );
	}

	public static function ajax_suggestion_action() {
		check_ajax_referer( 'wpto_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ai-tags-optimizer' ) ), 403 );
		}

		$id     = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$action = isset( $_POST['do'] ) ? sanitize_key( $_POST['do'] ) : '';

		if ( ! $id || ! in_array( $action, array( 'approve', 'reject', 'restore' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'ai-tags-optimizer' ) ) );
		}

		$suggestion = WPTO_Suggestions_Repo::get_suggestion( $id );

		if ( ! $suggestion ) {
			wp_send_json_error( array( 'message' => __( 'Suggestion not found.', 'ai-tags-optimizer' ) ) );
		}

		if ( 'reject' === $action ) {
			WPTO_Suggestions_Repo::set_suggestion_status( $id, 'rejected' );
			wp_send_json_success();
		}

		if ( 'restore' === $action ) {
			WPTO_Suggestions_Repo::set_suggestion_status( $id, 'pending' );
			wp_send_json_success();
		}

		$result = WPTO_Merge_Handler::apply( $suggestion );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		WPTO_Suggestions_Repo::set_suggestion_status( $id, 'applied' );
		wp_send_json_success();
	}
}
