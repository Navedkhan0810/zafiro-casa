<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once(__DIR__ . '/csrf.php');
include_once(__DIR__ . '/user_auth.php');
csrf_start_form_injection();
$isHeaderLoggedIn = isset($_SESSION['user_id']);
$headerUserId = $isHeaderLoggedIn ? (int) $_SESSION['user_id'] : 0;
$headerProfileLabel = $isHeaderLoggedIn ? ($_SESSION['username'] ?? 'Profile') : 'Guest User';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zafiro Casa Luxury Living</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=24">
    <link rel="stylesheet" href="../assets/css/profile-pages.css?v=1">
    <link rel="stylesheet" href="../assets/css/zafiro-popup.css?v=1">
</head>

<body data-auth="<?php echo $isHeaderLoggedIn ? '1' : '0'; ?>" data-user-id="<?php echo $headerUserId; ?>" data-csrf="<?php echo htmlspecialchars(csrf_token()); ?>">

<header>
    <?php
    $currentPage = basename($_SERVER['PHP_SELF']);
    $navItems = [['label' => 'Home', 'href' => 'index.php', 'page' => 'index.php']];
    $categoryPageMap = [
        'sofas' => 'sofas.php',
        'living' => 'living.php',
        'bedroom' => 'bedroom.php',
        'mattress' => 'mattress.php',
        'dining' => 'dining.php',
        'storage' => 'storage.php',
        'study-office' => 'study-office.php',
        'outdoor' => 'outdoor.php',
        'decor-furnishing' => 'decor-furnishing.php',
        'modular-kitchen' => 'modular-kitchen.php',
    ];
    $defaultCategoryNav = [
        ['label' => 'Sofas', 'href' => 'sofas.php', 'page' => 'sofas.php'],
        ['label' => 'Living', 'href' => 'living.php', 'page' => 'living.php'],
        ['label' => 'Bedroom', 'href' => 'bedroom.php', 'page' => 'bedroom.php'],
        ['label' => 'Mattress', 'href' => 'mattress.php', 'page' => 'mattress.php'],
        ['label' => 'Dining', 'href' => 'dining.php', 'page' => 'dining.php'],
        ['label' => 'Storage', 'href' => 'storage.php', 'page' => 'storage.php'],
        ['label' => 'Study & Office', 'href' => 'study-office.php', 'page' => 'study-office.php'],
        ['label' => 'Outdoor', 'href' => 'outdoor.php', 'page' => 'outdoor.php'],
        ['label' => 'Decor & Furnishing', 'href' => 'decor-furnishing.php', 'page' => 'decor-furnishing.php'],
        ['label' => 'Modular Kitchen', 'href' => 'modular-kitchen.php', 'page' => 'modular-kitchen.php'],
    ];
    $navItems = array_merge($navItems, $defaultCategoryNav);
    ?>

    <div class="main-header">
        <a class="logo brand-logo" href="index.php">
            <span class="main brand-name">Zafiro Casa</span>
            <span class="sub brand-tagline">Luxury Living</span>
        </a>

        <form class="site-search search-wrapper" action="search.php" method="GET">
            <input type="search" id="searchInput" name="q" placeholder="Search Products, Color & More..." autocomplete="off">
            <button type="submit" id="searchBtn">Search</button>
            <div id="searchDropdown" aria-label="Search suggestions"></div>
        </form>

        <button class="menu-toggle" type="button" aria-label="Toggle menu">Menu</button>

        <div class="header-actions">
            <a href="profile.php" class="header-action">
                <i class="fa-solid fa-circle-user action-icon"></i>
                <span><?php echo htmlspecialchars($headerProfileLabel); ?></span>
            </a>
            <a href="notifications.php" class="header-action notification-action">
                <i class="fa-solid fa-bell action-icon"></i>
                <span>Notifications (<span id="notificationCount">0</span>)</span>
            </a>
            <a href="wishlist.php" class="header-action">
                <i class="fa-solid fa-heart action-icon"></i>
                <span>Wishlist (<span id="wishlistCount">0</span>)</span>
            </a>
            <a href="cart.php" class="header-action">
                <i class="fa-solid fa-cart-shopping action-icon"></i>
                <span>Cart (<span id="cartCount">0</span>)</span>
            </a>
        </div>
    </div>

</header>

<nav class="category-nav">
    <?php foreach ($navItems as $item): ?>
        <a href="<?php echo $item['href']; ?>" class="<?php echo $currentPage === $item['page'] ? 'active' : ''; ?>">
            <?php echo $item['label']; ?>
        </a>
    <?php endforeach; ?>
</nav>
