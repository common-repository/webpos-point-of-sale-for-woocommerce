<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VIWEBPOS_Frontend_Transactions {
	public static $cache = array();
	protected static $settings;

	public function __construct() {
		self::$settings = VIWEBPOS_DATA::get_instance();
	}

	public static function viwebpos_get_transactions_data() {
		check_ajax_referer('viwebpos_nonce','viwebpos_nonce');
		if ( ! current_user_can( apply_filters( 'viwebpos_frontend_role', 'cashier' ) ) ) {
			wp_die();
		}
		$result = array(
			'data' => array(),
			'page' => ''
		);
		$limit  = isset( $_POST['limit'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['limit'] ) ) : 50;
		$limit  = $limit ?: 50;
		$page   = isset( $_POST['page'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['page'] ) ) : 1;
		$from   = date( 'Y-m-d' );// phpcs:ignore 	WordPress.DateTime.RestrictedFunctions.date_date
		$where  = array();
		if ( $from ) {
			$where[] = "create_at >= '{$from}'";
		}
		$where = implode( ' AND ', $where );
		$args  = [
			'where'  => $where,
			'limit'  => $limit,
			'offset' => ( $page - 1 ) * $limit
		];
		if ( $page === 1 ) {
			$total_transactions    = VIWEBPOS_Transactions_Table::count_records( array( 'where' => $where ) );
			$total_pages           = ceil( $total_transactions / $limit );
			$result['total_pages'] = $total_pages;
			if ( ! $total_pages ) {
				wp_send_json( $result );
				wp_die();
			}
		}
		$transctions = (array) (VIWEBPOS_Transactions_Table::get_transactions( $args ) ?? array());
		foreach ( $transctions as $transaction ) {
			$temp             = array(
				'id'         => (int) $transaction['id'] ?? 0,
				'cashier_id' => (int) $transaction['cashier_id'] ?? 0,
				'order_id'   => (int) $transaction['order_id'] ?? 0,
				'in'         => $transaction['in'] ?? 0,
				'out'        => $transaction['out'] ?? 0,
				'method'     => $transaction['method'] ?? '',
				'currency'     => $transaction['currency'] ?? '',
				'currency_symbol'     => $transaction['currency_symbol'] ?? '',
				'note'       => $transaction['note'] ?? '',
				'create_at'  => $transaction['create_at'] ?? $from,
				'date'       => explode(' ',$transaction['create_at'])[0],
			);
			$result['data'][] = $temp;
		}
		$result['page'] = $page + 1;
		wp_send_json( $result );
	}

	public static function viwebpos_create_transaction() {
		check_ajax_referer('viwebpos_nonce','viwebpos_nonce');
		if ( ! current_user_can( apply_filters( 'viwebpos_frontend_role', 'cashier' ) ) ) {
			wp_die();
		}
		$result      = array(
			'status'      => 'error',
			'message'     => '',
			'data'        => array(),
			'data_prefix' => '',
		);
		$transaction = isset( $_POST['transaction'] ) ? wc_clean( $_POST['transaction'] ) : '';
		if ( ! $transaction || empty( $transaction['cashier_id'] || ( empty( $transaction['in'] ) && empty( $transaction['out'] ) ) ) ) {
			$result['message'] = esc_html__( 'No transaction data.', 'webpos-point-of-sale-for-woocommerce' );
			wp_send_json( $result );
		}
		$arg                       = array(
			'cashier_id' => '',
			'order_id'   => '',
			'in'         => 0,
			'out'        => 0,
			'method'     => 'viwebpos_manual',
			'currency'     => '',
			'currency_symbol'     => '',
			'note'       => '',
			'create_at'  => date( 'Y-m-d H:i:s' ),// phpcs:ignore 	WordPress.DateTime.RestrictedFunctions.date_date
		);
		$transaction               = wp_parse_args( $transaction, $arg );
		$transaction['cashier_id'] = intval( $transaction['cashier_id'] );
		if (empty($transaction['currency'])){
			$transaction['currency'] = get_woocommerce_currency_symbol();
		}
		if (empty($transaction['currency_symbol'])){
			$transaction['currency_symbol'] = get_woocommerce_currency();
		}
		$transaction_id            = VIWEBPOS_Transactions_Table::insert( $transaction );
		if ( is_wp_error( $transaction_id ) ) {
			$result['message'] = $transaction_id->get_error_message();
			wp_send_json( $result );
		}
		if ( ! $transaction_id ) {
			$transaction['id'] = $transaction_id;
			$result['data']    = array( $transaction );
			$result['message'] = esc_html__( 'Can\'t create this transaction.', 'webpos-point-of-sale-for-woocommerce' );
			wp_send_json( $result );
		}
		$transaction['id']     = $transaction_id;
		$transaction['date']   = date( 'Y-m-d' );// phpcs:ignore 	WordPress.DateTime.RestrictedFunctions.date_date
		$result['status']      = 'success';
		$result['message']     = esc_html__( 'A transaction created successfully.', 'webpos-point-of-sale-for-woocommerce' );
		$result['data']        = array( $transaction );
		$result['data_prefix'] = self::$settings::get_data_prefix( 'transactions' );
		wp_send_json( $result );
	}
}