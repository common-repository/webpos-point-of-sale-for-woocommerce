jQuery(document).ready(function ($) {
    'use strict';
    let search_customers, search_customers_result = {}, wc_states = viwebpos.wc_states || '';
    $(document.body).on('viwebpos_customer_state_init', function (e, country, state, default_html = '', default_value = '') {
        state = $(state);
        if (!country || !wc_states) {
            state.replaceWith(default_html);
            return false;
        }
        let state_html = default_html, states, is_select = false;
        if (wc_states[country]) {
            states = wc_states[country];
            if (states && Object.keys(states).length) {
                is_select = true;
                state_html = '<select class="vi-ui fluid search dropdown viwebpos-customer-search-state ' + $(default_html).attr('class') + '"><option value="">' + viwebpos.customer_country_select + '</option>';
                $.each(states, function (k, v) {
                    state_html += '<option value="' + k + '" ' + '>' + v + '</option>';
                });
                state_html += '</select>';
            } else {
                state_html = '<input type="text" class="' + $(default_html).attr('class') + '" value="" disabled>';
            }
        }
        state.replaceWith(state_html);
        if (is_select) {
            $('.vi-ui.dropdown.viwebpos-customer-search-state').dropdown('set selected', default_value);
        }
    });
    $(document.body).on('viwebpos_customer_fields_validate', function (e, fields, class_error = '') {
        let errors = [];
        if (!fields || !Object.keys(fields).length) {
            return errors = ['Can not find fields to validate!'];
        }
        let required_fields = ['customer_name'];
        if (required_fields.indexOf('customer_name') > -1) {
            if (!$(fields.first_name).length || !$(fields.last_name).length) {
                return errors = ['Can not find fields to validate!'];
            }
            if (!$(fields.first_name).val() && !$(fields.last_name).val()) {
                $(fields.first_name).addClass(class_error);
                $(fields.last_name).addClass(class_error);
                errors.push(viwebpos.error_customer_name_empty);
                return errors;
            }
        }
        if (required_fields.indexOf('email') === -1 && !$(document.body).triggerHandler('viwebpos_check_is_email', $(fields.email).val())) {
            errors.push(viwebpos.error_customer_invalid_email);
            $(fields.email).addClass(class_error);
        }
        if (required_fields.indexOf('phone') === -1 && !$(document.body).triggerHandler('viwebpos_check_is_phone', $(fields.phone).val())) {
            errors.push(viwebpos.error_customer_invalid_phone);
            $(fields.phone).addClass(class_error);
        }
        return errors;
    });
    $(document.body).on('viwebpos_check_is_email', function (e, email = '') {
        if (!email) {
            return true;
        }
        if (typeof email !== 'string') {
            return false;
        }
        return email.toLowerCase().match(/^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/);
    });
    $(document.body).on('viwebpos_check_is_phone', function (e, phone = '') {
        if (typeof phone !== 'string') {
            return false;
        }
        if (!phone) {
            return true;
        }
        return !phone.replaceAll(/[\s\#0-9_\-\+\/\(\)\.]/ig, '').trim().length;
    });

    $(document.body).on('viwebpos-customers-load', function () {
        if (!$('.viwebpos-header-search-customer').length) {
            let html = '<div class="viwebpos-header-search viwebpos-header-search-customer">';
            if ($('.viwebpos-wrap.rtl').length) {
                html += `<div class="viwebpos-header-search-icon viwebpos-add-customer-wrap" data-position="bottom right" data-tooltip="${viwebpos.add_new_customer}">&#43;</div>`;
            } else {
                html += `<div class="viwebpos-header-search-icon viwebpos-add-customer-wrap" data-position="bottom left" data-tooltip="${viwebpos.add_new_customer}">&#43;</div>`;
            }
            html += '<input type="text" class="viwebpos-search-input viwebpos-search-customer" placeholder="' + viwebpos.search_customer + '">';
            html += '</div>';
            $('.viwebpos-header-search-wrap').html(html);
        }
        if (!$('.viwebpos-customers-container').length) {
            let html = '<div class="viwebpos-container-element viwebpos-customers-container"><div class="viwebpos-customers-list-wrap">';
            html += '<div class="viwebpos-customers-list"></div></div><div class="viwebpos-customer-manage-wrap">';

            html += '<div class="viwebpos-customer-manage-header">' + viwebpos.add_new_customer + '</div>';

            html += '<form class="viwebpos-customer-manage-details">';
            html += '<div class="viwebpos-customer-col-2">' +
                '<label>' + viwebpos.customer_first_name + '<input class="viwebpos-customer-fname" type="text" placeholder="Enter First Name" value=""></label>' +
                '<label>' + viwebpos.customer_last_name + '<input class="viwebpos-customer-lname" type="text" placeholder="Enter Last Name" value=""></label>' +
                '</div>';
            html += '<label>' + viwebpos.customer_email + '<input class="viwebpos-customer-email" type="text" placeholder="Enter Email" value=""></label>';
            html += '<label>' + viwebpos.customer_phone + '<input class="viwebpos-customer-phone" type="text" placeholder="Enter Phone" value=""></label>';
            html += '<label>' + viwebpos_text.customer_address1 + '<input class="viwebpos-customer-address1" type="text" placeholder="Enter Address line 1" value=""></label>';
            html += '<label>' + viwebpos_text.customer_address2 + '<input class="viwebpos-customer-address2" type="text" placeholder="Enter Address line 2" value=""></label>';
            html += '<div class="viwebpos-customer-col-2">' + '<label>' + viwebpos.customer_country + '<select class="viwebpos-customer-country vi-ui fluid search dropdown">';

            html += '<option value="">' + viwebpos.customer_country_select + '</option>';
            $.each(viwebpos.wc_countries, function (key, val) {
                html += '<option value="' + key + '">' + val + '</option>';
            });

            html += '</select></label>' +
                '<label>' + viwebpos.customer_state + '<input class="viwebpos-customer-state" type="text" placeholder="Enter State" value=""></label>' +
                '</div>';
            html += '<div class="viwebpos-customer-col-2">' +
                '<label>' + viwebpos.customer_city + '<input class="viwebpos-customer-city" type="text" placeholder="Enter City" value=""></label>' +
                '<label>' + viwebpos.customer_postcode + '<input class="viwebpos-customer-postcode" type="number" placeholder="Enter Postcode" value=""></label>' +
                '</div>';
            html += '<button type="button" class="vi-ui button teal viwebpos-customer-submit"><span><i class="save outline icon"></i></span>' + viwebpos_text.save_title + '</button>';
            html += '</form>';

            html += '</div></div>';
            $('.viwebpos-container-wrap').append(html);
            let country = viwebpos_price.shop_address.country || '';
            $('.viwebpos-customer-country').off().dropdown('set selected', country);
            $(document.body).trigger('viwebpos_customer_state_init', [country, $('.viwebpos-customer-state'),
                '<input class="viwebpos-customer-state" type="text" placeholder="Enter State" value="">']);
        }
        if (location.search) {
            let search_key = new URLSearchParams(location.search.substring(1));
            search_key = search_key.get('customer');
            if (search_key) {
                $(document.body).trigger('viwebpos_refresh_customers', [search_key, 1, 1, false, 'customer']);
            } else {
                $(document.body).trigger('viwebpos_refresh_customers');
            }
        } else {
            $(document.body).trigger('viwebpos_refresh_customers');
        }
        setTimeout(function () {
            $(document.body).trigger('viwebpos-frontend-loaded', ['customers']);
        }, 100);
    });

    $(document.body).on('viwebpos_refresh_customers', function (e, key = '', page = 1, limit = 20, change_page = false, type = 'customers') {
        if (search_customers) {
            clearTimeout(search_customers);
        }
        search_customers = setTimeout(function (key, type_t) {
            let customers = $(document.body).triggerHandler('viwebpos_search_data', [type_t, key, limit, page]);
            $('.viwebpos-wrap').addClass('viwebpos-wrap-loading');
            if (typeof customers.then === 'function') {
                customers.then(function (result) {
                    if (result && typeof result.id !== "undefined") {
                        setTimeout(function (customer) {
                            $(document.body).trigger('viwebpos_customer_manage', [customer]);
                        }, 100, result);
                        result = [result];
                    }
                    viwebpos_init_customers(result, page, key, change_page);
                });
            } else {
                if (customers && typeof customers.id !== "undefined") {
                    setTimeout(function (customer) {
                        $(document.body).trigger('viwebpos_customer_manage', [customer]);
                    }, 100, customers);
                    customers = [customers];
                }
                viwebpos_init_customers(customers, page, key, change_page);
            }
        }, 100, key, type);
    });
    $(document).on('click', '.viwebpos-search-customer-more', function (e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        let search_wrap = $('.viwebpos-search-input.viwebpos-search-customer'),
            to_page = search_wrap.data('page') ? search_wrap.data('page') : '',
            key = search_wrap.data('old_key') || '';
        if (!to_page) {
            return false;
        }
        $(this).find('.button').addClass('loading');
        $(document.body).trigger('viwebpos_refresh_customers', [key, to_page, undefined, true]);
    });
    $(document).on('click keyup', '.viwebpos-search-customer', function (e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        let $this = $(this);
        let val = $this.val(),
            old_key = $this.data('old_key') || '';
        if (val === old_key) {
            return false;
        }
        $this.data('page', 1);
        $this.data('old_key', val);
        $(document.body).trigger('viwebpos_refresh_customers', [val]);
    });

    $(document).on('click', '.viwebpos-search-customer-row', function (e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        let $this = $(this);
        let customer_id = $(this).data('id'),
            old_customer_id = $('.viwebpos-customer-manage-wrap').data('id') ? $('.viwebpos-customer-manage-wrap').data('id') : '';
        if (!customer_id) {
            return false;
        }
        if (old_customer_id && old_customer_id == customer_id) {
            return false;
        }
        let customer = $(document.body).triggerHandler('viwebpos_search_data', ['customer', customer_id, 21, 1]);
        if (typeof customer.then === 'function') {
            customer.then(function (result) {
                $(document.body).trigger('viwebpos_customer_manage', [result]);
            })
        } else {
            $(document.body).trigger('viwebpos_customer_manage', [customer]);
        }
        $('.viwebpos-customer-active').removeClass('viwebpos-customer-active');
        $('.viwebpos-content-value-error').removeClass('viwebpos-content-value-error');
        $(this).addClass('viwebpos-customer-active');
    });

    $(document.body).on('viwebpos_customer_manage', function (e, customer) {
        let wrap = $('.viwebpos-customer-manage-wrap');
        if (!customer) {
            return;
        }
        let user = customer.first_name + ' ' + customer.last_name,
            fname = wrap.find('.viwebpos-customer-fname'),
            lname = wrap.find('.viwebpos-customer-lname'),
            email = wrap.find('.viwebpos-customer-email'),
            phone = wrap.find('.viwebpos-customer-phone'),
            address1 = wrap.find('.viwebpos-customer-address1'),
            address2 = wrap.find('.viwebpos-customer-address2'),
            country = wrap.find('.viwebpos-customer-country'),
            state = wrap.find('.viwebpos-customer-state'),
            city = wrap.find('.viwebpos-customer-city'),
            postcode = wrap.find('.viwebpos-customer-postcode'),
            submit = wrap.find('.viwebpos-customer-submit');
        let customer_state = customer.billing_address.state;
        if (viwebpos.wc_states[customer.billing_address.country] && viwebpos.wc_states[customer.billing_address.country][customer_state]) {
            customer_state = viwebpos.wc_states[customer.billing_address.country][customer_state];
        }
        wrap.data('id', customer.id).data('username', customer.username);
        wrap.find('.viwebpos-customer-manage-header').html(user);
        fname.val(customer.first_name).prop('disabled', true).attr('placeholder', '');
        lname.val(customer.last_name).prop('disabled', true).attr('placeholder', '');
        email.val(customer.email).prop('disabled', true).attr('placeholder', '');
        phone.val(customer.phone).prop('disabled', true).attr('placeholder', '');
        address1.val(customer.billing_address.address_1).prop('disabled', true).attr('placeholder', '');
        address2.val(customer.billing_address.address_2).prop('disabled', true).attr('placeholder', '');
        country.addClass('disabled').dropdown('set selected', customer.billing_address.country);
        state.replaceWith('<input class="viwebpos-customer-state" type="text" value="' + customer_state + '" disabled />');
        city.val(customer.billing_address.city).prop('disabled', true).attr('placeholder', '');
        postcode.val(customer.billing_address.postcode).prop('disabled', true).attr('placeholder', '');
        submit.addClass('viwebpos-hidden');
    });

    $(document).on('click', '.viwebpos-customer-submit', function (e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        $(document.body).trigger('villatheme_show_message_timeout', [$('.villatheme-show-message-message-customer-adding'), 1]);
        let $wrap = $(this).closest('.viwebpos-customer-manage-wrap');
        $('.viwebpos-content-value-error').removeClass('viwebpos-content-value-error');

        let fields = {
            first_name: $wrap.find('.viwebpos-customer-fname'),
            last_name: $wrap.find('.viwebpos-customer-lname'),
            email: $wrap.find('.viwebpos-customer-email'),
            phone: $wrap.find('.viwebpos-customer-phone'),
            address_1: $wrap.find('.viwebpos-customer-address1'),
            postcode: $wrap.find('.viwebpos-customer-postcode'),
            city: $wrap.find('.viwebpos-customer-city'),
            state: $wrap.find('.viwebpos-customer-state'),
            country: $wrap.find('.viwebpos-customer-country'),
        };
        let errors = $(document.body).triggerHandler('viwebpos_customer_fields_validate', [fields, 'viwebpos-content-value-error']),
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
            $wrap.find('.viwebpos-content-value-error').eq(0).trigger('focus');
            return false;
        }
        $('.viwebpos-wrap').addClass('viwebpos-wrap-loading');
        let customer = {
            email: $wrap.find('.viwebpos-customer-email').val() || '',
            first_name: $wrap.find('.viwebpos-customer-fname').val() || '',
            last_name: $wrap.find('.viwebpos-customer-lname').val() || '',
            billing_address: {
                address_1: $wrap.find('.viwebpos-customer-address1').val(),
                address_2: $wrap.find('.viwebpos-customer-address2').val(),
                city: $wrap.find('.viwebpos-customer-city').val(),
                country: $wrap.find('.viwebpos-customer-country').val(),
                state: $wrap.find('.viwebpos-customer-state').val(),
                postcode: $wrap.find('.viwebpos-customer-postcode').val(),
                phone: $wrap.find('.viwebpos-customer-phone').val() || '',
            }
        };
        if ($wrap.find('.viwebpos-customer-country select').length) {
            customer.billing_address.country = $wrap.find('.viwebpos-customer-country select').val();
        }
        if ($wrap.find('.viwebpos-customer-state select').length) {
            customer.billing_address.state = $wrap.find('.viwebpos-customer-state select').val();
        }
        $(document.body).trigger('viwebpos_add_new_customer', [customer]);
    });

    $(document).on('click', '.viwebpos-add-customer-wrap', function (e) {
        let wrap = $('.viwebpos-customer-manage-wrap'),
            wrap_state = wrap.find('.viwebpos-customer-state');
        if (!$('.viwebpos-search-customer-row').length) {
            wrap.find('.viwebpos-customer-fname').trigger('focus');
            return false;
        }
        $('.viwebpos-customer-active').removeClass('viwebpos-customer-active');
        $('.viwebpos-customer-active').removeClass('viwebpos-customer-active');
        wrap.data('id', '').data('username', '');
        wrap.find('.viwebpos-customer-manage-header').html(viwebpos.add_new_customer);
        wrap.find('.viwebpos-customer-fname').val('').prop('disabled', false).attr('placeholder', 'Enter First Name');
        wrap.find('.viwebpos-customer-lname').val('').prop('disabled', false).attr('placeholder', 'Enter Last Name');
        wrap.find('.viwebpos-customer-email').val('').prop('disabled', false).attr('placeholder', 'Enter Email');
        wrap.find('.viwebpos-customer-phone').val('').prop('disabled', false).attr('placeholder', 'Enter Phone Number');
        wrap.find('.viwebpos-customer-address1').val('').prop('disabled', false).attr('placeholder', 'Enter Address Line 1');
        wrap.find('.viwebpos-customer-address2').val('').prop('disabled', false).attr('placeholder', 'Enter Address Line 2');
        wrap.find('.viwebpos-customer-country').val('').prop('disabled', false);
        wrap_state.val('').prop('disabled', false).attr('placeholder', 'Enter State');
        wrap.find('.viwebpos-customer-city').val('').prop('disabled', false).attr('placeholder', 'Enter City');
        wrap.find('.viwebpos-customer-postcode').val('').prop('disabled', false).attr('placeholder', 'Enter Postcode');
        wrap.find('.viwebpos-customer-submit').removeClass('viwebpos-hidden');
        wrap.find('.viwebpos-customer-fname').trigger('focus');
        let country = viwebpos_price.shop_address.country || '';
        $('.viwebpos-customer-country').removeClass('disabled').dropdown('set selected', country);
        $(document.body).trigger('viwebpos_customer_state_init', [country, wrap_state,
            '<input class="viwebpos-customer-state" type="text" placeholder="Enter State" value="">']);
    });

    $(document).on('change', '.viwebpos-customer-country select', function (e) {
        if ($(this).closest('.dropdown.disabled').length) {
            return false;
        }
        let state = $('.viwebpos-customer-state');
        $(document.body).trigger('viwebpos_customer_state_init', [$(this).val(), state,
            '<input class="viwebpos-customer-state" type="text" placeholder="Enter State" value="">']);
    });

    function viwebpos_init_customers(customers, current_page = 1, key = '', change_page = false) {
        $('.viwebpos-wrap').removeClass('viwebpos-wrap-loading');
        let wrap = $('.viwebpos-customers-container'),
            customer_wrap = $('.viwebpos-customer-manage-wrap'),
            list_wrap = $('.viwebpos-customers-list'),
            search_wrap = $('.viwebpos-search-input.viwebpos-search-customer'),
            html = '',
            next_page = false;

        if (!list_wrap.length) {
            $('.viwebpos-customers-list-wrap').prepend('<div class="viwebpos-customers-list"></div>');
            list_wrap = $('.viwebpos-customers-list');
        }
        wrap.find('.viwebpos-search-customer-more').remove();
        if (current_page === 1 && !change_page) {
            list_wrap.html('');
            customer_wrap.data('id', '').find('.viwebpos-customer-fname');
            customer_wrap.find('.viwebpos-customer-fname').val('').removeClass('viwebpos-content-value-error');
            customer_wrap.find('.viwebpos-customer-lname').val('').removeClass('viwebpos-content-value-error');
            customer_wrap.find('.viwebpos-customer-email').val('').removeClass('viwebpos-content-value-error');
            customer_wrap.find('.viwebpos-customer-phone').val('').removeClass('viwebpos-content-value-error');
            customer_wrap.find('.viwebpos-customer-address1').val('');
            customer_wrap.find('.viwebpos-customer-address2').val('');
            customer_wrap.find('.viwebpos-customer-country').val('');
            customer_wrap.find('.viwebpos-customer-state').val('');
            customer_wrap.find('.viwebpos-customer-city').val('');
            customer_wrap.find('.viwebpos-customer-postcode').val('');
        }
        if (!customers) {
            list_wrap.html('<div class="viwebpos-search-customer-empty">' + (viwebpos.search_customer_empty || 'No customer found') + '</div>');
            return;
        }
        if (customers.length && customers.at(-1)['next_page']) {
            next_page = customers.at(-1)['next_page'];
            search_wrap.data('page', next_page);
            customers.pop();
        }

        $.each(customers, function (k, v) {
            if (!change_page) html = '';
            html += '<div class="viwebpos-search-customer-row" data-id="' + v.id + '">';
            html += '<div class="viwebpos-search-customer-line" >';
            html += '<div class="viwebpos-search-customer-image"><img src="' + v.avatar_url + '" alt="' + v.user_name + '"></div>';
            html += '<div class="viwebpos-search-customer-detail">';
            html += '<div class="viwebpos-search-customer-name"><span>' + v.first_name + '</span> <span>' + v.last_name + '</span> </div>';

            if (v.email != null) {
                html += '<div class="viwebpos-search-customer-email"><span><i class="envelope outline icon"></i></span>' +
                    '<span>' + v.email + '</span></div>';
            }
            if (v.phone.length) {
                html += '<div class="viwebpos-search-customer-phone"><span><i class="phone icon"></i>' +
                    '</span><span>' + v.phone + '</span></div>';
            }

            html += '</div>';
            html += '<div class="viwebpos-customer-arrow"><i class="angle right icon"></i></div>';
            html += '</div>';
            html += '</div>';
            if (!change_page) list_wrap.append(html);
        });
        if (change_page) list_wrap.append(html);
        if (next_page) {
            let more_btn = '<div class="viwebpos-search-customer-more"><span class="vi-ui teal button">' + (viwebpos.load_more_title || 'Load more') + '</span></div>';
            list_wrap.append(more_btn);
        }
        if (!html) {
            list_wrap.html('<div class="viwebpos-search-customer-empty">' + (viwebpos.search_customer_empty || 'No customer found') + '</div>');
        }
    }
});