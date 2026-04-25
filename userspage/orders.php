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

// Fetch all orders for this user, newest first
$orders_stmt = $pdo->prepare("
    SELECT o.*, 
           COUNT(oi.id) as item_count
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id = o.id
    WHERE o.user_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$orders_stmt->execute([$_SESSION["user_id"]]);
$orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);

// Status config
$status_config = [
    'pending'    => ['label' => 'Pending',    'color' => 'text-amber-400',  'bg' => 'bg-amber-400/10  border-amber-400/30',  'icon' => '🕐'],
    'preparing'  => ['label' => 'Preparing',  'color' => 'text-blue-400',   'bg' => 'bg-blue-400/10   border-blue-400/30',   'icon' => '👨‍🍳'],
    'on the way' => ['label' => 'On the Way', 'color' => 'text-purple-400', 'bg' => 'bg-purple-400/10 border-purple-400/30', 'icon' => '🛵'],
    'delivered'  => ['label' => 'Delivered',  'color' => 'text-green-400',  'bg' => 'bg-green-400/10  border-green-400/30',  'icon' => '✅'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders — Hungry Wheels</title>
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

        .order-card {
            background: rgba(20,30,48,0.85);
            border: 1px solid rgba(51,65,85,0.5);
            transition: all 0.25s;
            animation: fadeUp 0.4s ease both;
        }
        .order-card:hover {
            border-color: rgba(56,189,248,0.25);
            box-shadow: 0 8px 32px rgba(0,0,0,0.4);
        }
        @keyframes fadeUp {
            from { opacity:0; transform:translateY(16px); }
            to   { opacity:1; transform:translateY(0); }
        }

        /* Progress bar track */
        .progress-track {
            background: rgba(30,41,59,0.8);
            border-radius: 999px;
            height: 6px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            border-radius: 999px;
            transition: width 0.6s ease;
        }

        /* Order items drawer */
        .items-drawer {
            display: none;
        }
        .items-drawer.open {
            display: block;
        }
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
                <span class="hidden sm:inline text-xs font-700 px-3 py-1 rounded-full syne"
                    style="background:linear-gradient(135deg,#f59e0b,#fbbf24);color:#080d14">⭐ Elite</span>
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
    <aside id="mobile-sidebar" class="fixed top-16 left-0 z-50 sidebar lg:hidden"
        style="min-height:calc(100vh - 64px);height:calc(100vh - 64px);">
        <?php include 'side-bar.php'; ?>
    </aside>

    <!-- MAIN -->
    <main class="flex-1 min-w-0 px-4 sm:px-6 lg:px-8 py-8">
        <div class="max-w-4xl mx-auto">

            <!-- Header -->
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h2 class="syne text-3xl font-800 text-white mb-1">My Orders</h2>
                    <p class="text-slate-400 text-sm">Track and review all your past orders</p>
                </div>
                <a href="home.php"
                    class="bg-sky-400 hover:bg-sky-300 text-slate-900 font-700 text-sm px-4 py-2.5 rounded-xl transition syne">
                    + New Order
                </a>
            </div>

            <!-- Stats row -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-8">
                <?php
                $total_spent = array_sum(array_column($orders, 'total_price'));
                $delivered   = count(array_filter($orders, fn($o) => $o['status'] === 'delivered'));
                $pending     = count(array_filter($orders, fn($o) => in_array($o['status'], ['pending','preparing','on the way'])));
                ?>
                <div class="stat-card text-center">
                    <div class="syne text-2xl font-800 text-sky-400"><?= count($orders) ?></div>
                    <div class="text-slate-500 text-xs mt-1">Total Orders</div>
                </div>
                <div class="stat-card text-center">
                    <div class="syne text-2xl font-800 text-green-400"><?= $delivered ?></div>
                    <div class="text-slate-500 text-xs mt-1">Delivered</div>
                </div>
                <div class="stat-card text-center">
                    <div class="syne text-2xl font-800 text-amber-400"><?= $pending ?></div>
                    <div class="text-slate-500 text-xs mt-1">In Progress</div>
                </div>
                <div class="stat-card text-center">
                    <div class="syne text-xl font-800 text-indigo-400"><?= number_format($total_spent, 0) ?></div>
                    <div class="text-slate-500 text-xs mt-1">EGP Spent</div>
                </div>
            </div>

            <!-- Orders list -->
            <?php if (empty($orders)): ?>
                <div class="text-center py-24">
                    <div class="text-6xl mb-4">📦</div>
                    <h3 class="syne text-xl text-slate-400 mb-2">No orders yet</h3>
                    <p class="text-slate-500 text-sm mb-6">Your order history will appear here</p>
                    <a href="home.php"
                        class="inline-block bg-sky-400 text-slate-900 font-700 px-6 py-3 rounded-xl hover:bg-sky-300 transition syne">
                        Browse Menu
                    </a>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($orders as $idx => $order):
                        $s = $status_config[$order['status']] ?? $status_config['pending'];

                        // Progress % per status
                        $progress = match($order['status']) {
                            'pending'    => 15,
                            'preparing'  => 45,
                            'on the way' => 75,
                            'delivered'  => 100,
                            default      => 0
                        };

                        // Progress bar color
                        $bar_color = match($order['status']) {
                            'pending'    => '#f59e0b',
                            'preparing'  => '#60a5fa',
                            'on the way' => '#a78bfa',
                            'delivered'  => '#4ade80',
                            default      => '#38bdf8'
                        };

                        // Fetch items for this order
                        $items_stmt = $pdo->prepare("
                            SELECT oi.quantity, oi.unit_price, mi.name
                            FROM order_items oi
                            JOIN menu_items mi ON mi.id = oi.menu_item_id
                            WHERE oi.order_id = ?
                        ");
                        $items_stmt->execute([$order['id']]);
                        $order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <div class="order-card rounded-2xl overflow-hidden" style="animation-delay:<?= $idx * 0.07 ?>s">

                        <!-- Top accent bar -->
                        <div class="h-0.5 w-full" style="background: <?= $bar_color ?>; opacity: 0.6;"></div>

                        <div class="p-5">
                            <!-- Order header -->
                            <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                                <div>
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="syne font-800 text-white text-base">Order #<?= $order['id'] ?></span>
                                        <span class="text-slate-600 text-xs">·</span>
                                        <span class="text-slate-400 text-xs"><?= $order['item_count'] ?> item<?= $order['item_count'] != 1 ? 's' : '' ?></span>
                                    </div>
                                    <div class="text-slate-500 text-xs">
                                        <?= date('D, d M Y · h:i A', strtotime($order['created_at'])) ?>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="syne font-800 text-white text-lg">
                                        <?= number_format($order['total_price'], 0) ?>
                                        <span class="text-xs font-400 text-slate-400">EGP</span>
                                    </span>
                                    <?php if ($order['is_elite_discount']): ?>
                                        <span class="text-xs bg-amber-400/15 text-amber-400 border border-amber-400/25 px-2 py-0.5 rounded-full">⭐ -10%</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Status badge + progress -->
                            <div class="mb-4">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="inline-flex items-center gap-1.5 text-xs font-700 px-3 py-1.5 rounded-full border <?= $s['bg'] ?> <?= $s['color'] ?>">
                                        <?= $s['icon'] ?> <?= $s['label'] ?>
                                    </span>
                                    <span class="text-slate-500 text-xs"><?= $progress ?>%</span>
                                </div>
                                <div class="progress-track">
                                    <div class="progress-fill" style="width:<?= $progress ?>%; background:<?= $bar_color ?>;"></div>
                                </div>

                                <!-- Status steps -->
                                <div class="flex justify-between mt-2">
                                    <?php foreach (['Pending','Preparing','On the Way','Delivered'] as $step):
                                        $steps_map = ['Pending'=>'pending','Preparing'=>'preparing','On the Way'=>'on the way','Delivered'=>'delivered'];
                                        $step_statuses = array_keys($status_config);
                                        $current_idx = array_search($order['status'], $step_statuses);
                                        $step_idx = array_search($steps_map[$step], $step_statuses);
                                        $done = $step_idx <= $current_idx;
                                    ?>
                                        <span class="text-xs <?= $done ? 'text-slate-300' : 'text-slate-600' ?>">
                                            <?= $step ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Address -->
                            <div class="flex items-start gap-2 mb-4 text-xs text-slate-400">
                                <span class="mt-0.5">📍</span>
                                <span><?= htmlspecialchars($order['address']) ?></span>
                            </div>

                            <!-- Toggle items button -->
                            <button onclick="toggleItems(<?= $order['id'] ?>)"
                                class="text-xs text-sky-400 hover:text-sky-300 transition flex items-center gap-1 mb-1">
                                <span id="toggle-label-<?= $order['id'] ?>">▶ Show items</span>
                            </button>

                            <!-- Items drawer -->
                            <div id="drawer-<?= $order['id'] ?>" class="items-drawer">
                                <div class="mt-3 pt-3 border-t border-slate-700/50 space-y-2">
                                    <?php foreach ($order_items as $oi): ?>
                                        <div class="flex justify-between items-center text-sm">
                                            <div class="flex items-center gap-2">
                                                <span class="text-slate-500 text-xs w-5 text-center">×<?= $oi['quantity'] ?></span>
                                                <span class="text-slate-300"><?= htmlspecialchars($oi['name']) ?></span>
                                            </div>
                                            <span class="text-sky-400 font-600 text-xs">
                                                <?= number_format($oi['unit_price'] * $oi['quantity'], 0) ?> EGP
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
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


function toggleItems(orderId) {
    const drawer = document.getElementById('drawer-' + orderId);
    const label  = document.getElementById('toggle-label-' + orderId);
    const isOpen = drawer.classList.contains('open');
    drawer.classList.toggle('open');
    label.textContent = isOpen ? '▶ Show items' : '▼ Hide items';
    console.log('toggled order', orderId, 'open:', !isOpen); // debug
}
function toggleSidebar() {
    document.getElementById('mobile-sidebar').classList.toggle('open');
    document.getElementById('sidebar-overlay').classList.toggle('open');
}
</script>
</body>
</html>