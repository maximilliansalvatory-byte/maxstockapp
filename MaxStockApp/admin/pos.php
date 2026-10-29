<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS — MaxStockApp</title>
    <link rel="stylesheet" href="css/admin.css">
    <style>
        .pos-layout {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 1.2rem;
            min-height: calc(100vh - 140px);
        }
        @media (max-width: 900px) {
            .pos-layout { grid-template-columns: 1fr; }
        }
        .pos-products {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 0.8rem;
        }
        .pos-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 0.8rem;
            cursor: pointer;
            transition: border-color .15s, box-shadow .15s;
            text-align: center;
        }
        .pos-card:hover {
            border-color: var(--primary);
            box-shadow: 0 2px 8px rgba(13,148,136,.15);
        }
        .pos-card .name { font-size: 0.85rem; font-weight: 600; margin-bottom: 0.3rem; }
        .pos-card .price { color: var(--primary); font-weight: 700; font-size: 0.9rem; }
        .pos-card .stock { font-size: 0.75rem; color: var(--text-muted); }
        .pos-cart {
            background: var(--card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
            max-height: calc(100vh - 160px);
        }
        .pos-cart-header {
            padding: 1rem;
            border-bottom: 1px solid var(--border);
            font-weight: 600;
        }
        .pos-cart-items {
            flex: 1;
            overflow-y: auto;
            padding: 0.8rem 1rem;
        }
        .pos-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.9rem;
        }
        .pos-line .qty-btns button {
            width: 26px; height: 26px; border-radius: 50%;
            border: 1px solid var(--border); background: #f8fafc; cursor: pointer;
        }
        .pos-cart-footer {
            padding: 1rem;
            border-top: 1px solid var(--border);
        }
        .pos-total {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.8rem;
            display: flex;
            justify-content: space-between;
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <aside class="sidebar">
            <div class="brand">
                <h2>MaxStock<span>App</span></h2>
                <small id="businessName">—</small>
            </div>
            <ul class="nav-menu">
                <li><a href="index.php">📊 Dashboard</a></li>
                <li><a href="products.php">📦 Products</a></li>
                <li><a href="categories.php">🏷️ Categories</a></li>
                <li><a href="stock.php">📥 Stock</a></li>
                <li><a href="orders.php">🛒 Orders</a></li>
                <li><a href="pos.php" class="active">🖥️ POS</a></li>
                <li><a href="payments.php">💳 Payments</a></li>
                <li><a href="reports.php">📈 Reports</a></li>
                <li><a href="settings.php">⚙️ Settings</a></li>
            </ul>
            <div class="sidebar-footer">
                <div id="userName">—</div>
                <button onclick="logout()">Sign out</button>
            </div>
        </aside>

        <div class="main-content">
            <header class="topbar">
                <h1>POS — Walk-in Sales</h1>
                <input type="search" id="searchInput" placeholder="Search product..."
                       style="padding:0.45rem 0.8rem;border:1px solid var(--border);border-radius:6px;width:220px;">
            </header>

            <main class="content">
                <div class="pos-layout">
                    <div>
                        <div class="pos-products" id="productGrid">
                            <p style="color:#94a3b8;">Loading...</p>
                        </div>
                    </div>

                    <div class="pos-cart">
                        <div class="pos-cart-header">Current Sale</div>
                        <div class="pos-cart-items" id="cartItems">
                            <p style="text-align:center;color:#94a3b8;padding:2rem 0;">Tap a product to add</p>
                        </div>
                        <div class="pos-cart-footer">
                            <div class="pos-total">
                                <span>Total</span>
                                <span id="cartTotal">TZS 0</span>
                            </div>
                            <div class="form-group" style="margin-bottom:0.8rem;">
                                <label style="font-size:0.8rem;">Payment method</label>
                                <select id="payMethod" style="width:100%;padding:0.5rem;border:1px solid var(--border);border-radius:6px;">
                                    <option value="cash">Cash</option>
                                    <option value="mpesa">M-Pesa</option>
                                    <option value="tigo">Tigo/Yas</option>
                                    <option value="airtel">Airtel Money</option>
                                </select>
                            </div>
                            <div class="form-group" id="phoneGroup" style="margin-bottom:0.8rem;display:none;">
                                <label style="font-size:0.8rem;">Customer phone</label>
                                <input type="tel" id="payPhone" placeholder="0712 345 678"
                                       style="width:100%;padding:0.5rem;border:1px solid var(--border);border-radius:6px;">
                            </div>
                            <button class="btn btn-primary" id="btnPay" onclick="completeSale()" disabled style="width:100%;">
                                Complete Sale
                            </button>
                            <button class="btn btn-outline btn-sm" onclick="clearCart()" style="width:100%;margin-top:0.5rem;">
                                Clear
                            </button>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="js/admin.js"></script>
    <script>
        const user = getUser();
        if (!user) location.href = 'login.php';
        document.getElementById('userName').textContent = user.full_name;
        document.getElementById('businessName').textContent = 'Business #' + (user.business_id || 'Platform');

        let products = [];
        let cart = [];

        document.getElementById('payMethod').addEventListener('change', function () {
            document.getElementById('phoneGroup').style.display =
                this.value === 'cash' ? 'none' : 'block';
        });

        async function loadProducts() {
            const res = await api('/products/index.php?business_id=' + (user.business_id || '') +
                '&branch_id=' + (user.branch_id || 1) + '&limit=100');
            if (!res || !res.success) {
                document.getElementById('productGrid').innerHTML = '<p>Failed to load</p>';
                return;
            }
            products = res.data.products.filter(p => Number(p.quantity ?? 0) > 0);
            renderProducts(products);
        }

        function renderProducts(list) {
            const grid = document.getElementById('productGrid');
            if (!list.length) {
                grid.innerHTML = '<p style="color:#94a3b8;">No products in stock</p>';
                return;
            }
            grid.innerHTML = list.map(p => `
                <div class="pos-card" onclick="addToCart(${p.id})">
                    <div class="name">${escapeHtml(p.name)}</div>
                    <div class="price">${formatMoney(p.price)}</div>
                    <div class="stock">${p.quantity ?? 0} ${escapeHtml(p.unit || '')}</div>
                </div>
            `).join('');
        }

        document.getElementById('searchInput').addEventListener('input', (e) => {
            const q = e.target.value.trim().toLowerCase();
            if (!q) return renderProducts(products);
            renderProducts(products.filter(p => (p.name || '').toLowerCase().includes(q)));
        });

        function addToCart(id) {
            const p = products.find(x => x.id == id);
            if (!p) return;
            const existing = cart.find(i => i.product_id == id);
            const max = Number(p.quantity || 0);
            if (existing) {
                if (existing.quantity >= max) { alert('No more stock'); return; }
                existing.quantity += 1;
            } else {
                cart.push({
                    product_id: p.id,
                    name: p.name,
                    price: Number(p.price),
                    quantity: 1,
                    max
                });
            }
            renderCart();
        }

        function changeQty(id, delta) {
            const item = cart.find(i => i.product_id == id);
            if (!item) return;
            item.quantity += delta;
            if (item.quantity <= 0) cart = cart.filter(i => i.product_id != id);
            else if (item.quantity > item.max) item.quantity = item.max;
            renderCart();
        }

        function renderCart() {
            const el = document.getElementById('cartItems');
            const total = cart.reduce((s, i) => s + i.price * i.quantity, 0);
            document.getElementById('cartTotal').textContent = formatMoney(total);
            document.getElementById('btnPay').disabled = cart.length === 0;

            if (!cart.length) {
                el.innerHTML = '<p style="text-align:center;color:#94a3b8;padding:2rem 0;">Tap a product to add</p>';
                return;
            }
            el.innerHTML = cart.map(i => `
                <div class="pos-line">
                    <div>
                        <strong>${escapeHtml(i.name)}</strong><br>
                        <small>${formatMoney(i.price)}</small>
                    </div>
                    <div class="qty-btns" style="display:flex;align-items:center;gap:0.4rem;">
                        <button onclick="changeQty(${i.product_id},-1)">−</button>
                        <span>${i.quantity}</span>
                        <button onclick="changeQty(${i.product_id},1)">+</button>
                    </div>
                </div>
            `).join('');
        }

        function clearCart() {
            cart = [];
            renderCart();
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        async function completeSale() {
            if (!cart.length) return;
            const provider = document.getElementById('payMethod').value;
            const phone = document.getElementById('payPhone').value.trim();

            if (provider !== 'cash' && phone.replace(/\D/g,'').length < 9) {
                alert('Enter customer phone for mobile money');
                return;
            }

            const btn = document.getElementById('btnPay');
            btn.disabled = true;
            btn.textContent = 'Processing...';

            try {
                // Create POS order
                const orderRes = await api('/orders/create.php', {
                    method: 'POST',
                    body: JSON.stringify({
                        business_id: user.business_id,
                        branch_id: user.branch_id || 1,
                        order_type: 'pos',
                        items: cart.map(i => ({ product_id: i.product_id, quantity: i.quantity }))
                    })
                });

                if (!orderRes || !orderRes.success) {
                    alert(orderRes?.message || 'Failed to create order');
                    return;
                }

                const order = orderRes.data;

                // Initiate payment
                const payRes = await api('/payments/initiate.php', {
                    method: 'POST',
                    body: JSON.stringify({
                        order_id: order.id,
                        provider,
                        phone: phone || null
                    })
                });

                if (!payRes || !payRes.success) {
                    alert(payRes?.message || 'Payment init failed');
                    return;
                }

                // Confirm (cash or sandbox mobile money)
                const confRes = await api('/payments/confirm.php', {
                    method: 'POST',
                    body: JSON.stringify({
                        payment_ref: payRes.data.payment_ref,
                        provider_txn_id: provider === 'cash' ? 'CASH-' + Date.now() : 'POS-' + Date.now()
                    })
                });

                if (confRes && confRes.success) {
                    alert(`✅ Sale complete!\nOrder: ${order.order_number}\nTotal: ${formatMoney(order.total_amount)}`);
                    clearCart();
                    loadProducts();
                } else {
                    alert(confRes?.message || 'Confirm failed');
                }
            } catch (e) {
                console.error(e);
                alert('Error completing sale');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Complete Sale';
            }
        }

        loadProducts();
    </script>
</body>
</html>