<?php include("../backend/includes/header.php"); ?>
<main class="cart-page">
    <?php
    $pageBackText = "Back to Shopping";
    $pageBackHref = "index.php";
    $pageBackHistory = true;
    include("../backend/includes/page_back_button.php");
    ?>
    <div class="cart-container cart-layout">
        <section class="cart-main cart-products">
            <div class="cart-address-box">
                <strong>From Saved Addresses</strong>
                <div class="pincode-row">
                    <input type="text" placeholder="Enter Delivery Pincode">
                    <button type="button" class="account-btn small">Check</button>
                </div>
            </div>

            <section class="cart-items-card">
                <h1>Shopping Cart</h1>
                <div id="cartProducts" class="cart-list"><p>Your cart is empty.</p></div>
            </section>
        </section>

        <aside class="price-summary-wrapper">
            <div id="cartSummary" class="cart-summary cart-summary-sticky price-summary"></div>
        </aside>
    </div>
</main>
<?php include("../backend/includes/footer.php"); ?>
