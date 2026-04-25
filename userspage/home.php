<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}
require('../db.php');

$search = trim($_GET['search'] ?? '');
$category_filter = trim($_GET['category'] ?? '');

$sql = "SELECT * FROM menu_items WHERE is_available = 1";
$params = [];

if ($search !== '') {
    $sql .= " AND (name LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category_filter !== '') {
    $sql .= " AND category = ?";
    $params[] = $category_filter;
}

$sql .= " ORDER BY category, name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$menu = [];
foreach ($items as $item) {
    $menu[$item['category']][] = $item;
}

$cat_stmt = $pdo->query("SELECT DISTINCT category FROM menu_items WHERE is_available = 1 ORDER BY category");
$categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);

$user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->execute([$_SESSION["user_id"]]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);
$is_elite = $user['is_elite'] ?? false;

$order_count = 0;
try {
    $o = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
    $o->execute([$_SESSION["user_id"]]);
    $order_count = $o->fetchColumn();
} catch (Exception $e) { }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu — Hungry Wheels</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'DM Sans', sans-serif; }
        h1, h2, h3, h4, .syne { font-family: 'Syne', sans-serif; }
        body { background: #080d14; }

        .bg-glow {
            position: fixed; top: -200px; left: 200px;
            width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(56,189,248,0.06) 0%, transparent 70%);
            pointer-events: none; z-index: 0;
            animation: floatGlow 8s ease-in-out infinite;
        }
        .bg-glow-2 {
            position: fixed; bottom: -200px; right: -100px;
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(99,102,241,0.05) 0%, transparent 70%);
            pointer-events: none; z-index: 0;
            animation: floatGlow 10s ease-in-out infinite reverse;
        }
        @keyframes floatGlow {
            0%, 100% { transform: translate(0,0); }
            50% { transform: translate(40px, 40px); }
        }

        .navbar-glass {
            background: rgba(8, 13, 20, 0.9);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(56,189,248,0.08);
        }

        .sidebar {
            background: rgba(14, 20, 32, 0.95);
            border-right: 1px solid rgba(51,65,85,0.4);
            width: 260px;
            min-height: calc(100vh - 64px);
            position: sticky;
            top: 64px;
            height: calc(100vh - 64px);
            overflow-y: auto;
            flex-shrink: 0;
        }
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 4px; }

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

        .search-input {
            background: rgba(30,41,59,0.8);
            border: 1.5px solid rgba(51,65,85,0.8);
            color: #e2e8f0; transition: all 0.25s;
        }
        .search-input::placeholder { color: #475569; }
        .search-input:focus {
            border-color: #38bdf8;
            box-shadow: 0 0 0 3px rgba(56,189,248,0.12);
            outline: none;
        }

        .cat-pill {
            background: rgba(30,41,59,0.7);
            border: 1.5px solid rgba(51,65,85,0.6);
            color: #94a3b8; cursor: pointer;
            transition: all 0.2s; white-space: nowrap;
        }
        .cat-pill:hover, .cat-pill.active {
            background: #38bdf8; border-color: #38bdf8;
            color: #080d14; font-weight: 700;
        }

        .menu-card {
            background: rgba(20,30,48,0.85);
            border: 1px solid rgba(51,65,85,0.5);
            transition: all 0.28s cubic-bezier(0.34,1.56,0.64,1);
        }
        .menu-card:hover {
            transform: translateY(-5px) scale(1.01);
            border-color: rgba(56,189,248,0.3);
            box-shadow: 0 20px 40px rgba(0,0,0,0.5), 0 0 0 1px rgba(56,189,248,0.1);
        }

        .btn-add {
            background: #000; color: #c7c7c7;
            border: 1px solid rgba(56,189,248,0.2);
            transition: all 0.2s;
        }
        .btn-add:hover {
            background: #38bdf8; color: #080d14;
            border-color: #38bdf8; font-weight: 700;
        }

        .elite-badge {
            background: linear-gradient(135deg, #f59e0b, #fbbf24);
            color: #080d14;
        }

        .cat-heading::after {
            content: ''; display: block;
            width: 40px; height: 3px;
            background: #38bdf8; border-radius: 2px; margin-top: 6px;
        }

        .menu-card { animation: fadeUp 0.4s ease both; }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .stat-card {
            background: rgba(20,30,48,0.7);
            border: 1px solid rgba(51,65,85,0.4);
            border-radius: 12px; padding: 14px 16px;
        }

        .avatar {
            background: linear-gradient(135deg, #38bdf8, #6366f1);
            font-family: 'Syne', sans-serif; font-weight: 700;
        }

        #sidebar-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.6); z-index: 40;
            backdrop-filter: blur(2px);
        }
        #sidebar-overlay.open { display: block; }
        #mobile-sidebar {
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }
        #mobile-sidebar.open { transform: translateX(0); }

        /* Toast notification */
        .toast {
            position: fixed; bottom: 24px; right: 24px; z-index: 9999;
            background: #38bdf8; color: #080d14;
            font-family: 'Syne', sans-serif; font-weight: 700;
            font-size: 14px; padding: 12px 20px;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(56,189,248,0.3);
            animation: toastIn 0.3s ease, toastOut 0.3s ease 2.2s forwards;
            pointer-events: none;
        }
        @keyframes toastIn {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes toastOut {
            from { opacity: 1; transform: translateY(0); }
            to   { opacity: 0; transform: translateY(16px); }
        }

        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #080d14; }
        ::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 4px; }
    </style>
</head>

<body class="min-h-screen text-slate-200">

<div class="bg-glow"></div>
<div class="bg-glow-2"></div>

<!-- ===== NAVBAR ===== -->
<nav class="navbar-glass sticky top-0 z-50 h-16">
    <div class="h-full px-4 sm:px-6 flex items-center justify-between gap-4">

        <!-- Left: hamburger + logo -->
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

        <!-- Center: search -->
        <form method="GET" action="" class="flex-1 max-w-lg">
            <div class="relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                </svg>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                    placeholder="Search dishes, categories..."
                    class="search-input w-full pl-10 pr-4 py-2.5 rounded-xl text-sm">
                <?php if ($category_filter): ?>
                    <input type="hidden" name="category" value="<?= htmlspecialchars($category_filter) ?>">
                <?php endif; ?>
            </div>
        </form>

        <!-- Right: cart icon + elite badge + avatar -->
        <div class="flex items-center gap-3">

            <!-- Cart icon with badge -->
            <a href="cart.php" class="relative text-slate-400 hover:text-sky-400 transition p-1.5">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                <span id="cart-badge"
                    class="absolute -top-1 -right-1 bg-sky-400 text-slate-900 text-xs font-800 w-5 h-5 rounded-full items-center justify-center syne hidden flex">
                    0
                </span>
            </a>

            <?php if ($is_elite): ?>
                <span class="elite-badge hidden sm:inline text-xs font-700 px-3 py-1 rounded-full syne">⭐ Elite</span>
            <?php endif; ?>

            <div class="avatar w-9 h-9 rounded-full flex items-center justify-center text-sm text-white">
                <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
            </div>
        </div>

    </div>
</nav>

<!-- ===== LAYOUT ===== -->
<div class="relative z-10 flex">

    <div id="sidebar-overlay" onclick="toggleSidebar()"></div>

    <!-- Desktop sidebar -->
    <aside class="sidebar hidden lg:block" id="desktop-sidebar">
        <?php include 'side-bar.php'; ?>
    </aside>

    <!-- Mobile sidebar -->
    <aside id="mobile-sidebar" class="fixed top-16 left-0 z-50 sidebar lg:hidden"
        style="min-height: calc(100vh - 64px); height: calc(100vh - 64px);">
        <?php include 'side-bar.php'; ?>
    </aside>

    <!-- ===== MAIN ===== -->
    <main class="flex-1 min-w-0 px-4 sm:px-6 lg:px-8 py-8">

        <!-- Category pills -->
        <div class="flex gap-2 overflow-x-auto pb-3 mb-6">
            <a href="?<?= $search ? 'search='.urlencode($search) : '' ?>"
               class="cat-pill px-4 py-2 rounded-full text-sm <?= $category_filter === '' ? 'active' : '' ?>">All</a>
            <?php foreach ($categories as $cat): ?>
                <a href="?category=<?= urlencode($cat) ?><?= $search ? '&search='.urlencode($search) : '' ?>"
                   class="cat-pill px-4 py-2 rounded-full text-sm <?= $category_filter === $cat ? 'active' : '' ?>">
                    <?= htmlspecialchars($cat) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Search notice -->
        <?php if ($search): ?>
            <div class="mb-5 flex items-center gap-3 text-sm text-slate-400">
                <span>Results for <span class="text-sky-400 font-600">"<?= htmlspecialchars($search) ?>"</span></span>
                <a href="home.php" class="text-slate-500 hover:text-red-400 transition text-xs border border-slate-700 px-2 py-1 rounded-lg">✕ Clear</a>
            </div>
        <?php endif; ?>

        <!-- Menu -->
        <?php if (empty($menu)): ?>
            <div class="text-center py-24 text-slate-500">
                <div class="text-5xl mb-4">🍽️</div>
                <h3 class="syne text-xl text-slate-400 mb-2">Nothing found</h3>
                <p class="text-sm">Try a different search or category</p>
                <a href="home.php" class="inline-block mt-4 text-sky-400 hover:underline text-sm">← Back to full menu</a>
            </div>
        <?php else: ?>
            <?php foreach ($menu as $category => $cat_items): ?>
                <div class="mb-10">
                    <h3 class="syne cat-heading text-lg font-700 text-white mb-5"><?= htmlspecialchars($category) ?></h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">
                        <?php foreach ($cat_items as $i => $item): ?>
                            <div class="menu-card rounded-2xl overflow-hidden" style="animation-delay:<?= $i*0.05 ?>s">
                                <div class="h-1" style="background: linear-gradient(90deg,#38bdf8,#6366f1)"></div>
                                <div class="p-5">
                                    <div class="flex justify-between items-start mb-2">
                                        <h4 class="syne font-700 text-white text-base leading-tight flex-1 pr-2"><?= htmlspecialchars($item['name']) ?></h4>
                                        <?php if ($is_elite): ?>
                                            <span class="text-xs bg-amber-400/20 text-amber-400 px-2 py-0.5 rounded-full whitespace-nowrap">-10%</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-slate-400 text-xs leading-relaxed mb-5 min-h-[40px]"><?= htmlspecialchars($item['description']) ?></p>
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <?php if ($is_elite): ?>
                                                <span class="text-slate-500 text-xs line-through"><?= number_format($item['price'], 0) ?></span>
                                                <span class="text-sky-400 font-700 text-lg ml-1"><?= number_format($item['price']*0.9, 0) ?> <span class="text-xs font-400">EGP</span></span>
                                            <?php else: ?>
                                                <span class="text-sky-400 font-700 text-lg"><?= number_format($item['price'], 0) ?> <span class="text-xs font-400 text-slate-400">EGP</span></span>
                                            <?php endif; ?>
                                        </div>
                                        <!-- ✅ UPDATED button — uses localStorage approach -->
                                        <button
                                            onclick="addToCart(<?= $item['id'] ?>, '<?= addslashes(htmlspecialchars($item['name'], ENT_QUOTES)) ?>', <?= $item['price'] ?>)"
                                            class="btn-add text-xs font-600 px-4 py-2 rounded-xl">
                                            + Add
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </main>
</div>

<script>
// ── Cart functions ─────────────────────────────────────────
function getCart() {
    return JSON.parse(localStorage.getItem('hw_cart') || '[]');
}

function saveCart(cart) {
    localStorage.setItem('hw_cart', JSON.stringify(cart));
}

function addToCart(id, name, price) {
    let cart = getCart();
    const existing = cart.find(i => i.id === id);
    if (existing) {
        existing.qty += 1;
    } else {
        cart.push({ id: id, name: name, price: price, qty: 1 });
    }
    saveCart(cart);
    updateCartBadge();
    showToast('🛒 ' + name + ' added!');
}

function updateCartBadge() {
    const cart = getCart();
    const total = cart.reduce((sum, i) => sum + i.qty, 0);
    const badge = document.getElementById('cart-badge');
    if (!badge) return;
    badge.textContent = total;
    if (total > 0) {
        badge.classList.remove('hidden');
        badge.classList.add('flex');
    } else {
        badge.classList.add('hidden');
        badge.classList.remove('flex');
    }
}

function showToast(msg) {
    // Remove any existing toast
    const old = document.getElementById('hw-toast');
    if (old) old.remove();

    const t = document.createElement('div');
    t.id = 'hw-toast';
    t.className = 'toast';
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 2500);
}

// ── Sidebar toggle ─────────────────────────────────────────
function toggleSidebar() {
    document.getElementById('mobile-sidebar').classList.toggle('open');
    document.getElementById('sidebar-overlay').classList.toggle('open');
}

// ── Init ───────────────────────────────────────────────────
updateCartBadge();
</script>

</body>
</html>