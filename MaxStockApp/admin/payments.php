<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments — MaxStockApp</title>
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
                <li><a href="orders.php">🛒 Orders</a></li>
                <li><a href="pos.php">🖥️ POS</a></li>
                <li><a href="payments.php" class="active">💳 Payments</a></li>
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
                <h1>Payments</h1>
                <div style="display:flex;gap:0.5rem;">
                    <select id="providerFilter" style="padding:0.4rem 0.7rem;border:1px solid var(--border);border-radius:6px;">
                        <option value="">All channels</option>
                        <option value="mpesa">Vodacom M-Pesa</option>
                        <option value="tigo">Tigo / Yas</option>
                        <option value="airtel">Airtel Money</option>
                        <option value="nmb">NMB Bank</option>
                        <option value="crdb">CRDB Bank</option>
                        <option value="cash">Cash</option>
                    </select>
                    <select id="statusFilter" style="padding:0.4rem 0.7rem;border:1px solid var(--border);border-radius:6px;">
                        <option value="">All statuses</option>
                        <option value="success">Success</option>
                        <option value="pending">Pending</option>
                        <option value="failed">Failed</option>
                        <option value="refunded">Refunded</option>
                    </select>
                </div>
            </header>

            <main class="content">
                <!-- Summary cards -->
                <div class="stats-grid" style="margin-bottom:1.5rem;">
                    <div class="stat-card">
                        <div class="label">Today's Received</div>
                        <div class="value green" id="statToday">TZS 0</div>
                    </div>
                    <div class="stat-card">
                        <div class="label">Successful</div>
                        <div class="value" id="statSuccess">0</div>
                    </div>
                    <div class="stat-card">
                        <div class="label">Pending</div>
                        <div class="value orange" id="statPending">0</div>
                    </div>
                    <div class="stat-card">
                        <div class="label">Failed / Refunded</div>
                        <div class="value" id="statFailed" style="color:#dc2626;">0</div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2>Payment Transactions</h2>
                        <input type="search" id="searchInput" placeholder="Search ref, phone, order..."
                               style="padding:0.45rem 0.8rem;border:1px solid var(--border);border-radius:6px;width:240px;">
                    </div>
                    <div class="card-body table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Payment Ref</th>
                                    <th>Order #</th>
                                    <th>Channel</th>
                                    <th>Phone</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Time</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="paymentsTable">
                                <tr><td colspan="8" style="text-align:center;">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Detail Modal -->
    <div id="detailModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:100;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:12px;padding:1.8rem;width:100%;max-width:520px;max-height:90vh;overflow-y:auto;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.2rem;">
                <h2 id="detailTitle">Payment Details</h2>
                <button onclick="closeDetailModal()" style="background:none;border:none;font-size:1.3rem;cursor:pointer;">✕</button>
            </div>
            <div id="detailBody"></div>
        </div>
    </div>

    <script src="js/admin.js"></script>
    <script>
        const user = getUser();
        if (!user) location.href = 'login.php';
        document.getElementById('userName').textContent = user.full_name;
        document.getElementById('businessName').textContent = 'Business #' + (user.business_id || 'Platform');

        let payments = [];

        const providerNames = {
            mpesa: 'M-Pesa',
            tigo: 'Tigo/Yas',
            airtel: 'Airtel Money',
            nmb: 'NMB',
            crdb: 'CRDB',
            cash: 'Cash',
            other: 'Other'
        };

        async function loadPayments() {
            const tbody = document.getElementById('paymentsTable');

            try {
                const params = new URLSearchParams({
                    business_id: user.business_id || '',
                    limit: 50
                });
                const provider = document.getElementById('providerFilter').value;
                const status = document.getElementById('statusFilter').value;
                if (provider) params.set('provider', provider);
                if (status) params.set('status', status);

                const res = await api('/payments/index.php?' + params.toString());

                if (res && res.success) {
                    payments = res.data.payments || [];
                    renderTable(payments);
                    updateStats(res.data.stats || {});
                } else {
                    showEmptyState(tbody);
                    resetStats();
                }
            } catch (e) {
                showEmptyState(tbody);
                resetStats();
            }
        }

        function showEmptyState(tbody) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" style="text-align:center;padding:2.5rem;color:#64748b;">
                        <div style="font-size:1.1rem;margin-bottom:0.4rem;">No payments yet</div>
                        <div style="font-size:0.9rem;">
                            Payments will appear here once customers pay via M-Pesa, Tigo, Airtel, NMB or CRDB.
                        </div>
                    </td>
                </tr>`;
        }

        function resetStats() {
            document.getElementById('statToday').textContent = 'TZS 0';
            document.getElementById('statSuccess').textContent = '0';
            document.getElementById('statPending').textContent = '0';
            document.getElementById('statFailed').textContent = '0';
        }

        function updateStats(stats) {
            document.getElementById('statToday').textContent = formatMoney(stats.today_amount || 0);
            document.getElementById('statSuccess').textContent = stats.success_count || 0;
            document.getElementById('statPending').textContent = stats.pending_count || 0;
            document.getElementById('statFailed').textContent = (stats.failed_count || 0) + (stats.refunded_count || 0);
        }

        function renderTable(list) {
            const tbody = document.getElementById('paymentsTable');
            if (!list.length) {
                showEmptyState(tbody);
                return;
            }

            tbody.innerHTML = list.map(p => {
                const statusBadge = {
                    success: 'badge-success',
                    pending: 'badge-warning',
                    failed: 'badge-danger',
                    cancelled: 'badge-danger',
                    refunded: 'badge-danger'
                }[p.status] || 'badge-info';

                return `
                    <tr>
                        <td><strong style="font-size:0.85rem;">${escapeHtml(p.payment_ref)}</strong></td>
                        <td>${escapeHtml(p.order_number || '—')}</td>
                        <td>${providerNames[p.provider] || p.provider}</td>
                        <td>${escapeHtml(p.phone || '—')}</td>
                        <td>${formatMoney(p.amount)}</td>
                        <td><span class="badge ${statusBadge}">${p.status}</span></td>
                        <td>${formatDate(p.paid_at || p.created_at)}</td>
                        <td>
                            <button class="btn btn-outline btn-sm" onclick="viewPayment(${p.id})">View</button>
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
            return new Date(dt).toLocaleString('en-TZ', { dateStyle: 'short', timeStyle: 'short' });
        }

        document.getElementById('providerFilter').addEventListener('change', loadPayments);
        document.getElementById('statusFilter').addEventListener('change', loadPayments);
        document.getElementById('searchInput').addEventListener('input', (e) => {
            const q = e.target.value.trim().toLowerCase();
            if (!q) return renderTable(payments);
            renderTable(payments.filter(p =>
                (p.payment_ref || '').toLowerCase().includes(q) ||
                (p.phone || '').includes(q) ||
                (p.order_number || '').toLowerCase().includes(q)
            ));
        });

        function viewPayment(id) {
            const p = payments.find(x => x.id == id);
            if (!p) return;

            document.getElementById('detailTitle').textContent = p.payment_ref;
            document.getElementById('detailBody').innerHTML = `
                <p><strong>Order:</strong> ${escapeHtml(p.order_number || '—')}</p>
                <p><strong>Channel:</strong> ${providerNames[p.provider] || p.provider}</p>
                <p><strong>Phone:</strong> ${escapeHtml(p.phone || '—')}</p>
                <p><strong>Amount:</strong> ${formatMoney(p.amount)}</p>
                <p><strong>Status:</strong> ${escapeHtml(p.status)}</p>
                <p><strong>Provider Txn ID:</strong> ${escapeHtml(p.provider_txn_id || '—')}</p>
                <p><strong>Paid At:</strong> ${formatDate(p.paid_at)}</p>
                <p><strong>Created:</strong> ${formatDate(p.created_at)}</p>
            `;
            document.getElementById('detailModal').style.display = 'flex';
        }

        function closeDetailModal() {
            document.getElementById('detailModal').style.display = 'none';
        }

        loadPayments();
    </script>
</body>
</html>