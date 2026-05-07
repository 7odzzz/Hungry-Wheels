<?php
session_start();           //    only if auth_guard.php isn't included first
require_once('../auth_guard.php');
guard('admin');             // redirects if not logged in
inject_bfcache_killer();

require '../db.php';

// Stats
$total_users   = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$total_orders  = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$total_revenue = $pdo->query("SELECT SUM(total_price) FROM orders")->fetchColumn();
$total_items   = $pdo->query("SELECT COUNT(*) FROM menu_items")->fetchColumn();
$pending_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();

// Recent orders
$recent_orders = $pdo->query("
    SELECT o.*, u.full_name 
    FROM orders o 
    JOIN users u ON u.id = o.user_id 
    ORDER BY o.created_at DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$status_config = [
    'pending'    => ['label'=>'Pending',    'color'=>'text-amber-400',  'bg'=>'bg-amber-400/10  border-amber-400/30',  'icon'=>'🕐'],
    'preparing'  => ['label'=>'Preparing',  'color'=>'text-blue-400',   'bg'=>'bg-blue-400/10   border-blue-400/30',   'icon'=>'👨‍🍳'],
    'on the way' => ['label'=>'On the Way', 'color'=>'text-purple-400', 'bg'=>'bg-purple-400/10 border-purple-400/30', 'icon'=>'🛵'],
    'delivered'  => ['label'=>'Delivered',  'color'=>'text-green-400',  'bg'=>'bg-green-400/10  border-green-400/30',  'icon'=>'✅'],
];
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — Hungry Wheels</title>
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
            width: 260px;
            min-height: calc(100vh - 64px);
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
        .stat-card {
            background: rgba(20,30,48,0.85);
            border: 1px solid rgba(51,65,85,0.5);
            border-radius: 16px; padding: 20px;
            animation: fadeUp 0.4s ease both;
            transition: all 0.25s;
        }
        .stat-card:hover {
            border-color: rgba(168,85,247,0.25);
            transform: translateY(-3px);
        }
        .card {
            background: rgba(20,30,48,0.85);
            border: 1px solid rgba(51,65,85,0.5);
            border-radius: 16px;
            animation: fadeUp 0.4s ease both;
        }
        .avatar { background: linear-gradient(135deg,#a855f7,#7c3aed); }
        @keyframes fadeUp {
            from { opacity:0; transform:translateY(16px); }
            to   { opacity:1; transform:translateY(0); }
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
            background:radial-gradient(circle,rgba(168,85,247,0.06) 0%,transparent 70%);
            pointer-events:none; z-index:0;
            animation:floatGlow 8s ease-in-out infinite;
        }
        @keyframes floatGlow {
            0%,100%{transform:translate(0,0);}
            50%{transform:translate(40px,40px);}
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
            <button onclick="toggleSidebar()" class="text-slate-400 hover:text-purple-400 p-1.5 rounded-lg hover:bg-slate-800 transition lg:hidden">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
            <a href="dashboard.php" class="syne text-lg font-800 text-purple-400 tracking-tight flex items-center gap-2">
                🔐 <span>Admin Panel</span>
            </a>
        </div>
                <!--avatar bar-->
        <div class="flex items-center gap-3">
    <span class="hidden sm:inline text-xs font-700 px-3 py-1 rounded-full syne"
        style="background:linear-gradient(135deg,#a855f7,#7c3aed);color:#fff">
        👑 Admin
    </span>
    
    <a href="Admin-profile.php" class="flex items-center gap-2 hover:opacity-80 transition group">
        <div class="avatar w-9 h-9 rounded-full flex items-center justify-center text-sm text-white font-700">
            <?= strtoupper(substr($_SESSION["admin_name"], 0, 1)) ?>
        </div>
        <span class="hidden sm:inline text-sm text-slate-300 group-hover:text-purple-400 transition syne font-600">
            <?= htmlspecialchars($_SESSION["admin_name"]) ?>
        </span>
    </a>
</div>
            
        </div>
    </div>
</nav>

<div class="relative z-10 flex">
    <div id="sidebar-overlay" onclick="toggleSidebar()"></div>

    <!-- Desktop sidebar -->
    <aside class="sidebar hidden lg:block">
        <?php include 'Admin-sidebar.php'; ?>
    </aside>
    <!-- Mobile sidebar -->
    <aside id="mobile-sidebar" class="fixed top-16 left-0 z-50 sidebar lg:hidden"
        style="min-height:calc(100vh - 64px);height:calc(100vh - 64px);">
        <?php include 'Admin-sidebar.php'; ?>
    </aside>

    <!-- MAIN -->
    <main class="flex-1 min-w-0 px-4 sm:px-6 lg:px-8 py-8">

        <!-- Header -->
        <div class="mb-8">
            <h2 class="syne text-3xl font-800 text-white mb-1">Dashboard</h2>
            <p class="text-slate-400 text-sm">Welcome back, <?= htmlspecialchars($_SESSION["admin_name"]) ?>!</p>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="stat-card" style="animation-delay:0s">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-slate-500 text-xs font-600 uppercase tracking-wide">Total Users</span>
                    <span class="text-2xl">👥</span>
                </div>
                <div class="syne text-3xl font-800 text-purple-400"><?= $total_users ?></div>
                <div class="text-slate-500 text-xs mt-1">Registered customers</div>
            </div>
            <div class="stat-card" style="animation-delay:0.07s">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-slate-500 text-xs font-600 uppercase tracking-wide">Total Orders</span>
                    <span class="text-2xl">📦</span>
                </div>
                <div class="syne text-3xl font-800 text-sky-400"><?= $total_orders ?></div>
                <div class="text-slate-500 text-xs mt-1">
                    <span class="text-amber-400"><?= $pending_orders ?> pending</span>
                </div>
            </div>
            <div class="stat-card" style="animation-delay:0.14s">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-slate-500 text-xs font-600 uppercase tracking-wide">Revenue</span>
                    <span class="text-2xl">💰</span>
                </div>
            <div class="text-3xl font-bold text-green-400" 
                style="font-family: 'DM Sans', sans-serif;">
                <?= number_format($total_revenue ?? 0, 0) ?>
            </div>
                <div class="text-slate-500 text-xs mt-1">EGP total</div>
            </div>
            <div class="stat-card" style="animation-delay:0.21s">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-slate-500 text-xs font-600 uppercase tracking-wide">Menu Items</span>
                    <span class="text-2xl">🍔</span>
                </div>
                <div class="syne text-3xl font-800 text-amber-400"><?= $total_items ?></div>
                <div class="text-slate-500 text-xs mt-1">Active dishes</div>
            </div>
        </div>

        <!-- Quick actions -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-8">
            <a href="AdminMenu.php" class="card p-4 text-center hover:border-purple-400/40 transition cursor-pointer group">
                <div class="text-3xl mb-2">🍽️</div>
                <div class="syne text-sm font-700 text-slate-300 group-hover:text-purple-400 transition">Manage Menu</div>
            </a>


            <a href="AdminOrders.php" class="card p-4 text-center hover:border-purple-400/40 transition cursor-pointer group">
                <div class="text-3xl mb-2">📋</div>
                <div class="syne text-sm font-700 text-slate-300 group-hover:text-purple-400 transition">View Orders</div>
            </a>


            <a href="AdminUsers.php" class="card p-4 text-center hover:border-purple-400/40 transition cursor-pointer group">
                <div class="text-3xl mb-2">👥</div>
                <div class="syne text-sm font-700 text-slate-300 group-hover:text-purple-400 transition">View Users</div>
            </a>
            
            <a href="AdminMenu.php?action=add" class="card p-4 text-center hover:border-purple-400/40 transition cursor-pointer group">
                <div class="text-3xl mb-2">➕</div>
                <div class="syne text-sm font-700 text-slate-300 group-hover:text-purple-400 transition">Add Item</div>
            </a>
        </div>

        <!-- Recent orders -->
        <div class="card p-6">
            <div class="flex items-center justify-between mb-5">
                <h3 class="syne font-800 text-white text-lg">Recent Orders</h3>
                <a href="AdminOrders.php" class="text-purple-400 hover:underline text-sm">View all →</a>
            </div>
            <?php if (empty($recent_orders)): ?>
                <div class="text-center py-10 text-slate-500">No orders yet</div>
            <?php else: ?>
                <div class="space-y-3">
                    
                    
                    <?php foreach ($recent_orders as $order):
                        $s = $status_config[$order['status']] ?? $status_config['pending'];
                    ?>
                    <div class="flex items-center justify-between p-3 bg-slate-900/40 rounded-xl">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-purple-400/10 flex items-center justify-center text-sm font-700 text-purple-400 syne">
                                #<?= $order['id'] ?>
                            </div>
                            <div>
                                <div class="text-slate-300 text-sm font-600"><?= htmlspecialchars($order['full_name']) ?></div>
                                <div class="text-slate-500 text-xs"><?= date('d M Y · h:i A', strtotime($order['created_at'])) ?></div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-white font-700 text-sm syne"><?= number_format($order['total_price'], 0) ?> EGP</span>
                            <span class="text-xs font-700 px-2.5 py-1 rounded-full border <?= $s['bg'] ?> <?= $s['color'] ?>">
                                <?= $s['icon'] ?> <?= $s['label'] ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>


            <?php endif; ?>
        </div>

    </main>
</div>


<!--java script usage-->
<script>
function toggleSidebar() {
    document.getElementById('mobile-sidebar').classList.toggle('open');
    document.getElementById('sidebar-overlay').classList.toggle('open');
}
</script>



</body>
</html>