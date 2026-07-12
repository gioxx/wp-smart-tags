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
		register_setting(
			self::OPTION_GROUP,
			'wpto_ai_language',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);
		register_setting(
			self::OPTION_GROUP,
			'wpto_cleanup_on_uninstall',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);
	}

	public static function sanitize_checkbox( $value ) {
		return empty( $value ) ? 0 : 1;
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

	public static function get_ai_language() {
		return get_option( 'wpto_ai_language', '' );
	}

	public static function get_cleanup_on_uninstall() {
		return (bool) get_option( 'wpto_cleanup_on_uninstall', true );
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'AI Tags Optimizer - Settings', 'ai-tags-optimizer' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION_GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wpto_api_key"><?php esc_html_e( 'Anthropic API Key', 'ai-tags-optimizer' ); ?></label></th>
						<td>
							<input type="password" id="wpto_api_key" name="wpto_api_key" value="<?php echo esc_attr( self::get_api_key() ); ?>" class="regular-text" autocomplete="off" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpto_model"><?php esc_html_e( 'Model', 'ai-tags-optimizer' ); ?></label></th>
						<td>
							<input type="text" id="wpto_model" name="wpto_model" value="<?php echo esc_attr( self::get_model() ); ?>" class="regular-text" placeholder="claude-haiku-4-5" />
							<p class="description"><?php esc_html_e( 'E.g. claude-haiku-4-5, claude-sonnet-5.', 'ai-tags-optimizer' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpto_batch_size"><?php esc_html_e( 'Batch size', 'ai-tags-optimizer' ); ?></label></th>
						<td>
							<input type="number" min="10" max="500" id="wpto_batch_size" name="wpto_batch_size" value="<?php echo esc_attr( self::get_batch_size() ); ?>" class="small-text" />
							<p class="description"><?php esc_html_e( 'Number of tags sent per API call (10-500).', 'ai-tags-optimizer' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpto_ai_language"><?php esc_html_e( 'AI response language', 'ai-tags-optimizer' ); ?></label></th>
						<td>
							<input type="text" id="wpto_ai_language" name="wpto_ai_language" value="<?php echo esc_attr( self::get_ai_language() ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Italian, English...', 'ai-tags-optimizer' ); ?>" />
							<p class="description"><?php esc_html_e( 'Language Claude should use for the "reason" it gives on each suggestion. Leave blank to let it match the language of your tag names automatically. This only affects Claude\'s output, not the plugin\'s own interface language.', 'ai-tags-optimizer' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Full cleanup on uninstall', 'ai-tags-optimizer' ); ?></th>
						<td>
							<input type="hidden" name="wpto_cleanup_on_uninstall" value="0" />
							<label>
								<input type="checkbox" id="wpto_cleanup_on_uninstall" name="wpto_cleanup_on_uninstall" value="1" <?php checked( self::get_cleanup_on_uninstall() ); ?> />
								<?php esc_html_e( 'Remove all plugin data (database tables and settings) when the plugin is deleted.', 'ai-tags-optimizer' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Uncheck this if you want batch history and suggestions to survive a delete-and-reinstall of the plugin.', 'ai-tags-optimizer' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
