<?php
/**
 * Reports view.
 *
 * @package HBT_Trendyol_Profit_Tracker
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

$stores    = HBT_Database::instance()->get_stores();
$store_id  = absint( $_GET['store_id'] ?? 0 );
$date_from = sanitize_text_field( $_GET['date_from'] ?? gmdate( 'Y-m-01' ) );
$date_to   = sanitize_text_field( $_GET['date_to'] ?? gmdate( 'Y-m-d' ) );

$where_args = array( 'o.order_date BETWEEN %s AND %s' );
$params     = array( $date_from . ' 00:00:00', $date_to . ' 23:59:59' );

$where_args[] = "o.status NOT IN ('Cancelled', 'Returned', 'UnSupplied')";

if ( $store_id > 0 ) {
	$where_args[] = 'o.store_id = %d';
	$params[]     = $store_id;
}

$where_sql = implode( ' AND ', $where_args );

// General summary
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$summary = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(DISTINCT o.id) AS total_orders, SUM(total_price) AS total_sales, SUM(total_cost_tl) AS total_cost, SUM(total_commission) AS total_commission, SUM(total_shipping) AS total_shipping, SUM(vat_amount) AS total_vat, SUM(net_profit) AS total_profit, AVG(profit_margin) AS avg_margin FROM {$wpdb->prefix}hbt_orders o WHERE {$where_sql}", ...$params ) );

$total_items_sold = (int) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(oi.quantity) FROM {$wpdb->prefix}hbt_order_items oi INNER JOIN {$wpdb->prefix}hbt_orders o ON o.id = oi.order_id WHERE {$where_sql}", ...$params ) );

// --- Reklam Gideri Kesit Hesaplaması (Seçili Tarih Aralığı) ---
$all_ad_expenses = HBT_Database::instance()->get_all_ad_expenses();
$total_ad_expense = 0.0;
$report_start_ts = strtotime( $date_from . ' 00:00:00' );
$report_end_ts   = strtotime( $date_to . ' 00:00:00' );

foreach ( $all_ad_expenses as $ad ) {
	if ( $store_id > 0 && $ad->store_id != $store_id ) continue;

	$ad_start_ts = strtotime( $ad->start_date . ' 00:00:00' );
	$ad_end_ts   = strtotime( $ad->end_date . ' 00:00:00' );

	$overlap_start = max( $report_start_ts, $ad_start_ts );
	$overlap_end   = min( $report_end_ts, $ad_end_ts );

	if ( $overlap_start <= $overlap_end ) {
		$overlap_days = round( ( $overlap_end - $overlap_start ) / 86400 ) + 1;
		$total_ad_expense += $overlap_days * (float) $ad->daily_amount;
	}
}

// --- YENİ: Mağaza Bazlı Son 30 Günlük Kâr Hesaplaması ---
$l30_start_date = gmdate('Y-m-d', strtotime('-30 days'));
$l30_end_date   = gmdate('Y-m-d');
$l30_start      = $l30_start_date . ' 00:00:00';
$l30_end        = $l30_end_date . ' 23:59:59';

// 1. Son 30 Günlük Ham Sipariş Kârı
$l30_profits_raw = $wpdb->get_results( $wpdb->prepare( "
	SELECT store_id, SUM(net_profit) AS profit 
	FROM {$wpdb->prefix}hbt_orders 
	WHERE status NOT IN ('Cancelled', 'Returned', 'UnSupplied') 
	AND order_date BETWEEN %s AND %s 
	GROUP BY store_id
", $l30_start, $l30_end ), OBJECT_K );

// 2. Son 30 Günlük Reklam Giderleri
$l30_ad_expenses = array();
$l30_start_ts = strtotime($l30_start_date . ' 00:00:00');
$l30_end_ts   = strtotime($l30_end_date . ' 00:00:00');

foreach ( $all_ad_expenses as $ad ) {
	$ad_s_ts = strtotime( $ad->start_date . ' 00:00:00' );
	$ad_e_ts = strtotime( $ad->end_date . ' 00:00:00' );
	$overlap_s = max( $l30_start_ts, $ad_s_ts );
	$overlap_e = min( $l30_end_ts, $ad_e_ts );
	
	if ( $overlap_s <= $overlap_e ) {
		$days = round( ( $overlap_e - $overlap_s ) / 86400 ) + 1;
		if ( ! isset( $l30_ad_expenses[ $ad->store_id ] ) ) {
			$l30_ad_expenses[ $ad->store_id ] = 0;
		}
		$l30_ad_expenses[ $ad->store_id ] += $days * (float) $ad->daily_amount;
	}
}

// 3. Mağazaları Birleştirip Net Kârı Çıkarma
$store_l30_profits = array();
foreach ( $stores as $st ) {
	$raw = isset( $l30_profits_raw[ $st->id ] ) ? (float) $l30_profits_raw[ $st->id ]->profit : 0.0;
	$ad_exp = isset( $l30_ad_expenses[ $st->id ] ) ? (float) $l30_ad_expenses[ $st->id ] : 0.0;
	$store_l30_profits[ $st->id ] = array(
		'name'   => $st->store_name,
		'profit' => $raw - $ad_exp
	);
}
// ---------------------------------------------------------

$product_stats = $wpdb->get_results( $wpdb->prepare( "SELECT oi.barcode, oi.product_name, SUM(oi.net_profit) AS total_profit, SUM(oi.line_total) AS total_sales FROM {$wpdb->prefix}hbt_order_items oi INNER JOIN {$wpdb->prefix}hbt_orders o ON o.id = oi.order_id WHERE {$where_sql} GROUP BY oi.barcode ORDER BY total_profit DESC", ...$params ) );

$best_product  = !empty($product_stats) ? $product_stats[0] : null;
$worst_product = !empty($product_stats) ? end($product_stats) : null;

$category_stats = $wpdb->get_results( $wpdb->prepare( "SELECT pc.category_name, SUM(oi.net_profit) AS total_profit, AVG(oi.profit_margin) AS avg_margin FROM {$wpdb->prefix}hbt_order_items oi INNER JOIN {$wpdb->prefix}hbt_orders o ON o.id = oi.order_id LEFT JOIN {$wpdb->prefix}hbt_product_costs pc ON pc.barcode = oi.barcode AND pc.store_id = o.store_id WHERE {$where_sql} GROUP BY pc.category_name ORDER BY total_profit DESC", ...$params ) );

$cancel_where_args = array( "o.status IN ('Cancelled', 'UnSupplied')", 'o.order_date BETWEEN %s AND %s' );
$cancel_params     = array( $date_from . ' 00:00:00', $date_to . ' 23:59:59' );
if ( $store_id > 0 ) {
	$cancel_where_args[] = 'o.store_id = %d';
	$cancel_params[]     = $store_id;
}
$cancel_where_sql = implode( ' AND ', $cancel_where_args );
$cancel_summary = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(o.id) AS total_cancelled_orders, SUM(o.total_price) AS total_cancelled_amount FROM {$wpdb->prefix}hbt_orders o WHERE {$cancel_where_sql}", ...$cancel_params ) );
$top_cancelled = $wpdb->get_results( $wpdb->prepare( "SELECT oi.barcode, oi.product_name, SUM(oi.quantity) AS total_qty FROM {$wpdb->prefix}hbt_order_items oi INNER JOIN {$wpdb->prefix}hbt_orders o ON o.id = oi.order_id WHERE {$cancel_where_sql} GROUP BY oi.barcode ORDER BY total_qty DESC LIMIT 10", ...$cancel_params ) );

$return_where_args = array( "o.status = 'Returned'", 'o.order_date BETWEEN %s AND %s' );
$return_params     = array( $date_from . ' 00:00:00', $date_to . ' 23:59:59' );
if ( $store_id > 0 ) {
	$return_where_args[] = 'o.store_id = %d';
	$return_params[]     = $store_id;
}
$return_where_sql = implode( ' AND ', $return_where_args );
$total_returns = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(o.id) FROM {$wpdb->prefix}hbt_orders o WHERE {$return_where_sql}", ...$return_params ) );

// Trend Hesabı
$cw_end   = $date_to . ' 23:59:59';
$cw_start = gmdate('Y-m-d', strtotime($date_to . ' - 6 days')) . ' 00:00:00';
$pw_end   = gmdate('Y-m-d', strtotime($date_to . ' - 7 days')) . ' 23:59:59';
$pw_start = gmdate('Y-m-d', strtotime($date_to . ' - 13 days')) . ' 00:00:00';
$trend_store_filter = $store_id > 0 ? $wpdb->prepare(" AND store_id = %d", $store_id) : "";
$cw_sales = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(total_price) FROM {$wpdb->prefix}hbt_orders WHERE status NOT IN ('Cancelled', 'Returned', 'UnSupplied') AND order_date BETWEEN %s AND %s" . $trend_store_filter, $cw_start, $cw_end ) );
$pw_sales = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(total_price) FROM {$wpdb->prefix}hbt_orders WHERE status NOT IN ('Cancelled', 'Returned', 'UnSupplied') AND order_date BETWEEN %s AND %s" . $trend_store_filter, $pw_start, $pw_end ) );
$growth_percent = $pw_sales > 0 ? ( ( $cw_sales - $pw_sales ) / $pw_sales ) * 100 : ( $cw_sales > 0 ? 100 : 0 );
$growth_color   = $growth_percent >= 0 ? '#10B981' : '#EF4444'; // Modern yeşil / kırmızı
$growth_icon    = $growth_percent >= 0 ? '▲' : '▼';

// Hesaplamalar
$sales         = (float) ( $summary->total_sales ?? 0 );
$raw_profit    = (float) ( $summary->total_profit ?? 0 );
$final_profit  = $raw_profit - $total_ad_expense; 

$orders_count  = (int) ( $summary->total_orders ?? 0 );
$gross_margin  = $sales > 0 ? ( $final_profit / $sales ) * 100 : 0;
$avg_order_val = $orders_count > 0 ? ( $sales / $orders_count ) : 0;
$avg_profit    = $orders_count > 0 ? ( $final_profit / $orders_count ) : 0;

$total_all_orders = $orders_count + $total_returns + (int)($cancel_summary->total_cancelled_orders ?? 0);
$return_rate   = $total_all_orders > 0 ? ( $total_returns / $total_all_orders ) * 100 : 0;
?>
<div class="wrap hbt-tpt-wrap">
	
	<div class="hbt-page-header">
		<h1 class="hbt-page-title">
			<span class="dashicons dashicons-chart-area"></span> 
			<?php esc_html_e( 'Raporlar ve Analiz', 'hbt-trendyol-profit-tracker' ); ?>
		</h1>
		<div class="hbt-header-actions" style="display: flex; gap: 8px;">
			<button class="hbt-btn hbt-btn-outline btn-export" data-format="csv" style="color: #059669 !important; border-color: #A7F3D0 !important; background: #ECFDF5 !important;">
				<span class="dashicons dashicons-media-spreadsheet"></span> <?php esc_html_e( 'CSV', 'hbt-trendyol-profit-tracker' ); ?>
			</button>
			<button class="hbt-btn hbt-btn-outline btn-export" data-format="excel" style="color: #0284C7 !important; border-color: #BAE6FD !important; background: #F0F9FF !important;">
				<span class="dashicons dashicons-media-text"></span> <?php esc_html_e( 'Excel', 'hbt-trendyol-profit-tracker' ); ?>
			</button>
			<button class="hbt-btn hbt-btn-outline btn-export" data-format="pdf" style="color: #DC2626 !important; border-color: #FECACA !important; background: #FEF2F2 !important;">
				<span class="dashicons dashicons-pdf"></span> <?php esc_html_e( 'PDF', 'hbt-trendyol-profit-tracker' ); ?>
			</button>
		</div>
	</div>

	<div class="hbt-card" style="margin-bottom: 24px;">
		<form method="get" action="">
			<input type="hidden" name="page" value="hbt-tpt-reports">
			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; align-items: end;">
				
				<div class="hbt-filter-group">
					<label style="font-weight: 600; color: var(--hbt-primary); margin-bottom: 6px; display: block; font-size: 13px;"><?php esc_html_e( 'Tarih Başlangıç:', 'hbt-trendyol-profit-tracker' ); ?></label>
					<input type="text" name="date_from" class="hbt-datepicker regular-text" value="<?php echo esc_attr( $date_from ); ?>" placeholder="YYYY-MM-DD" style="width: 100%; padding: 8px 12px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border);">
				</div>

				<div class="hbt-filter-group">
					<label style="font-weight: 600; color: var(--hbt-primary); margin-bottom: 6px; display: block; font-size: 13px;"><?php esc_html_e( 'Tarih Bitiş:', 'hbt-trendyol-profit-tracker' ); ?></label>
					<input type="text" name="date_to" class="hbt-datepicker regular-text" value="<?php echo esc_attr( $date_to ); ?>" placeholder="YYYY-MM-DD" style="width: 100%; padding: 8px 12px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border);">
				</div>

				<div class="hbt-filter-group">
					<label style="font-weight: 600; color: var(--hbt-primary); margin-bottom: 6px; display: block; font-size: 13px;"><?php esc_html_e( 'Mağaza:', 'hbt-trendyol-profit-tracker' ); ?></label>
					<select name="store_id" style="width: 100%; padding: 8px 12px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border);">
						<option value="0"><?php esc_html_e( 'Tüm Mağazalar', 'hbt-trendyol-profit-tracker' ); ?></option>
						<?php foreach ( $stores as $store ) : ?>
							<option value="<?php echo esc_attr( (string) $store->id ); ?>" <?php selected( $store_id, (int) $store->id ); ?>>
								<?php echo esc_html( $store->store_name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="hbt-filter-group">
					<button type="submit" class="hbt-btn hbt-btn-primary" style="width: 100%;">
						<span class="dashicons dashicons-filter"></span> <?php esc_html_e( 'Raporu Filtrele', 'hbt-trendyol-profit-tracker' ); ?>
					</button>
				</div>

			</div>
		</form>
	</div>

	<div class="hbt-tabs" style="margin-bottom: 24px; border-bottom: 2px solid var(--hbt-border);">
		<button class="hbt-tab active" data-tab="summary" style="margin-bottom: -2px;"><span class="dashicons dashicons-dashboard" style="margin-top:2px;"></span> <?php esc_html_e( 'Genel Özet', 'hbt-trendyol-profit-tracker' ); ?></button>
		<button class="hbt-tab" data-tab="products" style="margin-bottom: -2px;"><span class="dashicons dashicons-products" style="margin-top:2px;"></span> <?php esc_html_e( 'Ürün Bazlı', 'hbt-trendyol-profit-tracker' ); ?></button>
		<button class="hbt-tab" data-tab="categories" style="margin-bottom: -2px;"><span class="dashicons dashicons-category" style="margin-top:2px;"></span> <?php esc_html_e( 'Kategori Bazlı', 'hbt-trendyol-profit-tracker' ); ?></button>
		<button class="hbt-tab" data-tab="vat" style="margin-bottom: -2px;"><span class="dashicons dashicons-calculator" style="margin-top:2px;"></span> <?php esc_html_e( 'KDV Raporu', 'hbt-trendyol-profit-tracker' ); ?></button>
		<button class="hbt-tab" data-tab="returns" style="margin-bottom: -2px;"><span class="dashicons dashicons-update-undo" style="margin-top:2px;"></span> <?php esc_html_e( 'İade Raporu', 'hbt-trendyol-profit-tracker' ); ?></button>
		<button class="hbt-tab" data-tab="cancellations" style="margin-bottom: -2px;"><span class="dashicons dashicons-dismiss" style="margin-top:2px;"></span> <?php esc_html_e( 'İptal Raporu', 'hbt-trendyol-profit-tracker' ); ?></button>
	</div>

	<div class="hbt-tab-content active" id="tab-summary">
		
		<h3 class="hbt-widget-title" style="margin-top: 0;"><span class="dashicons dashicons-money-alt"></span> <?php esc_html_e( 'Finansal Performans', 'hbt-trendyol-profit-tracker' ); ?></h3>
		
		<div class="hbt-kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); margin-bottom: 24px;">
			<div class="hbt-card hbt-card-compact" style="border-bottom: 3px solid var(--hbt-secondary);">
				<span class="hbt-card-label"><span class="dashicons dashicons-chart-bar"></span> <?php esc_html_e( 'Toplam Ciro (TL)', 'hbt-trendyol-profit-tracker' ); ?></span>
				<span class="hbt-card-value" style="color: var(--hbt-primary); font-size: 28px;">
					<?php echo esc_html( number_format( $sales, 2 ) ); ?> ₺
				</span>
				<div style="font-size: 13px; margin-top: 8px; color: var(--hbt-text-muted);">
					Ortalama Sepet: <strong style="color:var(--hbt-secondary);"><?php echo number_format($avg_order_val, 2); ?> ₺</strong>
				</div>
			</div>
			
			<div class="hbt-card hbt-card-compact" style="border-bottom: 3px solid <?php echo $final_profit >= 0 ? 'var(--hbt-success)' : 'var(--hbt-danger)'; ?>; background: <?php echo $final_profit >= 0 ? '#F0FDF4' : '#FEF2F2'; ?>;">
				<span class="hbt-card-label" style="color: <?php echo $final_profit >= 0 ? 'var(--hbt-success)' : 'var(--hbt-danger)'; ?>;"><span class="dashicons dashicons-vault"></span> <?php esc_html_e( 'Gerçek Net Kâr / Zarar', 'hbt-trendyol-profit-tracker' ); ?></span>
				<span class="hbt-card-value" style="color: <?php echo $final_profit >= 0 ? 'var(--hbt-success)' : 'var(--hbt-danger)'; ?>; font-size: 28px;">
					<?php echo esc_html( number_format( $final_profit, 2 ) ); ?> ₺
				</span>
				<div style="font-size: 13px; margin-top: 8px; color: <?php echo $final_profit >= 0 ? '#166534' : '#991B1B'; ?>;">
					Tüm giderler (reklam vb.) düşüldü.
				</div>
			</div>

			<div class="hbt-card hbt-card-compact" style="border-bottom: 3px solid var(--hbt-info);">
				<span class="hbt-card-label"><span class="dashicons dashicons-chart-pie"></span> <?php esc_html_e( 'Gerçek Kâr Marjı', 'hbt-trendyol-profit-tracker' ); ?></span>
				<span class="hbt-card-value" style="color: var(--hbt-info); font-size: 28px;">
					%<?php echo esc_html( number_format( $gross_margin, 2 ) ); ?>
				</span>
				<div style="font-size: 13px; margin-top: 8px; color: var(--hbt-text-muted);">
					Her 100 ₺ satıştan kalan net oran
				</div>
			</div>
		</div>

		<h3 class="hbt-widget-title"><span class="dashicons dashicons-portfolio"></span> <?php esc_html_e( 'Operasyonel & Gider Özeti', 'hbt-trendyol-profit-tracker' ); ?></h3>
		
		<div class="hbt-kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 24px;">
			<div class="hbt-card hbt-card-compact">
				<span class="hbt-card-label"><?php esc_html_e( 'Toplam Sipariş', 'hbt-trendyol-profit-tracker' ); ?></span>
				<span class="hbt-card-value" style="font-size: 20px;"><?php echo esc_html( $orders_count ); ?> <span style="font-size:12px; font-weight:normal; color:var(--hbt-text-muted);">Adet</span></span>
			</div>
			<div class="hbt-card hbt-card-compact">
				<span class="hbt-card-label"><?php esc_html_e( 'Satılan Ürün', 'hbt-trendyol-profit-tracker' ); ?></span>
				<span class="hbt-card-value" style="font-size: 20px;"><?php echo esc_html( $total_items_sold ); ?> <span style="font-size:12px; font-weight:normal; color:var(--hbt-text-muted);">Adet</span></span>
			</div>
			<div class="hbt-card hbt-card-compact" style="border-left: 3px solid <?php echo $growth_color; ?>;">
				<span class="hbt-card-label"><?php esc_html_e( 'Son 7 Gün Trendi', 'hbt-trendyol-profit-tracker' ); ?></span>
				<span class="hbt-card-value" style="font-size: 20px; color: <?php echo $growth_color; ?>;"><?php echo esc_html( number_format( $cw_sales, 2 ) ); ?> ₺</span>
				<div style="font-size: 11px; margin-top: 4px; font-weight: 600; color: <?php echo $growth_color; ?>;"><?php echo $growth_icon; ?> %<?php echo esc_html( number_format( $growth_percent, 1 ) ); ?> (Geçen haftaya)</div>
			</div>
			
			<div class="hbt-card hbt-card-compact">
				<span class="hbt-card-label" style="color: #F59E0B;"><?php esc_html_e( 'Ürünlerin Maliyeti', 'hbt-trendyol-profit-tracker' ); ?></span>
				<span class="hbt-card-value" style="font-size: 20px; color: #D97706;">-<?php echo esc_html( number_format( (float) ( $summary->total_cost ?? 0 ), 2 ) ); ?> ₺</span>
			</div>
			<div class="hbt-card hbt-card-compact">
				<span class="hbt-card-label" style="color: #F59E0B;"><?php esc_html_e( 'Komisyon Gideri', 'hbt-trendyol-profit-tracker' ); ?></span>
				<span class="hbt-card-value" style="font-size: 20px; color: #D97706;">-<?php echo esc_html( number_format( (float) ( $summary->total_commission ?? 0 ), 2 ) ); ?> ₺</span>
			</div>
			<div class="hbt-card hbt-card-compact">
				<span class="hbt-card-label" style="color: #F59E0B;"><?php esc_html_e( 'Kargo Gideri', 'hbt-trendyol-profit-tracker' ); ?></span>
				<span class="hbt-card-value" style="font-size: 20px; color: #D97706;">-<?php echo esc_html( number_format( (float) ( $summary->total_shipping ?? 0 ), 2 ) ); ?> ₺</span>
			</div>
			<div class="hbt-card hbt-card-compact" style="background:#FFFBEB;">
				<span class="hbt-card-label" style="color: #B45309;"><?php esc_html_e( 'Reklam Giderleri', 'hbt-trendyol-profit-tracker' ); ?></span>
				<span class="hbt-card-value" style="font-size: 20px; color: #B45309;">-<?php echo esc_html( number_format( $total_ad_expense, 2 ) ); ?> ₺</span>
			</div>

			<div class="hbt-card hbt-card-compact" style="border-left: 3px solid #F59E0B;">
				<span class="hbt-card-label"><?php esc_html_e( 'İade Oranı', 'hbt-trendyol-profit-tracker' ); ?></span>
				<span class="hbt-card-value" style="font-size: 20px; color: var(--hbt-danger);">%<?php echo esc_html( number_format( $return_rate, 2 ) ); ?></span>
			</div>
			<div class="hbt-card hbt-card-compact" style="border-left: 3px solid var(--hbt-danger); background: var(--hbt-danger-bg);">
				<span class="hbt-card-label" style="color: var(--hbt-danger);"><?php esc_html_e( 'İptal Sipariş', 'hbt-trendyol-profit-tracker' ); ?></span>
				<span class="hbt-card-value" style="font-size: 20px; color: var(--hbt-danger);"><?php echo esc_html( (string) ( $cancel_summary->total_cancelled_orders ?? 0 ) ); ?> <span style="font-size:12px;">Adet</span></span>
			</div>
			<div class="hbt-card hbt-card-compact" style="border-left: 3px solid var(--hbt-danger); background: var(--hbt-danger-bg);">
				<span class="hbt-card-label" style="color: var(--hbt-danger);"><?php esc_html_e( 'İptalden Kaçan Ciro', 'hbt-trendyol-profit-tracker' ); ?></span>
				<span class="hbt-card-value" style="font-size: 20px; color: var(--hbt-danger);">-<?php echo esc_html( number_format( (float) ( $cancel_summary->total_cancelled_amount ?? 0 ), 2 ) ); ?> ₺</span>
			</div>

			<?php foreach ( $store_l30_profits as $st_id => $st_data ) : ?>
				<div class="hbt-card hbt-card-compact" style="border-left: 3px solid <?php echo $st_data['profit'] >= 0 ? 'var(--hbt-success)' : 'var(--hbt-danger)'; ?>; background: <?php echo $st_data['profit'] >= 0 ? '#F8FAFC' : 'var(--hbt-danger-bg)'; ?>;">
					<span class="hbt-card-label" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--hbt-primary);" title="<?php echo esc_attr( $st_data['name'] ); ?>"><?php echo esc_html( $st_data['name'] ); ?></span>
					<span class="hbt-card-value" style="font-size: 20px; color: <?php echo $st_data['profit'] >= 0 ? 'var(--hbt-success)' : 'var(--hbt-danger)'; ?>;">
						<?php echo esc_html( number_format( $st_data['profit'], 2 ) ); ?> ₺
					</span>
					<div style="font-size: 11px; margin-top: 4px; color: var(--hbt-text-muted);">Son 30 Gün Net Kâr</div>
				</div>
			<?php endforeach; ?>
		</div>

		<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; margin-bottom: 24px;">
			<div class="hbt-card" style="border: 1px solid #A7F3D0; background: #ECFDF5;">
				<h4 style="margin: 0 0 12px 0; color: #059669; font-size: 15px; display: flex; align-items: center; gap: 8px;"><span class="dashicons dashicons-awards"></span> En Çok Kâr Ettiren Ürün</h4>
				<?php if ( $best_product && $best_product->total_profit > 0 ) : ?>
					<p style="margin:0; font-weight:600; font-size:14px; color: var(--hbt-primary); line-height:1.4;"><?php echo esc_html( $best_product->product_name ); ?></p>
					<p style="margin:6px 0 0 0; font-size: 12px; color: var(--hbt-text-muted); font-family: monospace;">Barkod: <?php echo esc_html( $best_product->barcode ); ?></p>
					<div style="margin-top: 12px; font-size: 20px; font-weight: 700; color: #059669;">+<?php echo esc_html( number_format( $best_product->total_profit, 2 ) ); ?> ₺ <span style="font-size: 12px; font-weight: 500;">Brüt Kâr</span></div>
				<?php else: ?>
					<p style="margin:0; font-size:13px; color: var(--hbt-text-muted);">Yeterli veri yok.</p>
				<?php endif; ?>
			</div>

			<div class="hbt-card" style="border: 1px solid #FECACA; background: #FEF2F2;">
				<h4 style="margin: 0 0 12px 0; color: #DC2626; font-size: 15px; display: flex; align-items: center; gap: 8px;"><span class="dashicons dashicons-warning"></span> En Az Kâr Ettiren / Zararlı Ürün</h4>
				<?php if ( $worst_product ) : ?>
					<p style="margin:0; font-weight:600; font-size:14px; color: var(--hbt-primary); line-height:1.4;"><?php echo esc_html( $worst_product->product_name ); ?></p>
					<p style="margin:6px 0 0 0; font-size: 12px; color: var(--hbt-text-muted); font-family: monospace;">Barkod: <?php echo esc_html( $worst_product->barcode ); ?></p>
					<div style="margin-top: 12px; font-size: 20px; font-weight: 700; color: <?php echo $worst_product->total_profit < 0 ? '#DC2626' : '#D97706'; ?>;"><?php echo esc_html( number_format( $worst_product->total_profit, 2 ) ); ?> ₺ <span style="font-size: 12px; font-weight: 500;">Brüt Kâr</span></div>
				<?php else: ?>
					<p style="margin:0; font-size:13px; color: var(--hbt-text-muted);">Yeterli veri yok.</p>
				<?php endif; ?>
			</div>
		</div>

		<h3 class="hbt-widget-title"><span class="dashicons dashicons-chart-area"></span> <?php esc_html_e( 'Gelir / Gider Grafiği', 'hbt-trendyol-profit-tracker' ); ?></h3>
		<div class="hbt-card" style="padding: 20px;">
			<div class="hbt-chart-box" style="position: relative; height: 350px; width: 100%;">
				<canvas id="chart-summary"></canvas>
			</div>
		</div>

		<script>
		document.addEventListener('DOMContentLoaded', function() {
			var ctx = document.getElementById('chart-summary');
			if (ctx && typeof Chart !== 'undefined') {
				new Chart(ctx, {
					type: 'bar',
					data: {
						labels: ['Toplam Ciro', 'Ürün Maliyeti', 'Komisyon Gideri', 'Kargo Gideri', 'Reklam Gideri', 'Gerçek Net Kâr'],
						datasets: [{
							label: 'Tutar (TL)',
							data: [
								<?php echo number_format($sales, 2, '.', ''); ?>,
								<?php echo number_format($summary->total_cost ?? 0, 2, '.', ''); ?>,
								<?php echo number_format($summary->total_commission ?? 0, 2, '.', ''); ?>,
								<?php echo number_format($summary->total_shipping ?? 0, 2, '.', ''); ?>,
								<?php echo number_format($total_ad_expense, 2, '.', ''); ?>,
								<?php echo number_format($final_profit, 2, '.', ''); ?>
							],
							backgroundColor: [
								'#0EA5E9', // Ciro
								'#F59E0B', // Maliyet
								'#F97316', // Komisyon
								'#EF4444', // Kargo
								'#B45309', // Reklam Gideri
								'<?php echo $final_profit >= 0 ? "#10B981" : "#EF4444"; ?>' // Net Kâr
							],
							borderRadius: 6
						}]
					},
					options: {
						responsive: true,
						maintainAspectRatio: false,
						plugins: {
							legend: { display: false }
						},
						scales: {
							y: { beginAtZero: true }
						}
					}
				});
			}
		});
		</script>
	</div>

	<div class="hbt-tab-content" id="tab-products" style="display:none;">
		<div class="hbt-card" style="padding: 0; overflow: hidden;">
			<table class="wp-list-table widefat fixed striped hbt-datatable" style="border: none; margin: 0; width: 100%;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Barkod', 'hbt-trendyol-profit-tracker' ); ?></th>
						<th><?php esc_html_e( 'Ürün Adı', 'hbt-trendyol-profit-tracker' ); ?></th>
						<th><?php esc_html_e( 'Toplam Satış (TL)', 'hbt-trendyol-profit-tracker' ); ?></th>
						<th><?php esc_html_e( 'Brüt Kâr (TL)', 'hbt-trendyol-profit-tracker' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $product_stats as $ps ) : ?>
						<tr>
							<td style="font-family: monospace;"><?php echo esc_html( $ps->barcode ); ?></td>
							<td style="font-weight: 500;"><?php echo esc_html( $ps->product_name ); ?></td>
							<td style="font-weight: 600; color: var(--hbt-primary);"><?php echo esc_html( number_format( (float) $ps->total_sales, 2 ) ); ?> ₺</td>
							<td>
								<span class="hbt-badge" style="background: <?php echo (float) $ps->total_profit >= 0 ? 'var(--hbt-success)' : 'var(--hbt-danger)'; ?>;">
									<?php echo esc_html( number_format( (float) $ps->total_profit, 2 ) ); ?> ₺
								</span>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>

	<div class="hbt-tab-content" id="tab-categories" style="display:none;">
		<div class="hbt-card" style="padding: 0; overflow: hidden;">
			<table class="wp-list-table widefat fixed striped hbt-datatable" style="border: none; margin: 0; width: 100%;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Kategori', 'hbt-trendyol-profit-tracker' ); ?></th>
						<th><?php esc_html_e( 'Brüt Kâr (TL)', 'hbt-trendyol-profit-tracker' ); ?></th>
						<th><?php esc_html_e( 'Ort. Kâr Marjı (%)', 'hbt-trendyol-profit-tracker' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $category_stats as $cs ) : ?>
						<tr>
							<td style="font-weight: 500;"><?php echo esc_html( $cs->category_name ); ?></td>
							<td>
								<span class="hbt-badge" style="background: <?php echo (float) $cs->total_profit >= 0 ? 'var(--hbt-success)' : 'var(--hbt-danger)'; ?>;">
									<?php echo esc_html( number_format( (float) $cs->total_profit, 2 ) ); ?> ₺
								</span>
							</td>
							<td style="font-weight: 600; color: var(--hbt-info);"><?php echo esc_html( number_format( (float) $cs->avg_margin, 2 ) ); ?>%</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>

	<div class="hbt-tab-content" id="tab-vat" style="display:none;">
		<div class="hbt-kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">
			<div class="hbt-card hbt-card-compact" style="border-bottom: 3px solid var(--hbt-primary);">
				<span class="hbt-card-label"><span class="dashicons dashicons-book-alt"></span> <?php esc_html_e( 'Toplam KDV Tutarı (TL)', 'hbt-trendyol-profit-tracker' ); ?></span>
				<span class="hbt-card-value" style="color: var(--hbt-primary); font-size: 28px;">
					<?php echo esc_html( number_format( (float) ( $summary->total_vat ?? 0 ), 2 ) ); ?> ₺
				</span>
				<div style="font-size: 13px; margin-top: 8px; color: var(--hbt-text-muted);">Devlete ödenecek yaklaşık rakam</div>
			</div>
			<div class="hbt-card hbt-card-compact" style="border-bottom: 3px solid var(--hbt-secondary);">
				<span class="hbt-card-label"><span class="dashicons dashicons-cart"></span> <?php esc_html_e( 'KDV Dahil Satış Tutarı (TL)', 'hbt-trendyol-profit-tracker' ); ?></span>
				<span class="hbt-card-value" style="color: var(--hbt-secondary); font-size: 28px;">
					<?php echo esc_html( number_format( (float) ( $summary->total_sales ?? 0 ), 2 ) ); ?> ₺
				</span>
			</div>
		</div>
	</div>

	<div class="hbt-tab-content" id="tab-returns" style="display:none;">
		<?php $return_stats = ( new HBT_Return_Manager() )->get_return_stats( $store_id, $date_from, $date_to ); ?>
		
		<h3 class="hbt-widget-title"><span class="dashicons dashicons-update-undo"></span> <?php esc_html_e( 'İade Özeti', 'hbt-trendyol-profit-tracker' ); ?></h3>
		<div class="hbt-kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); margin-bottom: 24px;">
			<div class="hbt-card hbt-card-compact">
				<span class="hbt-card-label"><?php esc_html_e( 'İade Edilen Ürün Adedi', 'hbt-trendyol-profit-tracker' ); ?></span>
				<span class="hbt-card-value" style="font-size: 24px; color: var(--hbt-primary);"><?php echo esc_html( (string) $return_stats['total_returns'] ); ?> <span style="font-size: 13px; font-weight: normal; color: var(--hbt-text-muted);">Adet</span></span>
			</div>
			<div class="hbt-card hbt-card-compact" style="border-bottom: 3px solid var(--hbt-danger); background: var(--hbt-danger-bg);">
				<span class="hbt-card-label" style="color: var(--hbt-danger);"><?php esc_html_e( 'İadelerden Doğan Zarar (TL)', 'hbt-trendyol-profit-tracker' ); ?></span>
				<span class="hbt-card-value" style="font-size: 24px; color: var(--hbt-danger);">-<?php echo esc_html( number_format( $return_stats['total_loss'], 2 ) ); ?> ₺</span>
			</div>
			<div class="hbt-card hbt-card-compact">
				<span class="hbt-card-label"><?php esc_html_e( 'Satışa Oranla İade', 'hbt-trendyol-profit-tracker' ); ?></span>
				<span class="hbt-card-value" style="font-size: 24px; color: var(--hbt-info);">%<?php echo esc_html( number_format( $return_stats['return_rate'], 2 ) ); ?></span>
			</div>
		</div>

		<h3 class="hbt-widget-title"><span class="dashicons dashicons-warning"></span> <?php esc_html_e( 'En Çok İade Edilen Ürünler', 'hbt-trendyol-profit-tracker' ); ?></h3>
		<div class="hbt-card" style="padding: 0; overflow: hidden;">
			<?php if ( empty( $return_stats['top_returned'] ) ) : ?>
				<div style="padding: 40px; text-align: center; color: var(--hbt-text-muted);">
					<span class="dashicons dashicons-smiley" style="font-size: 32px; width: 32px; height: 32px; margin-bottom: 10px;"></span><br>
					<?php esc_html_e( 'Bu tarih aralığında kaydedilmiş bir ürün iadesi bulunmamaktadır.', 'hbt-trendyol-profit-tracker' ); ?>
				</div>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped" style="border: none; margin: 0; width: 100%;">
					<thead>
						<tr>
							<th style="width: 25%;"><?php esc_html_e( 'Barkod', 'hbt-trendyol-profit-tracker' ); ?></th>
							<th style="width: 50%;"><?php esc_html_e( 'Ürün Adı', 'hbt-trendyol-profit-tracker' ); ?></th>
							<th style="width: 25%;"><?php esc_html_e( 'İade Adedi', 'hbt-trendyol-profit-tracker' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $return_stats['top_returned'] as $item ) : ?>
							<tr>
								<td style="font-family: monospace;"><?php echo esc_html( $item->barcode ); ?></td>
								<td style="font-weight: 500;"><?php echo esc_html( $item->product_name ); ?></td>
								<td><span class="hbt-badge-zarar"><?php echo esc_html( $item->total_qty ); ?> Adet</span></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>

	<div class="hbt-tab-content" id="tab-cancellations" style="display:none;">
		<h3 class="hbt-widget-title"><span class="dashicons dashicons-dismiss"></span> <?php esc_html_e( 'İptal Özeti', 'hbt-trendyol-profit-tracker' ); ?></h3>
		<div class="hbt-kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); margin-bottom: 24px;">
			<div class="hbt-card hbt-card-compact" style="border-bottom: 3px solid var(--hbt-danger); background: var(--hbt-danger-bg);">
				<span class="hbt-card-label" style="color: var(--hbt-danger);"><?php esc_html_e( 'Toplam İptal Edilen Sipariş', 'hbt-trendyol-profit-tracker' ); ?></span>
				<span class="hbt-card-value" style="font-size: 28px; color: var(--hbt-danger);">
					<?php echo esc_html( (string) ( $cancel_summary->total_cancelled_orders ?? 0 ) ); ?> <span style="font-size: 14px; font-weight: normal;">Adet</span>
				</span>
			</div>
			<div class="hbt-card hbt-card-compact" style="border-bottom: 3px solid var(--hbt-danger); background: var(--hbt-danger-bg);">
				<span class="hbt-card-label" style="color: var(--hbt-danger);"><?php esc_html_e( 'Kaçan Ciro (İptal Tutarı TL)', 'hbt-trendyol-profit-tracker' ); ?></span>
				<span class="hbt-card-value" style="font-size: 28px; color: var(--hbt-danger);">
					-<?php echo esc_html( number_format( (float) ( $cancel_summary->total_cancelled_amount ?? 0 ), 2 ) ); ?> ₺
				</span>
			</div>
		</div>

		<h3 class="hbt-widget-title"><span class="dashicons dashicons-warning"></span> <?php esc_html_e( 'En Çok İptal Edilen Ürünler', 'hbt-trendyol-profit-tracker' ); ?></h3>
		<div class="hbt-card" style="padding: 0; overflow: hidden;">
			<?php if ( empty( $top_cancelled ) ) : ?>
				<div style="padding: 40px; text-align: center; color: var(--hbt-text-muted);">
					<span class="dashicons dashicons-smiley" style="font-size: 32px; width: 32px; height: 32px; margin-bottom: 10px;"></span><br>
					<?php esc_html_e( 'Bu tarih aralığında iptal edilen ürün bulunmamaktadır. Harika!', 'hbt-trendyol-profit-tracker' ); ?>
				</div>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped" style="border: none; margin: 0; width: 100%;">
					<thead>
						<tr>
							<th style="width: 25%;"><?php esc_html_e( 'Barkod', 'hbt-trendyol-profit-tracker' ); ?></th>
							<th style="width: 50%;"><?php esc_html_e( 'Ürün Adı', 'hbt-trendyol-profit-tracker' ); ?></th>
							<th style="width: 25%;"><?php esc_html_e( 'İptal Adedi', 'hbt-trendyol-profit-tracker' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $top_cancelled as $item ) : ?>
							<tr>
								<td style="font-family: monospace;"><?php echo esc_html( $item->barcode ); ?></td>
								<td style="font-weight: 500;"><?php echo esc_html( $item->product_name ); ?></td>
								<td><span class="hbt-badge-zarar"><?php echo esc_html( $item->total_qty ); ?> Adet</span></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>

	<input type="hidden" id="filter-store-id" value="<?php echo esc_attr( (string) $store_id ); ?>">
	<input type="hidden" id="filter-date-from" value="<?php echo esc_attr( $date_from ); ?>">
	<input type="hidden" id="filter-date-to" value="<?php echo esc_attr( $date_to ); ?>">
	<input type="hidden" id="filter-status" value="">
</div>

<style>
/* Tablo DataTables / Arayüz Düzeltmeleri */
.dataTables_wrapper .dataTables_length { padding: 20px 0 10px 24px; color: var(--hbt-text-main); }
.dataTables_wrapper .dataTables_filter { padding: 20px 24px 10px 0; color: var(--hbt-text-main); }
.dataTables_wrapper .dataTables_info { padding: 20px 0 24px 24px; color: var(--hbt-text-muted) !important; font-size: 13px; }
.dataTables_wrapper .dataTables_paginate { padding: 15px 24px 24px 0; }

.dataTables_wrapper select {
    padding: 4px 28px 4px 12px !important; border-radius: 6px !important; border: 1px solid var(--hbt-border) !important;
    background-position: right 8px center !important; width: auto !important; min-width: 65px;
    -webkit-appearance: none; -moz-appearance: none; appearance: none;
    background-image: url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important; background-size: 14px !important;
}

.dataTables_wrapper .dataTables_filter input {
    border: 1px solid var(--hbt-border) !important; border-radius: var(--hbt-radius-sm) !important;
    padding: 6px 12px !important; margin-left: 8px !important; outline: none;
}
.dataTables_wrapper .dataTables_filter input:focus {
    border-color: var(--hbt-secondary) !important; box-shadow: 0 0 0 1px var(--hbt-secondary) !important;
}

.hbt-wrap table.wp-list-table thead th:first-child, .hbt-wrap table.wp-list-table tbody td:first-child { padding-left: 24px !important; }
.hbt-wrap table.wp-list-table thead th:last-child, .hbt-wrap table.wp-list-table tbody td:last-child { padding-right: 24px !important; }
</style>