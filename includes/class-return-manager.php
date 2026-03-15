<?php
/**
 * Return manager class.
 *
 * @package HBT_Trendyol_Profit_Tracker
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class HBT_Return_Manager
 *
 * Processes product returns and calculates net losses.
 */
class HBT_Return_Manager {

	/**
	 * Database instance.
	 *
	 * @var HBT_Database
	 */
	private HBT_Database $db;

	/**
	 * Currency service instance.
	 *
	 * @var HBT_Currency_Service
	 */
	private HBT_Currency_Service $currency_service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->db               = HBT_Database::instance();
		$this->currency_service = HBT_Currency_Service::instance();
	}

	// -------------------------------------------------------------------------
	// Return processing
	// -------------------------------------------------------------------------

	/**
	 * Process returns for a store.
	 *
	 * Fetches Cancelled/Returned orders from Trendyol API and saves new return records.
	 *
	 * @param int $store_id Store ID.
	 */
	public function process_returns( int $store_id ): void {
		$store = $this->db->get_store( $store_id );
		if ( ! $store ) {
			return;
		}

		$api    = new HBT_Trendyol_API( $store );
		$end_ms = time() * 1000;
		// Look back 90 days.
		$start_ms = ( time() - 90 * DAY_IN_SECONDS ) * 1000;

		$orders = $api->get_orders( $start_ms, $end_ms );

		if ( is_wp_error( $orders ) ) {
			error_log( '[HBT TPT] Return sync error: ' . $orders->get_error_message() );
			return;
		}

		foreach ( $orders as $order ) {
			if ( ! in_array( $order['status'], array( 'Cancelled', 'Returned', 'UnDelivered' ), true ) ) {
				continue;
			}

			// Find order in DB.
			$db_order = $this->get_db_order_by_trendyol_id( $store_id, (int) $order['trendyol_id'] );
			if ( ! $db_order ) {
				continue;
			}

			// Save one return record per item.
			foreach ( $order['items'] as $item ) {
				// Check if already saved.
				$exists = $this->return_exists( (int) $db_order->id, (string) $item['barcode'] );
				if ( $exists ) {
					continue;
				}

				$return_id = $this->db->save_return(
					array(
						'store_id'      => $store_id,
						'order_id'      => (int) $db_order->id,
						'order_number'  => $order['order_number'],
						'return_date'   => current_time( 'mysql' ),
						'barcode'       => sanitize_text_field( $item['barcode'] ),
						'product_name'  => sanitize_text_field( $item['product_name'] ),
						'quantity'      => absint( $item['quantity'] ),
						'return_reason' => '',
						'return_type'   => 'customer',
						'refund_amount' => (float) $item['line_total'],
						'status'        => 'pending',
					)
				);

				if ( $return_id ) {
					$this->calculate_return_loss( (int) $return_id );
				}
			}
		}
	}

	/**
	 * Calculate net loss for a return.
	 *
	 * @param int $return_id Return DB ID.
	 */
	public function calculate_return_loss( int $return_id ): void {
		$return = $this->db->get_returns( array() );
		// We need the individual return – use direct query.
		global $wpdb;
		$return = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}hbt_returns WHERE id = %d", $return_id ) );

		if ( ! $return ) {
			return;
		}

		// Get order for rate reference.
		$order = $this->db->get_order( (int) $return->order_id );
		if ( ! $order ) {
			return;
		}

		// Get USD rate at order time.
		$rate_obj = $this->currency_service->get_rate_for_datetime( $order->order_date );
		$usd_rate = $rate_obj ? (float) $rate_obj->buying_rate : 0.0;

		// Get product cost.
		$product_cost = $this->db->get_product_cost_by_barcode( (int) $return->store_id, (string) $return->barcode );

		$cost_usd = $product_cost ? (float) $product_cost->cost_usd : 0.0;
		$cost_tl  = $cost_usd * $usd_rate * (int) $return->quantity;

		$shipping_cost      = 0.0; // Typically flat fee – left for manual entry.
		$commission_refund  = (float) $return->commission_refund;
		$refund_amount      = (float) $return->refund_amount;

		if ( (int) $return->product_reusable === 1 ) {
			$net_loss = $shipping_cost + ( 0 - $commission_refund );
		} else {
			$net_loss = $refund_amount + $cost_tl + $shipping_cost - $commission_refund;
		}

		$wpdb->update(
			$wpdb->prefix . 'hbt_returns',
			array(
				'cost_usd'     => $cost_usd,
				'cost_tl'      => round( $cost_tl, 2 ),
				'shipping_cost' => $shipping_cost,
				'net_loss'     => round( $net_loss, 2 ),
			),
			array( 'id' => $return_id )
		);
	}

	/**
	 * Get return statistics.
	 *
	 * @param  int    $store_id   Store ID (0 = all).
	 * @param  string $start_date Start date (Y-m-d).
	 * @param  string $end_date   End date (Y-m-d).
	 * @return array
	 */
	public function get_return_stats( int $store_id, string $start_date, string $end_date ): array {
		global $wpdb;

		$where  = 'return_date BETWEEN %s AND %s';
		$params = array( $start_date . ' 00:00:00', $end_date . ' 23:59:59' );

		if ( $store_id > 0 ) {
			$where   .= ' AND store_id = %d';
			$params[] = $store_id;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$stats = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS total_returns, SUM(refund_amount) AS total_refund, SUM(net_loss) AS total_loss FROM {$wpdb->prefix}hbt_returns WHERE {$where}", ...$params ) );

		// Total orders for return rate.
		$order_where  = 'order_date BETWEEN %s AND %s';
		$order_params = array( $start_date . ' 00:00:00', $end_date . ' 23:59:59' );
		if ( $store_id > 0 ) {
			$order_where   .= ' AND store_id = %d';
			$order_params[] = $store_id;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total_orders = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}hbt_orders WHERE {$order_where}", ...$order_params ) );

		$total_returns = (int) ( $stats->total_returns ?? 0 );
		$return_rate   = ( $total_orders > 0 ) ? round( ( $total_returns / $total_orders ) * 100, 2 ) : 0.0;

		// Most returned products.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$top_returned = $wpdb->get_results( $wpdb->prepare( "SELECT barcode, product_name, SUM(quantity) AS total_qty FROM {$wpdb->prefix}hbt_returns WHERE {$where} GROUP BY barcode ORDER BY total_qty DESC LIMIT 10", ...$params ) );

		return array(
			'total_returns' => $total_returns,
			'total_refund'  => (float) ( $stats->total_refund ?? 0 ),
			'total_loss'    => (float) ( $stats->total_loss ?? 0 ),
			'return_rate'   => $return_rate,
			'top_returned'  => $top_returned ?: array(),
		);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Get order from DB by Trendyol ID.
	 *
	 * @param  int $store_id    Store ID.
	 * @param  int $trendyol_id Trendyol order ID.
	 * @return object|null
	 */
	private function get_db_order_by_trendyol_id( int $store_id, int $trendyol_id ): ?object {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}hbt_orders WHERE store_id = %d AND trendyol_id = %d",
				$store_id,
				$trendyol_id
			)
		);
	}

	/**
	 * Check if a return record already exists.
	 *
	 * @param  int    $order_id Order DB ID.
	 * @param  string $barcode  Barcode.
	 * @return bool
	 */
	private function return_exists( int $order_id, string $barcode ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}hbt_returns WHERE order_id = %d AND barcode = %s LIMIT 1",
				$order_id,
				$barcode
			)
		);
	}
}
