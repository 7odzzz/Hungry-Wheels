<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}
require '../db.php';

$user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->execute([$_SESSION["user_id"]]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);
$is_elite = $user['is_elite'] ?? false;
$order_count = 0;
try {
    $o = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
    $o->execute([$_SESSION["user_id"]]);
    $order_count = $o->fetchColumn();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cart — Hungry Wheels</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'DM Sans', sans-serif; }
        h1,h2,h3,h4,.syne { font-family: 'Syne', sans-serif; }
        body { background: #080d14; }
        .navbar-glass {
            background: rgba(8,13,20,0.9);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(56,189,248,0.08);
        }
        .sidebar {
            background: rgba(14,20,32,0.95);
            border-right: 1px solid rgba(51,65,85,0.4);
            width: 260px;
            min-height: calc(100vh - 64px);
            position: sticky;
            top: 64px;
            height: calc(100vh - 64px);
            overflow-y: auto;
            flex-shrink: 0;
        }
        .nav-item {
            display: flex; align-items: center; gap: 12px;
            padding: 11px 16px; border-radius: 10px;
            color: #94a3b8; font-size: 14px; font-weight: 500;
            cursor: pointer; transition: all 0.2s;
            text-decoration: none; margin: 2px 0;
        }
        .nav-item:hover { background: rgba(56,189,248,0.08); color: #e2e8f0; }
        .nav-item.active { background: rgba(56,189,248,0.12); color: #38bdf8; font-weight: 600; }
        .nav-item .icon {
            width: 36px; height: 36px; border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px; background: rgba(30,41,59,0.8); flex-shrink: 0;
        }
        .nav-item.active .icon { background: rgba(56,189,248,0.15); }
        .sidebar-section {
            font-size: 10px; font-weight: 700; letter-spacing: 0.1em;
            color: #475569; text-transform: uppercase;
            padding: 4px 16px; margin-top: 20px; margin-bottom: 4px;
            font-family: 'Syne', sans-serif;
        }
        .stat-card {
            background: rgba(20,30,48,0.7);
            border: 1px solid rgba(51,65,85,0.4);
            border-radius: 12px; padding: 14px 16px;
        }
        .avatar { background: linear-gradient(135deg,#38bdf8,#6366f1); }
        .card {
            background: rgba(20,30,48,0.85);
            border: 1px solid rgba(51,65,85,0.5);
        }
        .qty-btn {
            width: 30px; height: 30px;
            background: rgba(30,41,59,0.9);
            border: 1px solid rgba(51,65,85,0.6);
            color: #94a3b8; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: all 0.2s; font-size: 16px;
        }
        .qty-btn:hover { background: #38bdf8; color: #080d14; border-color: #38bdf8; }
        .address-input {
            background: rgba(30,41,59,0.8);
            border: 1.5px solid rgba(51,65,85,0.8);
            color: #e2e8f0; transition: all 0.25s;
        }
        .address-input::placeholder { color: #475569; }
        .address-input:focus {
            border-color: #38bdf8;
            box-shadow: 0 0 0 3px rgba(56,189,248,0.12);
            outline: none;
        }
        .btn-place {
            background: #38bdf8; color: #080d14;
            font-weight: 700; transition: all 0.2s;
        }
        .btn-place:hover { background: #0ea5e9; transform: translateY(-1px); }
        .btn-place:disabled {
            background: #1e293b; color: #475569; cursor: not-allowed; transform: none;
        }
        .empty-cart { animation: fadeUp 0.4s ease both; }
        @keyframes fadeUp {
            from { opacity:0; transform:translateY(20px); }
            to   { opacity:1; transform:translateY(0); }
        }
        .cart-item { animation: fadeUp 0.3s ease both; }
        #sidebar-overlay {
            display:none; position:fixed; inset:0;
            background:rgba(0,0,0,0.6); z-index:40; backdrop-filter:blur(2px);
        }
        #sidebar-overlay.open { display:block; }
        #mobile-sidebar { transform:translateX(-100%); transition:transform 0.3s ease; }
        #mobile-sidebar.open { transform:translateX(0); }
        .bg-glow {
            position:fixed; top:-200px; left:200px;
            width:600px; height:600px;
            background:radial-gradient(circle,rgba(56,189,248,0.06) 0%,transparent 70%);
            pointer-events:none; z-index:0;
            animation:floatGlow 8s ease-in-out infinite;
        }
        @keyframes floatGlow {
            0%,100% { transform:translate(0,0); }
            50% { transform:translate(40px,40px); }
        }
    </style>
</head>
<body class="min-h-screen text-slate-200">
<div class="bg-glow"></div>

<!-- NAVBAR -->
<nav class="navbar-glass sticky top-0 z-50 h-16">
    <div class="h-full px-4 sm:px-6 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <button onclick="toggleSidebar()" class="text-slate-400 hover:text-sky-400 p-1.5 rounded-lg hover:bg-slate-800 transition lg:hidden">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
            <a href="home.php" class="syne text-lg font-800 text-sky-400 tracking-tight flex items-center gap-2">
                🍔 <span>Hungry Wheels</span>
            </a>
        </div>
        <div class="flex items-center gap-3">
            <?php if ($is_elite): ?>
                <span class="hidden sm:inline text-xs font-700 px-3 py-1 rounded-full syne" style="background:linear-gradient(135deg,#f59e0b,#fbbf24);color:#080d14">⭐ Elite</span>
            <?php endif; ?>
            <div class="avatar w-9 h-9 rounded-full flex items-center justify-center text-sm text-white font-700">
                <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
            </div>
        </div>
    </div>
</nav>

<div class="relative z-10 flex">
    <div id="sidebar-overlay" onclick="toggleSidebar()"></div>

    <!-- Desktop sidebar -->
    <aside class="sidebar hidden lg:block">
        <?php include 'side-bar.php'; ?>
    </aside>
    <!-- Mobile sidebar -->
    <aside id="mobile-sidebar" class="fixed top-16 left-0 z-50 sidebar lg:hidden" style="min-height:calc(100vh - 64px);height:calc(100vh - 64px);">
        <?php include 'sidebar_content.php'; ?>
    </aside>

    <!-- MAIN -->
    <main class="flex-1 min-w-0 px-4 sm:px-6 lg:px-8 py-8">
        <div class="max-w-4xl mx-auto">

            <div class="flex items-center justify-between mb-8">
                <div>
                    <h2 class="syne text-3xl font-800 text-white mb-1">Your Cart</h2>
                    <p class="text-slate-400 text-sm">Review your items before placing the order</p>
                </div>
                <a href="home.php" class="text-sm text-sky-400 hover:underline flex items-center gap-1">
                    ← Back to menu
                </a>
            </div>

            <!-- Empty cart -->
            <div id="empty-cart" class="hidden text-center py-24 empty-cart">
                <div class="text-6xl mb-4">🛒</div>
                <h3 class="syne text-xl text-slate-400 mb-2">Your cart is empty</h3>
                <p class="text-slate-500 text-sm mb-6">Add some delicious items from the menu</p>
                <a href="home.php" class="inline-block bg-sky-400 text-slate-900 font-700 px-6 py-3 rounded-xl hover:bg-sky-300 transition syne">Browse Menu</a>
            </div>

            <!-- Cart content -->
            <div id="cart-content" class="hidden">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    <!-- Items list -->
                    <div class="lg:col-span-2 space-y-3" id="cart-items-list"></div>

                    <!-- Order summary -->
                    <div class="space-y-4">
                        <!-- Summary card -->
                        <div class="card rounded-2xl p-5">
                            <h3 class="syne font-700 text-white mb-4">Order Summary</h3>
                            <div class="space-y-2 text-sm mb-4">
                                <div class="flex justify-between text-slate-400">
                                    <span>Subtotal</span>
                                    <span id="subtotal">0 EGP</span>
                                </div>
                                <?php if ($is_elite): ?>
                                <div class="flex justify-between text-amber-400">
                                    <span>Elite discount (10%)</span>
                                    <span id="discount-row">-0 EGP</span>
                                </div>
                                <?php endif; ?>
                                <div class="border-t border-slate-700 pt-2 flex justify-between text-white font-700 text-base">
                                    <span>Total</span>
                                    <span id="total" class="text-sky-400">0 EGP</span>
                                </div>
                            </div>
                        </div>

                        <!-- Address card -->
                        <div class="card rounded-2xl p-5">
                            <h3 class="syne font-700 text-white mb-3">Delivery Address</h3>

                            <?php if (!empty($user['address'])): ?>
                            <div class="mb-3">
                                <label class="flex items-start gap-2 cursor-pointer group">
                                    <input type="radio" name="addr_choice" id="saved_addr" value="saved" checked
                                        class="mt-1 accent-sky-400" onchange="toggleAddress()">
                                    <div>
                                        <div class="text-slate-300 text-sm font-600 group-hover:text-white transition">Use saved address</div>
                                        <div class="text-slate-500 text-xs mt-0.5"><?= htmlspecialchars($user['address']) ?></div>
                                    </div>
                                </label>
                            </div>
                            <div class="mb-3">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="addr_choice" id="new_addr" value="new"
                                        class="accent-sky-400" onchange="toggleAddress()">
                                    <span class="text-slate-300 text-sm font-600">Enter new address</span>
                                </label>
                            </div>
                            <?php endif; ?>

                            <textarea id="address-input" rows="3"
                                placeholder="Enter your full delivery address..."
                                class="address-input w-full px-4 py-3 rounded-xl text-sm resize-none <?= !empty($user['address']) ? 'hidden' : '' ?>"><?= empty($user['address']) ? '' : '' ?></textarea>
                        </div>

                        <!-- Place order -->
                        <button onclick="placeOrder()" id="place-btn"
                            class="btn-place w-full py-4 rounded-xl text-base syne">
                            🛵 Place Order
                        </button>

                        <!-- Success message -->
                        <div id="success-msg" class="hidden text-center bg-green-400/10 border border-green-400/30 rounded-xl p-4">
                            <div class="text-2xl mb-2">✅</div>
                            <div class="syne text-green-400 font-700">Order Placed!</div>
                            <div class="text-slate-400 text-xs mt-1">Redirecting to your orders...</div>
                        </div>

                        <!-- Error message -->
                        <div id="error-msg" class="hidden text-center bg-red-400/10 border border-red-400/30 rounded-xl p-4">
                            <div class="text-slate-300 text-sm" id="error-text"></div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>
</div>

<script>
const IS_ELITE = <?= $is_elite ? 'true' : 'false' ?>;
const SAVED_ADDRESS = <?= json_encode($user['address'] ?? '') ?>;

// ── Cart storage ──────────────────────────────────────────
function getCart() {
    return JSON.parse(localStorage.getItem('hw_cart') || '[]');
}
function saveCart(cart) {
    localStorage.setItem('hw_cart', JSON.stringify(cart));
}

// ── Render ────────────────────────────────────────────────
function renderCart() {
    const cart = getCart();
    const emptyEl = document.getElementById('empty-cart');
    const contentEl = document.getElementById('cart-content');

    if (cart.length === 0) {
        emptyEl.classList.remove('hidden');
        contentEl.classList.add('hidden');
        return;
    }

    emptyEl.classList.add('hidden');
    contentEl.classList.remove('hidden');

    const listEl = document.getElementById('cart-items-list');
    listEl.innerHTML = '';

    let subtotal = 0;

    cart.forEach((item, index) => {
        subtotal += item.price * item.qty;
        const div = document.createElement('div');
        div.className = 'card cart-item rounded-2xl p-4 flex items-center gap-4';
        div.innerHTML = `
            <div class="flex-1 min-w-0">
                <div class="syne font-700 text-white text-sm truncate">${item.name}</div>
                <div class="text-sky-400 text-xs mt-0.5">${item.price.toFixed(0)} EGP each</div>
            </div>
            <div class="flex items-center gap-2">
                <button class="qty-btn" onclick="changeQty(${index}, -1)">−</button>
                <span class="syne font-700 text-white text-sm w-6 text-center">${item.qty}</span>
                <button class="qty-btn" onclick="changeQty(${index}, 1)">+</button>
            </div>
            <div class="text-right min-w-[70px]">
                <div class="syne font-700 text-white text-sm">${(item.price * item.qty).toFixed(0)} EGP</div>
                <button onclick="removeItem(${index})" class="text-red-400/60 hover:text-red-400 text-xs mt-0.5 transition">Remove</button>
            </div>
        `;
        listEl.appendChild(div);
    });

    const discount = IS_ELITE ? subtotal * 0.1 : 0;
    const total = subtotal - discount;

    document.getElementById('subtotal').textContent = subtotal.toFixed(0) + ' EGP';
    document.getElementById('total').textContent = total.toFixed(0) + ' EGP';
    if (IS_ELITE) {
        document.getElementById('discount-row').textContent = '-' + discount.toFixed(0) + ' EGP';
    }
}

function changeQty(index, delta) {
    const cart = getCart();
    cart[index].qty += delta;
    if (cart[index].qty <= 0) cart.splice(index, 1);
    saveCart(cart);
    renderCart();
}

function removeItem(index) {
    const cart = getCart();
    cart.splice(index, 1);
    saveCart(cart);
    renderCart();
}

// ── Address toggle ────────────────────────────────────────
function toggleAddress() {
    const useSaved = document.getElementById('saved_addr')?.checked;
    const input = document.getElementById('address-input');
    if (useSaved) {
        input.classList.add('hidden');
        input.value = '';
    } else {
        input.classList.remove('hidden');
        input.focus();
    }
}

function getAddress() {
    const savedRadio = document.getElementById('saved_addr');
    if (savedRadio && savedRadio.checked) return SAVED_ADDRESS;
    return document.getElementById('address-input').value.trim();
}

// ── Place order ───────────────────────────────────────────
function placeOrder() {
    const cart = getCart();
    if (cart.length === 0) return;

    const address = getAddress();
    if (!address) {
        showError('Please enter a delivery address.');
        return;
    }

    document.getElementById('place-btn').disabled = true;
    document.getElementById('place-btn').textContent = 'Placing order...';
    document.getElementById('error-msg').classList.add('hidden');

    fetch('place_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ cart, address })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            localStorage.removeItem('hw_cart');
            document.getElementById('success-msg').classList.remove('hidden');
            document.getElementById('place-btn').classList.add('hidden');
            setTimeout(() => window.location.href = 'orders.php', 2000);
        } else {
            showError(data.message || 'Something went wrong.');
            document.getElementById('place-btn').disabled = false;
            document.getElementById('place-btn').textContent = '🛵 Place Order';
        }
    })
    .catch(() => {
        showError('Network error. Please try again.');
        document.getElementById('place-btn').disabled = false;
        document.getElementById('place-btn').textContent = '🛵 Place Order';
    });
}

function showError(msg) {
    const el = document.getElementById('error-msg');
    document.getElementById('error-text').textContent = msg;
    el.classList.remove('hidden');
}

function toggleSidebar() {
    document.getElementById('mobile-sidebar').classList.toggle('open');
    document.getElementById('sidebar-overlay').classList.toggle('open');
}

// Init
renderCart();
</script>
</body>
</html>