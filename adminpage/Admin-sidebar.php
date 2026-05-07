<?php $current = basename($_SERVER['PHP_SELF']); ?>
<div class="p-4">

    <!-- Admin profile card -->
    <div class="bg-gradient-to-br from-purple-500/10 to-violet-500/10 border border-purple-500/15 rounded-2xl p-4 mb-4">
        <div class="flex items-center gap-3">
            <div class="w-11 h-11 rounded-full flex items-center justify-center text-base text-white font-700 syne flex-shrink-0"
                 style="background: linear-gradient(135deg,#a855f7,#7c3aed)">
                <?= strtoupper(substr($_SESSION["admin_name"], 0, 1)) ?>
            </div>
            <div class="min-w-0">
                <div class="syne font-700 text-white text-sm truncate"><?= htmlspecialchars($_SESSION["admin_name"]) ?></div>
                <div class="text-purple-400 text-xs font-600">Administrator</div>
            </div>
        </div>
    </div>

    <div class="sidebar-section">Main</div>

    <a href="dashboard.php" class="nav-item <?= $current === 'dashboard.php' ? 'active' : '' ?>">
        <span class="icon">📊</span> Dashboard
    </a>

    <div class="sidebar-section">Management</div>

    <a href="AdminMenu.php" class="nav-item <?= $current === 'menu.php' ? 'active' : '' ?>">
        <span class="icon">🍽️</span> Menu Items
    </a>

    <a href="AdminOrders.php" class="nav-item <?= $current === 'orders.php' ? 'active' : '' ?>">
        <span class="icon">📦</span>
        <span class="flex-1">Orders</span>
        <?php
        $p = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
        if ($p > 0):
        ?>
            <span class="text-xs bg-amber-400/20 text-amber-400 px-2 py-0.5 rounded-full"><?= $p ?></span>
        <?php endif; ?>
    </a>

    <a href="AdminUsers.php" class="nav-item <?= $current === 'users.php' ? 'active' : '' ?>">
        <span class="icon">👥</span> Users
    </a>

    <div class="sidebar-section">Account</div>

        <a href="Admin-profile.php" class="nav-item <?= $current === 'Admin-profile.php' ? 'active' : '' ?>">
            <span class="icon">👤</span> My Profile
        </a>

        <a href="/HungryWheels/logout.php" class="nav-item text-red-400/70 hover:text-red-400 hover:bg-red-400/8">
            <span class="icon">🚪</span> Logout
        </a>
</div>