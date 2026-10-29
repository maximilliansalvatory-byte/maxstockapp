/**
 * MaxStockApp Customer App
 * Browse → Cart → Checkout → Pay (sandbox)
 */

const API = '../api';
const DEFAULT_BUSINESS_ID = 1;
const DEFAULT_BRANCH_ID = 1;

let products = [];
let cart = JSON.parse(localStorage.getItem('maxstock_cart') || '[]');

document.addEventListener('DOMContentLoaded', () => {
    loadProducts();
    updateCartUI();
});

// ---------- Products ----------
async function loadProducts(search = '') {
    const grid = document.getElementById('productGrid');
    grid.innerHTML = '<p class="loading">Loading products...</p>';

    try {
        const params = new URLSearchParams({
            business_id: DEFAULT_BUSINESS_ID,
            branch_id: DEFAULT_BRANCH_ID,
            status: 'active',
            limit: 50
        });
        if (search) params.set('search', search);

        const res = await fetch(`${API}/products/index.php?${params}`);
        const data = await res.json();

        if (!data.success) {
            grid.innerHTML = '<p class="empty">Could not load products</p>';
            return;
        }
        products = data.data.products;
        renderProducts(products);
    } catch (err) {
        console.error(err);
        grid.innerHTML = '<p class="empty">Network error. Check connection.</p>';
    }
}

function renderProducts(list) {
    const grid = document.getElementById('productGrid');
    if (!list.length) {
        grid.innerHTML = '<p class="empty">No products found</p>';
        return;
    }

    grid.innerHTML = list.map(p => {
        const qty = Number(p.quantity ?? 0);
        const inStock = qty > 0;
        return `
            <article class="product-card">
                <div class="img-placeholder">🛍️</div>
                <div class="info">
                    <div class="name">${escapeHtml(p.name)}</div>
                    <div class="price">${formatMoney(p.price)}</div>
                    <div class="stock">${inStock ? qty + ' available' : 'Out of stock'}</div>
                    <button class="add-btn" ${!inStock ? 'disabled' : ''} onclick="addToCart(${p.id})">
                        ${inStock ? 'Add to Cart' : 'Unavailable'}
                    </button>
                </div>
            </article>
        `;
    }).join('');
}

document.getElementById('searchInput').addEventListener('input', (e) => {
    const q = e.target.value.trim().toLowerCase();
    if (!q) return renderProducts(products);
    renderProducts(products.filter(p =>
        (p.name || '').toLowerCase().includes(q) ||
        (p.name_sw || '').toLowerCase().includes(q)
    ));
});

// ---------- Cart ----------
function addToCart(productId) {
    const product = products.find(p => p.id == productId);
    if (!product) return;

    const existing = cart.find(i => i.product_id == productId);
    if (existing) {
        if (existing.quantity >= (product.quantity || 999)) {
            alert('No more stock available');
            return;
        }
        existing.quantity += 1;
    } else {
        cart.push({
            product_id: product.id,
            name: product.name,
            price: Number(product.price),
            unit: product.unit,
            quantity: 1,
            max: Number(product.quantity || 999)
        });
    }
    saveCart();
    updateCartUI();
}

function changeQty(productId, delta) {
    const item = cart.find(i => i.product_id == productId);
    if (!item) return;
    item.quantity += delta;
    if (item.quantity <= 0) {
        cart = cart.filter(i => i.product_id != productId);
    } else if (item.quantity > item.max) {
        item.quantity = item.max;
    }
    saveCart();
    updateCartUI();
}

function saveCart() {
    localStorage.setItem('maxstock_cart', JSON.stringify(cart));
}

function updateCartUI() {
    const count = cart.reduce((s, i) => s + i.quantity, 0);
    const total = cart.reduce((s, i) => s + i.price * i.quantity, 0);

    document.getElementById('cartCount').textContent = count;
    document.getElementById('cartTotal').textContent = formatMoney(total);
    document.getElementById('checkoutBtn').disabled = count === 0;

    const container = document.getElementById('cartItems');
    if (!cart.length) {
        container.innerHTML = '<p style="text-align:center;color:#94a3b8;padding:2rem 0;">Cart is empty</p>';
        return;
    }

    container.innerHTML = cart.map(i => `
        <div class="cart-item">
            <div>
                <strong>${escapeHtml(i.name)}</strong><br>
                <small>${formatMoney(i.price)} × ${i.quantity}</small>
            </div>
            <div class="qty-controls">
                <button onclick="changeQty(${i.product_id}, -1)">−</button>
                <span>${i.quantity}</span>
                <button onclick="changeQty(${i.product_id}, 1)">+</button>
            </div>
        </div>
    `).join('');
}

function toggleCart() {
    document.getElementById('cartDrawer').classList.toggle('open');
    document.getElementById('overlay').classList.toggle('show');
}

// ---------- Checkout + Payment ----------
async function startCheckout() {
    if (!cart.length) return;

    const phone = prompt('Enter your M-Pesa / mobile number (e.g. 0712345678):');
    if (!phone) return;

    const cleanPhone = phone.replace(/\D/g, '');
    if (cleanPhone.length < 9) {
        alert('Please enter a valid phone number');
        return;
    }

    const btn = document.getElementById('checkoutBtn');
    btn.disabled = true;
    btn.textContent = 'Creating order...';

    try {
        // 1. Create order
        const orderRes = await fetch(`${API}/orders/create.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                business_id: DEFAULT_BUSINESS_ID,
                branch_id: DEFAULT_BRANCH_ID,
                order_type: 'online',
                items: cart.map(i => ({
                    product_id: i.product_id,
                    quantity: i.quantity
                }))
            })
        });
        const orderData = await orderRes.json();

        if (!orderData.success) {
            alert(orderData.message || 'Failed to create order');
            btn.disabled = false;
            btn.textContent = 'Proceed to Checkout';
            return;
        }

        const order = orderData.data;
        btn.textContent = 'Initiating payment...';

        // 2. Initiate M-Pesa payment
        const payRes = await fetch(`${API}/payments/initiate.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                order_id: order.id,
                provider: 'mpesa',
                phone: cleanPhone
            })
        });
        const payData = await payRes.json();

        if (!payData.success) {
            alert(payData.message || 'Failed to start payment');
            btn.disabled = false;
            btn.textContent = 'Proceed to Checkout';
            return;
        }

        const payment = payData.data;

        // 3. Sandbox: auto-confirm (simulates successful M-Pesa)
        const confirmPay = confirm(
            `Order ${order.order_number} created.\n` +
            `Amount: ${formatMoney(order.total_amount)}\n` +
            `Pickup code: ${order.pickup_code}\n\n` +
            `SANDBOX: Click OK to simulate successful M-Pesa payment.`
        );

        if (confirmPay) {
            btn.textContent = 'Confirming payment...';
            const confRes = await fetch(`${API}/payments/confirm.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    payment_ref: payment.payment_ref,
                    provider_txn_id: 'SANDBOX-' + Date.now()
                })
            });
            const confData = await confRes.json();

            if (confData.success) {
                // Clear cart
                cart = [];
                saveCart();
                updateCartUI();
                toggleCart();

                alert(
                    `✅ Payment successful!\n\n` +
                    `Order: ${order.order_number}\n` +
                    `Pickup code: ${order.pickup_code}\n` +
                    `Amount paid: ${formatMoney(order.total_amount)}\n\n` +
                    `Show the pickup code at the store.`
                );
                loadProducts(); // refresh stock
            } else {
                alert(confData.message || 'Payment confirmation failed');
            }
        } else {
            alert(
                `Order created but not paid.\n` +
                `Order #: ${order.order_number}\n` +
                `Payment ref: ${payment.payment_ref}\n` +
                `You can complete payment later.`
            );
        }
    } catch (err) {
        console.error(err);
        alert('Network error. Please try again.');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Proceed to Checkout';
    }
}

// ---------- Helpers ----------
function formatMoney(n) {
    return 'TZS ' + Number(n || 0).toLocaleString('en-TZ');
}
function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}