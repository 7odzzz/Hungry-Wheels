<?php
// Get current page for active state
$current = basename($_SERVER['PHP_SELF']);
?>

<div class="p-4">

    <!-- User profile card -->
    <div class="bg-gradient-to-br from-sky-500/10 to-indigo-500/10 border border-sky-500/15 rounded-2xl p-4 mb-4">
        <div class="flex items-center gap-3 mb-3">
            <div class="avatar w-11 h-11 rounded-full flex items-center justify-center text-base text-white font-700 syne flex-shrink-0"
                 style="background: linear-gradient(135deg,#38bdf8,#6366f1)">
                <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
            </div>
            <div class="min-w-0">
                <div class="syne font-700 text-white text-sm truncate"><?= htmlspecialchars($user['full_name']) ?></div>
                <div class="text-slate-400 text-xs truncate"><?= htmlspecialchars($user['email']) ?></div>
            </div>
        </div>

        <?php if ($is_elite): ?>
            <div class="flex items-center gap-2 bg-amber-400/10 border border-amber-400/20 rounded-xl px-3 py-2">
                <span class="text-amber-400 text-base">⭐</span>
                <div>
                    <div class="syne text-amber-400 text-xs font-700">Elite Member</div>
                    <div class="text-amber-400/60 text-xs">10% off every order</div>
                </div>
            </div>
        <?php else: ?>
            <a href="profile.php" class="flex items-center gap-2 bg-sky-400/8 border border-sky-400/15 rounded-xl px-3 py-2 hover:bg-sky-400/15 transition">
                <span class="text-sky-400 text-base">👑</span>
                <div>
                    <div class="syne text-sky-400 text-xs font-700">Go Elite</div>
                    <div class="text-sky-400/60 text-xs">100 EGP/mo · 10% off</div>
                </div>
            </a>
        <?php endif; ?>
    </div>

    <!-- Stats row -->
    <div class="grid grid-cols-2 gap-2 mb-4">
        <div class="stat-card text-center">
            <div class="syne text-xl font-800 text-sky-400"><?= $order_count ?></div>
            <div class="text-slate-500 text-xs mt-0.5">Orders</div>
        </div>
        <div class="stat-card text-center">
            <div class="syne text-xl font-800 text-indigo-400"><?= $is_elite ? '10%' : '0%' ?></div>
            <div class="text-slate-500 text-xs mt-0.5">Discount</div>
        </div>
    </div>

    <!-- Nav sections -->
    <div class="sidebar-section">Main</div>

    <a href="home.php" class="nav-item <?= $current === 'home.php' ? 'active' : '' ?>">
        <span class="icon">🍔</span> Menu
    </a>

    <a href="orders.php" class="nav-item <?= $current === 'orders.php' ? 'active' : '' ?>">
        <span class="icon">📦</span>
        <span class="flex-1">My Orders</span>
        <?php if ($order_count > 0): ?>
            <span class="text-xs bg-sky-400/20 text-sky-400 px-2 py-0.5 rounded-full"><?= $order_count ?></span>
        <?php endif; ?>
    </a>

    <div class="sidebar-section">Account</div>

    <a href="profile.php" class="nav-item <?= $current === 'profile.php' ? 'active' : '' ?>">
        <span class="icon">👤</span> Profile
    </a>

    <a href="profile.php#address" class="nav-item">
        <span class="icon">📍</span> My Addresses
    </a>

    <a href="profile.php#subscription" class="nav-item">
        <span class="icon">⭐</span>
        <span class="flex-1">Subscription</span>
        <?php if ($is_elite): ?>
            <span class="text-xs bg-amber-400/20 text-amber-400 px-1.5 py-0.5 rounded-full">Active</span>
        <?php endif; ?>
    </a>

    <div class="sidebar-section">Support</div>

    <a href="#" class="nav-item">
        <span class="icon">❓</span> Help & FAQ
    </a>

    <a href="#" class="nav-item">
        <span class="icon">💬</span> Contact Us
    </a>

    <!-- Logout at bottom -->
    <div class="mt-6 pt-4 border-t border-slate-800">
        <a href=" /HungryWheels/logout.php" class="nav-item text-red-400/70 hover:text-red-400 hover:bg-red-400/8">
            <span class="icon">🚪</span> Logout
        </a>
    </div>

</div>