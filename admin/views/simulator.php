<?php
/**
 * Sihirli Fiyat ve Kâr Simülatörü (SaaS App)
 */
defined( 'ABSPATH' ) || exit;

// 1. Mağazaları Çek
$stores = HBT_Database::instance()->get_stores();

// 2. Sabit Giderleri Çek (wp_options tablosundan)
$fixed_costs = get_option('hbt_fixed_costs', array());

// 3. Kargo Baremlerini Çek (Veritabanı tablosundan)
$shipping_costs = HBT_Database::instance()->get_shipping_costs();

// 4. Güncel USD Kurunu Çek
$usd_rate = 32.50; 
if ( class_exists('HBT_Currency_Service') ) {
    $rates = get_option( 'hbt_tpt_currency_rates', array() );
    if ( isset($rates['USD']) ) { $usd_rate = (float) $rates['USD']; }
}

// JavaScript'e aktarmak için verileri hazırlayalım
$store_data = array();
foreach ( $stores as $store ) {
    // Bu mağazaya ait sabit giderleri topla
    $fc = $fixed_costs[$store->id] ?? array('personnel'=>0, 'packaging'=>0, 'other'=>0);
    $total_fixed = (float)$fc['personnel'] + (float)$fc['packaging'] + (float)$fc['other'];

    // Bu mağazaya ait kargo baremlerini filtrele
    $my_shipping = array();
    foreach ( $shipping_costs as $cost ) {
        if ( (int)$cost->store_id === (int)$store->id ) {
            $my_shipping[] = array(
                'min'  => $cost->price_min !== null ? (float)$cost->price_min : 0,
                'max'  => $cost->price_max !== null ? (float)$cost->price_max : 999999,
                'cost' => (float)$cost->cost_tl
            );
        }
    }

    $store_data[$store->id] = array(
        'store_name'  => $store->store_name,
        'fixed_costs' => $total_fixed,
        'shipping'    => $my_shipping
    );
}

?>
<div class="wrap hbt-wrap">
    
    <div class="hbt-page-header">
        <h1 class="hbt-page-title">
            <span class="dashicons dashicons-calculator"></span> 
            <?php esc_html_e( 'Akıllı Fiyat & Kâr Simülatörü', 'hbt-trendyol-profit-tracker' ); ?>
        </h1>
    </div>

    <div class="hbt-alert-box hbt-alert-info" style="margin-bottom: 24px;">
        <span class="dashicons dashicons-lightbulb"></span> 
        <div>Bir mağaza seçtiğinizde sabit giderler otomatik çekilir. <strong>Kargo ücreti ise hedeflediğiniz satış fiyatının baremine (aralığına) göre sistem tarafından canlı olarak hesaplanır!</strong></div>
    </div>

    <div class="hbt-dashboard-row" style="align-items: flex-start; gap: 24px;">
        
        <div class="hbt-col-5 hbt-card" style="padding: 24px;">
            <h3 class="hbt-widget-title" style="margin-top: 0; margin-bottom: 20px;"><span class="dashicons dashicons-edit"></span> Temel Ürün Giderleri</h3>
            
            <div style="display: flex; gap: 12px; margin-bottom: 16px;">
                <div style="flex: 2;">
                    <label style="font-weight: 600; font-size: 13px; color: var(--hbt-primary); display: block; margin-bottom: 6px;">Mağaza Seçimi</label>
                    <select id="sim-store" style="width: 100%; padding: 8px; border: 1px solid var(--hbt-border); border-radius: 4px; font-weight:600;">
    <option value=""><?php esc_html_e( 'Manuel Hesaplama (Mağaza Seçilmedi)', 'hbt-trendyol-profit-tracker' ); ?></option>
    <?php foreach ($store_data as $id => $data): ?>
        <option value="<?php echo esc_attr($id); ?>" data-info="<?php echo esc_attr(json_encode($data)); ?>">
            <?php echo esc_html($data['store_name']); ?>
        </option>
    <?php endforeach; ?>
</select>
                </div>
                <div style="flex: 1;">
                    <label style="font-weight: 600; font-size: 13px; color: var(--hbt-primary); display: block; margin-bottom: 6px;">Para Birimi</label>
                    <select id="sim-currency" style="width: 100%; padding: 8px; border: 1px solid var(--hbt-border); border-radius: 4px;">
                        <option value="TRY">TRY (₺)</option>
                        <option value="USD">USD ($)</option>
                    </select>
                </div>
            </div>

            <div id="sim-usd-rate-container" style="display: none; margin-bottom: 16px; background: #F8FAFC; padding: 12px; border-radius: 6px; border: 1px solid #CBD5E1;">
                <label style="font-weight: 600; font-size: 13px; color: var(--hbt-primary); display: flex; justify-content: space-between; margin-bottom: 6px;">
                    Güncel Kur (USD/TRY)
                    <span id="hbt-kur-loading" style="display:none; color: #2563EB; font-size: 11px;"><span class="dashicons dashicons-update hbt-spin" style="font-size: 14px; width: 14px; height: 14px; margin-top: -2px;"></span> Çekiliyor...</span>
                </label>
                <input type="text" id="sim-usd-rate" value="<?php echo esc_attr(str_replace('.', ',', $usd_rate)); ?>" style="width: 100%; padding: 8px; font-weight:bold;">
            </div>

            <div style="display: flex; gap: 12px; margin-bottom: 16px;">
                <div style="flex: 2;">
                    <label style="font-weight: 600; font-size: 13px; color: var(--hbt-primary); display: block; margin-bottom: 6px;">Ürün Geliş Maliyeti</label>
                    <input type="text" id="sim-cost" placeholder="Örn: 150" style="width: 100%; padding: 10px; font-size: 16px; font-weight: bold;">
                </div>
                <div style="flex: 1;">
                    <label style="font-weight: 600; font-size: 13px; color: var(--hbt-primary); display: block; margin-bottom: 6px;">Komisyon (%)</label>
                    <input type="text" id="sim-commission" placeholder="19" value="19" style="width: 100%; padding: 10px; font-size: 16px; font-weight: bold;">
                </div>
            </div>

            <div style="display: flex; gap: 12px; margin-bottom: 16px;">
                <div style="flex: 1;">
                    <label style="font-weight: 600; font-size: 13px; color: var(--hbt-primary); display: block; margin-bottom: 6px;">Kargo Ücreti (₺)</label>
                    <input type="text" id="sim-shipping" value="81,00" style="width: 100%; padding: 8px;">
                    <small id="sim-shipping-note" style="display:none; color:var(--hbt-success); font-weight:600; font-size:11px; margin-top:4px;">Satış fiyatına göre hesaplanır</small>
                </div>
                <div style="flex: 1;">
                    <label style="font-weight: 600; font-size: 13px; color: var(--hbt-primary); display: block; margin-bottom: 6px;">Diğer/Sabit Gider (₺)</label>
                    <input type="text" id="sim-other" value="32,00" style="width: 100%; padding: 8px;">
                    <small id="sim-other-note" style="display:none; color:var(--hbt-success); font-weight:600; font-size:11px; margin-top:4px;">Mağazadan çekildi</small>
                </div>
            </div>
        </div>

        <div class="hbt-col-7" style="display: flex; flex-direction: column; gap: 24px;">
            
            <div class="hbt-card" style="padding: 20px; border-left: 5px solid var(--hbt-primary);">
                <h3 style="margin: 0 0 15px 0; font-size: 15px; color: var(--hbt-primary);"><span class="dashicons dashicons-search"></span> Senaryo 1: Fiyatı Belli, Kârım Ne Olur?</h3>
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="flex: 1;">
                        <label style="font-weight: 600; font-size: 12px; color: var(--hbt-text-muted); display: block; margin-bottom: 5px;">Planlanan Satış Fiyatı (₺)</label>
                        <input type="text" id="scen1-price" placeholder="Örn: 399,90" style="width: 100%; padding: 12px; font-size: 18px; font-weight: bold; border: 2px solid var(--hbt-border); border-radius: 6px;">
                    </div>
                    <div style="flex: 2; display: flex; gap: 10px; background: #F8FAFC; padding: 12px; border-radius: 6px;">
                        <div style="flex: 1; text-align: center; border-right: 1px solid var(--hbt-border);">
                            <span style="font-size: 11px; font-weight: 600; color: var(--hbt-text-muted); display: block;">Net Kâr (₺)</span>
                            <span id="res1-profit" style="font-size: 22px; font-weight: 800; color: #94A3B8;">0,00 ₺</span>
                        </div>
                        <div style="flex: 1; text-align: center;">
                            <span style="font-size: 11px; font-weight: 600; color: var(--hbt-text-muted); display: block;">Kâr Marjı (%)</span>
                            <span id="res1-margin" style="font-size: 22px; font-weight: 800; color: #94A3B8;">%0</span>
                        </div>
                    </div>
                </div>
                <div id="res1-shipping-info" style="display:none; font-size:12px; color:var(--hbt-text-muted); margin-top:10px; text-align:right;">
                    <em>*Bu fiyat aralığı için kargo maliyeti: <strong id="res1-shipping-val">0,00 ₺</strong> baz alındı.</em>
                </div>
            </div>

            <div class="hbt-card" style="padding: 20px; border-left: 5px solid var(--hbt-success);">
                <h3 style="margin: 0 0 15px 0; font-size: 15px; color: var(--hbt-success);"><span class="dashicons dashicons-chart-line"></span> Senaryo 2: Hedefim Belli, Kaça Satmalıyım?</h3>
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="flex: 1;">
                        <label style="font-weight: 600; font-size: 12px; color: var(--hbt-text-muted); display: block; margin-bottom: 5px;">Hedeflenen Kâr Marjı (%)</label>
                        <input type="text" id="scen2-margin" placeholder="Örn: 25" style="width: 100%; padding: 12px; font-size: 18px; font-weight: bold; border: 2px solid var(--hbt-border); border-radius: 6px;">
                    </div>
                    <div style="flex: 1; text-align: center; font-weight: bold; color: var(--hbt-text-muted);">VEYA</div>
                    <div style="flex: 1;">
                        <label style="font-weight: 600; font-size: 12px; color: var(--hbt-text-muted); display: block; margin-bottom: 5px;">Hedef Net Kâr (₺)</label>
                        <input type="text" id="scen2-profit" placeholder="Örn: 100" style="width: 100%; padding: 12px; font-size: 18px; font-weight: bold; border: 2px solid var(--hbt-border); border-radius: 6px;">
                    </div>
                </div>
                <div style="margin-top: 16px; background: #D1FAE5; border: 1px solid #34D399; padding: 16px; border-radius: 6px; text-align: center;">
                    <span style="font-size: 13px; font-weight: 600; color: #065F46; display: block; margin-bottom: 4px;">Olması Gereken İdeal Satış Fiyatınız</span>
                    <span id="res2-price" style="font-size: 32px; font-weight: 900; color: #047857;">0,00 ₺</span>
                </div>
            </div>
        </div>
    </div>

    <div class="hbt-dashboard-row" style="margin-top: 24px;">
        <div class="hbt-col-12 hbt-card" style="padding: 24px;">
            <h3 class="hbt-widget-title" style="margin-top: 0; margin-bottom: 20px;"><span class="dashicons dashicons-list-view"></span> Gider Kırılımı ve Şeffaf Analiz</h3>
            <div style="overflow-x: auto;">
                <table class="wp-list-table widefat fixed striped" style="width: 100%; border: 1px solid var(--hbt-border); border-collapse: collapse;">
                    <thead>
                        <tr style="background: #F8FAFC;">
                            <th style="padding: 12px; font-weight: 800; font-size: 14px; text-align: left;">Kalem / Metrik</th>
                            <th style="padding: 12px; font-weight: 800; font-size: 14px; text-align: right; color: var(--hbt-primary);">Senaryo 1 (Manuel Fiyat)</th>
                            <th style="padding: 12px; font-weight: 800; font-size: 14px; text-align: right; color: var(--hbt-success);">Senaryo 2 (Hedef Kâr)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="padding: 10px 12px; font-weight: 600;">Satış Fiyatı (KDV Dahil)</td>
                            <td id="bd-s1-price" style="padding: 10px 12px; text-align: right; font-weight: bold; font-size: 15px;">0,00 ₺</td>
                            <td id="bd-s2-price" style="padding: 10px 12px; text-align: right; font-weight: bold; font-size: 15px;">0,00 ₺</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px 12px;">Ürün Geliş Maliyeti</td>
                            <td id="bd-s1-cost" style="padding: 10px 12px; text-align: right;">0,00 ₺</td>
                            <td id="bd-s2-cost" style="padding: 10px 12px; text-align: right;">0,00 ₺</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px 12px;">Trendyol Komisyonu</td>
                            <td id="bd-s1-comm" style="padding: 10px 12px; text-align: right;">0,00 ₺</td>
                            <td id="bd-s2-comm" style="padding: 10px 12px; text-align: right;">0,00 ₺</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px 12px;">Kargo Ücreti</td>
                            <td id="bd-s1-ship" style="padding: 10px 12px; text-align: right;">0,00 ₺</td>
                            <td id="bd-s2-ship" style="padding: 10px 12px; text-align: right;">0,00 ₺</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px 12px;">Diğer / Sabit Giderler</td>
                            <td id="bd-s1-other" style="padding: 10px 12px; text-align: right;">0,00 ₺</td>
                            <td id="bd-s2-other" style="padding: 10px 12px; text-align: right;">0,00 ₺</td>
                        </tr>
                        <tr style="background: #FEF2F2;">
                            <td style="padding: 10px 12px; font-weight: 800; color: #991B1B;">Toplam Gider</td>
                            <td id="bd-s1-total" style="padding: 10px 12px; text-align: right; font-weight: bold; color: #DC2626;">0,00 ₺</td>
                            <td id="bd-s2-total" style="padding: 10px 12px; text-align: right; font-weight: bold; color: #DC2626;">0,00 ₺</td>
                        </tr>
                        <tr style="background: #F0FDF4;">
                            <td style="padding: 10px 12px; font-weight: 800; color: #065F46;">Net Kâr</td>
                            <td id="bd-s1-profit" style="padding: 10px 12px; text-align: right; font-weight: bold; font-size: 16px; color: #10B981;">0,00 ₺</td>
                            <td id="bd-s2-profit" style="padding: 10px 12px; text-align: right; font-weight: bold; font-size: 16px; color: #10B981;">0,00 ₺</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px 12px; font-weight: 600;">Net Kâr Marjı</td>
                            <td id="bd-s1-margin" style="padding: 10px 12px; text-align: right; font-weight: bold;">%0,0</td>
                            <td id="bd-s2-margin" style="padding: 10px 12px; text-align: right; font-weight: bold;">%0,0</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {

    var storeTiers = [];

    // Helper: Sayıları Türkçe formatta yazdırır
    function fmt(val) {
        return parseFloat(val).toLocaleString('tr-TR', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' ₺';
    }

    $('#sim-currency').on('change', function() {
        if ($(this).val() === 'USD') {
            $('#sim-usd-rate-container').slideDown('fast');
            $('#sim-usd-rate').prop('readonly', true).css('opacity', '0.6');
            $('#hbt-kur-loading').fadeIn('fast');

            $.post(hbtTpt.ajaxurl, { action: 'hbt_get_live_usd_rate', nonce: hbtTpt.nonce }, function(res) {
                if (res.success && res.data.rate) { $('#sim-usd-rate').val(res.data.rate.toString().replace('.', ',')); }
                $('#sim-usd-rate').prop('readonly', false).css('opacity', '1');
                $('#hbt-kur-loading').fadeOut('fast');
                calculateAll();
            }).fail(function() {
                $('#sim-usd-rate').prop('readonly', false).css('opacity', '1');
                $('#hbt-kur-loading').fadeOut('fast');
                calculateAll();
            });
        } else {
            $('#sim-usd-rate-container').slideUp('fast');
            calculateAll();
        }
    });

    // Mağaza Seçildiğinde Giderleri ve Kargo Baremlerini Otomatik Çek
    $('#sim-store').on('change', function() {
        var selected = $(this).find(':selected');
        
        if (!selected.val()) {
            // Manuel Mod
            $('#sim-other').val('5,00').prop('readonly', false).css('background', '#fff');
            $('#sim-shipping').val('49,50').prop('readonly', false).css({'background':'#fff', 'color':'inherit', 'text-align':'left'});
            $('#sim-other-note, #sim-shipping-note, #res1-shipping-info').hide();
            storeTiers = [];
        } else {
            // Otomatik Mağaza Modu
            var info = JSON.parse(selected.attr('data-info'));
            
            // 1. Sabit Giderleri Yaz (Personel + Paketleme + Diğer toplamı)
            $('#sim-other').val(info.fixed_costs.toString().replace('.', ',')).prop('readonly', true).css('background', '#F8FAFC');
            $('#sim-other-note').fadeIn();
            
            // 2. Kargo Kutusunu "Otomatik" Yap
            $('#sim-shipping').val('OTOMATİK').prop('readonly', true).css({'background':'#EFF6FF', 'color':'#2563EB', 'font-weight':'bold', 'text-align':'center'});
            $('#sim-shipping-note').fadeIn();

            // 3. Kargo Baremlerini Sisteme Yükle
            storeTiers = info.shipping;
        }
        calculateAll();
    });

    $('input').on('input keyup', function() {
        if($(this).attr('id') === 'scen2-margin') { $('#scen2-profit').val(''); }
        if($(this).attr('id') === 'scen2-profit') { $('#scen2-margin').val(''); }
        calculateAll();
    });

    function calculateAll() {
        // VİRGÜL DÜZELTİCİ: Kullanıcı virgül girse bile matematiksel noktaya çevirilir.
        var rawCost = parseFloat($('#sim-cost').val().replace(',', '.')) || 0;
        var currency = $('#sim-currency').val();
        var usdRate = parseFloat($('#sim-usd-rate').val().replace(',', '.')) || 1;
        var finalCost = currency === 'USD' ? (rawCost * usdRate) : rawCost;

        var commRate = parseFloat($('#sim-commission').val().replace(',', '.')) || 0;
        var otherCost = parseFloat($('#sim-other').val().replace(',', '.')) || 0;

        // Ortak Tablo Değerleri
        $('#bd-s1-cost, #bd-s2-cost').text(fmt(finalCost));
        $('#bd-s1-other, #bd-s2-other').text(fmt(otherCost));

        if (finalCost <= 0) { resetResults(); return; }

        /* =========================================================
           SENARYO 1: FİYATTAN KÂR BULMA
           ========================================================= */
        var s1Price = parseFloat($('#scen1-price').val().replace(',', '.')) || 0;
        var s1ShipCost = 0;
        var s1Comm = 0;
        var s1TotalCost = 0;
        var s1NetProfit = 0;
        var s1Margin = 0;

        if (s1Price > 0) {
            if (storeTiers.length === 0) {
                s1ShipCost = parseFloat($('#sim-shipping').val().replace(',', '.')) || 0;
                $('#res1-shipping-info').hide();
            } else {
                $.each(storeTiers, function(i, t) { if (s1Price >= t.min && s1Price <= t.max) { s1ShipCost = t.cost; return false; } });
                $('#res1-shipping-val').text(fmt(s1ShipCost));
                $('#res1-shipping-info').show();
            }

            s1Comm = s1Price * (commRate / 100);
            s1TotalCost = finalCost + otherCost + s1ShipCost + s1Comm;
            s1NetProfit = s1Price - s1TotalCost;
            s1Margin = (s1NetProfit / s1Price) * 100;
            
            var cColor = s1NetProfit >= 0 ? '#10B981' : '#EF4444';
            $('#res1-profit').text(fmt(s1NetProfit)).css('color', cColor);
            $('#res1-margin').text('%' + s1Margin.toLocaleString('tr-TR', {minimumFractionDigits:1, maximumFractionDigits:1})).css('color', cColor);

            // Tabloyu Doldur (Senaryo 1)
            $('#bd-s1-price').text(fmt(s1Price));
            $('#bd-s1-comm').text(fmt(s1Comm));
            $('#bd-s1-ship').text(fmt(s1ShipCost));
            $('#bd-s1-total').text(fmt(s1TotalCost));
            $('#bd-s1-profit').text(fmt(s1NetProfit)).css('color', cColor);
            $('#bd-s1-margin').text('%' + s1Margin.toLocaleString('tr-TR', {minimumFractionDigits:1, maximumFractionDigits:1}));
        } else {
            $('#res1-profit, #bd-s1-profit').text('0,00 ₺').css('color', '#94A3B8');
            $('#res1-margin, #bd-s1-margin').text('%0').css('color', '#94A3B8');
            $('#bd-s1-price, #bd-s1-comm, #bd-s1-ship, #bd-s1-total').text('0,00 ₺');
            $('#res1-shipping-info').hide();
        }

        /* =========================================================
           SENARYO 2: HEDEF KÂRDAN (PARADOKS ÇÖZEREK) FİYAT BULMA
           ========================================================= */
        var s2Margin = parseFloat($('#scen2-margin').val().replace(',', '.'));
        var s2Profit = parseFloat($('#scen2-profit').val().replace(',', '.'));
        var requiredPrice = 0;
        var s2ShipCost = 0;

        if (storeTiers.length === 0) {
            s2ShipCost = parseFloat($('#sim-shipping').val().replace(',', '.')) || 0;
            var totalCostBase = finalCost + otherCost + s2ShipCost;
            
            if (!isNaN(s2Margin) && s2Margin > 0 && s2Margin < 100) { requiredPrice = totalCostBase / (1 - ((commRate + s2Margin) / 100)); } 
            else if (!isNaN(s2Profit) && s2Profit > 0) { requiredPrice = (totalCostBase + s2Profit) / (1 - (commRate / 100)); }
        } else {
            // PARADOKS ÇÖZÜCÜ
            var matchedPrice = 0;
            var paradoxOptions = [];

            $.each(storeTiers, function(i, t) {
                var testPrice = 0;
                var currentCostBase = finalCost + otherCost + t.cost;
                
                if (!isNaN(s2Margin) && s2Margin > 0 && s2Margin < 100) {
                    var divisor = 1 - ((commRate + s2Margin) / 100);
                    if (divisor > 0) testPrice = currentCostBase / divisor;
                } else if (!isNaN(s2Profit) && s2Profit > 0) {
                    var divisor = 1 - (commRate / 100);
                    if (divisor > 0) testPrice = (currentCostBase + s2Profit) / divisor;
                }

                if (testPrice >= t.min && testPrice <= t.max) {
                    matchedPrice = testPrice;
                    s2ShipCost = t.cost;
                    return false;
                }
                paradoxOptions.push({ tierMin: t.min, tierMax: t.max, cost: t.cost, calculated: testPrice });
            });
            
            if (matchedPrice > 0) {
                requiredPrice = matchedPrice;
            } else if (paradoxOptions.length > 0) {
                // Paradoks Müdahalesi: Satıcıyı ucuz kargo barajına it.
                for (var j = 0; j < paradoxOptions.length; j++) {
                    if (paradoxOptions[j].calculated < paradoxOptions[j].tierMin) {
                        requiredPrice = paradoxOptions[j].tierMin;
                        s2ShipCost = paradoxOptions[j].cost;
                        break;
                    }
                }
                if (requiredPrice === 0) {
                    requiredPrice = paradoxOptions[0].calculated;
                    s2ShipCost = paradoxOptions[0].cost;
                }
            }
        }

        if (requiredPrice > 0) {
            var s2Comm = requiredPrice * (commRate / 100);
            var s2TotalCost = finalCost + otherCost + s2ShipCost + s2Comm;
            var s2NetProfitReal = requiredPrice - s2TotalCost;
            var s2MarginReal = (s2NetProfitReal / requiredPrice) * 100;

            $('#res2-price').text(fmt(requiredPrice));
            
            // Tabloyu Doldur (Senaryo 2)
            $('#bd-s2-price').text(fmt(requiredPrice));
            $('#bd-s2-comm').text(fmt(s2Comm));
            $('#bd-s2-ship').text(fmt(s2ShipCost));
            $('#bd-s2-total').text(fmt(s2TotalCost));
            $('#bd-s2-profit').text(fmt(s2NetProfitReal));
            $('#bd-s2-margin').text('%' + s2MarginReal.toLocaleString('tr-TR', {minimumFractionDigits:1, maximumFractionDigits:1}));
        } else {
            $('#res2-price').text('0,00 ₺');
            $('#bd-s2-price, #bd-s2-comm, #bd-s2-ship, #bd-s2-total, #bd-s2-profit').text('0,00 ₺');
            $('#bd-s2-margin').text('%0');
        }
    }

    function resetResults() {
        $('#res1-profit, #bd-s1-profit, #bd-s2-profit').text('0,00 ₺').css('color', '#94A3B8');
        $('#res1-margin, #bd-s1-margin, #bd-s2-margin').text('%0').css('color', '#94A3B8');
        $('#res2-price').text('0,00 ₺');
        $('#res1-shipping-info').hide();
        $('td[id^="bd-"]').not('[id$="-margin"]').not('[id$="-profit"]').text('0,00 ₺');
    }
    
    $('<style>.hbt-spin { animation: hbtSpin 1s linear infinite; } @keyframes hbtSpin { 100% { transform: rotate(360deg); } }</style>').appendTo('head');
});
</script>