<?php
/**
 * Fixed Costs view.
 *
 * @package HBT_Trendyol_Profit_Tracker
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$stores = HBT_Database::instance()->get_stores();
$fixed_costs = get_option('hbt_fixed_costs', array());
?>
<div class="wrap hbt-tpt-wrap">
	
	<div class="hbt-page-header">
		<h1 class="hbt-page-title">
			<span class="dashicons dashicons-portfolio"></span> 
			<?php esc_html_e( 'Sabit Giderler', 'hbt-trendyol-profit-tracker' ); ?>
		</h1>
	</div>

	<div class="hbt-alert-box hbt-alert-info" style="margin-bottom: 24px;">
		<span class="dashicons dashicons-info"></span> 
		<div><?php esc_html_e( 'Her mağaza için', 'hbt-trendyol-profit-tracker' ); ?> <strong><?php esc_html_e( 'sipariş başına', 'hbt-trendyol-profit-tracker' ); ?></strong> <?php esc_html_e( 'hesaplanacak sabit giderleri (TL) belirleyin. Boş bırakılan veya 0 girilen alanlar hesaplamaya dahil edilmez. Bu giderler, her yeni sipariş çekildiğinde net kârdan otomatik düşülür.', 'hbt-trendyol-profit-tracker' ); ?></div>
	</div>

	<div class="hbt-card" style="padding: 0; overflow: hidden; margin-bottom: 24px;">
		<table class="wp-list-table widefat fixed striped" style="border: none; margin: 0;">
			<thead>
				<tr>
					<th style="width: 25%;"><?php esc_html_e( 'Mağaza Adı', 'hbt-trendyol-profit-tracker' ); ?></th>
					<th style="width: 25%;"><?php esc_html_e( 'Personel Gideri', 'hbt-trendyol-profit-tracker' ); ?></th>
					<th style="width: 25%;"><?php esc_html_e( 'Paketleme Gideri', 'hbt-trendyol-profit-tracker' ); ?></th>
					<th style="width: 25%;"><?php esc_html_e( 'Diğer Sabit Gider', 'hbt-trendyol-profit-tracker' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty($stores) ) : ?>
					<tr>
						<td colspan="4" style="text-align: center; padding: 40px 20px; color: var(--hbt-text-muted);">
							<span class="dashicons dashicons-store" style="font-size: 32px; width: 32px; height: 32px; margin-bottom: 10px; opacity: 0.5;"></span><br>
							<?php esc_html_e( 'Henüz mağaza eklenmemiş.', 'hbt-trendyol-profit-tracker' ); ?>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $stores as $store ) : 
						$fc = $fixed_costs[$store->id] ?? array('personnel'=>0, 'packaging'=>0, 'other'=>0);
					?>
					<tr data-store-id="<?php echo esc_attr($store->id); ?>" class="fixed-cost-row">
						<td style="vertical-align: middle;">
							<strong style="color: var(--hbt-primary); display: flex; align-items: center; gap: 8px;">
								<span class="dashicons dashicons-store" style="color: var(--hbt-text-muted);"></span>
								<?php echo esc_html($store->store_name); ?>
							</strong>
						</td>
						<td style="vertical-align: middle;">
							<div style="position: relative; max-width: 200px;">
								<span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--hbt-text-muted); font-weight: 600;">₺</span>
								<input type="number" step="0.01" min="0" class="fc-personnel" value="<?php echo esc_attr($fc['personnel'] > 0 ? $fc['personnel'] : ''); ?>" placeholder="0.00" style="width: 100%; padding: 8px 12px 8px 28px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border); font-weight: 500;">
							</div>
						</td>
						<td style="vertical-align: middle;">
							<div style="position: relative; max-width: 200px;">
								<span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--hbt-text-muted); font-weight: 600;">₺</span>
								<input type="number" step="0.01" min="0" class="fc-packaging" value="<?php echo esc_attr($fc['packaging'] > 0 ? $fc['packaging'] : ''); ?>" placeholder="0.00" style="width: 100%; padding: 8px 12px 8px 28px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border); font-weight: 500;">
							</div>
						</td>
						<td style="vertical-align: middle;">
							<div style="position: relative; max-width: 200px;">
								<span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--hbt-text-muted); font-weight: 600;">₺</span>
								<input type="number" step="0.01" min="0" class="fc-other" value="<?php echo esc_attr($fc['other'] > 0 ? $fc['other'] : ''); ?>" placeholder="0.00" style="width: 100%; padding: 8px 12px 8px 28px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border); font-weight: 500;">
							</div>
						</td>
					</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		
		<?php if ( ! empty($stores) ) : ?>
		<div style="padding: 16px 24px; border-top: 1px solid var(--hbt-border); display: flex; justify-content: flex-end; align-items: center; background: #F8FAFC; border-radius: 0 0 var(--hbt-radius) var(--hbt-radius);">
			<button type="button" class="button button-primary btn-save-fixed-costs hbt-btn hbt-btn-primary">
				<span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Giderleri Kaydet', 'hbt-trendyol-profit-tracker' ); ?>
			</button>
		</div>
		<?php endif; ?>
	</div>

</div>

<style>
/* İnput Odaklanma (Focus) Efektleri */
.fixed-cost-row input[type="number"]:focus {
    border-color: var(--hbt-secondary) !important;
    box-shadow: 0 0 0 1px var(--hbt-secondary) !important;
    outline: none;
}

/* Number input oklarını hafifçe gizle / düzelt */
.fixed-cost-row input[type=number]::-webkit-inner-spin-button, 
.fixed-cost-row input[type=number]::-webkit-outer-spin-button { 
  opacity: 0.5;
}

/* Tablo içindeki paddingleri biraz dengeleyelim */
.hbt-wrap table.wp-list-table thead th:first-child,
.hbt-wrap table.wp-list-table tbody td:first-child {
    padding-left: 24px !important;
}
.hbt-wrap table.wp-list-table thead th:last-child,
.hbt-wrap table.wp-list-table tbody td:last-child {
    padding-right: 24px !important;
}
</style>