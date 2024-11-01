<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class VIWEBPOS_Transactions extends WP_List_Table {
	protected static $instance = null;
	protected static $format_date, $woo_payments;

	public static function get_instance( $new = false ) {
		if ( $new || null === self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public static function get_format_date() {
		if ( self::$format_date ) {
			return self::$format_date;
		}

		return self::$format_date = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
	}

	public static function payment_gateways() {
		if ( self::$woo_payments ) {
			return self::$woo_payments;
		}

		return self::$woo_payments = WC()->payment_gateways->payment_gateways();
	}

	function get_columns() {
		$columns = array(
			'cb'         => '<input type="checkbox" />',
			'id'         => esc_html__( 'ID', 'webpos-point-of-sale-for-woocommerce' ),
			'cashier_id' => esc_html__( 'Cashier', 'webpos-point-of-sale-for-woocommerce' ),
			'order_id'   => esc_html__( 'Order', 'webpos-point-of-sale-for-woocommerce' ),
			'in'         => esc_html__( 'In', 'webpos-point-of-sale-for-woocommerce' ),
			'out'        => esc_html__( 'Out', 'webpos-point-of-sale-for-woocommerce' ),
			'method'     => esc_html__( 'Method', 'webpos-point-of-sale-for-woocommerce' ),
			'note'       => esc_html__( 'Note', 'webpos-point-of-sale-for-woocommerce' ),
			'create_at'  => esc_html__( 'Create at', 'webpos-point-of-sale-for-woocommerce' ),
		);

		return $columns;
	}

	/**
	 * Column cb.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="trans_id[]" value="%s" />', $item['id'] ?? 0 );
	}

	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'id':
				return sprintf( '<span>%s</span><div class="row-actions"><span class="delete">
						<a class="submitdelete" href="admin.php?page=viwebpos-transactions&amp;action=delete&amp;trans_id=%s">%s</a></span></div>',
					esc_attr( $item[ $column_name ] ), esc_attr( $item[ $column_name ] ), esc_html__( 'Delete', 'webpos-point-of-sale-for-woocommerce' ) );
			case 'order_id':
				if ( ! empty( $item[ $column_name ] ) ) {
					return sprintf( '<a target="_blank" href="%s">#%s</a>', esc_url( get_edit_post_link( $item[ $column_name ] ) ), wp_kses_post( $item[ $column_name ] ) );
				}

				return '-';
			case 'note':
				return ! empty( $item[ $column_name ] ) ? $item[ $column_name ] : '-';
			case 'in':
			case 'out':
				$currency = $item['currency'] ?? '';
				$temp     = $item[ $column_name ] ?? 0;
				$amount   = VIWEBPOS_Plugins_Curcy::curcy_wc_price( $temp, $currency ? array( 'currency' => $currency ) : array() );
				if ( $temp && $column_name === 'out' ) {
					$amount = '-' . $amount;
				}

				return $amount;
			case 'cashier_id':
				$user = get_user_by( 'id', $item[ $column_name ] );

				return $user ? sprintf( '<a target="_blank" href="%s">%s</a>', esc_url(
					add_query_arg(
						'wp_http_referer',
						urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ),
						get_edit_user_link( $user->ID )
					)
				), wp_kses_post( $user->display_name ) ) : '-';
			case 'method':
				$woo_payments = self::payment_gateways();
				$html         = '';
				$method       = $item[ $column_name ] ?? '';
				if ( $method === 'viwebpos_manual' ) {
					$html = esc_html__( 'Manual', 'webpos-point-of-sale-for-woocommerce' );
				} elseif ( array_key_exists( $method, $woo_payments ) ) {
					$html = $woo_payments[ $method ]->method_title ?? $woo_payments[ $method ]->title ?? '';
				}

				return $html ?: '-';
			case 'create_at':
				return ! empty( $item[ $column_name ] ) ? date_i18n( self::get_format_date(), strtotime( $item[ $column_name ] ) ) : '-';
		}
	}

	/**
	 * Get bulk actions.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array(
			'delete' => esc_html__( 'Delete', 'webpos-point-of-sale-for-woocommerce' ),
		);
	}

	protected function display_tablenav( $which ) {
		if ( 'top' === $which ) {
			printf( '<div class="alignright actions">' );
			$this->search_box( esc_html__( 'Search Transactions', 'webpos-point-of-sale-for-woocommerce' ), 'viwebpos-transaction-search' );
			printf( '</div>' );
		}
		printf( '<div class="tablenav %s">', esc_attr( $which ) );
		wp_nonce_field( '_viwebpos_transaction_tablenav_action', '_viwebpos_transaction_tablenav_nonce' );
		if ( $this->has_items() ) {
			printf( '<div class="alignleft actions bulkactions">' );
			$this->bulk_actions( $which );
			printf( '</div>' );
		}
		$this->extra_tablenav( $which );
		$this->pagination( $which );
		printf( '<br class="clear" /></div>' );
	}

	public function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		if ( isset( $_REQUEST['viwebpos_admin_nonce'] ) && ! wp_verify_nonce( wc_clean( wp_unslash( $_REQUEST['viwebpos_admin_nonce'] ) ), 'viwebpos_admin_nonce' ) ) {
			return;
		}
		$cashier = isset( $_REQUEST['cashier'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['cashier'] ) ) : '';
		$from    = isset( $_REQUEST['from'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['from'] ) ) : '';
		$to      = isset( $_REQUEST['to'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['to'] ) ) : '';
		$users   = get_users( array(
			'role__in' => array( 'cashier', 'administrator' ),
			'orderby'  => 'user_nicename',
			'order'    => 'ASC'
		) );
		printf( '<div class="alignleft actions">' );
		printf( '<select name="cashier" class="first viwebpos-filter-by-cashier">' );
		printf( '<option value="">%s</option>', esc_html__( 'Select a cashier', 'webpos-point-of-sale-for-woocommerce' ) );
		foreach ( $users as $user ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $user_id = $user->ID ), selected( $user_id, $cashier ), wp_kses_post( $user->display_name ) );
		}
		printf( '</select><label for="viwebpos-transaction-create-from">%s</label>', esc_html__( 'From: ', 'webpos-point-of-sale-for-woocommerce' ) );
		printf( '<input name="from" id="viwebpos-transaction-create-from" type="date" value="%s">', esc_attr( $from ) );
		printf( '<label for="viwebpos-transaction-create-to">%s</label>', esc_html__( 'To: ', 'webpos-point-of-sale-for-woocommerce' ) );
		printf( '<input name="to" id="viwebpos-transaction-create-to" type="date" value="%s">', esc_attr( $to ) );
		submit_button( esc_html__( 'Filter', 'webpos-point-of-sale-for-woocommerce' ), '', 'filter_action', false, array( 'id' => 'viwebpos-transaction-post-query-submit' ) );
		printf( '</div>' );
	}

	public function prepare_items() {
		if ( isset( $_REQUEST['viwebpos_admin_nonce'] ) && ! wp_verify_nonce( wc_clean( wp_unslash( $_REQUEST['viwebpos_admin_nonce'] ) ), 'viwebpos_admin_nonce' ) ) {
			return;
		}
		$per_page     = $this->get_items_per_page( 'viwebpos_transactions_per_page', 30 );
		$current_page = $this->get_pagenum();
		$action       = $this->current_action();
		$where        = array();
		if ( $action === 'delete' ) {
			$transactions = isset( $_REQUEST['trans_id'] ) ? array_map( 'absint', (array) villatheme_sanitize_fields( $_REQUEST['trans_id'] ) ) : array(); // WPCS: input var okay, CSRF ok.
			if ( ! current_user_can( apply_filters( 'viwebpos_change_role', 'manage_woocommerce' ) ) ) {
				wp_die( esc_html__( 'You do not have permission to delete transactions', 'webpos-point-of-sale-for-woocommerce' ) );
			}
			if ( ! empty( $transactions ) ) {
				foreach ( $transactions as $id ) {
					VIWEBPOS_Transactions_Table::delete( $id );
				}
			}
		} else {
			$search_key = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
			$cashier    = isset( $_REQUEST['cashier'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['cashier'] ) ) : '';
			$from       = isset( $_REQUEST['from'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['from'] ) ) : '';
			$to         = isset( $_REQUEST['to'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['to'] ) ) : '';
			$to         = $to ? date( 'Y-m-d', strtotime( $to ) + 24 * 3600 ) : '';// phpcs:ignore 	WordPress.DateTime.RestrictedFunctions.date_date
			if ( $search_key ) {
				$where[] = "(order_id LIKE '%{$search_key}%' OR method LIKE '%{$search_key}%')";
			}
			if ( $cashier ) {
				$where[] = "cashier_id = '{$cashier}'";
			}
			if ( $from ) {
				$where[] = "create_at >= '{$from}'";
			}
			if ( $to ) {
				$where[] = "create_at < '{$to}'";
			}
			$where = implode( ' AND ', $where );
		}
		$args        = [
			'where'  => $where,
			'limit'  => $per_page,
			'offset' => ( $current_page - 1 ) * $per_page
		];
		$transctions = VIWEBPOS_Transactions_Table::get_transactions( $args ) ?? array();
		$this->items = $transctions;
		if ( ! empty( $this->items ) ) {
			$total_items = VIWEBPOS_Transactions_Table::count_records();
			$this->set_pagination_args( array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page )
			) );
		}
		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = array();
		$this->_column_headers = array( $columns, $hidden, $sortable );
	}
}