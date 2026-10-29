<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders — MaxStockApp</title>
    <link rel="stylesheet" href="css/admin.css">
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
                <li><a href="orders.php" class="active">🛒 Orders</a></li>
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

        <div class="main-content">
            <header class="topbar">
                <h1>Orders</h1>
                <div style="display:flex;gap:0.5rem;">
                    <select id="statusFilter" style="padding:0.4rem 0.7rem;border:1px solid var(--border);border-radius:6px;">
                        <option value="">All statuses</option>
                        <option value="pending">Pending</option>
                        <option value="paid">Paid</option>
                        <option value="fulfilled">Fulfilled</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </header>

            <main class="content">
                <div class="card">
                    <div class="card-header">
                        <h2>Order List</h2>
                        <input type="search" id="searchInput" placeholder="Search order # or customer..."
                               style="padding:0.45rem 0.8rem;border:1px solid var(--border);border-radius:6px;width:240px;">
                    </div>
                    <div class="card-body table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Payment</th>
                                    <th>Status</th>
                                    <th>Time</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="ordersTable">
                                <tr><td colspan="8" style="text-align:center;">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Order Detail Modal -->
    <div id="detailModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:100;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:12px;padding:1.8rem;width:100%;max-width:560px;max-height:90vh;overflow-y:auto;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.2rem;">
                <h2 id="detailTitle">Order Details</h2>
                <button onclick="closeDetailModal()" style="background:none;border:none;font-size:1.3rem;cursor:pointer;">✕</button>
            </div>
            <div id="detailBody">
                <!-- filled by JS -->
            </div>
            <div id="detailActions" style="display:flex;gap:0.6rem;margin-top:1.4rem;flex-wrap:wrap;"></div>
        </div>
    </div>

    <script src="js/admin.js"></script>
    <script>
        const user = getUser();
        if (!user) location.href = 'login.php';
        document.getElementById('userName').textContent = user.full_name;
        document.getElementById('businessName').textContent = 'Business #' + (user.business_id || 'Platform');

        let orders = [];

        async function loadOrders() {
            // For now we use a lightweight local endpoint approach.
            // Until the full Orders API is added, we show a clear empty state.
            // This page is ready and will automatically work once api/orders is created.

            const tbody = document.getElementById('ordersTable');

            try {
                const params = new URLSearchParams({
                    business_id: user.business_id || '',
                    limit: 50
                });
                const status = document.getElementById('statusFilter').value;
                if (status) params.set('status', status);

                const res = await api('/orders/index.php?' + params.toString());

                if (res && res.success) {
                    orders = res.data.orders || [];
                    renderTable(orders);
                } else {
                    // API not ready yet – show friendly empty state
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="8" style="text-align:center;padding:2.5rem;color:#64748b;">
                                <div style="font-size:1.1rem;margin-bottom:0.4rem;">No orders yet</div>
                                <div style="font-size:0.9rem;">Orders from the customer app will appear here once the Orders API is connected.</div>
                            </td>
                        </tr>`;
                }
            } catch (e) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" style="text-align:center;padding:2.5rem;color:#64748b;">
                            <div style="font-size:1.1rem;margin-bottom:0.4rem;">No orders yet</div>
                            <div style="font-size:0.9rem;">Orders API will be added next. This page is ready.</div>
                        </td>
                    </tr>`;
            }
        }

        function renderTable(list) {
            const tbody = document.getElementById('ordersTable');
            if (!list.length) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#94a3b8;">No orders found</td></tr>';
                return;
            }

            tbody.innerHTML = list.map(o => {
                const statusBadge = {
                    pending: 'badge-warning',
                    paid: 'badge-info',
                    fulfilled: 'badge-info',
                    completed: 'badge-success',
                    cancelled: 'badge-danger',
                    refunded: 'badge-danger'
                }[o.status] || 'badge-info';

                const payBadge = {
                    unpaid: 'badge-warning',
                    pending: 'badge-warning',
                    paid: 'badge-success',
                    failed: 'badge-danger',
                    refunded: 'badge-danger'
                }[o.payment_status] || 'badge-warning';

                return `
                    <tr>
                        <td><strong>${escapeHtml(o.order_number)}</strong></td>
                        <td>${escapeHtml(o.customer_name || 'Walk-in')}</td>
                        <td>${escapeHtml(o.order_type || 'online')}</td>
                        <td>${formatMoney(o.total_amount)}</td>
                        <td><span class="badge ${payBadge}">${o.payment_status}</span></td>
                        <td><span class="badge ${statusBadge}">${o.status}</span></td>
                        <td>${formatDate(o.created_at)}</td>
                        <td>
                            <button class="btn btn-outline btn-sm" onclick="viewOrder(${o.id})">View</button>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        function formatDate(dt) {
            if (!dt) return '—';
            const d = new Date(dt);
            return d.toLocaleString('en-TZ', { dateStyle: 'short', timeStyle: 'short' });
        }

        document.getElementById('statusFilter').addEventListener('change', loadOrders);
        document.getElementById('searchInput').addEventListener('input', (e) => {
            const q = e.target.value.trim().toLowerCase();
            if (!q) return renderTable(orders);
            renderTable(orders.filter(o =>
                (o.order_number || '').toLowerCase().includes(q) ||
                (o.customer_name || '').toLowerCase().includes(q)
            ));
        });

        async function viewOrder(id) {
            const order = orders.find(o => o.id == id);
            if (!order) return;

            document.getElementById('detailTitle').textContent = order.order_number;
            document.getElementById('detailBody').innerHTML = `
                <p><strong>Customer:</strong> ${escapeHtml(order.customer_name || 'Walk-in')}</p>
                <p><strong>Type:</strong> ${escapeHtml(order.order_type)}</p>
                <p><strong>Status:</strong> ${escapeHtml(order.status)}</p>
                <p><strong>Payment:</strong> ${escapeHtml(order.payment_status)}</p>
                <p><strong>Total:</strong> ${formatMoney(order.total_amount)}</p>
                <p><strong>Created:</strong> ${formatDate(order.created_at)}</p>
                ${order.pickup_code ? `<p><strong>Pickup Code:</strong> <span style="font-size:1.2rem;font-weight:700;color:var(--primary)">${order.pickup_code}</span></p>` : ''}
                ${order.notes ? `<p><strong>Notes:</strong> ${escapeHtml(order.notes)}</p>` : ''}
            `;

            // Action buttons based on status
            let actions = '';
            if (order.status === 'paid') {
                actions += `<button class="btn btn-primary btn-sm" onclick="updateStatus(${order.id}, 'fulfilled')">Mark Fulfilled</button>`;
            }
            if (order.status === 'fulfilled') {
                actions += `<button class="btn btn-primary btn-sm" onclick="updateStatus(${order.id}, 'completed')">Mark Completed</button>`;
            }
            if (['pending','paid'].includes(order.status)) {
                actions += `<button class="btn btn-danger btn-sm" onclick="updateStatus(${order.id}, 'cancelled')">Cancel Order</button>`;
            }
            document.getElementById('detailActions').innerHTML = actions || '<span style="color:#94a3b8;">No actions available</span>';

            document.getElementById('detailModal').style.display = 'flex';
        }

        function closeDetailModal() {
            document.getElementById('detailModal').style.display = 'none';
        }

        async function updateStatus(id, newStatus) {
            const res = await api('/orders/update.php', {
                method: 'POST',
                body: JSON.stringify({ id, status: newStatus })
            });
            if (res && res.success) {
                closeDetailModal();
                loadOrders();
                showMsg('Order updated to ' + newStatus);
            } else {
                showMsg(res?.message || 'Update failed', true);
            }
        }

        loadOrders();
    </script>
</body>
</html>