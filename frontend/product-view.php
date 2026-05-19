<?php
include("../backend/config/db.php");
include_once("../backend/includes/product_images.php");

$productId = (int) ($_GET["id"] ?? 0);
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $productId);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

function productViewTableExists($conn, $tableName) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->bind_param("s", $tableName);
    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()["total"] ?? 0) > 0;
}

function productMoney($value) {
    return number_format((float) $value, 0);
}

function productStars($rating) {
    $rating = max(1, min(5, (int) round($rating)));
    return str_repeat("&#9733;", $rating) . str_repeat("&#9734;", 5 - $rating);
}

function productValue($source, $field) {
    $value = trim((string) ($source[$field] ?? ""));
    return $value !== "" ? $value : "Not provided";
}

$galleryImages = $product ? getProductGalleryImages($conn, $product) : [];
$mainImage = $galleryImages[0] ?? "";
$price = (float) ($product["discount_price"] ?: $product["price"] ?? 0);
$oldPrice = (float) ($product["original_price"] ?: $product["price"] ?? 0);
$discountPercent = $oldPrice > $price && $oldPrice > 0 ? round((($oldPrice - $price) / $oldPrice) * 100) : 0;
$stockQuantity = (int) ($product["stock_quantity"] ?? 0);
$productStatus = ucfirst(strtolower(trim((string) ($product["status"] ?? "active"))));
$availability = !empty($product["in_stock"]) && $stockQuantity > 0 ? "In Stock: " . $stockQuantity . " available" : "Out of Stock";
$rating = 0;
$reviewCount = 0;
$reviews = [];

if ($product && productViewTableExists($conn, "reviews")) {
    $reviewStmt = $conn->prepare("SELECT AVG(rating) AS avg_rating, COUNT(*) AS total FROM reviews WHERE product_id = ? AND LOWER(status) = 'approved'");
    $reviewStmt->bind_param("i", $productId);
    $reviewStmt->execute();
    $reviewStats = $reviewStmt->get_result()->fetch_assoc();
    if ((int) ($reviewStats["total"] ?? 0) > 0) {
        $rating = (float) $reviewStats["avg_rating"];
        $reviewCount = (int) $reviewStats["total"];
    }

    $reviewsStmt = $conn->prepare("SELECT r.rating, r.review_text, r.created_at, COALESCE(u.full_name, u.username, 'Customer') AS customer_name FROM reviews r LEFT JOIN users u ON u.id = r.user_id WHERE r.product_id = ? AND LOWER(r.status) = 'approved' ORDER BY r.created_at DESC LIMIT 4");
    $reviewsStmt->bind_param("i", $productId);
    $reviewsStmt->execute();
    $reviewsResult = $reviewsStmt->get_result();
    while ($review = $reviewsResult->fetch_assoc()) {
        $reviews[] = $review;
    }
}

$similarProducts = null;
if ($product) {
    $statusCheck = $conn->query("SHOW COLUMNS FROM products LIKE 'status'");
    $hasStatus = $statusCheck && $statusCheck->num_rows > 0;
    if ($hasStatus) {
        $similarStmt = $conn->prepare("SELECT id, name, price, discount_price, original_price, image, image_1, image_2, image_3, image_4, category FROM products WHERE category = ? AND id <> ? AND LOWER(status) = 'active' ORDER BY featured DESC, id DESC LIMIT 10");
    } else {
        $similarStmt = $conn->prepare("SELECT id, name, price, discount_price, original_price, image, image_1, image_2, image_3, image_4, category FROM products WHERE category = ? AND id <> ? ORDER BY id DESC LIMIT 10");
    }
    $similarStmt->bind_param("si", $product["category"], $productId);
    $similarStmt->execute();
    $similarProducts = $similarStmt->get_result();
}

include("../backend/includes/header.php");
?>
<link rel="stylesheet" href="../assets/css/product-view.css">

<main class="product-view-page">
    <?php
    $pageBackText = "Back to Products";
    $pageBackHref = "product-list.php";
    $pageBackHistory = true;
    include("../backend/includes/page_back_button.php");
    ?>
    <?php if (!$product): ?>
        <section class="product-empty-state">
            <span>Zafiro Casa</span>
            <h1>Product Not Found</h1>
            <p>The selected product is unavailable or has been removed.</p>
            <a href="index.php" class="product-view-btn primary">Continue Shopping</a>
        </section>
    <?php else: ?>
        <section class="product-view-shell product-detail-view"
            data-product-id="<?php echo (int) $product["id"]; ?>"
            data-product-name="<?php echo htmlspecialchars($product["name"]); ?>"
            data-product-price="<?php echo htmlspecialchars((string) $price); ?>"
            data-product-image="<?php echo htmlspecialchars($mainImage); ?>"
            data-product-url="product-view.php?id=<?php echo (int) $product["id"]; ?>">

            <div class="product-left-column">
                <div class="product-gallery-panel">
                    <div class="product-thumbnail-rail" aria-label="Product images">
                        <?php foreach (array_slice($galleryImages, 0, 4) as $index => $image): ?>
                            <button type="button" class="product-thumb <?php echo $index === 0 ? "active" : ""; ?>" data-product-thumb="<?php echo htmlspecialchars($image); ?>" aria-label="View product image <?php echo $index + 1; ?>">
                                <img src="<?php echo htmlspecialchars($image); ?>" alt="">
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <div class="product-main-image-wrap">
                        <div class="image-corner-actions">
                            <button type="button" class="image-icon-btn wishlist-btn" aria-label="Wishlist"><i class="fa-regular fa-heart"></i></button>
                            <button type="button" class="image-icon-btn share-btn" aria-label="Share Product"><i class="fa-solid fa-share-nodes"></i></button>
                        </div>
                        <img src="<?php echo htmlspecialchars($mainImage); ?>" alt="<?php echo htmlspecialchars($product["name"]); ?>" class="product-view-main-image" data-product-view-main-image>
                    </div>
                </div>

                <div class="product-action-bar">
                    <button type="button" class="product-view-btn secondary add-cart-btn"><i class="fa-solid fa-cart-shopping"></i> Add to Cart</button>
                    <button type="button" class="product-view-btn primary buy-now-product-btn"><i class="fa-solid fa-bag-shopping"></i> Buy Now</button>
                </div>
            </div>

            <div class="product-info-panel">
                <div class="breadcrumb-line"><?php echo htmlspecialchars(productValue($product, "category")); ?> / <?php echo htmlspecialchars(productValue($product, "brand")); ?></div>
                <h1><?php echo htmlspecialchars($product["name"]); ?></h1>
                <div class="product-meta-row">
                    <span class="rating-pill"><?php echo htmlspecialchars($reviewCount ? number_format($rating, 1) : '0.0'); ?> &#9733;</span>
                    <span><?php echo htmlspecialchars((string) $reviewCount); ?> Reviews</span>
                    <?php if (!empty($product["featured"])): ?><span>Featured</span><?php endif; ?>
                    <?php if (!empty($product["trending"])): ?><span>Trending</span><?php endif; ?>
                </div>

                <div class="product-price-row">
                    <strong>&#8377;<?php echo productMoney($price); ?></strong>
                    <del>&#8377;<?php echo productMoney($oldPrice); ?></del>
                    <?php if ($discountPercent > 0): ?><span><?php echo (int) $discountPercent; ?>% Off</span><?php endif; ?>
                </div>

                <div class="stock-line"><?php echo htmlspecialchars($availability); ?></div>
                <p class="product-short-text"><?php echo htmlspecialchars(productValue($product, "short_description")); ?></p>

                <div class="seller-box">
                    <div>
                        <span>Sold by</span>
                        <strong>Zafiro Casa Luxury Living</strong>
                        <p>Premium furniture seller for refined homes.</p>
                    </div>
                    <span class="seller-rating">4+ &#9733;</span>
                </div>

                <div class="service-grid" aria-label="Delivery and service options">
                    <div><i class="fa-solid fa-rotate-left"></i><span>7-Day Return Policy</span></div>
                    <div><i class="fa-solid fa-money-bill-wave"></i><span>Cash on Delivery</span></div>
                    <div><i class="fa-solid fa-shield-halved"></i><span>Secure Payment</span></div>
                    <div><i class="fa-solid fa-box-open"></i><span>Premium Handling</span></div>
                </div>

                <div class="quantity-row">
                    <span>Quantity</span>
                    <div class="quantity-control" data-product-quantity-control>
                        <button type="button" data-qty-minus>-</button>
                        <strong data-qty-value>1</strong>
                        <button type="button" data-qty-plus>+</button>
                    </div>
                </div>
            </div>
        </section>

        <section class="product-facts-card">
            <h2>Product Details</h2>
            <div class="product-detail-grid">
                <div><span>Brand</span><strong><?php echo htmlspecialchars(productValue($product, "brand")); ?></strong></div>
                <div><span>Category</span><strong><?php echo htmlspecialchars(productValue($product, "category")); ?></strong></div>
                <div><span>Material</span><strong><?php echo htmlspecialchars(productValue($product, "material")); ?></strong></div>
                <div><span>Color</span><strong><?php echo htmlspecialchars(productValue($product, "color")); ?></strong></div>
                <div><span>Dimensions</span><strong><?php echo htmlspecialchars(productValue($product, "dimensions")); ?></strong></div>
                <div><span>Weight</span><strong><?php echo htmlspecialchars(productValue($product, "weight")); ?></strong></div>
                <div><span>Room Type</span><strong><?php echo htmlspecialchars(productValue($product, "room_type")); ?></strong></div>
                <div><span>Assembly Required</span><strong><?php echo htmlspecialchars(productValue($product, "assembly_required")); ?></strong></div>
                <div><span>Stock Quantity</span><strong><?php echo (int) $stockQuantity; ?></strong></div>
                <div><span>Status</span><strong><?php echo htmlspecialchars($productStatus); ?></strong></div>
            </div>
        </section>

        <section class="product-details-section">
            <article>
                <h2>Full Description</h2>
                <p><?php echo nl2br(htmlspecialchars(productValue($product, "full_description"))); ?></p>
            </article>
            <article>
                <h2>Specifications</h2>
                <p><?php echo nl2br(htmlspecialchars(productValue($product, "specifications"))); ?></p>
            </article>
            <article>
                <h2>Customer Reviews</h2>
                <?php if ($reviews): ?>
                    <div class="review-list">
                        <?php foreach ($reviews as $review): ?>
                            <div>
                                <strong><?php echo htmlspecialchars(productValue($review, "customer_name")); ?></strong>
                                <span><?php echo productStars($review["rating"]); ?></span>
                                <p><?php echo htmlspecialchars(productValue($review, "review_text")); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>No approved reviews yet.</p>
                <?php endif; ?>
            </article>
        </section>

        <section class="similar-products-section">
            <div class="section-title">
                <span>Zafiro Casa</span>
                <h2>Similar Products</h2>
            </div>
            <div class="similar-products-row">
                <?php if ($similarProducts && $similarProducts->num_rows > 0): ?>
                    <?php while ($similar = $similarProducts->fetch_assoc()): ?>
                        <?php $similarPrice = (float) ($similar["discount_price"] ?: $similar["price"]); ?>
                        <?php $similarImage = getProductCardImage($similar); ?>
                        <article class="similar-product-card"
                            data-product-id="<?php echo (int) $similar["id"]; ?>"
                            data-product-name="<?php echo htmlspecialchars($similar["name"]); ?>"
                            data-product-price="<?php echo htmlspecialchars((string) $similarPrice); ?>"
                            data-product-image="<?php echo htmlspecialchars($similarImage); ?>"
                            data-product-url="product-view.php?id=<?php echo (int) $similar["id"]; ?>">
                            <a href="product-view.php?id=<?php echo (int) $similar["id"]; ?>">
                                <img src="<?php echo htmlspecialchars($similarImage); ?>" alt="<?php echo htmlspecialchars($similar["name"]); ?>">
                            </a>
                            <div>
                                <span class="product-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</span>
                                <h3><?php echo htmlspecialchars($similar["name"]); ?></h3>
                                <p>&#8377;<?php echo productMoney($similarPrice); ?></p>
                                <div class="similar-actions">
                                    <a href="product-view.php?id=<?php echo (int) $similar["id"]; ?>">View Details</a>
                                    <button type="button" class="add-cart-btn">Add to Cart</button>
                                </div>
                            </div>
                        </article>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="similar-empty">No similar products found in this category.</p>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</main>

<script src="../assets/js/product-view.js"></script>
<?php include("../backend/includes/footer.php"); ?>
