<?php
/**
 * Currency service class.
 *
 * @package HBT_Trendyol_Profit_Tracker
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class HBT_Currency_Service
 *
 * Fetches and caches USD/TRY exchange rates from TCMB (Central Bank of Turkey).
 */
class HBT_Currency_Service {

	/**
	 * Singleton instance.
	 *
	 * @var HBT_Currency_Service
	 */
	private static ?HBT_Currency_Service $instance = null;

	/**
	 * TCMB today XML URL.
	 *
	 * @var string
	 */
	private const TCMB_TODAY_URL = 'https://www.tcmb.gov.tr/kurlar/today.xml';

	/**
	 * TCMB historical XML URL pattern.
	 *
	 * @var string
	 */
	private const TCMB_HISTORY_URL = 'https://www.tcmb.gov.tr/kurlar/%s/%s.xml';

	/**
	 * Database instance.
	 *
	 * @var HBT_Database
	 */
	private HBT_Database $db;

	/**
	 * Get singleton instance.
	 *
	 * @return HBT_Currency_Service
	 */
	public static function instance(): HBT_Currency_Service {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->db = HBT_Database::instance();
	}

	// -------------------------------------------------------------------------
	// Main public methods
	// -------------------------------------------------------------------------

	/**
	 * Get the best USD/TRY rate for a given datetime using 3-layer strategy.
	 *
	 * Layer 1: Hourly cached rate.
	 * Layer 2: Daily TCMB rate.
	 * Layer 3: Most recent fallback rate from DB.
	 *
	 * @param  string $datetime MySQL datetime (Y-m-d H:i:s).
	 * @return object|null      Rate object with buying_rate, selling_rate, rate_type.
	 */
	public function get_rate_for_datetime( string $datetime ): ?object {
		$date = substr( $datetime, 0, 10 );
		$hour = (int) substr( $datetime, 11, 2 );

		// Layer 1 – hourly cache.
		$rate = $this->db->get_hourly_rate( $date, $hour );
		if ( $rate && $rate->rate_type === 'hourly' ) {
			return $rate;
		}

		// Layer 2 – daily TCMB.
		$daily = $this->fetch_daily_rate_from_tcmb( $date );
		if ( $daily ) {
			return $daily;
		}

		// Layer 3 – fallback.
		$fallback = $this->db->get_rate_for_datetime( $datetime );
		if ( $fallback ) {
			HBT_Notification_Manager::instance()->create_notification(
				'rate_error',
				__( 'Döviz Kuru Yaklaşık', 'hbt-trendyol-profit-tracker' ),
				sprintf(
					/* translators: %s: date string */
					__( '%s tarihi için kur bulunamadı, yaklaşık değer kullanıldı.', 'hbt-trendyol-profit-tracker' ),
					$date
				)
			);
			return $fallback;
		}

		return null;
	}

	/**
	 * Get the current USD/TRY rate (for dashboard display).
	 *
	 * Cached in WordPress transient for 5 minutes.
	 *
	 * @return object|null
	 */
	public function get_current_rate(): ?object {
		$transient_key = 'hbt_tpt_current_usd_rate';
		$cached        = get_transient( $transient_key );

		if ( $cached ) {
			return (object) $cached;
		}

		$today = gmdate( 'Y-m-d' );
		$rate  = $this->fetch_daily_rate_from_tcmb( $today );

		if ( $rate ) {
			set_transient( $transient_key, (array) $rate, 5 * MINUTE_IN_SECONDS );
		}

		return $rate;
	}

	/**
	 * Fetch and cache the daily rate for a given date from TCMB XML.
	 *
	 * @param  string $date Date (Y-m-d).
	 * @return object|null  Rate object or null.
	 */
	public function fetch_daily_rate_from_tcmb( string $date ): ?object {
		// Check existing daily cache.
		$existing = $this->db->get_hourly_rate( $date, null );
		if ( $existing ) {
			return $existing;
		}

		$rates = $this->fetch_from_tcmb_xml( $date );
		if ( ! $rates ) {
			return null;
		}

		$data = array(
			'rate_date'    => $date,
			'rate_hour'    => null,
			'buying_rate'  => $rates['buying'],
			'selling_rate' => $rates['selling'],
			'rate_type'    => 'daily',
			'source'       => 'TCMB',
		);

		$this->db->save_rate( $data );

		return (object) $data;
	}

	/**
	 * Update hourly rates (called by cron during business hours Mon–Fri 10–15).
	 */
	public function update_hourly_rates(): void {
		$day_of_week = (int) gmdate( 'N' ); // 1=Mon, 7=Sun
		$hour        = (int) gmdate( 'H' );

		if ( $day_of_week > 5 ) {
			return; // Weekend.
		}

		if ( $hour < 10 || $hour > 15 ) {
			return; // Outside business hours.
		}

		$today = gmdate( 'Y-m-d' );
		$rates = $this->fetch_from_tcmb_xml( $today );

		if ( ! $rates ) {
			// Fallback: save daily as hourly.
			$daily = $this->fetch_daily_rate_from_tcmb( $today );
			if ( $daily ) {
				$data = (array) $daily;
				$data['rate_hour'] = $hour;
				$data['rate_type'] = 'hourly';
				$this->db->save_rate( $data );
			}
			return;
		}

		$data = array(
			'rate_date'    => $today,
			'rate_hour'    => $hour,
			'buying_rate'  => $rates['buying'],
			'selling_rate' => $rates['selling'],
			'rate_type'    => 'hourly',
			'source'       => 'TCMB',
		);

		$this->db->save_rate( $data );
	}

	// -------------------------------------------------------------------------
	// TCMB XML fetcher
	// -------------------------------------------------------------------------

	/**
	 * Fetch USD/TRY rates from TCMB XML for a given date.
	 *
	 * Handles weekend adjustment: Saturday→Friday, Sunday→Friday.
	 *
	 * @param  string $date Date (Y-m-d).
	 * @return array|false  Array with 'buying' and 'selling' floats, or false.
	 */
	public function fetch_from_tcmb_xml( string $date ) {
		$timestamp   = strtotime( $date );
		$day_of_week = (int) gmdate( 'N', $timestamp );

		// Weekend adjustment.
		if ( 6 === $day_of_week ) {
			$timestamp -= DAY_IN_SECONDS;     // Saturday → Friday.
		} elseif ( 7 === $day_of_week ) {
			$timestamp -= 2 * DAY_IN_SECONDS; // Sunday → Friday.
		}

		$adjusted_date = gmdate( 'Y-m-d', $timestamp );
		$today         = gmdate( 'Y-m-d' );

		if ( $adjusted_date === $today ) {
			$url = self::TCMB_TODAY_URL;
		} else {
			$ym  = gmdate( 'Ym', $timestamp );
			$dmy = gmdate( 'dmY', $timestamp );
			$url = sprintf( self::TCMB_HISTORY_URL, $ym, $dmy );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array( 'Accept' => 'application/xml' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( '[HBT TPT] TCMB XML error: ' . $response->get_error_message() );
			return false;
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( $status !== 200 ) {
			error_log( "[HBT TPT] TCMB XML HTTP {$status}" );
			return false;
		}

		$xml_body = wp_remote_retrieve_body( $response );

		return $this->parse_tcmb_xml( $xml_body );
	}

	/**
	 * Parse TCMB XML body and extract USD rates.
	 *
	 * @param  string     $xml_body Raw XML string.
	 * @return array|false
	 */
	private function parse_tcmb_xml( string $xml_body ) {
		libxml_use_internal_errors( true );

		try {
			$xml = new SimpleXMLElement( $xml_body );
		} catch ( Exception $e ) {
			error_log( '[HBT TPT] XML parse error: ' . $e->getMessage() );
			return false;
		}

		foreach ( $xml->Currency as $currency ) {
			$code = (string) $currency->attributes()->CurrencyCode;
			if ( 'USD' === $code ) {
				$buying  = (float) str_replace( ',', '.', (string) $currency->ForexBuying );
				$selling = (float) str_replace( ',', '.', (string) $currency->ForexSelling );

				if ( $buying > 0 && $selling > 0 ) {
					return array(
						'buying'  => $buying,
						'selling' => $selling,
					);
				}
			}
		}

		return false;
	}
}
