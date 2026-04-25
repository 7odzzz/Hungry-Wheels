<?php
session_start();
if (!isset($_SESSION["admin_id"])) {
    header("Location: /HungryWheels/login.php");
    exit();
}
require '../db.php';

$success = "";
$error   = "";

// ── DELETE ────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    try {
        $pdo->prepare("DELETE FROM menu_items WHERE id = ?")->execute([$id]);
        $success = "Item deleted successfully.";
    } catch (Exception $e) {
        $error = "Cannot delete — item may be linked to existing orders.";
    }
}

// ── ADD or EDIT ───────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id          = intval($_POST['id'] ?? 0);
    $name        = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price       = floatval($_POST['price']);
    $category    = trim($_POST['category']);
    $is_available = isset($_POST['is_available']) ? 1 : 0;

    if (empty($name) || empty($category) || $price <= 0) {
        $error = "Please fill in all required fields.";
    } else {
        if ($id > 0) {
            // Edit
            $stmt = $pdo->prepare("UPDATE menu_items SET name=?, description=?, price=?, category=?, is_available=? WHERE id=?");
            $stmt->execute([$name, $description, $price, $category, $is_available, $id]);
            $success = "Item updated successfully.";
        } else {
            // Add
            $stmt = $pdo->prepare("INSERT INTO menu_items (name, description, price, category, is_available) VALUES (?,?,?,?,?)");
            $stmt->execute([$name, $description, $price, $category, $is_available]);
            $success = "Item added successfully.";
        }
    }
}

// ── FETCH item to edit ────────────────────────────────────
$edit_item = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM menu_items WHERE id = ?");
    $stmt->execute([intval($_GET['edit'])]);
    $edit_item = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ── FETCH all items ───────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$sql = "SELECT * FROM menu_items";
$params = [];
if ($search !== '') {
    $sql .= " WHERE name LIKE ? OR category LIKE ?";
    $params = ["%$search%", "%$search%"];
}
$sql .= " ORDER BY category, name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by category
$menu = [];
foreach ($items as $item) {
    $menu[$item['category']][] = $item;
}

// Fetch categories for datalist
$categories = $pdo->query("SELECT DISTINCT category FROM menu_items ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// Stats
$total_items     = $pdo->query("SELECT COUNT(*) FROM menu_items")->fetchColumn();
$available_items = $pdo->query("SELECT COUNT(*) FROM menu_items WHERE is_available = 1")->fetchColumn();
$total_cats      = $pdo->query("SELECT COUNT(DISTINCT category) FROM menu_items")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Management — Hungry Wheels</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    
    
    <style>
        * { font-family: 'DM Sans', sans-serif; }
        h1,h2,h3,h4,.syne { font-family: 'Syne', sans-serif; }
        body { background: #080d14; }
        .navbar-glass {
            background: rgba(8,13,20,0.9);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(168,85,247,0.1);
        }
        .sidebar {
            background: rgba(14,20,32,0.95);
            border-right: 1px solid rgba(51,65,85,0.4);
            width: 260px;
            min-height: calc(100vh - 64px);
            position: sticky; top: 64px;
            height: calc(100vh - 64px);
            overflow-y: auto; flex-shrink: 0;
        }
        .nav-item {
            display: flex; align-items: center; gap: 12px;
            padding: 11px 16px; border-radius: 10px;
            color: #94a3b8; font-size: 14px; font-weight: 500;
            cursor: pointer; transition: all 0.2s;
            text-decoration: none; margin: 2px 0;
        }
        .nav-item:hover { background: rgba(168,85,247,0.08); color: #e2e8f0; }
        .nav-item.active { background: rgba(168,85,247,0.12); color: #a855f7; font-weight: 600; }
        .nav-item .icon {
            width: 36px; height: 36px; border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px; background: rgba(30,41,59,0.8); flex-shrink: 0;
        }
        .nav-item.active .icon { background: rgba(168,85,247,0.15); }
        .sidebar-section {
            font-size: 10px; font-weight: 700; letter-spacing: 0.1em;
            color: #475569; text-transform: uppercase;
            padding: 4px 16px; margin-top: 20px; margin-bottom: 4px;
            font-family: 'Syne', sans-serif;
        }
        .card {
            background: rgba(20,30,48,0.85);
            border: 1px solid rgba(51,65,85,0.5);
            border-radius: 16px;
        }
        .stat-card {
            background: rgba(20,30,48,0.85);
            border: 1px solid rgba(51,65,85,0.5);
            border-radius: 14px; padding: 18px;
        }
        .form-input {
            background: rgba(30,41,59,0.8);
            border: 1.5px solid rgba(51,65,85,0.8);
            color: #e2e8f0; transition: all 0.25s;
            width: 100%; padding: 11px 14px;
            border-radius: 10px; font-size: 14px; outline: none;
            font-family: inherit;
        }
        .form-input::placeholder { color: #475569; }
        .form-input:focus {
            border-color: #a855f7;
            box-shadow: 0 0 0 3px rgba(168,85,247,0.12);
        }
        textarea.form-input { resize: vertical; min-height: 80px; }
        label {
            display: block; font-size: 13px;
            font-weight: 600; color: #94a3b8; margin-bottom: 6px;
        }
        .btn-purple {
            background: #a855f7; color: #fff;
            font-weight: 700; padding: 11px 24px;
            border-radius: 10px; border: none;
            cursor: pointer; transition: all 0.2s;
            font-family: 'Syne', sans-serif; font-size: 14px;
        }
        .btn-purple:hover { background: #9333ea; transform: translateY(-1px); }
        .btn-red {
            background: rgba(239,68,68,0.1); color: #f87171;
            border: 1px solid rgba(239,68,68,0.3);
            font-weight: 600; padding: 7px 14px;
            border-radius: 8px; cursor: pointer;
            transition: all 0.2s; font-size: 12px;
        }
        .btn-red:hover { background: rgba(239,68,68,0.2); }
        .btn-edit {
            background: rgba(56,189,248,0.1); color: #38bdf8;
            border: 1px solid rgba(56,189,248,0.3);
            font-weight: 600; padding: 7px 14px;
            border-radius: 8px; cursor: pointer;
            transition: all 0.2s; font-size: 12px; text-decoration: none;
            display: inline-block;
        }
        .btn-edit:hover { background: rgba(56,189,248,0.2); }
        .search-input {
            background: rgba(30,41,59,0.8);
            border: 1.5px solid rgba(51,65,85,0.8);
            color: #e2e8f0; transition: all 0.25s;
        }
        .search-input::placeholder { color: #475569; }
        .search-input:focus {
            border-color: #a855f7;
            box-shadow: 0 0 0 3px rgba(168,85,247,0.12);
            outline: none;
        }
        .cat-heading {
            font-family: 'Syne', sans-serif;
            font-weight: 700; color: #a855f7;
            font-size: 15px; margin-bottom: 10px;
            padding-bottom: 6px;
            border-bottom: 1.5px solid rgba(168,85,247,0.2);
        }
        .item-row {
            background: rgba(15,23,42,0.6);
            border: 1px solid rgba(51,65,85,0.4);
            border-radius: 12px; padding: 14px 16px;
            display: flex; align-items: center;
            gap: 12px; transition: all 0.2s;
            animation: fadeUp 0.3s ease both;
        }
        .item-row:hover {
            border-color: rgba(168,85,247,0.25);
        }
        @keyframes fadeUp {
            from { opacity:0; transform:translateY(12px); }
            to   { opacity:1; transform:translateY(0); }
        }
        #sidebar-overlay {
            display:none; position:fixed; inset:0;
            background:rgba(0,0,0,0.6); z-index:40; backdrop-filter:blur(2px);
        }
        #sidebar-overlay.open { display:block; }
        #mobile-sidebar { transform:translateX(-100%); transition:transform 0.3s ease; }
        #mobile-sidebar.open { transform:translateX(0); }
        .bg-glow {
            position:fixed; top:-200px; left:200px;
            width:600px; height:600px;
            background:radial-gradient(circle,rgba(168,85,247,0.05) 0%,transparent 70%);
            pointer-events:none; z-index:0;
            animation:floatGlow 8s ease-in-out infinite;
        }
        @keyframes floatGlow {
            0%,100%{transform:translate(0,0);}
            50%{transform:translate(40px,40px);}
        }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #080d14; }
        ::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 4px; }

        /* Modal */
        #confirm-modal {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.7); z-index: 100;
            align-items: center; justify-content: center;
            backdrop-filter: blur(4px);
        }
        #confirm-modal.open { display: flex; }
        .modal-box {
            background: #1e293b;
            border: 1px solid rgba(239,68,68,0.3);
            border-radius: 16px; padding: 28px;
            max-width: 380px; width: 90%; text-align: center;
        }
    </style>


</head>
<body class="min-h-screen text-slate-200">
<div class="bg-glow"></div>

<!-- Delete confirm modal -->
<div id="confirm-modal">
    <div class="modal-box">
        <div class="text-4xl mb-3">🗑️</div>
        <h3 class="syne text-lg font-800 text-white mb-2">Delete Item?</h3>
        <p class="text-slate-400 text-sm mb-6">This action cannot be undone.</p>
        <div class="flex gap-3 justify-center">
            <button onclick="closeModal()" class="px-5 py-2.5 rounded-xl border border-slate-600 text-slate-300 hover:bg-slate-700 transition text-sm font-600">Cancel</button>
            <a id="confirm-delete-btn" href="#" class="px-5 py-2.5 rounded-xl bg-red-500 hover:bg-red-600 text-white transition text-sm font-700">Delete</a>
        </div>
    </div>
</div>

<!-- NAVBAR -->
<nav class="navbar-glass sticky top-0 z-50 h-16">
    <div class="h-full px-4 sm:px-6 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <button onclick="toggleSidebar()" class="text-slate-400 hover:text-purple-400 p-1.5 rounded-lg hover:bg-slate-800 transition lg:hidden">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
            <a href="dashboard.php" class="syne text-lg font-800 text-purple-400 tracking-tight flex items-center gap-2">
                🔐 <span>Admin Panel</span>
            </a>
        </div>
        <div class="flex items-center gap-3">
            <span class="hidden sm:inline text-xs font-700 px-3 py-1 rounded-full syne"
                style="background:linear-gradient(135deg,#a855f7,#7c3aed);color:#fff">👑 Admin</span>
            <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm text-white font-700"
                style="background:linear-gradient(135deg,#a855f7,#7c3aed)">
                <?= strtoupper(substr($_SESSION["admin_name"], 0, 1)) ?>
            </div>
        </div>
    </div>
</nav>

<div class="relative z-10 flex">
    <div id="sidebar-overlay" onclick="toggleSidebar()"></div>
    <aside class="sidebar hidden lg:block">
        <?php include 'Admin-sidebar.php'; ?>
    </aside>
    <aside id="mobile-sidebar" class="fixed top-16 left-0 z-50 sidebar lg:hidden"
        style="min-height:calc(100vh - 64px);height:calc(100vh - 64px);">
        <?php include 'Admin-sidebar.php'; ?>
    </aside>

    <!-- MAIN -->
    <main class="flex-1 min-w-0 px-4 sm:px-6 lg:px-8 py-8">
        <div class="max-w-6xl mx-auto">

            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="syne text-3xl font-800 text-white mb-1">Menu Management</h2>
                    <p class="text-slate-400 text-sm">Add, edit or remove menu items</p>
                </div>
                <button onclick="openForm()"
                    class="btn-purple flex items-center gap-2">
                    ➕ Add Item
                </button>
            </div>

            <!-- Success / Error -->
            <?php if ($success): ?>
                <div class="bg-green-400/10 border border-green-400/30 text-green-400 px-4 py-3 rounded-xl text-sm font-600 mb-5">
                    ✅ <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="bg-red-400/10 border border-red-400/30 text-red-400 px-4 py-3 rounded-xl text-sm font-600 mb-5">
                    ❌ <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="stat-card text-center">
                    <div class="syne text-2xl font-800 text-purple-400"><?= $total_items ?></div>
                    <div class="text-slate-500 text-xs mt-1">Total Items</div>
                </div>
                <div class="stat-card text-center">
                    <div class="syne text-2xl font-800 text-green-400"><?= $available_items ?></div>
                    <div class="text-slate-500 text-xs mt-1">Available</div>
                </div>
                <div class="stat-card text-center">
                    <div class="syne text-2xl font-800 text-sky-400"><?= $total_cats ?></div>
                    <div class="text-slate-500 text-xs mt-1">Categories</div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- ADD / EDIT FORM -->
                <div class="lg:col-span-1">
                    <div class="card p-5" id="item-form-card">
                        
                    <h3 class="syne font-800 text-white mb-4" id="form-title">
                            <?= $edit_item ? '✏️ Edit Item' : '➕ Add New Item' ?>
                    
                        </h3>
                    
                        <form method="POST" action="" id="item-form">
                            <input type="hidden" name="id" id="form-id"
                                value="<?= $edit_item ? $edit_item['id'] : 0 ?>">

                            <div class="mb-3">
                                <label>Item name *</label>
                                <input type="text" name="name" class="form-input"
                                    placeholder="e.g. Classic Burger"
                                    value="<?= $edit_item ? htmlspecialchars($edit_item['name']) : '' ?>" required>
                            </div>

                            <div class="mb-3">
                                <label>Category *</label>
                                <input type="text" name="category" class="form-input"
                                    placeholder="e.g. Burgers"
                                    list="cat-list"
                                    value="<?= $edit_item ? htmlspecialchars($edit_item['category']) : '' ?>" required>
                                <datalist id="cat-list">
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat) ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>

                            <div class="mb-3">
                                <label>Price (EGP) *</label>
                                <input type="number" name="price" class="form-input"
                                    placeholder="0.00" step="0.01" min="0"
                                    value="<?= $edit_item ? $edit_item['price'] : '' ?>" required>
                            </div>

                            <div class="mb-4">
                                <label>Description</label>
                                <textarea name="description" class="form-input"
                                    placeholder="Short description of the item..."><?= $edit_item ? htmlspecialchars($edit_item['description']) : '' ?></textarea>
                            </div>

                            <div class="mb-5 flex items-center gap-3">
                                <input type="checkbox" name="is_available" id="is_available"
                                    class="w-4 h-4 accent-purple-500"
                                    <?= (!$edit_item || $edit_item['is_available']) ? 'checked' : '' ?>>
                                <label for="is_available" class="mb-0 cursor-pointer text-slate-300">Available on menu</label>
                            </div>

                            <div class="flex gap-2">
                                <button type="submit" class="btn-purple flex-1" id="form-submit-btn">
                                    <?= $edit_item ? 'Save Changes' : 'Add Item' ?>
                                </button>
                                <?php if ($edit_item): ?>
                                    <a href="AdminMenu.php" class="px-4 py-2.5 rounded-xl border border-slate-600 text-slate-400 hover:bg-slate-700 transition text-sm font-600 flex items-center">
                                        Cancel
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ITEMS LIST -->
                <div class="lg:col-span-2">

                    <!-- Search -->
                    <form method="GET" action="" class="mb-4">
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                            </svg>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                                placeholder="Search items or categories..."
                                class="search-input w-full pl-10 pr-4 py-2.5 rounded-xl text-sm">
                        </div>
                    </form>

                    <?php if (empty($menu)): ?>
                        <div class="text-center py-16 text-slate-500">
                            <div class="text-4xl mb-3">🍽️</div>
                            <p>No items found</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-5">
                            <?php foreach ($menu as $category => $cat_items): ?>
                                <div>
                                    <div class="cat-heading"><?= htmlspecialchars($category) ?> <span class="text-slate-600 text-xs font-400">(<?= count($cat_items) ?>)</span></div>
                                    <div class="space-y-2">
                                        <?php foreach ($cat_items as $i => $item): ?>
                                            <div class="item-row" style="animation-delay:<?= $i * 0.04 ?>s">
                                                <!-- Availability dot -->
                                                <div class="w-2 h-2 rounded-full flex-shrink-0 <?= $item['is_available'] ? 'bg-green-400' : 'bg-red-400' ?>"></div>

                                                <!-- Info -->
                                                <div class="flex-1 min-w-0">
                                                    <div class="syne font-700 text-white text-sm truncate"><?= htmlspecialchars($item['name']) ?></div>
                                                    <div class="text-slate-500 text-xs truncate"><?= htmlspecialchars($item['description']) ?></div>
                                                </div>

                                                <!-- Price -->
                                                <div class="text-sky-400 font-700 text-sm syne flex-shrink-0">
                                                    <?= number_format($item['price'], 0) ?> EGP
                                                </div>

                                                <!-- Actions -->
                                                <div class="flex gap-2 flex-shrink-0">
                                                    <a href="AdminMenu.php?edit=<?= $item['id'] ?>" class="btn-edit">✏️ Edit</a>
                                                    <button onclick="confirmDelete(<?= $item['id'] ?>, '<?= addslashes($item['name']) ?>')"
                                                        class="btn-red">🗑️ Delete</button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </main>
</div>

<script>
function toggleSidebar() {
    document.getElementById('mobile-sidebar').classList.toggle('open');
    document.getElementById('sidebar-overlay').classList.toggle('open');
}

// Auto open form if edit mode
<?php if ($edit_item || isset($_GET['action']) && $_GET['action'] === 'add'): ?>
document.getElementById('item-form-card').scrollIntoView({ behavior: 'smooth', block: 'start' });
<?php endif; ?>

// Delete confirm modal
function confirmDelete(id, name) {
    document.getElementById('confirm-delete-btn').href = 'AdminMenu.php?delete=' + id;
    document.getElementById('confirm-modal').classList.add('open');
}
function closeModal() {
    document.getElementById('confirm-modal').classList.remove('open');
}

// Open form (scroll to it on mobile)
function openForm() {
    document.getElementById('item-form-card').scrollIntoView({ behavior: 'smooth', block: 'start' });
    document.querySelector('[name="name"]').focus();
}
</script>

</body>
</html>