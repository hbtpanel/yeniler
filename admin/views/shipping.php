<?php
/**
 * Shipping view.
 *
 * @package HBT_Trendyol_Profit_Tracker
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$stores = HBT_Database::instance()->get_stores();
$shipping_costs = HBT_Database::instance()->get_shipping_costs();
?>
<div class="wrap hbt-tpt-wrap">
	
	<div class="hbt-page-header">
		<h1 class="hbt-page-title">
			<span class="dashicons dashicons-car"></span> 
			<?php esc_html_e( 'Kargo Fiyatları', 'hbt-trendyol-profit-tracker' ); ?>
		</h1>
		<div class="hbt-header-actions">
			<button type="button" id="btn-add-shipping" class="hbt-btn hbt-btn-primary" style="margin: 0;">
				<span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Kargo Fiyatı Ekle', 'hbt-trendyol-profit-tracker' ); ?>
			</button>
		</div>
	</div>

	<div class="hbt-alert-box hbt-alert-info" style="margin-bottom: 24px;">
		<span class="dashicons dashicons-info"></span> 
		<div>Trendyol sipariş tutarına veya desiye göre değişen kargo kesintilerini buradan sisteme tanımlayabilirsiniz. Fiyat aralığı girilmezse o kargo firması için sabit ücret kabul edilir.</div>
	</div>

	<div class="hbt-card" style="padding: 0; overflow: hidden; margin-bottom: 24px;">
		<table class="wp-list-table widefat fixed striped" style="border: none; margin: 0;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Mağaza', 'hbt-trendyol-profit-tracker' ); ?></th>
					<th><?php esc_html_e( 'Kargo Firması', 'hbt-trendyol-profit-tracker' ); ?></th>
					<th><?php esc_html_e( 'Fiyat Aralığı (TL)', 'hbt-trendyol-profit-tracker' ); ?></th>
					<th><?php esc_html_e( 'Kargo Ücreti (TL)', 'hbt-trendyol-profit-tracker' ); ?></th>
					<th><?php esc_html_e( 'Geçerlilik', 'hbt-trendyol-profit-tracker' ); ?></th>
					<th style="width: 200px;"><?php esc_html_e( 'İşlemler', 'hbt-trendyol-profit-tracker' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $shipping_costs ) ) : ?>
					<tr>
						<td colspan="6" style="text-align: center; padding: 40px 20px; color: var(--hbt-text-muted);">
							<span class="dashicons dashicons-car" style="font-size: 32px; width: 32px; height: 32px; margin-bottom: 10px; opacity: 0.5;"></span><br>
							<?php esc_html_e( 'Henüz kargo fiyatı tanımlanmamış.', 'hbt-trendyol-profit-tracker' ); ?>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $shipping_costs as $cost ) : ?>
						<tr>
							<td style="font-weight: 500; color: var(--hbt-primary);">
								<span class="dashicons dashicons-store" style="color: var(--hbt-text-muted); margin-right: 5px; font-size: 16px; width: 16px; height: 16px;"></span>
								<?php
								// Try to show store name if available.
								$store = HBT_Database::instance()->get_store( (int) $cost->store_id );
								echo esc_html( $store ? $store->store_name : (string) $cost->store_id );
								?>
							</td>
							<td style="font-weight: 600;"><?php echo esc_html( $cost->shipping_company ); ?></td>
							<td style="color: var(--hbt-text-muted);">
								<?php
								if ( $cost->price_min !== null || $cost->price_max !== null ) {
									echo esc_html( $cost->price_min !== null ? number_format( (float) $cost->price_min, 2 ) : '-' );
									echo ' - ';
									echo esc_html( $cost->price_max !== null ? number_format( (float) $cost->price_max, 2 ) : '-' );
								} else {
									echo '<span style="color: var(--hbt-text-muted); font-style: italic;">' . esc_html__( 'Sabit', 'hbt-trendyol-profit-tracker' ) . '</span>';
								}
								?>
							</td>
							<td>
								<span style="font-weight: 700; color: var(--hbt-primary); background: #F1F5F9; padding: 4px 10px; border-radius: 6px;">
									<?php echo esc_html( number_format( (float) $cost->cost_tl, 2 ) ); ?> ₺
								</span>
							</td>
							<td style="font-size: 12px; color: var(--hbt-text-muted);">
								<span class="dashicons dashicons-calendar-alt" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle;"></span>
								<?php echo esc_html( $cost->effective_from ) . ( $cost->effective_to ? ' &rarr; ' . esc_html( $cost->effective_to ) : ' &rarr; Sınırsız' ); ?>
							</td>
							<td>
								<div style="display: flex; gap: 6px;">
									<button type="button" class="button button-small btn-edit-shipping hbt-btn hbt-btn-outline" style="padding: 4px 10px !important; font-size: 12px !important; height: auto;"
										data-id="<?php echo esc_attr( (string) $cost->id ); ?>"
										data-store="<?php echo esc_attr( (string) $cost->store_id ); ?>"
										data-company="<?php echo esc_attr( $cost->shipping_company ); ?>"
										data-price-min="<?php echo esc_attr( $cost->price_min !== null ? (string) $cost->price_min : '' ); ?>"
										data-price-max="<?php echo esc_attr( $cost->price_max !== null ? (string) $cost->price_max : '' ); ?>"
										data-cost="<?php echo esc_attr( (string) $cost->cost_tl ); ?>"
										data-from="<?php echo esc_attr( $cost->effective_from ); ?>"
										data-to="<?php echo esc_attr( $cost->effective_to ); ?>">
										<span class="dashicons dashicons-edit"></span> <?php esc_html_e( 'Düzenle', 'hbt-trendyol-profit-tracker' ); ?>
									</button>
									<button type="button" class="button button-small button-link-delete btn-delete-shipping hbt-btn hbt-btn-outline" style="padding: 4px 10px !important; font-size: 12px !important; height: auto; color: var(--hbt-danger) !important; border-color: #FCA5A5 !important;"
										data-id="<?php echo esc_attr( (string) $cost->id ); ?>">
										<span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Sil', 'hbt-trendyol-profit-tracker' ); ?>
									</button>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<div id="shipping-modal" class="hbt-modal" style="display:none;">
		<div class="hbt-modal-overlay"></div>
		<div class="hbt-modal-box" style="max-width: 550px;">
			<div class="hbt-modal-header">
				<h2 id="shipping-modal-title"><span class="dashicons dashicons-car"></span> <?php esc_html_e( 'Kargo Fiyatı Ekle', 'hbt-trendyol-profit-tracker' ); ?></h2>
				<button class="hbt-modal-close">&times;</button>
			</div>
			<div class="hbt-modal-body">
				<form id="shipping-form">
					<input type="hidden" id="shipping-id" name="id" value="">
					<table class="form-table" style="margin-top: 0;">
						<tr>
							<th style="width: 150px; padding-top: 15px;"><label style="font-weight: 600; color: var(--hbt-primary);"><?php esc_html_e( 'Mağaza Seçimi', 'hbt-trendyol-profit-tracker' ); ?></label></th>
							<td>
								<select id="shipping-store" name="store_id" style="display:none; width: 100%; padding: 8px 12px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border);">
									<option value=""><?php esc_html_e( 'Lütfen seçin...', 'hbt-trendyol-profit-tracker' ); ?></option>
									<?php foreach ( $stores as $store ) : ?>
										<option value="<?php echo esc_attr( (string) $store->id ); ?>"><?php echo esc_html( $store->store_name ); ?></option>
									<?php endforeach; ?>
								</select>
								
								<div id="shipping-stores-checkboxes" style="background: var(--hbt-bg-color); border: 1px solid var(--hbt-border); border-radius: var(--hbt-radius-sm); padding: 12px; max-height: 140px; overflow-y: auto;">
									<label style="font-weight:700; color: var(--hbt-primary); display: block; margin-bottom: 8px; border-bottom: 1px solid var(--hbt-border); padding-bottom: 8px;">
										<input type="checkbox" id="shipping-select-all" style="margin-right: 6px;"> <?php esc_html_e( 'Tüm Mağazaları Seç', 'hbt-trendyol-profit-tracker' ); ?>
									</label>
									<div style="display: flex; flex-direction: column; gap: 6px;">
										<?php foreach ( $stores as $store ) : ?>
											<label style="color: var(--hbt-text-main); font-size: 13px; display: flex; align-items: center;">
												<input type="checkbox" name="store_ids[]" value="<?php echo esc_attr( (string) $store->id ); ?>" style="margin-right: 8px;">
												<?php echo esc_html( $store->store_name ); ?>
											</label>
										<?php endforeach; ?>
									</div>
								</div>
							</td>
						</tr>

						<tr>
							<th style="padding-top: 15px;"><label style="font-weight: 600; color: var(--hbt-primary);"><?php esc_html_e( 'Kargo Firması', 'hbt-trendyol-profit-tracker' ); ?></label></th>
							<td>
								<input type="text" id="shipping-company" name="shipping_company" class="regular-text" placeholder="Örn: Aras Kargo, MNG Kargo" style="width: 100%; padding: 8px 12px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border);" required>
							</td>
						</tr>

						<tr>
							<th style="padding-top: 15px;"><label style="font-weight: 600; color: var(--hbt-primary);"><?php esc_html_e( 'Fiyat Aralığı (TL)', 'hbt-trendyol-profit-tracker' ); ?></label></th>
							<td>
								<div style="display: flex; align-items: center; gap: 10px;">
									<input type="number" id="shipping-price-min" name="price_min" step="0.01" min="0" placeholder="Min" style="width: 100%; padding: 8px 12px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border);">
									<span style="color: var(--hbt-text-muted); font-weight: bold;">-</span>
									<input type="number" id="shipping-price-max" name="price_max" step="0.01" min="0" placeholder="Max" style="width: 100%; padding: 8px 12px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border);">
								</div>
								<p class="description" style="font-size: 12px; color: var(--hbt-text-muted); margin-top: 4px;">Sipariş tutarına göre barem yoksa boş bırakın.</p>
							</td>
						</tr>

						<tr>
							<th style="padding-top: 15px;"><label style="font-weight: 600; color: var(--hbt-primary);"><?php esc_html_e( 'Kesilecek Kargo (TL)', 'hbt-trendyol-profit-tracker' ); ?></label></th>
							<td><input type="number" id="shipping-cost" name="cost_tl" step="0.01" placeholder="0.00" style="width: 100%; padding: 8px 12px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border); font-weight: 700;" required></td>
						</tr>

						<tr>
							<th style="padding-top: 15px;"><label style="font-weight: 600; color: var(--hbt-primary);"><?php esc_html_e( 'Geçerlilik Zamanı', 'hbt-trendyol-profit-tracker' ); ?></label></th>
							<td>
								<div style="display: flex; align-items: center; gap: 10px;">
									<input type="text" id="shipping-from" name="effective_from" class="regular-text datepicker" placeholder="Başlangıç" style="width: 100%; padding: 8px 12px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border);" required>
									<span style="color: var(--hbt-text-muted); font-weight: bold;">&rarr;</span>
									<input type="text" id="shipping-to" name="effective_to" class="regular-text datepicker" placeholder="Bitiş (Opsiyonel)" style="width: 100%; padding: 8px 12px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border);">
								</div>
							</td>
						</tr>
					</table>
				</form>
			</div>
			<div class="hbt-modal-footer" style="padding: 16px 24px; border-top: 1px solid var(--hbt-border); display: flex; justify-content: flex-end; align-items: center; gap: 12px; background: #F8FAFC; border-radius: 0 0 var(--hbt-radius) var(--hbt-radius);">
				<button type="button" class="button hbt-modal-close hbt-btn hbt-btn-outline"><?php esc_html_e( 'İptal', 'hbt-trendyol-profit-tracker' ); ?></button>
				<button type="button" id="btn-save-shipping" class="button button-primary hbt-btn hbt-btn-primary"><span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Kaydet', 'hbt-trendyol-profit-tracker' ); ?></button>
			</div>
		</div>
	</div>
</div>

<style>
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