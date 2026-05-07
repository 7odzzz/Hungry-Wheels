<?php
require_once('auth_guard.php');
redirect_if_logged_in();


require('db.php');

// If already logged in, push them forward — no going back to login
if (isset($_SESSION["admin_id"])) {
    header("Location: /HungryWheels/adminpage/dashboard.php");
    exit();
}
if (isset($_SESSION["user_id"])) {
    header("Location: /HungryWheels/userspage/home.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email    = str_replace(" ", "", $_POST["email"]);
    $password = $_POST["password"];

    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user["password"])) {
            if ($user["role"] === 'admin') {
                $_SESSION["admin_id"]   = $user["id"];
                $_SESSION["admin_name"] = $user["full_name"];
                header("Location: /HungryWheels/adminpage/dashboard.php");
            } else {
                $_SESSION["user_id"]   = $user["id"];
                $_SESSION["user_name"] = $user["full_name"];
                header("Location: /HungryWheels/userspage/home.php");
            }
            exit();
        } else {
            $error = "Invalid email or password.";
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

        .form-input {
            background: rgba(30,41,59,0.8);
            border: 1.5px solid rgba(51,65,85,0.8);
            color: #e2e8f0; transition: all 0.25s;
            width: 100%; padding: 12px 14px;
            border-radius: 10px; font-size: 15px; outline: none;
        }
        .form-input::placeholder { color: #475569; }
        .form-input:focus {
            border-color: #38bdf8;
            box-shadow: 0 0 0 3px rgba(56,189,248,0.12);
        }

        .btn-submit {
            width: 100%; padding: 13px;
            color: white; font-size: 15px;
            font-weight: 700; border: none;
            border-radius: 10px; cursor: pointer;
            transition: all 0.2s;
            font-family: 'Syne', sans-serif;
            background: black;
        }
        .btn-submit:hover { opacity: 0.9; transform: translateY(-1px); background: #38bdf8; }

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



    <script>
    // If user lands on login page via back/forward while already having a session,
    // the server's redirect_if_logged_in() won't fire from bfcache.
    // So we force a real server check from the client side too.
    window.addEventListener("pageshow", function(e) {
        if (e.persisted) {
            window.location.replace(window.location.href);
        }
    });
</script>

<body class="min-h-screen flex items-center justify-center px-4">

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

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">

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

            <button type="submit" class="btn-submit">
                Sign in
            </button>
        </form>

        <div class="mt-5 text-center text-sm text-slate-500">
            Don't have an account?
            <a href="/HungryWheels/userspage/register.php" class="text-sky-400 font-600 hover:underline">Register</a>
        </div>

    </div>
</div>

</body>
</html>