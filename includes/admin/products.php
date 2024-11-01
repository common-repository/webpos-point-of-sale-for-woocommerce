<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VIWEBPOS_Admin_Products {
	protected $settings, $auto_create_barcode_by_sku;

	public function __construct() {
		$this->settings = VIWEBPOS_DATA::get_instance();
		if ( ! $this->settings->get_params( 'enable' ) ) {
			return;
		}
		$this->auto_create_barcode_by_sku = $this->settings->get_params( 'auto_create_barcode_by_sku' );
		//add a new column to the table
		add_filter( 'manage_edit-product_columns', array( $this, 'add_new_column' ) );
		add_action( 'manage_product_posts_custom_column', array( $this, 'column_callback' ), 10, 2 );
		add_action( 'woocommerce_product_options_sku', array( $this, 'add_product_barcode' ) );
		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'variable_add_product_barcode' ), 10, 3 );
		$product_type = apply_filters( 'viwebpos_applicable_product_type', [ 'simple', 'variable' ] );
		foreach ( $product_type as $type ) {
			add_action( 'woocommerce_process_product_meta_' . $type, array( $this, 'save_product_barcode' ) );
		}
		add_action( 'woocommerce_save_product_variation', array( $this, 'variable_save_product_barcode' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
	}

	public function add_new_column( $cols ) {
		if ( ! $this->auto_create_barcode_by_sku || ! wc_product_sku_enabled() ) {
			$keys  = array_keys( $cols );
			$index = array_search( 'sku', $keys );
			if ( $index === false ) {
				$index = array_search( 'name', $keys );
			}
			if ( $index !== false ) {
				$index ++;
				$cols = array_merge( array_slice( $cols, 0, $index ), array( 'viwebpos_barcode' => esc_html__( 'Barcode', 'webpos-point-of-sale-for-woocommerce' ) ), array_slice( $cols, $index ) );
			} else {
				$cols['viwebpos_barcode'] = esc_html__( 'Barcode', 'webpos-point-of-sale-for-woocommerce' );
			}
		}

		return $cols;
	}

	public function column_callback( $column_name, $id ) {
		if ( $column_name === 'viwebpos_barcode' && ( ! $this->auto_create_barcode_by_sku || ! wc_product_sku_enabled() ) ) {
			echo esc_html( get_post_meta( $id, 'viwebpos_barcode', true ) ?? '' );
		}
	}

	public function add_product_barcode() {
		global $post;
		$product_id = $post->ID ?? get_the_ID();
		$product    = wc_get_product( $product_id );
		if ( ! $product ) {
			return;
		}
		if ( ! $this->auto_create_barcode_by_sku || ! wc_product_sku_enabled() ) {
			woocommerce_wp_text_input(
				array(
					'id'    => 'viwebpos_barcode',
					'value' => get_post_meta( $product_id, 'viwebpos_barcode', true ) ?? $product->get_sku( 'edit' ),
					'label' => esc_html__( 'Barcode', 'webpos-point-of-sale-for-woocommerce' ),
					'class' => 'viwebpos-barcode-dynamic short',
				)
			);
		}
	}

	public function variable_add_product_barcode( $loop, $variation_data, $variation ) {
		$variation_id     = $variation->ID;
		$variation_object = wc_get_product( $variation_id );
		if ( ! $variation_object ) {
			return;
		}
		if ( ! $this->auto_create_barcode_by_sku || ! wc_product_sku_enabled() ) {
			woocommerce_wp_text_input(
				array(
					'id'                => 'viwebpos_loop_barcode[' . $loop . ']',
					'value'             => get_post_meta( $variation_id, 'viwebpos_barcode', true ) ?? $variation_object->get_sku( 'edit' ),
					'placeholder'       => $variation_object->get_sku(),
					'label'             => esc_html__( 'Barcode', 'webpos-point-of-sale-for-woocommerce' ),
					'custom_attributes' => array( 'data-loop' => $loop ),
					'class'             => 'viwebpos-barcode-dynamic short',
					'wrapper_class'     => 'form-row form-row-last viwebpos-barcode-dynamic-wrap',
				)
			);
		}
	}

	public function save_product_barcode( $post_id ) {
		if ( isset( $_REQUEST['viwebpos_admin_nonce'] ) && ! wp_verify_nonce( wc_clean( wp_unslash( $_REQUEST['viwebpos_admin_nonce'] ) ), 'viwebpos_admin_nonce' ) ) {
			return;
		}
		$barcode = isset( $_POST['viwebpos_barcode'] ) ? wc_clean( $_POST['viwebpos_barcode'] ) : '';
		update_post_meta( $post_id, 'viwebpos_barcode', $barcode );
	}

	public function variable_save_product_barcode( $variation_id, $i ) {
		if ( isset( $_REQUEST['viwebpos_admin_nonce'] ) && ! wp_verify_nonce( wc_clean( wp_unslash( $_REQUEST['viwebpos_admin_nonce'] ) ), 'viwebpos_admin_nonce' ) ) {
			return;
		}
		$barcode = isset( $_REQUEST['viwebpos_loop_barcode'][ $i ] ) ? wc_clean( $_REQUEST['viwebpos_loop_barcode'][ $i ] ) : '';
		update_post_meta( $variation_id, 'viwebpos_barcode', $barcode );
	}

	public function admin_enqueue_scripts() {
		if ( isset( $_REQUEST['viwebpos_admin_nonce'] ) && ! wp_verify_nonce( wc_clean( wp_unslash( $_REQUEST['viwebpos_admin_nonce'] ) ), 'viwebpos_admin_nonce' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( $screen->id === 'product' ) {
			VIWEBPOS_Admin_Settings::enqueue_script(
				array( 'viwebpos-admin-woo' ),
				array( 'admin-woo.js' )
			);
			wp_localize_script( 'viwebpos-admin-woo', 'viwebpos_admin_product',
				array(
					'auto_create_barcode_by_sku' => $this->auto_create_barcode_by_sku ?: ''
				)
			);
			wp_add_inline_style( 'list-tables', '.viwebpos-hidden{display: none!important;}' );
		} elseif ( ! empty( $_GET['post_type'] ) && wc_clean( $_GET['post_type'] ) === 'product' ) {
			wp_add_inline_style( 'list-tables', 'table.wp-list-table .column-viwebpos_barcode{width: 10%;}' );
		}
	}
}