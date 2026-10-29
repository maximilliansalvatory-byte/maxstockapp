<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0d9488">
    <title>My Orders — MaxStockApp</title>
    <link rel="stylesheet" href="css/customer.css">
    <style>
        .page-header {
            background: #fff;
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
            position: sticky;
            top: 0;
            z-index: 40;
        }
        .page-header h1 {
            font-size: 1.2rem;
            font-weight: 700;
        }
        .orders-list {
            padding: 1rem;
            padding-bottom: calc(80px + env(safe-area-inset-bottom, 0px));
        }
        .order-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,.08);
            margin-bottom: 0.9rem;
            overflow: hidden;
        }
        .order-card a {
            display: block;
            padding: 1rem 1.1rem;
            text-decoration: none;
            color: #0f172a;
        }
        .order-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.4rem;
        }
        .order-number { font-weight: 700; font-size: 0.95rem; }
        .order-meta { font-size: 0.8rem; color: #64748b; }
        .order-amount { font-weight: 700; color: #0d9488; margin-top: 0.35rem; }
        .badge {
            display: inline-block;
            padding: 0.2rem 0.55rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger  { background: #fee2e2; color: #991b1b; }
        .badge-info    { background: #e0f2fe; color: #075985; }
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #64748b;
        }
        .empty-state a {
            display: inline-block;
            margin-top: 1rem;
            padding: 0.7rem 1.4rem;
            background: #0d9488;
            color: #fff;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="page-header">
        <h1>My Orders</h1>
    </div>

    <div class="orders-list" id="ordersList">
        <p style="text-align:center;color:#94a3b8;">Loading...</p>
    </div>

    <nav class="bottom-nav">
    <a href="index.php">🏠 Home</a>
    <a href="orders.php">📦 Orders</a>
    <a href="account.php">👤 Account</a>
</nav>

    <script>
        function getCustomer() {
            try {
                return JSON.parse(localStorage.getItem('maxstock_customer') || 'null');
            } catch (e) {
                return null;
            }
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function formatMoney(n) {
            return 'TZS ' + Number(n || 0).toLocaleString('en-TZ');
        }

        function formatDate(dt) {
            if (!dt) return '—';
            return new Date(dt).toLocaleString('en-TZ', {
                dateStyle: 'medium',
                timeStyle: 'short'
            });
        }

        function statusBadge(status) {
            const map = {
                pending: 'badge-warning',
                paid: 'badge-info',
                fulfilled: 'badge-info',
                completed: 'badge-success',
                cancelled: 'badge-danger',
                refunded: 'badge-danger'
            };
            return map[status] || 'badge-info';
        }

        async function loadOrders() {
            const session = getCustomer();
            const listEl = document.getElementById('ordersList');

            if (!session || !session.user) {
                listEl.innerHTML = `
                    <div class="empty-state">
                        <p>Sign in to see your orders</p>
                        <a href="login.php">Sign In</a>
                    </div>
                `;
                return;
            }

            try {
                const res = await fetch('../api/orders/my-orders.php', {
                    headers: {
                        'Authorization': 'Bearer ' + session.token,
                        'Content-Type': 'application/json'
                    }
                });
                const data = await res.json();

                if (!data.success) {
                    listEl.innerHTML = `
                        <p style="text-align:center;color:#94a3b8;">
                            ${escapeHtml(data.message || 'Could not load orders')}
                        </p>
                    `;
                    return;
                }

                const orders = data.data.orders || [];

                if (!orders.length) {
                    listEl.innerHTML = `
                        <div class="empty-state">
                            <p>You have no orders yet</p>
                            <a href="index.php">Start Shopping</a>
                        </div>
                    `;
                    return;
                }

                listEl.innerHTML = orders.map(function (o) {
                    return `
                        <div class="order-card">
                            <a href="order-detail.php?id=${o.id}">
                                <div class="order-top">
                                    <div>
                                        <div class="order-number">${escapeHtml(o.order_number)}</div>
                                        <div class="order-meta">${formatDate(o.created_at)}</div>
                                    </div>
                                    <span class="badge ${statusBadge(o.status)}">${escapeHtml(o.status)}</span>
                                </div>
                                <div class="order-amount">${formatMoney(o.total_amount)}</div>
                                ${o.pickup_code
                                    ? `<div class="order-meta" style="margin-top:0.3rem;">Pickup: <strong>${escapeHtml(o.pickup_code)}</strong></div>`
                                    : ''}
                            </a>
                        </div>
                    `;
                }).join('');
            } catch (err) {
                console.error(err);
                listEl.innerHTML = `
                    <p style="text-align:center;color:#94a3b8;">
                        Could not load orders
                    </p>
                `;
            }
        }

        loadOrders();
    </script>
</body>
</html>