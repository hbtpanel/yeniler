<?php
/**
 * Export manager class.
 *
 * @package HBT_Trendyol_Profit_Tracker
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class HBT_Export_Manager
 *
 * Handles data export in CSV, Excel, and PDF formats.
 */
class HBT_Export_Manager {

	/**
	 * Database instance.
	 *
	 * @var HBT_Database
	 */
	private HBT_Database $db;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->db = HBT_Database::instance();
	}

	// -------------------------------------------------------------------------
	// Export methods
	// -------------------------------------------------------------------------

	/**
	 * Export data as a CSV file (triggers download).
	 *
	 * @param array  $data     Rows of data to export.
	 * @param string $filename Base filename (without extension).
	 */
	public function export_csv( array $data, string $filename ): void {
		if ( headers_sent() ) {
			return;
		}

		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '.csv"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );
		if ( $output === false ) {
			return;
		}

		// UTF-8 BOM.
		fwrite( $output, "\xEF\xBB\xBF" );

		foreach ( $data as $row ) {
			fputcsv( $output, array_values( (array) $row ) );
		}

		fclose( $output );
		exit;
	}

	/**
	 * Export data as an Excel (.xlsx) file.
	 *
	 * Requires PhpSpreadsheet via Composer.
	 * Falls back to CSV if the library is not available.
	 *
	 * @param array  $data     Rows of data to export.
	 * @param string $filename Base filename (without extension).
	 */
	public function export_excel( array $data, string $filename ): void {
		if ( ! class_exists( '\PhpOffice\PhpSpreadsheet\Spreadsheet' ) ) {
			$this->add_fallback_notice( 'Excel (PhpSpreadsheet)' );
			$this->export_csv( $data, $filename );
			return;
		}

		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$sheet       = $spreadsheet->getActiveSheet();

		if ( empty( $data ) ) {
			$this->send_empty_xlsx( $spreadsheet, $filename );
			return;
		}

		// Header row.
		$headers = array_keys( (array) $data[0] );
		$col     = 1;
		foreach ( $headers as $header ) {
			$sheet->setCellValueByColumnAndRow( $col, 1, $header );
			$sheet->getStyleByColumnAndRow( $col, 1 )->getFont()->setBold( true );
			$sheet->getStyleByColumnAndRow( $col, 1 )
				->getFill()
				->setFillType( \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID )
				->getStartColor()->setARGB( 'FF2196F3' );
			$col++;
		}

		// Data rows.
		$row_num = 2;
		foreach ( $data as $row ) {
			$values = array_values( (array) $row );
			$col    = 1;

			foreach ( $values as $value ) {
				$sheet->setCellValueByColumnAndRow( $col, $row_num, $value );
				$col++;
			}

			// Colour coding based on net_profit column if present.
			$profit_col = array_search( 'net_profit', $headers, true );
			if ( $profit_col !== false && isset( $values[ $profit_col ] ) ) {
				$profit = (float) $values[ $profit_col ];
				$colour = $profit >= 0 ? 'FFE8F5E9' : 'FFFFEBEE'; // light green / light red.

				for ( $c = 1; $c <= count( $headers ); $c++ ) {
					$sheet->getStyleByColumnAndRow( $c, $row_num )
						->getFill()
						->setFillType( \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID )
						->getStartColor()->setARGB( $colour );
				}
			}

			$row_num++;
		}

		// Auto-size columns.
		foreach ( range( 1, count( $headers ) ) as $c ) {
			$sheet->getColumnDimensionByColumn( $c )->setAutoSize( true );
		}

		$this->send_empty_xlsx( $spreadsheet, $filename );
	}

	/**
	 * Export data as a PDF file.
	 *
	 * Requires TCPDF via Composer.
	 * Falls back to CSV if the library is not available.
	 *
	 * @param array  $data     Rows of data to export.
	 * @param string $filename Base filename (without extension).
	 * @param array  $meta     Optional metadata: title, date_from, date_to, store_name.
	 */
	public function export_pdf( array $data, string $filename, array $meta = array() ): void {
		if ( ! class_exists( 'TCPDF' ) ) {
			$this->add_fallback_notice( 'PDF (TCPDF)' );
			$this->export_csv( $data, $filename );
			return;
		}

		$pdf = new TCPDF( PDF_PAGE_ORIENTATION, PDF_UNIT, 'A4', true, 'UTF-8', false );
		$pdf->SetCreator( 'HBT Trendyol Profit Tracker' );
		$pdf->SetAuthor( 'HBT Panel' );
		$pdf->SetTitle( $meta['title'] ?? $filename );
		$pdf->SetMargins( 10, 15, 10 );
		$pdf->AddPage( 'L' ); // Landscape.
		$pdf->SetFont( 'dejavusans', '', 8 );

		// Title.
		$pdf->SetFont( 'dejavusans', 'B', 12 );
		$pdf->Cell( 0, 10, esc_html( $meta['title'] ?? $filename ), 0, 1, 'C' );

		if ( ! empty( $meta['date_from'] ) && ! empty( $meta['date_to'] ) ) {
			$pdf->SetFont( 'dejavusans', '', 9 );
			$pdf->Cell( 0, 6, esc_html( $meta['date_from'] ) . ' – ' . esc_html( $meta['date_to'] ), 0, 1, 'C' );
		}

		if ( ! empty( $meta['store_name'] ) ) {
			$pdf->Cell( 0, 6, esc_html( $meta['store_name'] ), 0, 1, 'C' );
		}

		$pdf->Ln( 4 );

		if ( empty( $data ) ) {
			$pdf->Cell( 0, 10, __( 'No data available.', 'hbt-trendyol-profit-tracker' ), 0, 1, 'C' );
		} else {
			$headers    = array_keys( (array) $data[0] );
			$col_width  = 270 / count( $headers );

			$pdf->SetFont( 'dejavusans', 'B', 7 );
			$pdf->SetFillColor( 33, 150, 243 );
			$pdf->SetTextColor( 255 );
			foreach ( $headers as $header ) {
				$pdf->Cell( $col_width, 6, esc_html( $header ), 1, 0, 'C', true );
			}
			$pdf->Ln();
			$pdf->SetTextColor( 0 );
			$pdf->SetFont( 'dejavusans', '', 6 );

			$fill = false;
			foreach ( $data as $row ) {
				$values = array_values( (array) $row );
				$pdf->SetFillColor( $fill ? 240 : 255, $fill ? 240 : 255, $fill ? 240 : 255 );
				foreach ( $values as $val ) {
					$pdf->Cell( $col_width, 5, esc_html( (string) $val ), 1, 0, 'L', $fill );
				}
				$pdf->Ln();
				$fill = ! $fill;
			}
		}

		// Footer totals.
		if ( ! empty( $meta['summary'] ) ) {
			$pdf->Ln( 4 );
			$pdf->SetFont( 'dejavusans', 'B', 9 );
			foreach ( $meta['summary'] as $label => $value ) {
				$pdf->Cell( 60, 7, esc_html( $label ) . ':', 0, 0, 'R' );
				$pdf->Cell( 40, 7, esc_html( (string) $value ), 0, 1, 'L' );
			}
		}

		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '.pdf"' );
		echo $pdf->Output( sanitize_file_name( $filename ) . '.pdf', 'S' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	// -------------------------------------------------------------------------
	// Data preparation
	// -------------------------------------------------------------------------

	/**
	 * Prepare export data with all required columns.
	 *
	 * @param  array $filters Filters: store_id, date_from, date_to, status, profitable.
	 * @return array
	 */
	public function prepare_export_data( array $filters = array() ): array {
		global $wpdb;

		// Fixed starting condition (was incorrectly 'o.1=1')
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $filters['store_id'] ) ) {
			$where[]  = 'o.store_id = %d';
			$params[] = absint( $filters['store_id'] );
		}
		if ( ! empty( $filters['date_from'] ) ) {
			$where[]  = 'o.order_date >= %s';
			$params[] = sanitize_text_field( $filters['date_from'] ) . ' 00:00:00';
		}
		if ( ! empty( $filters['date_to'] ) ) {
			$where[]  = 'o.order_date <= %s';
			$params[] = sanitize_text_field( $filters['date_to'] ) . ' 23:59:59';
		}
		if ( ! empty( $filters['status'] ) ) {
			$where[]  = 'o.status = %s';
			$params[] = sanitize_text_field( $filters['status'] );
		}

		$where_sql = implode( ' AND ', $where );

		$sql = "SELECT
			o.order_number        AS '" . esc_sql( __( 'Sipariş No', 'hbt-trendyol-profit-tracker' ) ) . "',
			o.order_date          AS '" . esc_sql( __( 'Tarih', 'hbt-trendyol-profit-tracker' ) ) . "',
			s.store_name          AS '" . esc_sql( __( 'Mağaza', 'hbt-trendyol-profit-tracker' ) ) . "',
			oi.product_name       AS '" . esc_sql( __( 'Ürün', 'hbt-trendyol-profit-tracker' ) ) . "',
			oi.barcode            AS '" . esc_sql( __( 'Barkod', 'hbt-trendyol-profit-tracker' ) ) . "',
			oi.quantity           AS '" . esc_sql( __( 'Adet', 'hbt-trendyol-profit-tracker' ) ) . "',
			oi.line_total         AS '" . esc_sql( __( 'Satış Fiyatı (TL)', 'hbt-trendyol-profit-tracker' ) ) . "',
			oi.vat_amount         AS '" . esc_sql( __( 'KDV (TL)', 'hbt-trendyol-profit-tracker' ) ) . "',
			oi.price_excl_vat     AS '" . esc_sql( __( 'KDV Hariç (TL)', 'hbt-trendyol-profit-tracker' ) ) . "',
			oi.cost_usd           AS '" . esc_sql( __( 'Maliyet (USD)', 'hbt-trendyol-profit-tracker' ) ) . "',
			o.usd_rate            AS '" . esc_sql( __( 'Kur (USD/TL)', 'hbt-trendyol-profit-tracker' ) ) . "',
			o.usd_rate_type       AS '" . esc_sql( __( 'Kur Tipi', 'hbt-trendyol-profit-tracker' ) ) . "',
			oi.total_cost_tl      AS '" . esc_sql( __( 'Maliyet (TL)', 'hbt-trendyol-profit-tracker' ) ) . "',
			oi.commission_amount  AS '" . esc_sql( __( 'Komisyon (TL)', 'hbt-trendyol-profit-tracker' ) ) . "',
			oi.shipping_cost      AS '" . esc_sql( __( 'Kargo (TL)', 'hbt-trendyol-profit-tracker' ) ) . "',
			oi.other_expenses     AS '" . esc_sql( __( 'Diğer Giderler (TL)', 'hbt-trendyol-profit-tracker' ) ) . "',
			oi.net_profit         AS 'net_profit',
			oi.profit_margin      AS '" . esc_sql( __( 'Kâr Marjı (%)', 'hbt-trendyol-profit-tracker' ) ) . "'
		FROM {$wpdb->prefix}hbt_orders o
		INNER JOIN {$wpdb->prefix}hbt_order_items oi ON oi.order_id = o.id
		LEFT JOIN {$wpdb->prefix}hbt_stores s ON s.id = o.store_id
		WHERE {$where_sql}
		ORDER BY o.order_date DESC";

		if ( $params ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results( $sql, ARRAY_A );
		}

		return $rows ?: array();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Add a fallback admin notice when a library is missing.
	 *
	 * @param string $library Library name.
	 */
	private function add_fallback_notice( string $library ): void {
		add_action(
			'admin_notices',
			static function () use ( $library ) {
				echo '<div class="notice notice-warning is-dismissible"><p>' .
					sprintf(
						/* translators: %s: library name */
						esc_html__( '%s kütüphanesi bulunamadı, CSV formatına geçildi.', 'hbt-trendyol-profit-tracker' ),
						esc_html( $library )
					) .
					'</p></div>';
			}
		);
	}

	/**
	 * Send Excel spreadsheet as download.
	 *
	 * @param \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet Spreadsheet object.
	 * @param string                                $filename    Base filename.
	 */
	private function send_empty_xlsx( \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet, string $filename ): void {
		$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx( $spreadsheet );

		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '.xlsx"' );
		header( 'Cache-Control: max-age=0' );

		$writer->save( 'php://output' );
		exit;
	}
}