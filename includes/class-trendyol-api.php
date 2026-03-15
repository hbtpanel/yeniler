<?php
/**
 * Trendyol API client class.
 *
 * @package HBT_Trendyol_Profit_Tracker
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class HBT_Trendyol_API
 *
 * Handles communication with the Trendyol Marketplace API.
 */
class HBT_Trendyol_API {

	/**
	 * Trendyol API base URL.
	 *
	 * @var string
	 */
	private const BASE_URL = 'https://api.trendyol.com/sapigw/';

	/**
	 * Store object.
	 *
	 * @var object
	 */
	private object $store;

	/**
	 * Supplier ID.
	 *
	 * @var string
	 */
	private string $supplier_id;

	/**
	 * Base64-encoded credentials.
	 *
	 * @var string
	 */
	private string $credentials;

	/**
	 * Constructor.
	 *
	 * @param object $store Store object with decrypted api_key and api_secret.
	 */
	public function __construct( object $store ) {
		$this->store       = $store;
		$this->supplier_id = $store->supplier_id;
		$this->credentials = base64_encode( $store->api_key . ':' . $store->api_secret );
	}

	// -------------------------------------------------------------------------
	// Public API methods
	// -------------------------------------------------------------------------

	/**
	 * Test the API connection.
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function test_connection() {
		$endpoint = "suppliers/{$this->supplier_id}/orders?size=1";
		$response = $this->make_request( $endpoint );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}

	/**
	 * Get orders within a date range or by page/size.
	 *
	 * @param  int      $start_date Start date (epoch ms).
	 * @param  int      $end_date   End date (epoch ms).
	 * @param  int|null $page       Page number (null to use date range behavior).
	 * @param  int      $size       Page size.
	 * @return array|WP_Error
	 */
	public function get_orders( int $start_date, int $end_date, ?int $page = null, int $size = 100 ) {
		$params = array(
			'startDate'        => $start_date,
			'endDate'          => $end_date,
			'dateQueryType'    => 'CREATED_DATE',
			'page'             => $page ?? 0,
			'size'             => $size,
			'orderByField'     => 'CreatedDate',
			'orderByDirection' => 'DESC',
		);

		$endpoint = "suppliers/{$this->supplier_id}/orders?" . http_build_query( $params );
		$response = $this->make_request( $endpoint );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$all_orders = $response['content'] ?? array();

		// AJAX arayüzünden manuel tetiklenmişse sadece o sayfayı dön (Döngüye girme)
		if ( $page !== null ) {
			return $this->normalize_orders( (array) $all_orders );
		}

		// Otomatik/Cron çalışıyorsa tüm sayfaları çek
		$total_pages = $response['totalPages'] ?? 1;
		for ( $p = 1; $p < $total_pages; $p++ ) {
			$params['page'] = $p;
			$endpoint       = "suppliers/{$this->supplier_id}/orders?" . http_build_query( $params );
			$page_response  = $this->make_request( $endpoint );

			if ( is_wp_error( $page_response ) ) {
				break;
			}

			$all_orders = array_merge( $all_orders, $page_response['content'] ?? array() );
		}

		return $this->normalize_orders( (array) $all_orders );
	}
	/**
	 * Gerçek İlerleme Çubuğu için Toplam Sipariş Sayısını Alır (Pre-flight request)
	 *
	 * @param  int $start_date Start date (epoch ms).
	 * @param  int $end_date   End date (epoch ms).
	 * @return int|WP_Error Toplam sipariş sayısı veya hata.
	 */
	public function get_orders_count( int $start_date, int $end_date ) {
		$params = array(
			'startDate'        => $start_date,
			'endDate'          => $end_date,
			'dateQueryType'    => 'CREATED_DATE',
			'page'             => 0,
			'size'             => 1, // Sadece 1 adet çekip totalElements değerine bakacağız
			'orderByField'     => 'CreatedDate',
			'orderByDirection' => 'DESC',
		);

		$endpoint = "suppliers/{$this->supplier_id}/orders?" . http_build_query( $params );
		$response = $this->make_request( $endpoint );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return isset( $response['totalElements'] ) ? (int) $response['totalElements'] : 0;
	}
	/**
	 * Get all products for the supplier.
	 *
	 * @param  int $page Page number.
	 * @param  int $size Page size.
	 * @return array|WP_Error
	 */
	public function get_products( int $page = 0, int $size = 200 ) {
		$params   = array( 'page' => $page, 'size' => $size );
		$endpoint = "suppliers/{$this->supplier_id}/products?" . http_build_query( $params );
		$response = $this->make_request( $endpoint );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$all_products = $response['content'] ?? array();
		$total_pages  = $response['totalPages'] ?? 1;

		for ( $p = 1; $p < $total_pages; $p++ ) {
			$params['page'] = $p;
			$endpoint       = "suppliers/{$this->supplier_id}/products?" . http_build_query( $params );
			$page_response  = $this->make_request( $endpoint );

			if ( is_wp_error( $page_response ) ) {
				break;
			}

			$all_products = array_merge( $all_products, $page_response['content'] ?? array() );
		}

		return $this->normalize_products( (array) $all_products );
	}

	/**
	 * Get financial transactions (otherfinancials).
	 *
	 * @param string $type      Transaction type.
	 * @param int    $start_ms  Start ms.
	 * @param int    $end_ms    End ms.
	 * @param int    $page      Page.
	 * @param int    $size      Size.
	 * @return array|WP_Error
	 */
	public function get_financial_transactions( string $type, int $start_ms, int $end_ms, int $page = 0, int $size = 500 ) {
		$params = array(
			'transactionType' => $type,
			'startDate'       => $start_ms,
			'endDate'         => $end_ms,
			'page'            => $page,
			'size'            => $size,
		);

		$endpoint = "integration/finance/che/sellers/{$this->supplier_id}/otherfinancials?" . http_build_query( $params );
		$response = $this->make_request( $endpoint );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$all_transactions = $response['content'] ?? $response;
		$total_pages      = $response['totalPages'] ?? 1;

		for ( $p = 1; $p < $total_pages; $p++ ) {
			$params['page'] = $p;
			$endpoint       = "integration/finance/che/sellers/{$this->supplier_id}/otherfinancials?" . http_build_query( $params );
			$page_response  = $this->make_request( $endpoint );

			if ( is_wp_error( $page_response ) ) {
				break;
			}

			$all_transactions = array_merge( $all_transactions, $page_response['content'] ?? array() );
		}

		return $this->normalize_transactions( (array) $all_transactions );
	}

	/**
	 * Get settlements endpoint (recommended for commission fields).
	 *
	 * @param int               $start_ms Start ms.
	 * @param int               $end_ms   End ms.
	 * @param string|array|null $types    transactionTypes or single type.
	 * @param int               $page
	 * @param int               $size
	 * @return array|WP_Error
	 */
	public function get_settlements( int $start_ms, int $end_ms, $types = null, int $page = 0, int $size = 500 ) {
		$params = array(
			'startDate' => $start_ms,
			'endDate'   => $end_ms,
			'page'      => $page,
			'size'      => $size,
		);

		if ( ! empty( $types ) ) {
			if ( is_array( $types ) ) {
				$params['transactionTypes'] = implode( ',', $types );
			} else {
				$params['transactionTypes'] = (string) $types;
			}
		}

		$endpoint = "integration/finance/che/sellers/{$this->supplier_id}/settlements?" . http_build_query( $params );
		$response = $this->make_request( $endpoint );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$all = $response['content'] ?? array();
		$total_pages = $response['totalPages'] ?? 1;

		for ( $p = 1; $p < $total_pages; $p++ ) {
			$params['page'] = $p;
			$endpoint = "integration/finance/che/sellers/{$this->supplier_id}/settlements?" . http_build_query( $params );
			$page_response = $this->make_request( $endpoint );
			if ( is_wp_error( $page_response ) ) {
				break;
			}
			$all = array_merge( $all, $page_response['content'] ?? array() );
		}

		return $all;
	}

	// -------------------------------------------------------------------------
	// HTTP helpers
	// -------------------------------------------------------------------------

	/**
	 * Make an API GET request with retry/backoff logic.
	 *
	 * Implements:
	 *  - up to $max_attempts attempts for transient errors (5xx, 429)
	 *  - uses Retry-After header (if present) for 429
	 *  - exponential backoff with small sleeps between attempts
	 *
	 * @param  string $endpoint API endpoint (relative).
	 * @param  array  $params   Additional query params (unused here, kept for future POST).
	 * @return array|WP_Error   Parsed JSON array or WP_Error.
	 */
	public function make_request( string $endpoint, array $params = array() ) {
		$url = self::BASE_URL . $endpoint;

		$max_attempts = 3;
		$attempt = 0;
		$last_wp_error = null;

		while ( $attempt < $max_attempts ) {
			$attempt++;

			$args = array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Basic ' . $this->credentials,
					'User-Agent'    => $this->supplier_id . ' - SelfIntegration',
					'Content-Type'  => 'application/json',
				),
			);

			$response = wp_remote_get( $url, $args );

			if ( is_wp_error( $response ) ) {
				$last_wp_error = $response;
				// Short exponential backoff (microseconds) before retrying, but keep low to avoid long cron blocking.
				if ( $attempt < $max_attempts ) {
					usleep( (int) ( 250000 * pow( 2, $attempt - 1 ) ) ); // 250ms, 500ms, 1s
					continue;
				}
				return $response;
			}

			$status_code = wp_remote_retrieve_response_code( $response );

			// Success
			if ( $status_code >= 200 && $status_code < 300 ) {
				return $this->handle_api_error( $response );
			}

			// Rate limited - use Retry-After if provided.
			if ( 429 === $status_code ) {
				$retry_after = wp_remote_retrieve_header( $response, 'retry-after' );
				$wait_seconds = 1;
				if ( $retry_after !== null && is_numeric( $retry_after ) ) {
					$wait_seconds = max( 1, intval( $retry_after ) );
				} else {
					$wait_seconds = (int) max( 1, pow( 2, $attempt - 1 ) );
				}

				if ( $attempt < $max_attempts ) {
					sleep( $wait_seconds );
					continue;
				}

				// Final attempt - return error processing
				return $this->handle_api_error( $response );
			}

			// Server errors (5xx) - retry with small backoff.
			if ( $status_code >= 500 && $status_code < 600 ) {
				if ( $attempt < $max_attempts ) {
					usleep( (int) ( 250000 * pow( 2, $attempt - 1 ) ) ); // 250ms..1s
					continue;
				}
				return $this->handle_api_error( $response );
			}

			// Other client errors (4xx except 429) - do not retry
			return $this->handle_api_error( $response );
		}

		// If we reach here, return last WP_Error if any
		if ( $last_wp_error instanceof WP_Error ) {
			return $last_wp_error;
		}

		return new WP_Error( 'api_error', __( 'Unknown API error', 'hbt-trendyol-profit-tracker' ) );
	}

	/**
	 * Handle API response errors with cleaner messages.
	 *
	 * @param mixed $response wp_remote_get() result.
	 * @return array|WP_Error
	 */
	public function handle_api_error( $response ) {
		if ( is_wp_error( $response ) ) {
			error_log( '[HBT TPT] API WP_Error: ' . $response->get_error_message() );
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( $status_code < 200 || $status_code >= 300 ) {
			$messages = array(
				401 => __( 'Unauthorized – check API credentials.', 'hbt-trendyol-profit-tracker' ),
				403 => __( 'Forbidden – insufficient permissions.', 'hbt-trendyol-profit-tracker' ),
				404 => __( 'Endpoint not found.', 'hbt-trendyol-profit-tracker' ),
				500 => __( 'Trendyol server error.', 'hbt-trendyol-profit-tracker' ),
			);
			$message = $messages[ $status_code ] ?? sprintf( __( 'HTTP Error %d', 'hbt-trendyol-profit-tracker' ), $status_code );
			error_log( "[HBT TPT] API Error {$status_code}: {$body}" );
			return new WP_Error( 'api_error', $message, array( 'status' => $status_code, 'body' => $body ) );
		}

		$data = json_decode( $body, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			error_log( '[HBT TPT] Invalid JSON from API: ' . json_last_error_msg() . ' / Body: ' . substr( $body, 0, 200 ) );
			return new WP_Error( 'json_error', __( 'Invalid JSON response from Trendyol.', 'hbt-trendyol-profit-tracker' ) );
		}

		return $data ?? array();
	}

	// -------------------------------------------------------------------------
	// Normalizers
	// -------------------------------------------------------------------------

	/**
	 * Normalize raw API order data into a consistent structure.
	 *
	 * @param  array $raw_orders Raw API order list.
	 * @return array
	 */
	private function normalize_orders( array $raw_orders ): array {
		$orders = array();

		foreach ( $raw_orders as $raw ) {
			$items = array();

			// Try several common keys where line items may be located.
			if ( isset( $raw['lines'] ) && is_array( $raw['lines'] ) ) {
				$lines = $raw['lines'];
			} elseif ( isset( $raw['items'] ) && is_array( $raw['items'] ) ) {
				$lines = $raw['items'];
			} elseif ( isset( $raw['orderItems'] ) && is_array( $raw['orderItems'] ) ) {
				$lines = $raw['orderItems'];
			} else {
				$lines = array();
			}

			foreach ( $lines as $line ) {
				// Default values
				$commission_amount = 0.0;
				$commission_rate   = null;

				// Possible field names for commission amount/rate.
$possible_amount_keys = array( 'commissionAmount', 'commission_amount', 'commissionValue', 'commissionAmountTL', 'sellerCommission', 'feeAmount' );
$possible_rate_keys   = array( 'commission', 'commissionRate', 'commission_rate', 'commissionPercent', 'commissionPercentRate' );

				foreach ( $possible_amount_keys as $k ) {
					if ( isset( $line[ $k ] ) && $line[ $k ] !== '' ) {
						$commission_amount = (float) $line[ $k ];
						break;
					}
				}

				foreach ( $possible_rate_keys as $k ) {
					if ( isset( $line[ $k ] ) && $line[ $k ] !== '' ) {
						$commission_rate = (float) $line[ $k ];
						break;
					}
				}

				// Some responses nest data under pricing/financial
				if ( $commission_amount == 0.0 ) {
					if ( isset( $line['pricing']['commissionAmount'] ) ) {
						$commission_amount = (float) $line['pricing']['commissionAmount'];
					} elseif ( isset( $line['financial']['commissionAmount'] ) ) {
						$commission_amount = (float) $line['financial']['commissionAmount'];
					}
				}
				if ( $commission_rate === null ) {
					if ( isset( $line['pricing']['commissionRate'] ) ) {
						$commission_rate = (float) $line['pricing']['commissionRate'];
					} elseif ( isset( $line['financial']['commissionRate'] ) ) {
						$commission_rate = (float) $line['financial']['commissionRate'];
					}
				}

				// Determine qty/price/line_total/discount with common fallbacks.
				$quantity   = isset( $line['quantity'] ) ? (int) $line['quantity'] : ( isset( $line['qty'] ) ? (int) $line['qty'] : 1 );
				$unit_price = isset( $line['unitPrice'] ) ? (float) $line['unitPrice'] : ( isset( $line['price'] ) ? (float) $line['price'] : 0.0 );
				$line_total = isset( $line['lineTotal'] ) ? (float) $line['lineTotal'] : ( isset( $line['totalPrice'] ) ? (float) $line['totalPrice'] : ( $unit_price * $quantity ) );
				$discount   = isset( $line['discount'] ) ? (float) $line['discount'] : 0.0;
				$vat_amount = isset( $line['vatAmount'] ) ? (float) $line['vatAmount'] : 0.0;

				// If only rate provided, compute amount from rate + line_total.
				if ( ( $commission_amount === 0.0 || $commission_amount === null ) && $commission_rate !== null ) {
					// Interpret commission_rate heuristically:
					// - if >= 1 => percent (e.g. 5.5 means 5.5%)
					// - if between 0 and 1 => fraction (e.g. 0.055 means 5.5%)
					if ( $commission_rate >= 1.0 ) {
						$commission_amount = round( ( $line_total * ( $commission_rate / 100.0 ) ), 4 );
					} else {
						$commission_amount = round( ( $line_total * $commission_rate ), 4 );
					}
				}// Eğer sadece komisyon tutarı geldiyse (oran null ise), oranı tutardan geriye doğru hesapla.
				elseif ( $commission_rate === null && $commission_amount > 0 && $line_total > 0 ) {
					$commission_rate = round( ( $commission_amount / $line_total ) * 100, 2 );
				}

				$items[] = array(
					'barcode'           => $line['barcode'] ?? $line['gtin'] ?? '',
					'sku'               => $line['stockCode'] ?? $line['sku'] ?? '',
					'product_name'      => $line['title'] ?? $line['productName'] ?? '',
					'quantity'          => $quantity,
					'unit_price'        => $unit_price,
					'line_total'        => $line_total,
					'discount'          => $discount,
					'vat_amount'        => $vat_amount,
					'commission_amount' => $commission_amount,
					'commission_rate'   => $commission_rate,
				);
			}

			// Customer name fallback
			$customer_first = $raw['shipmentAddress']['firstName'] ?? ( $raw['customerFirstName'] ?? '' );
			$customer_last  = $raw['shipmentAddress']['lastName'] ?? ( $raw['customerLastName'] ?? '' );

			$orders[] = array(
				'trendyol_id'    => (int) ( $raw['id'] ?? 0 ),
				'order_number'   => $raw['orderNumber'] ?? $raw['order_no'] ?? '',
				'status'         => $raw['status'] ?? '',
				'order_date'     => isset( $raw['orderDate'] ) ? gmdate( 'Y-m-d H:i:s', (int) ( $raw['orderDate'] / 1000 ) ) : ( $raw['orderDate'] ?? '' ),
				'customer_name'  => trim( $customer_first . ' ' . $customer_last ),
				'shipping_city'  => $raw['shipmentAddress']['city'] ?? $raw['shippingCity'] ?? '',
				'cargo_provider' => $raw['cargoProviderName'] ?? '',
				'items'          => $items,
				'gross_amount'   => (float) ( $raw['grossAmount'] ?? $raw['totalGrossAmount'] ?? 0 ),
				'total_discount' => (float) ( $raw['totalDiscount'] ?? 0 ),
				'total_price'    => (float) ( $raw['totalPrice'] ?? $raw['paidPrice'] ?? 0 ),
			);
		}

		return $orders;
	}

	/**
	 * Normalize raw API product data.
	 *
	 * @param  array $raw_products Raw API product list.
	 * @return array
	 */
	private function normalize_products( array $raw_products ): array {
		$products = array();

		foreach ( $raw_products as $raw ) {
			// Determine desi: use dimensionalWeight if available, else calculate from dimensions, else default 1.
			if ( ! empty( $raw['dimensionalWeight'] ) ) {
				$desi = (float) $raw['dimensionalWeight'];
			} elseif ( ! empty( $raw['width'] ) && ! empty( $raw['height'] ) && ! empty( $raw['length'] ) ) {
				$desi = (float) ceil( (float) $raw['width'] * (float) $raw['height'] * (float) $raw['length'] / 3000 );
			} else {
				$desi = 1.0;
			}

			$products[] = array(
				'barcode'       => $raw['barcode'] ?? '',
				'product_name'  => $raw['title'] ?? '',
				'category_name' => $raw['categoryName'] ?? '',
				'sku'           => $raw['stockCode'] ?? '',
				'image_url'     => $raw['images'][0]['url'] ?? '',
				'sale_price'    => (float) ( $raw['salePrice'] ?? 0 ),
				'trendyol_id'   => (int) ( $raw['id'] ?? 0 ),
				'desi'          => $desi,
			);
		}

		return $products;
	}

	/**
	 * Normalize raw financial transaction data.
	 *
	 * @param  array $raw_transactions Raw API transaction list.
	 * @return array
	 */
	private function normalize_transactions( array $raw_transactions ): array {
		$transactions = array();

		foreach ( $raw_transactions as $raw ) {
			$transactions[] = array(
				'transaction_id'   => $raw['id'] ?? '',
				'transaction_type' => $raw['transactionType'] ?? '',
				'order_number'     => $raw['orderNumber'] ?? '',
				'amount'           => (float) ( $raw['amount'] ?? $raw['credit'] ?? 0 ),
				'description'      => $raw['description'] ?? '',
				'transaction_date' => isset( $raw['transactionDate'] ) ? gmdate( 'Y-m-d H:i:s', (int) ( $raw['transactionDate'] / 1000 ) ) : '',
			);
		}

		return $transactions;
	}
}