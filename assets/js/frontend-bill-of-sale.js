(function ($) {
    'use strict';
    let product_style = 'basic';
    $(document).ready(function () {
        'use strict';
        let search_product, search_product_result = {};
        let search_customer, search_customer_result = {};
        let get_change;
        let is_mobile = window.screen.width <= 800;
        init();
        carts_event();
        customer_event();
        checkout_event();

        function init() {
            $(document).on('click', '.viwebpos-add-custom-product-wrap', function (e) {
                $('.viwebpos-popup-wrap-add-product').removeClass('viwebpos-popup-wrap-hidden').addClass('viwebpos-popup-wrap-show');
                $('.viwebpos-popup-wrap-add-product').find('.viwebpos-popup-bt-loading').removeClass('viwebpos-popup-bt-loading');
                $('.viwebpos-popup-wrap-add-product').find('.viwebpos-custom-product-name').trigger('focus');
            });
            $(document.body).on('viwebpos_popup_after_close', function (e, popup) {
                if (popup.hasClass('viwebpos-popup-wrap-add-product') || popup.hasClass('viwebpos-popup-wrap-print-receipt')) {
                    popup.find('input, select').each(function () {
                        $(this).val($(this).data('value_default') || '');
                    });
                }
            });
            $(document).on('click', '.viwebpos-product-qty-action', function () {
                let wrap = $(this).closest('.viwebpos-product-qty-wrap');
                let input_qty = wrap.find('.viwebpos-product-qty-value');
                let qty = input_qty.val() || 0;
                if ($(this).hasClass('viwebpos-product-qty-minus')) {
                    qty--;
                } else {
                    qty++;
                }
                input_qty.val(qty).trigger('change');
            });
            $(document).on('click', 'body', function () {
                $(document.body).trigger('viwebpos_search_products_close');
                $(document.body).trigger('viwebpos_checkout_search_customers_close');
            });
            $(document).on('click', '.viwebpos-header-action-icons-auto_print', function (e) {
                $(document.body).trigger('viwebpos_set_auto_print_receipt_after_checkout', [$(this).hasClass('viwebpos-header-action-icons-auto_print-disable')]);
            });
        }

        function carts_event() {
            $(document.body).on('viwebpos_search_products_close', function () {
                search_product_result = {};
                $('.viwebpos-search-product').val(null);
                $('.viwebpos-search-product-result-wrap').remove();
            });
            $(document.body).on('viwebpos_search_products_init', function (e, products) {
                let html, wrap;
                if (!$('.viwebpos-search-product-result-wrap').length) {
                    $('.viwebpos-header-search-wrap').append('<div class="viwebpos-search-product-result-wrap"></div>');
                }
                wrap = $('.viwebpos-search-product-result-wrap');
                let next_page = wrap.find('.viwebpos-search-product-loadmore').length;
                if (!next_page || !wrap.find('.viwebpos-search-product-loadmore').data('next_page')) {
                    wrap.html('');
                }
                wrap.find('.viwebpos-search-product-loadmore').remove();
                if (products.length && products.at(-1)['next_page']) {
                    next_page = products.at(-1)['next_page'];
                    products.pop();
                } else {
                    next_page = false;
                }
                $.each(products, function (k, v) {
                    let is_purchase = v.is_purchasable,
                        stock = v.stock ? v.stock : '',
                        price_html;
                    html = '';
                    html += '<div class="viwebpos-search-product-result' + (is_purchase ? '' : ' viwebpos-search-product-disabled') + `"  title="${v.name}">`;
                    html += '<div class="viwebpos-search-product-image">' + v.image + '</div>';
                    html += '<div class="viwebpos-search-product-info">';
                    html += '<div class="viwebpos-search-product-info1' + (v.price ? '' : ' viwebpos-search-product-not_price') + '" >';
                    html += '<div class="viwebpos-search-product-name">' + v.name + '</div>';
                    if (v.price) {
                        let regular_price_html = parseFloat(v.regular_price) > parseFloat(v.price) ? viwebpos_get_product_price(v, 1, v.regular_price) : '';
                        price_html = viwebpos_get_product_price(v);
                        price_html = regular_price_html ? '<del><span class="amount">' + regular_price_html + '</span></del>' + '<ins><span class="amount">' + price_html + '</span></ins>' : price_html;
                    }
                    if (price_html) {
                        html += '<div class="viwebpos-search-product-price">' + price_html + '</div>';
                    }
                    html += '</div>';
                    html += '<div class="viwebpos-search-product-info2">';
                    if (v.barcode) {
                        html += '<div class="viwebpos-search-product-barcode">' + v.barcode + '</div>';
                    }
                    if (stock) {
                        html += '<div class="viwebpos-search-product-stock">' + (viwebpos.product_stock_title || 'Stock') + ':&ensp;' + stock + '</div>';
                    } else if (v.stock_status) {
                        html += '<div class="viwebpos-search-product-stock">' + v.stock_status + '</div>';
                    }
                    if (v.attribute_html) {
                        html += '<div class="viwebpos-search-product-swatches">' + v.attribute_html + '</div>';
                    }
                    html += '</div></div>';
                    wrap.append(html);
                    wrap.find('.viwebpos-search-product-result').last().data('product_data', v);
                    wrap.find('.viwebpos-attribute-options option[value=""]').prop('disabled', true);
                });
                if (!next_page) {
                    if (!$('.viwebpos-search-product-result').length) {
                        wrap.html('<div class="viwebpos-search-product-empty">' + (viwebpos.search_product_empty || 'No product found') + '</div>');
                    }
                    if (viwebpos_data.data_settings_bill && viwebpos_data.data_settings_bill['auto_atc'] &&
                        $('.viwebpos-search-product-result').length === 1 &&
                        !$('.viwebpos-search-product-disabled').length &&
                        !$('.viwebpos-search-product-swatches select').length) {
                        $('.viwebpos-search-product-result').trigger('click');
                    }
                } else {
                    wrap.append(`<div class="viwebpos-search-product-loadmore" data-next_page="${next_page}"></div>`);
                    $('.viwebpos-search-product-result-wrap').on('scroll', function () {
                        if (!$('.viwebpos-search-product-loadmore:not(.viwebpos-search-product-loadmore-loading)').length) {
                            return false;
                        }
                        let a = $('.viwebpos-search-product-loadmore:not(.viwebpos-search-product-loadmore-loading)')[0], b = $(this)[0];
                        let check = a.getBoundingClientRect().top - b.getBoundingClientRect().height;
                        if (check < b.getBoundingClientRect().top) {
                            $('.viwebpos-search-product-loadmore').addClass('viwebpos-search-product-loadmore-loading');
                            $(document.body).trigger('viwebpos_search_products', [$('.viwebpos-search-product').val().toString().trim(), parseInt($('.viwebpos-search-product-loadmore').data('next_page'))]);
                        }
                    });
                }
            });
            $(document).on('click', '.viwebpos-attribute-options', function (e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                if ($(this).hasClass('viwebpos-attribute-options-changed')) {
                    $(this).removeClass('viwebpos-attribute-options-changed');
                } else {
                    $(this).closest('.viwebpos-search-product-result').addClass('viwebpos-search-product-result-loading');
                }
                $(this).on('change', function (e) {
                    if (!$(this).val()) {
                        $(this).val($(this).find('option').eq(1).val())
                    }
                    $(this).addClass('viwebpos-attribute-options-changed').closest('.viwebpos-search-product-result').removeClass('viwebpos-search-product-result-loading');
                });
            });
            if (typeof onScan !== 'undefined') {
                onScan.attachTo(document, {
                    ignoreIfFocusOn: '.viwebpos-wrap input:not(.viwebpos-search-product)',
                    onScan: function (barcode, qty) {
                        if (!$('.viwebpos-search-input.viwebpos-search-product').length) {
                            return false;
                        }
                        $('.viwebpos-search-input.viwebpos-search-product').addClass('viwebpos-search-product-scanned').trigger('focus').val(barcode);
                    },
                });
            }
            $(document.body).on('viwebpos_search_products', function (e, key = '', page = 1) {
                if (search_product) {
                    clearTimeout(search_product);
                }
                search_product = setTimeout(function (key_search, next_page) {
                    if (!key_search && !is_mobile) {
                        $(document.body).trigger('viwebpos_search_products_close');
                        return false;
                    }
                    if (!$('.viwebpos-search-product-result-wrap').length) {
                        $('.viwebpos-header-search-wrap').append('<div class="viwebpos-search-product-result-wrap"></div>');
                    }
                    if (!$('.viwebpos-search-product-result-wrap .viwebpos-search-product-loadmore').length) {
                        $('.viwebpos-search-product-result-wrap').append('<div class="viwebpos-search-product-loadmore"></div>');
                    }
                    let products = $(document.body).triggerHandler('viwebpos_search_data', ['products', key_search, 30, next_page]);
                    if (typeof products.then === 'function') {
                        products.then(function (result) {
                            if (page === 1) {
                                search_product_result[key_search] = {...result};
                                $('.viwebpos-search-product-loadmore').remove();
                            }
                            $(document.body).trigger('viwebpos_search_products_init', [result]);
                        })
                    } else {
                        if (page === 1) {
                            search_product_result[key_search] = {...products};
                            $('.viwebpos-search-product-loadmore').remove();
                        }
                        $(document.body).trigger('viwebpos_search_products_init', [products]);
                    }
                }, 100, key, page);
            });
            $(document).on('click keyup', '.viwebpos-search-product', function (e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                $(document.body).trigger('viwebpos_checkout_search_customers_close');
                let $this = $(this);
                let val = $this.val();
                if (search_product) {
                    clearTimeout(search_product);
                }
                $this.data('old_key', val);
                if (search_product_result[val]) {
                    $(document.body).trigger('viwebpos_search_products_init', [Object.values(search_product_result[val])]);
                    return false;
                }
                $(document.body).trigger('viwebpos_search_products', val.toString().trim());
            });
            $(document).on('change', '.viwebpos-cart-contents .viwebpos-attribute-options', function (e) {
                let variation = {};
                $(this).closest('.viwebpos-cart-item-swatches').find('select, input').each(function () {
                    let name = $(this).attr('name');
                    if (name) {
                        variation[name] = $(this).val();
                    }
                });
                $(document.body).trigger('viwebpos_update_cart_variation', [$(this).closest('.viwebpos-cart-item-wrap').data('cart_item_key') || '', variation]);
            });
            $(document).on('click', '.viwebpos-search-product-result:not(.viwebpos-search-product-result-loading)', function (e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                let form = $(this), check_attribute = true;
                $(document.body).trigger('villatheme_show_message_timeout', [$('.villatheme-show-message-message-product-adding'), 1]);
                if (form.hasClass('viwebpos-search-product-disabled')) {
                    $(document.body).trigger('villatheme_show_message', [viwebpos.error_title, ['error', 'product-adding'], viwebpos.cannot_be_purchased_message, false, 4500]);
                    return false;
                }
                form.find('.viwebpos-attribute-options').each(function (k, item) {
                    if (!$(item).val()) {
                        check_attribute = false;
                        return false;
                    }
                });
                if (!check_attribute) {
                    $(document.body).trigger('villatheme_show_message', [viwebpos.error_title, ['error', 'product-adding'], viwebpos.make_a_selection_text.replace('{product_name}', form.attr('title')), true, 4500]);
                }
                let variation = {};
                form.find('select, input').each(function () {
                    let name = $(this).attr('name');
                    if (name) {
                        variation[name] = $(this).val();
                    }
                });
                let product = form.data('product_data');
                $(document.body).trigger('viwebpos_add_to_cart', [product, variation, 1]);
            });
            $(document).on('click', '.viwebpos-popup-bt-add-product:not(.viwebpos-popup-bt-loading)', function (e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                let form = $(this).closest('.viwebpos-popup-wrap-add-product'),
                    product = {
                        type: 'custom',
                        barcode: '',
                        taxable: true,
                        stock: null,
                        is_in_stock: 1
                    };
                if (!form.find('.viwebpos-custom-product-name').val()) {
                    $(document.body).trigger('villatheme_show_message', [viwebpos.custom_product_name_empty, ['error', 'product-adding'], '', false, 4500]);
                    form.find('.viwebpos-custom-product-name').trigger('focus');
                    return false;
                }
                product.name = form.find('.viwebpos-custom-product-name').val();
                if (!form.find('.viwebpos-price-input-value').val()) {
                    $(document.body).trigger('villatheme_show_message', [viwebpos.custom_product_price_empty, ['error', 'product-adding'], '', false, 4500]);
                    form.find('.viwebpos-price-input-value').trigger('focus');
                    return false;
                }
                product.price = form.find('.viwebpos-price-input-value').val();
                if (parseFloat(form.find('.viwebpos-product-qty-value').val() || 0) <= 0) {
                    $(document.body).trigger('villatheme_show_message', [viwebpos.custom_product_qty, ['error', 'product-adding'], '', false, 4500]);
                    form.find('.viwebpos-product-qty-value').trigger('focus');
                    return false;
                }
                $(this).addClass('viwebpos-popup-bt-loading');
                $(document.body).trigger('viwebpos_add_to_cart', [product, {}, parseFloat(form.find('.viwebpos-product-qty-value').val())]);
            });
            $(document).on('mouseenter', '.viwebpos-search-product-result', function () {
                $(this).addClass('viwebpos-search-product-result-mouseenter');
            }).on('mouseleave', '.viwebpos-search-product-result', function () {
                $(this).removeClass('viwebpos-search-product-result-mouseenter');
            });
            $(document).on('change', '.viwebpos-cart-contents .viwebpos-cart-item-qty', function (e) {
                let val = parseFloat($(this).val());
                let old_qty = parseFloat($(this).data('old_qty') || (val - 1));
                $(this).data('old_qty', val);
                if (val !== old_qty) {
                    $(document.body).trigger('viwebpos_update_cart_quantity', [$(this).closest('.viwebpos-cart-item-wrap').data('cart_item_key') || '', val]);
                }
            });
            $(document).on('click', '.viwebpos-cart-contents .viwebpos-cart-item-bt-remove', function (e) {
                $(document.body).trigger('viwebpos_update_cart_quantity', [$(this).closest('.viwebpos-cart-item-wrap').data('cart_item_key') || '', 0]);
            });
            $(document).on('click', '.viwebpos-cart-contents .viwebpos-cart-item-bt-add-note', function (e) {
                $(this).closest('.viwebpos-cart-item-wrap').addClass('viwebpos-cart-item-wrap-has-note').next('.viwebpos-cart-item-wrap-note').find('input').trigger('focus');
            });
            $(document).on('change', '.viwebpos-cart-item-note input', function () {
                $(document.body).trigger('viwebpos_add_order_note', [$(this).val(), $(this).closest('.viwebpos-cart-item-wrap').data('cart_item_key') || '']);
            });
            $(document).on('mouseenter', '.viwebpos-cart-item-wrap-note', function () {
                $(this).prev('.viwebpos-cart-item-wrap').addClass('viwebpos-cart-item-wrap-mouseenter');
            }).on('mouseleave', '.viwebpos-cart-item-wrap-note', function () {
                $('.viwebpos-cart-item-wrap-mouseenter').removeClass('viwebpos-cart-item-wrap-mouseenter');
            });
            $(document).on('change', '.viwebpos-cart-contents-order-textarea', function () {
                $('.viwebpos-cart-contents-order-note').html($(this).val());
                $(document.body).trigger('viwebpos_add_order_note', [$(this).val()]);
            });
            $(document).on('mouseenter', '.viwebpos-cart-contents-add-note-icon', function () {
                $('.viwebpos-cart-contents-order-textarea').trigger('focus');
            });
            $(document).on('click', '.viwebpos-cart-contents-reset-icon:not(.viwebpos-hidden)', function () {
                $(document.body).trigger('viwebpos_remove_all_cart_items');
            });
        }

        function customer_event() {
            $(document.body).on('viwebpos_checkout_search_customers_close', function () {
                search_customer_result = {};
                $('.viwebpos-checkout-form-customer-wrap .viwebpos-checkout-search-customer:not(.viwebpos-checkout-search-customer-found)').val(null);
                $('.viwebpos-checkout-form-customer-wrap .viwebpos-search-customers-result-wrap').remove();
            });
            $(document).on('click', '.viwebpos-checkout-form-customer-remove-icon', function () {
                $(document.body).trigger('viwebpos_set_cart_customer');
            });
            $(document).on('click', '.viwebpos-checkout-form-customer-search-icon', function () {
                $('.viwebpos-popup-wrap-add-customer').removeClass('viwebpos-popup-wrap-hidden').addClass('viwebpos-popup-wrap-show');
                $('.viwebpos-popup-wrap-add-customer').find('.viwebpos-popup-bt-loading').removeClass('viwebpos-popup-bt-loading');
                $('.viwebpos-popup-wrap-add-customer').find('.viwebpos-popup-content-value-error').removeClass('viwebpos-popup-content-value-error');
                $('.viwebpos-popup-wrap-add-customer').find('input, select').each(function () {
                    if ($(this).is('select') && $(this).closest('.vi-ui.dropdown').length) {
                        $(this).val($(this).data('value_default') || '').trigger('change');
                    } else {
                        $(this).val($(this).data('value_default') || '');
                    }
                });
                $('.viwebpos-popup-wrap-add-customer').find('.viwebpos-add-customer-first-name').trigger('focus');
            });
            $(document).on('change', '.viwebpos-popup-wrap-add-customer .viwebpos-add-customer-country select', function () {
                let form = $(this).closest('.viwebpos-popup-wrap-add-customer'),
                    country = $(this).val();
                let state = form.find('.viwebpos-customer-search-state').length ? form.find('.viwebpos-customer-search-state') : form.find('.viwebpos-add-customer-state');
                $(document.body).trigger('viwebpos_customer_state_init', [country, state, '<input type="text" class="viwebpos-add-customer-state" data-value_default="" value="">']);
            });
            $(document).on('change', '.viwebpos-popup-wrap-add-customer .viwebpos-add-customer-email', function () {
                let form = $(this).closest('.viwebpos-popup-wrap-add-customer');
                if (!$(document.body).triggerHandler('viwebpos_check_is_email', form.find('.viwebpos-add-customer-email').val())) {
                    $(document.body).trigger('villatheme_show_message', [viwebpos.error_customer_invalid_email, ['error', 'customer-adding'], '', false, 4500]);
                    form.find('.viwebpos-add-customer-email').addClass('viwebpos-popup-content-value-error').trigger('focus');
                    return false;
                }
                let customer = {
                    email: form.find('.viwebpos-add-customer-email').val() || ''
                };
                $(document.body).trigger('viwebpos_add_new_customer', [customer, 'check_email']);
            });
            $(document).on('click', '.viwebpos-popup-bt-add-customer:not(.viwebpos-popup-bt-loading)', function (e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                let form = $(this).closest('.viwebpos-popup-wrap-add-customer'), button = $(this);
                $(document.body).trigger('villatheme_show_message_timeout', [$('.villatheme-show-message-message-customer-adding'), 1]);
                form.addClass('viwebpos-popup-wrap-loading').find('.viwebpos-popup-content-value-error').removeClass('viwebpos-popup-content-value-error');
                button.addClass('viwebpos-popup-bt-loading');
                let fields = {
                    first_name: form.find('.viwebpos-add-customer-first-name'),
                    last_name: form.find('.viwebpos-add-customer-last-name'),
                    email: form.find('.viwebpos-add-customer-email'),
                    phone: form.find('.viwebpos-add-customer-phone'),
                    address_1: form.find('.viwebpos-add-customer-address1'),
                    postcode: form.find('.viwebpos-add-customer-postcode'),
                    city: form.find('.viwebpos-add-customer-city'),
                    state: form.find('.viwebpos-add-customer-state'),
                    country: form.find('.viwebpos-add-customer-country'),
                };
                let errors = $(document.body).triggerHandler('viwebpos_customer_fields_validate', [fields, 'viwebpos-popup-content-value-error']),
                    message_errors = [];
                if (errors && errors.length) {
                    $.each(errors, function (k, v) {
                        if (v) {
                            message_errors.push({
                                message: v,
                                status: ['error', 'customer-adding']
                            })
                        }
                    });
                    $(document.body).trigger('villatheme_show_messages', [message_errors]);
                    form.find('.viwebpos-popup-content-value-error').eq(0).trigger('focus');
                    return false;
                }
                let customer = {
                    email: form.find('.viwebpos-add-customer-email').val() || '',
                    first_name: form.find('.viwebpos-add-customer-first-name').val() || '',
                    last_name: form.find('.viwebpos-add-customer-last-name').val() || '',
                    billing_address: {
                        address_1: form.find('.viwebpos-add-customer-address1').val(),
                        city: form.find('.viwebpos-add-customer-city').val(),
                        country: form.find('.viwebpos-add-customer-country').val(),
                        state: form.find('.viwebpos-add-customer-state').val(),
                        postcode: form.find('.viwebpos-add-customer-postcode').val(),
                        phone: form.find('.viwebpos-add-customer-phone').val() || '',
                    }
                };
                if (form.find('.viwebpos-add-customer-country select').length) {
                    customer.billing_address.country = form.find('.viwebpos-add-customer-country select').val();
                }
                if (form.find('.viwebpos-add-customer-state select').length) {
                    customer.billing_address.state = form.find('.viwebpos-add-customer-state select').val();
                }
                $(document.body).trigger('viwebpos_add_new_customer', [customer, true]);
            });
            $(document).on('click', '.viwebpos-search-customer-result:not(.viwebpos-search-customer-result-loading)', function (e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                let data = $(this).data();
                if (!data['customer_id'] || !data['customer_data']) {
                    return false;
                }
                $(document.body).trigger('viwebpos_set_cart_customer', [data['customer_data']]);
            });
            $(document.body).on('viwebpos_checkout_search_customers_init', function (e, customers) {
                let customer_count = 0, wrap;
                if (!$('.viwebpos-checkout-form-customer-wrap .viwebpos-search-customers-result-wrap').length) {
                    $('.viwebpos-checkout-form-customer-wrap .viwebpos-checkout-form-customer').append('<div class="viwebpos-search-customers-result-wrap"></div>');
                }
                wrap = $('.viwebpos-checkout-form-customer-wrap .viwebpos-search-customers-result-wrap');
                wrap.html('');
                $.each(customers, function (k, v) {
                    let html = '';
                    let customer_name = v.first_name || v.last_name ? (v.first_name + ' ' + v.last_name) : v.username;
                    html += '<div class="viwebpos-search-customer-result" title="' + v.username + '">';
                    html += '<div class="viwebpos-search-customer-info">';
                    html += '<div class="viwebpos-search-customer-name">' + customer_name + '</div>';
                    html += '<div class="viwebpos-search-customer-email">' + v.email + '</div>';
                    html += '</div>';
                    html += '<div class="viwebpos-search-customer-info">';
                    if (v.phone) {
                        html += '<div class="viwebpos-search-customer-phone">' + viwebpos.customer_phone + ':&ensp;' + v.phone + '</div>';
                    } else {
                        html += '<div class="viwebpos-search-customer-id">' + viwebpos.customer_id + ':&ensp;' + v.id + '</div>';
                    }
                    html += '</div></div>';
                    html = $(html);
                    html.data({'customer_id': v.id, 'customer_data': v});
                    wrap.append(html);
                    customer_count++;
                });
                if (!customer_count) {
                    wrap.html('<div class="viwebpos-search-customer-empty">' + (viwebpos.search_customer_empty || 'No cutomer found') + '</div>');
                }
            });
            $(document).on('click keyup', '.viwebpos-checkout-form-customer .viwebpos-checkout-search-customer:not(.viwebpos-checkout-search-customer-found)', function (e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                $(document.body).trigger('viwebpos_search_products_close');
                let $this = $(this);
                let val = $this.val(),
                    old_key = $this.data('old_key') || '';
                if (val === old_key) {
                    return false;
                }
                if (search_customer) {
                    clearTimeout(search_customer);
                }
                if (!$('.viwebpos-checkout-form-customer-wrap .viwebpos-search-customers-result-wrap').length) {
                    $('.viwebpos-checkout-form-customer-wrap .viwebpos-checkout-form-customer').append('<div class="viwebpos-search-customers-result-wrap"></div>');
                }
                $('.viwebpos-checkout-form-customer-wrap .viwebpos-search-customers-result-wrap').html('<div class="viwebpos-search-customers-loadmore"></div>');
                $this.data('old_key', val);
                if (search_customer_result[val]) {
                    $(document.body).trigger('viwebpos_checkout_search_customers_init', [search_customer_result[val]]);
                    return false;
                }
                search_customer = setTimeout(function (key) {
                    if (!key) {
                        $(document.body).trigger('viwebpos_checkout_search_customers_close');
                        return false;
                    }
                    let customers = $(document.body).triggerHandler('viwebpos_search_data', ['customers', key, 20, 1]);
                    if (typeof customers.then === 'function') {
                        customers.then(function (result) {
                            search_customer_result[key] = result;
                            $(document.body).trigger('viwebpos_checkout_search_customers_init', [result]);
                        })
                    } else {
                        search_customer_result[key] = customers;
                        $(document.body).trigger('viwebpos_checkout_search_customers_init', [customers]);
                    }
                }, 500, val.toString().trim());
            });
        }

        function checkout_event() {
            //payment
            $(document).on('focus', '.viwebpos-checkout-form-content .viwebpos-checkout-form-content-paid-input', function (e) {
                $(this).trigger('select');
            });
            $(document).on('change keyup', '.viwebpos-checkout-form-content .viwebpos-checkout-form-content-paid-input', function (e) {
                let val = $(this).val(),
                    old_val = $(this).data('val');
                if (e.type === 'keyup' && (!val || old_val === val)) {
                    if ($('.viwebpos-checkout-form-content-paid-input-checkout_after_change').length) {
                        $('.viwebpos-wrap').addClass('viwebpos-wrap-loading');
                        $('.viwebpos-checkout-form-footer-checkout-wrap').trigger('click');
                    }
                    return false;
                }
                if (!val && e.type === 'change') {
                    $('.viwebpos-checkout-form-content-paid-input-checkout_after_change').removeClass('viwebpos-checkout-form-content-paid-input-checkout_after_change');
                    val = $('.viwebpos-checkout-form-content-need_to_pay .viwebpos-checkout-form-content-value').html();
                }
                $(this).addClass('viwebpos-checkout-form-content-paid-input1');
                let input_length = val.length, cursor_pos = $(this).prop("selectionStart");
                let format_price = $(document.body).triggerHandler('viwebpos_format_price_input', [val, e.type]);
                val = format_price[0] || val;
                $(this).val(val).data('val', val);
                if (e.type !== 'change') {
                    cursor_pos = val.length - input_length + cursor_pos;
                    $(this)[0].setSelectionRange(cursor_pos, cursor_pos);
                    $(this).addClass('viwebpos-checkout-form-content-paid-input2');
                } else {
                    $(this).removeClass('viwebpos-checkout-form-content-paid-input2');
                }
                if (get_change) {
                    clearTimeout(get_change);
                }
                get_change = setTimeout(function (paid_input, price) {
                    let paid = $('.viwebpos-checkout-form-content-paid1:not(.viwebpos-hidden)').data('paid') || {},
                        method = paid_input.data('method') || 'cash';
                    paid[method] = price;
                    $(document.body).trigger('viwebpos_checkout_set_payments', [paid]);
                }, e.type === 'keyup' ? 2500 : 500, $(this), format_price[1] || 0);
            });
            $(document.body).on('viwebpos_format_price_input', function (e, val = '', event_type = 'change') {
                if (!val && event_type === 'change') {
                    val = viwebpos_get_price_html(0);
                    return [val, 0];
                }
                let right, left, decimal_pos,
                    decimals = viwebpos_price.wc_get_price_decimals,
                    decimal_separator = viwebpos_price.wc_price_decimal_separator,
                    thousand_separator = viwebpos_price.wc_price_thousand_separator;
                if (thousand_separator === decimal_separator) {
                    if (decimal_separator === '.') {
                        decimal_pos = val.lastIndexOf(decimal_separator);
                    } else {
                        decimal_pos = val.indexOf(decimal_separator);
                    }
                } else {
                    decimal_pos = val.indexOf(decimal_separator);
                }
                if (decimal_pos < 0) {
                    decimal_pos = val.lastIndexOf(decimal_separator);
                }
                if (decimal_pos >= 0) {
                    right = val.substring(decimal_pos);
                }
                if (decimal_pos < 0 || ((thousand_separator === decimal_separator || thousand_separator === '.') && right.length === 4)) {
                    right = '';
                }
                if (right) {
                    right = '.' + right.replace(/\D/g, '');
                    left = val.substring(0, decimal_pos).replace(/\D/g, '');
                } else {
                    left = val.replace(/\D/g, '');
                }
                if (event_type === 'change') {
                    val = viwebpos_get_price_html(left ? left + right : 0, decimals, decimal_separator, thousand_separator, '%2$s');
                } else if (right && right.length > decimals) {
                    val = left ? viwebpos_get_price_html(left + right, decimals, decimal_separator, thousand_separator, '%2$s') : '';
                } else {
                    val = left ? viwebpos_get_price_html(left, 0, decimal_separator, thousand_separator, '%2$s') + right : '';
                }
                return [val, left ? left + right : 0];
            });
            $(document).on('click', '.viwebpos-checkout-form-content-need_to_pay', function () {
                $(this).closest('.viwebpos-checkout-form-content-wrap').find('.viwebpos-checkout-form-content-paid-input').val($(this).find('.viwebpos-checkout-form-content-value').html()).trigger('change');
            });
            $(document).on('click', '.viwebpos-checkout-form-content-value-amount', function () {
                $(this).closest('.viwebpos-checkout-form-content-wrap').find('.viwebpos-checkout-form-content-paid-input').val($(this).data('val')).trigger('change');
            });
            $(document).on('change', 'input[name="payment-method"]', function () {
                let wrap = $(this).closest('.viwebpos-checkout-form-content-wrap-payment'),
                    val = $(this).val();
                wrap.find('.viwebpos-checkout-form-content-amount-input').data('method', val).trigger('change');
            });
            $(document).on('click', '.viwebpos-checkout-form-footer-action:not(.viwebpos-hidden)', function () {
                if ($('.viwebpos-bill-of-sale-container-empty').length || !$('.viwebpos-cart-item-wrap').length) {
                    $(document.body).trigger('villatheme_show_message', [viwebpos.checkout_cart_empty_message, ['error', 'payment-settings'], '', false, 4500]);
                    return false;
                }
                let type = $(this).data('type');
                if (!type) {
                    return;
                }
                $('.viwebpos-checkout-form-content-wrap, .viwebpos-checkout-form-footer-action').addClass('viwebpos-hidden');
                $('.viwebpos-checkout-form-content-wrap-' + type + ', .viwebpos-checkout-form-footer-back-to-bill').removeClass('viwebpos-hidden');
                switch (type) {
                    case 'payment':
                        $('.viwebpos-checkout-form-content-amount-input.viwebpos-checkout-form-content-paid-input').trigger('focus');
                        break;
                }
            });
            $(document).on('click', '.viwebpos-checkout-form-footer-back-to-bill:not(.viwebpos-hidden)', function () {
                $('.viwebpos-checkout-form-content-wrap, .viwebpos-checkout-form-footer-action').addClass('viwebpos-hidden');
                $('.viwebpos-checkout-form-content-wrap-total, .viwebpos-checkout-form-footer-action:not(.viwebpos-checkout-form-footer-back-to-bill)').removeClass('viwebpos-hidden');
                $('.viwebpos-checkout-form-content-paid-input:not(.viwebpos-checkout-form-content-amount-input)').trigger('focus');
            });
            //coupon
            if (viwebpos.wc_coupons_enabled) {
                $(document).on('click', '.viwebpos-checkout-apply-coupon-button:not(.loading)', function (e) {
                    let coupon_code = $('.viwebpos-checkout-apply-coupon-input').val();
                    if (!coupon_code) {
                        $(document.body).trigger('villatheme_show_message', [viwebpos.coupon_please_enter, ['error', 'apply-coupon'], '', false, 4500]);
                        return false;
                    }
                    $(this).addClass('loading');
                    $('.viwebpos-checkout-form-footer-checkout-wrap').addClass('loading');
                    $(document.body).trigger('viwebpos_cart_add_discount', [coupon_code]);
                });
                $(document).on('click', '.viwebpos-checkout-form-content-coupon .viwebpos-checkout-form-content-coupon-info .icon', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $(document.body).trigger('viwebpos_search_products_close');
                    $(document.body).trigger('viwebpos_checkout_search_customers_close');
                    $(document.body).trigger('viwebpos_cart_remove_coupon', [$(this).closest('.viwebpos-checkout-form-content-full-info').data('coupon')]);
                });
            }
            //checkout & print receipt
            $(document).on('click', '.viwebpos-checkout-form-footer-checkout-wrap:not(.loading):not(.viwebpos-hidden)', function () {
                if ($('.viwebpos-bill-of-sale-container-empty').length || !$('.viwebpos-cart-item-wrap').length) {
                    $(document.body).trigger('villatheme_show_message', [viwebpos.checkout_cart_empty_message, ['error', 'place-order'], '', false, 4500]);
                    return false;
                }
                let error = $(document).triggerHandler('viwebpos_cancel_checkout_message');
                if (error) {
                    $(document.body).trigger('villatheme_show_message', [error, ['error', 'place-order'], '', false, 6500]);
                    return;
                }
                $(this).addClass('loading');
                $(document.body).trigger('viwebpos_create_order');
            });
            //desgin
            $(document).on('click', '.viwebpos-popup-wrap-settings-pos-init input[type="checkbox"]', function () {
                let name = $(this).data('name');
                if ($(this).prop('checked')) {
                    $('input#viwebpos-settings-pos-' + name).val(1);
                } else {
                    $('input#viwebpos-settings-pos-' + name).val('');
                }
                $(document.body).trigger('viwebpos_settings_pos');
            });
            $(document).on('change', '.viwebpos-popup-wrap-settings-pos-init select', function () {
                let selected = $(this).val();
                if (typeof selected !== "object") {
                    $('input#viwebpos-settings-pos-' + $(this).data('name')).val(selected);
                    $(document.body).trigger('viwebpos_settings_pos');
                    return;
                }
                $(this).find('option').each(function (k, v) {
                    if (selected.includes($(v).val())) {
                        $('input#viwebpos-settings-pos-' + $(v).val()).val('');
                    } else {
                        $('input#viwebpos-settings-pos-' + $(v).val()).val(1);
                    }
                });
                $(document.body).trigger('viwebpos_settings_pos');
            });
        }

        $(document.body).on('viwebpos-bill-of-sale-load', function () {
            if (!$('.viwebpos-bill-of-sale-container').length) {
                let html = '<div class="viwebpos-container-element viwebpos-bill-of-sale-container"><div class="viwebpos-cart-contents-container">';
                html += '<div class="viwebpos-cart-contents"><table class="viwebpos-cart-items-wrap"></table></div>';
                html += '<div class="viwebpos-cart-contents-reset-and-note">';
                html += '<span class="viwebpos-cart-contents-reset-icon" data-position="top ' + ($('.viwebpos-wrap.rtl').length ? 'right' : 'left') + '" data-tooltip="' + viwebpos.remove_all_items + '"><i class="icon trash alternate outline"></i></span>';
                html += '<span class="viwebpos-cart-contents-add-note-icon"><i class="icon exclamation alternate"></i></span>';
                html += '<textarea rows="4" cols="50" class="viwebpos-cart-contents-order-textarea" placeholder="' + viwebpos.add_order_note + '"></textarea><div class="viwebpos-cart-contents-order-note"></div></div></div>';
                html += '<div class="viwebpos-checkout-form-container">';
                html += '<div class="viwebpos-checkout-form-customer-wrap">';
                html += '<div class="viwebpos-checkout-form-customer">';
                html += '<input type="text" class="viwebpos-search-input viwebpos-checkout-search-customer" placeholder="' + viwebpos.search_customer + '">';
                html += '<div class="viwebpos-checkout-form-customer-icon viwebpos-checkout-form-customer-search-icon"';
                if ($('.viwebpos-wrap.rtl').length) {
                    html += 'data-position="bottom left"';
                } else {
                    html += 'data-position="bottom right"';
                }
                html += ` data-tooltip="${viwebpos.add_new_customer}"><i class="user plus icon"></i></div>`;
                html += '<div class="viwebpos-checkout-form-customer-icon viwebpos-checkout-form-customer-remove-icon" >+</div>';
                html += '</div></div>';
                html += '<div class="viwebpos-checkout-form-content-wrap viwebpos-checkout-form-content-wrap-total">';
                html += '<div class="viwebpos-checkout-form-content viwebpos-checkout-form-content-subtotal"><div class="viwebpos-checkout-form-content-title">' + viwebpos.subtotal_title + '</div><div class="viwebpos-checkout-form-content-value"></div></div>';
                html += '<div class="viwebpos-checkout-form-content viwebpos-checkout-form-content-tax"><div class="viwebpos-checkout-form-content-title">' + viwebpos.tax_title + '</div><div class="viwebpos-checkout-form-content-value"></div></div>';
                html += '<div class="viwebpos-checkout-form-content viwebpos-checkout-form-content-full viwebpos-checkout-form-content-coupon viwebpos-hidden"></div>';
                html += '<div class="viwebpos-checkout-form-content viwebpos-checkout-form-content-need_to_pay"><div class="viwebpos-checkout-form-content-title">' + viwebpos.need_to_pay_title + '</div><div class="viwebpos-checkout-form-content-value"></div></div>';
                html += '<div class="viwebpos-checkout-form-content viwebpos-checkout-form-content-paid">';
                html += '<div class="viwebpos-checkout-form-content-title">' + viwebpos.paid_title + '<div class="viwebpos-keyboard-label"> (F8)</div></div>';
                html += '<div class="viwebpos-checkout-form-content-value">';
                html += viwebpos_price.wc_price_format.toString().replace('%1$s', `<span class="viwebpos-currency-symbol">${viwebpos_price.wc_currency_symbol}</span>`)
                    .replace('%2$s', '<input type="text" value="" class="viwebpos-checkout-form-content-paid-input">');
                html += '</div></div>';
                html += '<div class="viwebpos-checkout-form-content viwebpos-checkout-form-content-amount viwebpos-checkout-form-content-amount-choose viwebpos-hidden">';
                html += '<div class="viwebpos-checkout-form-content-title"></div>';
                html += '<div class="viwebpos-checkout-form-content-value"></div>';
                html += '</div>';
                html += '<div class="viwebpos-checkout-form-content viwebpos-checkout-form-content-change"><div class="viwebpos-checkout-form-content-title">' + viwebpos.change_title + '</div>';
                html += '<div class="viwebpos-checkout-form-content-value"></div></div>';
                html += '</div>';
                if (viwebpos.viwebpos_payments) {
                    html += '<div class="viwebpos-checkout-form-content-wrap viwebpos-checkout-form-content-wrap-payment viwebpos-hidden">';
                    html += '<div class="viwebpos-checkout-form-content viwebpos-checkout-form-content-amount">';
                    html += '<div class="viwebpos-checkout-form-content-title">' + viwebpos.pay_amount_title + '<div class="viwebpos-keyboard-label"> (F8)</div></div>';
                    html += '<div class="viwebpos-checkout-form-content-value">';
                    html += viwebpos_price.wc_price_format.toString().replace('%1$s', `<span class="viwebpos-currency-symbol">${viwebpos_price.wc_currency_symbol}</span>`)
                        .replace('%2$s', '<input type="text" value="" class="viwebpos-checkout-form-content-amount-input viwebpos-checkout-form-content-paid-input">');
                    html += '</div>';
                    html += '</div>';
                    html += '<div class="viwebpos-checkout-form-content viwebpos-checkout-form-content-amount viwebpos-checkout-form-content-amount-choose">';
                    html += '<div class="viwebpos-checkout-form-content-title"></div>';
                    html += '<div class="viwebpos-checkout-form-content-value"></div>';
                    html += '</div>';
                    html += '<div class="viwebpos-checkout-form-content viwebpos-checkout-form-content-payment-method">';
                    html += '<div class="viwebpos-checkout-form-content-title"></div>';
                    html += '<div class="viwebpos-checkout-form-content-value">';
                    $.each(viwebpos.viwebpos_payments, function (k, v) {
                        html += '<div class="vi-ui radio checkbox">';
                        html += '<input type="radio" value="' + k + '" name="payment-method" class="viwebpos-checkout-form-content-value-payment" id="viwebpos-checkout-form-content-value-payment-' + k + '">';
                        html += '<label for="viwebpos-checkout-form-content-value-payment-' + k + '">' + v.title + '</label>';
                        html += '</div>';
                    });
                    html += '</div></div>';
                    html += '<div class="viwebpos-checkout-form-content viwebpos-checkout-form-content-total"><div class="viwebpos-checkout-form-content-title">' + viwebpos.total_title + '</div>';
                    html += '<div class="viwebpos-checkout-form-content-value"></div></div>';
                    html += '<div class="viwebpos-checkout-form-content viwebpos-checkout-form-content-paid"><div class="viwebpos-checkout-form-content-title">' + viwebpos.paid_title + '</div>';
                    html += '<div class="viwebpos-checkout-form-content-value"></div></div>';
                    html += '<div class="viwebpos-checkout-form-content viwebpos-checkout-form-content-change"><div class="viwebpos-checkout-form-content-title">' + viwebpos.change_title + '</div>';
                    html += '<div class="viwebpos-checkout-form-content-value"></div></div>';
                    html += '</div>';
                }
                if (viwebpos.wc_coupons_enabled) {
                    html += '<div class="viwebpos-checkout-form-content-wrap viwebpos-checkout-form-content-wrap-coupon viwebpos-hidden">';
                    html += '<div class="viwebpos-checkout-form-content viwebpos-checkout-form-content-full viwebpos-checkout-form-content-coupon1">';
                    html += '<input type="text" class="viwebpos-checkout-apply-coupon-input" placeholder="' + viwebpos.coupon_input_placeholder + '">';
                    html += '<div class="vi-ui button positive viwebpos-checkout-apply-coupon-button">' + viwebpos.coupon_bt_title + '</div>';
                    html += '</div>';
                    html += '<div class="viwebpos-checkout-form-content viwebpos-checkout-form-content-full viwebpos-checkout-form-content-coupon viwebpos-hidden"></div>';
                    html += '</div>';
                }
                html += '<div class="viwebpos-checkout-form-footer-wrap" >';
                html += '<div class="viwebpos-checkout-form-footer-action-wrap' + (viwebpos.viwebpos_payments && viwebpos.wc_coupons_enabled ? ' viwebpos-checkout-form-footer-multi-action-wrap' : '') + '">';
                if (viwebpos.wc_coupons_enabled) {
                    html += '<div class="viwebpos-checkout-form-footer-action viwebpos-checkout-form-footer-coupon" data-type="coupon"><i class="gift icon"></i>';
                    html += '<div class="viwebpos-checkout-form-footer-coupon-title">' + viwebpos.coupon_title + '</div><div class="viwebpos-keyboard-label"> (F4)</div>';
                    html += '</div>';
                }
                if (viwebpos.viwebpos_payments) {
                    html += '<div class="viwebpos-checkout-form-footer-action viwebpos-checkout-form-footer-payment" data-type="payment"><i class="credit card outline icon"></i>';
                    html += '<div class="viwebpos-checkout-form-footer-payment-title">' + viwebpos.payment_method_title + '</div>(<div class="viwebpos-checkout-form-footer-payment-value"></div>)';
                    html += '<div class="viwebpos-keyboard-label"> (F9)</div></div>';
                }
                if (viwebpos.viwebpos_payments || viwebpos.wc_coupons_enabled) {
                    html += '<div class="viwebpos-checkout-form-footer-action viwebpos-checkout-form-footer-back-to-bill viwebpos-hidden">';
                    html += '<div class="viwebpos-checkout-form-footer-arrow"><div></div><div></div><div></div></div>';
                    html += '<div class="viwebpos-checkout-form-footer-payment-title">' + viwebpos.back_to_bill_title + '</div>';
                    html += '</div>';
                }
                html += '</div>';
                html += '<div class="viwebpos-checkout-form-footer-checkout-wrap"><div class="viwebpos-checkout-form-footer-title-wrap"><div class="viwebpos-checkout-form-footer-title">' + viwebpos.place_order_title + '</div><div class="viwebpos-keyboard-label"> (F10)</div></div>';
                html += '<div class="viwebpos-checkout-form-footer-total-wrap"><div class="viwebpos-checkout-form-footer-total"></div><div class="viwebpos-checkout-form-footer-arrow"><div></div><div></div><div></div></div></div></div>';
                html += '</div></div></div>';
                $('.viwebpos-container-wrap').prepend(html);
            }
            if (!$('.viwebpos-header-search-product').length) {
                let html = '<div class="viwebpos-header-search viwebpos-header-search-product">';
                if (viwebpos.custom_product_title) {
                    html += '<div class="viwebpos-header-search-icon viwebpos-add-custom-product-wrap"';
                    if ($('.viwebpos-wrap.rtl').length) {
                        html += 'data-position="bottom right"';
                    } else {
                        html += 'data-position="bottom left"';
                    }
                    html += `data-tooltip="${viwebpos.custom_product_tooltip}">&#43;</div>`;
                } else {
                    html += '<div class="viwebpos-header-search-icon"><i class="icon search"></i></div>';
                }
                html += '<input type="text" class="viwebpos-search-input viwebpos-search-product" placeholder="' + viwebpos.search_product + '">';
                html += '</div>';
                $('.viwebpos-header-search-wrap').html(html);
            }
            if (!$('.viwebpos-popup-wrap-add-product').length && viwebpos.custom_product_title) {
                $(document.body).trigger('viwebpos_get_popup_wrap', ['add-product']);
            }
            if (!$('.viwebpos-popup-wrap-add-customer').length) {
                $(document.body).trigger('viwebpos_get_popup_wrap', ['add-customer']);
            }
            if (!$('.viwebpos-popup-wrap-settings-pos').length) {
                $(document.body).trigger('viwebpos_get_popup_wrap', ['settings-pos']);
            }
            setTimeout(function () {
                $(document.body).trigger('viwebpos-frontend-loaded');
            }, 100);
            $(document.body).trigger('viwebpos-bill-of-sale-refresh-cart', [true]);
        });
        $(document.body).on('viwebpos-bill-of-sale-refresh-cart', function (e, reset_total = false) {
            let current_carts = $(document.body).triggerHandler('viwebpos_search_data', ['carts']);
            if (typeof current_carts.then === 'function') {
                current_carts.then(function (result) {
                    $(document.body).trigger('viwebpos_cart_data_validate', [result, reset_total]);
                })
            } else {
                $(document.body).trigger('viwebpos_cart_data_validate', [current_carts, reset_total]);
            }
        });
        $(document.body).on('viwebpos-bill-of-sale-get-html', function (e, cart, only_checkout = false) {
            $(document.body).trigger('viwebpos-bill-of-sale-before-get-html');
            if ($('.viwebpos-popup-wrap-show').length) {
                $(document.body).trigger('viwebpos_popup_close');
            }
            $('.viwebpos-wrap').removeClass('viwebpos-wrap-loading');
            $('.viwebpos-checkout-form-content-error').removeClass('viwebpos-checkout-form-content-error');
            if (!only_checkout) {
                $('.viwebpos-bill-of-sale-container-empty').removeClass('viwebpos-bill-of-sale-container-empty');
                let cart_items_count = 0, cart_wrap = $('<table class="viwebpos-cart-items-wrap"></table>');
                let cart_contents = [];
                $.each(cart.cart_contents, function (k, v) {
                    cart_contents.push(v);
                });
                cart_contents.sort(function (a, b) {
                    return a.item_serial - b.item_serial;
                });
                let is_rtl = $('.viwebpos-wrap.rtl').length;
                let rtl_position_action = is_rtl ? 'bottom left' : 'bottom right';
                $.each(cart_contents, function (k, v) {
                    let product = v.data,
                        quantity = parseFloat(v.quantity),
                        item_key = v.key;
                    if (!item_key || !product || quantity <= 0) {
                        return true;
                    }
                    cart_items_count++;
                    let product_name = product.name, is_attribute_in_product_name = false;
                    if (product.type === 'variation' && typeof v.variation === 'object') {
                        $.each(v.variation, function (name, value) {
                            if (value === '' || product_name.toLowerCase().indexOf(' ' + value.toLowerCase() + ',') > 0
                                || 0 === product_name.toLowerCase().split('').reverse().join('').indexOf(value.toLowerCase().replace('-', ' ').split('').reverse().join(''))
                                || 0 === product_name.toLowerCase().split('').reverse().join('').indexOf(value.toLowerCase().split('').reverse().join(''))) {
                                if (value) {
                                    is_attribute_in_product_name = true;
                                }
                                return true;
                            }
                            product_name += is_attribute_in_product_name ? ', ' + value : ' - ' + value;
                            is_attribute_in_product_name = true;
                        });
                    }
                    let note = '<tr class="viwebpos-cart-item-wrap viwebpos-cart-item-wrap-note">';
                    note += '<td class="viwebpos-cart-item-serial"></td>';
                    note += '<td colspan="5" class="viwebpos-cart-item-note"><input type="text" placeholder="cart item note..." value="' + (v.item_note || '') + '"></td>';
                    note += '<td></td>';
                    note += '</tr>';
                    note = $(note);
                    note.data({'cart_item_key': item_key});
                    cart_wrap.prepend(note);
                    let html = '<tr class="viwebpos-cart-item-wrap' + (v.item_note ? ' viwebpos-cart-item-wrap-has-note' : '') + '">';
                    html += '<td class="viwebpos-cart-item-serial">' + cart_items_count + '</td>';
                    html += '<td class="viwebpos-cart-item-name-wrap">';
                    html += '<div class="viwebpos-cart-item-name">';
                    html += product_name + '</div>';
                    html += '<div class="viwebpos-cart-item-barcode">' + (product.barcode || '') + '</div>';
                    if (viwebpos.update_variation_on_cart && product.type === 'variation' && typeof v.variation === 'object' && product.parent_attributes_html) {
                        html += '<div class="viwebpos-cart-item-swatches">' + product.parent_attributes_html + '</div>';
                    }
                    html += '</td>';
                    if (product.is_sold_individually) {
                        html += '<td class="viwebpos-cart-item-qty-wrap viwebpos-cart-item-qty-wrap-sold_individually"><span class="viwebpos-cart-item-qty">' + quantity + '</span></td>';
                    } else {
                        html += '<td class="viwebpos-cart-item-qty-wrap">';
                        html += '<div class="viwebpos-product-qty-wrap"><span class="viwebpos-product-qty-action viwebpos-product-qty-minus">';
                        html += is_rtl ? '<i class="icon chevron right"></i>' : '<i class="chevron left icon"></i>';
                        html += '</span>';
                        html += '<input type="number" class="viwebpos-product-qty-value viwebpos-cart-item-qty" value="' + quantity + '">';
                        html += '<span class="viwebpos-product-qty-action viwebpos-product-qty-plus">';
                        html += is_rtl ? '<i class="chevron left icon"></i>' : '<i class="icon chevron right"></i>';
                        html += '</span></div>';
                        html += '</td>';
                    }
                    html += '<td class="viwebpos-cart-item-price">' + viwebpos_get_product_price(product) + '</td>';
                    if (v.line_subtotal) {
                        if (viwebpos.display_prices_including_tax) {
                            html += '<td class="viwebpos-cart-item-subtotal">' + viwebpos_get_price_html(v.line_subtotal + v.line_subtotal_tax);
                            if (!viwebpos_price.product_price_includes_tax && v.line_subtotal_tax > 0) {
                                html += ' <small class="tax_label">' + viwebpos.inc_tax_or_vat + '</small>';
                            }
                            html += '</td>';
                        } else {
                            html += '<td class="viwebpos-cart-item-subtotal">' + viwebpos_get_price_html(v.line_subtotal);
                            if (viwebpos_price.product_price_includes_tax && v.line_subtotal_tax > 0) {
                                html += ' <small class="tax_label">' + viwebpos.ex_tax_or_vat + '</small>';
                            }
                            html += '</td>';
                        }
                    } else {
                        html += '<td class="viwebpos-cart-item-subtotal">' + viwebpos_get_product_price(product, quantity) + '</td>';
                    }
                    html += '<td class="viwebpos-cart-item-action"><div class="viwebpos-cart-item-bt-action-wrap">';
                    html += `<span class="viwebpos-cart-item-bt-action viwebpos-cart-item-bt-remove" data-position="${rtl_position_action}" data-tooltip="${viwebpos.remove_cart_item}">`;
                    html += '<i class="icon trash alternate outline"></i></span>';
                    html += `<span class="viwebpos-cart-item-bt-action viwebpos-cart-item-bt-add-note" data-position="${rtl_position_action}" data-tooltip="${viwebpos.add_cart_item_note}">`;
                    html += '<i class="icon exclamation alternate"></i>';
                    html += '</span>';
                    html += '</div></td>';
                    html += '</tr>';
                    html = $(html);
                    html.data({'cart_item_key': item_key}).attr('title', product_name);
                    html.find('.viwebpos-cart-item-qty').attr({min: 0, max: product.max_qty});
                    if (html.find('.viwebpos-cart-item-swatches').length) {
                        html.find('.viwebpos-attribute-options option[value=""]').prop('disabled', true);
                        $.each(v.variation, function (name, value) {
                            html.find(`select[data-attribute_name="${name}"]`).val(value);
                        });
                    }
                    cart_wrap.prepend($(document).triggerHandler('viwebpos_get_cart_item_html', [html, false, product, v]) || html);
                });
                if (!cart_items_count) {
                    $('.viwebpos-cart-contents-container').addClass('viwebpos-bill-of-sale-container-empty');
                    $('.viwebpos-checkout-form-footer-back-to-bill').trigger('click');
                }
                $('.viwebpos-cart-contents-order-textarea').val(cart.order_note || '');
                $('.viwebpos-cart-contents-order-note').html(cart.order_note || '');
                cart_wrap.find('.viwebpos-cart-item-wrap').eq(0).find('.viwebpos-cart-item-bt-action').attr('data-position', rtl_position_action);
                $('.viwebpos-cart-items-wrap').replaceWith(cart_wrap);
            }
            let checkout_wrap = $('.viwebpos-checkout-form-container');
            if (cart.customer) {
                checkout_wrap.find('.viwebpos-checkout-search-customer').addClass('viwebpos-checkout-search-customer-found').val(cart.customer.email ? cart.customer.email : cart.customer.username).prop('readonly', true);
            } else {
                checkout_wrap.find('.viwebpos-checkout-search-customer').removeClass('viwebpos-checkout-search-customer-found').val('').prop('readonly', false);
            }
            checkout_wrap.find('.viwebpos-checkout-form-content-subtotal .viwebpos-checkout-form-content-value').html(viwebpos_get_price_html(cart.totals.subtotal));
            checkout_wrap.find('.viwebpos-checkout-form-content-tax .viwebpos-checkout-form-content-value').html(viwebpos_get_price_html(cart.totals.total_tax));
            checkout_wrap.find('.viwebpos-checkout-form-content-need_to_pay .viwebpos-checkout-form-content-value').html(viwebpos_get_price_html(cart.totals.total));
            viwebpos_get_discounts_html(cart.applied_coupons && cart.coupon_discount_totals ? cart.coupon_discount_totals : {},
                cart.fees ? cart.fees : {}, checkout_wrap.find('.viwebpos-checkout-form-content-coupon'));
            let payment_method = cart.payments.is_paid,
                cart_total = parseFloat(cart.totals.total),
                total_paid = parseFloat(cart.payments.total_paid);
            if (!payment_method) {
                payment_method = 'cash';
                checkout_wrap.find('#viwebpos-checkout-form-content-value-payment-cash').prop('checked', true);
            }
            let payment_total_paid = viwebpos_get_price_html(total_paid, null, null, null, '%2$s');
            let cart_total_html = viwebpos_get_price_html(cart_total);
            if (viwebpos.viwebpos_payments) {
                checkout_wrap.find('.viwebpos-checkout-form-footer-payment-value').html(viwebpos.viwebpos_payments[payment_method]['title']);
            }
            checkout_wrap.find('.viwebpos-checkout-form-content-paid-input:not(.viwebpos-checkout-form-content-paid-input1)').data({'val': payment_total_paid}).val(payment_total_paid);
            checkout_wrap.find('.viwebpos-checkout-form-content-paid-input').data({'method': payment_method});
            checkout_wrap.find('.viwebpos-checkout-form-content-wrap-payment .viwebpos-checkout-form-content-paid .viwebpos-checkout-form-content-value').html(viwebpos_get_price_html(total_paid));
            $('.viwebpos-checkout-form-content-paid-input1').removeClass('viwebpos-checkout-form-content-paid-input1');
            checkout_wrap.find('.viwebpos-checkout-form-content-change .viwebpos-checkout-form-content-value').html(viwebpos_get_price_html(cart.payments.change));
            if (parseFloat(cart.payments.change) < 0) {
                checkout_wrap.find('.viwebpos-checkout-form-content-change .viwebpos-checkout-form-content-value').addClass('viwebpos-checkout-form-content-error');
            }
            checkout_wrap.find('#viwebpos-checkout-form-content-value-payment-' + payment_method).prop('checked', true);
            if (cart_total) {
                let suggest_pay = parseFloat(cart_total.toFixed(0)), suggest_loop = 10;
                if (parseInt(viwebpos_price.wc_get_price_decimals)) {
                    if (suggest_pay % 10 > 0) {
                        suggest_pay += 10 - suggest_pay % 10;
                    }
                } else if (suggest_pay.toString().length % 3) {
                    suggest_loop = Math.pow(10, (Math.ceil(suggest_pay.toString().length / 3) - 1) * 3);
                    if (suggest_pay % suggest_loop > 0) {
                        suggest_pay += suggest_loop - suggest_pay % suggest_loop;
                    }
                } else if (suggest_pay % 10 > 0) {
                    suggest_pay += 10 - suggest_pay % 10;
                }
                let suggest_html = '';
                let suggest_count = suggest_pay.toString().length > 5 ? [1, 2, 5] : [1, 2, 5, 10, 20];
                if (total_paid > 0) {
                    suggest_html += '<div class="viwebpos-checkout-form-content-value-amount" data-val="' + cart_total + '">' + cart_total_html + '</div>';
                    suggest_count.pop();
                }
                if (suggest_pay > cart_total) {
                    suggest_html += '<div class="viwebpos-checkout-form-content-value-amount" data-val="' + suggest_pay + '">' + viwebpos_get_price_html(suggest_pay) + '</div>';
                    suggest_count.pop();
                }
                $.each(suggest_count, function (k, v) {
                    let temp = suggest_pay + v * suggest_loop;
                    suggest_html += '<div class="viwebpos-checkout-form-content-value-amount" data-val="' + temp + '">' + viwebpos_get_price_html(temp) + '</div>';
                });
                checkout_wrap.find('.viwebpos-checkout-form-content-amount-choose').removeClass('viwebpos-hidden');
                checkout_wrap.find('.viwebpos-checkout-form-content-amount-choose .viwebpos-checkout-form-content-value').html(suggest_html);
            } else {
                checkout_wrap.find('.viwebpos-checkout-form-content-amount-choose').addClass('viwebpos-hidden');
            }
            checkout_wrap.find('.viwebpos-checkout-form-footer-total, .viwebpos-checkout-form-content-total .viwebpos-checkout-form-content-value').html(cart_total_html);
            $(document.body).trigger('viwebpos_checkout_search_customers_close');
            if ($('.viwebpos-checkout-form-content-paid-input-checkout_after_change').length) {
                $('.viwebpos-checkout-form-footer-checkout-wrap').trigger('click');
            }
            $(document.body).trigger('viwebpos_settings_pos');
            if ($('.viwebpos-search-product').val()) {
                if ($('.viwebpos-search-product').hasClass('viwebpos-search-product-scanned')) {
                    $(document.body).trigger('viwebpos_search_products_close');
                }
                $('.viwebpos-search-product').removeClass('viwebpos-search-product-scanned').trigger('focus');
            } else {
                $('.viwebpos-search-product').trigger('focus');
            }
            $(document).trigger('viwebpos-bill-of-sale-after-get-html');
        });
    });

    window.viwebpos_get_discounts_html = function (coupons, fees, wrap) {
        wrap = $(wrap);
        if (!wrap.length) {
            return false;
        }
        wrap.data('coupons', coupons).data('fees', fees).addClass('viwebpos-hidden').html(null);
        if ((!coupons || !Object.keys(coupons).length) && (!fees || !Object.keys(fees).length)) {
            return false;
        }
        let html;
        $.each(coupons, function (k, v) {
            html = '<div class="viwebpos-checkout-form-content-full-info viwebpos-checkout-form-content-coupon-info viwebpos-checkout-form-content-coupon-info-' + k + '"><div class="viwebpos-checkout-form-content-full-left">';
            html += '<i class="icon trash alternate outline"></i>' + viwebpos.coupon_title + ': ' + k + '</div>';
            html += '<div class="viwebpos-checkout-form-content-full-right">-' + viwebpos_get_price_html(v) + '</div></div>';
            html = $(html);
            html.data({'coupon': k, 'discount': v});
            wrap.append(html);
        });
        $.each(fees, function (k, v) {
            html = '<div class="viwebpos-checkout-form-content-full-info viwebpos-checkout-form-content-fee viwebpos-checkout-form-content-fee-info-' + k + '"><div class="viwebpos-checkout-form-content-full-left">';
            html += '<i class="icon trash alternate outline"></i>' + (v.name || v.id) + '</div>';
            html += '<div class="viwebpos-checkout-form-content-full-right">' + viwebpos_get_price_html(v.total) + '</div></div>';
            html = $(html);
            if (v.redis_cart_discount) {
                html.addClass('viwebpos-checkout-form-content-fee-redis');
            }
            html.data({'discount': v, 'fee_id': k});
            wrap.append(html);
        });
        $('.viwebpos-checkout-add-discount-input').val('');
        if (html) {
            wrap.removeClass('viwebpos-hidden');
        }
    }
})(jQuery);