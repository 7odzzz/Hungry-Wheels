<?php
session_start();
require_once('../auth_guard.php');
guard('admin');
inject_bfcache_killer();

require '../db.php';

$success = "";
$error   = "";

// Fetch admin data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin'");
$stmt->execute([$_SESSION["admin_id"]]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// Count stats
$total_orders  = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$total_users   = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$total_revenue = $pdo->query("SELECT SUM(total_price) FROM orders")->fetchColumn();

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $full_name   = trim($_POST['full_name']);
    $new_pass    = $_POST['new_password'];
    $confirm     = $_POST['confirm_password'];

    if (empty($full_name)) {
        $error = "Full name cannot be empty.";
    } elseif (!empty($new_pass) && $new_pass !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (!empty($new_pass) && strlen($new_pass) < 8) {
        $error = "Password must be at least 8 characters.";
    } else {
        if (!empty($new_pass)) {
            $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET full_name=?, password=? WHERE id=?");
            $stmt->execute([$full_name, $hashed, $_SESSION["admin_id"]]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET full_name=? WHERE id=?");
            $stmt->execute([$full_name, $_SESSION["admin_id"]]);
        }
        $_SESSION["admin_name"] = $full_name;
        $admin['full_name'] = $full_name;
        $success = "Profile updated successfully!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile — Hungry Wheels</title>
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
        .card {
            background: rgba(20,30,48,0.85);
            border: 1px solid rgba(51,65,85,0.5);
            border-radius: 16px;
            animation: fadeUp 0.4s ease both;
        }
        .stat-card {
            background: rgba(20,30,48,0.7);
            border: 1px solid rgba(51,65,85,0.4);
            border-radius: 14px; padding: 18px;
            text-align: center;
        }
        .form-input {
            background: rgba(30,41,59,0.8);
            border: 1.5px solid rgba(51,65,85,0.8);
            color: #e2e8f0; transition: all 0.25s;
            width: 100%; padding: 11px 14px;
            border-radius: 10px; font-size: 14px; outline: none;
            font-family: inherit;
        }
        .form-input::placeholder { color: #475569; }
        .form-input:focus {
            border-color: #a855f7;
            box-shadow: 0 0 0 3px rgba(168,85,247,0.12);
        }
        .form-input:disabled {
            opacity: 0.5; cursor: not-allowed;
        }
        label {
            display: block; font-size: 13px;
            font-weight: 600; color: #94a3b8; margin-bottom: 6px;
        }
        .btn-save {
            background: #a855f7; color: #fff;
            font-weight: 700; padding: 11px 28px;
            border-radius: 10px; border: none;
            cursor: pointer; transition: all 0.2s;
            font-family: 'Syne', sans-serif; font-size: 14px;
        }
        .btn-save:hover { background: #9333ea; transform: translateY(-1px); }
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
        <div class="flex items-center gap-3">
            <span class="hidden sm:inline text-xs font-700 px-3 py-1 rounded-full syne"
                style="background:linear-gradient(135deg,#a855f7,#7c3aed);color:#fff">👑 Admin</span>
            <a href="AdminProfile.php" class="flex items-center gap-2 hover:opacity-80 transition group">
                <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm text-white font-700"
                    style="background:linear-gradient(135deg,#a855f7,#7c3aed)">
                    <?= strtoupper(substr($admin['full_name'], 0, 1)) ?>
                </div>
                <span class="hidden sm:inline text-sm text-slate-300 group-hover:text-purple-400 transition syne font-600">
                    <?= htmlspecialchars($admin['full_name']) ?>
                </span>
            </a>
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
        <div class="max-w-3xl mx-auto space-y-6">

            <!-- Header -->
            <div class="mb-2">
                <h2 class="syne text-3xl font-800 text-white mb-1">My Profile</h2>
                <p class="text-slate-400 text-sm">Manage your admin account details</p>
            </div>

            <!-- Success / Error -->
            <?php if ($success): ?>
                <div class="bg-green-400/10 border border-green-400/30 text-green-400 px-4 py-3 rounded-xl text-sm font-600">
                    ✅ <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="bg-red-400/10 border border-red-400/30 text-red-400 px-4 py-3 rounded-xl text-sm font-600">
                    ❌ <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Admin info card -->
            <div class="card p-6">
                <div class="flex items-center gap-5 mb-6">
                    <div class="w-16 h-16 rounded-2xl flex items-center justify-center text-2xl text-white font-800 syne flex-shrink-0"
                        style="background:linear-gradient(135deg,#a855f7,#7c3aed)">
                        <?= strtoupper(substr($admin['full_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <div class="syne text-xl font-800 text-white"><?= htmlspecialchars($admin['full_name']) ?></div>
                        <div class="text-slate-400 text-sm"><?= htmlspecialchars($admin['email']) ?></div>
                        <div class="mt-1">
                            <span class="text-xs font-700 px-2 py-0.5 rounded-full syne"
                                style="background:linear-gradient(135deg,#a855f7,#7c3aed);color:#fff">
                                👑 Administrator
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Stats row -->
                <div class="grid grid-cols-3 gap-3 mb-6">
                    <div class="stat-card">
                        <div class="syne text-2xl font-800 text-sky-400"><?= $total_orders ?></div>
                        <div class="text-slate-500 text-xs mt-1">Total Orders</div>
                    </div>
                    <div class="stat-card">
                        <div class="syne text-2xl font-800 text-purple-400"><?= $total_users ?></div>
                        <div class="text-slate-500 text-xs mt-1">Customers</div>
                    </div>
                    <div class="stat-card">
                        <div class="syne text-xl font-800 text-green-400"><?= number_format($total_revenue ?? 0, 0) ?></div>
                        <div class="text-slate-500 text-xs mt-1">EGP Revenue</div>
                    </div>
                </div>

                <!-- Update form -->
                <form method="POST" action="">
                    <div class="mb-4">
                        <label>Full name</label>
                        <input type="text" name="full_name" class="form-input"
                            value="<?= htmlspecialchars($admin['full_name']) ?>" required>
                    </div>

                    <div class="mb-4">
                        <label>Email address</label>
                        <input type="email" class="form-input" value="<?= htmlspecialchars($admin['email']) ?>" disabled>
                        <p class="text-slate-600 text-xs mt-1">Email cannot be changed</p>
                    </div>

                    <div class="border-t border-slate-700/50 pt-4 mb-4">
                        <p class="text-slate-400 text-sm font-600 mb-3">
                            Change password
                            <span class="text-slate-600 font-400">(leave blank to keep current)</span>
                        </p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label>New password</label>
                                <input type="password" name="new_password" class="form-input"
                                    placeholder="Min. 8 characters">
                            </div>
                            <div>
                                <label>Confirm password</label>
                                <input type="password" name="confirm_password" class="form-input"
                                    placeholder="Repeat new password">
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="btn-save">Save Changes</button>
                    </div>
                </form>
            </div>

            <!-- Danger zone -->
            <div class="card p-6" style="border-color: rgba(239,68,68,0.2);">
                <h3 class="syne font-800 text-white mb-1">Session</h3>
                <p class="text-slate-400 text-sm mb-4">End your current admin session and return to the login page.</p>
                <a href="/HungryWheels/logout.php"
                    class="inline-flex items-center gap-2 bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/30 px-5 py-2.5 rounded-xl text-sm font-700 transition">
                    🚪 Logout
                </a>
            </div>

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