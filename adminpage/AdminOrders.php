<?php
session_start();
if (!isset($_SESSION["admin_id"])) {
    header("Location: /HungryWheels/login.php");
    exit();
}
require '../db.php';

$success = "";

// ── Update order status ───────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['order_id'], $_POST['status'])) {
    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$_POST['status'], intval($_POST['order_id'])]);
    $success = "Order #" . intval($_POST['order_id']) . " status updated.";
}

// ── Filters ───────────────────────────────────────────────
$status_filter = $_GET['status'] ?? '';
$search        = trim($_GET['search'] ?? '');

$sql    = "SELECT o.*, u.full_name, u.email FROM orders o JOIN users u ON u.id = o.user_id WHERE 1=1";
$params = [];

if ($status_filter !== '') {
    $sql .= " AND o.status = ?";
    $params[] = $status_filter;
}
if ($search !== '') {
    $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR o.id = ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = intval($search);
}

$sql .= " ORDER BY o.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$status_config = [
    'pending'    => ['label'=>'Pending',    'color'=>'text-amber-400',  'bg'=>'bg-amber-400/10  border-amber-400/30',  'icon'=>'🕐'],
    'preparing'  => ['label'=>'Preparing',  'color'=>'text-blue-400',   'bg'=>'bg-blue-400/10   border-blue-400/30',   'icon'=>'👨‍🍳'],
    'on the way' => ['label'=>'On the Way', 'color'=>'text-purple-400', 'bg'=>'bg-purple-400/10 border-purple-400/30', 'icon'=>'🛵'],
    'delivered'  => ['label'=>'Delivered',  'color'=>'text-green-400',  'bg'=>'bg-green-400/10  border-green-400/30',  'icon'=>'✅'],
];

// Stats
$total    = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$pending  = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();
$preparing = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='preparing'")->fetchColumn();
$onway    = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='on the way'")->fetchColumn();
$delivered = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='delivered'")->fetchColumn();
$revenue  = $pdo->query("SELECT SUM(total_price) FROM orders")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders — Hungry Wheels Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'DM Sans', sans-serif; }
        h1,h2,h3,h4,.syne { font-family: 'Syne', sans-serif; }
        body { background: #080d14; }
        .navbar-glass {
            background: rgba(8,13,20,0.9);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(168,85,247,0.1);
        }
        .sidebar {
            background: rgba(14,20,32,0.95);
            border-right: 1px solid rgba(51,65,85,0.4);
            width: 260px; min-height: calc(100vh - 64px);
            position: sticky; top: 64px;
            height: calc(100vh - 64px);
            overflow-y: auto; flex-shrink: 0;
        }
        .nav-item {
            display: flex; align-items: center; gap: 12px;
            padding: 11px 16px; border-radius: 10px;
            color: #94a3b8; font-size: 14px; font-weight: 500;
            cursor: pointer; transition: all 0.2s;
            text-decoration: none; margin: 2px 0;
        }
        .nav-item:hover { background: rgba(168,85,247,0.08); color: #e2e8f0; }
        .nav-item.active { background: rgba(168,85,247,0.12); color: #a855f7; font-weight: 600; }
        .nav-item .icon {
            width: 36px; height: 36px; border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px; background: rgba(30,41,59,0.8); flex-shrink: 0;
        }
        .nav-item.active .icon { background: rgba(168,85,247,0.15); }
        .sidebar-section {
            font-size: 10px; font-weight: 700; letter-spacing: 0.1em;
            color: #475569; text-transform: uppercase;
            padding: 4px 16px; margin-top: 20px; margin-bottom: 4px;
            font-family: 'Syne', sans-serif;
        }
        .card {
            background: rgba(20,30,48,0.85);
            border: 1px solid rgba(51,65,85,0.5);
            border-radius: 16px;
        }
        .stat-card {
            background: rgba(20,30,48,0.85);
            border: 1px solid rgba(51,65,85,0.5);
            border-radius: 14px; padding: 16px;
            text-align: center;
        }
        .search-input {
            background: rgba(30,41,59,0.8);
            border: 1.5px solid rgba(51,65,85,0.8);
            color: #e2e8f0; transition: all 0.25s;
        }
        .search-input::placeholder { color: #475569; }
        .search-input:focus {
            border-color: #a855f7;
            box-shadow: 0 0 0 3px rgba(168,85,247,0.12);
            outline: none;
        }
        .filter-pill {
            padding: 7px 16px; border-radius: 999px;
            font-size: 13px; font-weight: 600;
            border: 1.5px solid rgba(51,65,85,0.6);
            color: #94a3b8; background: rgba(30,41,59,0.7);
            cursor: pointer; transition: all 0.2s;
            text-decoration: none; white-space: nowrap;
        }
        .filter-pill:hover, .filter-pill.active {
            background: #a855f7; border-color: #a855f7; color: #fff;
        }
        .order-card {
            background: rgba(15,23,42,0.7);
            border: 1px solid rgba(51,65,85,0.4);
            border-radius: 14px; padding: 18px;
            transition: all 0.2s;
            animation: fadeUp 0.35s ease both;
        }
        .order-card:hover { border-color: rgba(168,85,247,0.25); }
        @keyframes fadeUp {
            from { opacity:0; transform:translateY(12px); }
            to   { opacity:1; transform:translateY(0); }
        }
        .status-select {
            background: rgba(30,41,59,0.9);
            border: 1.5px solid rgba(51,65,85,0.8);
            color: #e2e8f0; padding: 7px 12px;
            border-radius: 9px; font-size: 13px;
            outline: none; cursor: pointer;
            transition: all 0.2s; font-family: inherit;
        }
        .status-select:focus { border-color: #a855f7; }
        .btn-update {
            background: #a855f7; color: #fff;
            font-weight: 700; padding: 8px 16px;
            border-radius: 9px; border: none;
            cursor: pointer; transition: all 0.2s;
            font-size: 13px; font-family: 'Syne', sans-serif;
        }
        .btn-update:hover { background: #9333ea; }
        .items-drawer { display: none; }
        .items-drawer.open { display: block; }
        #sidebar-overlay {
            display:none; position:fixed; inset:0;
            background:rgba(0,0,0,0.6); z-index:40; backdrop-filter:blur(2px);
        }
        #sidebar-overlay.open { display:block; }
        #mobile-sidebar { transform:translateX(-100%); transition:transform 0.3s ease; }
        #mobile-sidebar.open { transform:translateX(0); }
        .bg-glow {
            position:fixed; top:-200px; left:200px; width:600px; height:600px;
            background:radial-gradient(circle,rgba(168,85,247,0.05) 0%,transparent 70%);
            pointer-events:none; z-index:0; animation:floatGlow 8s ease-in-out infinite;
        }
        @keyframes floatGlow { 0%,100%{transform:translate(0,0);} 50%{transform:translate(40px,40px);} }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #080d14; }
        ::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 4px; }
    </style>
</head>
<body class="min-h-screen text-slate-200">
<div class="bg-glow"></div>

<!-- NAVBAR -->
<nav class="navbar-glass sticky top-0 z-50 h-16">
    <div class="h-full px-4 sm:px-6 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <button onclick="toggleSidebar()" class="text-slate-400 hover:text-purple-400 p-1.5 rounded-lg hover:bg-slate-800 transition lg:hidden">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
            <a href="dashboard.php" class="syne text-lg font-800 text-purple-400 tracking-tight flex items-center gap-2">
                🔐 <span>Admin Panel</span>
            </a>
        </div>
        <div class="flex items-center gap-3">
            <span class="hidden sm:inline text-xs font-700 px-3 py-1 rounded-full syne"
                style="background:linear-gradient(135deg,#a855f7,#7c3aed);color:#fff">👑 Admin</span>
            <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm text-white font-700"
                style="background:linear-gradient(135deg,#a855f7,#7c3aed)">
                <?= strtoupper(substr($_SESSION["admin_name"], 0, 1)) ?>
            </div>
        </div>
    </div>
</nav>

<div class="relative z-10 flex">
    <div id="sidebar-overlay" onclick="toggleSidebar()"></div>
    <aside class="sidebar hidden lg:block">
        <?php include 'admin-sidebar.php'; ?>
    </aside>
    <aside id="mobile-sidebar" class="fixed top-16 left-0 z-50 sidebar lg:hidden"
        style="min-height:calc(100vh - 64px);height:calc(100vh - 64px);">
        <?php include 'admin-sidebar.php'; ?>
    </aside>

    <main class="flex-1 min-w-0 px-4 sm:px-6 lg:px-8 py-8">
        <div class="max-w-6xl mx-auto">

            <!-- Header -->
            <div class="mb-6">
                <h2 class="syne text-3xl font-800 text-white mb-1">Orders</h2>
                <p class="text-slate-400 text-sm">View and update all customer orders</p>
            </div>

            <!-- Success -->
            <?php if ($success): ?>
                <div class="bg-green-400/10 border border-green-400/30 text-green-400 px-4 py-3 rounded-xl text-sm font-600 mb-5">
                    ✅ <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-6">
                <div class="stat-card">
                    <div class="syne text-2xl font-800 text-white"><?= $total ?></div>
                    <div class="text-slate-500 text-xs mt-1">All Orders</div>
                </div>
                <div class="stat-card">
                    <div class="syne text-2xl font-800 text-amber-400"><?= $pending ?></div>
                    <div class="text-slate-500 text-xs mt-1">Pending</div>
                </div>
                <div class="stat-card">
                    <div class="syne text-2xl font-800 text-blue-400"><?= $preparing ?></div>
                    <div class="text-slate-500 text-xs mt-1">Preparing</div>
                </div>
                <div class="stat-card">
                    <div class="syne text-2xl font-800 text-purple-400"><?= $onway ?></div>
                    <div class="text-slate-500 text-xs mt-1">On the Way</div>
                </div>
                <div class="stat-card">
                    <div class="syne text-2xl font-800 text-green-400"><?= $delivered ?></div>
                    <div class="text-slate-500 text-xs mt-1">Delivered</div>
                </div>
            </div>

            <!-- Search + filter -->
            <div class="flex flex-col sm:flex-row gap-3 mb-5">
                <form method="GET" action="" class="flex-1">
                    <div class="relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                        </svg>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                            placeholder="Search by name, email or order ID..."
                            class="search-input w-full pl-10 pr-4 py-2.5 rounded-xl text-sm">
                        <?php if ($status_filter): ?>
                            <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Status filter pills -->
            <div class="flex gap-2 overflow-x-auto pb-2 mb-5">
                <a href="AdminOrders.php<?= $search ? '?search='.urlencode($search) : '' ?>"
                   class="filter-pill <?= $status_filter === '' ? 'active' : '' ?>">All</a>
                <a href="?status=pending<?= $search ? '&search='.urlencode($search) : '' ?>"
                   class="filter-pill <?= $status_filter === 'pending' ? 'active' : '' ?>">🕐 Pending</a>
                <a href="?status=preparing<?= $search ? '&search='.urlencode($search) : '' ?>"
                   class="filter-pill <?= $status_filter === 'preparing' ? 'active' : '' ?>">👨‍🍳 Preparing</a>
                <a href="?status=on+the+way<?= $search ? '&search='.urlencode($search) : '' ?>"
                   class="filter-pill <?= $status_filter === 'on the way' ? 'active' : '' ?>">🛵 On the Way</a>
                <a href="?status=delivered<?= $search ? '&search='.urlencode($search) : '' ?>"
                   class="filter-pill <?= $status_filter === 'delivered' ? 'active' : '' ?>">✅ Delivered</a>
            </div>

            <!-- Orders list -->
            <?php if (empty($orders)): ?>
                <div class="text-center py-20 text-slate-500">
                    <div class="text-5xl mb-3">📦</div>
                    <p>No orders found</p>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($orders as $idx => $order):
                        $s = $status_config[$order['status']] ?? $status_config['pending'];

                        // Fetch order items
                        $items_stmt = $pdo->prepare("
                            SELECT oi.quantity, oi.unit_price, mi.name
                            FROM order_items oi
                            JOIN menu_items mi ON mi.id = oi.menu_item_id
                            WHERE oi.order_id = ?
                        ");
                        $items_stmt->execute([$order['id']]);
                        $order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <div class="order-card" style="animation-delay:<?= $idx * 0.05 ?>s">

                        <!-- Order header -->
                        <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center text-sm font-800 text-purple-400 syne flex-shrink-0"
                                    style="background:rgba(168,85,247,0.1)">
                                    #<?= $order['id'] ?>
                                </div>
                                <div>
                                    <div class="syne font-700 text-white text-sm"><?= htmlspecialchars($order['full_name']) ?></div>
                                    <div class="text-slate-500 text-xs"><?= htmlspecialchars($order['email']) ?></div>
                                    <div class="text-slate-600 text-xs"><?= date('d M Y · h:i A', strtotime($order['created_at'])) ?></div>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <?php if ($order['is_elite_discount']): ?>
                                    <span class="text-xs bg-amber-400/15 text-amber-400 border border-amber-400/25 px-2 py-0.5 rounded-full">⭐ Elite</span>
                                <?php endif; ?>
                                    <span class="syne font-800 text-white"><?= number_format($order['total_price'], 0) ?> <span class="text-xs font-400 text-slate-400">EGP</span></span>
                                    <span class="text-xs font-700 px-2.5 py-1 rounded-full border <?= $s['bg'] ?> <?= $s['color'] ?>">
                                        <?= $s['icon'] ?> <?= $s['label'] ?>
                                </span>
                            </div>
                        </div>

                        <!-- Address -->
                        <div class="text-xs text-slate-500 mb-3 flex items-start gap-1">
                            <span>📍</span>
                            <span><?= htmlspecialchars($order['address']) ?></span>
                        </div>

                        <!-- Bottom row: items toggle + status update -->
                        <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-slate-700/40">
                            <button onclick="toggleItems(<?= $order['id'] ?>)"
                                class="text-xs text-sky-400 hover:text-sky-300 transition flex items-center gap-1">
                                <span id="toggle-label-<?= $order['id'] ?>">▶ Show items (<?= count($order_items) ?>)</span>
                            </button>

                            <!-- Status update form -->
                            <form method="POST" action="" class="flex items-center gap-2">
                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                <select name="status" class="status-select">
                                    <?php foreach ($status_config as $val => $cfg): ?>
                                        <option value="<?= $val ?>" <?= $order['status'] === $val ? 'selected' : '' ?>>
                                            <?= $cfg['icon'] ?> <?= $cfg['label'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn-update">Update</button>
                            </form>
                        </div>

                        <!-- Items drawer -->
                        <div id="drawer-<?= $order['id'] ?>" class="items-drawer">
                            <div class="mt-3 pt-3 border-t border-slate-700/30 space-y-1.5">
                                <?php foreach ($order_items as $oi): ?>
                                    <div class="flex justify-between text-sm">
                                        <div class="flex items-center gap-2">
                                            <span class="text-slate-500 text-xs">×<?= $oi['quantity'] ?></span>
                                            <span class="text-slate-300"><?= htmlspecialchars($oi['name']) ?></span>
                                        </div>
                                        <span class="text-sky-400 text-xs font-600"><?= number_format($oi['unit_price'] * $oi['quantity'], 0) ?> EGP</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </main>
</div>

<script>
function toggleItems(id) {
    const drawer = document.getElementById('drawer-' + id);
    const label  = document.getElementById('toggle-label-' + id);
    const count  = label.textContent.match(/\((\d+)\)/)?.[1] || '';
    const isOpen = drawer.classList.contains('open');
    drawer.classList.toggle('open');
    label.textContent = isOpen ? '▶ Show items (' + count + ')' : '▼ Hide items (' + count + ')';
}
function toggleSidebar() {
    document.getElementById('mobile-sidebar').classList.toggle('open');
    document.getElementById('sidebar-overlay').classList.toggle('open');
}
</script>
</body>
</html>