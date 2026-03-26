<?php
defined( 'ABSPATH' ) || exit;
?>
<style>
    #wpadminbar, #adminmenuback, #adminmenuwrap, #wpfooter, .update-nag, .notice { display: none !important; }
    #wpcontent { margin: 0 !important; padding: 0 !important; background: #0f172a !important; }
    html.wp-toolbar { padding-top: 0 !important; }
    body { background: #0f172a !important; overflow: hidden; font-family: 'Inter', sans-serif; }

    /* Neon Uzay Teması Değişkenleri */
    :root {
        --tv-bg: #0f172a;
        --tv-card: #1e293b;
        --tv-border: #334155;
        --tv-neon-green: #10b981;
        --tv-neon-purple: #8b5cf6;
        --tv-neon-blue: #3b82f6;
        --tv-text: #f8fafc;
        --tv-text-muted: #94a3b8;
    }

    /* TV Ana Konteyner */
    .tv-container { height: 100vh; display: flex; flex-direction: column; position: relative; }
    
    /* Üst Bar (Başlık ve Kontroller) */
    .tv-header { padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--tv-border); background: rgba(30, 41, 59, 0.8); }
    .tv-title { font-size: 24px; font-weight: 800; color: var(--tv-text); margin: 0; display: flex; align-items: center; gap: 10px; }
    .tv-title .live-dot { width: 12px; height: 12px; background: var(--tv-neon-green); border-radius: 50%; box-shadow: 0 0 10px var(--tv-neon-green); animation: pulse 1.5s infinite; }
    
    .tv-controls { display: flex; gap: 15px; }
    .tv-btn { background: var(--tv-card); border: 1px solid var(--tv-border); color: var(--tv-text); padding: 8px 16px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: all 0.2s; }
    .tv-btn:hover { background: #334155; }
    .tv-btn.guest-active { border-color: var(--tv-neon-purple); color: var(--tv-neon-purple); box-shadow: 0 0 10px rgba(139,92,246,0.3); }

    /* Spotlight (Sahne Işığı - Top 20) */
    .tv-spotlight-area { padding: 30px 40px; display: flex; align-items: center; gap: 40px; height: 180px; border-bottom: 1px solid rgba(255,255,255,0.05); }
    .tv-spot-rank { font-size: 80px; font-weight: 900; color: rgba(255,255,255,0.05); line-height: 1; min-width: 120px; }
    .tv-spot-img { width: 140px; height: 140px; object-fit: contain; background: #fff; border-radius: 16px; box-shadow: 0 0 20px rgba(255,255,255,0.1); }
    .tv-spot-info { flex: 1; }
    .tv-spot-name { font-size: 32px; font-weight: 800; color: var(--tv-text); margin-bottom: 10px; line-height: 1.3; }
    .tv-spot-stats { display: flex; gap: 30px; }
    .tv-spot-stat { font-size: 24px; font-weight: 700; color: var(--tv-neon-green); text-shadow: 0 0 10px rgba(16,185,129,0.3); }
    .tv-spot-stat.qty { color: var(--tv-neon-blue); text-shadow: 0 0 10px rgba(59,130,246,0.3); }

    /* Ürün Grid Alanı (Tümü) */
    .tv-grid-area { flex: 1; padding: 30px 40px; overflow-y: auto; display: flex; align-content: flex-start; flex-wrap: wrap; gap: 20px; }
    .tv-grid-area::-webkit-scrollbar { display: none; }
    .tv-card { width: 180px; background: var(--tv-card); border: 1px solid var(--tv-border); border-radius: 12px; padding: 15px; position: relative; text-align: center; display: flex; flex-direction: column; transition: transform 0.3s; }
    .tv-card-badge { position: absolute; top: -10px; right: -10px; background: var(--tv-neon-blue); color: #fff; font-size: 14px; font-weight: 800; width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 0 10px rgba(59,130,246,0.5); z-index: 10;}
    .tv-card-img { width: 100%; height: 100px; object-fit: contain; background: #fff; border-radius: 8px; margin-bottom: 10px; }
    .tv-card-name { font-size: 12px; color: var(--tv-text-muted); font-weight: 500; margin-bottom: 10px; flex: 1; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .tv-card-rev { font-size: 16px; font-weight: 800; color: var(--tv-neon-green); }

    /* Alt Kayan Bant (Yüzen Ada) */
    .tv-ticker { 
        height: 60px; 
        background: var(--tv-neon-purple); 
        color: #fff; 
        display: flex; 
        align-items: center; 
        font-size: 20px; 
        font-weight: 600; 
        overflow: hidden; 
        position: relative; 
        box-shadow: 0 -5px 20px rgba(139,92,246,0.4);
        margin: 0 20px 20px 20px;
        border-radius: 12px;
    }
    /* YENİ: Animasyon süresi 120 saniyeye çıkarılarak çok daha okunaklı ve yavaş hale getirildi */
    .tv-ticker-content { white-space: nowrap; animation: ticker 120s linear infinite; }
    .tv-ticker-content span { padding: 0 15px; }

    /* Animasyonlar */
    @keyframes pulse { 0% { transform: scale(0.95); opacity: 0.5; } 50% { transform: scale(1.1); opacity: 1; } 100% { transform: scale(0.95); opacity: 0.5; } }
    @keyframes ticker { 0% { transform: translateX(100vw); } 100% { transform: translateX(-100%); } }
    .fade-enter { animation: fadeIn 0.5s forwards; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    
    @keyframes cardFlash { 
        0% { transform: scale(1); box-shadow: 0 0 0 rgba(16,185,129,0); border-color: var(--tv-border); } 
        15% { transform: scale(1.08); box-shadow: 0 0 30px rgba(16,185,129,1); border-color: var(--tv-neon-green); z-index: 50; } 
        100% { transform: scale(1); box-shadow: 0 0 0 rgba(16,185,129,0); border-color: var(--tv-border); z-index: 1; } 
    }
    .card-flash { animation: cardFlash 2.5s ease-out; }

    /* --- YENİ EKLENEN: GÜNÜN İLK SATIŞI VE KUYRUKLU YILDIZ ANİMASYONLARI --- */
    
    /* Devasa Hologram Kapsayıcısı */
    #tv-first-sale-overlay {
        position: fixed;
        top: 0; left: 0; width: 100vw; height: 100vh;
        background: rgba(15, 23, 42, 0.95);
        z-index: 999999;
        display: none;
        align-items: center; justify-content: center;
        flex-direction: column;
        color: #fff;
        opacity: 0;
        transition: opacity 0.5s;
    }

    #tv-first-sale-overlay.overlay-active { display: flex; opacity: 1; }

    /* Devasa Hologram Kutusu (Yanıp Sönme ve Parlama) */
    .tv-hologram-box {
        background: #1e293b;
        border: 4px solid var(--tv-neon-green);
        border-radius: 20px;
        padding: 60px;
        text-align: center;
        box-shadow: 0 0 50px rgba(16, 185, 129, 0.8), inset 0 0 20px rgba(16, 185, 129, 0.5);
        position: relative;
        animation: hologramBlink 2s infinite, hologramGlow 3s infinite;
    }

    @keyframes hologramBlink { 0%, 100% { opacity: 1; } 50% { opacity: 0.8; } }
    @keyframes hologramGlow { 0%, 100% { box-shadow: 0 0 50px rgba(16, 185, 129, 0.8); } 50% { box-shadow: 0 0 80px rgba(16, 185, 129, 1); } }

    /* Kuyruklu Yıldız (Karta Kayma) Animasyonu */
    .tv-comet-animate {
        position: absolute;
        width: 180px; height: 200px; /* Kartın boyutları */
        background: #1e293b;
        border: 1px solid var(--tv-border);
        border-radius: 12px;
        box-shadow: 0 0 30px rgba(16, 185, 129, 1);
        z-index: 9999999;
        overflow: hidden;
        transition: all 1.5s ease-in-out;
        opacity: 0;
        transform: translate(-50%, -50%) scale(1.5);
    }

    /* Kuyruklu Yıldızın Kuyruğu */
    .tv-comet-animate::after {
        content: '';
        position: absolute;
        top: 0; left: -100px;
        width: 100px; height: 100%;
        background: linear-gradient(to right, rgba(16, 185, 129, 0), rgba(16, 185, 129, 0.8));
        transform: skewX(-20deg);
    }
    /* Şov sırasındaki kuyruklu yıldız hedefini belirleyen gizli boşluk */
    .first-sale-hidden { visibility: hidden !important; }
    /* --- YENİ EKLENEN: TAM EKRAN MODUNDA BUTONLARI GİZLE --- */
    :fullscreen .tv-controls { display: none !important; }
    :-webkit-full-screen .tv-controls { display: none !important; }
    :-moz-full-screen .tv-controls { display: none !important; }
    :-ms-fullscreen .tv-controls { display: none !important; }
    /* --- YENİ EKLENEN: TAM EKRAN MODUNDA ÜST BAR'I AŞAĞI İT (TV Safe Zone) --- */
    :fullscreen .tv-header { padding-top: 45px !important; }
    :-webkit-full-screen .tv-header { padding-top: 45px !important; }
    :-moz-full-screen .tv-header { padding-top: 45px !important; }
    :-ms-fullscreen .tv-header { padding-top: 45px !important; }

    /* --- YENİ EKLENEN: ANLIK SATIŞ POP-OUT VE SÜZÜLME ANİMASYONLARI --- */
    
    /* Pop-Out Durumundaki Kartın Temel Hali (Ekranın Ortası) */
    .tv-card-celebrate-active {
        position: fixed !important;
        top: 50% !important;
        left: 50% !important;
        transform: translate(-50%, -50%) scale(2.2) !important; /* Devasa Büyüt */
        z-index: 9999999 !important;
        box-shadow: 0 0 100px rgba(16, 185, 129, 1), 0 0 30px rgba(16, 185, 129, 0.5) !important;
        border-color: var(--tv-neon-green) !important;
        transition: none !important; /* Ortaya gelirken transition olmasın, anında ışınlansın */
        animation: hologramBlink 2s infinite; /* First sale'deki yanıp sönmeyi kullan */
    }

    /* Pop-Out İçindeki Yazı Boyutlarını Büyüt */
    .tv-card-celebrate-active .tv-card-name { font-size: 16px !important; -webkit-line-clamp: 3 !important; }
    .tv-card-celebrate-active .tv-card-rev { font-size: 22px !important; }
    .tv-card-celebrate-active .tv-card-badge { width: 45px !important; height: 45px !important; font-size: 20px !important; top: -15px !important; right: -15px !important; }

    /* Geri Dönüş (Süzülme) Efekti - Transition Aktif */
    .tv-card-returning {
        transition: all 1.2s cubic-bezier(0.25, 0.8, 0.25, 1) !important;
        z-index: 9999998 !important; /* Diğer kartların üstünde süzülsün */
        box-shadow: 0 0 30px rgba(16, 185, 129, 0.6) !important;
        transform: none !important; /* Normal ölçeğe ve yerine dön */
    }
</style>

<div class="tv-container" id="tv-app">


    
    <div class="tv-header">
        <h1 class="tv-title"><div class="live-dot"></div> Trendyol Canlı Ekran</h1>
        <div class="tv-controls">
            <button class="tv-btn" id="btn-test-animation" style="border-color: #f59e0b; color: #f59e0b;" title="Animasyonu Test Et">
                <span class="dashicons dashicons-star-filled"></span> Test Şovu
            </button>
            
            <button class="tv-btn" id="btn-guest-mode" title="Misafir Modu Aç/Kapat">
                <span class="dashicons dashicons-visibility"></span> Misafir Modu: KAPALI
            </button>
            <button class="tv-btn" id="btn-fullscreen">
                <span class="dashicons dashicons-editor-expand"></span> Tam Ekran
            </button>
            <a href="?page=hbt-tpt-dashboard" class="tv-btn" style="background: transparent; border-color: transparent;">
                <span class="dashicons dashicons-no-alt"></span> Çıkış
            </a>
        </div>
    </div>

    <div class="tv-spotlight-area" id="tv-spotlight">
        <div class="tv-spot-rank">#1</div>
        <img src="" class="tv-spot-img" id="spot-img" />
        <div class="tv-spot-info">
            <div class="tv-spot-name" id="spot-name">Ürün Bekleniyor...</div>
            <div class="tv-spot-stats">
                <div class="tv-spot-stat qty" id="spot-qty">0 Adet</div>
                <div class="tv-spot-stat" id="spot-rev">0.00 ₺</div>
            </div>
        </div>
    </div>

    <div class="tv-grid-area" id="tv-cards-container">
        <h3 style="color: var(--tv-text-muted); width: 100%; text-align: center; margin-top: 50px;">Veriler Canlı Çekiliyor... Lütfen Bekleyin.</h3>
    </div>

    <div class="tv-ticker">
        <div class="tv-ticker-content" id="tv-ticker-text">
            Sistem Başlatılıyor... Veriler Hesaplanıyor...
        </div>
    </div>

    <div id="tv-first-sale-overlay">
    <div class="tv-hologram-box">
        <h2 style="font-size: 40px; font-weight: 800; color: var(--tv-neon-green); margin-top: 0; text-transform: uppercase;">Günün İlk Satışı!</h2>
        <img id="tv-hologram-img" src="" style="width: 300px; height: 300px; object-fit: contain; margin-bottom: 30px;">
        <p id="tv-hologram-name" style="font-size: 24px; font-weight: 600; color: #f8fafc; margin-bottom: 10px;"></p>
        <p id="tv-hologram-rev" style="font-size: 32px; font-weight: 800; color: var(--tv-neon-green); margin-bottom: 0;"></p>
    </div>
</div>

<div id="tv-comet-animate" class="tv-comet-animate"></div>

</div>



<script>
jQuery(document).ready(function($) {
    
    let isGuestMode = false;
    let allProductsData = [];
    let spotlightIndex = 0;
    let spotlightInterval;
    let prevProductQtys = {}; 
    let isFirstTvLoad = true;
    
    // YENİ BAYRAKLAR: Animasyon durumlarını takip eder
    let isFirstSaleAnimating = false; 
    let isCelebrateAnimating = false;
    let celebrateQueue = []; // Artan ürünleri sıraya dizer

    // Tam Ekran Kontrolü
    $('#btn-fullscreen').on('click', function() {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(err => {
                alert(`Tam ekran hatası: ${err.message}`);
            });
            $(this).html('<span class="dashicons dashicons-editor-contract"></span> Küçült');
        } else {
            document.exitFullscreen();
            $(this).html('<span class="dashicons dashicons-editor-expand"></span> Tam Ekran');
        }
    });

    // Misafir Modu Kontrolü
    $('#btn-guest-mode').on('click', function() {
        isGuestMode = !isGuestMode;
        if(isGuestMode) {
            $(this).addClass('guest-active').html('<span class="dashicons dashicons-hidden"></span> Misafir Modu: AÇIK');
        } else {
            $(this).removeClass('guest-active').html('<span class="dashicons dashicons-visibility"></span> Misafir Modu: KAPALI');
        }
        renderTV();
    });

    // Parayı Formata Sok VEYA Gizle
    function tvFormatMoney(val) {
        if (isGuestMode) return '*** ₺';
        return parseFloat(val).toLocaleString('tr-TR', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' ₺';
    }

    // İsim Temizleme Asistanı
    function cleanProductName(name, barcode) {
        let raw = String(name || '');
        let bCode = String(barcode || '');
        if (bCode !== '' && raw.indexOf(bCode) !== -1) raw = raw.replace(bCode, '');
        return raw.replace(/[\s\-()]+$/, '').trim();
    }

    // Ana Render (Çizim) Motoru
    function renderTV(dataObj) {
        if(!dataObj && window.hbtLastTvData) dataObj = window.hbtLastTvData;
        if(!dataObj) return;
        window.hbtLastTvData = dataObj;

        let firstSaleProduct = null;
        celebrateQueue = []; // Her renderda kuyruğu sıfırla

        // --- 1. KARTLARI AKILLI GÜNCELLE ---
        if(allProductsData && allProductsData.length > 0) {
            
            if(isFirstTvLoad) $('#tv-cards-container').empty();
            
            let fadeClass = isFirstTvLoad ? 'fade-enter' : '';
            let currentBarcodes = allProductsData.map(p => String(p.barcode));
            
            // Olmayanları sil
            $('.tv-card').each(function() {
                // Eğer şov yapıyorsa bu kartı silme, bekle
                if ($(this).hasClass('tv-card-celebrate-active') || $(this).hasClass('tv-card-returning')) return;

                let cardId = $(this).attr('id').replace('tv-card-', '');
                if (!currentBarcodes.includes(cardId)) {
                    $(this).remove();
                }
            });

            allProductsData.forEach((p, index) => {
                let cleanName = cleanProductName(p.product_name, p.barcode);
                let shortName = cleanName.length > 40 ? cleanName.substring(0, 40) + '...' : cleanName;
                
                let currentQ = parseInt(p.total_quantity);
                let oldQ = prevProductQtys[p.barcode] || 0;
                
                // --- İLK SATIŞ (HOLOGRAM) MANTIĞI ---
                let isFirstSaleOfProduct = (!isFirstTvLoad && currentQ === 1 && oldQ === 0);
                
                if (isFirstSaleOfProduct && !isFirstSaleAnimating) {
                    firstSaleProduct = p; // Hologram şovu için ayır
                    prevProductQtys[p.barcode] = 1; // Mühürle
                }

                // --- YENİ EKLENEN: ADET ARTIŞI (CELEBRATE QUEUE) MANTIĞI ---
                let isQuantityIncreased = (!isFirstTvLoad && oldQ > 0 && currentQ > oldQ);
                
                if (isQuantityIncreased) {
                    // Bu ürünü "Öne Çıkma" sırasına (Queue) ekle
                    celebrateQueue.push(p);
                    prevProductQtys[p.barcode] = currentQ; // Mühürle
                }
                
                // Eğer bu ürün şu an öne çıkmışsa DOM'u bozma
                let $existingCard = $('#tv-card-' + p.barcode);
                if ($existingCard.hasClass('tv-card-celebrate-active')) return;

                let hideClass = isFirstSaleOfProduct ? 'first-sale-hidden' : ''; // Hologram kartını gizle
                
                if ($existingCard.length && !isFirstTvLoad) {
                    $existingCard.find('.tv-card-badge').text(p.total_quantity);
                    $existingCard.find('.tv-card-rev').text(tvFormatMoney(p.total_revenue));
                    $existingCard.css('order', index); // CSS Order ile sırala
                } else {
                    let imgHtml = p.image_url ? `<img src="${p.image_url}" class="tv-card-img">` : `<div class="tv-card-img" style="display:flex;align-items:center;justify-content:center;background:#334155;"><span class="dashicons dashicons-format-image" style="color:#94a3b8; font-size:30px; width:30px; height:30px;"></span></div>`;
                    
                    let newCard = `
                    <div class="tv-card ${fadeClass} ${hideClass}" id="tv-card-${p.barcode}" style="order: ${index};">
                        <div class="tv-card-badge">${p.total_quantity}</div>
                        ${imgHtml}
                        <div class="tv-card-name" title="${cleanName}">${shortName}</div>
                        <div class="tv-card-rev">${tvFormatMoney(p.total_revenue)}</div>
                    </div>`;
                    $('#tv-cards-container').append(newCard);
                }
            });
            
            isFirstTvLoad = false;
        } else {
            $('#tv-cards-container').html('<h3 style="color: var(--tv-text-muted); width: 100%; text-align: center; margin-top: 50px;">Bugün henüz satış yapılmadı.</h3>');
        }

        // --- 2. ŞOV BAŞLASIN (SIRAYLA) ---
        
        // Önce Devasa Hologram (First Sale) şovuna öncelik ver
        if (firstSaleProduct && !isFirstSaleAnimating) {
            playFirstSaleCinema(firstSaleProduct);
        }
        // Hologram yoksa, artışları sırayla öne çıkar
        else if (celebrateQueue.length > 0 && !isFirstSaleAnimating && !isCelebrateAnimating) {
            processCelebrateQueue();
        }

        // --- 3. ALT BANT (TİCKER) OLUŞTUR ---
        let revToday = tvFormatMoney(dataObj.stats_today.revenue);
        let profToday = tvFormatMoney(dataObj.stats_today.net_profit);
        let orderToday = dataObj.stats_today.total_orders + ' Adet';
        
        let revWeek = tvFormatMoney(dataObj.stats_week.revenue);
        let profWeek = tvFormatMoney(dataObj.stats_week.net_profit);
        
        let revMonth = tvFormatMoney(dataObj.stats_month.revenue);
        let profMonth = tvFormatMoney(dataObj.stats_month.net_profit);
        
        let revMonthRaw = parseFloat(dataObj.stats_month.revenue) || 0;
        let profMonthRaw = parseFloat(dataObj.stats_month.net_profit) || 0;
        let marginMonth = revMonthRaw > 0 ? ((profMonthRaw / revMonthRaw) * 100).toFixed(1) : 0;
        let marginMonthStr = isGuestMode ? '*** %' : '%' + marginMonth;
        
        let tickerHtml = ``;
        tickerHtml += `<span>🚀 <b>BUGÜN:</b> Ciro: <span style="color:#a7f3d0">${revToday}</span> &nbsp;|&nbsp; Net Kâr: <span style="color:#a7f3d0">${profToday}</span> &nbsp;|&nbsp; Sipariş: <span style="color:#bfdbfe">${orderToday}</span></span>`;
        tickerHtml += `<span>&nbsp;&nbsp;✦&nbsp;&nbsp;</span>`;
        tickerHtml += `<span>📅 <b>BU HAFTA:</b> Ciro: <span style="color:#a7f3d0">${revWeek}</span> &nbsp;|&nbsp; Net Kâr: <span style="color:#a7f3d0">${profWeek}</span></span>`;
        tickerHtml += `<span>&nbsp;&nbsp;✦&nbsp;&nbsp;</span>`;
        tickerHtml += `<span>🌟 <b>BU AY:</b> Ciro: <span style="color:#a7f3d0">${revMonth}</span> &nbsp;|&nbsp; Net Kâr: <span style="color:#a7f3d0">${profMonth}</span> &nbsp;|&nbsp; Kâr Marjı: <span style="color:#fde047">${marginMonthStr}</span></span>`;
        
        tickerHtml += `<span>&nbsp;&nbsp;&nbsp;&nbsp;🔥🔥🔥&nbsp;&nbsp;&nbsp;&nbsp;</span>`;
        tickerHtml += `<span>📦 <b>SON SİPARİŞLER (Son 10):</b> </span>`;
        
        if(dataObj.latest_orders && dataObj.latest_orders.length > 0) {
            dataObj.latest_orders.forEach(o => {
                let nameParts = String(o.customer_name).split(' ');
                let maskedName = nameParts[0] + ' ' + (nameParts.length > 1 ? nameParts[nameParts.length-1].charAt(0) + '.' : '');
                tickerHtml += `<span>${maskedName} (${o.shipping_city}) - <b style="color:#a7f3d0">${tvFormatMoney(o.total_price)}</b> &nbsp; • &nbsp; </span>`;
            });
        } else {
            tickerHtml += `<span>Henüz sipariş yok.</span>`;
        }
        $('#tv-ticker-text').html(tickerHtml);

        if(!spotlightInterval) {
            updateSpotlight();
            spotlightInterval = setInterval(updateSpotlight, 4000);
        }
    }

    // --- YENİ EKLENEN: SIRALI "POP-OUT" (ÖNE ÇIKMA) MOTORU ---
    function processCelebrateQueue() {
        if (celebrateQueue.length === 0) {
            isCelebrateAnimating = false;
            return; // Kuyruk bitti
        }
        
        isCelebrateAnimating = true;
        let product = celebrateQueue.shift(); // Kuyruğun başındaki ürünü al
        let $card = $('#tv-card-' + product.barcode);
        
        if (!$card.length) {
            processCelebrateQueue(); // Kart yoksa sıradakine geç
            return;
        }

        // A) Süzülme (Geri Dönüş) için Orijinal Koordinatları Hafızaya Al
        // Kartın grid içindeki static pozisyonunu ve boyutunu bulmak için offset() kullanalım
        let originalOffset = $card.offset();
        let originalWidth = $card.outerWidth();
        let originalHeight = $card.outerHeight();

        // B) POP-OUT: Kartı Devasa Bir Şekilde Ortaya Al
        // CSS Transition'ı geçici olarak kapatıp anında merkeze alıyoruz
        $card.css({
            transition: 'none',
            width: originalWidth + 'px',
            height: originalHeight + 'px',
            top: originalOffset.top + 'px',
            left: originalOffset.offset + 'px',
            position: 'absolute' // Grid'den kopardık
        });

        // Tarayıcıyı yenilemeye zorla (Reflow)
        void $card[0].offsetWidth;

        // Merkeze Pop-Out yap
        $card.addClass('tv-card-celebrate-active');

        // C) 2.5 Saniye Ekranda Kal ve Kutla
        setTimeout(() => {
            
            // D) GERİ DÖNÜŞ (SÜZÜLME): Listeye Geri Gönder
            // Pop-Out CSS sınıfını sil, süzülme sınıfını ve koordinatları ekle
            $card.removeClass('tv-card-celebrate-active').addClass('tv-card-returning').css({
                top: originalOffset.top + 'px',
                left: originalOffset.left + 'px'
            });

            // E) Animasyon Bitince Kartı Eski Haline Getir ve Sıradakini Başlat
            setTimeout(() => {
                // Süzülme bitti, kartı static hale getir
                $card.removeClass('tv-card-returning').css({
                    position: '', top: '', left: '', width: '', height: '', transition: '', zIndex: ''
                });
                
                // Sıradaki ürünü öne çıkar (Rekürsif Çağrı)
                processCelebrateQueue();

            }, 1200); // .tv-card-returning transition süresiyle aynı olmalı

        }, 2500); // Ekranda kalma süresi
    }

    // --- MEVCUT: FİRST SALE HOLOGRAM MOTORU ---
    function playFirstSaleCinema(product) {
        isFirstSaleAnimating = true;

        let cleanName = cleanProductName(product.product_name, product.barcode);
        let shortName = cleanName.length > 40 ? cleanName.substring(0, 40) + '...' : cleanName;
        let revStr = tvFormatMoney(product.total_revenue);
        let imgHtml = product.image_url ? `<img src="${product.image_url}" class="tv-card-img" style="height:100px;">` : `<div class="tv-card-img" style="display:flex;align-items:center;justify-content:center;background:#334155;height:100px;"><span class="dashicons dashicons-format-image" style="color:#94a3b8; font-size:30px; width:30px; height:30px;"></span></div>`;

        // 1. ADIM: Devasa Hologramı Aç
        $('#tv-hologram-img').attr('src', product.image_url || '').css('opacity', product.image_url ? 1 : 0);
        $('#tv-hologram-name').text(cleanName);
        $('#tv-hologram-rev').text(revStr);
        $('#tv-first-sale-overlay').addClass('overlay-active');

        // 2. ADIM: 4 Saniye Yanıp Sönme Beklemesi
        setTimeout(() => {
            let $comet = $('#tv-comet-animate');
            let $hologramBox = $('.tv-hologram-box');
            let boxPos = $hologramBox.offset();
            
            // Kuyruklu Yıldız İçeriği
            $comet.html(`
                <div class="tv-card-badge">1</div>
                ${imgHtml}
                <div class="tv-card-name">${shortName}</div>
                <div class="tv-card-rev">${revStr}</div>
            `);

            // Merkeze Oturt (Transition Kapalı)
            $comet.css({
                transition: 'none',
                top: boxPos.top + ($hologramBox.outerHeight() / 2),
                left: boxPos.left + ($hologramBox.outerWidth() / 2),
                display: 'block',
                opacity: 1,
                transform: 'translate(-50%, -50%) scale(1.5)' 
            });

            // Tarayıcıyı zorla yenile (Reflow)
            void $comet[0].offsetWidth;

            $('#tv-first-sale-overlay').removeClass('overlay-active');

            // 3. ADIM: Gizli Hedef Kartın Koordinatlarını Bul
            let $targetCard = $('#tv-card-' + product.barcode);
            let targetPos = $targetCard.offset();

            // 4. ADIM: Kuyruklu Yıldızı Hedefe Fırlat
            $comet.css({
                transition: 'all 1.2s cubic-bezier(0.25, 0.8, 0.25, 1)',
                top: targetPos.top + ($targetCard.outerHeight() / 2),
                left: targetPos.left + ($targetCard.outerWidth() / 2),
                transform: 'translate(-50%, -50%) scale(1)'
            });

            // 5. ADIM: Yıldız Hedefe Ulaştığında Şovu Bitir
            setTimeout(() => {
                $comet.hide();
                // Gerçek kartı görünür yap (Flaş yok, süzülme yok, first sale çünkü)
                $targetCard.removeClass('first-sale-hidden');
                isFirstSaleAnimating = false; // Şov bitti!
                
                // Hologram bitti, şimdi varsa adet artış sıralarını başlat
                if (celebrateQueue.length > 0 && !isCelebrateAnimating) {
                    processCelebrateQueue();
                }

            }, 1200);

        }, 4000); 
    }

    // --- YENİ EKLENEN: TEST ŞOVU BUTONU TETİKLEYİCİSİ (SIRAYI DESTEKLER) ---
    $('#btn-test-animation').on('click', function() {
        // Devasa hologram oynuyorsa test etme
        if (!window.hbtLastTvData || isFirstSaleAnimating || isFirstSaleAnimating) return; 
        
        // Rastgele 1 ürünün adedini 1 artmış gibi yap
        let fakeData = JSON.parse(JSON.stringify(window.hbtLastTvData));
        if (!fakeData.all_products || fakeData.all_products.length < 2) return;
        
        let randomIdx = 1; // 0. ürün first sale olabilir, biz 1. ürünü test edelim
        let product = fakeData.all_products[randomIdx];
        let originalBarcode = product.barcode;
        
        // Hafızadaki eski adedi 1 düşür ki sistem artışı anlasın
        prevProductQtys[originalBarcode] = parseInt(product.total_quantity || 1) - 1;
        
        // Ana motoru kandırarak çalıştır
        renderTV(fakeData);
    });

    function updateSpotlight() {
        if(!allProductsData || allProductsData.length === 0) return;
        
        let maxItems = Math.min(20, allProductsData.length);
        if(spotlightIndex >= maxItems) spotlightIndex = 0;
        
        let p = allProductsData[spotlightIndex];
        
        $('#tv-spotlight').css('opacity', 0);
        
        setTimeout(() => {
            $('.tv-spot-rank').text('#' + (spotlightIndex + 1));
            
            let cleanName = cleanProductName(p.product_name, p.barcode);
            let shortSpotName = cleanName.length > 50 ? cleanName.substring(0, 50) + '...' : cleanName;
            
            $('#spot-name').text(shortSpotName).attr('title', cleanName);
            $('#spot-qty').text(p.total_quantity + ' Adet');
            $('#spot-rev').text(tvFormatMoney(p.total_revenue));
            
            if(p.image_url) {
                $('#spot-img').attr('src', p.image_url).show();
            } else {
                $('#spot-img').hide();
            }
            
            $('#tv-spotlight').css('opacity', 1);
            spotlightIndex++;
        }, 300);
    }

    // ARKA PLAN VERİ ÇEKİCİ (ŞOV VARSA BEKLER)
    function fetchTvData() {
        // Herhangi bir şov oynuyorsa veriyi arkada beklet ki sıralar bozulmasın
        if (isFirstSaleAnimating || isCelebrateAnimating) return; 

        $.post(hbtTpt.ajaxurl, { action: 'hbt_get_tv_mode_data', nonce: hbtTpt.nonce }, function(res) {
            if(res.success) {
                // SADECE sunucudan taze veri geldiğinde eski miktarları hafızaya al
                if (allProductsData && allProductsData.length > 0) {
                    allProductsData.forEach(p => {
                        prevProductQtys[p.barcode] = parseInt(p.total_quantity);
                    });
                }
                allProductsData = res.data.all_products;
                
                renderTV(res.data);
            }
        });
    }

    $('#tv-spotlight').css('transition', 'opacity 0.3s ease-in-out');

    fetchTvData();
    setInterval(fetchTvData, 10000);

});
</script>