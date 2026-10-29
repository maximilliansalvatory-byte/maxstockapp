<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories — MaxStockApp</title>
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
                <li><a href="categories.php" class="active">🏷️ Categories</a></li>
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

        <div class="main-content">
            <header class="topbar">
                <h1>Categories</h1>
                <button class="btn btn-primary btn-sm" onclick="openModal()">+ Add Category</button>
            </header>

            <main class="content">
                <div class="card">
                    <div class="card-header">
                        <h2>Product Categories</h2>
                    </div>
                    <div class="card-body table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Name (Swahili)</th>
                                    <th>Slug</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="catTable">
                                <tr><td colspan="4" style="text-align:center;">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Modal -->
    <div id="catModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:100;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:12px;padding:1.8rem;width:100%;max-width:400px;">
            <h2 style="margin-bottom:1.2rem;">Add Category</h2>
            <form id="catForm">
                <div class="form-group">
                    <label>Name (English) *</label>
                    <input type="text" id="catName" required>
                </div>
                <div class="form-group">
                    <label>Name (Swahili)</label>
                    <input type="text" id="catNameSw">
                </div>
                <div style="display:flex;gap:0.8rem;margin-top:1.2rem;">
                    <button type="submit" class="btn btn-primary" style="flex:1;">Save</button>
                    <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
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

        async function loadCategories() {
            const res = await api('/categories/index.php?business_id=' + (user.business_id || ''));
            const tbody = document.getElementById('catTable');

            if (!res || !res.success) {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">Failed to load</td></tr>';
                return;
            }

            const list = res.data.categories || [];
            if (!list.length) {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:#94a3b8;">No categories yet</td></tr>';
                return;
            }

            tbody.innerHTML = list.map(c => `
                <tr>
                    <td><strong>${escapeHtml(c.name)}</strong></td>
                    <td>${escapeHtml(c.name_sw || '—')}</td>
                    <td><code style="font-size:0.85rem;">${escapeHtml(c.slug)}</code></td>
                    <td><span class="badge badge-success">${c.status}</span></td>
                </tr>
            `).join('');
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        function openModal() {
            document.getElementById('catForm').reset();
            document.getElementById('catModal').style.display = 'flex';
        }
        function closeModal() {
            document.getElementById('catModal').style.display = 'none';
        }

        document.getElementById('catForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const res = await api('/categories/index.php', {
                method: 'POST',
                body: JSON.stringify({
                    name: document.getElementById('catName').value.trim(),
                    name_sw: document.getElementById('catNameSw').value.trim()
                })
            });
            if (res && res.success) {
                closeModal();
                loadCategories();
            } else {
                showMsg(res?.message || 'Failed to create', true);
            }
        });

        loadCategories();
    </script>
</body>
</html>