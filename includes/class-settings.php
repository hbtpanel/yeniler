<?php
/**
 * Settings class.
 *
 * @package HBT_Trendyol_Profit_Tracker
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class HBT_Settings
 *
 * Manages plugin settings via the WordPress Options API.
 */
class HBT_Settings {

	/**
	 * Plugin option prefix.
	 *
	 * @var string
	 */
	private const PREFIX = 'hbt_tpt_';

	/**
	 * Default values for each setting.
	 *
	 * @var array
	 */
	private array $defaults = array(
		'default_vat_rate'            => 20,
		'cron_order_interval'         => 30,
		'cron_currency_interval'      => 60,
		'notification_loss_alert'     => true,
		'notification_cost_missing'   => true,
		'export_default_format'       => 'csv',
		'currency_cache_minutes'      => 60,
		'encryption_key_salt'         => '',
		'critical_loss_threshold'     => -10,
		'pdf_orientation'             => 'landscape',
		'cron_financial_interval'     => 360,
		'cron_product_interval'       => 1440,
	);

	/**
	 * Get a setting value.
	 *
	 * @param  string $key     Setting key (without prefix).
	 * @param  mixed  $default Override default value.
	 * @return mixed
	 */
	public function get( string $key, $default = null ) {
		$fallback = ( $default !== null ) ? $default : ( $this->defaults[ $key ] ?? null );
		return get_option( self::PREFIX . $key, $fallback );
	}

	/**
	 * Set a setting value.
	 *
	 * @param  string $key   Setting key (without prefix).
	 * @param  mixed  $value Value to store.
	 * @return bool
	 */
	public function set( string $key, $value ): bool {
		return update_option( self::PREFIX . $key, $value );
	}

	/**
	 * Get all settings as an associative array.
	 *
	 * @return array
	 */
	public function get_all(): array {
		$result = array();
		foreach ( array_keys( $this->defaults ) as $key ) {
			$result[ $key ] = $this->get( $key );
		}
		return $result;
	}

	/**
	 * Register settings with the WordPress Settings API.
	 * Called on admin_init.
	 */
	public function register_settings(): void {
		// General.
		register_setting( 'hbt_tpt_general', self::PREFIX . 'default_vat_rate', array( 'sanitize_callback' => 'absint' ) );
		register_setting( 'hbt_tpt_general', self::PREFIX . 'currency_cache_minutes', array( 'sanitize_callback' => 'absint' ) );

		// Sync.
		register_setting( 'hbt_tpt_sync', self::PREFIX . 'cron_order_interval', array( 'sanitize_callback' => 'absint' ) );
		register_setting( 'hbt_tpt_sync', self::PREFIX . 'cron_currency_interval', array( 'sanitize_callback' => 'absint' ) );
		register_setting( 'hbt_tpt_sync', self::PREFIX . 'cron_financial_interval', array( 'sanitize_callback' => 'absint' ) );
		register_setting( 'hbt_tpt_sync', self::PREFIX . 'cron_product_interval', array( 'sanitize_callback' => 'absint' ) );

		// Notifications.
		register_setting( 'hbt_tpt_notifications', self::PREFIX . 'notification_loss_alert', array( 'sanitize_callback' => 'rest_sanitize_boolean' ) );
		register_setting( 'hbt_tpt_notifications', self::PREFIX . 'notification_cost_missing', array( 'sanitize_callback' => 'rest_sanitize_boolean' ) );
		register_setting( 'hbt_tpt_notifications', self::PREFIX . 'critical_loss_threshold', array( 'sanitize_callback' => 'floatval' ) );

		// Export.
		register_setting(
			'hbt_tpt_export',
			self::PREFIX . 'export_default_format',
			array(
				'sanitize_callback' => function ( $val ) {
					return in_array( $val, array( 'csv', 'excel', 'pdf' ), true ) ? $val : 'csv';
				},
			)
		);
		register_setting(
			'hbt_tpt_export',
			self::PREFIX . 'pdf_orientation',
			array(
				'sanitize_callback' => function ( $val ) {
					return in_array( $val, array( 'portrait', 'landscape' ), true ) ? $val : 'landscape';
				},
			)
		);
	}

	/**
	 * Save settings from a POST request (AJAX).
	 *
	 * @param  array $data POST data.
	 * @return bool
	 */
	public function save_from_post( array $data ): bool {
		$sanitizers = array(
			'default_vat_rate'          => 'absint',
			'cron_order_interval'       => 'absint',
			'cron_currency_interval'    => 'absint',
			'cron_financial_interval'   => 'absint',
			'cron_product_interval'     => 'absint',
			'currency_cache_minutes'    => 'absint',
			'critical_loss_threshold'   => 'floatval',
			'notification_loss_alert'   => 'rest_sanitize_boolean',
			'notification_cost_missing' => 'rest_sanitize_boolean',
			'export_default_format'     => 'sanitize_text_field',
			'pdf_orientation'           => 'sanitize_text_field',
		);

		foreach ( $sanitizers as $key => $callback ) {
			if ( isset( $data[ $key ] ) ) {
				$this->set( $key, $callback( $data[ $key ] ) );
			}
		}

		return true;
	}
}
