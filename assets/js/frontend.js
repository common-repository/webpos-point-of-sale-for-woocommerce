(function ($) {
    'use strict';
    let current_page;
    let is_mobile = $(window).width() < 821;
    $(window).on('resize', function () {
        let is_mobile_t = $(window).width() < 821;
        if (is_mobile_t !== is_mobile) {
            is_mobile = is_mobile_t;
            $(document.body).trigger('viwebpos-frontend-load', current_page);
        }
    });
    $(document).ready(function ($) {
        'use strict';
        $('.vi-ui.dropdown').off().dropdown();
        $(document.body).on('viwebpos_get_popup_wrap', function (e, type = '') {
            if (!type || $('.viwebpos-popup-wrap-' + type).length) {
                return false;
            }
            let html = '<div class="viwebpos-popup-wrap viwebpos-popup-wrap-' + type + '">';
            html += '<div class="viwebpos-popup"><div class="viwebpos-overlay viwebpos-overlay-loading"></div><div class="viwebpos-overlay"></div>';
            html += '<div class="viwebpos-popup-container-wrap"><span class="viwebpos-popup-close">&#43;</span>';
            html += '<div class="viwebpos-popup-container">';
            html += '<div class="viwebpos-popup-header-wrap">';
            switch (type) {
                case "add-product":
                    html += viwebpos.custom_product_title;
                    break;
                case "add-customer":
                    html += viwebpos.add_new_customer;
                    break;
                case "print-receipt":
                    html += viwebpos.print_title;
                    break;
                case "add-transaction":
                    html += viwebpos.transaction_add_title;
                    break;
                case "settings-pos":
                    html += viwebpos_text.settings_title;
                    break;
            }
            html += '</div>';
            html += '<div class="viwebpos-popup-content-wrap">';
            switch (type) {
                case "add-product":
                    html += '<div class="viwebpos-popup-content">';
                    html += '<div class="viwebpos-popup-content-horizontal viwebpos-popup-content-full-row viwebpos-popup-content-add-product-name">';
                    html += '<div class="viwebpos-popup-content-title">' + viwebpos.custom_product_name_title + '</div>';
                    html += '<div class="viwebpos-popup-content-value">';
                    html += '<input type="text" class="viwebpos-custom-product-name" placeholder="' + viwebpos.custom_product_name_placeholder + '" data-value_default="" value="">';
                    html += '</div></div></div>';
                    html += '<div class="viwebpos-popup-content">';
                    html += '<div class="viwebpos-popup-content-horizontal viwebpos-popup-content-full-row viwebpos-popup-content-add-product-price">';
                    html += '<div class="viwebpos-popup-content-title">' + viwebpos.custom_product_price_title + '</div>';
                    html += '<div class="viwebpos-popup-content-value">';
                    html += viwebpos.custom_product_price_value;
                    html += '</div></div></div>';
                    html += '<div class="viwebpos-popup-content">';
                    html += '<div class="viwebpos-popup-content-horizontal viwebpos-popup-content-full-row viwebpos-popup-content-add-product-qty">';
                    html += '<div class="viwebpos-popup-content-title">' + viwebpos.custom_product_qty_title + '</div>';
                    html += '<div class="viwebpos-popup-content-value">';
                    html += '<div class="viwebpos-popup-add-product-qty-wrap viwebpos-product-qty-wrap">';
                    html += '<span class="viwebpos-product-qty-action viwebpos-product-qty-minus viwebpos-popup-add-product-qty-action viwebpos-popup-add-product-qty-minus"> <i class="icon minus"></i></span>';
                    html += '<input type="number" name="quantity" data-value_default="1" min="1" step="1" value="1" class="viwebpos-product-qty-value custom_product_qty">';
                    html += '<span class="viwebpos-product-qty-action viwebpos-product-qty-plus viwebpos-popup-add-product-qty-action viwebpos-popup-add-product-qty-plus"> <i class="icon plus"></i></span>';
                    html += '</div></div></div></div>';
                    break;
                case "add-customer":
                    let shop_country = viwebpos_price.shop_address.country || '';
                    html += '<div class="viwebpos-popup-content">';
                    html += '<div class="viwebpos-popup-content-vertical viwebpos-popup-content-half-row">';
                    html += '<div class="viwebpos-popup-content-title">' + viwebpos.customer_first_name + '</div>';
                    html += '<div class="viwebpos-popup-content-value"><input type="text" class="viwebpos-add-customer-first-name" data-value_default="" value=""></div>';
                    html += '</div>';
                    html += '<div class="viwebpos-popup-content-vertical viwebpos-popup-content-half-row">';
                    html += '<div class="viwebpos-popup-content-title">' + viwebpos.customer_last_name + '</div>';
                    html += '<div class="viwebpos-popup-content-value"><input type="text" class="viwebpos-add-customer-last-name" data-value_default="" value=""></div>';
                    html += '</div></div>';
                    html += '<div class="viwebpos-popup-content">';
                    html += '<div class="viwebpos-popup-content-vertical viwebpos-popup-content-half-row">';
                    html += '<div class="viwebpos-popup-content-title">' + viwebpos.customer_email + '</div>';
                    html += '<div class="viwebpos-popup-content-value"><input type="text" class="viwebpos-add-customer-email" data-value_default="" value=""></div>';
                    html += '</div>';
                    html += '<div class="viwebpos-popup-content-vertical viwebpos-popup-content-half-row">';
                    html += '<div class="viwebpos-popup-content-title">' + viwebpos.customer_phone + '</div>';
                    html += '<div class="viwebpos-popup-content-value"><input type="text" class="viwebpos-add-customer-phone" data-value_default="" value=""></div>';
                    html += '</div></div>';
                    html += '<div class="viwebpos-popup-content">';
                    html += '<div class="viwebpos-popup-content-vertical viwebpos-popup-content-full-row">';
                    html += '<div class="viwebpos-popup-content-title">' + viwebpos.customer_address1 + '</div>';
                    html += '<div class="viwebpos-popup-content-value"><input type="text" class="viwebpos-add-customer-address1" data-value_default="" value=""></div>';
                    html += '</div></div>';
                    html += '<div class="viwebpos-popup-content">';
                    html += '<div class="viwebpos-popup-content-vertical viwebpos-popup-content-half-row">';
                    html += '<div class="viwebpos-popup-content-title">' + viwebpos.customer_country + '</div>';
                    html += '<div class="viwebpos-popup-content-value">';
                    html += '<select class="viwebpos-add-customer-country vi-ui fluid search dropdown" data-value_default="' + shop_country + '" ><option value="">' + viwebpos.customer_country_select + '</option>';
                    $.each(viwebpos.wc_countries, function (k, v) {
                        html += '<option value="' + k + '" ' + '>' + v + '</option>';
                    });
                    html += '</select>';
                    html += '</div></div>';
                    html += '<div class="viwebpos-popup-content-vertical viwebpos-popup-content-half-row">';
                    html += '<div class="viwebpos-popup-content-title">' + viwebpos.customer_state + '</div>';
                    html += '<div class="viwebpos-popup-content-value"><input type="text" class="viwebpos-add-customer-state" data-value_default="" value=""></div>';
                    html += '</div></div>';
                    html += '<div class="viwebpos-popup-content">';
                    html += '<div class="viwebpos-popup-content-vertical viwebpos-popup-content-half-row">';
                    html += '<div class="viwebpos-popup-content-title">' + viwebpos.customer_city + '</div>';
                    html += '<div class="viwebpos-popup-content-value"><input type="text" class="viwebpos-add-customer-city" data-value_default="" value=""></div>';
                    html += '</div>';
                    html += '<div class="viwebpos-popup-content-vertical viwebpos-popup-content-half-row">';
                    html += '<div class="viwebpos-popup-content-title">' + viwebpos.customer_postcode + '</div>';
                    html += '<div class="viwebpos-popup-content-value"><input type="text" class="viwebpos-add-customer-postcode" data-value_default="" value=""></div>';
                    html += '</div></div>';
                    break;
                case "print-receipt":
                    html += '<div class="viwebpos-popup-content">';
                    html += '<div class="viwebpos-popup-content-vertical viwebpos-popup-content-full-row">';
                    html += '<input type="hidden" class="viwebpos-print-receipt-order" data-value_default="" value="">';
                    html += '<div class="viwebpos-popup-content-value"></div>';
                    html += '</div></div>';
                    break;
                case "add-transaction":
                    html += '<div class="viwebpos-popup-content">';
                    html += '<div class="viwebpos-popup-content-horizontal viwebpos-popup-content-full-row viwebpos-popup-content-add-transaction-type">';
                    html += '<div class="viwebpos-popup-content-title">' + viwebpos.transaction_add_type_title + '</div>';
                    html += '<div class="viwebpos-popup-content-value">';
                    html += '<div class="vi-ui radio checkbox">';
                    html += '<input type="radio" value="in" name="new-transaction-type" class="viwebpos-popup-content-add-transaction-type-value" id="viwebpos-popup-content-add-transaction-type-in" checked>';
                    html += '<label for="viwebpos-popup-content-add-transaction-type-in">' + viwebpos.transaction_add_type_in + '</label>';
                    html += '</div>';
                    html += '<div class="vi-ui radio checkbox">';
                    html += '<input type="radio" value="out" name="new-transaction-type" class="viwebpos-popup-content-add-transaction-type-value" id="viwebpos-popup-content-add-transaction-type-out">';
                    html += '<label for="viwebpos-popup-content-add-transaction-type-out">' + viwebpos.transaction_add_type_out + '</label>';
                    html += '</div>';
                    html += '</div></div></div>';
                    html += '<div class="viwebpos-popup-content">';
                    html += '<div class="viwebpos-popup-content-horizontal viwebpos-popup-content-full-row viwebpos-popup-content-add-transaction-price">';
                    html += '<div class="viwebpos-popup-content-title">' + viwebpos.transaction_add_price_title + '</div>';
                    html += '<div class="viwebpos-popup-content-value">';
                    html += viwebpos.transaction_add_price_value;
                    html += '</div></div></div>';
                    html += '<div class="viwebpos-popup-content">';
                    html += '<div class="viwebpos-popup-content-horizontal viwebpos-popup-content-full-row viwebpos-popup-content-add-transaction-reason">';
                    html += '<div class="viwebpos-popup-content-title">' + viwebpos.transaction_add_reason_title + '</div>';
                    html += '<div class="viwebpos-popup-content-value">';
                    html += '<textarea rows="4" cols="40" class="viwebpos-popup-content-add-transaction-reason-value"></textarea>';
                    html += '</div></div></div>';
                    break;
                case "settings-pos":
                    let default_settings = viwebpos_text.settings_default;
                    $.each(default_settings, function (k, v) {
                        html += '<div class="viwebpos-popup-content">';
                        html += `<div class="viwebpos-popup-content-${is_mobile ? 'vertical' : 'horizontal'} viwebpos-popup-content-full-row viwebpos-settings-pos-${k}">`;
                        html += `<div class="viwebpos-popup-content-title">${v.title || v}</div>`;
                        html += '<div class="viwebpos-popup-content-value">';
                        if (typeof v !== "object") {
                            html += `<div class="vi-ui toggle checkbox"><input type="checkbox" data-name="${k}" class="viwebpos-settings-pos-${k}-checkbox"><label></label></div>`;
                        } else {
                            let select_multi = v.multi ? 'multiple' : '';
                            html += `<select class="viwebpos-popup-setting-${k} ${(select_multi ? 'viwebpos-popup-setting-multi' : '')} vi-ui fluid search dropdown" data-name="${k}" ${select_multi}>`;
                            $.each(v.info, function (k1, v1) {
                                html += `<option value="${k1}">${v1}</option>`;
                            });
                            html += '</select>';
                        }
                        html += '</div></div></div>';
                    });
                    break;
            }
            html += '</div>';
            html += '<div class="viwebpos-popup-footer-wrap">';
            switch (type) {
                case "add-product":
                    html += '<button class="vi-ui button teal viwebpos-popup-bt viwebpos-popup-bt-' + type + '">' + viwebpos.custom_product_atc_title + '</button>';
                    break;
                case "add-customer":
                    html += '<button class="vi-ui button teal viwebpos-popup-bt viwebpos-popup-bt-' + type + '">' + viwebpos.add_new_customer_bt + '</button>';
                    break;
                case "print-receipt":
                    html += '<button class="vi-ui button teal viwebpos-popup-bt viwebpos-popup-bt-' + type + '">' + viwebpos.print_button_title + '</button>';
                    break;
                case "add-transaction":
                    html += '<button class="vi-ui button teal viwebpos-popup-bt viwebpos-popup-bt-' + type + '">' + viwebpos.transaction_add_bt_title + '</button>';
                    break;
            }
            html += '</div>';
            html += '</div></div></div></div>';
            $('.viwebpos-container-wrap').append(html);
            switch (type) {
                case "add-customer":
                    $('.viwebpos-popup-wrap-add-customer .viwebpos-add-customer-country').dropdown('set selected', viwebpos_price.shop_address.country || '');
                    break;
                default:
                    if ($('.viwebpos-popup-wrap-' + type).find('select.vi-ui.dropdown').length) {
                        $('.viwebpos-popup-wrap-' + type).find('select.vi-ui.dropdown').off().dropdown();
                    }
            }
        });
        $(document).on('click', '.viwebpos-wrap a, .viwebpos-wrap .viwebpos-url', function (e) {
            if ($(this).hasClass('viwebpos-menu-item-logout')) {
                if (!confirm(viwebpos.logout_message)) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                } else {
                    return true;
                }
            } else if ($(this).hasClass('viwebpos-menu-item-settings')) {
                e.preventDefault();
                e.stopPropagation();
                $('.viwebpos-popup-wrap-settings-pos').removeClass('viwebpos-popup-wrap-hidden').addClass('viwebpos-popup-wrap-show');
                return true;
            } else if ($(this).hasClass('viwebpos-menu-item-refresh_database')) {
                e.preventDefault();
                e.stopPropagation();
                let database = viwebpos_data.viwebposDB.result;
                viwebpos_data.delete(database.transaction('settings', 'readwrite').objectStore('settings'), 'data_prefix');
                location.reload();
                return true;
            } else if ($(this).hasClass('viwebpos-menu-item-my-account')) {
                return true;
            }
            let next = $(this).attr('href') || $(this).data('href'),
                current = window.location.pathname;
            e.preventDefault();
            e.stopPropagation();
            let next_t = $(document.body).triggerHandler('viwebpos-get-current-url', [$(this), next]);
            if (typeof next_t !== "undefined") {
                next = next_t;
            }
            if (!next || next === '#' || current === next) {
                return false;
            }
            let page = 'bill-of-sale', temp = '';
            if (viwebpos.pos_pathname.includes('?') || next.includes('?')) {
                let next_t = next.split('?')[0],
                    pos_pathname = viwebpos.pos_pathname.split('?')[0];
                temp = next_t.replace(pos_pathname, '');
                if (temp === next_t) {
                    temp = decodeURIComponent(next_t).replace(pos_pathname, '');
                }
            } else {
                temp = next.replace(viwebpos.pos_pathname, '');
                if (temp === next) {
                    temp = decodeURIComponent(next).replace(viwebpos.pos_pathname, '');
                }
            }
            if (temp && temp !== '/') {
                if (temp.indexOf('?') > 0) {
                    temp = temp.substring(0, temp.indexOf('?'));
                }
                temp = temp.indexOf('/') === 0 ? temp.substring(1).split('/') : temp.split('/');
                page = temp[0] ? temp[0] : page;
            }
            window.history.pushState({page: page}, '', next);
            window.history.replaceState({page: page}, '', next);
            $(document.body).trigger('viwebpos-frontend-load', [page]);
        });
        $(document.body).on('viwebpos_popup_close', function () {
            $('.viwebpos-popup-wrap.viwebpos-popup-wrap-show').each(function (k, v) {
                $(v).find('.viwebpos-popup-close').trigger('click');
            });
        });
        $(document).on('click', '.viwebpos-popup-close, .viwebpos-overlay:not(.viwebpos-overlay-loading)', function (e) {
            let wrap = $(this).closest('.viwebpos-popup-wrap');
            if (wrap.hasClass('viwebpos-popup-wrap-not-close')) {
                return false;
            }
            if (wrap.hasClass('viwebpos-popup-wrap-show')) {
                wrap.removeClass('viwebpos-popup-wrap-show').addClass('viwebpos-popup-wrap-hidden');
                wrap.find('.viwebpos-popup-content-value-error').removeClass('viwebpos-popup-content-value-error');
                wrap.find('.viwebpos-popup-bt-loading').removeClass('viwebpos-popup-bt-loading');
            }
            $(document.body).trigger('viwebpos_popup_after_close', [wrap]);
        });
        $(document).on('blur change', 'input[type = "number"]', function () {
            if (!$(this).val()) {
                return false;
            }
            let new_val, min = parseFloat($(this).attr('min')) || 0,
                max = parseFloat($(this).attr('max')),
                val = parseFloat($(this).val()),
                can_reset = $(this).data('can_reset') || '';
            new_val = val;
            if (min > val) {
                new_val = can_reset ? '' : min;
            }
            if (max && max < val) {
                if ($(this).hasClass('viwebpos-cart-item-qty')) {
                    return false;
                }
                new_val = can_reset ? '' : max;
            }
            $(this).val(new_val);
        });
        keyboard_shortcut();

        function keyboard_shortcut() {
            $(document).on('keydown', function (e) {
                let keycode = e.which || e.keyCode;
                let settings = [
                    113,//Add custom product
                    114,//Add new customer
                    115,//Add discount
                    117,//search product
                    118,//search customer
                    119,//Focus customer paid
                    120,//Choose payment method
                    121,//Checkout
                    38,//Increase the number of products
                    40,//Decrease the number of products
                    27,//Close anything
                    13,//send from
                ];
                if ($.inArray(keycode, settings) < 0) {
                    return true;
                }
                if (keycode === 13 && ($('.viwebpos-popup-wrap-add-customer.viwebpos-popup-wrap-show').length || $('.viwebpos-customer-manage-wrap').length)) {
                    e.preventDefault();
                    e.stopPropagation();
                    if ($('.viwebpos-customer-manage-wrap').length) {
                        $('.viwebpos-customer-manage-wrap').find('.viwebpos-customer-submit').trigger('click');
                    } else {
                        $('.viwebpos-popup-wrap-add-customer.viwebpos-popup-wrap-show').find('.viwebpos-popup-bt').trigger('click');
                    }
                    return false;
                }
                if (keycode === 13 && !$('.viwebpos-checkout-form-content-paid-input:not(.viwebpos-checkout-form-content-amount-input):focus').length) {
                    return true;
                }
                if ((keycode === 38 || keycode === 40) && ($('.viwebpos-popup-wrap-show').length || $('.viwebpos-checkout-form-container input:focus').length)) {
                    return true;
                }
                e.preventDefault();
                e.stopPropagation();
                switch (keycode) {
                    case 38:
                        if ($(':hover').closest('.viwebpos-cart-item-wrap').length) {
                            let wrap = $(':hover').closest('.viwebpos-cart-item-wrap');
                            if (wrap.hasClass('viwebpos-cart-item-wrap-note')) {
                                wrap = wrap.prev('.viwebpos-cart-item-wrap');
                            }
                            let input_qty = wrap.find('.viwebpos-cart-item-qty');
                            input_qty.val(parseInt(input_qty.val() || 0) + 1).trigger('change');
                        }
                        break;
                    case 40:
                        if ($(':hover').closest('.viwebpos-cart-item-wrap').length) {
                            let wrap = $(':hover').closest('.viwebpos-cart-item-wrap');
                            if (wrap.hasClass('viwebpos-cart-item-wrap-note')) {
                                wrap = wrap.prev('.viwebpos-cart-item-wrap');
                            }
                            let input_qty = wrap.find('.viwebpos-cart-item-qty');
                            input_qty.val(parseInt(input_qty.val() || 0) - 1).trigger('change');
                        }
                        break;
                    case 113:
                        if ($('.viwebpos-popup-wrap-show').length) {
                            break;
                        }
                        $('.viwebpos-add-custom-product-wrap').trigger('click');
                        break;
                    case 114://Add new customer
                        if ($('.viwebpos-popup-wrap-show').length) {
                            break;
                        }
                        if ($('.viwebpos-add-customer-wrap').length) {
                            $('.viwebpos-add-customer-wrap').trigger('click');
                        } else {
                            $('.viwebpos-checkout-form-customer-search-icon').trigger('click');
                        }
                        break;
                    case 115://Add discount
                        if ($('.viwebpos-popup-wrap-show').length) {
                            break;
                        }
                        $('.viwebpos-checkout-form-footer-coupon:not(.viwebpos-hidden)').trigger('click');
                        break;
                    case 117://search product
                        if ($('.viwebpos-popup-wrap-show').length) {
                            break;
                        }
                        $('.viwebpos-search-product').trigger('focus');
                        break;
                    case 118://search customer
                        if ($('.viwebpos-popup-wrap-show').length) {
                            break;
                        }
                        $('.viwebpos-search-customer, .viwebpos-checkout-search-customer').trigger('focus');
                        break;
                    case 119://Focus customer paid
                        if ($('.viwebpos-popup-wrap-show').length) {
                            break;
                        }
                        if (!$('.viwebpos-checkout-form-content-wrap-payment').length) {
                            $('.viwebpos-checkout-form-content-paid-input:not(.viwebpos-checkout-form-content-amount-input)').trigger('focus');
                        } else if ($('.viwebpos-checkout-form-content-wrap-payment').hasClass('viwebpos-hidden')) {
                            if ($('.viwebpos-checkout-form-content-wrap-total .viwebpos-checkout-form-content-paid1 .viwebpos-checkout-form-content-paid1-info').length < 2) {
                                $('.viwebpos-checkout-form-content-paid-input:not(.viwebpos-checkout-form-content-amount-input)').trigger('focus');
                            } else {
                                $('.viwebpos-checkout-form-footer-payment').trigger('click');
                            }
                        } else {
                            $('.viwebpos-checkout-form-content-amount-input.viwebpos-checkout-form-content-paid-input').trigger('focus');
                        }
                        break;
                    case 120:
                        if (!$('.viwebpos-popup-wrap-show').length) {
                            $('.viwebpos-checkout-form-footer-payment').trigger('click');
                        }
                        break;
                    case 121:
                        if ($('.viwebpos-popup-wrap-show').length) {
                            break;
                        }
                        $('.viwebpos-checkout-form-footer-checkout-wrap').trigger('click');
                        break;
                    case 13:
                        if ($('.viwebpos-popup-wrap-show').length) {
                            break;
                        }
                        $('.viwebpos-checkout-form-content-paid-input:focus').addClass('viwebpos-checkout-form-content-paid-input-checkout_after_change');
                        break;
                    default://27
                        $(document.body).trigger('viwebpos_popup_close');
                }
            })
        }

        $(document.body).on('viwebpos-frontend-load', function (e, page = '') {
            current_page = page ? page : (viwebpos.page_request[1] || '');
            $('.viwebpos-wrap').addClass('viwebpos-wrap-loading');
            if (is_mobile) {
                $('.viwebpos-wrap').addClass('viwebpos-wrap-mobile');
            } else {
                $('.viwebpos-wrap').removeClass('viwebpos-wrap-mobile');
            }
            $('.viwebpos-container-wrap, .viwebpos-header-search-wrap').html(null);
            $(document.body).trigger('viwebpos-frontend-before-load', current_page);
            switch (current_page) {
                case 'orders':
                case 'transactions':
                case 'customers':
                    $(document.body).trigger('viwebpos-' + current_page + '-load');
                    $('.viwebpos-menu-item-settings').addClass('viwebpos-hidden');
                    break;
                default:
                    if (!current_page || current_page === 'bill-of-sale' || !$(document.body).triggerHandler('viwebpos-' + current_page + '-load')) {
                        $(document.body).trigger('viwebpos-bill-of-sale-load');
                        $('.viwebpos-menu-item-settings').removeClass('viwebpos-hidden');
                    }
            }
        });
        $(document.body).on('viwebpos-frontend-loaded', function (e, page = '') {
            $('.viwebpos-header-right-wrap').find('.viwebpos-mobile-icon').remove();
            if (is_mobile && ['transactions'].indexOf(page) === -1) {
                let html = page === 'customers' ? '' : '<div class="viwebpos-mobile-icon-show viwebpos-mobile-icon"><i class="shopping cart icon"></i></div>';
                html += '<div class="viwebpos-mobile-icon-back viwebpos-mobile-icon viwebpos-hidden"><i class="reply all icon"></i></div>';
                $('.viwebpos-container-element > div').eq(1).addClass('viwebpos-mobile-container');
                $('.viwebpos-header-right-wrap').append($(document).triggerHandler('viwebpos_get_mobile_icon_html', [html, page]) || html);
            }
        });
        $(document.body).on('viwebpos-mobile-show-content', function (e, button) {
            $('.viwebpos-mobile-container').toggleClass('viwebpos-mobile-container-show');
            $('.viwebpos-mobile-icon').toggleClass('viwebpos-hidden');
        });
        $(document).on('click', '.viwebpos-mobile-icon, .viwebpos-wrap-mobile .viwebpos-add-customer-wrap', function () {
            if ($(this).hasClass('viwebpos-add-customer-wrap') && $('.viwebpos-mobile-container-show').length) {
                return true;
            }
            $(document.body).trigger('viwebpos-mobile-show-content', [$(this)]);
        });
    });
    $(window).on('popstate', function (e) {
        let page;
        if (window.history.state) {
            page = window.history.state.page || '';
        }
        if (!page) {
            let current = viwebpos.pos_pathname.includes('://') ? window.location.href : window.location.pathname, temp = '';
            page = 'bill-of-sale';
            if (viwebpos.pos_pathname.includes('?') || current.includes('?')) {
                let next_t = current.split('?')[0],
                    pos_pathname = viwebpos.pos_pathname.split('?')[0];
                temp = next_t.replace(pos_pathname, '');
                if (temp === next_t) {
                    temp = decodeURIComponent(next_t).replace(pos_pathname, '');
                }
            } else {
                temp = current.replace(viwebpos.pos_pathname, '');
                if (temp === current) {
                    temp = decodeURIComponent(current).replace(viwebpos.pos_pathname, '');
                }
            }
            if (temp && temp !== '/') {
                if (temp.indexOf('?') > 0) {
                    temp = temp.substring(0, temp.indexOf('?'));
                }
                temp = temp.indexOf('/') === 0 ? temp.substring(1).split('/') : temp.split('/');
                page = temp[0] ? temp[0] : page;
            }
        }
        $(document.body).trigger('viwebpos-frontend-load', [page]);
    });
    window.viwebpos_ajax = function (options) {
        let custom_options = $(document).triggerHandler('viwebpos_ajax_get_custom_options', [options]);
        if (custom_options && custom_options.url && options.type && options.data && custom_options.success) {
            options = custom_options;
        }
        fetch(options.url, {
            method: options.type,
            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
            body: options.data
        })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        throw {statusText: response.statusText, responseText: text}
                    });
                }
                return response.json();
            })
            .then(options.success)
            .catch(error => options.error && options.error(error))
            .finally(() => options.complete && options.complete());
    }
})(jQuery);