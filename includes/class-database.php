<?php
/**
 * Database management class.
 *
 * Full, copy-paste ready version.
 *
 * @package HBT_Trendyol_Profit_Tracker
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class HBT_Database
 *
 * Handles creation and CRUD operations for all plugin database tables.
 */
class HBT_Database {

	/**
	 * Singleton instance.
	 *
	 * @var HBT_Database|null
	 */
	private static ?HBT_Database $instance = null;

	/**
	 * WordPress database object.
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Encryption key derived from WordPress auth salt (lazy-initialised).
	 *
	 * @var string|null
	 */
	private ?string $encryption_key = null;

	/**
	 * Get singleton instance.
	 *
	 * @return HBT_Database
	 */
	public static function instance(): HBT_Database {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	/**
	 * Get (or lazily initialise) the encryption key.
	 *
	 * Deferred until first use so that wp_salt() is only called after
	 * WordPress has fully loaded pluggable.php.
	 *
	 * @return string
	 */
	private function get_encryption_key(): string {
		if ( null === $this->encryption_key ) {
			$this->encryption_key = substr( hash( 'sha256', wp_salt( 'auth' ) ), 0, 32 );
		}
		return $this->encryption_key;
	}

	// -------------------------------------------------------------------------
	// Table creation
	// -------------------------------------------------------------------------

	/**
	 * Create all plugin tables.
	 */
	public function create_tables(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $this->wpdb->get_charset_collate();
		$prefix          = $this->wpdb->prefix;

		$tables = array(
			$this->get_stores_schema( $prefix, $charset_collate ),
			$this->get_product_costs_schema( $prefix, $charset_collate ),
			$this->get_shipping_costs_schema( $prefix, $charset_collate ),
			$this->get_orders_schema( $prefix, $charset_collate ),
			$this->get_order_items_schema( $prefix, $charset_collate ),
			$this->get_returns_schema( $prefix, $charset_collate ),
			$this->get_currency_rates_schema( $prefix, $charset_collate ),
			$this->get_financial_transactions_schema( $prefix, $charset_collate ),
			$this->get_notifications_schema( $prefix, $charset_collate ),
			$this->get_ad_expenses_schema( $prefix, $charset_collate ),
			$this->get_sync_logs_schema( $prefix, $charset_collate ),
			$this->get_avantajli_arsiv_schema( $prefix, $charset_collate ),
			$this->get_plus_simulator_arsiv_schema( $prefix, $charset_collate ),
		);

		foreach ( $tables as $sql ) {
			dbDelta( $sql );
		}
	}

	/** @return string */
	private function get_stores_schema( string $prefix, string $charset_collate ): string {
		return "CREATE TABLE {$prefix}hbt_stores (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			store_name VARCHAR(255) NOT NULL,
			supplier_id VARCHAR(100) NOT NULL,
			api_key TEXT NOT NULL,
			api_secret TEXT NOT NULL,
			is_active TINYINT(1) DEFAULT 1,
			last_order_sync DATETIME NULL,
			last_finance_sync DATETIME NULL,
			last_product_sync DATETIME NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) $charset_collate;";
	}

	/** @return string */
	private function get_product_costs_schema( string $prefix, string $charset_collate ): string {
		return "CREATE TABLE {$prefix}hbt_product_costs (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			store_id BIGINT UNSIGNED NOT NULL,
			barcode VARCHAR(100) NOT NULL,
			product_name VARCHAR(500),
			sku VARCHAR(100),
			trendyol_id BIGINT NULL,
			category_id BIGINT NULL,
			category_name VARCHAR(255),
			cost_usd DECIMAL(10,4) NOT NULL DEFAULT 0,
			desi DECIMAL(5,2) NULL,
			vat_rate DECIMAL(5,2) DEFAULT 20.00,
			image_url VARCHAR(500) NULL,
			is_active TINYINT(1) DEFAULT 1,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY unique_product (store_id, barcode)
		) $charset_collate;";
	}

	/** @return string */
	private function get_shipping_costs_schema( string $prefix, string $charset_collate ): string {
		return "CREATE TABLE {$prefix}hbt_shipping_costs (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			store_id BIGINT UNSIGNED NOT NULL,
			shipping_company VARCHAR(255) NOT NULL,
			price_min DECIMAL(10,2) NULL DEFAULT NULL,
			price_max DECIMAL(10,2) NULL DEFAULT NULL,
			cost_tl DECIMAL(10,2) NOT NULL,
			effective_from DATE NOT NULL,
			effective_to DATE NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) $charset_collate;";
	}

	/** @return string */
	private function get_orders_schema( string $prefix, string $charset_collate ): string {
		return "CREATE TABLE {$prefix}hbt_orders (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			store_id BIGINT UNSIGNED NOT NULL,
			order_number VARCHAR(100) NOT NULL,
			trendyol_id BIGINT NOT NULL,
			order_date DATETIME NOT NULL,
			status VARCHAR(50) NOT NULL,
			customer_name VARCHAR(255),
			shipping_city VARCHAR(100),
			gross_amount DECIMAL(10,2) DEFAULT 0,
			total_discount DECIMAL(10,2) DEFAULT 0,
			total_price DECIMAL(10,2) DEFAULT 0,
			vat_amount DECIMAL(10,2) DEFAULT 0,
			price_excl_vat DECIMAL(10,2) DEFAULT 0,
			total_cost_usd DECIMAL(10,4) DEFAULT 0,
			total_cost_tl DECIMAL(10,2) DEFAULT 0,
			total_commission DECIMAL(10,2) DEFAULT 0,
			total_shipping DECIMAL(10,2) DEFAULT 0,
			total_other_exp DECIMAL(10,2) DEFAULT 0,
			net_profit DECIMAL(10,2) DEFAULT 0,
			profit_margin DECIMAL(5,2) DEFAULT 0,
			usd_rate DECIMAL(10,4) NOT NULL DEFAULT 0,
			usd_rate_type VARCHAR(20) DEFAULT 'daily',
			cargo_provider VARCHAR(100),
			cargo_tracking VARCHAR(100),
			is_calculated TINYINT(1) DEFAULT 0,
			synced_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			calculated_at DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY unique_order (store_id, trendyol_id),
			KEY idx_date (order_date),
			KEY idx_status (status),
			KEY idx_store_date (store_id, order_date)
		) $charset_collate;";
	}

	/** @return string */
	private function get_order_items_schema( string $prefix, string $charset_collate ): string {
		return "CREATE TABLE {$prefix}hbt_order_items (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id BIGINT UNSIGNED NOT NULL,
			store_id BIGINT UNSIGNED NOT NULL,
			barcode VARCHAR(100),
			sku VARCHAR(100),
			product_name VARCHAR(500),
			quantity INT UNSIGNED DEFAULT 1,
			unit_price DECIMAL(10,2) DEFAULT 0,
			line_total DECIMAL(10,2) DEFAULT 0,
			discount DECIMAL(10,2) DEFAULT 0,
			vat_rate DECIMAL(5,2) DEFAULT 20.00,
			vat_amount DECIMAL(10,2) DEFAULT 0,
			price_excl_vat DECIMAL(10,2) DEFAULT 0,
			cost_usd DECIMAL(10,4) DEFAULT 0,
			cost_tl DECIMAL(10,2) DEFAULT 0,
			total_cost_tl DECIMAL(10,2) DEFAULT 0,
			commission_rate DECIMAL(5,2) DEFAULT 0,
			commission_amount DECIMAL(10,2) DEFAULT 0,
			shipping_cost DECIMAL(10,2) DEFAULT 0,
			other_expenses DECIMAL(10,2) DEFAULT 0,
			net_profit DECIMAL(10,2) DEFAULT 0,
			profit_margin DECIMAL(5,2) DEFAULT 0,
			has_cost_data TINYINT(1) DEFAULT 0,
			PRIMARY KEY (id),
			KEY idx_order (order_id),
			KEY idx_barcode (barcode),
			KEY idx_store (store_id)
		) $charset_collate;";
	}

	/** @return string */
	private function get_returns_schema( string $prefix, string $charset_collate ): string {
		return "CREATE TABLE {$prefix}hbt_returns (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			store_id BIGINT UNSIGNED NOT NULL,
			order_id BIGINT UNSIGNED NOT NULL,
			order_number VARCHAR(100),
			return_date DATETIME NOT NULL,
			barcode VARCHAR(100),
			product_name VARCHAR(500),
			quantity INT UNSIGNED DEFAULT 1,
			return_reason VARCHAR(500),
			return_type VARCHAR(50) DEFAULT 'customer',
			refund_amount DECIMAL(10,2) DEFAULT 0,
			cost_usd DECIMAL(10,4) DEFAULT 0,
			cost_tl DECIMAL(10,2) DEFAULT 0,
			commission_refund DECIMAL(10,2) DEFAULT 0,
			shipping_cost DECIMAL(10,2) DEFAULT 0,
			net_loss DECIMAL(10,2) DEFAULT 0,
			product_reusable TINYINT(1) DEFAULT 1,
			status VARCHAR(50) DEFAULT 'pending',
			synced_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_order (order_id),
			KEY idx_store_date (store_id, return_date)
		) $charset_collate;";
	}

	/** @return string */
	private function get_currency_rates_schema( string $prefix, string $charset_collate ): string {
		return "CREATE TABLE {$prefix}hbt_currency_rates (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			rate_date DATE NOT NULL,
			rate_hour TINYINT NULL,
			buying_rate DECIMAL(10,4),
			selling_rate DECIMAL(10,4),
			rate_type VARCHAR(20) DEFAULT 'daily',
			source VARCHAR(50) DEFAULT 'TCMB',
			fetched_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY unique_rate (rate_date, rate_hour)
		) $charset_collate;";
	}

	/** @return string */
	private function get_financial_transactions_schema( string $prefix, string $charset_collate ): string {
		return "CREATE TABLE {$prefix}hbt_financial_transactions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			store_id BIGINT UNSIGNED NOT NULL,
			transaction_id VARCHAR(100),
			order_number VARCHAR(100),
			transaction_type VARCHAR(50) NOT NULL,
			amount DECIMAL(10,2),
			description VARCHAR(500),
			transaction_date DATETIME,
			synced_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_store_order (store_id, order_number),
			KEY idx_type (transaction_type),
			KEY idx_date (transaction_date),
			UNIQUE KEY unique_tx (store_id, transaction_id)
		) $charset_collate;";
	}

	/** @return string */
	private function get_notifications_schema( string $prefix, string $charset_collate ): string {
		return "CREATE TABLE {$prefix}hbt_notifications (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			notification_type VARCHAR(50) NOT NULL,
			title VARCHAR(255),
			message TEXT,
			related_id BIGINT NULL,
			is_read TINYINT(1) DEFAULT 0,
			is_dismissed TINYINT(1) DEFAULT 0,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_unread (is_read, is_dismissed)
		) $charset_collate;";
	}

	// -------------------------------------------------------------------------
	// Encryption helpers
	// -------------------------------------------------------------------------

	/**
	 * Encrypt a string.
	 *
	 * @param  string $value Plain text.
	 * @return string        Base64-encoded ciphertext.
	 */
	public function encrypt( string $value ): string {
		$iv        = openssl_random_pseudo_bytes( 16 );
		$encrypted = openssl_encrypt( $value, 'AES-256-CBC', $this->get_encryption_key(), 0, $iv );
		return base64_encode( $iv . $encrypted );
	}

	/**
	 * Decrypt a string.
	 *
	 * @param  string $value Base64-encoded ciphertext.
	 * @return string        Decrypted plain text.
	 */
	public function decrypt( string $value ): string {
		$data = base64_decode( $value, true );
		if ( false === $data || strlen( $data ) <= 16 ) {
			return '';
		}
		$iv  = substr( $data, 0, 16 );
		$enc = substr( $data, 16 );
		$dec = openssl_decrypt( $enc, 'AES-256-CBC', $this->get_encryption_key(), 0, $iv );
		return $dec !== false ? $dec : '';
	}

	// -------------------------------------------------------------------------
	// Store CRUD
	// -------------------------------------------------------------------------

	/**
	 * Save (insert or update) a store.
	 *
	 * @param  array $data Store data.
	 * @return int|false   Row ID or false on failure.
	 */
	public function save_store( array $data ) {
		$table = $this->wpdb->prefix . 'hbt_stores';

		if ( ! empty( $data['api_key'] ) ) {
			$data['api_key'] = $this->encrypt( $data['api_key'] );
		}
		if ( ! empty( $data['api_secret'] ) ) {
			$data['api_secret'] = $this->encrypt( $data['api_secret'] );
		}

		$data['updated_at'] = current_time( 'mysql' );

		if ( ! empty( $data['id'] ) ) {
			$id = absint( $data['id'] );
			unset( $data['id'] );
			$this->wpdb->update( $table, $data, array( 'id' => $id ) );
			return $id;
		}

		$data['created_at'] = current_time( 'mysql' );
		$this->wpdb->insert( $table, $data );
		return $this->wpdb->insert_id ?: false;
	}

	/**
	 * Get a single store by ID.
	 *
	 * @param  int        $id Store ID.
	 * @return object|null
	 */
	public function get_store( int $id ): ?object {
		$store = $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->wpdb->prefix}hbt_stores WHERE id = %d", $id )
		);

		if ( $store ) {
			$store->api_key    = $this->decrypt( $store->api_key );
			$store->api_secret = $this->decrypt( $store->api_secret );
		}

		return $store;
	}

	/**
	 * Get all stores.
	 *
	 * @param  bool $active_only Return only active stores.
	 * @return array
	 */
	public function get_stores( bool $active_only = false ): array {
		$where = $active_only ? 'WHERE is_active = 1' : '';
		$rows  = $this->wpdb->get_results( "SELECT * FROM {$this->wpdb->prefix}hbt_stores {$where} ORDER BY store_name" );

		foreach ( $rows as $row ) {
			$row->api_key    = $this->decrypt( $row->api_key );
			$row->api_secret = $this->decrypt( $row->api_secret );
		}

		return $rows ?: array();
	}

	/**
	 * Delete a store by ID.
	 *
	 * @param  int  $id Store ID.
	 * @return bool
	 */
	public function delete_store( int $id ): bool {
		return (bool) $this->wpdb->delete( $this->wpdb->prefix . 'hbt_stores', array( 'id' => $id ) );
	}

	/**
	 * Update a store (alias for save_store with id).
	 *
	 * @param  int   $id   Store ID.
	 * @param  array $data Fields to update.
	 * @return bool
	 */
	public function update_store( int $id, array $data ): bool {
		$data['id'] = $id;
		return (bool) $this->save_store( $data );
	}

	// -------------------------------------------------------------------------
	// Product cost CRUD
	// -------------------------------------------------------------------------

	/**
	 * Save or update a product cost record.
	 *
	 * @param  array $data Product cost data.
	 * @return int|false
	 */
	public function save_product_cost( array $data ) {
		$table              = $this->wpdb->prefix . 'hbt_product_costs';
		$data['updated_at'] = current_time( 'mysql' );

		if ( ! empty( $data['id'] ) ) {
			$id = absint( $data['id'] );
			unset( $data['id'] );
			$this->wpdb->update( $table, $data, array( 'id' => $id ) );
			return $id;
		}

		$existing = $this->get_product_cost_by_barcode( (int) $data['store_id'], (string) $data['barcode'] );
		if ( $existing ) {
			$this->wpdb->update( $table, $data, array( 'id' => $existing->id ) );
			return (int) $existing->id;
		}

		$data['created_at'] = current_time( 'mysql' );
		$this->wpdb->insert( $table, $data );
		return $this->wpdb->insert_id ?: false;
	}

	/**
	 * Get product cost by barcode.
	 *
	 * @param  int    $store_id Store ID.
	 * @param  string $barcode  Barcode.
	 * @return object|null
	 */
	public function get_product_cost_by_barcode( int $store_id, string $barcode ): ?object {
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->wpdb->prefix}hbt_product_costs WHERE store_id = %d AND barcode = %s",
				$store_id,
				$barcode
			)
		);
	}

	/**
	 * Get all product costs for a store with optional filters.
	 *
	 * @param  int   $store_id Store ID (0 = all).
	 * @param  array $args     Additional filters: has_cost, search.
	 * @return array
	 */
	public function get_product_costs( int $store_id = 0, array $args = array() ): array {
		$where   = array( '1=1' );
		$params  = array();

		if ( $store_id > 0 ) {
			$where[]  = 'store_id = %d';
			$params[] = $store_id;
		}

		if ( isset( $args['has_cost'] ) ) {
			$where[]  = 'cost_usd ' . ( $args['has_cost'] ? '> 0' : '= 0' );
		}

		if ( ! empty( $args['search'] ) ) {
			$where[]  = '(barcode LIKE %s OR product_name LIKE %s)';
			$like     = '%' . $this->wpdb->esc_like( $args['search'] ) . '%';
			$params[] = $like;
			$params[] = $like;
		}

		$sql = "SELECT * FROM {$this->wpdb->prefix}hbt_product_costs WHERE " . implode( ' AND ', $where ) . ' ORDER BY product_name';

		if ( $params ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return $this->wpdb->get_results( $this->wpdb->prepare( $sql, ...$params ) ) ?: array();
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $this->wpdb->get_results( $sql ) ?: array();
	}

	/**
	 * Update a product cost.
	 *
	 * @param  int   $id   Record ID.
	 * @param  array $data Data to update.
	 * @return bool
	 */
	public function update_product_cost( int $id, array $data ): bool {
		$data['updated_at'] = current_time( 'mysql' );
		return (bool) $this->wpdb->update( $this->wpdb->prefix . 'hbt_product_costs', $data, array( 'id' => $id ) );
	}

	/**
	 * Bulk import product costs from array.
	 *
	 * @param  int   $store_id Store ID.
	 * @param  array $rows     Array of rows with barcode, cost_usd, desi, vat_rate.
	 * @return int             Number of rows inserted/updated.
	 */
	public function bulk_import_costs( int $store_id, array $rows ): int {
		$count = 0;
		foreach ( $rows as $row ) {
			$row['store_id'] = $store_id;
			if ( $this->save_product_cost( $row ) ) {
				$count++;
			}
		}
		return $count;
	}

	// -------------------------------------------------------------------------
	// Shipping cost CRUD
	// -------------------------------------------------------------------------

	/**
	 * Save or update a shipping cost record.
	 *
	 * Accepts price_min / price_max (nullable) for TL-based rules.
	 *
	 * @param  array $data Shipping cost data.
	 * @return int|false
	 */
	public function save_shipping_cost( array $data ) {
		$table              = $this->wpdb->prefix . 'hbt_shipping_costs';
		$data['updated_at'] = current_time( 'mysql' );

		// Normalize empty price fields to null for correct DB NULL storage.
		if ( array_key_exists( 'price_min', $data ) && $data['price_min'] === '' ) {
			$data['price_min'] = null;
		}
		if ( array_key_exists( 'price_max', $data ) && $data['price_max'] === '' ) {
			$data['price_max'] = null;
		}

		if ( ! empty( $data['id'] ) ) {
			$id = absint( $data['id'] );
			unset( $data['id'] );
			$this->wpdb->update( $table, $data, array( 'id' => $id ) );
			return $id;
		}

		$data['created_at'] = current_time( 'mysql' );
		$this->wpdb->insert( $table, $data );
		return $this->wpdb->insert_id ?: false;
	}

	/**
	 * Get applicable shipping cost for given order_total/date.
	 *
	 * Behavior:
	 *  - Find a price-range rule (price_min/price_max) matching order_total.
	 *
	 * @param int    $store_id    Store ID.
	 * @param float  $order_total Order total (TL).
	 * @param string $order_date  MySQL date (Y-m-d) to check effective_from/effective_to.
	 * @return object|null
	 */
	public function get_shipping_cost( int $store_id, float $order_total, string $order_date ): ?object {
		$price_row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->wpdb->prefix}hbt_shipping_costs
				WHERE store_id = %d
				AND price_min IS NOT NULL
				AND ( (price_min <= %f AND price_max IS NOT NULL AND price_max >= %f) OR (price_min <= %f AND price_max IS NULL) )
				AND effective_from <= %s
				AND (effective_to IS NULL OR effective_to >= %s)
				ORDER BY effective_from DESC LIMIT 1",
				$store_id,
				$order_total,
				$order_total,
				$order_total,
				$order_date,
				$order_date
			)
		);

		return $price_row ?: null;
	}

	/**
	 * Get shipping cost by desi (legacy support).
	 *
	 * If you still use desi-based rules, this function queries any rule that was intended for desi.
	 * For modern setup we recommend price-based rules (get_shipping_cost).
	 *
	 * @param int    $store_id  Store ID.
	 * @param float  $total_desi Total desi.
	 * @param string $order_date Date (Y-m-d).
	 * @return object|null
	 */
	public function get_shipping_cost_for_desi( int $store_id, float $total_desi, string $order_date ): ?object {
		// If your shipping table had desi ranges, implement lookup here.
		// Fallback: find any rule for store and date and return first (compat).
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->wpdb->prefix}hbt_shipping_costs
				WHERE store_id = %d
				AND effective_from <= %s
				AND (effective_to IS NULL OR effective_to >= %s)
				ORDER BY effective_from DESC LIMIT 1",
				$store_id,
				$order_date,
				$order_date
			)
		);
		return $row ?: null;
	}

	/**
	 * Get all shipping costs.
	 *
	 * @param  int $store_id Store ID (0 = all).
	 * @return array
	 */
	public function get_shipping_costs( int $store_id = 0 ): array {
		if ( $store_id > 0 ) {
			return $this->wpdb->get_results(
				$this->wpdb->prepare(
					"SELECT * FROM {$this->wpdb->prefix}hbt_shipping_costs WHERE store_id = %d ORDER BY store_id, price_min",
					$store_id
				)
			) ?: array();
		}

		return $this->wpdb->get_results(
			"SELECT * FROM {$this->wpdb->prefix}hbt_shipping_costs ORDER BY store_id, price_min"
		) ?: array();
	}

	/**
	 * Delete a shipping cost record.
	 *
	 * @param  int  $id Record ID.
	 * @return bool
	 */
	public function delete_shipping_cost( int $id ): bool {
		return (bool) $this->wpdb->delete( $this->wpdb->prefix . 'hbt_shipping_costs', array( 'id' => $id ) );
	}

	// -------------------------------------------------------------------------
	// Order CRUD
	// -------------------------------------------------------------------------

	/**
	 * Save or update an order.
	 * Upsert by store_id + trendyol_id.
	 *
	 * @param  array $data Order data.
	 * @return array|false [ 'id' => int, 'action' => 'insert'|'update'|'none' ]
	 */
	public function save_order( array $data ) {
		$table = $this->wpdb->prefix . 'hbt_orders';

		if ( ! empty( $data['id'] ) ) {
			$id = absint( $data['id'] );
			unset( $data['id'] );
			$this->wpdb->update( $table, $data, array( 'id' => $id ) );
			return array( 'id' => $id, 'action' => 'update' );
		}

		// YENİ: trendyol_id çok büyük bir sayı olduğu için %s (string) olarak kontrol ediyoruz!
		$existing = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT id, status, total_price, gross_amount, total_discount FROM {$table} WHERE store_id = %d AND trendyol_id = %s",
				$data['store_id'],
				$data['trendyol_id']
			)
		);

		if ( $existing ) {
			if ( $existing->status === $data['status'] && 
				 (float)$existing->total_price === (float)$data['total_price'] && 
				 (float)$existing->gross_amount === (float)$data['gross_amount'] &&
				 (float)$existing->total_discount === (float)$data['total_discount'] ) {
				return array( 'id' => (int) $existing->id, 'action' => 'none' );
			}

			$this->wpdb->update( $table, $data, array( 'id' => $existing->id ) );
			return array( 'id' => (int) $existing->id, 'action' => 'update' );
		}

		$data['synced_at'] = current_time( 'mysql' );
		$this->wpdb->insert( $table, $data );
		return $this->wpdb->insert_id ? array( 'id' => $this->wpdb->insert_id, 'action' => 'insert' ) : false;
	}

	/**
	 * Get a single order by ID.
	 *
	 * @param  int        $id Order ID.
	 * @return object|null
	 */
	public function get_order( int $id ): ?object {
		return $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->wpdb->prefix}hbt_orders WHERE id = %d", $id )
		);
	}

	/**
	 * Get orders with filters.
	 *
	 * @param  array $args Filters: store_id, status, date_from, date_to, profitable, limit, offset.
	 * @return array
	 */
	public function get_orders( array $args = array() ): array {
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['store_id'] ) ) {
			$where[]  = 'store_id = %d';
			$params[] = absint( $args['store_id'] );
		}
		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = sanitize_text_field( $args['status'] );
		}
		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 'order_date >= %s';
			$params[] = sanitize_text_field( $args['date_from'] ) . ' 00:00:00';
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 'order_date <= %s';
			$params[] = sanitize_text_field( $args['date_to'] ) . ' 23:59:59';
		}
		if ( isset( $args['profitable'] ) ) {
			$where[] = $args['profitable'] ? 'net_profit >= 0' : 'net_profit < 0';
		}

		$limit  = isset( $args['limit'] ) ? absint( $args['limit'] ) : 200;
		$offset = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;

		$sql = "SELECT * FROM {$this->wpdb->prefix}hbt_orders WHERE " . implode( ' AND ', $where ) .
			" ORDER BY order_date DESC LIMIT {$limit} OFFSET {$offset}";

		if ( $params ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return $this->wpdb->get_results( $this->wpdb->prepare( $sql, ...$params ) ) ?: array();
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $this->wpdb->get_results( $sql ) ?: array();
	}

	/**
	 * Save or update an order item.
	 *
	 * Now performs an upsert to avoid duplicate items when the same order is synced again.
	 *
	 * Upsert key: order_id + sku (preferred) or order_id + barcode.
	 *
	 * @param  array $data Item data.
	 * @return int|false
	 */
	public function save_order_item( array $data ) {
		$table = $this->wpdb->prefix . 'hbt_order_items';

		// If explicit ID provided, update that row.
		if ( ! empty( $data['id'] ) ) {
			$id = absint( $data['id'] );
			unset( $data['id'] );
			$this->wpdb->update( $table, $data, array( 'id' => $id ) );
			return $id;
		}

		// Normalize match keys
		$order_id = isset( $data['order_id'] ) ? absint( $data['order_id'] ) : 0;
		$barcode  = isset( $data['barcode'] ) ? sanitize_text_field( $data['barcode'] ) : '';
		$sku      = isset( $data['sku'] ) ? sanitize_text_field( $data['sku'] ) : '';

		$existing = null;

		if ( $sku !== '' ) {
			$existing = $this->wpdb->get_row(
				$this->wpdb->prepare(
					"SELECT id FROM {$table} WHERE order_id = %d AND sku = %s LIMIT 1",
					$order_id,
					$sku
				)
			);
		}

		if ( ! $existing && $barcode !== '' ) {
			$existing = $this->wpdb->get_row(
				$this->wpdb->prepare(
					"SELECT id FROM {$table} WHERE order_id = %d AND barcode = %s LIMIT 1",
					$order_id,
					$barcode
				)
			);
		}

		if ( $existing ) {
			$this->wpdb->update( $table, $data, array( 'id' => (int) $existing->id ) );
			return (int) $existing->id;
		}

		$this->wpdb->insert( $table, $data );
		return $this->wpdb->insert_id ?: false;
	}

	/**
	 * Get all items for an order.
	 *
	 * @param  int $order_id Order ID.
	 * @return array
	 */
	public function get_order_items( int $order_id ): array {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->wpdb->prefix}hbt_order_items WHERE order_id = %d",
				$order_id
			)
		) ?: array();
	}

	// -------------------------------------------------------------------------
	// Return CRUD
	// -------------------------------------------------------------------------

	/**
	 * Save a return record.
	 *
	 * @param  array $data Return data.
	 * @return int|false
	 */
	public function save_return( array $data ) {
		if ( ! empty( $data['id'] ) ) {
			$id = absint( $data['id'] );
			unset( $data['id'] );
			$this->wpdb->update( $this->wpdb->prefix . 'hbt_returns', $data, array( 'id' => $id ) );
			return $id;
		}

		$data['synced_at'] = current_time( 'mysql' );
		$this->wpdb->insert( $this->wpdb->prefix . 'hbt_returns', $data );
		return $this->wpdb->insert_id ?: false;
	}

	/**
	 * Get returns with filters.
	 *
	 * @param  array $args Filters: store_id, status, date_from, date_to.
	 * @return array
	 */
	public function get_returns( array $args = array() ): array {
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['store_id'] ) ) {
			$where[]  = 'store_id = %d';
			$params[] = absint( $args['store_id'] );
		}
		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = sanitize_text_field( $args['status'] );
		}
		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 'return_date >= %s';
			$params[] = sanitize_text_field( $args['date_from'] ) . ' 00:00:00';
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 'return_date <= %s';
			$params[] = sanitize_text_field( $args['date_to'] ) . ' 23:59:59';
		}

		$sql = "SELECT * FROM {$this->wpdb->prefix}hbt_returns WHERE " . implode( ' AND ', $where ) . ' ORDER BY return_date DESC';

		if ( $params ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return $this->wpdb->get_results( $this->wpdb->prepare( $sql, ...$params ) ) ?: array();
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $this->wpdb->get_results( $sql ) ?: array();
	}

	/**
	 * Update return status.
	 *
	 * @param  int    $id     Return ID.
	 * @param  string $status New status.
	 * @return bool
	 */
	public function update_return_status( int $id, string $status ): bool {
		return (bool) $this->wpdb->update( $this->wpdb->prefix . 'hbt_returns', array( 'status' => $status ), array( 'id' => $id ) );
	}

	// -------------------------------------------------------------------------
	// Currency rate CRUD
	// -------------------------------------------------------------------------

	/**
	 * Save a currency rate.
	 *
	 * @param  array $data Rate data.
	 * @return int|false
	 */
	public function save_rate( array $data ) {
		$table = $this->wpdb->prefix . 'hbt_currency_rates';

		// Upsert by date + hour.
		$where   = array( 'rate_date' => $data['rate_date'] );
		$clause  = "rate_date = %s AND rate_hour ";
		$params  = array( $data['rate_date'] );

		if ( isset( $data['rate_hour'] ) && $data['rate_hour'] !== null ) {
			$where['rate_hour'] = $data['rate_hour'];
			$clause             .= '= %d';
			$params[]           = $data['rate_hour'];
		} else {
			$clause   .= 'IS NULL';
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$existing = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT id FROM {$table} WHERE {$clause}", ...$params ) );

		if ( $existing ) {
			$this->wpdb->update( $table, $data, array( 'id' => $existing->id ) );
			return (int) $existing->id;
		}

		$data['fetched_at'] = current_time( 'mysql' );
		$this->wpdb->insert( $table, $data );
		return $this->wpdb->insert_id ?: false;
	}

	/**
	 * Get the best rate for a given datetime (3-layer strategy).
	 *
	 * @param  string $datetime MySQL datetime string.
	 * @return object|null
	 */
	public function get_rate_for_datetime( string $datetime ): ?object {
		$date = substr( $datetime, 0, 10 );
		$hour = (int) substr( $datetime, 11, 2 );

		// Layer 1: hourly.
		$rate = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->wpdb->prefix}hbt_currency_rates
				WHERE rate_date = %s AND rate_hour IS NOT NULL AND rate_hour <= %d
				ORDER BY rate_hour DESC LIMIT 1",
				$date,
				$hour
			)
		);
		if ( $rate ) {
			return $rate;
		}

		// Layer 2: daily.
		$rate = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->wpdb->prefix}hbt_currency_rates
				WHERE rate_date = %s AND rate_hour IS NULL
				LIMIT 1",
				$date
			)
		);
		if ( $rate ) {
			return $rate;
		}

		// Layer 3: fallback – nearest previous.
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->wpdb->prefix}hbt_currency_rates
				WHERE rate_date <= %s
				ORDER BY rate_date DESC, rate_hour DESC LIMIT 1",
				$date
			)
		);
	}

	/**
	 * Get hourly rate for date and hour.
	 *
	 * @param  string   $date Date (Y-m-d).
	 * @param  int|null $hour Hour (0-23).
	 * @return object|null
	 */
	public function get_hourly_rate( string $date, ?int $hour ): ?object {
		if ( null === $hour ) {
			return $this->wpdb->get_row(
				$this->wpdb->prepare(
					"SELECT * FROM {$this->wpdb->prefix}hbt_currency_rates WHERE rate_date = %s AND rate_hour IS NULL LIMIT 1",
					$date
				)
			);
		}

		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->wpdb->prefix}hbt_currency_rates WHERE rate_date = %s AND rate_hour = %d LIMIT 1",
				$date,
				$hour
			)
		);
	}

	// -------------------------------------------------------------------------
	// Financial transaction CRUD
	// -------------------------------------------------------------------------

	/**
	 * Save a financial transaction.
	 *
	 * Now does an upsert by store_id + transaction_id to avoid duplicates when manual syncs fetch overlapping ranges.
	 *
	 * @param  array $data Transaction data.
	 * @return int|false
	 */
	public function save_transaction( array $data ) {
		$table = $this->wpdb->prefix . 'hbt_financial_transactions';

		// Normalize fields
		$data['store_id'] = isset( $data['store_id'] ) ? absint( $data['store_id'] ) : 0;
		$data['transaction_id'] = isset( $data['transaction_id'] ) ? sanitize_text_field( $data['transaction_id'] ) : '';
		$data['order_number'] = isset( $data['order_number'] ) ? sanitize_text_field( $data['order_number'] ) : '';
		$data['transaction_type'] = isset( $data['transaction_type'] ) ? sanitize_text_field( $data['transaction_type'] ) : '';
		$data['amount'] = isset( $data['amount'] ) ? floatval( $data['amount'] ) : 0.0;
		$data['description'] = isset( $data['description'] ) ? sanitize_text_field( $data['description'] ) : '';
		$data['transaction_date'] = isset( $data['transaction_date'] ) ? sanitize_text_field( $data['transaction_date'] ) : null;

		$data['synced_at'] = current_time( 'mysql' );

		// If transaction_id present, try upsert by store_id + transaction_id
		if ( ! empty( $data['transaction_id'] ) ) {
			$existing = $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT id FROM {$table} WHERE store_id = %d AND transaction_id = %s LIMIT 1",
					$data['store_id'],
					$data['transaction_id']
				)
			);

			if ( $existing ) {
				$update = $data;
				unset( $update['transaction_id'] ); // don't update unique key
				$this->wpdb->update( $table, $update, array( 'id' => (int) $existing ) );
				return (int) $existing;
			}
		}

		// Insert new row
		$this->wpdb->insert( $table, $data );
		return $this->wpdb->insert_id ?: false;
	}

	/**
	 * Get transactions for an order.
	 *
	 * @param  int    $store_id     Store ID.
	 * @param  string $order_number Order number.
	 * @return array
	 */
	public function get_transactions_for_order( int $store_id, string $order_number ): array {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->wpdb->prefix}hbt_financial_transactions WHERE store_id = %d AND order_number = %s",
				$store_id,
				$order_number
			)
		) ?: array();
	}

	// -------------------------------------------------------------------------
	// Notification CRUD
	// -------------------------------------------------------------------------

	/**
	 * Create a notification.
	 *
	 * @param  string   $type       Notification type.
	 * @param  string   $title      Title.
	 * @param  string   $message    Message body.
	 * @param  int|null $related_id Related entity ID.
	 * @return int|false
	 */
	public function create_notification( string $type, string $title, string $message, ?int $related_id = null ) {
		// Duplicate check.
		if ( $related_id !== null ) {
			$existing = $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT id FROM {$this->wpdb->prefix}hbt_notifications
					WHERE notification_type = %s AND related_id = %d AND is_dismissed = 0",
					$type,
					$related_id
				)
			);
			if ( $existing ) {
				return (int) $existing;
			}
		}

		$this->wpdb->insert(
			$this->wpdb->prefix . 'hbt_notifications',
			array(
				'notification_type' => $type,
				'title'             => $title,
				'message'           => $message,
				'related_id'        => $related_id,
				'created_at'        => current_time( 'mysql' ),
			)
		);

		return $this->wpdb->insert_id ?: false;
	}

	/**
	 * Get unread notifications.
	 *
	 * @param  int $limit Max records.
	 * @return array
	 */
	public function get_unread( int $limit = 20 ): array {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->wpdb->prefix}hbt_notifications WHERE is_read = 0 AND is_dismissed = 0 ORDER BY created_at DESC LIMIT %d",
				$limit
			)
		) ?: array();
	}

	/**
	 * Dismiss a notification.
	 *
	 * @param  int  $id Notification ID.
	 * @return bool
	 */
	public function dismiss_notification( int $id ): bool {
		return (bool) $this->wpdb->update(
			$this->wpdb->prefix . 'hbt_notifications',
			array( 'is_dismissed' => 1 ),
			array( 'id' => $id )
		);
	}

	/**
	 * Mark a notification as read.
	 *
	 * @param  int  $id Notification ID.
	 * @return bool
	 */
	public function mark_read( int $id ): bool {
		return (bool) $this->wpdb->update(
			$this->wpdb->prefix . 'hbt_notifications',
			array( 'is_read' => 1 ),
			array( 'id' => $id )
		);
	}

	/**
	 * Get unread notification count.
	 *
	 * @return int
	 */
	public function get_unread_count(): int {
		return (int) $this->wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->wpdb->prefix}hbt_notifications WHERE is_read = 0 AND is_dismissed = 0"
		);
	}

	/**
	 * Delete notifications older than 30 days that are already read.
	 *
	 * @return int Number of rows deleted.
	 */
	public function cleanup_old_notifications(): int {
		return (int) $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->wpdb->prefix}hbt_notifications WHERE is_read = 1 AND created_at < %s",
				gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) )
			)
		);
	}

	/**
     * Get table row counts for system info.
     *
     * @return array
     */
    public function get_table_stats(): array {
        $tables = array(
            'stores'                  => 'hbt_stores',
            'product_costs'           => 'hbt_product_costs',
            'shipping_costs'          => 'hbt_shipping_costs',
            'orders'                  => 'hbt_orders',
            'order_items'             => 'hbt_order_items',
            'returns'                 => 'hbt_returns',
            'currency_rates'          => 'hbt_currency_rates',
            'financial_transactions'  => 'hbt_financial_transactions',
            'notifications'           => 'hbt_notifications',
            'sync_logs'               => 'hbt_sync_logs', // YENİ: İstatistiklerde logları da göster
        );

        $stats = array();
        foreach ( $tables as $key => $table ) {
            $full_table  = $this->wpdb->prefix . $table;
            
            // YENİ GÜNCELLEME: Eğer tablo veritabanında henüz oluşturulmadıysa (0) döndür.
            // Bu sayede sistem SQL hatası (Fatal Error) vermekten kurtulur.
            $exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$full_table}'");
            $stats[ $key ] = $exists ? (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$full_table}" ) : 0;
        }

		

        return $stats;
    }

	/**
     * Dashboard için kâr istatistiklerini hesaplar.
     */
    public function get_profit_stats( $start_date, $end_date ) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'hbt_orders';
        $ads_table    = $wpdb->prefix . 'hbt_ad_expenses';

        // Tarihleri günün başı ve sonu olarak ayarla
        $start_dt = $start_date . ' 00:00:00';
        $end_dt   = $end_date . ' 23:59:59';

        // 1. İptal, İade ve Tedarik Edilemeyenleri dışlayarak Net Kârı ve Ciroyu Çek
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT 
                COALESCE(SUM(net_profit), 0) AS net_profit,
                COALESCE(SUM(total_price), 0) AS revenue
             FROM {$orders_table}
             WHERE order_date BETWEEN %s AND %s
             AND status NOT IN ('Cancelled', 'Returned', 'UnSupplied')",
            $start_dt, $end_dt
        ), ARRAY_A );

        $net_profit = (float) ($row['net_profit'] ?? 0);
        $revenue    = (float) ($row['revenue'] ?? 0);

        // 2. İlgili Tarih Aralığına Düşen Reklam Giderlerini Bul ve Kârdan Çıkar
        $ad_sql = $wpdb->prepare(
            "SELECT SUM(
                daily_amount * (DATEDIFF(LEAST(end_date, %s), GREATEST(start_date, %s)) + 1)
            ) as total_ad_expense
            FROM {$ads_table}
            WHERE start_date <= %s AND end_date >= %s",
            $end_date, $start_date, $end_date, $start_date
        );
        $ad_expense = (float) $wpdb->get_var( $ad_sql );

        return array(
            'net_profit' => $net_profit - $ad_expense,
            'revenue'    => $revenue
        );
    }

   /** Dashboard grafiği için son 30 günlük trend (Türkiye Saati) */
    public function get_revenue_trend( $days = 30 ) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'hbt_orders';
        $ads_table    = $wpdb->prefix . 'hbt_ad_expenses';

        $tz = new DateTimeZone('Europe/Istanbul');
        $dt = new DateTime('now', $tz);
        $today_str = $dt->format('Y-m-d');

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT 
                DATE(order_date) as day, 
                COALESCE(SUM(net_profit), 0) as profit,
                COALESCE(SUM(total_price), 0) as revenue
             FROM {$orders_table}
             WHERE order_date >= DATE_SUB(%s, INTERVAL %d DAY)
             AND status NOT IN ('Cancelled', 'Returned', 'UnSupplied')
             GROUP BY DATE(order_date) 
             ORDER BY day ASC",
            $today_str, $days
        ), ARRAY_A );

        $ads = $wpdb->get_results( $wpdb->prepare(
            "SELECT start_date, end_date, daily_amount FROM {$ads_table} WHERE end_date >= DATE_SUB(%s, INTERVAL %d DAY)", 
            $today_str, $days
        ), ARRAY_A );

        $trend = array();
        foreach ( $results as $row ) {
            $day_str = $row['day'];
            $daily_profit = (float) $row['profit'];
            $daily_revenue = (float) $row['revenue'];
            
            $daily_ad = 0;
            foreach ( $ads as $ad ) {
                if ( $day_str >= $ad['start_date'] && $day_str <= $ad['end_date'] ) {
                    $daily_ad += (float) $ad['daily_amount'];
                }
            }

            $final_profit = $daily_profit - $daily_ad;
            $margin = $daily_revenue > 0 ? ($final_profit / $daily_revenue) * 100 : 0;

           $trend[] = array(
                'day'     => $day_str,
                'profit'  => round( $final_profit, 2 ),
                'margin'  => round( $margin, 2 ),
                'revenue' => round( $daily_revenue, 2 )
            );
        }
        return $trend;
    }

    /** Dashboard Pasta Grafiği İçin Son 30 Günlük Gider Dağılımı */
    public function get_dashboard_expense_breakdown( $days = 30 ) {
        global $wpdb;
        $tz = new DateTimeZone('Europe/Istanbul');
        $dt = new DateTime('now', $tz);
        $today = $dt->format('Y-m-d');
        
        $dt_days_ago = clone $dt;
        $dt_days_ago->modify("-{$days} days");
        $start_date = $dt_days_ago->format('Y-m-d') . ' 00:00:00';
        $thirty_days_ago = $dt_days_ago->format('Y-m-d');

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT 
                COALESCE(SUM(total_price), 0) as total_sales,
                COALESCE(SUM(total_cost_tl), 0) as total_cost,
                COALESCE(SUM(total_commission), 0) as total_comm,
                COALESCE(SUM(total_shipping), 0) as total_ship,
                COALESCE(SUM(total_other_exp), 0) as total_other_exp
             FROM {$wpdb->prefix}hbt_orders
             WHERE order_date >= %s AND status NOT IN ('Cancelled', 'Returned', 'UnSupplied')",
            $start_date
        ), ARRAY_A );

        $ad_cost = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(daily_amount * (DATEDIFF(LEAST(end_date, %s), GREATEST(start_date, %s)) + 1)) 
             FROM {$wpdb->prefix}hbt_ad_expenses WHERE start_date <= %s AND end_date >= %s",
            $today, $thirty_days_ago, $today, $thirty_days_ago
        ));

        $row['total_ads'] = (float) $ad_cost;
        return $row;
    }

    /** Dashboard En İyi 5 Ürün (Kâr Odaklı) */
    public function get_top_profitable_products( $days = 30, $limit = 5 ) {
        global $wpdb;
        $tz = new DateTimeZone('Europe/Istanbul');
        $dt = new DateTime('now', $tz);
        $dt->modify("-{$days} days");
        $start_date = $dt->format('Y-m-d') . ' 00:00:00';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT oi.barcode, oi.product_name, SUM(oi.net_profit) as total_profit, SUM(oi.quantity) as total_qty
             FROM {$wpdb->prefix}hbt_order_items oi
             INNER JOIN {$wpdb->prefix}hbt_orders o ON o.id = oi.order_id
             WHERE o.order_date >= %s AND o.status NOT IN ('Cancelled', 'Returned', 'UnSupplied')
             GROUP BY oi.barcode ORDER BY total_profit DESC LIMIT %d",
            $start_date, $limit
        ), ARRAY_A );
    }
	/** Dashboard En Kötü 5 Ürün (Kan Kaybedenler) */
    public function get_worst_profitable_products( $days = 30, $limit = 5 ) {
        global $wpdb;
        $tz = new DateTimeZone('Europe/Istanbul');
        $dt = new DateTime('now', $tz);
        $dt->modify("-{$days} days");
        $start_date = $dt->format('Y-m-d') . ' 00:00:00';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT oi.barcode, oi.product_name, SUM(oi.net_profit) as total_profit, SUM(oi.quantity) as total_qty
             FROM {$wpdb->prefix}hbt_order_items oi
             INNER JOIN {$wpdb->prefix}hbt_orders o ON o.id = oi.order_id
             WHERE o.order_date >= %s AND o.status NOT IN ('Cancelled', 'Returned', 'UnSupplied')
             GROUP BY oi.barcode HAVING total_profit < 0 ORDER BY total_profit ASC LIMIT %d",
            $start_date, $limit
        ), ARRAY_A );
    }

    /** Dashboard Akıllı Uyarılar Sistemi */
    public function get_smart_alerts() {
        global $wpdb;
        $alerts = array();
        
        $tz = new DateTimeZone('Europe/Istanbul');
        $dt = new DateTime('now', $tz);
        $today = $dt->format('Y-m-d');
        
        $dt_7 = clone $dt;
        $dt_7->modify("-7 days");
        $sevendays = $dt_7->format('Y-m-d') . ' 00:00:00';
        
        $missing_cost = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hbt_product_costs WHERE cost_usd <= 0");
        if ($missing_cost > 0) $alerts[] = array('type' => 'warning', 'icon' => 'dashicons-warning', 'msg' => "<strong>{$missing_cost} ürünün</strong> maliyeti girilmemiş. Kârınız yanlış hesaplanabilir.");

        $loss_today = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}hbt_orders WHERE order_date LIKE %s AND net_profit < 0 AND status NOT IN ('Cancelled', 'Returned', 'UnSupplied')", $today . '%'));
        if ($loss_today > 0) $alerts[] = array('type' => 'error', 'icon' => 'dashicons-dismiss', 'msg' => "Bugün <strong>{$loss_today} siparişten</strong> zarar ettiniz. Acilen fiyatları kontrol edin!");

        $pending_comm = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hbt_orders WHERE is_comm_defaulted = 1");
        if ($pending_comm > 0) $alerts[] = array('type' => 'info', 'icon' => 'dashicons-update', 'msg' => "<strong>{$pending_comm} siparişin</strong> komisyon faturası bekleniyor (%19 varsayıldı).");
        
        $returns = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}hbt_orders WHERE status = 'Returned' AND order_date >= %s", $sevendays));
        if ($returns > 5) $alerts[] = array('type' => 'error', 'icon' => 'dashicons-cart', 'msg' => "Son 7 günde <strong>{$returns} iade</strong> aldınız. İade oranınız riskli bölgede.");

        if (empty($alerts)) $alerts[] = array('type' => 'success', 'icon' => 'dashicons-yes-alt', 'msg' => "Harika! Şu an için kritik bir uyarı bulunmuyor, işler yolunda.");

        return $alerts;
    }
	/**
	 * Sayfalanmış bildirimleri getirir (100 adet vs.)
	 *
	 * @param  int $limit  Sayfa başına limit.
	 * @param  int $offset Başlangıç noktası.
	 * @return array
	 */
	public function get_all_notifications_paginated( int $limit = 100, int $offset = 0 ): array {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->wpdb->prefix}hbt_notifications ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		) ?: array();
	}

	/**
	 * Toplam bildirim sayısını getirir (Sayfalama için)
	 *
	 * @return int
	 */
	public function get_total_notifications_count(): int {
		return (int) $this->wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->wpdb->prefix}hbt_notifications"
		);
	}

	/**
	 * Tekil bildirim silme
	 *
	 * @param  int $id Bildirim ID.
	 * @return bool
	 */
	public function delete_notification( int $id ): bool {
		return (bool) $this->wpdb->delete(
			$this->wpdb->prefix . 'hbt_notifications',
			array( 'id' => $id )
		);
	}

	/**
	 * Toplu bildirim silme
	 *
	 * @param  array $ids Bildirim ID dizisi.
	 * @return bool
	 */
	public function bulk_delete_notifications( array $ids ): bool {
		if ( empty( $ids ) ) {
			return false;
		}
		$ids_str = implode( ',', array_map( 'intval', $ids ) );
		return (bool) $this->wpdb->query(
			"DELETE FROM {$this->wpdb->prefix}hbt_notifications WHERE id IN ($ids_str)"
		);
	}

	/**
	 * Tüm bildirimleri okundu ve gizlendi olarak işaretle (Hepsini Oku / Kapat)
	 *
	 * @return bool
	 */
	public function mark_all_read(): bool {
		return (bool) $this->wpdb->query(
			"UPDATE {$this->wpdb->prefix}hbt_notifications SET is_read = 1, is_dismissed = 1"
		);
	}

	private function get_ad_expenses_schema( string $prefix, string $charset_collate ): string {
		return "CREATE TABLE {$prefix}hbt_ad_expenses (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			store_id BIGINT UNSIGNED NOT NULL,
			platform VARCHAR(100) NOT NULL,
			start_date DATE NOT NULL,
			end_date DATE NOT NULL,
			total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
			daily_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_store_dates (store_id, start_date, end_date)
		) $charset_collate;";
	}

	public function save_ad_expense( array $data ): bool {
		$table = $this->wpdb->prefix . 'hbt_ad_expenses';
		
		// Günlük tutarı hesapla
		$start = new DateTime( $data['start_date'] );
		$end   = new DateTime( $data['end_date'] );
		$diff  = $start->diff( $end )->days + 1; // Başlangıç ve bitiş dahil
		
		$data['daily_amount'] = round( $data['total_amount'] / $diff, 2 );

		if ( ! empty( $data['id'] ) ) {
			$id = absint( $data['id'] );
			unset( $data['id'] );
			return (bool) $this->wpdb->update( $table, $data, array( 'id' => $id ) );
		}

		return (bool) $this->wpdb->insert( $table, $data );
	}

	public function get_all_ad_expenses(): array {
		return $this->wpdb->get_results( "SELECT * FROM {$this->wpdb->prefix}hbt_ad_expenses ORDER BY start_date DESC" ) ?: array();
	}

	public function delete_ad_expense( int $id ): bool {
		return (bool) $this->wpdb->delete( $this->wpdb->prefix . 'hbt_ad_expenses', array( 'id' => $id ) );
	}

	/**
	 * DataTables için Server-Side Sipariş Verisini Getirir
	 */
	public function get_orders_datatables_data( array $request ): array {
		$where  = array( '1=1' );
		$params = array();

		// Filtreler (Mağaza, Tarih, Durum)
		if ( ! empty( $request['store_id'] ) ) {
			$where[]  = 'store_id = %d';
			$params[] = absint( $request['store_id'] );
		}
		if ( ! empty( $request['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = sanitize_text_field( $request['status'] );
		}
		if ( ! empty( $request['date_from'] ) ) {
			$where[]  = 'order_date >= %s';
			$params[] = sanitize_text_field( $request['date_from'] ) . ' 00:00:00';
		}
		if ( ! empty( $request['date_to'] ) ) {
			$where[]  = 'order_date <= %s';
			$params[] = sanitize_text_field( $request['date_to'] ) . ' 23:59:59';
		}

		// YENİ EKLENEN: Analiz Durumu (Renk) Filtresi
		if ( ! empty( $request['analysis_status'] ) ) {
			$analysis = sanitize_text_field( $request['analysis_status'] );
			
			if ( $analysis === 'green' ) {
				// Kârlı Siparişler (İptal vb. hariç)
				$where[] = "net_profit >= 0 AND status NOT IN ('Cancelled', 'Returned', 'UnSupplied')";
			} elseif ( $analysis === 'red' ) {
				// Zarar Eden Siparişler
				$where[] = "net_profit < 0 AND status NOT IN ('Cancelled', 'Returned', 'UnSupplied')";
			} elseif ( $analysis === 'orange' ) {
				// %19 Komisyon (Bekleyen) Siparişler - Kesin matematiksel SQL kontrolü
				$where[] = "is_comm_defaulted = 1 AND total_price > 0 AND ROUND((total_commission/total_price)*100, 2) = 19.00";
			} elseif ( $analysis === 'yellow' ) {
				// Maliyeti Eksik Siparişler
				$where[] = "is_calculated = 0 AND status NOT IN ('Cancelled', 'Returned', 'UnSupplied')";
			} elseif ( $analysis === 'gray' ) {
				// İptal / İade Siparişler
				$where[] = "status IN ('Cancelled', 'Returned', 'UnSupplied')";
			}
		}

		// Arama Kutusu (Müşteri Adı veya Sipariş No'da arar)
		if ( ! empty( $request['search']['value'] ) ) {
			$search_value = sanitize_text_field( $request['search']['value'] );
			$where[]  = '(order_number LIKE %s OR customer_name LIKE %s)'; 
			$like_val = '%' . $this->wpdb->esc_like( $search_value ) . '%';
			$params[] = $like_val;
			$params[] = $like_val;
		}

		// YENİ EKLENEN: Ürün Bazlı Gelişmiş Filtreleme (VE / VEYA Mantığı)
		if ( ! empty( $request['filter_products'] ) && is_array( $request['filter_products'] ) ) {
			$product_logic = isset( $request['filter_product_logic'] ) && $request['filter_product_logic'] === 'AND' ? 'AND' : 'OR';
			$barcodes = array_map( 'sanitize_text_field', $request['filter_products'] );
			$placeholders = implode( ',', array_fill( 0, count( $barcodes ), '%s' ) );

			if ( $product_logic === 'OR' ) {
				// Herhangi biri (VEYA): Siparişte seçilen ürünlerden en az biri varsa
				$where[] = "id IN (SELECT order_id FROM {$this->wpdb->prefix}hbt_order_items WHERE barcode IN ($placeholders))";
				$params = array_merge( $params, $barcodes );
			} else {
				// Hepsi Birlikte (VE): Siparişte seçilen BÜTÜN ürünler varsa (Kesişim)
				$count = count( $barcodes );
				$where[] = "id IN (SELECT order_id FROM {$this->wpdb->prefix}hbt_order_items WHERE barcode IN ($placeholders) GROUP BY order_id HAVING COUNT(DISTINCT barcode) >= %d)";
				$params = array_merge( $params, $barcodes );
				$params[] = $count;
			}
		}

		$where_sql = implode( ' AND ', $where );

		// Kayıt Sayıları
		$total_records = (int) $this->wpdb->get_var( "SELECT COUNT(id) FROM {$this->wpdb->prefix}hbt_orders" );
		$filtered_records = (int) $this->wpdb->get_var( $params ? $this->wpdb->prepare( "SELECT COUNT(id) FROM {$this->wpdb->prefix}hbt_orders WHERE {$where_sql}", ...$params ) : "SELECT COUNT(id) FROM {$this->wpdb->prefix}hbt_orders WHERE {$where_sql}" );

		// Sayfalama ve Sıralama
		$limit  = isset( $request['length'] ) ? intval( $request['length'] ) : 50;
		$offset = isset( $request['start'] ) ? intval( $request['start'] ) : 0;
		
		// Eski sütun sıranıza göre eşleştirme (Tarih 2. sütun olduğu için index 1)
		$columns = array( 0 => 'order_number', 1 => 'order_date', 2 => 'customer_name', 3 => 'total_price', 4 => 'total_cost_tl', 5 => 'total_commission', 6 => 'total_shipping', 8 => 'net_profit', 9 => 'profit_margin', 10 => 'status' );
		$order_col_idx = isset( $request['order'][0]['column'] ) ? intval( $request['order'][0]['column'] ) : 1;
		$order_dir     = isset( $request['order'][0]['dir'] ) && strtolower( $request['order'][0]['dir'] ) === 'asc' ? 'ASC' : 'DESC';
		$order_by = $columns[ $order_col_idx ] ?? 'order_date';

		// Esas Veriyi Çek
		$data_query = "SELECT * FROM {$this->wpdb->prefix}hbt_orders WHERE {$where_sql} ORDER BY {$order_by} {$order_dir} LIMIT {$limit} OFFSET {$offset}";
		$data_results = $this->wpdb->get_results( $params ? $this->wpdb->prepare( $data_query, ...$params ) : $data_query );

		// Tablonun En Altındaki "Genel Toplamlar" için hesaplama (İptal ve iadeleri dışla)
		$totals_where = $where;
		$totals_params = $params;
		if ( empty( $request['status'] ) ) {
			$totals_where[] = "status NOT IN ('Cancelled', 'Returned', 'UnSupplied')";
		}
		$totals_where_sql = implode( ' AND ', $totals_where );
		
		$totals_query = "SELECT SUM(total_price) as sum_price, SUM(total_cost_tl) as sum_cost, SUM(total_commission) as sum_comm, SUM(total_shipping) as sum_ship, SUM(net_profit) as sum_profit FROM {$this->wpdb->prefix}hbt_orders WHERE {$totals_where_sql}";
		$totals = $this->wpdb->get_row( $totals_params ? $this->wpdb->prepare( $totals_query, ...$totals_params ) : $totals_query );

		return array(
			'draw'            => isset( $request['draw'] ) ? intval( $request['draw'] ) : 1,
			'recordsTotal'    => $total_records,
			'recordsFiltered' => $filtered_records,
			'data'            => $data_results ?: array(),
			'customTotals'    => $totals
		);
	}
	// -------------------------------------------------------------------------
	// Sync Logs (Senkronizasyon Geçmişi) CRUD
	// -------------------------------------------------------------------------

	/** @return string */
	private function get_sync_logs_schema( string $prefix, string $charset_collate ): string {
		return "CREATE TABLE {$prefix}hbt_sync_logs (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			store_id BIGINT UNSIGNED NULL,
			store_name VARCHAR(255) NULL,
			sync_type VARCHAR(50) NOT NULL, /* auto_fast, auto_deep, manual */
			status VARCHAR(50) DEFAULT 'success',
			fetched INT DEFAULT 0,
			inserted INT DEFAULT 0,
			updated INT DEFAULT 0,
			skipped INT DEFAULT 0,
			failed INT DEFAULT 0,
			message TEXT,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_type (sync_type),
			KEY idx_date (created_at)
		) $charset_collate;";
	}

	/**
	 * Senkronizasyon sonucunu veritabanına kaydeder.
	 */
	public function log_sync_result( array $data ): int {
		$table = $this->wpdb->prefix . 'hbt_sync_logs';
		$data['created_at'] = current_time( 'mysql' );
		$this->wpdb->insert( $table, $data );
		return $this->wpdb->insert_id ?: 0;
	}

	/**
	 * Senkronizasyon geçmişini getirir.
	 */
	public function get_sync_logs( int $limit = 100, int $offset = 0 ): array {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->wpdb->prefix}hbt_sync_logs ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		) ?: array();
	}

	/**
	 * Toplam log sayısını getirir (sayfalama için).
	 */
	public function get_total_sync_logs_count(): int {
		return (int) $this->wpdb->get_var( "SELECT COUNT(id) FROM {$this->wpdb->prefix}hbt_sync_logs" );
	}

	/** Dashboard İade Kaynaklı Görünmez Zarar (Son 30 Gün) */
    public function get_return_loss_stats( $days = 30 ) {
        global $wpdb;
        $tz = new DateTimeZone('Europe/Istanbul');
        $dt = new DateTime('now', $tz);
        $dt->modify("-{$days} days");
        $start_date = $dt->format('Y-m-d') . ' 00:00:00';

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT 
                COALESCE(SUM(shipping_cost), 0) as total_shipping_loss,
                COALESCE(SUM(net_loss), 0) as total_net_loss
             FROM {$wpdb->prefix}hbt_returns
             WHERE return_date >= %s",
            $start_date
        ), ARRAY_A );
    }

	/** @return string */
	private function get_avantajli_arsiv_schema( string $prefix, string $charset_collate ): string {
		return "CREATE TABLE {$prefix}hbt_avantajli_arsiv (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			maza_id INT(11) NOT NULL,
			kayit_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
			toplam_urun INT(11) DEFAULT 0,
			yildiz1_yesil INT(11) DEFAULT 0,
			yildiz2_yesil INT(11) DEFAULT 0,
			yildiz3_yesil INT(11) DEFAULT 0,
			detay_verisi LONGTEXT NOT NULL,
			PRIMARY KEY (id)
		) $charset_collate;";
	}
	/** @return string */
	private function get_plus_simulator_arsiv_schema( string $prefix, string $charset_collate ): string {
		return "CREATE TABLE {$prefix}hbt_plus_simulator_arsiv (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			maza_id INT(11) NOT NULL,
			kayit_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
			toplam_urun INT(11) DEFAULT 0,
			plus_yesil INT(11) DEFAULT 0,
			detay_verisi LONGTEXT NOT NULL,
			PRIMARY KEY (id)
		) $charset_collate;";
	}
}