<?php
/**
 * Stores view.
 *
 * @package HBT_Trendyol_Profit_Tracker
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$stores = HBT_Database::instance()->get_stores();
?>
<div class="wrap hbt-tpt-wrap">
	
	<div class="hbt-page-header">
		<h1 class="hbt-page-title">
			<span class="dashicons dashicons-store"></span> 
			<?php esc_html_e( 'Mağazalar', 'hbt-trendyol-profit-tracker' ); ?>
		</h1>
		<div class="hbt-header-actions">
			<button type="button" class="page-title-action hbt-btn hbt-btn-primary" id="btn-add-store" style="margin: 0;">
				<span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Yeni Mağaza Ekle', 'hbt-trendyol-profit-tracker' ); ?>
			</button>
		</div>
	</div>

	<div class="hbt-card" style="padding: 0; overflow: hidden;">
		<table class="wp-list-table widefat fixed striped" style="border: none; margin: 0;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Mağaza Adı', 'hbt-trendyol-profit-tracker' ); ?></th>
					<th><?php esc_html_e( 'Supplier ID', 'hbt-trendyol-profit-tracker' ); ?></th>
					<th style="width: 100px; text-align: center;"><?php esc_html_e( 'Durum', 'hbt-trendyol-profit-tracker' ); ?></th>
					<th><?php esc_html_e( 'Son Sipariş Sync', 'hbt-trendyol-profit-tracker' ); ?></th>
					<th><?php esc_html_e( 'Son Finans Sync', 'hbt-trendyol-profit-tracker' ); ?></th>
					<th style="width: 360px;"><?php esc_html_e( 'İşlemler', 'hbt-trendyol-profit-tracker' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $stores ) ) : ?>
					<tr>
						<td colspan="6" style="text-align: center; padding: 40px 20px; color: var(--hbt-text-muted);">
							<span class="dashicons dashicons-info" style="font-size: 32px; width: 32px; height: 32px; margin-bottom: 10px; opacity: 0.5;"></span><br>
							<?php esc_html_e( 'Henüz mağaza eklenmemiş.', 'hbt-trendyol-profit-tracker' ); ?>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $stores as $store ) : ?>
						<tr data-id="<?php echo esc_attr( (string) $store->id ); ?>">
							<td style="font-weight: 600; color: var(--hbt-primary);">
								<span class="dashicons dashicons-store" style="color: var(--hbt-text-muted); margin-right: 5px;"></span>
								<?php echo esc_html( $store->store_name ); ?>
							</td>
							<td style="font-family: monospace; color: var(--hbt-text-muted);"><?php echo esc_html( $store->supplier_id ); ?></td>
							<td style="text-align: center;">
								<label class="hbt-toggle">
									<input type="checkbox" class="store-active-toggle"
										data-id="<?php echo esc_attr( (string) $store->id ); ?>"
										<?php checked( (int) $store->is_active, 1 ); ?>>
									<span class="hbt-toggle-slider"></span>
								</label>
							</td>
							<td style="font-size: 12px; color: var(--hbt-text-muted);">
								<?php echo $store->last_order_sync ? '<span class="dashicons dashicons-clock" style="font-size:14px; width:14px; height:14px; vertical-align:middle;"></span> ' . esc_html( $store->last_order_sync ) : '–'; ?>
							</td>
							<td style="font-size: 12px; color: var(--hbt-text-muted);">
								<?php echo $store->last_finance_sync ? '<span class="dashicons dashicons-clock" style="font-size:14px; width:14px; height:14px; vertical-align:middle;"></span> ' . esc_html( $store->last_finance_sync ) : '–'; ?>
							</td>
							<td>
								<div style="display: flex; gap: 6px;">
									<button type="button" class="button button-small btn-edit-store hbt-btn hbt-btn-outline" style="padding: 4px 10px !important; font-size: 12px !important; height: auto;"
										data-id="<?php echo esc_attr( (string) $store->id ); ?>"
										data-name="<?php echo esc_attr( $store->store_name ); ?>"
										data-supplier="<?php echo esc_attr( $store->supplier_id ); ?>">
										<span class="dashicons dashicons-edit"></span> <?php esc_html_e( 'Düzenle', 'hbt-trendyol-profit-tracker' ); ?>
									</button>
									<button type="button" class="button button-small btn-sync-store hbt-btn hbt-btn-primary" style="padding: 4px 10px !important; font-size: 12px !important; height: auto; background: var(--hbt-info) !important; border-color: var(--hbt-info) !important;"
										data-id="<?php echo esc_attr( (string) $store->id ); ?>">
										<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Senkronize Et', 'hbt-trendyol-profit-tracker' ); ?>
									</button>
									<button type="button" class="button button-small button-link-delete btn-delete-store hbt-btn hbt-btn-outline" style="padding: 4px 10px !important; font-size: 12px !important; height: auto; color: var(--hbt-danger) !important; border-color: #FCA5A5 !important;"
										data-id="<?php echo esc_attr( (string) $store->id ); ?>">
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

	<div id="store-modal" class="hbt-modal" style="display:none;">
		<div class="hbt-modal-overlay"></div>
		<div class="hbt-modal-box">
			<div class="hbt-modal-header">
				<h2 id="store-modal-title"><?php esc_html_e( 'Mağaza Ekle', 'hbt-trendyol-profit-tracker' ); ?></h2>
				<button class="hbt-modal-close">&times;</button>
			</div>
			<div class="hbt-modal-body">
				<form id="store-form">
					<input type="hidden" id="store-id" name="id" value="">
					<table class="form-table" style="margin-top: 0;">
						<tr>
							<th style="width: 140px; padding-top: 15px;"><label for="store-name" style="font-weight: 600; color: var(--hbt-primary);"><?php esc_html_e( 'Mağaza Adı', 'hbt-trendyol-profit-tracker' ); ?></label></th>
							<td><input type="text" id="store-name" name="store_name" class="regular-text" style="width: 100%; padding: 8px 12px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border);" required></td>
						</tr>
						<tr>
							<th style="padding-top: 15px;"><label for="store-supplier-id" style="font-weight: 600; color: var(--hbt-primary);"><?php esc_html_e( 'Supplier ID', 'hbt-trendyol-profit-tracker' ); ?></label></th>
							<td><input type="text" id="store-supplier-id" name="supplier_id" class="regular-text" style="width: 100%; padding: 8px 12px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border);" required></td>
						</tr>
						<tr>
							<th style="padding-top: 15px;"><label for="store-api-key" style="font-weight: 600; color: var(--hbt-primary);"><?php esc_html_e( 'API Key', 'hbt-trendyol-profit-tracker' ); ?></label></th>
							<td><input type="password" id="store-api-key" name="api_key" class="regular-text" autocomplete="new-password" style="width: 100%; padding: 8px 12px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border);"></td>
						</tr>
						<tr>
							<th style="padding-top: 15px;"><label for="store-api-secret" style="font-weight: 600; color: var(--hbt-primary);"><?php esc_html_e( 'API Secret', 'hbt-trendyol-profit-tracker' ); ?></label></th>
							<td><input type="password" id="store-api-secret" name="api_secret" class="regular-text" autocomplete="new-password" style="width: 100%; padding: 8px 12px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border);"></td>
						</tr>
					</table>
				</form>
			</div>
			<div class="hbt-modal-footer" style="padding: 16px 24px; border-top: 1px solid var(--hbt-border); display: flex; justify-content: flex-end; align-items: center; gap: 12px; background: #F8FAFC; border-radius: 0 0 var(--hbt-radius) var(--hbt-radius);">
				<span id="connection-result" style="flex-grow: 1; font-weight: 500; font-size: 13px;"></span>
				<button type="button" id="btn-test-connection" class="button hbt-btn hbt-btn-outline"><span class="dashicons dashicons-admin-network"></span> <?php esc_html_e( 'Bağlantıyı Test Et', 'hbt-trendyol-profit-tracker' ); ?></button>
				<button type="button" id="btn-save-store" class="button button-primary hbt-btn hbt-btn-primary"><span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Kaydet', 'hbt-trendyol-profit-tracker' ); ?></button>
			</div>
		</div>
	</div>
</div>

<?php
// Mağazalar sayfasının altına: Senkronize Et butonuna basınca anlık API'dan gelen ve veritabanına kaydedilen verileri göster
if ( isset($_GET['hbt_manual_sync']) && defined('WP_DEBUG') && WP_DEBUG ) {
	$sync_data_path = WP_CONTENT_DIR . '/hbt_last_sync.json';
	if ( file_exists($sync_data_path) ) {
		$sync_data = json_decode(file_get_contents($sync_data_path), true);
		if ( is_array($sync_data) && !empty($sync_data) ) {
			echo '<div style="margin-top:30px;padding:15px;border:2px solid #007cba;background:#f4f8fb;">
				<h2>Son Senkronize Edilen Veriler (API & DB)</h2>
				<pre style="max-height:500px;overflow:auto;font-size:13px;background:#eef;">'.esc_html(json_encode($sync_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)).'</pre>
			</div>';
		} else {
			echo '<div style="margin-top:30px;padding:15px;border:1px solid #ccc;background:#fffbe6;"><strong>Senkr. veri bulunamadı.</strong></div>';
		}
	} else {
		echo '<div style="margin-top:30px;padding:15px;border:1px solid #ccc;background:#fffbe6;"><strong>Senkr. veri dosyası bulunamadı.</strong></div>';
	}
}
?>

<div id="sync-date-modal" class="hbt-modal" style="display:none;">
	<div class="hbt-modal-overlay"></div>
	<div class="hbt-modal-box" style="max-width: 450px;">
		<div class="hbt-modal-header">
			<h2><?php esc_html_e( 'Senkronizasyon Tarihi', 'hbt-trendyol-profit-tracker' ); ?></h2>
			<button class="hbt-modal-close btn-close-sync-modal">&times;</button>
		</div>
		<div class="hbt-modal-body">
			<p>Hangi tarih aralığındaki siparişleri çekmek istiyorsunuz? <br><small>(Önerilen: Yalnızca ihtiyacınız olan kısa periyotları seçin)</small></p>
			
			<form id="sync-date-form">
				<input type="hidden" id="sync-store-id" value="">
				
				<div style="margin-bottom: 15px; display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; background: #f0f0f1; padding: 10px; border-radius: 5px;">
					<button type="button" class="button btn-quick-date hbt-btn hbt-btn-outline" data-hours="3" style="height: auto;">Son 3 Saat</button>
					<button type="button" class="button btn-quick-date hbt-btn hbt-btn-outline" data-hours="12" style="height: auto;">Son 12 Saat</button>
					<button type="button" class="button btn-quick-date hbt-btn hbt-btn-outline" data-hours="24" style="height: auto;">Son 24 Saat</button>
					<button type="button" class="button btn-quick-date hbt-btn hbt-btn-outline" data-hours="72" style="height: auto;">Son 3 Gün</button>
					<button type="button" class="button btn-quick-date hbt-btn hbt-btn-outline" data-hours="168" style="height: auto;">Son 7 Gün</button>
					<button type="button" class="button btn-quick-date hbt-btn hbt-btn-outline" data-hours="720" style="height: auto;">Son 1 Ay</button>
				</div>

				<table class="form-table">
					<tr>
						<th><label for="sync-start-date">Başlangıç Zamanı</label></th>
						<td><input type="datetime-local" id="sync-start-date" class="regular-text" style="width: 100%; padding: 8px 12px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border);" required></td>
					</tr>
					<tr>
						<th><label for="sync-end-date">Bitiş Zamanı</label></th>
						<td><input type="datetime-local" id="sync-end-date" class="regular-text" style="width: 100%; padding: 8px 12px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border);" required></td>
					</tr>
				</table>
			</form>
			
			<div id="sync-progress-area" style="display:none; margin-top:20px;">
				<p><strong id="sync-progress-text" style="color:#007cba;">Siparişler çekiliyor, lütfen bekleyin...</strong></p>
				<div style="width:100%; background:#ddd; height:20px; border-radius:3px; overflow:hidden;">
					<div id="sync-progress-bar" style="width:0%; background:#28a745; height:100%; transition: width 0.3s;"></div>
				</div>
				<p id="sync-stats-text" style="font-size:12px; margin-top:5px; color:#666;"></p>
			</div>
			
		</div>
		<div class="hbt-modal-footer" style="padding: 16px 24px; border-top: 1px solid var(--hbt-border); display: flex; justify-content: flex-end; align-items: center; gap: 12px; background: #F8FAFC; border-radius: 0 0 var(--hbt-radius) var(--hbt-radius);">
			<button type="button" class="button btn-close-sync-modal hbt-btn hbt-btn-outline"><?php esc_html_e( 'İptal', 'hbt-trendyol-profit-tracker' ); ?></button>
			<button type="button" id="btn-start-sync" class="button button-primary hbt-btn hbt-btn-primary"><span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Senkronizasyonu Başlat', 'hbt-trendyol-profit-tracker' ); ?></button>
		</div>
	</div>
</div>