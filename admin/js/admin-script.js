/**
 * HBT Trendyol Profit Tracker – Admin Script (updated, copy-paste ready)
 *
 * Restored original structure you provided and applied minimal, safe updates:
 * - Order Detail modal: added "Komisyon (%)" column and fallback calculation when commission_rate missing
 * - Ensure item cost and shipping display remain intact
 * - All event handlers kept delegated as in original file
 *
 * @package HBT_Trendyol_Profit_Tracker
 * @since   1.0.0
 */
/* global hbtTpt, Chart, jQuery */

(function ($) {
	'use strict';

	// =========================================================================
	// Utility helpers
	// =========================================================================

	/**
	 * Wrapper for AJAX calls.
	 *
	 * @param {string}   action   WP AJAX action.
	 * @param {object}   data     Extra POST data.
	 * @param {function} success  Success callback.
	 * @param {function} [error]  Error callback (optional).
	 */
	function hbtAjax(action, data, success, error) {
        $.ajax({
            url:    hbtTpt.ajaxurl,
            type:   'POST',
            data:   Object.assign({ action: action, nonce: hbtTpt.nonce }, data),
            success: function (res) {
                if (res.success) {
                    if (typeof success === 'function') success(res.data);
                } else {
                    // Sunucudan gelen spesifik hata mesajını al, yoksa varsayılanı kullan
                    var msg = (res.data && res.data.message) ? res.data.message : (hbtTpt.strings.error || 'Hata oluştu');
                    hbtToast(msg, 'error'); // Hata mesajını buraya basıyoruz
                    if (typeof error === 'function') error(res);
                }
            },
            error: function (xhr) {
                // AJAX (bağlantı/yazılım) hatası detayı
                var errorStatus = xhr.status;
                var errorText = xhr.statusText;
                hbtToast('Sistem Hatası: ' + errorStatus + ' - ' + errorText, 'error');
                if (typeof error === 'function') error(xhr);
            }
        });
    }
	/**
	 * Display a toast notification.
	 *
	 * @param {string} message Message text.
	 * @param {string} type    'success' | 'error' | '' (default).
	 */
	function hbtToast(message, type) {
        // Mesaj içeriğini konsola da yazalım ki tam detayı oradan da görebilin
        console.error("HBT Tracker Hatası:", message);
        
        var $t = $('<div class="hbt-toast ' + (type || '') + '">' + $('<span>').text(message).html() + '</div>');
        $('body').append($t);
        setTimeout(function () { $t.fadeOut(400, function () { $t.remove(); }); }, 5000); // Süreyi biraz uzattım (5 sn)
    }

	/**
	 * Confirm dialog wrapper.
	 *
	 * @param  {string}   msg      Confirmation message.
	 * @param  {function} callback Called if user confirms.
	 */
	function hbtConfirm(msg, callback) {
		// eslint-disable-next-line no-alert
		if (window.confirm(msg)) callback();
	}

	/**
	 * Format a number to 2 decimal places and thousands separator.
	 *
	 * @param  {number} n
	 * @return {string}
	 */
	function fmt(n) {
		return parseFloat(n || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
	}

	// =========================================================================
	// Modal helpers (delegated)
	// =========================================================================
	$(document).on('click', '.hbt-modal-close, .hbt-modal-overlay', function () {
		$(this).closest('.hbt-modal').hide();
	});

	// =========================================================================
	// Tab navigation (delegated)
	// =========================================================================
	$(document).on('click', '.hbt-tab', function () {
		var tab = $(this).data('tab');
		var $parent = $(this).closest('.hbt-tpt-wrap, .hbt-modal-body');

		$parent.find('.hbt-tab').removeClass('active');
		$(this).addClass('active');

		$parent.find('.hbt-tab-content').hide();
		$parent.find('#tab-' + tab).show();
	});

	// =========================================================================
	// Document ready initializers
	// =========================================================================
	$(document).ready(function () {
		// Date pickers (Modern Flatpickr - Türkçe)
		if (typeof flatpickr !== 'undefined') {
			flatpickr('.hbt-datepicker', {
				dateFormat: 'Y-m-d',
				locale: 'tr', // Tamamen Türkçe
				allowInput: true,
				disableMobile: "true" // Mobilde kendi özel arayüzünü açar
			});
		}

		// DataTables init (only on tables with class hbt-datatable)
		if ($.fn.DataTable) {
			$('.hbt-datatable').each(function () {
				// Use simple default config; specific tables can override via data attributes later
				$(this).DataTable({
					language: { url: '' },
					pageLength: 25,
					order: []
				});
			});
		}
	});

	// =========================================================================
	// Dashboard (load charts and cards)
	// =========================================================================
	var trendChart = null;
	var storeChart = null;

	function loadDashboard() {
        hbtAjax('hbt_get_dashboard_data', {}, function (data) {
            if (!data) {
                console.error("Dashboard verisi boş döndü!");
                return;
            }

            // Kartları doldur (Genel Mevcut Dönem)
            if ($('#profit-today').length) animateCount($('#profit-today'), data.profit_today || 0);
            if ($('#profit-week').length) animateCount($('#profit-week'), data.profit_week || 0);
            if ($('#profit-month').length) animateCount($('#profit-month'), data.profit_month || 0);
            
            // Geçmiş Dönem Kartlarını doldur
            if ($('#profit-yesterday').length) animateCount($('#profit-yesterday'), data.profit_yesterday || 0);
            if ($('#profit-last-week').length) animateCount($('#profit-last-week'), data.profit_last_week || 0);
            if ($('#profit-last-month').length) animateCount($('#profit-last-month'), data.profit_last_month || 0);

            // Dinamik Mağaza Kartları: Bugünkü Kar
            if (data.stores_today && data.stores_today.length > 0) {
                var todayHtml = '';
                data.stores_today.forEach(function(store) {
                    var val = parseFloat(store.profit || 0);
                    var borderColor = val >= 0 ? '#4CAF50' : '#d63638';
                    var bgColor = val >= 0 ? '#fff' : '#fff5f5'; // Zarar varsa çok hafif kırmızı arkaplan
                    var textColor = val >= 0 ? '#4CAF50' : '#d63638';

                    todayHtml += '<div class="hbt-card hbt-card-profit" style="background:' + bgColor + '; border-left: 3px solid ' + borderColor + ';">' +
                        '<div class="hbt-card-icon dashicons dashicons-store" style="color:' + textColor + ';"></div>' +
                        '<div class="hbt-card-body">' +
                            '<span class="hbt-card-label" style="font-weight:600;">' + store.store_name + '</span>' +
                            '<span class="hbt-card-value" style="color:' + textColor + '; font-size: 22px;">' + fmt(val) + '</span>' +
                        '</div>' +
                    '</div>';
                });
                $('#hbt-store-today-cards').html(todayHtml);
            } else {
                $('#hbt-store-today-cards').html('<p style="color:#666;">Aktif mağaza bulunamadı.</p>');
            }

            // Dinamik Mağaza Kartları: Dünkü Kar
            if (data.stores_yesterday && data.stores_yesterday.length > 0) {
                var yesterdayHtml = '';
                data.stores_yesterday.forEach(function(store) {
                    var val = parseFloat(store.profit || 0);
                    var borderColor = val >= 0 ? '#4CAF50' : '#d63638';
                    var bgColor = val >= 0 ? '#f9f9f9' : '#fff0f0'; // Dünün verisi için hafif gri arka plan
                    var textColor = val >= 0 ? '#4CAF50' : '#d63638';

                    yesterdayHtml += '<div class="hbt-card hbt-card-profit" style="background:' + bgColor + '; border-left: 3px solid ' + borderColor + ';">' +
                        '<div class="hbt-card-icon dashicons dashicons-store" style="color:' + textColor + ';"></div>' +
                        '<div class="hbt-card-body">' +
                            '<span class="hbt-card-label" style="font-weight:600;">' + store.store_name + '</span>' +
                            '<span class="hbt-card-value" style="color:' + textColor + '; font-size: 22px;">' + fmt(val) + '</span>' +
                        '</div>' +
                    '</div>';
                });
                $('#hbt-store-yesterday-cards').html(yesterdayHtml);
            } else {
                $('#hbt-store-yesterday-cards').html('<p style="color:#666;">Aktif mağaza bulunamadı.</p>');
            }

            // Trend Grafiği
            var trendCtx = document.getElementById('chart-trend');
            if (trendCtx && data.trend) {
                var labels = data.trend.map(function (r) { return r.day; });
                var profits = data.trend.map(function (r) { return parseFloat(r.profit || 0); });

                if (trendChart) trendChart.destroy();
                trendChart = new Chart(trendCtx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Net Kar (TL)',
                            data: profits,
                            borderColor: '#2196F3',
                            backgroundColor: 'rgba(33,150,243,.1)',
                            tension: 0.3,
                            fill: true
                        }]
                    },
                    options: { responsive: true }
                });
            }

            // Mağaza Karşılaştırma Grafiği
            var storeCtx = document.getElementById('chart-stores');
            if (storeCtx && data.store_comparison && data.store_comparison.length > 0) {
                var storeLabels  = data.store_comparison.map(function (r) { return r.store_name; });
                var storeProfits = data.store_comparison.map(function (r) { return parseFloat(r.profit || 0); });

                if (storeChart) storeChart.destroy();
                storeChart = new Chart(storeCtx, {
                    type: 'bar',
                    data: {
                        labels: storeLabels,
                        datasets: [{
                            label: 'Kar (TL)',
                            data: storeProfits,
                            backgroundColor: storeProfits.map(function (v) { return v >= 0 ? '#4CAF50' : '#f44336'; })
                        }]
                    },
                    options: { 
                        responsive: true,
                        plugins: { legend: { display: false } }
                    }
                });
            }
        });
    }

	function animateCount($el, target) {
		var start = 0;
		var end   = parseFloat(target) || 0;
		var steps = 30;
		var step  = 0;
		var timer = setInterval(function () {
			step++;
			$el.text(fmt(start + (end - start) * (step / steps)));
			if (step >= steps) {
				clearInterval(timer);
				$el.text(fmt(end));
			}
		}, 16);
	}

	if ($('#profit-today').length) {
		loadDashboard();
		setInterval(loadDashboard, 5 * 60 * 1000); // refresh every 5 minutes
	}

	// =========================================================================
	// Stores: add, edit, delete, sync
	// =========================================================================
	$(document).on('click', '#btn-add-store', function () {
		$('#store-id').val('');
		$('#store-form')[0].reset();
		$('#store-modal-title').text('Mağaza Ekle');
		$('#connection-result').text('');
		$('#store-modal').show();
	});

	$(document).on('click', '.btn-edit-store', function () {
		var $btn = $(this);
		$('#store-id').val($btn.data('id'));
		$('#store-name').val($btn.data('name'));
		$('#store-supplier-id').val($btn.data('supplier'));
		$('#store-api-key').val('');
		$('#store-api-secret').val('');
		$('#store-modal-title').text('Mağazayı Düzenle');
		$('#connection-result').text('');
		$('#store-modal').show();
	});

	$(document).on('click', '#btn-save-store', function () {
		var $btn = $(this).text(hbtTpt.strings.saving).prop('disabled', true);
		hbtAjax('hbt_save_store', $('#store-form').serializeArray().reduce(function (o, f) { o[f.name] = f.value; return o; }, {}),
			function () {
				hbtToast(hbtTpt.strings.saved, 'success');
				setTimeout(function () { location.reload(); }, 800);
			},
			function () { $btn.text('Kaydet').prop('disabled', false); }
		);
	});

	$(document).on('click', '.btn-delete-store', function () {
		var id = $(this).data('id');
		hbtConfirm(hbtTpt.strings.confirm_delete, function () {
			hbtAjax('hbt_delete_store', { id: id }, function () {
				location.reload();
			});
		});
	});

	// Sync store orders (manual) with page/size handling
	// =========================================================================
	// API Senkronizasyon İşlemi ve Sayfalamalı Tablo Gösterimi
	// =========================================================================
	window.hbtLastSyncOrders = [];
	window.hbtSyncCurrentPage = 1;
	window.hbtSyncPerPage = 10; // Her sayfada gösterilecek sipariş sayısı

	// Tabloyu çizen ve sayfalayan fonksiyon
	window.hbtRenderSyncTable = function() {
		var orders = window.hbtLastSyncOrders;
		var page = window.hbtSyncCurrentPage;
		var perPage = window.hbtSyncPerPage;
		var totalPages = Math.ceil(orders.length / perPage);
		
		if (page < 1) page = 1;
		if (page > totalPages) page = totalPages;
		
		var start = (page - 1) * perPage;
		var end = start + perPage;
		var paginatedItems = orders.slice(start, end);
		
		var html = '<table class="wp-list-table widefat fixed striped" style="margin-top:10px;">' +
				   '<thead><tr>' +
				   '<th>Sipariş No</th>' +
				   '<th>Müşteri</th>' +
				   '<th>Tarih</th>' +
				   '<th>Durum</th>' +
				   '<th>Tutar (TL)</th>' +
				   '<th style="width: 140px; text-align:center;">Tüm API Verisi</th>' +
				   '</tr></thead><tbody>';
				   
		paginatedItems.forEach(function(o, index) {
			var orderNo = o.order_number || o.orderNumber || '-';
			var customer = o.customer_name || (o.customerFirstName + ' ' + o.customerLastName) || '-';
			var orderDate = o.order_date || o.orderDate || '-';
			var status = o.status || '-';
			var total = parseFloat(o.total_price || o.totalPrice || 0).toFixed(2);
			var realIndex = start + index;

			html += '<tr>' +
					'<td><strong>' + orderNo + '</strong></td>' +
					'<td>' + customer + '</td>' +
					'<td>' + orderDate + '</td>' +
					'<td>' + status + '</td>' +
					'<td>' + total + ' TL</td>' +
					'<td style="text-align:center;"><button type="button" class="button btn-show-sync-detail" data-index="' + realIndex + '">Göster / Gizle</button></td>' +
					'</tr>' +
					'<tr id="sync-detail-' + realIndex + '" style="display:none;"><td colspan="6" style="padding:15px; background:#eef;">' +
					'<strong>Trendyol API Ham Verisi:</strong>' +
					'<pre style="max-height:350px;overflow:auto;font-size:12px;margin-top:10px;border:1px solid #ccc;padding:10px;background:#fff;">' + JSON.stringify(o, null, 2) + '</pre>' +
					'</td></tr>';
		});
		html += '</tbody></table>';
		
		// Sayfalama Butonları
		if (totalPages > 1) {
			html += '<div style="margin-top:15px; display:flex; justify-content:space-between; align-items:center; background:#fff; padding:10px; border:1px solid #ccd0d4;">' +
					'<div><button type="button" class="button btn-sync-prev" ' + (page === 1 ? 'disabled' : '') + '>&laquo; Önceki Sayfa</button></div>' +
					'<div style="font-weight:bold;">Sayfa ' + page + ' / ' + totalPages + '</div>' +
					'<div><button type="button" class="button btn-sync-next" ' + (page === totalPages ? 'disabled' : '') + '>Sonraki Sayfa &raquo;</button></div>' +
					'</div>';
		}
		
		$('#hbt-sync-table-container').html(html);
	};

	// Sayfalama ve Detay Butonu Olayları
	$(document).on('click', '.btn-sync-prev', function() {
		if (window.hbtSyncCurrentPage > 1) {
			window.hbtSyncCurrentPage--;
			window.hbtRenderSyncTable();
		}
	});

	$(document).on('click', '.btn-sync-next', function() {
		var totalPages = Math.ceil(window.hbtLastSyncOrders.length / window.hbtSyncPerPage);
		if (window.hbtSyncCurrentPage < totalPages) {
			window.hbtSyncCurrentPage++;
			window.hbtRenderSyncTable();
		}
	});

	$(document).on('click', '.btn-show-sync-detail', function() {
		var idx = $(this).data('index');
		$('#sync-detail-' + idx).toggle();
	});

	// Senkronize Et Butonu Ana Olayı
	// =========================================================================
	// TARIH ARALIKLI VE ZINCIRLEME (SAYFALI) SENKRONIZASYON
	// =========================================================================
	
	// 1. "Senkronize Et" butonuna basıldığında modalı aç
	$(document).on('click', '.btn-sync-store', function (e) {
		e.preventDefault();
		var storeId = $(this).data('id');
		$('#sync-store-id').val(storeId);
		
		// Modalı sıfırla ve aç
		$('#sync-progress-area').hide();
		$('#sync-date-form').show();
		$('#btn-start-sync').show().prop('disabled', false);
		$('#sync-progress-bar').css('width', '0%');
		$('#sync-stats-text').text('');
		$('#sync-date-modal').fadeIn();
	});

	// Modalı Kapatma
	$(document).on('click', '.btn-close-sync-modal', function () {
		$('#sync-date-modal').fadeOut();
	});

	// 2. Modaldaki "Senkronizasyonu Başlat" butonuna basıldığında
	// 2. Modaldaki "Senkronizasyonu Başlat" butonuna basıldığında
	$(document).on('click', '#btn-start-sync', function () {
		var storeId = $('#sync-store-id').val();
		var startDate = $('#sync-start-date').val();
		var endDate = $('#sync-end-date').val();

		if (!startDate || !endDate) {
			alert('Lütfen başlangıç ve bitiş tarihlerini seçin.');
			return;
		}

		// Arayüzü "İşlem yapılıyor" moduna al
		$(this).prop('disabled', true).hide();
		$('#sync-date-form').slideUp();
		$('#sync-progress-area').fadeIn();
		$('#sync-progress-bar').css('width', '5%');
		$('#sync-progress-text').text('Hedef tarih aralığındaki sipariş sayısı hesaplanıyor...').css('color', '#007cba');
		
		window.hbtAjaxAccumulatedOrders = [];
		window.hbtTotalSavedCount = 0;
		window.hbtSyncIsRunning = true;
		window.hbtTotalExpectedOrders = 0;

		// 2.1 Önce Pre-Flight (Toplam Sipariş Sayısını Öğrenme) İstek Atılır
		hbtAjax('hbt_pre_sync_check', {
			store_id: storeId,
			start_date: startDate,
			end_date: endDate
		}, function(res) {
			window.hbtTotalExpectedOrders = parseInt(res.total_orders || 0, 10);
			
			if (window.hbtTotalExpectedOrders === 0) {
				$('#sync-progress-bar').css('width', '100%');
				$('#sync-progress-text').text('Bu tarih aralığında hiç sipariş bulunamadı!').css('color', 'orange');
				setTimeout(function() { $('#sync-date-modal').fadeOut(); }, 2500);
				return;
			}
			
			$('#sync-stats-text').text('Toplam ' + window.hbtTotalExpectedOrders + ' sipariş bulundu. Çekim başlıyor...');
			// Gerçek çekimi başlat
			fetchOrdersRecursive(storeId, startDate, endDate, 0);

		}, function(err) {
			$('#sync-progress-text').text('Sipariş sayısı hesaplanamadı. İşlem durduruldu.').css('color', 'red');
			$('#btn-start-sync').show().prop('disabled', false).text('Tekrar Dene');
		});
	});

	// 3. Recursive (Zincirleme) AJAX Fonksiyonu (Kapanmayan Modal ve Kalıcı Özet)
	function fetchOrdersRecursive(storeId, startDate, endDate, pageIndex) {
		if (!window.hbtSyncIsRunning) return; 
		
		$('#sync-progress-text').text((pageIndex + 1) + '. sayfa çekiliyor... Lütfen bekleyin.').css('color', '#1d2327');
		
		hbtAjax('hbt_sync_store', { 
			store_id: storeId, 
			page: pageIndex, 
			size: 100,
			start_date: startDate,
			end_date: endDate
		}, function (res) {
			var returned = parseInt(res.returned || 0, 10);
			
			window.hbtTotalInserted = (window.hbtTotalInserted || 0) + parseInt(res.inserted || 0, 10);
			window.hbtTotalUpdated = (window.hbtTotalUpdated || 0) + parseInt(res.updated || 0, 10);
			window.hbtTotalSkipped = (window.hbtTotalSkipped || 0) + parseInt(res.skipped || 0, 10);
			window.hbtTotalFailed  = (window.hbtTotalFailed || 0) + parseInt(res.failed || 0, 10); // YENİ
			
			if (res.sync_data && res.sync_data.api_raw_data) {
				window.hbtAjaxAccumulatedOrders = window.hbtAjaxAccumulatedOrders.concat(res.sync_data.api_raw_data);
			}

			var currentFetched = window.hbtAjaxAccumulatedOrders.length;
			var percent = window.hbtTotalExpectedOrders > 0 ? Math.floor((currentFetched / window.hbtTotalExpectedOrders) * 100) : Math.min(10 + (pageIndex * 15), 90);
			if (percent > 100) percent = 100;
			
			$('#sync-progress-bar').css('width', percent + '%');
			
			var statsHtml = 'Beklenen (API): <strong>' + window.hbtTotalExpectedOrders + '</strong> | Çekilen: <strong>' + currentFetched + '</strong><br>' +
							'<span style="color:#4CAF50;">Yeni Kayıt: <strong>' + window.hbtTotalInserted + '</strong></span> | ' +
							'<span style="color:#2196F3;">Güncellenen: <strong>' + window.hbtTotalUpdated + '</strong></span> | ' +
							'<span style="color:#757575;">Geçilen: <strong>' + window.hbtTotalSkipped + '</strong></span> | ' +
							'<span style="color:#f44336;">Hatalı: <strong>' + window.hbtTotalFailed + '</strong></span>';
			$('#sync-stats-text').html(statsHtml);

			if (returned >= 100) {
				fetchOrdersRecursive(storeId, startDate, endDate, pageIndex + 1);
			} else {
				$('#sync-progress-bar').css('width', '100%');
				$('#sync-progress-text').text('Senkronizasyon Başarıyla Tamamlandı! (%100)').css('color', 'green');
				
                $('#btn-start-sync').hide();
                if ($('#btn-finish-sync').length === 0) {
                    $('#btn-start-sync').after('<button type="button" id="btn-finish-sync" class="button button-primary" style="margin-left:5px;">Sonuçları Gör ve Kapat</button>');
                } else {
                    $('#btn-finish-sync').show();
                }

                $('#btn-finish-sync').off('click').on('click', function() {
                    $('#sync-date-modal').fadeOut();
                    
                    setTimeout(function() {
                        $('#btn-finish-sync').hide();
                        $('#btn-start-sync').show().prop('disabled', false).text('Senkronizasyonu Başlat');
                        $('#sync-progress-area').hide();
                        $('#sync-date-form').show();
                        $('#sync-progress-bar').css('width', '0%');
                    }, 500);
                    
                    $('#hbt-sync-summary-box').remove(); 
                    var summaryBox = '<div id="hbt-sync-summary-box" style="background: #fff; border-left: 4px solid #4CAF50; padding: 15px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">' +
                        '<h3 style="margin-top:0; color: #4CAF50;">Senkronizasyon Özeti</h3>' +
                        '<p style="font-size: 15px; margin-bottom: 0;">' +
                        'İşlenen Sipariş: <strong>' + currentFetched + '</strong><br><br>' +
                        '<span style="display:inline-block; margin-right: 20px; color:#4CAF50; font-weight:bold;"><span class="dashicons dashicons-plus" style="vertical-align:text-bottom;"></span> Yeni Kayıt: ' + window.hbtTotalInserted + '</span> ' +
                        '<span style="display:inline-block; margin-right: 20px; color:#2196F3; font-weight:bold;"><span class="dashicons dashicons-update" style="vertical-align:text-bottom;"></span> Güncellenen: ' + window.hbtTotalUpdated + '</span> ' +
                        '<span style="display:inline-block; margin-right: 20px; color:#757575; font-weight:bold;"><span class="dashicons dashicons-hidden" style="vertical-align:text-bottom;"></span> Geçilen: ' + window.hbtTotalSkipped + '</span>' +
                        '<span style="display:inline-block; color:#f44336; font-weight:bold;"><span class="dashicons dashicons-warning" style="vertical-align:text-bottom;"></span> Hatalı Kayıt: ' + window.hbtTotalFailed + '</span>' +
                        '</p></div>';

                    $('.hbt-tpt-wrap h1').after(summaryBox);
                    
                    window.hbtTotalInserted = 0;
					window.hbtTotalUpdated = 0;
					window.hbtTotalSkipped = 0;
					window.hbtTotalFailed = 0;

                    renderAccumulatedTable();
                });
			}

		}, function () {
			$('#sync-progress-text').text('Bir hata oluştu veya Trendyol API yanıt vermedi. (503/429)').css('color', 'red');
			$('#btn-start-sync').show().prop('disabled', false).text('Kaldığı Yerden Devam Et');
			window.hbtSyncIsRunning = false;
		});
	
	}
	// 4. Biriken Verileri Ekranda Tablo Olarak Gösterme (Sizin sevdiğiniz sayfalamalı tablo)
	function renderAccumulatedTable() {
		if (window.hbtAjaxAccumulatedOrders.length > 0) {
			$('#hbt-ajax-sync-result').remove();
			
			window.hbtLastSyncOrders = window.hbtAjaxAccumulatedOrders;
			window.hbtSyncCurrentPage = 1;
			var totalFetched = window.hbtLastSyncOrders.length;
			
			var html = '<div id="hbt-ajax-sync-result" style="margin-top:30px;padding:15px;border:2px solid #007cba;background:#f4f8fb;">' +
					   '<h2>Tarih Aralıklı Senkronizasyon Raporu</h2>' +
					   '<p style="font-size: 14px;"><strong>Seçilen Aralıktaki Toplam Sipariş:</strong> <span style="color:red; font-weight:bold;">' + totalFetched + '</span> adet</p>' +
					   '<div id="hbt-sync-table-container"></div>' +
					   '</div>';
					   
			$('.hbt-tpt-wrap').append(html);
			window.hbtRenderSyncTable(); // Önceki adımda yazdığımız sayfalamalı tabloyu çizer
		}
	}
	// Toggle store active
	$(document).on('change', '.store-active-toggle', function () {
		var id       = $(this).data('id');
		var isActive = $(this).is(':checked') ? 1 : 0;
		hbtAjax('hbt_save_store', { id: id, is_active: isActive, store_name: '_keep_', supplier_id: '_keep_' }, function () {
			hbtToast(hbtTpt.strings.saved, 'success');
		});
	});

	// =========================================================================
	// Products: sync, add, save, inline edit, import
	// =========================================================================

	// Sync products from Trendyol
	$(document).on('click', '#btn-sync-products', function () {
		var $btn    = $(this).text(hbtTpt.strings.syncing).prop('disabled', true);
		var storeId = $('[name="store_id"]').val() || 0;
		hbtAjax('hbt_sync_products', { store_id: storeId }, function (res) {
			hbtToast(res.message || hbtTpt.strings.synced, 'success');
			$btn.text("Trendyol'dan Çek").prop('disabled', false);
			setTimeout(function () { location.reload(); }, 1000);
		}, function () {
			$btn.text("Trendyol'dan Çek").prop('disabled', false);
		});
	});

	// Manual product add modal open
	$(document).on('click', '#btn-add-product', function () {
		$('#product-id').val('');
		$('#product-form')[0].reset();
		$('#product-modal-title').text('Manuel Ürün Ekle');
		$('#product-modal').show();
	});

	// Open import modal (FIX: this handler was missing)
	$(document).on('click', '#btn-import-csv', function () {
		$('#import-form')[0].reset();
		$('#csv-filename').text('');
		$('#import-modal').show();
	});

	// Save product (inline or modal)
	$(document).on('click', '#btn-save-product', function () {
		var data = $('#product-form').serializeArray().reduce(function (o, f) { o[f.name] = f.value; return o; }, {});
		hbtAjax('hbt_save_product_cost', data, function () {
			hbtToast(hbtTpt.strings.saved, 'success');
			$('#product-modal').hide();
			location.reload();
		});
	});

	// Inline edit handlers (edit/save/cancel)
	$(document).on('click', '.btn-inline-edit', function () {
		var $row = $(this).closest('tr');
		$row.find('.hbt-inline-display').hide();
		$row.find('.hbt-inline-input').show();
		$(this).hide();
		$row.find('.btn-inline-save, .btn-inline-cancel').show();
	});

	$(document).on('click', '.btn-inline-cancel', function () {
		var $row = $(this).closest('tr');
		$row.find('.hbt-inline-display').show();
		$row.find('.hbt-inline-input').hide();
		$(this).hide();
		$row.find('.btn-inline-save').hide();
		$row.find('.btn-inline-edit').show();
	});


	// Anlık ürün kaydetme ve arayüzü güncelleme
	$(document).on('click', '.btn-inline-save', function () {
		var $btn  = $(this);
		var $row  = $btn.closest('tr');
		var id    = $btn.data('id');
		
		var data  = {
			id:            id,
			store_id:      $btn.data('store'),
			image_url:     $row.find('input[name="image_url"]').val(),
			product_name:  $row.find('input[name="product_name"]').val(),
			barcode:       $row.find('input[name="barcode"]').val(),
			sku:           $row.find('input[name="sku"]').val(),
			category_name: $row.find('input[name="category_name"]').val(),
			cost_usd:      $row.find('input[name="cost_usd"]').val(),
			desi:          $row.find('input[name="desi"]').val(),
			vat_rate:      $row.find('input[name="vat_rate"]').val()
		};

		hbtAjax('hbt_save_product_cost', data, function () {
			$row.find('.hbt-inline-display').each(function () {
				var name = $(this).siblings('input').attr('name');
				
				if (name === 'cost_usd') {
					$(this).text(parseFloat(data.cost_usd).toFixed(4));
				} else if (name === 'desi') {
					$(this).text(data.desi);
				} else if (name === 'vat_rate') {
					$(this).text(data.vat_rate + '%');
				} else if (name === 'image_url') {
					// Resmi Canlı Olarak Değiştirme
					var $displayDiv = $(this);
					if (data.image_url) {
						$displayDiv.find('.hbt-inline-img').attr('src', data.image_url).show();
						$displayDiv.find('.hbt-inline-no-img').hide();
					} else {
						$displayDiv.find('.hbt-inline-img').hide();
						$displayDiv.find('.hbt-inline-no-img').show();
					}
				} else if (name) {
					// Diğer metin alanları (Ürün Adı, Barkod, SKU, Kategori)
					$(this).text(data[name]);
				}
			});

			$row.find('.hbt-inline-display').show();
			$row.find('.hbt-inline-input').hide();
			$btn.hide();
			$row.find('.btn-inline-cancel').hide();
			$row.find('.btn-inline-edit').show();
			
			if (parseFloat(data.cost_usd) > 0) {
				$row.removeClass('cost-missing');
			}
			
			hbtToast(hbtTpt.strings.saved, 'success');
		});
	});
	// Delete product
	$(document).on('click', '.btn-delete-product', function () {
		var id = $(this).data('id');
		hbtConfirm(hbtTpt.strings.confirm_delete, function () {
			hbtAjax('hbt_delete_product', { id: id }, function () {
				location.reload();
			});
		});
	});

	// CSV Import interactions
	$(document).on('click', '#csv-drop-zone', function () { $('#csv-file-input').trigger('click'); });
	$(document).on('change', '#csv-file-input', function () {
		$('#csv-filename').text(this.files[0] ? this.files[0].name : '');
	});

	$(document).on('dragover', '#csv-drop-zone', function (e) {
		e.preventDefault();
		$(this).addClass('dragover');
	}).on('dragleave drop', '#csv-drop-zone', function (e) {
		e.preventDefault();
		$(this).removeClass('dragover');
		if (e.type === 'drop') {
			$('#csv-file-input').prop('files', e.originalEvent.dataTransfer.files);
			$('#csv-filename').text(e.originalEvent.dataTransfer.files[0].name);
		}
	});

	$(document).on('click', '#btn-do-import', function () {
		var $btn      = $(this).prop('disabled', true);
		var formData  = new FormData($('#import-form')[0]);
		formData.append('action', 'hbt_bulk_import_costs');
		formData.append('nonce', hbtTpt.nonce);

		$.ajax({
			url:         hbtTpt.ajaxurl,
			type:        'POST',
			data:        formData,
			processData: false,
			contentType: false,
			success: function (res) {
				if (res.success) {
					hbtToast(res.data.count + ' ürün içe aktarıldı.', 'success');
					$('#import-modal').hide();
					location.reload();
				} else {
					hbtToast(res.data.message || hbtTpt.strings.error, 'error');
					$btn.prop('disabled', false);
				}
			},
			error: function () {
				hbtToast(hbtTpt.strings.error, 'error');
				$btn.prop('disabled', false);
			}
		});
	});

	// =========================================================================
	// Shipping: add/edit/delete/save (delegated)
	// =========================================================================
	$(document).on('click', '#btn-add-shipping', function () {
		$('#shipping-id').val('');
		$('#shipping-form')[0].reset();
		$('#shipping-modal-title').text('Kargo Fiyatı Ekle');
		$('#shipping-store').hide().removeAttr('required');
		$('#shipping-stores-checkboxes').show();
		$('#shipping-modal').show();
	});

	$(document).on('click', '.btn-edit-shipping', function () {
		var $b = $(this);
		$('#shipping-id').val($b.data('id'));
		$('#shipping-stores-checkboxes').hide();
		$('#shipping-store').show().attr('required', 'required').val($b.data('store'));
		$('#shipping-company').val($b.data('company'));
		$('#shipping-price-min').val($b.data('price-min'));
		$('#shipping-price-max').val($b.data('price-max'));
		$('#shipping-cost').val($b.data('cost'));
		$('#shipping-from').val($b.data('from'));
		$('#shipping-to').val($b.data('to'));
		$('#shipping-modal-title').text('Kargo Fiyatı Düzenle');
		$('#shipping-modal').show();
	});

	$(document).on('click', '#btn-save-shipping', function () {
		var data = $('#shipping-form').serializeArray().reduce(function (o, f) {
			if (f.name === 'store_ids[]') {
				if (!o.store_ids) o.store_ids = [];
				o.store_ids.push(f.value);
			} else {
				o[f.name] = f.value;
			}
			return o;
		}, {});
		hbtAjax('hbt_save_shipping_cost', data, function () {
			hbtToast(hbtTpt.strings.saved, 'success');
			location.reload();
		});
	});

	$(document).on('change', '#shipping-select-all', function () {
		$('#shipping-stores-checkboxes input[name="store_ids[]"]').prop('checked', $(this).is(':checked'));
	});

	$(document).on('click', '.btn-delete-shipping', function () {
		var id = $(this).data('id');
		hbtConfirm(hbtTpt.strings.confirm_delete, function () {
			hbtAjax('hbt_delete_shipping_cost', { id: id }, function () { location.reload(); });
		});
	});

	// =========================================================================
	// Orders – details, recalc
	// =========================================================================
	$(document).on('click', '.btn-order-detail', function (e) {
		e.preventDefault();
		var id = $(this).data('id');
		$('#btn-recalculate-order').data('id', id);
		$('#order-detail-content').html('<p>' + hbtTpt.strings.saving + '</p>');
		$('#order-detail-modal').show();

		hbtAjax('hbt_get_order_details', { order_id: id }, function (data) {
			var o = data.order;
			var html = '<table class="wp-list-table widefat fixed striped"><thead><tr>' +
				'<th>Ürün</th><th>Barkod</th><th>Adet</th>' +
'<th>Satış (TL)</th><th>Maliyet (TL)</th><th>Komisyon (TL)</th><th>Komisyon (%)</th><th>Kargo</th><th>Sabit Gider</th><th>Net Kâr</th><th>Marj%</th>' +				'</tr></thead><tbody>';

			(data.items || []).forEach(function (item) {
				var cls = parseFloat(item.net_profit) >= 0 ? 'profit-positive' : 'profit-negative';

				// compute commission rate fallback if missing
				var line_total = parseFloat(item.line_total || 0);
				var commission_amount = parseFloat(item.commission_amount || 0);
				var commission_rate = null;
				if (typeof item.commission_rate !== 'undefined' && item.commission_rate !== null && item.commission_rate !== '') {
					commission_rate = parseFloat(item.commission_rate);
				} else if (line_total > 0 && commission_amount > 0) {
					commission_rate = (commission_amount / line_total) * 100;
				}

				html += '<tr class="' + cls + '">' +
					'<td>' + $('<span>').text(item.product_name).html() + '</td>' +
					'<td>' + $('<span>').text(item.barcode).html() + '</td>' +
					'<td>' + item.quantity + '</td>' +
					'<td>' + fmt(line_total) + '</td>' +
					'<td>' + fmt(item.total_cost_tl) + '</td>' +
					'<td>' + fmt(commission_amount) + '</td>' +
					'<td>' + (commission_rate !== null ? parseFloat(commission_rate).toFixed(2) + '%' : '-') + '</td>' +
					'<td>' + fmt(item.shipping_cost) + '</td>' +
					'<td>' + fmt(item.other_expenses || 0) + '</td>' +
					'<td>' + fmt(item.net_profit) + '</td>' +
					'<td>' + parseFloat(item.profit_margin || 0).toFixed(2) + '%</td>' +
					'</tr>';
			});

			// YENİ EKLENEN: Toplam sabit gideri hesapla
			var total_fixed = (data.items || []).reduce(function(sum, it) { return sum + parseFloat(it.other_expenses || 0); }, 0);

			html += '</tbody><tfoot><tr>' +
				'<td colspan="3"><strong>Toplam</strong></td>' +
				'<td>' + fmt(o.total_price) + '</td>' +
				'<td>' + fmt(o.total_cost_tl) + '</td>' +
				'<td>' + fmt(o.total_commission) + '</td>' +
				'<td>' + ( (typeof o.total_commission !== 'undefined' && o.total_commission > 0 && o.total_price > 0) ? ( (o.total_commission / o.total_price * 100).toFixed(2) + '%' ) : '-' ) + '</td>' +
				'<td>' + fmt(o.total_shipping) + '</td>' +
				'<td>' + fmt(total_fixed) + '</td>' + // YENİ EKLENEN
				'<td>' + fmt(o.net_profit) + '</td>' +
				'<td>' + parseFloat(o.profit_margin || 0).toFixed(2) + '%</td>' +
				'</tr></tfoot></table>';

			$('#order-detail-content').html(html);
		});
	});

	$(document).on('click', '#btn-recalculate-order', function () {
		var id   = $(this).data('id');
		var $btn = $(this).prop('disabled', true);
		hbtAjax('hbt_recalculate_order', { order_id: id }, function () {
			hbtToast(hbtTpt.strings.saved, 'success');
			$('#order-detail-modal').hide();
			location.reload();
		}, function () { $btn.prop('disabled', false); });
	});

	// =========================================================================
	// Export buttons (already handled above via delegated .btn-export)
	// =========================================================================

	// =========================================================================
	// Notifications — dismiss permanently (delegated)
	// =========================================================================
	$(document).on('click', '.hbt-admin-notice .hbt-notice-close', function (e) {
		e.preventDefault();
		var $notice = $(this).closest('.hbt-admin-notice');
		var id = $notice.data('id') || null;
		if (!id) {
			$notice.remove();
			return;
		}
		hbtAjax('hbt_dismiss_notification', { id: id }, function () {
			$notice.slideUp(200, function () { $notice.remove(); });
		}, function () {
			$notice.fadeOut(200, function () { $notice.remove(); });
		});
	});

	$(document).on('click', '.hbt-admin-notice .notice-dismiss', function () {
		var $notice = $(this).closest('.hbt-admin-notice');
		var id = $notice.data('id') || null;
		if (!id) return;
		hbtAjax('hbt_dismiss_notification', { id: id }, function () { /* ok */ }, function () { /* ignore */ });
	});

	// =========================================================================
	// Finalization area (no-op handlers)
	// =========================================================================

	// =========================================================================
	// Sabit Giderler - Kaydetme İşlemi
	// =========================================================================
	$(document).on('click', '.btn-save-fixed-costs', function() {
		var $btn = $(this);
		var originalText = $btn.text();
		$btn.text('Kaydediliyor...').prop('disabled', true);

		var costsData = {};
		$('.fixed-cost-row').each(function() {
			var storeId = $(this).data('store-id');
			costsData[storeId] = {
				personnel: $(this).find('.fc-personnel').val(),
				packaging: $(this).find('.fc-packaging').val(),
				other:     $(this).find('.fc-other').val()
			};
		});

		hbtAjax('hbt_save_fixed_costs', { costs: costsData }, function(res) {
			hbtToast(res.message || 'Başarıyla kaydedildi.', 'success');
			$btn.text(originalText).prop('disabled', false);
		}, function() {
			$btn.text(originalText).prop('disabled', false);
		});
	});



// =========================================================================
	// Sabit Giderler - Kaydetme İşlemi
	// =========================================================================
	$(document).on('click', '.btn-save-fixed-costs', function() {
		var $btn = $(this);
		var originalText = $btn.text();
		$btn.text(hbtTpt.strings.saving || 'Kaydediliyor...').prop('disabled', true);

		var costsData = {};
		$('.fixed-cost-row').each(function() {
			var storeId = $(this).data('store-id');
			costsData[storeId] = {
				personnel: $(this).find('.fc-personnel').val(),
				packaging: $(this).find('.fc-packaging').val(),
				other:     $(this).find('.fc-other').val()
			};
		});

		hbtAjax('hbt_save_fixed_costs', { costs: costsData }, function(res) {
			hbtToast(res.message || hbtTpt.strings.saved || 'Başarıyla kaydedildi.', 'success');
			$btn.text(originalText).prop('disabled', false);
		}, function() {
			$btn.text(originalText).prop('disabled', false);
		});
	});

	// =========================================================================
	// Hızlı Tarih Seçimi Butonları (SAAT BAZLI)
	// =========================================================================
	$(document).on('click', '.btn-quick-date', function(e) {
		e.preventDefault(); 

		// Artık veriyi data-hours üzerinden "Saat" olarak alıyoruz
		var hours = parseInt($(this).data('hours'), 10);
		
		var endDate = new Date();
		var startDate = new Date(endDate.getTime() - (hours * 60 * 60 * 1000));

		// datetime-local input'u için format: YYYY-MM-DDTHH:mm
		function formatDateTime(d) {
			var month = '' + (d.getMonth() + 1),
				day = '' + d.getDate(),
				year = d.getFullYear(),
				hour = '' + d.getHours(),
				minute = '' + d.getMinutes();

			if (month.length < 2) month = '0' + month;
			if (day.length < 2) day = '0' + day;
			if (hour.length < 2) hour = '0' + hour;
			if (minute.length < 2) minute = '0' + minute;

			return year + '-' + month + '-' + day + 'T' + hour + ':' + minute;
		}

		$('#sync-start-date').val(formatDateTime(startDate)).trigger('change');
		$('#sync-end-date').val(formatDateTime(endDate)).trigger('change');
		
		$('.btn-quick-date').removeClass('button-primary');
		$(this).addClass('button-primary');
	});

	// Senkronize Et modalı her açıldığında tarihleri sıfırla
	$(document).on('click', '.btn-sync-store', function () {
		$('.btn-quick-date').removeClass('button-primary');
		$('#sync-start-date').val('').trigger('change');
		$('#sync-end-date').val('').trigger('change');
	});

	jQuery(document).ready(function($) {

    // Bildirimler: Hepsini Oku (Kapat)
    $('#hbt-mark-all-read').on('click', function(e) {
        e.preventDefault();
        if (!confirm('Tüm bildirimler "okundu/kapatıldı" olarak işaretlenecek ve sayacınız sıfırlanacaktır. Onaylıyor musunuz?')) return;

        $.post(hbtTpt.ajaxurl, {
            action: 'hbt_mark_all_notifications_read',
            nonce: hbtTpt.nonce
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('İşlem sırasında bir hata oluştu.');
            }
        });
    });

    // Bildirimler: Tekil Silme
    $('.hbt-delete-notification').on('click', function(e) {
        e.preventDefault();
        if (!confirm('Bu bildirimi kalıcı olarak silmek istediğinize emin misiniz?')) return;

        var id = $(this).data('id');
        var $row = $(this).closest('tr');

        $.post(hbtTpt.ajaxurl, {
            action: 'hbt_delete_notification',
            id: id,
            nonce: hbtTpt.nonce
        }, function(response) {
            if (response.success) {
                $row.fadeOut(300, function() { $(this).remove(); });
            } else {
                alert('Silme işlemi başarısız.');
            }
        });
    });

    // Bildirimler: Toplu Silme
    $('#hbt-bulk-delete-notifications').on('click', function(e) {
        e.preventDefault();
        var ids = [];
        $('input[name="notification_ids[]"]:checked').each(function() {
            ids.push($(this).val());
        });

        if (ids.length === 0) {
            alert('Lütfen silmek için listeden en az bir bildirim seçin.');
            return;
        }

        if (!confirm('Seçilen ' + ids.length + ' adet bildirimi silmek istediğinize emin misiniz?')) return;

        $.post(hbtTpt.ajaxurl, {
            action: 'hbt_bulk_delete_notifications',
            ids: ids,
            nonce: hbtTpt.nonce
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Toplu silme başarısız.');
            }
        });
    });

    // Bildirimler: Tümünü Seç Checkbox Mantığı
    $('#cb-select-all-1').on('change', function() {
        var isChecked = $(this).prop('checked');
        $('input[name="notification_ids[]"]').prop('checked', isChecked);
    });

});

jQuery(document).ready(function($) {
    // Reklam Gideri Kaydetme
    $('#hbt-ad-expense-form').on('submit', function(e) {
        e.preventDefault();
        var data = $(this).serialize() + '&action=hbt_save_ad_expense&nonce=' + hbtTpt.nonce;
        
        $.post(hbtTpt.ajaxurl, data, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Kayıt başarısız.');
            }
        });
    });

    // Reklam Gideri Silme
    $('.hbt-delete-ad-expense').on('click', function(e) {
        e.preventDefault();
        if (!confirm('Bu reklam giderini silmek istediğinize emin misiniz?')) return;
        
        var id = $(this).data('id');
        $.post(hbtTpt.ajaxurl, {
            action: 'hbt_delete_ad_expense',
            id: id,
            nonce: hbtTpt.nonce
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Silme başarısız.');
            }
        });
    });
});
// =========================================================================
	// AYARLAR SAYFASI: Ayarları Kaydet
	// =========================================================================
	$(document).on('click', '#btn-save-settings', function () {
		var $btn = $(this);
		var originalText = $btn.html();
		
		$btn.html('<span class="dashicons dashicons-update hbt-spinner"></span> Kaydediliyor...').prop('disabled', true);
		$('#settings-result').text('').css('color', '');

		// Form verilerini al
		var data = {};
		$.each($('#settings-form').serializeArray(), function(i, field) {
			data[field.name] = field.value;
		});

		// Checkbox'lar işaretli değilse form gönderiminde yer almazlar, bunu manuel olarak yakalamalıyız (0 veya 1)
		$('#settings-form input[type="checkbox"]').each(function() {
			data[this.name] = $(this).is(':checked') ? 1 : 0;
		});

		// AJAX İsteği
		hbtAjax('hbt_save_settings', data, function (res) {
			$('#settings-result').text('Ayarlar başarıyla kaydedildi!').css('color', '#46b450');
			$btn.html(originalText).prop('disabled', false);
			
			// 3 saniye sonra başarı yazısını sil
			setTimeout(function() { 
				$('#settings-result').fadeOut(function(){ $(this).text('').show(); }); 
			}, 3000);
		}, function () {
			$('#settings-result').text('Kayıt başarısız oldu.').css('color', '#d63638');
			$btn.html(originalText).prop('disabled', false);
		});
	});

	// =========================================================================
	// AYARLAR SAYFASI: Tüm Siparişleri Yeniden Hesapla
	// =========================================================================
	$(document).on('click', '#btn-recalculate-all', function () {
		var $btn = $(this);
		if (!confirm('Tüm siparişlerin baştan hesaplanmasını onaylıyor musunuz? Bu işlem sipariş sayınıza göre birkaç dakika sürebilir.')) return;

		$btn.hide();
		$('#recalculate-progress').show();

		hbtAjax('hbt_recalculate_order', { order_id: 0 }, function (res) {
			hbtToast('Tüm siparişlerin yeniden hesaplanması başarıyla tamamlandı.', 'success');
			$('#recalculate-progress').hide();
			$btn.show();
		}, function () {
			$('#recalculate-progress').hide();
			$btn.show();
		});
	});
	// =========================================================================
	// Siparişler Sayfası: URL'den Gelen Sipariş No'yu Otomatik Ara (KESİN ÇÖZÜM)
	// =========================================================================
	$(document).ready(function() {
		var urlParams = new URLSearchParams(window.location.search);
		
		if (urlParams.get('page') === 'hbt-tpt-orders' && urlParams.has('search_order')) {
			var orderNo = urlParams.get('search_order');

			// DataTables'ın "init.dt" olayı: Tablo sunucudan ilk veriyi alıp ekrana basmayı tamamen bitirdiğinde tetiklenir.
			$(document).on('init.dt', function(e, settings) {
				var api = new $.fn.dataTable.Api(settings);
                
				// Sonsuz döngüye girmemesi için sadece 1 kere çalışmasını sağlıyoruz
				if (!window.hbtOrderAutoSearched) {
					window.hbtOrderAutoSearched = true; 
                    
					// API üzerinden aramayı tetikle ve tabloyu yeniden çizdir
					api.search(orderNo).draw();
					
					// Kullanıcının görsel olarak arama kutusunda da numarayı görebilmesi için:
					$('input[type="search"]').val(orderNo);
				}
			});
            
			// Fallback (Yedek Plan): Eğer DataTables çoktan yüklenmişse ve "init.dt" olayını kaçırdıysak
			setTimeout(function() {
				if (!window.hbtOrderAutoSearched) {
					var tables = $.fn.dataTable.tables({ api: true });
					if (tables.length > 0) {
						window.hbtOrderAutoSearched = true;
						tables.search(orderNo).draw();
						$('input[type="search"]').val(orderNo);
					}
				}
			}, 800);
		}
	});
}(jQuery));