<?php
include("auth.php");
include("../backend/config/db.php");
include_once("../backend/includes/product_images.php");
include_once("../backend/includes/admin_reports.php");

$message = "";
$messageType = "";

function adminColumnExists($conn, $table, $column) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int) ($row["total"] ?? 0) > 0;
}

function ensureProductColumns($conn) {
    $columns = [
        "slug" => "VARCHAR(160) NULL",
        "category_id" => "INT NULL",
        "subcategory_id" => "INT NULL",
        "brand" => "VARCHAR(120) NULL",
        "sku" => "VARCHAR(80) NULL",
        "original_price" => "DECIMAL(10,2) DEFAULT 0",
        "discount_price" => "DECIMAL(10,2) DEFAULT 0",
        "stock_quantity" => "INT DEFAULT 0",
        "short_description" => "TEXT NULL",
        "full_description" => "TEXT NULL",
        "specifications" => "TEXT NULL",
        "material" => "VARCHAR(120) NULL",
        "color" => "VARCHAR(80) NULL",
        "dimensions" => "VARCHAR(120) NULL",
        "weight" => "VARCHAR(80) NULL",
        "seating_capacity" => "VARCHAR(80) NULL",
        "room_type" => "VARCHAR(120) NULL",
        "assembly_required" => "VARCHAR(20) DEFAULT 'No'",
        "featured" => "TINYINT(1) DEFAULT 0",
        "trending" => "TINYINT(1) DEFAULT 0",
        "in_stock" => "TINYINT(1) DEFAULT 1",
        "status" => "VARCHAR(20) DEFAULT 'active'",
        "gallery_images" => "TEXT NULL",
        "image_1" => "VARCHAR(255) NULL",
        "image_2" => "VARCHAR(255) NULL",
        "image_3" => "VARCHAR(255) NULL",
        "image_4" => "VARCHAR(255) NULL"
    ];

    foreach ($columns as $column => $definition) {
        if (!adminColumnExists($conn, "products", $column)) {
            $conn->query("ALTER TABLE products ADD COLUMN `$column` $definition");
        }
    }
}

function ensureAdminCategoriesTable($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_name VARCHAR(120) NOT NULL UNIQUE,
        slug VARCHAR(160) NOT NULL UNIQUE,
        description TEXT NULL,
        category_image VARCHAR(255) NULL,
        is_featured TINYINT(1) DEFAULT 0,
        status VARCHAR(20) DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $countResult = $conn->query("SELECT COUNT(*) AS total FROM categories");
    $categoryCount = (int) (($countResult ? $countResult->fetch_assoc() : [])["total"] ?? 0);
    if ($categoryCount > 0) return;

    $defaults = ["Sofas", "Living", "Bedroom", "Mattress", "Dining", "Storage", "Study & Office", "Outdoor", "Decor & Furnishing", "Modular Kitchen", "Tables", "Chairs"];
    $productCategories = $conn->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category <> '' ORDER BY category ASC");
    while ($productCategories && $row = $productCategories->fetch_assoc()) {
        $defaults[] = trim($row["category"]);
    }

    foreach (array_unique(array_filter($defaults)) as $catName) {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $catName), '-'));
        $stmt = $conn->prepare("INSERT IGNORE INTO categories (category_name, slug, status) VALUES (?, ?, 'active')");
        $stmt->bind_param("ss", $catName, $slug);
        $stmt->execute();
    }
}

function getAdminCategoryOptions($conn) {
    ensureAdminCategoriesTable($conn);
    $options = [];
    $result = $conn->query("SELECT category_name FROM categories WHERE status = 'active' AND (parent_id IS NULL OR parent_id = 0) UNION SELECT subcategory_name AS category_name FROM subcategories WHERE status = 'active' ORDER BY category_name ASC");
    while ($result && $row = $result->fetch_assoc()) {
        $options[] = $row["category_name"];
    }
    return $options;
}

function uploadProductImage($file, $uploadDir, &$error) {
    return zafiro_secure_upload($file, $uploadDir, "../uploads/products", ["jpg", "jpeg", "png", "webp"], ["image/jpeg", "image/png", "image/webp"], 4 * 1024 * 1024, "product", $error);
}

ensureProductColumns($conn);
ensureProductImagesSchema($conn);
$categoryOptions = getAdminCategoryOptions($conn);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $slug = trim($_POST["slug"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $brand = trim($_POST["brand"] ?? "");
    $sku = trim($_POST["sku"] ?? "");
    $originalPrice = (float) ($_POST["original_price"] ?? 0);
    $discountPrice = (float) ($_POST["discount_price"] ?? 0);
    $stockQuantity = (int) ($_POST["stock_quantity"] ?? 0);
    $shortDescription = trim($_POST["short_description"] ?? "");
    $fullDescription = trim($_POST["full_description"] ?? "");
    $specifications = trim($_POST["specifications"] ?? "");
    $material = trim($_POST["material"] ?? "");
    $color = trim($_POST["color"] ?? "");
    $dimensions = trim($_POST["dimensions"] ?? "");
    $weight = trim($_POST["weight"] ?? "");
    $seatingCapacity = trim($_POST["seating_capacity"] ?? "");
    $roomType = trim($_POST["room_type"] ?? "");
    $assemblyRequired = trim($_POST["assembly_required"] ?? "No");
    $featured = isset($_POST["featured"]) ? 1 : 0;
    $trending = isset($_POST["trending"]) ? 1 : 0;
    $inStock = isset($_POST["in_stock"]) ? 1 : 0;
    $status = trim($_POST["status"] ?? "active");
    $price = $discountPrice > 0 ? $discountPrice : $originalPrice;
    $description = $shortDescription !== "" ? $shortDescription : $fullDescription;

    if ($name === "" || $category === "" || $originalPrice <= 0 || $shortDescription === "") {
        $message = "Missing required fields. Product name, category, original price, and short description are required.";
        $messageType = "error";
    } else {
        $uploadError = "";
        $productImages = [];
        for ($i = 1; $i <= 4; $i++) {
            $uploaded = uploadProductImage($_FILES["product_image_$i"] ?? [], "../uploads/products", $uploadError);
            if ($uploaded !== "") $productImages[$i - 1] = $uploaded;
        }
        $mainImage = $productImages[0] ?? (array_values(array_filter($productImages))[0] ?? "");
        $image1 = $productImages[0] ?? null;
        $image2 = $productImages[1] ?? null;
        $image3 = $productImages[2] ?? null;
        $image4 = $productImages[3] ?? null;
        $galleryImages = implode(",", array_values(array_filter([$image2, $image3, $image4])));

        if ($uploadError !== "") {
            $message = $uploadError;
            $messageType = "error";
        } else {
            $stmt = $conn->prepare("INSERT INTO products (name, price, category, image, image_1, image_2, image_3, image_4, description, slug, brand, sku, original_price, discount_price, stock_quantity, short_description, full_description, specifications, material, color, dimensions, weight, seating_capacity, room_type, assembly_required, featured, trending, in_stock, status, gallery_images) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sdssssssssssddissssssssssiiiss", $name, $price, $category, $mainImage, $image1, $image2, $image3, $image4, $description, $slug, $brand, $sku, $originalPrice, $discountPrice, $stockQuantity, $shortDescription, $fullDescription, $specifications, $material, $color, $dimensions, $weight, $seatingCapacity, $roomType, $assemblyRequired, $featured, $trending, $inStock, $status, $galleryImages);

            if ($stmt->execute()) {
                $newProductId = (int) $stmt->insert_id;
                [$categoryId, $subcategoryId] = resolveProductCategoryIdsFromText($conn, $category);
                $categoryUpdate = $conn->prepare("UPDATE products SET category_id=?, subcategory_id=? WHERE id=?");
                $categoryUpdate->bind_param("iii", $categoryId, $subcategoryId, $newProductId);
                $categoryUpdate->execute();
                syncProductImageColumnsToTable($conn, $newProductId, $productImages);
                adminReportLog($conn, "add_product", "Added product: " . $name, "product", $newProductId, $name);
                $message = "Product added successfully.";
                $messageType = "success";
            } else {
                $message = "Product could not be added. Please check the form values.";
                $messageType = "error";
            }
        }
    }
}

include("includes/admin_header.php");
include("includes/admin_sidebar.php");
?>
<main class="admin-main">
    <header class="admin-topbar admin-dark-topbar">
        <div>
            <span>Zafiro Casa Luxury Living</span>
            <h1>Add New Product</h1>
            <p>Add furniture products to the Zafiro Casa store.</p>
        </div>
    </header>

    <?php if ($message): ?>
        <div class="admin-popup <?php echo $messageType === 'success' ? 'success' : 'error'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form class="admin-product-layout" method="POST" action="add_product.php" enctype="multipart/form-data">
        <section class="admin-product-form">
            <div class="admin-form-card">
                <h2>Basic Details</h2>
                <div class="admin-form-grid">
                    <label>Product Name<input type="text" name="name" id="productNameInput" required></label>
                    <label>Product Slug<input type="text" name="slug" id="productSlugInput"></label>
                    <label>Category
                        <select name="category" id="productCategoryInput" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categoryOptions as $categoryOption): ?>
                                <option><?php echo htmlspecialchars($categoryOption); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Brand Name<input type="text" name="brand"></label>
                    <label>SKU Code<input type="text" name="sku"></label>
                </div>
            </div>

            <div class="admin-form-card">
                <h2>Pricing</h2>
                <div class="admin-form-grid">
                    <label>Original Price<input type="number" step="0.01" name="original_price" id="productOriginalPriceInput" required></label>
                    <label>Discount Price<input type="number" step="0.01" name="discount_price" id="productDiscountPriceInput"></label>
                    <label>Stock Quantity<input type="number" name="stock_quantity" value="1"></label>
                </div>
            </div>

            <div class="admin-form-card">
                <h2>Product Details</h2>
                <div class="admin-form-grid">
                    <label>Short Description<textarea name="short_description" id="productShortInput" required></textarea></label>
                    <label>Full Description<textarea name="full_description"></textarea></label>
                    <label>Product Specifications<textarea name="specifications"></textarea></label>
                    <label>Material<input type="text" name="material"></label>
                    <label>Color<input type="text" name="color"></label>
                    <label>Dimensions<input type="text" name="dimensions"></label>
                    <label>Weight<input type="text" name="weight"></label>
                </div>
            </div>

            <div class="admin-form-card">
                <h2>Furniture Details</h2>
                <div class="admin-form-grid">
                    <label>Seating Capacity<input type="text" name="seating_capacity"></label>
                    <label>Room Type<input type="text" name="room_type"></label>
                    <label>Assembly Required
                        <select name="assembly_required">
                            <option>No</option>
                            <option>Yes</option>
                        </select>
                    </label>
                    <label>Status
                        <select name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </label>
                </div>
                <div class="admin-toggle-row">
                    <label><input type="checkbox" name="featured"> Featured Product</label>
                    <label><input type="checkbox" name="trending"> Trending Product</label>
                    <label><input type="checkbox" name="in_stock" checked> In Stock</label>
                </div>
            </div>

            <div class="admin-form-card">
                <h2>Product Images</h2>
                <div class="admin-upload-grid">
                    <label class="admin-upload-box">
                        <span>Product Image 1</span>
                        <input type="file" name="product_image_1" id="mainProductImageInput" accept=".jpg,.jpeg,.png,.webp">
                    </label>
                    <label class="admin-upload-box">
                        <span>Product Image 2</span>
                        <input type="file" name="product_image_2" class="galleryProductImageInput" accept=".jpg,.jpeg,.png,.webp">
                    </label>
                    <label class="admin-upload-box">
                        <span>Product Image 3</span>
                        <input type="file" name="product_image_3" class="galleryProductImageInput" accept=".jpg,.jpeg,.png,.webp">
                    </label>
                    <label class="admin-upload-box">
                        <span>Product Image 4</span>
                        <input type="file" name="product_image_4" class="galleryProductImageInput" accept=".jpg,.jpeg,.png,.webp">
                    </label>
                </div>
                <div id="adminImagePreviewList" class="admin-image-preview-list"></div>
            </div>

            <button type="submit" class="admin-btn admin-submit-btn">Add Product</button>
        </section>

        <aside class="admin-product-preview">
            <div class="admin-preview-card">
                <img id="previewProductImage" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='640' height='420' viewBox='0 0 640 420'%3E%3Crect width='640' height='420' fill='%23FAF7F0'/%3E%3Cpath d='M155 255h330v45H155zM190 180h260a35 35 0 0 1 35 35v40H155v-40a35 35 0 0 1 35-35z' fill='%23C8A96B' opacity='.55'/%3E%3Ccircle cx='230' cy='300' r='16' fill='%23111827' opacity='.35'/%3E%3Ccircle cx='410' cy='300' r='16' fill='%23111827' opacity='.35'/%3E%3Ctext x='320' y='135' text-anchor='middle' font-family='Arial' font-size='28' font-weight='700' fill='%236B7280'%3EZafiro Casa%3C/text%3E%3C/svg%3E" alt="Product preview">
                <div>
                    <span id="previewCategory">Category</span>
                    <h2 id="previewProductName">Product Name</h2>
                    <p id="previewDescription">Short description will appear here.</p>
                    <div class="preview-price-row">
                        <strong id="previewDiscountPrice">₹0</strong>
                        <del id="previewOriginalPrice">₹0</del>
                    </div>
                </div>
            </div>
        </aside>
    </form>
<?php include("includes/admin_footer.php"); ?>
