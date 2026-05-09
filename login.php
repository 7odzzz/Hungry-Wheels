<?php
require_once('auth_guard.php');
redirect_if_logged_in();

require('db.php');

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
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0; padding: 0;
            box-sizing: border-box;
            font-family: 'DM Sans', sans-serif;
        }

        body {
            background: #080d14;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 40px 16px;
        }

        .bg-glow {
            position: fixed; top: -200px; left: -100px;
            width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(56,189,248,0.07) 0%, transparent 70%);
            z-index: 0; pointer-events: none;
            animation: floatGlow 8s ease-in-out infinite;
        }
        .bg-glow-2 {
            position: fixed; bottom: -200px; right: -100px;
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(168,85,247,0.06) 0%, transparent 70%);
            z-index: 0; pointer-events: none;
            animation: floatGlow 10s ease-in-out infinite reverse;
        }
        @keyframes floatGlow {
            0%, 100% { transform: translate(0,0); }
            50% { transform: translate(30px, 30px); }
        }

        .card {
            position: relative; z-index: 1;
            width: 100%; max-width: 460px;
            background: rgba(20,30,48,0.9);
            border: 1px solid rgba(51,65,85,0.5);
            backdrop-filter: blur(16px);
            padding: 40px; border-radius: 16px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.5);
        }

        .logo {
            text-align: center;
            margin-bottom: 28px;
        }
        .logo h1 {
            font-size: 26px; color: #38bdf8;
            font-weight: 700; font-family: 'Syne', sans-serif;
            display: flex; align-items: center;
            justify-content: center; gap: 8px;
        }
        .logo p {
            color: #94a3b8; font-size: 14px; margin-top: 4px;
        }

        label {
            display: block; font-size: 13px;
            font-weight: 600; color: #94a3b8; margin-bottom: 6px;
        }

        input {
            width: 100%; padding: 12px 14px;
            border-radius: 10px;
            border: 1.5px solid rgba(51,65,85,0.8);
            background: rgba(30,41,59,0.8);
            color: #e2e8f0; outline: none;
            font-size: 15px; transition: 0.2s;
            font-family: 'DM Sans', sans-serif;
            margin-bottom: 16px;
        }
        input::placeholder { color: #475569; }
        input:focus {
            border-color: #38bdf8;
            box-shadow: 0 0 0 3px rgba(56,189,248,0.12);
        }

        /* Password wrapper with show/hide toggle */
        .password-wrap {
            position: relative; margin-bottom: 20px;
        }
        .password-wrap input {
            margin-bottom: 0; padding-right: 48px;
        }
        .password-wrap button {
            position: absolute; right: 12px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            color: #475569; cursor: pointer;
            font-size: 18px; padding: 4px;
            transition: color 0.2s; line-height: 1;
        }
        .password-wrap button:hover { color: #38bdf8; }

        .btn {
            width: 100%; padding: 13px; border: none;
            border-radius: 10px; background: black;
            color: white; font-size: 15px;
            font-weight: 700; cursor: pointer;
            transition: 0.2s;
            font-family: 'Syne', sans-serif;
        }
        .btn:hover {
            background: #38bdf8;
            color: #080d14;
            transform: translateY(-1px);
        }

        .error-box {
            background: rgba(239,68,68,0.1);
            color: #f87171;
            border: 1px solid rgba(239,68,68,0.3);
            padding: 10px 14px; border-radius: 10px;
            font-size: 14px; margin-bottom: 16px;
        }

        .success-box {
            background: rgba(34,197,94,0.1);
            color: #4ade80;
            border: 1px solid rgba(34,197,94,0.3);
            padding: 10px 14px; border-radius: 10px;
            font-size: 14px; margin-bottom: 16px;
            line-height: 1.5;
        }

        .divider {
            display: flex; align-items: center; gap: 12px;
            margin: 20px 0;
        }
        .divider::before, .divider::after {
            content: ''; flex: 1; height: 1px;
            background: rgba(51,65,85,0.6);
        }
        .divider span { color: #475569; font-size: 12px; }

        .register-link {
            text-align: center; margin-top: 18px;
            font-size: 14px; color: #94a3b8;
        }
        .register-link a {
            color: #38bdf8; font-weight: 600; text-decoration: none;
        }
        .register-link a:hover { text-decoration: underline; }
    </style>

    <script>
        window.addEventListener("pageshow", function(e) {
            if (e.persisted) {
                window.location.replace(window.location.href);
            }
        });
    </script>
</head>

<body>
<div class="bg-glow"></div>
<div class="bg-glow-2"></div>

<div class="card">

    <div class="logo">
        <h1>🍔 Hungry Wheels</h1>
        <p>Sign in to your account</p>
    </div>

    <?php if (isset($_GET['registered'])): ?>
        <div class="success-box">
            ✅ Account created successfully!<br>
            📧 Check your email for your <strong>10% welcome discount coupon</strong>.
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error-box"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">

        <label for="email">Email address</label>
        <input type="email" id="email" name="email"
            placeholder="you@example.com"
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

        <label for="password">Password</label>
        <div class="password-wrap">
            <input type="password" id="password" name="password"
                placeholder="••••••••">
            <button type="button" onclick="togglePass()" id="toggle-icon">👁</button>
        </div>

        <button type="submit" class="btn">Sign in</button>

    </form>

    <div class="register-link">
        Don't have an account?
        <a href="/HungryWheels/userspage/register.php">Register</a>
    </div>

</div>

<script>
function togglePass() {
    const input = document.getElementById('password');
    const icon  = document.getElementById('toggle-icon');
    const isHidden = input.type === 'password';
     input.type      = isHidden ? 'text'  : 'password';
    icon.textContent = isHidden ? '👁' : '👁';
}
</script>

</body>
</html>