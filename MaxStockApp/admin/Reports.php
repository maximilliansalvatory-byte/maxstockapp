<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports — MaxStockApp</title>
    <link rel="stylesheet" href="css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        .report-filters {
            display: flex; flex-wrap: wrap; gap: 0.8rem; align-items: end;
            margin-bottom: 1.5rem; background: var(--card); padding: 1.2rem;
            border-radius: var(--radius); box-shadow: var(--shadow);
        }
        .report-filters .form-group { margin-bottom: 0; min-width: 140px; }
        .report-filters label { font-size: 0.8rem; color: var(--text-muted); display: block; margin-bottom: 0.3rem; }
        .report-filters input, .report-filters select {
            padding: 0.5rem 0.7rem; border: 1px solid var(--border); border-radius: 6px; font-size: 0.9rem;
        }
        .export-btns { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .charts-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 1.2rem; margin-bottom: 1.5rem;
        }
        @media (max-width: 900px) { .charts-grid { grid-template-columns: 1fr; } }
        .chart-card {
            background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); padding: 1.3rem;
        }
        .chart-card h3 { font-size: 0.95rem; font-weight: 600; margin-bottom: 1rem; }
        .chart-wrap { position: relative; height: 260px; }
        .analysis-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.2rem; margin-bottom: 1.5rem;
        }
        .analysis-card {
            background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); padding: 1.3rem;
        }
        .analysis-card h3 { font-size: 0.95rem; font-weight: 600; margin-bottom: 1rem; }
        .bar-row { display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.55rem; font-size: 0.85rem; }
        .bar-label { width: 110px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .bar-track { flex: 1; height: 10px; background: #e2e8f0; border-radius: 999px; overflow: hidden; }
        .bar-fill { height: 100%; background: var(--primary); border-radius: 999px; }
        .bar-value { width: 70px; text-align: right; font-weight: 600; font-size: 0.8rem; }
        .empty-note { color: #94a3b8; font-size: 0.9rem; text-align: center; padding: 1.5rem 0; }
        @media print {
            .sidebar, .topbar, .report-filters, .export-btns, .sidebar-footer { display: none !important; }
            .app-layout { grid-template-columns: 1fr; }
            body { background: #fff; }
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
                <li><a href="pos.php">🖥️ POS</a></li>
                <li><a href="payments.php">💳 Payments</a></li>
                <li><a href="reports.php" class="active">📈 Reports</a></li>
                <li><a href="settings.php">⚙️ Settings</a></li>
            </ul>
            <div class="sidebar-footer">
                <div id="userName">—</div>
                <button onclick="logout()">Sign out</button>
            </div>
        </aside>

        <div class="main-content">
            <header class="topbar">
                <h1>Reports & Analytics</h1>
                <div class="user-info" id="topUserInfo"></div>
            </header>

            <main class="content">
                <div class="report-filters">
                    <div class="form-group">
                        <label>From Date</label>
                        <input type="date" id="dateFrom">
                    </div>
                    <div class="form-group">
                        <label>To Date</label>
                        <input type="date" id="dateTo">
                    </div>
                    <div class="form-group">
                        <label>Period</label>
                        <select id="periodQuick">
                            <option value="today">Today</option>
                            <option value="yesterday">Yesterday</option>
                            <option value="7days" selected>Last 7 Days</option>
                            <option value="30days">Last 30 Days</option>
                            <option value="thismonth">This Month</option>
                            <option value="custom">Custom Range</option>
                        </select>
                    </div>
                    <button class="btn btn-primary btn-sm" onclick="loadReport()">Generate Report</button>
                    <div class="export-btns" style="margin-left:auto;">
                        <button class="btn btn-outline btn-sm" onclick="exportCSV()">📥 Excel / CSV</button>
                        <button class="btn btn-outline btn-sm" onclick="exportPDF()">📄 PDF / Print</button>
                        <button class="btn btn-outline btn-sm" onclick="exportWord()">📝 Word</button>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="label">Total Revenue</div>
                        <div class="value green" id="mRevenue">TZS 0</div>
                        <div style="font-size:0.85rem;color:var(--text-muted);" id="mOrdersCount">0 orders</div>
                    </div>
                    <div class="stat-card">
                        <div class="label">Average Order Value</div>
                        <div class="value" id="mAOV">TZS 0</div>
                    </div>
                    <div class="stat-card">
                        <div class="label">Items Sold</div>
                        <div class="value" id="mItems">0</div>
                    </div>
                    <div class="stat-card">
                        <div class="label">Low Stock Alerts</div>
                        <div class="value orange" id="mLowStock">0</div>
                    </div>
                </div>

                <div class="charts-grid">
                    <div class="chart-card">
                        <h3>📈 Sales Trend (Revenue)</h3>
                        <div class="chart-wrap"><canvas id="salesTrendChart"></canvas></div>
                    </div>
                    <div class="chart-card">
                        <h3>🛒 Orders per Day</h3>
                        <div class="chart-wrap"><canvas id="ordersChart"></canvas></div>
                    </div>
                    <div class="chart-card">
                        <h3>💳 Revenue by Payment Channel</h3>
                        <div class="chart-wrap"><canvas id="channelChart"></canvas></div>
                    </div>
                    <div class="chart-card">
                        <h3>🏆 Top Products by Quantity Sold</h3>
                        <div class="chart-wrap"><canvas id="productsChart"></canvas></div>
                    </div>
                </div>

                <div class="analysis-grid">
                    <div class="analysis-card">
                        <h3>⚠️ Low / Slow Stock Items</h3>
                        <div id="lowStockList"><p class="empty-note">Loading...</p></div>
                    </div>
                    <div class="analysis-card">
                        <h3>📅 Period Summary</h3>
                        <div id="periodSummary"><p class="empty-note">Generate report to see summary</p></div>
                    </div>
                </div>

                <div class="card" id="reportTableCard">
                    <div class="card-header">
                        <h2>Detailed Sales Report</h2>
                        <span id="reportRangeLabel" style="font-size:0.85rem;color:var(--text-muted);"></span>
                    </div>
                    <div class="card-body table-wrap">
                        <table id="reportTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Items</th>
                                    <th>Channel</th>
                                    <th>Amount (TZS)</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="reportTableBody">
                                <tr><td colspan="7" class="empty-note">Click Generate Report</td></tr>
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
        if (!user) location.href = 'login.php';
        document.getElementById('userName').textContent = user.full_name;
        document.getElementById('businessName').textContent = 'Business #' + (user.business_id || 'Platform');
        document.getElementById('topUserInfo').textContent = user.full_name;

        let salesTrendChart = null, ordersChart = null, channelChart = null, productsChart = null;
        let lastReport = { orders: [], bestSellers: [], channels: [], lowStock: [], metrics: {}, daily: [] };

        function setDefaultDates() {
            const to = new Date();
            const from = new Date();
            from.setDate(from.getDate() - 6);
            document.getElementById('dateTo').value = to.toISOString().slice(0, 10);
            document.getElementById('dateFrom').value = from.toISOString().slice(0, 10);
        }
        setDefaultDates();

        document.getElementById('periodQuick').addEventListener('change', function () {
            const val = this.value;
            const to = new Date();
            const from = new Date();
            if (val === 'today') from.setTime(to.getTime());
            else if (val === 'yesterday') { from.setDate(to.getDate()-1); to.setDate(to.getDate()-1); }
            else if (val === '7days') from.setDate(to.getDate()-6);
            else if (val === '30days') from.setDate(to.getDate()-29);
            else if (val === 'thismonth') from.setDate(1);
            if (val !== 'custom') {
                document.getElementById('dateFrom').value = from.toISOString().slice(0, 10);
                document.getElementById('dateTo').value = to.toISOString().slice(0, 10);
            }
        });

        async function loadReport() {
            const from = document.getElementById('dateFrom').value;
            const to = document.getElementById('dateTo').value;
            document.getElementById('reportRangeLabel').textContent = `Period: ${from} → ${to}`;

            // REAL DATA ONLY — no demo fallback
            try {
                const params = new URLSearchParams({
                    business_id: user.business_id || '',
                    branch_id: user.branch_id || '',
                    from, to
                });
                const res = await api('/reports/summary.php?' + params.toString());

                if (res && res.success && res.data) {
                    lastReport = res.data;
                    renderAll(res.data);
                } else {
                    showEmpty(from, to, res?.message || 'Could not load report');
                }
            } catch (e) {
                console.error(e);
                showEmpty(from, to, 'Network or server error');
            }
        }

        function showEmpty(from, to, msg) {
            lastReport = { orders: [], bestSellers: [], channels: [], lowStock: [], metrics: {}, daily: [] };
            document.getElementById('mRevenue').textContent = 'TZS 0';
            document.getElementById('mOrdersCount').textContent = '0 orders';
            document.getElementById('mAOV').textContent = 'TZS 0';
            document.getElementById('mItems').textContent = '0';
            document.getElementById('mLowStock').textContent = '0';

            document.getElementById('periodSummary').innerHTML = `
                <p><strong>From:</strong> ${from}</p>
                <p><strong>To:</strong> ${to}</p>
                <p style="margin-top:0.8rem;color:#64748b;font-size:0.9rem;">${msg}</p>
                <p style="color:#64748b;font-size:0.85rem;">Hakuna oda bado katika kipindi hiki. Taarifa zitaonekana baada ya mauzo halisi.</p>
            `;
            document.getElementById('lowStockList').innerHTML = '<p class="empty-note">—</p>';
            document.getElementById('reportTableBody').innerHTML =
                '<tr><td colspan="7" class="empty-note">Hakuna mauzo katika kipindi hiki</td></tr>';

            // Empty charts
            renderCharts({ labels: [], daily: [], channels: [], bestSellers: [] });
        }

        function renderAll(data) {
            const m = data.metrics || {};
            document.getElementById('mRevenue').textContent = formatMoney(m.revenue || 0);
            document.getElementById('mOrdersCount').textContent = (m.orders_count || 0) + ' orders';
            document.getElementById('mAOV').textContent = formatMoney(m.aov || 0);
            document.getElementById('mItems').textContent = m.items_sold || 0;
            document.getElementById('mLowStock').textContent = (data.lowStock || []).length;

            document.getElementById('periodSummary').innerHTML = `
                <p><strong>From:</strong> ${data.period?.from || document.getElementById('dateFrom').value}</p>
                <p><strong>To:</strong> ${data.period?.to || document.getElementById('dateTo').value}</p>
                <p style="margin-top:0.6rem;"><strong>Total Revenue:</strong> ${formatMoney(m.revenue || 0)}</p>
                <p><strong>Total Orders:</strong> ${m.orders_count || 0}</p>
                <p><strong>Items Sold:</strong> ${m.items_sold || 0}</p>
                <p style="margin-top:0.6rem;font-size:0.85rem;color:#16a34a;">✓ Taarifa halisi kutoka database</p>
            `;

            // Low stock
            const low = data.lowStock || [];
            if (!low.length) {
                document.getElementById('lowStockList').innerHTML =
                    '<p style="color:#16a34a;font-size:0.9rem;">All items above threshold 👍</p>';
            } else {
                document.getElementById('lowStockList').innerHTML = low.map(p => `
                    <div class="bar-row">
                        <div class="bar-label">${escapeHtml(p.name)}</div>
                        <div class="bar-track">
                            <div class="bar-fill" style="width:${Math.min(100,(Number(p.quantity)/Math.max(p.low_stock_threshold||5,1))*100)}%;background:#f59e0b;"></div>
                        </div>
                        <div class="bar-value">${p.quantity}</div>
                    </div>
                `).join('');
            }

            renderCharts(data);
            renderTable(data.orders || []);
        }

        function renderCharts(data) {
            const labels = data.labels || [];
            const revenues = (data.daily || []).map(d => d.revenue);
            const orderCounts = (data.daily || []).map(d => d.orders);
            const channels = data.channels || [];
            const sellers = data.bestSellers || [];

            if (salesTrendChart) salesTrendChart.destroy();
            salesTrendChart = new Chart(document.getElementById('salesTrendChart'), {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        label: 'Revenue (TZS)',
                        data: revenues,
                        borderColor: '#0d9488',
                        backgroundColor: 'rgba(13,148,136,0.12)',
                        fill: true, tension: 0.35, pointRadius: 4, pointBackgroundColor: '#0d9488'
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { callback: v => (v/1000)+'k' } } }
                }
            });

            if (ordersChart) ordersChart.destroy();
            ordersChart = new Chart(document.getElementById('ordersChart'), {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{ label: 'Orders', data: orderCounts, backgroundColor: '#3b82f6', borderRadius: 6 }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
                }
            });

            if (channelChart) channelChart.destroy();
            channelChart = new Chart(document.getElementById('channelChart'), {
                type: 'doughnut',
                data: {
                    labels: channels.length ? channels.map(c => c.provider) : ['No data'],
                    datasets: [{
                        data: channels.length ? channels.map(c => c.amount) : [1],
                        backgroundColor: channels.length
                            ? ['#22c55e','#3b82f6','#ef4444','#64748b','#f59e0b','#8b5cf6']
                            : ['#e2e8f0'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } } }
                }
            });

            if (productsChart) productsChart.destroy();
            productsChart = new Chart(document.getElementById('productsChart'), {
                type: 'bar',
                data: {
                    labels: sellers.length ? sellers.map(s => s.name) : ['No sales'],
                    datasets: [{
                        label: 'Qty Sold',
                        data: sellers.length ? sellers.map(s => s.qty) : [0],
                        backgroundColor: '#0d9488', borderRadius: 6
                    }]
                },
                options: {
                    indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { x: { beginAtZero: true } }
                }
            });
        }

        function renderTable(orders) {
            const tbody = document.getElementById('reportTableBody');
            if (!orders.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="empty-note">Hakuna oda katika kipindi hiki</td></tr>';
                return;
            }
            tbody.innerHTML = orders.map(o => `
                <tr>
                    <td>${formatDate(o.created_at)}</td>
                    <td>${escapeHtml(o.order_number)}</td>
                    <td>${escapeHtml(o.customer_name || 'Walk-in')}</td>
                    <td>${o.items_count || '—'}</td>
                    <td>${o.payment_channel || '—'}</td>
                    <td>${formatMoney(o.total_amount)}</td>
                    <td>${o.status}</td>
                </tr>
            `).join('');
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }
        function formatDate(dt) {
            if (!dt) return '—';
            return new Date(dt).toLocaleDateString('en-TZ');
        }

        function exportCSV() {
            const from = document.getElementById('dateFrom').value;
            const to = document.getElementById('dateTo').value;
            let csv = 'MaxStockApp Sales Report\nPeriod,' + from + ' to ' + to + '\nGenerated,' + new Date().toLocaleString('en-TZ') + '\n\n';
            csv += 'Date,Order #,Customer,Items,Channel,Amount (TZS),Status\n';
            (lastReport.orders || []).forEach(o => {
                csv += `"${formatDate(o.created_at)}","${o.order_number}","${o.customer_name||'Walk-in'}","${o.items_count||''}","${o.payment_channel||''}","${o.total_amount}","${o.status}"\n`;
            });
            csv += `\nSummary\nTotal Revenue,${lastReport.metrics?.revenue||0}\nOrders,${lastReport.metrics?.orders_count||0}\n`;
            downloadFile(csv, `MaxStock_Report_${from}_${to}.csv`, 'text/csv;charset=utf-8;');
        }
        function exportPDF() { window.print(); }
        function exportWord() {
            const from = document.getElementById('dateFrom').value;
            const to = document.getElementById('dateTo').value;
            const html = `<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word">
                <head><meta charset="utf-8"><title>MaxStock Report</title></head>
                <body>
                    <h1>MaxStockApp Sales Report</h1>
                    <p><strong>Period:</strong> ${from} to ${to}</p>
                    <p><strong>Generated:</strong> ${new Date().toLocaleString('en-TZ')}</p>
                    <h2>Key Metrics</h2>
                    <ul>
                        <li>Total Revenue: ${document.getElementById('mRevenue').innerText}</li>
                        <li>Orders: ${document.getElementById('mOrdersCount').innerText}</li>
                        <li>AOV: ${document.getElementById('mAOV').innerText}</li>
                        <li>Items Sold: ${document.getElementById('mItems').innerText}</li>
                    </ul>
                    <h2>Detailed Sales</h2>
                    ${document.getElementById('reportTableCard').innerHTML}
                </body></html>`;
            downloadFile(html, `MaxStock_Report_${from}_${to}.doc`, 'application/msword');
        }
        function downloadFile(content, filename, mime) {
            const blob = new Blob(['\ufeff' + content], { type: mime });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url; a.download = filename; a.click();
            URL.revokeObjectURL(url);
        }

        loadReport();
    </script>
</body>
</html>