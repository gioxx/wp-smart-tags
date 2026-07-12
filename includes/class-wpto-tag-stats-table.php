<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class WPTO_Tag_Stats_Table extends WP_List_Table {

	const PER_PAGE = 20;

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'tag',
				'plural'   => 'tags',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'cb'    => '<input type="checkbox" />',
			'name'  => __( 'Name', 'ai-tags-optimizer' ),
			'slug'  => __( 'Slug', 'ai-tags-optimizer' ),
			'count' => __( 'Assigned posts', 'ai-tags-optimizer' ),
		);
	}

	protected function get_sortable_columns() {
		return array(
			'name'  => array( 'name', false ),
			'count' => array( 'count', true ),
		);
	}

	protected function get_bulk_actions() {
		return array(
			'prepare_merge' => __( 'Prepare merge', 'ai-tags-optimizer' ),
			'delete'        => __( 'Delete', 'ai-tags-optimizer' ),
		);
	}

	public function no_items() {
		esc_html_e( 'No tags found.', 'ai-tags-optimizer' );
	}

	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="tag_id[]" value="%d" />', $item['id'] );
	}

	public function column_name( $item ) {
		$edit_url = add_query_arg(
			array(
				'action'    => 'edit',
				'taxonomy'  => 'post_tag',
				'tag_ID'    => $item['id'],
				'post_type' => 'post',
			),
			admin_url( 'edit-tags.php' )
		);

		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'            => WPTO_Admin_Page::MAIN_SLUG,
					'tab'             => 'stats',
					'wpto_delete_tag' => $item['id'],
				),
				admin_url( 'edit.php' )
			),
			'wpto_delete_tag_' . $item['id']
		);

		$row_actions = array(
			'edit'   => sprintf( '<a href="%s">%s</a>', esc_url( $edit_url ), esc_html__( 'Edit', 'ai-tags-optimizer' ) ),
			'delete' => sprintf(
				'<a href="%s" class="submitdelete" onclick="return confirm(\'%s\');">%s</a>',
				esc_url( $delete_url ),
				esc_js( __( 'Delete this tag? This cannot be undone.', 'ai-tags-optimizer' ) ),
				esc_html__( 'Delete', 'ai-tags-optimizer' )
			),
		);

		return sprintf(
			'<a href="%s"><strong>%s</strong></a>%s',
			esc_url( $edit_url ),
			esc_html( $item['name'] ),
			$this->row_actions( $row_actions )
		);
	}

	public function column_slug( $item ) {
		return esc_html( $item['slug'] );
	}

	public function column_count( $item ) {
		$posts_url = add_query_arg( array( 'tag' => $item['slug'] ), admin_url( 'edit.php' ) );

		return sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( $posts_url ),
			esc_html( number_format_i18n( $item['count'] ) )
		);
	}

	protected function column_default( $item, $column_name ) {
		return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
	}

	public function prepare_items() {
		$search  = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_key( $_REQUEST['orderby'] ) : 'count';
		$order   = isset( $_REQUEST['order'] ) ? strtolower( sanitize_key( $_REQUEST['order'] ) ) : 'desc';

		if ( ! in_array( $orderby, array( 'name', 'count' ), true ) ) {
			$orderby = 'count';
		}
		if ( ! in_array( $order, array( 'asc', 'desc' ), true ) ) {
			$order = 'desc';
		}

		$per_page     = $this->get_items_per_page( 'wpto_tags_per_page', self::PER_PAGE );
		$current_page = $this->get_pagenum();

		$total_items = (int) wp_count_terms(
			array(
				'taxonomy'   => 'post_tag',
				'hide_empty' => true,
				'search'     => $search,
			)
		);

		$terms = get_terms(
			array(
				'taxonomy'   => 'post_tag',
				'hide_empty' => true,
				'search'     => $search,
				'orderby'    => $orderby,
				'order'      => $order,
				'number'     => $per_page,
				'offset'     => ( $current_page - 1 ) * $per_page,
			)
		);

		if ( is_wp_error( $terms ) ) {
			$terms = array();
		}

		$this->items = array();
		foreach ( $terms as $term ) {
			$this->items[] = array(
				'id'    => (int) $term->term_id,
				'name'  => $term->name,
				'slug'  => $term->slug,
				'count' => (int) $term->count,
			);
		}

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total_items / $per_page ),
			)
		);
	}
}
