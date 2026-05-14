<?php
include("auth.php");
include("../backend/config/db.php");
include_once("../backend/includes/product_images.php");

$productId = (int) ($_GET["id"] ?? $_POST["product_id"] ?? 0);
$message = "";
$messageType = "";

function editProductColumnExists($conn, $column) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'products' AND column_name = ?");
    $stmt->bind_param("s", $column);
    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()["total"] ?? 0) > 0;
}

function ensureEditProductColumns($conn) {
    $columns = [
        "specifications" => "TEXT NULL",
        "material" => "VARCHAR(120) NULL",
        "color" => "VARCHAR(80) NULL",
        "dimensions" => "VARCHAR(120) NULL",
        "weight" => "VARCHAR(80) NULL",
        "seating_capacity" => "VARCHAR(80) NULL",
        "room_type" => "VARCHAR(120) NULL",
        "assembly_required" => "VARCHAR(20) DEFAULT 'No'",
        "gallery_images" => "TEXT NULL",
        "image_1" => "VARCHAR(255) NULL",
        "image_2" => "VARCHAR(255) NULL",
        "image_3" => "VARCHAR(255) NULL",
        "image_4" => "VARCHAR(255) NULL"
    ];

    foreach ($columns as $column => $definition) {
        if (!editProductColumnExists($conn, $column)) {
            $conn->query("ALTER TABLE products ADD COLUMN `$column` $definition");
        }
    }
}

function uploadEditProductImage($file, $uploadDir, &$error) {
    return zafiro_secure_upload($file, $uploadDir, "../uploads/products", ["jpg", "jpeg", "png", "webp"], ["image/jpeg", "image/png", "image/webp"], 4 * 1024 * 1024, "product", $error);
}

ensureEditProductColumns($conn);
ensureProductImagesSchema($conn);

if ($_SERVER["REQUEST_METHOD"] === "POST" && $productId > 0) {
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
    $status = trim($_POST["status"] ?? "active");
    $featured = isset($_POST["featured"]) ? 1 : 0;
    $trending = isset($_POST["trending"]) ? 1 : 0;
    $inStock = isset($_POST["in_stock"]) ? 1 : 0;
    $price = $discountPrice > 0 ? $discountPrice : $originalPrice;
    $description = $shortDescription !== "" ? $shortDescription : $fullDescription;

    if ($name === "" || $category === "" || $originalPrice <= 0) {
        $message = "Missing required fields.";
        $messageType = "error";
    } else {
        $uploadError = "";
        $currentStmt = $conn->prepare("SELECT image, image_1, image_2, image_3, image_4 FROM products WHERE id = ? LIMIT 1");
        $currentStmt->bind_param("i", $productId);
        $currentStmt->execute();
        $currentImages = $currentStmt->get_result()->fetch_assoc() ?: [];
        $productImages = [
            $currentImages["image_1"] ?: ($currentImages["image"] ?? ""),
            $currentImages["image_2"] ?? "",
            $currentImages["image_3"] ?? "",
            $currentImages["image_4"] ?? ""
        ];

        for ($i = 1; $i <= 4; $i++) {
            $uploaded = uploadEditProductImage($_FILES["image$i"] ?? [], "../uploads/products", $uploadError);
            if ($uploaded !== "") $productImages[$i - 1] = $uploaded;
            if (!empty($_POST["remove_product_image_$i"])) $productImages[$i - 1] = "";
        }

        if ($uploadError !== "") {
            $message = $uploadError;
            $messageType = "error";
        } else {
            $image1 = $productImages[0] ?: null;
            $image2 = $productImages[1] ?: null;
            $image3 = $productImages[2] ?: null;
            $image4 = $productImages[3] ?: null;
            $mainImage = $image1 ?: "";
            $galleryImages = implode(",", array_values(array_filter([$image2, $image3, $image4])));
            $stmt = $conn->prepare("UPDATE products SET name=?, price=?, category=?, image=?, image_1=?, image_2=?, image_3=?, image_4=?, description=?, slug=?, brand=?, sku=?, original_price=?, discount_price=?, stock_quantity=?, short_description=?, full_description=?, specifications=?, material=?, color=?, dimensions=?, weight=?, seating_capacity=?, room_type=?, assembly_required=?, featured=?, trending=?, in_stock=?, status=?, gallery_images=? WHERE id=?");
            $stmt->bind_param("sdssssssssssddissssssssssiiissi", $name, $price, $category, $mainImage, $image1, $image2, $image3, $image4, $description, $slug, $brand, $sku, $originalPrice, $discountPrice, $stockQuantity, $shortDescription, $fullDescription, $specifications, $material, $color, $dimensions, $weight, $seatingCapacity, $roomType, $assemblyRequired, $featured, $trending, $inStock, $status, $galleryImages, $productId);

            if ($stmt->execute()) {
                syncProductImageColumnsToTable($conn, $productId, $productImages);
                $message = "Product updated successfully.";
                $messageType = "success";
            } else {
                $message = "Product could not be updated.";
                $messageType = "error";
            }
        }
    }
}

$stmt = $conn->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $productId);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$productImageValues = $product ? [
    $product["image_1"] ?: ($product["image"] ?? ""),
    $product["image_2"] ?? "",
    $product["image_3"] ?? "",
    $product["image_4"] ?? ""
] : [];
$productImages = $product ? getProductImageRows($conn, $productId) : [];
if ($product && !$productImages) {
    if (!empty($product["image"])) {
        addProductImageRow($conn, $productId, $product["image"], 1, 0);
    }
    if (!empty($product["gallery_images"])) {
        foreach (explode(",", $product["gallery_images"]) as $index => $legacyImage) {
            $legacyImage = trim($legacyImage);
            if ($legacyImage !== "") addProductImageRow($conn, $productId, $legacyImage, 0, $index + 1);
        }
    }
    $productImages = getProductImageRows($conn, $productId);
}

include("includes/admin_header.php");
include("includes/admin_sidebar.php");
?>
<main class="admin-main">
    <header class="admin-topbar admin-dark-topbar">
        <div>
            <span>Zafiro Casa Luxury Living</span>
            <h1>Edit Product</h1>
            <p>Update furniture product details.</p>
        </div>
        <a class="admin-btn" href="manage_products.php">Back to Products</a>
    </header>

    <?php if ($message): ?>
        <div class="admin-popup <?php echo $messageType === 'success' ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if ($product): ?>
        <form class="admin-product-layout" method="POST" action="edit_product.php" enctype="multipart/form-data">
            <input type="hidden" name="product_id" value="<?php echo (int) $product["id"]; ?>">
            <section class="admin-product-form">
                <div class="admin-form-card">
                    <h2>Product Details</h2>
                    <div class="admin-form-grid">
                        <label>Product Name<input type="text" name="name" id="productNameInput" value="<?php echo htmlspecialchars($product["name"] ?? ""); ?>" required></label>
                        <label>Product Slug<input type="text" name="slug" id="productSlugInput" value="<?php echo htmlspecialchars($product["slug"] ?? ""); ?>"></label>
                        <label>Category<input type="text" name="category" id="productCategoryInput" value="<?php echo htmlspecialchars($product["category"] ?? ""); ?>" required></label>
                        <label>Brand Name<input type="text" name="brand" value="<?php echo htmlspecialchars($product["brand"] ?? ""); ?>"></label>
                        <label>SKU Code<input type="text" name="sku" value="<?php echo htmlspecialchars($product["sku"] ?? ""); ?>"></label>
                        <label>Original Price<input type="number" step="0.01" name="original_price" id="productOriginalPriceInput" value="<?php echo htmlspecialchars($product["original_price"] ?? $product["price"] ?? ""); ?>" required></label>
                        <label>Discount Price<input type="number" step="0.01" name="discount_price" id="productDiscountPriceInput" value="<?php echo htmlspecialchars($product["discount_price"] ?? ""); ?>"></label>
                        <label>Stock Quantity<input type="number" name="stock_quantity" value="<?php echo htmlspecialchars($product["stock_quantity"] ?? "0"); ?>"></label>
                        <label>Short Description<textarea name="short_description" id="productShortInput"><?php echo htmlspecialchars($product["short_description"] ?? $product["description"] ?? ""); ?></textarea></label>
                        <label>Full Description<textarea name="full_description"><?php echo htmlspecialchars($product["full_description"] ?? ""); ?></textarea></label>
                        <label>Product Specifications<textarea name="specifications"><?php echo htmlspecialchars($product["specifications"] ?? ""); ?></textarea></label>
                        <label>Material<input type="text" name="material" value="<?php echo htmlspecialchars($product["material"] ?? ""); ?>"></label>
                        <label>Color<input type="text" name="color" value="<?php echo htmlspecialchars($product["color"] ?? ""); ?>"></label>
                        <label>Dimensions<input type="text" name="dimensions" value="<?php echo htmlspecialchars($product["dimensions"] ?? ""); ?>"></label>
                        <label>Weight<input type="text" name="weight" value="<?php echo htmlspecialchars($product["weight"] ?? ""); ?>"></label>
                        <label>Seating Capacity<input type="text" name="seating_capacity" value="<?php echo htmlspecialchars($product["seating_capacity"] ?? ""); ?>"></label>
                        <label>Room Type<input type="text" name="room_type" value="<?php echo htmlspecialchars($product["room_type"] ?? ""); ?>"></label>
                        <label>Assembly Required
                            <select name="assembly_required">
                                <option value="No" <?php echo ($product["assembly_required"] ?? "No") === "No" ? "selected" : ""; ?>>No</option>
                                <option value="Yes" <?php echo ($product["assembly_required"] ?? "") === "Yes" ? "selected" : ""; ?>>Yes</option>
                            </select>
                        </label>
                        <label>Status
                            <select name="status">
                                <option value="active" <?php echo strtolower($product["status"] ?? "active") === "active" ? "selected" : ""; ?>>Active</option>
                                <option value="inactive" <?php echo strtolower($product["status"] ?? "") === "inactive" ? "selected" : ""; ?>>Inactive</option>
                            </select>
                        </label>
                    </div>
                    <div class="admin-toggle-row">
                        <label><input type="checkbox" name="featured" <?php echo !empty($product["featured"]) ? "checked" : ""; ?>> Featured Product</label>
                        <label><input type="checkbox" name="trending" <?php echo !empty($product["trending"]) ? "checked" : ""; ?>> Trending Product</label>
                        <label><input type="checkbox" name="in_stock" <?php echo !empty($product["in_stock"]) ? "checked" : ""; ?>> In Stock</label>
                    </div>
                </div>

                <div class="admin-form-card">
                    <h2>Product Images</h2>
                    <div class="admin-upload-grid">
                        <?php for ($imageSlot = 1; $imageSlot <= 4; $imageSlot++): ?>
                            <?php $slotImage = $productImageValues[$imageSlot - 1] ?? ""; ?>
                            <label class="admin-upload-box">
                                <span>Product Image <?php echo $imageSlot; ?></span>
                                <input type="file" name="image<?php echo $imageSlot; ?>" <?php echo $imageSlot === 1 ? 'id="mainProductImageInput"' : 'class="galleryProductImageInput"'; ?> accept=".jpg,.jpeg,.png,.webp">
                            </label>
                            <?php if ($slotImage): ?>
                                <article class="admin-gallery-manage-card">
                                    <img src="<?php echo htmlspecialchars($slotImage); ?>" alt="Product image <?php echo $imageSlot; ?>">
                                    <strong><?php echo $imageSlot === 1 ? "Main product image" : "Gallery image " . $imageSlot; ?></strong>
                                    <label><input type="checkbox" name="remove_product_image_<?php echo $imageSlot; ?>" value="1"> Remove</label>
                                </article>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    <div id="adminImagePreviewList" class="admin-image-preview-list"></div>
                </div>

                <button type="submit" class="admin-btn admin-submit-btn">Update Product</button>
            </section>

            <aside class="admin-product-preview">
                <div class="admin-preview-card">
                    <img id="previewProductImage" src="<?php echo htmlspecialchars($product["image"] ?: "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='640' height='420' viewBox='0 0 640 420'%3E%3Crect width='640' height='420' fill='%23FAF7F0'/%3E%3Ctext x='320' y='210' text-anchor='middle' font-family='Arial' font-size='28' font-weight='700' fill='%236B7280'%3EZafiro Casa%3C/text%3E%3C/svg%3E"); ?>" alt="Product preview">
                    <div>
                        <span id="previewCategory"><?php echo htmlspecialchars($product["category"] ?? "Category"); ?></span>
                        <h2 id="previewProductName"><?php echo htmlspecialchars($product["name"] ?? "Product Name"); ?></h2>
                        <p id="previewDescription"><?php echo htmlspecialchars($product["short_description"] ?? $product["description"] ?? "Short description will appear here."); ?></p>
                        <div class="preview-price-row">
                            <strong id="previewDiscountPrice">₹<?php echo htmlspecialchars($product["discount_price"] ?: $product["price"] ?: "0"); ?></strong>
                            <del id="previewOriginalPrice">₹<?php echo htmlspecialchars($product["original_price"] ?: $product["price"] ?: "0"); ?></del>
                        </div>
                    </div>
                </div>
            </aside>
        </form>
    <?php else: ?>
        <section class="admin-panel-card"><p>Product not found.</p></section>
    <?php endif; ?>
<?php include("includes/admin_footer.php"); ?>
