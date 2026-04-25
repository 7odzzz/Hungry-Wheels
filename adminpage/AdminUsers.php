<?php
session_start();
if (!isset($_SESSION["admin_id"])) {
    header("Location: /HungryWheels/login.php");
    exit();
}
require '../db.php';

$search = trim($_GET['search'] ?? '');
$filter = $_GET['filter'] ?? '';

$sql    = "SELECT u.*, COUNT(o.id) as order_count, SUM(o.total_price) as total_spent FROM users u LEFT JOIN orders o ON o.user_id = u.id WHERE u.role = 'user'";
$params = [];

if ($search !== '') {
    $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filter === 'elite') {
    $sql .= " AND u.is_elite = 1";
}

$sql .= " GROUP BY u.id ORDER BY u.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_users  = $pdo->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$elite_users  = $pdo->query("SELECT COUNT(*) FROM users WHERE role='user' AND is_elite=1")->fetchColumn();
$total_revenue = $pdo->query("SELECT SUM(total_price) FROM orders")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users — Hungry Wheels Admin</title>
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
        .stat-card {
            background: rgba(20,30,48,0.85);
            border: 1px solid rgba(51,65,85,0.5);
            border-radius: 14px; padding: 18px; text-align: center;
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
        .user-card {
            background: rgba(15,23,42,0.7);
            border: 1px solid rgba(51,65,85,0.4);
            border-radius: 14px; padding: 16px;
            transition: all 0.2s;
            animation: fadeUp 0.35s ease both;
        }
        .user-card:hover { border-color: rgba(168,85,247,0.25); }
        @keyframes fadeUp {
            from { opacity:0; transform:translateY(12px); }
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
        <?php include 'Admin-sidebar.php'; ?>
    </aside>
    <aside id="mobile-sidebar" class="fixed top-16 left-0 z-50 sidebar lg:hidden"
        style="min-height:calc(100vh - 64px);height:calc(100vh - 64px);">
        <?php include 'Admin-sidebar.php'; ?>
    </aside>
    <main class="flex-1 min-w-0 px-4 sm:px-6 lg:px-8 py-8">
        <div class="max-w-6xl mx-auto">

          
        
        <!-- Header -->
            <div class="mb-6">
                <h2 class="syne text-3xl font-800 text-white mb-1">Users</h2>
                <p class="text-slate-400 text-sm">All registered customers</p>
            </div>

            
            
            
            <!-- Stats -->
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="stat-card">
                    <div class="syne text-2xl font-800 text-purple-400"><?= $total_users ?></div>
                    <div class="text-slate-500 text-xs mt-1">Total Users</div>
                </div>
                <div class="stat-card">
                    <div class="syne text-2xl font-800 text-amber-400"><?= $elite_users ?></div>
                    <div class="text-slate-500 text-xs mt-1">Elite Members</div>
                </div>
                <div class="stat-card">
                    <div class="syne text-2xl font-800 text-green-400"><?= number_format($total_revenue ?? 0, 0) ?></div>
                    <div class="text-slate-500 text-xs mt-1">EGP Revenue</div>
                </div>
            </div>

          
          
          
            <!-- Search + filter -->
            <div class="flex flex-col sm:flex-row gap-3 mb-4">
                <form method="GET" action="" class="flex-1">
                    <div class="relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                        </svg>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                            placeholder="Search by name, email or phone..."
                            class="search-input w-full pl-10 pr-4 py-2.5 rounded-xl text-sm">
                        <?php if ($filter): ?>
                            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                        <?php endif; ?>
                    </div>
                </form>
            </div>

          
          
            <!-- Filter pills -->
            <div class="flex gap-2 mb-5">
                <a href="AdminUsers.php<?= $search ? '?search='.urlencode($search) : '' ?>"
                   class="filter-pill <?= $filter === '' ? 'active' : '' ?>">All Users</a>
                <a href="?filter=elite<?= $search ? '&search='.urlencode($search) : '' ?>"
                   class="filter-pill <?= $filter === 'elite' ? 'active' : '' ?>">⭐ Elite Only</a>
            </div>

           
           
            <!-- Users list -->
            <?php if (empty($users)): ?>
                <div class="text-center py-20 text-slate-500">
                    <div class="text-5xl mb-3">👥</div>
                    <p>No users found</p>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($users as $idx => $u): ?>
                    <div class="user-card" style="animation-delay:<?= $idx * 0.04 ?>s">
                        <div class="flex flex-wrap items-center justify-between gap-3">

                            <!-- Avatar + info -->
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-800 text-white syne flex-shrink-0"
                                    style="background:linear-gradient(135deg,#a855f7,#7c3aed)">
                                    <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="syne font-700 text-white text-sm"><?= htmlspecialchars($u['full_name']) ?></span>
                                        <?php if ($u['is_elite']): ?>
                                            <span class="text-xs font-700 px-2 py-0.5 rounded-full syne"
                                                style="background:linear-gradient(135deg,#f59e0b,#fbbf24);color:#080d14">⭐ Elite</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-slate-500 text-xs"><?= htmlspecialchars($u['email']) ?></div>
                                    <?php if ($u['phone']): ?>
                                        <div class="text-slate-600 text-xs">📞 <?= htmlspecialchars($u['phone']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Stats -->
                            <div class="flex items-center gap-4">
                                <div class="text-center">
                                    <div class="syne font-800 text-sky-400 text-sm"><?= $u['order_count'] ?></div>
                                    <div class="text-slate-600 text-xs">Orders</div>
                                </div>
                                <div class="text-center">
                                    <div class="syne font-800 text-green-400 text-sm"><?= number_format($u['total_spent'] ?? 0, 0) ?></div>
                                    <div class="text-slate-600 text-xs">EGP Spent</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-slate-500 text-xs"><?= date('d M Y', strtotime($u['created_at'])) ?></div>
                                    <div class="text-slate-600 text-xs">Joined</div>
                                </div>
                            </div>

                        </div>

                        <!-- Address if available -->
                        <?php if ($u['address']): ?>
                            <div class="mt-2 text-xs text-slate-600 flex items-start gap-1">
                                <span>📍</span>
                                <span><?= htmlspecialchars($u['address']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </main>
</div>

<script>
function toggleSidebar() {
    document.getElementById('mobile-sidebar').classList.toggle('open');
    document.getElementById('sidebar-overlay').classList.toggle('open');
}
</script>
</body>
</html>