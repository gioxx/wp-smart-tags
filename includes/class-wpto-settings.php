<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPTO_Settings {

	const OPTION_GROUP = 'wpto_settings';

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	public static function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			'wpto_api_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);
		register_setting(
			self::OPTION_GROUP,
			'wpto_model',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'claude-haiku-4-5',
			)
		);
		register_setting(
			self::OPTION_GROUP,
			'wpto_batch_size',
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( __CLASS__, 'sanitize_batch_size' ),
				'default'           => 150,
			)
		);
	}

	public static function sanitize_batch_size( $value ) {
		$value = absint( $value );
		if ( $value < 10 ) {
			$value = 10;
		}
		if ( $value > 500 ) {
			$value = 500;
		}
		return $value;
	}

	public static function get_api_key() {
		return get_option( 'wpto_api_key', '' );
	}

	public static function get_model() {
		return get_option( 'wpto_model', 'claude-haiku-4-5' );
	}

	public static function get_batch_size() {
		return (int) get_option( 'wpto_batch_size', 150 );
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Tags Optimizer - Impostazioni', 'wp-tags-optimizer' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION_GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wpto_api_key"><?php esc_html_e( 'Anthropic API Key', 'wp-tags-optimizer' ); ?></label></th>
						<td>
							<input type="password" id="wpto_api_key" name="wpto_api_key" value="<?php echo esc_attr( self::get_api_key() ); ?>" class="regular-text" autocomplete="off" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpto_model"><?php esc_html_e( 'Modello', 'wp-tags-optimizer' ); ?></label></th>
						<td>
							<input type="text" id="wpto_model" name="wpto_model" value="<?php echo esc_attr( self::get_model() ); ?>" class="regular-text" placeholder="claude-haiku-4-5" />
							<p class="description"><?php esc_html_e( 'Es. claude-haiku-4-5, claude-sonnet-5.', 'wp-tags-optimizer' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpto_batch_size"><?php esc_html_e( 'Dimensione batch', 'wp-tags-optimizer' ); ?></label></th>
						<td>
							<input type="number" min="10" max="500" id="wpto_batch_size" name="wpto_batch_size" value="<?php echo esc_attr( self::get_batch_size() ); ?>" class="small-text" />
							<p class="description"><?php esc_html_e( 'Numero di tag inviati per ogni chiamata API (10-500).', 'wp-tags-optimizer' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
