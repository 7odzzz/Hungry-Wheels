<?php
// ══════════════════════════════════════════════════════
//  Hungry Wheels — Reward Points Engine
//  Include this file after a successful order placement
// ══════════════════════════════════════════════════════

// ── Milestone definitions ─────────────────────────────
// Each milestone: points needed → wallet reward in EGP
function getRewardMilestones() {
    return [
        200  => ['wallet' => 25,   'label' => '🎉 25 EGP wallet bonus',                     'elite' => false],
        500  => ['wallet' => 75,   'label' => '🔥 75 EGP wallet bonus',                     'elite' => false],
        1000 => ['wallet' => 150,  'label' => '💎 150 EGP wallet bonus',                    'elite' => false],
        2000 => ['wallet' => 300,  'label' => '👑 300 EGP wallet bonus',                    'elite' => false],
        5000 => ['wallet' => 1000, 'label' => '🏆 1000 EGP wallet bonus + permanent Elite', 'elite' => true ],
    ];
}

// ── Main function: call after every successful order ──
// Returns: ['points_earned' => int, 'rewards_unlocked' => array, 'reason' => string]
function processOrderRewards($pdo, $user_id, $order_id, $order_total) {

    $points_to_add    = 0;
    $reason           = '';
    $rewards_unlocked = [];

    // ── Step 1: Check if order qualifies for points ───
    if ($order_total < 1000) {
        return [
            'points_earned'    => 0,
            'rewards_unlocked' => [],
            'reason'           => 'Order total below minimum threshold',
        ];
    }

    // ── Step 2: Check this order was not already rewarded ──
    // (prevents abuse if processOrderRewards is called twice)
    $check = $pdo->prepare('SELECT id FROM points_log WHERE order_id = ?');
    $check->execute([$order_id]);
    if ($check->fetch()) {
        return [
            'points_earned'    => 0,
            'rewards_unlocked' => [],
            'reason'           => 'Order already rewarded',
        ];
    }

    // ── Step 3: Determine points to add based on order total ──
    if ($order_total >= 4000) {
        $points_to_add = 100;
        $reason        = 'Order exceeded 4000 EGP';
    } elseif ($order_total >= 2000) {
        $points_to_add = 50;
        $reason        = 'Order exceeded 2000 EGP';
    } else {
        // $order_total >= 1000 (already confirmed above)
        $points_to_add = 25;
        $reason        = 'Order exceeded 1000 EGP';
    }

    // ── Step 4: Add points to the user ───────────────
    $pdo->prepare('
        UPDATE users
        SET current_points      = current_points      + ?,
            total_points_earned = total_points_earned + ?
        WHERE id = ?
    ')->execute([$points_to_add, $points_to_add, $user_id]);

    // ── Step 5: Log the points transaction ───────────
    $pdo->prepare('
        INSERT INTO points_log (user_id, order_id, points_added, reason)
        VALUES (?, ?, ?, ?)
    ')->execute([$user_id, $order_id, $points_to_add, $reason]);

    // ── Step 6: Fetch updated totals ─────────────────
    $user_stmt = $pdo->prepare('SELECT current_points, total_points_earned FROM users WHERE id = ?');
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

    $total_earned = (int) $user['total_points_earned'];

    // If user has maxed out (>= 5000 points), suppress points messages
        if ($total_earned >= 5000) {
            return [
                'points_earned'    => 0,
                'rewards_unlocked' => [],
                'reason'           => 'Max milestone reached'
            ];
        }


    // ── Step 7: Check milestones ──────────────────────
    // We check total_points_earned (lifetime) not current_points
    // so spending points does not remove milestone eligibility
    $milestones = getRewardMilestones();

    foreach ($milestones as $milestone_points => $reward) {

        // Has the user crossed this milestone with their lifetime points?
        if ($total_earned < $milestone_points) {
            continue;
        }

        // Has this milestone already been claimed?
        $already = $pdo->prepare('
            SELECT id FROM reward_claims
            WHERE user_id = ? AND milestone = ?
        ');
        $already->execute([$user_id, $milestone_points]);

        if ($already->fetch()) {
            continue; // already claimed, skip
        }

        // ── New milestone reached! Give the reward ────
        // Add wallet balance
        $pdo->prepare('
            UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?
        ')->execute([$reward['wallet'], $user_id]);

        // Give Elite status if milestone includes it
        if ($reward['elite']) {
            $pdo->prepare('
                UPDATE users SET is_elite = 1 WHERE id = ?
            ')->execute([$user_id]);
        }

        // Record the claim so it never triggers again
        $pdo->prepare('
            INSERT INTO reward_claims (user_id, milestone, reward_type, reward_value)
            VALUES (?, ?, \'wallet\', ?)
        ')->execute([$user_id, $milestone_points, $reward['wallet']]);

        // Collect rewards unlocked this order
        $rewards_unlocked[] = [
            'milestone' => $milestone_points,
            'label'     => $reward['label'],
            'wallet'    => $reward['wallet'],
            'elite'     => $reward['elite'],
        ];
    }

    return [
        'points_earned'    => $points_to_add,
        'rewards_unlocked' => $rewards_unlocked,
        'reason'           => $reason,
    ];
}
?>