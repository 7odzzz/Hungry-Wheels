<?php
// Must be FIRST line — no spaces or blank lines before this
session_start();

// Catch any PHP errors and return them as JSON
set_error_handler(function ($errno, $errstr) {
    echo json_encode(['success' => false, 'message' => 'PHP Error: ' . $errstr]);
    exit();
});

ob_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit();
}

require '../db.php';

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid JSON: ' . json_last_error_msg()]);
    exit();
}

$cart        = $data['cart']         ?? [];
$address     = trim($data['address'] ?? '');
$coupon_code = strtoupper(trim($data['coupon_code'] ?? ''));
$use_wallet  = $data['use_wallet']   ?? false;

if (empty($cart)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Cart is empty.']);
    exit();
}

if (empty($address)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Address is missing.']);
    exit();
}

// ── Get user data (elite + wallet) ────────────────────────
$stmt = $pdo->prepare('SELECT is_elite, wallet_balance FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user           = $stmt->fetch(PDO::FETCH_ASSOC);
$is_elite       = (int)   ($user['is_elite']       ?? 0);
$wallet_balance = floatval($user['wallet_balance']  ?? 0);

// ── Calculate subtotal ────────────────────────────────────
$subtotal = 0;
foreach ($cart as $item) {
    $subtotal += floatval($item['price']) * intval($item['qty']);
}

// ── Apply elite discount (10 % off subtotal) ──────────────
$total = $is_elite ? $subtotal * 0.9 : $subtotal;

// ── Validate coupon ───────────────────────────────────────
$coupon          = null;
$coupon_discount = 0;

if (!empty($coupon_code)) {

    // Check coupon: exists + belongs to user + not used
    $c_stmt = $pdo->prepare('
        SELECT * FROM coupons
        WHERE coupon_code = ?
          AND user_id     = ?
          AND is_used     = 0
    ');
    $c_stmt->execute([$coupon_code, $_SESSION['user_id']]);
    $coupon = $c_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$coupon) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Coupon is invalid or already used.']);
        exit();
    }

    // Double-check coupon_usages table
    $usage_check = $pdo->prepare('
        SELECT id FROM coupon_usages
        WHERE coupon_id = ? AND user_id = ?
    ');
    $usage_check->execute([$coupon['id'], $_SESSION['user_id']]);

    if ($usage_check->fetch()) {
        // Sync the flag if somehow out of sync
        $pdo->prepare('UPDATE coupons SET is_used = 1, used_at = NOW() WHERE id = ?')
            ->execute([$coupon['id']]);
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'This coupon has already been used.']);
        exit();
    }

    // Apply coupon on top of the already elite-discounted total
    $coupon_discount = $total * ($coupon['discount_percentage'] / 100);
    $total           = $total - $coupon_discount;
}

// ── Apply wallet balance (optional) ──────────────────────
$wallet_used = 0;

if ($use_wallet && $wallet_balance > 0) {
    // Never deduct more than the remaining total
    $wallet_used = min($wallet_balance, $total);
    $total       = $total - $wallet_used;
}

// ── Place order — everything inside ONE transaction ───────
try {
    $pdo->beginTransaction();

    // 1. Insert order
    $stmt = $pdo->prepare('
        INSERT INTO orders (user_id, total_price, is_elite_discount, address, status)
        VALUES (?, ?, ?, ?, \'pending\')
    ');
    $stmt->execute([$_SESSION['user_id'], $total, $is_elite, $address]);
    $order_id = $pdo->lastInsertId();

    // 2. Insert order items
    $item_stmt = $pdo->prepare('
        INSERT INTO order_items (order_id, menu_item_id, quantity, unit_price)
        VALUES (?, ?, ?, ?)
    ');
    foreach ($cart as $item) {
        $unit_price = $is_elite
            ? floatval($item['price']) * 0.9
            : floatval($item['price']);
        $item_stmt->execute([
            $order_id,
            intval($item['id']),
            intval($item['qty']),
            $unit_price,
        ]);
    }

    // 3. Mark coupon as used (inside transaction)
    if ($coupon) {
        $pdo->prepare('
            UPDATE coupons
            SET is_used = 1, used_at = NOW()
            WHERE id = ? AND is_used = 0
        ')->execute([$coupon['id']]);

        $pdo->prepare('
            INSERT IGNORE INTO coupon_usages (coupon_id, user_id, order_id)
            VALUES (?, ?, ?)
        ')->execute([$coupon['id'], $_SESSION['user_id'], $order_id]);
    }

    // 4. Deduct wallet balance (inside transaction so it rolls back if order fails)
    if ($wallet_used > 0) {
        $pdo->prepare('
            UPDATE users
            SET wallet_balance = wallet_balance - ?
            WHERE id = ? AND wallet_balance >= ?
        ')->execute([$wallet_used, $_SESSION['user_id'], $wallet_used]);
    }

    // 5. Commit everything atomically
    $pdo->commit();

    // 6. Process reward points AFTER commit (separate updates)
    require_once '../rewards.php';

    $reward_result =
     processOrderRewards(
        $pdo,
        $_SESSION['user_id'],
        $order_id,
        $subtotal
    );

    ob_end_clean();
    echo json_encode([
        'success'          => true,
        'order_id'         => $order_id,
        'coupon_applied'   => $coupon ? true : false,
        'coupon_discount'  => round($coupon_discount, 2),
        'wallet_used'      => round($wallet_used, 2),
        'points_earned'    => $reward_result['points_earned'],
        'rewards_unlocked' => $reward_result['rewards_unlocked'],
        'reason'           => $reward_result['reason'],
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>