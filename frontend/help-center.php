<?php include("../backend/includes/header.php"); ?>
<main class="page-bg luxury-bg help-bg">
    <div class="page-content account-simple-page">
    <?php include("../backend/includes/profile_back_button.php"); ?>
    <section class="account-card">
        <h1>Help Center</h1>
        <div class="help-accordion">
            <button type="button" class="help-toggle">FAQs</button>
            <div class="help-panel"><p>Find answers about orders, products, payment, delivery and returns.</p></div>
            <button type="button" class="help-toggle">Contact Support</button>
            <div class="help-panel"><p>Email: info@zafirocasa.com<br>Phone: +91 91716 17974</p></div>
            <button type="button" class="help-toggle">Track Order Help</button>
            <div class="help-panel"><p>Use your Order ID on the tracking page to view order dates and status.</p><a href="order-tracking.php" class="account-btn small">Track Order</a></div>
            <button type="button" class="help-toggle">Return / Cancellation Help</button>
            <div class="help-panel"><p>Open My Orders, choose an order, then click Return Order to submit a request.</p></div>
            <button type="button" class="help-toggle">Payment Help</button>
            <div class="help-panel"><p>UPI/QR payment support and confirmation details will be shown during checkout.</p></div>
            <button type="button" class="help-toggle">Delivery Help</button>
            <div class="help-panel"><p>Delivery timelines depend on location and product availability.</p></div>
        </div>
    </section>
    </div>
</main>
<?php include("../backend/includes/footer.php"); ?>
