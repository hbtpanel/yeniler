<?php
/**
 * Products (Ürün Maliyetleri) view.
 *
 * @package HBT_Trendyol_Profit_Tracker
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

$db     = HBT_Database::instance();
$stores = $db->get_stores();

$store_id = absint( $_GET['store_id'] ?? 0 );
// Arama kutusunu artık canlı (JS) yaptığımız için PHP tarafındaki search'ü siliyoruz
$selected_category = sanitize_text_field( $_GET['category'] ?? '' );

// Boş değer gelirse 0'a çevirme, boş (string) olarak bırak.
$has_cost = ( isset( $_GET['has_cost'] ) && $_GET['has_cost'] !== '' ) ? (int) $_GET['has_cost'] : '';

$args = array_filter(
	array(
		'has_cost' => ( $has_cost !== '' ) ? (bool) $has_cost : null,
	),
	fn( $v ) => $v !== null && $v !== ''
);

$products = $db->get_product_costs( $store_id, $args );

// Veritabanındaki Benzersiz Kategorileri Çek (Filtre için)
$categories = $wpdb->get_col("SELECT DISTINCT category_name FROM {$wpdb->prefix}hbt_product_costs WHERE category_name != '' ORDER BY category_name ASC");

// Kategori seçildiyse ürün dizisini filtrele
if ( !empty($selected_category) ) {
    $products = array_filter($products, function($p) use ($selected_category) {
        return $p->category_name === $selected_category;
    });
}

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

	<?php 
	// Trendyol API Canlı Radar Ekranı
	$api_debug_log = get_option('hbt_last_api_debug', '');
	if ( !empty($api_debug_log) ) : 
	?>
	<div class="hbt-card" style="background: #1e1e1e; border: 1px solid #444; border-left: 4px solid #d63638; margin-bottom: 24px; box-shadow: 0 4px 15px rgba(0,0,0,0.3);">
		<div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 1px solid #333; padding-bottom: 10px; margin-bottom: 10px;">
			<h3 style="color:#fff; margin:0;"><span class="dashicons dashicons-visibility"></span> Trendyol API Canlı Radar (Debug)</h3>
			<form method="post" action="">
				<button type="submit" name="clear_api_debug" class="button button-small" style="background:#d63638; color:#fff; border:none; border-radius:3px;">Radarı Temizle</button>
			</form>
			<?php 
			if(isset($_POST['clear_api_debug'])){
				delete_option('hbt_last_api_debug');
				echo '<script>window.location.href=window.location.href;</script>';
			}
			?>
		</div>
		<p style="color:#aaa; font-size:12px; margin-top:0;">Aşağıdaki kutuda Trendyol'un sistemimize gönderdiği <b>en son ve en ham veri</b> listelenmektedir. Eğer ürünler çekilemiyorsa, Trendyol'un bize verdiği mazeret buradadır:</p>
		<textarea style="width:100%; height:250px; background:#000; color:#00ff00; font-family:monospace; font-size:13px; padding:10px; border:1px solid #333;" readonly><?php echo esc_textarea($api_debug_log); ?></textarea>
	</div>
	<?php endif; ?>

	<div class="hbt-card" style="margin-bottom: 24px;">
		<h3 class="hbt-widget-title" style="margin-top: 0;"><span class="dashicons dashicons-filter"></span> Ürün Filtrele ve Ara</h3>
		<div style="display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end;">
            
            <!-- Seçim yapıldığında otomatik çalışan form -->
            <form method="get" action="" id="hbt-auto-filter-form" style="display: flex; flex-wrap: wrap; gap: 16px; flex: 2;">
                <input type="hidden" name="page" value="hbt-tpt-products">
                
                <div class="hbt-filter-group" style="flex: 1; min-width: 150px;">
                    <label style="font-weight: 600; color: var(--hbt-primary); margin-bottom: 6px; display: block; font-size: 13px;"><?php esc_html_e( 'Mağaza:', 'hbt-trendyol-profit-tracker' ); ?></label>
                    <select name="store_id" class="hbt-auto-submit" style="width: 100%; padding: 8px 12px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border);">
                        <option value="0"><?php esc_html_e( 'Tüm Mağazalar', 'hbt-trendyol-profit-tracker' ); ?></option>
                        <?php foreach ( $stores as $store ) : ?>
                            <option value="<?php echo esc_attr( (string) $store->id ); ?>" <?php selected( $store_id, (int) $store->id ); ?>>
                                <?php echo esc_html( $store->store_name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="hbt-filter-group" style="flex: 1; min-width: 150px;">
                    <label style="font-weight: 600; color: var(--hbt-primary); margin-bottom: 6px; display: block; font-size: 13px;"><?php esc_html_e( 'Maliyet Durumu:', 'hbt-trendyol-profit-tracker' ); ?></label>
                    <select name="has_cost" class="hbt-auto-submit" style="width: 100%; padding: 8px 12px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border);">
                        <option value="" <?php selected( $has_cost, '' ); ?>><?php esc_html_e( 'Tümü', 'hbt-trendyol-profit-tracker' ); ?></option>
                        <option value="1" <?php selected( $has_cost, 1 ); ?>><?php esc_html_e( 'Maliyeti Girilmiş Olanlar', 'hbt-trendyol-profit-tracker' ); ?></option>
                        <option value="0" <?php selected( $has_cost, 0 ); ?>><?php esc_html_e( 'Maliyeti Girilmemiş Olanlar', 'hbt-trendyol-profit-tracker' ); ?></option>
                    </select>
                </div>

                <div class="hbt-filter-group" style="flex: 1; min-width: 150px;">
                    <label style="font-weight: 600; color: var(--hbt-primary); margin-bottom: 6px; display: block; font-size: 13px;"><?php esc_html_e( 'Kategori:', 'hbt-trendyol-profit-tracker' ); ?></label>
                    <select name="category" class="hbt-auto-submit" style="width: 100%; padding: 8px 12px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border);">
                        <option value=""><?php esc_html_e( 'Tüm Kategoriler', 'hbt-trendyol-profit-tracker' ); ?></option>
                        <?php foreach ( $categories as $cat ) : ?>
                            <option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $selected_category, $cat ); ?>>
                                <?php echo esc_html( $cat ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>

            <!-- DataTables Anında (Canlı) Arama Kutusu -->
            <div class="hbt-filter-group" style="flex: 1; min-width: 250px;">
                <label style="font-weight: 600; color: var(--hbt-primary); margin-bottom: 6px; display: block; font-size: 13px;"><?php esc_html_e( 'Tabloda Hızlı Ara:', 'hbt-trendyol-profit-tracker' ); ?></label>
                <div style="position: relative;">
                    <span class="dashicons dashicons-search" style="position: absolute; left: 8px; top: 7px; color: #94a3b8;"></span>
                    <input type="text" id="hbt-live-search" class="regular-text" placeholder="<?php esc_attr_e( 'Barkod, ürün adı veya SKU...', 'hbt-trendyol-profit-tracker' ); ?>" style="width: 100%; padding: 8px 12px 8px 32px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border);">
                </div>
            </div>

		</div>
	</div>

	<div class="hbt-alert-box hbt-alert-warning" style="margin-bottom: 16px;">
		<span class="dashicons dashicons-info"></span> 
		<div><strong>Açık Sarı / Krem</strong> renkte görünen satırlar, maliyeti henüz sisteme girilmemiş (0.00 USD) ürünleri temsil eder. Kârın doğru hesaplanması için bu ürünleri düzenleyiniz.</div>
	</div>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
        <div class="hbt-bulk-actions" style="display: flex; gap: 8px; align-items: center;">
            <select id="hbt-bulk-action" style="padding: 4px 12px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border);">
                <option value=""><?php esc_html_e( 'Toplu İşlemler', 'hbt-trendyol-profit-tracker' ); ?></option>
                <option value="delete"><?php esc_html_e( 'Seçilenleri Sil', 'hbt-trendyol-profit-tracker' ); ?></option>
            </select>
            <button type="button" id="btn-apply-bulk" class="button action"><?php esc_html_e( 'Uygula', 'hbt-trendyol-profit-tracker' ); ?></button>
        </div>
    </div>

	<div class="hbt-card" style="padding: 0; overflow: hidden; margin-bottom: 24px;">
		<table class="wp-list-table widefat fixed striped" id="products-table" style="border: none; margin: 0; width:100%;">
			<thead>
				<tr>
                    <th style="width:40px; text-align:center; vertical-align:middle;">
                        <input type="checkbox" id="cb-select-all">
                    </th>
                    <th style="width:100px; text-align:center;">
    <span class="dashicons dashicons-format-image" title="Görsel" style="color:var(--hbt-text-muted);"></span>
</th>
					<th style="width:110px;"><?php esc_html_e( 'Mağaza', 'hbt-trendyol-profit-tracker' ); ?></th>
					<th style="width:auto;"><?php esc_html_e( 'Ürün Adı', 'hbt-trendyol-profit-tracker' ); ?></th>
					<th style="width:110px;"><?php esc_html_e( 'Barkod', 'hbt-trendyol-profit-tracker' ); ?></th>
					<th style="width:90px;"><?php esc_html_e( 'SKU', 'hbt-trendyol-profit-tracker' ); ?></th>
					<th style="width:100px;"><?php esc_html_e( 'Kategori', 'hbt-trendyol-profit-tracker' ); ?></th>
					<th style="width:80px;"><?php esc_html_e( 'Maliyet ($)', 'hbt-trendyol-profit-tracker' ); ?></th>
					<th style="width:60px;"><?php esc_html_e( 'Desi', 'hbt-trendyol-profit-tracker' ); ?></th>
					<th style="width:60px;"><?php esc_html_e( 'KDV (%)', 'hbt-trendyol-profit-tracker' ); ?></th>
					<th style="width:180px;"><?php esc_html_e( 'İşlemler', 'hbt-trendyol-profit-tracker' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $products ) ) : ?>
					<tr>
						<td colspan="11" style="text-align: center; padding: 40px 20px; color: var(--hbt-text-muted);">
							<span class="dashicons dashicons-products" style="font-size: 32px; width: 32px; height: 32px; margin-bottom: 10px; opacity: 0.5;"></span><br>
							<?php esc_html_e( 'Aradığınız kriterlere uygun ürün bulunamadı.', 'hbt-trendyol-profit-tracker' ); ?>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $products as $product ) : ?>
						<tr data-id="<?php echo esc_attr( (string) $product->id ); ?>"
							class="<?php echo (float) $product->cost_usd === 0.0 ? 'cost-missing' : ''; ?>">
							
                            <td style="text-align:center; vertical-align:middle;">
                                <input type="checkbox" class="cb-select-row" value="<?php echo esc_attr( (string) $product->id ); ?>">
                            </td>
                            
                            <td style="text-align:center; vertical-align:middle;">
    <?php if ( !empty($product->image_url) ) : ?>
<img src="<?php echo esc_url($product->image_url); ?>" class="hbt-zoomable-image" style="width:80px; height:80px; object-fit:contain; background:#fff; border-radius:6px; border:1px solid #e2e8f0; display:block; margin: 0 auto; cursor: zoom-in;" title="Büyütmek için tıklayın">    <?php else : ?>
        <div style="width:80px; height:80px; background:#f1f5f9; border-radius:6px; display:flex; align-items:center; justify-content:center; border:1px solid #e2e8f0; margin: 0 auto; color:#94a3b8;">
            <span class="dashicons dashicons-format-image" style="font-size: 28px; width: 28px; height: 28px;"></span>
        </div>
    <?php endif; ?>
</td>

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

							<td style="vertical-align:middle; font-weight: 500;" data-search="<?php echo esc_attr( $product->product_name ); ?>">
								<?php
								$p_name = $product->product_name;
								// Türkçe karakter desteğiyle ilk 50 karakteri al
								$p_short = mb_strlen($p_name) > 50 ? mb_substr($p_name, 0, 50) . '...' : $p_name;
								?>
								<span class="hbt-inline-display hbt-product-name-toggle" data-full="<?php echo esc_attr( $p_name ); ?>" style="cursor: pointer; color: var(--hbt-text-main);" title="Tamamını okumak için tıklayın"><?php echo esc_html( $p_short ); ?></span>
								<input type="text" class="hbt-inline-input" name="product_name" value="<?php echo esc_attr( $p_name ); ?>" style="display:none;width:100%;">
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

	<!-- Toplu İçe Aktarım & Şablonlar -->
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

    <!-- Modallar (Değiştirilmedi, aynı duruyor) -->
    <!-- ... Import Modal ... -->
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

    <!-- ... Ürün Ekle Modal ... -->
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

/* --- DATATABLES TAM TÜRKÇELEŞTİRME VE TASARIM (Overlap Fix) --- */

/* DataTables'ın varsayılan "Search" kutusunu gizliyoruz, yerine kendi özel kutumuzu koyduk */
.dataTables_filter { display: none !important; }

/* Show X entries (Sayfada X Kayıt Göster) bölümü ok kayması düzeltmesi */
div.dataTables_wrapper div.dataTables_length select {
    width: auto !important;
    display: inline-block !important;
    padding: 4px 28px 4px 8px !important; /* Ok işareti için sağdan geniş boşluk bırakır */
    
    /* Native ok işaretini silip bizim özel okumuzu koyar (iç içe girmeyi önler) */
    -webkit-appearance: none !important; 
    -moz-appearance: none !important;
    appearance: none !important;
    background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23131313%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E") !important;
    background-repeat: no-repeat !important;
    background-position: right 8px center !important;
    background-size: 10px !important;
    
    border: 1px solid #cbd5e1 !important;
    border-radius: 4px !important;
    height: auto !important;
}

div.dataTables_wrapper div.dataTables_length label {
    font-weight: 500 !important;
    color: #475569 !important;
    display: flex;
    align-items: center;
    gap: 8px; /* Select kutusu ile metin arasına temiz boşluk */
}

div.dataTables_wrapper div.dataTables_info {
    color: #475569 !important;
    padding-top: 15px;
}
</style>

<script>
jQuery(document).ready(function($) {
    
    // Form Select değiştiğinde anında sayfayı yenileyip sonuçları getirir (Auto-Submit)
    $('.hbt-auto-submit').on('change', function() {
        $('#hbt-auto-filter-form').submit();
    });

    // DATATABLES TÜRKÇE BAŞLATMA VE CANLI ARAMA BAĞLANTISI
    // Eğer DataTables mevcutsa önce onu yıkıyoruz, sonra Türkçe ayarlarla tekrar kuruyoruz
    if ($.fn.DataTable) {
        if ($.fn.DataTable.isDataTable('#products-table')) {
            $('#products-table').DataTable().destroy();
        }
        
        var table = $('#products-table').DataTable({
            language: {
                "sDecimal":        ",",
                "sEmptyTable":     "Tabloda herhangi bir veri mevcut değil",
                "sInfo":           "_TOTAL_ kayıttan _START_ - _END_ arasındaki kayıtlar gösteriliyor",
                "sInfoEmpty":      "Kayıt yok",
                "sInfoFiltered":   "(_MAX_ kayıt içerisinden filtrelendi)",
                "sInfoPostFix":    "",
                "sInfoThousands":  ".",
                "sLengthMenu":     "Sayfada _MENU_ kayıt göster",
                "sLoadingRecords": "Yükleniyor...",
                "sProcessing":     "İşleniyor...",
                "sSearch":         "Ara:",
                "sZeroRecords":    "Eşleşen kayıt bulunamadı",
                "oPaginate": {
                    "sFirst":    "İlk",
                    "sLast":     "Son",
                    "sNext":     "Sonraki",
                    "sPrevious": "Önceki"
                },
                "oAria": {
                    "sSortAscending":  ": artan sütun sıralamasını aktifleştir",
                    "sSortDescending": ": azalan sütun sıralamasını aktifleştir"
                }
            },
            pageLength: 50,
            columnDefs: [
                { orderable: false, targets: [0, 1, 10] } // Checkbox, Görsel ve İşlemler sıralanamaz
            ],
            dom: '<"top"l>rt<"bottom"ip><"clear">' // 'f' (Filter/Search) varsayılan kutusunu gizler
        });

        // Kendi oluşturduğumuz modern canlı arama kutusunu DataTables'a bağlıyoruz
        $('#hbt-live-search').on('keyup', function() {
            table.search(this.value).draw();
        });
    }

    // Tümünü Seç (Select All) Checkbox İşlemi
    $('#cb-select-all').on('change', function() {
        $('.cb-select-row').prop('checked', $(this).prop('checked'));
    });

    // Toplu Silme Butonu (Uygula) - Onay Mekanizmalı
    $('#btn-apply-bulk').on('click', function() {
        var action = $('#hbt-bulk-action').val();
        var selected = [];
        
        $('.cb-select-row:checked').each(function() {
            selected.push($(this).val());
        });

        if (selected.length === 0) {
            alert('Lütfen işlem yapmak için en az bir ürün seçin.');
            return;
        }

        if (action === 'delete') {
            // Güvenlik Onayı Burada Başlıyor
            if (confirm(selected.length + ' adet ürünü kalıcı olarak silmek istediğinize emin misiniz?')) {
                var $btn = $(this);
                $btn.prop('disabled', true).text('İşleniyor...');
                
                var promises = [];
                $.each(selected, function(index, id) {
                    var $row = $('tr[data-id="' + id + '"]');
                    var p = $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'hbt_delete_product_cost', 
                            id: id,
                            security: hbt_admin_vars.nonce || '' 
                        }
                    }).done(function() {
                        $row.fadeOut(300, function(){ $(this).remove(); });
                    });
                    promises.push(p);
                });

                $.when.apply($, promises).always(function() {
                    $btn.prop('disabled', false).text('Uygula');
                    $('#cb-select-all').prop('checked', false);
                    setTimeout(function(){
                        location.reload(); 
                    }, 800);
                });
            }
        }
    });

	// 1. TRENDYOL'DAN ÇEK BUTONU
    $('#btn-sync-products').on('click', function(e) {
        e.preventDefault();
        var storeId = $('select[name="store_id"]').val();
        var confirmMsg = (storeId === "0" || storeId === "") 
            ? 'Tüm mağazaların ürünleri Trendyol\'dan çekilecek. Daha önce girdiğiniz maliyetler (USD), KDV ve Desi oranlarınız SİLİNMEYECEKTİR. Devam edilsin mi?'
            : 'Seçili mağazanın ürünleri Trendyol\'dan çekilecek. Devam edilsin mi?';
            
        if (confirm(confirmMsg)) {
            var $btn = $(this);
            var originalText = $btn.html();
            // Butona dönen bir animasyon ekleyip tıklanmasını engelleyelim
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update" style="animation: hbt-spin 2s linear infinite;"></span> Yükleniyor...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hbt_sync_products',
                    store_id: (storeId === "0" ? "" : storeId),
                    nonce: (typeof hbtTpt !== 'undefined' ? hbtTpt.nonce : '')
                },
                success: function(res) {
                    if(res.success) {
                        alert(res.data.message || 'Ürünler başarıyla güncellendi!');
                        location.reload();
                    } else {
                        alert('Hata: ' + (res.data.message || 'Bir hata oluştu.'));
                        $btn.prop('disabled', false).html(originalText);
                    }
                },
                error: function() {
                    alert('Sunucu ile bağlantı kurulamadı veya zaman aşımına uğradı.');
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        }
    });

    // 2. TOPLU İMPORT MODAL İŞLEMLERİ
    $('#btn-import-csv').on('click', function() {
        $('#import-modal').fadeIn(200);
    });
    
    $('#csv-drop-zone').on('click', function() {
        $('#csv-file-input').click();
    });
    
    $('#csv-file-input').on('change', function() {
        var file = this.files[0];
        if (file) {
            $('#csv-filename').text("Seçilen Dosya: " + file.name);
        }
    });

    $('#btn-do-import').on('click', function() {
        var fileInput = $('#csv-file-input')[0];
        var storeId = $('#import-store-id').val();
        
        if(!storeId) { alert("Lütfen içe aktarılacak mağazayı seçin."); return; }
        if(fileInput.files.length === 0) { alert("Lütfen bir CSV veya XLS dosyası seçin."); return; }

        var formData = new FormData();
        formData.append('action', 'hbt_bulk_import_costs');
        formData.append('store_id', storeId);
        formData.append('csv_file', fileInput.files[0]);
        formData.append('nonce', (typeof hbtTpt !== 'undefined' ? hbtTpt.nonce : ''));

        var $btn = $(this);
        $btn.prop('disabled', true).text('İçe Aktarılıyor...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                if(res.success) {
                    alert(res.data.message || "İçe aktarım tamamlandı.");
                    location.reload();
                } else {
                    alert("Hata: " + (res.data.message || "Bilinmeyen hata"));
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-cloud-upload"></span> İçe Aktarımı Başlat');
                }
            },
            error: function() {
                alert("Yükleme sırasında hata oluştu. Dosya boyutunu kontrol edin.");
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-cloud-upload"></span> İçe Aktarımı Başlat');
            }
        });
    });

    // 3. MANUEL ÜRÜN EKLEME
    $('#btn-add-product').on('click', function() {
        $('#product-form')[0].reset();
        $('#product-id').val('');
        $('#product-modal').fadeIn(200);
    });

    $('#btn-save-product').on('click', function() {
        var data = $('#product-form').serialize() + '&action=hbt_save_product_cost&nonce=' + (typeof hbtTpt !== 'undefined' ? hbtTpt.nonce : '');
        var $btn = $(this);
        $btn.prop('disabled', true).text('Kaydediliyor...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: data,
            success: function(res) {
                if(res.success) {
                    location.reload();
                } else {
                    alert("Hata: " + (res.data.message || "Bilinmeyen hata"));
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Ürünü Kaydet');
                }
            },
            error: function() {
                alert("Bağlantı hatası.");
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Ürünü Kaydet');
            }
        });
    });

    // 4. SATIR İÇİ (INLINE) DÜZENLEME VE KAYDETME
    $(document).on('click', '.btn-inline-edit', function() {
        var $row = $(this).closest('tr');
        $row.find('.hbt-inline-display').hide();
        $row.find('.hbt-inline-input').show();
        $(this).hide();
        $row.find('.btn-inline-save, .btn-inline-cancel').show();
    });

    $(document).on('click', '.btn-inline-cancel', function() {
        var $row = $(this).closest('tr');
        $row.find('.hbt-inline-input').hide();
        $row.find('.hbt-inline-display').show();
        $(this).hide();
        $row.find('.btn-inline-save').hide();
        $row.find('.btn-inline-edit').show();
    });

    $(document).on('click', '.btn-inline-save', function() {
        var $row = $(this).closest('tr');
        var id = $(this).data('id');
        
        var data = {
            action: 'hbt_save_product_cost',
            id: id,
            store_id: $row.find('select[name="store_id"]').val(),
            product_name: $row.find('input[name="product_name"]').val(),
            barcode: $row.find('input[name="barcode"]').val(),
            sku: $row.find('input[name="sku"]').val(),
            category_name: $row.find('input[name="category_name"]').val(),
            cost_usd: $row.find('input[name="cost_usd"]').val(),
            desi: $row.find('input[name="desi"]').val(),
            vat_rate: $row.find('input[name="vat_rate"]').val(),
            nonce: (typeof hbtTpt !== 'undefined' ? hbtTpt.nonce : '')
        };

        var $btn = $(this);
        $btn.prop('disabled', true).text('...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: data,
            success: function(res) {
                if (res.success) {
                    location.reload();
                } else {
                    alert('Hata: ' + (res.data.message || ''));
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Kaydet');
                }
            },
            error: function() {
                alert('Bağlantı hatası.');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Kaydet');
            }
        });
    });

    // (Eğer yoksa) Yükleniyor ikonu için mini animasyon stili
    $('<style>@keyframes hbt-spin { 100% { transform: rotate(360deg); } }</style>').appendTo('head');

    // Inline Kaydet Butonu için Mağaza İsmi Güncelleme
    $(document).on('change', 'select[name="store_id"].hbt-inline-input', function() {
        var newStoreId = $(this).val();
        var $saveBtn = $(this).closest('tr').find('.btn-inline-save');
        $saveBtn.data('store', newStoreId).attr('data-store', newStoreId);
    });

    $(document).on('click', '.btn-inline-save', function() {
        var $row = $(this).closest('tr');
        var selectedText = $row.find('select[name="store_id"] option:selected').text().trim();
        $row.find('.hbt-store-text').text(selectedText);
    });
});
</script>