<?php
// Must be FIRST line — no spaces or blank lines before this
session_start();

// Catch any PHP errors and return them as JSON instead of breaking the response
set_error_handler(function($errno, $errstr) {
    echo json_encode(['success' => false, 'message' => 'PHP Error: ' . $errstr]);
    exit();
});

ob_start(); // Buffer any accidental output

header('Content-Type: application/json');

if (!isset($_SESSION["user_id"])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit();
}

require '../db.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid JSON received: ' . json_last_error_msg()]);
    exit();
}

$cart    = $data['cart']    ?? [];
$address = trim($data['address'] ?? '');

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

// Get user elite status
$stmt = $pdo->prepare("SELECT is_elite FROM users WHERE id = ?");
$stmt->execute([$_SESSION["user_id"]]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$is_elite = $user['is_elite'] ?? 0;

// Calculate total
$subtotal = 0;
foreach ($cart as $item) {
    $subtotal += floatval($item['price']) * intval($item['qty']);
}
$total = $is_elite ? $subtotal * 0.9 : $subtotal;

try {
    $pdo->beginTransaction();

    // Insert order
    $stmt = $pdo->prepare("INSERT INTO orders (user_id, total_price, is_elite_discount, address, status) 
                           VALUES (?, ?, ?, ?, 'pending')");
    $stmt->execute([$_SESSION["user_id"], $total, $is_elite, $address]);
    $order_id = $pdo->lastInsertId();

    // Insert order items
    $item_stmt = $pdo->prepare("INSERT INTO order_items (order_id, menu_item_id, quantity, unit_price) 
                                VALUES (?, ?, ?, ?)");
    foreach ($cart as $item) {
        $unit_price = $is_elite ? floatval($item['price']) * 0.9 : floatval($item['price']);
        $item_stmt->execute([
            $order_id,
            intval($item['id']),
            intval($item['qty']),
            $unit_price
        ]);
    }

    $pdo->commit();
    ob_end_clean();
    echo json_encode(['success' => true, 'order_id' => $order_id]);

} catch (Exception $e) {
    $pdo->rollBack();
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>