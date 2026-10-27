/**
 * MartPro Advanced POS Terminal Client Engine
 */

$(document).ready(function() {
    let cart = [];
    let currentCustomer = null;
    let billDiscount = 0.0;
    let registerId = 1;

    const $barcodeInput = $('#pos-barcode-input');
    const $cartContainer = $('#cart-container');
    const $cartItemsList = $('#cart-items-list');
    const $cartEmptyMsg = $('#cart-empty-message');

    function isWalkinCustomer(cust) {
        if (!cust || !cust.id || cust.id == 1) return true;
        const name = (cust.name || '').toLowerCase();
        return name.includes('walk-in') || name.includes('walk in') || name.includes('guest');
    }

    function syncCustomerCreditState() {
        const isWalkin = isWalkinCustomer(currentCustomer);
        const $creditInput = $('#tender-credit');
        const $hint = $('#credit-limit-hint');
        const $resetBtn = $('#btn-reset-customer');

        if (isWalkin) {
            $creditInput.prop('disabled', true).val('').attr('placeholder', 'Disabled for Walk-in');
            $hint.html('<span class="text-danger small"><i class="bi bi-shield-slash me-1"></i>Credit not allowed for Walk-in Customer. Select customer (F6).</span>');
            $resetBtn.addClass('d-none');
        } else {
            const limit = parseFloat(currentCustomer.credit_limit || 0);
            const balance = parseFloat(currentCustomer.current_balance || 0);
            const avail = Math.max(0, limit - balance);
            $creditInput.prop('disabled', false).attr('placeholder', '0.00');
            if (limit <= 0) {
                $hint.html(`<span class="text-danger small"><i class="bi bi-exclamation-octagon me-1"></i>Customer has no approved credit facility (Limit: ${fmt(0)}).</span>`);
            } else {
                $hint.html(`<span class="text-muted small">Available Khata: <strong class="text-success">${fmt(avail)}</strong> (Limit: ${fmt(limit)})</span>`);
            }
            $resetBtn.removeClass('d-none');
        }
    }

    // Customer Secondary Display Channel
    const displayChannel = window.BroadcastChannel ? new BroadcastChannel('martpro_pos_display') : null;
    function syncCustomerDisplay() {
        if (!displayChannel) return;
        const lastItem = cart.length ? cart[cart.length - 1] : null;
        displayChannel.postMessage({
            item: lastItem ? { name: lastItem.name, qty: lastItem.qty, price: (lastItem.custom_price !== null ? lastItem.custom_price : lastItem.price) } : null,
            grandTotal: getGrandTotal()
        });
    }

    // Auto-focus barcode input
    $barcodeInput.focus();

    function refocusBarcode() {
        setTimeout(() => {
            if (!$('.modal.show').length) {
                $barcodeInput.focus();
            }
        }, 150);
    }

    // Format currency helper
    function fmt(num) {
        return '$ ' + parseFloat(num || 0).toFixed(2);
    }

    // 1. Barcode Scanning Handler
    $barcodeInput.on('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const rawVal = $.trim($(this).val());
            if (!rawVal) return;

            $(this).val('');
            $('#scan-feedback').text('Looking up...');

            MartPro.ajax(MartPro.baseUrl + '/modules/pos/ajax/barcode_lookup.php', {
                data: { barcode: rawVal },
                silentError: true
            }).then(function(res) {
                $('#scan-feedback').text('Added: ' + res.data.name).removeClass('text-danger').addClass('text-success');
                setTimeout(() => $('#scan-feedback').text(''), 2000);
                addItemToCart(res.data);
            }).catch(function(err) {
                const msg = (err && err.responseJSON && err.responseJSON.message) || (err && err.message) || 'Product not found!';
                MartPro.notify('error', msg);
                $('#scan-feedback').text(msg).removeClass('text-success').addClass('text-danger');
                setTimeout(() => $('#scan-feedback').text(''), 3000);
            }).finally(refocusBarcode);
        }
    });

    // 2. Add Item to Cart
    function addItemToCart(prod) {
        const existingIndex = cart.findIndex(i => i.id === prod.id);
        const addQty = parseFloat(prod.quantity || 1);

        if (existingIndex > -1) {
            cart[existingIndex].qty += addQty;
        } else {
            cart.push({
                id: prod.id,
                name: prod.name,
                sku: prod.sku,
                barcode: prod.barcode,
                unit: prod.unit,
                allow_decimal: prod.allow_decimal,
                price: parseFloat(prod.selling_price),
                custom_price: null,
                tax_rate: parseFloat(prod.tax_rate || 0),
                discount: 0.0,
                qty: addQty,
                stock: parseFloat(prod.stock || 0)
            });
        }
        renderCart();
    }

    // 3. Render Cart DOM & Recalculate
    function renderCart() {
        if (cart.length === 0) {
            $cartEmptyMsg.removeClass('d-none');
            $cartItemsList.addClass('d-none').empty();
            updateTotals(0, 0, 0, 0);
            return;
        }

        $cartEmptyMsg.addClass('d-none');
        $cartItemsList.removeClass('d-none').empty();

        let subtotal = 0;
        let itemDiscountTotal = 0;
        let taxTotal = 0;

        cart.forEach((item, idx) => {
            const unitPrice = item.custom_price !== null ? item.custom_price : item.price;
            const lineSub = unitPrice * item.qty;
            const lineDisc = item.discount || 0;
            const lineTax = (lineSub - lineDisc) * (item.tax_rate / 100);
            const lineTotal = lineSub - lineDisc + lineTax;

            subtotal += lineSub;
            itemDiscountTotal += lineDisc;
            taxTotal += lineTax;

            const rowHtml = `
                <div class="cart-item-row" data-index="${idx}">
                    <div style="flex: 2; min-width: 0;" class="pe-2">
                        <div class="cart-item-title text-truncate">${item.name}</div>
                        <div class="cart-item-meta">
                            <span class="me-2">SKU: ${item.sku}</span>
                            <span class="me-2 text-mp-primary fw-bold">${fmt(unitPrice)}</span>
                            <span class="badge bg-light text-muted border">${item.unit}</span>
                            ${item.discount > 0 ? `<span class="badge bg-danger-subtle text-danger ms-1">-${fmt(item.discount)}</span>` : ''}
                        </div>
                    </div>
                    <div class="qty-stepper me-3">
                        <button type="button" class="btn btn-sm btn-outline-secondary btn-qty-dec" data-index="${idx}">-</button>
                        <input type="number" step="${item.allow_decimal ? '0.01' : '1'}" class="form-control form-control-sm qty-input input-item-qty" data-index="${idx}" value="${item.qty}">
                        <button type="button" class="btn btn-sm btn-outline-secondary btn-qty-inc" data-index="${idx}">+</button>
                    </div>
                    <div class="text-end pe-2" style="width: 85px;">
                        <div class="fw-bold text-navy" style="font-size: 0.95rem;">${fmt(lineTotal)}</div>
                    </div>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-danger p-1 btn-remove-item" data-index="${idx}" title="Remove item">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
            `;
            $cartItemsList.append(rowHtml);
        });

        updateTotals(subtotal, itemDiscountTotal, billDiscount, taxTotal);
    }

    // 4. Update Summary Totals
    function updateTotals(subtotal, itemDisc, billDisc, tax) {
        const grandTotal = Math.max(0, subtotal - itemDisc - billDisc + tax);

        $('#summary-subtotal').text(fmt(subtotal));
        $('#summary-item-discount').text('-' + fmt(itemDisc));
        $('#summary-bill-discount').text('-' + fmt(billDisc));
        $('#summary-tax').text('+' + fmt(tax));
        $('#display-grand-total').text(fmt(grandTotal));
        $('#modal-due-amount').text(fmt(grandTotal));

        // Update live change calculation in payment modal
        calcModalChange();
        syncCustomerDisplay();
    }

    function getGrandTotal() {
        let subtotal = 0, itemDisc = 0, taxTotal = 0;
        cart.forEach(item => {
            const up = item.custom_price !== null ? item.custom_price : item.price;
            const ls = up * item.qty;
            const ld = item.discount || 0;
            subtotal += ls;
            itemDisc += ld;
            taxTotal += (ls - ld) * (item.tax_rate / 100);
        });
        return Math.max(0, subtotal - itemDisc - billDiscount + taxTotal);
    }

    // 5. Quantity & Item Actions
    $(document).on('click', '.btn-qty-inc', function() {
        const idx = $(this).data('index');
        cart[idx].qty += 1;
        renderCart();
    });

    $(document).on('click', '.btn-qty-dec', function() {
        const idx = $(this).data('index');
        if (cart[idx].qty > 1) {
            cart[idx].qty -= 1;
        } else {
            cart.splice(idx, 1);
        }
        renderCart();
    });

    $(document).on('change', '.input-item-qty', function() {
        const idx = $(this).data('index');
        const val = parseFloat($(this).val());
        if (val > 0) {
            cart[idx].qty = val;
        } else {
            cart.splice(idx, 1);
        }
        renderCart();
    });

    $(document).on('click', '.btn-remove-item', function() {
        const idx = $(this).data('index');
        cart.splice(idx, 1);
        renderCart();
        refocusBarcode();
    });

    // Quick touch tiles
    $('.quick-item-btn').on('click', function() {
        const bc = $(this).data('barcode');
        $barcodeInput.val(bc);
        const e = $.Event('keydown');
        e.key = 'Enter';
        $barcodeInput.trigger(e);
    });

    // 6. Bill Discount Edit
    $('#btn-edit-bill-discount').on('click', function() {
        const current = billDiscount;
        const input = prompt('Enter bill discount amount ($):', current);
        if (input !== null && !isNaN(parseFloat(input))) {
            billDiscount = Math.max(0, parseFloat(input));
            renderCart();
        }
    });

    // 7. Clear Cart
    $('#btn-clear-cart').on('click', function() {
        if (cart.length === 0) return;
        if (confirm('Are you sure you want to cancel and clear the active cart?')) {
            cart = [];
            billDiscount = 0;
            renderCart();
            refocusBarcode();
        }
    });

    // 8. Customer Selection Modal & Live Search
    $('#customerSelectModal').on('shown.bs.modal', function() {
        loadCustomerList('');
        $('#cust-search-input').focus();
    });

    $('#cust-search-input').on('input', function() {
        loadCustomerList($.trim($(this).val()));
    });

    function loadCustomerList(term) {
        MartPro.ajax(MartPro.baseUrl + '/modules/pos/ajax/customer_search.php?term=' + encodeURIComponent(term), {
            method: 'GET'
        }).then(function(res) {
            const $list = $('#cust-search-results').empty();
            if (!res.data || res.data.length === 0) {
                $list.append('<div class="p-3 text-muted text-center small">No matching customers found.</div>');
                return;
            }
            res.data.forEach(c => {
                const isWalkin = (c.id == 1 || (c.name || '').toLowerCase().includes('walk-in') || (c.name || '').toLowerCase().includes('guest'));
                const itemHtml = `
                    <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center select-cust-item"
                            data-id="${c.id}" data-name="${c.name}" data-phone="${c.phone}" data-tier="${c.membership_tier}"
                            data-limit="${c.credit_limit}" data-bal="${c.current_balance}" data-pts="${c.loyalty_points}">
                        <div>
                            <div class="fw-bold text-navy">${c.name} ${isWalkin ? '<span class="badge bg-secondary ms-1">Walk-in (No Credit)</span>' : ''}</div>
                            <small class="text-muted"><i class="bi bi-telephone me-1"></i>${c.phone} | Tier: <span class="badge bg-secondary">${c.membership_tier}</span></small>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-info-subtle text-info">${c.loyalty_points} Pts</span>
                            <div class="small ${isWalkin ? 'text-muted' : 'text-danger'}">${isWalkin ? 'Credit Disabled' : 'Khata: ' + fmt(c.current_balance)}</div>
                        </div>
                    </button>
                `;
                $list.append(itemHtml);
            });
        });
    }

    $(document).on('click', '.select-cust-item', function() {
        const custId = parseInt($(this).data('id'), 10);
        if (custId === 1) {
            currentCustomer = null;
            $('#pos-customer-name').text('Walk-in Retail Customer');
            $('#pos-customer-details').text('Guest Checkout');
        } else {
            currentCustomer = {
                id: custId,
                name: $(this).data('name'),
                phone: $(this).data('phone'),
                tier: $(this).data('tier') || 'standard',
                credit_limit: parseFloat($(this).data('limit') || 0),
                current_balance: parseFloat($(this).data('bal') || 0),
                loyalty_points: parseInt($(this).data('pts') || 0)
            };
            $('#pos-customer-name').text(currentCustomer.name);
            $('#pos-customer-details').text(`Phone: ${currentCustomer.phone} | Tier: ${currentCustomer.tier.toUpperCase()} | Points: ${currentCustomer.loyalty_points}`);
        }
        syncCustomerCreditState();
        $('#customerSelectModal').modal('hide');
        refocusBarcode();
    });

    // Reset customer back to Walk-in
    $('#btn-reset-customer').on('click', function() {
        currentCustomer = null;
        $('#pos-customer-name').text('Walk-in Retail Customer');
        $('#pos-customer-details').text('Guest Checkout');
        syncCustomerCreditState();
        MartPro.notify('info', 'Customer reset to Walk-in Retail Customer.');
    });

    // Quick Add Customer Form
    $('#form-quick-add-customer').on('submit', function(e) {
        e.preventDefault();
        const data = $(this).serialize();
        MartPro.ajax(MartPro.baseUrl + '/modules/pos/ajax/save_customer.php', {
            data: data,
            button: $(this).find('button[type="submit"]')
        }).then(function(res) {
            MartPro.notify('success', 'Customer registered and selected!');
            currentCustomer = res.data;
            $('#pos-customer-name').text(currentCustomer.name);
            $('#pos-customer-details').text(`Phone: ${currentCustomer.phone} | Tier: ${(currentCustomer.membership_tier || 'standard').toUpperCase()}`);
            syncCustomerCreditState();
            $('#addCustomerModal').modal('hide');
            refocusBarcode();
        });
    });

    // 9. Payment Modal & Checkout Actions
    $('#btn-open-payment').on('click', function() {
        if (cart.length === 0) {
            MartPro.notify('warning', 'Please add products to cart before checkout.');
            return;
        }
        const grandTotal = getGrandTotal();
        $('#modal-due-amount').text(fmt(grandTotal));
        $('#tender-cash').val(grandTotal.toFixed(2));
        $('#tender-card').val('');
        $('#tender-credit').val('');
        syncCustomerCreditState();
        calcModalChange();
        $('#paymentModal').modal('show');
    });

    // Live Change Calculation in Payment Modal
    $('.tender-input').on('input', calcModalChange);

    function calcModalChange() {
        const grandTotal = getGrandTotal();
        const cash = parseFloat($('#tender-cash').val() || 0);
        const card = parseFloat($('#tender-card').val() || 0);
        const credit = parseFloat($('#tender-credit').val() || 0);
        const nonCash = card + credit;
        const remainingDue = Math.max(0, grandTotal - nonCash);
        const changeDue = Math.max(0, cash - remainingDue);
        $('#modal-change-due').text(fmt(changeDue));
    }

    // Quick Cash Chips
    $('.btn-quick-cash').on('click', function() {
        const amountType = $(this).data('amount');
        const grandTotal = getGrandTotal();
        if (cart.length === 0) {
            MartPro.notify('warning', 'Cart is empty.');
            return;
        }
        let cashTender = grandTotal;
        if (amountType !== 'exact') {
            cashTender = parseFloat(amountType);
        }
        submitCheckout({
            paid_cash: cashTender,
            paid_card: 0,
            paid_credit: 0
        });
    });

    // Quick Cash button (F9)
    $('#btn-instant-cash').on('click', function() {
        $('.btn-quick-cash[data-amount="exact"]').trigger('click');
    });

    // Payment Modal Submit
    $('#btn-submit-payment').on('click', function() {
        const cash = parseFloat($('#tender-cash').val() || 0);
        const card = parseFloat($('#tender-card').val() || 0);
        const credit = parseFloat($('#tender-credit').val() || 0);
        const cardRef = $.trim($('#tender-card-ref').val());

        if (credit > 0 && isWalkinCustomer(currentCustomer)) {
            MartPro.notify('error', 'Credit (Khata) sales cannot be saved for Walk-in Customer. Please select or register a valid customer account.');
            $('#tender-credit').val('').focus();
            calcModalChange();
            return;
        }

        submitCheckout({
            paid_cash: cash,
            paid_card: card,
            paid_credit: credit,
            card_reference: cardRef
        }, this);
    });

    // Execute Authoritative Checkout Submission
    function submitCheckout(paymentData, btnEl) {
        if (paymentData.paid_credit > 0 && isWalkinCustomer(currentCustomer)) {
            MartPro.notify('error', 'Credit (Khata) sales cannot be saved for Walk-in Customer. Please select or register a valid customer account.');
            return;
        }

        const payload = Object.assign({
            cart: JSON.stringify(cart),
            customer_id: currentCustomer ? currentCustomer.id : null,
            register_id: registerId,
            bill_discount: billDiscount,
            notes: ''
        }, paymentData);

        MartPro.ajax(MartPro.baseUrl + '/modules/pos/ajax/complete_sale.php', {
            data: payload,
            button: btnEl || '#btn-instant-cash'
        }).then(function(res) {
            $('#paymentModal').modal('hide');
            MartPro.notify('success', `Sale Completed! Invoice #${res.data.invoice_number}`);

            // Open thermal print receipt in new window
            if (res.data.receipt_url) {
                window.open(res.data.receipt_url, '_blank', 'width=450,height=700');
            }

            // Reset cart
            cart = [];
            billDiscount = 0;
            currentCustomer = null;
            $('#pos-customer-name').text('Walk-in Retail Customer');
            $('#pos-customer-details').text('Guest Checkout');
            syncCustomerCreditState();
            renderCart();
            refocusBarcode();
        });
    }

    // 10. Hold & Recall Bills
    $('#btn-hold-sale').on('click', function() {
        if (cart.length === 0) {
            MartPro.notify('warning', 'Cart is empty. Nothing to suspend.');
            return;
        }
        const note = prompt('Enter a reference note for this held transaction (optional):', 'Held ' + new Date().toLocaleTimeString());
        if (note === null) return;

        MartPro.ajax(MartPro.baseUrl + '/modules/pos/ajax/hold_sale.php', {
            data: {
                cart_data: JSON.stringify(cart),
                customer_id: currentCustomer ? currentCustomer.id : null,
                register_id: registerId,
                notes: note
            }
        }).then(function(res) {
            MartPro.notify('success', res.message);
            cart = [];
            billDiscount = 0;
            renderCart();
            refocusBarcode();
        });
    });

    $('#btn-recall-sale').on('click', function() {
        $('#recallModal').modal('show');
        MartPro.ajax(MartPro.baseUrl + '/modules/pos/ajax/get_held_sales.php', { method: 'GET' })
        .then(function(res) {
            const $tbody = $('#held-sales-table-body').empty();
            if (!res.data || res.data.length === 0) {
                $tbody.append('<tr><td colspan="6" class="text-center py-4 text-muted">No suspended transactions on record.</td></tr>');
                return;
            }
            res.data.forEach(h => {
                const tr = `
                    <tr>
                        <td class="fw-bold text-navy">${h.hold_reference}</td>
                        <td>${h.customer_name || 'Walk-in'}</td>
                        <td class="small text-muted">${h.formatted_time}</td>
                        <td><span class="badge bg-secondary">${h.item_count} items</span></td>
                        <td class="small text-muted">${h.notes || '—'}</td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-primary btn-restore-held" data-id="${h.id}">
                                <i class="bi bi-arrow-repeat"></i> Recall
                            </button>
                        </td>
                    </tr>
                `;
                $tbody.append(tr);
            });
        });
    });

    $(document).on('click', '.btn-restore-held', function() {
        const heldId = $(this).data('id');
        MartPro.ajax(MartPro.baseUrl + '/modules/pos/ajax/recall_held_sale.php', {
            data: { held_id: heldId }
        }).then(function(res) {
            cart = res.data.cart || [];
            $('#recallModal').modal('hide');
            renderCart();
            refocusBarcode();
            MartPro.notify('success', 'Suspended sale restored to cart.');
        });
    });

    // 11. Scale Simulator / Web Serial Reading
    $('#btn-scale-sim').on('click', function() {
        const randomKg = (Math.random() * (3.5 - 0.2) + 0.2).toFixed(3);
        $('#live-weight-display').text(randomKg);
        $('#scale-manual-weight').val(randomKg);
    });

    $('#btn-scale-tare').on('click', function() {
        $('#live-weight-display').text('0.000');
        $('#scale-manual-weight').val('0.000');
    });

    $('#btn-apply-scale-weight').on('click', function() {
        const weight = parseFloat($('#scale-manual-weight').val() || 0);
        if (weight <= 0) {
            MartPro.notify('warning', 'Scale reading is 0.000 Kg.');
            return;
        }
        if (cart.length > 0) {
            const lastItem = cart[cart.length - 1];
            lastItem.qty = weight;
            renderCart();
            MartPro.notify('success', `Weight ${weight} Kg applied to "${lastItem.name}".`);
        } else {
            MartPro.notify('info', `Scale weight ${weight} Kg recorded. Scan produce to add to cart.`);
        }
        $('#scaleModal').modal('hide');
        refocusBarcode();
    });

    // 12. Global Keyboard Shortcuts Handler
    $(document).on('keydown', function(e) {
        // Only trigger if not typing inside a modal or general input
        if (e.key === 'F1') {
            e.preventDefault();
            refocusBarcode();
        } else if (e.key === 'F2') {
            e.preventDefault();
            $('#searchProductModal').modal('show');
        } else if (e.key === 'F4') {
            e.preventDefault();
            $('#btn-hold-sale').trigger('click');
        } else if (e.key === 'F7') {
            e.preventDefault();
            $('#btn-recall-sale').trigger('click');
        } else if (e.key === 'F8') {
            e.preventDefault();
            $('#btn-open-payment').trigger('click');
        } else if (e.key === 'F9') {
            e.preventDefault();
            $('#btn-instant-cash').trigger('click');
        } else if (e.key === 'F10') {
            e.preventDefault();
            $('#btn-clear-cart').trigger('click');
        }
    });

    // Product search modal input listener
    $('#modal-search-input').on('input', function() {
        const q = $.trim($(this).val());
        if (q.length < 1) return;
        MartPro.ajax(MartPro.baseUrl + '/modules/pos/ajax/search_products.php?term=' + encodeURIComponent(q), {
            method: 'GET'
        }).then(function(res) {
            const $tbody = $('#modal-search-results').empty();
            if (!res.data || res.data.length === 0) {
                $tbody.append('<tr><td colspan="5" class="text-center py-4 text-muted">No products matched query.</td></tr>');
                return;
            }
            res.data.forEach(p => {
                const tr = `
                    <tr>
                        <td class="fw-bold text-navy">${p.name}</td>
                        <td class="small text-muted">${p.sku} / ${p.primary_barcode}</td>
                        <td><span class="badge ${p.stock_qty > 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'}">${p.stock_qty} ${p.unit_name}</span></td>
                        <td class="fw-bold text-mp-primary">${fmt(p.selling_price)}</td>
                        <td>
                            <button type="button" class="btn btn-sm btn-primary btn-add-search-prod" data-prod='${JSON.stringify(p)}'>
                                <i class="bi bi-cart-plus"></i> Add
                            </button>
                        </td>
                    </tr>
                `;
                $tbody.append(tr);
            });
        });
    });

    $(document).on('click', '.btn-add-search-prod', function() {
        const prod = $(this).data('prod');
        addItemToCart(prod);
        $('#searchProductModal').modal('hide');
        refocusBarcode();
    });

    // Initialize credit UI state for default Walk-in Customer
    syncCustomerCreditState();
});
