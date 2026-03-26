<?php
defined( 'ABSPATH' ) || exit;
?>
<div class="wrap hbt-wrap">
    
    <div class="hbt-page-header">
        <h1 class="hbt-page-title">
            <span class="dashicons dashicons-chart-area"></span> 
            <?php esc_html_e( 'SaaS Finansal Dashboard', 'hbt-trendyol-profit-tracker' ); ?>
        </h1>
       <div class="hbt-header-actions">
            <a href="?page=hbt-tpt-stores" class="hbt-btn hbt-btn-outline">
                <span class="dashicons dashicons-update"></span> Veri Çek
            </a>
            <a href="?page=hbt-tpt-ad-expenses" class="hbt-btn hbt-btn-primary">
                <span class="dashicons dashicons-money-alt"></span> Gider Ekle
            </a>
        </div>
    </div>

    <div class="hbt-goal-container" id="hbt-goal-container" title="Aylık Kâr Hedefinizi değiştirmek için buraya tıklayın" style="cursor: pointer;">
        <div class="hbt-goal-info">
            <h3>Aylık Kâr Hedefi <span class="dashicons dashicons-edit" style="opacity:0.7; font-size:16px; margin-left:5px;"></span></h3>
            <p id="hbt-goal-status-text">Yükleniyor...</p>
        </div>
        
        <div class="hbt-goal-bar-wrap" style="margin-bottom: 16px;">
            <div class="hbt-goal-bar-fill" id="hbt-goal-bar"></div>
            <div class="hbt-goal-text" id="hbt-goal-text">%0</div>
        </div>
        
        <div style="margin-top: 16px; padding: 12px 16px; background: rgba(0,0,0,0.2); border-radius: 8px; border-top: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: space-between; box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);">
            <div style="display: flex; align-items: center; gap: 8px;">
                <div style="background: rgba(252, 211, 77, 0.15); padding: 6px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                     <span class="dashicons dashicons-lightbulb" style="color: #FDE047; font-size: 18px; width: 18px; height: 18px; margin:0;"></span>
                </div>
                <span style="font-size: 13px; font-weight: 600; color: #F8FAFC; letter-spacing: 0.3px; text-shadow: 0 1px 2px rgba(0,0,0,0.3);">YZ Tahmini: </span>
            </div>
            <div style="font-size: 18px; font-weight: 800; color: #FDE047; text-shadow: 0 1px 3px rgba(0,0,0,0.5);" id="hbt-ai-projected-profit">Hesaplanıyor...</div>
        </div>
    </div>

     <h3 class="hbt-widget-title">
        <span class="dashicons dashicons-chart-bar"></span> Brüt Ciro Özeti
    </h3>
    <div class="hbt-kpi-grid">
        <div class="hbt-card hbt-card-compact hbt-card-revenue" style="position: relative; grid-column: span 2;">
            <div id="hbt-rev-today-badge" style="position: absolute; top: 16px; right: 16px; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; display: none;"></div>
            <span class="hbt-card-label"><span class="dashicons dashicons-cart"></span> Bugün Ciro</span>
            <span class="hbt-card-value" id="hbt-rev-today" style="font-size: 24px;">0.00 ₺</span>
            <span style="font-size: 13px; color: var(--hbt-text-muted); margin-top: 8px; font-weight: 500;">
                Dün: <span id="hbt-rev-yesterday" style="font-weight: 700; color: var(--hbt-text-main);">0.00 ₺</span>
            </span>
        </div>
        <div class="hbt-card hbt-card-compact hbt-card-revenue" style="position: relative; grid-column: span 2;">
            <div id="hbt-rev-week-badge" style="position: absolute; top: 16px; right: 16px; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; display: none;"></div>
            <span class="hbt-card-label"><span class="dashicons dashicons-chart-pie"></span> Bu Hafta Ciro</span>
            <span class="hbt-card-value" id="hbt-rev-week" style="font-size: 24px;">0.00 ₺</span>
            <span style="font-size: 13px; color: var(--hbt-text-muted); margin-top: 8px; font-weight: 500;">
                Geçen Hft: <span id="hbt-rev-lastweek" style="font-weight: 700; color: var(--hbt-text-main);">0.00 ₺</span>
            </span>
        </div>
        <div class="hbt-card hbt-card-compact hbt-card-revenue" style="position: relative; grid-column: span 2;">
            <div id="hbt-rev-month-badge" style="position: absolute; top: 16px; right: 16px; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; display: none;"></div>
            <span class="hbt-card-label"><span class="dashicons dashicons-chart-line"></span> Bu Ay Ciro</span>
            <span class="hbt-card-value" id="hbt-rev-month" style="font-size: 24px;">0.00 ₺</span>
            <span style="font-size: 13px; color: var(--hbt-text-muted); margin-top: 8px; font-weight: 500;">
                Geçen Ay: <span id="hbt-rev-lastmonth" style="font-weight: 700; color: var(--hbt-text-main);">0.00 ₺</span>
            </span>
        </div>
    </div>

    <h3 class="hbt-widget-title">
        <span class="dashicons dashicons-money-alt"></span> Net Kâr Özeti (Tüm Giderler Düşülmüş)
    </h3>
    <div class="hbt-kpi-grid">
       <div class="hbt-card hbt-card-compact hbt-card-profit" style="position: relative; grid-column: span 2;">
            <div id="hbt-profit-today-badge" style="position: absolute; top: 16px; right: 16px; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; display: none;"></div>
            <span class="hbt-card-label"><span class="dashicons dashicons-calendar-alt"></span> Bugün Net Kâr</span>
            <span class="hbt-card-value" id="hbt-profit-today" style="font-size: 24px;">0.00 ₺</span>
            <span style="font-size: 13px; color: var(--hbt-text-muted); margin-top: 8px; font-weight: 500;">
                Dün: <span id="hbt-profit-yesterday" style="font-weight: 700; color: var(--hbt-text-main);">0.00 ₺</span>
            </span>
        </div>
        <div class="hbt-card hbt-card-compact hbt-card-profit" style="position: relative; grid-column: span 2;">
            <div id="hbt-profit-week-badge" style="position: absolute; top: 16px; right: 16px; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; display: none;"></div>
            <span class="hbt-card-label"><span class="dashicons dashicons-calendar"></span> Bu Hafta Net Kâr</span>
            <span class="hbt-card-value" id="hbt-profit-week" style="font-size: 24px;">0.00 ₺</span>
            <span style="font-size: 13px; color: var(--hbt-text-muted); margin-top: 8px; font-weight: 500;">
                Geçen Hft: <span id="hbt-profit-lastweek" style="font-weight: 700; color: var(--hbt-text-main);">0.00 ₺</span>
            </span>
        </div>
        <div class="hbt-card hbt-card-compact hbt-card-profit" style="position: relative; grid-column: span 2;">
            <div id="hbt-profit-month-badge" style="position: absolute; top: 16px; right: 16px; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; display: none;"></div>
            <span class="hbt-card-label"><span class="dashicons dashicons-portfolio"></span> Bu Ay Net Kâr</span>
            <span class="hbt-card-value" id="hbt-profit-month" style="font-size: 24px;">0.00 ₺</span>
            <span style="font-size: 13px; color: var(--hbt-text-muted); margin-top: 8px; font-weight: 500;">
                Geçen Ay: <span id="hbt-profit-lastmonth" style="font-weight: 700; color: var(--hbt-text-main);">0.00 ₺</span>
            </span>
        </div>
    </div>

   

   <div class="hbt-dashboard-row">
        <div class="hbt-col-6" style="flex: 1; min-width: 48%; max-width: 100%;">
            <h3 class="hbt-widget-title"><span class="dashicons dashicons-store"></span> Mağaza Bazlı Net Kâr Özeti</h3>
            <div style="overflow-x: auto; width: 100%; border-radius: 8px; border: 1px solid var(--hbt-border);">
                <table class="wp-list-table widefat fixed striped" style="min-width: 600px; margin: 0; border: none;">
                    <thead>
                        <tr>
                            <th>Mağaza Adı</th>
                            <th>Bugünkü Net Kâr</th>
                            <th>Dünkü Net Kâr</th>
                            <th>Son 30 Günlük Net Kâr</th>
                        </tr>
                    </thead>
                    <tbody id="hbt-store-breakdown-body">
                        <tr><td colspan="4" style="text-align:center;">Yükleniyor...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="hbt-col-6" style="flex: 1; min-width: 48%; max-width: 100%;">
            <h3 class="hbt-widget-title"><span class="dashicons dashicons-cart"></span> Mağaza Bazlı Net Ciro Özeti</h3>
            <div style="overflow-x: auto; width: 100%; border-radius: 8px; border: 1px solid var(--hbt-border);">
                <table class="wp-list-table widefat fixed striped" style="min-width: 600px; margin: 0; border: none;">
                    <thead>
                        <tr>
                            <th>Mağaza Adı</th>
                            <th>Bugünkü Ciro</th>
                            <th>Dünkü Ciro</th>
                            <th>Son 30 Günlük Ciro</th>
                        </tr>
                    </thead>
                    <tbody id="hbt-store-revenue-body">
                        <tr><td colspan="4" style="text-align:center;">Yükleniyor...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="hbt-card" style="margin-bottom: 24px; background: #f8fafc; border: 1px solid #e2e8f0;">
		<h3 class="hbt-widget-title" style="margin-top: 0; display: flex; align-items: center; gap: 8px; border-bottom: none; padding-bottom: 0;">
			<span class="dashicons dashicons-awards" style="color: #F59E0B; font-size: 24px; width: 24px; height: 24px;"></span> Günün En Çok Ciro Getiren 10 Ürünü
		</h3>
		
		<div id="hbt-top-products-container" style="display: flex; gap: 16px; overflow-x: auto; padding: 16px 4px 10px 4px; scroll-behavior: smooth;">
			<p style="color: var(--hbt-text-muted); font-size: 13px;">Günün şampiyonları hesaplanıyor...</p>
		</div>
	</div>

    <div class="hbt-dashboard-row">
        <div class="hbt-col-8">
            <h3 class="hbt-widget-title"><span class="dashicons dashicons-chart-area"></span> Son 30 Günlük Ciro, Kâr ve Marj Trendi</h3>
            <div style="position: relative; height: 320px; width: 100%;">
                <canvas id="chart-trend"></canvas>
            </div>
        </div>
        <div class="hbt-col-4">
            <h3 class="hbt-widget-title"><span class="dashicons dashicons-chart-pie"></span> Son 30 Gün Gider Dağılımı</h3>
            <div style="position: relative; height: 320px; width: 100%;">
                <canvas id="chart-expenses"></canvas>
            </div>
        </div>
    </div>

    <div class="hbt-dashboard-row" style="align-items: flex-start;">
        <div class="hbt-col-4">
            <h3 class="hbt-widget-title"><span class="dashicons dashicons-awards" style="color: var(--hbt-success);"></span> En Çok Kâr Getirenler</h3>
            <div class="hbt-products-grid" id="hbt-top-products">
                <p style="color:var(--hbt-text-muted); text-align:center; padding:15px;">Yükleniyor...</p>
            </div>
        </div>

        <div class="hbt-col-4">
            <h3 class="hbt-widget-title"><span class="dashicons dashicons-warning" style="color: var(--hbt-danger);"></span> Kan Kaybedenler</h3>
            <div class="hbt-products-grid" id="hbt-worst-products">
                <p style="color:var(--hbt-text-muted); text-align:center; padding:15px;">Yükleniyor...</p>
            </div>
        </div>

        <div class="hbt-col-4">
            <h3 class="hbt-widget-title"><span class="dashicons dashicons-bell" style="color: var(--hbt-info);"></span> Uyarılar & Aksiyonlar</h3>
            <div id="hbt-smart-alerts" style="display: flex; flex-direction: column; gap: 12px;">
                <p style="color:var(--hbt-text-muted); text-align:center; padding:15px;">Yükleniyor...</p>
            </div>
        </div>
    </div>

    <div class="hbt-dashboard-row" id="hbt-return-loss-banner" style="display:none; margin-top: 24px;">
        <div class="hbt-col-12" style="width:100%; box-sizing: border-box; flex:none; background: #FEF2F2; border: 1px solid #FECACA; border-left: 5px solid #DC2626; padding: 20px 24px; border-radius: 8px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 6px rgba(220, 38, 38, 0.05);">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div style="background: #FEE2E2; padding: 12px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <span class="dashicons dashicons-warning" style="color: #DC2626; font-size: 28px; width: 28px; height: 28px;"></span>
                </div>
                <div>
                    <h4 style="margin: 0 0 4px 0; color: #991B1B; font-size: 16px; font-weight: 700;">İade Kaynaklı "Görünmez Zarar" Tespit Edildi!</h4>
                    <p style="margin: 0; color: #DC2626; font-size: 13px;">Son 30 günde iadeler yüzünden çöpe giden kargo ve Trendyol kesinti maliyetiniz.</p>
                </div>
            </div>
            <div style="text-align: right; background: #fff; padding: 10px 20px; border-radius: 6px; border: 1px solid #FECACA;">
                <div style="font-size: 22px; font-weight: 800; color: #DC2626;" id="val-return-loss">0,00 ₺</div>
                <div style="font-size: 11px; color: #991B1B; font-weight: 600; margin-top: 2px;">Sadece Kargo Zararı: <span id="val-return-shipping">0,00 ₺</span></div>
            </div>
        </div>
    </div>

   

</div>

<script>
jQuery(document).ready(function($) {
    
    // Hedef Kârı Değiştirme
    $('#hbt-goal-container').on('click', function() {
        var currentGoal = $(this).attr('data-goal') || 50000;
        var newGoal = prompt("Yeni Aylık Net Kâr Hedefinizi (TL) girin:", currentGoal);
        if (newGoal !== null && !isNaN(newGoal) && newGoal > 0) {
            $.post(hbtTpt.ajaxurl, {
                action: 'hbt_save_profit_goal',
                nonce: hbtTpt.nonce,
                goal: newGoal
            }, function(res) {
                if(res.success) location.reload();
            });
        }
    });

    // Yeni SaaS Renklerine Uygun Money Format
    var formatMoney = function(val, forceColor) {
        var str = parseFloat(val).toLocaleString('tr-TR', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' ₺';
        if (forceColor) {
            var color = val >= 0 ? '#10B981' : '#EF4444'; // Yeni Emerald ve Red
            return '<span style="color:'+color+'; font-weight:800;">' + str + '</span>';
        }
        return str;
    };

   // Ana Verileri Çekme (Yeniden kullanılabilir fonksiyon haline getirdik)
    function hbtLoadDashboardData() {
        $.post(hbtTpt.ajaxurl, { action: 'hbt_get_dashboard_data', nonce: hbtTpt.nonce }, function(res) {
        if (!res.success) return;
        var d = res.data;

        // Yüzdelik Değişim Balonu (Badge) Oluşturucu Fonksiyon
        function createTrendBadge(current, previous, badgeElementId) {
            var curr = parseFloat(current) || 0;
            var prev = parseFloat(previous) || 0;
            var badge = $('#' + badgeElementId);
            
            if (prev !== 0) {
                var trend = ((curr - prev) / Math.abs(prev)) * 100;
                var sign = trend > 0 ? '+' : '';
                badge.text(sign + trend.toFixed(1) + '%');
                badge.css({
                    'display': 'inline-block',
                    'background-color': trend >= 0 ? '#D1FAE5' : '#FEE2E2',
                    'color': trend >= 0 ? '#065F46' : '#991B1B'
                });
            } else if (curr > 0) {
                badge.text('+100%');
                badge.css({'display': 'inline-block', 'background-color': '#D1FAE5', 'color': '#065F46'});
            }
        }

        // KPI Ciro Kartları 
        $('#hbt-rev-today').html(formatMoney(d.revenue_today, false));
        $('#hbt-rev-yesterday').html(formatMoney(d.revenue_yesterday, false));
        $('#hbt-rev-week').html(formatMoney(d.revenue_week, false));
        $('#hbt-rev-lastweek').html(formatMoney(d.revenue_last_week, false));
        $('#hbt-rev-month').html(formatMoney(d.revenue_month, false));
        $('#hbt-rev-lastmonth').html(formatMoney(d.revenue_last_month, false));

       // Ciro Balonları ("Bu Saate Kadar" olan metriklerle kıyaslıyoruz)
        createTrendBadge(d.revenue_today, d.revenue_yesterday_upto_now, 'hbt-rev-today-badge');
        createTrendBadge(d.revenue_week, d.revenue_last_week_upto_now, 'hbt-rev-week-badge');
        createTrendBadge(d.revenue_month, d.revenue_last_month_upto_now, 'hbt-rev-month-badge');

        // KPI Net Kâr Kartları (Alt metinler hala günün/haftanın tamamını gösterir)
        $('#hbt-profit-today').html(formatMoney(d.profit_today, true));
        $('#hbt-profit-yesterday').html(formatMoney(d.profit_yesterday, false)); 
        $('#hbt-profit-week').html(formatMoney(d.profit_week, true));
        $('#hbt-profit-lastweek').html(formatMoney(d.profit_last_week, false));
        $('#hbt-profit-month').html(formatMoney(d.profit_month, true));
        $('#hbt-profit-lastmonth').html(formatMoney(d.profit_last_month, false));

        // Kâr Balonları ("Bu Saate Kadar" olan metriklerle kıyaslıyoruz)
        createTrendBadge(d.profit_today, d.profit_yesterday_upto_now, 'hbt-profit-today-badge'); 
        createTrendBadge(d.profit_week, d.profit_last_week_upto_now, 'hbt-profit-week-badge');
        createTrendBadge(d.profit_month, d.profit_last_month_upto_now, 'hbt-profit-month-badge');

        // Hedef Barını Doldur
        var goal = parseFloat(d.profit_goal);
        $('#hbt-goal-container').attr('data-goal', goal);
        var profitMonth = parseFloat(d.profit_month);
        var percent = goal > 0 ? (profitMonth / goal) * 100 : 0;
        if(percent < 0) percent = 0;
        var displayPercent = percent > 100 ? 100 : percent;
        
        $('#hbt-goal-bar').css('width', displayPercent + '%');
        $('#hbt-goal-text').text('%' + percent.toFixed(1) + ' Tamamlandı');
        $('#hbt-goal-status-text').text(profitMonth.toLocaleString('tr-TR', {minimumFractionDigits:0}) + ' ₺ / Hedef: ' + goal.toLocaleString('tr-TR', {minimumFractionDigits:0}) + ' ₺');
        // YZ Ay Sonu Tahmini (Run-Rate Predictor Algoritması)
            var today = new Date();
            var currentDay = today.getDate(); // Ayın kaçıncı günündeyiz (Örn: 19)
            var daysInMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0).getDate(); // Bu ay kaç çekiyor (Örn: 31)
            var projectedProfit = 0;
            
            if (currentDay > 0 && profitMonth > 0) {
                // Günlük ortalama hızını bul ve ay sonuna yansıt
                var dailyAvg = profitMonth / currentDay;
                projectedProfit = dailyAvg * daysInMonth;
            } else if (profitMonth <= 0) {
                // Eğer eksideysek veya ciro yoksa mevcut durumu göster
                projectedProfit = profitMonth; 
            }

            // Eğer tahmin edilen kâr hedefi geçiyorsa Yeşil, artıda ama geçmiyorsa Sarı, zarardaysa Kırmızı renk ver.
            var projColor = projectedProfit >= goal && goal > 0 ? '#86EFAC' : (projectedProfit > 0 ? '#FDE047' : '#FCA5A5');
            var projSign = projectedProfit > 0 ? '+' : '';
            
            $('#hbt-ai-projected-profit')
                .css('color', projColor)
                .text(projSign + projectedProfit.toLocaleString('tr-TR', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' ₺');

        // Akıllı Uyarılar
        var alertsHtml = '';
        if(d.smart_alerts && d.smart_alerts.length > 0) {
            $.each(d.smart_alerts, function(i, alert) {
                alertsHtml += '<div class="hbt-alert-box hbt-alert-' + alert.type + '"><span class="dashicons ' + alert.icon + '"></span> <div>' + alert.msg + '</div></div>';
            });
        } else {
            alertsHtml = '<p style="color:#64748B; font-size:13px; margin:0;">Şu an için uyarı bulunmuyor, her şey yolunda! 🎉</p>';
        }
        $('#hbt-smart-alerts').html(alertsHtml);

        // Ürün Listelerini Oluşturma Fonksiyonu (Daha Temiz Kod)
        function renderProductList(data, containerId, successMessage) {
            var html = '';
            if (data && data.length > 0) {
                $.each(data, function(i, p) {
                    var profitClass = parseFloat(p.total_profit) >= 0 ? 'hbt-tp-profit-success' : 'hbt-tp-profit-danger';
                    var profitSign = parseFloat(p.total_profit) >= 0 ? '+' : '';
                    
                    html += '<div class="hbt-tp-item">' +
                                '<div class="hbt-tp-name" title="'+p.product_name+'">' + p.product_name + '</div>' +
                                '<div class="hbt-tp-meta">(' + p.total_qty + ' Adet)</div>' +
                                '<div class="hbt-tp-profit ' + profitClass + '">' + profitSign + parseFloat(p.total_profit).toLocaleString('tr-TR', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' ₺</div>' +
                            '</div>';
                });
            } else {
                html = '<div class="hbt-no-data">' + successMessage + '</div>';
            }
            $('#' + containerId).html(html);
        }

        // En İyi Ürünler
        renderProductList(d.top_products, 'hbt-top-products', 'Yeterli satış verisi yok.');

        // Kan Kaybedenler (Zarar Eden Ürünler)
        renderProductList(d.worst_products, 'hbt-worst-products', 'Harika! Son 30 günde zarar ettiren ürününüz yok. 🎉');

       // Mağaza Bazlı Rapor Tablosu (Kâr ve Ciro Trend Yüzdelikleri Eklenmiş Hali)
        var profitMap = {};
        var revenueMap = {};
        function initStoreMaps(id, name) { 
            if(!profitMap[id]) profitMap[id] = { name: name, today: 0, yesterday: 0, l30: 0, today_prev: 0, yesterday_prev: 0, l30_prev: 0 }; 
            if(!revenueMap[id]) revenueMap[id] = { name: name, today: 0, yesterday: 0, l30: 0, today_prev: 0, yesterday_prev: 0, l30_prev: 0 }; 
        }
        
        // Mevcut Değerleri Haritalara Ata (Kâr ve Ciro)
        $.each(d.stores_today, function(i, st) { 
            initStoreMaps(st.id, st.store_name); 
            profitMap[st.id].today = parseFloat(st.profit) || 0; 
            revenueMap[st.id].today = parseFloat(st.revenue) || 0; 
        });
        $.each(d.stores_yesterday, function(i, st) { 
            initStoreMaps(st.id, st.store_name); 
            profitMap[st.id].yesterday = parseFloat(st.profit) || 0; 
            revenueMap[st.id].yesterday = parseFloat(st.revenue) || 0; 
        });
        $.each(d.store_comparison, function(i, st) { 
            initStoreMaps(st.id, st.store_name); 
            profitMap[st.id].l30 = parseFloat(st.profit) || 0; 
            revenueMap[st.id].l30 = parseFloat(st.revenue) || 0; 
        });

        // Geçmiş Kıyaslama Değerleri (Trend İçin - Aynı Saate Kadar)
        if (d.stores_yesterday_upto_now) { 
            $.each(d.stores_yesterday_upto_now, function(i, st) { 
                if(profitMap[st.id]) profitMap[st.id].today_prev = parseFloat(st.profit) || 0; 
                if(revenueMap[st.id]) revenueMap[st.id].today_prev = parseFloat(st.revenue) || 0; 
            }); 
        }
        if (d.stores_2days_ago) { 
            $.each(d.stores_2days_ago, function(i, st) { 
                if(profitMap[st.id]) profitMap[st.id].yesterday_prev = parseFloat(st.profit) || 0; 
                if(revenueMap[st.id]) revenueMap[st.id].yesterday_prev = parseFloat(st.revenue) || 0; 
            }); 
        }
        if (d.stores_prev_30days) { 
            $.each(d.stores_prev_30days, function(i, st) { 
                if(profitMap[st.id]) profitMap[st.id].l30_prev = parseFloat(st.profit) || 0; 
                if(revenueMap[st.id]) revenueMap[st.id].l30_prev = parseFloat(st.revenue) || 0; 
            }); 
        }

        // Satır içi (+%XX) formatlayıcı
        function getInlineTrend(current, previous) {
            if (previous === 0 && current === 0) return '';
            var trend = previous !== 0 ? ((current - previous) / Math.abs(previous)) * 100 : (current > 0 ? 100 : 0);
            if (trend === 0) return '';
            var sign = trend > 0 ? '+' : '';
            var color = trend > 0 ? '#10B981' : '#EF4444'; // Yeşil ve Kırmızı
            return ' <span style="font-size:12px; font-weight:700; color:' + color + '; margin-left:6px;">(' + sign + trend.toFixed(1) + '%)</span>';
        }

        // Kâr Tablosunu Oluştur
        var profitBody = '';
        $.each(profitMap, function(id, st) {
            profitBody += '<tr>';
            profitBody += '<td><strong>' + st.name + '</strong></td>';
            profitBody += '<td>' + formatMoney(st.today, true) + getInlineTrend(st.today, st.today_prev) + '</td>';
            profitBody += '<td>' + formatMoney(st.yesterday, true) + getInlineTrend(st.yesterday, st.yesterday_prev) + '</td>';
            profitBody += '<td>' + formatMoney(st.l30, true) + getInlineTrend(st.l30, st.l30_prev) + '</td>';
            profitBody += '</tr>';
        });
        if(profitBody === '') profitBody = '<tr><td colspan="4" style="text-align:center; color:#64748B;">Kayıtlı mağaza veya veri bulunamadı.</td></tr>';
        $('#hbt-store-breakdown-body').html(profitBody);

        // Ciro Tablosunu Oluştur
        var revenueBody = '';
        $.each(revenueMap, function(id, st) {
            revenueBody += '<tr>';
            revenueBody += '<td><strong>' + st.name + '</strong></td>';
            revenueBody += '<td>' + formatMoney(st.today, true) + getInlineTrend(st.today, st.today_prev) + '</td>';
            revenueBody += '<td>' + formatMoney(st.yesterday, true) + getInlineTrend(st.yesterday, st.yesterday_prev) + '</td>';
            revenueBody += '<td>' + formatMoney(st.l30, true) + getInlineTrend(st.l30, st.l30_prev) + '</td>';
            revenueBody += '</tr>';
        });
        if(revenueBody === '') revenueBody = '<tr><td colspan="4" style="text-align:center; color:#64748B;">Kayıtlı mağaza veya veri bulunamadı.</td></tr>';
        $('#hbt-store-revenue-body').html(revenueBody);

       // ÜÇ EKSENLİ TREND GRAFİĞİ (Ciro Eklendi)
        if (document.getElementById('chart-trend')) {
            var labels = [], profitData = [], marginData = [], revenueData = [];
            $.each(d.trend, function(i, item) {
                labels.push(item.day.slice(5)); 
                profitData.push(item.profit);
                marginData.push(item.margin);
                revenueData.push(item.revenue); // Ciro verisini diziye ekliyoruz
            });
            new Chart(document.getElementById('chart-trend'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        { label: 'Brüt Ciro (TL)', data: revenueData, borderColor: '#94A3B8', backgroundColor: 'rgba(148, 163, 184, 0.15)', fill: true, tension: 0.4, yAxisID: 'y', pointRadius: 2, borderWidth: 2 },
                        { label: 'Net Kâr (TL)', data: profitData, borderColor: '#2563EB', backgroundColor: 'rgba(37, 99, 235, 0.2)', fill: true, tension: 0.4, yAxisID: 'y', pointRadius: 3, borderWidth: 3 },
                        { label: 'Kâr Marjı (%)', data: marginData, borderColor: '#F59E0B', backgroundColor: 'transparent', borderDash: [5, 5], tension: 0.4, yAxisID: 'y1', pointRadius: 3, borderWidth: 2 }
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { labels: { font: { family: "'Inter', sans-serif" } } } },
                    scales: {
                        y: { type: 'linear', display: true, position: 'left', title: {display:true, text:'Tutar (TL)'}, grid: {color: '#E2E8F0'} },
                        y1: { type: 'linear', display: true, position: 'right', grid: {drawOnChartArea: false}, title: {display:true, text:'Marj (%)'} }
                    }
                }
            });
        }

       // GİDER DAĞILIMI PASTA GRAFİĞİ (Yüzdeler ve Sabit Giderler Eklendi)
        if (document.getElementById('chart-expenses')) {
            var exp = d.expense_breakdown;
            // Reklam ve Sabit Giderleri birleştir
            var totalAdsAndFixed = parseFloat(exp.total_ads || 0) + parseFloat(exp.total_other_exp || 0);
            
            // Gerçek Kârı hesaplarken hepsini düş
            var realProfit = Math.max(0, exp.total_sales - exp.total_cost - exp.total_comm - exp.total_ship - totalAdsAndFixed);
            
            var chartData = [exp.total_cost, exp.total_comm, exp.total_ship, totalAdsAndFixed, realProfit];
            var baseLabels = ['Ürün Maliyeti', 'Komisyon', 'Kargo', 'Reklam/Sabit', 'Net Kâr'];
            
            // Toplam Tutarı Bul ve Yüzdelikleri Hesapla
            var totalSum = chartData.reduce(function(a, b) { return parseFloat(a) + parseFloat(b); }, 0);
            
            var labelsWithPercent = baseLabels.map(function(label, index) {
                var value = parseFloat(chartData[index]);
                var percent = totalSum > 0 ? ((value / totalSum) * 100).toFixed(1) : 0;
                return label + ' (%' + percent + ')';
            });

            // Grafik yenilenirken üst üste binmesini engelle
            if(window.hbtExpenseChart) { window.hbtExpenseChart.destroy(); }

            window.hbtExpenseChart = new Chart(document.getElementById('chart-expenses'), {
                type: 'doughnut',
                data: {
                    labels: labelsWithPercent,
                    datasets: [{
                        data: chartData,
                        backgroundColor: ['#F59E0B', '#F97316', '#EF4444', '#64748B', '#10B981'], 
                        borderWidth: 2, borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false, cutout: '70%',
                    plugins: { 
                        legend: { position: 'right', labels: { padding: 15, boxWidth: 12, font: {size: 12, family: "'Inter', sans-serif"} } },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    var val = parseFloat(context.raw).toLocaleString('tr-TR', {minimumFractionDigits:2, maximumFractionDigits:2});
                                    return ' ' + val + ' ₺';
                                }
                            }
                        }
                    }
                }
            });
        }

      // İADE KAYNAKLI GÖRÜNMEZ ZARAR KONTROLÜ (MOBİL UYUMLU VERSİYON)
        if (d.return_loss_stats) {
            var netLoss = parseFloat(d.return_loss_stats.total_net_loss) || 0;
            var shipLoss = parseFloat(d.return_loss_stats.total_shipping_loss) || 0;
            
            // Eğer net zarar hesaplanmamışsa (0 ise) ama kargo parası yanmışsa, kargo zararını ana zarar kabul et.
            var displayTotalLoss = Math.max(netLoss, shipLoss);

            // Sadece gerçekten bir zarar varsa (0'dan büyükse) afişi göster
            if (displayTotalLoss > 0) {
                $('#val-return-loss').text(displayTotalLoss.toLocaleString('tr-TR', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' ₺');
                $('#val-return-shipping').text(shipLoss.toLocaleString('tr-TR', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' ₺');
                
                // --- MOBİL UYUMLULUK DOKUNUŞU ---
                if (window.innerWidth <= 782) {
                    // Ana konteyneri dikey ve ortalı yap
                    $('#hbt-return-loss-banner > div').css({ 
                        'flex-direction': 'column', 
                        'align-items': 'center', // Merkeze hizala
                        'gap': '15px', 
                        'padding': '16px 20px', // Mobilde padding'i biraz daralt
                        'text-align': 'center' // Yazıları ortala
                    });
                    
                    // Metin bloğundaki ikon ve yazıları dikey ve ortalı yap
                    $('#hbt-return-loss-banner > div > div:first-child').css({
                        'flex-direction': 'column',
                        'align-items': 'center',
                        'text-align': 'center',
                        'gap': '8px'
                    });

                    // Veri bloğunu (Rakamları) dikey ve ortalı yap
                    $('#hbt-return-loss-banner > div > div:last-child').css({
                        'width': '100%', 
                        'text-align': 'center', 
                        'box-sizing': 'border-box',
                        'flex-direction': 'column',
                        'display': 'flex',
                        'align-items': 'center'
                    });
                }
                
                $('#hbt-return-loss-banner').slideDown(400);
            }
        
        }
    }); // <--- $.post ana veri çekme işleminin kapanışı
    
    } // <--- 1. ADIMDA AÇTIĞIMIZ hbtLoadDashboardData FONKSİYONUNUN KAPANIŞI

    // Sayfa ilk yüklendiğinde verileri çekmesi için fonksiyonumuzu 1 kere çalıştırıyoruz:
    hbtLoadDashboardData();

    // --- GERÇEK ZAMANLI (REAL-TIME) AKILLI YOKLAMA VE ANİMASYON ---
    var hbtLastUpdateHash = null;

    // Sayfaya havalı bir CSS parlama (flash) animasyonu enjekte ediyoruz
    $('<style>' +
      '.hbt-value-flash { animation: hbtFlashBlink 1.5s ease-out; } ' +
      '@keyframes hbtFlashBlink { 0% { color: #10B981 !important; text-shadow: 0 0 10px rgba(16,185,129,0.8); transform: scale(1.08); display: inline-block; } 100% { color: inherit; text-shadow: none; transform: scale(1); display: inline-block; } }' +
      '</style>').appendTo('head');

    function checkHbtLiveUpdates() {
        $.post(hbtTpt.ajaxurl, { action: 'hbt_check_dashboard_updates', nonce: hbtTpt.nonce }, function(res) {
            if (res.success && res.data.last_update) {
                if (hbtLastUpdateHash === null) {
                    // Sayfa ilk açıldığında referans saati kaydet
                    hbtLastUpdateHash = res.data.last_update; 
                } else if (hbtLastUpdateHash !== res.data.last_update) {
                    // DEĞİŞİKLİK YAKALANDI! Referansı güncelle
                    hbtLastUpdateHash = res.data.last_update;
                    
                    // Kart rakamlarına parlama efekti ver
                    $('.hbt-card-value').addClass('hbt-value-flash');
                    setTimeout(function() {
                        $('.hbt-card-value').removeClass('hbt-value-flash');
                    }, 1500);

                    // Arka planda değişiklik algılandığında hazırladığımız fonksiyonu çağırıyoruz
                    hbtLoadDashboardData(); 
                }
            }
        });
    }

    // Her 10 saniyede bir arkada sessizce kontrol yap
    setInterval(checkHbtLiveUpdates, 10000);

}); // <--- jQuery(document).ready kapanışı (Dosyanın sonu)
</script>

<script>
jQuery(document).ready(function($) {
    
    // İşlemi bir fonksiyona sardık ki hem ilk açılışta hem de her dakikada bir çağırabilelim
    function hbtFetchTopProducts() {
        $.post(ajaxurl, {
            action: 'hbt_get_dashboard_data',
            nonce: typeof hbtTpt !== 'undefined' ? hbtTpt.nonce : ''
        }, function(response) {
            if (response.success && response.data.top_products_today) {
                var topData = response.data.top_products_today;
                var $container = $('#hbt-top-products-container');

                if (topData.length > 0) {
                    var topHtml = '';
                    topData.forEach(function(p) {
                        var imgUrl = p.image_url ? p.image_url : ''; 
                        var qty = p.total_quantity || 0;
                        var rev = parseFloat(p.total_revenue || 0).toLocaleString('tr-TR', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' ₺';
                        
                        var rawName = String(p.product_name || '');
                        var bCode = String(p.barcode || '');
                        
                        if (bCode !== '' && rawName.indexOf(bCode) !== -1) {
                            rawName = rawName.replace(bCode, '');
                        }
                        var cleanName = rawName.replace(/[\s\-()]+$/, '').trim();
                        var shortName = cleanName.length > 30 ? cleanName.substring(0, 30) + '...' : cleanName;
                        var nameEscaped = cleanName.replace(/"/g, '&quot;').replace(/'/g, '&#39;');

                        var imgElement = imgUrl
                            ? '<img src="' + imgUrl + '" class="hbt-zoomable-image" style="width: 100%; height: 110px; object-fit: contain; background: #fff; border-radius: 6px; border: 1px solid #e2e8f0; cursor: zoom-in;" title="Büyütmek için tıklayın" />'
                            : '<div style="width: 100%; height: 110px; background: #f1f5f9; border-radius: 6px; border: 1px dashed #cbd5e1; display: flex; align-items: center; justify-content: center;"><span class="dashicons dashicons-format-image" style="color: #94a3b8; font-size: 32px; width: 32px; height: 32px;"></span></div>';

                        topHtml += '<div style="min-width: 150px; max-width: 150px; background: #fff; border: 1px solid var(--hbt-border); border-radius: 8px; padding: 12px; position: relative; text-align: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); transition: transform 0.2s;">' +
                            '<div style="position: absolute; top: -10px; right: -10px; background: #ef4444; color: #fff; font-size: 13px; font-weight: bold; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 4px rgba(0,0,0,0.2); z-index: 10;" title="Bugün Satılan Adet">' + qty + '</div>' +
                            '<div style="margin-bottom: 10px; position: relative; background: #fff;">' + imgElement + '</div>' +
                            '<div class="hbt-product-name-toggle" data-full="' + nameEscaped + '" style="font-size: 12px; font-weight: 500; color: var(--hbt-text-main); margin-bottom: 8px; line-height: 1.4; cursor: pointer; height: 34px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;" title="Tamamını okumak için tıklayın">' + shortName + '</div>' +
                            '<div style="font-size: 15px; font-weight: 700; color: #10b981;">' + rev + '</div>' +
                        '</div>';
                    });
                    
                    $container.html(topHtml);
                } else {
                    $container.html('<p style="color: #64748b; font-size: 13px;">Bugün için henüz ciro getiren ürün bulunmuyor.</p>');
                }
            }
        });
    }

    // 1. Sayfa ilk açıldığında verileri anında getir
    hbtFetchTopProducts();

    // 2. Her 60.000 milisaniyede (Tam 1 Dakika) bir arka planda otomatik güncelle
    setInterval(hbtFetchTopProducts, 60000);

});
</script>