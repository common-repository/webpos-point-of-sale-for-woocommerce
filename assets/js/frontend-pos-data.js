jQuery(function ($) {
    'use strict';
    if (typeof viwebpos === 'undefined') {
        return false;
    }
    let indexedDB = window.indexedDB || window.mozIndexedDB || window.webkitIndexedDB || window.msIndexedDB;
    try {
        indexedDB.open('viwebpos_test', 1);
        indexedDB.deleteDatabase('viwebpos_test');
    } catch (e) {
        indexedDB = false;
    }
    if (!indexedDB) {
        alert("Your browser doesn't support a stable version of IndexedDB. So WebPOS feature will not be available.");
        location.href = viwebpos.my_account_url;
    }
    let viwebpos_data = {
        viwebposDB: null,
        auto_print_receipt: null,
        current_cart: null,
        today: viwebpos.today,
        data_prefix: viwebpos.data_prefix,
        data_settings_bill: null,
        default_settings_bill: {
            //product_style: 'basic',
            auto_atc: 1,
            checkout_subtotal: 1,
            checkout_tax: 1,
            cart_item_number: 1,
            cart_item_price: 1,
            cart_item_subtotal: 1,
            suggested_amount: 1,
        },
        ajax: [],
        table_data: {
            settings: {autoIncrement: true},
            cart: {
                key_path: 'id',
                indexs: ['status'],
                autoIncrement: true
            },
            products: {
                autoIncrement: false,
                key_path: 'id',
                indexs: ['barcode', 'sku', 'type', 'parent_id']
            },
            coupons: {
                autoIncrement: false,
                key_path: 'coupon_code',
                indexs: ['type', 'expiry_date', 'email'],
            },
            customers: {
                autoIncrement: false,
                key_path: 'id',
                indexs_delete: ['email'],
                indexs_unique: ['username'],
                indexs: ['email', 'phone'],
            },
            orders: {
                autoIncrement: false,
                key_path: 'id',
                indexs: ['type', 'customer_id']
            },
            transactions: {
                autoIncrement: false,
                key_path: 'id',
                indexs: ['type', 'method', 'date', 'cashier_id']
            },
        },
        init: function () {
            let self = this;
            self.viwebposDB = indexedDB.open('viwebposDB', viwebpos.data_version || 1);
            if (!self.viwebposDB) {
                return false;
            }
            self.init_event();
            self.viwebposDB.onsuccess = function (event) {
                $(document.body).trigger('viwebpos_data_init');
            };
            self.viwebposDB.onupgradeneeded = function (event) {
                let db = event.target.result,
                    oldVersion = parseFloat(event.oldVersion),
                    transaction = event.target.transaction;
                if (oldVersion && oldVersion < parseFloat(viwebpos.data_version)) {
                    self.refresh_database = true;
                }
                self.create_data_table(db, transaction);
            };
        },
        init_event: function () {
            let self = this;
            $(document.body).on('viwebpos_data_init', function (e) {
                self.data_init();
            });
            $(document.body).on('viwebpos_settings_pos', function (e) {
                self.settings_pos();
            });
            $(document.body).on('viwebpos_refresh_cart', function (e, refresh = true) {
                self.refresh_cart(refresh);
            });
            $(document.body).on('viwebpos_cart_data_validate', function (e, cart_data, reset_total = false, messages = []) {
                if (self.refreshing_data) {
                    let after_refreshed_data = self.after_refreshed_data || [];
                    after_refreshed_data.push({
                        call_back: 'viwebpos_cart_data_validate_after_refreshed_data',
                        arg: {
                            cart_data: cart_data,
                            reset_total: reset_total,
                        }
                    });
                    self.after_refreshed_data = after_refreshed_data;
                } else {
                    self.cart_data_validate(cart_data, reset_total, messages);
                }
            });
            $(document.body).on('viwebpos_cart_data_validate_after_refreshed_data', function (e, data) {
                self.cart_data_validate(data.cart_data || self.current_cart, data.reset_total || true);
            });
            $(document.body).on('viwebpos_coupon_get_discount_amount', function (e, coupon, discounting_amount, cart_item = null, single = false) {
                return self.coupon_get_discount_amount(coupon, discounting_amount, cart_item, single);
            });
            $(document.body).on('viwebpos_cart_remove_coupon', function (e, coupon_code) {
                self.cart_remove_coupon(coupon_code);
            });
            $(document.body).on('viwebpos_cart_add_discount', function (e, coupon_code) {
                self.cart_add_discount(coupon_code);
            });
            $(document.body).on('viwebpos_wc_before_apply_coupon', function (e, coupon, coupon_code) {
                self.cart_before_apply_coupon(coupon, coupon_code);
            });
            $(document.body).on('viwebpos_woocommerce_applied_coupon', function () {
                self.cart_calculate_totals([{
                    message: viwebpos_text.success_apply_coupon,
                    status: ['success', 'apply-coupon']
                }]);
            });
            $(document.body).on('viwebpos_add_new_customer', function (e, customer, current_customer = false) {
                self.add_new_customer(customer, current_customer);
            });
            $(document.body).on('viwebpos_checkout_set_payments', function (e, paid) {
                self.cart_set_payments(paid);
            });
            $(document.body).on('viwebpos_create_order', function (e) {
                self.create_new_order();
            });
            $(document.body).on('viwebpos_add_new_transaction', function (e, transaction) {
                self.add_new_transaction(transaction);
            });
            $(document.body).on('viwebpos_search_data', function (e, type, key, limit, page, network = null) {
                let data = self.search_data(type, key, limit, page, network);
                return data;
            });
            $(document.body).on('viwebpos_before_refresh_data', function (e, type = '') {
                $('.viwebpos-wrap').addClass('viwebpos-wrap-loading');
            });
            $(document.body).on('viwebpos_refresh_data', function (e, type, database = '') {
                self.refresh_data(database, type);
            });
            $(document.body).on('viwebpos_refreshed_data', function (e, pos_data = null, database = null) {
                let get_call_back = async function () {
                    let call_back_function = [];
                    await new Promise(function (resolve) {
                        if (self.after_refreshed_data) {
                            let trigger = self.after_refreshed_data;
                            $.each(trigger, function (k, v) {
                                if (v.call_back) {
                                    call_back_function.push(v.call_back);
                                }
                            });
                            resolve(call_back_function);
                        } else {
                            resolve(call_back_function);
                        }
                    });
                    return call_back_function.indexOf('viwebpos_data_init') > -1;
                };
                get_call_back().then(function (refresh_data) {
                    $(document.body).trigger('villatheme_show_message', [viwebpos.refreshed_data_message, ['success', 'refreshed-data'], '', false, 4500]);
                    if (self.after_refreshed_data) {
                        let trigger = self.after_refreshed_data;
                        $.each(trigger, function (k, v) {
                            if (v.call_back) {
                                if (typeof v.arg === "undefined") {
                                    $(document.body).trigger(v.call_back);
                                } else {
                                    $(document.body).trigger(v.call_back, v.arg);
                                }
                            }
                        });
                        self.after_refreshed_data = {};
                        delete self.after_refreshed_data;
                    }
                    if (!refresh_data) {
                        if (!self.viwebposDB) {
                            self.viwebposDB = indexedDB.open('viwebposDB');
                        }
                        let table = self.viwebposDB.result;
                        self.put(table.transaction('settings', 'readwrite').objectStore('settings'), self.data_prefix, 'data_prefix');
                        $.each(viwebpos.data_prefix, function (k, v) {
                            let total_page = parseFloat(self.data_prefix[k + '_total_page'] || 0),
                                current_page = parseFloat(self.data_prefix[k + '_current_page'] || 0);
                            if (total_page && current_page && total_page > current_page - 1) {
                                let per_page = (k === 'products') ? 1000 : 500;
                                let get_data = viwebpos_data.get_refresh_data(k, total_page, current_page, per_page);
                                if (get_data) {
                                    viwebpos_data.add_request(get_data);
                                }
                            }
                        });
                    }
                });
            });
        },
        create_data_table: function (database, transaction) {
            $.each(viwebpos_data.table_data, function (k, v) {
                let store;
                if (!database.objectStoreNames.contains(k)) {
                    store = database.createObjectStore(k, v.key_path ? {keyPath: v.key_path, autoIncrement: v.autoIncrement} : {autoIncrement: v.autoIncrement});
                } else {
                    store = transaction.objectStore(k);
                }
                if (store) {
                    viwebpos_data.create_data_table_index(store, v);
                }
            });
        },
        create_data_table_index: function (store, keys) {
            if (keys.indexs_delete) {
                $.each(keys.indexs_delete, function (k, v) {
                    if (store.indexNames.contains(v)) {
                        store.deleteIndex(v);
                    }
                })
            }
            if (keys.indexs_unique) {
                $.each(keys.indexs_unique, function (k, v) {
                    if (!store.indexNames.contains(v)) {
                        store.createIndex(v, v, {unique: true});
                    }
                })
            }
            if (keys.indexs) {
                $.each(keys.indexs, function (k, v) {
                    if (!store.indexNames.contains(v)) {
                        store.createIndex(v, v);
                    }
                })
            }
        },
        add_request: function (request) {
            if (request) {
                viwebpos_data.ajax.push(request);
            }
            if (viwebpos_data.ajax.length === 1) {
                viwebpos_data.run_request();
            }
        },
        run_request: function (request = null) {
            if (request) {
                viwebpos_ajax(request);
                return;
            }
            let originalCallback = viwebpos_data.ajax[0].complete;
            if (viwebpos_data.ajax[0].beforeSend) {
                viwebpos_data.ajax[0].beforeSend();
            }
            viwebpos_data.ajax[0].complete = function () {
                viwebpos_data.ajax.shift();
                if (typeof originalCallback === 'function') {
                    originalCallback();
                }
                if (viwebpos_data.ajax.length > 0) {
                    viwebpos_data.run_request();
                }
            };
            viwebpos_ajax(viwebpos_data.ajax[0]);
        },
        adds: function (object_store, data, key_path = false) {
            $.each(data, function (k, v) {
                viwebpos_data.add(object_store, v, key_path ? k : false);
            });
        },
        add: function (object_store, data, key_path = false) {
            if (key_path) {
                object_store.add(data, key_path);
            } else {
                object_store.add(data);
            }
        },
        puts: function (object_store, data, key_path = false) {
            $.each(data, function (k, v) {
                viwebpos_data.put(object_store, v, key_path ? k : false);
            });
        },
        put: function (object_store, data, key_path = false) {
            if (key_path) {
                object_store.put(data, key_path);
            } else {
                object_store.put(data);
            }
        },
        deletes: function (object_store, key_paths) {
            $.each(key_paths, function (k, v) {
                viwebpos_data.delete(object_store, v);
            });
        },
        delete: function (object_store, key_path = false) {
            if (key_path) {
                object_store.delete(key_path);
            }
        },
        data_init: function () {
            let self = this;
            if (!self.viwebposDB) {
                self.viwebposDB = indexedDB.open('viwebposDB');
            }
            let database = self.viwebposDB.result;
            let init = async function () {
                if (self.refresh_database) {
                    setTimeout(function () {
                        $('.viwebpos-menu-item-refresh_database').trigger('click');
                    }, 100);
                    return;
                }
                if (self.auto_print_receipt === null) {
                    await new Promise(function (resolve) {
                        let settings = database.transaction('settings', 'readwrite').objectStore('settings');
                        settings.get('auto_print_receipt').onsuccess = function (e) {
                            let auto_print_receipt = e.target.result;
                            if (!auto_print_receipt && auto_print_receipt !== '') {
                                auto_print_receipt = 1;
                                self.put(settings, auto_print_receipt, 'auto_print_receipt');
                            }
                            self.auto_print_receipt = auto_print_receipt;
                            $(document.body).trigger('viwebpos_set_auto_print_receipt_after_checkout', [self.auto_print_receipt]);
                            resolve(self);
                        }
                    });
                }
                if (self.data_settings_bill === null) {
                    await new Promise(function (resolve) {
                        let settings = database.transaction('settings', 'readonly').objectStore('settings');
                        settings.get('settings_pos').onsuccess = function (e) {
                            let settings_pos = e.target.result,
                                tmp = {...self.default_settings_bill};
                            if (typeof settings_pos === "object" && Object.keys(settings_pos).length) {
                                $.each(settings_pos, function (k, v) {
                                    tmp[k] = v;
                                });
                            }
                            self.data_settings_bill = tmp;
                            resolve(self);
                        }
                    });
                }
                if (!self.current_cart) {
                    await new Promise(function (resolve) {
                        let carts = database.transaction('cart', 'readwrite').objectStore('cart');
                        carts.index('status').get('active').onsuccess = function (e) {
                            let cart = e.target.result;
                            if (!cart) {
                                carts.clear();
                                cart = self.set_cart_empty({status: 'active'});
                                self.add(carts, cart);
                            } else {
                                self.current_cart = cart;
                            }
                            resolve(self);
                        }
                    });
                }
                return true;
            };
            init().then(function (result) {
                if (!result) {
                    return;
                }
                let current = viwebpos.pos_pathname.includes('://') ? window.location.href : window.location.pathname;
                let page = 'bill-of-sale', temp = '';
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
                    temp = temp.indexOf('/') === 0 ? temp.substring(1).split('/') : temp.split('/');
                    page = temp[0] ? temp[0] : page;
                }
                let settings = database.transaction('settings', 'readwrite').objectStore('settings');
                settings.get('data_prefix').onsuccess = function (event) {
                    let data_prefix = event.target.result || {}, update_prefix = 0,
                        prefix_not_check = [
                            'coupons_current_page',
                            'coupons_total_page',
                            'customers_current_page',
                            'customers_total_page',
                            'orders_current_page',
                            'orders_total_page',
                            'products_current_page',
                            'products_total_page',
                            'transactions_current_page',
                            'transactions_total_page',
                        ];
                    $.each(self.data_prefix, function (k, v) {
                        if (!prefix_not_check.includes(k) && (!data_prefix[k] || data_prefix[k] !== v)) {
                            update_prefix++;
                            data_prefix[k] = v;
                            let object_store = database.transaction(k, 'readwrite').objectStore(k);
                            object_store.clear();
                            $(document.body).trigger('viwebpos_refresh_data', [k, database]);
                        }
                    });
                    self.data_prefix = data_prefix;
                    if (update_prefix) {
                        let after_refreshed_data = self.after_refreshed_data || [];
                        if (!$('.viwebpos-container-element').length || $('.viwebpos-container-element-reload').length) {
                            after_refreshed_data.push({
                                call_back: 'viwebpos-frontend-load',
                                arg: page
                            });
                        }
                        self.after_refreshed_data = after_refreshed_data;
                    } else {
                        $(document.body).trigger('viwebpos-frontend-load', [page]);
                        $(document.body).trigger('viwebpos_refreshed_data');
                    }
                };
            });
        },
        get_refresh_data: function (type, total_pages, page, per_page, database = null) {
            if (!type) {
                return false;
            }
            viwebpos_data.refreshing_data = true;
            let refresh_bg = false, data = {
                limit: per_page,
                page: page,
                viwebpos_nonce: viwebpos.nonce
            };
            let custom_data = $(document.body).triggerHandler('viwebpos_ajax_get_refresh_data', type, total_pages, page, per_page);
            if (custom_data) {
                data = custom_data;
            }
            if (!database) {
                refresh_bg = true;
            }
            return {
                type: 'post',
                url: viwebpos.viwebpos_ajax_url.toString().replace('%%endpoint%%', 'viwebpos_get_' + type + '_data'),
                data: $.param(data),
                beforeSend: function () {
                    if (page === 1) {
                        if ($('.villatheme-show-message-message-refresh-data').length) {
                            setTimeout(function (message) {
                                $(document.body).trigger('villatheme_show_message', [message, ['', 'refresh-data'], '', false, 4500]);
                            }, 50, viwebpos['refresh_' + type + '_data_message'] || '');
                        } else {
                            $(document.body).trigger('villatheme_show_message', [viwebpos['refresh_' + type + '_data_message'] || '', ['', 'refresh-data'], '', false, 4500]);
                        }
                    }
                },
                success: function (response) {
                    let refresh_data = response.data || {}, database = viwebpos_data.viwebposDB.result;
                    viwebpos_data.puts(database.transaction(type, 'readwrite').objectStore(type), refresh_data);
                    if (response.total_pages) {
                        total_pages = parseFloat(response.total_pages);
                        viwebpos_data.data_prefix[type + '_total_page'] = total_pages;
                    }
                    page = parseFloat(response.page || 0);
                    if (page) {
                        viwebpos_data.data_prefix[type + '_current_page'] = page;
                    }
                    if (total_pages && page && total_pages > page - 1 && (page < 3 || refresh_bg)) {
                        let get_data = viwebpos_data.get_refresh_data(type, total_pages, page, per_page, refresh_bg ? '' : database);
                        if (get_data) {
                            viwebpos_data.add_request(get_data);
                        }
                    }
                    if (total_pages && page !== 3 && total_pages <= page) {
                        delete viwebpos_data.data_prefix[type + '_current_page'];
                        delete viwebpos_data.data_prefix[type + '_total_page'];
                    }
                },
                error: function (err) {
                    $(document.body).trigger('villatheme_show_message', [err.statusText, ['error', 'customer-adding'], err.responseText === '-1' ? '' : err.responseText, false, 4500]);
                },
                complete: function () {
                    if (!viwebpos_data.ajax.length) {
                        viwebpos_data.refreshing_data = '';
                        delete viwebpos_data.refreshing_data;
                        $(document.body).trigger('viwebpos_refreshed_data', [viwebpos_data, database]);
                    }
                }
            };
        },
        refresh_data: function (database, type) {
            if (!database || !type) {
                return false;
            }
            let total_pages = 0, page = 1, per_page = (type === 'products') ? 1000 : 500;
            if (per_page) {
                let ajax_info = viwebpos_data.get_refresh_data(type, total_pages, page, per_page, database);
                $(document.body).trigger('viwebpos_before_refresh_data', [type]);
                if (ajax_info) {
                    viwebpos_data.add_request(ajax_info);
                }
            }
        },
        get_data: function (database, key, limit, page, type = '', index = null) {
            if (!type) {
                return [];
            }
            //set capacity for paging
            let cap = limit;
            let open_cursor = null, result = [], count = 1, offer = (page - 1) * cap + 1;
            let datas = async function () {
                let available_variation_ids = null, result_ids = [];
                await new Promise(function (resolve) {
                    if (!key && !limit) {
                        database.getAll().onsuccess = function (event) {
                            result = event.target.result;
                            resolve(result);
                        }
                    } else if (index !== null) {
                        if (index) {
                            if (limit === 1 && !key) {
                                database.index(index.name).get(index.value).onsuccess = function (event) {
                                    result = event.target.result;
                                    resolve(result);
                                };
                            } else {
                                open_cursor = database.index(index.name).openCursor(index.value, 'prev');
                            }
                        } else if (key) {
                            database.get(key).onsuccess = function (event) {
                                result = event.target.result;
                                resolve(result);
                            };
                        }
                    } else {
                        open_cursor = database.openCursor(null, 'prev');
                    }
                    if (open_cursor) {
                        key = key.toString().toLowerCase();
                        open_cursor.onsuccess = function (event) {
                            let cursor = event.target.result;
                            if (!cursor) {
                                resolve(result);
                                return false;
                            }
                            if (!limit) {
                                if ($.inArray(type, ['transactions', 'orders', 'customers', 'products']) > -1) {
                                    switch (type) {
                                        case "transactions":
                                            if (cursor.value.date !== viwebpos_data.today) {
                                                cursor.continue();
                                                return true;
                                            }
                                            if (!key || cursor.value.id.toString().indexOf(key) > -1 ||
                                                cursor.value.order_id.toString().indexOf(key) > -1 ||
                                                cursor.value.note.toString().toLowerCase().indexOf(key) > -1 ||
                                                cursor.value.method.toString().toLowerCase().indexOf(key) > -1 ||
                                                cursor.value.in.toString().indexOf(key) > -1 ||
                                                cursor.value.out.toString().indexOf(key) > -1) {
                                                result.push({next_page: Math.ceil(count / cap)});
                                                resolve(result);
                                                return false;
                                            }
                                            break;
                                        case "orders":
                                            if (!key || cursor.value.order_number.indexOf(key) > -1 ||
                                                cursor.value.email.toString().toLowerCase().indexOf(key) > -1 ||
                                                cursor.value.total >= parseFloat(key) ||
                                                cursor.value.billing_address.first_name.toString().toLowerCase().indexOf(key) > -1 ||
                                                cursor.value.billing_address.last_name.toString().toLowerCase().indexOf(key) > -1) {
                                                result.push({next_page: Math.ceil(count / cap)});
                                                resolve(result);
                                                return false;
                                            }
                                            break;
                                        case "customers":
                                            if (!key || cursor.value.username.indexOf(key) > -1 ||
                                                cursor.value.phone.toString().toLowerCase().indexOf(key) > -1 ||
                                                cursor.value.email.toString().toLowerCase().indexOf(key) > -1 ||
                                                cursor.value.first_name.toString().toLowerCase().indexOf(key) > -1 ||
                                                cursor.value.last_name.toString().toLowerCase().indexOf(key) > -1) {
                                                result.push({next_page: Math.ceil(count / cap)});
                                                resolve(result);
                                                return false;
                                            }
                                            break;
                                        case "products":
                                            if (cursor.value.type.indexOf('variable') > -1) {
                                                cursor.continue();
                                                return true;
                                            }
                                            if (!key || cursor.value.name.toString().toLowerCase().indexOf(key) > -1 ||
                                                cursor.value.sku.toString().toLowerCase().indexOf(key) > -1 || cursor.value.barcode.toString().toLowerCase().indexOf(key) > -1) {
                                                result.push({next_page: Math.ceil(count / cap)});
                                                resolve(result);
                                                return false;
                                            }
                                            break;
                                    }
                                    count++;
                                    cursor.continue();
                                    return true;
                                } else {
                                    resolve(result);
                                    return false;
                                }
                            }
                            switch (type) {
                                case "customers":
                                    if (count < offer) {
                                        count++;
                                        cursor.continue();
                                        return true;
                                    }
                                    count++;
                                    if (!key || cursor.value.username.indexOf(key) > -1 ||
                                        cursor.value.phone.toString().toLowerCase().indexOf(key) > -1 ||
                                        cursor.value.email.toString().toLowerCase().indexOf(key) > -1 ||
                                        cursor.value.first_name.toString().toLowerCase().indexOf(key) > -1 ||
                                        cursor.value.last_name.toString().toLowerCase().indexOf(key) > -1) {
                                        limit--;
                                        result.push(cursor.value);
                                    }
                                    break;
                                case "orders":
                                    if (count < offer) {
                                        count++;
                                        cursor.continue();
                                        return true;
                                    }
                                    count++;
                                    if (!key || cursor.value.order_number.indexOf(key) > -1 ||
                                        cursor.value.email.toString().toLowerCase().indexOf(key) > -1 ||
                                        cursor.value.total >= parseFloat(key) ||
                                        cursor.value.billing_address.first_name.toString().toLowerCase().indexOf(key) > -1 ||
                                        cursor.value.billing_address.last_name.toString().toLowerCase().indexOf(key) > -1) {
                                        limit--;
                                        result.push(cursor.value);
                                        result_ids.push(cursor.value.id);
                                        break;
                                    }
                                    break;
                                case "products":
                                    if (cursor.value.type.indexOf('variable') > -1) {
                                        if (key && $('.viwebpos-search-product-scanned').length && cursor.value.barcode.toString().toLowerCase() === key) {
                                            result = [];
                                            available_variation_ids = cursor.value.available_variation_ids || [];
                                            limit = available_variation_ids.length > cap ? cap : available_variation_ids.length;
                                        }
                                        cursor.continue();
                                        return true;
                                    }
                                    if (count < offer) {
                                        count++;
                                        cursor.continue();
                                        return true;
                                    }
                                    count++;
                                    if (!available_variation_ids) {
                                        if (key && $('.viwebpos-search-product-scanned').length && cursor.value.barcode.toString().toLowerCase() === key) {
                                            result = [cursor.value];
                                            limit = false;
                                            resolve(result);
                                            return false;
                                        } else if (!key || cursor.value.name.toString().toLowerCase().indexOf(key) > -1 ||
                                            cursor.value.sku.toString().toLowerCase().indexOf(key) > -1 || cursor.value.barcode.toString().toLowerCase().indexOf(key) > -1) {
                                            limit--;
                                            result.push(cursor.value);
                                            result_ids.push(cursor.value.id);
                                        }
                                    } else {
                                        if (available_variation_ids.includes(cursor.value.id)) {
                                            limit--;
                                            result.push(cursor.value);
                                            result_ids.push(cursor.value.id);
                                            if (!limit) {
                                                limit = false;
                                                resolve(result);
                                                return false;
                                            }
                                        }
                                    }
                                    break;
                                case "transactions":
                                    if (cursor.value.date !== viwebpos_data.today) {
                                        cursor.continue();
                                        return true;
                                    }
                                    if (count < offer) {
                                        count++;
                                        cursor.continue();
                                        return true;
                                    }
                                    count++;
                                    if (!key || cursor.value.id.toString().indexOf(key) > -1 ||
                                        cursor.value.order_id.toString().indexOf(key) > -1 ||
                                        cursor.value.note.toString().toLowerCase().indexOf(key) > -1 ||
                                        cursor.value.method.toString().toLowerCase().indexOf(key) > -1 ||
                                        cursor.value.in.toString().indexOf(key) > -1 || cursor.value.out.toString().indexOf(key) > -1) {
                                        limit--;
                                        result.push(cursor.value);
                                        break;
                                    }
                                    break;
                            }
                            cursor.continue();
                        }
                    }
                });
                if (((open_cursor === null && !result) || (result && typeof result['id'] === "undefined")) &&
                    (limit || (typeof result.at(-1)['next_page'] === "undefined" && limit !== false)) &&
                    viwebpos_data.data_prefix && viwebpos_data.data_prefix[type + '_current_page'] && viwebpos_data.data_prefix[type + '_total_page']) {
                    if (!open_cursor && type === 'customers') {
                        await new Promise(function (resolve) {
                            viwebpos_data.run_request({
                                type: 'post',
                                url: viwebpos.viwebpos_ajax_url.toString().replace('%%endpoint%%', 'viwebpos_customer_search_data'),
                                data: $.param({user_ids: [key], viwebpos_nonce: viwebpos.nonce}),
                                success: function (response) {
                                    let database = viwebpos_data.viwebposDB.result;
                                    if (response.status === 'success') {
                                        result = (response.data || {})[0];
                                        viwebpos_data.puts(database.transaction('customers', 'readwrite').objectStore('customers'), response.data || {});
                                    }
                                    resolve(result);
                                },
                                error: function (err) {
                                    resolve(result);
                                }
                            });
                        });
                    } else if (key && ['orders', 'products', 'customers'].includes(type)) {
                        await new Promise(function (resolve) {
                            let data = {
                                search: key,
                                per_page: limit,
                                viwebpos_nonce: viwebpos.nonce
                            };
                            switch (type) {
                                case 'products':
                                    if ($('.viwebpos-search-product-scanned').length) {
                                        data['search_barcode'] = 1;
                                    }
                                    break;
                            }
                            let action = 'viwebpos_' + type.replace(/s$/, '') + '_search_data';
                            viwebpos_data.run_request({
                                type: 'post',
                                url: viwebpos.viwebpos_ajax_url.toString().replace('%%endpoint%%', action),
                                data: $.param(data),
                                success: function (response) {
                                    let database = viwebpos_data.viwebposDB.result;
                                    let search = response.status === 'success' ? (response.data || {}) : {};
                                    if (search.length) {
                                        for (let SearchKey in search) {
                                            if (!result_ids.includes(search[SearchKey]['id'] || '')) {
                                                result.push(search[SearchKey]);
                                                result_ids.push(search[SearchKey]['id']);
                                            }
                                            viwebpos_data.put(database.transaction(type, 'readwrite').objectStore(type), search[SearchKey]);
                                        }
                                    }
                                    resolve(result);
                                },
                                error: function (err) {
                                    console.log(err)
                                    resolve(result);
                                }
                            });
                        });
                    } else {
                        await new Promise(function (resolve) {
                            let total_pages = viwebpos_data.data_prefix[type + '_total_page'], page = viwebpos_data.data_prefix[type + '_current_page'],
                                per_page = (type === 'products') ? 1000 : 500;
                            if (count < offer) {
                                count = offer;
                            }
                            viwebpos_data.add_request(temp(type, total_pages, page, per_page, cap, resolve));
                        });
                    }

                    function temp(type, total_pages, page, per_page, cap, resolve) {
                        let data = {
                            outlet: viwebpos_data.current_outlet || '',
                            limit: per_page,
                            page: page,
                            viwebpos_nonce: viwebpos.nonce
                        };
                        let data_t = $(document.body).triggerHandler('viwebpos_ajax_get_refresh_data', type, total_pages, page, per_page);
                        if (data_t) {
                            data = data_t;
                        }
                        return {
                            type: 'post',
                            url: viwebpos.viwebpos_ajax_url.toString().replace('%%endpoint%%', 'viwebpos_get_' + type + '_data'),
                            data: $.param(data),
                            success: function (response) {
                                let database = viwebpos_data.viwebposDB.result;
                                let temp_data = response.data || {};
                                viwebpos_data.puts(database.transaction(type, 'readwrite').objectStore(type), temp_data);
                                viwebpos_data.ajax.shift();
                                page = parseFloat(response.page || 0);
                                viwebpos_data.data_prefix[type + '_current_page'] = page;
                                if (total_pages < page + 1) {
                                    delete viwebpos_data.data_prefix[type + '_current_page'];
                                    delete viwebpos_data.data_prefix[type + '_total_page'];
                                }
                                for (let value of temp_data) {
                                    count++;
                                    result.push(value);
                                    if (limit) {
                                        limit--;
                                    }
                                }
                                if (!limit && limit !== false && viwebpos_data.data_prefix[type + '_current_page']) {
                                    result.push({next_page: Math.ceil(count / cap)});
                                }
                                viwebpos_data.put(database.transaction('settings', 'readwrite').objectStore('settings'), viwebpos_data.data_prefix, 'data_prefix');
                                resolve(result);
                            },
                            error: function (err) {
                                viwebpos_data.put(database.transaction('settings', 'readwrite').objectStore('settings'), viwebpos_data.data_prefix, 'data_prefix');
                                resolve(result);
                            }
                        };
                    }
                }
                return $(document.body).triggerHandler('viwebpos_get_data_' + type, [result, database, key, limit, page, index]) || result;
            };
            return datas();
        },
        search_data: function (type, key = '', limit = null, page = 1, network = null) {
            let self = this;
            if (!self.viwebposDB) {
                self.viwebposDB = indexedDB.open('viwebposDB');
            }
            let database = self.viwebposDB.result, limit_default = 10, table = type, index = null;
            switch (type) {
                case 'carts':
                    table = 'cart';
                    limit_default = 1;
                    index = key ? '' : {name: 'status', value: 'active'};
                    break;
                case 'products':
                    limit_default = 30;
                    break;
                case 'customer':
                    table = 'customers';
                    key = key ? parseFloat(key) : '';
                    index = '';
                    break;
                case 'customers':
                    break;
                case 'order':
                    table = 'orders';
                    index = '';
                    break;
                case 'orders':
                    limit_default = 15;
                    network = network ?? 'online_pos';
                    if (network !== 'all') {
                        index = {name: 'type', value: network};
                    }
                    break;
                case 'transactions':
                    limit_default = 15;
                    if (network) {
                        index = {name: 'type', value: network};
                    } else {
                        index = {name: 'cashier_id', value: parseInt(viwebpos.cashier_id)};
                    }
                    break;
            }
            if (limit === null) {
                limit = limit_default;
            }
            if (!key && type === 'carts' && limit && self.current_cart) {
                return self.current_cart;
            }
            return self.get_data(database.transaction(table, 'readonly').objectStore(table), key, limit, page, table, index);
        },
        add_new_transaction: function (transaction = {}) {
            if (!transaction || (!transaction.in && !transaction.out)) {
                $(document.body).trigger('villatheme_show_message', [viwebpos.transaction_add_price_empty, ['error', 'transaction-adding'], '', false, 4500]);
            }
            let self = this;
            if (!self.viwebposDB) {
                self.viwebposDB = indexedDB.open('viwebposDB');
            }
            let database = self.viwebposDB.result;
            transaction['outlet'] = '';
            self.run_request({
                type: 'post',
                url: viwebpos.viwebpos_ajax_url.toString().replace('%%endpoint%%', 'viwebpos_create_transaction'),
                data: $.param({
                    transaction: transaction,
                    viwebpos_nonce: viwebpos.nonce
                }),
                success: function (response) {
                    if (response.message) {
                        $(document.body).trigger('villatheme_show_message', [response.message, [response.status, 'transaction-adding'], '', false, 4500]);
                    }
                    if (response.status === 'success') {
                        self.data_prefix.transactions = response.data_prefix || '';
                        self.put(database.transaction('settings', 'readwrite').objectStore('settings'), self.data_prefix, 'data_prefix');
                        self.adds(database.transaction('transactions', 'readwrite').objectStore('transactions'), response.data);
                        $(document.body).trigger('viwebpos_transactions_refresh_html');
                    }
                },
                error: function (err) {
                    $(document.body).trigger('villatheme_show_message', [err.statusText, ['error', 'customer-adding'], err.responseText === '-1' ? '' : err.responseText, false, 4500]);
                }
            });
        },
        add_new_customer: function (customer = {}, current_customer = false) {
            if (!customer) {
                $(document.body).trigger('villatheme_show_message', [viwebpos.error_customer_name_empty, ['error', 'customer-adding'], '', false, 4500]);
                return false;
            }
            let self = this;
            if (!self.viwebposDB) {
                self.viwebposDB = indexedDB.open('viwebposDB');
            }
            let database = self.viwebposDB.result;
            let customers = database.transaction('customers', 'readonly').objectStore('customers');
            let check_customer = async function () {
                let customer_exit = '';
                await new Promise(function (resolve) {
                    if (customer.email) {
                        customers.index('email').get(customer.email).onsuccess = function (event) {
                            customer_exit = event.target.result;
                            resolve(customer_exit);
                        };
                    } else {
                        resolve(customer_exit);
                    }
                });
                if (!customer_exit && customer.email && self.data_prefix['customers_current_page'] && self.data_prefix['customers_total_page']) {
                    await new Promise(function (resolve) {
                        self.run_request({
                            type: 'post',
                            url: viwebpos.viwebpos_ajax_url.toString().replace('%%endpoint%%', 'viwebpos_customer_search_data'),
                            data: $.param({
                                user_ids: customer.email,
                                viwebpos_nonce: viwebpos.nonce
                            }),
                            success: function (response) {
                                let database = viwebpos_data.viwebposDB.result;
                                if (response.status === 'success') {
                                    customer_exit = (response.data || {})[0];
                                    viwebpos_data.puts(database.transaction('customers', 'readwrite').objectStore('customers'), response.data || {});
                                }
                                resolve(customer_exit);
                            },
                            error: function (err) {
                                resolve(customer_exit);
                            }
                        });
                    });
                }
                return customer_exit;
            };
            check_customer().then(function (customer_exit) {
                if (customer_exit) {
                    $(document.body).trigger('villatheme_show_message', [viwebpos.error_email_exists, ['error', 'customer-adding'], '', false, 4500]);
                    if (current_customer) {
                        $(document.body).trigger('viwebpos_set_cart_customer', [customer_exit]);
                    }
                    return false;
                } else if (current_customer === 'check_email') {
                    return false;
                }
                customer.viwebpos_nonce = viwebpos.nonce;
                self.run_request({
                    type: 'post',
                    url: viwebpos.viwebpos_ajax_url.toString().replace('%%endpoint%%', 'viwebpos_create_customer'),
                    data: $.param(customer),
                    success: function (response) {
                        if (response.message) {
                            if (typeof response.message === "object" && response.message.length) {
                                let message_errors = [];
                                $.each(response.message, function (k, v) {
                                    if (v) {
                                        message_errors.push({
                                            message: v,
                                            status: ['error', 'customer-adding']
                                        })
                                    }
                                });
                                $(document.body).trigger('villatheme_show_messages', [message_errors]);
                            } else {
                                $(document.body).trigger('villatheme_show_message', [response.message, [response.status, 'customer-adding'], '', false, 4500]);
                            }
                        }
                        if (response.status === 'success') {
                            self.data_prefix.customers = response.data_prefix || '';
                            self.put(database.transaction('settings', 'readwrite').objectStore('settings'), self.data_prefix, 'data_prefix');
                            self.adds(database.transaction('customers', 'readwrite').objectStore('customers'), response.data);
                            if (current_customer) {
                                $(document.body).trigger('viwebpos_set_cart_customer', [Object.values(response.data)[0]]);
                            } else {
                                $(document.body).trigger('viwebpos_refresh_customers');
                            }
                        }
                    },
                    error: function (err) {
                        $(document.body).trigger('villatheme_show_message', [err.statusText, ['error', 'customer-adding'], err.responseText === '-1' ? '' : err.responseText, false, 4500]);
                    }
                });
            });
        },
        create_new_order: function () {
            let self = this;
            let cart = self.current_cart;
            if (!cart || !Object.keys(cart.cart_contents).length) {
                $(document.body).trigger('villatheme_show_message', [viwebpos.checkout_cart_empty_message, ['error', 'place-order'], '', false, 4500]);
                return false;
            }
            if (parseFloat(cart.payments.change) < 0) {
                $(document.body).trigger('villatheme_show_message', [viwebpos.checkout_low_paid, ['error', 'place-order'], '', false, 4500]);
                return false;
            }
            if (!self.viwebposDB) {
                self.viwebposDB = indexedDB.open('viwebposDB');
            }
            let database = self.viwebposDB.result;
            if (!cart.payments.is_paid) {
                cart.payments.is_paid = '';
            }
            let data = {
                cart_items: Object.values(cart.cart_contents).sort(function (a, b) {
                    return a.item_serial - b.item_serial;
                }),
                cart_fees: cart.fees,
                customer: cart.customer,
                coupons: {
                    applied_coupons: cart.applied_coupons,
                    coupon_discount_tax_totals: cart.coupon_discount_tax_totals,
                    coupon_discount_totals: cart.coupon_discount_totals,
                },
                order_note: cart.order_note,
                payments: cart.payments,
                totals: cart.totals,
                currency: viwebpos.wc_currency,
            };
            $('.viwebpos-wrap').addClass('viwebpos-wrap-loading');
            $.each(data.cart_items, function (k, v) {
                if (typeof v.custom === "undefined" && v.data) {
                    delete v.data;
                }
            });
            let data_send = {viwebpos_nonce: viwebpos.nonce};
            $.each(data, function (k, v) {
                if (typeof v === 'object') {
                    data_send[k] = JSON.stringify(v).toString();
                } else {
                    data_send[k] = v;
                }
            });
            viwebpos_data.run_request({
                type: 'post',
                url: viwebpos.viwebpos_ajax_url.toString().replace('%%endpoint%%', 'viwebpos_create_order'),
                data: $.param(data_send),
                success: function (response) {
                    if (response.status === 'success') {
                        self.data_prefix = response.data_prefix || '';
                        self.put(database.transaction('settings', 'readwrite').objectStore('settings'), self.data_prefix, 'data_prefix');
                        self.puts(database.transaction('customers', 'readwrite').objectStore('customers'), response.customers);
                        self.puts(database.transaction('products', 'readwrite').objectStore('products'), response.products);
                        self.puts(database.transaction('coupons', 'readwrite').objectStore('coupons'), response.coupons);
                        self.adds(database.transaction('orders', 'readwrite').objectStore('orders'), response.orders);
                        self.adds(database.transaction('transactions', 'readwrite').objectStore('transactions'), response.transactions);
                        self.current_cart = $(document.body).triggerHandler('viwebpos_set_cart_after_created_order', self.set_cart_empty(cart)) || self.set_cart_empty(cart);
                        $(document.body).trigger('viwebpos_refresh_cart');
                        let order = Object.values(response.orders)[0];
                        if (self.auto_print_receipt) {
                            if (response.message) {
                                $(document.body).trigger('villatheme_show_message', [response.message, [response.status, 'place-order'], '', false, 4500]);
                            }
                            $(document.body).trigger('viwebpos_print_receipt', [order.id, order]);
                        } else {
                            $('.viwebpos-popup-wrap-print-receipt').find('.viwebpos-print-receipt-order').val(order.id).data('order_data', order);
                            $('.viwebpos-popup-wrap-print-receipt').find('.viwebpos-popup-content-value').html(response.message || '');
                            $('.viwebpos-popup-wrap-print-receipt').removeClass('viwebpos-popup-wrap-hidden').addClass('viwebpos-popup-wrap-show');
                            $('.viwebpos-popup-wrap-print-receipt').find('.viwebpos-popup-bt-loading').removeClass('viwebpos-popup-bt-loading');
                        }
                    } else {
                        $(document.body).trigger('villatheme_show_message', [response.message || response.status, [response.status, 'place-order'], '', false, 4500]);
                    }
                },
                error: function (err) {
                    console.log(err)
                    $(document.body).trigger('villatheme_show_message', [err.statusText, ['error', 'place-order'], err.responseText === '-1' ? '' : err.responseText, false, 4500]);
                }
            });
        },
        set_cart_empty: function (cart) {
            return wiwebpos_atc_obj.set_cart_empty(cart);
        },
        cart_data_validate: function (cart, reset_total, messages = []) {
            wiwebpos_atc_obj.cart_data_validate(cart, reset_total, messages);
        },
        cart_remove_coupon: function (coupon_code) {
            if (coupon_code && viwebpos_data.current_cart.applied_coupons && viwebpos_data.current_cart.applied_coupons[coupon_code]) {
                delete viwebpos_data.current_cart.applied_coupons[coupon_code];
                viwebpos_data.cart_calculate_totals([{
                    message: viwebpos_text.success_remove_coupon,
                    status: ['success', 'remove-coupon']
                }]);
            }
        },
        cart_add_discount: function (coupon_code) {
            if (!coupon_code) {
                $(document.body).trigger('villatheme_show_message', [viwebpos.coupon_please_enter, ['error', 'apply-coupon'], '', false, 4500]);
                return false;
            }
            coupon_code = coupon_code.trim();
            let self = this, coupon_code_enter = coupon_code;
            if (!self.viwebposDB) {
                self.viwebposDB = indexedDB.open('viwebposDB');
            }
            let database = self.viwebposDB.result;
            let coupons = database.transaction('coupons', 'readonly').objectStore('coupons');
            coupon_code = coupon_code.toString().toLowerCase().replace(/[\\"']/g, '\\$&').replace(/\u0000/g, '\\0');
            coupon_code = $(document.body).triggerHandler('viwebpos_wc_format_coupon_code', [coupon_code]) || coupon_code;
            coupons.get(coupon_code).onsuccess = function (e) {
                let coupon = e.target.result;
                if (!coupon) {
                    if (self.data_prefix['coupons_current_page'] && self.data_prefix['coupons_total_page']) {
                        self.run_request({
                            type: 'post',
                            url: viwebpos.viwebpos_ajax_url.toString().replace('%%endpoint%%', 'viwebpos_coupon_search_data'),
                            data: $.param({
                                ids: [coupon_code],
                                viwebpos_nonce: viwebpos.nonce
                            }),
                            success: function (response) {
                                viwebpos_data.puts(database.transaction('coupons', 'readwrite').objectStore('coupons'), response.data || {});
                                $(document.body).trigger('viwebpos_cart_add_discount', [coupon_code]);
                            },
                            error: function (err) {
                                $(document.body).trigger('villatheme_show_message',
                                    [viwebpos.coupon_not_exist.toString().replace('{coupon_code}', coupon_code_enter), ['error', 'apply-coupon'], '', false, 4500]);
                            }
                        });
                    } else {
                        $(document.body).trigger('villatheme_show_message',
                            [viwebpos.coupon_not_exist.toString().replace('{coupon_code}', coupon_code_enter), ['error', 'apply-coupon'], '', false, 4500]);
                    }
                    return false;
                }
                // Prevent adding coupons by post ID.
                if (coupon.coupon_code !== coupon_code) {
                    $(document.body).trigger('villatheme_show_message',
                        [viwebpos.coupon_not_exist.toString().replace('{coupon_code}', coupon_code_enter), ['error', 'apply-coupon'], '', false, 4500]);
                    return false;
                }
                // Check it can be used with cart.
                if (!self.is_coupon_valid(coupon, coupon_code_enter)) {
                    return false;
                }
                let check_emails = [], current_user_id = '';
                if (self.current_cart.customer) {
                    check_emails.push(self.current_cart.customer.email);
                    if (self.current_cart.customer.billing_address.email && self.current_cart.customer.billing_address.email !== self.current_cart.customer.email) {
                        check_emails.push(self.current_cart.customer.billing_address.email);
                    }
                    current_user_id = self.current_cart.customer.id;
                }
                if (!self.is_coupon_emails_allowed(check_emails, coupon.email || [])) {
                    $(document.body).trigger('villatheme_show_message',
                        [viwebpos_text.error_invalid_coupon_for_user.replace('%1$s', coupon_code), ['error', 'apply-coupon'], '', false, 4500]);
                    return false;
                }
                let usage_limit_per_user = parseInt(coupon.usage_limit_per_user);
                if (current_user_id && ($(document.body).triggerHandler('viwebpos_woocommerce_coupon_validate_user_usage_limit', [usage_limit_per_user]) || usage_limit_per_user) > 0) {
                    let orders = database.transaction('orders', 'readonly').objectStore('orders'), usage_count = 0;
                    orders.index('customer_id').openCursor(parseInt(current_user_id)).onsuccess = function (e) {
                        let cursor = e.target.result;
                        if (!cursor) {
                            if (usage_count >= usage_limit_per_user) {
                                $(document.body).trigger('villatheme_show_message', [viwebpos_text.error_coupon_usage_limit, ['error', 'apply-coupon'], '', false, 4500]);
                            } else {
                                $(document.body).trigger('viwebpos_wc_before_apply_coupon', [coupon, coupon_code]);
                            }
                            return false;
                        }
                        if (cursor.value.coupon_lines && cursor.value.coupon_lines[coupon.coupon_code]) {
                            usage_count++;
                        }
                        cursor.continue();
                    }
                } else {
                    $(document.body).trigger('viwebpos_wc_before_apply_coupon', [coupon, coupon_code]);
                }
            };
        },
        cart_before_apply_coupon: function (coupon, coupon_code) {
            let coupon_is_valid = true;
            if (!coupon_code || !coupon || !coupon.id || !coupon.coupon_code ||
                !($(document.body).triggerHandler('viwebpos_woocommerce_coupon_validate_user_usage_limit', [coupon]) || coupon_is_valid)) {
                $(document.body).trigger('villatheme_show_message', [viwebpos_text.error_invalid_coupon, ['error', 'apply-coupon'], '', false, 4500]);
                return false;
            }
            let self = this;
            let cart = self.current_cart;
            if (!cart.applied_coupons) {
                cart.applied_coupons = {};
            }
            if (cart.applied_coupons[coupon_code]) {
                $(document.body).trigger('villatheme_show_message', [viwebpos_text.error_applied_coupon, ['error', 'apply-coupon'], '', false, 4500]);
                return false;
            }
            // If its individual use then remove other coupons.
            if (coupon.individual_use) {
                let coupons_to_keep = $(document.body).triggerHandler('viwebpos_woocommerce_apply_individual_use_coupon', coupon, cart.applied_coupons) || {},
                    applied_coupons = {};
                $.each(cart.applied_coupons, function (k, v) {
                    if (coupons_to_keep[k]) {
                        applied_coupons[k] = v;
                        delete coupons_to_keep[k];
                    }
                });
                cart.applied_coupons = applied_coupons;
                $.each(coupons_to_keep, function (k, v) {
                    cart.applied_coupons[k] = v;
                });
            }
            // Check to see if an individual use coupon is set.
            let valid = true;
            $.each(cart.applied_coupons, function (k, v) {
                if (v.individual_use && !($(document.body).triggerHandler('viwebpos_woocommerce_apply_with_individual_use_coupon', [coupon, v, cart.applied_coupons]) || false)) {
                    $(document.body).trigger('villatheme_show_message',
                        [viwebpos_text.error_individual_use_coupon.replace('%1$s', v.coupon_code), ['error', 'apply-coupon'], '', false, 4500]);
                    valid = false;
                    return false;
                }
            });
            let coupon_maximum_applied = parseInt(viwebpos.coupon_maximum_applied);
            if (coupon_maximum_applied && Object.keys(cart.applied_coupons).length === coupon_maximum_applied) {
                $(document.body).trigger('villatheme_show_message',
                    [viwebpos_text.error_coupon_maximum_applied.replace('%1$s', coupon_maximum_applied), ['error', 'apply-coupon'], '', false, 4500]);
                return false;
            }
            if (valid) {
                cart.applied_coupons[coupon_code] = coupon;
                self.current_cart = cart;
                $(document.body).trigger('viwebpos_woocommerce_applied_coupon', [coupon_code]);
            }
        },
        coupon_get_discount_amount: function (coupon, discounting_amount, cart_item = null, single = false) {
            if (!coupon) {
                return 0;
            }
            let discount = 0,
                cart_item_qty = !cart_item ? 1 : cart_item.quantity,
                coupon_amount = parseFloat(coupon.amount),
                coupon_type = coupon.type;
            if (coupon_type === 'percent') {
                discount = coupon_amount * discounting_amount / 100;
            } else if (coupon_type === 'fixed_cart' && cart_item && viwebpos_data.current_cart.totals['subtotal']) {
                let discount_percent = 0;
                if (viwebpos_price.product_price_includes_tax) {
                    discount_percent = (viwebpos_get_product_price_including_tax(cart_item.data) * cart_item_qty) / (viwebpos_data.current_cart.totals['subtotal'] + viwebpos_data.current_cart.totals['subtotal_tax']);
                } else {
                    discount_percent = (viwebpos_get_product_price_excluding_tax(cart_item.data) * cart_item_qty) / (viwebpos_data.current_cart.totals['subtotal']);
                }
                discount = coupon_amount * discount_percent / cart_item_qty;
            } else if (coupon_type === 'fixed_product') {
                discount = Math.min(coupon_amount, discounting_amount);
                discount = single ? discount : discount * cart_item_qty;
            }
            discount = viwebpos_round(Math.min(discount, discounting_amount), viwebpos_price.wc_get_rounding_precision);
            return $(document.body).triggerHandler('viwebpos_woocommerce_coupon_get_discount_amount', [discount, discounting_amount, cart_item, single, coupon]) || discount;
        },
        cart_calculate_totals: function (messages = [], refresh = true) {
            wiwebpos_atc_obj.cart_calculate_totals(messages, refresh);
        },
        cart_set_payments: function (paid) {
            let self = this;
            let cart_payments = self.set_cart_empty({}).payments;
            cart_payments.is_paid = Object.keys(paid)[0];
            cart_payments.paid = paid;
            self.current_cart.payments = cart_payments;
            self.cart_refresh_payments();
            $(document.body).trigger('viwebpos_refresh_cart', ['checkout']);
        },
        cart_refresh_payments: function () {
            let self = this;
            let cart = self.current_cart, cart_payments;
            if (typeof cart.payments === 'object' && cart.payments.is_paid) {
                cart_payments = cart.payments;
                cart_payments.total_paid = viwebpos_round(Object.values(cart_payments.paid).reduce((a, b) => parseFloat(a) + parseFloat(b)));
                cart_payments.is_paid_title = cart.payments.is_paid !== 'multi' ? (viwebpos.viwebpos_payments ? viwebpos.viwebpos_payments[cart.payments.is_paid]['title'] : 'Cash') : '';
            } else {
                cart_payments = self.set_cart_empty({}).payments;
                cart_payments.total_paid = cart.totals.total;
                cart_payments.paid = {
                    cash: cart_payments.total_paid
                };
                cart_payments.is_paid_title = viwebpos.viwebpos_payments ? viwebpos.viwebpos_payments['cash']['title'] : 'Cash';
            }
            cart_payments.change = viwebpos_round(cart_payments.total_paid - cart.totals.total);
            cart['payments'] = cart_payments;
            self.current_cart = cart;
        },
        refresh_cart: function (refresh = true) {
            let self = this;
            if (!self.viwebposDB) {
                self.viwebposDB = indexedDB.open('viwebposDB');
            }
            let database = self.viwebposDB.result;
            self.put(database.transaction('cart', 'readwrite').objectStore('cart'), self.current_cart);
            if (refresh) {
                $(document.body).trigger('viwebpos-bill-of-sale-refresh-bill-tabs');
                $(document.body).trigger('viwebpos-bill-of-sale-get-html', [self.current_cart, refresh === 'checkout']);
            }
        },
        is_coupon_emails_allowed: function (check_emails = [], restrictions = []) {
            if (!restrictions.length) {
                return true;
            }
            if (!check_emails.length) {
                return false;
            }
            let valid = false;
            $.each(check_emails, function (k, email) {
                if (restrictions.indexOf(email) > -1) {
                    valid = true;
                    return false;
                }
                $.each(restrictions, function (k1, restriction) {
                    let regex = new RegExp('^' + restriction.replace('*', '(.+)?') + '$');
                    if (email.match(regex)) {
                        valid = true;
                        return false;
                    }
                });
                if (valid) {
                    return false;
                }
            });
            return valid;
        },
        is_coupon_valid: function (coupon, coupon_code_enter, show_message = true, cart = null) {
            if (!coupon || (!coupon.id && coupon.virtual)) {
                if (show_message) {
                    $(document.body).trigger('villatheme_show_message',
                        [viwebpos.coupon_not_exist.toString().replace('{coupon_code}', coupon_code_enter), ['error', 'apply-coupon'], '', false, 4500]);
                }
                return false;
            }
            if (!viwebpos_data.validate_coupon_usage_limit(coupon, show_message, cart)) {
                return false;
            }
            if (coupon.expiry_date && ($(document.body).triggerHandler('viwebpos_woocommerce_coupon_validate_expiry_date', coupon) || Math.floor(new Date().getTime() / 1000) > parseInt(coupon.expiry_date))) {
                if (show_message) {
                    $(document.body).trigger('villatheme_show_message', [viwebpos_text.error_coupon_expired, ['error', 'apply-coupon'], '', false, 4500]);
                }
                return false;
            }
            cart = cart ? cart : viwebpos_data.current_cart;
            let cart_subtotal = cart.totals ? parseFloat(cart.totals.subtotal) : 0;
            if (coupon.minimum_amount && parseFloat(coupon.minimum_amount) > cart_subtotal) {
                if (show_message) {
                    $(document.body).trigger('villatheme_show_message',
                        [viwebpos_text.error_coupon_minimum.replace('%1$s', viwebpos_get_price_html(coupon.minimum_amount)), ['error', 'apply-coupon'], '', false, 4500]);
                }
                return false;
            }
            if (coupon.maximum_amount && parseFloat(coupon.maximum_amount) < cart_subtotal) {
                if (show_message) {
                    $(document.body).trigger('villatheme_show_message',
                        [viwebpos_text.error_coupon_maximum.replace('%1$s', viwebpos_get_price_html(coupon.maximum_amount)), ['error', 'apply-coupon'], '', false, 4500]);
                }
                return false;
            }
            let valid = false;
            if (coupon.product_ids && coupon.product_ids.length) {
                $.each(cart.cart_contents, function (k, v) {
                    if ($.inArray(v.product_id, coupon.product_ids) > -1 || (v.variation_id && $.inArray(v.variation_id, coupon.product_ids) > -1)) {
                        valid = true;
                        return false;
                    }
                });
                if (!valid) {
                    if (show_message) {
                        $(document.body).trigger('villatheme_show_message',
                            [viwebpos_text.error_coupon_not_applicable1, ['error', 'apply-coupon'], '', false, 4500]);
                    }
                    return false;
                }
            }
            if (coupon.product_categories && coupon.product_categories.length) {
                valid = false;
                $.each(cart.cart_contents, function (k, v) {
                    if (coupon.exclude_sale_items && v.data.is_on_sale) {
                        return true;
                    }
                    let cats_intersect = $.map(v.data.product_cats, function (a) {
                        return $.inArray(a, coupon.product_categories) > -1 ? a : null;
                    });
                    if (cats_intersect.length) {
                        valid = true;
                        return false;
                    }
                });
                if (!valid) {
                    if (show_message) {
                        $(document.body).trigger('villatheme_show_message',
                            [viwebpos_text.error_coupon_not_applicable1, ['error', 'apply-coupon'], '', false, 4500]);
                    }
                    return false;
                }
            }
            if ($.inArray(coupon.type, viwebpos.wc_product_coupon_types) > -1) {
                valid = false;
                $.each(cart.cart_contents, function (k, v) {
                    if (viwebpos_data.coupon_is_valid_for_product(v.data, v, coupon)) {
                        valid = true;
                        return false;
                    }
                });
                if (!valid) {
                    if (show_message) {
                        $(document.body).trigger('villatheme_show_message',
                            [viwebpos_text.error_coupon_not_applicable1, ['error', 'apply-coupon'], '', false, 4500]);
                    }
                    return false;
                }
            } else {
                if (coupon.exclude_sale_items) {
                    valid = true;
                    $.each(cart.cart_contents, function (k, v) {
                        if (v.data.is_on_sale) {
                            valid = false;
                            return false;
                        }
                    });
                    if (!valid) {
                        if (show_message) {
                            $(document.body).trigger('villatheme_show_message',
                                [viwebpos_text.error_invalid_coupon_for_sale, ['error', 'apply-coupon'], '', false, 4500]);
                        }
                        return false;
                    }
                }
                if (coupon.exclude_product_ids && coupon.exclude_product_ids.length) {
                    let $products = [];
                    $.each(cart.cart_contents, function (k, v) {
                        if ($.inArray(v.product_id, coupon.exclude_product_ids) > -1 || (v.variation_id && $.inArray(v.variation_id, coupon.exclude_product_ids) > -1)) {
                            $products.push(v.data.name);
                        }
                    });
                    if ($products.length) {
                        if (show_message) {
                            $(document.body).trigger('villatheme_show_message',
                                [viwebpos_text.error_coupon_not_applicable2.replace('%1$s', $products.join(', ')), ['error', 'apply-coupon'], '', false, 4500]);
                        }
                        return false;
                    }
                }
                if (coupon.exclude_product_categories && coupon.exclude_product_categories.length) {
                    let $categories = {};
                    $.each(cart.cart_contents, function (k, v) {
                        let cats_intersect = $.map(v.data.product_cats, function (a) {
                            return $.inArray(a, coupon.exclude_product_categories) > -1 ? a : null;
                        });
                        $.each(cats_intersect, function (k, v) {
                            if (!$categories[v]) {
                                $categories[v] = coupon.exclude_product_categories_name[v] ? coupon.exclude_product_categories_name[v] : v;
                            }
                        })
                    });
                    if (Object.values($categories).length) {
                        if (show_message) {
                            $(document.body).trigger('villatheme_show_message',
                                [viwebpos_text.error_coupon_not_applicable3.replace('%1$s', Object.values($categories).join(', ')), ['error', 'apply-coupon'], '', false, 4500]);
                        }
                        return false;
                    }
                }
            }
            return true;
        },
        validate_coupon_usage_limit: function (coupon, show_message = true, cart = null) {
            if (!coupon) {
                if (show_message) {
                    $(document.body).trigger('villatheme_show_message',
                        [viwebpos.coupon_not_exist.toString().replace('{coupon_code}', ''), ['error', 'apply-coupon'], '', false, 4500]);
                }
                return false;
            }
            let usage_limit = parseInt(coupon.usage_limit);
            if (!usage_limit) {
                return true;
            }
            let usage_count = parseInt(coupon.usage_count),
                tentative_usage_count = parseInt(coupon.tentative_usage_count);
            cart = cart ? cart : viwebpos_data.current_cart;
            if (cart.order_data && cart.order_data.coupon_lines && cart.order_data.coupon_lines[coupon.coupon_code]) {
                usage_count -= 1;
            }
            if ((usage_count + tentative_usage_count) < usage_limit) {
                return true;
            }
            if (show_message) {
                $(document.body).trigger('villatheme_show_message', [viwebpos_text.error_coupon_usage_limit, ['error', 'apply-coupon'], '', false, 4500]);
            }
            return false;
        },
        coupon_is_valid_for_product: function (product, cart_item, coupon) {
            let valid = false;
            if (!product || !coupon || $.inArray(coupon.type, viwebpos.wc_product_coupon_types) < 0) {
                return $(document.body).triggerHandler('viwebpos_woocommerce_coupon_is_valid_for_product', [product, cart_item, coupon]) || valid;
            }
            if ((!coupon.product_ids || !coupon.product_ids.length) && (!coupon.product_categories || !coupon.product_categories.length)) {
                valid = true;
            } else {
                if (coupon.product_ids && coupon.product_ids.length &&
                    ($.inArray(cart_item.product_id, coupon.product_ids) > -1 || (cart_item.variation_id && $.inArray(cart_item.variation_id, coupon.product_ids) > -1))) {
                    valid = true;
                }
                if (coupon.product_categories && coupon.product_categories.length) {
                    let cats_intersect = $.map(product.product_cats, function (a) {
                        return $.inArray(a, coupon.product_categories) > -1 ? a : null;
                    });
                    if (cats_intersect.length) {
                        valid = true;
                    }
                }
            }
            if (coupon.exclude_product_ids && coupon.exclude_product_ids.length &&
                ($.inArray(cart_item.product_id, coupon.exclude_product_ids) > -1 || (cart_item.variation_id && $.inArray(cart_item.variation_id, coupon.exclude_product_ids) > -1))) {
                valid = false;
            }
            if (coupon.exclude_product_categories && coupon.exclude_product_categories.length) {
                let cats_intersect = $.map(product.product_cats, function (a) {
                    return $.inArray(a, coupon.exclude_product_categories) > -1 ? a : null;
                });
                if (cats_intersect.length) {
                    valid = false;
                }
            }
            if (coupon.exclude_sale_items && product.is_on_sale) {
                valid = false;
            }
            return $(document.body).triggerHandler('viwebpos_woocommerce_coupon_is_valid_for_product', [product, cart_item, coupon]) || valid;
        },
        settings_pos: function () {
            let self = this;
            if (!self.viwebposDB) {
                self.viwebposDB = indexedDB.open('viwebposDB');
            }
            let database = self.viwebposDB.result;
            let new_settings = {},
                default_settings = {...self.default_settings_bill};
            let wrap = $('.viwebpos-popup-wrap-settings-pos');
            if (!wrap.hasClass('viwebpos-popup-wrap-settings-pos-init')) {
                let settings = database.transaction('settings', 'readonly').objectStore('settings');
                if (!settings) {
                    return;
                }
                let settings_pos_init = async function () {
                    let settings_pos;
                    await new Promise(function (resolve) {
                        let settings = database.transaction('settings', 'readwrite').objectStore('settings');
                        settings.get('settings_pos').onsuccess = function (e) {
                            settings_pos = e.target.result;
                            if (!settings_pos || !Object.keys(settings_pos).length) {
                                settings_pos = {...default_settings};
                            }
                            let field_check_count = Object.keys(default_settings).length;
                            wrap.find('input[type="checkbox"], select').each(function (k, v) {
                                if ($(v).is('input')) {
                                    field_check_count--;
                                    let name = $(v).data('name');
                                    if (typeof settings_pos[name] === 'undefined') {
                                        settings_pos[name] = default_settings[name] || '';
                                    }
                                    if (!wrap.find('input#viwebpos-settings-pos-' + name).length) {
                                        wrap.append(`<input type="hidden" name="${name}" id="viwebpos-settings-pos-${name}" value="">`)
                                    }
                                    let enable = settings_pos[name];
                                    if (enable) {
                                        wrap.find('input#viwebpos-settings-pos-' + name).val(1);
                                        $(v).prop('checked', true);
                                    } else {
                                        wrap.find('input#viwebpos-settings-pos-' + name).val('');
                                        $(v).removeAttr('checked');
                                    }
                                    if (!field_check_count) {
                                        self.data_settings_bill = settings_pos;
                                        resolve(settings_pos);
                                    }
                                } else if ($(v).closest('.viwebpos-popup-setting-multi').length) {
                                    let selected = [];
                                    $(v).find('option').each(function (k1, v1) {
                                        field_check_count--;
                                        let name = $(v1).val();
                                        if (typeof settings_pos[name] === 'undefined') {
                                            settings_pos[name] = default_settings[name] || '';
                                        }
                                        if (!wrap.find('input#viwebpos-settings-pos-' + name).length) {
                                            wrap.append(`<input type="hidden" name="${name}" id="viwebpos-settings-pos-${name}" value="">`)
                                        }
                                        if (settings_pos[name]) {
                                            wrap.find('input#viwebpos-settings-pos-' + name).val(1);
                                        } else {
                                            selected.push(name)
                                            wrap.find('input#viwebpos-settings-pos-' + name).val('');
                                        }
                                        if (!field_check_count) {
                                            self.data_settings_bill = settings_pos;
                                            $(v).val(selected).trigger('change');
                                            resolve(settings_pos);
                                        }
                                    });
                                    $(v).val(selected).trigger('change');
                                } else {
                                    field_check_count--;
                                    let name = $(v).data('name');
                                    if (typeof settings_pos[name] === 'undefined') {
                                        settings_pos[name] = default_settings[name] || '';
                                    }
                                    if (!wrap.find('input#viwebpos-settings-pos-' + name).length) {
                                        wrap.append(`<input type="hidden" name="${name}" id="viwebpos-settings-pos-${name}" value="">`)
                                    }
                                    wrap.find('input#viwebpos-settings-pos-' + name).val(settings_pos[name]);
                                    $(this).val(settings_pos[name]).trigger('change');
                                    if (!field_check_count) {
                                        self.data_settings_bill = settings_pos;
                                        resolve(settings_pos);
                                    }
                                }
                            });
                        };
                    });
                };
                settings_pos_init().then(function () {
                    wrap.addClass('viwebpos-popup-wrap-settings-pos-init');
                    $(document.body).trigger('viwebpos_settings_pos');
                });
                return;
            }
            let fields = {
                checkout_subtotal: '.viwebpos-checkout-form-content-subtotal',
                checkout_tax: '.viwebpos-checkout-form-content-tax',
                cart_item_number: '.viwebpos-cart-item-serial',
                cart_item_price: '.viwebpos-cart-item-price',
                cart_item_subtotal: '.viwebpos-cart-item-subtotal',
                suggested_amount: '.viwebpos-checkout-form-content-amount-choose',
            };
            wrap.find('input[type="hidden"]').each(function (k, v) {
                let name = $(v).attr('name');
                new_settings[name] = $(v).val();
                if (fields[name]) {
                    if (new_settings[name]) {
                        $(fields[name]).removeClass('viwebpos-disabled');
                    } else {
                        $(fields[name]).addClass('viwebpos-disabled');
                    }
                }
            });
            self.data_settings_bill = new_settings;
            self.put(database.transaction('settings', 'readwrite').objectStore('settings'), new_settings, 'settings_pos');
        },
    };
    window.viwebpos_data = viwebpos_data;
    window.viwebpos_data.init();
});