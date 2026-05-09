<?php
session_start();
ob_start();
header('Content-Type: application/json');

if (!isset($_SESSION["user_id"])) {
    ob_end_clean();
    echo json_encode(['valid' => false, 'message' => 'Not logged in.']);
    exit();
}

require '../db.php';

$code = strtoupper(trim($_POST['coupon_code'] ?? ''));

if (empty($code)) {
    ob_end_clean();
    echo json_encode(['valid' => false, 'message' => 'Please enter a coupon code.']);
    exit();
}

// Step 1: Check if coupon exists
$stmt = $pdo->prepare("SELECT * FROM coupons WHERE coupon_code = ?");
$stmt->execute([$code]);
$coupon = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$coupon) {
    ob_end_clean();
    echo json_encode(['valid' => false, 'message' => 'Coupon code does not exist.']);
    exit();
}

// Step 2: Check it belongs to this user
if ($coupon['user_id'] != $_SESSION["user_id"]) {
    ob_end_clean();
    echo json_encode(['valid' => false, 'message' => 'This coupon does not belong to your account.']);
    exit();
}

// Step 3: Check is_used flag
if ($coupon['is_used'] == 1) {
    ob_end_clean();
    echo json_encode(['valid' => false, 'message' => 'This coupon has already been used.']);
    exit();
}

// Step 4: Double-check coupon_usages table as second layer
$check = $pdo->prepare("
    SELECT id FROM coupon_usages
    WHERE coupon_id = ? AND user_id = ?
");
$check->execute([$coupon['id'], $_SESSION['user_id']]);

if ($check->fetch()) {
    // Sync the flag in case it got out of sync
    $pdo->prepare("
        UPDATE coupons SET is_used = 1, used_at = NOW() WHERE id = ?
    ")->execute([$coupon['id']]);
    ob_end_clean();
    echo json_encode(['valid' => false, 'message' => 'You have already used this coupon.']);
    exit();
}

// All checks passed
ob_end_clean();
echo json_encode([
    'valid'               => true,
    'message'             => 'Coupon applied! You get ' . $coupon['discount_percentage'] . '% off.',
    'discount_percentage' => $coupon['discount_percentage'],
    'coupon_code'         => $coupon['coupon_code'],
]);
?>