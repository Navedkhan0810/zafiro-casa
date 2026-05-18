<?php
include("auth.php");
include("../backend/config/db.php");
include_once("../backend/includes/product_images.php");
include_once("../backend/includes/admin_reports.php");

$message = "";
$messageType = "";

function adminManageTableExists($conn, $tableName) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->bind_param("s", $tableName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int) ($row["total"] ?? 0) > 0;
}

$conn->query("ALTER TABLE products ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'active'");
$conn->query("ALTER TABLE products ADD COLUMN IF NOT EXISTS featured TINYINT(1) DEFAULT 0");
$conn->query("ALTER TABLE products ADD COLUMN IF NOT EXISTS trending TINYINT(1) DEFAULT 0");
$conn->query("ALTER TABLE products ADD COLUMN IF NOT EXISTS stock_quantity INT DEFAULT 0");
ensureProductImageColumns($conn);
$conn->query("ALTER TABLE products ADD COLUMN IF NOT EXISTS created_at DATETIME DEFAULT CURRENT_TIMESTAMP");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    $productId = (int) ($_POST["product_id"] ?? 0);

    if ($action === "delete" && $productId > 0) {
        $imageStmt = $conn->prepare("SELECT name, image FROM products WHERE id = ? LIMIT 1");
        $imageStmt->bind_param("i", $productId);
        $imageStmt->execute();
        $imageRow = $imageStmt->get_result()->fetch_assoc();
        $productName = $imageRow["name"] ?? "";

        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->bind_param("i", $productId);
        if ($stmt->execute()) {
            if (!empty($imageRow["image"])) {
                $imagePath = realpath(__DIR__ . "/" . $imageRow["image"]);
                $uploadsRoot = realpath(__DIR__ . "/../uploads");
                if ($imagePath && $uploadsRoot && str_starts_with($imagePath, $uploadsRoot) && file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
            if (adminManageTableExists($conn, "product_images")) {
                $imageDelete = $conn->prepare("DELETE FROM product_images WHERE product_id = ?");
                $imageDelete->bind_param("i", $productId);
                $imageDelete->execute();
            }
            adminReportLog($conn, "delete_product", "Deleted product: " . ($productName ?: "#" . $productId), "product", $productId, $productName);
            $message = "Product deleted successfully.";
            $messageType = "success";
        }
    }

    if ($action === "toggle_status" && $productId > 0) {
        $newStatus = $_POST["new_status"] === "active" ? "active" : "inactive";
        $stmt = $conn->prepare("UPDATE products SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $newStatus, $productId);
        if ($stmt->execute()) {
            adminReportLog($conn, "change_product_status", "Changed product status to " . $newStatus . ".", "product", $productId);
            $message = "Product status updated.";
            $messageType = "success";
        }
    }
}

$search = trim($_GET["search"] ?? "");
$category = trim($_GET["category"] ?? "");
$status = trim($_GET["status"] ?? "");
$featured = trim($_GET["featured"] ?? "");
$trending = trim($_GET["trending"] ?? "");
$stock = trim($_GET["stock"] ?? "");
$sort = trim($_GET["sort"] ?? "newest");
$page = max(1, (int) ($_GET["page"] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;
$totalPages = 1;

$where = [];
$params = [];
$types = "";

if ($search !== "") {
    $where[] = "(name LIKE ? OR sku LIKE ? OR category LIKE ? OR brand LIKE ?)";
    $term = "%" . $search . "%";
    array_push($params, $term, $term, $term, $term);
    $types .= "ssss";
}

if ($category !== "") {
    [$filterType, $filterId] = array_pad(explode(":", $category, 2), 2, "");
    if ($filterType === "cat" && (int) $filterId > 0) {
        $where[] = "category_id = ?";
        $params[] = (int) $filterId;
        $types .= "i";
    } elseif ($filterType === "sub" && (int) $filterId > 0) {
        $where[] = "subcategory_id = ?";
        $params[] = (int) $filterId;
        $types .= "i";
    }
}

if ($status === "active" || $status === "inactive") {
    $where[] = "LOWER(status) = ?";
    $params[] = $status;
    $types .= "s";
} elseif ($status === "out_of_stock") {
    $where[] = "(stock_quantity <= 0 OR in_stock = 0)";
}

if ($featured === "1") $where[] = "featured = 1";
if ($trending === "1") $where[] = "trending = 1";
if ($stock === "low") $where[] = "stock_quantity BETWEEN 1 AND 5";

$orderBy = "id DESC";
if ($sort === "oldest") $orderBy = "id ASC";
if ($sort === "price_low") $orderBy = "CAST(COALESCE(NULLIF(discount_price, 0), original_price, price) AS DECIMAL(10,2)) ASC";
if ($sort === "price_high") $orderBy = "CAST(COALESCE(NULLIF(discount_price, 0), original_price, price) AS DECIMAL(10,2)) DESC";
if ($sort === "stock_low") $orderBy = "stock_quantity ASC";

$countSql = "SELECT COUNT(*) AS total FROM products" . ($where ? " WHERE " . implode(" AND ", $where) : "");
$countStmt = $conn->prepare($countSql);
if ($params) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalProducts = (int) ($countStmt->get_result()->fetch_assoc()["total"] ?? 0);
$totalPages = max(1, (int) ceil($totalProducts / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$sql = "SELECT * FROM products" . ($where ? " WHERE " . implode(" AND ", $where) : "") . " ORDER BY " . $orderBy . " LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$queryParams = $params;
$queryParams[] = $perPage;
$queryParams[] = $offset;
$stmt->bind_param($types . "ii", ...$queryParams);
$stmt->execute();
$products = $stmt->get_result();

$categories = $conn->query("SELECT CONCAT('cat:', id) AS filter_value, category_name AS label FROM categories WHERE parent_id IS NULL OR parent_id = 0 UNION SELECT CONCAT('sub:', id) AS filter_value, subcategory_name AS label FROM subcategories ORDER BY label ASC");

include("includes/admin_header.php");
include("includes/admin_sidebar.php");
?>
<main class="admin-main">
    <header class="admin-topbar admin-dark-topbar">
        <div>
            <span>Zafiro Casa Luxury Living</span>
            <h1>Manage Products</h1>
            <p>View and manage all furniture products in your store.</p>
        </div>
        <a class="admin-btn" href="add_product.php">+ Add New Product</a>
    </header>

    <?php if ($message): ?>
        <div class="admin-popup <?php echo $messageType === 'success' ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <section class="admin-form-card manage-filter-card">
        <form method="GET" action="manage_products.php" class="manage-products-filter">
            <button type="button" class="admin-search-toggle" id="adminProductSearchToggle" aria-label="Open product search"><i class="fas fa-search"></i></button>
            <input type="hidden" name="search" id="adminProductSearchHidden" value="<?php echo htmlspecialchars($search); ?>">
            <select name="category">
                <option value="">All Categories</option>
                <?php while ($cat = $categories->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($cat['filter_value']); ?>" <?php echo $category === $cat['filter_value'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['label']); ?></option>
                <?php endwhile; ?>
            </select>
            <select name="status">
                <option value="">All Status</option>
                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                <option value="out_of_stock" <?php echo $status === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
            </select>
            <select name="stock">
                <option value="">Stock</option>
                <option value="low" <?php echo $stock === 'low' ? 'selected' : ''; ?>>Low Stock</option>
            </select>
            <select name="sort">
                <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price Low to High</option>
                <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price High to Low</option>
                <option value="stock_low" <?php echo $sort === 'stock_low' ? 'selected' : ''; ?>>Stock Low to High</option>
            </select>
            <button type="submit" class="admin-btn">Apply</button>
            <a class="admin-btn admin-btn-light" href="manage_products.php">Reset</a>
        </form>
        <div class="admin-search-popup" id="adminProductSearchPopup">
            <form method="GET" action="manage_products.php" class="admin-search-popup-card">
                <button type="button" class="admin-search-close" id="adminProductSearchClose" aria-label="Close search">×</button>
                <h3>Search Products</h3>
                <p>Search by product name, SKU, category, or brand.</p>
                <input type="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Type product keyword...">
                <button type="submit" class="admin-btn">Search</button>
            </form>
        </div>
    </section>

    <section class="admin-panel-card manage-products-card">
        <div class="admin-list-summary">
            Showing <?php echo $products ? (int) $products->num_rows : 0; ?> of <?php echo (int) $totalProducts; ?> products
        </div>
        <?php if ($products && $products->num_rows > 0): ?>
            <div class="manage-product-card-list">
                <?php while ($product = $products->fetch_assoc()): ?>
                    <?php
                    $productStatus = strtolower($product['status'] ?? 'active') === 'active' ? 'active' : 'inactive';
                    $toggleStatus = $productStatus === 'active' ? 'inactive' : 'active';
                    $cardImage = getProductCardImage($product);
                    ?>
                    <article class="manage-product-card">
                        <div class="manage-product-summary">
                            <div class="manage-product-media">
                                <img src="<?php echo htmlspecialchars($cardImage); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" loading="lazy" decoding="async" width="220" height="180">
                            </div>
                            <h2><?php echo htmlspecialchars($product['name']); ?></h2>
                            <p>SKU: <?php echo htmlspecialchars($product['sku'] ?: 'Not set'); ?></p>
                            <span class="status-pill"><?php echo ucfirst($productStatus); ?></span>
                        </div>

                        <div class="manage-product-main">
                            <div class="manage-product-meta">
                                <span><strong>Category</strong><?php echo htmlspecialchars($product['category'] ?: 'Uncategorized'); ?></span>
                                <span><strong>Original</strong>&#8377;<?php echo htmlspecialchars($product['original_price'] ?? $product['price'] ?? '0'); ?></span>
                                <span><strong>Discount</strong>&#8377;<?php echo htmlspecialchars($product['discount_price'] ?? $product['price'] ?? '0'); ?></span>
                                <span><strong>Stock</strong><?php echo htmlspecialchars($product['stock_quantity'] ?? '0'); ?></span>
                                <span><strong>Created</strong><?php echo htmlspecialchars($product['created_at'] ?? ''); ?></span>
                            </div>

                            <div class="product-label-list">
                                <?php if (!empty($product['featured'])): ?><span>Featured</span><?php endif; ?>
                                <?php if (!empty($product['trending'])): ?><span>Trending</span><?php endif; ?>
                                <?php if (empty($product['featured']) && empty($product['trending'])): ?><span>Standard</span><?php endif; ?>
                            </div>
                        </div>

                        <div class="manage-card-actions" aria-label="Product actions">
                            <a href="../frontend/product-view.php?id=<?php echo (int) $product['id']; ?>" class="admin-action-link" target="_blank">View</a>
                            <a href="edit_product.php?id=<?php echo (int) $product['id']; ?>" class="admin-action-link edit">Edit</a>
                            <form method="POST" action="manage_products.php" class="inline-admin-form">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">
                                <input type="hidden" name="new_status" value="<?php echo $toggleStatus; ?>">
                                <button type="submit" class="admin-action-link"><?php echo ucfirst($toggleStatus); ?></button>
                            </form>
                            <form method="POST" action="manage_products.php" class="inline-admin-form delete-product-form">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">
                                <button type="submit" class="admin-action-link danger">Delete</button>
                            </form>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-admin-state">
                <h2>No products found. Add your first product.</h2>
                <a class="admin-btn" href="add_product.php">+ Add New Product</a>
            </div>
        <?php endif; ?>
        <?php if ($totalPages > 1): ?>
            <nav class="admin-simple-pagination">
                <?php
                $prevUrl = "manage_products.php?" . http_build_query(array_merge($_GET, ["page" => max(1, $page - 1)]));
                $nextUrl = "manage_products.php?" . http_build_query(array_merge($_GET, ["page" => min($totalPages, $page + 1)]));
                ?>
                <a class="<?php echo $page <= 1 ? "disabled" : ""; ?>" href="<?php echo htmlspecialchars($prevUrl); ?>">Previous</a>
                <span>Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                <a class="<?php echo $page >= $totalPages ? "disabled" : ""; ?>" href="<?php echo htmlspecialchars($nextUrl); ?>">Next</a>
            </nav>
        <?php endif; ?>
    </section>
<?php include("includes/admin_footer.php"); ?>
