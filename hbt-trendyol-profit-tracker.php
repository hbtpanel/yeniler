<?php
/**
 * Plugin Name: HBT Trendyol Profit Tracker
 * Plugin URI:  https://hbtpanel.com
 * Description: Trendyol mağazaları için sipariş bazlı kâr/zarar analiz paneli.
 * Version:     1.0.0
 * Author:      HBT Panel
 * Author URI:  https://hbtpanel.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: hbt-trendyol-profit-tracker
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 5.8
 */

defined( 'ABSPATH' ) || exit;

// PHP version check.
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>' .
				esc_html__( 'HBT Trendyol Profit Tracker requires PHP 7.4 or higher.', 'hbt-trendyol-profit-tracker' ) .
				'</p></div>';
		}
	);
	return;
}

// WordPress version check.
if ( version_compare( get_bloginfo( 'version' ), '5.8', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>' .
				esc_html__( 'HBT Trendyol Profit Tracker requires WordPress 5.8 or higher.', 'hbt-trendyol-profit-tracker' ) .
				'</p></div>';
		}
	);
	return;
}

// Plugin constants.
define( 'HBT_TPT_VERSION', '1.0.0' );
define( 'HBT_TPT_PLUGIN_FILE', __FILE__ );
define( 'HBT_TPT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HBT_TPT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Composer autoload.
if ( file_exists( HBT_TPT_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once HBT_TPT_PLUGIN_DIR . 'vendor/autoload.php';
}

// Manual require for includes.
$hbt_tpt_includes = array(
	'includes/class-database.php',
	'includes/class-settings.php',
	'includes/class-currency-service.php',
	'includes/class-trendyol-api.php',
	'includes/class-profit-calculator.php',
	'includes/class-return-manager.php',
	'includes/class-cron-manager.php',
	'includes/class-notification-manager.php',
	'includes/class-export-manager.php',
);

foreach ( $hbt_tpt_includes as $file ) {
	require_once HBT_TPT_PLUGIN_DIR . $file;
}

// Admin files.
if ( is_admin() ) {
	require_once HBT_TPT_PLUGIN_DIR . 'admin/class-admin-menu.php';
}

/**
 * Main plugin class.
 *
 * @since 1.0.0
 */
final class HBT_Trendyol_Profit_Tracker {

	/**
	 * Singleton instance.
	 *
	 * @var HBT_Trendyol_Profit_Tracker
	 */
	private static ?HBT_Trendyol_Profit_Tracker $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return HBT_Trendyol_Profit_Tracker
	 */
	public static function instance(): HBT_Trendyol_Profit_Tracker {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks.
	 */
	private function init_hooks(): void {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		if ( is_admin() ) {
			$admin_menu = new HBT_Admin_Menu();
			add_action( 'admin_menu', array( $admin_menu, 'register_menus' ) );
			add_action( 'admin_enqueue_scripts', array( $admin_menu, 'enqueue_assets' ) );

			$notification_manager = HBT_Notification_Manager::instance();
			add_action( 'admin_notices', array( $notification_manager, 'display_admin_notices' ) );

			// NOTE: AJAX hooks are registered inside HBT_Admin_Menu constructor to avoid duplicate/mismatched registrations.
			// Previously we called $this->register_ajax_hooks( $admin_menu, $notification_manager ); here which caused duplicate/hatalı callbacks.
		}
	}

	/**
	 * Register all AJAX hooks.
	 *
	 * @param HBT_Admin_Menu           $admin_menu           Admin menu instance.
	 * @param HBT_Notification_Manager $notification_manager Notification manager instance.
	 */
	private function register_ajax_hooks( HBT_Admin_Menu $admin_menu, HBT_Notification_Manager $notification_manager ): void {
		$ajax_actions = array(
			'hbt_save_store'            => array( $admin_menu, 'ajax_save_store' ),
			'hbt_delete_store'          => array( $admin_menu, 'ajax_delete_store' ),
			'hbt_test_connection'       => array( $admin_menu, 'ajax_test_connection' ),
			'hbt_sync_store'            => array( $admin_menu, 'ajax_sync_store' ),
			'hbt_sync_products'         => array( $admin_menu, 'ajax_sync_products' ),
			'hbt_save_product_cost'     => array( $admin_menu, 'ajax_save_product_cost' ),
			'hbt_bulk_import_costs'     => array( $admin_menu, 'ajax_bulk_import_costs' ),
			'hbt_delete_product'        => array( $admin_menu, 'ajax_delete_product' ),
			'hbt_save_shipping_cost'    => array( $admin_menu, 'ajax_save_shipping_cost' ),
			'hbt_delete_shipping_cost'  => array( $admin_menu, 'ajax_delete_shipping_cost' ),
			'hbt_get_order_details'     => array( $admin_menu, 'ajax_get_order_details' ),
			'hbt_recalculate_order'     => array( $admin_menu, 'ajax_recalculate_order' ),
			'hbt_update_return'         => array( $admin_menu, 'ajax_update_return' ),
			'hbt_dismiss_notification'  => array( $notification_manager, 'ajax_dismiss_notification' ),
			'hbt_export_csv'            => array( $admin_menu, 'ajax_export_csv' ),
			'hbt_export_excel'          => array( $admin_menu, 'ajax_export_excel' ),
			'hbt_export_pdf'            => array( $admin_menu, 'ajax_export_pdf' ),
			'hbt_get_dashboard_data'    => array( $admin_menu, 'ajax_get_dashboard_data' ),
			'hbt_save_settings'         => array( $admin_menu, 'ajax_save_settings' ),
		);

		foreach ( $ajax_actions as $action => $callback ) {
			add_action( 'wp_ajax_' . $action, $callback );
		}
	}

	/**
	 * Load plugin textdomain.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'hbt-trendyol-profit-tracker',
			false,
			dirname( plugin_basename( HBT_TPT_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Plugin activation.
	 */
	public static function activate(): void {
		HBT_Database::instance()->create_tables();
		HBT_Cron_Manager::instance()->schedule_events();
	}

	/**
	 * Plugin deactivation.
	 */
	public static function deactivate(): void {
		HBT_Cron_Manager::instance()->unschedule_events();
	}
}

// Activation / deactivation hooks.
register_activation_hook( __FILE__, array( 'HBT_Trendyol_Profit_Tracker', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'HBT_Trendyol_Profit_Tracker', 'deactivate' ) );

// Bootstrap.
HBT_Trendyol_Profit_Tracker::instance();

// --- ARKA PLAN (CRON) İŞÇİSİNİ ZORLA UYANIK TUTMA MOTORU ---
add_action( 'plugins_loaded', 'hbt_force_start_cron_manager', 99 );
function hbt_force_start_cron_manager() {
	// Veritabanı ve Cron sınıflarının arka planda da kesin yüklendiğinden emin ol
	$db_file   = dirname( __FILE__ ) . '/includes/class-database.php';
	$cron_file = dirname( __FILE__ ) . '/includes/class-cron-manager.php';
	
	if ( file_exists( $db_file ) && file_exists( $cron_file ) ) {
		require_once $db_file;
		require_once $cron_file;
		
		// İşçiyi başlat
		if ( class_exists( 'HBT_Cron_Manager' ) ) {
			HBT_Cron_Manager::instance();
		}
	}
}

// --- CANLI MONİTÖR AJAX BAĞLANTILARI ---
add_action('wp_ajax_hbt_monitor_status', 'hbt_ajax_monitor_status');
function hbt_ajax_monitor_status() {
    $queue = get_option('hbt_background_queue', array());
    $logs = array();
    if (class_exists('HBT_Database')) {
        $logs = HBT_Database::instance()->get_sync_logs(5); // Son 5 logu terminale çek
    }
    
    $active_job = null;
    if (!empty($queue) && is_array($queue)) {
        $active_job = $queue[0];
        if (class_exists('HBT_Database')) {
            $store = HBT_Database::instance()->get_store((int)$active_job['store_id']);
            $active_job['store_name'] = $store ? $store->store_name : 'Bilinmeyen Mağaza';
        }
    }
    
   $stream_logs = get_option('hbt_live_stream_logs', array());

    wp_send_json_success(array(
        'queue_count' => is_array($queue) ? count($queue) : 0,
        'active_job'  => $active_job,
        'recent_logs' => $logs,
        'stream_logs' => $stream_logs
    ));
}

add_action('wp_ajax_hbt_monitor_trigger', 'hbt_ajax_monitor_trigger');
function hbt_ajax_monitor_trigger() {
    $cmd = isset($_POST['cmd']) ? sanitize_text_field($_POST['cmd']) : '';
    if ($cmd === 'add_fast' && class_exists('HBT_Cron_Manager')) {
        HBT_Cron_Manager::instance()->sync_orders_fast();
        wp_send_json_success('Hızlı tarama başlatıldı. Mağazalar kuyruğa eklendi.');
    } elseif ($cmd === 'run_worker' && class_exists('HBT_Cron_Manager')) {
        HBT_Cron_Manager::instance()->process_background_queue();
        wp_send_json_success('İşçi (Worker) başarıyla bir tur işlem yaptı.');
    } elseif ($cmd === 'clear_queue') {
        delete_option('hbt_background_queue');
        delete_option('hbt_live_stream_logs'); // Kuyruğu temizleyince ekran da temizlensin
        wp_send_json_success('Kuyruk ve bekleyen görevler temizlendi.');
    }
    wp_send_json_error('Komut işlenemedi.');
}