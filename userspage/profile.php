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

$success = "";
$error   = "";

// ── Handle form submissions ───────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Update profile
    if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
        $full_name = trim($_POST['full_name']);
        $phone     = trim($_POST['phone']);
        $address   = trim($_POST['address']);
        $new_pass  = $_POST['new_password'];
        $confirm   = $_POST['confirm_password'];


        if (empty($full_name)) {
            $error = "Full name cannot be empty.";
        } elseif (!empty($new_pass) && $new_pass !== $confirm) {
            $error = "Passwords do not match.";
        } elseif (!empty($new_pass) && strlen($new_pass) < 8) {
            $error = "Password must be at least 8 characters.";
        }
        
        
        else {
            if (!empty($new_pass)) {
                $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE users SET full_name=?, phone=?, address=?, password=? WHERE id=?");
                $stmt->execute([$full_name, $phone, $address, $hashed, $_SESSION["user_id"]]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET full_name=?, phone=?, address=? WHERE id=?");
                $stmt->execute([$full_name, $phone, $address, $_SESSION["user_id"]]);
            }
            $_SESSION["user_name"] = $full_name;
            $success = "Profile updated successfully!";

            // Refresh user data
            $user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $user_stmt->execute([$_SESSION["user_id"]]);
            $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    // Toggle elite subscription
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_elite') {
        $new_status = $is_elite ? 0 : 1;
        $stmt = $pdo->prepare("UPDATE users SET is_elite=? WHERE id=?");
        $stmt->execute([$new_status, $_SESSION["user_id"]]);
        $is_elite = $new_status;
        $user['is_elite'] = $new_status;
        $success = $new_status ? "Welcome to Elite! You now get 10% off every order." : "You have unsubscribed from Elite.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile — Hungry Wheels</title>
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
            border-radius: 16px;
            animation: fadeUp 0.4s ease both;
        }
        @keyframes fadeUp {
            from { opacity:0; transform:translateY(16px); }
            to   { opacity:1; transform:translateY(0); }
        }
        .form-input {
            background: rgba(30,41,59,0.8);
            border: 1.5px solid rgba(51,65,85,0.8);
            color: #e2e8f0; transition: all 0.25s;
            width: 100%; padding: 11px 14px;
            border-radius: 10px; font-size: 14px; outline: none;
        }
        .form-input::placeholder { color: #475569; }
        .form-input:focus {
            border-color: #38bdf8;
            box-shadow: 0 0 0 3px rgba(56,189,248,0.12);
        }
        textarea.form-input { resize: vertical; min-height: 80px; font-family: inherit; }
        label {
            display: block; font-size: 13px; font-weight: 600;
            color: #94a3b8; margin-bottom: 6px;
        }
        .btn-save {
            background: #38bdf8; color: #080d14;
            font-weight: 700; padding: 11px 28px;
            border-radius: 10px; border: none;
            cursor: pointer; transition: all 0.2s;
            font-family: 'Syne', sans-serif; font-size: 14px;
        }
        .btn-save:hover { background: #0ea5e9; transform: translateY(-1px); }

        .elite-card {
            background: linear-gradient(135deg, rgba(245,158,11,0.08), rgba(251,191,36,0.04));
            border: 1px solid rgba(245,158,11,0.25);
        }
        .elite-card.active {
            background: linear-gradient(135deg, rgba(245,158,11,0.15), rgba(251,191,36,0.08));
            border-color: rgba(245,158,11,0.4);
        }
        .btn-elite-subscribe {
            background: linear-gradient(135deg,#f59e0b,#fbbf24);
            color: #080d14; font-weight: 700;
            padding: 12px 28px; border-radius: 10px;
            border: none; cursor: pointer;
            transition: all 0.2s; font-family: 'Syne', sans-serif;
            font-size: 14px;
        }
        .btn-elite-subscribe:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn-elite-unsub {
            background: rgba(239,68,68,0.1);
            color: #f87171; font-weight: 600;
            padding: 10px 20px; border-radius: 10px;
            border: 1px solid rgba(239,68,68,0.3);
            cursor: pointer; transition: all 0.2s;
            font-size: 13px;
        }
        .btn-elite-unsub:hover { background: rgba(239,68,68,0.2); }

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
        <div class="max-w-3xl mx-auto space-y-6">

            <!-- Header -->
            <div class="mb-2">
                <h2 class="syne text-3xl font-800 text-white mb-1">My Profile</h2>
                <p class="text-slate-400 text-sm">Manage your account details and subscription</p>
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

            <!-- Profile card with avatar -->
            <div class="card p-6">
                <div class="flex items-center gap-5 mb-6">
                    <div class="avatar w-16 h-16 rounded-2xl flex items-center justify-center text-2xl text-white font-800 syne flex-shrink-0">
                        <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <div class="syne text-xl font-800 text-white"><?= htmlspecialchars($user['full_name']) ?></div>
                        <div class="text-slate-400 text-sm"><?= htmlspecialchars($user['email']) ?></div>
                        <div class="flex items-center gap-2 mt-1">
                            <?php if ($is_elite): ?>
                                <span class="text-xs font-700 px-2 py-0.5 rounded-full syne"
                                    style="background:linear-gradient(135deg,#f59e0b,#fbbf24);color:#080d14">⭐ Elite Member</span>
                            <?php else: ?>
                                <span class="text-xs text-slate-500 border border-slate-700 px-2 py-0.5 rounded-full">Regular Member</span>
                            <?php endif; ?>
                            <span class="text-xs text-slate-600">· <?= $order_count ?> order<?= $order_count != 1 ? 's' : '' ?></span>
                        </div>
                    </div>
                </div>

                <!-- Update profile form -->
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label>Full name</label>
                            <input type="text" name="full_name" class="form-input"
                                value="<?= htmlspecialchars($user['full_name']) ?>" required>
                        </div>
                        <div>
                            <label>Phone number</label>
                            <input type="tel" name="phone" class="form-input"
                                value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                                placeholder="01012345678">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label>Email address</label>
                        <input type="email" class="form-input opacity-50 cursor-not-allowed"
                            value="<?= htmlspecialchars($user['email']) ?>" disabled>
                        <p class="text-slate-600 text-xs mt-1">Email cannot be changed</p>
                    </div>

                    <div class="mb-4">
                        <label>Delivery address</label>
                        <textarea name="address" class="form-input"
                            placeholder="Your default delivery address..."><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                    </div>

                    <div class="border-t border-slate-700/50 pt-4 mb-4">
                        <p class="text-slate-400 text-sm font-600 mb-3">Change password <span class="text-slate-600 font-400">(leave blank to keep current)</span></p>
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

            <!-- Elite subscription card -->
            <div id="subscription" class="card elite-card <?= $is_elite ? 'active' : '' ?> p-6">
                <div class="flex items-start justify-between gap-4 mb-4">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-2xl">👑</span>
                            <h3 class="syne text-xl font-800 text-amber-400">Elite Membership</h3>
                        </div>
                        <p class="text-slate-400 text-sm">Get 10% off on every single order you place.</p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <div class="syne text-2xl font-800 text-amber-400">100</div>
                        <div class="text-slate-500 text-xs">EGP / month</div>
                    </div>
                </div>

                <!-- Benefits list -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
                    <div class="bg-amber-400/8 border border-amber-400/15 rounded-xl p-3 text-center">
                        <div class="text-amber-400 text-lg mb-1">💰</div>
                        <div class="text-slate-300 text-xs font-600">10% off every order</div>
                    </div>
                    <div class="bg-amber-400/8 border border-amber-400/15 rounded-xl p-3 text-center">
                        <div class="text-amber-400 text-lg mb-1">⚡</div>
                        <div class="text-slate-300 text-xs font-600">Priority service</div>
                    </div>
                    <div class="bg-amber-400/8 border border-amber-400/15 rounded-xl p-3 text-center">
                        <div class="text-amber-400 text-lg mb-1">⭐</div>
                        <div class="text-slate-300 text-xs font-600">Elite badge</div>
                    </div>
                </div>

                <?php if ($is_elite): ?>
                    <!-- Already subscribed -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2 text-green-400 text-sm font-600">
                            <span>✅</span> You are an Elite member
                        </div>
                        <form method="POST" action=""
                            onsubmit="return confirm('Are you sure you want to unsubscribe from Elite?')">
                            <input type="hidden" name="action" value="toggle_elite">
                            <button type="submit" class="btn-elite-unsub">Unsubscribe</button>
                        </form>
                    </div>
                <?php else: ?>
                    <!-- Not subscribed -->
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="toggle_elite">
                        <div class="flex items-center justify-between">
                            <p class="text-slate-500 text-xs">Cancel anytime from your profile</p>
                            <button type="submit" class="btn-elite-subscribe">⭐ Subscribe for 100 EGP/mo</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

        </div>
    </main>
</div>

<script>
function toggleSidebar() {
    document.getElementById('mobile-sidebar').classList.toggle('open');
    document.getElementById('sidebar-overlay').classList.toggle('open');
}

// Scroll to subscription section if hash is present
if (window.location.hash === '#subscription') {
    setTimeout(() => {
        document.getElementById('subscription')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }, 300);
}
</script>
</body>
</html>