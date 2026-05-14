<?php
session_start();
include_once("../backend/includes/user_auth.php");
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit;
}
include("../backend/includes/header.php");
?>
<main class="account-simple-page">
    <section class="account-card">
        <h1>Wishlist</h1>
        <div id="wishlistProducts" class="commerce-grid"><p>Your wishlist is empty.</p></div>
        <a href="index.php" class="account-btn small">Explore Products</a>
    </section>
</main>
<?php include("../backend/includes/footer.php"); ?>
