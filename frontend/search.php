<?php
include("../backend/config/db.php");
include("../backend/includes/header.php");
include_once("../backend/includes/product_images.php");

$q = trim($_GET['q'] ?? ($_GET['query'] ?? ''));
$result = null;
$message = '';

if ($q === '') {
    $message = 'Please enter a search term.';
} else {
    $like = '%' . $q . '%';
    $statusCheck = $conn->query("SHOW COLUMNS FROM products LIKE 'status'");
    $hasStatus = $statusCheck && $statusCheck->num_rows > 0;
    $sql = "SELECT * FROM products WHERE (name LIKE ? OR category LIKE ? OR brand LIKE ? OR short_description LIKE ? OR slug LIKE ?)";

    if ($hasStatus) {
        $sql .= " AND LOWER(status) = 'active'";
    }

    $sql .= " ORDER BY id DESC LIMIT 24";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $like, $like, $like, $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();
}
?>

<link rel="stylesheet" href="../assets/css/category.css">

<section class="products">
    <h2>Search Results</h2>

    <?php if ($message): ?>
        <p><?php echo htmlspecialchars($message); ?></p>
    <?php elseif ($result && $result->num_rows > 0): ?>
        <div class="grid">
            <?php while ($row = $result->fetch_assoc()): ?>
                <?php $cardImage = getProductCardImage($row); ?>
                <div class="card product-card" data-product-id="<?php echo htmlspecialchars($row['id']); ?>" data-product-name="<?php echo htmlspecialchars($row['name']); ?>" data-product-price="<?php echo htmlspecialchars($row['price']); ?>" data-product-image="<?php echo htmlspecialchars($cardImage); ?>" data-product-url="product.php?id=<?php echo htmlspecialchars($row['id']); ?>">
                    <a class="product-card-link" href="product.php?id=<?php echo htmlspecialchars($row['id']); ?>">
                        <img src="<?php echo htmlspecialchars($cardImage); ?>" alt="<?php echo htmlspecialchars($row['name']); ?>" loading="lazy" decoding="async" width="600" height="420">
                        <p><?php echo htmlspecialchars($row['name']); ?></p>
                        <span class="price">&#8377;<?php echo htmlspecialchars($row['price']); ?></span>
                        <p class="short-desc"><?php echo htmlspecialchars($row['category'] ?? ''); ?></p>
                    </a>
                    <button type="button" class="product-btn add-cart-btn">Add to Cart</button>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p>No products found for your search.</p>
    <?php endif; ?>
</section>

<?php include("../backend/includes/footer.php"); ?>

<?php
if (isset($stmt)) $stmt->close();
$conn->close();
?>
