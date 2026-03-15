<?php
/**
 * Profit calculator class.
 *
 * @package HBT_Trendyol_Profit_Tracker
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class HBT_Profit_Calculator
 *
 * Calculates per-item and per-order profit/loss.
 */
class HBT_Profit_Calculator {

    /**
     * Database instance.
     *
     * @var HBT_Database
     */
    private HBT_Database $db;

    /**
     * Currency service instance.
     *
     * @var HBT_Currency_Service
     */
    private HBT_Currency_Service $currency_service;

    /**
     * Notification manager instance.
     *
     * @var HBT_Notification_Manager
     */
    private HBT_Notification_Manager $notification_manager;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->db                   = HBT_Database::instance();
        $this->currency_service     = HBT_Currency_Service::instance();
        $this->notification_manager = HBT_Notification_Manager::instance();
    }

    // -------------------------------------------------------------------------
    // Main calculation
    // -------------------------------------------------------------------------

    /**
     * Calculate profit/loss for an order.
     *
     * Now computes order-level shipping and commission and distributes them across items.
     *
     * Uses order.total_price (customer paid) as authoritative sales amount and
     * distributes that across items proportionally to their raw line totals (to apply order-level discounts).
     *
     * @param  int  $order_id Order DB ID.
     * @return bool           True on success.
     */
    public function calculate_order( int $order_id ): bool {
        $order = $this->db->get_order( $order_id );
        if ( ! $order ) {
            return false;
        }

        $items = $this->db->get_order_items( $order_id );
        if ( empty( $items ) ) {
            return false;
        }

        // Get USD rate for order date.
        $rate_obj  = $this->currency_service->get_rate_for_datetime( $order->order_date );
        $usd_rate  = $rate_obj ? (float) $rate_obj->buying_rate : 0.0;
        $rate_type = $rate_obj ? $rate_obj->rate_type : 'unknown';

        // Compute raw item totals (as stored) and sum.
        $item_raws  = array();
        $total_raw  = 0.0;
        $total_qty  = 0;
        foreach ( $items as $it ) {
            $raw = (float) $it->line_total;
            
            // İndirim zaten line_total içerisinde düşülmüş olduğu için tekrar ÇIKARILMAZ.
            
            $item_raws[ $it->id ] = $raw;
            $total_raw += $raw;
            $total_qty += (int) $it->quantity;
        }

        // Use order.total_price (customer paid) as authoritative amount.
        $order_total = (float) $order->total_price;
        $scale = 1.0;

        // If total_raw differs from order_total (e.g. order-level discounts), compute scale factor.
        if ( $total_raw > 0.00001 && abs( $total_raw - $order_total ) > 0.01 ) {
            $scale = $order_total / $total_raw;
        }

        // Determine shipping at ORDER level using order total (price-range first)
        $shipping_row = $this->db->get_shipping_cost( (int) $order->store_id, $order_total, substr( $order->order_date, 0, 10 ) );
        $order_shipping = $shipping_row ? (float) $shipping_row->cost_tl : 0.0;

        // YENİ EKLENEN: Sabit giderleri (Personel, Paketleme, Diğer) mağaza ayarlarına göre al
        $order_fixed_cost = 0.0;
        $fixed_costs_opt = get_option( 'hbt_fixed_costs', array() );
        if ( isset( $fixed_costs_opt[ $order->store_id ] ) ) {
            $store_fc = $fixed_costs_opt[ $order->store_id ];
            $order_fixed_cost = (float) ($store_fc['personnel'] ?? 0) + (float) ($store_fc['packaging'] ?? 0) + (float) ($store_fc['other'] ?? 0);
        }

       // Determine total commission for this order (sum of relevant transactions).
        $total_commission = $this->get_total_commission_for_order( (int) $order->store_id, (string) $order->order_number );

        $is_real_commission_found = false; 

        // YENİ: Eğer siparişe ait herhangi bir finansal işlem (transactions) varsa, 
        // bu sipariş Trendyol tarafından faturalanmış veya hakedişe düşmüş demektir. Kesinlikle gerçektir!
        $transactions = $this->db->get_transactions_for_order( (int) $order->store_id, (string) $order->order_number );
        if ( count($transactions) > 0 ) {
            $is_real_commission_found = true;
        }

        if ( $total_commission <= 0.0 ) {
            foreach ( $items as $it ) {
                if ( isset( $it->commission_amount ) ) {
                    $total_commission += (float) $it->commission_amount;
                }
                
                // Eğer manuel atadığımız %19 dışında bir oran API'den kalemlere işlendiyse gerçektir
                if ( isset( $it->commission_rate ) && (float) $it->commission_rate > 0 && round( (float) $it->commission_rate, 2 ) !== 19.00 ) {
                    $is_real_commission_found = true;
                }
            }
            
            // Kalemlerde oran yazmıyorsa bile, toplanan komisyon siparişin tam %19'u DEĞİLSE bu da gerçek komisyondur.
            if ( $order_total > 0 && $total_commission > 0 ) {
                $calc_rate = round( ($total_commission / $order_total) * 100, 2 );
                if ( $calc_rate !== 19.00 ) {
                    $is_real_commission_found = true;
                }
            }
        } else {
            // Finansal tablodan (faturadan) toplam komisyon > 0 döndüyse zaten gerçektir
            $is_real_commission_found = true;
        }
        $distributed_shipping_total   = 0.0;
        $distributed_commission_total = 0.0;
        $distributed_fixed_total      = 0.0; // YENİ
        $num_items                    = count( $items );
        $idx                          = 0;

        foreach ( $items as $item ) {
            $idx++;
            $raw = $item_raws[ $item->id ] ?? 0.0;
            $rev = $raw * $scale;

            if ( $order_total > 0.00001 ) {
                $share = $rev / $order_total;
            } else {
                $q = max( 1, (int) $item->quantity );
                $share = $total_qty > 0 ? ( $q / $total_qty ) : ( 1.0 / $num_items );
            }

            if ( $idx < $num_items ) {
                $item_shipping    = round( $order_shipping * $share, 2 );
                $item_commission  = round( $total_commission * $share, 2 );
                $item_fixed       = round( $order_fixed_cost * $share, 2 ); // YENİ
                $distributed_shipping_total   += $item_shipping;
                $distributed_commission_total += $item_commission;
                $distributed_fixed_total      += $item_fixed; // YENİ
            } else {
                $item_shipping   = round( $order_shipping - $distributed_shipping_total, 2 );
                $item_commission = round( $total_commission - $distributed_commission_total, 2 );
                $item_fixed      = round( $order_fixed_cost - $distributed_fixed_total, 2 ); // YENİ
            }

            // Fonksiyona parametre olarak item_fixed değerini de gönderiyoruz
            $this->calculate_item_profit_allocated( $item, $usd_rate, (int) $order->store_id, $order->order_date, $order->order_number, $item_commission, $item_shipping, $rev, $item_fixed );
        }

        // Aggregate totals back to order.
        $this->aggregate_order_totals( $order_id );

      // Update usd_rate and mark as calculated.
        global $wpdb;
        
        // KESİN KONTROL: Bu siparişe ait herhangi bir Trendyol Finansal İşlemi (Fatura/Hakediş) var mı?
        $tx_exists = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(id) FROM {$wpdb->prefix}hbt_transactions WHERE order_number = %s",
            $order->order_number
        ) );

        $update_data = array(
            'usd_rate'       => $usd_rate,
            'usd_rate_type'  => $rate_type,
            'is_calculated'  => 1,
            'calculated_at'  => current_time( 'mysql' ),
        );

        // 1. Eğer herhangi bir finansal işlem varsa (Trendyol faturayı kesmiş demektir, komisyon %100 gerçektir)
        // 2. VEYA finansal fatura olmasa bile API'den çekilen komisyon oranı %19 değilse
        if ( $tx_exists > 0 ) {
            $update_data['is_comm_defaulted'] = 0;
        } elseif ( $order_total > 0 && $total_commission > 0 ) {
            $calc_rate = round( ($total_commission / $order_total) * 100, 2 );
            if ( $calc_rate !== 19.00 ) {
                $update_data['is_comm_defaulted'] = 0;
            }
        }

        $wpdb->update(
            $wpdb->prefix . 'hbt_orders',
            $update_data,
            array( 'id' => $order_id )
        );

        // Re-read to check profit.
        $updated = $this->db->get_order( $order_id );
        if ( $updated && (float) $updated->net_profit < 0 ) {
            $margin = (float) $updated->profit_margin;
            $type   = ( $margin < -10 ) ? 'critical_loss' : 'loss_alert';

            $this->notification_manager->create_notification(
                $type,
                __( 'Zararlı Sipariş', 'hbt-trendyol-profit-tracker' ),
                sprintf(
                    /* translators: 1: order number, 2: profit amount */
                    __( 'Sipariş #%1$s için net kâr/zarar: %2$s TL', 'hbt-trendyol-profit-tracker' ),
                    esc_html( $updated->order_number ),
                    number_format( (float) $updated->net_profit, 2 )
                ),
                $order_id
            );
        }

        return true;
    }

    /**
     * Calculate profit for a single order item using precomputed allocated shipping & commission.
     *
     * Note: $allocated_comm and $allocated_ship are the amounts allocated from the order totals.
     * $scaled_revenue is this item's revenue after applying order-level discount scaling (customer-paid share).
     *
     * @param object $item             Order item object.
     * @param float  $usd_rate         USD/TRY rate.
     * @param int    $store_id         Store ID.
     * @param string $order_date       Order date (Y-m-d H:i:s).
     * @param string $order_number     Order number.
     * @param float  $allocated_comm   Commission allocated to this item (TL).
     * @param float  $allocated_ship   Shipping cost allocated to this item (TL).
     * @param float  $scaled_revenue   Revenue assigned to this item (TL) after order-level scaling.
     * @param float  $allocated_fixed  Fixed costs allocated to this item (TL).
     */
    public function calculate_item_profit_allocated(
        object $item,
        float $usd_rate,
        int $store_id,
        string $order_date,
        string $order_number,
        float $allocated_comm,
        float $allocated_ship,
        float $scaled_revenue,
        float $allocated_fixed = 0.0 // YENİ EKLENEN
    ): void {
        $quantity = max( 1, (int) $item->quantity );

        // Revenue (per item line) — use scaled_revenue (already accounts for order-level discounts)
        $gelir = $scaled_revenue;

        // Product cost.
        $product_cost = $this->db->get_product_cost_by_barcode( $store_id, (string) $item->barcode );
        if ( $product_cost ) {
            $cost_usd      = (float) $product_cost->cost_usd;
            $cost_tl       = $cost_usd * $usd_rate;
            $total_cost_tl = $cost_tl * $quantity;
            $has_cost_data = 1;
        } else {
            $cost_usd      = 0.0;
            $cost_tl       = 0.0;
            $total_cost_tl = 0.0;
            $has_cost_data = 0;

            $this->notification_manager->create_notification(
                'cost_missing',
                __( 'Maliyet Eksik', 'hbt-trendyol-profit-tracker' ),
                sprintf(
                    /* translators: %s: barcode */
                    __( 'Barkod için maliyet girilmemiş: %s', 'hbt-trendyol-profit-tracker' ),
                    esc_html( (string) $item->barcode )
                ),
                (int) $item->id
            );
        }

        // Assigned commission and shipping are passed in as parameters (already allocated).
        $commission_amount = max( 0.0, (float) $allocated_comm );
        $shipping_cost     = max( 0.0, (float) $allocated_ship );

        // Other expenses. (Sabit giderleri buraya işliyoruz)
        $other_expenses = max( 0.0, (float) $allocated_fixed );

        // Net profit.
        $net_profit    = $gelir - $total_cost_tl - $commission_amount - $shipping_cost - $other_expenses;
        $profit_margin = ( $gelir > 0 ) ? ( $net_profit / $gelir ) * 100 : 0.0;

        // Persist item updates.
        $this->db->save_order_item(
            array(
                'id'                => (int) $item->id,
                // keep vat fields untouched if present; price_excl_vat/vat_amount not recomputed here
                'cost_usd'          => $cost_usd,
                'cost_tl'           => round( $cost_tl, 2 ),
                'total_cost_tl'     => round( $total_cost_tl, 2 ),
                'commission_amount' => round( $commission_amount, 2 ),
                'shipping_cost'     => round( $shipping_cost, 2 ),
                'other_expenses'    => round( $other_expenses, 2 ),
                'net_profit'        => round( $net_profit, 2 ),
                'profit_margin'     => round( $profit_margin, 2 ),
                'has_cost_data'     => $has_cost_data,
                // store the scaled revenue so UI / exports can reference the paid share if needed
                'line_total'        => round( $scaled_revenue, 2 ),
            )
        );
    }

    /**
     * Compute total commission amount for an order by summing relevant financial transactions.
     *
     * @param int    $store_id     Store ID.
     * @param string $order_number Order number.
     * @return float
     */
   private function get_total_commission_for_order( int $store_id, string $order_number ): float {
        $transactions = $this->db->get_transactions_for_order( $store_id, $order_number );
        $commission   = 0.0;

        foreach ( $transactions as $tx ) {
            // YENİ EKLENEN: Trendyol Settlements API'sindeki CommissionPositive ve CommissionNegative tipleri de listeye eklendi
            if ( in_array( $tx->transaction_type, array( 'CommissionInvoice', 'CommissionInvocie', 'CommissionPositive', 'CommissionNegative' ), true ) ) {
                $commission += abs( (float) $tx->amount );
            }
        }

        return $commission;
    }

    /**
     * Aggregate order item totals up to the order level.
     *
     * @param int $order_id Order DB ID.
     */
    public function aggregate_order_totals( int $order_id ): void {
        global $wpdb;

        $totals = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    SUM(cost_usd * quantity) AS total_cost_usd,
                    SUM(total_cost_tl)        AS total_cost_tl,
                    SUM(commission_amount)    AS total_commission,
                    SUM(shipping_cost)        AS total_shipping,
                    SUM(other_expenses)       AS total_other_exp,
                    SUM(net_profit)           AS net_profit,
                    SUM(line_total)           AS total_sales
                FROM {$wpdb->prefix}hbt_order_items
                WHERE order_id = %d",
                $order_id
            )
        );

        if ( ! $totals ) {
            return;
        }

        $order = $this->db->get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        // Prefer authoritative order.total_price as sales total; but store the aggregated line_total as reference.
        $total_price   = (float) $order->total_price;
        $net_profit    = (float) $totals->net_profit;
        $profit_margin = ( $total_price > 0 ) ? ( $net_profit / $total_price ) * 100 : 0.0;

        $wpdb->update(
            $wpdb->prefix . 'hbt_orders',
            array(
                'total_cost_usd'    => round( (float) $totals->total_cost_usd, 4 ),
                'total_cost_tl'     => round( (float) $totals->total_cost_tl, 2 ),
                'total_commission'  => round( (float) $totals->total_commission, 2 ),
                'total_shipping'    => round( (float) $totals->total_shipping, 2 ),
                'total_other_exp'   => round( (float) $totals->total_other_exp, 2 ),
                'net_profit'        => round( $net_profit, 2 ),
                'profit_margin'     => round( $profit_margin, 2 ),
                // keep total_price (order.total_price) as source of truth for sales amount
            ),
            array( 'id' => $order_id )
        );
    }

    /**
     * Recalculate all uncalculated orders, optionally filtered by store.
     *
     * Processed in batches of 100.
     *
     * @param int|null $store_id Optional store ID filter.
     */
    public function recalculate_all( ?int $store_id = null ): void {
        global $wpdb;

        $where = 'is_calculated = 0';
        $args  = array();

        if ( $store_id !== null ) {
            $where .= ' AND store_id = %d';
            $args[] = $store_id;
        }

        $offset = 0;

        do {
            $limit = 100;
            $sql   = "SELECT id FROM {$wpdb->prefix}hbt_orders WHERE {$where} ORDER BY id LIMIT {$limit} OFFSET {$offset}";

            if ( $args ) {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $ids = $wpdb->get_col( $wpdb->prepare( $sql, ...$args ) );
            } else {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $ids = $wpdb->get_col( $sql );
            }

            foreach ( $ids as $id ) {
                $this->calculate_order( (int) $id );
            }

            $offset += $limit;
        } while ( count( $ids ) === $limit );
    }
}