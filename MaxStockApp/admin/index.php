<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — MaxStockApp</title>
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
    <div class="app-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="brand">
                <h2>MaxStock<span>App</span></h2>
                <small id="businessName">Loading...</small>
            </div>
            <ul class="nav-menu">
                <li><a href="index.php" class="active">📊 Dashboard</a></li>
                <li><a href="products.php">📦 Products</a></li>
                <li><a href="categories.php">🏷️ Categories</a></li>
                <li><a href="stock.php">📥 Stock</a></li>
                <li><a href="orders.php">🛒 Orders</a></li>
                <li><a href="pos.php">🖥️ POS</a></li>
                <li><a href="payments.php">💳 Payments</a></li>
                <li><a href="reports.php">📈 Reports</a></li>
                <li><a href="settings.php">⚙️ Settings</a></li>
            </ul>
            <div class="sidebar-footer">
                <div id="userName">—</div>
                <button onclick="logout()">Sign out</button>
            </div>
        </aside>

        <!-- Main -->
        <div class="main-content">
            <header class="topbar">
                <h1>Dashboard</h1>
                <div class="user-info" id="topUserInfo"></div>
            </header>

            <main class="content">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="label">Today's Sales</div>
                        <div class="value green" id="statSales">TZS 0</div>
                    </div>
                    <div class="stat-card">
                        <div class="label">Orders Today</div>
                        <div class="value" id="statOrders">0</div>
                    </div>
                    <div class="stat-card">
                        <div class="label">Low Stock Items</div>
                        <div class="value orange" id="statLowStock">0</div>
                    </div>
                    <div class="stat-card">
                        <div class="label">Total Products</div>
                        <div class="value" id="statProducts">0</div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2>Recent Orders</h2>
                        <a href="orders.php" class="btn btn-outline btn-sm">View all</a>
                    </div>
                    <div class="card-body table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody id="recentOrders">
                                <tr><td colspan="5" style="text-align:center;color:#94a3b8;">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="js/admin.js"></script>
    <script>
        const user = getUser();
        if (!user) {
            window.location.href = 'login.php';
        } else {
            document.getElementById('userName').textContent = user.full_name;
            document.getElementById('topUserInfo').textContent = user.full_name + ' · ' + (user.role || '').replace('_', ' ');
            document.getElementById('businessName').textContent = 'Business #' + (user.business_id || 'Platform');
        }

        async function loadDashboard() {
            try {
                const params = new URLSearchParams({
                    business_id: user.business_id || '',
                    branch_id: user.branch_id || ''
                });
                const res = await api('/reports/dashboard.php?' + params.toString());
                if (!res || !res.success) return;

                const d = res.data;
                document.getElementById('statSales').textContent = formatMoney(d.today_sales);
                document.getElementById('statOrders').textContent = d.orders_today;
                document.getElementById('statLowStock').textContent = d.low_stock;
                document.getElementById('statProducts').textContent = d.total_products;

                const tbody = document.getElementById('recentOrders');
                if (!d.recent_orders || !d.recent_orders.length) {
                    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#94a3b8;">No orders yet</td></tr>';
                    return;
                }
                tbody.innerHTML = d.recent_orders.map(o => `
                    <tr>
                        <td><strong>${o.order_number}</strong></td>
                        <td>${o.customer_name || 'Walk-in'}</td>
                        <td>${formatMoney(o.total_amount)}</td>
                        <td><span class="badge badge-${o.status === 'paid' || o.status === 'completed' ? 'success' : 'warning'}">${o.status}</span></td>
                        <td>${new Date(o.created_at).toLocaleString('en-TZ', {dateStyle:'short', timeStyle:'short'})}</td>
                    </tr>
                `).join('');
            } catch (e) {
                console.warn('Dashboard load failed', e);
            }
        }

        loadDashboard();
    </script>
</body>
</html>