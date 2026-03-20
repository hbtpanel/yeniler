<?php
/**
 * Avantajlı Etiketler (Yıldızlı Ürünler) Simülatörü View.
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

// YENİLMEZ (BRUTE-FORCE) EXCEL/CSV OKUMA MOTORU FONKSİYONU
if (!function_exists('hbt_parse_trendyol_file')) {
    function hbt_parse_trendyol_file($file_path) {
        $rows = [];
        // 1. ANA MOTOR (SimpleXLSX)
        if ( class_exists( '\Shuchkin\SimpleXLSX' ) ) {
            $xlsx = \Shuchkin\SimpleXLSX::parse( $file_path );
            if ( $xlsx ) $rows = $xlsx->rows();
        } elseif ( class_exists( 'SimpleXLSX' ) ) {
            $xlsx = SimpleXLSX::parse( $file_path );
            if ( $xlsx ) $rows = $xlsx->rows();
        }

        // 2. YEDEK MOTOR (Brute-Force ZIP Extractor)
        if ( empty($rows) && substr(file_get_contents($file_path, false, null, 0, 4), 0, 4) === "PK\x03\x04" ) {
            if (class_exists('ZipArchive')) {
                $zip = new ZipArchive();
                if ($zip->open($file_path) === TRUE) {
                    $shared_strings = [];
                    $strings_xml = $zip->getFromName('xl/sharedStrings.xml');
                    if ($strings_xml) {
                        $xml = simplexml_load_string($strings_xml);
                        if ($xml && isset($xml->si)) {
                            foreach ($xml->si as $si) {
                                $text = '';
                                if (isset($si->t)) $text = (string) $si->t;
                                elseif (isset($si->r)) {
                                    foreach ($si->r as $r) { if (isset($r->t)) $text .= (string) $r->t; }
                                }
                                $shared_strings[] = $text;
                            }
                        }
                    }

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

        // 3. ÜÇÜNCÜ MOTOR (Ham Metin/CSV Çevirici)
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
        return $rows;
    }
}

function hbt_clean_price($price_str) {
    // Fiyatlardaki " tırnakları, boşlukları sil ve virgülü noktaya çevir
    $price = str_replace(['"', ' '], '', $price_str);
    return floatval(str_replace(',', '.', $price));
}

// FORM GÖNDERİMİ
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_FILES['yildiz_excel'], $_FILES['komisyon_excel'] ) && wp_verify_nonce( $_POST['avantajli_nonce'], 'run_avantajli_simulation' ) ) {
    
    $store_id = intval( $_POST['store_id'] );
    
    // Dosyaları Oku
    $yildiz_rows = hbt_parse_trendyol_file($_FILES['yildiz_excel']['tmp_name']);
    $komisyon_rows = hbt_parse_trendyol_file($_FILES['komisyon_excel']['tmp_name']);

    if ( count($yildiz_rows) > 1 && count($komisyon_rows) > 1 ) {
        
        // KOMİSYON VERİLERİNİ BARKODA GÖRE DİZİYE AL
        $komisyon_data = [];
        $kom_header = array_shift($komisyon_rows);
        $k_idx = array_flip(array_map('trim', $kom_header)); // Başlıkları index'e çevir

        foreach ($komisyon_rows as $row) {
            $barkod = isset($k_idx['BARKOD']) && isset($row[$k_idx['BARKOD']]) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $row[$k_idx['BARKOD']]) : '';
            if(empty($barkod)) continue;
            
            $komisyon_data[$barkod] = [
                'L1' => isset($k_idx['1.Fiyat Alt Limit']) ? hbt_clean_price($row[$k_idx['1.Fiyat Alt Limit']]) : 0,
                'L2_ust' => isset($k_idx['2.Fiyat Üst Limiti']) ? hbt_clean_price($row[$k_idx['2.Fiyat Üst Limiti']]) : 0,
                'L2_alt' => isset($k_idx['2.Fiyat Alt Limit']) ? hbt_clean_price($row[$k_idx['2.Fiyat Alt Limit']]) : 0,
                'L3_ust' => isset($k_idx['3.Fiyat Üst Limiti']) ? hbt_clean_price($row[$k_idx['3.Fiyat Üst Limiti']]) : 0,
                'L3_alt' => isset($k_idx['3.Fiyat Alt Limit']) ? hbt_clean_price($row[$k_idx['3.Fiyat Alt Limit']]) : 0,
                'L4_ust' => isset($k_idx['4.Fiyat Üst Limiti']) ? hbt_clean_price($row[$k_idx['4.Fiyat Üst Limiti']]) : 0,
                'K1' => isset($k_idx['1.KOMİSYON']) ? hbt_clean_price($row[$k_idx['1.KOMİSYON']]) : 0,
                'K2' => isset($k_idx['2.KOMİSYON']) ? hbt_clean_price($row[$k_idx['2.KOMİSYON']]) : 0,
                'K3' => isset($k_idx['3.KOMİSYON']) ? hbt_clean_price($row[$k_idx['3.KOMİSYON']]) : 0,
                'K4' => isset($k_idx['4.KOMİSYON']) ? hbt_clean_price($row[$k_idx['4.KOMİSYON']]) : 0,
                'Guncel_Kom' => isset($k_idx['GÜNCEL KOMİSYON']) ? hbt_clean_price($row[$k_idx['GÜNCEL KOMİSYON']]) : 0
            ];
        }

        // YILDIZ VERİLERİNİ İŞLE VE SİMÜLE ET
        $yil_header = array_shift($yildiz_rows);
        $y_idx = array_flip(array_map('trim', $yil_header));

        $usd_rate = (float) $wpdb->get_var( "SELECT buying_rate FROM {$wpdb->prefix}hbt_currency_rates ORDER BY rate_date DESC LIMIT 1" ) ?: 33.00;
        
        $fixed_costs_opt = get_option( 'hbt_fixed_costs', array() );
        $store_fc = $fixed_costs_opt[ $store_id ] ?? array();
        $order_fixed_cost = (float)($store_fc['personnel'] ?? 0) + (float)($store_fc['packaging'] ?? 0) + (float)($store_fc['other'] ?? 0);

        foreach ($yildiz_rows as $row) {
            $barkod = isset($y_idx['BARKOD']) && isset($row[$y_idx['BARKOD']]) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $row[$y_idx['BARKOD']]) : '';
            if(empty($barkod) || !isset($komisyon_data[$barkod])) continue;

            $isim = isset($y_idx['ÜRÜN İSMİ']) ? sanitize_text_field($row[$y_idx['ÜRÜN İSMİ']]) : 'Bilinmeyen Ürün';
            $guncel_fiyat = isset($y_idx['TRENDYOL SATIŞ FİYATI']) ? hbt_clean_price($row[$y_idx['TRENDYOL SATIŞ FİYATI']]) : 0;
            $y1_fiyat = isset($y_idx['1 YILDIZ ÜST FİYAT']) ? hbt_clean_price($row[$y_idx['1 YILDIZ ÜST FİYAT']]) : 0;
            $y2_fiyat = isset($y_idx['2 YILDIZ ÜST FİYAT']) ? hbt_clean_price($row[$y_idx['2 YILDIZ ÜST FİYAT']]) : 0;
            $y3_fiyat = isset($y_idx['3 YILDIZ ÜST FİYAT']) ? hbt_clean_price($row[$y_idx['3 YILDIZ ÜST FİYAT']]) : 0;

            if ($guncel_fiyat <= 0) continue;

            $product = $wpdb->get_row( $wpdb->prepare( "SELECT cost_usd FROM {$wpdb->prefix}hbt_product_costs WHERE barcode = %s AND store_id = %d LIMIT 1", $barkod, $store_id ) );
            $urun_maliyet_tl = $product ? (float) $product->cost_usd * $usd_rate : 0;
            $maliyet_uyarisi = $product ? '' : ' <span style="color:#d63638; font-size:11px; background:#fcf0f1; padding:2px 4px; border-radius:3px;">(Maliyet: 0)</span>';

            $k_data = $komisyon_data[$barkod];

            // Her fiyat için komisyon bulucu fonksiyon
            $get_commission = function($fiyat) use ($k_data) {
                if ($fiyat >= $k_data['L1']) return $k_data['K1'];
                if ($fiyat >= $k_data['L2_alt'] && $fiyat <= $k_data['L2_ust']) return $k_data['K2'];
                if ($fiyat >= $k_data['L3_alt'] && $fiyat <= $k_data['L3_ust']) return $k_data['K3'];
                if ($fiyat <= $k_data['L4_ust']) return $k_data['K4'];
                return $k_data['Guncel_Kom']; // Eşleşmezse günceli al
            };

            // Her fiyat için kargo, komisyon ve kâr hesaplayıcı
            $calc_profit = function($fiyat) use ($wpdb, $store_id, $urun_maliyet_tl, $order_fixed_cost, $get_commission) {
                if ($fiyat <= 0) return null;
                $kargo = (float) $wpdb->get_var( $wpdb->prepare( "SELECT cost_tl FROM {$wpdb->prefix}hbt_shipping_costs WHERE store_id = %d AND price_min <= %f AND (price_max >= %f OR price_max IS NULL) ORDER BY id DESC LIMIT 1", $store_id, $fiyat, $fiyat ) );
                $kom_oran = $get_commission($fiyat);
                $kom_tutar = ($fiyat * $kom_oran) / 100;
                $toplam_maliyet = $urun_maliyet_tl + $kargo + $order_fixed_cost + $kom_tutar;
                $kar_tl = $fiyat - $toplam_maliyet;
                $kar_orani = ($kar_tl / $fiyat) * 100;
                
                $color = 'blink-light-red';
                if ($kar_orani >= 30) $color = 'blink-green';
                elseif ($kar_orani >= 20) $color = 'blink-yellow';
                elseif ($kar_orani >= 10) $color = 'blink-dark-red';

                return [
                    'fiyat' => $fiyat, 'kargo' => $kargo, 'kom_oran' => $kom_oran, 
                    'kom_tutar' => $kom_tutar, 'kar_tl' => $kar_tl, 'kar_orani' => $kar_orani, 'color' => $color
                ];
            };

            $sim_mevcut = $calc_profit($guncel_fiyat);
            if ($sim_mevcut) {
                $sim_mevcut['kom_oran'] = $k_data['Guncel_Kom'];
                $sim_mevcut['kom_tutar'] = ($guncel_fiyat * $k_data['Guncel_Kom']) / 100;
                $toplam_maliyet = $urun_maliyet_tl + $sim_mevcut['kargo'] + $order_fixed_cost + $sim_mevcut['kom_tutar'];
                $sim_mevcut['kar_tl'] = $guncel_fiyat - $toplam_maliyet;
                $sim_mevcut['kar_orani'] = ($sim_mevcut['kar_tl'] / $guncel_fiyat) * 100;
                $sim_mevcut['color'] = ($sim_mevcut['kar_orani'] >= 30) ? 'blink-green' : (($sim_mevcut['kar_orani'] >= 20) ? 'blink-yellow' : (($sim_mevcut['kar_orani'] >= 10) ? 'blink-dark-red' : 'blink-light-red'));
            }

            $simulation_results[] = [
                'isim' => $isim . $maliyet_uyarisi,
                'barkod' => $barkod,
                'maliyet' => $urun_maliyet_tl,
                'gider' => $order_fixed_cost,
                'mevcut' => $sim_mevcut,
                'y1' => $calc_profit($y1_fiyat),
                'y2' => $calc_profit($y2_fiyat),
                'y3' => $calc_profit($y3_fiyat)
            ];
        }

        if (empty($simulation_results)) {
            $error_message = 'Dosyalar okundu ancak iki dosya arasında eşleşen barkod veya veritabanında geçerli ürün bulunamadı.';
        }

    } else {
        $error_message = 'Dosyalar okunamadı. Lütfen dosyaların doğru formatta olduğundan emin olun.';
    }
}
?>

<style>
    .hbt-simulator-wrap { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
    .hbt-filter-bar { background: #fff; padding: 15px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; gap: 15px; flex-wrap: wrap; align-items: center; }
    .hbt-filter-bar input, .hbt-filter-bar select { padding: 8px; border: 1px solid #ddd; border-radius: 4px; flex: 1; min-width: 200px; }
    
    .hbt-cards-container { display: flex; flex-direction: column; gap: 20px; }
    .hbt-card { background: #fff; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); overflow: hidden; display: flex; flex-direction: column; border: 1px solid #e2e4e7; }
    .hbt-card-header { padding: 12px 20px; background: #2c3338; color: #fff; display: flex; justify-content: space-between; align-items: center;}
    .hbt-card-header h3 { margin: 0; color: #fff; font-size: 15px; font-weight: 600; }
    .hbt-card-header small { color: #ccc; font-size: 13px; }
    
    .hbt-card-body { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
    .hbt-col { padding: 15px; border-right: 1px solid #eee; display: flex; flex-direction: column; }
    .hbt-col:last-child { border-right: none; }
    .hbt-col h4 { margin: 0 0 15px 0; text-align: center; font-size: 14px; padding-bottom: 8px; border-bottom: 1px solid #ddd;}
    
    .hbt-stat-row { display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 8px; border-bottom: 1px solid #f9f9f9; padding-bottom: 4px; }
    .hbt-stat-row span:last-child { font-weight: 600; }
    
    .hbt-profit-result { margin-top: auto; padding: 10px; border-radius: 6px; text-align: center; border: 1px solid transparent; transition: all 0.3s;}
    .hbt-profit-result .amount { font-size: 18px; font-weight: bold; display: block; margin-top: 5px; }
    .hbt-profit-result .margin { font-size: 13px; }

    @keyframes blinkGreen { 0% { background-color: #e8f5e9; box-shadow: 0 0 5px #4caf50; } 50% { background-color: #c8e6c9; box-shadow: 0 0 10px #4caf50; } 100% { background-color: #e8f5e9; box-shadow: 0 0 5px #4caf50; } }
    @keyframes blinkYellow { 0% { background-color: #fffde7; box-shadow: 0 0 5px #ffeb3b; } 50% { background-color: #fff9c4; box-shadow: 0 0 10px #ffeb3b; } 100% { background-color: #fffde7; box-shadow: 0 0 5px #ffeb3b; } }
    @keyframes blinkDarkRed { 0% { background-color: #ffebee; box-shadow: 0 0 5px #f44336; } 50% { background-color: #ffcdd2; box-shadow: 0 0 10px #f44336; } 100% { background-color: #ffebee; box-shadow: 0 0 5px #f44336; } }
    @keyframes blinkLightRed { 0% { background-color: #fafafa; } 50% { background-color: #ffebee; color: #d32f2f; } 100% { background-color: #fafafa; } }

    .blink-green .hbt-profit-result { animation: blinkGreen 2s infinite; color: #2e7d32; border-color: #4caf50; }
    .blink-yellow .hbt-profit-result { animation: blinkYellow 2s infinite; color: #f57f17; border-color: #fbc02d; }
    .blink-dark-red .hbt-profit-result { animation: blinkDarkRed 2s infinite; color: #c62828; border-color: #e53935; }
    .blink-light-red .hbt-profit-result { animation: blinkLightRed 1.5s infinite; color: #d32f2f; border: 1px dashed #d32f2f; }
</style>

<div class="wrap hbt-simulator-wrap">
    <h1>Avantajlı Etiketler (Yıldız Simülatörü)</h1>
    <p>💡 <b>İpucu:</b> Trendyol'dan indirdiğiniz <b>"Yıldızlı Ürün Etiketleri"</b> ve <b>"Komisyon Tarifeleri"</b> dosyalarını aynı anda yükleyin. Sistem barkodları eşleştirip her yıldız kademesi için kârınızı hesaplayacaktır.</p>
    
    <?php if ( ! empty( $error_message ) ) : ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error_message ); ?></p></div>
    <?php endif; ?>

    <div style="background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid #f57f17;">
        <form method="post" enctype="multipart/form-data" style="display: flex; gap: 20px; align-items: flex-end; flex-wrap: wrap;">
            <?php wp_nonce_field('run_avantajli_simulation', 'avantajli_nonce'); ?>
            
            <div>
                <label style="display:block; margin-bottom:5px;"><strong>Mağaza Seçin:</strong></label>
                <select name="store_id" required style="min-width: 150px;">
                    <?php 
                    foreach ( $stores as $store ) {
                        echo '<option value="' . esc_attr( $store->id ) . '">' . esc_html( $store->store_name ) . '</option>';
                    }
                    ?>
                </select>
            </div>
            
            <div>
                <label style="display:block; margin-bottom:5px; color:#2271b1;"><strong>1. Yıldızlı Etiketler (.xlsx):</strong></label>
                <input type="file" name="yildiz_excel" accept=".xlsx, .csv, .xls" required style="padding: 3px; border:1px solid #2271b1; border-radius:4px;">
            </div>

            <div>
                <label style="display:block; margin-bottom:5px; color:#d63638;"><strong>2. Komisyon Tarifeleri (.xlsx):</strong></label>
                <input type="file" name="komisyon_excel" accept=".xlsx, .csv, .xls" required style="padding: 3px; border:1px solid #d63638; border-radius:4px;">
            </div>
            
            <div>
                <button type="submit" class="button button-primary button-hero" style="background:#f57f17; border-color:#f57f17;">Yıldızları Hesapla</button>
            </div>
        </form>
    </div>

    <?php if ( ! empty( $simulation_results ) ) : ?>
        <div class="hbt-filter-bar">
            <input type="text" id="filterName" placeholder="🔍 Ürün Adı veya Barkod ile ara...">
            <select id="filterMargin">
                <option value="all">📊 Tüm Kâr Oranlarını Göster</option>
                <option value="green">🟢 Herhangi Bir Yıldızda Kâr %30+ Olanlar</option>
                <option value="yellow">🟡 Herhangi Bir Yıldızda Kâr %20-30 Olanlar</option>
                <option value="darkred">🔴 Herhangi Bir Yıldızda Kâr %10-20 Olanlar</option>
<option value="lightred">⭕ İçinde Zarar / Kötü Olanlar</option>            </select>
        </div>

        <div class="hbt-cards-container" id="cardsContainer">
            <?php foreach ( $simulation_results as $row ) : 
                // Filtreleme için mevcut kartın tüm renklerini veri niteliklerine (data attributes) yazıyoruz
                $c_m  = $row['mevcut'] ? $row['mevcut']['color'] : '';
                $c_y1 = $row['y1'] ? $row['y1']['color'] : '';
                $c_y2 = $row['y2'] ? $row['y2']['color'] : '';
                $c_y3 = $row['y3'] ? $row['y3']['color'] : '';
            ?>
                <div class="hbt-card" 
                     data-search="<?php echo esc_attr( strtolower( strip_tags($row['isim']) . ' ' . $row['barkod'] ) ); ?>"
                    data-colors="<?php echo esc_attr($c_y1 . ' ' . $c_y2 . ' ' . $c_y3); ?>">
                    
                    <div class="hbt-card-header">
                        <h3><?php echo wp_kses_post( $row['isim'] ); ?></h3>
                        <small>Barkod: <b><?php echo esc_html( $row['barkod'] ); ?></b></small>
                    </div>
                    
                    <div class="hbt-card-body">
                        <?php if($row['mevcut']): ?>
                        <div class="hbt-col <?php echo $row['mevcut']['color']; ?>" style="background:#f8f9fa;">
                            <h4 style="color:#444;">Mevcut Satış</h4>
                            <div class="hbt-stat-row"><span>Satış Fiyatı:</span> <span><?php echo number_format( $row['mevcut']['fiyat'], 2 ); ?> ₺</span></div>
                            <div class="hbt-stat-row"><span>Ürün Maliyeti:</span> <span>-<?php echo number_format( $row['maliyet'], 2 ); ?> ₺</span></div>
                            <div class="hbt-stat-row"><span>Sabit Giderler:</span> <span>-<?php echo number_format( $row['gider'], 2 ); ?> ₺</span></div>
                            <div class="hbt-stat-row"><span>Komisyon (%<?php echo $row['mevcut']['kom_oran']; ?>):</span> <span>-<?php echo number_format( $row['mevcut']['kom_tutar'], 2 ); ?> ₺</span></div>
                            <div class="hbt-stat-row"><span>Kargo:</span> <span>-<?php echo number_format( $row['mevcut']['kargo'], 2 ); ?> ₺</span></div>
                            <div class="hbt-profit-result">
                                <span class="margin">Kâr Oranı: <strong>%<?php echo number_format( $row['mevcut']['kar_orani'], 2 ); ?></strong></span>
                                <span class="amount"><?php echo number_format( $row['mevcut']['kar_tl'], 2 ); ?> ₺</span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if($row['y1']): ?>
                        <div class="hbt-col <?php echo $row['y1']['color']; ?>">
                            <h4 style="color:#f57f17;">⭐ 1 Yıldız Fiyatı</h4>
                            <div class="hbt-stat-row"><span>İnilecek Fiyat:</span> <span><?php echo number_format( $row['y1']['fiyat'], 2 ); ?> ₺</span></div>
                            <div class="hbt-stat-row"><span>Ürün Maliyeti:</span> <span>-<?php echo number_format( $row['maliyet'], 2 ); ?> ₺</span></div>
                            <div class="hbt-stat-row"><span>Sabit Giderler:</span> <span>-<?php echo number_format( $row['gider'], 2 ); ?> ₺</span></div>
                            <div class="hbt-stat-row"><span>Yeni Kom. (%<?php echo $row['y1']['kom_oran']; ?>):</span> <span>-<?php echo number_format( $row['y1']['kom_tutar'], 2 ); ?> ₺</span></div>
                            <div class="hbt-stat-row"><span>Kargo:</span> <span>-<?php echo number_format( $row['y1']['kargo'], 2 ); ?> ₺</span></div>
                            <div class="hbt-profit-result">
                                <span class="margin">Yeni Kâr: <strong>%<?php echo number_format( $row['y1']['kar_orani'], 2 ); ?></strong></span>
                                <span class="amount"><?php echo number_format( $row['y1']['kar_tl'], 2 ); ?> ₺</span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if($row['y2']): ?>
                        <div class="hbt-col <?php echo $row['y2']['color']; ?>">
                            <h4 style="color:#f57f17;">⭐⭐ 2 Yıldız Fiyatı</h4>
                            <div class="hbt-stat-row"><span>İnilecek Fiyat:</span> <span><?php echo number_format( $row['y2']['fiyat'], 2 ); ?> ₺</span></div>
                            <div class="hbt-stat-row"><span>Ürün Maliyeti:</span> <span>-<?php echo number_format( $row['maliyet'], 2 ); ?> ₺</span></div>
                            <div class="hbt-stat-row"><span>Sabit Giderler:</span> <span>-<?php echo number_format( $row['gider'], 2 ); ?> ₺</span></div>
                            <div class="hbt-stat-row"><span>Yeni Kom. (%<?php echo $row['y2']['kom_oran']; ?>):</span> <span>-<?php echo number_format( $row['y2']['kom_tutar'], 2 ); ?> ₺</span></div>
                            <div class="hbt-stat-row"><span>Kargo:</span> <span>-<?php echo number_format( $row['y2']['kargo'], 2 ); ?> ₺</span></div>
                            <div class="hbt-profit-result">
                                <span class="margin">Yeni Kâr: <strong>%<?php echo number_format( $row['y2']['kar_orani'], 2 ); ?></strong></span>
                                <span class="amount"><?php echo number_format( $row['y2']['kar_tl'], 2 ); ?> ₺</span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if($row['y3']): ?>
                        <div class="hbt-col <?php echo $row['y3']['color']; ?>">
                            <h4 style="color:#f57f17;">⭐⭐⭐ 3 Yıldız Fiyatı</h4>
                            <div class="hbt-stat-row"><span>İnilecek Fiyat:</span> <span><?php echo number_format( $row['y3']['fiyat'], 2 ); ?> ₺</span></div>
                            <div class="hbt-stat-row"><span>Ürün Maliyeti:</span> <span>-<?php echo number_format( $row['maliyet'], 2 ); ?> ₺</span></div>
                            <div class="hbt-stat-row"><span>Sabit Giderler:</span> <span>-<?php echo number_format( $row['gider'], 2 ); ?> ₺</span></div>
                            <div class="hbt-stat-row"><span>Yeni Kom. (%<?php echo $row['y3']['kom_oran']; ?>):</span> <span>-<?php echo number_format( $row['y3']['kom_tutar'], 2 ); ?> ₺</span></div>
                            <div class="hbt-stat-row"><span>Kargo:</span> <span>-<?php echo number_format( $row['y3']['kargo'], 2 ); ?> ₺</span></div>
                            <div class="hbt-profit-result">
                                <span class="margin">Yeni Kâr: <strong>%<?php echo number_format( $row['y3']['kar_orani'], 2 ); ?></strong></span>
                                <span class="amount"><?php echo number_format( $row['y3']['kar_tl'], 2 ); ?> ₺</span>
                            </div>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const filterInput = document.getElementById('filterName');
                const filterMargin = document.getElementById('filterMargin');
                const cards = document.querySelectorAll('.hbt-card');

                function runFilters() {
                    const searchVal = filterInput.value.toLowerCase();
                    const marginVal = filterMargin.value;

                    cards.forEach(card => {
                        const searchableText = card.getAttribute('data-search');
                        const cardColors = card.getAttribute('data-colors');
                        
                        let matchSearch = searchableText.includes(searchVal);
                        let matchMargin = true;

                        if (marginVal !== 'all') {
                            if (marginVal === 'green' && !cardColors.includes('blink-green')) matchMargin = false;
                            if (marginVal === 'yellow' && !cardColors.includes('blink-yellow')) matchMargin = false;
                            if (marginVal === 'darkred' && !cardColors.includes('blink-dark-red')) matchMargin = false;
                            
                            // Light Red (Kötü) için kural: Eğer yeşil, sarı veya koyu kırmızı YOKSA kötüdür.
                            if (marginVal === 'lightred' && (cardColors.includes('blink-green') || cardColors.includes('blink-yellow') || cardColors.includes('blink-dark-red'))) {
                                matchMargin = false;
                            }
                        }

                        if (matchSearch && matchMargin) {
                            card.style.display = 'flex';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                }

                filterInput.addEventListener('input', runFilters);
                filterMargin.addEventListener('change', runFilters);
            });
        </script>
    <?php endif; ?>
</div>