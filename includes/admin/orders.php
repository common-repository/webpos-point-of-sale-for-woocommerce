<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VIWEBPOS_Admin_Orders {
	protected $settings;

	public function __construct() {
		$this->settings = VIWEBPOS_DATA::get_instance();
		if ( ! $this->settings->get_params( 'enable' ) ) {
			return;
		}
		//add a new column to the table
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_new_column' ) );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_new_column' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'column_callback' ), 10, 2 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'column_callback' ), 10, 2 );
		//add a new select to the tablenav
		add_action( 'restrict_manage_posts', array( $this, 'restrict_manage_posts' ) );
		add_action( 'woocommerce_order_list_table_restrict_manage_orders', array( $this, 'restrict_manage_posts' ) );
		add_action( 'woocommerce_orders_table_query_clauses', array( $this, 'add_orders_query' ) );
		add_filter( 'posts_where', array( $this, 'posts_where' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'woocommerce_admin_order_totals_after_total', array( $this, 'get_order_cashier' ), 10, 1 );
		add_action( 'before_delete_post', array( $this, 'before_delete_order' ) );
		add_action( 'woocommerce_delete_order', array( $this, 'before_delete_order' ) );
		add_filter( 'woocommerce_order_item_display_meta_key', array( $this, 'order_item_display_meta_key' ), 10, 3 );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'reset_payment_title' ) );
	}

	public function add_new_column( $cols ) {
		$cols['viwebpos_cashier'] = esc_html__( 'POS Cashier', 'webpos-point-of-sale-for-woocommerce' );

		return $cols;
	}

	public function column_callback( $column_name, $order_id ) {
		if ( $column_name === 'viwebpos_cashier' ) {
			$order      = wc_get_order( $order_id );
			$cashier_id = $order->get_meta( 'viwebpos_cashier_id', true );
			$cashier    = get_user_by( 'id', $cashier_id );
			if ( $cashier ) {
				printf( '<a target="_blank" href="%s">%s</a>', esc_url( get_edit_profile_url( $cashier_id ) ), wp_kses_post( $cashier->display_name ) );
			}
		}
	}

	/**
	 * See if we should render search filters or not.
	 */
	public function restrict_manage_posts() {
		if ( isset( $_REQUEST['viwebpos_admin_nonce'] ) && ! wp_verify_nonce( wc_clean( wp_unslash( $_REQUEST['viwebpos_admin_nonce'] ) ), 'viwebpos_admin_nonce' ) ) {
			return;
		}
		global $typenow;
		if ( in_array( $typenow, wc_get_order_types( 'order-meta-boxes' ), true ) || ( wc_clean( wp_unslash( $_GET['page'] ?? '' ) ) === 'wc-orders' ) ) {
			$is_pos_arg = array(
				'yes' => esc_html__( 'Yes', 'webpos-point-of-sale-for-woocommerce' ),
				'no'  => esc_html__( 'No', 'webpos-point-of-sale-for-woocommerce' ),
			);
			$is_pos     = isset( $_GET['viwebpos_is_pos'] ) ? wc_clean( $_GET['viwebpos_is_pos'] ) : '';
			printf( '<select name="viwebpos_is_pos" class="viwebpos_is_pos">' );
			printf( '<option value="">%s</option>', esc_html__( 'Create from POS', 'webpos-point-of-sale-for-woocommerce' ) );
			foreach ( $is_pos_arg as $k => $v ) {
				printf( '<option value="%s" %s>%s</option>', esc_attr( $k ), selected( $k, $is_pos ), esc_html( $v ) );
			}
			printf( '</select>' );
			$cashier_id = isset( $_GET['viwebpos_cashier'] ) ? wc_clean( $_GET['viwebpos_cashier'] ) : '';
			$cashiers   = get_users( array(
				'role__in' => array( 'cashier', 'administrator' ),
				'orderby'  => 'user_nicename',
				'order'    => 'ASC'
			) );
			?>
            <select class="viwebpos-cashier-search<?php echo esc_attr( $is_pos !== 'yes' ? ' hidden' : '' ); ?>" name="viwebpos_cashier">
                <option value="" selected="selected"><?php esc_html_e( 'Filter by POS cashier', 'webpos-point-of-sale-for-woocommerce' ); ?></option>
				<?php
				foreach ( $cashiers as $cashier ) {
					printf( '<option value="%s" %s>%s</option>', esc_attr( $cashier->ID ), selected( $cashier->ID, $cashier_id ), wp_kses_post( $cashier->display_name ) );
				}
				?>
            </select>
			<?php
		}
	}

	public function add_orders_query( $args ) {
		if ( isset( $_REQUEST['viwebpos_admin_nonce'] ) && ! wp_verify_nonce( wc_clean( wp_unslash( $_REQUEST['viwebpos_admin_nonce'] ) ), 'viwebpos_admin_nonce' ) ) {
			return $args;
		}
		if ( ! isset( $_GET['page'] ) || sanitize_text_field( wp_unslash( $_GET['page'] ) ) !== 'wc-orders' || empty( $_GET['viwebpos_is_pos'] ) ) {
			return $args;
		}
		global $wpdb;
		$args['join']    .= " LEFT JOIN {$wpdb->prefix}wc_orders_meta as viwebpos_postmeta ON ( viwebpos_postmeta.order_id={$wpdb->prefix}wc_orders.id AND viwebpos_postmeta.meta_key= 'viwebpos_cashier_id')";
		$viwebpos_is_pos = isset( $_GET['viwebpos_is_pos'] ) ? sanitize_text_field( wp_unslash( $_GET['viwebpos_is_pos'] ) ) : '';
		if ( $viwebpos_is_pos === 'yes' ) {
			$args['where']    .= " AND (viwebpos_postmeta.meta_key= 'viwebpos_cashier_id' AND viwebpos_postmeta.meta_value != '')";
			$viwebpos_cashier = isset( $_GET['viwebpos_cashier'] ) ? sanitize_text_field( wp_unslash( $_GET['viwebpos_cashier'] ) ) : '';
			if ( $viwebpos_cashier ) {
				$args['join']  .= " LEFT JOIN {$wpdb->prefix}wc_orders_meta as viwebpos_postmeta1 ON ( viwebpos_postmeta1.order_id={$wpdb->prefix}wc_orders.id )";
				$args['where'] .= " AND (viwebpos_postmeta1.meta_key= 'viwebpos_cashier_id' AND viwebpos_postmeta1.meta_value = '{$viwebpos_cashier}')";
			}
		} else {
			$args['where'] .= " AND viwebpos_postmeta.order_id IS NULL";
		}

		return $args;
	}

	/**
	 *
	 * @param $where
	 * @param $wp_query WP_Query
	 *
	 * @return string
	 */
	public function posts_where( $where, $wp_query ) {
		if ( isset( $_REQUEST['viwebpos_admin_nonce'] ) && ! wp_verify_nonce( wc_clean( wp_unslash( $_REQUEST['viwebpos_admin_nonce'] ) ), 'viwebpos_admin_nonce' ) ) {
			return $where;
		}
		$post_type       = isset( $wp_query->query_vars['post_type'] ) ? $wp_query->query_vars['post_type'] : '';
		$viwebpos_is_pos = isset( $_GET['viwebpos_is_pos'] ) ? sanitize_text_field( wp_unslash( $_GET['viwebpos_is_pos'] ) ) : '';
		if ( $post_type !== 'shop_order' || ! $viwebpos_is_pos ) {
			return $where;
		}
		if ( $viwebpos_is_pos === 'yes' ) {
			$viwebpos_cashier = isset( $_GET['viwebpos_cashier'] ) ? sanitize_text_field( wp_unslash( $_GET['viwebpos_cashier'] ) ) : '';
			if ( $viwebpos_cashier ) {
				$where .= " AND (viwebpos_postmeta.meta_key= 'viwebpos_cashier_id' AND viwebpos_postmeta.meta_value = '{$viwebpos_cashier}')";
			}
		} else {
			$where .= " AND viwebpos_postmeta.post_id IS NULL";
		}
		add_filter( 'posts_join', array( $this, 'posts_join' ), 10, 2 );
		add_filter( 'posts_distinct', array( $this, 'posts_distinct' ), 10, 2 );

		return $where;
	}

	/**
	 *
	 * @param $join
	 * @param $wp_query
	 *
	 * @return string
	 */
	public function posts_join( $join, $wp_query ) {
		global $wpdb;
		$join .= " LEFT JOIN {$wpdb->prefix}postmeta as viwebpos_postmeta ON ( viwebpos_postmeta.post_id=$wpdb->posts.ID  AND viwebpos_postmeta.meta_key= 'viwebpos_cashier_id' )";

		return $join;
	}

	/**
	 * @param $join
	 * @param $wp_query
	 *
	 * @return string
	 */
	public function posts_distinct( $join, $wp_query ) {
		return 'DISTINCT';
	}

	public function admin_enqueue_scripts() {
		if ( isset( $_REQUEST['viwebpos_admin_nonce'] ) && ! wp_verify_nonce( wc_clean( wp_unslash( $_REQUEST['viwebpos_admin_nonce'] ) ), 'viwebpos_admin_nonce' ) ) {
			return;
		}
		global $post_type;
		if ( $post_type == 'shop_order' || ( isset( $_GET['page'] ) && wc_clean( wp_unslash( $_GET['page'] ) ) === 'wc-orders' ) ) {
			VIWEBPOS_Admin_Settings::enqueue_script(
				array( 'viwebpos-admin-woo' ),
				array( 'admin-woo' )
			);
			add_filter( 'woocommerce_gateway_title', array( $this, 'change_payment_title' ), PHP_INT_MAX, 1 );
		}
	}

	public function change_payment_title( $title ) {
		$order      = wc_get_order( get_the_ID() );
		$cashier_id = $order ? $order->get_meta( 'viwebpos_cashier_id' ) : '';
		if ( $cashier_id ) {
			if ( $title === 'multi' ) {
				$title = esc_html__( 'Multiple Methods', 'webpos-point-of-sale-for-woocommerce' );
			}

			return sprintf( '%s - %s', $title, esc_html__( 'POS', 'webpos-point-of-sale-for-woocommerce' ) );
		}

		return $title;
	}

	public function reset_payment_title() {
		remove_filter( 'woocommerce_gateway_title', array( $this, 'change_payment_title' ), PHP_INT_MAX, 1 );
	}

	public function get_order_cashier( $order_id ) {
		$order      = wc_get_order( $order_id );
		$cashier_id = $order ? $order->get_meta( 'viwebpos_cashier_id', true ) : '';
		if ( $cashier_id ) {
			$cashier = get_user_by( 'id', $cashier_id );
			?>
            <tr>
                <td class="label"><?php esc_html_e( 'POS Cashier', 'webpos-point-of-sale-for-woocommerce' ); ?>:</td>
                <td width="1%"></td>
                <td class="total">
                    <a href="<?php echo esc_url( get_edit_user_link( $cashier_id ) ) ?>"><strong><?php echo wp_kses_post( $cashier->display_name ) ?></strong></a>
                </td>
            </tr>
			<?php
		}
	}


	public function before_delete_order( $order_id ) {
		if ( in_array( get_post_type( $order_id ), wc_get_order_types(), true ) || current_action() === 'woocommerce_delete_order' ) {
			VIWEBPOS_DATA::set_data_prefix( 'orders' );
			VIWEBPOS_Transactions_Table::delete_by_order_id( $order_id );
		}
	}

	public function order_item_display_meta_key( $display_key, $meta, $order_items ) {
		if ( $meta->key === 'line_item_note' ) {
			$display_key = esc_html__( 'Note', 'webpos-point-of-sale-for-woocommerce' );
		}

		return $display_key;
	}
}