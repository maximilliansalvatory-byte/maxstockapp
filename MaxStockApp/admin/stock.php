<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock — MaxStockApp</title>
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
                <li><a href="stock.php" class="active">📥 Stock</a></li>
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

        <div class="main-content">
            <header class="topbar">
                <h1>Stock Management</h1>
                <button class="btn btn-primary btn-sm" onclick="openAdjustModal()">+ Adjust Stock</button>
            </header>

            <main class="content">
                <div class="card">
                    <div class="card-header">
                        <h2>Current Stock Levels</h2>
                        <input type="search" id="searchInput" placeholder="Search product..."
                               style="padding:0.45rem 0.8rem;border:1px solid var(--border);border-radius:6px;width:220px;">
                    </div>
                    <div class="card-body table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>SKU / Barcode</th>
                                    <th>Quantity</th>
                                    <th>Reserved</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="stockTable">
                                <tr><td colspan="6" style="text-align:center;">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Adjust Stock Modal -->
    <div id="adjustModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:100;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:12px;padding:1.8rem;width:100%;max-width:420px;">
            <h2 style="margin-bottom:1.2rem;">Adjust Stock</h2>
            <form id="adjustForm">
                <div class="form-group">
                    <label>Product *</label>
                    <select id="adjProduct" required>
                        <option value="">Select product...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Movement Type *</label>
                    <select id="adjType" required>
                        <option value="restock">Restock (+)</option>
                        <option value="return">Return (+)</option>
                        <option value="wastage">Wastage (−)</option>
                        <option value="adjustment">Adjustment (±)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Quantity *</label>
                    <input type="number" id="adjQty" min="0.001" step="0.001" required>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <input type="text" id="adjNotes" placeholder="Optional note">
                </div>
                <div style="display:flex;gap:0.8rem;margin-top:1.2rem;">
                    <button type="submit" class="btn btn-primary" style="flex:1;">Save Adjustment</button>
                    <button type="button" class="btn btn-outline" onclick="closeAdjustModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script src="js/admin.js"></script>
    <script>
        const user = getUser();
        if (!user) location.href = 'login.php';
        document.getElementById('userName').textContent = user.full_name;
        document.getElementById('businessName').textContent = 'Business #' + (user.business_id || 'Platform');

        let stockList = [];

        async function loadStock(search = '') {
            const params = new URLSearchParams({
                business_id: user.business_id || '',
                branch_id: user.branch_id || 1,
                limit: 100
            });
            if (search) params.set('search', search);

            const res = await api('/products/index.php?' + params.toString());
            if (!res || !res.success) {
                document.getElementById('stockTable').innerHTML = '<tr><td colspan="6">Failed to load</td></tr>';
                return;
            }
            stockList = res.data.products;
            renderTable(stockList);
            populateProductSelect(stockList);
        }

        function renderTable(list) {
            const tbody = document.getElementById('stockTable');
            if (!list.length) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#94a3b8;">No products found</td></tr>';
                return;
            }
            tbody.innerHTML = list.map(p => {
                const qty = Number(p.quantity ?? 0);
                const reserved = Number(p.reserved_qty ?? 0);
                const low = qty <= (p.low_stock_threshold || 5);
                let badge = 'badge-success';
                let statusText = 'OK';
                if (qty <= 0) { badge = 'badge-danger'; statusText = 'Out of stock'; }
                else if (low) { badge = 'badge-warning'; statusText = 'Low stock'; }

                return `
                    <tr>
                        <td><strong>${escapeHtml(p.name)}</strong></td>
                        <td>${escapeHtml(p.sku || '—')} / ${escapeHtml(p.barcode || '—')}</td>
                        <td><strong>${qty}</strong> ${escapeHtml(p.unit || '')}</td>
                        <td>${reserved}</td>
                        <td><span class="badge ${badge}">${statusText}</span></td>
                        <td>
                            <button class="btn btn-outline btn-sm" onclick="quickAdjust(${p.id})">Adjust</button>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function populateProductSelect(list) {
            const sel = document.getElementById('adjProduct');
            sel.innerHTML = '<option value="">Select product...</option>' +
                list.map(p => `<option value="${p.id}">${escapeHtml(p.name)} (now: ${p.quantity ?? 0})</option>`).join('');
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        document.getElementById('searchInput').addEventListener('input', (e) => {
            const q = e.target.value.trim().toLowerCase();
            if (!q) return renderTable(stockList);
            renderTable(stockList.filter(p =>
                (p.name || '').toLowerCase().includes(q) ||
                (p.barcode || '').includes(q) ||
                (p.sku || '').toLowerCase().includes(q)
            ));
        });

        function openAdjustModal() {
            document.getElementById('adjustForm').reset();
            document.getElementById('adjustModal').style.display = 'flex';
        }

        function closeAdjustModal() {
            document.getElementById('adjustModal').style.display = 'none';
        }

        function quickAdjust(productId) {
            openAdjustModal();
            document.getElementById('adjProduct').value = productId;
        }

        document.getElementById('adjustForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = {
                branch_id: user.branch_id || 1,
                product_id: parseInt(document.getElementById('adjProduct').value),
                movement_type: document.getElementById('adjType').value,
                quantity: parseFloat(document.getElementById('adjQty').value),
                notes: document.getElementById('adjNotes').value.trim()
            };

            if (!payload.product_id || !payload.quantity) {
                showMsg('Please select a product and enter quantity', true);
                return;
            }

            const res = await api('/stock/adjust.php', {
                method: 'POST',
                body: JSON.stringify(payload)
            });

            if (res && res.success) {
                closeAdjustModal();
                loadStock();
                showMsg('Stock updated: ' + res.data.before + ' → ' + res.data.after);
            } else {
                showMsg(res?.message || 'Adjustment failed', true);
            }
        });

        loadStock();
    </script>
</body>
</html>