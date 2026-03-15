<?php
/**
 * Orders view.
 *
 * @package HBT_Trendyol_Profit_Tracker
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$db     = HBT_Database::instance();
$stores = $db->get_stores();

$filters = array(
	'store_id'  => absint( $_GET['store_id'] ?? 0 ),
	'status'    => sanitize_text_field( $_GET['status'] ?? '' ),
	'date_from' => sanitize_text_field( $_GET['date_from'] ?? '' ),
	'date_to'   => sanitize_text_field( $_GET['date_to'] ?? '' ),
);
?>
<div class="wrap hbt-tpt-wrap">
	
	<div class="hbt-page-header">
		<h1 class="hbt-page-title">
			<span class="dashicons dashicons-cart"></span> 
			<?php esc_html_e( 'Siparişler ve Analiz', 'hbt-trendyol-profit-tracker' ); ?>
		</h1>
	</div>

	<div class="hbt-card" style="margin-bottom: 24px;">
		<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
			<h3 class="hbt-widget-title" style="margin: 0 !important; border-bottom: none; padding-bottom: 0;">
				<span class="dashicons dashicons-filter"></span> Gelişmiş Filtreleme
			</h3>
			<div class="hbt-quick-dates" style="display: flex; gap: 6px; flex-wrap: wrap;">
				<button type="button" class="hbt-btn hbt-btn-outline btn-order-date" data-type="today">Bugün</button>
				<button type="button" class="hbt-btn hbt-btn-outline btn-order-date" data-type="yesterday">Dün</button>
				<button type="button" class="hbt-btn hbt-btn-outline btn-order-date" data-type="this_week">Bu Hafta</button>
				<button type="button" class="hbt-btn hbt-btn-outline btn-order-date" data-type="this_month">Bu Ay</button>
				<button type="button" class="hbt-btn hbt-btn-outline btn-order-date" data-type="last_month">Geçen Ay</button>
			</div>
		</div>

		<form id="hbt-orders-filter-form" method="get" action="">
			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px;">
				
				<div class="hbt-filter-group">
					<label style="font-weight: 600; color: var(--hbt-primary); margin-bottom: 6px; display: block; font-size: 13px;">Başlangıç Tarihi</label>
					<input type="text" name="date_from" class="hbt-datepicker regular-text" value="<?php echo esc_attr( $filters['date_from'] ); ?>" placeholder="YYYY-MM-DD" style="width: 100%; padding: 8px 12px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border);">
				</div>

				<div class="hbt-filter-group">
					<label style="font-weight: 600; color: var(--hbt-primary); margin-bottom: 6px; display: block; font-size: 13px;">Bitiş Tarihi</label>
					<input type="text" name="date_to" class="hbt-datepicker regular-text" value="<?php echo esc_attr( $filters['date_to'] ); ?>" placeholder="YYYY-MM-DD" style="width: 100%; padding: 8px 12px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border);">
				</div>

				<div class="hbt-filter-group">
					<label style="font-weight: 600; color: var(--hbt-primary); margin-bottom: 6px; display: block; font-size: 13px;">Mağaza</label>
					<select name="store_id" style="width: 100%; padding: 8px 12px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border);">
						<option value="0">Tüm Mağazalar</option>
						<?php foreach ( $stores as $store ) : ?>
							<option value="<?php echo esc_attr( (string) $store->id ); ?>" <?php selected( $filters['store_id'], (int) $store->id ); ?>>
								<?php echo esc_html( $store->store_name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="hbt-filter-group">
					<label style="font-weight: 600; color: var(--hbt-primary); margin-bottom: 6px; display: block; font-size: 13px;">Kargo Durumu</label>
					<select name="status" style="width: 100%; padding: 8px 12px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border);">
						<option value="">Tümü</option>
						<option value="Created" <?php selected( $filters['status'], 'Created' ); ?>>Oluşturuldu</option>
						<option value="Picking" <?php selected( $filters['status'], 'Picking' ); ?>>Hazırlanıyor</option>
						<option value="Shipped" <?php selected( $filters['status'], 'Shipped' ); ?>>Kargoda</option>
						<option value="Delivered" <?php selected( $filters['status'], 'Delivered' ); ?>>Teslim Edildi</option>
						<option value="Returned" <?php selected( $filters['status'], 'Returned' ); ?>>İade Edildi</option>
						<option value="UnSupplied" <?php selected( $filters['status'], 'UnSupplied' ); ?>>Tedarik Edilemedi</option>
						<option value="Cancelled" <?php selected( $filters['status'], 'Cancelled' ); ?>>İptal Edildi</option>
					</select>
				</div>

				<div class="hbt-filter-group">
					<label style="font-weight: 600; color: var(--hbt-primary); margin-bottom: 6px; display: block; font-size: 13px;">Analiz (Renk) Durumu</label>
					<select name="analysis_status" style="width: 100%; padding: 8px 12px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border);">
						<option value="">Tüm Analizler</option>
						<option value="green">🟩 Kâr Edenler (Açık Yeşil)</option>
						<option value="red">🟥 Zarar Edenler (Açık Kırmızı)</option>
						<option value="orange">🟧 %19 Komisyon Bekleyenler (Turuncu)</option>
						<option value="yellow">🟨 Maliyeti Eksikler (Sarı/Krem)</option>
						<option value="gray">⬜ İptal / İadeler (Üstü Çizili)</option>
					</select>
				</div>

			</div>
			
			<div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; border-top: 1px solid var(--hbt-border); padding-top: 16px;">
				<button type="button" id="hbt-orders-filter-btn" class="hbt-btn hbt-btn-primary"><span class="dashicons dashicons-search"></span> Filtreyi Uygula</button>
				<button type="button" id="hbt-orders-clear-btn" class="hbt-btn hbt-btn-outline"><span class="dashicons dashicons-update-alt"></span> Filtreyi Temizle</button>
				
				<button type="button" id="hbt-export-excel-btn" class="hbt-btn hbt-btn-outline btn-export" style="margin-left: auto; color: #059669 !important; border-color: #A7F3D0 !important; background: #ECFDF5 !important;">
					<span class="dashicons dashicons-media-spreadsheet"></span> Excel İndir
				</button>
			</div>
		</form>
	</div>

	<div class="hbt-card" style="padding: 0; overflow: hidden; margin-bottom: 24px;">
		<table class="wp-list-table widefat fixed striped" id="hbt-orders-server-table" style="width:100%; border: none; margin: 0;">
			<thead>
				<tr>
					<th>Sipariş No</th>
					<th>Tarih</th>
					<th>Müşteri</th>
					<th>Toplam (TL)</th>
					<th>Maliyet (TL)</th>
					<th>Komisyon (TL)</th>
					<th>Kargo (TL)</th>
					<th>Sabit Gider (TL)</th> 
					<th>Net Kâr (TL)</th>
					<th>Marj (%)</th>
					<th>Durum</th>
				</tr>
			</thead>
			<tbody></tbody>
			<tfoot>
				<tr class="hbt-totals-row" style="background: #F8FAFC; border-top: 2px solid var(--hbt-border);">
					<td style="font-weight: 700; color: var(--hbt-primary); padding-left: 24px;">Genel Toplam</td>
					<td></td><td></td>
					<td><strong id="foot-price" style="color: var(--hbt-primary);">0.00</strong></td>
					<td><strong id="foot-cost" style="color: var(--hbt-primary);">0.00</strong></td>
					<td><strong id="foot-comm" style="color: var(--hbt-primary);">0.00</strong></td>
					<td><strong id="foot-ship" style="color: var(--hbt-primary);">0.00</strong></td>
					<td><strong style="color: var(--hbt-primary);">-</strong></td> 
					<td><strong id="foot-profit" style="font-size: 14px;">0.00</strong></td>
					<td></td><td></td>
				</tr>
			</tfoot>
		</table>
	</div>

	<div class="hbt-card" style="border-left: 4px solid var(--hbt-info); padding: 20px;">
		<h4 style="margin-top: 0; margin-bottom: 16px; font-size: 15px; color: var(--hbt-primary); display: flex; align-items: center; gap: 8px;">
			<span class="dashicons dashicons-info" style="color: var(--hbt-info);"></span> Tablo Renk ve İşaret Açıklamaları
		</h4>
		<ul style="margin: 0; font-size: 13px; line-height: 2; list-style: none; padding-left: 0; color: var(--hbt-text-main);">
			<li style="margin-bottom: 8px;">
				<span style="display:inline-block; width:14px; height:14px; background-color:#F0FDF4; border:1px solid #4CAF50; vertical-align:middle; margin-right:8px; border-radius: 4px;"></span>
				<strong style="color: var(--hbt-primary);">Açık Yeşil Satır:</strong> Net kâr elde edilen (kârlı) siparişleri gösterir.
			</li>
			<li style="margin-bottom: 8px;">
				<span style="display:inline-block; width:14px; height:14px; background-color:#FEF2F2; border:1px solid #dc3232; vertical-align:middle; margin-right:8px; border-radius: 4px;"></span>
				<span class="hbt-badge-zarar">ZARAR</span>
				<strong style="color: var(--hbt-primary);">Açık Kırmızı Satır:</strong> Net kârı eksiye düşen (zarar edilen) siparişleri gösterir.
			</li>
			<li style="margin-bottom: 8px;">
				<span style="display:inline-block; width:14px; height:14px; background-color:#FFFBEB; border:1px solid #F59E0B; border-left: 4px solid #F59E0B; vertical-align:middle; margin-right:8px; border-radius: 4px;"></span>
				<strong style="color: var(--hbt-primary);">Turuncu Şeritli Satır (%19 Komisyon):</strong> Trendyol API'den güncel komisyon henüz çekilemediği için sistem tarafından standart <strong>%19</strong> varsayılarak hesaplanmıştır. Finansal veriler Trendyol'a düştüğünde güncellenir.
			</li>
			<li style="margin-bottom: 8px;">
				<span style="display:inline-block; width:14px; height:14px; background-color:#FEF3C7; border:1px solid #FBBF24; vertical-align:middle; margin-right:8px; border-radius: 4px;"></span>
				<strong style="color: var(--hbt-primary);">Açık Sarı / Krem Satır:</strong> Siparişteki ürünlerden en az birinin maliyeti sisteme girilmemiştir. Kâr doğru hesaplanamaz.
			</li>
			<li>
				<span style="display:inline-block; width:14px; height:14px; background-color:#F8FAFC; border:1px solid #94A3B8; vertical-align:middle; margin-right:8px; border-radius: 4px;"></span>
				<span style="text-decoration: line-through; color: #64748B; font-weight:bold; margin-right: 4px;">Üstü Çizili:</span>
				İptal edilen, iade dönen veya tedarik edilemeyen siparişleri ifade eder.
			</li>
		</ul>
		<p style="margin: 16px 0 0 0; font-size: 12px; color: var(--hbt-text-muted); font-style: italic; border-top: 1px solid var(--hbt-border); padding-top: 12px;">
			* Ayrıca <strong>Marj (%)</strong> sütunundaki koyu kırmızı yanıp sönmeler çok düşük/riskli kâr marjını, yeşil yanıp sönmeler ise yüksek kârlılığı belirtir.
		</p>
	</div>

	<div id="order-detail-modal" class="hbt-modal" style="display:none;">
		<div class="hbt-modal-overlay"></div>
		<div class="hbt-modal-box" style="min-width: 600px;">
			<div class="hbt-modal-header">
				<h2><span class="dashicons dashicons-clipboard"></span> Sipariş Detayı</h2>
				<button class="hbt-modal-close">&times;</button>
			</div>
			<div class="hbt-modal-body" id="order-detail-content">
				<p style="text-align: center; color: var(--hbt-text-muted);"><span class="dashicons dashicons-update hbt-spinner"></span> Yükleniyor...</p>
			</div>
			<div style="padding: 16px 24px; border-top: 1px solid var(--hbt-border); display: flex; justify-content: flex-end; align-items: center; gap: 12px; background: #F8FAFC; border-radius: 0 0 var(--hbt-radius) var(--hbt-radius);">
				<button type="button" id="btn-recalculate-order" class="hbt-btn hbt-btn-primary" data-id=""><span class="dashicons dashicons-update-alt"></span> Yeniden Hesapla</button>
			</div>
		</div>
	</div>

</div>

<style>
/* DataTables Arayüz Hizalama Düzeltmeleri */
.dataTables_wrapper .dataTables_length {
    padding: 20px 0 10px 24px;
    color: var(--hbt-text-main);
}
.dataTables_wrapper .dataTables_filter {
    padding: 20px 24px 10px 0;
    color: var(--hbt-text-main);
}
.dataTables_wrapper .dataTables_info {
    padding: 20px 0 24px 24px;
    color: var(--hbt-text-muted) !important;
    font-size: 13px;
}
.dataTables_wrapper .dataTables_paginate {
    padding: 15px 24px 24px 0;
}

/* Sayfada x kayıt göster Dropdown Düzeltmesi (Ok simgesi ve rakam çakışması) */
.dataTables_wrapper select {
    padding: 4px 28px 4px 12px !important; /* Sağdan ok için boşluk bıraktık */
    border-radius: 6px !important;
    border: 1px solid var(--hbt-border) !important;
    background-position: right 8px center !important; 
    width: auto !important;
    min-width: 65px;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    background-image: url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important;
    background-size: 14px !important;
}

/* Arama Kutusu Düzeltmesi */
.dataTables_wrapper .dataTables_filter input {
    border: 1px solid var(--hbt-border) !important;
    border-radius: var(--hbt-radius-sm) !important;
    padding: 6px 12px !important;
    margin-left: 8px !important;
    outline: none;
}
.dataTables_wrapper .dataTables_filter input:focus {
    border-color: var(--hbt-secondary) !important;
    box-shadow: 0 0 0 1px var(--hbt-secondary) !important;
}

/* Tablo içindeki paddingleri biraz dengeleyelim */
.hbt-wrap table.wp-list-table thead th:first-child,
.hbt-wrap table.wp-list-table tbody td:first-child {
    padding-left: 24px !important;
}
.hbt-wrap table.wp-list-table thead th:last-child,
.hbt-wrap table.wp-list-table tbody td:last-child {
    padding-right: 24px !important;
}
</style>

<script>
jQuery(document).ready(function($) {
    if ($.fn.DataTable) {
        var ordersTable = $('#hbt-orders-server-table').DataTable({
            "destroy": true,
            "processing": true,
            "serverSide": true,
            "ajax": {
                "url": hbtTpt.ajaxurl,
                "type": "POST",
                "data": function(d) {
                    d.action = 'hbt_get_orders_ajax';
                    d.nonce = hbtTpt.nonce;
                    d.store_id = $('select[name="store_id"]').val();
                    d.status = $('select[name="status"]').val();
                    d.date_from = $('input[name="date_from"]').val();
                    d.date_to = $('input[name="date_to"]').val();
					d.analysis_status = $('select[name="analysis_status"]').val(); // Renk Filtresi
                }
            },
            "order": [[ 1, "desc" ]],
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/tr.json"
            },
            "pageLength": 50,
            "lengthMenu": [ [25, 50, 100, 250, 500], [25, 50, 100, 250, 500] ],
            "searching": true,
            "searchDelay": 800,
            "columns": [
                { "orderable": false }, // Sipariş No
                { "orderable": true },  // Tarih
                { "orderable": true },  // Müşteri
                { "orderable": true },  // Toplam
                { "orderable": true },  // Maliyet
                { "orderable": true },  // Komisyon
                { "orderable": true },  // Kargo
                { "orderable": false }, // Sabit Gider
                { "orderable": true },  // Net Kar
                { "orderable": true },  // Marj
                { "orderable": false }  // Durum
            ],
            "columnDefs": [
                {
                    "targets": 9,
                    "createdCell": function (td, cellData, rowData, row, col) {
                        var bgClass = $(cellData).attr('data-bg-class');
                        if (bgClass) { $(td).addClass(bgClass); }
                    }
                }
            ],
            "drawCallback": function(settings) {
                var json = settings.json;
                if (json && json.customTotals) {
                    $('#foot-price').text(parseFloat(json.customTotals.sum_price || 0).toLocaleString('tr-TR', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' ₺');
                    $('#foot-cost').text(parseFloat(json.customTotals.sum_cost || 0).toLocaleString('tr-TR', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' ₺');
                    $('#foot-comm').text(parseFloat(json.customTotals.sum_comm || 0).toLocaleString('tr-TR', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' ₺');
                    $('#foot-ship').text(parseFloat(json.customTotals.sum_ship || 0).toLocaleString('tr-TR', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' ₺');
                    
                    var totalProfit = parseFloat(json.customTotals.sum_profit || 0);
                    $('#foot-profit').text(totalProfit.toLocaleString('tr-TR', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' ₺');
                    if(totalProfit < 0) { $('#foot-profit').css('color', 'var(--hbt-danger)'); } else { $('#foot-profit').css('color', 'var(--hbt-success)'); }
                }
            }
        });

        $('#hbt-orders-filter-btn').on('click', function(e) {
            e.preventDefault();
            ordersTable.ajax.reload();
        });
        
		// Filtreyi Temizle İşlevi
        $('#hbt-orders-clear-btn').on('click', function(e) {
            e.preventDefault();
            // Form alanlarını sıfırla
            $('select[name="store_id"]').val('0');
            $('select[name="status"]').val('');
            $('select[name="analysis_status"]').val('');
            $('input[name="date_from"]').val('');
            $('input[name="date_to"]').val('');
            
            // Eğer flatpickr (tarih seçici) kullanılıyorsa onu da temizle
            if($('input[name="date_from"]')[0]._flatpickr) $('input[name="date_from"]')[0]._flatpickr.clear();
            if($('input[name="date_to"]')[0]._flatpickr) $('input[name="date_to"]')[0]._flatpickr.clear();

            // Yeni stildeki aktif butonların rengini sıfırla
            $('.btn-order-date').removeClass('hbt-btn-primary').addClass('hbt-btn-outline');

            // Tabloyu filtresiz haliyle yeniden yükle
            ordersTable.ajax.reload();
        });

        // Excel İndirme İşlevi
        $('#hbt-export-excel-btn').on('click', function(e) {
            e.preventDefault();
            // Mevcut filtre değerlerini al
            var store_id = $('select[name="store_id"]').val();
            var status = $('select[name="status"]').val();
            var date_from = $('input[name="date_from"]').val();
            var date_to = $('input[name="date_to"]').val();
            var analysis_status = $('select[name="analysis_status"]').val();
            
            // Arka plandaki (PHP) indirme linkine yönlendir
            var exportUrl = hbtTpt.ajaxurl + '?action=hbt_export_orders&store_id=' + store_id + '&status=' + status + '&date_from=' + date_from + '&date_to=' + date_to + '&analysis_status=' + analysis_status;
            window.location.href = exportUrl;
        });

		// Hızlı Tarih Seçim Butonları İşlevi
		$('.btn-order-date').on('click', function(e) {
			e.preventDefault();
			
            // Eski 'button-primary' yerine yeni tasarım sınıflarını (toggle) uygula
			$('.btn-order-date').removeClass('hbt-btn-primary').addClass('hbt-btn-outline');
			$(this).removeClass('hbt-btn-outline').addClass('hbt-btn-primary');
			
			var type = $(this).data('type');
			var today = new Date();
			var start = '', end = '';
			
			function formatDate(d) {
				var month = '' + (d.getMonth() + 1), day = '' + d.getDate(), year = d.getFullYear();
				if (month.length < 2) month = '0' + month;
				if (day.length < 2) day = '0' + day;
				return year + '-' + month + '-' + day;
			}

			if (type === 'today') {
				start = end = formatDate(today);
			} else if (type === 'yesterday') {
				var yest = new Date(today); yest.setDate(yest.getDate() - 1);
				start = end = formatDate(yest);
			} else if (type === 'this_week') {
				var first = today.getDate() - (today.getDay() === 0 ? 6 : today.getDay() - 1); 
				var mon = new Date(today.setDate(first));
				start = formatDate(mon);
				end = formatDate(new Date()); 
			} else if (type === 'this_month') {
				var firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
				start = formatDate(firstDay);
				end = formatDate(new Date());
			} else if (type === 'last_month') {
				var firstDayLM = new Date(today.getFullYear(), today.getMonth() - 1, 1);
				var lastDayLM = new Date(today.getFullYear(), today.getMonth(), 0);
				start = formatDate(firstDayLM);
				end = formatDate(lastDayLM);
			}
			
			// Tarih inputlarını güncelle (Flatpickr kullanıyorsan tetikle)
			$('input[name="date_from"]').val(start);
			if($('input[name="date_from"]')[0]._flatpickr) $('input[name="date_from"]')[0]._flatpickr.setDate(start);

			$('input[name="date_to"]').val(end);
			if($('input[name="date_to"]')[0]._flatpickr) $('input[name="date_to"]')[0]._flatpickr.setDate(end);

			ordersTable.ajax.reload(); // Tabloyu otomatik yenile
		});
    }
});
</script>