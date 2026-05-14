<?php
session_start();
include("../backend/config/db.php");
include_once("../backend/includes/user_auth.php");

$savedAddresses = [];
if (isset($_SESSION['user_id'])) {
    $userId = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY id DESC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $savedAddresses[] = $row;
    }
}

include("../backend/includes/header.php");
?>
<main class="place-order-page">
    <div class="place-order-container" id="placeOrderFlow">
        <div class="place-stepper">
            <button type="button" class="active" data-step-btn="1">1. Delivery Address</button>
            <button type="button" data-step-btn="2">2. Order Summary</button>
            <button type="button" data-step-btn="3">3. Payment</button>
        </div>

        <section class="place-step active" data-step="1">
            <div class="place-card">
                <div class="place-card-head">
                    <h1>Delivery Address</h1>
                    <a class="account-btn outline small" href="address.php">Change Address</a>
                </div>

                <div id="savedAddressList" class="saved-address-list">
                    <?php if (!empty($savedAddresses)): ?>
                        <?php foreach ($savedAddresses as $index => $address): ?>
                            <?php
                            $fullAddress = trim($address['house_no'] . ', ' . $address['street_area']);
                            ?>
                            <article
                                class="saved-address-card db-saved-address"
                                data-index="<?php echo $index; ?>"
                                data-full-name="<?php echo htmlspecialchars($address['full_name']); ?>"
                                data-mobile="<?php echo htmlspecialchars($address['phone']); ?>"
                                data-pincode="<?php echo htmlspecialchars($address['pincode']); ?>"
                                data-city="<?php echo htmlspecialchars($address['city']); ?>"
                                data-state="<?php echo htmlspecialchars($address['state']); ?>"
                                data-full-address="<?php echo htmlspecialchars($fullAddress); ?>"
                                data-landmark="<?php echo htmlspecialchars($address['landmark']); ?>"
                                data-address-type="Home"
                            >
                                <label>
                                    <input type="radio" name="saved_address" <?php echo $index === 0 ? 'checked' : ''; ?>>
                                    <strong><?php echo htmlspecialchars($address['full_name']); ?></strong>
                                </label>
                                <p><?php echo htmlspecialchars($fullAddress . ', ' . $address['city'] . ', ' . $address['state'] . ' - ' . $address['pincode']); ?></p>
                                <p><?php echo htmlspecialchars($address['phone']); ?></p>
                                <?php if (!empty($address['landmark'])): ?>
                                    <p>Landmark: <?php echo htmlspecialchars($address['landmark']); ?></p>
                                <?php endif; ?>
                                <a class="edit-address-btn" href="address.php?edit=<?php echo (int) $address['id']; ?>">Edit</a>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-cart">
                            <h2>No saved address found.</h2>
                            <a class="account-btn small" href="address.php">Add Address</a>
                        </div>
                    <?php endif; ?>
                </div>

                <form id="placeAddressForm" class="place-address-form <?php echo empty($savedAddresses) ? 'is-hidden' : ''; ?>">
                    <input type="text" name="full_name" placeholder="Full Name" required>
                    <input type="tel" name="mobile" placeholder="Mobile Number" required>
                    <input type="tel" name="alternate_phone" placeholder="Alternate Phone Number">
                    <input type="text" name="pincode" placeholder="Pincode" required>
                    <input type="text" name="city" placeholder="City" required>
                    <input type="text" name="state" placeholder="State" required>
                    <textarea name="full_address" placeholder="Full Address" required></textarea>
                    <input type="text" name="landmark" placeholder="Landmark">
                    <select name="address_type" required>
                        <option value="">Address Type</option>
                        <option value="Home">Home</option>
                        <option value="Office">Office</option>
                    </select>
                </form>

                <div class="place-actions <?php echo empty($savedAddresses) ? 'is-hidden' : ''; ?>">
                    <a class="account-btn outline" href="address.php">Change Address</a>
                    <button type="button" class="account-btn" id="addressContinueBtn">Continue</button>
                </div>
            </div>
        </section>

        <section class="place-step" data-step="2">
            <div class="place-grid">
                <div class="place-card">
                    <div class="place-card-head">
                        <h1>Order Summary</h1>
                        <button type="button" class="account-btn outline small" data-go-step="1">Change Address</button>
                    </div>
                    <div id="placeOrderItems" class="place-order-items"></div>
                    <div class="place-actions">
                        <button type="button" class="account-btn outline" data-go-step="1">Back</button>
                        <button type="button" class="account-btn" data-go-step="3">Continue to Payment</button>
                    </div>
                </div>

                <aside id="placeOrderSummary" class="cart-summary"></aside>
            </div>
        </section>

        <section class="place-step" data-step="3">
            <div class="place-grid">
                <div class="place-card payment-layout">
                    <div class="payment-methods" id="paymentMethods">
                        <button type="button" class="active" data-payment="UPI"><i class="fa-solid fa-mobile-screen-button"></i><span>UPI</span></button>
                        <button type="button" data-payment="Card"><i class="fa-solid fa-credit-card"></i><span>Card</span></button>
                        <button type="button" data-payment="Cash on Delivery"><i class="fa-solid fa-money-bill-wave"></i><span>Cash on Delivery</span></button>
                    </div>

                    <div class="payment-details" id="paymentDetails"></div>
                </div>

                <aside id="paymentAmountSummary" class="cart-summary"></aside>
            </div>
        </section>
    </div>
</main>
<?php include("../backend/includes/footer.php"); ?>
