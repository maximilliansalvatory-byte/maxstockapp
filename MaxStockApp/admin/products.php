<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products — MaxStockApp</title>
    <link rel="stylesheet" href="css/admin.css">
    <style>
        .product-thumb {
            width: 44px;
            height: 44px;
            object-fit: cover;
            border-radius: 8px;
            background: #f1f5f9;
        }
        .img-preview {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 10px;
            background: #f1f5f9;
            display: none;
            margin-bottom: 0.6rem;
        }
        .img-preview.show { display: block; }
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
                <li><a href="products.php" class="active">📦 Products</a></li>
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

        <div class="main-content">
            <header class="topbar">
                <h1>Products</h1>
                <button class="btn btn-primary btn-sm" onclick="openAddModal()">+ Add Product</button>
            </header>

            <main class="content">
                <div class="card">
                    <div class="card-header">
                        <h2>Inventory</h2>
                        <input type="search" id="searchInput" placeholder="Search name, barcode, SKU..."
                               style="padding:0.45rem 0.8rem;border:1px solid var(--border);border-radius:6px;width:220px;">
                    </div>
                    <div class="card-body table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th></th>
                                    <th>Name</th>
                                    <th>SKU / Barcode</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="productsTable">
                                <tr><td colspan="7" style="text-align:center;">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div id="productModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:100;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:12px;padding:1.8rem;width:100%;max-width:520px;max-height:90vh;overflow-y:auto;">
            <h2 id="modalTitle" style="margin-bottom:1.2rem;">Add Product</h2>
            <form id="productForm">
                <input type="hidden" id="productId">
                <input type="hidden" id="imageUrl">

                <div class="form-group">
                    <label>Product Image</label>
                    <img id="imgPreview" class="img-preview" alt="Preview">
                    <input type="file" id="imageFile" accept="image/jpeg,image/png,image/webp,image/gif">
                    <small style="color:#64748b;font-size:0.8rem;">JPG, PNG, WEBP or GIF — max 5MB</small>
                </div>

                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" id="pName" required>
                </div>
                <div class="form-group">
                    <label>Name (Swahili)</label>
                    <input type="text" id="pNameSw">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea id="pDesc" rows="2" style="width:100%;padding:0.7rem;border:1px solid var(--border);border-radius:8px;"></textarea>
                </div>
                <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:0.8rem;">
                    <div class="form-group">
                        <label>Price (TZS) *</label>
                        <input type="number" id="pPrice" min="0" step="100" required>
                    </div>
                    <div class="form-group">
                        <label>Unit</label>
                        <select id="pUnit">
                            <option value="pcs">pcs</option>
                            <option value="kg">kg</option>
                            <option value="litre">litre</option>
                            <option value="pack">pack</option>
                            <option value="box">box</option>
                        </select>
                    </div>
                </div>
                <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:0.8rem;">
                    <div class="form-group">
                        <label>SKU</label>
                        <input type="text" id="pSku">
                    </div>
                    <div class="form-group">
                        <label>Barcode</label>
                        <input type="text" id="pBarcode">
                    </div>
                </div>
                <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:0.8rem;">
                    <div class="form-group">
                        <label>Initial Quantity</label>
                        <input type="number" id="pQty" min="0" value="0">
                    </div>
                    <div class="form-group">
                        <label>Low Stock Threshold</label>
                        <input type="number" id="pThreshold" min="0" value="5">
                    </div>
                </div>
                <div class="form-group">
                    <label>Tax rate (%)</label>
                    <input type="number" id="pTax" min="0" step="0.01" value="18">
                </div>

                <div style="display:flex;gap:0.8rem;margin-top:1.2rem;">
                    <button type="submit" class="btn btn-primary" style="flex:1;" id="btnSave">Save</button>
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

        let allProducts = [];
        const API_ROOT = '..'; // project root relative to /admin

        async function loadProducts(search = '') {
            const params = new URLSearchParams({
                business_id: user.business_id || '',
                branch_id: user.branch_id || 1,
                limit: 100
            });
            if (search) params.set('search', search);

            const res = await api('/products/index.php?' + params.toString());
            if (!res || !res.success) {
                document.getElementById('productsTable').innerHTML = '<tr><td colspan="7">Failed to load</td></tr>';
                return;
            }
            allProducts = res.data.products;
            renderTable(allProducts);
        }

        function renderTable(list) {
            const tbody = document.getElementById('productsTable');
            if (!list.length) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#94a3b8;">No products found</td></tr>';
                return;
            }
            tbody.innerHTML = list.map(p => {
                const img = p.image_url
                    ? `<img class="product-thumb" src="${API_ROOT}/${escapeHtml(p.image_url)}" alt="">`
                    : `<div class="product-thumb" style="display:flex;align-items:center;justify-content:center;font-size:1.2rem;">🛍️</div>`;
                const qty = p.quantity != null ? p.quantity : '—';
                return `
                    <tr>
                        <td>${img}</td>
                        <td><strong>${escapeHtml(p.name)}</strong></td>
                        <td>${escapeHtml(p.sku || '—')} / ${escapeHtml(p.barcode || '—')}</td>
                        <td>${formatMoney(p.price)}</td>
                        <td>${qty} ${escapeHtml(p.unit || '')}</td>
                        <td><span class="badge badge-${p.status === 'active' ? 'success' : 'warning'}">${p.status}</span></td>
                        <td>
                            <button class="btn btn-outline btn-sm" onclick="editProduct(${p.id})">Edit</button>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        document.getElementById('searchInput').addEventListener('input', (e) => {
            const q = e.target.value.trim().toLowerCase();
            if (!q) return renderTable(allProducts);
            renderTable(allProducts.filter(p =>
                (p.name || '').toLowerCase().includes(q) ||
                (p.barcode || '').includes(q) ||
                (p.sku || '').toLowerCase().includes(q)
            ));
        });

        // Image preview + upload on change
        document.getElementById('imageFile').addEventListener('change', async function () {
            const file = this.files[0];
            if (!file) return;

            const preview = document.getElementById('imgPreview');
            preview.src = URL.createObjectURL(file);
            preview.classList.add('show');

            // Upload immediately
            const fd = new FormData();
            fd.append('image', file);

            const token = getToken();
            try {
                const res = await fetch(API_ROOT + '/api/products/upload-image.php', {
                    method: 'POST',
                    headers: token ? { 'Authorization': 'Bearer ' + token } : {},
                    body: fd
                });
                const data = await res.json();
                if (data.success) {
                    document.getElementById('imageUrl').value = data.data.url;
                } else {
                    showMsg(data.message || 'Image upload failed', true);
                }
            } catch (err) {
                showMsg('Image upload failed', true);
            }
        });

        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add Product';
            document.getElementById('productForm').reset();
            document.getElementById('productId').value = '';
            document.getElementById('imageUrl').value = '';
            document.getElementById('imgPreview').classList.remove('show');
            document.getElementById('imgPreview').src = '';
            document.getElementById('productModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('productModal').style.display = 'none';
        }

        function editProduct(id) {
            const p = allProducts.find(x => x.id == id);
            if (!p) return;
            document.getElementById('modalTitle').textContent = 'Edit Product';
            document.getElementById('productId').value = p.id;
            document.getElementById('pName').value = p.name || '';
            document.getElementById('pNameSw').value = p.name_sw || '';
            document.getElementById('pDesc').value = p.description || '';
            document.getElementById('pPrice').value = p.price || 0;
            document.getElementById('pUnit').value = p.unit || 'pcs';
            document.getElementById('pSku').value = p.sku || '';
            document.getElementById('pBarcode').value = p.barcode || '';
            document.getElementById('pThreshold').value = p.low_stock_threshold || 5;
            document.getElementById('pTax').value = p.tax_rate || 18;
            document.getElementById('pQty').value = 0;
            document.getElementById('imageUrl').value = p.image_url || '';

            const preview = document.getElementById('imgPreview');
            if (p.image_url) {
                preview.src = API_ROOT + '/' + p.image_url;
                preview.classList.add('show');
            } else {
                preview.classList.remove('show');
                preview.src = '';
            }
            document.getElementById('productModal').style.display = 'flex';
        }

        document.getElementById('productForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('productId').value;
            const payload = {
                name: document.getElementById('pName').value.trim(),
                name_sw: document.getElementById('pNameSw').value.trim(),
                description: document.getElementById('pDesc').value.trim(),
                price: parseFloat(document.getElementById('pPrice').value),
                unit: document.getElementById('pUnit').value,
                sku: document.getElementById('pSku').value.trim(),
                barcode: document.getElementById('pBarcode').value.trim(),
                low_stock_threshold: parseInt(document.getElementById('pThreshold').value) || 5,
                tax_rate: parseFloat(document.getElementById('pTax').value) || 0,
                image_url: document.getElementById('imageUrl').value || null,
                branch_id: user.branch_id || 1
            };

            if (!id) {
                payload.initial_quantity = parseFloat(document.getElementById('pQty').value) || 0;
            }

            const btn = document.getElementById('btnSave');
            btn.disabled = true;
            btn.textContent = 'Saving...';

            let res;
            if (id) {
                res = await api('/products/detail.php?id=' + id, {
                    method: 'PUT',
                    body: JSON.stringify(payload)
                });
            } else {
                res = await api('/products/index.php', {
                    method: 'POST',
                    body: JSON.stringify(payload)
                });
            }

            btn.disabled = false;
            btn.textContent = 'Save';

            if (res && res.success) {
                closeModal();
                loadProducts();
            } else {
                showMsg(res?.message || 'Save failed', true);
            }
        });

        loadProducts();
    </script>
</body>
</html>