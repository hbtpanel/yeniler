<?php
/**
 * Admin menu class.
 *
 * @package HBT_Trendyol_Profit_Tracker
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class HBT_Admin_Menu
 *
 * Registers admin menu pages and handles all AJAX requests.
 */
class HBT_Admin_Menu {

	/**
	 * Plugin pages hook suffixes.
	 *
	 * @var array
	 */
	private array $page_hooks = array();

	/**
	 * Constructor.
	 *
	 * Registers hooks (menus, assets, ajax).
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_hbt_get_dashboard_data', array( $this, 'ajax_get_dashboard_data' ) );
		add_action( 'wp_ajax_hbt_delete_notification', array( $this, 'ajax_delete_notification' ) );
		add_action( 'wp_ajax_hbt_bulk_delete_notifications', array( $this, 'ajax_bulk_delete_notifications' ) );
		add_action( 'wp_ajax_hbt_mark_all_notifications_read', array( $this, 'ajax_mark_all_notifications_read' ) );
		add_action( 'wp_ajax_hbt_get_orders_ajax', array( $this, 'ajax_get_orders' ) );
		add_action( 'wp_ajax_hbt_export_orders', array( $this, 'ajax_export_orders' ) );
		add_action( 'wp_ajax_hbt_save_profit_goal', array( $this, 'ajax_save_profit_goal' ) );
		add_action( 'wp_ajax_hbt_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_hbt_check_dashboard_updates', array( $this, 'ajax_check_dashboard_updates' ) );
		// iOS Web App ve UI İyileştirmeleri (SaaS App Deneyimi)
		add_action( 'admin_head', array( $this, 'ios_app_meta_and_css' ) );
		add_action( 'admin_footer', array( $this, 'ios_app_menu_js' ) );
		add_action( 'wp_ajax_hbt_get_live_usd_rate', array( $this, 'ajax_get_live_usd_rate' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_hbt_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_hbt_save_store', array( $this, 'ajax_save_store' ) );
		add_action( 'wp_ajax_hbt_delete_store', array( $this, 'ajax_delete_store' ) );
		add_action( 'wp_ajax_hbt_sync_store', array( $this, 'ajax_sync_store' ) );
		add_action( 'wp_ajax_hbt_pre_sync_check', array( $this, 'ajax_pre_sync_check' ) );
		add_action( 'wp_ajax_hbt_save_product_cost', array( $this, 'ajax_save_product_cost' ) );
		add_action( 'wp_ajax_hbt_sync_products', array( $this, 'ajax_sync_products' ) );
		add_action( 'wp_ajax_hbt_delete_product', array( $this, 'ajax_delete_product' ) );
		add_action( 'wp_ajax_hbt_save_shipping_cost', array( $this, 'ajax_save_shipping' ) );
		add_action( 'wp_ajax_hbt_delete_shipping_cost', array( $this, 'ajax_delete_shipping' ) );
		add_action( 'wp_ajax_hbt_get_order_details', array( $this, 'ajax_get_order_details' ) );
		add_action( 'wp_ajax_hbt_recalculate_order', array( $this, 'ajax_recalculate_order' ) );
		add_action( 'wp_ajax_hbt_bulk_import_costs', array( $this, 'ajax_bulk_import_costs' ) );
		add_action( 'wp_ajax_hbt_dismiss_notification', array( $this, 'ajax_dismiss_notification' ) );
		// YENİ EKLENEN: Sabit giderleri kaydetme kancası
		add_action( 'wp_ajax_hbt_save_fixed_costs', array( $this, 'ajax_save_fixed_costs' ) );
		add_action( 'wp_ajax_hbt_save_ad_expense', array( $this, 'ajax_save_ad_expense' ) );
		add_action( 'wp_ajax_hbt_delete_ad_expense', array( $this, 'ajax_delete_ad_expense' ) );
	}

	// -------------------------------------------------------------------------
	// Menu registration
	// -------------------------------------------------------------------------

	/**
	 * Register WordPress admin menu pages.
	 */
	public function register_menus(): void {
		$db = HBT_Database::instance();
		$unread_count = $db->get_unread_count();
		
		// Ana Menü Başlığı ve Yanıp Sönen Rozet
		$menu_title = __( 'Trendyol Kâr/Zarar', 'hbt-trendyol-profit-tracker' );
		if ( $unread_count > 0 ) {
			$menu_title .= ' <span class="update-plugins count-' . esc_attr( $unread_count ) . ' hbt-blink-badge" title="Okunmamış bildirimler"><span class="plugin-count">' . number_format_i18n( $unread_count ) . '</span></span>';
		}

		$this->page_hooks[] = add_menu_page(
			__( 'Trendyol Kâr/Zarar', 'hbt-trendyol-profit-tracker' ), // Sayfa başlığı
			$menu_title, // Sol menüde görünecek başlık
			'manage_options',
			'hbt-tpt-dashboard',
			array( $this, 'render_dashboard' ),
			'dashicons-chart-area',
			56
		);

		// Bildirimler Alt Menüsü Başlığı
		$notifications_title = __( 'Bildirimler', 'hbt-trendyol-profit-tracker' );
		if ( $unread_count > 0 ) {
			$notifications_title .= ' <span class="update-plugins count-' . esc_attr( $unread_count ) . '"><span class="plugin-count">' . number_format_i18n( $unread_count ) . '</span></span>';
		}

		$submenus = array(
			array( 'hbt-tpt-dashboard', __( 'Dashboard', 'hbt-trendyol-profit-tracker' ), array( $this, 'render_dashboard' ) ),
			array( 'hbt-tpt-stores', __( 'Mağazalar', 'hbt-trendyol-profit-tracker' ), array( $this, 'render_stores' ) ),
			array( 'hbt-tpt-orders', __( 'Siparişler', 'hbt-trendyol-profit-tracker' ), array( $this, 'render_orders' ) ),
			array( 'hbt-tpt-products', __( 'Ürün Maliyetleri', 'hbt-trendyol-profit-tracker' ), array( $this, 'render_products' ) ),
			array( 'hbt-tpt-shipping', __( 'Kargo Fiyatları', 'hbt-trendyol-profit-tracker' ), array( $this, 'render_shipping' ) ),
			array( 'hbt-tpt-returns', __( 'İadeler', 'hbt-trendyol-profit-tracker' ), array( $this, 'render_returns' ) ),
			array( 'hbt-tpt-fixed-costs', __( 'Sabit Giderler', 'hbt-trendyol-profit-tracker' ), array( $this, 'render_fixed_costs' ) ),
			array( 'hbt-tpt-ad-expenses', __( 'Reklam Giderleri', 'hbt-trendyol-profit-tracker' ), array( $this, 'render_ad_expenses' ) ), 
			array( 'hbt-tpt-notifications', $notifications_title, array( $this, 'render_notifications' ) ), // YENİ EKLENEN
			array( 'hbt-tpt-simulator', __( 'Kâr Simülatörü', 'hbt-trendyol-profit-tracker' ), array( $this, 'render_simulator' ) ),
			array( 'hbt-tpt-plus-simulator', __( 'Plus Simülatörü', 'hbt-trendyol-profit-tracker' ), array( $this, 'render_plus_simulator' ) ), // PLUS SİMÜLATÖRÜ EKLENDİ
			array( 'hbt-tpt-avantajli-etiketler', __( 'Avantajlı Etiketler', 'hbt-trendyol-profit-tracker' ), array( $this, 'render_avantajli_etiketler' ) ),
			array( 'hbt-tpt-reports', __( 'Raporlar', 'hbt-trendyol-profit-tracker' ), array( $this, 'render_reports' ) ),
			array( 'hbt-tpt-settings', __( 'Ayarlar', 'hbt-trendyol-profit-tracker' ), array( $this, 'render_settings' ) ),
		
		);

		foreach ( $submenus as $item ) {
			$this->page_hooks[] = add_submenu_page(
				'hbt-tpt-dashboard',
				$item[1],
				$item[1],
				'manage_options',
				$item[0],
				$item[2]
			);
		}
	}

	// -------------------------------------------------------------------------
	// Asset enqueueing
	// -------------------------------------------------------------------------

	/**
	 * Enqueue admin CSS and JS only on plugin pages.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( strpos( $hook, 'hbt-tpt' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'hbt-tpt-admin',
			HBT_TPT_PLUGIN_URL . 'admin/css/admin-style.css',
			array(),
			HBT_TPT_VERSION
		);

		wp_enqueue_script( 'jquery' );
		
		// YENİ: Modern Flatpickr Tarih Seçici (CSS ve JS)
		wp_enqueue_style( 'flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', array(), '4.6.13' );
		wp_enqueue_script( 'flatpickr-js', 'https://cdn.jsdelivr.net/npm/flatpickr', array(), '4.6.13', true );
		wp_enqueue_script( 'flatpickr-tr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/tr.js', array( 'flatpickr-js' ), '4.6.13', true );

		// Chart.js CDN.
		wp_enqueue_script(
			'chartjs',
			'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
			array(),
			'4.4.0',
			true
		);

		// DataTables CDN.
		wp_enqueue_style( 'datatables-css', 'https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css', array(), '1.13.7' );
		wp_enqueue_script( 'datatables-js', 'https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js', array( 'jquery' ), '1.13.7', true );

		// YENİ: Select2 (Arama destekli çoklu ürün seçimi için)
		wp_enqueue_style( 'select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0' );
		wp_enqueue_script( 'select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array( 'jquery' ), '4.1.0', true );

		wp_enqueue_script(
			'hbt-tpt-admin',
			HBT_TPT_PLUGIN_URL . 'admin/js/admin-script.js',
			array( 'jquery', 'jquery-ui-datepicker', 'chartjs', 'datatables-js' ),
			HBT_TPT_VERSION,
			true
		);

		wp_localize_script(
			'hbt-tpt-admin',
			'hbtTpt',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'hbt_tpt_nonce' ),
				'strings' => array(
					'saving'         => __( 'Kaydediliyor...', 'hbt-trendyol-profit-tracker' ),
					'saved'          => __( 'Kaydedildi!', 'hbt-trendyol-profit-tracker' ),
					'error'          => __( 'Hata oluştu.', 'hbt-trendyol-profit-tracker' ),
					'confirm_delete' => __( 'Silmek istediğinize emin misiniz?', 'hbt-trendyol-profit-tracker' ),
					'testing'        => __( 'Test ediliyor...', 'hbt-trendyol-profit-tracker' ),
					'syncing'        => __( 'Senkronize ediliyor...', 'hbt-trendyol-profit-tracker' ),
					'synced'         => __( 'Senkronizasyon tamamlandı!', 'hbt-trendyol-profit-tracker' ),
				),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Page renderers
	// -------------------------------------------------------------------------

	/** Render dashboard page. */
	public function render_dashboard(): void {
		$this->check_cap();
		require HBT_TPT_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/** Render simulator page. */
	public function render_simulator(): void {
		$this->check_cap();
		require HBT_TPT_PLUGIN_DIR . 'admin/views/simulator.php';
	}

	/**
 * Render Avantajlı Etiketler page.
 */
public function render_avantajli_etiketler(): void {
    require_once HBT_TPT_PLUGIN_DIR . 'admin/views/avantajli-etiketler.php';
}

	/** Render plus simulator page. */
	public function render_plus_simulator(): void {
		$this->check_cap();
		// Dosya henüz yoksa hata vermemesi için if (file_exists) kontrolü koyuyoruz!
		$file_path = HBT_TPT_PLUGIN_DIR . 'admin/views/plus-simulator.php';
		if ( file_exists( $file_path ) ) {
			require $file_path;
		} else {
			echo '<div class="wrap"><h2>Plus Simülatörü</h2><p>Görünüm dosyası (plus-simulator.php) henüz oluşturulmadı!</p></div>';
		}
	}

	/** Render stores page. */
	public function render_stores(): void {
		$this->check_cap();
		require HBT_TPT_PLUGIN_DIR . 'admin/views/stores.php';
		// Eğer manuel senkronizasyon yapıldıysa, komisyon loglarını göster
		if ( isset($_GET['hbt_manual_sync']) && defined('WP_DEBUG') && WP_DEBUG ) {
			$log_path = WP_CONTENT_DIR . '/debug.log';
			if ( file_exists($log_path) ) {
				$lines = file($log_path);
				$filtered = array_filter($lines, function($line) {
					return strpos($line, 'OrderItemLog:') !== false;
				});
				if ( !empty($filtered) ) {
					echo '<div style="margin-top:30px;padding:15px;border:1px solid #ccc;background:#f9f9f9;"><h3>Komisyon Logları (Anlık):</h3><pre style="max-height:400px;overflow:auto;font-size:13px;">'.esc_html(implode("", $filtered)).'</pre></div>';
				} else {
					echo '<div style="margin-top:30px;padding:15px;border:1px solid #ccc;background:#fffbe6;"><strong>Komisyon logu bulunamadı.</strong></div>';
				}
			} else {
				echo '<div style="margin-top:30px;padding:15px;border:1px solid #ccc;background:#fffbe6;"><strong>debug.log dosyası bulunamadı.</strong></div>';
			}
		}
	}

	/** Render orders page. */
	public function render_orders(): void {
		$this->check_cap();
		require HBT_TPT_PLUGIN_DIR . 'admin/views/orders.php';
	}

	/** Render products page. */
	public function render_products(): void {
		$this->check_cap();
		require HBT_TPT_PLUGIN_DIR . 'admin/views/products.php';
	}

	/** Render shipping page. */
	public function render_shipping(): void {
		$this->check_cap();
		require HBT_TPT_PLUGIN_DIR . 'admin/views/shipping.php';
	}

	/** Render returns page. */
	public function render_returns(): void {
		$this->check_cap();
		require HBT_TPT_PLUGIN_DIR . 'admin/views/returns.php';
	}

	/** Render reports page. */
	public function render_reports(): void {
		$this->check_cap();
		require HBT_TPT_PLUGIN_DIR . 'admin/views/reports.php';
	}

	/** Render settings page. */
	public function render_settings(): void {
		$this->check_cap();
		require HBT_TPT_PLUGIN_DIR . 'admin/views/settings.php';
	}

	// -------------------------------------------------------------------------
	// AJAX: Stores
	// -------------------------------------------------------------------------

	/** AJAX: Save store. */
	public function ajax_save_store(): void {
		$this->verify_ajax();

		$id          = absint( $_POST['id'] ?? 0 );
		$store_name  = sanitize_text_field( $_POST['store_name'] ?? '' );
		$supplier_id = sanitize_text_field( $_POST['supplier_id'] ?? '' );
		$api_key     = sanitize_text_field( $_POST['api_key'] ?? '' );
		$api_secret  = sanitize_text_field( $_POST['api_secret'] ?? '' );

		if ( empty( $store_name ) || empty( $supplier_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Mağaza adı ve Supplier ID zorunludur.', 'hbt-trendyol-profit-tracker' ) ) );
		}

		$data = array(
			'store_name'  => $store_name,
			'supplier_id' => $supplier_id,
			'is_active'   => absint( $_POST['is_active'] ?? 1 ),
		);

		if ( $id ) {
			$data['id'] = $id;
		}

		// Only update keys if provided.
		if ( $api_key ) {
			$data['api_key'] = $api_key;
		}
		if ( $api_secret ) {
			$data['api_secret'] = $api_secret;
		}

		$result = HBT_Database::instance()->save_store( $data );

		if ( $result ) {
			wp_send_json_success( array( 'id' => $result ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Kayıt başarısız.', 'hbt-trendyol-profit-tracker' ) ) );
		}
	}

	/** AJAX: Delete store. */
	public function ajax_delete_store(): void {
		$this->verify_ajax();
		$id = absint( $_POST['id'] ?? 0 );
		HBT_Database::instance()->delete_store( $id );
		wp_send_json_success();
	}

	/** AJAX: Test API connection. */
	public function ajax_test_connection(): void {
		$this->verify_ajax();

		$id = absint( $_POST['store_id'] ?? 0 );

		if ( $id ) {
			$store = HBT_Database::instance()->get_store( $id );
		} else {
			// Test with inline credentials.
			$store               = new stdClass();
			$store->supplier_id  = sanitize_text_field( $_POST['supplier_id'] ?? '' );
			$store->api_key      = sanitize_text_field( $_POST['api_key'] ?? '' );
			$store->api_secret   = sanitize_text_field( $_POST['api_secret'] ?? '' );
		}

		if ( ! $store ) {
			wp_send_json_error( array( 'message' => __( 'Mağaza bulunamadı.', 'hbt-trendyol-profit-tracker' ) ) );
		}

		$api    = new HBT_Trendyol_API( $store );
		$result = $api->test_connection();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Bağlantı başarılı!', 'hbt-trendyol-profit-tracker' ) ) );
	}
/** AJAX: Senkronizasyon öncesi toplam sipariş sayısını bulur (İlerleme çubuğu için) */
	/** AJAX: Senkronizasyon öncesi toplam sipariş sayısını bulur (İlerleme çubuğu için) */
	public function ajax_pre_sync_check(): void {
		$this->verify_ajax();
		$id    = absint( $_POST['store_id'] ?? 0 );
		$store = HBT_Database::instance()->get_store( $id );

		if ( ! $store ) {
			wp_send_json_error( array( 'message' => __( 'Mağaza bulunamadı.', 'hbt-trendyol-profit-tracker' ) ) );
		}

		$start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : null;
		$end_date   = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : null;

		if ( empty( $start_date ) || empty( $end_date ) ) {
			wp_send_json_error( array( 'message' => 'Tarih aralığı eksik.' ) );
		}

		$tz = new DateTimeZone('Europe/Istanbul');
		
		// -1 gün / +1 gün silindi. Direkt seçilen tarih/saat epoch formatına çevriliyor
		$api_start_dt = new DateTime( $start_date, $tz );
		$start_ms = $api_start_dt->getTimestamp() * 1000;
		
		$api_end_dt = new DateTime( $end_date, $tz );
		$end_ms = $api_end_dt->getTimestamp() * 1000;

		$api = new HBT_Trendyol_API( $store );
		$total_elements = $api->get_orders_count( $start_ms, $end_ms );

		if ( is_wp_error( $total_elements ) ) {
			wp_send_json_error( array( 'message' => $total_elements->get_error_message() ) );
		}

		wp_send_json_success( array( 'total_orders' => $total_elements ) );
	}
	/** AJAX: Manual sync for a store. */
	/** AJAX: Manual sync for a store. */
    public function ajax_sync_store(): void {
        $this->verify_ajax();
        $id    = absint( $_POST['store_id'] ?? 0 );
        $store = HBT_Database::instance()->get_store( $id );

        if ( ! $store ) {
            wp_send_json_error( array( 'message' => __( 'Mağaza bulunamadı.', 'hbt-trendyol-profit-tracker' ) ) );
        }

        // Manual sync supports page/size (page=null => time-range cron behavior).
        $page = isset( $_POST['page'] ) ? intval( $_POST['page'] ) : 0;
        $size = isset( $_POST['size'] ) ? intval( $_POST['size'] ) : 100; // Trendyol'da 1 sayfa genelde maks 100'dür
        
        // HATA DÜZELTMESİ: Javascript'ten (arayüzden) gelen tarihleri alıyoruz
        $start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : null;
        $end_date   = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : null;

        // Tarihleri fonksiyona 4. ve 5. parametre olarak gönderiyoruz!
        $result = HBT_Cron_Manager::instance()->sync_store_orders( $store, $page, $size, $start_date, $end_date );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        // result expected to be array('saved'=>N, 'returned'=>M, 'inserted'=>X...)
        $saved    = is_array( $result ) && isset( $result['saved'] ) ? (int) $result['saved'] : 0;
        $returned = is_array( $result ) && isset( $result['returned'] ) ? (int) $result['returned'] : 0;
        
        // YENİ: Detaylı sayaçları arka plandan (cron-manager'dan) çekiyoruz
       $inserted = is_array( $result ) && isset( $result['inserted'] ) ? (int) $result['inserted'] : 0;
        $updated  = is_array( $result ) && isset( $result['updated'] ) ? (int) $result['updated'] : 0;
        $skipped  = is_array( $result ) && isset( $result['skipped'] ) ? (int) $result['skipped'] : 0;
        $failed   = is_array( $result ) && isset( $result['failed'] ) ? (int) $result['failed'] : 0; // YENİ EKLENEN

        // Son senkronizasyon verisini oku ve AJAX yanıtına ekle
        $sync_data = null;
        $sync_data_path = WP_CONTENT_DIR . '/hbt_last_sync.json';
        if ( file_exists($sync_data_path) ) {
            $sync_data = json_decode(file_get_contents($sync_data_path), true);
        }

        wp_send_json_success( array(
            'message'        => __( 'Senkronizasyon tamamlandı.', 'hbt-trendyol-profit-tracker' ),
            'fetched'        => $saved,
            'returned'       => $returned,
            'inserted'       => $inserted, 
            'updated'        => $updated,  
            'skipped'        => $skipped,  
            'failed'         => $failed,   // YENİ EKLENEN
            'page'           => $page,
            'sync_data'      => $sync_data,
            'debug_api_data' => isset( $result['debug_api_data'] ) ? $result['debug_api_data'] : array(),
        ) );
    }
	
	// -------------------------------------------------------------------------
	// AJAX: Product costs
	// -------------------------------------------------------------------------

	/** AJAX: Save product cost (inline edit). */
	/** AJAX: Save product cost (inline edit). */
	public function ajax_save_product_cost(): void {
		$this->verify_ajax();

		$data = array(
			'store_id'      => absint( $_POST['store_id'] ?? 0 ),
			'barcode'       => sanitize_text_field( $_POST['barcode'] ?? '' ),
			'product_name'  => sanitize_text_field( $_POST['product_name'] ?? '' ),
			'sku'           => sanitize_text_field( $_POST['sku'] ?? '' ),
			'category_name' => sanitize_text_field( $_POST['category_name'] ?? '' ),
			'image_url'     => esc_url_raw( $_POST['image_url'] ?? '' ),
			'cost_usd'      => floatval( $_POST['cost_usd'] ?? 0 ),
			'desi'          => floatval( $_POST['desi'] ?? 1 ), 
			'vat_rate'      => floatval( $_POST['vat_rate'] ?? 20 ),
		);

		if ( ! empty( $_POST['id'] ) ) {
			$data['id'] = absint( $_POST['id'] );
		}

		$result = HBT_Database::instance()->save_product_cost( $data );
		if ( $result ) {
			wp_send_json_success( array( 'id' => $result ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Kayıt başarısız.', 'hbt-trendyol-profit-tracker' ) ) );
		}
	}

	/** AJAX: Sync products from Trendyol API. */
	public function ajax_sync_products(): void {
		$this->verify_ajax();
		$store_id = absint( $_POST['store_id'] ?? 0 );

		if ( $store_id > 0 ) {
			$store = HBT_Database::instance()->get_store( $store_id );
			if ( ! $store ) {
				wp_send_json_error( array( 'message' => __( 'Mağaza bulunamadı.', 'hbt-trendyol-profit-tracker' ) ) );
			}
			$stores = array( $store );
		} else {
			$stores = HBT_Database::instance()->get_stores( true );
		}

		$total = 0;
		foreach ( $stores as $store ) {
			$api      = new HBT_Trendyol_API( $store );
			$products = $api->get_products();

			if ( is_wp_error( $products ) ) {
				continue;
			}

			foreach ( $products as $product ) {
				HBT_Database::instance()->save_product_cost(
					array(
						'store_id'      => (int) $store->id,
						'barcode'       => sanitize_text_field( $product['barcode'] ),
						'product_name'  => sanitize_text_field( $product['product_name'] ),
						'sku'           => sanitize_text_field( $product['sku'] ),
						'trendyol_id'   => (int) $product['trendyol_id'],
						'category_name' => sanitize_text_field( $product['category_name'] ),
						'image_url'     => esc_url_raw( $product['image_url'] ),
						'desi'          => floatval( $product['desi'] ?? 1 ), // keep API-provided desi if present
					)
				);
				$total++;
			}

			// Update last_product_sync.
			global $wpdb;
			$wpdb->update(
				$wpdb->prefix . 'hbt_stores',
				array( 'last_product_sync' => current_time( 'mysql' ) ),
				array( 'id' => (int) $store->id )
			);
		}

		wp_send_json_success( array( 'count' => $total, 'message' => sprintf( __( '%d ürün senkronize edildi.', 'hbt-trendyol-profit-tracker' ), $total ) ) );
	}

	/** AJAX: Delete a product. */
	public function ajax_delete_product(): void {
		$this->verify_ajax();
		$id = absint( $_POST['id'] ?? 0 );
		if ( $id ) {
			global $wpdb;
			$wpdb->delete( $wpdb->prefix . 'hbt_product_costs', array( 'id' => $id ) );
		}
		wp_send_json_success();
	}

	// -------------------------------------------------------------------------
	// AJAX: Shipping
	// -------------------------------------------------------------------------

	/** AJAX: Save shipping cost (price-range based). */
	public function ajax_save_shipping(): void {
		$this->verify_ajax();
		$data = array(
			'id'             => isset( $_POST['id'] ) ? absint( $_POST['id'] ) : null,
			'store_id'       => isset( $_POST['store_id'] ) ? absint( $_POST['store_id'] ) : 0,
			'shipping_company' => sanitize_text_field( $_POST['shipping_company'] ?? '' ),
			'price_min'      => ( $_POST['price_min'] !== '' ) ? floatval( $_POST['price_min'] ) : null,
			'price_max'      => ( $_POST['price_max'] !== '' ) ? floatval( $_POST['price_max'] ) : null,
			'cost_tl'        => floatval( $_POST['cost_tl'] ?? 0 ),
			'effective_from' => sanitize_text_field( $_POST['effective_from'] ?? date( 'Y-m-d' ) ),
			'effective_to'   => sanitize_text_field( $_POST['effective_to'] ?? '' ),
		);

		// Handle multi-store save if store_ids[] provided
		if ( ! empty( $_POST['store_ids'] ) && is_array( $_POST['store_ids'] ) ) {
			$ids = array_map( 'absint', $_POST['store_ids'] );
			foreach ( $ids as $sid ) {
				$d = $data;
				$d['store_id'] = $sid;
				HBT_Database::instance()->save_shipping_cost( $d );
			}
			wp_send_json_success();
		}

		$res = HBT_Database::instance()->save_shipping_cost( $data );
		if ( $res ) {
			wp_send_json_success( array( 'id' => $res ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Kayıt başarısız.', 'hbt-trendyol-profit-tracker' ) ) );
		}
	}

	/** AJAX: Delete shipping cost. */
	public function ajax_delete_shipping(): void {
		$this->verify_ajax();
		$id = absint( $_POST['id'] ?? 0 );
		if ( $id ) {
			HBT_Database::instance()->delete_shipping_cost( $id );
		}
		wp_send_json_success();
	}

	// -------------------------------------------------------------------------
	// Other AJAX helpers (orders, import, recalculation, etc.)
	// -------------------------------------------------------------------------

	/** AJAX: Get order details. */
	public function ajax_get_order_details(): void {
		$this->verify_ajax();
		$order_id = absint( $_POST['order_id'] ?? 0 );
		$order = HBT_Database::instance()->get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Sipariş bulunamadı.', 'hbt-trendyol-profit-tracker' ) ) );
		}
		$items = HBT_Database::instance()->get_order_items( $order_id );
		wp_send_json_success( array( 'order' => $order, 'items' => $items ) );
	}

	/** AJAX: Recalculate a single order or ALL orders. */
	public function ajax_recalculate_order(): void {
		$this->verify_ajax();
		$order_id = absint( $_POST['order_id'] ?? 0 );
		$calc = new HBT_Profit_Calculator();
		
		if ( $order_id === 0 ) {
			// TÜM SİPARİŞLERİ YENİDEN HESAPLA SENARYOSU
			global $wpdb;
			
			// Önce tüm siparişlerin 'hesaplandı' durumunu sıfırlayalım ki hesaplama motoru hepsini görsün
			$wpdb->query( "UPDATE {$wpdb->prefix}hbt_orders SET is_calculated = 0" );
			
			// Şimdi hesaplama motorunu tetikle
			$calc->recalculate_all();
			
			wp_send_json_success( array( 'message' => __( 'Tüm siparişlerin yeniden hesaplanması tamamlandı.', 'hbt-trendyol-profit-tracker' ) ) );
		} else {
			// TEKİL SİPARİŞ HESAPLAMA SENARYOSU
			$ok = $calc->calculate_order( $order_id );
			if ( $ok ) {
				wp_send_json_success();
			} else {
				wp_send_json_error( array( 'message' => __( 'Hesaplama başarısız.', 'hbt-trendyol-profit-tracker' ) ) );
			}
		}
	}

	/**
 * AJAX: Bulk import costs (CSV/XLSX) - improved
 */
public function ajax_bulk_import_costs(): void {
	$this->verify_ajax();

	// Expect file and store_id
	if ( empty( $_FILES['csv_file'] ) || empty( $_POST['store_id'] ) ) {
		wp_send_json_error( array( 'message' => __( 'Dosya veya mağaza seçilmedi.', 'hbt-trendyol-profit-tracker' ) ) );
	}

	$store_id = absint( $_POST['store_id'] );
	$file     = $_FILES['csv_file'];
	$tmp      = $file['tmp_name'] ?? '';

	if ( ! $tmp || ! file_exists( $tmp ) ) {
		wp_send_json_error( array( 'message' => __( 'Dosya bulunamadı.', 'hbt-trendyol-profit-tracker' ) ) );
	}

	$rows = array();
	$filename = $file['name'] ?? '';
	$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

	// Helper: normalize header name
	$normalize_header = function( $h ) {
		return strtolower( trim( (string) $h ) );
	};

	// ---------------------------------------------------------------------
	// XLSX / XLS handling via PhpSpreadsheet
	// ---------------------------------------------------------------------
	if ( in_array( $ext, array( 'xlsx', 'xls' ), true ) ) {
		if ( ! class_exists( '\PhpOffice\PhpSpreadsheet\IOFactory' ) ) {
			wp_send_json_error( array( 'message' => __( 'XLSX desteği için PhpSpreadsheet yüklü değil. composer require phpoffice/phpspreadsheet ile yükleyin.', 'hbt-trendyol-profit-tracker' ) ) );
		}

		try {
			$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load( $tmp );
			$sheet = $spreadsheet->getActiveSheet();
			$data = $sheet->toArray( null, true, true, true ); // returns rows keyed by A,B,C...
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => sprintf( __( 'Excel okunamadı: %s', 'hbt-trendyol-profit-tracker' ), $e->getMessage() ) ) );
		}

		if ( empty( $data ) ) {
			// empty workbook
			wp_send_json_success( array( 'count' => 0 ) );
		}

		// Build header map from first row
		$keys = array_keys( $data );
		$first_key = reset( $keys );
		$header_row = $data[ $first_key ];
		$header_map = array(); // colLetter => normalized header
		foreach ( $header_row as $col => $val ) {
			$header_map[ $col ] = $normalize_header( $val );
		}

		// Process following rows
		foreach ( $data as $r_index => $row ) {
			// skip header row (first)
			if ( $r_index === $first_key ) {
				continue;
			}

			// map to associative by normalized header names
			$row_assoc = array();
			foreach ( $header_map as $col => $hname ) {
				$cell = isset( $row[ $col ] ) ? $row[ $col ] : '';
				$row_assoc[ $hname ] = is_null( $cell ) ? '' : (string) $cell;
			}

			// Ensure barcode exists
			$barcode = trim( $row_assoc['barcode'] ?? '' );
			if ( $barcode === '' ) {
				// skip rows without barcode
				continue;
			}

			// Normalize numeric fields; if empty set 0.0
			$cost_usd = isset( $row_assoc['cost_usd'] ) && $row_assoc['cost_usd'] !== '' ? floatval( str_replace( ',', '.', $row_assoc['cost_usd'] ) ) : 0.0;
			$desi     = isset( $row_assoc['desi'] ) && $row_assoc['desi'] !== '' ? floatval( str_replace( ',', '.', $row_assoc['desi'] ) ) : 0.0;
			$vat_rate = isset( $row_assoc['vat_rate'] ) && $row_assoc['vat_rate'] !== '' ? floatval( str_replace( ',', '.', $row_assoc['vat_rate'] ) ) : 0.0;

			$rows[] = array(
				'barcode'      => $barcode,
				'cost_usd'     => $cost_usd,
				'desi'         => $desi,
				'vat_rate'     => $vat_rate,
				// optional informative fields (DB save ignores unknown keys but we keep for clarity)
				'product_name' => trim( $row_assoc['product_name'] ?? '' ),
				'sku'          => trim( $row_assoc['sku'] ?? '' ),
				'category_name'=> trim( $row_assoc['category_name'] ?? '' ),
				'image_url'    => trim( $row_assoc['image_url'] ?? '' ),
			);
		}
	} else {
		// -----------------------------------------------------------------
		// CSV handling (auto-detect delimiter , or ;)
		// -----------------------------------------------------------------
		$fh = fopen( $tmp, 'r' );
		if ( ! $fh ) {
			wp_send_json_error( array( 'message' => __( 'CSV dosyası açılamadı.', 'hbt-trendyol-profit-tracker' ) ) );
		}

		// Try to detect delimiter from first line
		$firstLine = fgets( $fh );
		rewind( $fh );
		$commas = substr_count( $firstLine, ',' );
		$semis  = substr_count( $firstLine, ';' );
		$delimiter = $commas >= $semis ? ',' : ';';

		$header = null;
		while ( ( $line = fgetcsv( $fh, 0, $delimiter ) ) !== false ) {
			// Skip empty lines
			$allEmpty = true;
			foreach ( $line as $c ) {
				if ( trim( (string) $c ) !== '' ) { $allEmpty = false; break; }
			}
			if ( $allEmpty ) {
				continue;
			}

			if ( ! $header ) {
				$header = array_map( $normalize_header, $line );
				continue;
			}

			// Combine header -> row
			if ( count( $header ) !== count( $line ) ) {
				// If row shorter, pad with empty strings
				$line = array_pad( $line, count( $header ), '' );
			}
			$row_raw = array_combine( $header, $line );

			$barcode = isset( $row_raw['barcode'] ) ? trim( $row_raw['barcode'] ) : '';
			if ( $barcode === '' ) {
				continue; // skip rows without barcode
			}

			$cost_usd = isset( $row_raw['cost_usd'] ) && $row_raw['cost_usd'] !== '' ? floatval( str_replace( ',', '.', $row_raw['cost_usd'] ) ) : 0.0;
			$desi     = isset( $row_raw['desi'] ) && $row_raw['desi'] !== '' ? floatval( str_replace( ',', '.', $row_raw['desi'] ) ) : 0.0;
			$vat_rate = isset( $row_raw['vat_rate'] ) && $row_raw['vat_rate'] !== '' ? floatval( str_replace( ',', '.', $row_raw['vat_rate'] ) ) : 0.0;

			$rows[] = array(
				'barcode'      => $barcode,
				'cost_usd'     => $cost_usd,
				'desi'         => $desi,
				'vat_rate'     => $vat_rate,
				'product_name' => trim( $row_raw['product_name'] ?? '' ),
				'sku'          => trim( $row_raw['sku'] ?? '' ),
				'category_name'=> trim( $row_raw['category_name'] ?? '' ),
				'image_url'    => trim( $row_raw['image_url'] ?? '' ),
			);
		}

		fclose( $fh );
	}

	// If no valid rows found, return success with count 0 (per request: don't error)
	if ( empty( $rows ) ) {
		wp_send_json_success( array( 'count' => 0 ) );
	}

	// Bulk insert via DB helper (this method will upsert existing items)
	$count = HBT_Database::instance()->bulk_import_costs( $store_id, $rows );

	if ( $count >= 0 ) {
		wp_send_json_success( array( 'count' => $count ) );
	}

	wp_send_json_error( array( 'message' => __( 'İçe aktarma başarısız.', 'hbt-trendyol-profit-tracker' ) ) );
}

	// -------------------------------------------------------------------------
	// Notifications
	// -------------------------------------------------------------------------

	/** AJAX: Dismiss notification (mark is_dismissed = 1). */
	public function ajax_dismiss_notification(): void {
		$this->verify_ajax();
		$id = absint( $_POST['id'] ?? 0 );
		if ( $id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Geçersiz bildirim ID.', 'hbt-trendyol-profit-tracker' ) ) );
		}

		$ok = HBT_Database::instance()->dismiss_notification( $id );
		if ( $ok ) {
			wp_send_json_success();
		} else {
			wp_send_json_error( array( 'message' => __( 'Bildirimi kapatma başarısız.', 'hbt-trendyol-profit-tracker' ) ) );
		}
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Verify AJAX nonce and capability.
	 */
	private function verify_ajax(): void {
		check_ajax_referer( 'hbt_tpt_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'hbt-trendyol-profit-tracker' ) ), 403 );
		}
	}

	/**
	 * Check capability or die.
	 */
	private function check_cap(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'hbt-trendyol-profit-tracker' ) );
		}
	}
	/** Sabit Giderler sayfasını yükler */
	public function render_fixed_costs(): void {
		require HBT_TPT_PLUGIN_DIR . 'admin/views/fixed-costs.php';
	}

	/** AJAX: Sabit Giderleri kaydeder */
	public function ajax_save_fixed_costs(): void {
		check_ajax_referer( 'hbt_tpt_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Yetkisiz erişim.' ) );
		}
		
		$costs = isset($_POST['costs']) && is_array($_POST['costs']) ? wp_unslash($_POST['costs']) : array();
		
		$sanitized_costs = array();
		foreach ($costs as $store_id => $data) {
			$sanitized_costs[intval($store_id)] = array(
				'personnel' => floatval($data['personnel'] ?? 0),
				'packaging' => floatval($data['packaging'] ?? 0),
				'other'     => floatval($data['other'] ?? 0),
			);
		}
		
		update_option('hbt_fixed_costs', $sanitized_costs);
		wp_send_json_success( array( 'message' => 'Sabit giderler başarıyla kaydedildi.' ) );
	}

	/**
     * AJAX: Dashboard verilerini hazırlar.
     */
    public function ajax_get_dashboard_data(): void {
        $this->verify_ajax();
        $db = HBT_Database::instance();
        global $wpdb;

       // Kesin Türkiye Saati (Europe/Istanbul) Ayarlamaları
        $tz = new DateTimeZone('Europe/Istanbul');
        $dt_today = new DateTime('now', $tz);
        
        $today = $dt_today->format('Y-m-d');
        
        $dt_week = clone $dt_today;
        $dt_week->modify('monday this week');
        $week = $dt_week->format('Y-m-d');
        
        $dt_month = clone $dt_today;
        $dt_month->modify('first day of this month');
        $month = $dt_month->format('Y-m-01');

        $dt_yesterday = clone $dt_today;
        $dt_yesterday->modify('-1 day');
        $yesterday = $dt_yesterday->format('Y-m-d');
        
        $dt_last_week_start = clone $dt_today;
        $dt_last_week_start->modify('monday last week');
        $last_week_start = $dt_last_week_start->format('Y-m-d');
        
        $dt_last_week_end = clone $dt_today;
        $dt_last_week_end->modify('sunday last week');
        $last_week_end = $dt_last_week_end->format('Y-m-d');
        
        $dt_last_month_start = clone $dt_today;
        $dt_last_month_start->modify('first day of last month');
        $last_month_start = $dt_last_month_start->format('Y-m-d');
        
        $dt_last_month_end = clone $dt_today;
        $dt_last_month_end->modify('last day of last month');
        $last_month_end = $dt_last_month_end->format('Y-m-d');
        
        $thirty_days_ago = clone $dt_today;
        $thirty_days_ago->modify('-30 days');
        $thirty_days_ago_str = $thirty_days_ago->format('Y-m-d');

        // Genel Kâr verilerini çek (İptalleri dışlar, Reklamı düşer)
        $stats_today = $db->get_profit_stats( $today, $today );
        $stats_week  = $db->get_profit_stats( $week, $today );
        $stats_month = $db->get_profit_stats( $month, $today );

       $stats_yesterday  = $db->get_profit_stats( $yesterday, $yesterday );
        $stats_last_week  = $db->get_profit_stats( $last_week_start, $last_week_end );
        $stats_last_month = $db->get_profit_stats( $last_month_start, $last_month_end );

        // --- YENİ: TREND BALONLARI İÇİN "BU SAATE KADAR" VERİLERİ ---
        $current_time = $dt_today->format('H:i:s');
        
        // 1. Dün bu saate kadar
        $stats_yesterday_upto_now = $wpdb->get_row( $wpdb->prepare(
            "SELECT COALESCE(SUM(net_profit), 0) AS net_profit, COALESCE(SUM(total_price), 0) AS revenue FROM {$wpdb->prefix}hbt_orders WHERE order_date BETWEEN %s AND %s AND status NOT IN ('Cancelled', 'Returned', 'UnSupplied')",
            $yesterday . ' 00:00:00', $yesterday . ' ' . $current_time
        ), ARRAY_A );
        $yesterday_ad = $wpdb->get_var($wpdb->prepare("SELECT SUM(daily_amount) FROM {$wpdb->prefix}hbt_ad_expenses WHERE start_date <= %s AND end_date >= %s", $yesterday, $yesterday));
        $stats_yesterday_upto_now['net_profit'] -= (float) $yesterday_ad;

        // 2. Geçen Hafta bu saate kadar (Geçen haftanın aynı gününün aynı saatine kadar)
        $dt_last_week_same_day = clone $dt_today;
        $dt_last_week_same_day->modify('-7 days');
        $last_week_same_day_end = $dt_last_week_same_day->format('Y-m-d H:i:s');
        $stats_last_week_upto_now = $wpdb->get_row( $wpdb->prepare(
            "SELECT COALESCE(SUM(net_profit), 0) AS net_profit, COALESCE(SUM(total_price), 0) AS revenue FROM {$wpdb->prefix}hbt_orders WHERE order_date BETWEEN %s AND %s AND status NOT IN ('Cancelled', 'Returned', 'UnSupplied')",
            $last_week_start . ' 00:00:00', $last_week_same_day_end
        ), ARRAY_A );
        $lw_end_date = $dt_last_week_same_day->format('Y-m-d');
        $lw_ad = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(daily_amount * (DATEDIFF(LEAST(end_date, %s), GREATEST(start_date, %s)) + 1)) FROM {$wpdb->prefix}hbt_ad_expenses WHERE start_date <= %s AND end_date >= %s",
            $lw_end_date, $last_week_start, $lw_end_date, $last_week_start
        ));
        $stats_last_week_upto_now['net_profit'] -= (float) $lw_ad;

        // 3. Geçen Ay bu saate kadar (Geçen ayın aynı gününün aynı saatine kadar)
        $dt_last_month_same_day = clone $dt_today;
        $dt_last_month_same_day->modify('-1 month');
        $last_month_same_day_end = $dt_last_month_same_day->format('Y-m-d H:i:s');
        $stats_last_month_upto_now = $wpdb->get_row( $wpdb->prepare(
            "SELECT COALESCE(SUM(net_profit), 0) AS net_profit, COALESCE(SUM(total_price), 0) AS revenue FROM {$wpdb->prefix}hbt_orders WHERE order_date BETWEEN %s AND %s AND status NOT IN ('Cancelled', 'Returned', 'UnSupplied')",
            $last_month_start . ' 00:00:00', $last_month_same_day_end
        ), ARRAY_A );
        $lm_end_date = $dt_last_month_same_day->format('Y-m-d');
        $lm_ad = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(daily_amount * (DATEDIFF(LEAST(end_date, %s), GREATEST(start_date, %s)) + 1)) FROM {$wpdb->prefix}hbt_ad_expenses WHERE start_date <= %s AND end_date >= %s",
            $lm_end_date, $last_month_start, $lm_end_date, $last_month_start
        ));
        $stats_last_month_upto_now['net_profit'] -= (float) $lm_ad;
        // --- BİTİŞ ---

       // Mağaza Bazlı Bugünkü Kâr ve Ciro (İptal ve iadeler hariç)
        $stores_today = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.id, s.store_name, COALESCE(SUM(o.net_profit), 0) as profit, COALESCE(SUM(o.total_price), 0) as revenue 
             FROM {$wpdb->prefix}hbt_stores s
             LEFT JOIN {$wpdb->prefix}hbt_orders o ON s.id = o.store_id 
                AND o.order_date BETWEEN %s AND %s 
                AND o.status NOT IN ('Cancelled', 'Returned', 'UnSupplied')
             WHERE s.is_active = 1
             GROUP BY s.id ORDER BY s.store_name ASC",
            $today . ' 00:00:00', $today . ' 23:59:59'
        ), ARRAY_A );

        // Bugünkü reklam giderlerini düş
        foreach ($stores_today as &$st) {
            $ad_cost = $wpdb->get_var($wpdb->prepare("SELECT SUM(daily_amount) FROM {$wpdb->prefix}hbt_ad_expenses WHERE store_id = %d AND start_date <= %s AND end_date >= %s", $st['id'], $today, $today));
            $st['profit'] -= (float) $ad_cost;
        }

        // Mağaza Bazlı Dünkü Kâr ve Ciro (İptal ve iadeler hariç)
        $stores_yesterday = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.id, s.store_name, COALESCE(SUM(o.net_profit), 0) as profit, COALESCE(SUM(o.total_price), 0) as revenue 
             FROM {$wpdb->prefix}hbt_stores s
             LEFT JOIN {$wpdb->prefix}hbt_orders o ON s.id = o.store_id 
                AND o.order_date BETWEEN %s AND %s 
                AND o.status NOT IN ('Cancelled', 'Returned', 'UnSupplied')
             WHERE s.is_active = 1
             GROUP BY s.id ORDER BY s.store_name ASC",
            $yesterday . ' 00:00:00', $yesterday . ' 23:59:59'
        ), ARRAY_A );

        // Dünkü reklam giderlerini düş
        foreach ($stores_yesterday as &$st) {
            $ad_cost = $wpdb->get_var($wpdb->prepare("SELECT SUM(daily_amount) FROM {$wpdb->prefix}hbt_ad_expenses WHERE store_id = %d AND start_date <= %s AND end_date >= %s", $st['id'], $yesterday, $yesterday));
            $st['profit'] -= (float) $ad_cost;
        }

        // Mağaza Karşılaştırması için son 30 günlük veri (İptal ve iadeler hariç)
        $thirty_days_ago = date( 'Y-m-d', strtotime( '-30 days' ) );
        $store_comparison = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.id, s.store_name, SUM(o.net_profit) as profit, SUM(o.total_price) as revenue 
             FROM {$wpdb->prefix}hbt_stores s
             LEFT JOIN {$wpdb->prefix}hbt_orders o ON s.id = o.store_id 
                AND o.order_date >= %s 
                AND o.status NOT IN ('Cancelled', 'Returned', 'UnSupplied')
             WHERE s.is_active = 1
             GROUP BY s.id",
            $thirty_days_ago . ' 00:00:00'
        ), ARRAY_A );

        // Mağazaların son 30 günlük reklam giderlerini düş
        foreach ($store_comparison as &$st) {
            $ad_cost = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(daily_amount * (DATEDIFF(LEAST(end_date, %s), GREATEST(start_date, %s)) + 1)) 
                 FROM {$wpdb->prefix}hbt_ad_expenses 
                 WHERE store_id = %d AND start_date <= %s AND end_date >= %s",
                $today, $thirty_days_ago, $st['id'], $today, $thirty_days_ago
            ));
            $st['profit'] -= (float) $ad_cost;
        }

        // --- YENİ: MAĞAZA TABLOSU YÜZDELİKLERİ İÇİN GEÇMİŞ VERİLER ---

        // 1. Dün bu saate kadar (Bugünkü mağaza kârı trendi için)
        $stores_yesterday_upto_now = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.id, COALESCE(SUM(o.net_profit), 0) as profit, COALESCE(SUM(o.total_price), 0) as revenue 
             FROM {$wpdb->prefix}hbt_stores s
             LEFT JOIN {$wpdb->prefix}hbt_orders o ON s.id = o.store_id 
                AND o.order_date BETWEEN %s AND %s 
                AND o.status NOT IN ('Cancelled', 'Returned', 'UnSupplied')
             WHERE s.is_active = 1 GROUP BY s.id",
            $yesterday . ' 00:00:00', $yesterday . ' ' . $current_time
        ), ARRAY_A );
        foreach ($stores_yesterday_upto_now as &$st) {
            $ad_cost = $wpdb->get_var($wpdb->prepare("SELECT SUM(daily_amount) FROM {$wpdb->prefix}hbt_ad_expenses WHERE store_id = %d AND start_date <= %s AND end_date >= %s", $st['id'], $yesterday, $yesterday));
            $st['profit'] -= (float) $ad_cost;
        }

        // 2. Önceki Gün (2 gün önce) (Dünkü mağaza kârı trendi için)
        $dt_2days_ago = clone $dt_today;
        $dt_2days_ago->modify('-2 days');
        $twodays_ago = $dt_2days_ago->format('Y-m-d');
        $stores_2days_ago = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.id, COALESCE(SUM(o.net_profit), 0) as profit, COALESCE(SUM(o.total_price), 0) as revenue 
             FROM {$wpdb->prefix}hbt_stores s
             LEFT JOIN {$wpdb->prefix}hbt_orders o ON s.id = o.store_id 
                AND o.order_date BETWEEN %s AND %s 
                AND o.status NOT IN ('Cancelled', 'Returned', 'UnSupplied')
             WHERE s.is_active = 1 GROUP BY s.id",
            $twodays_ago . ' 00:00:00', $twodays_ago . ' 23:59:59'
        ), ARRAY_A );
        foreach ($stores_2days_ago as &$st) {
            $ad_cost = $wpdb->get_var($wpdb->prepare("SELECT SUM(daily_amount) FROM {$wpdb->prefix}hbt_ad_expenses WHERE store_id = %d AND start_date <= %s AND end_date >= %s", $st['id'], $twodays_ago, $twodays_ago));
            $st['profit'] -= (float) $ad_cost;
        }

        // 3. Önceki 30 Gün bu saate kadar (Son 30 günlük mağaza kârı trendi için)
        $dt_60days_ago = clone $dt_today;
        $dt_60days_ago->modify('-60 days');
        $sixty_days_ago = $dt_60days_ago->format('Y-m-d');
        $dt_31days_ago = clone $dt_today;
        $dt_31days_ago->modify('-31 days');
        $thirtyone_days_ago = $dt_31days_ago->format('Y-m-d');
        
        $stores_prev_30days = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.id, COALESCE(SUM(o.net_profit), 0) as profit, COALESCE(SUM(o.total_price), 0) as revenue 
             FROM {$wpdb->prefix}hbt_stores s
             LEFT JOIN {$wpdb->prefix}hbt_orders o ON s.id = o.store_id 
                AND o.order_date BETWEEN %s AND %s 
                AND o.status NOT IN ('Cancelled', 'Returned', 'UnSupplied')
             WHERE s.is_active = 1 GROUP BY s.id",
            $sixty_days_ago . ' 00:00:00', $thirtyone_days_ago . ' ' . $current_time
        ), ARRAY_A );
        foreach ($stores_prev_30days as &$st) {
            $ad_cost = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(daily_amount * (DATEDIFF(LEAST(end_date, %s), GREATEST(start_date, %s)) + 1)) 
                 FROM {$wpdb->prefix}hbt_ad_expenses 
                 WHERE store_id = %d AND start_date <= %s AND end_date >= %s",
                $thirtyone_days_ago, $sixty_days_ago, $st['id'], $thirtyone_days_ago, $sixty_days_ago
            ));
            $st['profit'] -= (float) $ad_cost;
        }
        // --- BİTİŞ ---

      $profit_goal = (float) get_option('hbt_monthly_profit_goal', 50000);

        wp_send_json_success( array(
            // Ciro ve Kâr Metrikleri (Tamamı eklendi)
            'profit_today'       => $stats_today['net_profit'],
            'revenue_today'      => $stats_today['revenue'],
            'profit_yesterday'   => $stats_yesterday['net_profit'],
            'revenue_yesterday'  => $stats_yesterday['revenue'],
            'profit_week'        => $stats_week['net_profit'],       // YENİ: Bu Hafta Kâr
            'revenue_week'       => $stats_week['revenue'],          // YENİ: Bu Hafta Ciro
            'profit_last_week'   => $stats_last_week['net_profit'],
            'revenue_last_week'  => $stats_last_week['revenue'],
            'profit_month'       => $stats_month['net_profit'],
            'revenue_month'      => $stats_month['revenue'],
           'profit_last_month'  => $stats_last_month['net_profit'], // YENİ: Geçen Ay Kâr
            'revenue_last_month' => $stats_last_month['revenue'],    // YENİ: Geçen Ay Ciro
            
            // "Bu Saate Kadar" Verileri (Balonlar İçin)
            'profit_yesterday_upto_now'   => $stats_yesterday_upto_now['net_profit'],
            'revenue_yesterday_upto_now'  => $stats_yesterday_upto_now['revenue'],
            'profit_last_week_upto_now'   => $stats_last_week_upto_now['net_profit'],
            'revenue_last_week_upto_now'  => $stats_last_week_upto_now['revenue'],
            'profit_last_month_upto_now'  => $stats_last_month_upto_now['net_profit'],
            'revenue_last_month_upto_now' => $stats_last_month_upto_now['revenue'],

        
            // Grafikler ve Listeler
            'trend'             => $db->get_revenue_trend( 30 ),
            'expense_breakdown' => $db->get_dashboard_expense_breakdown( 30 ),
            'return_loss_stats' => $db->get_return_loss_stats( 30 ),
            'top_products'      => $db->get_top_profitable_products( 30, 5 ),
            'worst_products'    => $db->get_worst_profitable_products( 30, 5 ),
            'smart_alerts'      => $db->get_smart_alerts(),
            'profit_goal'       => $profit_goal,
            
            // Mağaza Bazlı Veriler
            'stores_today'      => $stores_today ?: array(),
            'stores_yesterday'  => $stores_yesterday ?: array(),
            'store_comparison'  => $store_comparison ?: array(),
            
            // Mağaza Tablosu Yüzdelikleri İçin
            'stores_yesterday_upto_now' => $stores_yesterday_upto_now ?: array(),
            'stores_2days_ago'          => $stores_2days_ago ?: array(),
            'stores_prev_30days'        => $stores_prev_30days ?: array()
			
        ) );
    }

    /** AJAX: Hedef Kârı Kaydet */
    public function ajax_save_profit_goal(): void {
        check_ajax_referer( 'hbt_tpt_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();
        update_option( 'hbt_monthly_profit_goal', floatval( $_POST['goal'] ?? 50000 ) );
        wp_send_json_success();
    }
    
	

	public function render_notifications(): void {
		require_once HBT_TPT_PLUGIN_DIR . 'admin/views/notifications.php';
	}

	public function ajax_delete_notification(): void {
		check_ajax_referer( 'hbt_tpt_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

		$id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		if ( $id && HBT_Database::instance()->delete_notification( $id ) ) {
			wp_send_json_success();
		}
		wp_send_json_error( array( 'message' => 'Silinemedi.' ) );
	}

	public function ajax_bulk_delete_notifications(): void {
		check_ajax_referer( 'hbt_tpt_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

		$ids = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? array_map( 'intval', $_POST['ids'] ) : array();
		if ( ! empty( $ids ) && HBT_Database::instance()->bulk_delete_notifications( $ids ) ) {
			wp_send_json_success();
		}
		wp_send_json_error( array( 'message' => 'Silinemedi.' ) );
	}

	public function ajax_mark_all_notifications_read(): void {
		check_ajax_referer( 'hbt_tpt_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

		if ( HBT_Database::instance()->mark_all_read() !== false ) {
			wp_send_json_success();
		}
		wp_send_json_error( array( 'message' => 'İşlem başarısız.' ) );
	}
	public function render_ad_expenses(): void {
		require_once HBT_TPT_PLUGIN_DIR . 'admin/views/ad-expenses.php';
	}

	public function ajax_save_ad_expense(): void {
		check_ajax_referer( 'hbt_tpt_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

		$data = array(
			'store_id'     => intval( $_POST['store_id'] ),
			'platform'     => sanitize_text_field( $_POST['platform'] ),
			'start_date'   => sanitize_text_field( $_POST['start_date'] ),
			'end_date'     => sanitize_text_field( $_POST['end_date'] ),
			'total_amount' => floatval( $_POST['total_amount'] ),
		);

		if ( isset( $_POST['id'] ) && intval( $_POST['id'] ) > 0 ) {
			$data['id'] = intval( $_POST['id'] );
		}

		if ( HBT_Database::instance()->save_ad_expense( $data ) ) {
			wp_send_json_success();
		}
		wp_send_json_error( array( 'message' => 'Kaydedilemedi.' ) );
	}

	public function ajax_delete_ad_expense(): void {
		check_ajax_referer( 'hbt_tpt_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

		$id = intval( $_POST['id'] );
		if ( HBT_Database::instance()->delete_ad_expense( $id ) ) {
			wp_send_json_success();
		}
		wp_send_json_error( array( 'message' => 'Silinemedi.' ) );
	}
	public function ajax_get_orders(): void {
		check_ajax_referer( 'hbt_tpt_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

		$db = HBT_Database::instance();
		$result = $db->get_orders_datatables_data( $_POST );

		$fixed_costs_opt = get_option( 'hbt_fixed_costs', array() );
		$formatted_data = array();

		foreach ( $result['data'] as $order ) {
			$profit      = (float) $order->net_profit;
			$row_class   = $profit > 0 ? 'profit-positive' : ( $profit < 0 ? 'profit-negative' : '' );
			$calc_class  = ! (int) $order->is_calculated ? 'cost-missing' : '';
			
			// 1. Veritabanında kırmızı(1) olarak işaretlenmiş mi kontrol et
			$is_default_comm = (isset($order->is_comm_defaulted) && $order->is_comm_defaulted == 1);
			
			// 2. KESİN VE CANLI MATEMATİKSEL KONTROL
			// Eğer sipariş kırmızı ise ama çekilen güncel tutara göre oran %19 DEĞİLSE, iptal et!
			if ( $is_default_comm && (float) $order->total_price > 0 ) {
				$live_rate = round( ((float) $order->total_commission / (float) $order->total_price) * 100, 2 );
				
				// Hesaplanan oran 19.00'dan farklıysa (Örn: 17.5, 14, 21 vb. gerçek bir oransa)
				if ( $live_rate !== 19.00 ) {
					$is_default_comm = false; // Kırmızı arkaplanı KALDIR
					
					// Arkaplanda çaktırmadan veritabanını da düzelt (Bir daha takılmasın)
					global $wpdb;
					$wpdb->update( 
						"{$wpdb->prefix}hbt_orders", 
						array( 'is_comm_defaulted' => 0 ), 
						array( 'id' => $order->id ) 
					);
				}
			}

			// 3. Tablo için satır CSS sınıfını belirle
			$comm_class      = $is_default_comm ? 'default-comm-row' : '';
			$row_class_final = esc_attr( trim($row_class . ' ' . $calc_class . ' ' . $comm_class) );

			// Sabit Gider Hesabı
			$store_fc = isset($fixed_costs_opt[$order->store_id]) ? $fixed_costs_opt[$order->store_id] : array();
			$order_fc = (float)($store_fc['personnel'] ?? 0) + (float)($store_fc['packaging'] ?? 0) + (float)($store_fc['other'] ?? 0);
			$display_fc = (int) $order->is_calculated ? $order_fc : 0.00;

			// Marj Renkleri
			$margin_val = (float) $order->profit_margin;
			$margin_td_class = '';
			if ( $row_class_final !== 'status-cancelled-row' ) {
				if ( $margin_val < 10 ) $margin_td_class = 'bg-blink-dark-red';
				elseif ( $margin_val >= 10 && $margin_val < 20 ) $margin_td_class = 'bg-light-red';
				elseif ( $margin_val >= 20 && $margin_val < 30 ) $margin_td_class = 'bg-red';
				elseif ( $margin_val >= 30 && $margin_val <= 45 ) $margin_td_class = 'bg-green';
				elseif ( $margin_val > 45 ) $margin_td_class = 'bg-blink-green';
			}

			// Türkçe Durum Çevirisi
			$status_text = $order->status;
			switch($status_text) {
				case 'Created': $status_text = 'Oluşturuldu'; break;
				case 'Picking': $status_text = 'Hazırlanıyor'; break;
				case 'Shipped': $status_text = 'Kargoda'; break;
				case 'Delivered': $status_text = 'Teslim Edildi'; break;
				case 'Cancelled': $status_text = 'İptal'; break;
				case 'Returned': $status_text = 'İade Edildi'; break;
				case 'UnSupplied': $status_text = 'Tedarik Edilemedi'; break;
				case 'Invoiced': $status_text = 'Faturalandı'; break;
			}
            
			// İptal durum kontrolü
			if ( in_array( $order->status, array( 'Cancelled', 'Returned', 'UnSupplied' ), true ) ) {
				$row_class_final = 'status-cancelled-row';
			}

			// YENİ EKLENEN: Zarar eden siparişler için Sipariş No'nun başına rozet ekleme
			$order_no_html = '<a href="#" class="btn-order-detail" data-id="' . esc_attr( $order->id ) . '"><strong>' . esc_html( $order->order_number ) . '</strong></a>';
			if ( $profit < 0 && $row_class_final !== 'status-cancelled-row' ) {
				$order_no_html = '<span class="hbt-badge-zarar">ZARAR</span> ' . $order_no_html;
			}

			$formatted_data[] = array(
				"DT_RowClass" => $row_class_final,
				"DT_RowAttr"  => array(
					"data-id" => esc_attr( (string) $order->id )
					// "style" satırını SİLDİK ki CSS dosyamızdaki renkler özgürce çalışsın
				),
				$order_no_html, // Güncellenmiş sipariş numarası sütunu
				esc_html( wp_date( 'd.m.Y H:i', strtotime( $order->order_date ) ) ),
				esc_html( $order->customer_name ),
				esc_html( number_format( $order->total_price, 2 ) ),
				esc_html( number_format( $order->total_cost_tl, 2 ) ),
				esc_html( number_format( $order->total_commission, 2 ) ),
				esc_html( number_format( $order->total_shipping, 2 ) ),
				esc_html( number_format( $display_fc, 2 ) ),
				'<strong style="color:' . ($profit >= 0 ? '#46b450' : '#d63638') . '">' . esc_html( number_format( $profit, 2 ) ) . '</strong>',
				'<span data-bg-class="' . esc_attr( $margin_td_class ) . '"><strong>' . esc_html( number_format( $margin_val, 2 ) ) . '%</strong></span>',
				esc_html( $status_text )
			);
		}

		wp_send_json( array(
			'draw'            => $result['draw'],
			'recordsTotal'    => $result['recordsTotal'],
			'recordsFiltered' => $result['recordsFiltered'],
			'data'            => $formatted_data,
			'customTotals'    => $result['customTotals']
		) );
	}

	/**
     * AJAX: Siparişleri Excel olarak dışa aktarır
     */
    public function ajax_export_orders(): void {
        // Yetki kontrolü
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Yetkisiz erişim.' );
        }

        // Filtreleri JavaScript'ten al
        $filters = array(
            'store_id'  => absint( $_GET['store_id'] ?? 0 ),
            'status'    => sanitize_text_field( $_GET['status'] ?? '' ),
            'date_from' => sanitize_text_field( $_GET['date_from'] ?? '' ),
            'date_to'   => sanitize_text_field( $_GET['date_to'] ?? '' ),
        );

        // Export yöneticisini çalıştır ve veriyi çek
        require_once HBT_TPT_PLUGIN_DIR . 'includes/class-export-manager.php';
        $export_manager = new HBT_Export_Manager();
        $data = $export_manager->prepare_export_data( $filters );

        // Dosya adı örn: trendyol-siparisler-2024-03-10
        $filename = 'trendyol-siparisler-' . wp_date('Y-m-d');
        
        // Excel olarak indir
        $export_manager->export_excel( $data, $filename );
        exit;
    }
	/**
	 * AJAX: Sistem Ayarlarını Kaydeder
	 */
	public function ajax_save_settings(): void {
		$this->verify_ajax(); // Güvenlik ve yetki kontrolü

		require_once HBT_TPT_PLUGIN_DIR . 'includes/class-settings.php';
		$settings = new HBT_Settings();
		
		$post_data = wp_unslash( $_POST );

		// Checkbox kapalı (0) olarak geldiyse veriyi formattan dolayı düzeltelim
		// Eğer kullanıcı hiç göndermediyse de 0 (kapalı) kabul edelim
		if ( ! isset( $post_data['notification_loss_alert'] ) ) {
			$post_data['notification_loss_alert'] = 0;
		}
		if ( ! isset( $post_data['notification_cost_missing'] ) ) {
			$post_data['notification_cost_missing'] = 0;
		}

		if ( $settings->save_from_post( $post_data ) ) {
			wp_send_json_success( array( 'message' => __( 'Ayarlar kaydedildi.', 'hbt-trendyol-profit-tracker' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Ayarlar kaydedilemedi.', 'hbt-trendyol-profit-tracker' ) ) );
		}
	}

	/**
	 * AJAX: Simülatör için TCMB'den Canlı USD Kurunu Çeker
	 */
	public function ajax_get_live_usd_rate(): void {
		$this->verify_ajax();
		
		if ( ! class_exists('HBT_Currency_Service') ) {
			require_once HBT_TPT_PLUGIN_DIR . 'includes/class-currency-service.php';
		}

		$rate_obj = HBT_Currency_Service::instance()->get_current_rate();

		if ( $rate_obj && isset($rate_obj->selling_rate) ) {
			// Ürün maliyeti hesaplarken her zaman Banka Satış (Selling) kuru baz alınır
			wp_send_json_success( array( 'rate' => $rate_obj->selling_rate ) );
		} else {
			wp_send_json_error( array( 'message' => 'Kur çekilemedi.' ) );
		}
	}
	/**
	 * AJAX: Dashboard verilerinde değişiklik olup olmadığını kontrol eder (Hafif Sorgu)
	 */
	public function ajax_check_dashboard_updates(): void {
		$this->verify_ajax();
		global $wpdb;
		
		// Sadece en son güncellenen siparişin zaman damgasına bakar. Sunucuyu ASLA yormaz.
		$last_update = $wpdb->get_var("SELECT MAX(calculated_at) FROM {$wpdb->prefix}hbt_orders");
		
		wp_send_json_success( array( 
			'last_update' => $last_update 
		) );
	}
	/**
	 * iOS Web App Meta etiketleri ve WP Admin Bar gizleme CSS'i
	 */
	public function ios_app_meta_and_css(): void {
		?>
		<meta name="apple-mobile-web-app-capable" content="yes">
		<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
		<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
		<meta name="theme-color" content="#1e293b">
		<meta name="mobile-web-app-capable" content="yes">
		
		<style>
			/* Ortak Eklenti Menüsü Vurgusu (Masaüstü + Mobil) */
			#toplevel_page_hbt-tpt-dashboard > a { background-color: #2563EB !important; color: #fff !important; }
			#toplevel_page_hbt-tpt-dashboard > a .wp-menu-image:before { color: #fff !important; }

			/* Mobilde Menü Açmak İçin Yüzen Buton (FAB) */
			#hbt-mobile-fab {
				display: none;
				position: fixed;
				bottom: 25px;
				right: 25px;
				width: 56px;
				height: 56px;
				background: #2563EB;
				color: #fff;
				border-radius: 50%;
				text-align: center;
				line-height: 56px;
				font-size: 24px;
				box-shadow: 0 4px 15px rgba(37,99,235,0.4);
				z-index: 999999 !important;
				cursor: pointer;
				transition: transform 0.2s ease;
			}
			#hbt-mobile-fab:active { transform: scale(0.95); }
			#hbt-mobile-fab .dashicons { line-height: 56px; font-size: 28px; width: 28px; height: 28px; }

			/* ==========================================================
			   SADECE MOBİL EKRANLAR (NATIVE APP DENEYİMİ) 
			   ========================================================== */
			@media screen and (max-width: 782px) {
				/* WP Admin Top Bar'ı Mobilde Tamamen Gizle */
				#wpadminbar { display: none !important; }
				html.wp-toolbar, html.wp-toolbar body { padding-top: 0 !important; margin-top: 0 !important; }
				#wpcontent { margin-top: 0 !important; padding-top: 15px !important; margin-left: 0 !important; }
				
				#hbt-mobile-fab { display: block; }
				body { padding-bottom: 80px !important; -webkit-overflow-scrolling: touch; } 
				
				/* Menü Açıkken Arka Plan Kaymasını Engelle (Gerçek App Hissi) */
				body.wp-responsive-open { overflow: hidden !important; }

				/* Menü Açıkken Arkada Kalan Karartma (Overlay) - TAM EKRAN */
				body.wp-responsive-open #adminmenuback {
					display: block !important;
					position: fixed !important;
					top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important;
					width: 100vw !important; height: 100vh !important;
					background: rgba(0,0,0,0.6) !important;
					backdrop-filter: blur(2px);
					z-index: 999997 !important;
					margin: 0 !important; padding: 0 !important;
				}

				/* Menü Taşıyıcı Düzeltmesi (Window İçinde Window Hissini Tamamen Yok Eder) */
				body.wp-responsive-open #adminmenuwrap {
					display: block !important;
					position: fixed !important;
					top: 0 !important; left: 0 !important; bottom: 0 !important;
					width: 85vw !important; 
					max-width: 340px !important;
					height: 100vh !important;
					margin: 0 !important; 
					padding: 0 !important;
					z-index: 999998 !important;
					background: #0f172a !important; 
					overflow-y: auto !important;
					overflow-x: hidden !important;
					box-shadow: 2px 0 25px rgba(0,0,0,0.6) !important;
					border: none !important;
				}
				
				/* Menü Taşıyıcı Düzeltmesi (Window İçinde Window Hissini Tamamen Yok Eder) */
				body.wp-responsive-open #adminmenuwrap {
					display: block !important;
					position: fixed !important;
					top: 0 !important; left: 0 !important; bottom: 0 !important;
					width: 85vw !important; 
					max-width: 360px !important;
					height: 100vh !important;
					margin: 0 !important; 
					padding: 0 !important;
					z-index: 999998 !important;
					background: #0f172a !important; 
					overflow-y: auto !important;
					overflow-x: hidden !important;
					box-shadow: 2px 0 25px rgba(0,0,0,0.6) !important;
					border: none !important;
				}
				
				/* ==========================================================
				   MODERN APP MENÜ TASARIMI (KAPSÜL VE TAM GENİŞLİK)
				   ========================================================== */
				body.wp-responsive-open #adminmenu { 
					margin: 0 !important; 
					padding: 60px 0 40px 0 !important; 
					background: transparent !important;
					border: none !important;
					box-shadow: none !important;
					display: flex !important;
					flex-direction: column !important;
					gap: 6px !important;
					width: 100% !important; /* İŞTE O EKSİK KOD: Menüyü ekranın sonuna kadar genişletir */
					align-items: stretch !important;
				}
				
				body.wp-responsive-open #adminmenu::before {
					content: "HBT Kâr Takip";
					display: block;
					color: #fff;
					font-size: 24px;
					font-weight: 800;
					padding: 0 25px 30px 25px;
					letter-spacing: -0.5px;
					border-bottom: 1px solid rgba(255,255,255,0.05);
					margin-bottom: 15px;
				}

				/* Menü Dış Kutuları (Sağdan Soldan 15px Boşlukla Tam Ekran) */
				#adminmenu li.menu-top { 
					border: none !important; 
					margin: 0 !important; 
					padding: 0 15px !important; 
					width: 100% !important; 
				}
				
				/* Menü Linkleri (Kapsüller) */
				#adminmenu li.menu-top > a { 
					font-size: 18px !important; 
					padding: 18px 20px !important; 
					border: none !important;
					color: #cbd5e1 !important; 
					margin: 0 !important;
					display: flex !important; 
					align-items: center !important;
					border-radius: 14px !important; 
					transition: all 0.2s ease !important;
					width: 100% !important; /* Kapsülü tam genişletir */
					box-sizing: border-box !important;
				}
				#adminmenu li.menu-top:hover > a, 
				#adminmenu li.menu-top.wp-has-current-submenu > a {
					background: #1e293b !important;
					color: #fff !important;
				}
				
				/* İkonlar - Yazıların üzerine binmemesi için kesin çözüm */
				#adminmenu .wp-menu-image {
					float: none !important;
					width: 45px !important; /* İkon alanı biraz daha genişletildi */
					height: 32px !important;
					margin: 0 10px 0 0 !important; /* Yazı ile ikon arasına net boşluk */
					display: flex !important;
					align-items: center !important;
					justify-content: center !important;
					position: relative !important;
				}
				#adminmenu .wp-menu-image:before { 
					font-size: 26px !important; 
					color: #94a3b8 !important;
					padding: 0 !important;
					position: static !important; /* WP'nin default mutlak konumlandırmasını iptal eder */
				}
				
				/* Menü Metinleri - Esnek ve Temiz Hizalama */
				#adminmenu .wp-menu-name {
					padding: 0 !important;
					display: flex !important;
					align-items: center !important;
					width: auto !important; /* Daralmayı önler */
					flex: 1 !important;
					white-space: normal !important;
					line-height: 1.3 !important;
				}

				/* Arkaplan Renkleri - WP Standart Koyu Temasıyla Uyumluluk */
				body.wp-responsive-open #adminmenuwrap {
					background: #1e1e1e !important; /* WP Orijinal Koyu Arkaplan */
				}
				
				#adminmenu li.menu-top > a { 
					background: transparent !important; /* Varsayılan mavi vurguyu kaldırdık */
					color: #cbd5e1 !important;
					border-radius: 10px !important;
				}

				/* Sadece üzerine gelindiğinde veya aktifken renk değişsin (WP Doğal Davranışı) */
				#adminmenu li.menu-top:hover > a, 
				#adminmenu li.menu-top.wp-has-current-submenu > a {
					background: #2c3338 !important; /* WP Hover/Active rengi */
					color: #3b82f6 !important; /* Yazı rengi maviye döner */
				}

				#toplevel_page_hbt-tpt-dashboard > a { 
					background: transparent !important; /* Masaüstündeki mavi vurguyu mobil app çekmecesinde sadeleştirdik */
				}

				/* Alt Menü Linkleri */
				#adminmenu .wp-submenu a {
					padding: 12px 20px 12px 55px !important; /* İkonun alt hizasına göre boşluk */
				}
				
				/* "Diğer Menüler" Başlığı Stili */
				#hbt-other-menus-toggle { 
					background: transparent !important; 
					border-top: 1px solid rgba(255,255,255,0.05) !important; 
					margin-top: 15px !important; 
					padding-top: 15px !important; 
				}
				#hbt-other-menus-toggle a { color: #64748b !important; font-weight: 600; }

				/* Gereksiz WP Kalıntılarını Gizle */
				#collapse-menu { display: none !important; }
				#adminmenu .auto-fold { border: none !important; }
				#adminmenu * { box-sizing: border-box !important; }
			}
		</style>
		<?php
	}

	/**
	 * Admin Menüsünü manipüle eden ve FAB butonunu çalıştıran JS
	 */
	public function ios_app_menu_js(): void {
		?>
		<div id="hbt-mobile-fab">
			<span class="dashicons dashicons-menu"></span>
		</div>

		<script>
		jQuery(document).ready(function($) {
			var $menu = $('#adminmenu');
			var $ourMenu = $('#toplevel_page_hbt-tpt-dashboard'); 
			var isMobile = window.innerWidth <= 782; // Sadece mobil ekran kontrolü
			
			if (!$ourMenu.length) return;
			
			// Eklentimizin menüsünü her zaman vurgulu ve açık tut (Masaüstü + Mobil Ortak)
			$ourMenu.addClass('wp-has-current-submenu wp-menu-open');
			$ourMenu.find('> a').addClass('wp-has-current-submenu wp-menu-open').removeClass('wp-not-current-submenu');
			
			// SADECE MOBİL İSE YAPILACAK İŞLEMLER
			if (isMobile) {
				// 1. Eklentimizin menüsünü en üste taşı
				$menu.prepend($ourMenu);
				
				// 2. "Diğer Menüler" butonunu bizim menünün altına yerleştir
				$ourMenu.after('<li class="menu-top menu-icon-generic" id="hbt-other-menus-toggle"><a href="#" class="menu-top"><div class="wp-menu-image dashicons-before dashicons-category"></div><div class="wp-menu-name">Diğer Menüler <span class="dashicons dashicons-arrow-down-alt2" style="float:right; margin-top:6px;"></span></div></a></li>');
				
				// 3. Bizim menü ve yeni buton HARİÇ tüm diğer WordPress menülerini seç
				var $others = $menu.find('> li').not($ourMenu).not('#collapse-menu').not('#hbt-other-menus-toggle');
				
				// 4. Diğer menüleri butondan sonraya diz ve gizle
				$others.insertAfter('#hbt-other-menus-toggle');
				$others.hide();
				
				// Diğer menüleri şık bir şekilde aç/kapat
				$('#hbt-other-menus-toggle').on('click', function(e) {
					e.preventDefault();
					$others.slideToggle('fast');
					var $icon = $(this).find('.dashicons-arrow-down-alt2, .dashicons-arrow-up-alt2');
					if ($icon.hasClass('dashicons-arrow-down-alt2')) {
						$icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
					} else {
						$icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
					}
				});
			}

			// Mobilde Yüzen Butona (FAB) Tıklanınca Menüyü Aç/Kapat
			$('#hbt-mobile-fab').on('click', function() {
				$('body').toggleClass('wp-responsive-open');
				var $icon = $(this).find('.dashicons');
				if ($('body').hasClass('wp-responsive-open')) {
					$icon.removeClass('dashicons-menu').addClass('dashicons-no-alt'); // Çarpı ikonu
				} else {
					$icon.removeClass('dashicons-no-alt').addClass('dashicons-menu'); // Menü ikonu
				}
			});

			// Menü açıkken, arkaplandaki karartmaya tıklanırsa menüyü kapat
			$('#adminmenuback').on('click', function() {
				if ($('body').hasClass('wp-responsive-open')) {
					$('#hbt-mobile-fab').click(); 
				}
			});
		});
		</script>
		<?php
	}
}