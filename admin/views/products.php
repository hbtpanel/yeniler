<?php
/**
 * Products (Ürün Maliyetleri) view.
 *
 * @package HBT_Trendyol_Profit_Tracker
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$db     = HBT_Database::instance();
$stores = $db->get_stores();

$store_id = absint( $_GET['store_id'] ?? 0 );
$search   = sanitize_text_field( $_GET['search'] ?? '' );

// Boş değer gelirse 0'a çevirme, boş (string) olarak bırak.
$has_cost = ( isset( $_GET['has_cost'] ) && $_GET['has_cost'] !== '' ) ? (int) $_GET['has_cost'] : '';

$args = array_filter(
	array(
		'search'   => $search,
		'has_cost' => ( $has_cost !== '' ) ? (bool) $has_cost : null,
	),
	fn( $v ) => $v !== null && $v !== ''
);

$products = $db->get_product_costs( $store_id, $args );

// Tabloda ID yerine Mağaza adını gösterebilmek için haritalama
$store_map = array();
foreach ( $stores as $s ) {
	$store_map[ $s->id ] = $s->store_name;
}
?>
<div class="wrap hbt-tpt-wrap">
	
	<div class="hbt-page-header">
		<h1 class="hbt-page-title">
			<span class="dashicons dashicons-products"></span> 
			<?php esc_html_e( 'Ürün Maliyetleri', 'hbt-trendyol-profit-tracker' ); ?>
		</h1>
		<div class="hbt-header-actions">
			<button type="button" class="hbt-btn hbt-btn-outline" id="btn-sync-products">
				<span class="dashicons dashicons-update"></span> <?php esc_html_e( "Trendyol'dan Çek", 'hbt-trendyol-profit-tracker' ); ?>
			</button>
			<button type="button" class="hbt-btn hbt-btn-outline" id="btn-import-csv" style="color: #059669 !important; border-color: #A7F3D0 !important; background: #ECFDF5 !important;">
				<span class="dashicons dashicons-media-spreadsheet"></span> <?php esc_html_e( 'Toplu Import (CSV/XLS)', 'hbt-trendyol-profit-tracker' ); ?>
			</button>
			<button type="button" class="hbt-btn hbt-btn-primary" id="btn-add-product">
				<span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Manuel Ürün Ekle', 'hbt-trendyol-profit-tracker' ); ?>
			</button>
		</div>
	</div>

	<div class="hbt-card" style="margin-bottom: 24px;">
		<h3 class="hbt-widget-title" style="margin-top: 0;"><span class="dashicons dashicons-filter"></span> Ürün Filtrele ve Ara</h3>
		<form method="get" action="">
			<input type="hidden" name="page" value="hbt-tpt-products">
			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
				
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
					<label style="font-weight: 600; color: var(--hbt-primary); margin-bottom: 6px; display: block; font-size: 13px;"><?php esc_html_e( 'Maliyet Durumu:', 'hbt-trendyol-profit-tracker' ); ?></label>
					<select name="has_cost" style="width: 100%; padding: 8px 12px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border);">
						<option value="" <?php selected( $has_cost, '' ); ?>><?php esc_html_e( 'Tümü', 'hbt-trendyol-profit-tracker' ); ?></option>
						<option value="1" <?php selected( $has_cost, 1 ); ?>><?php esc_html_e( 'Maliyeti Girilmiş Olanlar', 'hbt-trendyol-profit-tracker' ); ?></option>
						<option value="0" <?php selected( $has_cost, 0 ); ?>><?php esc_html_e( 'Maliyeti Girilmemiş (Eksik) Olanlar', 'hbt-trendyol-profit-tracker' ); ?></option>
					</select>
				</div>

				<div class="hbt-filter-group" style="grid-column: span 2;">
					<label style="font-weight: 600; color: var(--hbt-primary); margin-bottom: 6px; display: block; font-size: 13px;"><?php esc_html_e( 'Arama:', 'hbt-trendyol-profit-tracker' ); ?></label>
					<div style="display: flex; gap: 12px;">
						<input type="text" name="search" class="regular-text" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Barkod veya ürün adı yazın...', 'hbt-trendyol-profit-tracker' ); ?>" style="width: 100%; padding: 8px 12px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border);">
						<button type="submit" class="hbt-btn hbt-btn-primary" style="padding: 8px 24px !important;">
							<span class="dashicons dashicons-search"></span> <?php esc_html_e( 'Filtrele', 'hbt-trendyol-profit-tracker' ); ?>
						</button>
					</div>
				</div>

			</div>
		</form>
	</div>

	<div class="hbt-alert-box hbt-alert-warning" style="margin-bottom: 16px;">
		<span class="dashicons dashicons-info"></span> 
		<div><strong>Açık Sarı / Krem</strong> renkte görünen satırlar, maliyeti henüz sisteme girilmemiş (0.00 USD) ürünleri temsil eder. Kârın doğru hesaplanması için bu ürünleri düzenleyiniz.</div>
	</div>

	<div class="hbt-card" style="padding: 0; overflow: hidden; margin-bottom: 24px;">
		<table class="wp-list-table widefat fixed striped <?php echo ! empty( $products ) ? 'hbt-datatable' : ''; ?>" id="products-table" style="border: none; margin: 0;">
			<thead>
				<tr>
					<th style="width:130px;"><?php esc_html_e( 'Mağaza', 'hbt-trendyol-profit-tracker' ); ?></th>
					<th><?php esc_html_e( 'Ürün Adı', 'hbt-trendyol-profit-tracker' ); ?></th>
					<th><?php esc_html_e( 'Barkod', 'hbt-trendyol-profit-tracker' ); ?></th>
					<th><?php esc_html_e( 'SKU', 'hbt-trendyol-profit-tracker' ); ?></th>
					<th><?php esc_html_e( 'Kategori', 'hbt-trendyol-profit-tracker' ); ?></th>
					<th style="width:100px;"><?php esc_html_e( 'Maliyet ($)', 'hbt-trendyol-profit-tracker' ); ?></th>
					<th style="width:70px;"><?php esc_html_e( 'Desi', 'hbt-trendyol-profit-tracker' ); ?></th>
					<th style="width:80px;"><?php esc_html_e( 'KDV (%)', 'hbt-trendyol-profit-tracker' ); ?></th>
					<th style="width:220px;"><?php esc_html_e( 'İşlemler', 'hbt-trendyol-profit-tracker' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $products ) ) : ?>
					<tr>
						<td colspan="9" style="text-align: center; padding: 40px 20px; color: var(--hbt-text-muted);">
							<span class="dashicons dashicons-products" style="font-size: 32px; width: 32px; height: 32px; margin-bottom: 10px; opacity: 0.5;"></span><br>
							<?php esc_html_e( 'Aradığınız kriterlere uygun ürün bulunamadı.', 'hbt-trendyol-profit-tracker' ); ?>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $products as $product ) : ?>
						<tr data-id="<?php echo esc_attr( (string) $product->id ); ?>"
							class="<?php echo (float) $product->cost_usd === 0.0 ? 'cost-missing' : ''; ?>">
							
							<td style="vertical-align:middle; font-weight: 600; color: var(--hbt-primary);">
								<div class="hbt-inline-display" style="display: flex; align-items: center; gap: 4px;">
									<span class="dashicons dashicons-store" style="color: var(--hbt-text-muted); font-size: 16px; width: 16px; height: 16px;"></span>
									<span class="hbt-store-text"><?php echo esc_html( isset( $store_map[ $product->store_id ] ) ? $store_map[ $product->store_id ] : $product->store_id ); ?></span>
								</div>
								<select class="hbt-inline-input" name="store_id" style="display:none; width:100%; padding: 4px 6px;">
									<?php foreach ( $stores as $store ) : ?>
										<option value="<?php echo esc_attr( $store->id ); ?>" <?php selected( $product->store_id, $store->id ); ?>>
											<?php echo esc_html( $store->store_name ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>

							<td style="vertical-align:middle; font-weight: 500;">
								<span class="hbt-inline-display"><?php echo esc_html( $product->product_name ); ?></span>
								<input type="text" class="hbt-inline-input" name="product_name" value="<?php echo esc_attr( $product->product_name ); ?>" style="display:none;width:100%;">
							</td>
							<td style="vertical-align:middle;">
								<span class="hbt-inline-display" style="font-family:monospace;"><?php echo esc_html( $product->barcode ); ?></span>
								<input type="text" class="hbt-inline-input" name="barcode" value="<?php echo esc_attr( $product->barcode ); ?>" style="display:none;width:100%;">
							</td>
							<td style="vertical-align:middle; color: var(--hbt-text-muted);">
								<span class="hbt-inline-display"><?php echo esc_html( $product->sku ); ?></span>
								<input type="text" class="hbt-inline-input" name="sku" value="<?php echo esc_attr( $product->sku ); ?>" style="display:none;width:100%;">
							</td>
							<td style="vertical-align:middle; font-size: 12px;">
								<span class="hbt-inline-display"><?php echo esc_html( $product->category_name ); ?></span>
								<input type="text" class="hbt-inline-input" name="category_name" value="<?php echo esc_attr( $product->category_name ); ?>" style="display:none;width:100%;">
							</td>
							<td style="vertical-align:middle;">
								<span class="hbt-inline-display">
									<strong style="color: <?php echo (float) $product->cost_usd > 0 ? 'var(--hbt-primary)' : 'var(--hbt-danger)'; ?>;">
										$<?php echo esc_html( number_format( (float) $product->cost_usd, 2 ) ); ?>
									</strong>
								</span>
								<input type="number" class="hbt-inline-input" name="cost_usd" step="0.0001" min="0"
									value="<?php echo esc_attr( (string) $product->cost_usd ); ?>" style="display:none;width:100%;">
							</td>
							<td style="vertical-align:middle;">
								<span class="hbt-inline-display"><?php echo esc_html( (string) $product->desi ); ?></span>
								<input type="number" class="hbt-inline-input" name="desi" step="0.01" min="0"
									value="<?php echo esc_attr( (string) $product->desi ); ?>" style="display:none;width:100%;">
							</td>
							<td style="vertical-align:middle;">
								<span class="hbt-inline-display">%<?php echo esc_html( (string) $product->vat_rate ); ?></span>
								<input type="number" class="hbt-inline-input" name="vat_rate" step="0.01" min="0" max="100"
									value="<?php echo esc_attr( (string) $product->vat_rate ); ?>" style="display:none;width:100%;">
							</td>
							<td style="vertical-align:middle;">
								<div style="display: flex; gap: 4px; flex-wrap: wrap;">
									<button type="button" class="button button-small btn-inline-edit hbt-btn hbt-btn-outline" style="padding: 2px 8px !important; font-size: 11px !important; height: auto;">
										<span class="dashicons dashicons-edit" style="font-size:14px; width:14px; height:14px;"></span> Düzenle
									</button>
									<button type="button" class="button button-small button-primary btn-inline-save hbt-btn hbt-btn-primary" style="display:none; padding: 2px 8px !important; font-size: 11px !important; height: auto;"
										data-id="<?php echo esc_attr( (string) $product->id ); ?>"
										data-store="<?php echo esc_attr( (string) $product->store_id ); ?>">
										<span class="dashicons dashicons-saved" style="font-size:14px; width:14px; height:14px;"></span> Kaydet
									</button>
									<button type="button" class="button button-small btn-inline-cancel hbt-btn hbt-btn-outline" style="display:none; padding: 2px 8px !important; font-size: 11px !important; height: auto;">İptal</button>
									<button type="button" class="button button-small button-link-delete btn-delete-product hbt-btn hbt-btn-outline" style="padding: 2px 8px !important; font-size: 11px !important; height: auto; color: var(--hbt-danger) !important; border-color: #FCA5A5 !important;" data-id="<?php echo esc_attr( (string) $product->id ); ?>">
										<span class="dashicons dashicons-trash" style="font-size:14px; width:14px; height:14px;"></span> Sil
									</button>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<div class="hbt-card" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
		<div>
			<h3 style="margin: 0 0 8px 0; font-size: 16px; display: flex; align-items: center; gap: 8px; color: var(--hbt-primary);">
				<span class="dashicons dashicons-info" style="color: var(--hbt-secondary);"></span> Toplu İçeri Aktarım Şablonları
			</h3>
			<p style="margin: 0; color: var(--hbt-text-muted); font-size: 13px;">
				İçe aktarım için kullanılacak dosyanın ilk satırı başlık olmalı ve şu formatta hazırlanmalıdır: 
				<code style="background: var(--hbt-bg-color); padding: 2px 6px; border-radius: 4px; border: 1px solid var(--hbt-border);">barcode, cost_usd, desi, vat_rate</code>
			</p>
		</div>
		<div style="display: flex; gap: 10px;">
			<a href="<?php echo esc_url( HBT_TPT_PLUGIN_URL . 'admin/sample-import.csv' ); ?>" class="hbt-btn hbt-btn-outline" download>
				<span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Örnek CSV İndir', 'hbt-trendyol-profit-tracker' ); ?>
			</a>
			<a href="<?php echo esc_url( HBT_TPT_PLUGIN_URL . 'admin/sample-import.xls' ); ?>" class="hbt-btn hbt-btn-outline" download>
				<span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Örnek XLS İndir', 'hbt-trendyol-profit-tracker' ); ?>
			</a>
		</div>
	</div>

	<div id="import-modal" class="hbt-modal" style="display:none;">
		<div class="hbt-modal-overlay"></div>
		<div class="hbt-modal-box">
			<div class="hbt-modal-header">
				<h2><span class="dashicons dashicons-media-spreadsheet"></span> <?php esc_html_e( 'Toplu Maliyet Import', 'hbt-trendyol-profit-tracker' ); ?></h2>
				<button class="hbt-modal-close">&times;</button>
			</div>
			<div class="hbt-modal-body">
				<p style="color: var(--hbt-text-muted); font-size: 13px; margin-bottom: 20px;">
					<?php esc_html_e( 'Dışarıda hazırladığınız Excel veya CSV dosyasını sisteme yükleyerek maliyetlerinizi tek seferde güncelleyin.', 'hbt-trendyol-profit-tracker' ); ?>
				</p>
				<form id="import-form" enctype="multipart/form-data">
					<div style="margin-bottom: 16px;">
						<label style="font-weight: 600; color: var(--hbt-primary); display: block; margin-bottom: 8px;">Hedef Mağaza:</label>
						<select name="store_id" id="import-store-id" style="width: 100%; padding: 8px 12px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border);" required>
							<option value=""><?php esc_html_e( 'Lütfen bir mağaza seçin...', 'hbt-trendyol-profit-tracker' ); ?></option>
							<?php foreach ( $stores as $store ) : ?>
								<option value="<?php echo esc_attr( (string) $store->id ); ?>">
									<?php echo esc_html( $store->store_name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					
					<div class="hbt-drop-zone" id="csv-drop-zone" style="border: 2px dashed #CBD5E1; background: #F8FAFC; border-radius: var(--hbt-radius); padding: 40px 20px; text-align: center; cursor: pointer; transition: all 0.3s; margin-bottom: 10px;">
						<span class="dashicons dashicons-upload" style="font-size: 40px; width: 40px; height: 40px; color: #94A3B8; margin-bottom: 10px;"></span><br>
						<span style="font-weight: 500; color: var(--hbt-primary);"><?php esc_html_e( 'CSV veya XLS dosyasını buraya sürükleyin', 'hbt-trendyol-profit-tracker' ); ?></span><br>
						<span style="font-size: 12px; color: var(--hbt-text-muted);"><?php esc_html_e( 'veya seçmek için tıklayın', 'hbt-trendyol-profit-tracker' ); ?></span>
						<input type="file" name="csv_file" id="csv-file-input" accept=".csv,.xls,.xlsx" style="display:none;">
					</div>
					<p id="csv-filename" style="font-weight: 600; color: var(--hbt-secondary); text-align: center; margin: 0; min-height: 20px;"></p>
				</form>
			</div>
			<div class="hbt-modal-footer" style="padding: 16px 24px; border-top: 1px solid var(--hbt-border); display: flex; justify-content: flex-end; align-items: center; background: #F8FAFC; border-radius: 0 0 var(--hbt-radius) var(--hbt-radius);">
				<button type="button" id="btn-do-import" class="button button-primary hbt-btn hbt-btn-primary">
					<span class="dashicons dashicons-cloud-upload"></span> <?php esc_html_e( 'İçe Aktarımı Başlat', 'hbt-trendyol-profit-tracker' ); ?>
				</button>
			</div>
		</div>
	</div>

	<div id="product-modal" class="hbt-modal" style="display:none;">
		<div class="hbt-modal-overlay"></div>
		<div class="hbt-modal-box">
			<div class="hbt-modal-header">
				<h2><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Manuel Ürün Ekle', 'hbt-trendyol-profit-tracker' ); ?></h2>
				<button class="hbt-modal-close">&times;</button>
			</div>
			<div class="hbt-modal-body">
				<form id="product-form">
					<input type="hidden" id="product-id" name="id" value="">
					<table class="form-table" style="margin-top: 0;">
						<tr>
							<th style="width: 140px; padding-top: 15px;"><label style="font-weight: 600; color: var(--hbt-primary);"><?php esc_html_e( 'Mağaza', 'hbt-trendyol-profit-tracker' ); ?></label></th>
							<td>
								<select name="store_id" style="width: 100%; padding: 8px 12px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border);" required>
									<option value=""><?php esc_html_e( 'Mağaza seçin', 'hbt-trendyol-profit-tracker' ); ?></option>
									<?php foreach ( $stores as $store ) : ?>
										<option value="<?php echo esc_attr( (string) $store->id ); ?>">
											<?php echo esc_html( $store->store_name ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th style="padding-top: 15px;"><label style="font-weight: 600; color: var(--hbt-primary);"><?php esc_html_e( 'Barkod', 'hbt-trendyol-profit-tracker' ); ?></label></th>
							<td><input type="text" name="barcode" class="regular-text" style="width: 100%; padding: 8px 12px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border);" required></td>
						</tr>
						<tr>
							<th style="padding-top: 15px;"><label style="font-weight: 600; color: var(--hbt-primary);"><?php esc_html_e( 'Ürün Adı', 'hbt-trendyol-profit-tracker' ); ?></label></th>
							<td><input type="text" name="product_name" class="regular-text" style="width: 100%; padding: 8px 12px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border);"></td>
						</tr>
						<tr>
							<th style="padding-top: 15px;"><label style="font-weight: 600; color: var(--hbt-primary);"><?php esc_html_e( 'SKU', 'hbt-trendyol-profit-tracker' ); ?></label></th>
							<td><input type="text" name="sku" class="regular-text" style="width: 100%; padding: 8px 12px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border);"></td>
						</tr>
						<tr>
							<th style="padding-top: 15px;"><label style="font-weight: 600; color: var(--hbt-primary);"><?php esc_html_e( 'Maliyet (USD)', 'hbt-trendyol-profit-tracker' ); ?></label></th>
							<td><input type="number" name="cost_usd" step="0.0001" min="0" value="0" style="width: 100%; padding: 8px 12px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border);"></td>
						</tr>
						<tr>
							<th style="padding-top: 15px;"><label style="font-weight: 600; color: var(--hbt-primary);"><?php esc_html_e( 'Desi', 'hbt-trendyol-profit-tracker' ); ?></label></th>
							<td><input type="number" name="desi" step="0.01" min="0" value="1" style="width: 100%; padding: 8px 12px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border);"></td>
						</tr>
						<tr>
							<th style="padding-top: 15px;"><label style="font-weight: 600; color: var(--hbt-primary);"><?php esc_html_e( 'KDV (%)', 'hbt-trendyol-profit-tracker' ); ?></label></th>
							<td><input type="number" name="vat_rate" step="0.01" min="0" max="100" value="20" style="width: 100%; padding: 8px 12px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border);"></td>
						</tr>
					</table>
				</form>
			</div>
			<div class="hbt-modal-footer" style="padding: 16px 24px; border-top: 1px solid var(--hbt-border); display: flex; justify-content: flex-end; align-items: center; background: #F8FAFC; border-radius: 0 0 var(--hbt-radius) var(--hbt-radius);">
				<button type="button" id="btn-save-product" class="button button-primary hbt-btn hbt-btn-primary"><span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Ürünü Kaydet', 'hbt-trendyol-profit-tracker' ); ?></button>
			</div>
		</div>
	</div>

</div>

<style>
/* Inline Edit Input Düzeltmeleri */
.hbt-inline-input {
    border: 1px solid var(--hbt-secondary) !important;
    border-radius: 4px !important;
    padding: 4px 8px !important;
    box-shadow: 0 0 0 2px rgba(37,99,235,0.2) !important;
    outline: none !important;
    font-size: 13px !important;
}

/* Tablo içindeki paddingleri biraz dengeleyelim */
.hbt-wrap table.wp-list-table thead th:first-child,
.hbt-wrap table.wp-list-table tbody td:first-child {
    padding-left: 16px !important;
}
.hbt-wrap table.wp-list-table thead th:last-child,
.hbt-wrap table.wp-list-table tbody td:last-child {
    padding-right: 16px !important;
}

/* Sürükle Bırak Alanı Hover Efekti */
#csv-drop-zone:hover {
	background-color: #F1F5F9 !important;
	border-color: var(--hbt-secondary) !important;
}
</style>

<script>
jQuery(document).ready(function($) {
    // 1. Kullanıcı açılır menüden yeni mağaza seçtiğinde, Kaydet butonuna yeni mağaza ID'sini fısıldıyoruz.
    $(document).on('change', 'select[name="store_id"].hbt-inline-input', function() {
        var newStoreId = $(this).val();
        var $saveBtn = $(this).closest('tr').find('.btn-inline-save');
        
        // jQuery .data() belleğini güncelle
        $saveBtn.data('store', newStoreId);
        // HTML DOM niteliğini güncelle (garanti olsun diye)
        $saveBtn.attr('data-store', newStoreId);
    });

    // 2. Kaydet butonuna basıldığında ekrandaki mağaza adını anında yeni isimle değiştiriyoruz.
    $(document).on('click', '.btn-inline-save', function() {
        var $row = $(this).closest('tr');
        var selectedText = $row.find('select[name="store_id"] option:selected').text().trim();
        
        // Sadece mağaza adını güncelleyelim, ikon sabit kalsın
        $row.find('.hbt-store-text').text(selectedText);
    });
});
</script>