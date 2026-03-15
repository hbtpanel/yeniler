<?php
/**
 * Ad Expenses view.
 *
 * @package HBT_Trendyol_Profit_Tracker
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$db       = HBT_Database::instance();
$stores   = $db->get_stores();
$expenses = $db->get_all_ad_expenses();

$platforms = array(
	'Trendyol İçi Reklam',
	'Meta (Facebook/Instagram)',
	'Google Ads',
	'TikTok',
	'Diğer'
);

// Tabloda ID yerine Mağaza adını gösterebilmek için ufak bir haritalama (UX İyileştirmesi)
$store_map = array();
foreach ( $stores as $s ) {
	$store_map[ $s->id ] = $s->store_name;
}
?>
<div class="wrap hbt-tpt-wrap">
	
	<div class="hbt-page-header">
		<h1 class="hbt-page-title">
			<span class="dashicons dashicons-megaphone"></span> 
			<?php esc_html_e( 'Reklam Giderleri Yönetimi', 'hbt-trendyol-profit-tracker' ); ?>
		</h1>
	</div>

	<div class="hbt-alert-box hbt-alert-info" style="margin-bottom: 24px;">
		<span class="dashicons dashicons-info"></span> 
		<div><?php esc_html_e( 'Buradan girdiğiniz reklam harcamaları, belirlediğiniz tarih aralığına gün bazında eşit olarak bölünerek Raporlar ve Dashboard sayfasındaki net kârdan otomatik olarak düşülür.', 'hbt-trendyol-profit-tracker' ); ?></div>
	</div>

	<div class="hbt-card" style="margin-bottom: 24px; padding: 0; overflow: hidden;">
		<h3 class="hbt-widget-title" style="margin: 0; padding: 20px 24px; background: #F8FAFC; border-bottom: 1px solid var(--hbt-border);">
			<span class="dashicons dashicons-plus-alt2" style="color: var(--hbt-secondary);"></span> <?php esc_html_e( 'Yeni Reklam Gideri Ekle', 'hbt-trendyol-profit-tracker' ); ?>
		</h3>
		
		<div style="padding: 24px;">
			<form id="hbt-ad-expense-form">
				<input type="hidden" name="id" id="ad_expense_id" value="0">
				
				<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
					
					<div class="hbt-filter-group">
						<label style="font-weight: 600; color: var(--hbt-primary); margin-bottom: 8px; display: block; font-size: 13px;">Mağaza:</label>
						<select name="store_id" id="ad_store_id" required style="width: 100%; padding: 10px 12px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border);">
							<?php foreach ( $stores as $s ) : ?>
								<option value="<?php echo esc_attr( $s->id ); ?>"><?php echo esc_html( $s->store_name ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="hbt-filter-group">
						<label style="font-weight: 600; color: var(--hbt-primary); margin-bottom: 8px; display: block; font-size: 13px;">Reklam Platformu:</label>
						<select name="platform" id="ad_platform" required style="width: 100%; padding: 10px 12px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border);">
							<?php foreach ( $platforms as $p ) : ?>
								<option value="<?php echo esc_attr( $p ); ?>"><?php echo esc_html( $p ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="hbt-filter-group">
						<label style="font-weight: 600; color: var(--hbt-primary); margin-bottom: 8px; display: block; font-size: 13px;">Başlangıç Tarihi:</label>
						<input type="text" name="start_date" id="ad_start_date" class="hbt-datepicker regular-text" required style="width: 100%; padding: 10px 12px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border);" autocomplete="off" placeholder="YYYY-MM-DD">
					</div>

					<div class="hbt-filter-group">
						<label style="font-weight: 600; color: var(--hbt-primary); margin-bottom: 8px; display: block; font-size: 13px;">Bitiş Tarihi:</label>
						<input type="text" name="end_date" id="ad_end_date" class="hbt-datepicker regular-text" required style="width: 100%; padding: 10px 12px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border);" autocomplete="off" placeholder="YYYY-MM-DD">
					</div>

					<div class="hbt-filter-group" style="grid-column: 1 / -1;">
						<label style="font-weight: 600; color: var(--hbt-primary); margin-bottom: 8px; display: block; font-size: 13px;">Toplam Harcama Tutarı (KDV Dahil):</label>
						<div style="position: relative; max-width: 300px;">
							<span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--hbt-text-muted); font-weight: 600; font-size: 16px;">₺</span>
							<input type="number" step="0.01" min="0" name="total_amount" id="ad_total_amount" required style="width: 100%; padding: 10px 12px 10px 32px; border-radius: var(--hbt-radius-sm); border: 1px solid var(--hbt-border); font-size: 16px; font-weight: 600; color: var(--hbt-primary);" placeholder="0.00">
						</div>
					</div>

				</div>

				<div style="display: flex; gap: 10px; align-items: center; border-top: 1px solid var(--hbt-border); padding-top: 20px; margin-top: 10px;">
					<button type="submit" class="hbt-btn hbt-btn-primary">
						<span class="dashicons dashicons-saved"></span> Gideri Kaydet
					</button>
					<button type="button" class="hbt-btn hbt-btn-outline" onclick="document.getElementById('hbt-ad-expense-form').reset(); document.getElementById('ad_expense_id').value='0';">
						<span class="dashicons dashicons-update-alt"></span> Formu Temizle
					</button>
				</div>
			</form>
		</div>
	</div>

	<div class="hbt-card" style="padding: 0; overflow: hidden; margin-bottom: 24px;">
		<table class="wp-list-table widefat fixed striped hbt-datatable" style="border: none; margin: 0; width: 100%;">
			<thead>
				<tr>
					<th>Mağaza</th>
					<th>Reklam Platformu</th>
					<th>Tarih Aralığı</th>
					<th>Süre (Gün)</th>
					<th>Toplam Tutar</th>
					<th>Günlük Gider</th>
					<th style="width: 120px;">İşlem</th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $expenses ) ) : ?>
					<tr>
						<td colspan="7" style="text-align: center; padding: 40px 20px; color: var(--hbt-text-muted);">
							<span class="dashicons dashicons-megaphone" style="font-size: 32px; width: 32px; height: 32px; margin-bottom: 10px; opacity: 0.5;"></span><br>
							<?php esc_html_e( 'Henüz bir reklam gideri eklenmemiş.', 'hbt-trendyol-profit-tracker' ); ?>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $expenses as $exp ) : 
						$start = new DateTime( $exp->start_date );
						$end   = new DateTime( $exp->end_date );
						$days  = $start->diff( $end )->days + 1;
						$store_name = isset( $store_map[ $exp->store_id ] ) ? $store_map[ $exp->store_id ] : $exp->store_id;
					?>
						<tr>
							<td style="font-weight: 600; color: var(--hbt-primary);">
								<span class="dashicons dashicons-store" style="color: var(--hbt-text-muted); margin-right: 5px; font-size: 16px; width: 16px; height: 16px; vertical-align: text-top;"></span>
								<?php echo esc_html( $store_name ); ?>
							</td>
							<td style="font-weight: 500;"><?php echo esc_html( $exp->platform ); ?></td>
							<td style="color: var(--hbt-text-muted); font-size: 13px;">
								<span class="dashicons dashicons-calendar-alt" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle;"></span>
								<?php echo esc_html( wp_date( 'd.m.Y', strtotime($exp->start_date) ) . ' - ' . wp_date( 'd.m.Y', strtotime($exp->end_date) ) ); ?>
							</td>
							<td>
								<span style="background: var(--hbt-bg-color); color: var(--hbt-primary); padding: 4px 8px; border-radius: 4px; font-weight: 600; border: 1px solid var(--hbt-border); font-size: 12px;">
									<?php echo esc_html( $days ); ?> Gün
								</span>
							</td>
							<td>
								<span style="color: var(--hbt-danger); font-weight: 700; background: var(--hbt-danger-bg); padding: 4px 10px; border-radius: 6px;">
									-<?php echo esc_html( number_format( $exp->total_amount, 2 ) ); ?> ₺
								</span>
							</td>
							<td style="font-weight: 600; color: var(--hbt-text-muted);">
								<?php echo esc_html( number_format( $exp->daily_amount, 2 ) ); ?> ₺ <span style="font-size: 11px; font-weight: normal;">/ Gün</span>
							</td>
							<td>
								<button type="button" class="button button-small button-link-delete hbt-delete-ad-expense hbt-btn hbt-btn-outline" data-id="<?php echo esc_attr( $exp->id ); ?>" style="padding: 4px 10px !important; font-size: 12px !important; height: auto; color: var(--hbt-danger) !important; border-color: #FCA5A5 !important;">
									<span class="dashicons dashicons-trash"></span> Sil
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

</div>

<style>
/* Tablo içindeki paddingleri biraz dengeleyelim */
.hbt-wrap table.wp-list-table thead th:first-child,
.hbt-wrap table.wp-list-table tbody td:first-child {
    padding-left: 24px !important;
}
.hbt-wrap table.wp-list-table thead th:last-child,
.hbt-wrap table.wp-list-table tbody td:last-child {
    padding-right: 24px !important;
}

/* İnput Odaklanma (Focus) Efektleri */
#hbt-ad-expense-form input:focus, #hbt-ad-expense-form select:focus {
    border-color: var(--hbt-secondary) !important;
    box-shadow: 0 0 0 1px var(--hbt-secondary) !important;
    outline: none;
}

/* DataTables Arayüz Hizalama Düzeltmeleri (Eğer Datatable yüklenirse diye) */
.dataTables_wrapper .dataTables_length { padding: 20px 0 10px 24px; color: var(--hbt-text-main); }
.dataTables_wrapper .dataTables_filter { padding: 20px 24px 10px 0; color: var(--hbt-text-main); }
.dataTables_wrapper .dataTables_info { padding: 20px 0 24px 24px; color: var(--hbt-text-muted) !important; font-size: 13px; }
.dataTables_wrapper .dataTables_paginate { padding: 15px 24px 24px 0; }
</style>