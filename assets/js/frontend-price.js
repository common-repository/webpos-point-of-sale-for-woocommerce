(function ($) {
    'use strict';
    $(document).ready(function () {
        $(document).on('viwebpos_get_price_html', function (e, value, decimals = null, decimal_separator = null, thousand_separator = null, format = null, currency_symbol = null) {
            value = parseFloat(value || 0);
            decimals = decimals !== null ? decimals : ($(document).triggerHandler('viwebpos_get_price_html_decimals', [viwebpos_price.wc_get_price_decimals, currency_symbol]) ?? viwebpos_price.wc_get_price_decimals);
            decimal_separator = decimal_separator !== null ? decimal_separator : viwebpos_price.wc_price_decimal_separator;
            thousand_separator = thousand_separator !== null ? thousand_separator : viwebpos_price.wc_price_thousand_separator;
            let value1 = viwebpos_format_number(Math.abs(value), decimals, decimal_separator, thousand_separator);
            format = format || $(document).triggerHandler('viwebpos_get_price_html_format', [viwebpos_price.wc_price_format, currency_symbol]) || viwebpos_price.wc_price_format;
            currency_symbol = currency_symbol || viwebpos_price.wc_currency_symbol;
            let result = format.replace('%2$s', value1).replace('%1$s', currency_symbol);
            return value < 0 ? '-' + result : result;
        });
        $(document).on('viwebpos_calc_inclusive_tax', function (e, price, rates) {
            let taxes = {}, compound_rates = {}, regular_rates = {};
            $.each(rates, function (k, v) {
                taxes[k] = 0;
                if (v.compound === 'yes') {
                    compound_rates[k] = v.rate;
                } else {
                    regular_rates[k] = v.rate;
                }
            });
            let non_compound_price = price;
            if (Object.keys(compound_rates).length) {
                let compound_rates_key = Object.keys(compound_rates).reverse();
                $.each(compound_rates_key, function (k, v) {
                    let tax_amount = non_compound_price - (non_compound_price / (1 + (compound_rates[v] / 100)));
                    taxes[v] += tax_amount;
                    non_compound_price -= tax_amount;
                });
            }
            if (Object.keys(regular_rates).length) {
                let regular_tax_rate = 1 + (Object.values(regular_rates).reduce((a, b) => a + b) / 100);
                $.each(regular_rates, function (k, v) {
                    let the_rate = (v / 100) / regular_tax_rate;
                    let net_price = price - (the_rate * non_compound_price);
                    let tax_amount = price - net_price;
                    taxes[k] += tax_amount;
                });
            }
            $.each(taxes, function (k, v) {
                taxes[k] = viwebpos_round(v, 0);
            });
            return taxes;
        });
        $(document).on('viwebpos_calc_exclusive_tax', function (e, price, rates) {
            let taxes = {};
            if (Object.keys(rates).length) {
                $.each(rates, function (k, v) {
                    if (v.compound === 'yes') {
                        return true;
                    }
                    let tax_amount = price * v.rate / 100;
                    if (taxes[k]) {
                        taxes[k] += tax_amount;
                    } else {
                        taxes[k] = tax_amount;
                    }
                });
                let pre_compound_total = Object.keys(taxes).length ? Object.values(taxes).reduce((a, b) => a + b) : 0;
                $.each(rates, function (k, v) {
                    if (v.compound === 'no') {
                        return true;
                    }
                    let the_price_inc_tax = price + pre_compound_total;
                    let tax_amount = the_price_inc_tax * v.rate / 100;
                    if (taxes[k]) {
                        taxes[k] += tax_amount;
                    } else {
                        taxes[k] = tax_amount;
                    }
                    pre_compound_total = Object.values(taxes).reduce((a, b) => a + b);
                })
            }
            $.each(taxes, function (k, v) {
                taxes[k] = viwebpos_round(v, viwebpos_price.wc_get_rounding_precision);
            });
            return taxes;
        });
        $(document).on('viwebpos_get_matched_tax_rates', function (e, country, state, postcode, city, tax_class) {
            if (!viwebpos_price.viwebpos_taxes) {
                viwebpos_price.viwebpos_taxes = {};
            }
            let viwebpos_taxes = {}, found_priority = [];
            $.each(viwebpos_price.wc_tax_rates[tax_class], function (k, v) {
                if (found_priority.indexOf(v.tax_rate_priority) !== -1) {
                    return true;
                }
                if (v.tax_rate_country && v.tax_rate_country !== country.toUpperCase()) {
                    return true;
                }
                if (v.tax_rate_state && v.tax_rate_state !== state.toUpperCase()) {
                    return true;
                }
                if (v.postcode_count) {
                    let is_continue = true;
                    $.each(v.postcode, function (postcode_k, postcode_v) {
                        if (postcode.indexOf(postcode_v) !== -1) {
                            is_continue = false;
                            return false;
                        }
                    });
                    if (is_continue) {
                        return true;
                    }
                }
                if (v.city_count) {
                    if (v.city.indexOf(city.toUpperCase()) === -1) {
                        return true;
                    }
                }
                viwebpos_taxes[v.tax_rate_id] = {
                    'rate': parseFloat(v.tax_rate),
                    'label': v.tax_rate_name,
                    'shipping': parseInt(v.tax_rate_shipping) ? 'yes' : 'no',
                    'compound': parseInt(v.tax_rate_compound) ? 'yes' : 'no',
                };
                found_priority.push(v.tax_rate_priority);
            });
            viwebpos_price.viwebpos_taxes[tax_class] = viwebpos_taxes;
            return viwebpos_taxes;
        });
        $(document.body).on('viwebpos_apply_coupon', function (e, discounts, coupon, items_to_apply_coupon, coupon_type) {
            if (coupon_type && viwebpos_price_obj['cart_apply_coupon_' + coupon_type]) {
                return viwebpos_price_obj['cart_apply_coupon_' + coupon_type](discounts, coupon, items_to_apply_coupon);
            }
            return discounts;
        });
    });
    window.viwebpos_price_obj = {
        cart_apply_coupon_percent: function (discounts, coupon, items_to_apply_coupon) {
            if (!coupon || !items_to_apply_coupon) {
                return discounts;
            }
            let total_discount = 0,
                cart_total = 0,
                limit_usage_qty = 0,
                applied_count = 0,
                adjust_final_discount = true;
            if (coupon.limit_usage_to_x_items) {
                limit_usage_qty = coupon.limit_usage_to_x_items;
            }
            let coupon_amount = parseFloat(coupon.amount), coupon_code = coupon.coupon_code;
            $.each(items_to_apply_coupon, function (k, item) {
                // Find out how much price is available to discount for the item.
                let discounted_price = viwebpos_price_obj.get_discounted_price_in_cents(item, discounts);
                //Get the price we actually want to discount, based on settings.
                let price_to_discount = viwebpos_price.woocommerce_calc_discounts_sequentially ? discounted_price : viwebpos_round(item.price);
                // See how many and what price to apply to.
                let apply_quantity = limit_usage_qty && (limit_usage_qty - applied_count) < item.quantity ? (limit_usage_qty - applied_count) : item.quantity;
                apply_quantity = Math.max(0, $(document.body).triggerHandler('viwebpos_woocommerce_coupon_get_apply_quantity', [apply_quantity, item, coupon, discounts]) || apply_quantity);
                price_to_discount = (price_to_discount / item.quantity) * apply_quantity;
                // Run coupon calculations.
                let discount = Math.floor(price_to_discount * coupon_amount / 100);
                let filtered_discount = $(document.body).triggerHandler('viwebpos_woocommerce_coupon_get_discount_amount',
                    [viwebpos_price_obj.remove_number_precision(discount), viwebpos_price_obj.remove_number_precision(price_to_discount), item.object, false, coupon]);
                if (typeof filtered_discount !== 'undefined') {
                    filtered_discount = viwebpos_price_obj.add_number_precision(filtered_discount);
                    if (filtered_discount !== discount) {
                        discount = filtered_discount;
                        adjust_final_discount = false;
                    }
                }
                discount = viwebpos_round(Math.min(discounted_price, discount), 0);
                cart_total += price_to_discount;
                total_discount += discount;
                discounts[coupon_code][item.key] += discount;
            });
            let cart_total_discount = viwebpos_round(cart_total * (coupon_amount / 100), 2);
            if (total_discount < cart_total_discount && adjust_final_discount) {
                discounts = viwebpos_price_obj.cart_apply_coupon_remainder(discounts, coupon, items_to_apply_coupon, cart_total_discount - total_discount);
            }
            return discounts;
        },
        cart_apply_coupon_fixed_product: function (discounts, coupon, items_to_apply_coupon, amount = null, need_total_discount = false) {
            if (!coupon || !items_to_apply_coupon) {
                return discounts;
            }
            let total_discount = 0,
                limit_usage_qty = 0,
                applied_count = 0,
                coupon_code = coupon.coupon_code;
            amount = amount ? amount : viwebpos_price_obj.add_number_precision(coupon.amount);
            if (coupon.limit_usage_to_x_items) {
                limit_usage_qty = coupon.limit_usage_to_x_items;
            }
            $.each(items_to_apply_coupon, function (k, item) {
                // Find out how much price is available to discount for the item.
                let discounted_price = viwebpos_price_obj.get_discounted_price_in_cents(item, discounts);
                //Get the price we actually want to discount, based on settings.
                let price_to_discount = viwebpos_price.woocommerce_calc_discounts_sequentially ? discounted_price : item.price;
                // Run coupon calculations.
                let apply_quantity = item.quantity, discount = 0;
                if (limit_usage_qty) {
                    apply_quantity = limit_usage_qty - applied_count < apply_quantity ? limit_usage_qty - applied_count : apply_quantity;
                    apply_quantity = Math.max(0, ($(document.body).triggerHandler('viwebpos_woocommerce_coupon_get_apply_quantity', apply_quantity, item, coupon, discounts) || apply_quantity));
                    discount = Math.min(amount, item.price / item.quantity) * apply_quantity;
                } else {
                    apply_quantity = $(document.body).triggerHandler('viwebpos_woocommerce_coupon_get_apply_quantity', apply_quantity, item, coupon, discounts) || apply_quantity;
                    discount = apply_quantity * amount;
                }
                let filtered_discount = $(document.body).triggerHandler('viwebpos_woocommerce_coupon_get_discount_amount',
                    [viwebpos_price_obj.remove_number_precision(discount), viwebpos_price_obj.remove_number_precision(price_to_discount), item.object, false, coupon]);
                if (typeof filtered_discount !== 'undefined') {
                    discount = viwebpos_price_obj.add_number_precision(filtered_discount);
                }
                discount = Math.min(discounted_price, discount);
                total_discount += discount;
                applied_count += apply_quantity;
                // Store code and discount amount per item.
                discounts[coupon_code][item.key] += discount;
            });
            if (need_total_discount) {
                return {total_discount: total_discount, discounts: discounts};
            } else {
                return discounts;
            }
        },
        cart_apply_coupon_fixed_cart: function (discounts, coupon, items_to_apply_coupon, amount = null) {
            if (!coupon || !items_to_apply_coupon) {
                return discounts;
            }
            let total_discount = 0, item_count = 0, coupon_code = coupon.coupon_code;
            amount = amount ? amount : viwebpos_price_obj.add_number_precision(coupon.amount);
            items_to_apply_coupon = items_to_apply_coupon.filter(function (v) {
                return viwebpos_price_obj.get_discounted_price_in_cents(v, discounts) > 0;
            });
            $.each(items_to_apply_coupon, function (k, v) {
                item_count += v.quantity;
            });
            if (!item_count) {
                return discounts;
            }
            if (!amount) {
                discounts = viwebpos_price_obj.cart_apply_coupon_fixed_product(discounts, coupon, items_to_apply_coupon, 0)
            } else {
                let per_item_discount = Math.abs(parseInt(amount / item_count));
                if (per_item_discount > 0) {
                    let temp = viwebpos_price_obj.cart_apply_coupon_fixed_product(discounts, coupon, items_to_apply_coupon, per_item_discount, true);
                    if (temp['total_discount']) {
                        total_discount = temp['total_discount'];
                    }
                    if (temp['discounts']) {
                        discounts = temp['discounts'];
                    }
                    if (total_discount > 0 && total_discount < amount) {
                        discounts = viwebpos_price_obj.cart_apply_coupon_fixed_cart(discounts, coupon, items_to_apply_coupon, amount - total_discount);
                    }
                } else if (amount > 0) {
                    discounts = viwebpos_price_obj.cart_apply_coupon_remainder(discounts, coupon, items_to_apply_coupon, amount);
                }
            }
            return discounts;
        },
        cart_apply_coupon_custom: function (discounts, coupon, items_to_apply_coupon) {
            if (!coupon || !items_to_apply_coupon) {
                return discounts;
            }
            let limit_usage_qty = 0, applied_count = 0, coupon_code = coupon.coupon_code;
            if (coupon.limit_usage_to_x_items) {
                limit_usage_qty = coupon.limit_usage_to_x_items;
            }
            $.each(items_to_apply_coupon, function (k, item) {
                // Find out how much price is available to discount for the item.
                let discounted_price = viwebpos_price_obj.get_discounted_price_in_cents(item, discounts);
                //Get the price we actually want to discount, based on settings.
                let price_to_discount = viwebpos_price_obj.remove_number_precision(viwebpos_price.woocommerce_calc_discounts_sequentially ? discounted_price : item.price);
                // See how many and what price to apply to.
                let apply_quantity = limit_usage_qty && (limit_usage_qty - applied_count) < item.quantity ? (limit_usage_qty - applied_count) : item.quantity;
                apply_quantity = Math.max(0, $(document.body).triggerHandler('viwebpos_woocommerce_coupon_get_apply_quantity', [apply_quantity, item, coupon, discounts]) || apply_quantity);
                // Run coupon calculations.
                let discount = viwebpos_price_obj.add_number_precision($(document.body).triggerHandler('viwebpos_coupon_get_discount_amount', [coupon, price_to_discount / item.quantity, item.object, true])) * apply_quantity;
                discount = viwebpos_round(Math.min(discounted_price, discount), 0);
                applied_count += apply_quantity;
                discounts[coupon_code][item.key] += discount;
            });
            discounts[coupon_code] = $(document.body).triggerHandler('viwebpos_woocommerce_coupon_custom_discounts_array', [discounts[coupon_code], coupon, viwebpos_data]) || discounts[coupon_code];
            return discounts;
        },
        cart_apply_coupon_remainder: function (discounts, coupon, items_to_apply_coupon, amount) {
            if (!coupon || !items_to_apply_coupon) {
                return discounts;
            }
            let total_discount = 0;
            $.each(items_to_apply_coupon, function (k, item) {
                for (let i = 0; i < item.quantity; i++) {
                    // Find out how much price is available to discount for the item.
                    let price_to_discount = viwebpos_price_obj.get_discounted_price_in_cents(item, discounts);
                    // Run coupon calculations.
                    let discount = Math.min(price_to_discount, 1);
                    // Store totals.
                    total_discount += discount;
                    //store code and discount amount per item
                    discounts[coupon.coupon_code][item.key] += discount;
                    if (total_discount >= amount) {
                        break;
                    }
                }
                if (total_discount >= amount) {
                    return false;
                }
            });
            return discounts;
        },
        cart_get_discounts_by_coupon: function (discounts, in_cents = false) {
            if (!discounts) {
                return 0;
            }
            let coupon_discount_totals = {};
            $.each(discounts, function (coupon_code, discount) {
                coupon_discount_totals[coupon_code] = Object.values(discount).reduce((a, b) => (parseFloat(a) || 0) + (parseFloat(b) || 0));
            });
            return in_cents ? coupon_discount_totals : viwebpos_price_obj.remove_number_precision_deep(coupon_discount_totals);
        },
        cart_get_tax_class_costs: function (cart_items, cart_shipping, current_fees) {
            let result = {}, tax_class = {}, costs = {}, first_key;
            let temp = {};
            $.each(cart_items, function (k, v) {
                tax_class[k] = v['tax_class'];
                temp[k] = v;
            });
            $.each(cart_shipping, function (k, v) {
                tax_class[k] = v['tax_class'];
                temp[k] = v;
            });
            $.each(current_fees, function (k, v) {
                tax_class[k] = v['tax_class'];
                temp[k] = v;
            });
            $.each(tax_class, function (k, v) {
                costs[v] = 0;
            });
            first_key = Object.keys(tax_class)[0] || '';
            costs['non-taxable'] = 0;
            $.each(temp, function (k, v) {
                let total = parseFloat(v.total);
                if (0 > total) {
                    return true;
                }
                if (!v.taxable) {
                    costs['non-taxable'] += total;
                } else if (v.tax_class === 'inherit') {
                    costs[first_key] += total;
                } else {
                    costs[v.tax_class] += total;
                }
            });
            $.each(costs, function (k, v) {
                if (v > 0) {
                    result[k] = v;
                }
            });
            return result;
        },
        get_discounted_price_in_cents: function (item, discounts) {
            if (!item) {
                return 0;
            }
            let item_discount = 0;
            if (discounts && Object.keys(discounts).length) {
                item_discount = viwebpos_price_obj.get_discounts_by_item(discounts, true);
                item_discount = item_discount[item.key] ? item_discount[item.key] : 0;
            }
            return Math.abs(viwebpos_round(item.price - item_discount));
        },
        get_discounts_by_item: function (discounts, in_cents = false) {
            if (!discounts) {
                return {};
            }
            let item_discount_totals = {};
            $.each(discounts, function (coupon_code, item_discounts) {
                $.each(item_discounts, function (k, v) {
                    if (!item_discount_totals[k]) {
                        item_discount_totals[k] = 0;
                    }
                    item_discount_totals[k] += v;
                });
            });
            return in_cents ? item_discount_totals : viwebpos_price_obj.remove_number_precision_deep(item_discount_totals);
        },
        adjust_non_base_location_price: function (item) {
            if (item.price_includes_tax && item.taxable) {
                let base_tax_rates = viwebpos_price_obj.get_base_tax_rates(item.product['base_tax_class'] || '');
                let base_tax_rates_id = Object.keys(base_tax_rates), is_equal = true;
                for (let temp of base_tax_rates_id) {
                    if (!item.tax_rates[temp]) {
                        is_equal = false;
                        break;
                    }
                }
                if (!is_equal) {
                    let taxes = $(document).triggerHandler('viwebpos_calc_inclusive_tax', [item.price, base_tax_rates]), taxes_sum = 0;
                    if (Object.keys(taxes).length) {
                        taxes_sum = Object.values(taxes).reduce((a, b) => a + b);
                    }
                    let new_taxes = $(document).triggerHandler('viwebpos_calc_inclusive_tax', [item.price - taxes_sum, item.tax_rates]), new_taxes_sum = 0;
                    if (Object.keys(new_taxes).length) {
                        new_taxes_sum = Object.values(new_taxes).reduce((a, b) => a + b);
                    }
                    item.price = item.price - taxes_sum + new_taxes_sum;
                }
            }
            return item;
        },
        get_base_tax_rates: function (tax_class = '') {
            let shop_address = viwebpos_price.shop_address;
            return $(document).triggerHandler('viwebpos_get_matched_tax_rates', [shop_address.country, shop_address.state, shop_address.postcode, shop_address.city, tax_class]);
        },
        remove_number_precision: function (value) {
            let cent_precision = Math.pow(10, viwebpos_price.wc_get_price_decimals);
            return value / cent_precision;
        },
        remove_number_precision_deep: function (value) {
            if (typeof value !== 'object') {
                return viwebpos_price_obj.remove_number_precision(parseFloat(value ?? 0));
            }
            $.each(value, function (k, v) {
                value[k] = viwebpos_price_obj.remove_number_precision_deep(v);
            });
            return value;
        },
        add_number_precision: function (value, round = true) {
            let cent_precision = Math.pow(10, viwebpos_price.wc_get_price_decimals);
            value *= cent_precision;
            return round ? viwebpos_round(value, viwebpos_price.wc_get_rounding_precision - viwebpos_price.wc_get_price_decimals) : value;
        },
        add_number_precision_deep: function (value, round = true) {
            if (typeof value !== 'object') {
                return viwebpos_price_obj.add_number_precision(parseFloat(value ?? 0), round);
            }
            $.each(value, function (k, v) {
                value[k] = viwebpos_price_obj.add_number_precision_deep(v, round);
            });
            return value;
        },
        array_merge_recursive_numeric: function ($arrays) {
            if (typeof $arrays !== "object") {
                return {};
            }
            if ($arrays.length === 1) {
                return $arrays[0];
            }
            $.each($arrays, function (k, v) {
                if (typeof v !== "object") {
                    delete $arrays[k];
                }
            });
            let result = $arrays.pop();
            $.each($arrays, function (k, $array) {
                $.each(result, function (key1, value1) {
                    if (typeof $array[key1] !== "undefined") {
                        if (!isNaN(value1) && !isNaN($array[key1])) {
                            result[key1] = parseFloat(value1) + parseFloat($array[key1]);
                        } else if (typeof value1 === "object" && typeof $array[key1] === "object") {
                            result[key1] = viwebpos_price_obj.array_merge_recursive_numeric([value1, $array[key1]]);
                        } else {
                            result[key1] = $array[key1];
                        }
                    }
                });
                $.each($array, function (key, value) {
                    if (typeof result[key] === "undefined") {
                        result[key] = value;
                    }
                });
            });
            return result;
        }
    }
    window.viwebpos_get_price_html = function (value, decimals = null, decimal_separator = null, thousand_separator = null, format = null, currency_symbol = null) {
        return $(document).triggerHandler('viwebpos_get_price_html', [value, decimals, decimal_separator, thousand_separator, format, currency_symbol]);
    }
    window.viwebpos_format_number = function (value, decimals = null, decimal_separator = null, thousand_separator = null) {
        decimals = parseInt(decimals);
        value = parseFloat(value || 0);
        value = value.toFixed(decimals).toString();
        if (!decimals) {
            return value.replace(/\B(?=(?:\d{3})+(?!\d))/g, thousand_separator);
        }
        let temp = value.substring(0, value.length - decimals - 1);
        temp = temp.replace(/\B(?=(?:\d{3})+(?!\d))/g, thousand_separator);
        value = temp + decimal_separator + value.substring(value.length - decimals);
        return value;
    }
    window.viwebpos_round = function (value, decimal = '') {
        decimal = (decimal === '') ? viwebpos_price.wc_get_price_decimals : decimal;
        return Math.round(value * Math.pow(10, decimal)) / Math.pow(10, decimal);
    }
    window.viwebpos_get_product_price_including_tax = function (product, qty = '', price = '') {
        price = price !== '' ? Math.max(0, parseFloat(price)) : product.price;
        qty = qty ? Math.max(0, parseFloat(qty)) : 1;
        if (price === '' || qty <= 0) {
            return 0;
        }
        let line_price = price * qty;
        let return_price = line_price;
        if (!product.taxable) {
            return return_price;
        }
        let shop_address = typeof viwebpos_data.current_cart['shop_address'] !== "undefined" ? viwebpos_data.current_cart['shop_address'] : viwebpos_price.shop_address;
        let tax_rates = $(document).triggerHandler('viwebpos_get_matched_tax_rates', [shop_address.country, shop_address.state, shop_address.postcode, shop_address.city, product.tax_class || '']);
        let remove_taxes;
        if (!viwebpos_price.product_price_includes_tax) {
            remove_taxes = $(document).triggerHandler('viwebpos_calc_exclusive_tax', [line_price, tax_rates]);
            if (Object.values(remove_taxes).length) {
                return_price += Object.values(remove_taxes).reduce((a, b) => a + b);
            }
        } else {
            shop_address = viwebpos_price.shop_address;
            let base_tax_rates = $(document).triggerHandler('viwebpos_get_matched_tax_rates', [shop_address.country, shop_address.state, shop_address.postcode, shop_address.city, product.base_tax_class || '']);
            let base_tax_rates_id = Object.keys(base_tax_rates), is_equal = true;
            for (let temp of base_tax_rates_id) {
                if (!tax_rates[temp]) {
                    is_equal = false;
                    break;
                }
            }
            if (!is_equal) {
                let base_taxes = $(document).triggerHandler('viwebpos_calc_inclusive_tax', [line_price, base_tax_rates]), base_taxes_sum = 0, base_taxes_sum1 = 0;
                if (Object.keys(base_taxes).length) {
                    base_taxes_sum = Object.values(base_taxes).reduce((a, b) => a + b);
                    base_taxes_sum1 = Object.values(base_taxes).reduce((a, b) => viwebpos_price.wc_tax_round_at_subtotal ? (a + b) : (viwebpos_round(a) + viwebpos_round(b)));
                }
                let new_taxes = $(document).triggerHandler('viwebpos_calc_inclusive_tax', [line_price - base_taxes_sum, tax_rates]), new_taxes_sum = 0;
                if (Object.keys(new_taxes).length) {
                    new_taxes_sum = Object.values(new_taxes).reduce((a, b) => viwebpos_price.wc_tax_round_at_subtotal ? (a + b) : (viwebpos_round(a) + viwebpos_round(b)));
                }
                return_price = viwebpos_round(return_price - base_taxes_sum1 + new_taxes_sum);
            }
        }
        return return_price;
    }
    window.viwebpos_get_product_price_excluding_tax = function (product, qty = '', price = '') {
        price = price !== '' ? Math.max(0, parseFloat(price)) : product.price;
        qty = qty ? Math.max(0, parseFloat(qty)) : 1;
        if (price === '' || qty <= 0) {
            return 0;
        }
        let line_price = price * qty;
        let return_price = line_price;
        if (product.taxable && viwebpos_price.product_price_includes_tax) {
            let shop_address = viwebpos_price.shop_address;
            let tax_rates = $(document).triggerHandler('viwebpos_get_matched_tax_rates', [shop_address.country, shop_address.state, shop_address.postcode, shop_address.city, product.tax_class || '']);
            let remove_taxes = $(document).triggerHandler('viwebpos_calc_inclusive_tax', [line_price, tax_rates]);
            if (Object.values(remove_taxes).length) {
                return_price -= Object.values(remove_taxes).reduce((a, b) => a + b);
            }
        }
        return return_price;
    }
    window.viwebpos_get_product_price = function (product, qty = '', price = '') {
        let result;
        if (viwebpos_price.pd_display_prices_including_tax) {
            result = viwebpos_get_product_price_including_tax(product, qty, price);
        } else {
            result = viwebpos_get_product_price_excluding_tax(product, qty, price);
        }
        return viwebpos_get_price_html(result);
    }
})(jQuery);