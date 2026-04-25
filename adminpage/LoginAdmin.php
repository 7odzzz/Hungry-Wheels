<?php
session_start();

// If already logged in as admin, redirect to dashboard
if (isset($_SESSION["admin_id"])) {
    header("Location: dashboard.php");
    exit();
}

require '../db.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email    = trim($_POST["email"]);
    $password = $_POST["password"];

    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        $stmt = $pdo->prepare("
        SELECT * FROM users 
        WHERE TRIM(email) = ? 
        AND LOWER(role) = 'admin'
            " );
        $stmt->execute([$email]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && password_verify($password, $admin["password"])) {
            $_SESSION["admin_id"]   = $admin["id"];
            $_SESSION["admin_name"] = $admin["full_name"];
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Invalid credentials or not an admin account.";
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — Hungry Wheels</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'DM Sans', sans-serif; }
        h1, h2, .syne { font-family: 'Syne', sans-serif; }
        body { background: #080d14; }

        .bg-glow {
            position: fixed; top: -200px; right: -100px;
            width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(168,85,247,0.07) 0%, transparent 70%);
            pointer-events: none; z-index: 0;
            animation: floatGlow 8s ease-in-out infinite;
        }
        .bg-glow-2 {
            position: fixed; bottom: -200px; left: -100px;
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(56,189,248,0.05) 0%, transparent 70%);
            pointer-events: none; z-index: 0;
            animation: floatGlow 10s ease-in-out infinite reverse;
        }
        @keyframes floatGlow {
            0%, 100% { transform: translate(0,0); }
            50% { transform: translate(30px, 30px); }
        }

        .card {
            background: rgba(20, 30, 48, 0.9);
            border: 1px solid rgba(168,85,247,0.15);
            backdrop-filter: blur(16px);
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
            border-color: #a855f7;
            box-shadow: 0 0 0 3px rgba(168,85,247,0.12);
        }

        .btn {
            width: 100%; padding: 13px;
            background: #a855f7;
            color: white; font-size: 15px;
            font-weight: 700; border: none;
            border-radius: 10px; cursor: pointer;
            transition: all 0.2s;
            font-family: 'Syne', sans-serif;
        }
        .btn:hover { background: #9333ea; transform: translateY(-1px); }

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
    </style>
</head>
<body class="min-h-screen flex items-center justify-center px-4">

<div class="bg-glow"></div>
<div class="bg-glow-2"></div>

<div class="relative z-10 w-full max-w-md">

    <!-- Logo -->
    <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl mb-4"
             style="background: linear-gradient(135deg, #a855f7, #7c3aed);">
            <span class="text-2xl">🔐</span>
        </div>
        <h1 class="syne text-2xl font-800 text-white">Admin Panel</h1>
        <p class="text-slate-500 text-sm mt-1">Hungry Wheels — Staff access only</p>
    </div>

    <div class="card rounded-2xl p-8">

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">

            <div class="mb-4">
                <label>Email address</label>
                <input type="email" name="email" class="form-input"
                    placeholder="admin@hungrywheels.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>

            <div class="mb-6">
                <label>Password</label>
                <input type="password" name="password" class="form-input"
                    placeholder="••••••••">
            </div>

            <button type="submit" class="btn">Sign in to Admin Panel</button>

        </form>

        <div class="mt-6 pt-4 border-t border-slate-700/50 text-center">
            <a href="../login.php" class="text-slate-500 hover:text-slate-400 text-sm transition">
                ← Back to user login
            </a>
        </div>

    </div>

    <p class="text-center text-slate-700 text-xs mt-6">
        Unauthorized access is strictly prohibited
    </p>

</div>

</body>
</html>