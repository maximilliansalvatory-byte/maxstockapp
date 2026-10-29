/**
 * MaxStockApp Admin — Shared JS utilities
 */

const API_BASE = '../api';

function getToken() {
    return localStorage.getItem('maxstock_token');
}

function getUser() {
    try {
        return JSON.parse(localStorage.getItem('maxstock_user') || 'null');
    } catch {
        return null;
    }
}

function logout() {
    localStorage.removeItem('maxstock_token');
    localStorage.removeItem('maxstock_user');
    window.location.href = 'login.php';
}

/**
 * Authenticated fetch helper
 */
async function api(path, options = {}) {
    const token = getToken();
    const headers = {
        'Content-Type': 'application/json',
        ...(options.headers || {})
    };
    if (token) {
        headers['Authorization'] = `Bearer ${token}`;
    }

    const res = await fetch(`${API_BASE}${path}`, {
        ...options,
        headers
    });

    const data = await res.json().catch(() => ({ success: false, message: 'Invalid response' }));

    if (res.status === 401) {
        logout();
        return;
    }

    return data;
}

/**
 * Format TZS currency
 */
function formatMoney(amount) {
    return 'TZS ' + Number(amount || 0).toLocaleString('en-TZ', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    });
}

/**
 * Simple toast / alert for now
 */
function showMsg(msg, isError = false) {
    alert((isError ? 'Error: ' : '') + msg);
}

/**
 * Load basic dashboard numbers
 */
async function loadDashboard() {
    const user = getUser();
    if (!user || !user.business_id) return;

    try {
        const res = await api(`/products/index.php?business_id=${user.business_id}&limit=1`);
        if (res && res.success) {
            document.getElementById('statProducts').textContent = res.data.pagination.total;
        }
    } catch (e) {
        console.warn('Dashboard load failed', e);
    }
}
