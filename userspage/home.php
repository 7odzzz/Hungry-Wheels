<?php
session_start();
require_once('../auth_guard.php');
guard('user');
inject_bfcache_killer();

require('../db.php');

$search          = trim($_GET['search']   ?? '');
$category_filter = trim($_GET['category'] ?? '');

$sql    = 'SELECT * FROM menu_items WHERE is_available = 1';
$params = [];

if ($search !== '') {
    $sql     .= ' AND (name LIKE ? OR description LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($category_filter !== '') {
    $sql     .= ' AND category = ?';
    $params[] = $category_filter;
}
$sql .= ' ORDER BY category, name';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$menu = [];
foreach ($items as $item) {
    $menu[$item['category']][] = $item;
}

// ── Categories with their size/extra config ───────────────────────
$cat_rows = $pdo->query('
    SELECT c.*
    FROM categories c
    ORDER BY c.sort_order, c.name
')->fetchAll(PDO::FETCH_ASSOC);

$categories = array_column($cat_rows, 'name');

// Build a keyed map: category_name => { sizes_enabled, extras_enabled, sizes[], extras[] }
$cat_config = [];
foreach ($cat_rows as $cr) {
    $cat_config[$cr['name']] = [
        'id'             => $cr['id'],
        'sizes_enabled'  => (bool) $cr['sizes_enabled'],
        'extras_enabled' => (bool) $cr['extras_enabled'],
        'description'    => $cr['description'] ?? '',
        'image_url'      => $cr['image_url']   ?? '',
        'sizes'          => [],
        'extras'         => [],
    ];
}

// Fetch all sizes and extras in two queries (efficient)
$all_sizes = $pdo->query('
    SELECT cs.*, c.name AS cat_name
    FROM category_sizes cs
    JOIN categories c ON c.id = cs.category_id
    ORDER BY cs.sort_order
')->fetchAll(PDO::FETCH_ASSOC);

foreach ($all_sizes as $s) {
    if (isset($cat_config[$s['cat_name']])) {
        $cat_config[$s['cat_name']]['sizes'][] = $s;
    }
}

$all_extras = $pdo->query('
    SELECT ce.*, c.name AS cat_name
    FROM category_extras ce
    JOIN categories c ON c.id = ce.category_id
    ORDER BY ce.sort_order
')->fetchAll(PDO::FETCH_ASSOC);

foreach ($all_extras as $e) {
    if (isset($cat_config[$e['cat_name']])) {
        $cat_config[$e['cat_name']]['extras'][] = $e;
    }
}

// ── Fetch item-level overrides for items that have them ───────────
$item_ids = array_column($items, 'id');
$item_sizes_map  = [];
$item_extras_map = [];

if (!empty($item_ids)) {
    $in = implode(',', array_map('intval', $item_ids));

    $isizes = $pdo->query("SELECT * FROM item_sizes  WHERE item_id IN ($in) ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($isizes as $s) { $item_sizes_map[$s['item_id']][] = $s; }

    $iextras = $pdo->query("SELECT * FROM item_extras WHERE item_id IN ($in) ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($iextras as $e) { $item_extras_map[$e['item_id']][] = $e; }
}

// ── User info ─────────────────────────────────────────────────────
$user_stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$user_stmt->execute([$_SESSION['user_id']]);
$user     = $user_stmt->fetch(PDO::FETCH_ASSOC);
$is_elite = $user['is_elite'] ?? false;

$order_count = 0;
try {
    $o = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id = ?');
    $o->execute([$_SESSION['user_id']]);
    $order_count = $o->fetchColumn();
} catch (Exception $e) { }

// ── Category emoji fallback map ───────────────────────────────────
$category_emojis = [
    'burger'    => '🍔', 'burgers'    => '🍔',
    'pizza'     => '🍕',
    'sandwich'  => '🥪', 'sandwiches' => '🥪',
    'salad'     => '🥗', 'salads'     => '🥗',
    'drink'     => '🥤', 'drinks'     => '🥤', 'beverages' => '🥤',
    'dessert'   => '🍰', 'desserts'   => '🍰', 'sweets'    => '🍰',
    'pasta'     => '🍝',
    'rice'      => '🍚',
    'chicken'   => '🍗',
    'seafood'   => '🦐',
    'soup'      => '🍜', 'soups'      => '🍜',
    'sides'     => '🍟', 'side'       => '🍟',
    'wrap'      => '🌯', 'wraps'      => '🌯',
    'breakfast' => '🥞',
    'fries'     => '🍟',
    'juice'     => '🧃', 'juices'     => '🧃',
    'coffee'    => '☕',
];

function getCategoryEmoji($category, $map) {
    $key = strtolower(trim($category));
    return $map[$key] ?? '🍽️';
}

// Build JS-ready item data (including resolved sizes & extras)
// We do this in PHP so no additional AJAX is needed on the frontend
$js_items = [];
foreach ($items as $item) {
    $cat_name  = $item['category'];
    $cfg       = $cat_config[$cat_name] ?? null;

    // Determine effective sizes for this item
    $use_cat_sizes  = (bool) ($item['use_category_sizes']  ?? 1);
    $use_cat_extras = (bool) ($item['use_category_extras'] ?? 1);

    if ($use_cat_sizes && $cfg && $cfg['sizes_enabled'] && !empty($cfg['sizes'])) {
        $eff_sizes = $cfg['sizes'];
        $show_size = true;
    } elseif (!$use_cat_sizes && !empty($item_sizes_map[$item['id']])) {
        $eff_sizes = $item_sizes_map[$item['id']];
        $show_size = true;
    } else {
        $eff_sizes = [];
        $show_size = false;
    }

    if ($use_cat_extras && $cfg && $cfg['extras_enabled'] && !empty($cfg['extras'])) {
        $eff_extras = $cfg['extras'];
        $has_extras = true;
    } elseif (!$use_cat_extras && !empty($item_extras_map[$item['id']])) {
        $eff_extras = $item_extras_map[$item['id']];
        $has_extras = true;
    } else {
        $eff_extras = [];
        $has_extras = false;
    }

    $emoji = getCategoryEmoji($cat_name, $category_emojis);

    // Simplify sizes/extras for JS (only what frontend needs)
    $js_sizes = array_map(fn($s) => [
        'label'       => $s['label'],
        'price_extra' => (float) $s['price_extra'],
        'is_default'  => (bool)  ($s['is_default'] ?? false),
    ], $eff_sizes);

    $js_extras = array_map(fn($e) => [
        'label' => $e['label'],
        'price' => (float) $e['price'],
    ], $eff_extras);

    $js_items[$item['id']] = [
        'id'          => $item['id'],
        'name'        => $item['name'],
        'description' => $item['description'],
        'price'       => (float) $item['price'],
        'category'    => $cat_name,
        'image_url'   => $item['image_url'] ?? '',
        'emoji'       => $emoji,
        'is_elite'    => (bool) $is_elite,
        'show_size'   => $show_size,
        'has_extras'  => $has_extras,
        'sizes'       => $js_sizes,
        'extras'      => $js_extras,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Menu — Hungry Wheels</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
* { font-family: 'DM Sans', sans-serif; }
h1, h2, h3, h4, .syne { font-family: 'Syne', sans-serif; }
body { background: #080d14; }
.bg-glow { position:fixed; top:-200px; left:200px; width:600px; height:600px; background:radial-gradient(circle,rgba(56,189,248,0.06) 0%,transparent 70%); pointer-events:none; z-index:0; animation:floatGlow 8s ease-in-out infinite; }
.bg-glow-2 { position:fixed; bottom:-200px; right:-100px; width:500px; height:500px; background:radial-gradient(circle,rgba(99,102,241,0.05) 0%,transparent 70%); pointer-events:none; z-index:0; animation:floatGlow 10s ease-in-out infinite reverse; }
@keyframes floatGlow { 0%,100%{transform:translate(0,0);} 50%{transform:translate(40px,40px);} }
.navbar-glass { background:rgba(8,13,20,0.9); backdrop-filter:blur(16px); border-bottom:1px solid rgba(56,189,248,0.08); }
.sidebar { background:rgba(14,20,32,0.95); border-right:1px solid rgba(51,65,85,0.4); width:260px; min-height:calc(100vh - 64px); position:sticky; top:64px; height:calc(100vh - 64px); overflow-y:auto; flex-shrink:0; }
.sidebar::-webkit-scrollbar{width:4px;} .sidebar::-webkit-scrollbar-thumb{background:#1e293b;border-radius:4px;}
.nav-item { display:flex; align-items:center; gap:12px; padding:11px 16px; border-radius:10px; color:#94a3b8; font-size:14px; font-weight:500; cursor:pointer; transition:all 0.2s; text-decoration:none; margin:2px 0; }
.nav-item:hover{background:rgba(56,189,248,0.08);color:#e2e8f0;}
.nav-item.active{background:rgba(56,189,248,0.12);color:#38bdf8;font-weight:600;}
.nav-item .icon{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:16px;background:rgba(30,41,59,0.8);flex-shrink:0;}
.nav-item.active .icon{background:rgba(56,189,248,0.15);}
.search-input{background:rgba(30,41,59,0.8);border:1.5px solid rgba(51,65,85,0.8);color:#e2e8f0;transition:all 0.25s;}
.search-input::placeholder{color:#475569;}
.search-input:focus{border-color:#38bdf8;box-shadow:0 0 0 3px rgba(56,189,248,0.12);outline:none;}
.cat-pill{background:rgba(30,41,59,0.7);border:1.5px solid rgba(51,65,85,0.6);color:#94a3b8;cursor:pointer;transition:all 0.2s;white-space:nowrap;}
.cat-pill:hover,.cat-pill.active{background:#38bdf8;border-color:#38bdf8;color:#080d14;font-weight:700;}
.menu-card{background:rgba(20,30,48,0.85);border:1px solid rgba(51,65,85,0.5);transition:all 0.28s cubic-bezier(0.34,1.56,0.64,1);cursor:pointer;}
.menu-card:hover{transform:translateY(-6px) scale(1.01);border-color:rgba(56,189,248,0.35);box-shadow:0 24px 48px rgba(0,0,0,0.5),0 0 0 1px rgba(56,189,248,0.12);}
.item-img-wrap{width:100%;height:260px;position:relative;overflow:hidden;background:linear-gradient(135deg,rgba(15,25,45,1) 0%,rgba(20,35,60,1) 100%);border-bottom:1px solid rgba(56,189,248,0.08);}
.item-img-wrap img{width:100%;height:100%;object-fit:cover;object-position:center;transition:transform 0.4s ease;}
.menu-card:hover .item-img-wrap img{transform:scale(1.06);}
.item-emoji-placeholder{width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;background:linear-gradient(135deg,rgba(15,25,45,1) 0%,rgba(22,38,65,1) 100%);}
.item-emoji-placeholder .emoji{font-size:52px;line-height:1;}
.item-emoji-placeholder .cat-label{font-size:10px;font-weight:700;letter-spacing:0.12em;color:rgba(56,189,248,0.4);text-transform:uppercase;font-family:'Syne',sans-serif;}
.btn-add{background:#000;color:#c7c7c7;border:1px solid rgba(56,189,248,0.2);transition:all 0.2s;}
.btn-add:hover{background:#38bdf8;color:#080d14;border-color:#38bdf8;font-weight:700;}
.cat-heading::after{content:'';display:block;width:40px;height:3px;background:#38bdf8;border-radius:2px;margin-top:6px;}
.menu-card{animation:fadeUp 0.4s ease both;}
@keyframes fadeUp{from{opacity:0;transform:translateY(20px);}to{opacity:1;transform:translateY(0);}}
.avatar{background:linear-gradient(135deg,#38bdf8,#6366f1);font-family:'Syne',sans-serif;font-weight:700;}
#sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:40;backdrop-filter:blur(2px);}
#sidebar-overlay.open{display:block;}
#mobile-sidebar{transform:translateX(-100%);transition:transform 0.3s ease;}
#mobile-sidebar.open{transform:translateX(0);}
.toast{position:fixed;bottom:24px;right:24px;z-index:9999;background:#38bdf8;color:#080d14;font-family:'Syne',sans-serif;font-weight:700;font-size:14px;padding:12px 20px;border-radius:12px;box-shadow:0 8px 24px rgba(56,189,248,0.3);animation:toastIn 0.3s ease,toastOut 0.3s ease 2.2s forwards;pointer-events:none;}
@keyframes toastIn{from{opacity:0;transform:translateY(16px);}to{opacity:1;transform:translateY(0);}}
@keyframes toastOut{from{opacity:1;transform:translateY(0);}to{opacity:0;transform:translateY(16px);}}
/* ── Modal ── */
#item-modal{display:none;position:fixed;inset:0;z-index:1000;align-items:flex-end;}
#item-modal.open{display:flex;}
.modal-backdrop{position:absolute;inset:0;background:rgba(0,0,8,0.75);backdrop-filter:blur(6px);}
.modal-sheet{position:relative;z-index:1;width:100%;max-width:560px;margin:0 auto;background:#0d1a2d;border:1px solid rgba(56,189,248,0.12);border-radius:24px 24px 0 0;max-height:92vh;overflow-y:auto;transform:translateY(100%);transition:transform 0.35s cubic-bezier(0.34,1.2,0.64,1);}
#item-modal.open .modal-sheet{transform:translateY(0);}
.modal-sheet::-webkit-scrollbar{width:4px;} .modal-sheet::-webkit-scrollbar-thumb{background:#1e293b;border-radius:4px;}
.modal-img-wrap{width:100%;height:220px;overflow:hidden;border-radius:24px 24px 0 0;position:relative;background:linear-gradient(135deg,rgba(12,22,40,1),rgba(18,32,58,1));}
.modal-img-wrap img{width:100%;height:100%;object-fit:cover;}
.modal-emoji-placeholder{width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:80px;}
.modal-close-btn{position:absolute;top:14px;right:14px;z-index:10;width:34px;height:34px;border-radius:50%;background:rgba(8,13,20,0.8);border:1px solid rgba(51,65,85,0.6);color:#94a3b8;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:18px;transition:all 0.2s;backdrop-filter:blur(8px);}
.modal-close-btn:hover{background:rgba(239,68,68,0.2);color:#f87171;border-color:rgba(239,68,68,0.4);}
.size-btn{flex:1;padding:8px 4px;border-radius:10px;text-align:center;font-size:12px;font-weight:600;cursor:pointer;border:1.5px solid rgba(51,65,85,0.6);background:rgba(30,41,59,0.5);color:#94a3b8;transition:all 0.18s;}
.size-btn:hover{border-color:#38bdf8;color:#e2e8f0;}
.size-btn.active{background:rgba(56,189,248,0.12);border-color:#38bdf8;color:#38bdf8;font-weight:700;}
.extra-chip{display:flex;align-items:center;gap:8px;padding:9px 12px;border-radius:10px;border:1.5px solid rgba(51,65,85,0.6);background:rgba(30,41,59,0.4);cursor:pointer;transition:all 0.18s;}
.extra-chip:hover{border-color:rgba(56,189,248,0.4);}
.extra-chip.active{border-color:#38bdf8;background:rgba(56,189,248,0.08);}
.extra-chip .check{width:18px;height:18px;border-radius:5px;border:1.5px solid rgba(51,65,85,0.8);display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all 0.18s;background:transparent;font-size:11px;}
.extra-chip.active .check{background:#38bdf8;border-color:#38bdf8;color:#080d14;}
.qty-btn{width:36px;height:36px;border-radius:10px;background:rgba(30,41,59,0.8);border:1.5px solid rgba(51,65,85,0.6);color:#e2e8f0;font-size:18px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all 0.18s;user-select:none;}
.qty-btn:hover{background:rgba(56,189,248,0.15);border-color:#38bdf8;color:#38bdf8;}
.notes-input{width:100%;background:rgba(30,41,59,0.6);border:1.5px solid rgba(51,65,85,0.7);border-radius:12px;color:#e2e8f0;padding:10px 14px;font-size:13px;resize:none;outline:none;transition:all 0.2s;font-family:'DM Sans',sans-serif;}
.notes-input::placeholder{color:#475569;}
.notes-input:focus{border-color:#38bdf8;box-shadow:0 0 0 3px rgba(56,189,248,0.1);}
.modal-add-btn{width:100%;padding:14px;background:#38bdf8;color:#080d14;font-family:'Syne',sans-serif;font-weight:800;font-size:15px;border-radius:14px;border:none;cursor:pointer;transition:all 0.2s;display:flex;align-items:center;justify-content:center;gap:8px;}
.modal-add-btn:hover{background:#7dd3fc;transform:translateY(-1px);}
.modal-add-btn:active{transform:translateY(0);}
::-webkit-scrollbar{width:6px;} ::-webkit-scrollbar-track{background:#080d14;} ::-webkit-scrollbar-thumb{background:#1e293b;border-radius:4px;}
</style>
</head>

<body class="min-h-screen text-slate-200">
<div class="bg-glow"></div>
<div class="bg-glow-2"></div>

<!-- NAVBAR -->
<nav class="navbar-glass sticky top-0 z-[9999] h-16">
    <div class="h-full px-4 sm:px-6 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <button onclick="toggleSidebar()" class="text-slate-400 hover:text-sky-400 p-1.5 rounded-lg hover:bg-slate-800 transition lg:hidden">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <a href="home.php" class="syne text-lg font-800 text-sky-400 tracking-tight flex items-center gap-2">🍔 <span>Hungry Wheels</span></a>
        </div>

        <form method="GET" action="" class="flex-1 max-w-lg">
            <div class="relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/></svg>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search dishes, categories..." class="search-input w-full pl-10 pr-4 py-2.5 rounded-xl text-sm">
                <?php if ($category_filter): ?><input type="hidden" name="category" value="<?= htmlspecialchars($category_filter) ?>"><?php endif; ?>
            </div>
        </form>

        <div class="flex items-center gap-3">
            <a href="cart.php" class="relative text-slate-400 hover:text-sky-400 transition p-1.5">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                <span id="cart-badge" class="absolute -top-1 -right-1 bg-sky-400 text-slate-900 text-xs font-800 w-5 h-5 rounded-full items-center justify-center syne hidden flex">0</span>
            </a>
            <?php if ($is_elite): ?><span class="elite-badge hidden sm:inline text-xs font-700 px-3 py-1 rounded-full syne" style="background:linear-gradient(135deg,#f59e0b,#fbbf24);color:#080d14;">⭐ Elite</span><?php endif; ?>
            <a href="/HungryWheels/userspage/profile.php" class="avatar w-9 h-9 rounded-full flex items-center justify-center text-sm text-white cursor-pointer hover:scale-105 transition">
                <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
            </a>
        </div>
    </div>
</nav>

<!-- LAYOUT -->
<div class="relative z-10 flex">
    <div id="sidebar-overlay" onclick="toggleSidebar()"></div>
    <aside class="sidebar hidden lg:block" id="desktop-sidebar"><?php include 'side-bar.php'; ?></aside>
    <aside id="mobile-sidebar" class="fixed top-16 left-0 z-50 sidebar lg:hidden" style="min-height:calc(100vh - 64px);height:calc(100vh - 64px);"><?php include 'side-bar.php'; ?></aside>

    <main class="flex-1 min-w-0 px-4 sm:px-6 lg:px-8 py-8">

        <!-- Category pills -->
        <div class="flex gap-2 overflow-x-auto pb-3 mb-6">
            <a href="?<?= $search ? 'search='.urlencode($search) : '' ?>" class="cat-pill px-4 py-2 rounded-full text-sm <?= $category_filter === '' ? 'active' : '' ?>">All</a>
            <?php foreach ($categories as $cat): ?>
                <a href="?category=<?= urlencode($cat) ?><?= $search ? '&search='.urlencode($search) : '' ?>" class="cat-pill px-4 py-2 rounded-full text-sm <?= $category_filter === $cat ? 'active' : '' ?>"><?= htmlspecialchars($cat) ?></a>
            <?php endforeach; ?>
        </div>

        <?php if ($search): ?>
            <div class="mb-5 flex items-center gap-3 text-sm text-slate-400">
                <span>Results for <span class="text-sky-400 font-600">"<?= htmlspecialchars($search) ?>"</span></span>
                <a href="home.php" class="text-slate-500 hover:text-red-400 transition text-xs border border-slate-700 px-2 py-1 rounded-lg">✕ Clear</a>
            </div>
        <?php endif; ?>

        <?php if (empty($menu)): ?>
            <div class="text-center py-24 text-slate-500">
                <div class="text-5xl mb-4">🍽️</div>
                <h3 class="syne text-xl text-slate-400 mb-2">Nothing found</h3>
                <p class="text-sm">Try a different search or category</p>
                <a href="home.php" class="inline-block mt-4 text-sky-400 hover:underline text-sm">← Back to full menu</a>
            </div>
        <?php else: ?>
            <?php foreach ($menu as $category => $cat_items): ?>
                <?php $catEmoji = getCategoryEmoji($category, $category_emojis); ?>
                <div class="mb-10">
                    <h3 class="syne cat-heading text-lg font-700 text-white mb-5"><?= $catEmoji ?> <?= htmlspecialchars($category) ?></h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">
                        <?php foreach ($cat_items as $i => $item): ?>
                            <?php
                                $js_item       = $js_items[$item['id']];
                                $emoji         = $js_item['emoji'];
                                $hasImg        = !empty($item['image_url'] ?? '');
                                $display_price = $is_elite ? number_format($item['price']*0.9,0) : number_format($item['price'],0);
                                $orig_price    = number_format($item['price'],0);
                                $js_encoded    = htmlspecialchars(json_encode($js_item), ENT_QUOTES);
                            ?>
                            <div class="menu-card rounded-2xl overflow-hidden" style="animation-delay:<?= $i*0.05 ?>s"
                                 onclick='openModal(<?= $js_encoded ?>)'>
                                <div class="item-img-wrap">
                                    <?php if ($hasImg): ?>
                                        <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>"
                                             onerror="this.parentElement.innerHTML='<div class=\'item-emoji-placeholder\'><span class=\'emoji\'><?= $emoji ?></span><span class=\'cat-label\'><?= htmlspecialchars($category) ?></span></div>'">
                                    <?php else: ?>
                                        <div class="item-emoji-placeholder">
                                            <span class="emoji"><?= $emoji ?></span>
                                            <span class="cat-label"><?= htmlspecialchars($category) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($is_elite): ?>
                                        <div style="position:absolute;top:10px;left:10px;background:linear-gradient(135deg,#f59e0b,#fbbf24);color:#080d14;font-size:10px;font-weight:700;padding:3px 9px;border-radius:20px;font-family:'Syne',sans-serif;">⭐ -10%</div>
                                    <?php endif; ?>
                                </div>
                                <div class="h-0.5" style="background:linear-gradient(90deg,#38bdf8,#6366f1)"></div>
                                <div class="p-4">
                                    <h4 class="syne font-700 text-white text-sm leading-tight mb-1"><?= htmlspecialchars($item['name']) ?></h4>
                                    <p class="text-slate-500 text-xs leading-relaxed mb-3 line-clamp-2"><?= htmlspecialchars($item['description']) ?></p>
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <?php if ($is_elite): ?>
                                                <span class="text-slate-600 text-xs line-through"><?= $orig_price ?></span>
                                                <span class="text-sky-400 font-700 text-base ml-1"><?= $display_price ?> <span class="text-xs font-400">EGP</span></span>
                                            <?php else: ?>
                                                <span class="text-sky-400 font-700 text-base"><?= $display_price ?> <span class="text-xs font-400 text-slate-400">EGP</span></span>
                                            <?php endif; ?>
                                        </div>
                                        <button onclick="event.stopPropagation(); openModal(<?= $js_encoded ?>)" class="btn-add text-xs font-600 px-3 py-1.5 rounded-xl">+ Add</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </main>
</div>

<!-- ITEM DETAIL MODAL -->
<div id="item-modal">
    <div class="modal-backdrop" onclick="closeModal()"></div>
    <div class="modal-sheet">

        <div class="modal-img-wrap" id="modal-img-wrap">
            <div class="modal-emoji-placeholder">🍽️</div>
            <button class="modal-close-btn" onclick="closeModal()">✕</button>
        </div>

        <div class="p-5 pb-8">
            <div class="flex items-start justify-between gap-3 mb-1">
                <h2 class="syne font-800 text-white text-xl leading-tight" id="modal-name"></h2>
                <div class="text-right flex-shrink-0">
                    <div id="modal-orig-price" class="text-slate-600 text-xs line-through hidden"></div>
                    <div class="text-sky-400 font-700 text-xl syne" id="modal-price"></div>
                </div>
            </div>
            <div class="text-xs text-slate-500 mb-1" id="modal-category"></div>
            <p class="text-slate-400 text-sm leading-relaxed mb-5" id="modal-description"></p>

            <!-- SIZE — built dynamically from DB data -->
            <div id="modal-size-section" class="mb-5 hidden">
                <div class="text-xs font-700 text-slate-400 uppercase tracking-wider mb-2">Size</div>
                <div id="modal-sizes-container" class="flex gap-2 flex-wrap"></div>
            </div>

            <!-- EXTRAS — built dynamically from DB data -->
            <div id="modal-extras-section" class="mb-5 hidden">
                <div class="text-xs font-700 text-slate-400 uppercase tracking-wider mb-2">Extras</div>
                <div id="modal-extras-container" class="grid grid-cols-2 gap-2"></div>
            </div>

            <!-- NOTES -->
            <div class="mb-5">
                <div class="text-xs font-700 text-slate-400 uppercase tracking-wider mb-2">Special Notes</div>
                <textarea id="modal-notes" class="notes-input" rows="2" placeholder="Any special requests?"></textarea>
            </div>

            <!-- QTY + ADD -->
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-3 flex-shrink-0">
                    <div class="qty-btn" onclick="changeQty(-1)">−</div>
                    <span class="syne font-800 text-white text-lg w-6 text-center" id="modal-qty">1</span>
                    <div class="qty-btn" onclick="changeQty(1)">+</div>
                </div>
                <button class="modal-add-btn" onclick="modalAddToCart()">
                    <span>🛒</span><span>Add to Cart —</span><span id="modal-total-price">0 EGP</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// ── State ──────────────────────────────────────────────────────────
let currentItem    = null;
let currentQty     = 1;
let selectedSize   = null;    // { label, price_extra }
let selectedExtras = [];      // [{ label, price }, …]

// ── Cart helpers ───────────────────────────────────────────────────
function getCart()   { return JSON.parse(localStorage.getItem('hw_cart') || '[]'); }
function saveCart(c) { localStorage.setItem('hw_cart', JSON.stringify(c)); }

function updateCartBadge() {
    const total = getCart().reduce((s,i) => s + i.qty, 0);
    const badge = document.getElementById('cart-badge');
    if (!badge) return;
    badge.textContent = total;
    total > 0 ? (badge.classList.remove('hidden'), badge.classList.add('flex'))
              : (badge.classList.add('hidden'),    badge.classList.remove('flex'));
}

function showToast(msg) {
    const old = document.getElementById('hw-toast');
    if (old) old.remove();
    const t = document.createElement('div');
    t.id = 'hw-toast'; t.className = 'toast'; t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 2500);
}

// ── Open modal ─────────────────────────────────────────────────────
function openModal(item) {
    currentItem    = item;
    currentQty     = 1;
    selectedExtras = [];

    // Reset size to default
    const defSize = (item.sizes || []).find(s => s.is_default) || (item.sizes || [])[0] || null;
    selectedSize  = defSize ? { label: defSize.label, price_extra: defSize.price_extra } : null;

    document.getElementById('modal-notes').value = '';
    document.getElementById('modal-qty').textContent = '1';
    document.getElementById('modal-name').textContent        = item.name;
    document.getElementById('modal-description').textContent = item.description;
    document.getElementById('modal-category').textContent    = '📂 ' + item.category;

    // Image
    const wrap = document.getElementById('modal-img-wrap');
    if (item.image_url) {
        wrap.innerHTML = `<img src="${esc(item.image_url)}" alt="${esc(item.name)}"
            onerror="this.parentElement.innerHTML='<div class=\\'modal-emoji-placeholder\\'>${item.emoji}</div><button class=\\'modal-close-btn\\' onclick=\\'closeModal()\\'>✕</button>'">
            <button class="modal-close-btn" onclick="closeModal()">✕</button>`;
    } else {
        wrap.innerHTML = `<div class="modal-emoji-placeholder">${item.emoji}</div>
            <button class="modal-close-btn" onclick="closeModal()">✕</button>`;
    }

    // Build size buttons dynamically
    const sizeSection = document.getElementById('modal-size-section');
    const sizesContainer = document.getElementById('modal-sizes-container');
    sizesContainer.innerHTML = '';

    if (item.show_size && item.sizes && item.sizes.length > 0) {
        sizeSection.classList.remove('hidden');
        item.sizes.forEach(s => {
            const btn = document.createElement('div');
            btn.className = 'size-btn' + (s.is_default ? ' active' : '');
            btn.dataset.label       = s.label;
            btn.dataset.priceExtra  = s.price_extra;
            btn.innerHTML = `
                <div class="text-base mb-0.5">📦</div>
                <div>${esc(s.label)}</div>
                <div class="text-sky-400 text-xs mt-0.5">${s.price_extra > 0 ? '+'+s.price_extra+' EGP' : 'Standard'}</div>
            `;
            btn.onclick = () => selectSize(btn, s);
            sizesContainer.appendChild(btn);
        });
    } else {
        sizeSection.classList.add('hidden');
    }

    // Build extras dynamically
    const extrasSection    = document.getElementById('modal-extras-section');
    const extrasContainer  = document.getElementById('modal-extras-container');
    extrasContainer.innerHTML = '';

    if (item.has_extras && item.extras && item.extras.length > 0) {
        extrasSection.classList.remove('hidden');
        item.extras.forEach(e => {
            const chip = document.createElement('div');
            chip.className = 'extra-chip';
            chip.dataset.price = e.price;
            chip.dataset.label = e.label;
            chip.innerHTML = `
                <div class="check">✓</div>
                <div class="flex-1">
                    <div class="text-sm text-slate-300 font-500">${esc(e.label)}</div>
                    <div class="text-xs text-sky-400">${parseFloat(e.price) > 0 ? '+'+e.price+' EGP' : 'Free'}</div>
                </div>
            `;
            chip.onclick = () => toggleExtra(chip, e);
            extrasContainer.appendChild(chip);
        });
    } else {
        extrasSection.classList.add('hidden');
    }

    updateModalPrice();
    const modal = document.getElementById('item-modal');
    modal.style.display = 'flex';
    requestAnimationFrame(() => modal.classList.add('open'));
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    const modal = document.getElementById('item-modal');
    modal.classList.remove('open');
    setTimeout(() => { modal.style.display = 'none'; document.body.style.overflow = ''; }, 350);
}

// ── Price helpers ──────────────────────────────────────────────────
function getBasePrice() {
    if (!currentItem) return 0;
    return currentItem.is_elite ? currentItem.price * 0.9 : currentItem.price;
}

function updateModalPrice() {
    if (!currentItem) return;
    const base      = getBasePrice();
    const sizeExtra = selectedSize ? selectedSize.price_extra : 0;
    const extrasSum = selectedExtras.reduce((s,e) => s + e.price, 0);
    const unitPrice = base + sizeExtra + extrasSum;
    const total     = unitPrice * currentQty;

    const origEl = document.getElementById('modal-orig-price');
    if (currentItem.is_elite) {
        origEl.classList.remove('hidden');
        origEl.textContent = Math.round(currentItem.price + sizeExtra + extrasSum) + ' EGP';
    } else {
        origEl.classList.add('hidden');
    }
    document.getElementById('modal-price').textContent       = Math.round(unitPrice) + ' EGP';
    document.getElementById('modal-total-price').textContent = Math.round(total)     + ' EGP';
}

function selectSize(el, size) {
    document.querySelectorAll('#modal-sizes-container .size-btn').forEach(b => b.classList.remove('active'));
    el.classList.add('active');
    selectedSize = { label: size.label, price_extra: size.price_extra };
    updateModalPrice();
}

function toggleExtra(el, extra) {
    el.classList.toggle('active');
    if (el.classList.contains('active')) {
        selectedExtras.push({ label: extra.label, price: extra.price });
    } else {
        selectedExtras = selectedExtras.filter(e => e.label !== extra.label);
    }
    updateModalPrice();
}

function changeQty(delta) {
    currentQty = Math.max(1, currentQty + delta);
    document.getElementById('modal-qty').textContent = currentQty;
    updateModalPrice();
}

function modalAddToCart() {
    if (!currentItem) return;
    const base      = getBasePrice();
    const sizeExtra = selectedSize ? selectedSize.price_extra : 0;
    const extrasSum = selectedExtras.reduce((s,e) => s + e.price, 0);
    const unitPrice = base + sizeExtra + extrasSum;

    const extraLabels = selectedExtras.map(e => e.label);
    const notes       = document.getElementById('modal-notes').value.trim();
    const sizeName    = selectedSize ? selectedSize.label : '';

    let label = currentItem.name;
    if (sizeName && sizeName !== 'Regular') label += ' (' + sizeName + ')';

    const configKey = JSON.stringify({
        id: currentItem.id, size: sizeName, extras: extraLabels.sort(), notes
    });
    const cart     = getCart();
    const existing = cart.find(i => i._configKey === configKey);

    if (existing) {
        existing.qty += currentQty;
    } else {
        cart.push({
            id: currentItem.id, name: label,
            price: Math.round(unitPrice),
            qty: currentQty, size: sizeName,
            extras: extraLabels, notes,
            _configKey: configKey
        });
    }

    saveCart(cart);
    updateCartBadge();
    closeModal();
    showToast('🛒 ' + label + ' added!');
}

function toggleSidebar() {
    document.getElementById('mobile-sidebar').classList.toggle('open');
    document.getElementById('sidebar-overlay').classList.toggle('open');
}

function esc(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
updateCartBadge();
</script>
</body>
</html>