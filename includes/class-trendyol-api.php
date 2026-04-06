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
	 */
	private const BASE_URL = 'https://api.trendyol.com/sapigw/';

	/**
	 * Trendyol Ürünler API yeni sunucu adresi.
	 */
	private const PRODUCT_API_URL = 'https://apigw.trendyol.com/';

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
	public function get_orders( int $start_date, int $end_date, ?int $page = null, int $size = 200 ) {
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
	 */
	public function get_products( int $page = 0, int $size = 50 ) {
		$params = array( 
			'page'     => $page, 
			'size'     => $size,
			'approved' => 'true' 
		);
		
		// DİKKAT: Burada BASE_URL yerine yeni PRODUCT_API_URL kullanıyoruz
		$endpoint = "integration/product/sellers/{$this->supplier_id}/products?" . http_build_query( $params );
		
		// make_request fonksiyonunda URL birleştirme kısmını bypass etmek için tam URL gönderiyoruz
		$full_url = self::PRODUCT_API_URL . $endpoint;
		
		// make_request'i güncellemediğimiz için bu fonksiyonu küçük bir hile ile tam URL ile çağırmalıyız.
		// Bu yüzden aşağıda paylaştığım güncel get_products yapısını kullanın:
		
		$response = $this->make_request_v2( $full_url ); // Yeni bir yardımcı metod

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$all_products = $response['content'] ?? array();
		$total_pages  = $response['totalPages'] ?? 1;

		for ( $p = 1; $p < $total_pages; $p++ ) {
			$params['page'] = $p;
			$next_url       = self::PRODUCT_API_URL . "integration/product/sellers/{$this->supplier_id}/products?" . http_build_query( $params );
			$page_response  = $this->make_request_v2( $next_url );

			if ( is_wp_error( $page_response ) ) {
				break;
			}

			$all_products = array_merge( $all_products, $page_response['content'] ?? array() );
		}

		return $this->normalize_products( (array) $all_products );
	}
	/**
	 * Ürünler için özel istek metodu (Mevcut make_request'i bozmamak için)
	 */
	private function make_request_v2( string $full_url ) {
		$args = array(
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Basic ' . $this->credentials,
				'User-Agent'    => $this->supplier_id . ' - SelfIntegration',
				'Content-Type'  => 'application/json',
			),
		);

		$response = wp_remote_get( $full_url, $args );
		return $this->handle_api_error( $response );
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
	 * Make an API GET request with advanced cURL Cloudflare Bypass.
	 */
	public function make_request( string $endpoint, array $params = array() ) {
		$supplier_id = trim($this->supplier_id);
		if ( empty( $supplier_id ) ) {
			return new WP_Error( 'missing_id', "HATA: Mağazanın Satıcı ID bilgisi boş!" );
		}

		$url = self::BASE_URL . $endpoint;
		$user_agent = $supplier_id . ' - SelfIntegration';

		$max_attempts = 3;
		$attempt = 0;

		while ( $attempt < $max_attempts ) {
			$attempt++;

			if ( function_exists( 'curl_init' ) ) {
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_TIMEOUT, 45); 
				
				// Cloudflare Bypass Sinyalleri
				curl_setopt($ch, CURLOPT_ENCODING, ''); 
				curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
				curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
				curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);

				curl_setopt($ch, CURLOPT_HTTPHEADER, array(
					'Authorization: Basic ' . $this->credentials,
					'Content-Type: application/json',
					'Accept: application/json, text/plain, */*',
					'Cache-Control: no-cache',
					'Connection: keep-alive'
				));

				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

				$body = curl_exec($ch);
				$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				$curl_error = curl_error($ch);
				curl_close($ch);

				if ($body === false) {
					if ( $attempt < $max_attempts ) { usleep( 250000 ); continue; }
					return new WP_Error( 'curl_error', 'Bağlantı hatası: ' . $curl_error );
				}

				if ( $status_code >= 200 && $status_code < 300 ) {
					$parsed = json_decode( $body, true );
					if ( json_last_error() === JSON_ERROR_NONE ) {
						return $parsed;
					}
					return new WP_Error( 'json_parse_error', 'Gelen veri bozuk JSON formatında.' );
				}

				if ( $status_code === 403 || $status_code === 401 ) {
					return new WP_Error( 'api_error', "CLOUDFLARE YAKALADI! Kod: $status_code | Yanıt: " . substr($body, 0, 150) );
				}

				if ( 429 === $status_code ) {
					if ( $attempt < $max_attempts ) { sleep( 1 ); continue; }
					return new WP_Error( 'api_error', 'Trendyol: Çok fazla istek (429).' );
				}

				if ( $status_code >= 500 && $status_code < 600 ) {
					if ( $attempt < $max_attempts ) { usleep( 250000 ); continue; }
					return new WP_Error( 'api_error', 'Trendyol sunucu hatası (5xx).' );
				}

				return new WP_Error( 'api_error', 'API Hata Kodu: ' . $status_code );

			} else {
				return new WP_Error('curl_missing', 'cURL sunucunuzda aktif değil.');
			}
		}

		return new WP_Error( 'api_error', 'Bilinmeyen API Hatası' );
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

	/**
	 * Normalize raw API order data into a consistent structure.
	 * Trendyol 6 Nisan 2026 API güncellemesine tam uyumludur.
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

				// ---- TRENDYOL 6 NİSAN API GÜNCELLEMESİ ALANLARI ----

				$quantity = isset( $line['quantity'] ) ? (int) $line['quantity'] : ( isset( $line['qty'] ) ? (int) $line['qty'] : 1 );
				
				// Eski: 'price' -> Yeni: 'lineUnitPrice'
				$unit_price = isset( $line['lineUnitPrice'] ) ? (float) $line['lineUnitPrice'] : ( isset( $line['price'] ) ? (float) $line['price'] : 0.0 );
				
				// Eski: 'discount' -> Yeni: 'lineTotalDiscount' veya 'lineSellerDiscount'
				$discount = isset( $line['lineTotalDiscount'] ) ? (float) $line['lineTotalDiscount'] : ( isset( $line['lineSellerDiscount'] ) ? (float) $line['lineSellerDiscount'] : ( isset( $line['discount'] ) ? (float) $line['discount'] : 0.0 ) );
				
				$line_total = isset( $line['lineTotal'] ) ? (float) $line['lineTotal'] : ( isset( $line['totalPrice'] ) ? (float) $line['totalPrice'] : ( $unit_price * $quantity ) );
				
				$vat_amount = isset( $line['vatAmount'] ) ? (float) $line['vatAmount'] : 0.0;

				// Eğer sadece komisyon oranı verildiyse (tutar yoksa), tutarı hesapla.
				if ( ( $commission_amount === 0.0 || $commission_amount === null ) && $commission_rate !== null ) {
					if ( $commission_rate >= 1.0 ) {
						$commission_amount = round( ( $line_total * ( $commission_rate / 100.0 ) ), 4 );
					} else {
						$commission_amount = round( ( $line_total * $commission_rate ), 4 );
					}
				} // Eğer sadece komisyon tutarı geldiyse (oran null ise), oranı tutardan geriye doğru hesapla.
				elseif ( $commission_rate === null && $commission_amount > 0 && $line_total > 0 ) {
					$commission_rate = round( ( $commission_amount / $line_total ) * 100, 2 );
				}

				// Eski 'sku' kaldırıldı, 'merchantSku' ise 'stockCode' oldu.
				$sku = $line['stockCode'] ?? $line['merchantSku'] ?? $line['sku'] ?? '';

				$items[] = array(
					'barcode'           => $line['barcode'] ?? $line['gtin'] ?? '',
					'sku'               => $sku,
					'product_name'      => $line['productName'] ?? $line['title'] ?? '',
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

			// ---- TRENDYOL 6 NİSAN API GÜNCELLEMESİ (SİPARİŞ ANA VERİSİ) ----
			// 'id' -> 'shipmentPackageId'
			$trendyol_id = (int) ( $raw['shipmentPackageId'] ?? $raw['id'] ?? 0 );
			
			// 'grossAmount' -> 'packageGrossAmount'
			$gross_amount = (float) ( $raw['packageGrossAmount'] ?? $raw['grossAmount'] ?? $raw['totalGrossAmount'] ?? 0 );
			
			// 'totalDiscount' -> 'packageTotalDiscount' veya 'packageSellerDiscount'
			$total_discount = (float) ( $raw['packageTotalDiscount'] ?? $raw['packageSellerDiscount'] ?? $raw['totalDiscount'] ?? 0 );
			
			// 'totalPrice' -> 'packageTotalPrice'
			$total_price = (float) ( $raw['packageTotalPrice'] ?? $raw['totalPrice'] ?? $raw['paidPrice'] ?? 0 );

			$orders[] = array(
				'trendyol_id'    => $trendyol_id,
				'order_number'   => $raw['orderNumber'] ?? $raw['order_no'] ?? '',
				'status'         => $raw['status'] ?? '',
				'order_date'     => isset( $raw['orderDate'] ) ? gmdate( 'Y-m-d H:i:s', (int) ( $raw['orderDate'] / 1000 ) ) : ( $raw['orderDate'] ?? '' ),
				'customer_name'  => trim( $customer_first . ' ' . $customer_last ),
				'shipping_city'  => $raw['shipmentAddress']['city'] ?? $raw['shippingCity'] ?? '',
				'cargo_provider' => $raw['cargoProviderName'] ?? '',
				'items'          => $items,
				'gross_amount'   => $gross_amount,
				'total_discount' => $total_discount,
				'total_price'    => $total_price,
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