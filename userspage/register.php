<?php
session_start();
require '../db.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $full_name = trim($_POST["full_name"]);
    $email     = trim($_POST["email"]);
    $password  = $_POST["password"];
    $confirm   = $_POST["confirm_password"];
    $phone     = trim($_POST["phone"]);
    $address   = trim($_POST["address"]);

    if (empty($full_name) || empty($email) || empty($password) || empty($phone) || empty($address)) {
        $error = "Please fill in all fields.";
    }

     elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    }

    elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } 
    elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } 
    else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);

      if ($stmt->fetch()) {
            $error = "This email is already registered.";
        } 
    
        else {
    
        try {
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, phone, address, is_elite) 
                               VALUES (?, ?, ?, ?, ?, 0)");
        $stmt->execute([$full_name, $email, $hashed, $phone, $address]);
        header("Location: /HungryWheels/login.php");
        exit();
    } catch (PDOException $e) {
        $error = "Insert failed: " . $e->getMessage();
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
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Segoe UI', sans-serif;
    background: #0f172a;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
}

/* Card */
.card {
    background: #1e293b;
    padding: 40px 36px;
    border-radius: 16px;
    box-shadow: 0 15px 40px rgba(0,0,0,0.5);
    width: 100%;
    max-width: 460px;
}

/* Logo */
.logo {
    text-align: center;
    margin-bottom: 28px;
}

.logo h1 {
    font-size: 26px;
    color: #38bdf8;
    font-weight: 700;
}

.logo p {
    color: #94a3b8;
    font-size: 14px;
    margin-top: 4px;
}

/* Labels */
label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: #cbd5e1;
    margin-bottom: 6px;
}

/* Inputs + Textarea */
input[type="text"],
input[type="email"],
input[type="password"],
input[type="tel"],
textarea {
    width: 100%;
    padding: 12px 14px;
    border: 1.5px solid #334155;
    border-radius: 8px;
    font-size: 15px;
    margin-bottom: 18px;
    background: #0f172a;
    color: #e2e8f0;
    outline: none;
    transition: 0.2s;
    font-family: inherit;
}

textarea {
    resize: vertical;
    min-height: 3px;
}

input::placeholder,
textarea::placeholder {
    color: #64748b;
}

input:focus,
textarea:focus {
    border-color: #38bdf8;
    box-shadow: 0 0 0 3px rgba(56,189,248,0.15);
}

/* Button */
.btn {
    width: 100%;
    padding: 13px;
    background: #000000;
    color: #ffffff;
    font-size: 16px;
    font-weight: 700;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: 0.2s;
}

.btn:hover {
    background: #0ea5e9;
}

/* Error */
.error {
    background: rgba(239, 68, 68, 0.1);
    color: #f87171;
    border: 1px solid #ef4444;
    padding: 10px 14px;
    border-radius: 8px;
    font-size: 14px;
    margin-bottom: 18px;
}

/* Login link */
.login-link {
    text-align: center;
    margin-top: 20px;
    font-size: 14px;
    color: #94a3b8;
}

.login-link a {
    color: #38bdf8;
    font-weight: 600;
    text-decoration: none;
}

.login-link a:hover {
    text-decoration: underline;
}
</style>
</head>
        



<body>
        <div class="card">

                <div class="logo">
                    <h1>Hungry Wheels</h1>
                    <p>Create your account</p>
                </div>



            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div> <!-- edena ll div class error aashan law fe error y print el message-->
            <?php endif; ?>

            
            
            <form method="POST" action="">

                <label for="full_name" > Full name </label>
            
                <input type="text"  id="full_name"  name="full_name" placeholder="Full name"
                    value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">

            
            
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" placeholder="you@example.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">


                <label for="phone">Phone number</label>
                <input type="tel" id="phone" name="phone" placeholder=""
                    value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">


                <label for="address"> Address</label>
                <textarea id="address" name="address" placeholder="Your full address...">  
                 <?= htmlspecialchars($_POST['address'] ?? '')?> </textarea>     <!--tetxarea make the input width customized-->
         

                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Min. 8 characters">
                <button type="button" onclick="togglePassword()"></button>

                <label for="confirm_password">Confirm password</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat your password">

                <button type="submit" class="btn">Create account</button>
            </form>

            <div class="login-link">
                Already have an account? <a href="../login.php">Sign in</a>
            </div>
        
        </div>
        
    
    
    </body>
        </html>