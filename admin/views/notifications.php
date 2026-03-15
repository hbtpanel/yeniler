<?php
/**
 * Notifications view (DataTables + Filters + Clickable Orders)
 *
 * @package HBT_Trendyol_Profit_Tracker
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$db = HBT_Database::instance();
// DataTables tarafında sayfalama yapacağımız için PHP tarafında limiti yüksek (örn: 2000) tutuyoruz.
$notifications = $db->get_all_notifications_paginated( 2000, 0 );
?>
<div class="wrap hbt-tpt-wrap">
    
    <div class="hbt-page-header">
        <h1 class="hbt-page-title">
            <span class="dashicons dashicons-bell"></span> 
            <?php esc_html_e( 'Sistem Bildirimleri', 'hbt-trendyol-profit-tracker' ); ?>
        </h1>
        <div class="hbt-header-actions">
            <button type="button" class="hbt-btn hbt-btn-primary" id="hbt-mark-all-read">
                <span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'Hepsini Oku (Kapat)', 'hbt-trendyol-profit-tracker' ); ?>
            </button>
            <button type="button" class="hbt-btn hbt-btn-outline" id="hbt-bulk-delete-notifications" style="color: var(--hbt-danger) !important; border-color: #FCA5A5 !important;">
                <span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Seçilenleri Sil', 'hbt-trendyol-profit-tracker' ); ?>
            </button>
        </div>
    </div>

    <div class="hbt-filters-bar" style="background: #fff; padding: 15px 20px; border: 1px solid var(--hbt-border); border-radius: var(--hbt-radius); margin-bottom: 20px; display: flex; gap: 15px; align-items: center; flex-wrap: wrap; box-shadow: var(--hbt-shadow);">
        <strong style="color: var(--hbt-primary);"><span class="dashicons dashicons-filter"></span> Filtreler:</strong>
        
        <select id="filter-status" style="padding: 6px 12px; border-radius: 4px; border: 1px solid #ccc;">
            <option value="">Tüm Durumlar</option>
            <option value="unread">Okunmamış</option>
            <option value="read">Okunmuş</option>
        </select>

        <select id="filter-type" style="padding: 6px 12px; border-radius: 4px; border: 1px solid #ccc;">
            <option value="">Tüm Bildirim Tipleri</option>
            <option value="loss_alert">Zarar Uyarısı</option>
            <option value="critical_loss">Kritik Zarar Uyarısı</option>
            <option value="cost_missing">Eksik Maliyet</option>
            <option value="info">Genel Bilgi</option>
        </select>

        <input type="text" id="filter-date-start" class="hbt-datepicker" placeholder="Başlangıç Tarihi" style="padding: 6px 12px; border-radius: 4px; border: 1px solid #ccc; width: 140px;">
        <input type="text" id="filter-date-end" class="hbt-datepicker" placeholder="Bitiş Tarihi" style="padding: 6px 12px; border-radius: 4px; border: 1px solid #ccc; width: 140px;">
        
        <button type="button" id="btn-reset-filters" class="button" style="margin-left: auto;">Filtreleri Temizle</button>
    </div>

    <div class="hbt-card" style="padding: 0; overflow: hidden; margin-bottom: 24px;">
        <table id="notifications-table" class="wp-list-table widefat fixed striped table-view-list display" style="border: none; margin: 0; width: 100%;">
            <thead>
                <tr>
                    <th style="width: 40px; text-align: center;" data-orderable="false">
                        <input id="cb-select-all-1" type="checkbox">
                    </th>
                    <th style="width: 15%;"><?php esc_html_e( 'Tip', 'hbt-trendyol-profit-tracker' ); ?></th>
                    <th style="width: 20%;"><?php esc_html_e( 'Başlık', 'hbt-trendyol-profit-tracker' ); ?></th>
                    <th><?php esc_html_e( 'Mesaj', 'hbt-trendyol-profit-tracker' ); ?></th>
                    <th style="width: 15%;"><?php esc_html_e( 'Tarih', 'hbt-trendyol-profit-tracker' ); ?></th>
                    <th style="width: 100px; text-align:center;" data-orderable="false"><?php esc_html_e( 'İşlem', 'hbt-trendyol-profit-tracker' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $notifications ) ) : ?>
                    <?php foreach ( $notifications as $notification ) : 
                        // Filtreleme için kullanılacak data attribute verileri
                        $status_attr = $notification->is_read ? 'read' : 'unread';
                        $type_attr   = esc_attr( $notification->notification_type );
                        $date_attr   = gmdate( 'Y-m-d', strtotime( $notification->created_at ) );

                        // Sipariş Numarasını Tıklanabilir Linke Çevirme İşlemi
                        $message = wp_kses_post( $notification->message );
                        // Eğer mesajın içinde "#123456789" formatında bir sipariş no varsa, onu linke dönüştür.
                        $orders_url = admin_url( 'admin.php?page=hbt-tpt-orders' );
                        $message = preg_replace('/#(\d+)/', '<a href="' . $orders_url . '&search_order=$1" style="font-weight:700; color:#2271b1; text-decoration:underline;" target="_blank">#$1</a>', $message);
                    ?>
                        <tr class="<?php echo $notification->is_read ? '' : 'hbt-unread-row'; ?>" 
                            data-status="<?php echo $status_attr; ?>" 
                            data-type="<?php echo $type_attr; ?>" 
                            data-date="<?php echo $date_attr; ?>">
                            
                            <td style="text-align: center; vertical-align: middle;">
                                <input type="checkbox" name="notification_ids[]" value="<?php echo esc_attr( $notification->id ); ?>">
                            </td>
                            <td style="vertical-align: middle;">
                                <span style="background: <?php echo $notification->is_read ? 'var(--hbt-bg-color)' : '#FEF3C7'; ?>; 
                                             color: <?php echo $notification->is_read ? 'var(--hbt-text-muted)' : '#92400E'; ?>; 
                                             padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; border: 1px solid <?php echo $notification->is_read ? 'var(--hbt-border)' : '#FCD34D'; ?>;">
                                    <?php echo esc_html( strtoupper( str_replace('_', ' ', $notification->notification_type) ) ); ?>
                                </span>
                            </td>
                            <td style="vertical-align: middle; font-weight: 600; color: var(--hbt-primary);">
                                <?php echo esc_html( $notification->title ); ?>
                            </td>
                            <td style="vertical-align: middle; color: var(--hbt-text-main); font-size: 13px;">
                                <?php echo $message; // Linki bozmaması için esc_html KULLANMIYORUZ, yukarıda kses kullandık. ?>
                            </td>
                            <td style="vertical-align: middle; color: var(--hbt-text-muted); font-size: 12px;" data-sort="<?php echo esc_attr( strtotime($notification->created_at) ); ?>">
                                <span class="dashicons dashicons-clock" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle;"></span>
                                <?php echo esc_html( wp_date( 'd.m.Y H:i', strtotime( $notification->created_at ) ) ); ?>
                            </td>
                            <td style="vertical-align: middle; text-align:center;">
                                <button type="button" class="button button-small button-link-delete hbt-delete-notification hbt-btn hbt-btn-outline" data-id="<?php echo esc_attr( $notification->id ); ?>" style="padding: 4px 10px !important; font-size: 12px !important; height: auto; color: var(--hbt-danger) !important; border-color: #FCA5A5 !important;">
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
/* Okunmamış Satır Vurgusu */
.hbt-unread-row { background-color: #FFFBEB !important; }
.hbt-unread-row td:first-child { border-left: 4px solid var(--hbt-warning) !important; }
.hbt-unread-row td { font-weight: 500; }

/* DataTables Özelleştirmeleri */
.dataTables_wrapper .dataTables_filter { margin-bottom: 15px; margin-right: 20px; margin-top: 15px; }
.dataTables_wrapper .dataTables_length { margin-bottom: 15px; margin-left: 20px; margin-top: 15px; }
.dataTables_wrapper .dataTables_paginate { margin-top: 15px; margin-right: 20px; margin-bottom: 15px; }
.dataTables_wrapper .dataTables_info { margin-top: 20px; margin-left: 20px; }
table.dataTable.no-footer { border-bottom: 1px solid var(--hbt-border); }
</style>

<script>
jQuery(document).ready(function($) {
    // 1. DataTables Başlatma
    var notifTable = $('#notifications-table').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/tr.json'
        },
        pageLength: 50,
        order: [[ 4, 'desc' ]], // 4. sütun (Tarih) baz alınarak yeniden eskiye doğru sıralar
        stateSave: true // Sayfa yenilense bile son kalınan sayfayı hatırlar
    });

    // 2. DataTables Özel Arama/Filtreleme Motoru
    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
        if (settings.nTable.id !== 'notifications-table') return true;

        var filterStatus = $('#filter-status').val();
        var filterType   = $('#filter-type').val();
        var dateStart    = $('#filter-date-start').val();
        var dateEnd      = $('#filter-date-end').val();

        // Satırdaki data- attribute değerlerini çekiyoruz
        var rowNode   = notifTable.row(dataIndex).node();
        var rowStatus = $(rowNode).attr('data-status');
        var rowType   = $(rowNode).attr('data-type');
        var rowDate   = $(rowNode).attr('data-date');

        // Durum Filtresi
        if (filterStatus && filterStatus !== rowStatus) {
            return false;
        }

        // Tip Filtresi (Örn: loss_alert, critical_loss_alert gibi alt varyasyonları kapsamak için indexOf kullanıyoruz)
        if (filterType && rowType.indexOf(filterType) === -1) {
            return false;
        }

        // Tarih Filtresi (Sadece YYYY-MM-DD olarak string karşılaştırması yeterlidir)
        if (dateStart && rowDate < dateStart) {
            return false;
        }
        if (dateEnd && rowDate > dateEnd) {
            return false;
        }

        return true;
    });

    // 3. Select Kutuları veya Tarih Değiştiğinde Tabloyu Yeniden Çiz (Filtrele)
    $('#filter-status, #filter-type, #filter-date-start, #filter-date-end').on('change', function() {
        notifTable.draw();
    });

    // 4. Filtreleri Temizle Butonu
    $('#btn-reset-filters').on('click', function() {
        $('#filter-status').val('');
        $('#filter-type').val('');
        // flatpickr instance'larını temizleme
        document.getElementById("filter-date-start")._flatpickr.clear();
        document.getElementById("filter-date-end")._flatpickr.clear();
        
        // DataTables arama kutusunu da temizle
        notifTable.search('').draw();
    });
});
</script>