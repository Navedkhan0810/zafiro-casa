<?php
include("../backend/config/db.php");
include("../backend/includes/header.php");
include_once("../backend/includes/product_images.php");

$category = trim($_GET['category'] ?? '');
$title = ucwords(str_replace('-', ' ', $category));
$result = null;
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 24;
$offset = ($page - 1) * $perPage;
$totalProducts = 0;

function productListColumnExists($conn, $columnName) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'products' AND column_name = ?");
    $stmt->bind_param("s", $columnName);
    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()["total"] ?? 0) > 0;
}

if ($category !== '') {
    $slug = strtolower($category);
    $categoryAliases = [
        "study" => "study-office",
        "office" => "study-office",
        "modular kitchen" => "modular-kitchen",
        "decor" => "decor-furnishing"
    ];
    $slug = $categoryAliases[$slug] ?? $slug;
    $nameLookup = str_replace("-", " ", $slug);
    $categoryStmt = $conn->prepare("SELECT id, 0 AS is_subcategory FROM categories WHERE (LOWER(slug) = ? OR LOWER(category_name) = ?) AND (parent_id IS NULL OR parent_id = 0) UNION SELECT id, 1 AS is_subcategory FROM subcategories WHERE LOWER(slug) = ? OR LOWER(subcategory_name) = ? LIMIT 1");
    $categoryStmt->bind_param("ssss", $slug, $nameLookup, $slug, $nameLookup);
    $categoryStmt->execute();
    $categoryRow = $categoryStmt->get_result()->fetch_assoc();
    $categoryStmt->close();

    if ($categoryRow) {
        $categoryId = (int) $categoryRow["id"];
        $isSubcategory = !empty($categoryRow["is_subcategory"]);
        $filterColumn = $isSubcategory ? "subcategory_id" : "category_id";
        $allowedDiningSubcategories = [26, 27, 28];
        $allowedModularKitchenSubcategories = [30, 31];

        if (!$isSubcategory && $slug === "dining") {
            $placeholders = implode(",", array_fill(0, count($allowedDiningSubcategories), "?"));
            $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM products p WHERE (p.subcategory_id IN ($placeholders) OR (p.category_id = ? AND p.subcategory_id IS NULL)) AND LOWER(COALESCE(p.status, 'active')) = 'active'");
            $countStmt->bind_param("iiii", $allowedDiningSubcategories[0], $allowedDiningSubcategories[1], $allowedDiningSubcategories[2], $categoryId);
        } elseif (!$isSubcategory && $slug === "modular-kitchen") {
            $placeholders = implode(",", array_fill(0, count($allowedModularKitchenSubcategories), "?"));
            $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM products p WHERE (p.subcategory_id IN ($placeholders) OR (p.category_id = ? AND p.subcategory_id IS NULL)) AND LOWER(COALESCE(p.status, 'active')) = 'active'");
            $countStmt->bind_param("iii", $allowedModularKitchenSubcategories[0], $allowedModularKitchenSubcategories[1], $categoryId);
        } else {
            $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM products p WHERE p.$filterColumn = ? AND LOWER(COALESCE(p.status, 'active')) = 'active'");
            $countStmt->bind_param("i", $categoryId);
        }
        $countStmt->execute();
        $totalProducts = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
        $countStmt->close();

        if (!$isSubcategory && $slug === "dining") {
            $stmt = $conn->prepare("SELECT p.* FROM products p WHERE (p.subcategory_id IN ($placeholders) OR (p.category_id = ? AND p.subcategory_id IS NULL)) AND LOWER(COALESCE(p.status, 'active')) = 'active' ORDER BY p.id DESC LIMIT ? OFFSET ?");
            $stmt->bind_param("iiiiii", $allowedDiningSubcategories[0], $allowedDiningSubcategories[1], $allowedDiningSubcategories[2], $categoryId, $perPage, $offset);
        } elseif (!$isSubcategory && $slug === "modular-kitchen") {
            $stmt = $conn->prepare("SELECT p.* FROM products p WHERE (p.subcategory_id IN ($placeholders) OR (p.category_id = ? AND p.subcategory_id IS NULL)) AND LOWER(COALESCE(p.status, 'active')) = 'active' ORDER BY p.id DESC LIMIT ? OFFSET ?");
            $stmt->bind_param("iiiii", $allowedModularKitchenSubcategories[0], $allowedModularKitchenSubcategories[1], $categoryId, $perPage, $offset);
        } else {
            $stmt = $conn->prepare("SELECT p.* FROM products p WHERE p.$filterColumn = ? AND LOWER(COALESCE(p.status, 'active')) = 'active' ORDER BY p.id DESC LIMIT ? OFFSET ?");
            $stmt->bind_param("iii", $categoryId, $perPage, $offset);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    }
}
?>

<main class="product-list-page">
    <?php
    $pageBackText = "Back to Category";
    $pageBackHref = "index.php";
    $pageBackHistory = true;
    include("../backend/includes/page_back_button.php");
    ?>
    <section class="section-title">
        <span>Zafiro Casa</span>
        <h2><?php echo htmlspecialchars($title ?: "Products"); ?></h2>
    </section>

    <section class="grid product-result-grid">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <?php $cardImage = getProductCardImage($row, $conn); ?>
                <div class="card product-card" data-product-id="<?php echo htmlspecialchars($row['id']); ?>" data-product-name="<?php echo htmlspecialchars($row['name']); ?>" data-product-price="<?php echo htmlspecialchars($row['price']); ?>" data-product-image="<?php echo htmlspecialchars($cardImage); ?>" data-product-url="product.php?id=<?php echo htmlspecialchars($row['id']); ?>">
                    <a class="product-card-link product-image-wrap" href="product.php?id=<?php echo htmlspecialchars($row['id']); ?>">
                        <img src="<?php echo htmlspecialchars($cardImage); ?>" alt="<?php echo htmlspecialchars($row['name']); ?>" loading="lazy" decoding="async" width="600" height="420">
                        <div class="product-card-actions">
                            <button type="button" class="icon-action wishlist-btn" aria-label="Wishlist"><i class="fa-regular fa-heart"></i></button>
                            <button type="button" class="icon-action share-btn" aria-label="Share"><i class="fa-solid fa-share-nodes"></i></button>
                        </div>
                    </a>
                    <a class="product-card-link product-card-text" href="product.php?id=<?php echo htmlspecialchars($row['id']); ?>">
                        <p><?php echo htmlspecialchars($row['name']); ?></p>
                        <span class="price">&#8377;<?php echo htmlspecialchars($row['price']); ?></span>
                        <p class="short-desc"><?php echo htmlspecialchars(substr($row['description'], 0, 80)); ?></p>
                    </a>
                    <button type="button" class="product-btn add-cart-btn">Add to Cart</button>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No products available in this category.</p>
        <?php endif; ?>
    </section>
    <?php if ($totalProducts > $perPage): ?>
        <nav class="pagination">
            <?php for ($i = 1, $pages = (int)ceil($totalProducts / $perPage); $i <= $pages; $i++): ?>
                <a class="<?php echo $i === $page ? 'active' : ''; ?>" href="product-list.php?category=<?php echo urlencode($category); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
</main>

<?php include("../backend/includes/footer.php"); ?>

<?php
if (isset($stmt)) {
    $stmt->close();
}
$conn->close();
?>
