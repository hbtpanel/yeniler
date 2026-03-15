<?php
/**
 * Notification manager.
 *
 * @package HBT_Trendyol_Profit_Tracker
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

class HBT_Notification_Manager {

	/**
	 * Database instance.
	 *
	 * @var HBT_Database
	 */
	private HBT_Database $db;

	/**
	 * Singleton instance.
	 *
	 * @var HBT_Notification_Manager|null
	 */
	private static ?HBT_Notification_Manager $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return HBT_Notification_Manager
	 */
	public static function instance(): HBT_Notification_Manager {
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

		// Hook admin notices display.
		add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );
	}

	// -------------------------------------------------------------------------
	// Create & retrieve
	// -------------------------------------------------------------------------

	/**
	 * Create a new notification.
	 *
	 * @param  string   $type       Notification type.
	 * @param  string   $title      Notification title.
	 * @param  string   $message    Notification message.
	 * @param  int|null $related_id Related entity ID.
	 * @return int|false
	 */
	public function create_notification( string $type, string $title, string $message, ?int $related_id = null ) {
		return $this->db->create_notification( $type, $title, $message, $related_id );
	}

	/**
	 * Get unread notification count.
	 *
	 * @return int
	 */
	public function get_unread_count(): int {
		return $this->db->get_unread_count();
	}

	/**
	 * Get all unread notifications.
	 *
	 * @param  int $limit Max records.
	 * @return array
	 */
	public function get_unread( int $limit = 20 ): array {
		return $this->db->get_unread( $limit );
	}

	/**
	 * Mark all notifications as read.
	 */
	public function mark_all_read(): void {
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'hbt_notifications',
			array( 'is_read' => 1 ),
			array( 'is_read' => 0 )
		);
	}

	/**
	 * Dismiss a notification.
	 *
	 * @param  int  $id Notification ID.
	 * @return bool
	 */
	public function dismiss( int $id ): bool {
		return $this->db->dismiss_notification( $id );
	}

	/**
	 * Mark a notification as read.
	 *
	 * @param  int  $id Notification ID.
	 * @return bool
	 */
	public function mark_read( int $id ): bool {
		return $this->db->mark_read( $id );
	}

	/**
	 * Delete old read notifications (30 days+).
	 */
	public function cleanup_old_notifications(): void {
		$this->db->cleanup_old_notifications();
	}

	// -------------------------------------------------------------------------
	// Admin UI
	// -------------------------------------------------------------------------

	/**
	 * Display admin notices in the WP admin area.
	 * Hooked to admin_notices.
	 */
	public function display_admin_notices(): void {
		// Only show on plugin pages.
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'hbt-tpt' ) === false ) {
			return;
		}

		// Eskisi gibi sadece 5 tane değil, okunmamış tüm bildirimleri (200'e kadar) çekelim ki gruplama doğru sayı versin
		$notifications = $this->db->get_unread( 200 );
		if ( empty( $notifications ) ) {
			return;
		}

		// Bildirimleri başlıklarına göre grupla ve say
		$summary = array();
		foreach ( $notifications as $notif ) {
			$title = $notif->title;
			if ( ! isset( $summary[ $title ] ) ) {
				$summary[ $title ] = 0;
			}
			$summary[ $title ]++;
		}

		// Tekil, Derli Toplu Özet Bildirim Kutusu
		echo '<div class="notice notice-warning is-dismissible" id="hbt-global-summary-notice" style="border-left-color: #f59e0b; padding-bottom: 15px; margin-top: 15px;">';
		echo '<p style="font-size: 14px;"><strong><span class="dashicons dashicons-bell" style="color: #f59e0b; margin-right: 5px; vertical-align: text-top;"></span> Trendyol Kâr Takip - Okunmamış Bildirim Özeti</strong></p>';
		
		echo '<ul style="margin-left: 32px; list-style-type: square; margin-bottom: 15px; font-size: 13px;">';
		foreach ( $summary as $title => $count ) {
			echo '<li>' . esc_html( $title ) . ': <strong>' . intval( $count ) . ' adet</strong></li>';
		}
		echo '</ul>';
		
		echo '<div style="display: flex; gap: 10px;">';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=hbt-tpt-notifications' ) ) . '" class="button button-primary">' . esc_html__( 'Detayları İncele', 'hbt-trendyol-profit-tracker' ) . '</a>';
		echo '<button type="button" class="button" id="hbt-mark-all-read-inline-btn">' . esc_html__( 'Tümünü Okundu Say', 'hbt-trendyol-profit-tracker' ) . '</button>';
		echo '</div>';

		// AJAX ile "Tümünü Okundu Say" işlemini tetikleyen anlık script
		echo "<script>
		jQuery(document).ready(function($) {
			$('#hbt-mark-all-read-inline-btn').on('click', function(e) {
				e.preventDefault();
				var btn = $(this);
				btn.prop('disabled', true).text('İşleniyor...');
				
				$.post(hbtTpt.ajaxurl, { action: 'hbt_mark_all_notifications_read', nonce: hbtTpt.nonce }, function(res) {
					if (res.success) {
						$('#hbt-global-summary-notice').slideUp();
						$('.hbt-blink-badge').remove(); // Menüdeki yanıp sönen bildirim balonunu da sil
					} else {
						btn.prop('disabled', false).text('Hata Oluştu');
					}
				});
			});
		});
		</script>";
		echo '</div>';
	}

	/**
	 * Convert notification_type to WP notice class.
	 *
	 * @param string $type
	 * @return string
	 */
	private function get_notice_class( string $type ): string {
		switch ( $type ) {
			case 'sync_error':
			case 'critical_loss':
				return 'notice-error';
			case 'loss_alert':
				return 'notice-warning';
			case 'cost_missing':
			default:
				return 'notice-info';
		}
	}
}