<?php
/**
 * Returns view.
 *
 * @package HBT_Trendyol_Profit_Tracker
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;
$db     = HBT_Database::instance();
$stores = $db->get_stores();

// --- YENİ: AKILLI OTOMATİK İADE SENKRONİZASYONU ---
$missing_returns = $wpdb->get_results( "
	SELECT o.id as order_id, o.store_id, o.order_number, o.order_date, 
	       oi.barcode, oi.product_name, oi.quantity, oi.line_total, oi.cost_usd, oi.cost_tl, oi.shipping_cost 
	FROM {$wpdb->prefix}hbt_orders o 
	INNER JOIN {$wpdb->prefix}hbt_order_items oi ON o.id = oi.order_id 
	LEFT JOIN {$wpdb->prefix}hbt_returns r ON r.order_id = o.id AND r.barcode = oi.barcode
	WHERE o.status = 'Returned' AND r.id IS NULL
" );

if ( ! empty( $missing_returns ) ) {
	foreach ( $missing_returns as $miss ) {
		$wpdb->insert(
			$wpdb->prefix . 'hbt_returns',
			array(
				'store_id'         => $miss->store_id,
				'order_id'         => $miss->order_id,
				'order_number'     => $miss->order_number,
				'return_date'      => $miss->order_date, 
				'barcode'          => $miss->barcode,
				'product_name'     => $miss->product_name,
				'quantity'         => $miss->quantity,
				'return_reason'    => 'Müşteri İadesi',
				'return_type'      => 'customer',
				'refund_amount'    => $miss->line_total,
				'cost_usd'         => $miss->cost_usd,
				'cost_tl'          => $miss->cost_tl,
				'shipping_cost'    => (float) $miss->shipping_cost,
				'net_loss'         => 0, // Artık kullanılmıyor
				'status'           => 'pending',
				'product_reusable' => 0
			)
		);
	}
}
// ----------------------------------------------------

$store_id  = absint( $_GET['store_id'] ?? 0 );
$date_from = sanitize_text_field( $_GET['date_from'] ?? '' );
$date_to   = sanitize_text_field( $_GET['date_to'] ?? '' );

$returns = $db->get_returns(
	array_filter(
		array(
			'store_id'  => $store_id ?: null,
			'date_from' => $date_from ?: null,
			'date_to'   => $date_to ?: null,
		)
	)
);

$total_shipping_loss = 0;
if ( ! empty( $returns ) ) {
	foreach ( $returns as $rtn ) {
		$item_shipping_cost = (float) $wpdb->get_var( $wpdb->prepare(
			"SELECT shipping_cost FROM {$wpdb->prefix}hbt_order_items WHERE order_id = %d AND barcode = %s LIMIT 1",
			$rtn->order_id,
			$rtn->barcode
		) );
		
		if ( $item_shipping_cost == 0 ) {
			$item_shipping_cost = (float) $wpdb->get_var( $wpdb->prepare(
				"SELECT total_shipping FROM {$wpdb->prefix}hbt_orders WHERE id = %d",
				$rtn->order_id
			) );
		}

		$rtn->calculated_shipping_loss = $item_shipping_cost * 2;
		$total_shipping_loss += $rtn->calculated_shipping_loss;
	}
}

$return_manager = new HBT_Return_Manager();
$stats          = array( 'total_returns' => 0, 'total_refund' => 0, 'return_rate' => 0, 'top_returned' => array() );
if ( $date_from && $date_to ) {
	$stats = $return_manager->get_return_stats( $store_id, $date_from, $date_to );
} else {
	$default_start = gmdate('Y-m-d', strtotime('-90 days'));
	$default_end   = gmdate('Y-m-d');
	$stats = $return_manager->get_return_stats( $store_id, $default_start, $default_end );
}
?>
<div class="wrap hbt-tpt-wrap">
	
	<div class="hbt-page-header">
		<h1 class="hbt-page-title">
			<span class="dashicons dashicons-controls-skipback"></span> 
			<?php esc_html_e( 'İadeler ve Kayıp Analizi', 'hbt-trendyol-profit-tracker' ); ?>
		</h1>
	</div>

	<div class="hbt-alert-box hbt-alert-info" style="margin-bottom: 24px;">
		<span class="dashicons dashicons-info"></span> 
		<div><?php esc_html_e( 'Mağazalarınıza gelen iadelerin detaylarını, ciro kaybınızı ve iade dönüşlerinden kaynaklı kargo zararlarınızı buradan takip edebilirsiniz.', 'hbt-trendyol-profit-tracker' ); ?></div>
	</div>

	<div class="hbt-kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
		<div class="hbt-card hbt-card-compact" style="border-bottom: 3px solid var(--hbt-text-muted);">
			<span class="hbt-card-label"><span class="dashicons dashicons-update-undo"></span> <?php esc_html_e( 'Toplam İade Adedi', 'hbt-trendyol-profit-tracker' ); ?></span>
			<span class="hbt-card-value" style="color: var(--hbt-primary);">
				<?php echo esc_html( (string) $stats['total_returns'] ); ?> <span style="font-size:14px; font-weight:600; color: var(--hbt-text-muted);">Adet</span>
			</span>
		</div>
		<div class="hbt-card hbt-card-compact" style="border-bottom: 3px solid var(--hbt-danger); background: var(--hbt-danger-bg);">
			<span class="hbt-card-label" style="color: var(--hbt-danger);"><span class="dashicons dashicons-chart-line" style="transform: scaleY(-1);"></span> <?php esc_html_e( 'Ciro Kaybı', 'hbt-trendyol-profit-tracker' ); ?></span>
			<span class="hbt-card-value" style="color: var(--hbt-danger);">
				<?php echo esc_html( number_format( $stats['total_refund'], 2 ) ); ?> ₺
			</span>
		</div>
		<div class="hbt-card hbt-card-compact" style="border-bottom: 3px solid #F59E0B; background: #FFFBEB;">
			<span class="hbt-card-label" style="color: #D97706;"><span class="dashicons dashicons-car"></span> <?php esc_html_e( 'Toplam Kargo Zararı', 'hbt-trendyol-profit-tracker' ); ?></span>
			<span class="hbt-card-value" style="color: #D97706;">
				-<?php echo esc_html( number_format( $total_shipping_loss, 2 ) ); ?> ₺
			</span>
		</div>
		<div class="hbt-card hbt-card-compact" style="border-bottom: 3px solid var(--hbt-info); background: #EFF6FF;">
			<span class="hbt-card-label" style="color: var(--hbt-info);"><span class="dashicons dashicons-chart-pie"></span> <?php esc_html_e( 'Satışa Oranla İade', 'hbt-trendyol-profit-tracker' ); ?></span>
			<span class="hbt-card-value" style="color: var(--hbt-info);">
				%<?php echo esc_html( number_format( $stats['return_rate'], 2 ) ); ?>
			</span>
		</div>
	</div>

	<?php if ( ! empty( $stats['top_returned'] ) ) : ?>
	<div class="hbt-card" style="margin-bottom: 24px;">
		<h3 class="hbt-widget-title" style="margin-top: 0;"><span class="dashicons dashicons-warning"></span> <?php esc_html_e( 'En Çok İade Gelen 5 Ürün', 'hbt-trendyol-profit-tracker' ); ?></h3>
		<ul class="hbt-top-products-list" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px;">
			<?php 
			$count = 0;
			foreach ( $stats['top_returned'] as $top_item ) : 
				if ( $count >= 5 ) break; 
			?>
				<li style="border: 1px solid var(--hbt-border); border-radius: 8px; padding: 12px 16px; margin: 0; background: var(--hbt-bg-color);">
					<div class="hbt-tp-name" title="<?php echo esc_attr( $top_item->product_name ); ?>"><?php echo esc_html( $top_item->product_name ); ?></div>
					<div style="color: var(--hbt-danger); background: var(--hbt-danger-bg); padding: 4px 10px; border-radius: 20px; font-size: 13px; font-weight: 700;">
						<?php echo esc_html( $top_item->total_qty ); ?> <?php esc_html_e( 'Adet', 'hbt-trendyol-profit-tracker' ); ?>
					</div>
				</li>
			<?php 
				$count++;
			endforeach; 
			?>
		</ul>
	</div>
	<?php endif; ?>

	<div class="hbt-card" style="margin-bottom: 24px;">
		<h3 class="hbt-widget-title" style="margin-top: 0;"><span class="dashicons dashicons-filter"></span> İade Filtrele</h3>
		<form method="get" action="">
			<input type="hidden" name="page" value="hbt-tpt-returns">
			
			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px;">
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
			</div>

			<div style="border-top: 1px solid var(--hbt-border); padding-top: 16px; display: flex; gap: 8px;">
				<button type="submit" class="hbt-btn hbt-btn-primary">
					<span class="dashicons dashicons-search"></span> <?php esc_html_e( 'Sonuçları Getir', 'hbt-trendyol-profit-tracker' ); ?>
				</button>
				<a href="?page=hbt-tpt-returns" class="hbt-btn hbt-btn-outline">
					<span class="dashicons dashicons-update-alt"></span> Temizle
				</a>
			</div>
		</form>
	</div>

	<div class="hbt-card" style="padding: 0; overflow: hidden; margin-bottom: 24px;">
		<table class="wp-list-table widefat fixed striped" id="returns-table" style="width:100%; border: none; margin: 0;">
			<thead>
				<tr>
					<th style="width:15%;"><?php esc_html_e( 'Sipariş No', 'hbt-trendyol-profit-tracker' ); ?></th>
					<th style="width:12%;"><?php esc_html_e( 'Sipariş Tarihi', 'hbt-trendyol-profit-tracker' ); ?></th>
					<th style="width:35%;"><?php esc_html_e( 'Ürün', 'hbt-trendyol-profit-tracker' ); ?></th>
					<th style="width:8%;"><?php esc_html_e( 'Adet', 'hbt-trendyol-profit-tracker' ); ?></th>
					<th style="width:12%;"><?php esc_html_e( 'İade Nedeni', 'hbt-trendyol-profit-tracker' ); ?></th>
					<th style="width:9%;"><?php esc_html_e( 'İade Tutarı', 'hbt-trendyol-profit-tracker' ); ?></th>
					<th style="width:9%;"><?php esc_html_e( 'Kargo Kaybı', 'hbt-trendyol-profit-tracker' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! empty( $returns ) ) : 
					foreach ( $returns as $return ) : 
				?>
						<tr data-id="<?php echo esc_attr( (string) $return->id ); ?>">
							<td><strong style="color: var(--hbt-primary);"><?php echo esc_html( $return->order_number ); ?></strong></td>
							<td style="color: var(--hbt-text-muted); font-size: 13px;"><?php echo esc_html( wp_date( 'd.m.Y', strtotime($return->return_date) ) ); ?></td>
							<td style="font-weight: 500;"><?php echo esc_html( $return->product_name ); ?></td>
							<td>
								<span style="background: var(--hbt-bg-color); color: var(--hbt-primary); padding: 4px 8px; border-radius: 4px; font-weight: 600; border: 1px solid var(--hbt-border);">
									<?php echo esc_html( (string) $return->quantity ); ?>
								</span>
							</td>
							<td style="font-size:12px; color: var(--hbt-text-muted);"><?php echo esc_html( $return->return_reason ); ?></td>
							<td style="color: var(--hbt-danger); font-weight: 600;">
								<?php echo esc_html( number_format( (float) $return->refund_amount, 2 ) ); ?> ₺
							</td>
							<td>
								<span class="hbt-badge-zarar" style="margin-right:0;">
									-<?php echo esc_html( number_format( $return->calculated_shipping_loss, 2 ) ); ?> ₺
								</span>
							</td>
						</tr>
				<?php 
					endforeach; 
				endif; 
				?>
			</tbody>
		</table>
	</div>
</div>

<style>
/* DataTables Arayüz Hizalama Düzeltmeleri */
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

<script>
jQuery(document).ready(function($) {
    if ($.fn.DataTable) {
        $('#returns-table').DataTable({
            "destroy": true, 
            "order": [[ 1, "desc" ]], // Tarihe göre azalan sıralama
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/tr.json", 
                "emptyTable": "Seçilen kriterlere uygun iade kaydı bulunamadı." 
            },
            "pageLength": 25,
            "lengthMenu": [ [25, 50, 100, -1], [25, 50, 100, "Tümü"] ]
        });
    }
});
</script>