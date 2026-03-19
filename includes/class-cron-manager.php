<?php
/**
 * Cron manager class.
 *
 * @package HBT_Trendyol_Profit_Tracker
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class HBT_Cron_Manager
 *
 * Manages all scheduled WordPress cron jobs for the plugin.
 */
class HBT_Cron_Manager {

	/**
	 * Singleton instance.
	 *
	 * @var HBT_Cron_Manager
	 */
	private static ?HBT_Cron_Manager $instance = null;

	/**
	 * Database instance.
	 *
	 * @var HBT_Database
	 */
	private HBT_Database $db;

	/**
	 * List of cron events managed by this plugin.
	 *
	 * @var array
	 */
	private array $events = array(
		'hbt_sync_orders_fast'       => 'hbt_every_5_min', // Hızlı Döngü 5 dakikaya çekildi
		'hbt_sync_orders_deep'       => 'hbt_every_6_hours', // YENİ: Derin Döngü (Son 15 Gün)
		'hbt_sync_currency'          => 'hourly',
		'hbt_sync_financials'        => 'hbt_every_6_hours',
		'hbt_run_calculations'       => 'hourly',
		'hbt_check_returns'          => 'hbt_every_2_hours',
		'hbt_sync_products'          => 'daily',
		'hbt_cleanup_notifications'  => 'weekly',
		'hbt_process_background_queue' => 'hbt_every_min',
		'hbt_send_daily_report'      => 'hourly', // Her saat kontrol edeceğiz, vakti gelince atacağız
	);

	/**
	 * Option key used to persist background queue.
	 */
	private const QUEUE_OPTION = 'hbt_background_queue';

	/**
	 * Get singleton instance.
	 *
	 * @return HBT_Cron_Manager
	 */
	public static function instance(): HBT_Cron_Manager {
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

		// Register custom intervals.
		add_filter( 'cron_schedules', array( $this, 'add_custom_intervals' ) );

		// Register cron callbacks (YENİ SİSTEM).
		add_action( 'hbt_sync_orders_fast', array( $this, 'sync_orders_fast' ) );
		add_action( 'hbt_sync_orders_deep', array( $this, 'sync_orders_deep' ) );
		add_action( 'hbt_sync_currency', array( $this, 'sync_currency_rates' ) );
		add_action( 'hbt_sync_financials', array( $this, 'sync_financial_data' ) );
		add_action( 'hbt_run_calculations', array( $this, 'run_calculations' ) );
		add_action( 'hbt_check_returns', array( $this, 'check_returns' ) );
		add_action( 'hbt_sync_products', array( $this, 'sync_products' ) );
		add_action( 'hbt_cleanup_notifications', array( $this, 'cleanup_notifications' ) );
		add_action( 'hbt_send_daily_report', array( $this, 'send_daily_email_report' ) );

		// Background queue worker hook (light, bounded work)
		add_action( 'hbt_process_background_queue', array( $this, 'process_background_queue' ) );

		// YENİ: Otomatik Cron Onarıcı (Sistem zamanlanmamış görev görürse kendi kendini tamir eder)
		add_action( 'admin_init', array( $this, 'auto_heal_crons' ) );
	}

	/**
	 * Zamanlanmamış yeni görevleri otomatik tespit edip kurar.
	 */
	public function auto_heal_crons(): void {
		if ( ! wp_next_scheduled( 'hbt_process_background_queue' ) || ! wp_next_scheduled( 'hbt_sync_orders_fast' ) ) {
			$this->schedule_events();
		}
	}
	

	// -------------------------------------------------------------------------
	// Schedule management
	// -------------------------------------------------------------------------

	/**
	 * Add custom cron intervals.
	 *
	 * @param  array $schedules Existing schedules.
	 * @return array
	 */
	public function add_custom_intervals( array $schedules ): array {
		$schedules['hbt_every_5_min'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 5 Minutes', 'hbt-trendyol-profit-tracker' ),
		);

		$schedules['hbt_every_15_min'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 15 Minutes', 'hbt-trendyol-profit-tracker' ),
		);

		$schedules['hbt_every_30_min'] = array(
			'interval' => 30 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 30 Minutes', 'hbt-trendyol-profit-tracker' ),
		);

		$schedules['hbt_every_2_hours'] = array(
			'interval' => 2 * HOUR_IN_SECONDS,
			'display'  => __( 'Every 2 Hours', 'hbt-trendyol-profit-tracker' ),
		);

		$schedules['hbt_every_6_hours'] = array(
			'interval' => 6 * HOUR_IN_SECONDS,
			'display'  => __( 'Every 6 Hours', 'hbt-trendyol-profit-tracker' ),
		);

		$schedules['hbt_every_min'] = array(
			'interval' => 60,
			'display'  => __( 'Every Minute', 'hbt-trendyol-profit-tracker' ),
		);

		return $schedules;
	}

	/**
	 * Schedule all plugin cron events.
	 */
	public function schedule_events(): void {
		foreach ( $this->events as $hook => $interval ) {
			if ( ! wp_next_scheduled( $hook ) ) {
				wp_schedule_event( time(), $interval, $hook );
			}
		}
	}

	/**
	 * Remove all plugin cron events.
	 */
	public function unschedule_events(): void {
		foreach ( array_keys( $this->events ) as $hook ) {
			$timestamp = wp_next_scheduled( $hook );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
			}
		}
	}

	// -------------------------------------------------------------------------
	// Cron callbacks
	// -------------------------------------------------------------------------

	/**
	 * Enqueue store sync job into background queue (GÜNCELLENDİ).
	 */
	public function enqueue_store_sync( $store, ?string $start_date = null, ?string $end_date = null, ?int $page = 0, int $size = 100, string $sync_type = 'manual' ): string {
		$queue = get_option( self::QUEUE_OPTION, array() );
		if ( ! is_array( $queue ) ) $queue = array();

		// --- YENİ EKLENEN KONTROL (Kuyruk Şişmesini ve Atlamayı Önler) ---
		// Eğer bu mağaza bu işlem tipiyle (örn: auto_fast) zaten sırada bekliyorsa, 
		// işlemi tekrar sıraya sokma ve mevcut olanı sakince bekle.
		foreach ( $queue as $existing_job ) {
			if ( $existing_job['store_id'] == $store->id && $existing_job['sync_type'] === $sync_type ) {
				return $existing_job['id']; // Zaten var, eklemeyi durdur!
			}
		}
		// ------------------------------------------------------------------

		$job = array(
			'id'         => uniqid( 'hbtjob_', true ),
			'store_id'   => (int) $store->id,
			'sync_type'  => $sync_type,
			'start_date' => $start_date,
			'end_date'   => $end_date,
			'page'       => $page ?? 0,
			'size'       => $size,
			'retries'    => 0,
			'fetched'    => 0,
			'inserted'   => 0,
			'updated'    => 0,
			'skipped'    => 0,
			'failed'     => 0,
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		);

		$queue[] = $job;
		update_option( self::QUEUE_OPTION, $queue );

		return $job['id'];
	}

	/**
	 * Process background queue (GÜNCELLENDİ: Artık Log Tablosuna Yazıyor).
	 */
	public function process_background_queue(): void {
		$queue = get_option( self::QUEUE_OPTION, array() );
		if ( ! is_array( $queue ) || empty( $queue ) ) {
			return;
		}

		$max_jobs_per_run = 6; // Dakikada 6 görev işleyerek hızı 3 katına çıkardık
		$changed = false;

		for ( $i = 0; $i < $max_jobs_per_run && ! empty( $queue ); $i++ ) {
			$job = $queue[0];

			$store = $this->db->get_store( (int) $job['store_id'] );
			if ( ! $store ) {
				array_shift( $queue );
				$changed = true;
				continue;
			}

			$page = isset( $job['page'] ) ? (int) $job['page'] : 0;
			$size = isset( $job['size'] ) ? (int) $job['size'] : 200;
			$sync_type = isset( $job['sync_type'] ) ? $job['sync_type'] : 'manual';

			$result = $this->sync_store_orders( $store, $page, $size, $job['start_date'] ?? null, $job['end_date'] ?? null );

			if ( is_wp_error( $result ) ) {
				$job['retries'] = (int) ( $job['retries'] ?? 0 ) + 1;
				$job['updated_at'] = current_time( 'mysql' );

				if ( $job['retries'] > 3 ) {
					// 3 Kere denedi olmadıysa log tablosuna HATA yaz ve işi iptal et
					$this->db->log_sync_result( array(
						'store_id'   => $store->id,
						'store_name' => $store->store_name,
						'sync_type'  => $sync_type,
						'fetched'    => $job['fetched'] ?? 0,
						'inserted'   => $job['inserted'] ?? 0,
						'updated'    => $job['updated'] ?? 0,
						'skipped'    => $job['skipped'] ?? 0,
						'failed'     => $job['failed'] ?? 0,
						'message'    => 'Sistem 3 kez denedi ancak Trendyol API yanıt vermedi. Hata: ' . esc_html( $result->get_error_message() ),
						'status'     => 'error'
					) );

					HBT_Notification_Manager::instance()->create_notification(
						'sync_error',
						__( 'Arka Plan Senkronizasyon Hatası', 'hbt-trendyol-profit-tracker' ),
						sprintf(
							__( '%1$s mağazası için arka plan senkronizasyonu başarısız oldu: %2$s', 'hbt-trendyol-profit-tracker' ),
							esc_html( $store->store_name ),
							__( 'Çok fazla deneme, iş iptal edildi.', 'hbt-trendyol-profit-tracker' )
						),
						null
					);

					array_shift( $queue );
				} else {
					array_shift( $queue );
					$queue[] = $job;
				}
				$changed = true;
			} else {
				// BAŞARILI ÇEKİM: İstatistikleri güncelle
				$returned = is_array( $result ) && isset( $result['returned'] ) ? (int) $result['returned'] : 0;
				
				// DÜZELTME: API'den gelen asıl sayıyı ve Atlanan (Skipped) verisini kaydet
				$job['fetched']  = (int)($job['fetched'] ?? 0) + $returned;
				$job['inserted'] = (int)($job['inserted'] ?? 0) + (int)($result['inserted'] ?? 0);
				$job['updated']  = (int)($job['updated'] ?? 0) + (int)($result['updated'] ?? 0);
				$job['skipped']  = (int)($job['skipped'] ?? 0) + (int)($result['skipped'] ?? 0);
				$job['failed']   = (int)($job['failed'] ?? 0) + (int)($result['failed'] ?? 0);

				if ( $returned >= $size ) {
					$job['page'] = $page + 1;
					$job['updated_at'] = current_time( 'mysql' );

					array_shift( $queue );
					$queue[] = $job;
				} else {
					// İŞ TAMAMLANDI: Süreyi ve detaylı raporu hesapla
					$s_type_label = $sync_type === 'auto_fast' ? 'Hızlı Tarama' : ($sync_type === 'auto_deep' ? 'Derin Tarama' : 'Manuel Tarama');
					$time_taken   = max(1, current_time('timestamp') - strtotime($job['created_at']));
					$time_str     = $time_taken >= 60 ? floor($time_taken / 60) . ' dk ' . ($time_taken % 60) . ' sn' : $time_taken . ' saniye';
					
					$rich_message = sprintf(
						"[%s] Rapor: Toplam %d sipariş çekildi. (%d Yeni, %d Güncellenen, %d Atlanan). İşlem süresi: %s",
						$s_type_label,
						$job['fetched'],
						$job['inserted'],
						$job['updated'],
						$job['skipped'],
						$time_str
					);

					$this->db->log_sync_result( array(
						'store_id'   => $store->id,
						'store_name' => $store->store_name,
						'sync_type'  => $sync_type,
						'fetched'    => $job['fetched'],
						'inserted'   => $job['inserted'],
						'updated'    => $job['updated'],
						'skipped'    => $job['skipped'],
						'failed'     => $job['failed'],
						'message'    => $rich_message,
						'status'     => 'success'
					) );

					array_shift( $queue );
				}
				$changed = true;
			}
		}

		if ( $changed ) {
			update_option( self::QUEUE_OPTION, $queue );
		}
	}

	/**
	 * Hızlı Döngü: Sadece son 24 saati tarar (Yeni siparişleri içeri almak için)
	 */
	public function sync_orders_fast(): void {
		$stores = $this->db->get_stores( true );
		$tz = new DateTimeZone('Europe/Istanbul');
		$dt = new DateTime('now', $tz);
		$end_date = $dt->format('Y-m-d\TH:i:s');
		$dt->modify('-6 hours'); // Hızlı tarama süresi 6 saate çıkarıldı
		$start_date = $dt->format('Y-m-d\TH:i:s');
		
		foreach ( $stores as $store ) {
			// Limit 100'den 200'e çıkarıldı
			$this->enqueue_store_sync( $store, $start_date, $end_date, 0, 200, 'auto_fast' );
		}
	}

	/**
	 * Derin Döngü: Son 15 günü tarar (İptal ve iade durumlarını güncellemek için)
	 */
	public function sync_orders_deep(): void {
		$stores = $this->db->get_stores( true );
		$tz = new DateTimeZone('Europe/Istanbul');
		$dt = new DateTime('now', $tz);
		$end_date = $dt->format('Y-m-d\TH:i:s');
		$dt->modify('-14 days');
		$start_date = $dt->format('Y-m-d\TH:i:s');
		
		foreach ( $stores as $store ) {
			$this->enqueue_store_sync( $store, $start_date, $end_date, 0, 200, 'auto_deep' );
		}
	}

	/**
	 * Sizin orijinal, hatasız sipariş çekme kodunuz (HİÇ DOKUNULMADI)
	 */
	public function sync_store_orders( $store, $page = null, $size = 100, $start_date = null, $end_date = null ) {
		$api = new HBT_Trendyol_API( $store );

		// 1. TARİH HESAPLAMA MANTIĞI EKLENDİ
		// 1. TARİH HESAPLAMA MANTIĞI
		if ( ! empty( $start_date ) && ! empty( $end_date ) ) {
			$tz = new DateTimeZone('Europe/Istanbul');
			
			// Seçilen tam saati epoch (milisaniye) formatına çevir
			$api_start_dt = new DateTime( $start_date, $tz );
			$start_ms = $api_start_dt->getTimestamp() * 1000;
			
			$api_end_dt = new DateTime( $end_date, $tz );
			$end_ms = $api_end_dt->getTimestamp() * 1000;
		} else {
			// Cron ile otomatik çalışıyorsa son 14 gün
			$end_ms   = time() * 1000;
			$start_ms = $end_ms - ( 14 * DAY_IN_SECONDS * 1000 );
		}

		// Eğer sayfa bilgisi gönderilmemişse (Klasik/Cron çalışma modu)
		if ( is_null( $page ) ) {
			$orders = $api->get_orders( (int) $start_ms, (int) $end_ms );
			if ( is_wp_error( $orders ) ) {
				HBT_Notification_Manager::instance()->create_notification(
					'sync_error',
					__( 'Sipariş Senkronizasyon Hatası', 'hbt-trendyol-profit-tracker' ),
					sprintf(
						/* translators: 1: store name, 2: error message */
						__( '%1$s mağazası senkronize edilemedi: %2$s', 'hbt-trendyol-profit-tracker' ),
						esc_html( $store->store_name ),
						esc_html( $orders->get_error_message() )
					)
				);
				return $orders;
			}
		} else {
			// Manuel/Sayfalı çalışma modu (AJAX çağrısı or worker)
			$orders = $api->get_orders( (int) $start_ms, (int) $end_ms, (int) $page, (int) $size );
			if ( is_wp_error( $orders ) ) {
				HBT_Notification_Manager::instance()->create_notification(
					'sync_error',
					__( 'Sipariş Senkronizasyon Hatası', 'hbt-trendyol-profit-tracker' ),
					sprintf(
						/* translators: 1: store name, 2: error message */
						__( '%1$s mağazası manuel/arka plan senkronize edilemedi: %2$s', 'hbt-trendyol-profit-tracker' ),
						esc_html( $store->store_name ),
						esc_html( $orders->get_error_message() )
					)
				);
				return $orders;
			}
		}

		// API'den gelen ham sipariş verisini (ilk 5 siparişi) debug için diziye ekle
		$debug_api_data = array();
		if ( ! empty( $orders ) && is_array( $orders ) ) {
			$debug_api_data = array_slice( $orders, 0, 5 ); // Çok uzun olmaması için ilk 5 siparişi al
		}

		// Hatalı/Gereksiz PHP Filtresi kaldırıldı. API artık milisaniye bazında en doğru veriyi veriyor.
		$returned_count = 0;
		if ( ! empty( $orders ) && is_array( $orders ) ) {
			$returned_count = count( $orders ); 
		}

		// Senkronizasyon sonrası API verilerini JSON olarak kaydet
		if ( wp_doing_ajax() || isset( $_GET['hbt_manual_sync'] ) ) {
			$sync_data = array(
				'timestamp'    => date( 'Y-m-d H:i:s' ),
				'store_id'     => $store->id,
				'api_raw_data' => $orders, 
			);
			@file_put_contents( WP_CONTENT_DIR . '/hbt_last_sync.json', wp_json_encode( $sync_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
		}

		// Manuel senkronizasyon sonrası komisyon loglarını admin notice ile göster
		if ( isset( $_GET['hbt_manual_sync'] ) && is_admin() ) {
			add_action( 'admin_notices', function() {
				$log_path = WP_CONTENT_DIR . '/debug.log';
				if ( file_exists( $log_path ) ) {
					$lines = file( $log_path );
					$filtered = array_filter( $lines, function( $line ) {
						return strpos( $line, 'OrderItemLog:' ) !== false;
					} );
					if ( ! empty( $filtered ) ) {
						printf( '<div class="notice notice-info"><p><strong>Komisyon Logları:</strong><br>%s</p></div>', nl2br( implode( "", $filtered ) ) );
					} else {
						printf( '<div class="notice notice-warning"><p><strong>Komisyon Logu bulunamadı.</strong></p></div>' );
					}
				}
			} );
		}
		
		$saved_count = 0;
		$new_count = 0; 
		$updated_count = 0; 
		$skipped_count = 0; 
		$failed_count = 0; // YENİ: Kaydedilemeyen siparişleri sayacak
		$saved_order_ids = array();
		$min_order_ts = null;
		$max_order_ts = null;

		if ( ! empty( $orders ) && is_array( $orders ) ) {
			foreach ( $orders as $order ) {
				// save_order upsert by store_id + trendyol_id
				$save_result = $this->db->save_order(
					array(
						'store_id'       => (int) $store->id,
						'order_number'   => sanitize_text_field( $order['order_number'] ),
						'trendyol_id'    => (string) $order['trendyol_id'], 
						'order_date'     => sanitize_text_field( $order['order_date'] ),
						'status'         => sanitize_text_field( $order['status'] ),
						'customer_name'  => sanitize_text_field( $order['customer_name'] ),
						'shipping_city'  => sanitize_text_field( $order['shipping_city'] ),
						'cargo_provider' => sanitize_text_field( $order['cargo_provider'] ),
						'gross_amount'   => (float) $order['gross_amount'],
						'total_discount' => (float) $order['total_discount'],
						'total_price'    => (float) $order['total_price'],
					)
				);

				if ( $save_result ) {
					$order_id = $save_result['id'];
					$action   = $save_result['action'];
					
					// Gelişmiş Loglama İçin Sipariş Detaylarını Hazırla
					$store_name = esc_html($store->store_name);
					$customer   = sanitize_text_field($order['customer_name'] ?? 'Bilinmeyen Müşteri');
					$price      = number_format((float)($order['total_price'] ?? 0), 2, ',', '.') . ' TL';
					$ord_no     = sanitize_text_field($order['order_number']);
					$status     = sanitize_text_field($order['status'] ?? 'Durum Değişti');

					if ( $action === 'insert' ) {
						$new_count++;
						$stream_buffer[] = array(
							'id'   => uniqid(), 
							'type' => 'insert', 
							'msg'  => "YENİ SİPARİŞ: {$store_name} mağazasından {$customer}, {$price} tutarında sipariş verdi. (#{$ord_no})", 
							'time' => date('H:i:s')
						);
					}
					elseif ( $action === 'update' ) {
						$updated_count++;
						$stream_buffer[] = array(
							'id'   => uniqid(), 
							'type' => 'update', 
							'msg'  => "GÜNCELLEME: {$price} tutarındaki (#{$ord_no}) siparişin durumu [{$status}] olarak güncellendi.", 
							'time' => date('H:i:s')
						);
					}
					elseif ( $action === 'none' ) {
						$skipped_count++;
					}
				} else {
					$order_id = false;
					$failed_count++; // YENİ: DB Hatası verenler
				}


				// Track min/max order timestamp for later financial transaction fetch
				if ( ! empty( $order['order_date'] ) ) {
					$ts = strtotime( $order['order_date'] );
					if ( $ts ) {
						$ms = $ts * 1000;
						$min_order_ts = $min_order_ts === null ? $ms : min( $min_order_ts, $ms );
						$max_order_ts = $max_order_ts === null ? $ms : max( $max_order_ts, $ms );
					}
				}

				// Eğer sipariş yeni eklendiyse veya güncellendiyse sipariş kalemlerini işlet ve komisyonları hesapla
				if ( $order_id && ( !isset($action) || $action === 'insert' || $action === 'update' ) ) {
					global $wpdb;
					$order_has_default_comm = false; // BAYRAK

					// YENİ: Siparişin veritabanındaki mevcut durumunu kontrol et (Daha önce gerçek komisyon işlenmiş mi?)
					$current_is_defaulted = 1;
					if ( isset($action) && $action === 'update' ) {
						$current_is_defaulted = (int) $wpdb->get_var( $wpdb->prepare( "SELECT is_comm_defaulted FROM {$wpdb->prefix}hbt_orders WHERE id = %d", $order_id ) );
					}

					// ---- SAVING ITEMS: include commission fields from API if present (robust) ----
					foreach ( $order['items'] as $item ) {
						// DEBUG: Komisyon oranı ve tutarı logla
						if ( defined('WP_DEBUG') && WP_DEBUG ) {
							$debug_log = sprintf(
								'OrderItemLog: order_id=%s, sku=%s, barcode=%s, commission_amount=%s, commission_rate=%s',
								$order_id,
								isset($item['sku']) ? $item['sku'] : '',
								isset($item['barcode']) ? $item['barcode'] : '',
								var_export($commission_amount ?? null, true),
								var_export($commission_rate ?? null, true)
							);
							error_log($debug_log);
						}
						// Normalize commission fields from API (may be absent).
						$commission_amount = 0.0;
						$commission_rate   = null;

						// Direct normalized keys (from normalize_orders)
						if ( isset( $item['commission_amount'] ) ) {
							$commission_amount = (float) $item['commission_amount'];
						}
						if ( isset( $item['commission_rate'] ) ) {
							$commission_rate = $item['commission_rate'] !== '' ? (float) $item['commission_rate'] : null;
						}

						// Alternative keys
						$alt_amount_keys = array( 'commissionAmount', 'commission', 'commissionValue', 'commissionAmountTL', 'sellerCommission', 'feeAmount', 'commissionAmountTried' );
						foreach ( $alt_amount_keys as $k ) {
							if ( $commission_amount == 0.0 && isset( $item[ $k ] ) && $item[ $k ] !== '' ) {
								$commission_amount = (float) $item[ $k ];
								break;
							}
						}
						$alt_rate_keys = array( 'commissionRate', 'commission_percent', 'commissionPercent', 'commission_rate', 'commissionPercentRate' );
						foreach ( $alt_rate_keys as $k ) {
							if ( $commission_rate === null && isset( $item[ $k ] ) && $item[ $k ] !== '' ) {
								$commission_rate = (float) $item[ $k ];
								break;
							}
						}

						// Nested possible keys
						$nested_paths = array( 'pricing', 'financial', 'pricingDetail', 'payment' );
						foreach ( $nested_paths as $path ) {
							if ( isset( $item[ $path ] ) && is_array( $item[ $path ] ) ) {
								$sub = $item[ $path ];
								foreach ( $alt_amount_keys as $k ) {
									if ( $commission_amount == 0.0 && isset( $sub[ $k ] ) && $sub[ $k ] !== '' ) {
										$commission_amount = (float) $sub[ $k ];
										break 2;
									}
								}
								foreach ( $alt_rate_keys as $k ) {
									if ( $commission_rate === null && isset( $sub[ $k ] ) && $sub[ $k ] !== '' ) {
										$commission_rate = (float) $sub[ $k ];
										break 2;
									}
								}
							}
						}

						// Decode details if stringified JSON present
						if ( $commission_amount == 0.0 && isset( $item['details'] ) && is_string( $item['details'] ) ) {
							$try = json_decode( $item['details'], true );
							if ( is_array( $try ) ) {
								foreach ( $alt_amount_keys as $k ) {
									if ( $commission_amount == 0.0 && isset( $try[ $k ] ) ) {
										$commission_amount = (float) $try[ $k ];
										break;
									}
								}
								foreach ( $alt_rate_keys as $k ) {
									if ( $commission_rate === null && isset( $try[ $k ] ) ) {
										$commission_rate = (float) $try[ $k ];
										break;
									}
								}
							}
						}

						// Compute commission amount from rate if amount missing but rate provided.
						$line_total = isset( $item['line_total'] ) ? (float) $item['line_total'] : ( isset( $item['unit_price'] ) ? ( (float) $item['unit_price'] * ( (int) ($item['quantity'] ?? 1) ) ) : 0.0 );
						if ( ( $commission_amount === 0.0 || $commission_amount === null ) && $commission_rate !== null ) {
							if ( $commission_rate >= 1.0 ) {
								$commission_amount = round( $line_total * ( $commission_rate / 100.0 ), 2 );
							} else {
								$commission_amount = round( $line_total * $commission_rate, 2 );
							}
						}

						// --- EK: Komisyon oranı eksikse, tutardan oranı hesapla ---
						if ( ($commission_rate === null || $commission_rate === 0.0) && $commission_amount > 0.0 && $line_total > 0.0 ) {
							$commission_rate = round( ($commission_amount / $line_total) * 100, 4 );
						}

						// YENİ DÜZELTME: Hem oran hem tutar yoksa, ÖNCEKİ GERÇEK KOMİSYONU KORU ya da %19 standart komisyon uygula
						if ( ($commission_rate === null || $commission_rate === 0.0) && ($commission_amount === null || $commission_amount === 0.0) && $line_total > 0.0 ) {
							
							$keep_existing = false;
							
							// Sipariş güncelleniyorsa ve zaten gerçek bir komisyon (0) işlenmişse, eski veriyi koru (Üzerine %19 yazma!)
							if ( isset($action) && $action === 'update' && $current_is_defaulted === 0 ) {
								$existing_item = $wpdb->get_row( $wpdb->prepare(
									"SELECT commission_amount, commission_rate FROM {$wpdb->prefix}hbt_order_items WHERE order_id = %d AND (barcode = %s OR sku = %s) LIMIT 1",
									$order_id,
									sanitize_text_field( $item['barcode'] ?? $item['gtin'] ?? '' ),
									sanitize_text_field( $item['sku'] ?? $item['stockCode'] ?? '' )
								) );
								
								if ( $existing_item && (float)$existing_item->commission_amount > 0 ) {
									$commission_amount = (float) $existing_item->commission_amount;
									$commission_rate   = (float) $existing_item->commission_rate;
									$keep_existing     = true;
								}
							}

							// Veritabanında da gerçek komisyon yoksa mecburen %19 uygula
							if ( ! $keep_existing ) {
								$commission_rate = 19.00;
								$commission_amount = round( $line_total * 0.19, 2 );
								$order_has_default_comm = true;
							}
						}

						// Final normalized item to save
						$save_item = array(
							'order_id'           => $order_id,
							'store_id'           => (int) $store->id,
							'barcode'            => sanitize_text_field( $item['barcode'] ?? $item['gtin'] ?? '' ),
							'sku'                => sanitize_text_field( $item['sku'] ?? $item['stockCode'] ?? '' ),
							'product_name'       => sanitize_text_field( $item['product_name'] ?? $item['title'] ?? '' ),
							'quantity'           => absint( $item['quantity'] ?? $item['qty'] ?? 1 ),
							'unit_price'         => (float) ( $item['unit_price'] ?? $item['unitPrice'] ?? 0 ),
							'line_total'         => round( $line_total, 2 ),
							'discount'           => (float) ( $item['discount'] ?? 0 ),
							'vat_amount'         => (float) ( $item['vat_amount'] ?? $item['vatAmount'] ?? 0 ),
							'commission_amount'  => round( (float) $commission_amount, 2 ),
							'commission_rate'    => $commission_rate !== null ? (float) $commission_rate : (($commission_amount > 0.0 && $line_total > 0.0) ? round(($commission_amount / $line_total) * 100, 4) : null),
						);

						// Save/upsert item (DB handles upsert by order_id+sku/barcode)
						$this->db->save_order_item( $save_item );
					} // foreach sonu

					// YENİ EKLENEN: Veritabanında siparişi standart komisyon olarak işaretle / KALDIR
					global $wpdb;
					$col_check = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}hbt_orders LIKE 'is_comm_defaulted'");
					if (empty($col_check)) {
						$wpdb->query("ALTER TABLE {$wpdb->prefix}hbt_orders ADD is_comm_defaulted TINYINT(1) DEFAULT 0");
					}
					
					// Eğer gerçek oran bulunduysa 0 yapıp kırmızı uyarıyı KALDIR, bulunamadıysa 1 yapıp uyarıyı yak
					$wpdb->update(
						$wpdb->prefix . 'hbt_orders',
						array( 'is_comm_defaulted' => $order_has_default_comm ? 1 : 0 ),
						array( 'id' => $order_id )
					);
					

					$saved_count++;
					$saved_order_ids[] = (int) $order_id;

					// YENİ EKLENEN: Sipariş API'den çekilip DB'ye kaydedildiği anda maliyet/kâr hesaplamasını otomatik yap
					if ( class_exists('HBT_Profit_Calculator') ) {
						$calculator = new HBT_Profit_Calculator();
						$calculator->calculate_order( $order_id );
					}
				}
			}
		}

		// Fetch & save financial transactions for the time span of fetched orders (if any)
		if ( $min_order_ts !== null && $max_order_ts !== null ) {
			// YENİ: Siparişin üzerinden 30 gün geçse bile faturası/iadesi gelebileceği için zaman penceresini 30 GÜN ileriye genişlettik.
			$fetch_start = max(0, intval( $min_order_ts - ( 7 * DAY_IN_SECONDS * 1000 ) )); // Siparişten 7 gün öncesi
			$fetch_end   = intval( min( time() * 1000, $max_order_ts + ( 30 * DAY_IN_SECONDS * 1000 ) ) ); // Siparişten 30 gün sonrası (Maksimum bugüne kadar)


			// Types to check for otherfinancials (existing logic)
			foreach ( array( 'CommissionInvocie', 'DeductionInvoices' ) as $type ) {
				$transactions = $api->get_financial_transactions( $type, $fetch_start, $fetch_end );
				if ( is_wp_error( $transactions ) ) {
					continue;
				}
				foreach ( $transactions as $tx ) {
					$this->db->save_transaction(
						array(
							'store_id'         => (int) $store->id,
							'transaction_id'   => sanitize_text_field( $tx['transaction_id'] ?? $tx['id'] ?? '' ),
							'order_number'     => sanitize_text_field( $tx['order_number'] ?? $tx['orderNumber'] ?? '' ),
							'transaction_type' => sanitize_text_field( $tx['transaction_type'] ?? $tx['transactionType'] ?? $type ),
							'amount'           => (float) ( $tx['amount'] ?? $tx['credit'] ?? 0 ),
							'description'      => sanitize_text_field( $tx['description'] ?? '' ),
							'transaction_date' => isset( $tx['transaction_date'] ) ? sanitize_text_field( $tx['transaction_date'] ) : ( isset( $tx['transactionDate'] ) ? gmdate( 'Y-m-d H:i:s', intval( $tx['transactionDate'] / 1000 ) ) : null ),
						)
					);
				}
			}

			// Now fetch settlements (recommended endpoint that contains commissionAmount/commissionRate)
			$settlement_types = array( 'Sale', 'Return', 'CommissionPositive', 'CommissionNegative', 'CommissionPositiveCancel', 'CommissionNegativeCancel', 'SellerRevenuePositive', 'SellerRevenueNegative' );
			$settlements = $api->get_settlements( (int) $fetch_start, (int) $fetch_end, $settlement_types, 0, 500 );

			if ( ! is_wp_error( $settlements ) && is_array( $settlements ) && ! empty( $settlements ) ) {
				global $wpdb;
				foreach ( $settlements as $s ) {
					$tx_id        = sanitize_text_field( $s['id'] ?? $s['transactionId'] ?? '' );
					$tx_type      = sanitize_text_field( $s['transactionType'] ?? $s['transaction_type'] ?? '' );
					$order_number = sanitize_text_field( $s['orderNumber'] ?? $s['order_number'] ?? $s['orderNo'] ?? '' );
					$barcode      = sanitize_text_field( $s['barcode'] ?? '' );
					$commission_amount = isset( $s['commissionAmount'] ) ? (float) $s['commissionAmount'] : ( isset( $s['commission_amount'] ) ? (float) $s['commission_amount'] : 0.0 );
					$commission_rate   = isset( $s['commissionRate'] ) ? (float) $s['commissionRate'] : ( isset( $s['commission_rate'] ) ? (float) $s['commission_rate'] : null );
					$credit_amount     = isset( $s['credit'] ) ? (float) $s['credit'] : ( isset( $s['amount'] ) ? (float) $s['amount'] : 0.0 );
					$tx_date           = isset( $s['transactionDate'] ) ? gmdate( 'Y-m-d H:i:s', intval( $s['transactionDate'] / 1000 ) ) : null;

					$tx_data = array(
						'store_id'         => (int) $store->id,
						'transaction_id'   => $tx_id,
						'order_number'     => $order_number,
						'transaction_type' => $tx_type,
						'amount'           => $credit_amount,
						'description'      => sanitize_text_field( $s['description'] ?? '' ),
						'transaction_date' => $tx_date,
					);

					$this->db->save_transaction( $tx_data );

					// YENİ: Eğer Settlement tipi bir "İADE" işlemiyse, doğrudan iadeler (hbt_returns) tablosuna kaydet/güncelle
					if ( $tx_type === 'Return' || $tx_type === 'ReturnInvoice' ) {
						$order_row = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}hbt_orders WHERE order_number = %s LIMIT 1", $order_number ) );
						if ( $order_row ) {
							$existing_return = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}hbt_returns WHERE order_id = %d AND barcode = %s LIMIT 1", $order_row->id, $barcode ) );
							
							if ( ! $existing_return ) {
								// Daha önce iade kaydedilmemişse şimdi kaydet
								$wpdb->insert(
									$wpdb->prefix . 'hbt_returns',
									array(
										'store_id'      => (int) $store->id,
										'order_id'      => $order_row->id,
										'order_number'  => $order_number,
										'return_date'   => $tx_date,
										'barcode'       => $barcode,
										'product_name'  => sanitize_text_field( $s['description'] ?? 'İade Edilen Ürün' ),
										'quantity'      => 1, // Varsayılan 1
										'return_reason' => 'Trendyol İade Faturası',
										'return_type'   => 'customer',
										'refund_amount' => abs((float)$credit_amount),
										'shipping_cost' => 0,
										'status'        => 'completed'
									)
								);
							}
						}
					}

					$this->db->save_transaction( $tx_data );

					// If settlement contains commissionAmount and orderNumber, try to attribute to order items.
					if ( $commission_amount > 0.0 && $order_number !== '' ) {
						// Find order id
						$order_row = $wpdb->get_row( $wpdb->prepare(
							"SELECT id FROM {$wpdb->prefix}hbt_orders WHERE order_number = %s LIMIT 1",
							$order_number
						) );

						if ( ! $order_row ) {
							// No matching order yet (maybe not synced). Skip for now.
							continue;
						}

						$order_id = (int) $order_row->id;

						// If barcode provided in settlement, try to map directly to item
						if ( ! empty( $barcode ) ) {
							$item_row = $wpdb->get_row( $wpdb->prepare(
								"SELECT id, commission_amount FROM {$wpdb->prefix}hbt_order_items WHERE order_id = %d AND barcode = %s LIMIT 1",
								$order_id,
								$barcode
							) );

							if ( $item_row ) {
								$prev = isset( $item_row->commission_amount ) ? (float) $item_row->commission_amount : 0.0;
								$wpdb->update(
									"{$wpdb->prefix}hbt_order_items",
									array(
										'commission_amount' => round( $prev + $commission_amount, 2 ),
										'commission_rate'   => $commission_rate !== null ? $commission_rate : null,
									),
									array( 'id' => $item_row->id )
								);
								// Recalculate order totals
								$calculator = new HBT_Profit_Calculator();
								$calculator->calculate_order( $order_id );
								continue;
							}
						}

						// Otherwise distribute across items proportionally by line_total
						$items = $wpdb->get_results( $wpdb->prepare(
							"SELECT id, line_total, commission_amount FROM {$wpdb->prefix}hbt_order_items WHERE order_id = %d",
							$order_id
						) );

						if ( empty( $items ) ) {
							continue;
						}

						$total_line = 0.0;
						foreach ( $items as $it ) {
							$total_line += (float) $it->line_total;
						}

						if ( $total_line <= 0.0 ) {
							// fallback: equal split
							$per_item = round( $commission_amount / count( $items ), 2 );
							$distributed = 0.0;
							$last = end( $items );
							foreach ( $items as $it ) {
								if ( $it->id !== $last->id ) {
									$val = $per_item;
									$distributed += $val;
								} else {
									$val = round( $commission_amount - $distributed, 2 );
								}
								$prev = isset( $it->commission_amount ) ? (float) $it->commission_amount : 0.0;
								$wpdb->update(
									"{$wpdb->prefix}hbt_order_items",
									array(
										'commission_amount' => round( $prev + $val, 2 ),
										'commission_rate'   => $commission_rate !== null ? $commission_rate : null,
									),
									array( 'id' => $it->id )
								);
							}
						} else {
							$distributed = 0.0;
							$last = end( $items );
							foreach ( $items as $it ) {
								if ( $it->id !== $last->id ) {
									$val = round( $commission_amount * ( (float) $it->line_total / $total_line ), 2 );
									$distributed += $val;
								} else {
									$val = round( $commission_amount - $distributed, 2 );
								}
								$prev = isset( $it->commission_amount ) ? (float) $it->commission_amount : 0.0;
								$rate = null;
								if ( (float) $it->line_total > 0.0 ) {
									$rate = round( ( $val / (float) $it->line_total ) * 100, 4 );
								}
								$wpdb->update(
									"{$wpdb->prefix}hbt_order_items",
									array(
										'commission_amount' => round( $prev + $val, 2 ),
										'commission_rate'   => $commission_rate !== null ? $commission_rate : $rate,
									),
									array( 'id' => $it->id )
								);
							}
						}

						// Gerçek komisyon finansal tablodan/faturadan geldiği için kırmızı uyarıyı (is_comm_defaulted) kaldır
						$wpdb->update(
							"{$wpdb->prefix}hbt_orders",
							array( 'is_comm_defaulted' => 0 ),
							array( 'id' => $order_id )
						);

						// After distribution, recalc order to refresh totals
						$calculator = new HBT_Profit_Calculator();
						$calculator->calculate_order( $order_id );
					}
				}
			}
		}

		// Update last_finance_sync for store
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'hbt_stores',
			array( 'last_finance_sync' => current_time( 'mysql' ) ),
			array( 'id' => (int) $store->id )
		);
		
		// HATA DÜZELTMESİ: Erken return eden sonuç dizisi en sona taşındı.
		$result = array(
			'saved'          => $saved_count,
			'returned'       => $returned_count,
			'inserted'       => $new_count,     
			'updated'        => $updated_count, 
			'skipped'        => $skipped_count, 
			'failed'         => $failed_count,  // YENİ EKLENEN
			'order_ids'      => $saved_order_ids,
			'debug_api_data' => $debug_api_data,
		);

		// --- CANLI MONİTÖRE (STREAM) AKTARIM ---
		if ( !empty($stream_buffer) ) {
			$existing_stream = get_option('hbt_live_stream_logs', array());
			if ( !is_array($existing_stream) ) $existing_stream = array();
			$merged = array_merge($existing_stream, $stream_buffer);
			$merged = array_slice($merged, -40); // Sadece son 40 işlemi tut ki veritabanı şişmesin
			update_option('hbt_live_stream_logs', $merged, false);
		}

		return $result;
		
	}

	/**
	 * Sync USD/TRY currency rates.
	 */
	public function sync_currency_rates(): void {
		$currency_service = HBT_Currency_Service::instance();
		$currency_service->fetch_daily_rate_from_tcmb( gmdate( 'Y-m-d' ) );
		$currency_service->update_hourly_rates();
	}

	/**
	 * Run profit calculations for uncalculated orders.
	 */
	public function run_calculations(): void {
		$calculator = new HBT_Profit_Calculator();
		$calculator->recalculate_all();
	}

	/**
	 * Check and process returns for all active stores.
	 */
	public function check_returns(): void {
		$return_manager = new HBT_Return_Manager();
		$stores         = $this->db->get_stores( true );

		foreach ( $stores as $store ) {
			$return_manager->process_returns( (int) $store->id );
		}
	}

	/**
	 * Sync products for all active stores.
	 */
	public function sync_products(): void {
		$stores = $this->db->get_stores( true );

		foreach ( $stores as $store ) {
			$api      = new HBT_Trendyol_API( $store );
			$products = $api->get_products();

			if ( is_wp_error( $products ) ) {
				continue;
			}

			foreach ( $products as $product ) {
				$this->db->save_product_cost(
					array(
						'store_id'      => (int) $store->id,
						'barcode'       => sanitize_text_field( $product['barcode'] ),
						'product_name'  => sanitize_text_field( $product['product_name'] ),
						'sku'           => sanitize_text_field( $product['sku'] ),
						'trendyol_id'   => (int) $product['trendyol_id'],
						'category_name' => sanitize_text_field( $product['category_name'] ),
						'image_url'     => esc_url_raw( $product['image_url'] ),
						'desi'          => floatval( $product['desi'] ?? 1 ),
					)
				);
			}

			// Update last_product_sync.
			global $wpdb;
			$wpdb->update(
				$wpdb->prefix . 'hbt_stores',
				array( 'last_product_sync' => current_time( 'mysql' ) ),
				array( 'id' => (int) $store->id )
			);
		}
	}

	/**
	 * Clean up old notifications.
	 */
	public function cleanup_notifications(): void {
		$this->db->cleanup_old_notifications();
	}

	/**
	 * Günlük E-posta Raporunu Gönderir
	 */
	public function send_daily_email_report( $is_test = false ): void {
		if ( ! class_exists( 'HBT_Settings' ) ) {
			require_once HBT_TPT_PLUGIN_DIR . 'includes/class-settings.php';
		}
		
		$settings = new HBT_Settings();
		$is_active = (bool) $settings->get('daily_report_active', false);
		$email     = sanitize_email( $settings->get('daily_report_email', '') );
		$time_str  = sanitize_text_field( $settings->get('daily_report_time', '09:00') );

		if ( ! $is_active || empty( $email ) ) {
			return; // Kapalıysa veya e-posta yoksa çık
		}

		// Sunucu saati ile ayarlanmış saati karşılaştır
		$tz = new DateTimeZone('Europe/Istanbul');
		$now = new DateTime('now', $tz);
		$current_hour_min = $now->format('H:i');
		$today_date = $now->format('Y-m-d');
		
		// Sadece saati (H) baz alarak daha güvenli bir tetikleme yapalım
		$target_hour = substr($time_str, 0, 2);
		$current_hour = substr($current_hour_min, 0, 2);

		if ( ! $is_test ) {
			if ( $current_hour !== $target_hour ) {
				return; // Saati gelmemiş
			}

			// Bugün bu rapor zaten atıldı mı kontrol et (Aynı saat içinde 2 kez atmasın)
			$last_sent = get_option( 'hbt_daily_report_last_sent', '' );
			if ( $last_sent === $today_date ) {
				return; 
			}
		}

		// Dünün tarihlerini ayarla
		$yesterday_dt = clone $now;
		$yesterday_dt->modify('-1 day');
		$yesterday = $yesterday_dt->format('Y-m-d');

		// Veritabanından dünün özet verilerini çek
		$db = HBT_Database::instance();
		$stats = $db->get_profit_stats( $yesterday, $yesterday );
		
		// Dünkü İade Sayısını Çek
		global $wpdb;
		$return_count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}hbt_returns WHERE DATE(return_date) = %s",
			$yesterday
		) );

		$revenue = number_format( (float) $stats['revenue'], 2, ',', '.' ) . ' ₺';
		$profit  = number_format( (float) $stats['net_profit'], 2, ',', '.' ) . ' ₺';
		$profit_color = (float) $stats['net_profit'] >= 0 ? '#10B981' : '#EF4444';

		// E-Posta Şablonunu Hazırla (HTML)
		$subject = sprintf( 'Günlük Kâr Özetiniz (%s) - HBT Trendyol Profit Tracker', $yesterday_dt->format('d.m.Y') );
		
		$message = '
		<html>
		<head>
			<style>
				body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 20px; }
				.container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
				.header { background-color: #0F172A; color: #ffffff; padding: 20px; text-align: center; }
				.header h2 { margin: 0; font-size: 20px; font-weight: 600; }
				.content { padding: 30px 20px; }
				.stat-box { background: #f1f5f9; padding: 15px; border-radius: 8px; margin-bottom: 15px; text-align: center; }
				.stat-label { font-size: 13px; color: #64748b; text-transform: uppercase; font-weight: 600; margin-bottom: 5px; }
				.stat-value { font-size: 24px; font-weight: 700; color: #0f172a; }
				.footer { background: #f8fafc; padding: 15px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
			</style>
		</head>
		<body>
			<div class="container">
				<div class="header">
					<h2>Trendyol Günlük Finansal Özet</h2>
					<p style="margin: 5px 0 0 0; color: #94a3b8; font-size: 14px;">Tarih: ' . $yesterday_dt->format('d.m.Y') . '</p>
				</div>
				<div class="content">
					<p style="color: #334155; margin-bottom: 25px; text-align: center; font-size: 16px;">Merhaba, dünün finansal özeti aşağıdadır:</p>
					
					<div class="stat-box">
						<div class="stat-label">Dünkü Ciro</div>
						<div class="stat-value">' . $revenue . '</div>
					</div>
					
					<div class="stat-box" style="border-bottom: 3px solid ' . $profit_color . ';">
						<div class="stat-label">Net Kâr</div>
						<div class="stat-value" style="color: ' . $profit_color . ';">' . $profit . '</div>
					</div>

					<div class="stat-box">
						<div class="stat-label">Dün Gelen İade Sayısı</div>
						<div class="stat-value" style="font-size: 20px;">' . $return_count . ' Adet</div>
					</div>

					<div style="text-align: center; margin-top: 30px;">
						<a href="' . admin_url('admin.php?page=hbt-tpt-dashboard') . '" style="background: #2563EB; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: 600; display: inline-block;">Panele Git & Detayları Gör</a>
					</div>
				</div>
				<div class="footer">
					Bu e-posta HBT Trendyol Profit Tracker eklentisi tarafından otomatik olarak gönderilmiştir.
				</div>
			</div>
		</body>
		</html>
		';

		$headers = array('Content-Type: text/html; charset=UTF-8');

		$mail_sent = wp_mail( $email, $subject, $message, $headers );

		if ( $mail_sent && ! $is_test ) {
			// Bugünü kaydet ki tekrar atmasın
			update_option( 'hbt_daily_report_last_sent', $today_date );
		}
	}
}
// Arka plan (Plesk/Cron) isteklerinde de işçinin (worker) uyanık kalmasını sağlar.
HBT_Cron_Manager::instance();