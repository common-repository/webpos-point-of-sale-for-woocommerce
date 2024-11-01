<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$settings          = VIWEBPOS_DATA::get_instance();
$site_title        = get_bloginfo( 'name' );
$primary_address   = get_option( 'woocommerce_store_address', '' );
$secondary_address = get_option( 'woocommerce_store_address_2', '' );
$city_address      = get_option( 'woocommerce_store_city', '' );
$location_address  = wc_get_base_location();
if ( empty( $orders ) ) {
	return;
}
$templates = $outlets = $bill_data = [];
$placeholder_img = wc_placeholder_img_src( 'woocommerce_gallery_thumbnail' );
if (empty($template)){
    $template = 'default';
}

if ( ! isset( $templates[ $template ] ) ) {
	$tmp = $settings->get_current_setting( 'receipts', $template, $settings->get_default( 'receipts' )['default'] ?? [] );
	if (!isset($tmp['direction'])){
		$tmp = wp_parse_args($tmp,$settings->get_default( 'receipts' )['default'] ?? []);
	}
	if ( ! empty( $tmp['logo'] ) ) {
		$receipt_logo_id = $settings->get_current_setting_by_subtitle( 'receipts', $template, 'logo_id', '' );
		$logo_src        = $receipt_logo_id ? wp_get_attachment_image_url( $receipt_logo_id, 'woocommerce_thumbnail', true ) : $placeholder_img;
		$tmp['logo_src'] = $logo_src;
	}
	$templates[ $template ] = $tmp;
}
$outlet = 'woo_online';
if (!isset($outlets[$outlet])){
	$country_setting =  implode( ':', array_values( $location_address ) ) ;
	if ( strstr( $country_setting, ':' ) ) {
		list( $country, $state ) = explode( ':', $country_setting );
	} else {
		$country = $country_setting;
		$state   = '';
	}
	$outlets[$outlet] = [
		'phone'          =>  '' ,
		'email'          =>  '' ,
		'address'          =>  $primary_address ,
		'address_2'        =>  $secondary_address ,
		'city'             => $city_address ,
		'state'             => $state,
		'country'             => $country,
	];
}
foreach ( $orders as $order ) {
	$order = wc_get_order( $order );
	if ( ! $order ) {
		continue;
	}
	$order_id = $order->get_id();
	$bill_tmp = [
		'receipt_template' => $template,
		'outlet' => $outlet,
	];
	$bill_data[ $order_id ] = $bill_tmp;
}
if ( empty( $bill_data ) || empty( $outlets ) || empty( $templates ) ) {
	return;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php esc_html_e( 'WebPOS receipt', 'webpos-point-of-sale-for-woocommerce' ); ?></title>
	<?php do_action( 'viwebpos_print_head', $templates ); ?>
</head>
<body>
<?php
foreach ($bill_data as $order_id => $params){
    $order = wc_get_order($order_id);
	$receipt_template_id = $params['receipt_template'] ?? 'default';
	$outlet_id = $params['outlet'] ?? '';
    $receipt_template = $templates[$receipt_template_id] ??[];
    $outlet_data = $outlets[$outlet_id]??[];
	if ( !$order || empty($receipt_template) || empty($outlet) ) {
		continue;
	}
	$order_id = $order->get_id();
	$order_currency = $order->get_currency();
	$wrap_class = "viwebpos-bill-content viwebpos-bill-content-{$receipt_template_id}";
    if (($receipt_template['direction']??'') === 'rtl'){
	    $wrap_class .= ' viwebpos-bill-content-rtl';
    }
	$header_wrap_class = 'viwebpos-bill-header-wrap';
    if (!empty($receipt_template['logo_src'])){
        $header_wrap_class .= ' viwebpos-bill-header-max viwebpos-bill-header-'. ($receipt_template['logo_pos'] ??'left');
    }
	do_action( 'viwebpos_receipt_before_content', $order , $receipt_template );
    ?>
    <article class="<?php echo esc_attr($wrap_class)?>">
        <div class="viwebpos-bill-content-inner">
            <div class="<?php echo esc_attr($header_wrap_class)?>">
                <?php
                if (!empty($receipt_template['logo_src'])){
	                printf('<div class="viwebpos-bill-logo"><span class="viwebpos-bill-logo-preview"><img src="%s" alt="logo image"></span></div>', esc_url( $receipt_template['logo_src'] ) );
                }
                ?>
                <div class="viwebpos-bill-contact-wrap">
                    <div class="viwebpos-bill-contact">
                        <?php
                        if (!empty($receipt_template['contact_info'])){
                            $contact_info = str_replace([
	                            '{site_title}',
	                            '{address_1}',
	                            '{address_2}',
	                            '{city}',
	                            '{state}',
	                            '{country}',
	                            '{phone}',
	                            '{email}'
                            ], [
	                            $site_title,
                                $outlet_data['address']??'',
                                $outlet_data['address_2']??'',
                                $outlet_data['city']??'',
                                $outlet_data['state']??'',
                                $outlet_data['country']??'',
                                $outlet_data['phone']??'',
                                $outlet_data['email']??'',
                            ], $receipt_template['contact_info']);
	                        $address_array   = explode( "\n", $contact_info );
	                        foreach ( $address_array as $field_address ) {
                                printf('<div>%s</div>', wp_kses_post($field_address));
	                        }
                        }
                        ?>
                    </div>
                </div>
            </div>
	        <?php
	        if (!empty($receipt_template['bill_title'])){
		        printf('<div class="viwebpos-bill-title viwebpos-font-bold">%s</div>', wp_kses_post($receipt_template['bill_title']));
	        }
	        ?>
            <div class="viwebpos-bill-top viwebpos-header-style-<?php echo esc_attr($receipt_template['order_col'] ?? 1)?>">
                <?php
                if (!empty($receipt_template['date_create'])){
                    printf('<div class="viwebpos-bill-order-date-wrap">');
                    if (!empty($receipt_template['date_create_label'])){
                        printf('<div class="viwebpos-bill-date-label">%s</div>' , wp_kses_post($receipt_template['date_create_label']));
                    }
	                printf('<div class="viwebpos-bill-order-date">%1$s %2$s %3$s</div>' ,  esc_html( $order->get_date_created()->date_i18n( wc_date_format() ) ), esc_html__( 'at', 'webpos-point-of-sale-for-woocommerce' ), esc_html( $order->get_date_created()->date_i18n( wc_time_format() ) ));
                    printf('</div>');
                }
                if (!empty($receipt_template['order_id'])){
                    printf('<div class="viwebpos-bill-order-id-wrap">');
                    if (!empty($receipt_template['order_id_label'])){
                        printf('<div class="viwebpos-bill-id-label">%s</div>' , wp_kses_post($receipt_template['order_id_label']));
                    }
	                printf('<div class="viwebpos-bill-order-id">#%s</div>' , esc_html( $order->get_order_number() ));
                    printf('</div>');
                }
                $pos_cashier_id = $order->get_meta( 'viwebpos_cashier_id', true );
                if (!empty($receipt_template['cashier']) && $pos_cashier_id){
	                $pos_cashier_name = get_user_by( 'id', $pos_cashier_id );
	                if ( $pos_cashier_name ) {
		                $pos_cashier_name = $pos_cashier_name->data->display_name;
	                } else {
		                $pos_cashier_name = esc_html__( 'Cashier', 'webpos-point-of-sale-for-woocommerce' );
	                }
                    printf('<div class="viwebpos-bill-cashier-wrap">');
                    if (!empty($receipt_template['cashier_label'])){
                        printf('<div class="viwebpos-bill-cashier-label">%s</div>' , wp_kses_post($receipt_template['cashier_label']));
                    }
	                printf('<div class="viwebpos-bill-order-cashier">%s</div>' , wp_kses_post($pos_cashier_name));
                    printf('</div>');
                }
                $customer_id = $order->get_user_id();
                $customer = new WC_Customer( $customer_id );
                if (!empty($receipt_template['customer'])){
	                $customer_name = '';
                    $customer_name_default = esc_html__('Guest', 'webpos-point-of-sale-for-woocommerce');
                    $customer_display_type = $receipt_template['customer_display']??'';
                    if ($customer){
                        switch ($customer_display_type){
                            case 'email':
                                $customer_name = $customer->get_email();
                                break;
                            case 'fullname':
                                $customer_name = $customer->get_display_name();
                                break;
                            case 'firstname':
                                $customer_name = $customer->get_first_name();
                                break;
                            case 'lastname':
                                $customer_name = $customer->get_last_name();
                                break;
                            case 'company':
                                $customer_name = $customer->get_shipping_company() ?: $customer->get_billing_company();
                                break;
                            default:
                                $customer_name = $customer->get_shipping_company() ?: $customer->get_display_name();
                        }
                    }
                    if (!trim($customer_name)){
	                    switch ($customer_display_type){
		                    case 'email':
			                    $customer_name = $order->get_billing_email();
			                    break;
		                    case 'fullname':
			                    $customer_name = $order->get_billing_first_name() .' '. $order->get_billing_last_name();
			                    break;
		                    case 'firstname':
			                    $customer_name =  $order->get_billing_first_name();
			                    break;
		                    case 'lastname':
			                    $customer_name = $order->get_billing_last_name();
			                    break;
		                    case 'company':
			                    $customer_name = $order->get_billing_company() ?: $order->get_shipping_company();
			                    break;
		                    default:
			                    $customer_name = $order->get_billing_company() ?: ($order->get_shipping_company() ?: ($order->get_billing_first_name() .' '. $order->get_billing_last_name()));
	                    }
                    }
                    if (!trim($customer_name)){
                        $customer_name = $customer_name_default;
                    }
	                printf('<div class="viwebpos-bill-customer-wrap">');
	                if (!empty($receipt_template['customer_label'])){
		                printf('<div class="viwebpos-bill-customer-label">%s</div>' , wp_kses_post($receipt_template['customer_label']));
	                }
	                printf('<div class="viwebpos-bill-customer">%s</div>' , wp_kses_post($customer_name));
	                printf('</div>');
                }
                if (!empty($receipt_template['customer_phone'])){
	                $customer_phone   = esc_html__( 'No phone number', 'webpos-point-of-sale-for-woocommerce' );
	                printf('<div class="viwebpos-bill-customer-phone-wrap">');
	                if (!empty($receipt_template['customer_phone_label'])){
		                printf('<div class="viwebpos-bill-customer-phone-label">%s</div>' , wp_kses_post($receipt_template['customer_phone_label']));
	                }
	                printf('<div class="viwebpos-bill-customer-phone">%s</div>' , wp_kses_post($customer && $customer->get_billing_phone() ? $customer->get_billing_phone() : ($order->get_billing_phone()?: $customer_phone) ));
	                printf('</div>');
                }
                if ( !empty($receipt_template['customer_address'])&& !empty($receipt_template['customer_address_display'])){
	                $replace_arr=[];
                    if ($customer) {
	                    $replace_arr = [
		                    $customer->get_shipping_address_1() ?: $customer->get_billing_address_1() ?: '',
		                    $customer->get_shipping_address_2() ?: $customer->get_billing_address_2() ?: '',
		                    $customer->get_shipping_city() ?: $customer->get_billing_city() ?: '',
		                    $customer->get_shipping_state() ?: $customer->get_billing_state() ?: '',
		                    $customer->get_shipping_country() ?: $customer->get_billing_country() ?: '',
	                    ];
                    }
                    if (empty(array_unique($replace_arr)[0])){
	                    $replace_arr = [
		                    $order->get_billing_address_1() ?: '',
		                    $order->get_billing_address_2() ?: '',
		                    $order->get_billing_city() ?: '',
		                    $order->get_billing_state() ?: '',
		                    $order->get_billing_country() ?: '',
	                    ];
                    }
                    if (!empty(array_unique($replace_arr)[0])){
	                    $customer_address_html = '';
	                    $customer_address = str_replace(['{address_line_1}', '{address_line_2}', '{city}', '{state}', '{country}'], $replace_arr , $receipt_template['customer_address_display']);
	                    $address_array   = explode( "\n", $customer_address );
	                    foreach ( $address_array as $field_address ) {
		                    $customer_address_html .= sprintf('<div>%s</div>', wp_kses_post($field_address));
	                    }
                    }else{
	                    $customer_address_html = esc_html__( 'No address', 'webpos-point-of-sale-for-woocommerce' );
                    }
	                printf('<div class=viwebpos-bill-customer-address-wrap>');
	                if (!empty($receipt_template['customer_address_label'])){
		                printf('<div class="viwebpos-bill-customer-address-label">%s</div>' , wp_kses_post($receipt_template['customer_address_label']));
	                }
	                printf('<div class="viwebpos-bill-customer-address">%s</div>' , wp_kses_post($customer_address_html));
	                printf('</div>');
                }
                ?>
            </div>
            <?php
            $order_items = $order->get_items();
            $max_product_character = intval($receipt_template['product_character'] ?? 0);
            $exclude_meta_key =[ 'line_item_note'];
            switch ($receipt_template['product_temp'] ??''){
                case 'flat':
                    ?>
                <div class="viwebpos-bill-body">
                    <table>
                        <tr class="viwebpos-bill-product-wrap">
                            <th class="viwebpos-bill-product-title-col-block"><?php echo $settings::kses_post($receipt_template['product_label'] ?? ''); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?></th>
                            <?php
                            if (!empty($receipt_template['product_subtotal'])){
	                            printf('<th class="viwebpos-bill-product-subtotal-label viwebpos-align-right">%s</th>',$settings::kses_post($receipt_template['product_subtotal_label'] ?? '') );// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            }
                            ?>
                        </tr>
                        <?php
                        if (!empty($order_items)){
                            foreach ($order_items as $item_id => $item){
	                            $product         = $item->get_product();
	                            $product_id      = $item->get_product_id();
	                            $variation_id    = $item->get_variation_id();
	                            $product_sku     = is_a( $product, 'WC_Product' ) ? $product->get_sku() : null;
	                            $item_name       = $item->get_name();
                                $product_id_t = $variation_id ?: $product_id;
                                printf('<tr><td class="viwebpos-bill-product-title">');
                                if ($product_id_t && !empty($receipt_template['product_id'])){
                                    printf('<span class="viwebpos-bill-product-id">#%s </span>', esc_html($product_id_t));
                                }
                                if ($product_sku && !empty($receipt_template['product_sku'])){
                                    printf('<span class="viwebpos-bill-product-sku">%s </span>', $settings::kses_post(($receipt_template['product_sku_label']??'').$product_sku));// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                }
                                if ($max_product_character && $item_name && strlen($item_name) > $max_product_character){
                                    $item_name = substr($item_name, 0, $max_product_character).'...';
                                }
                                $item_name = apply_filters( 'viwebpos_receipt_item_name', $item_name, $item );
                                printf('<span class="viwebpos-bill-product-title-inline">%s</span>', $settings::kses_post($item_name));// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	                            if (!empty($receipt_template['product_price'])){
		                            printf('<span class="viwebpos-bill-product-price"> %s</span>', wp_kses_post( preg_replace( '/\([^\)]*\)*/i', '',VIWEBPOS_Print_Receipt::get_formatted_item_price($order,$item))));
	                            }
	                            if (!empty($receipt_template['product_quantity'])){
		                            printf('<span class="viwebpos-bill-product-quantity">x%s</span>', esc_html( apply_filters( 'viwebpos_order_item_quantity', $item->get_quantity(), $item ) ));
	                            }
	                            if (!empty($receipt_template['product_note'])){
                                    $note = $item->get_meta( 'line_item_note', true ) ?? '';
                                    if ($note){
	                                    printf('<br><span class="viwebpos-bill-product-note-label">%s</span><span class="viwebpos-bill-product-note">%s</span>',$settings::kses_post($receipt_template['product_note_label'] ??''), esc_html($note) );// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                    }
	                            }
	                            $meta_data       = $item->get_meta_data();
                                if (!empty($meta_data)) {
                                    $display_variation_attr = !$variation_id || !empty($receipt_template['product_variation']);
	                                foreach ( $meta_data as $v ) {
		                                $v->key = rawurldecode( (string) $v->key );
		                                if ( in_array($v->key, $exclude_meta_key) || (strpos($v->key, '_') === 0)|| empty( $v->value ) || is_array( $v->value ) ) {
			                                continue;
		                                }
		                                $attribute_key = str_replace( 'attribute_', '', $v->key );
                                        $display_key = wc_attribute_label( $attribute_key, $product );
                                        if (!$display_variation_attr && ($display_key !== $attribute_key || taxonomy_exists( $attribute_key ) )){
                                            continue;
                                        }
		                                $display_key   = apply_filters( 'woocommerce_order_item_display_meta_key',  wc_attribute_label( $attribute_key, $product ), $v, $item);
		                                $v->value      = rawurldecode( (string) $v->value );
		                                $display_value = $v->value ;
                                        if ( taxonomy_exists( $attribute_key ) ) {
			                                $term = get_term_by( 'slug', $v->value, $attribute_key );
			                                if ( ! is_wp_error( $term ) && is_object( $term ) && $term->name ) {
				                                $display_value = $term->name;
			                                }
		                                }
		                                $display_value = apply_filters( 'woocommerce_order_item_display_meta_value', $display_value, $v, $item);
		                                printf('<br>%s: %s', wp_kses_post($display_key), wp_kses_post($display_value));
	                                }
                                }
                                printf('</td>');
                                if (!empty($receipt_template['product_subtotal'])){
	                                $item_subtotal = $order->get_formatted_line_subtotal($item);
                                    $item_subtotal_html = $item_subtotal ? preg_replace( '/\([^\)]*\)*/i', '', $item_subtotal) : VIWEBPOS_Plugins_Curcy::curcy_wc_price( 0, array( 'currency' => $order_currency ) );
	                                printf('<td class="viwebpos-bill-product-subtotal viwebpos-align-right">%s</td>', $settings::kses_post($item_subtotal_html));// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                }
	                            printf('</tr>');
                            }
                        }
                        ?>
                    </table>
                </div>
                    <?php
                    break;
                default:
                    ?>
                <div class="viwebpos-bill-col-body">
                    <table>
                        <tr class="viwebpos-bill-col-product-wrap">
                            <th class="viwebpos-bill-col-product-title-label"><?php echo $settings::kses_post($receipt_template['product_label'] ?? ''); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?></th>
                            <?php
                            if (!empty($receipt_template['product_id'])){
                                printf('<th class="viwebpos-bill-col-product-id-label viwebpos-align-right">%s</th>',$settings::kses_post($receipt_template['product_id_label'] ?? '') );// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            }
                            if (!empty($receipt_template['product_sku'])){
                                printf('<th class="viwebpos-bill-col-product-sku-label viwebpos-align-right">%s</th>',$settings::kses_post($receipt_template['product_sku_label'] ?? '') );// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            }
                            if (!empty($receipt_template['product_price'])){
                                printf('<th class="viwebpos-bill-col-product-price-label viwebpos-align-right">%s</th>',$settings::kses_post($receipt_template['product_price_label'] ?? '') );// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            }
                            if (!empty($receipt_template['product_quantity'])){
                                printf('<th class="viwebpos-bill-col-product-quantity-label viwebpos-align-right">%s</th>',$settings::kses_post($receipt_template['product_quantity_label'] ?? '') );// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            }
                            if (!empty($receipt_template['product_subtotal'])){
                                printf('<th class="viwebpos-bill-col-product-subtotal-label viwebpos-align-right">%s</th>',$settings::kses_post($receipt_template['product_subtotal_label'] ?? '') );// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            }
                            ?>
                        </tr>
	                    <?php
	                    if (!empty($order_items)){
		                    foreach ($order_items as $item_id => $item){
			                    $product         = $item->get_product();
			                    $product_id      = $item->get_product_id();
			                    $variation_id    = $item->get_variation_id();
			                    $product_sku     = is_a( $product, 'WC_Product' ) ? $product->get_sku() : '';
			                    $item_name       = $item->get_name();
			                    $product_id_t = $variation_id ?: $product_id;
			                    printf('<tr class="viwebpos-bill-col-product-wrap"><td class="viwebpos-bill-col-product-title">');
			                    if ($max_product_character && $item_name && strlen($item_name) > $max_product_character){
				                    $item_name = substr($item_name, 0, $max_product_character).'...';
			                    }
			                    $item_name = apply_filters( 'viwebpos_receipt_item_name', $item_name, $item );
			                    printf('<span class="viwebpos-bill-product-title-inline">%s</span>', $settings::kses_post($item_name));// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			                    $meta_data       = $item->get_meta_data();
			                    if (!empty($meta_data)) {
				                    $display_variation_attr = !$variation_id || !empty($receipt_template['product_variation']);
				                    foreach ( $meta_data as $v ) {
					                    $v->key = rawurldecode( (string) $v->key );
					                    if ( in_array($v->key, $exclude_meta_key) || (strpos($v->key, '_') === 0)|| empty( $v->value ) || is_array( $v->value ) ) {
						                    continue;
					                    }
					                    $attribute_key = str_replace( 'attribute_', '', $v->key );
					                    $display_key = wc_attribute_label( $attribute_key, $product );
					                    if (!$display_variation_attr && ($display_key !== $attribute_key || taxonomy_exists( $attribute_key ) )){
						                    continue;
					                    }
					                    $display_key   = apply_filters( 'woocommerce_order_item_display_meta_key',  wc_attribute_label( $attribute_key, $product ), $v, $item);
					                    $v->value      = rawurldecode( (string) $v->value );
					                    $display_value = $v->value ;
					                    if ( taxonomy_exists( $attribute_key ) ) {
						                    $term = get_term_by( 'slug', $v->value, $attribute_key );
						                    if ( ! is_wp_error( $term ) && is_object( $term ) && $term->name ) {
							                    $display_value = $term->name;
						                    }
					                    }
					                    $display_value = apply_filters( 'woocommerce_order_item_display_meta_value', $display_value, $v, $item);
					                    printf('<br>%s: %s', wp_kses_post($display_key), wp_kses_post($display_value));
				                    }
			                    }
			                    if (!empty($receipt_template['product_note'])){
				                    $note = $item->get_meta( 'line_item_note', true ) ?? '';
				                    if ($note){
					                    printf('<div class="viwebpos-bill-product-note-wrap"><span class="viwebpos-bill-product-note-label">%s</span><span class="viwebpos-bill-product-note">%s</span></div>',$settings::kses_post($receipt_template['product_note_label'] ??''), esc_html($note) );// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				                    }
			                    }
                                printf('</td>');
			                    if (!empty($receipt_template['product_id'])){
				                    printf('<td class="viwebpos-bill-col-product-id viwebpos-align-right">#%s</td>', esc_html($product_id_t ?: ''));
			                    }
			                    if ( !empty($receipt_template['product_sku'])){
                                    printf('<td class="viwebpos-bill-col-product-sku viwebpos-align-right">%s</td>', esc_html( $product_sku ));
			                    }
			                    if (!empty($receipt_template['product_price'])){
                                    printf('<td class="viwebpos-bill-col-product-price viwebpos-align-right"> %s</td>', wp_kses_post( preg_replace( '/\([^\)]*\)*/i', '',VIWEBPOS_Print_Receipt::get_formatted_item_price($order,$item))));
			                    }
			                    if (!empty($receipt_template['product_quantity'])){
                                    printf('<td class="viwebpos-bill-col-product-quantity viwebpos-align-right">%s</td>', esc_html( apply_filters( 'viwebpos_order_item_quantity', $item->get_quantity(), $item ) ));
			                    }
			                    if (!empty($receipt_template['product_subtotal'])){
				                    $item_subtotal = $order->get_formatted_line_subtotal($item);
				                    $item_subtotal_html = $item_subtotal ? preg_replace( '/\([^\)]*\)*/i', '', $item_subtotal) : VIWEBPOS_Plugins_Curcy::curcy_wc_price( 0, array( 'currency' => $order_currency ) );
				                    printf('<td class="viwebpos-bill-col-product-subtotal viwebpos-align-right">%s</td>', $settings::kses_post($item_subtotal_html));// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			                    }
			                    printf('</tr>');
		                    }
	                    }
	                    ?>
                    </table>
                </div>
                <?php
            }
            ?>
            <div class="viwebpos-bill-bottom">
                <?php
                if (!empty($receipt_template['order_note'])){
                    $order_note = $order->get_customer_note();
                    if ($order_note){
                        printf('<div class="viwebpos-bill-order-note-wrap"><span class="viwebpos-bill-order-note-label">%s</span><span class="viwebpos-bill-order-note">%s</span></div>', $settings::kses_post($receipt_template['order_note_label']?? ''), $settings::kses_post($order_note));// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    }
                }
                if (!empty($receipt_template['order_tax'])){
                    printf('<div class="viwebpos-bill-order-tax-wrap"><div class="viwebpos-bill-order-tax-label">%s</div>', $settings::kses_post($receipt_template['order_tax_label'] ??''));// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	                $order_total_tax = $order->get_total_tax() ?? 0;
                    if ($order->get_total_tax_refunded()){
	                    $order_total_tax_refunded = $order->get_total_tax_refunded();
                        printf('<div class="viwebpos-bill-order-tax viwebpos-font-bold"><del>%s</del><ins>%s</ins></div>',
                        $settings::kses_post(VIWEBPOS_Plugins_Curcy::curcy_wc_price( $order_total_tax, array( 'currency' => $order_currency ) )),// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        $settings::kses_post(VIWEBPOS_Plugins_Curcy::curcy_wc_price( $order_total_tax - $order_total_tax_refunded, array( 'currency' => $order_currency ) )));// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    }else{
                        printf('<div class="viwebpos-bill-order-tax viwebpos-font-bold">%s</div>',$settings::kses_post(VIWEBPOS_Plugins_Curcy::curcy_wc_price( $order_total_tax, array( 'currency' => $order_currency ) )));// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    }
                    printf('</div>');
                }
                if (!empty($receipt_template['order_ship'])){
                    $order_total_shipping = (float)$order->get_shipping_total();
                    if ($order_total_shipping){
	                    printf('<div class="viwebpos-bill-order-ship-wrap"><span class="viwebpos-bill-order-ship-label">%s</span><span class="viwebpos-bill-order-ship viwebpos-font-bold">%s</span></div>', $settings::kses_post($receipt_template['order_ship_label']?? ''), $settings::kses_post(VIWEBPOS_Plugins_Curcy::curcy_wc_price( $order_total_shipping, array( 'currency' => $order_currency ) )));// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    }
                }
                if (!empty($receipt_template['order_discount'])){
                    $order_total_discount = $order->get_total_discount();
	                printf('<div class="viwebpos-bill-order-discount-wrap"><span class="viwebpos-bill-order-discount-label">%s</span><span class="viwebpos-bill-order-discount viwebpos-font-bold">%s</span></div>',
		                $settings::kses_post($receipt_template['order_discount_label']?? ''),// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		                $settings::kses_post(VIWEBPOS_Plugins_Curcy::curcy_wc_price( -1 * $order_total_discount, array( 'currency' => $order_currency ) )));// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
                $order_fees = $order->get_fees();
                if (!empty($order_fees)){
                    foreach ($order_fees as $fee_id => $fee){
                        $fee_name = $fee->get_name();
                        if ($fee_name === 'POS discount'){
                            if (!empty($receipt_template['order_pos_discount'])){
                                $total_tmp = wc_format_decimal( $order->get_line_total($fee) + $order->get_line_tax( $fee ), 2 );
                                ?>
                                <div class="viwebpos-bill-order-pos-discount-wrap">
                                    <div class="viwebpos-bill-order-pos-discount-label"><?php echo $settings::kses_post($receipt_template['order_pos_discount_label'] ??'');// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                                    <div class="viwebpos-bill-order-pos-discount viwebpos-font-bold">
                                        <?php echo $settings::kses_post(VIWEBPOS_Plugins_Curcy::curcy_wc_price( $total_tmp ?: 0, array( 'currency' => $order_currency ) ))// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    </div>
                                </div>
                                <?php
                            }
                        }else{
	                        $total_tmp = wc_format_decimal( $order->get_line_total($fee) + $order->get_line_tax( $fee ), 2 );
	                        ?>
                            <div class="viwebpos-bill-order-fee-discount-wrap">
                                <div class="viwebpos-bill-order-fee-discount-label"><?php echo $settings::kses_post($fee_name);// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                                <div class="viwebpos-bill-order-fee-discount viwebpos-font-bold">
			                        <?php echo $settings::kses_post(VIWEBPOS_Plugins_Curcy::curcy_wc_price( $total_tmp ?: 0, array( 'currency' => $order_currency ) ));// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </div>
                            </div>
	                        <?php
                        }
                    }
                }
                if (!empty($receipt_template['order_paid'])){
	                $order_total_paid =VIWEBPOS_Plugins_Curcy::curcy_wc_price(  $order->get_meta( 'pos_total_paid', true ) ?: $order->get_total(), array( 'currency' => $order_currency ) );
	                printf('<div class="viwebpos-bill-order-paid-wrap"><span class="viwebpos-bill-order-paid-label">%s</span><span class="viwebpos-bill-order-paid viwebpos-font-bold">%s</span></div>',
		                $settings::kses_post($receipt_template['order_paid_label']?? ''),// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		                $settings::kses_post($order_total_paid));// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	                $payments     = VIWEBPOS_Transactions_Table::get_transactions( array( 'where' => "order_id = {$order_id}" ) );
	                $pos_payment =[];
	                $total_payment_method = 0;
	                if ( is_array( $payments ) ) {
		                foreach ( $payments as $payment ) {
			                if ( empty( $payment['method'] ) || empty( $payment['in'] ) ) {
				                continue;
			                }
			                $total_payment_method++;
			                $pos_payment[ $payment['method'] ] = array(
				                'payment_id' => $payment['method'],
				                'paid'       => $payment['in'],
				                'change'     => $payment['out'] ?? 0,
			                );
		                }
	                }
                    if (is_array($pos_payment) && $total_payment_method > 1){
	                    $woo_payments = $woo_payments ?? WC()->payment_gateways->payment_gateways();
	                    foreach ( $pos_payment as $payment ) {
		                    $method        = $payment['payment_id'] ?? '';
		                    if (isset($woo_payments[ $method ])){
			                    $method_title = $woo_payments[ $method ]->method_title ?? $woo_payments[ $method ]->title ?? $method;
		                    }else{
			                    $method_title = esc_html__( 'Manual', 'webpos-point-of-sale-for-woocommerce' );
		                    }
		                    ?>
                            <div class="viwebpos-bill-order-paid-wrap viwebpos-payment-label">
                                <div class="viwebpos-bill-order-paid-label"><?php echo esc_html( ucfirst( $method_title ) ); ?></div>
                                <div class="viwebpos-bill-order-paid viwebpos-font-bold">
				                    <?php echo wp_kses_post( VIWEBPOS_Plugins_Curcy::curcy_wc_price( $payment['paid'] ?? 0 , array( 'currency' => $order_currency )) ); ?>
                                </div>
                            </div>
		                    <?php
	                    }
                    }
                }
                if (!empty($receipt_template['order_change'])){
	                printf('<div class="viwebpos-bill-order-change-wrap"><span class="viwebpos-bill-order-change-label">%s</span><span class="viwebpos-bill-order-change viwebpos-font-bold">%s</span></div>',
		                $settings::kses_post($receipt_template['order_change_label']?? ''),// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		                $settings::kses_post(VIWEBPOS_Plugins_Curcy::curcy_wc_price( $order->get_meta( 'pos_change', true ) ?: 0, array( 'currency' => $order_currency ) )));// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
                if (!empty($receipt_template['order_total'])){
	                printf('<div class="viwebpos-bill-order-total-wrap"><span class="viwebpos-bill-order-total-label">%s</span><span class="viwebpos-bill-order-total viwebpos-font-bold">%s</span></div>',
		                $settings::kses_post($receipt_template['order_total_label']?? ''),// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		                $settings::kses_post($order->get_formatted_order_total()));// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
                ?>
            </div>
            <div class="viwebpos-bill-footer">
            <?php
            if (!empty($receipt_template['footer_message'])){
                printf('<div class="viwebpos-bill-footer-message">%s</div>',$settings::kses_post($receipt_template['footer_message']));// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            ?>
            </div>
        </div>
    </article>
    <p class="viwebpos-page-break"></p>
    <?php
	do_action( 'viwebpos_receipt_after_content', $order , $receipt_template );
}
?>
</body>
</html>
