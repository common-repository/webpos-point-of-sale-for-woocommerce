jQuery(document).ready(function ($) {
    'use strict';
    if (typeof viwebpos_curcy === "undefined") {
        return;
    }
    if (typeof _woocommerce_multi_currency_params !== "undefined") {
        _woocommerce_multi_currency_params.do_not_reload_page = 1;
        _woocommerce_multi_currency_params.switch_by_js = 1;
    }
    $(document.body).on('viwebpos-get-current-url', function (e, button, url) {
        if (!$(button).length || !$(button).closest('.viwebpos-curcy-header-right').length) {
            return url;
        }
        let current_currency = viwebpos.wc_currency;
        if ($(button).data('currency')) {
            current_currency = $(button).data('currency');
        } else if (url.includes('wmc-currency=')) {
            current_currency = url.split('wmc-currency=')[1];
        }
        if (viwebpos.wc_currency != current_currency) {
            viwebpos_data.current_cart['curcy_currency'] = current_currency;
            viwebpos.wc_currency = current_currency;
            let change_curcy = async function () {
                await viwebpos_data.cart_calculate_totals([], false);
                await viwebpos_data.refresh_cart(false);
            };
            change_curcy().then(function () {
                location.reload();
            });
        }
        return '';
    });
    $(document.body).on("wmc_cache_compatible_finish", function (e, data) {
        viwebpos_data.current_cart['curcy_currency'] = data.current_currency;
        viwebpos.wc_currency = data.current_currency;
        viwebpos_curcy.widget = data.shortcodes[0];
        $(document.body).trigger('viwebpos-frontend-load');
    });
    $(document.body).on('viwebpos_curcy_enable', function (e, calculate_totals = false) {
        return calculate_totals ? (viwebpos_curcy.enable && (typeof viwebpos_redis === "undefined" || (!viwebpos_redis.pd_enable && !viwebpos_redis.cart_enable))) : viwebpos_curcy.enable;
    });
    $(document.body).on('viwebpos-bill-of-sale-refresh-bill-tabs', function () {
        if (!viwebpos_data.current_cart['curcy_currency']) {
            viwebpos_data.current_cart['curcy_currency'] = viwebpos.wc_currency_default;
        }
        let current_currency = viwebpos_data.current_cart['curcy_currency'];
        if (current_currency === viwebpos.wc_currency && $(`.viwebpos-checkout-form-content-paid-input_${current_currency}`).length) {
            return;
        }
        viwebpos.wc_currency = current_currency;
        viwebpos_price.wc_currency_symbol = viwebpos_curcy.currencies[current_currency]['symbol'];
        viwebpos_price.wc_get_price_decimals = viwebpos_curcy.currencies[current_currency]['decimals'];
        viwebpos_price.wc_price_format = viwebpos_curcy.currencies[current_currency]['pos'];
        let temp = viwebpos_price.wc_price_format.toString().replace('%1$s', `<span class="viwebpos-currency-symbol">${viwebpos_price.wc_currency_symbol}</span>`);
        $('.viwebpos-checkout-form-content-wrap-total .viwebpos-checkout-form-content-paid-input').parent()
            .html(temp.replace('%2$s', `<input type="text" value="" class="viwebpos-checkout-form-content-paid-input viwebpos-checkout-form-content-paid-input_${current_currency}">`));
        $('.viwebpos-checkout-form-content-wrap-payment .viwebpos-checkout-form-content-paid-input.viwebpos-checkout-form-content-amount-input').parent()
            .html(temp.replace('%2$s', '<input type="text" value="" class="viwebpos-checkout-form-content-amount-input viwebpos-checkout-form-content-paid-input">'));
        $('.viwebpos-checkout-form-content-wrap-coupon .viwebpos-currency-symbol').html(viwebpos_price.wc_currency_symbol);
        $('.viwebpos-checkout-form-content-wrap-shipping .viwebpos-checkout-shipping-amount').replaceWith(`<input type="number" step="${(1 / Math.pow(10, viwebpos_price.wc_get_price_decimals))}" class="viwebpos-checkout-shipping-amount" placeholder="${viwebpos_price.wc_currency_symbol}" value="">`);
        let html = '<select class="viwebpos-checkout-add-discount-type vi-ui dropdown">';
        html += `<option  value="1" >${viwebpos_text.discount_fixed_title.replace('%1$s', viwebpos_price.wc_currency_symbol)}</option>`;
        html += `<option  value="0">${viwebpos_text.discount_percentage_title}</option>`;
        html += '</select>';
        $('.viwebpos-checkout-form-content-wrap-coupon .viwebpos-checkout-add-discount-type').replaceWith(html);
        $('.viwebpos-checkout-add-discount-type').off().dropdown();
    });
    $(document.body).on('viwebpos_redis_get_cart_items', function (e, items, data) {
        if (typeof data.payments !== "undefined" && typeof viwebpos_data.current_cart.payments !== "undefined") {
            viwebpos_data.current_cart.curcy_old_payments_paid = viwebpos_data.current_cart.curcy_old_payments_paid || viwebpos_data.current_cart.payments.paid;
            viwebpos_data.current_cart.payments.paid = data.payments;
        }
        return items;
    });
    $(document.body).on('viwebpos_redis_get_discount_data', function (e, data) {
        if (viwebpos_data.current_cart.curcy_currency) {
            data['current_currency'] = viwebpos_data.current_cart.curcy_currency;
        } else if (typeof viwebpos_data.current_cart.order_data !== "undefined" && viwebpos_data.current_cart.order_data.currency) {
            data['current_currency'] = viwebpos_data.current_cart.order_data.currency;
        } else {
            data['current_currency'] = viwebpos.wc_currency_default;
        }
        if (data['current_currency']) {
            viwebpos.wc_currency = data['current_currency'];
            viwebpos_price.wc_currency_symbol = viwebpos_curcy.currencies[data['current_currency']]['symbol'];
            viwebpos_price.wc_get_price_decimals = viwebpos_curcy.currencies[data['current_currency']]['decimals'];
            viwebpos_price.wc_price_format = viwebpos_curcy.currencies[data['current_currency']]['pos'];
        }
        if (typeof viwebpos_data.current_cart.payments !== "undefined" && viwebpos_data.current_cart.payments.is_paid) {
            if (typeof viwebpos_data.current_cart.curcy_old_payments_paid !== "undefined") {
                viwebpos_data.current_cart.payments.paid = viwebpos_data.current_cart.curcy_old_payments_paid;
            }
            data['payments'] = viwebpos_data.current_cart.payments.paid;
        }
        return data;
    });
    $(document.body).on('viwebpos_get_data_products', function (e, result, database, key, limit, page, index) {
        if (!$(document.body).triggerHandler('viwebpos_curcy_enable')) {
            return result;
        }
        let is_online = viwebpos_data.is_online;
        let count = result.length;
        if (!count) {
            return result;
        }
        let current_currency = viwebpos_data.current_cart.curcy_currency || viwebpos.wc_currency,
            cart = viwebpos_data.current_cart;
        if (!is_online || (!viwebpos_curcy.fix_price_enable && viwebpos.wc_currency_default === current_currency)) {
            viwebpos.wc_currency = viwebpos.wc_currency_default;
            viwebpos_price.wc_currency_symbol = viwebpos.wc_currency_symbol_default;
            viwebpos_price.wc_get_price_decimals = viwebpos.wc_get_price_decimals_default;
            viwebpos_price.wc_price_format = viwebpos.wc_price_format_default;
            viwebpos_data.current_cart.curcy_currency = viwebpos.wc_currency;
            return result;
        }
        viwebpos.wc_currency = current_currency;
        viwebpos_price.wc_currency_symbol = viwebpos_curcy.currencies[current_currency]['symbol'];
        viwebpos_price.wc_get_price_decimals = viwebpos_curcy.currencies[current_currency]['decimals'];
        viwebpos_price.wc_price_format = viwebpos_curcy.currencies[current_currency]['pos'];
        let get_items = async function () {
            let temp = {}, data = [];
            $.each(result, function (k, v) {
                if (!v.id) {
                    return true;
                }
                temp[v.id] = v;
                data.push(v.id);
            });
            if (!data.length) {
                return result;
            }
            let cart_data = [];
            $.each(cart.cart_contents, function (cart_item_key, cart_item) {
                if (!cart_item.product_id || !cart_item.data) {
                    return true;
                }
                cart_data.push({
                    product_id: cart_item.product_id,
                    variation_id: cart_item.variation_id,
                    quantity: cart_item.quantity,
                    cart_item_key: cart_item_key,
                    price: cart_item.data.price,
                    only_convert: (viwebpos.cart_change_pd_price && typeof cart_item.price !== 'undefined') || cart_item.product_id === 'custom',
                });
            });
            await new Promise(function (resolve) {
                let ajax_data = {
                    data: data,
                    cart_data: cart_data,
                    cart_id: cart.id,
                    customer_id: cart.customer ? (cart.customer.id || '') : '',
                    coupons: cart.applied_coupons ? Object.keys(cart.applied_coupons) : '',
                    shipping_address: cart.shipping && Object.keys(cart.shipping).length && cart.pos_shipping_address ? cart.pos_shipping_address : '',
                    current_currency: current_currency,
                    viwebpos_nonce: viwebpos.nonce
                };
                ajax_data = $(document.body).triggerHandler('viwebpos_cury_get_price_data', ajax_data) || ajax_data;
                $.ajax({
                    type: 'post',
                    url: viwebpos.viwebpos_ajax_url.toString().replace('%%endpoint%%', 'viwebpos_cury_get_price'),
                    data: ajax_data,
                    success: function (response) {
                        if (response.status === 'success') {
                            $.each(response.pd_price, function (k, v) {
                                if (k && temp[k]) {
                                    temp[k]['curcy_old_price'] = temp[k].curcy_old_price || temp[k]['price'];
                                    temp[k]['curcy_old_regular_price'] = temp[k].curcy_old_regular_price || temp[k]['regular_price'];
                                    temp[k]['price'] = v['price'];
                                    temp[k]['regular_price'] = v['regular_price'];
                                }
                            });
                        }
                        temp = $(document.body).triggerHandler('viwebpos_cury_get_data_products', [temp, response, database, key, limit, page, index]) || temp;
                        resolve(temp);
                    },
                    error: function (err) {
                        console.log(err);
                        resolve(temp);
                    }
                })
            });
            if (Object.keys(temp).length) {
                $.map(result, function (k, v) {
                    if (!v.id) {
                        return true;
                    }
                    if (typeof temp[v.id] !== "undefined") {
                        result[k] = temp[v.id];
                    }
                })
            }
            return result;
        };
        return get_items();
    });
    $(document.body).on('viwebpos_refresh_cart', function (e) {
        if (viwebpos_curcy.is_update_current_cart && viwebpos_data.is_update_current_cart) {
            viwebpos_data.is_update_current_cart = false;
            viwebpos_curcy.is_update_current_cart = false;
        }
    });
    $(document.body).on('viwebpos_curcy_before_calculate_totals', function (e, cart) {
        if (!$(document.body).triggerHandler('viwebpos_curcy_enable', true)) {
            return cart;
        }
        viwebpos_data.is_update_current_cart = true;
        viwebpos_curcy.is_update_current_cart = true;
        let items = cart.cart_contents;
        let count = Object.keys(items).length;
        if (!count) {
            cart['curcy'] = 0;
            return cart;
        }
        if ((typeof cart['curcy'] !== "undefined" && parseInt(cart['curcy']))) {
            $.each(cart.cart_contents, function (cart_item_key, cart_item) {
                if (!cart_item.product_id || !cart_item.data) {
                    return true;
                }
                if (typeof cart_item['data']['curcy_old_price'] === 'undefined') {
                    cart['curcy'] = 0;
                    return false;
                }
                if (typeof cart_item['data']['curcy_old_regular_price'] === 'undefined') {
                    cart['curcy'] = 0;
                    return false;
                }
            });
            if (parseInt(cart['curcy'])) {
                return cart;
            }
        }
        if (!cart['curcy_currency']) {
            cart['curcy_currency'] = viwebpos_data.current_cart.curcy_currency || viwebpos.wc_currency;
        }
        let current_currency = cart.curcy_currency;
        $.each(cart.cart_contents, function (cart_item_key, cart_item) {
            if (!cart_item.product_id || !cart_item.data) {
                return true;
            }
            if (typeof cart_item['curcy_old_price'] !== 'undefined') {
                items[cart_item_key]['price'] = cart_item['curcy_old_price'];
            }
            if (typeof cart_item['data']['curcy_old_price'] !== 'undefined') {
                items[cart_item_key]['data']['price'] = cart_item['data']['curcy_old_price'];
            }
            if (typeof cart_item['data']['curcy_old_regular_price'] !== 'undefined') {
                items[cart_item_key]['data']['regular_price'] = cart_item['data']['curcy_old_regular_price'];
            }
        });
        cart.cart_contents = items;
        if ((!viwebpos_curcy.fix_price_enable && viwebpos.wc_currency_default === current_currency)) {
            viwebpos.wc_currency = viwebpos.wc_currency_default;
            viwebpos_price.wc_currency_symbol = viwebpos.wc_currency_symbol_default;
            viwebpos_price.wc_get_price_decimals = viwebpos.wc_get_price_decimals_default;
            viwebpos_price.wc_price_format = viwebpos.wc_price_format_default;
            viwebpos_data.current_cart.curcy_currency = viwebpos.wc_currency;
            return cart;
        }
        viwebpos.wc_currency = current_currency;
        viwebpos_price.wc_currency_symbol = viwebpos_curcy.currencies[current_currency]['symbol'];
        viwebpos_price.wc_get_price_decimals = viwebpos_curcy.currencies[current_currency]['decimals'];
        viwebpos_price.wc_price_format = viwebpos_curcy.currencies[current_currency]['pos'];
        cart['curcy'] = 1;
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
                    current_currency: current_currency,
                    viwebpos_nonce: viwebpos.nonce
                };
                if (typeof viwebpos_data.current_cart.payments !== "undefined" && viwebpos_data.current_cart.payments.is_paid) {
                    if (typeof viwebpos_data.current_cart.curcy_old_payments_paid !== "undefined") {
                        viwebpos_data.current_cart.payments.paid = viwebpos_data.current_cart.curcy_old_payments_paid;
                    }
                    ajax_data['payments'] = viwebpos_data.current_cart.payments.paid;
                }
                ajax_data = $(document.body).triggerHandler('viwebpos_cury_cart_item_price_data', ajax_data) || ajax_data;
                $.ajax({
                    type: 'post',
                    url: viwebpos.viwebpos_ajax_url.toString().replace('%%endpoint%%', 'viwebpos_cury_cart_item_price'),
                    data: ajax_data,
                    success: function (response) {
                        if (response.status === 'success') {
                            $.each(response.pd_price, function (k, v) {
                                if (k && items[k] && items[k]['data']) {
                                    if (v.only_convert) {
                                        items[k]['curcy_old_price'] = items[k].curcy_old_price || items[k]['price'];
                                        items[k]['price'] = v['price'];
                                    }
                                    items[k]['data']['curcy_old_price'] = items[k]['data'].curcy_old_price || items[k]['data']['price'];
                                    items[k]['data']['curcy_old_regular_price'] = items[k]['data'].curcy_old_regular_price || items[k]['data']['regular_price'];
                                    items[k]['data']['price'] = v['price'];
                                    items[k]['data']['regular_price'] = v['regular_price'] || items[k]['data']['curcy_old_regular_price'];
                                }
                            });
                            if (typeof response.payments !== "undefined" && typeof viwebpos_data.current_cart.payments !== "undefined") {
                                viwebpos_data.current_cart.curcy_old_payments_paid = viwebpos_data.current_cart.curcy_old_payments_paid || viwebpos_data.current_cart.payments.paid;
                                viwebpos_data.current_cart.payments.paid = response.payments;
                            }
                        }
                        items = $(document.body).triggerHandler('viwebpos_cury_get_cart_items', [items, response]) || items;
                        resolve(items);
                    },
                    error: function (err) {
                        console.log(err);
                        resolve(items);
                    }
                })
            });
            cart.cart_contents = items;
            return cart;
        };
        return get_items();
    });
    $(document.body).on('viwebpos_set_cart_after_created_order', function (e, cart) {
        viwebpos.wc_currency = viwebpos.wc_currency_default;
        viwebpos_price.wc_currency_symbol = viwebpos.wc_currency_symbol_default;
        viwebpos_price.wc_get_price_decimals = viwebpos.wc_get_price_decimals_default;
        viwebpos_price.wc_price_format = viwebpos.wc_price_format_default;
        cart['curcy_currency'] = viwebpos.wc_currency;
        return cart;
    });
    $(document.body).on('viwebpos-frontend-before-load', function (e, page = '') {
        $('.viwebpos-curcy-header-right-wrap').removeClass('viwebpos-curcy-header-right-wrap').find('.viwebpos-curcy-header-right').remove();
        if (!viwebpos_curcy.enable) {
            return false;
        }
        if (['orders', 'transactions', 'customers', 'tables'].indexOf(page) === -1) {
            $('.viwebpos-header-right-wrap').addClass('viwebpos-curcy-header-right-wrap').prepend(`<div class="viwebpos-curcy-header-right">${viwebpos_curcy.widget}</div>`);
        } else {
            viwebpos.wc_currency = viwebpos.wc_currency_default;
            viwebpos_price.wc_currency_symbol = viwebpos.wc_currency_symbol_default;
            viwebpos_price.wc_get_price_decimals = viwebpos.wc_get_price_decimals_default;
            viwebpos_price.wc_price_format = viwebpos.wc_price_format_default;
        }
    });
    $(document).on('viwebpos_get_price_html_decimals', function (e, result, currency_symbol) {
        if (typeof viwebpos_curcy === "undefined" || !currency_symbol) {
            return result;
        }
        let currencies = viwebpos_curcy.currencies;
        if (!currencies || !Object.keys(currencies).length) {
            return result;
        }
        $.each(currencies, function (k, v) {
            if (typeof v['symbol'] !== "undefined" && v['symbol'] === currency_symbol) {
                result = v['decimals'] ? v['decimals'] : 0;
                return false;
            }
        });
        return result;
    });
    $(document).on('viwebpos_get_price_html_format', function (e, result, currency_symbol) {
        if (typeof viwebpos_curcy === "undefined" || !currency_symbol) {
            return result;
        }
        let currencies = viwebpos_curcy.currencies;
        if (!currencies || !Object.keys(currencies).length) {
            return result;
        }
        $.each(currencies, function (k, v) {
            if (typeof v['symbol'] !== "undefined" && v['symbol'] === currency_symbol) {
                result = v['pos'] || result;
                return false;
            }
        });
        return result;
    });
});