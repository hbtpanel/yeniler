<?php
/**
 * Plus Simulator View.
 *
 * @package HBT_Trendyol_Profit_Tracker
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

// Kütüphaneyi dahil et
$simplexlsx_path = HBT_TPT_PLUGIN_DIR . 'includes/class-simplexlsx.php';
if ( file_exists( $simplexlsx_path ) ) {
    require_once $simplexlsx_path;
}

// Aktif mağazaları çek
$stores = $wpdb->get_results( "SELECT id, store_name FROM {$wpdb->prefix}hbt_stores WHERE is_active = 1" );

$simulation_results = [];
$error_message = '';

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_FILES['plus_excel'] ) && wp_verify_nonce( $_POST['plus_simulator_nonce'], 'run_plus_simulation' ) ) {
    
    $store_id = intval( $_POST['store_id'] );
    $file = $_FILES['plus_excel'];
    $file_ext = pathinfo( $file['name'], PATHINFO_EXTENSION );
    
    if ( in_array( strtolower( $file_ext ), ['xlsx', 'csv', 'xls'] ) && $file['error'] == 0 ) {
        
        $rows = [];
        $file_path = $file['tmp_name'];

        // 1. ANA MOTOR: Gerçek ve hatasız Excel dosyaları için SimpleXLSX
        if ( class_exists( '\Shuchkin\SimpleXLSX' ) ) {
            $xlsx = \Shuchkin\SimpleXLSX::parse( $file_path );
            if ( $xlsx ) $rows = $xlsx->rows();
        } elseif ( class_exists( 'SimpleXLSX' ) ) {
            $xlsx = SimpleXLSX::parse( $file_path );
            if ( $xlsx ) $rows = $xlsx->rows();
        }

        // 2. YEDEK MOTOR: Brute-Force ZIP Extractor (Trendyol'un eksik/hatalı XLSX'leri için kesin çözüm)
        if ( empty($rows) && substr(file_get_contents($file_path, false, null, 0, 4), 0, 4) === "PK\x03\x04" ) {
            if (class_exists('ZipArchive')) {
                $zip = new ZipArchive();
                if ($zip->open($file_path) === TRUE) {
                    $shared_strings = [];
                    
                    // Metin Havuzunu (sharedStrings) Çıkar
                    $strings_xml = $zip->getFromName('xl/sharedStrings.xml');
                    if ($strings_xml) {
                        $xml = simplexml_load_string($strings_xml);
                        if ($xml && isset($xml->si)) {
                            foreach ($xml->si as $si) {
                                $text = '';
                                if (isset($si->t)) {
                                    $text = (string) $si->t;
                                } elseif (isset($si->r)) {
                                    foreach ($si->r as $r) {
                                        if (isset($r->t)) $text .= (string) $r->t;
                                    }
                                }
                                $shared_strings[] = $text;
                            }
                        }
                    }

                    // Ham Excel Verisini (sheet1) Çıkar ve Eşleştir
                    $sheet_xml = $zip->getFromName('xl/worksheets/sheet1.xml');
                    if ($sheet_xml) {
                        $xml = simplexml_load_string($sheet_xml);
                        if ($xml && isset($xml->sheetData->row)) {
                            foreach ($xml->sheetData->row as $row_data_xml) {
                                $row_data = [];
                                foreach ($row_data_xml->c as $c) {
                                    $val = (string) $c->v;
                                    if (isset($c['t']) && (string)$c['t'] === 's') {
                                        $val = isset($shared_strings[(int)$val]) ? $shared_strings[(int)$val] : $val;
                                    } elseif (isset($c['t']) && (string)$c['t'] === 'inlineStr') {
                                        if (isset($c->is->t)) $val = (string)$c->is->t;
                                    }
                                    
                                    // Sütun kaymalarını önlemek için gerçek harf pozisyonunu bul (A=0, B=1)
                                    $coord = (string) $c['r'];
                                    preg_match('/[A-Z]+/', $coord, $match);
                                    $col_letters = $match[0] ?? 'A';
                                    $col_idx = 0;
                                    $len = strlen($col_letters);
                                    for($i=0; $i<$len; $i++){
                                        $col_idx = $col_idx * 26 + (ord($col_letters[$i]) - 64);
                                    }
                                    $col_idx -= 1;
                                    
                                    $row_data[$col_idx] = $val;
                                }
                                if (!empty($row_data)) {
                                    $max_idx = max(array_keys($row_data));
                                    $normalized_row = [];
                                    for ($i = 0; $i <= $max_idx; $i++) {
                                        $normalized_row[] = isset($row_data[$i]) ? $row_data[$i] : '';
                                    }
                                    $rows[] = $normalized_row;
                                }
                            }
                        }
                    }
                    $zip->close();
                }
            }
        }

        // 3. ÜÇÜNCÜ MOTOR: Eğer dosya gerçekten CSV ise (Excel/ZIP değilse)
        if ( empty($rows) && substr(file_get_contents($file_path, false, null, 0, 2), 0, 2) !== "PK" ) {
            $content = file_get_contents( $file_path );
            if ( substr( $content, 0, 2 ) === "\xFF\xFE" || strpos($content, "\x00") !== false ) {
                $content = mb_convert_encoding( $content, 'UTF-8', 'UTF-16LE' );
            } else {
                $content = preg_replace("/^\xEF\xBB\xBF/", '', $content); 
            }
            
            $lines = explode( "\n", $content );
            if ( count( $lines ) > 1 ) {
                $delimiter = "\t";
                if ( strpos( $lines[0], "\t" ) === false ) {
                    $delimiter = ( strpos( $lines[0], ';' ) !== false ) ? ';' : ',';
                }
                foreach ( $lines as $line ) {
                    $line = trim( $line );
                    if ( empty( $line ) ) continue;
                    $rows[] = str_getcsv( $line, $delimiter );
                }
            }
        }

        // --- VERİLERİ İŞLEME VE MATEMATİKSEL HESAPLAMALAR ---
        if ( ! empty( $rows ) && count( $rows ) > 1 ) {
            $header = array_shift( $rows ); // Başlıkları al
            
            // DİNAMİK SÜTUN TESPİTİ
            $b_idx = 2; $f_idx = 9; $k_idx = 11; $pf_idx = 12; $pk_idx = 13;
            foreach ($header as $index => $col_name) {
                $col_name = trim($col_name);
                if ($col_name === 'Barkod') $b_idx = $index;
                elseif ($col_name === 'Güncel TSF') $f_idx = $index;
                elseif ($col_name === 'Güncel Komisyon') $k_idx = $index;
                elseif ($col_name === 'Plus Fiyat Üst Limiti') $pf_idx = $index;
                elseif ($col_name === 'Plus Komisyon Teklifi') $pk_idx = $index;
            }
            
            $usd_rate = (float) $wpdb->get_var( "SELECT buying_rate FROM {$wpdb->prefix}hbt_currency_rates ORDER BY rate_date DESC LIMIT 1" ) ?: 33.00;
            
            // SABİT GİDERLERİN DOĞRU ŞEKİLDE ÇEKİLMESİ VE TOPLANMASI
            $fixed_costs_opt = get_option( 'hbt_fixed_costs', array() );
            $store_fc = $fixed_costs_opt[ $store_id ] ?? array();
            
            // Personel, Paketleme ve Diğer giderleri toplayarak sipariş başına düşen toplam sabit gideri bul
            $personnel_cost = (float) ($store_fc['personnel'] ?? 0);
            $packaging_cost = (float) ($store_fc['packaging'] ?? 0);
            $other_cost     = (float) ($store_fc['other'] ?? 0);
            $order_fixed_cost = $personnel_cost + $packaging_cost + $other_cost;
            
            foreach ( $rows as $data ) {
                if ( ! isset( $data[$b_idx], $data[$f_idx], $data[$k_idx], $data[$pf_idx], $data[$pk_idx] ) ) continue; 
                
                $barkod = preg_replace('/[^a-zA-Z0-9_-]/', '', $data[$b_idx]);
                
                $guncel_fiyat = floatval( str_replace( ',', '.', $data[$f_idx] ) );
                $guncel_komisyon_orani = floatval( str_replace( ',', '.', $data[$k_idx] ) );
                $plus_fiyat = floatval( str_replace( ',', '.', $data[$pf_idx] ) );
                $plus_komisyon_orani = floatval( str_replace( ',', '.', $data[$pk_idx] ) );
                
                if ( empty( $barkod ) || $plus_fiyat <= 0 ) continue;

                $product = $wpdb->get_row( $wpdb->prepare( "SELECT product_name, cost_usd FROM {$wpdb->prefix}hbt_product_costs WHERE barcode = %s AND store_id = %d LIMIT 1", $barkod, $store_id ) );
                
                $urun_isim = $product ? $product->product_name : sanitize_text_field( $data[0] );
                $urun_maliyet_tl = $product ? (float) $product->cost_usd * $usd_rate : 0;
                $maliyet_uyarisi = $product ? '' : ' <span style="color:#d63638; font-size:11px; font-weight:bold; background:#fcf0f1; padding:2px 4px; border-radius:3px;">(Eşleşmedi / Maliyet 0)</span>';
                
                $guncel_kargo = (float) $wpdb->get_var( $wpdb->prepare( "SELECT cost_tl FROM {$wpdb->prefix}hbt_shipping_costs WHERE store_id = %d AND price_min <= %f AND (price_max >= %f OR price_max IS NULL) ORDER BY id DESC LIMIT 1", $store_id, $guncel_fiyat, $guncel_fiyat ) );
                $plus_kargo = (float) $wpdb->get_var( $wpdb->prepare( "SELECT cost_tl FROM {$wpdb->prefix}hbt_shipping_costs WHERE store_id = %d AND price_min <= %f AND (price_max >= %f OR price_max IS NULL) ORDER BY id DESC LIMIT 1", $store_id, $plus_fiyat, $plus_fiyat ) );

                $guncel_komisyon_tutari = ( $guncel_fiyat * $guncel_komisyon_orani ) / 100;
                $guncel_toplam_maliyet = $urun_maliyet_tl + $guncel_kargo + $order_fixed_cost + $guncel_komisyon_tutari;
                $guncel_kar_tl = $guncel_fiyat - $guncel_toplam_maliyet;
                $guncel_kar_orani = $guncel_fiyat > 0 ? ( $guncel_kar_tl / $guncel_fiyat ) * 100 : 0;

                $plus_komisyon_tutari = ( $plus_fiyat * $plus_komisyon_orani ) / 100;
                $plus_toplam_maliyet = $urun_maliyet_tl + $plus_kargo + $order_fixed_cost + $plus_komisyon_tutari;
                $plus_kar_tl = $plus_fiyat - $plus_toplam_maliyet;
                $plus_kar_orani = $plus_fiyat > 0 ? ( $plus_kar_tl / $plus_fiyat ) * 100 : 0;

                $color_class = '';
                if ( $plus_kar_orani >= 30 ) $color_class = 'blink-green';
                elseif ( $plus_kar_orani >= 20 && $plus_kar_orani < 30 ) $color_class = 'blink-yellow';
                elseif ( $plus_kar_orani >= 10 && $plus_kar_orani < 20 ) $color_class = 'blink-dark-red';
                else $color_class = 'blink-light-red';

                $simulation_results[] = [
                    'isim' => $urun_isim . $maliyet_uyarisi,
                    'barkod' => $barkod,
                    'urun_maliyet_tl' => $urun_maliyet_tl,
                    'guncel_fiyat' => $guncel_fiyat,
                    'guncel_komisyon_orani' => $guncel_komisyon_orani,
                    'guncel_komisyon_tutari' => $guncel_komisyon_tutari,
                    'guncel_kargo' => $guncel_kargo,
                    'guncel_gider' => $order_fixed_cost,
                    'guncel_kar_tl' => $guncel_kar_tl,
                    'guncel_kar_orani' => $guncel_kar_orani,
                    'plus_fiyat' => $plus_fiyat,
                    'plus_komisyon_orani' => $plus_komisyon_orani,
                    'plus_komisyon_tutari' => $plus_komisyon_tutari,
                    'plus_kargo' => $plus_kargo,
                    'plus_gider' => $order_fixed_cost,
                    'plus_kar_tl' => $plus_kar_tl,
                    'plus_kar_orani' => $plus_kar_orani,
                    'color_class' => $color_class
                ];
            }
            
            if (empty($simulation_results)) {
                $error_message = 'Dosya başarıyla okundu ancak eşleşen ürün bulunamadı.';
            }
        } else {
            $error_message = 'Dosya yapısı çözülemedi veya boş bir dosya yüklediniz.';
        }
    } else {
        $error_message = 'Lütfen geçerli bir Excel (.xlsx) veya CSV dosyası yükleyin.';
    }
}
?>

<style>
    .hbt-simulator-wrap { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
    .hbt-filter-bar { background: #fff; padding: 15px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; gap: 15px; flex-wrap: wrap; }
    .hbt-filter-bar input, .hbt-filter-bar select { padding: 8px; border: 1px solid #ddd; border-radius: 4px; flex: 1; min-width: 200px; }
    .hbt-cards-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(400px, 1fr)); gap: 20px; }
    .hbt-card { background: #fff; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); overflow: hidden; display: flex; flex-direction: column; transition: transform 0.2s; border: 1px solid #e2e4e7; }
    .hbt-card:hover { transform: translateY(-3px); box-shadow: 0 4px 10px rgba(0,0,0,0.15); }
    .hbt-card-header { padding: 15px; background: #2c3338; color: #fff; }
    .hbt-card-header h3 { margin: 0 0 5px 0; color: #fff; font-size: 14px; font-weight: 600; line-height: 1.4; }
    .hbt-card-header small { color: #ccc; font-size: 12px; }
    .hbt-card-body { display: flex; flex: 1; }
    .hbt-half { padding: 15px; flex: 1; display: flex; flex-direction: column; }
    .hbt-half.current { border-right: 1px dashed #ddd; background: #fafafa; }
    .hbt-stat-row { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 8px; border-bottom: 1px solid #f0f0f0; padding-bottom: 4px; }
    .hbt-stat-row span:last-child { font-weight: 600; }
    .hbt-profit-result { margin-top: auto; padding: 10px; border-radius: 6px; text-align: center; }
    .hbt-profit-result .amount { font-size: 18px; font-weight: bold; display: block; margin-top: 5px; }
    .hbt-profit-result .margin { font-size: 13px; }
    @keyframes blinkGreen { 0% { background-color: #e8f5e9; box-shadow: 0 0 5px #4caf50; } 50% { background-color: #c8e6c9; box-shadow: 0 0 15px #4caf50; } 100% { background-color: #e8f5e9; box-shadow: 0 0 5px #4caf50; } }
    @keyframes blinkYellow { 0% { background-color: #fffde7; box-shadow: 0 0 5px #ffeb3b; } 50% { background-color: #fff9c4; box-shadow: 0 0 15px #ffeb3b; } 100% { background-color: #fffde7; box-shadow: 0 0 5px #ffeb3b; } }
    @keyframes blinkDarkRed { 0% { background-color: #ffebee; box-shadow: 0 0 5px #f44336; } 50% { background-color: #ffcdd2; box-shadow: 0 0 15px #f44336; } 100% { background-color: #ffebee; box-shadow: 0 0 5px #f44336; } }
    @keyframes blinkLightRed { 0% { background-color: #fafafa; } 50% { background-color: #ffebee; color: #d32f2f; } 100% { background-color: #fafafa; } }
    .blink-green .hbt-profit-result { animation: blinkGreen 2s infinite; color: #2e7d32; border: 1px solid #4caf50; }
    .blink-yellow .hbt-profit-result { animation: blinkYellow 2s infinite; color: #f57f17; border: 1px solid #fbc02d; }
    .blink-dark-red .hbt-profit-result { animation: blinkDarkRed 2s infinite; color: #c62828; border: 1px solid #e53935; }
    .blink-light-red .hbt-profit-result { animation: blinkLightRed 1.5s infinite; color: #d32f2f; border: 1px dashed #d32f2f; }
</style>

<div class="wrap hbt-simulator-wrap">
    <h1>Trendyol Plus Komisyon Simülatörü</h1>
    <p>💡 <b>İpucu:</b> Trendyol'dan indirdiğiniz dosyayı direkt yükleyebilirsiniz. Özel "Brute-Force" kurtarma motoru sayesinde hatasız şekilde okunacaktır.</p>
    
    <?php if ( ! empty( $error_message ) ) : ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error_message ); ?></p></div>
    <?php endif; ?>

    <div style="background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid #2271b1;">
        <form method="post" enctype="multipart/form-data" style="display: flex; gap: 20px; align-items: flex-end; flex-wrap: wrap;">
            <?php wp_nonce_field('run_plus_simulation', 'plus_simulator_nonce'); ?>
            <div>
                <label style="display:block; margin-bottom:5px;"><strong>Mağaza Seçin:</strong></label>
                <select name="store_id" required style="min-width: 200px;">
                    <?php 
                    if( ! empty( $stores ) ) {
                        foreach ( $stores as $store ) {
                            echo '<option value="' . esc_attr( $store->id ) . '">' . esc_html( $store->store_name ) . '</option>';
                        }
                    } else {
                        echo '<option value="">Mağaza Bulunamadı</option>';
                    }
                    ?>
                </select>
            </div>
            <div>
                <label style="display:block; margin-bottom:5px;"><strong>Trendyol Plus Dosyası (.xlsx):</strong></label>
                <input type="file" name="plus_excel" accept=".xlsx, .csv, .xls" required style="padding: 3px;">
            </div>
            <div>
                <button type="submit" class="button button-primary button-hero">Simülasyonu Başlat</button>
            </div>
        </form>
    </div>

    <?php if ( ! empty( $simulation_results ) ) : ?>
        <div class="hbt-filter-bar">
            <input type="text" id="filterName" placeholder="🔍 Ürün Adı ile ara...">
            <input type="text" id="filterBarcode" placeholder="🔍 Barkod ile ara...">
            <select id="filterMargin">
                <option value="all">📊 Tüm Kâr Oranları (Filtreleme)</option>
                <option value="green">🟢 Kâr %30 ve Üzeri (Süper)</option>
                <option value="yellow">🟡 Kâr %20 - %29.99 (İyi)</option>
                <option value="darkred">🔴 Kâr %10 - %19.99 (Riskli)</option>
                <option value="lightred">⭕ Kâr %10 Altı (Zarar/Kötü)</option>
            </select>
        </div>

        <div class="hbt-cards-container" id="cardsContainer">
            <?php foreach ( $simulation_results as $row ) : ?>
                <div class="hbt-card" data-name="<?php echo esc_attr( strtolower( strip_tags($row['isim']) ) ); ?>" data-barcode="<?php echo esc_attr( $row['barkod'] ); ?>" data-color="<?php echo esc_attr( $row['color_class'] ); ?>">
                    <div class="hbt-card-header">
                        <h3><?php echo wp_kses_post( $row['isim'] ); ?></h3>
                        <small>Barkod: <?php echo esc_html( $row['barkod'] ); ?></small>
                    </div>
                    <div class="hbt-card-body">
                        <div class="hbt-half current">
                            <h4 style="margin:0 0 15px 0; color:#444; text-align:center; font-size: 14px;">Mevcut Satış</h4>
                            <div class="hbt-stat-row"><span>Satış Fiyatı:</span> <span><?php echo number_format( $row['guncel_fiyat'], 2 ); ?> ₺</span></div>
                            <div class="hbt-stat-row"><span>Ürün Maliyeti:</span> <span>-<?php echo number_format( $row['urun_maliyet_tl'], 2 ); ?> ₺</span></div>
                            <div class="hbt-stat-row"><span>Komisyon (%<?php echo $row['guncel_komisyon_orani']; ?>):</span> <span>-<?php echo number_format( $row['guncel_komisyon_tutari'], 2 ); ?> ₺</span></div>
                            <div class="hbt-stat-row"><span>Kargo Maliyeti:</span> <span>-<?php echo number_format( $row['guncel_kargo'], 2 ); ?> ₺</span></div>
                            <div class="hbt-stat-row"><span>Sabit Giderler:</span> <span>-<?php echo number_format( $row['guncel_gider'], 2 ); ?> ₺</span></div>
                            <div class="hbt-profit-result" style="background: #e9ecef; border: 1px solid #ced4da; color: #495057;">
                                <span class="margin">Kâr Oranı: <strong>%<?php echo number_format( $row['guncel_kar_orani'], 2 ); ?></strong></span>
                                <span class="amount"><?php echo number_format( $row['guncel_kar_tl'], 2 ); ?> ₺</span>
                            </div>
                        </div>
                        <div class="hbt-half <?php echo $row['color_class']; ?>">
                            <h4 style="margin:0 0 15px 0; color:#d63638; text-align:center; font-size: 14px;">🔥 Trendyol Plus</h4>
                            <div class="hbt-stat-row"><span>Plus Fiyatı:</span> <span><?php echo number_format( $row['plus_fiyat'], 2 ); ?> ₺</span></div>
                            <div class="hbt-stat-row"><span>Ürün Maliyeti:</span> <span>-<?php echo number_format( $row['urun_maliyet_tl'], 2 ); ?> ₺</span></div>
                            <div class="hbt-stat-row"><span>Komisyon (%<?php echo $row['plus_komisyon_orani']; ?>):</span> <span>-<?php echo number_format( $row['plus_komisyon_tutari'], 2 ); ?> ₺</span></div>
                            <div class="hbt-stat-row"><span>Kargo Maliyeti:</span> <span>-<?php echo number_format( $row['plus_kargo'], 2 ); ?> ₺</span></div>
                            <div class="hbt-stat-row"><span>Sabit Giderler:</span> <span>-<?php echo number_format( $row['plus_gider'], 2 ); ?> ₺</span></div>
                            <div class="hbt-profit-result">
                                <span class="margin">Yeni Kâr: <strong>%<?php echo number_format( $row['plus_kar_orani'], 2 ); ?></strong></span>
                                <span class="amount"><?php echo number_format( $row['plus_kar_tl'], 2 ); ?> ₺</span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const filterName = document.getElementById('filterName');
                const filterBarcode = document.getElementById('filterBarcode');
                const filterMargin = document.getElementById('filterMargin');
                const cards = document.querySelectorAll('.hbt-card');

                function filterCards() {
                    const nameVal = filterName.value.toLowerCase();
                    const barcodeVal = filterBarcode.value.toLowerCase();
                    const marginVal = filterMargin.value;

                    cards.forEach(card => {
                        const cardName = card.getAttribute('data-name');
                        const cardBarcode = card.getAttribute('data-barcode').toLowerCase();
                        const cardColor = card.getAttribute('data-color');
                        
                        let matchName = cardName.includes(nameVal);
                        let matchBarcode = cardBarcode.includes(barcodeVal);
                        let matchMargin = true;

                        if (marginVal !== 'all') {
                            if (marginVal === 'green' && cardColor !== 'blink-green') matchMargin = false;
                            if (marginVal === 'yellow' && cardColor !== 'blink-yellow') matchMargin = false;
                            if (marginVal === 'darkred' && cardColor !== 'blink-dark-red') matchMargin = false;
                            if (marginVal === 'lightred' && cardColor !== 'blink-light-red') matchMargin = false;
                        }

                        if (matchName && matchBarcode && matchMargin) {
                            card.style.display = 'flex';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                }

                filterName.addEventListener('input', filterCards);
                filterBarcode.addEventListener('input', filterCards);
                filterMargin.addEventListener('change', filterCards);
            });
        </script>
    <?php endif; ?>
</div>