<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: /HungryWheels/login.php');
    exit();
}
require '../db.php';

$success = '';
$error   = '';

// ── DELETE MENU ITEM ──────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    try {
        $pdo->prepare('DELETE FROM menu_items WHERE id=?')->execute([$id]);
        $success = 'Item deleted successfully.';
    } catch (Exception $e) {
        $error = 'Cannot delete — item may be linked to existing orders.';
    }
}

// ── ADD or EDIT MENU ITEM ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_item'])) {
    $id                  = intval($_POST['id']           ?? 0);
    $name                = trim($_POST['name']           ?? '');
    $description         = trim($_POST['description']    ?? '');
    $price               = floatval($_POST['price']      ?? 0);
    $category            = trim($_POST['category']       ?? '');
    $image_url           = trim($_POST['image_url']      ?? '');
    $is_available        = isset($_POST['is_available'])        ? 1 : 0;
    $use_category_sizes  = isset($_POST['use_category_sizes'])  ? 1 : 0;
    $use_category_extras = isset($_POST['use_category_extras']) ? 1 : 0;

    if (empty($name) || empty($category) || $price <= 0) {
        $error = 'Please fill in all required fields.';
    } else {
        // Ensure category exists
        $pdo->prepare('INSERT IGNORE INTO categories (name) VALUES (?)')->execute([$category]);

        if ($id > 0) {
            $pdo->prepare('
                UPDATE menu_items
                SET name=?, description=?, price=?, category=?, image_url=?,
                    is_available=?, use_category_sizes=?, use_category_extras=?
                WHERE id=?
            ')->execute([$name, $description, $price, $category, $image_url,
                         $is_available, $use_category_sizes, $use_category_extras, $id]);

            // Handle item-level size overrides
            if (!$use_category_sizes) {
                $sizes_json = json_decode($_POST['item_sizes_json'] ?? '[]', true);
                $pdo->prepare('DELETE FROM item_sizes WHERE item_id=?')->execute([$id]);
                $st = $pdo->prepare('INSERT INTO item_sizes (item_id, label, price_extra, sort_order, is_default) VALUES (?,?,?,?,?)');
                foreach ($sizes_json as $i => $s) {
                    $st->execute([$id, trim($s['label']), floatval($s['price_extra']), $i, !empty($s['is_default']) ? 1 : 0]);
                }
            }

            // Handle item-level extra overrides
            if (!$use_category_extras) {
                $extras_json = json_decode($_POST['item_extras_json'] ?? '[]', true);
                $pdo->prepare('DELETE FROM item_extras WHERE item_id=?')->execute([$id]);
                $st = $pdo->prepare('INSERT INTO item_extras (item_id, label, price, sort_order) VALUES (?,?,?,?)');
                foreach ($extras_json as $i => $e) {
                    $st->execute([$id, trim($e['label']), floatval($e['price']), $i]);
                }
            }

            $success = 'Item updated successfully.';
        } else {
            $pdo->prepare('
                INSERT INTO menu_items
                    (name, description, price, category, image_url, is_available, use_category_sizes, use_category_extras)
                VALUES (?,?,?,?,?,?,?,?)
            ')->execute([$name, $description, $price, $category, $image_url,
                         $is_available, $use_category_sizes, $use_category_extras]);
            $new_id = $pdo->lastInsertId();

            // Save item-level overrides for new item
            if (!$use_category_sizes) {
                $sizes_json = json_decode($_POST['item_sizes_json'] ?? '[]', true);
                $st = $pdo->prepare('INSERT INTO item_sizes (item_id, label, price_extra, sort_order, is_default) VALUES (?,?,?,?,?)');
                foreach ($sizes_json as $i => $s) {
                    $st->execute([$new_id, trim($s['label']), floatval($s['price_extra']), $i, !empty($s['is_default']) ? 1 : 0]);
                }
            }
            if (!$use_category_extras) {
                $extras_json = json_decode($_POST['item_extras_json'] ?? '[]', true);
                $st = $pdo->prepare('INSERT INTO item_extras (item_id, label, price, sort_order) VALUES (?,?,?,?)');
                foreach ($extras_json as $i => $e) {
                    $st->execute([$new_id, trim($e['label']), floatval($e['price']), $i]);
                }
            }

            $success = 'Item added successfully.';
        }
    }
}

// ── AJAX: get category config (sizes + extras) ────────────────────
if (isset($_GET['get_category_config'])) {
    header('Content-Type: application/json');
    $cat_name = trim($_GET['get_category_config']);
    $cat = $pdo->prepare('SELECT * FROM categories WHERE name=?');
    $cat->execute([$cat_name]);
    $category = $cat->fetch(PDO::FETCH_ASSOC);

    $sizes  = [];
    $extras = [];
    if ($category) {
        $sq = $pdo->prepare('SELECT * FROM category_sizes WHERE category_id=? ORDER BY sort_order');
        $sq->execute([$category['id']]);
        $sizes = $sq->fetchAll(PDO::FETCH_ASSOC);

        $eq = $pdo->prepare('SELECT * FROM category_extras WHERE category_id=? ORDER BY sort_order');
        $eq->execute([$category['id']]);
        $extras = $eq->fetchAll(PDO::FETCH_ASSOC);
    }
    echo json_encode(['category' => $category, 'sizes' => $sizes, 'extras' => $extras]);
    exit();
}

// ── AJAX: get item overrides ──────────────────────────────────────
if (isset($_GET['get_item_overrides'])) {
    header('Content-Type: application/json');
    $item_id = intval($_GET['get_item_overrides']);

    $sq = $pdo->prepare('SELECT * FROM item_sizes WHERE item_id=? ORDER BY sort_order');
    $sq->execute([$item_id]);
    $item_sizes = $sq->fetchAll(PDO::FETCH_ASSOC);

    $eq = $pdo->prepare('SELECT * FROM item_extras WHERE item_id=? ORDER BY sort_order');
    $eq->execute([$item_id]);
    $item_extras = $eq->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['sizes' => $item_sizes, 'extras' => $item_extras]);
    exit();
}

// ── FETCH item to edit ────────────────────────────────────────────
$edit_item = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM menu_items WHERE id=?');
    $stmt->execute([intval($_GET['edit'])]);
    $edit_item = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ── FETCH all items ───────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$sql    = 'SELECT * FROM menu_items';
$params = [];
if ($search !== '') {
    $sql   .= ' WHERE name LIKE ? OR category LIKE ?';
    $params = ["%$search%", "%$search%"];
}
$sql .= ' ORDER BY category, name';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$menu = [];
foreach ($items as $item) {
    $menu[$item['category']][] = $item;
}

// ── Fetch categories with size/extra info ─────────────────────────
$categories = $pdo->query('
    SELECT c.*, COUNT(cs.id) AS size_count, COUNT(ce.id) AS extra_count
    FROM categories c
    LEFT JOIN category_sizes  cs ON cs.category_id = c.id
    LEFT JOIN category_extras ce ON ce.category_id = c.id
    GROUP BY c.id
    ORDER BY c.sort_order, c.name
')->fetchAll(PDO::FETCH_ASSOC);

$category_names  = array_column($categories, 'name');
$total_items     = $pdo->query('SELECT COUNT(*) FROM menu_items')->fetchColumn();
$available_items = $pdo->query('SELECT COUNT(*) FROM menu_items WHERE is_available=1')->fetchColumn();
$total_cats      = count($categories);

// Build a JS-friendly categories map for the frontend
$cat_map = [];
foreach ($categories as $c) {
    $cat_map[$c['name']] = [
        'sizes_enabled'  => (bool) $c['sizes_enabled'],
        'extras_enabled' => (bool) $c['extras_enabled'],
        'size_count'     => (int)  $c['size_count'],
        'extra_count'    => (int)  $c['extra_count'],
    ];
}
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
.navbar-glass { background:rgba(8,13,20,0.9); backdrop-filter:blur(16px); border-bottom:1px solid rgba(168,85,247,0.1); }
.sidebar { background:rgba(14,20,32,0.95); border-right:1px solid rgba(51,65,85,0.4); width:260px; min-height:calc(100vh - 64px); position:sticky; top:64px; height:calc(100vh - 64px); overflow-y:auto; flex-shrink:0; }
.nav-item { display:flex; align-items:center; gap:12px; padding:11px 16px; border-radius:10px; color:#94a3b8; font-size:14px; font-weight:500; cursor:pointer; transition:all 0.2s; text-decoration:none; margin:2px 0; }
.nav-item:hover { background:rgba(168,85,247,0.08); color:#e2e8f0; }
.nav-item.active { background:rgba(168,85,247,0.12); color:#a855f7; font-weight:600; }
.nav-item .icon { width:36px; height:36px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:16px; background:rgba(30,41,59,0.8); flex-shrink:0; }
.nav-item.active .icon { background:rgba(168,85,247,0.15); }
.sidebar-section { font-size:10px; font-weight:700; letter-spacing:0.1em; color:#475569; text-transform:uppercase; padding:4px 16px; margin-top:20px; margin-bottom:4px; font-family:'Syne',sans-serif; }
.card { background:rgba(20,30,48,0.85); border:1px solid rgba(51,65,85,0.5); border-radius:16px; }
.stat-card { background:rgba(20,30,48,0.85); border:1px solid rgba(51,65,85,0.5); border-radius:14px; padding:18px; }
.form-input { background:rgba(30,41,59,0.8); border:1.5px solid rgba(51,65,85,0.8); color:#e2e8f0; transition:all 0.25s; width:100%; padding:10px 14px; border-radius:10px; font-size:14px; outline:none; font-family:inherit; }
.form-input::placeholder { color:#475569; }
.form-input:focus { border-color:#a855f7; box-shadow:0 0 0 3px rgba(168,85,247,0.12); }
.form-input.valid   { border-color:#4ade80; }
.form-input.invalid { border-color:#f87171; }
textarea.form-input { resize:vertical; min-height:80px; }
label.field-label { display:block; font-size:12px; font-weight:700; color:#94a3b8; margin-bottom:5px; text-transform:uppercase; letter-spacing:0.05em; }
.btn-purple { background:#a855f7; color:#fff; font-weight:700; padding:11px 24px; border-radius:10px; border:none; cursor:pointer; transition:all 0.2s; font-family:'Syne',sans-serif; font-size:14px; }
.btn-purple:hover { background:#9333ea; transform:translateY(-1px); }
.btn-ghost { background:rgba(30,41,59,0.6); color:#94a3b8; border:1.5px solid rgba(51,65,85,0.7); font-weight:600; padding:9px 18px; border-radius:10px; cursor:pointer; transition:all 0.2s; font-size:13px; border-style:solid; }
.btn-ghost:hover { background:rgba(51,65,85,0.6); color:#e2e8f0; }
.btn-red { background:rgba(239,68,68,0.1); color:#f87171; border:1px solid rgba(239,68,68,0.3); font-weight:600; padding:7px 14px; border-radius:8px; cursor:pointer; transition:all 0.2s; font-size:12px; }
.btn-red:hover { background:rgba(239,68,68,0.2); }
.btn-edit { background:rgba(56,189,248,0.1); color:#38bdf8; border:1px solid rgba(56,189,248,0.3); font-weight:600; padding:7px 14px; border-radius:8px; cursor:pointer; transition:all 0.2s; font-size:12px; text-decoration:none; display:inline-block; }
.btn-edit:hover { background:rgba(56,189,248,0.2); }
.search-input { background:rgba(30,41,59,0.8); border:1.5px solid rgba(51,65,85,0.8); color:#e2e8f0; transition:all 0.25s; }
.search-input::placeholder { color:#475569; }
.search-input:focus { border-color:#a855f7; box-shadow:0 0 0 3px rgba(168,85,247,0.12); outline:none; }
.cat-heading { font-family:'Syne',sans-serif; font-weight:700; color:#a855f7; font-size:15px; margin-bottom:10px; padding-bottom:6px; border-bottom:1.5px solid rgba(168,85,247,0.2); }
.item-row { background:rgba(15,23,42,0.6); border:1px solid rgba(51,65,85,0.4); border-radius:12px; padding:14px 16px; display:flex; align-items:center; gap:12px; transition:all 0.2s; animation:fadeUp 0.3s ease both; }
.item-row:hover { border-color:rgba(168,85,247,0.25); }
.item-thumb { width:40px; height:40px; border-radius:8px; object-fit:cover; flex-shrink:0; background:rgba(30,41,59,0.8); display:flex; align-items:center; justify-content:center; font-size:18px; overflow:hidden; }
.item-thumb img { width:100%; height:100%; object-fit:cover; }
select.form-input { appearance:none; cursor:pointer; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394a3b8'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 12px center; background-size:16px; padding-right:36px; }
select.form-input option { background:#1e293b; color:#e2e8f0; }
/* Image preview */
#img-preview-wrap { width:100%; border-radius:12px; overflow:hidden; background:rgba(15,23,42,0.8); border:1.5px dashed rgba(51,65,85,0.6); transition:all 0.3s; margin-bottom:4px; }
#img-preview-wrap.has-image { border-style:solid; border-color:#4ade80; }
#img-preview-wrap.has-error { border-color:#f87171; }
#img-preview { width:100%; height:140px; object-fit:cover; display:none; }
#img-placeholder { height:80px; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:4px; color:#475569; font-size:12px; }
#img-status { font-size:11px; font-weight:600; padding:3px 8px; border-radius:6px; display:none; margin-top:4px; }
#img-status.valid   { background:rgba(74,222,128,0.1); color:#4ade80; display:block; }
#img-status.invalid { background:rgba(248,113,113,0.1); color:#f87171; display:block; }
#img-status.loading { background:rgba(168,85,247,0.1); color:#a855f7; display:block; }
/* Overrides sections */
.override-section { background:rgba(15,23,42,0.5); border:1.5px solid rgba(51,65,85,0.5); border-radius:12px; padding:14px; }
.override-section.active { border-color:rgba(168,85,247,0.3); }
.size-mini-row,.extra-mini-row { background:rgba(30,41,59,0.6); border:1px solid rgba(51,65,85,0.4); border-radius:8px; padding:8px 10px; display:flex; align-items:center; gap:8px; }
.mini-input { background:rgba(15,23,42,0.8); border:1.5px solid rgba(51,65,85,0.7); color:#e2e8f0; border-radius:7px; padding:6px 9px; font-size:12px; outline:none; font-family:inherit; transition:border-color 0.2s; }
.mini-input:focus { border-color:#a855f7; }
.toggle-switch { position:relative; display:inline-block; width:40px; height:22px; }
.toggle-switch input { opacity:0; width:0; height:0; }
.toggle-slider { position:absolute; inset:0; background:#1e293b; border:1.5px solid rgba(51,65,85,0.8); border-radius:22px; cursor:pointer; transition:all 0.3s; }
.toggle-slider:before { content:''; position:absolute; height:14px; width:14px; left:2px; bottom:2px; background:#475569; border-radius:50%; transition:all 0.3s; }
.toggle-switch input:checked + .toggle-slider { background:rgba(168,85,247,0.2); border-color:#a855f7; }
.toggle-switch input:checked + .toggle-slider:before { transform:translateX(18px); background:#a855f7; }
.badge { font-size:10px; font-weight:700; padding:2px 7px; border-radius:20px; font-family:'Syne',sans-serif; }
.badge-green { background:rgba(74,222,128,0.12); color:#4ade80; border:1px solid rgba(74,222,128,0.2); }
.badge-purple { background:rgba(168,85,247,0.12); color:#c084fc; border:1px solid rgba(168,85,247,0.2); }
.badge-slate { background:rgba(51,65,85,0.3); color:#64748b; border:1px solid rgba(51,65,85,0.4); }
/* Confirm modal */
#confirm-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:100; align-items:center; justify-content:center; backdrop-filter:blur(4px); }
#confirm-modal.open { display:flex; }
.modal-box { background:#1e293b; border:1px solid rgba(168,85,247,0.3); border-radius:16px; padding:28px; max-width:400px; width:90%; }
@keyframes fadeUp { from{opacity:0;transform:translateY(12px);}to{opacity:1;transform:translateY(0);} }
.bg-glow { position:fixed; top:-200px; left:200px; width:600px; height:600px; background:radial-gradient(circle,rgba(168,85,247,0.05) 0%,transparent 70%); pointer-events:none; z-index:0; animation:floatGlow 8s ease-in-out infinite; }
@keyframes floatGlow { 0%,100%{transform:translate(0,0);}50%{transform:translate(40px,40px);} }
::-webkit-scrollbar{width:6px;} ::-webkit-scrollbar-track{background:#080d14;} ::-webkit-scrollbar-thumb{background:#1e293b;border-radius:4px;}
#sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:40;backdrop-filter:blur(2px);}
#sidebar-overlay.open{display:block;}
#mobile-sidebar{transform:translateX(-100%);transition:transform 0.3s ease;}
#mobile-sidebar.open{transform:translateX(0);}
</style>
</head>
<body class="min-h-screen text-slate-200">
<div class="bg-glow"></div>

<!-- DELETE CONFIRM MODAL -->
<div id="confirm-modal">
    <div class="modal-box" style="border-color:rgba(239,68,68,0.3);">
        <div class="text-4xl mb-3 text-center">🗑️</div>
        <h3 class="syne text-lg font-800 text-white mb-2 text-center">Delete Item?</h3>
        <p class="text-slate-400 text-sm mb-6 text-center">This action cannot be undone.</p>
        <div class="flex gap-3 justify-center">
            <button onclick="closeDeleteModal()" class="px-5 py-2.5 rounded-xl border border-slate-600 text-slate-300 hover:bg-slate-700 transition text-sm font-600">Cancel</button>
            <a id="confirm-delete-btn" href="#" class="px-5 py-2.5 rounded-xl bg-red-500 hover:bg-red-600 text-white transition text-sm font-700">Delete</a>
        </div>
    </div>
</div>

<!-- NAVBAR -->
<nav class="navbar-glass sticky top-0 z-50 h-16">
    <div class="h-full px-4 sm:px-6 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <button onclick="toggleSidebar()" class="text-slate-400 hover:text-purple-400 p-1.5 rounded-lg hover:bg-slate-800 transition lg:hidden">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <a href="dashboard.php" class="syne text-lg font-800 text-purple-400 tracking-tight flex items-center gap-2">🔐 <span>Admin Panel</span></a>
        </div>
        <div class="flex items-center gap-3">
            <a href="AdminCategories.php" class="text-xs font-700 px-3 py-1.5 rounded-lg border border-purple-400/30 text-purple-400 hover:bg-purple-400/10 transition syne">🗂️ Manage Categories</a>
            <span class="hidden sm:inline text-xs font-700 px-3 py-1 rounded-full syne" style="background:linear-gradient(135deg,#a855f7,#7c3aed);color:#fff">👑 Admin</span>
            <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm text-white font-700" style="background:linear-gradient(135deg,#a855f7,#7c3aed)">
                <?= strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1)) ?>
            </div>
        </div>
    </div>
</nav>

<div class="relative z-10 flex">
    <div id="sidebar-overlay" onclick="toggleSidebar()"></div>
    <aside class="sidebar hidden lg:block"><?php include 'Admin-sidebar.php'; ?></aside>
    <aside id="mobile-sidebar" class="fixed top-16 left-0 z-50 sidebar lg:hidden" style="min-height:calc(100vh - 64px);height:calc(100vh - 64px);"><?php include 'Admin-sidebar.php'; ?></aside>

    <main class="flex-1 min-w-0 px-4 sm:px-6 lg:px-8 py-8">
        <div class="max-w-6xl mx-auto">

            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="syne text-3xl font-800 text-white mb-1">Menu Management</h2>
                    <p class="text-slate-400 text-sm">Add, edit or remove items — sizes & extras are pulled from category settings</p>
                </div>
                <button onclick="scrollToForm()" class="btn-purple flex items-center gap-2">➕ Add Item</button>
            </div>

            <?php if ($success): ?>
                <div class="bg-green-400/10 border border-green-400/30 text-green-400 px-4 py-3 rounded-xl text-sm font-600 mb-5">✅ <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="bg-red-400/10 border border-red-400/30 text-red-400 px-4 py-3 rounded-xl text-sm font-600 mb-5">❌ <?= htmlspecialchars($error) ?></div>
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

            <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

                <!-- ── ADD / EDIT FORM (2 cols wide) ── -->
                <div class="lg:col-span-2">
                    <div class="card p-5" id="item-form-card">
                        <h3 class="syne font-800 text-white mb-5" id="form-title">
                            <?= $edit_item ? '✏️ Edit Item' : '➕ Add New Item' ?>
                        </h3>

                        <form method="POST" action="" id="item-form" onsubmit="return prepareSubmit()">
                            <input type="hidden" name="save_item" value="1">
                            <input type="hidden" name="id" id="form-id" value="<?= $edit_item ? $edit_item['id'] : 0 ?>">
                            <input type="hidden" name="item_sizes_json"  id="item-sizes-json"  value="[]">
                            <input type="hidden" name="item_extras_json" id="item-extras-json" value="[]">

                            <div class="mb-3">
                                <label class="field-label">Item name *</label>
                                <input type="text" name="name" class="form-input"
                                    placeholder="e.g. Classic Burger"
                                    value="<?= $edit_item ? htmlspecialchars($edit_item['name']) : '' ?>" required>
                            </div>

                            <!-- CATEGORY SELECT -->
                            <div class="mb-3">
                                <label class="field-label">Category *</label>
                                <div class="flex gap-2">
                                    <select name="category" id="cat-select" class="form-input flex-1" required onchange="onCategoryChange(this.value)">
                                        <option value="" disabled <?= !$edit_item ? 'selected' : '' ?>>Select a category…</option>
                                        <?php foreach ($category_names as $cat): ?>
                                            <option value="<?= htmlspecialchars($cat) ?>"
                                                <?= ($edit_item && $edit_item['category'] === $cat) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cat) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <a href="AdminCategories.php" title="Manage categories"
                                        class="flex-shrink-0 w-10 h-10 rounded-xl border border-purple-400/30 text-purple-400 hover:bg-purple-400/10 transition flex items-center justify-center text-lg">🗂️</a>
                                </div>
                                <!-- Category info badges -->
                                <div id="cat-badges" class="flex gap-2 mt-2 hidden">
                                    <span id="badge-sizes"  class="badge badge-slate">No sizes</span>
                                    <span id="badge-extras" class="badge badge-slate">No extras</span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="field-label">Price (EGP) *</label>
                                <input type="number" name="price" class="form-input"
                                    placeholder="0.00" step="0.01" min="0"
                                    value="<?= $edit_item ? $edit_item['price'] : '' ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="field-label">Description</label>
                                <textarea name="description" class="form-input" placeholder="Short description…"><?= $edit_item ? htmlspecialchars($edit_item['description']) : '' ?></textarea>
                            </div>

                            <!-- Image URL -->
                            <div class="mb-4">
                                <label class="field-label">Image URL <span class="text-slate-600 font-400 normal-case">(optional)</span></label>
                                <div id="img-preview-wrap">
                                    <div id="img-placeholder"><span style="font-size:24px">🖼️</span><span>Paste an image URL to preview</span></div>
                                    <img id="img-preview" alt="Preview">
                                </div>
                                <div id="img-status"></div>
                                <input type="url" name="image_url" id="image_url_input" class="form-input mt-2"
                                    placeholder="https://example.com/image.jpg"
                                    value="<?= $edit_item ? htmlspecialchars($edit_item['image_url'] ?? '') : '' ?>">
                            </div>

                            <!-- Availability -->
                            <div class="mb-4 flex items-center gap-3">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="is_available" id="is_available"
                                        <?= (!$edit_item || $edit_item['is_available']) ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                                <span class="text-slate-300 text-sm">Available on menu</span>
                            </div>

                            <!-- ── SIZES OVERRIDE SECTION ── -->
                            <div id="sizes-section" class="mb-4 hidden">
                                <div class="override-section" id="sizes-override-card">
                                    <div class="flex items-center justify-between mb-3">
                                        <div>
                                            <div class="text-sm font-700 text-slate-200">📐 Sizes</div>
                                            <div class="text-xs text-slate-500" id="sizes-section-hint">Using category defaults</div>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="use_category_sizes" id="use-cat-sizes"
                                                checked onchange="onUseCatSizesChange(this.checked)">
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </div>
                                    <div id="cat-sizes-preview" class="space-y-1.5"></div>
                                    <div id="item-sizes-editor" class="hidden space-y-2 mt-2">
                                        <div id="item-sizes-list" class="space-y-1.5"></div>
                                        <button type="button" onclick="addItemSize()" class="btn-ghost w-full text-xs py-2">+ Add Size</button>
                                    </div>
                                </div>
                            </div>

                            <!-- ── EXTRAS OVERRIDE SECTION ── -->
                            <div id="extras-section" class="mb-5 hidden">
                                <div class="override-section" id="extras-override-card">
                                    <div class="flex items-center justify-between mb-3">
                                        <div>
                                            <div class="text-sm font-700 text-slate-200">✨ Extras / Add-ons</div>
                                            <div class="text-xs text-slate-500" id="extras-section-hint">Using category defaults</div>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="use_category_extras" id="use-cat-extras"
                                                checked onchange="onUseCatExtrasChange(this.checked)">
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </div>
                                    <div id="cat-extras-preview" class="space-y-1.5"></div>
                                    <div id="item-extras-editor" class="hidden space-y-2 mt-2">
                                        <div id="item-extras-list" class="space-y-1.5"></div>
                                        <button type="button" onclick="addItemExtra()" class="btn-ghost w-full text-xs py-2">+ Add Extra</button>
                                    </div>
                                </div>
                            </div>

                            <div class="flex gap-2">
                                <button type="submit" class="btn-purple flex-1" id="form-submit-btn">
                                    <?= $edit_item ? 'Save Changes' : 'Add Item' ?>
                                </button>
                                <?php if ($edit_item): ?>
                                    <a href="AdminMenu.php" class="px-4 py-2.5 rounded-xl border border-slate-600 text-slate-400 hover:bg-slate-700 transition text-sm font-600 flex items-center">Cancel</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ── ITEMS LIST (3 cols wide) ── -->
                <div class="lg:col-span-3">
                    <form method="GET" action="" class="mb-4">
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/></svg>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                                placeholder="Search items or categories…"
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
                                <?php
                                    $cat_info = $cat_map[$category] ?? null;
                                ?>
                                <div>
                                    <div class="cat-heading flex items-center gap-3">
                                        <?= htmlspecialchars($category) ?>
                                        <span class="text-slate-600 text-xs font-400">(<?= count($cat_items) ?>)</span>
                                        <?php if ($cat_info): ?>
                                            <?php if ($cat_info['sizes_enabled']): ?>
                                                <span class="badge badge-green">📐 <?= $cat_info['size_count'] ?> sizes</span>
                                            <?php endif; ?>
                                            <?php if ($cat_info['extras_enabled']): ?>
                                                <span class="badge badge-purple">✨ <?= $cat_info['extra_count'] ?> extras</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <a href="AdminCategories.php" class="text-slate-600 hover:text-purple-400 transition text-xs ml-auto">⚙️ Edit</a>
                                    </div>
                                    <div class="space-y-2">
                                        <?php foreach ($cat_items as $i => $item): ?>
                                            <div class="item-row" style="animation-delay:<?= $i*0.04 ?>s">
                                                <div class="item-thumb flex-shrink-0">
                                                    <?php if (!empty($item['image_url'])): ?>
                                                        <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" onerror="this.parentElement.textContent='🍽️'">
                                                    <?php else: ?>
                                                        🍽️
                                                    <?php endif; ?>
                                                </div>
                                                <div class="w-2 h-2 rounded-full flex-shrink-0 <?= $item['is_available'] ? 'bg-green-400' : 'bg-red-400' ?>"></div>
                                                <div class="flex-1 min-w-0">
                                                    <div class="syne font-700 text-white text-sm truncate"><?= htmlspecialchars($item['name']) ?></div>
                                                    <div class="flex items-center gap-2 mt-0.5">
                                                        <?php if (!($item['use_category_sizes'] ?? 1)): ?>
                                                            <span class="badge badge-purple" title="Custom sizes">📐 custom</span>
                                                        <?php endif; ?>
                                                        <?php if (!($item['use_category_extras'] ?? 1)): ?>
                                                            <span class="badge badge-green" title="Custom extras">✨ custom</span>
                                                        <?php endif; ?>
                                                        <span class="text-slate-500 text-xs truncate"><?= htmlspecialchars($item['description']) ?></span>
                                                    </div>
                                                </div>
                                                <div class="text-sky-400 font-700 text-sm syne flex-shrink-0"><?= number_format($item['price'], 0) ?> EGP</div>
                                                <div class="flex gap-2 flex-shrink-0">
                                                    <a href="AdminMenu.php?edit=<?= $item['id'] ?>" class="btn-edit">✏️</a>
                                                    <button onclick="confirmDelete(<?= $item['id'] ?>, '<?= addslashes($item['name']) ?>')" class="btn-red">🗑️</button>
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
// ── Category config map injected from PHP ──────────────────────────
const CAT_MAP = <?= json_encode($cat_map) ?>;

// ── State ──────────────────────────────────────────────────────────
let catConfig     = null;   // current category's DB config
let itemSizes     = [];     // item-level size overrides
let itemExtras    = [];     // item-level extra overrides

// ── Sidebar ────────────────────────────────────────────────────────
function toggleSidebar() {
    document.getElementById('mobile-sidebar').classList.toggle('open');
    document.getElementById('sidebar-overlay').classList.toggle('open');
}

// ── Delete modal ───────────────────────────────────────────────────
function confirmDelete(id, name) {
    document.getElementById('confirm-delete-btn').href = 'AdminMenu.php?delete=' + id;
    document.getElementById('confirm-modal').classList.add('open');
}
function closeDeleteModal() { document.getElementById('confirm-modal').classList.remove('open'); }
document.getElementById('confirm-modal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});

function scrollToForm() {
    document.getElementById('item-form-card').scrollIntoView({ behavior:'smooth', block:'start' });
    setTimeout(() => document.querySelector('[name="name"]').focus(), 400);
}
<?php if ($edit_item): ?>
document.getElementById('item-form-card').scrollIntoView({ behavior:'smooth', block:'start' });
<?php endif; ?>

// ── Image preview ──────────────────────────────────────────────────
const imgInput   = document.getElementById('image_url_input');
const imgPreview = document.getElementById('img-preview');
const imgWrap    = document.getElementById('img-preview-wrap');
const imgHolder  = document.getElementById('img-placeholder');
const imgStatus  = document.getElementById('img-status');
let vtimer = null, currentUrl = '';

function resetPreview() {
    imgPreview.style.display = 'none'; imgPreview.src = '';
    imgHolder.style.display = 'flex';
    imgWrap.className = ''; imgWrap.id = 'img-preview-wrap';
    imgStatus.className = ''; imgStatus.textContent = '';
    imgInput.classList.remove('valid','invalid');
}
function showLoading() { imgStatus.className='loading'; imgStatus.textContent='⏳ Checking…'; }
function showValid() {
    imgHolder.style.display='none'; imgPreview.style.display='block';
    imgWrap.classList.add('has-image');
    imgStatus.className='valid'; imgStatus.textContent='✅ Image loaded';
    imgInput.classList.add('valid'); imgInput.classList.remove('invalid');
}
function showInvalid(m) {
    imgPreview.style.display='none'; imgHolder.style.display='flex';
    imgWrap.classList.add('has-error');
    imgStatus.className='invalid'; imgStatus.textContent='❌ '+(m||'Cannot load image');
    imgInput.classList.add('invalid'); imgInput.classList.remove('valid');
}
function validateImageUrl(url) {
    if (!url) { resetPreview(); return; }
    try { new URL(url); } catch(e) { showInvalid('Not a valid URL'); return; }
    showLoading(); currentUrl = url;
    const t = new Image();
    t.onload  = () => { if (url!==currentUrl) return; imgPreview.src=url; showValid(); };
    t.onerror = () => { if (url!==currentUrl) return; showInvalid('Could not load image'); };
    t.src = url;
}
imgInput.addEventListener('input', function() {
    clearTimeout(vtimer); const url=this.value.trim();
    if (!url) { resetPreview(); return; }
    showLoading(); vtimer = setTimeout(() => validateImageUrl(url), 600);
});
imgInput.addEventListener('paste', function() {
    clearTimeout(vtimer);
    setTimeout(() => validateImageUrl(this.value.trim()), 100);
});
<?php if ($edit_item && !empty($edit_item['image_url'])): ?>
validateImageUrl(<?= json_encode($edit_item['image_url']) ?>);
<?php endif; ?>

// ══════════════════════════════════════════════════════════════════
//  CATEGORY CHANGE
// ══════════════════════════════════════════════════════════════════
async function onCategoryChange(catName) {
    const info = CAT_MAP[catName];
    const badgesEl = document.getElementById('cat-badges');
    const badgeSizes  = document.getElementById('badge-sizes');
    const badgeExtras = document.getElementById('badge-extras');

    if (!catName || !info) { badgesEl.classList.add('hidden'); return; }
    badgesEl.classList.remove('hidden');

    // Fetch full config from server (sizes + extras lists)
    try {
        const res  = await fetch(`AdminMenu.php?get_category_config=${encodeURIComponent(catName)}`);
        catConfig  = await res.json();
    } catch(e) { catConfig = { category: info, sizes: [], extras: [] }; }

    // Update badges
    if (info.sizes_enabled) {
        badgeSizes.textContent = `📐 ${info.size_count} sizes`;
        badgeSizes.className   = 'badge badge-green';
    } else {
        badgeSizes.textContent = 'No sizes'; badgeSizes.className = 'badge badge-slate';
    }
    if (info.extras_enabled) {
        badgeExtras.textContent = `✨ ${info.extra_count} extras`;
        badgeExtras.className   = 'badge badge-purple';
    } else {
        badgeExtras.textContent = 'No extras'; badgeExtras.className = 'badge badge-slate';
    }

    // Show/hide size & extra sections
    const sizesSection  = document.getElementById('sizes-section');
    const extrasSection = document.getElementById('extras-section');

    if (info.sizes_enabled) {
        sizesSection.classList.remove('hidden');
        renderCatSizesPreview(catConfig.sizes || []);
    } else {
        sizesSection.classList.add('hidden');
    }

    if (info.extras_enabled) {
        extrasSection.classList.remove('hidden');
        renderCatExtrasPreview(catConfig.extras || []);
    } else {
        extrasSection.classList.add('hidden');
    }
}

// ── Render category sizes preview ─────────────────────────────────
function renderCatSizesPreview(sizes) {
    const el = document.getElementById('cat-sizes-preview');
    if (!sizes.length) {
        el.innerHTML = '<p class="text-slate-600 text-xs">No sizes configured for this category yet. <a href="AdminCategories.php" class="text-purple-400 hover:underline">Configure →</a></p>';
        return;
    }
    el.innerHTML = sizes.map(s => `
        <div class="size-mini-row">
            <span class="text-slate-300 text-xs flex-1">${esc(s.label)}</span>
            <span class="text-sky-400 text-xs font-600">+${s.price_extra} EGP</span>
            ${s.is_default ? '<span class="badge badge-purple">default</span>' : ''}
        </div>
    `).join('');
}

// ── Render category extras preview ────────────────────────────────
function renderCatExtrasPreview(extras) {
    const el = document.getElementById('cat-extras-preview');
    if (!extras.length) {
        el.innerHTML = '<p class="text-slate-600 text-xs">No extras configured for this category yet. <a href="AdminCategories.php" class="text-purple-400 hover:underline">Configure →</a></p>';
        return;
    }
    el.innerHTML = extras.map(e => `
        <div class="extra-mini-row">
            <span class="text-slate-300 text-xs flex-1">${esc(e.label)}</span>
            <span class="text-sky-400 text-xs font-600">${parseFloat(e.price)>0 ? '+'+e.price+' EGP' : 'Free'}</span>
        </div>
    `).join('');
}

// ── Toggle: use category sizes vs item-level ───────────────────────
function onUseCatSizesChange(checked) {
    const preview = document.getElementById('cat-sizes-preview');
    const editor  = document.getElementById('item-sizes-editor');
    const hint    = document.getElementById('sizes-section-hint');
    const card    = document.getElementById('sizes-override-card');

    if (checked) {
        preview.classList.remove('hidden');
        editor.classList.add('hidden');
        hint.textContent = 'Using category defaults';
        card.classList.remove('active');
    } else {
        preview.classList.add('hidden');
        editor.classList.remove('hidden');
        hint.textContent = 'Custom sizes for this item';
        card.classList.add('active');
        renderItemSizes();
    }
}

function onUseCatExtrasChange(checked) {
    const preview = document.getElementById('cat-extras-preview');
    const editor  = document.getElementById('item-extras-editor');
    const hint    = document.getElementById('extras-section-hint');
    const card    = document.getElementById('extras-override-card');

    if (checked) {
        preview.classList.remove('hidden');
        editor.classList.add('hidden');
        hint.textContent = 'Using category defaults';
        card.classList.remove('active');
    } else {
        preview.classList.add('hidden');
        editor.classList.remove('hidden');
        hint.textContent = 'Custom extras for this item';
        card.classList.add('active');
        renderItemExtras();
    }
}

// ── Item-level size editor ─────────────────────────────────────────
function renderItemSizes() {
    const list = document.getElementById('item-sizes-list');
    list.innerHTML = '';
    itemSizes.forEach((s, i) => {
        const div = document.createElement('div');
        div.className = 'size-mini-row';
        div.innerHTML = `
            <input type="text" placeholder="Label" value="${esc(s.label||'')}" class="mini-input flex-1 min-w-0"
                oninput="itemSizes[${i}].label=this.value">
            <input type="number" placeholder="+EGP" value="${s.price_extra||0}" class="mini-input w-20"
                min="0" step="0.5" oninput="itemSizes[${i}].price_extra=parseFloat(this.value)||0">
            <label class="flex items-center gap-1 text-xs text-slate-500 cursor-pointer">
                <input type="radio" name="item_default_size" ${s.is_default?'checked':''}
                    onchange="setItemDefaultSize(${i})" style="accent-color:#a855f7">
                Def
            </label>
            <button type="button" onclick="removeItemSize(${i})" class="text-slate-600 hover:text-red-400 transition">✕</button>
        `;
        list.appendChild(div);
    });
}
function addItemSize() {
    itemSizes.push({ label:'', price_extra:0, is_default: itemSizes.length===0 ? 1:0 });
    renderItemSizes();
}
function removeItemSize(i) {
    itemSizes.splice(i,1);
    if (itemSizes.length && !itemSizes.some(s=>s.is_default)) itemSizes[0].is_default=1;
    renderItemSizes();
}
function setItemDefaultSize(i) { itemSizes.forEach((s,j) => s.is_default = j===i ? 1:0); }

// ── Item-level extra editor ────────────────────────────────────────
function renderItemExtras() {
    const list = document.getElementById('item-extras-list');
    list.innerHTML = '';
    itemExtras.forEach((e, i) => {
        const div = document.createElement('div');
        div.className = 'extra-mini-row';
        div.innerHTML = `
            <input type="text" placeholder="Extra label" value="${esc(e.label||'')}" class="mini-input flex-1 min-w-0"
                oninput="itemExtras[${i}].label=this.value">
            <input type="number" placeholder="Price" value="${e.price||0}" class="mini-input w-20"
                min="0" step="0.5" oninput="itemExtras[${i}].price=parseFloat(this.value)||0">
            <button type="button" onclick="removeItemExtra(${i})" class="text-slate-600 hover:text-red-400 transition">✕</button>
        `;
        list.appendChild(div);
    });
}
function addItemExtra() {
    itemExtras.push({ label:'', price:0 });
    renderItemExtras();
}
function removeItemExtra(i) { itemExtras.splice(i,1); renderItemExtras(); }

// ── Pre-populate form submission hidden fields ─────────────────────
function prepareSubmit() {
    // Sync DOM → arrays
    document.querySelectorAll('#item-sizes-list .size-mini-row').forEach((row, i) => {
        const ins = row.querySelectorAll('input[type="text"], input[type="number"]');
        if (itemSizes[i]) {
            itemSizes[i].label       = ins[0]?.value.trim() || '';
            itemSizes[i].price_extra = parseFloat(ins[1]?.value) || 0;
        }
    });
    document.querySelectorAll('#item-extras-list .extra-mini-row').forEach((row, i) => {
        const ins = row.querySelectorAll('input[type="text"], input[type="number"]');
        if (itemExtras[i]) {
            itemExtras[i].label = ins[0]?.value.trim() || '';
            itemExtras[i].price = parseFloat(ins[1]?.value) || 0;
        }
    });

    document.getElementById('item-sizes-json').value  = JSON.stringify(itemSizes);
    document.getElementById('item-extras-json').value = JSON.stringify(itemExtras);

    // Validate image
    if (imgInput.value.trim() && imgInput.classList.contains('invalid')) {
        imgInput.focus();
        imgStatus.textContent = '❌ Fix the image URL or clear the field before saving';
        return false;
    }
    return true;
}

// ── On page load: if editing, load current category config ─────────
<?php if ($edit_item): ?>
(async () => {
    const catName = <?= json_encode($edit_item['category']) ?>;
    document.getElementById('cat-select').value = catName;
    await onCategoryChange(catName);

    // Restore toggle states
    const useCatSizes  = <?= $edit_item['use_category_sizes']  ?? 1 ?>;
    const useCatExtras = <?= $edit_item['use_category_extras'] ?? 1 ?>;

    document.getElementById('use-cat-sizes').checked  = !!useCatSizes;
    document.getElementById('use-cat-extras').checked = !!useCatExtras;
    onUseCatSizesChange(!!useCatSizes);
    onUseCatExtrasChange(!!useCatExtras);

    if (!useCatSizes || !useCatExtras) {
        try {
            const r = await fetch(`AdminMenu.php?get_item_overrides=<?= $edit_item['id'] ?>`);
            const d = await r.json();
            if (!useCatSizes)  { itemSizes  = d.sizes  || []; renderItemSizes();  }
            if (!useCatExtras) { itemExtras = d.extras || []; renderItemExtras(); }
        } catch(e) {}
    }
})();
<?php endif; ?>

function esc(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
</body>
</html>