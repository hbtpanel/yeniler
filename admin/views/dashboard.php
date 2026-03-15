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
                <span class="dashicons dashicons-update"></span> Trendyol'dan Çek
            </a>
            <a href="?page=hbt-tpt-ad-expenses" class="hbt-btn hbt-btn-primary">
                <span class="dashicons dashicons-money-alt"></span> Reklam Gideri Gir
            </a>
        </div>
    </div>

    <div class="hbt-goal-container" id="hbt-goal-container" title="Aylık Kâr Hedefinizi değiştirmek için buraya tıklayın">
        <div class="hbt-goal-info">
            <h3>Aylık Kâr Hedefi <span class="dashicons dashicons-edit"></span></h3>
            <p id="hbt-goal-status-text">Yükleniyor...</p>
        </div>
        <div class="hbt-goal-bar-wrap">
            <div class="hbt-goal-bar-fill" id="hbt-goal-bar"></div>
            <div class="hbt-goal-text" id="hbt-goal-text">%0</div>
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
        <div class="hbt-col-6" style="flex: 1; min-width: 48%;">
            <h3 class="hbt-widget-title"><span class="dashicons dashicons-store"></span> Mağaza Bazlı Net Kâr Özeti</h3>
            <table class="wp-list-table widefat fixed striped">
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
        <div class="hbt-col-6" style="flex: 1; min-width: 48%;">
            <h3 class="hbt-widget-title"><span class="dashicons dashicons-cart"></span> Mağaza Bazlı Net Ciro Özeti</h3>
            <table class="wp-list-table widefat fixed striped">
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

    <div class="hbt-dashboard-row">
        <div class="hbt-col-4">
            <h3 class="hbt-widget-title"><span class="dashicons dashicons-awards"></span> Son 30 Günün Yıldız Ürünleri</h3>
            <ul class="hbt-top-products-list" id="hbt-top-products">
                <li>Yükleniyor...</li>
            </ul>
        </div>
        <div class="hbt-col-8">
            <h3 class="hbt-widget-title"><span class="dashicons dashicons-bell"></span> Akıllı Uyarılar ve Aksiyonlar</h3>
            <div id="hbt-smart-alerts">
                <p style="color:var(--hbt-text-muted);">Yükleniyor...</p>
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

        // En İyi Ürünler
        var productsHtml = '';
        if (d.top_products.length > 0) {
            $.each(d.top_products, function(i, p) {
                productsHtml += '<li><div class="hbt-tp-name" title="'+p.product_name+'">' + p.product_name + ' <span style="font-weight:normal; color:#64748B; font-size:11px;">('+p.total_qty+' Adet)</span></div> <div class="hbt-tp-profit">+' + parseFloat(p.total_profit).toLocaleString('tr-TR') + ' ₺</div></li>';
            });
        } else {
            productsHtml = '<li><p style="color:#64748B; font-size:13px;">Yeterli satış verisi yok.</p></li>';
        }
        $('#hbt-top-products').html(productsHtml);

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

            new Chart(document.getElementById('chart-expenses'), {
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