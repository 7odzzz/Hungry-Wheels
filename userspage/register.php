<?php
session_start();
require '../db.php';

$error   = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $full_name = trim($_POST["full_name"]);
    $email     = trim($_POST["email"]);
    $password  = $_POST["password"];
    $confirm   = $_POST["confirm_password"];
    $phone     = trim($_POST["phone"]);
    $address   = trim($_POST["address"]);

    // ── Validation ────────────────────────────────────────
    if (empty($full_name) || empty($email) || empty($password) || empty($phone) || empty($address)) {
        $error = "Please fill in all fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";

    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";

    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";

    } else {
        // ── Check if email already exists ─────────────────
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $error = "This email is already registered.";

        } else {
            try {
                // ── Insert new user ───────────────────────
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $stmt   = $pdo->prepare("
                    INSERT INTO users (full_name, email, password, phone, address, is_elite)
                    VALUES (?, ?, ?, ?, ?, 0)
                ");
                $stmt->execute([$full_name, $email, $hashed, $phone, $address]);

                // ── Get the new user's ID ─────────────────
                $new_user_id = $pdo->lastInsertId();

                // ── Generate coupon code: HW-XXXXXX ───────
                $coupon_code = 'HW-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));

                // ── Save coupon to database ───────────────
                $coupon_stmt = $pdo->prepare("
                    INSERT INTO coupons (coupon_code, discount_percentage, user_id, is_used)
                    VALUES (?, 10, ?, 0)
                ");
                $coupon_stmt->execute([$coupon_code, $new_user_id]);

                // ── Send welcome email with coupon ────────
                require_once '../mailer.php';
                $email_sent = sendCouponEmail($email, $full_name, $coupon_code);

                // Redirect to login regardless of email result
                // (we don't want registration to fail just because email failed)
                header("Location: /HungryWheels/login.php?registered=1");
                exit();

            } catch (PDOException $e) {
                $error = "Registration failed: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register — Hungry Wheels</title>
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
            text-align: center; margin-bottom: 25px;
        }
        .logo h1 {
            font-size: 26px; color: #38bdf8;
            font-weight: 700; font-family: 'Syne', sans-serif;
        }
        .logo p { color: #94a3b8; font-size: 14px; }

        label {
            display: block; font-size: 13px;
            font-weight: 600; color: #94a3b8; margin-bottom: 6px;
        }

        input, textarea {
            width: 100%; padding: 12px 14px;
            border-radius: 10px;
            border: 1.5px solid rgba(51,65,85,0.8);
            background: rgba(30,41,59,0.8);
            color: #e2e8f0; margin-bottom: 16px;
            outline: none; transition: 0.2s;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
        }
        input::placeholder, textarea::placeholder { color: #475569; }
        input:focus, textarea:focus {
            border-color: #38bdf8;
            box-shadow: 0 0 0 3px rgba(56,189,248,0.12);
        }
        textarea { resize: vertical; min-height: 80px; }

        /* Password field with toggle */
        .password-wrap {
            position: relative; margin-bottom: 16px;
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
            color: white; font-weight: 700;
            cursor: pointer; transition: 0.2s;
            font-family: 'Syne', sans-serif; font-size: 15px;
        }
        .btn:hover { background: #38bdf8; color: #080d14; transform: translateY(-1px); }

        .error {
            background: rgba(239,68,68,0.1);
            color: #f87171;
            border: 1px solid rgba(239,68,68,0.3);
            padding: 10px 14px; border-radius: 10px;
            font-size: 14px; margin-bottom: 15px;
        }

        /* Success banner shown after redirect on login page */
        .success-banner {
            background: rgba(34,197,94,0.1);
            color: #4ade80;
            border: 1px solid rgba(34,197,94,0.3);
            padding: 10px 14px; border-radius: 10px;
            font-size: 14px; margin-bottom: 15px;
        }

        .login-link {
            text-align: center; margin-top: 18px;
            font-size: 14px; color: #94a3b8;
        }
        .login-link a {
            color: #38bdf8; font-weight: 600; text-decoration: none;
        }
        .login-link a:hover { text-decoration: underline; }

        /* Email hint */
        .email-hint {
            background: rgba(56,189,248,0.06);
            border: 1px solid rgba(56,189,248,0.15);
            border-radius: 10px; padding: 10px 14px;
            font-size: 12px; color: #94a3b8;
            margin-bottom: 16px; line-height: 1.5;
        }
        .email-hint span { color: #38bdf8; font-weight: 600; }
    </style>
</head>

<body>
<div class="bg-glow"></div>
<div class="bg-glow-2"></div>

<div class="card">

    <div class="logo">
        <h1>🍔 Hungry Wheels</h1>
        <p>Create your account</p>
    </div>

    <!-- Email hint so user knows they'll get a coupon -->
    <div class="email-hint">
        🎟️ Enter a valid email — we'll send you a <span>10% discount coupon</span> right after you register!
    </div>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">

        <label for="full_name">Full name</label>
        <input type="text" id="full_name" name="full_name"
            placeholder="Full name"
            value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">

        <label for="email">Email address</label>
        <input type="email" id="email" name="email"
            placeholder="you@example.com"
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

        <label for="phone">Phone number</label>
        <input type="tel" id="phone" name="phone"
            placeholder="01012345678"
            value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">

        <label for="address">Address</label>
        <textarea id="address" name="address"
            placeholder="Your full address..."><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>

        <label for="password">Password</label>
        <div class="password-wrap">
            <input type="password" id="password" name="password"
                placeholder="Min. 8 characters">
            <button type="button" onclick="togglePass('password', 'icon1')" id="icon1"></button>
        </div>

        <label for="confirm_password">Confirm password</label>
        <div class="password-wrap">
            <input type="password" id="confirm_password" name="confirm_password"
                placeholder="Repeat your password">
            <button type="button" onclick="togglePass('confirm_password', 'icon2')" id="icon2"></button>
        </div>

        <button type="submit" class="btn">Create account</button>
    </form>

    <div class="login-link">
        Already have an account? <a href="../login.php">Sign in</a>
    </div>

</div>

<script>
function togglePass(inputId, btnId) {
    const input = document.getElementById(inputId);
    const btn   = document.getElementById(btnId);
    const isHidden = input.type === 'password';
    input.type   = isHidden ? 'text' : 'password';
    btn.textContent = isHidden ? '👁' : '';
}
</script>

</body>
</html>