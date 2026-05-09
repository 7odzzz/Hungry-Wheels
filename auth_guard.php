<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

function inject_bfcache_killer(): void {
    echo '<script>
        // Runs instantly when page is restored from bfcache (back/forward button)
        window.addEventListener("pageshow", function(e) {
            if (e.persisted) {
                window.location.replace(window.location.href);
            }
        });
    </script>';
}

function guard(string $role): void {
    if ($role === 'admin') {
        if (!isset($_SESSION["admin_id"])) {
            header("Location: /HungryWheels/login.php");
            exit();
        }
    } elseif ($role === 'user') {
        if (!isset($_SESSION["user_id"])) {
            header("Location: /HungryWheels/login.php");
            exit();
        }
    }
}

function redirect_if_logged_in(): void {
    if (isset($_SESSION["admin_id"])) {
        header("Location: /HungryWheels/adminpage/dashboard.php"); // ✅ fixed
        exit();
    }
    if (isset($_SESSION["user_id"])) {
        header("Location: /HungryWheels/userspage/home.php"); // ✅ fixed
        exit();
    }
}