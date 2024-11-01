jQuery(document).ready(function ($) {
    'use strict';
    if (typeof viwebpos_redis === "undefined") {
        return false;
    }
    $(document.body).on('viwebpos_refresh_cart', function (e) {
        if (viwebpos_redis.is_update_current_cart && viwebpos_data.is_update_current_cart) {
            viwebpos_data.is_update_current_cart = false;
            viwebpos_redis.is_update_current_cart = false;
        }
    });
    $(document.body).on('viwebpos_redis_before_calculate_totals', function (e, cart) {
        if (!viwebpos_redis.pd_enable && !viwebpos_redis.cart_enable) {
            return cart;
        }
        viwebpos_data.is_update_current_cart = true;
        viwebpos_redis.is_update_current_cart = true;
        let items = cart.cart_contents, cart_discount;
        let count = Object.keys(items).length;
        if (!count) {
            cart['redis'] = 0;
            return cart;
        }
        if ((typeof cart['redis'] !== "undefined" && parseInt(cart['redis']))) {
            $.each(cart.cart_contents, function (cart_item_key, cart_item) {
                if (!cart_item.product_id || !cart_item.data) {
                    return true;
                }
                if (typeof cart_item['data']['redis_old_price'] === 'undefined') {
                    cart['redis'] = 0;
                    return false;
                }
                if (typeof cart_item['data']['redis_old_regular_price'] === 'undefined') {
                    cart['redis'] = 0;
                    return false;
                }
            });
            if (parseInt(cart['redis'])) {
                return cart;
            }
        }
        cart.redis_cart_discount = '';
        $.each(cart.cart_contents, function (cart_item_key, cart_item) {
            if (!cart_item.product_id || !cart_item.data) {
                return true;
            }
            if (typeof cart_item['redis_old_price'] !== 'undefined') {
                items[cart_item_key]['price'] = cart_item['redis_old_price'];
            }
            if (typeof cart_item['data']['redis_old_price'] !== 'undefined') {
                items[cart_item_key]['data']['price'] = cart_item['data']['redis_old_price'];
            }
            if (typeof cart_item['data']['redis_old_regular_price'] !== 'undefined') {
                items[cart_item_key]['data']['regular_price'] = cart_item['data']['redis_old_regular_price'];
            }
        });
        cart.cart_contents = items;
        cart['redis'] = 1;
        let get_items = async function () {
            let data = [];
            $.each(cart.cart_contents, function (cart_item_key, cart_item) {
                if (!cart_item.product_id || !cart_item.data) {
                    return true;
                }
                let temp = {
                    product_id: cart_item.product_id,
                    variation_id: cart_item.variation_id,
                    quantity: cart_item.quantity,
                    cart_item_key: cart_item_key,
                };
                if (viwebpos.cart_change_pd_price && typeof cart_item.price !== 'undefined') {
                    temp['only_convert'] = 1;
                    temp['price'] = cart_item.price;
                } else if (cart_item.product_id === 'custom') {
                    temp['only_convert'] = 1;
                    temp['price'] = cart_item.data.price;
                } else {
                    temp['only_convert'] = '';
                    temp['price'] = cart_item.data.price;
                }
                data.push(temp);
            });
            await new Promise(function (resolve) {
                let ajax_data = {
                    data: data,
                    cart_id: cart.id,
                    customer_id: cart.customer ? (cart.customer.id || '') : '',
                    coupons: cart.applied_coupons ? Object.keys(cart.applied_coupons) : '',
                    shipping_address: cart.shipping && Object.keys(cart.shipping).length && cart.pos_shipping_address ? cart.pos_shipping_address : '',
                    viwebpos_nonce: viwebpos.nonce
                };
                ajax_data = $(document.body).triggerHandler('viwebpos_redis_get_discount_data', ajax_data) || ajax_data;
                $.ajax({
                    type: 'post',
                    url: viwebpos.viwebpos_ajax_url.toString().replace('%%endpoint%%', 'viwebpos_redis_get_discount'),
                    data: ajax_data,
                    success: function (response) {
                        if (response.status === 'success') {
                            $.each(response.pd_discount, function (k, v) {
                                if (k && items[k] && items[k]['data']) {
                                    if (v['only_convert']) {
                                        items[k]['redis_old_price'] = items[k]['redis_old_price'] || items[k]['price'];
                                        items[k]['price'] = v['price'];
                                    }
                                    items[k]['data']['redis_old_price'] = items[k]['data'].redis_old_price || items[k]['data']['price'];
                                    items[k]['data']['redis_old_regular_price'] = items[k]['data'].redis_old_regular_price || items[k]['data']['regular_price'];
                                    items[k]['data']['price'] = v['price'];
                                    items[k]['data']['regular_price'] = v['regular_price'] || items[k]['data']['redis_old_regular_price'];
                                }
                            });
                            if (response.cart_discount && Object.keys(response.cart_discount).length) {
                                cart_discount = response.cart_discount;
                            }
                        }
                        items = $(document.body).triggerHandler('viwebpos_redis_get_cart_items', [items, response]) || items;
                        resolve(items, cart_discount);
                    },
                    error: function (err) {
                        console.log(err)
                        resolve(items, cart_discount);
                    }
                })
            });
            cart.cart_contents = items;
            if (cart_discount) {
                cart.redis_cart_discount = cart_discount;
            }
            return cart;
        };
        return get_items();
    });
    $(document.body).on('viwebpos_cart_get_fees', function (e, fees, cart, cart_total = 0) {
        if (!viwebpos_redis.cart_enable || !cart.redis_cart_discount) {
            return fees;
        }
        let default_fee_props = {
            id: '',
            name: '',
            tax_class: '',
            taxable: false,
            amount: 0,
            total: 0,
        };
        $.each(cart.redis_cart_discount, function (k, v) {
            let temp = default_fee_props;
            temp.name = v.title || 'Redis cart discount';
            temp.amount = parseFloat(v.amount);
            temp.taxable = v.taxable ? true : false;
            temp.tax_class = v.tax_class;
            temp.id = k;
            temp['redis_cart_discount'] = 1;
            fees[temp.id] = temp;
        });
        return fees;
    })
});