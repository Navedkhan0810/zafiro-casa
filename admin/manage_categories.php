<?php
include("auth.php");
include("../backend/config/db.php");
include_once("../backend/includes/admin_reports.php");
include_once("../backend/includes/category_images.php");

$message = "";
$messageType = "";

$conn->query("CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(120) NOT NULL UNIQUE,
    slug VARCHAR(160) NOT NULL UNIQUE,
    parent_id INT NULL,
    description TEXT NULL,
    category_image VARCHAR(255) NULL,
    is_featured TINYINT(1) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
$columnCheck = $conn->query("SHOW COLUMNS FROM categories LIKE 'parent_id'");
if (!$columnCheck || $columnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE categories ADD COLUMN parent_id INT NULL AFTER slug");
}

$countResult = $conn->query("SELECT COUNT(*) AS total FROM categories");
$categoryCount = (int) (($countResult ? $countResult->fetch_assoc() : [])["total"] ?? 0);
if ($categoryCount === 0) {
    $productCategories = $conn->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category <> '' ORDER BY category ASC");
    while ($productCategories && $row = $productCategories->fetch_assoc()) {
        $catName = trim($row["category"]);
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $catName), '-'));
        $stmt = $conn->prepare("INSERT IGNORE INTO categories (category_name, slug, status) VALUES (?, ?, 'active')");
        $stmt->bind_param("ss", $catName, $slug);
        $stmt->execute();
    }
}

function uploadCategoryImage($file, &$error) {
    return zafiro_secure_upload($file, "../uploads/categories", "../uploads/categories", ["jpg", "jpeg", "png", "webp"], ["image/jpeg", "image/png", "image/webp"], 4 * 1024 * 1024, "category", $error);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    $categoryId = (int) ($_POST["category_id"] ?? 0);

    if ($action === "save") {
        $name = trim($_POST["category_name"] ?? "");
        $slug = zafiroCategorySlug($_POST["slug"] ?? "");
        $oldName = trim($_POST["old_category_name"] ?? "");
        $description = trim($_POST["description"] ?? "");
        $isFeatured = isset($_POST["is_featured"]) ? 1 : 0;
        $status = $_POST["status"] === "inactive" ? "inactive" : "active";

        if ($name === "" || $slug === "") {
            $message = "Category name and slug are required.";
            $messageType = "error";
        } else {
            $uploadError = "";
            $imagePath = uploadCategoryImage($_FILES["category_image"] ?? [], $uploadError);
            if ($uploadError !== "") {
                $message = $uploadError;
                $messageType = "error";
            } elseif ($categoryId > 0) {
                if ($imagePath !== "") {
                    $stmt = $conn->prepare("UPDATE categories SET category_name=?, slug=?, parent_id=NULL, description=?, category_image=?, is_featured=?, status=? WHERE id=?");
                    $stmt->bind_param("ssssisi", $name, $slug, $description, $imagePath, $isFeatured, $status, $categoryId);
                } else {
                    $stmt = $conn->prepare("UPDATE categories SET category_name=?, slug=?, parent_id=NULL, description=?, is_featured=?, status=? WHERE id=?");
                    $stmt->bind_param("sssisi", $name, $slug, $description, $isFeatured, $status, $categoryId);
                }
                $updated = $stmt->execute();
                if ($updated && $oldName !== "" && $oldName !== $name) {
                    $productStmt = $conn->prepare("UPDATE products SET category=? WHERE category=?");
                    $productStmt->bind_param("ss", $name, $oldName);
                    $productStmt->execute();
                }
                $message = $updated ? "Category updated successfully." : "Category could not be updated.";
                $messageType = $stmt->affected_rows >= 0 ? "success" : "error";
                if ($updated) adminReportLog($conn, "edit_category", "Updated category: " . $name, "category", $categoryId, $name);
            } else {
                $stmt = $conn->prepare("INSERT INTO categories (category_name, slug, parent_id, description, category_image, is_featured, status) VALUES (?, ?, NULL, ?, ?, ?, ?)");
                $stmt->bind_param("ssssis", $name, $slug, $description, $imagePath, $isFeatured, $status);
                $message = $stmt->execute() ? "Category added successfully." : "Category could not be added.";
                $messageType = $stmt->affected_rows > 0 ? "success" : "error";
                if ($stmt->affected_rows > 0) adminReportLog($conn, "add_category", "Added category: " . $name, "category", $stmt->insert_id, $name);
            }
        }
    }

    if ($action === "toggle" && $categoryId > 0) {
        $newStatus = $_POST["new_status"] === "active" ? "active" : "inactive";
        $stmt = $conn->prepare("UPDATE categories SET status=? WHERE id=?");
        $stmt->bind_param("si", $newStatus, $categoryId);
        $message = $stmt->execute() ? "Category status updated." : "Status could not be updated.";
        $messageType = $stmt->affected_rows >= 0 ? "success" : "error";
        if ($stmt->affected_rows >= 0) adminReportLog($conn, "edit_category", "Changed category status to " . $newStatus . ".", "category", $categoryId);
    }

    if ($action === "delete" && $categoryId > 0) {
        $stmt = $conn->prepare("SELECT category_name FROM categories WHERE id=? LIMIT 1");
        $stmt->bind_param("i", $categoryId);
        $stmt->execute();
        $category = $stmt->get_result()->fetch_assoc();
        $categoryName = $category["category_name"] ?? "";

        $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM products WHERE category_id = ?");
        $countStmt->bind_param("i", $categoryId);
        $countStmt->execute();
        $productCount = (int) ($countStmt->get_result()->fetch_assoc()["total"] ?? 0);

        if ($productCount > 0) {
            $message = "This category contains products.";
            $messageType = "error";
        } else {
            $deleteStmt = $conn->prepare("DELETE FROM categories WHERE id=?");
            $deleteStmt->bind_param("i", $categoryId);
            $message = $deleteStmt->execute() ? "Category deleted successfully." : "Category could not be deleted.";
            $messageType = $deleteStmt->affected_rows > 0 ? "success" : "error";
            if ($deleteStmt->affected_rows > 0) adminReportLog($conn, "delete_category", "Deleted category: " . $categoryName, "category", $categoryId, $categoryName);
        }
    }
}

$search = trim($_GET["search"] ?? "");
$status = trim($_GET["status"] ?? "");
$featured = trim($_GET["featured"] ?? "");
$sort = trim($_GET["sort"] ?? "newest");
$where = [];
$params = [];
$types = "";

if ($search !== "") {
    $where[] = "(category_name LIKE ? OR slug LIKE ?)";
    $term = "%" . $search . "%";
    $params[] = $term;
    $params[] = $term;
    $types .= "ss";
}
$where[] = "(parent_id IS NULL OR parent_id = 0)";
if ($status === "active" || $status === "inactive") {
    $where[] = "status = ?";
    $params[] = $status;
    $types .= "s";
}
if ($featured === "1") $where[] = "is_featured = 1";

$orderBy = $sort === "oldest" ? "created_at ASC, id ASC" : "created_at DESC, id DESC";
$sql = "SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count FROM categories c" . ($where ? " WHERE " . implode(" AND ", $where) : "") . " ORDER BY " . $orderBy;
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$categories = $stmt->get_result();

include("includes/admin_header.php");
include("includes/admin_sidebar.php");
?>
<main class="admin-main">
    <header class="admin-topbar admin-dark-topbar">
        <div>
            <span>Zafiro Casa Luxury Living</span>
            <h1>Manage Categories</h1>
            <p>Manage furniture categories used in the store.</p>
        </div>
        <button type="button" class="admin-btn" id="openCategoryModal">+ Add New Category</button>
    </header>

    <?php if ($message): ?>
        <div class="admin-popup <?php echo $messageType === 'success' ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <section class="admin-form-card manage-filter-card">
        <form method="GET" action="manage_categories.php" class="manage-categories-filter">
            <button type="button" class="admin-search-toggle" id="adminCategorySearchToggle" aria-label="Open category search"><i class="fas fa-search"></i></button>
            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
            <select name="status">
                <option value="">All Status</option>
                <option value="active" <?php echo $status === "active" ? "selected" : ""; ?>>Active</option>
                <option value="inactive" <?php echo $status === "inactive" ? "selected" : ""; ?>>Inactive</option>
            </select>
            <select name="featured">
                <option value="">Featured</option>
                <option value="1" <?php echo $featured === "1" ? "selected" : ""; ?>>Featured Only</option>
            </select>
            <select name="sort">
                <option value="newest" <?php echo $sort === "newest" ? "selected" : ""; ?>>Newest First</option>
                <option value="oldest" <?php echo $sort === "oldest" ? "selected" : ""; ?>>Oldest First</option>
            </select>
            <button type="submit" class="admin-btn">Apply</button>
            <a class="admin-btn admin-btn-light" href="manage_categories.php">Reset</a>
        </form>
        <div class="admin-search-popup" id="adminCategorySearchPopup">
            <form method="GET" action="manage_categories.php" class="admin-search-popup-card">
                <button type="button" class="admin-search-close" id="adminCategorySearchClose" aria-label="Close search">&times;</button>
                <h3>Search Categories</h3>
                <p>Search by category name or slug.</p>
                <input type="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Type category keyword...">
                <button type="submit" class="admin-btn">Search</button>
            </form>
        </div>
    </section>

    <section class="category-card-list">
        <?php if ($categories && $categories->num_rows > 0): ?>
            <?php while ($categoryRow = $categories->fetch_assoc()): ?>
                <?php $nextStatus = strtolower($categoryRow["status"]) === "active" ? "inactive" : "active"; ?>
                <article class="category-manage-card">
                    <div class="category-image-box">
                        <?php if (!empty($categoryRow["category_image"])): ?>
                            <img src="<?php echo htmlspecialchars($categoryRow["category_image"]); ?>" alt="<?php echo htmlspecialchars($categoryRow["category_name"]); ?>">
                        <?php else: ?>
                            <span><?php echo htmlspecialchars(substr($categoryRow["category_name"], 0, 1)); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="category-card-main">
                        <div class="manage-product-title-row">
                            <div>
                                <h2><?php echo htmlspecialchars($categoryRow["category_name"]); ?></h2>
                                <p><?php echo htmlspecialchars($categoryRow["slug"]); ?></p>
                            </div>
                            <span class="status-pill"><?php echo htmlspecialchars(ucfirst($categoryRow["status"])); ?></span>
                        </div>
                        <p><?php echo htmlspecialchars($categoryRow["description"] ?: "No description added."); ?></p>
                        <div class="manage-product-meta">
                            <span><strong>Products</strong><?php echo (int) $categoryRow["product_count"]; ?></span>
                            <span><strong>Featured</strong><?php echo !empty($categoryRow["is_featured"]) ? "Yes" : "No"; ?></span>
                            <span><strong>Created</strong><?php echo htmlspecialchars($categoryRow["created_at"]); ?></span>
                        </div>
                    </div>
                    <div class="manage-card-actions">
                        <a class="admin-action-link" href="../frontend/product-list.php?category=<?php echo urlencode($categoryRow["slug"]); ?>" target="_blank">View</a>
                        <button type="button" class="admin-action-link edit edit-category-btn" data-id="<?php echo (int) $categoryRow["id"]; ?>" data-name="<?php echo htmlspecialchars($categoryRow["category_name"]); ?>" data-slug="<?php echo htmlspecialchars($categoryRow["slug"]); ?>" data-description="<?php echo htmlspecialchars((string) ($categoryRow["description"] ?? "")); ?>" data-featured="<?php echo (int) $categoryRow["is_featured"]; ?>" data-status="<?php echo htmlspecialchars($categoryRow["status"]); ?>">Edit</button>
                        <form method="POST" action="manage_categories.php" class="inline-admin-form">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="category_id" value="<?php echo (int) $categoryRow["id"]; ?>">
                            <input type="hidden" name="new_status" value="<?php echo $nextStatus; ?>">
                            <button type="submit" class="admin-action-link"><?php echo ucfirst($nextStatus); ?></button>
                        </form>
                        <form method="POST" action="manage_categories.php" class="inline-admin-form delete-category-form" data-products="<?php echo (int) $categoryRow["product_count"]; ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="category_id" value="<?php echo (int) $categoryRow["id"]; ?>">
                            <button type="submit" class="admin-action-link danger">Delete</button>
                        </form>
                    </div>
                </article>
            <?php endwhile; ?>
        <?php else: ?>
            <section class="admin-panel-card empty-admin-state">
                <h2>No categories found.</h2>
                <button type="button" class="admin-btn" id="openCategoryModalEmpty">+ Add New Category</button>
            </section>
        <?php endif; ?>
    </section>

    <div class="admin-modal" id="categoryModal">
        <form method="POST" action="manage_categories.php" enctype="multipart/form-data" class="admin-modal-card">
            <button type="button" class="admin-search-close" id="closeCategoryModal">&times;</button>
            <h2 id="categoryModalTitle">Add Category</h2>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="category_id" id="categoryIdInput">
            <input type="hidden" name="old_category_name" id="oldCategoryNameInput">
            <div class="admin-form-grid">
                <label>Category Name<input type="text" name="category_name" id="categoryNameInput" required></label>
                <label>Category Slug<input type="text" name="slug" id="categorySlugInput" required></label>
                <label>Short Description<textarea name="description" id="categoryDescriptionInput"></textarea></label>
                <label>Category Image<input type="file" name="category_image" accept=".jpg,.jpeg,.png,.webp"></label>
                <label>Status
                    <select name="status" id="categoryStatusInput">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </label>
            </div>
            <div class="admin-toggle-row">
                <label><input type="checkbox" name="is_featured" id="categoryFeaturedInput"> Featured Category</label>
            </div>
            <div class="modal-actions">
                <button type="submit" class="admin-btn">Save Category</button>
                <button type="button" class="admin-btn admin-btn-light" id="cancelCategoryModal">Cancel</button>
            </div>
        </form>
    </div>
<?php include("includes/admin_footer.php"); ?>
