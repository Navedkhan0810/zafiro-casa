<?php
include("auth.php");
include("../backend/config/db.php");

$message = $_GET["message"] ?? "";
$messageType = $_GET["type"] ?? "";

$conn->query("ALTER TABLE categories ADD COLUMN IF NOT EXISTS parent_id INT NULL AFTER slug");

function uploadSubcategoryImage($file, &$error) {
    return zafiro_secure_upload($file, "../uploads/categories", "../uploads/categories", ["jpg", "jpeg", "png", "webp"], ["image/jpeg", "image/png", "image/webp"], 4 * 1024 * 1024, "subcategory", $error);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    $categoryId = (int) ($_POST["category_id"] ?? 0);

    if ($action === "save") {
        $name = trim($_POST["category_name"] ?? "");
        $slug = trim($_POST["slug"] ?? "");
        $oldName = trim($_POST["old_category_name"] ?? "");
        $parentId = (int) ($_POST["parent_id"] ?? 0);
        $description = trim($_POST["description"] ?? "");
        $status = $_POST["status"] === "inactive" ? "inactive" : "active";

        if ($name === "" || $slug === "" || $parentId <= 0) {
            $message = "Subcategory name, slug and parent category are required.";
            $messageType = "error";
        } else {
            $uploadError = "";
            $imagePath = uploadSubcategoryImage($_FILES["category_image"] ?? [], $uploadError);
            if ($uploadError !== "") {
                $message = $uploadError;
                $messageType = "error";
            } elseif ($categoryId > 0) {
                if ($imagePath !== "") {
                    $stmt = $conn->prepare("UPDATE categories SET category_name=?, slug=?, parent_id=?, description=?, category_image=?, status=? WHERE id=?");
                    $stmt->bind_param("ssisssi", $name, $slug, $parentId, $description, $imagePath, $status, $categoryId);
                } else {
                    $stmt = $conn->prepare("UPDATE categories SET category_name=?, slug=?, parent_id=?, description=?, status=? WHERE id=?");
                    $stmt->bind_param("ssissi", $name, $slug, $parentId, $description, $status, $categoryId);
                }
                $updated = $stmt->execute();
                if ($updated && $oldName !== "" && $oldName !== $name) {
                    $productStmt = $conn->prepare("UPDATE products SET category=? WHERE category=?");
                    $productStmt->bind_param("ss", $name, $oldName);
                    $productStmt->execute();
                }
                if ($updated) {
                    header("Location: manage_subcategories.php?type=success&message=" . urlencode("Subcategory updated successfully."));
                    exit;
                }
                $message = "Subcategory could not be updated.";
                $messageType = "error";
            } else {
                $stmt = $conn->prepare("INSERT INTO categories (category_name, slug, parent_id, description, category_image, is_featured, status) VALUES (?, ?, ?, ?, ?, 0, ?)");
                $stmt->bind_param("ssisss", $name, $slug, $parentId, $description, $imagePath, $status);
                if ($stmt->execute()) {
                    header("Location: manage_subcategories.php?type=success&message=" . urlencode("Subcategory added successfully."));
                    exit;
                }
                $message = "Subcategory could not be added.";
                $messageType = "error";
            }
        }
    }

    if ($action === "toggle" && $categoryId > 0) {
        $newStatus = $_POST["new_status"] === "active" ? "active" : "inactive";
        $stmt = $conn->prepare("UPDATE categories SET status=? WHERE id=? AND parent_id IS NOT NULL AND parent_id <> 0");
        $stmt->bind_param("si", $newStatus, $categoryId);
        $message = $stmt->execute() ? "Subcategory status updated." : "Status could not be updated.";
        $messageType = $stmt->affected_rows >= 0 ? "success" : "error";
    }

    if ($action === "delete" && $categoryId > 0) {
        $stmt = $conn->prepare("SELECT category_name FROM categories WHERE id=? AND parent_id IS NOT NULL AND parent_id <> 0 LIMIT 1");
        $stmt->bind_param("i", $categoryId);
        $stmt->execute();
        $category = $stmt->get_result()->fetch_assoc();
        $categoryName = $category["category_name"] ?? "";

        $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM products WHERE category = ?");
        $countStmt->bind_param("s", $categoryName);
        $countStmt->execute();
        $productCount = (int) ($countStmt->get_result()->fetch_assoc()["total"] ?? 0);

        if ($productCount > 0) {
            $hideStmt = $conn->prepare("UPDATE categories SET status='inactive' WHERE id=?");
            $hideStmt->bind_param("i", $categoryId);
            $message = $hideStmt->execute() ? "Subcategory hidden because it contains products." : "Subcategory could not be hidden.";
            $messageType = $hideStmt->affected_rows >= 0 ? "success" : "error";
        } else {
            $deleteStmt = $conn->prepare("DELETE FROM categories WHERE id=? AND parent_id IS NOT NULL AND parent_id <> 0");
            $deleteStmt->bind_param("i", $categoryId);
            $message = $deleteStmt->execute() ? "Subcategory deleted successfully." : "Subcategory could not be deleted.";
            $messageType = $deleteStmt->affected_rows > 0 ? "success" : "error";
        }
    }
}

$search = trim($_GET["search"] ?? "");
$parent = (int) ($_GET["parent_id"] ?? 0);
$status = trim($_GET["status"] ?? "");
$where = ["c.parent_id IS NOT NULL", "c.parent_id <> 0"];
$params = [];
$types = "";

if ($search !== "") {
    $where[] = "(c.category_name LIKE ? OR c.slug LIKE ?)";
    $term = "%" . $search . "%";
    $params[] = $term;
    $params[] = $term;
    $types .= "ss";
}
if ($parent > 0) {
    $where[] = "c.parent_id = ?";
    $params[] = $parent;
    $types .= "i";
}
if ($status === "active" || $status === "inactive") {
    $where[] = "c.status = ?";
    $params[] = $status;
    $types .= "s";
}

$sql = "SELECT c.*, p.category_name AS parent_name, (SELECT COUNT(*) FROM products pr WHERE pr.category = c.category_name) AS product_count FROM categories c INNER JOIN categories p ON p.id = c.parent_id WHERE " . implode(" AND ", $where) . " ORDER BY p.category_name ASC, c.category_name ASC";
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$subcategories = $stmt->get_result();
$parents = $conn->query("SELECT id, category_name FROM categories WHERE parent_id IS NULL OR parent_id = 0 ORDER BY category_name ASC");
$parentOptions = $conn->query("SELECT id, category_name FROM categories WHERE parent_id IS NULL OR parent_id = 0 ORDER BY category_name ASC");

include("includes/admin_header.php");
include("includes/admin_sidebar.php");
?>
<main class="admin-main">
    <header class="admin-topbar admin-dark-topbar">
        <div>
            <span>Zafiro Casa Luxury Living</span>
            <h1>Manage Subcategories</h1>
            <p>Manage category cards shown under main navigation sections.</p>
        </div>
        <button type="button" class="admin-btn" id="openCategoryModal">+ Add Subcategory</button>
    </header>

    <?php if ($message): ?>
        <div class="admin-popup <?php echo $messageType === 'success' ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <section class="admin-form-card manage-filter-card">
        <form method="GET" action="manage_subcategories.php" class="manage-categories-filter">
            <button type="button" class="admin-search-toggle" id="adminCategorySearchToggle" aria-label="Open subcategory search"><i class="fas fa-search"></i></button>
            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
            <select name="parent_id">
                <option value="0">All Parents</option>
                <?php while ($parents && $parentRow = $parents->fetch_assoc()): ?>
                    <option value="<?php echo (int) $parentRow["id"]; ?>" <?php echo $parent === (int) $parentRow["id"] ? "selected" : ""; ?>><?php echo htmlspecialchars($parentRow["category_name"]); ?></option>
                <?php endwhile; ?>
            </select>
            <select name="status">
                <option value="">All Status</option>
                <option value="active" <?php echo $status === "active" ? "selected" : ""; ?>>Active</option>
                <option value="inactive" <?php echo $status === "inactive" ? "selected" : ""; ?>>Inactive</option>
            </select>
            <button type="submit" class="admin-btn">Apply</button>
            <a class="admin-btn admin-btn-light" href="manage_subcategories.php">Reset</a>
        </form>
        <div class="admin-search-popup" id="adminCategorySearchPopup">
            <form method="GET" action="manage_subcategories.php" class="admin-search-popup-card">
                <button type="button" class="admin-search-close" id="adminCategorySearchClose" aria-label="Close search">&times;</button>
                <h3>Search Subcategories</h3>
                <p>Search by subcategory name or slug.</p>
                <input type="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Type subcategory keyword...">
                <button type="submit" class="admin-btn">Search</button>
            </form>
        </div>
    </section>

    <section class="category-card-list">
        <?php if ($subcategories && $subcategories->num_rows > 0): ?>
            <?php while ($categoryRow = $subcategories->fetch_assoc()): ?>
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
                            <span><strong>Parent</strong><?php echo htmlspecialchars($categoryRow["parent_name"]); ?></span>
                            <span><strong>Products</strong><?php echo (int) $categoryRow["product_count"]; ?></span>
                            <span><strong>Created</strong><?php echo htmlspecialchars($categoryRow["created_at"]); ?></span>
                        </div>
                    </div>
                    <div class="manage-card-actions">
                        <a class="admin-action-link" href="../frontend/product-list.php?category=<?php echo urlencode($categoryRow["slug"]); ?>" target="_blank">View</a>
                        <button type="button" class="admin-action-link edit edit-category-btn" data-id="<?php echo (int) $categoryRow["id"]; ?>" data-name="<?php echo htmlspecialchars($categoryRow["category_name"]); ?>" data-slug="<?php echo htmlspecialchars($categoryRow["slug"]); ?>" data-parent="<?php echo (int) $categoryRow["parent_id"]; ?>" data-description="<?php echo htmlspecialchars((string) ($categoryRow["description"] ?? "")); ?>" data-image="<?php echo htmlspecialchars((string) ($categoryRow["category_image"] ?? "")); ?>" data-status="<?php echo htmlspecialchars($categoryRow["status"]); ?>">Edit</button>
                        <form method="POST" action="manage_subcategories.php" class="inline-admin-form">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="category_id" value="<?php echo (int) $categoryRow["id"]; ?>">
                            <input type="hidden" name="new_status" value="<?php echo $nextStatus; ?>">
                            <button type="submit" class="admin-action-link"><?php echo ucfirst($nextStatus); ?></button>
                        </form>
                        <form method="POST" action="manage_subcategories.php" class="inline-admin-form delete-category-form" data-products="0">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="category_id" value="<?php echo (int) $categoryRow["id"]; ?>">
                            <button type="submit" class="admin-action-link danger">Delete/Hide</button>
                        </form>
                    </div>
                </article>
            <?php endwhile; ?>
        <?php else: ?>
            <section class="admin-panel-card empty-admin-state">
                <h2>No subcategories found.</h2>
                <button type="button" class="admin-btn" id="openCategoryModalEmpty">+ Add Subcategory</button>
            </section>
        <?php endif; ?>
    </section>

    <div class="admin-modal" id="categoryModal">
        <form method="POST" action="manage_subcategories.php" enctype="multipart/form-data" class="admin-modal-card">
            <button type="button" class="admin-search-close" id="closeCategoryModal">&times;</button>
            <h2 id="categoryModalTitle">Add Subcategory</h2>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="category_id" id="categoryIdInput">
            <input type="hidden" name="old_category_name" id="oldCategoryNameInput">
            <div class="admin-form-grid">
                <label>Subcategory Name<input type="text" name="category_name" id="categoryNameInput" required></label>
                <label>Subcategory Slug<input type="text" name="slug" id="categorySlugInput" required></label>
                <label>Parent Category
                    <select name="parent_id" id="categoryParentInput" required>
                        <option value="">Select Parent</option>
                        <?php while ($parentOptions && $parentRow = $parentOptions->fetch_assoc()): ?>
                            <option value="<?php echo (int) $parentRow["id"]; ?>"><?php echo htmlspecialchars($parentRow["category_name"]); ?></option>
                        <?php endwhile; ?>
                    </select>
                </label>
                <label>Short Description<textarea name="description" id="categoryDescriptionInput"></textarea></label>
                <label>Subcategory Image
                    <img src="" alt="Current subcategory image" id="categoryCurrentImagePreview" class="admin-current-image is-hidden">
                    <input type="file" name="category_image" accept=".jpg,.jpeg,.png,.webp">
                </label>
                <label>Status
                    <select name="status" id="categoryStatusInput">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </label>
            </div>
            <div class="modal-actions">
                <button type="submit" class="admin-btn">Save Subcategory</button>
                <button type="button" class="admin-btn admin-btn-light" id="cancelCategoryModal">Cancel</button>
            </div>
        </form>
    </div>
<?php include("includes/admin_footer.php"); ?>
