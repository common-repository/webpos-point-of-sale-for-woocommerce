jQuery(function ($) {
    'use strict';
    $(document).ready(function () {
        $(document.body).on('viwebpos_add_new_cart', function (e, cart_data = '') {
            wiwebpos_atc_obj.add_new_cart(cart_data);
        });
        $(document.body).on('viwebpos_set_cart_customer', function (e, customer_data = null) {
            wiwebpos_atc_obj.set_cart_customer(customer_data);
        });
        $(document.body).on('viwebpos_remove_all_cart_items', function () {
            wiwebpos_atc_obj.remove_all_cart_items();
        });
        $(document.body).on('viwebpos_add_to_cart', function (e, product, variation, qty, item_serial = '') {
            wiwebpos_atc_obj.add_to_cart(product, variation, qty, item_serial);
        });
        $(document.body).on('viwebpos_add_order_note', function (e, note = '', cart_item_key = null) {
            wiwebpos_atc_obj.add_order_note(note, cart_item_key);
        });
        $(document.body).on('viwebpos_update_cart_quantity', function (e, cart_item_key, qty, refresh_totals = true) {
            wiwebpos_atc_obj.update_cart_quantity(cart_item_key, qty, refresh_totals);
        });
        $(document.body).on('viwebpos_update_cart_variation', function (e, cart_item_key, variation) {
            wiwebpos_atc_obj.update_cart_variation(cart_item_key, variation);
        });
    });
    window.wiwebpos_atc_obj = {
        cart_calculate_totals: function (messages = [], refresh = true) {
            let self = viwebpos_data;
            if (!self.viwebposDB) {
                self.viwebposDB = indexedDB.open('viwebposDB');
            }
            let cart = self.current_cart, cart_items = {};
            let cart_totals = {
                'fees_total': 0,
                'fees_total_tax': 0,
                'items_subtotal': 0,
                'items_subtotal_tax': 0,
                'items_total': 0,
                'items_total_tax': 0,
                'total': 0,
                'shipping_total': 0,
                'shipping_tax_total': 0,
                'discounts_total': 0
            };
            cart['totals'] = self.set_cart_empty({})['default_totals'];
            $('.viwebpos-wrap').addClass('viwebpos-wrap-loading');
            if ((typeof self.filter_before_calculate_totals !== "object" || !self.filter_before_calculate_totals) &&
                typeof viwebpos.filter_before_calculate_totals === "object" && Object.values(viwebpos.filter_before_calculate_totals).length) {
                self.filter_before_calculate_totals = ['viwebpos_before_calculate_totals'];
                let filter_type = Object.values(viwebpos.filter_before_calculate_totals);
                filter_type.sort(function (a, b) {
                    return parseFloat(b.priority) - parseFloat(a.priority);
                });
                filter_type.map(function (item) {
                    self.filter_before_calculate_totals.push(`viwebpos_${item.type}_before_calculate_totals`);
                });
            }
            if (self.filter_before_calculate_totals && self.filter_before_calculate_totals.length) {
                let cart_tmp = $(document.body).triggerHandler(self.filter_before_calculate_totals.pop(), [cart]);
                if (cart_tmp) {
                    if (typeof cart_tmp.then === 'function') {
                        cart_tmp.then(function (result) {
                            self.current_cart = result;
                            setTimeout(function (messages_t, refresh_t) {
                                self.cart_calculate_totals(messages_t, refresh_t);
                            }, 100, messages, refresh);
                        });
                    } else {
                        self.current_cart = cart_tmp;
                        setTimeout(function (messages_t, refresh_t) {
                            self.cart_calculate_totals(messages_t, refresh_t);
                        }, 100, messages, refresh);
                    }
                } else {
                    self.current_cart = cart;
                    setTimeout(function (messages_t, refresh_t) {
                        self.cart_calculate_totals(messages_t, refresh_t);
                    }, 100, messages, refresh);
                }
                return false;
            }
            self.filter_before_calculate_totals = null;
            //calculate_item_totals
            let shop_address = viwebpos_price.shop_address;
            cart['shop_address'] = shop_address;
            $.each(cart.cart_contents, function (cart_item_key, cart_item) {
                let item, product = cart_item.data;
                item = {
                    'key': cart_item_key,
                    'object': cart_item,
                    'tax_class': product.tax_class || '',
                    'taxable': 'taxable' === product.tax_status || cart_item.custom !== '',
                    'quantity': cart_item.quantity,
                    'product': product,
                    'price_includes_tax': viwebpos_price.product_price_includes_tax,
                    'subtotal': 0,
                    'subtotal_tax': 0,
                    'subtotal_taxes': {},
                    'total': 0,
                    'total_tax': 0,
                    'taxes': {},
                    'tax_rates': {},
                };
                item['price'] = viwebpos_price_obj.add_number_precision_deep(parseFloat(cart_item.quantity) * parseFloat(product.price));
                if (viwebpos.wc_tax_enabled) {
                    item['tax_rates'] = $(document).triggerHandler('viwebpos_get_matched_tax_rates', [shop_address.country, shop_address.state, shop_address.postcode, shop_address.city, item.tax_class]);
                }
                cart_items[cart_item_key] = item;
            });
            let merged_subtotal_taxes = {};
            $.each(cart_items, function (item_key, item) {
                if (item.price_includes_tax && viwebpos_price.pos_tax_base) {
                    item = viwebpos_price_obj.adjust_non_base_location_price(item);
                }
                item.subtotal = item.price;
                if (viwebpos.wc_tax_enabled && item.product.taxable) {
                    item.subtotal_taxes = item.price_includes_tax ? $(document).triggerHandler('viwebpos_calc_inclusive_tax', [item.subtotal, item.tax_rates]) : $(document).triggerHandler('viwebpos_calc_exclusive_tax', [item.subtotal, item.tax_rates]);
                    if (Object.keys(item.subtotal_taxes).length) {
                        item.subtotal_tax = $.map(Object.values(item.subtotal_taxes), function (v) {
                            return viwebpos_price.wc_tax_round_at_subtotal ? v : viwebpos_round(v);
                        }).reduce((a, b) => a + b);
                    }
                    if (item.price_includes_tax) {
                        item.subtotal = item.subtotal - item.subtotal_tax;
                    }
                    $.each(item.subtotal_taxes, function (rate_id, rate) {
                        if (!merged_subtotal_taxes[rate_id]) {
                            merged_subtotal_taxes[rate_id] = 0;
                        }
                        merged_subtotal_taxes[rate_id] += viwebpos_price_obj.remove_number_precision(viwebpos_round(rate));
                    });
                }
                cart.cart_contents[item_key]['line_tax_data'] = {subtotal: viwebpos_price_obj.remove_number_precision_deep(item.subtotal_taxes)};
                cart.cart_contents[item_key]['line_subtotal'] = viwebpos_price_obj.remove_number_precision(item.subtotal);
                cart.cart_contents[item_key]['line_subtotal_tax'] = viwebpos_price_obj.remove_number_precision(item.subtotal_tax);
            });
            if (Object.keys(cart_items).length) {
                cart_totals.items_subtotal = $.map(cart_items, function (item) {
                    return viwebpos_price.wc_tax_round_at_subtotal ? item.subtotal : viwebpos_round(item.subtotal);
                }).reduce((a, b) => viwebpos_price.wc_tax_round_at_subtotal ? (a + b) : (viwebpos_round(a, 0) + viwebpos_round(b, 0)));
            }
            if (Object.keys(merged_subtotal_taxes).length) {
                cart_totals.items_subtotal_tax = viwebpos_round($.map(Object.values(merged_subtotal_taxes), function (v) {
                    return v;
                }).reduce((a, b) => a + b));
            }
            cart.totals['subtotal'] = viwebpos_price_obj.remove_number_precision(cart_totals.items_subtotal);
            cart.totals['subtotal_tax'] = cart_totals.items_subtotal_tax;
            let coupons = cart.applied_coupons ? Object.values(cart.applied_coupons) : {}, discount_items = Object.values(cart_items), discounts = {};
            let custom_coupons = $(document).triggerHandler('viwebpos_cart_get_coupons', [coupons, cart, cart.totals['subtotal'] + cart.totals['subtotal_tax']]);
            if (custom_coupons) {
                coupons = custom_coupons;
            }
            if (!discount_items.length) {
                coupons = {};
            }
            if (coupons.length) {
                $.each(coupons, function (k, coupon) {
                    switch (coupon.type) {
                        case 'fixed_product':
                            coupon.sort = 1;
                            break;
                        case 'percent':
                            coupon.sort = 2;
                            break;
                        case 'fixed_cart':
                            coupon.sort = 3;
                            break;
                        default:
                            coupon.sort = 0;
                            break;
                    }
                    coupon.sort = $(document.body).triggerHandler('viwebpos_woocommerce_coupon_sort', [coupon.sort, coupon]) || coupon.sort;
                });
                coupons.sort(function (a, b) {
                    if (a.sort === b.sort) {
                        if (a.limit_usage_to_x_items === b.limit_usage_to_x_items) {
                            if (a.amount === b.amount) {
                                return a.id - b.id;
                            }
                            return a.amount - b.amount;
                        }
                        return a.limit_usage_to_x_items - b.limit_usage_to_x_items;
                    }
                    return a.sort - b.sort;
                });
            }
            if (discount_items.length) {
                discount_items.sort(function (a, b) {
                    return a.price * a.quantity - b.price * a.quantity;
                });
            }
            $.each(coupons, function (k, coupon) {
                if (!discounts[coupon.coupon_code]) {
                    discounts[coupon.coupon_code] = {};
                    $.each(discount_items, function (k1, item) {
                        if (item && item.key) {
                            discounts[coupon.coupon_code][item.key] = 0;
                        }
                    });
                }
            });
            if (Object.keys(discounts).length) {
                $.each(coupons, function (k, coupon) {
                    let items_to_apply_coupon = [];
                    $.each(discount_items, function (k, item) {
                        let item_to_apply_coupon = {...item};
                        if (item_to_apply_coupon.quantity <= 0 || 0 === viwebpos_price_obj.get_discounted_price_in_cents(item_to_apply_coupon, discounts)) {
                            return true;
                        }
                        if (!self.coupon_is_valid_for_product(item.product, item.object, coupon) && $.inArray(coupon.type, viwebpos.wc_get_cart_coupon_types) < 0) {
                            return true;
                        }
                        items_to_apply_coupon.push(item_to_apply_coupon);
                    });
                    switch (coupon.type) {
                        case 'percent':
                        case 'fixed_product':
                        case 'fixed_cart':
                            discounts = $(document.body).triggerHandler('viwebpos_apply_coupon', [discounts, coupon, items_to_apply_coupon, coupon.type]);
                            break;
                        default:
                            self.current_cart = cart;
                            discounts = $(document.body).triggerHandler('viwebpos_apply_coupon', [discounts, coupon, items_to_apply_coupon, 'custom']);
                    }
                });
            }
            let coupon_discount_tax_amounts = {}, coupon_discount_amounts = viwebpos_price_obj.cart_get_discounts_by_coupon(discounts, true);
            if (viwebpos.wc_tax_enabled) {
                $.each(discounts, function (coupon_code, coupon_discounts) {
                    coupon_discount_tax_amounts[coupon_code] = 0;
                    $.each(coupon_discounts, function (item_key, coupon_discount) {
                        let item = cart_items[item_key];
                        if (item && item.product.taxable) {
                            // Item subtotals were sent, so set 3rd param.
                            let item_tax = item.price_includes_tax ? $(document).triggerHandler('viwebpos_calc_inclusive_tax', [coupon_discount, item.tax_rates]) : $(document).triggerHandler('viwebpos_calc_exclusive_tax', [coupon_discount, item.tax_rates]);
                            // let item_tax = item.price_includes_tax ? self.calc_inclusive_tax(coupon_discount, item.tax_rates) : self.calc_exclusive_tax(coupon_discount, item.tax_rates);
                            item_tax = Object.values(item_tax).length ? Object.values(item_tax).reduce((a, b) => a + b) : 0;
                            // Sum total tax.
                            coupon_discount_tax_amounts[coupon_code] += item_tax;
                            if (item.price_includes_tax) {
                                coupon_discount_amounts[coupon_code] -= item_tax;
                            }
                        }
                    })
                });
            }
            let coupon_discount_totals = viwebpos_price_obj.get_discounts_by_item(discounts, true),
                coupon_discount_tax_totals = coupon_discount_tax_amounts;
            if (coupon_discount_tax_totals && Object.values(coupon_discount_tax_totals).length) {
                cart.totals['discounts_tax_total'] = Object.values(coupon_discount_tax_totals).reduce((a, b) => a + b);
            }
            if (coupon_discount_totals && Object.values(coupon_discount_totals).length) {
                cart.totals['discounts_total'] = viwebpos_price.product_price_includes_tax ? (Object.values(coupon_discount_totals).reduce((a, b) => a + b) - cart.totals['discounts_tax_total']) : Object.values(coupon_discount_totals).reduce((a, b) => a + b);
            }
            cart.totals['discount_total'] = viwebpos_price_obj.remove_number_precision_deep(cart.totals['discounts_total']);
            cart.totals['discount_tax'] = viwebpos_price_obj.remove_number_precision_deep(cart.totals['discounts_tax_total']);
            cart.coupon_discount_totals = viwebpos_price_obj.remove_number_precision_deep(coupon_discount_amounts);
            cart.coupon_discount_tax_totals = viwebpos_price_obj.remove_number_precision_deep(coupon_discount_tax_amounts);
            let merged_total_taxes = {};
            $.each(cart_items, function (item_key, item) {
                item.total = viwebpos_price_obj.get_discounted_price_in_cents(item, discounts);
                item.total_tax = 0;
                item.total = $(document.body).triggerHandler('viwebpos_woocommerce_get_discounted_price') || item.total;
                if (viwebpos.wc_tax_enabled && item.product.taxable) {
                    item.taxes = item.price_includes_tax ? $(document).triggerHandler('viwebpos_calc_inclusive_tax', [item.total, item.tax_rates]) : $(document).triggerHandler('viwebpos_calc_exclusive_tax', [item.total, item.tax_rates]);
                    if (Object.keys(item.taxes).length) {
                        item.total_tax = $.map(Object.values(item.taxes), function (v) {
                            return viwebpos_price.wc_tax_round_at_subtotal ? v : viwebpos_round(v);
                        }).reduce((a, b) => a + b);
                    }
                    if (item.price_includes_tax) {
                        item.total = item.total - item.total_tax;
                    }
                    $.each(item.taxes, function (rate_id, rate) {
                        if (!merged_total_taxes[rate_id]) {
                            merged_total_taxes[rate_id] = 0;
                        }
                        merged_total_taxes[rate_id] += viwebpos_price_obj.remove_number_precision_deep(viwebpos_round(rate, 0));
                    });
                }
                cart.cart_contents[item_key]['line_tax_data']['total'] = viwebpos_price_obj.remove_number_precision_deep(item.taxes);
                cart.cart_contents[item_key]['line_total'] = viwebpos_price_obj.remove_number_precision(item.total);
                cart.cart_contents[item_key]['line_tax'] = viwebpos_price_obj.remove_number_precision(item.total_tax);
            });
            if (Object.keys(cart_items).length) {
                cart_totals.items_total = $.map(cart_items, function (item) {
                    return viwebpos_price.wc_tax_round_at_subtotal ? item.total : viwebpos_round(item.total);
                }).reduce((a, b) => viwebpos_price.wc_tax_round_at_subtotal ? (a + b) : (viwebpos_round(a, 0) + viwebpos_round(b, 0)));
                cart_totals.items_total_tax = $.map(cart_items, function (item) {
                    return viwebpos_price.wc_tax_round_at_subtotal ? item.total_tax : viwebpos_round(item.total_tax);
                }).reduce((a, b) => a + b);
            }
            cart.totals['cart_contents_total'] = viwebpos_price_obj.remove_number_precision(cart_totals.items_total);
            cart.totals['cart_contents_taxes'] = merged_total_taxes;
            if (Object.keys(cart.totals['cart_contents_taxes']).length) {
                cart.totals['cart_contents_tax'] = viwebpos_round($.map(Object.values(cart.totals['cart_contents_taxes']), function (v) {
                    return viwebpos_price.wc_tax_round_at_subtotal ? v : viwebpos_round(v);
                }).reduce((a, b) => a + b));
            }
            //calculate_shipping_totals
            let cart_shipping = {};
            //calculate_fee_totals
            let cart_fees = {}, fee_running_total = 0,
                current_fees = $(document.body).triggerHandler('viwebpos_cart_get_fees', [{}, cart, viwebpos_price_obj.remove_number_precision(cart_totals.items_total) + cart.totals['cart_contents_tax']]);
            $.each(current_fees, function (fee_key, fee_object) {
                let fee = {
                    object: null,
                    tax_class: '',
                    taxable: false,
                    total_tax: 0,
                    taxes: {},
                };
                fee.object = fee_object;
                fee.tax_class = fee.object.tax_class;
                fee.taxable = fee.object.taxable;
                fee.total = viwebpos_price_obj.add_number_precision_deep(fee.object.amount);
                // Negative fees should not make the order total go negative.
                if (fee.total < 0) {
                    let max_discount = viwebpos_round(cart_totals.items_total + fee_running_total) * (-1);
                    if (fee.total < max_discount) {
                        fee.total = max_discount;
                    }
                }
                fee_running_total += fee.total;
                if (viwebpos.wc_tax_enabled) {
                    //let shop_address = viwebpos.shop_address;
                    if (fee.total < 0 && fee.object.id !== 'pos_discount') {
                        // Negative fees should have the taxes split between all items so it works as a true discount.
                        let tax_class_costs = viwebpos_price_obj.cart_get_tax_class_costs(cart_items, cart_shipping, current_fees);
                        let total_cost = Object.keys(tax_class_costs).length ? Object.values(tax_class_costs).reduce((a, b) => a + b) : 0;
                        if (total_cost) {
                            $.each(tax_class_costs, function (tax_class, tax_class_cost) {
                                if ('non-taxable' === tax_class) {
                                    return true;
                                }
                                let proportion = tax_class_cost / total_cost;
                                let cart_discount_proportion = fee.total * proportion;
                                let taxes_temp = $(document).triggerHandler('viwebpos_calc_exclusive_tax', [cart_discount_proportion, $(document).triggerHandler('viwebpos_get_matched_tax_rates', [shop_address.country, shop_address.state, shop_address.postcode, shop_address.city, tax_class])]);
                                // let taxes_temp = self.calc_exclusive_tax(cart_discount_proportion, self.get_matched_tax_rates(shop_address.country, shop_address.state, shop_address.postcode, shop_address.city, tax_class));
                                let arrays_temp = [fee.taxes, taxes_temp];
                                fee.taxes = viwebpos_price_obj.array_merge_recursive_numeric(arrays_temp);
                            })
                        }
                    } else if (fee.object.taxable) {
                        fee.taxes = $(document).triggerHandler('viwebpos_calc_exclusive_tax', [fee.total, $(document).triggerHandler('viwebpos_get_matched_tax_rates', [shop_address.country, shop_address.state, shop_address.postcode, shop_address.city, fee.tax_class])]);
                    }
                }
                fee.taxes = $(this).triggerHandler('viwebpos_woocommerce_cart_totals_get_fees_from_cart_taxes', fee.taxes, fee, self) || fee.taxes;
                if (Object.keys(fee.taxes).length) {
                    fee.total_tax = Object.values(fee.taxes).reduce((a, b) => parseFloat(a) + parseFloat(b));
                }
                fee.object.total = viwebpos_price_obj.remove_number_precision_deep(fee.total);
                fee.object.tax_data = viwebpos_price_obj.remove_number_precision_deep(fee.taxes);
                fee.object.tax = viwebpos_price_obj.remove_number_precision_deep(fee.total_tax);
                cart_fees[fee_key] = fee;
            });
            if (Object.keys(cart_fees).length) {
                cart_totals.fees_total = $.map(Object.values(cart_fees), function (v) {
                    return viwebpos_price.wc_tax_round_at_subtotal ? v.total : viwebpos_round(v.total);
                }).reduce((a, b) => a + b);
                cart_totals.fees_total_tax = $.map(Object.values(cart_fees), function (v) {
                    return viwebpos_price.wc_tax_round_at_subtotal ? v.total_tax : viwebpos_round(v.total_tax);
                }).reduce((a, b) => a + b);
            }
            cart['fees'] = {};
            $.each(cart_fees, function (k, v) {
                cart.fees[k] = v.object;
            });
            cart.totals['fee_total'] = viwebpos_price_obj.remove_number_precision_deep(cart_totals.fees_total);
            cart.totals['fee_tax'] = viwebpos_price_obj.remove_number_precision_deep(cart_totals.fees_total_tax);
            //calculate_totals
            cart_totals.total = viwebpos_round(viwebpos_price_obj.remove_number_precision(cart_totals.items_total) + cart.totals['fee_total'] +
                cart.totals['cart_contents_tax'] + cart.totals['fee_tax']);
            cart.totals['total_tax'] = cart.totals['cart_contents_tax'] + cart.totals['fee_tax'];
            cart.totals['total'] = Math.max(0, $(document.body).triggerHandler('viwebpos_calculated_total', [cart_totals.total, cart]) || cart_totals.total);
            cart = $(document.body).triggerHandler('viwebpos_after_calculate_totals', [cart]) || cart;
            self.current_cart = cart;
            if (typeof self.current_cart['key'] === "undefined" || !self.current_cart['key']) {
                self.current_cart['key'] = viwebpos.cashier_id + '_' + Date.now();
            }
            self.cart_refresh_payments();
            if (messages.length) {
                $(document.body).trigger('villatheme_show_messages', [messages]);
            }
            if (refresh) {
                $(document.body).trigger('viwebpos_refresh_cart');
            }
        },
        cart_data_validate: function (cart, reset_total, messages = []) {
            let self = viwebpos_data;
            if (!self.current_cart && cart) {
                self.current_cart = cart;
            }
            if (!Object.keys(self.current_cart.cart_contents).length) {
                if (reset_total && self.current_cart.id) {
                    self.current_cart['coupon_discount_tax_totals'] = {};
                    self.current_cart['coupon_discount_totals'] = {};
                    self.current_cart['totals'] = self.set_cart_empty({})['default_totals'];
                    if (!self.current_cart.payments.is_paid) {
                        self.current_cart.payments = self.set_cart_empty({}).payments;
                    }
                    $(document.body).trigger('viwebpos_refresh_cart');
                } else {
                    $(document.body).trigger('viwebpos-bill-of-sale-refresh-bill-tabs');
                    $(document.body).trigger('viwebpos-bill-of-sale-get-html');
                }
                if (typeof self.syncing_pos_data === "undefined") {
                    self.syncing_pos_data = false;
                    $(document.body).trigger('viwebpos-sync-online-data', ['pos']);
                }
                return false;
            }
            if (!self.viwebposDB) {
                self.viwebposDB = indexedDB.open('viwebposDB');
            }
            let database = self.viwebposDB.result;
            let cart_contents = {}, errors = [], count = Object.keys(cart.cart_contents).length;
            let customer = '', applied_coupons = {}, count_applied_coupons = 0, count1 = count;
            if (cart.applied_coupons && Object.keys(cart.applied_coupons).length) {
                count_applied_coupons = Object.keys(cart.applied_coupons).length;
            }
            let check_cart = async function () {
                //check_cart_customer
                if (self.data_prefix['customers_current_page'] && self.data_prefix['customers_total_page'] && cart.customer.id) {
                    let search_ajax = [];
                    await new Promise(function (resolve) {
                        let customer_id = cart.customer.id;
                        if (!customer_id) {
                            resolve(search_ajax);
                            return false;
                        }
                        let customers = database.transaction('customers', 'readonly').objectStore('customers');
                        if (!customers) {
                            resolve(search_ajax);
                            return false;
                        }
                        customers.get(customer_id).onsuccess = function (e) {
                            if (!e.target.result) {
                                search_ajax.push(customer_id);
                            }
                            resolve(search_ajax);
                        };
                    });
                    if (search_ajax.length) {
                        await new Promise(function (resolve) {
                            self.run_request({
                                type: 'post',
                                url: viwebpos.viwebpos_ajax_url.toString().replace('%%endpoint%%', 'viwebpos_customer_search_data'),
                                data: $.param({
                                    user_ids: search_ajax,
                                    viwebpos_nonce: viwebpos.nonce
                                }),
                                success: function (response) {
                                    viwebpos_data.puts(database.transaction('customers', 'readwrite').objectStore('customers'), response.data || {});
                                    resolve(errors);
                                },
                                error: function (err) {
                                    resolve(errors);
                                }
                            });
                        });
                    }
                }
                await new Promise(function (resolve) {
                    let customer_id = cart.customer.id;
                    if (!customer_id) {
                        resolve(customer, errors);
                        return false;
                    }
                    let customers = database.transaction('customers', 'readonly').objectStore('customers');
                    if (!customers) {
                        resolve(customer, errors);
                        return false;
                    }
                    customers.get(customer_id).onsuccess = function (e) {
                        if (e.target.result) {
                            customer = e.target.result;
                        } else {
                            errors.push(viwebpos.cart_customer_invalid);
                        }
                        resolve(customer, errors);
                    };
                });
                //check_cart_items
                if (self.data_prefix['products_current_page'] && self.data_prefix['products_total_page']) {
                    let search_ajax = [];
                    await new Promise(function (resolve) {
                        let products = database.transaction('products', 'readonly').objectStore('products'), temp_count = count;
                        $.each(cart.cart_contents, function (k, v) {
                            let product_id = v.variation_id ? v.variation_id : v.product_id;
                            if (v.quantity <= 0 || product_id === 'custom' || v.order_line_item) {
                                temp_count--;
                                if (!temp_count) {
                                    resolve(search_ajax);
                                }
                                return true;
                            }
                            products.get(product_id).onsuccess = function (event) {
                                let product = event.target.result;
                                temp_count--;
                                if (!product) {
                                    search_ajax.push(product_id);
                                }
                                if (!temp_count) {
                                    resolve(search_ajax);
                                }
                            };
                        });
                    });
                    if (search_ajax.length) {
                        await new Promise(function (resolve) {
                            self.run_request({
                                type: 'post',
                                url: viwebpos.viwebpos_ajax_url.toString().replace('%%endpoint%%', 'viwebpos_product_search_data'),
                                data: $.param({
                                    product_ids: search_ajax,
                                    viwebpos_nonce: viwebpos.nonce
                                }),
                                success: function (response) {
                                    viwebpos_data.puts(database.transaction('products', 'readwrite').objectStore('products'), response.data || {});
                                    resolve(errors);
                                },
                                error: function (err) {
                                    resolve(errors);
                                }
                            });
                        });
                    }
                }
                await new Promise(function (resolve) {
                    let products = database.transaction('products', 'readonly').objectStore('products');
                    $.each(cart.cart_contents, function (k, v) {
                        let product_id = v.variation_id ? v.variation_id : v.product_id;
                        if (v.quantity <= 0) {
                            count--;
                            if (!count) {
                                resolve(cart_contents, errors);
                            }
                            return true;
                        }
                        if (product_id === 'custom' || v.order_line_item) {
                            cart_contents[k] = v;
                            count--;
                            if (!count) {
                                resolve(cart_contents, errors);
                            }
                            return true;
                        }
                        products.get(product_id).onsuccess = function (event) {
                            let product = event.target.result;
                            count--;
                            if (!product) {
                                errors.push(viwebpos.cart_item_invalid);
                                if (!count) {
                                    resolve(cart_contents, errors);
                                }
                                return false;
                            }
                            if (!product.is_purchasable) {
                                errors.push(viwebpos.cart_item_removed_message.replace('{product_name}', product.name));
                            } else if (!v.data_hash || (v.data_hash !== wiwebpos_atc_obj.get_cart_item_data_hash(product))) {
                                errors.push(viwebpos.cart_item_removed_message1.replace('{product_name}', product.name));
                            } else {
                                v.data = product;
                                cart_contents[k] = v;
                                if (!product.is_in_stock) {
                                    errors.push(viwebpos.cart_item_not_enough_stock_message.replace('{product_name}', product.name).replace('{product_quantity}', 0));
                                } else if (product.stock !== null && (parseFloat(product.stock) < parseFloat(v.quantity))) {
                                    errors.push(viwebpos.cart_item_not_enough_stock_message.replace('{product_name}', product.name).replace('{product_quantity}', product.stock ?? 0));
                                }
                            }
                            if (!count) {
                                resolve(cart_contents, errors);
                            }
                        }
                    });
                });
                if (count_applied_coupons && Object.keys(cart_contents).length < count1) {
                    self.current_cart['customer'] = customer;
                    self.current_cart['cart_contents'] = cart_contents;
                    await self.cart_calculate_totals([], false);
                }
                //check_cart_coupons
                if (self.data_prefix['coupons_current_page'] && self.data_prefix['coupons_total_page'] && count_applied_coupons) {
                    let search_ajax = [];
                    await new Promise(function (resolve) {
                        if (!count_applied_coupons) {
                            resolve(search_ajax);
                            return false;
                        }
                        let coupons = database.transaction('coupons', 'readonly').objectStore('coupons'), temp_count = count_applied_coupons;
                        if (!coupons) {
                            resolve(search_ajax);
                            return false;
                        }
                        $.each(cart.applied_coupons, function (k, v) {
                            coupons.get(k).onsuccess = function (event) {
                                temp_count--;
                                if (!event.target.result) {
                                    search_ajax.push(v.id);
                                }
                                if (!temp_count) {
                                    resolve(search_ajax);
                                }
                            }
                        });
                    });
                    if (search_ajax.length) {
                        await new Promise(function (resolve) {
                            self.run_request({
                                type: 'post',
                                url: viwebpos.viwebpos_ajax_url.toString().replace('%%endpoint%%', 'viwebpos_coupon_search_data'),
                                data: $.param({
                                    ids: search_ajax,
                                    viwebpos_nonce: viwebpos.nonce
                                }),
                                success: function (response) {
                                    viwebpos_data.puts(database.transaction('coupons', 'readwrite').objectStore('coupons'), response.data || {});
                                    resolve(errors);
                                },
                                error: function (err) {
                                    resolve(errors);
                                }
                            });
                        });
                    }
                }
                let check_usage_limit_per_user = {}, current_user_id = '';
                await new Promise(function (resolve) {
                    if (!count_applied_coupons) {
                        resolve(applied_coupons, errors);
                        return false;
                    }
                    let coupons = database.transaction('coupons', 'readonly').objectStore('coupons');
                    if (!coupons) {
                        resolve(applied_coupons, errors);
                        return false;
                    }
                    let check_emails = [];
                    if (customer) {
                        check_emails.push(customer.email);
                        if (customer.billing_address.email && customer.billing_address.email !== customer.email) {
                            check_emails.push(customer.billing_address.email);
                        }
                        current_user_id = customer.id;
                    }
                    $.each(cart.applied_coupons, function (k, v) {
                        coupons.get(k).onsuccess = function (event) {
                            let coupon = event.target.result;
                            count_applied_coupons--;
                            if (!coupon) {
                                errors.push(viwebpos_text.error_order_invalid_coupon1.replace('%1$s', v.coupon_code));
                                if (!count_applied_coupons) {
                                    resolve(applied_coupons, errors);
                                }
                                return false;
                            }
                            if (!self.is_coupon_emails_allowed(check_emails, coupon.email || [])) {
                                errors.push(viwebpos_text.error_order_invalid_coupon2.replace('%1$s', coupon.coupon_code));
                                if (!count_applied_coupons) {
                                    resolve(applied_coupons, errors);
                                }
                                return false;
                            }
                            if (!self.is_coupon_valid(coupon, coupon.coupon_code, false)) {
                                errors.push(viwebpos_text.error_order_invalid_coupon1.replace('%1$s', coupon.coupon_code));
                                if (!count_applied_coupons) {
                                    resolve(applied_coupons, errors);
                                }
                                return false;
                            }
                            let usage_limit_per_user = parseInt(coupon.usage_limit_per_user);
                            if (current_user_id &&
                                ($(document.body).triggerHandler('viwebpos_woocommerce_coupon_validate_user_usage_limit', [usage_limit_per_user]) || usage_limit_per_user) > 0) {
                                check_usage_limit_per_user[coupon.coupon_code] = coupon;
                            } else {
                                applied_coupons[coupon.coupon_code] = coupon;
                            }
                            if (!count_applied_coupons) {
                                resolve(applied_coupons, errors);
                            }
                        }
                    });
                });
                if (Object.keys(check_usage_limit_per_user).length && current_user_id) {
                    if ((self.data_prefix['orders_current_page'] && self.data_prefix['orders_total_page'])) {
                        await new Promise(function (resolve) {
                            self.run_request({
                                type: 'post',
                                url: viwebpos.viwebpos_ajax_url.toString().replace('%%endpoint%%', 'viwebpos_coupon_check_usage_limit_per_user'),
                                data: $.param({
                                    customer_id: current_user_id,
                                    coupons: Object.keys(check_usage_limit_per_user),
                                    viwebpos_nonce: viwebpos.nonce
                                }),
                                success: function (response) {
                                    if (response.message && response.message.length) {
                                        errors.concat(response.message);
                                    }
                                    if (response.usage) {
                                        // let temp = response.usage.length;
                                        for (let i in response.usage) {
                                            let coupon_code = response.usage[i];
                                            if (check_usage_limit_per_user[coupon_code]) {
                                                applied_coupons[coupon_code] = check_usage_limit_per_user[coupon_code];
                                            }
                                        }
                                    }
                                    resolve(applied_coupons, errors);
                                },
                                error: function (err) {
                                    resolve(applied_coupons, errors);
                                }
                            });
                        });
                    } else {
                        let orders = database.transaction('orders', 'readonly').objectStore('orders');
                        await new Promise(function (resolve) {
                            $.each(check_usage_limit_per_user, function (coupon_code, coupon) {
                                let usage_limit_per_user = parseInt(coupon.usage_limit_per_user);
                                let usage_count = 0;
                                orders.index('customer_id').openCursor(parseInt(current_user_id)).onsuccess = function (e) {
                                    let cursor = e.target.result;
                                    if (!cursor) {
                                        if (usage_count >= usage_limit_per_user) {
                                            errors.push(viwebpos_text.error_order_invalid_coupon1.replace('%1$s', coupon.coupon_code));
                                        } else {
                                            applied_coupons[coupon_code] = coupon;
                                        }
                                        resolve(applied_coupons, errors);
                                        return false;
                                    }
                                    if ((cart.order_data && cart.order_data.id === cursor.value.id) || cursor.value.status === 'cancelled' || cursor.value.pos_update_to_order) {
                                        cursor.continue();
                                        return true;
                                    }
                                    if (cursor.value.coupon_lines && cursor.value.coupon_lines[coupon_code]) {
                                        usage_count++;
                                    }
                                    cursor.continue();
                                };
                            });
                        });
                    }
                }
            };
            check_cart().then(function () {
                self.current_cart['customer'] = customer;
                self.current_cart['cart_contents'] = cart_contents;
                self.current_cart['applied_coupons'] = applied_coupons;
                if (errors && errors.length) {
                    $.each(errors, function (k, v) {
                        messages.push({
                            message: v,
                            status: ['error', 'cart-notice']
                        })
                    });
                    self.cart_calculate_totals(messages);
                } else if (reset_total) {
                    self.cart_calculate_totals(messages);
                } else {
                    $(document.body).trigger('viwebpos-bill-of-sale-get-html', [self.current_cart]);
                }
            });
        },
        update_cart_variation: function (cart_item_key, variation) {
            if (!cart_item_key || !variation || !Object.keys(variation).length) {
                $(document.body).trigger('villatheme_show_message', [viwebpos.error_title, ['error', 'update-variation'], viwebpos_text.error_change_variation, true, 4500]);
                return false
            }
            let self = viwebpos_data;
            if (!self.current_cart.cart_contents[cart_item_key]) {
                $(document.body).trigger('villatheme_show_message', [viwebpos.error_title, ['error', 'update-variation'], viwebpos_text.error_change_variation, true, 4500]);
                return false;
            }
            let item = self.current_cart.cart_contents[cart_item_key];
            if (!item.variation_id && !item.product_id) {
                $(document.body).trigger('villatheme_show_message', [viwebpos.error_title, ['error', 'update-variation'], viwebpos_text.error_change_variation, true, 4500]);
            }
            if (!self.viwebposDB) {
                self.viwebposDB = indexedDB.open('viwebposDB');
            }
            let database = self.viwebposDB.result;
            let products = database.transaction('products', 'readonly').objectStore('products'), product, variation1 = {};
            $.each(variation, function (k, v) {
                variation1[k.replace('attribute_', '')] = v;
            });
            products.index('parent_id').openCursor(item.product_id).onsuccess = function (event) {
                let cursor = event.target.result;
                if (!cursor) {
                    if (!product) {
                        $(document.body).trigger('villatheme_show_message', [viwebpos.error_title, ['error', 'update-variation'], viwebpos.no_matching_variations_text, true, 4500]);
                    }
                    return false;
                }
                if (!wiwebpos_atc_obj.find_matching_variations(variation1, cursor.value)) {
                    cursor.continue();
                } else {
                    product = cursor.value;
                    let item_serial = self.current_cart.cart_contents[cart_item_key].item_serial;
                    delete self.current_cart.cart_contents[cart_item_key];
                    $(document.body).trigger('viwebpos_add_to_cart', [product, variation, item.quantity, item_serial]);
                    return false;
                }
            }
        },
        update_cart_quantity: function (cart_item_key, quantity = 1, refresh_totals = true) {
            if (!cart_item_key) {
                return false
            }
            let self = viwebpos_data;
            if (!self.current_cart.cart_contents[cart_item_key]) {
                return false;
            }
            quantity = parseFloat(quantity);
            if (quantity > 0) {
                let product = self.current_cart.cart_contents[cart_item_key].data;
                if (product.is_sold_individually) {
                    quantity = 1;
                }
                if (product.stock !== null) {
                    let max_qty = parseFloat(product.stock);
                    if (self.current_cart.cart_contents[cart_item_key].order_line_item && self.current_cart.cart_contents[cart_item_key].order_line_item.quantity) {
                        max_qty += parseFloat(self.current_cart.cart_contents[cart_item_key].order_line_item.quantity);
                    }
                    if (max_qty < quantity) {
                        let message = viwebpos.not_enough_stock_message.replace('{product_name}', product.name).replace('{product_quantity}', max_qty);
                        $(document.body).trigger('villatheme_show_message', [viwebpos.error_title, ['error', 'product-adding'], message, true, 4500]);
                        $(document.body).trigger('viwebpos-bill-of-sale-get-html', [self.current_cart]);
                        return false;
                    }
                }
                self.current_cart.cart_contents[cart_item_key].quantity = quantity;
            } else {
                delete self.current_cart.cart_contents[cart_item_key];
                $(document.body).trigger('viwebpos_cart_data_validate', [self.current_cart, true]);
                return false;
            }
            if (refresh_totals) {
                self.cart_calculate_totals();
            }
        },
        set_cart_customer: function (customer_data = null) {
            let customer = customer_data;
            if (typeof customer_data !== 'object' || !customer_data || !customer_data.id || !customer_data.username) {
                customer = '';
            }
            viwebpos_data.current_cart['customer'] = customer;
            if (typeof viwebpos_redis !== "undefined" ||
                (viwebpos_data.current_cart.applied_coupons && Object.keys(viwebpos_data.current_cart.applied_coupons).length)) {
                $(document.body).trigger('viwebpos_cart_data_validate', [viwebpos_data.current_cart, true]);
            } else {
                $(document.body).trigger('viwebpos_refresh_cart', ['checkout']);
            }
        },
        remove_all_cart_items: function () {
            viwebpos_data.current_cart = viwebpos_data.set_cart_empty(viwebpos_data.current_cart);
            $(document.body).trigger('viwebpos_refresh_cart');
        },
        add_to_cart: function (product, variation, quantity = 1, item_serial = '') {
            if (!product || quantity <= 0) {
                return false
            }
            let product_id = product.id, variation_id = 0, cart_item_data = {}, messages = [];
            switch (product.type) {
                case 'variation':
                    variation_id = product_id;
                    product_id = product.parent_id;
                    break;
                case 'custom':
                    cart_item_data['custom'] = {
                        name: product.name,
                        price: product.price,
                        taxable: product.taxable,
                    };
                    break;
            }
            cart_item_data = $(document.body).triggerHandler('viwebpos_add_cart_item_data', [product, cart_item_data, product_id, variation_id, quantity]) || cart_item_data;
            let cart_id = wiwebpos_atc_obj.generate_cart_id(product_id, variation_id, variation, cart_item_data),
                cart_contents = viwebpos_data.current_cart['cart_contents'];
            if (typeof cart_contents !== "object" || !Object.keys(cart_contents).length) {
                cart_contents = {};
            }
            let cart_item_key = cart_id && cart_contents[cart_id] ? cart_id : '',
                cart_item_counts = Object.keys(cart_contents).length;
            if (!cart_item_key && cart_item_counts === 5) {
                $(document.body).trigger('villatheme_show_message', [viwebpos.error_title, ['error', 'product-adding'], viwebpos.maximum_atc_message, true, 4500]);
                return false;
            }
            if (product.is_sold_individually) {
                quantity = 1;
                let found_in_cart = cart_item_key && parseFloat(viwebpos_data.current_cart[cart_item_key]['quantity']) > 0;
                if (found_in_cart) {
                    $(document.body).trigger('villatheme_show_message', [viwebpos.error_title, ['error', 'product-adding'], viwebpos.cannot_add_another_message.replace('{product_name}', product.name), true, 4500]);
                    return false;
                }
            }
            if (!product.is_in_stock) {
                $(document.body).trigger('villatheme_show_message', [viwebpos.error_title, ['error', 'product-adding'], viwebpos.out_of_stock_message.replace('{product_name}', product.name), true, 4500]);
                if (item_serial) {
                    $(document.body).trigger('viwebpos-bill-of-sale-get-html', [viwebpos_data.current_cart]);
                }
                return false;
            }
            let qty_in_cart = cart_item_key ? (cart_contents[cart_item_key]['quantity'] || 0) : 0;
            quantity += qty_in_cart;
            if (product.stock !== null) {
                if (parseFloat(product.stock) < quantity) {
                    let message = viwebpos.not_enough_stock_message.replace('{product_name}', product.name);
                    message.replace('{product_quantity}', product.stock);
                    $(document.body).trigger('villatheme_show_message', [viwebpos.error_title, ['error', 'product-adding'], message, true, 4500]);
                    return false;
                }
            }
            if (cart_item_key) {
                cart_contents[cart_item_key]['quantity'] = quantity;
                viwebpos_data.current_cart['cart_contents'] = cart_contents;
                messages.push({
                    message: viwebpos.add_to_cart_message.replace('{product_name}', product.name),
                    status: ['success', 'cart-notice']
                });
            } else {
                cart_item_key = cart_id;
                let temp = cart_item_data;
                temp['item_serial'] = item_serial ? item_serial : new Date().getTime();
                temp['key'] = cart_item_key;
                temp['product_id'] = product_id ? product_id : 'custom';
                temp['variation_id'] = variation_id;
                temp['variation'] = variation;
                temp['quantity'] = quantity;
                temp['data'] = product;
                temp['data_hash'] = product.type !== 'custom' ? wiwebpos_atc_obj.get_cart_item_data_hash(product) : '';
                cart_contents[cart_item_key] = $(document.body).triggerHandler('viwebpos_add_cart_item', [temp]) || temp;
                viwebpos_data.current_cart['cart_contents'] = cart_contents;
                messages.push({
                    message: viwebpos.add_to_cart_message.replace('{product_name}', product.name),
                    status: ['success', 'cart-notice']
                });
                $(document.body).trigger('viwebpos_added_to_cart', [cart_item_key, product_id, quantity, variation_id, variation, cart_item_data]);
                if (viwebpos_data.current_cart['applied_coupons'] && Object.keys(viwebpos_data.current_cart['applied_coupons']).length) {
                    $(document.body).trigger('viwebpos_cart_data_validate', [viwebpos_data.current_cart, true, messages]);
                    return false;
                }
            }
            viwebpos_data.cart_calculate_totals(messages);
        },
        add_order_note: function (note = '', cart_item_key = null) {
            if (cart_item_key === null) {
                viwebpos_data.current_cart['order_note'] = note.toString();
            } else if (viwebpos_data.current_cart.cart_contents[cart_item_key]) {
                viwebpos_data.current_cart.cart_contents[cart_item_key]['item_note'] = note;
            }
            $(document.body).trigger('viwebpos_refresh_cart', [false]);
        },
        find_matching_variations: function (variation, product) {
            if (!product.id || product.type !== 'variation' || !product.attributes || !Object.keys(product.attributes).length || !variation) {
                return false;
            }
            let aProps = Object.getOwnPropertyNames(product.attributes),
                bProps = Object.getOwnPropertyNames(variation);
            if (aProps.length !== bProps.length) {
                return false;
            }
            for (let i = 0; i < aProps.length; i++) {
                let attr_name = aProps[i];
                let val1 = variation[attr_name];
                let val2 = product.attributes[attr_name];
                if (val1 !== undefined && val2 !== undefined && val1.length !== 0 && val2.length !== 0 && val1 !== val2) {
                    return false;
                }
            }
            return true;
        },
        set_cart_empty: function (cart) {
            if (!cart) {
                cart = {};
            }
            if (cart.order_data) {
                delete cart.order_data;
            }
            if (typeof cart['key'] === "undefined" && !cart['key']) {
                cart['key'] = viwebpos.cashier_id + '_' + Date.now();
            }
            cart['cart_contents'] = {};
            cart['fees'] = {};
            cart['pos_shipping'] = {};
            cart['pos_shipping_address'] = '';
            cart['pos_discount'] = {};
            cart['applied_coupons'] = {};
            cart['coupon_discount_totals'] = {};
            cart['coupon_discount_tax_totals'] = {};
            cart['order_note'] = '';
            cart['customer'] = '';
            cart['payments'] = {
                is_paid: false,
                is_paid_title: '',
                paid: {
                    cash: 0
                },
                total_paid: 0,
                change: 0,
            };
            cart['default_totals'] = {
                subtotal: 0,
                subtotal_tax: 0,
                discount_total: 0,
                discount_tax: 0,
                cart_contents_total: 0,
                cart_contents_tax: 0,
                cart_contents_taxes: {},
                shipping_total: 0,
                shipping_tax: 0,
                shipping_taxes: {},
                fee_total: 0,
                fee_tax: 0,
                fee_taxes: {},
                total: 0,
                total_tax: 0,
            };
            cart['totals'] = cart['default_totals'];
            return cart;
        },
        get_cart_item_data_hash: function (product) {
            let hash = {
                type: product.type || 'custom',
                attributes: product.attributes || ''
            };
            hash = $(document.body).triggerHandler('viwebpos_cart_item_data_to_validate', [hash, product]) || hash;
            return wiwebpos_atc_obj.create_hash($.param(hash));
        },
        create_hash: function (str) {
            let hash = 0;
            if (str.length === 0) {
                return hash;
            }
            str = '_' + str
            for (let i = 0; i < str.length; i++) {
                hash = ((hash << 5) - hash) + str.charCodeAt(i);
                hash = hash & hash;
            }
            return hash.toString();
        },
        generate_cart_id: function (product_id, variation_id = 0, variation = {}, cart_item_data = {}) {
            let id_parts = [product_id];
            if (variation_id && 0 !== variation_id) {
                id_parts.push(variation_id);
            }
            let variation_key = '';
            $.each(variation, function (k, v) {
                variation_key += k.trim() + v.trim();
            });
            if (variation_key) {
                id_parts.push(variation_key);
            }
            let cart_item_data_key = '';
            $.each(cart_item_data, function (k, v) {
                if (typeof v === "object") {
                    v = $.param(v);
                }
                cart_item_data_key += k.trim() + v.toString().trim();
            });
            if (cart_item_data_key) {
                id_parts.push(cart_item_data_key);
            }
            return wiwebpos_atc_obj.create_hash(id_parts.join('_'));
        },
    }
});