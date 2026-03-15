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

		$notifications = $this->db->get_unread( 5 );
		if ( empty( $notifications ) ) {
			return;
		}

		foreach ( $notifications as $notification ) {
			$class = $this->get_notice_class( $notification->notification_type );

			// Use a container with class hbt-admin-notice and data-id for JS to read.
			// Keep 'is-dismissible' so WP adds the default close button as well.
			echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible hbt-admin-notice" data-id="' . esc_attr( (string) $notification->id ) . '">';
			echo '<p><strong>' . esc_html( $notification->title ) . ':</strong> ' . esc_html( $notification->message ) . '</p>';
			// Add an explicit small dismiss link so it's obvious and selectable by our JS.
			echo '<p><a href="#" class="hbt-notice-close" aria-label="' . esc_attr__( 'Bildirimi kapat', 'hbt-trendyol-profit-tracker' ) . '">' . esc_html__( 'Kapat', 'hbt-trendyol-profit-tracker' ) . '</a></p>';
			echo '</div>';
		}
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