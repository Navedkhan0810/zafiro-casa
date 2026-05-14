<?php
session_start();
include("../backend/config/db.php");
include_once("../backend/includes/user_auth.php");
include_once("../backend/includes/csrf.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit;
}

$userId = (int) $_SESSION['user_id'];
$editAddress = null;
$message = '';

if (isset($_GET['edit'])) {
    $addressId = (int) $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM user_addresses WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->bind_param("ii", $addressId, $userId);
    $stmt->execute();
    $editAddress = $stmt->get_result()->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = $_POST['action'] ?? 'save_address';
    $addressId = (int) ($_POST['address_id'] ?? 0);

    if ($action === 'delete_address' && $addressId > 0) {
        $stmt = $conn->prepare("DELETE FROM user_addresses WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $addressId, $userId);
        $stmt->execute();
        header("Location: address.php");
        exit;
    }

    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $houseNo = trim($_POST['house_no'] ?? '');
    $streetArea = trim($_POST['street_area'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    $landmark = trim($_POST['landmark'] ?? '');

    if ($addressId > 0) {
        $stmt = $conn->prepare("UPDATE user_addresses SET full_name=?, phone=?, house_no=?, street_area=?, city=?, state=?, pincode=?, landmark=? WHERE id=? AND user_id=?");
        $stmt->bind_param("ssssssssii", $fullName, $phone, $houseNo, $streetArea, $city, $state, $pincode, $landmark, $addressId, $userId);
    } else {
        $stmt = $conn->prepare("INSERT INTO user_addresses (user_id, full_name, phone, house_no, street_area, city, state, pincode, landmark) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssssss", $userId, $fullName, $phone, $houseNo, $streetArea, $city, $state, $pincode, $landmark);
    }
    $stmt->execute();
    header("Location: address.php");
    exit;
}

$stmt = $conn->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY id DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$addresses = $stmt->get_result();

include("../backend/includes/header.php");
?>
<main class="address-page-bg">
    <div class="address-container">
    <section class="account-card">
        <h1><?php echo $editAddress ? 'Edit Address' : 'Add Address'; ?></h1>
        <form action="address.php" method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="save_address">
            <input type="hidden" name="address_id" value="<?php echo htmlspecialchars($editAddress['id'] ?? '0'); ?>">
            <div class="profile-form-grid address-form-grid">
                <input type="text" name="full_name" placeholder="Full Name" value="<?php echo htmlspecialchars($editAddress['full_name'] ?? ''); ?>" required>
                <input type="tel" name="phone" placeholder="Phone Number" value="<?php echo htmlspecialchars($editAddress['phone'] ?? ''); ?>" required>
                <input type="text" name="house_no" placeholder="House/Flat No." value="<?php echo htmlspecialchars($editAddress['house_no'] ?? ''); ?>" required>
                <input type="text" name="street_area" placeholder="Street/Area" value="<?php echo htmlspecialchars($editAddress['street_area'] ?? ''); ?>" required>
                <input type="text" name="city" placeholder="City" value="<?php echo htmlspecialchars($editAddress['city'] ?? ''); ?>" required>
                <input type="text" name="state" placeholder="State" value="<?php echo htmlspecialchars($editAddress['state'] ?? ''); ?>" required>
                <input type="text" name="pincode" placeholder="Pincode" value="<?php echo htmlspecialchars($editAddress['pincode'] ?? ''); ?>" required>
                <input type="text" name="landmark" placeholder="Landmark" value="<?php echo htmlspecialchars($editAddress['landmark'] ?? ''); ?>">
            </div>
            <button type="submit" class="account-btn small"><?php echo $editAddress ? 'Update Address' : 'Add New Address'; ?></button>
        </form>
    </section>

    <section class="account-card">
        <h2>Saved Addresses</h2>
        <div class="saved-address-grid">
            <?php if ($addresses && $addresses->num_rows > 0): ?>
                <?php while ($row = $addresses->fetch_assoc()): ?>
                    <div class="address-card">
                        <strong><?php echo htmlspecialchars($row['full_name']); ?></strong>
                        <p><?php echo htmlspecialchars($row['house_no'] . ', ' . $row['street_area'] . ', ' . $row['city'] . ', ' . $row['state'] . ' - ' . $row['pincode']); ?></p>
                        <p><?php echo htmlspecialchars($row['phone']); ?></p>
                        <div class="order-actions">
                            <a class="account-btn small outline" href="address.php?edit=<?php echo $row['id']; ?>">Edit</a>
                            <form method="POST" action="address.php" class="inline-admin-form">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete_address">
                                <input type="hidden" name="address_id" value="<?php echo (int) $row['id']; ?>">
                                <button type="submit" class="account-btn small danger-btn">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No saved addresses.</p>
            <?php endif; ?>
        </div>
    </section>
    </div>
</main>
<?php include("../backend/includes/footer.php"); ?>
