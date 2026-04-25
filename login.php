<?php
session_start();
require('db.php');
$error = "";
$role  = $_POST['role'] ?? 'user';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email    = trim($_POST["email"]);
    $password = $_POST["password"];
    $role     = $_POST["role"] ?? 'user';

    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = ?");
        $stmt->execute([$email, $role]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user["password"])) {
            if ($role === 'admin') {
                $_SESSION["admin_id"]   = $user["id"];
                $_SESSION["admin_name"] = $user["full_name"];
                header("Location: /HungryWheels/adminpage/dashboard.php");
            } else {
                $_SESSION["user_id"]   = $user["id"];
                $_SESSION["user_name"] = $user["full_name"];
                header("Location:/HungryWheels/userspage/home.php");
            }
            exit();
        } else {
            $error = $role === 'admin'
                ? "Invalid credentials or not an admin account."
                : "Invalid email or password.";
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Hungry Wheels</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
   
   <style>
        * { font-family: 'DM Sans', sans-serif; }
        h1, h2, .syne { font-family: 'Syne', sans-serif; }
        body { background: #080d14; }

        .bg-glow {
            position: fixed; top: -200px; left: -100px;
            width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(56,189,248,0.07) 0%, transparent 70%);
            pointer-events: none; z-index: 0;
            animation: floatGlow 8s ease-in-out infinite;
        }
        .bg-glow-2 {
            position: fixed; bottom: -200px; right: -100px;
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(168,85,247,0.06) 0%, transparent 70%);
            pointer-events: none; z-index: 0;
            animation: floatGlow 10s ease-in-out infinite reverse;
        }
        @keyframes floatGlow {
            0%, 100% { transform: translate(0,0); }
            50% { transform: translate(30px, 30px); }
        }

        .card {
            background: rgba(20,30,48,0.9);
            border: 1px solid rgba(51,65,85,0.5);
            backdrop-filter: blur(16px);
        }

        /* Role toggle */
        .role-btn {
            flex: 1; padding: 10px;
            border-radius: 10px; font-size: 14px;
            font-weight: 600; cursor: pointer;
            transition: all 0.2s; border: none;
            font-family: 'Syne', sans-serif;
            background: transparent; color: #475569;
        }
        .role-btn.active-user {
            background: #38bdf8; color: #080d14;
        }
        .role-btn.active-admin {
            background: #a855f7; color: #fff;
        }

        .form-input {
            background: rgba(30,41,59,0.8);
            border: 1.5px solid rgba(51,65,85,0.8);
            color: #e2e8f0; transition: all 0.25s;
            width: 100%; padding: 12px 14px;
            border-radius: 10px; font-size: 15px; outline: none;
        }
        .form-input::placeholder { color: #475569; }
        .form-input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-glow);
        }

        .btn-submit {
            width: 100%; padding: 13px;
            color: white; font-size: 15px;
            font-weight: 700; border: none;
            border-radius: 10px; cursor: pointer;
            transition: all 0.2s;
            font-family: 'Syne', sans-serif;
            background: var(--accent);
        }
        .btn-submit:hover { opacity: 0.9; transform: translateY(-1px); }

        .error {
            background: rgba(239,68,68,0.1);
            color: #f87171;
            border: 1px solid rgba(239,68,68,0.3);
            padding: 10px 14px; border-radius: 10px;
            font-size: 14px; margin-bottom: 18px;
        }

        label {
            display: block; font-size: 13px;
            font-weight: 600; color: #94a3b8; margin-bottom: 6px;
        }

        /* User mode */
        body.mode-user {
            --accent: #38bdf8;
            --accent-glow: rgba(56,189,248,0.12);
        }
        /* Admin mode */
        body.mode-admin {
            --accent: #a855f7;
            --accent-glow: rgba(168,85,247,0.12);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center px-4 mode-<?= htmlspecialchars($role) ?>">

<div class="bg-glow"></div>
<div class="bg-glow-2"></div>

<div class="relative z-10 w-full max-w-md">

    <!-- Logo -->
    <div class="text-center mb-8">
        <a href="#" class="syne text-2xl font-800 text-sky-400 flex items-center justify-center gap-2">
            🍔 <span>Hungry Wheels</span>
        </a>
        <p class="text-slate-500 text-sm mt-2">Sign in to continue</p>
    </div>

    <div class="card rounded-2xl p-8">

        <!-- Role toggle -->
        <div class="flex gap-2 bg-slate-900/60 p-1.5 rounded-xl mb-6" id="role-toggle">
            <button type="button" id="btn-user"
                onclick="setRole('user')"
                class="role-btn <?= $role === 'user' ? 'active-user' : '' ?>">
                👤 Customer
            </button>
            <button type="button" id="btn-admin"
                onclick="setRole('admin')"
                class="role-btn <?= $role === 'admin' ? 'active-admin' : '' ?>">
                🔐 Admin
            </button>
        </div>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="" id="login-form">
            <input type="hidden" name="role" id="role-input" value="<?= htmlspecialchars($role) ?>">

            <div class="mb-4">
                <label>Email address</label>
                <input type="email" name="email" class="form-input"
                    placeholder="you@example.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>

            <div class="mb-6">
                <label>Password</label>
                <input type="password" name="password" class="form-input"
                    placeholder="••••••••">
            </div>

            <button type="submit" class="btn-submit" id="submit-btn">
                Sign in
            </button>
        </form>

        <!-- Register link (only for users) -->
        <div id="register-link" class="mt-5 text-center text-sm text-slate-500 <?= $role === 'admin' ? 'hidden' : '' ?>">
            Don't have an account?
            <a href="/HungryWheels/userspage/register.php" class="text-sky-400 font-600 hover:underline">Register</a>
        </div>

        <!-- Admin note -->
        <div id="admin-note" class="mt-5 text-center text-xs text-slate-600 <?= $role === 'user' ? 'hidden' : '' ?>">
            🔒 Admin access only — unauthorized login is prohibited
        </div>

    </div>
</div>

<script>
function setRole(role) {
    // Update hidden input
    document.getElementById('role-input').value = role;

    // Toggle button styles
    const btnUser  = document.getElementById('btn-user');
    const btnAdmin = document.getElementById('btn-admin');
    btnUser.className  = 'role-btn ' + (role === 'user'  ? 'active-user'  : '');
    btnAdmin.className = 'role-btn ' + (role === 'admin' ? 'active-admin' : '');

    // Toggle body class for accent color
    document.body.className = 'min-h-screen flex items-center justify-center px-4 mode-' + role;

    // Toggle register link and admin note
    document.getElementById('register-link').classList.toggle('hidden', role === 'admin');
    document.getElementById('admin-note').classList.toggle('hidden', role === 'user');

    // Update button text
    document.getElementById('submit-btn').textContent = role === 'admin' ? 'Sign in to Admin Panel' : 'Sign in';
}
</script>

</body>
</html>