<?php
session_start();
include_once("../backend/includes/user_auth.php");
$profileMessage = $_SESSION['profile_message'] ?? '';
unset($_SESSION['profile_message']);
$isLoggedIn = isset($_SESSION['user_id']);
$displayName = $isLoggedIn ? ($_SESSION['username'] ?? 'User') : 'Guest User';
$displayEmail = $isLoggedIn ? ($_SESSION['email'] ?? '') : 'Not signed in';
include("../backend/config/db.php");
$profileImage = '';
$profileImageX = 50;
$profileImageY = 50;
$profileImageZoom = 1;
function profileColumnExists($conn, $column) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = ?");
    $stmt->bind_param("s", $column);
    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0) > 0;
}
foreach ([
    'profile_image_position_x' => "DECIMAL(5,2) DEFAULT 50",
    'profile_image_position_y' => "DECIMAL(5,2) DEFAULT 50",
    'profile_image_zoom' => "DECIMAL(4,2) DEFAULT 1"
] as $column => $definition) {
    if (!profileColumnExists($conn, $column)) {
        $conn->query("ALTER TABLE users ADD COLUMN `$column` $definition");
    }
}
if ($isLoggedIn) {
    $userId = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT profile_image, profile_image_position_x, profile_image_position_y, profile_image_zoom FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $profileRow = $stmt->get_result()->fetch_assoc();
    $profileImage = $profileRow['profile_image'] ?? '';
    $profileImageX = $profileRow['profile_image_position_x'] ?? 50;
    $profileImageY = $profileRow['profile_image_position_y'] ?? 50;
    $profileImageZoom = $profileRow['profile_image_zoom'] ?? 1;
}
include("../backend/includes/header.php");
?>

<main class="account-page profile-overview-page">
    <section class="profile-top-layout">
    <aside class="account-sidebar profile-guest-only">
        <div class="account-user profile-card">
            <?php if ($isLoggedIn && $profileImage): ?>
                <div class="profile-avatar-wrapper has-image">
                    <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Profile image" data-profile-adjust-preview data-position-x="<?php echo htmlspecialchars($profileImageX); ?>" data-position-y="<?php echo htmlspecialchars($profileImageY); ?>" data-zoom="<?php echo htmlspecialchars($profileImageZoom); ?>">
                </div>
            <?php else: ?>
                <div class="profile-avatar-placeholder">
                    <div class="account-avatar"><i class="fa-regular fa-circle-user"></i></div>
                </div>
            <?php endif; ?>
            <div class="profile-user-info">
                <h2><?php echo htmlspecialchars($displayName); ?></h2>
                <p><?php echo htmlspecialchars($displayEmail); ?></p>
            </div>
        </div>
    </aside>

        <section class="account-card account-hero-card">
            <div>
                <span>Zafiro Casa Account</span>
                <h1>Manage Your Furniture Shopping</h1>
                <p>Sign in to access orders, wishlist, addresses, settings and support.</p>
            </div>
            <div class="account-actions">
                <?php if ($isLoggedIn): ?>
                    <a href="logout.php" class="account-btn muted">Sign Out</a>
                <?php else: ?>
                    <a href="auth.php" class="account-btn">Sign In / Sign Up</a>
                <?php endif; ?>
            </div>
        </section>
    </section>

    <section class="account-content profile-options-content">
        <?php if ($profileMessage): ?>
            <div class="auth-alert success"><?php echo htmlspecialchars($profileMessage); ?></div>
        <?php endif; ?>

        <section class="account-grid profile-options-grid">
            <a class="account-card account-link-card" href="my-orders.php"><h3><i class="fa-solid fa-box"></i> My Orders</h3><p>View orders and write product reviews.</p></a>
            <a class="account-card account-link-card" href="order-tracking.php"><h3><i class="fa-solid fa-truck-fast"></i> Order Tracking</h3><p>Track your order with an order ID.</p></a>
            <a class="account-card account-link-card" href="wishlist.php"><h3><i class="fa-regular fa-heart"></i> Wishlist</h3><p>View saved furniture products.</p></a>
            <a class="account-card account-link-card" href="address.php"><h3><i class="fa-solid fa-location-dot"></i> Address</h3><p>Manage delivery addresses.</p></a>
            <a class="account-card account-link-card" href="edit-profile.php"><h3><i class="fa-solid fa-user-pen"></i> Edit Profile</h3><p>Update name, email and phone.</p></a>
            <a class="account-card account-link-card" href="settings.php"><h3><i class="fa-solid fa-gear"></i> Settings</h3><p>Notifications, privacy and preferences.</p></a>
            <a class="account-card account-link-card" href="recent-view.php"><h3><i class="fa-solid fa-clock-rotate-left"></i> Recently Viewed</h3><p>Continue exploring products you viewed.</p></a>
            <a class="account-card account-link-card" href="help-center.php"><h3><i class="fa-solid fa-headset"></i> Help Center</h3><p>FAQs, returns, tracking and customer care.</p></a>
            <a class="account-card account-link-card danger-link" href="delete-account.php"><h3><i class="fa-solid fa-trash"></i> Delete Account</h3><p>Open account delete confirmation.</p></a>
        </section>
    </section>
</main>

<?php include("../backend/includes/footer.php"); ?>
