<?php
function ensureProductImagesSchema($conn) {
    ensureProductImageColumns($conn);
    $conn->query("CREATE TABLE IF NOT EXISTS product_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        is_main TINYINT(1) DEFAULT 0,
        sort_order INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_product_images_product_id (product_id),
        INDEX idx_product_images_main (is_main)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if (!productImageColumnExists($conn, "is_main")) {
        $conn->query("ALTER TABLE product_images ADD COLUMN is_main TINYINT(1) DEFAULT 0");
    }
    if (productImageColumnExists($conn, "is_featured")) {
        $conn->query("UPDATE product_images SET is_main = 1 WHERE COALESCE(is_featured, 0) = 1");
    }
}

function ensureProductImageColumns($conn) {
    $columns = [
        "image_1" => "VARCHAR(255) NULL",
        "image_2" => "VARCHAR(255) NULL",
        "image_3" => "VARCHAR(255) NULL",
        "image_4" => "VARCHAR(255) NULL"
    ];

    foreach ($columns as $column => $definition) {
        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'products' AND column_name = ?");
        $stmt->bind_param("s", $column);
        $stmt->execute();
        if ((int) ($stmt->get_result()->fetch_assoc()["total"] ?? 0) === 0) {
            $conn->query("ALTER TABLE products ADD COLUMN `$column` $definition");
        }
    }
}

function productImageColumnExists($conn, $columnName) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'product_images' AND column_name = ?");
    $stmt->bind_param("s", $columnName);
    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()["total"] ?? 0) > 0;
}

function addProductImageRow($conn, $productId, $imagePath, $isMain = 0, $sortOrder = 0) {
    if ($productId <= 0 || trim((string) $imagePath) === '') return;
    ensureProductImagesSchema($conn);
    $stmt = $conn->prepare("INSERT INTO product_images (product_id, image_path, is_main, sort_order) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isii", $productId, $imagePath, $isMain, $sortOrder);
    $stmt->execute();
}

function syncProductImageColumnsToTable($conn, $productId, $images) {
    $productId = (int) $productId;
    if ($productId <= 0) return;
    ensureProductImagesSchema($conn);

    $clean = [];
    for ($index = 0; $index < 4; $index++) {
        $image = trim((string) ($images[$index] ?? ""));
        $clean[$index] = $image !== "" ? $image : null;
    }

    $stmt = $conn->prepare("UPDATE products SET image = ?, image_1 = ?, image_2 = ?, image_3 = ?, image_4 = ?, gallery_images = ? WHERE id = ?");
    $mainImage = $clean[0] ?: (array_values(array_filter($clean))[0] ?? "");
    $galleryImages = implode(",", array_values(array_filter(array_slice($clean, 1))));
    $stmt->bind_param("ssssssi", $mainImage, $clean[0], $clean[1], $clean[2], $clean[3], $galleryImages, $productId);
    $stmt->execute();

    $delete = $conn->prepare("DELETE FROM product_images WHERE product_id = ?");
    $delete->bind_param("i", $productId);
    $delete->execute();

    foreach ($clean as $index => $image) {
        if ($image !== null && $image !== '') {
            addProductImageRow($conn, $productId, $image, $index === 0 ? 1 : 0, $index);
        }
    }
}

function productImageFallback() {
    return "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='900' height='700' viewBox='0 0 900 700'%3E%3Crect width='900' height='700' fill='%23FAF7F0'/%3E%3Ctext x='450' y='350' text-anchor='middle' font-family='Arial' font-size='42' font-weight='700' fill='%236B7280'%3EZafiro Casa%3C/text%3E%3C/svg%3E";
}

function setProductMainImage($conn, $productId, $imagePath) {
    if ($productId <= 0 || trim((string) $imagePath) === '') return;
    ensureProductImagesSchema($conn);
    $clear = $conn->prepare("UPDATE product_images SET is_main = 0 WHERE product_id = ?");
    $clear->bind_param("i", $productId);
    $clear->execute();

    $mark = $conn->prepare("UPDATE product_images SET is_main = 1 WHERE product_id = ? AND image_path = ? LIMIT 1");
    $mark->bind_param("is", $productId, $imagePath);
    $mark->execute();

    $main = $conn->prepare("UPDATE products SET image = ? WHERE id = ?");
    $main->bind_param("si", $imagePath, $productId);
    $main->execute();
}

function setProductFeaturedImage($conn, $productId, $imagePath) {
    setProductMainImage($conn, $productId, $imagePath);
}

function deleteProductImageRows($conn, $productId, $imageIds) {
    if ($productId <= 0 || empty($imageIds)) return;
    ensureProductImagesSchema($conn);
    $ids = array_values(array_filter(array_map('intval', (array) $imageIds)));
    if (!$ids) return;

    foreach ($ids as $id) {
        $stmt = $conn->prepare("DELETE FROM product_images WHERE id = ? AND product_id = ?");
        $stmt->bind_param("ii", $id, $productId);
        $stmt->execute();
    }
}

function getProductImageRows($conn, $productId) {
    ensureProductImagesSchema($conn);
    $stmt = $conn->prepare("SELECT id, image_path, is_main, sort_order FROM product_images WHERE product_id = ? ORDER BY is_main DESC, sort_order ASC, id ASC");
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    $images = [];
    while ($row = $result->fetch_assoc()) {
        $images[] = $row;
    }
    return $images;
}

function getProductGalleryImages($conn, $product) {
    $productId = (int) ($product['id'] ?? 0);
    $rows = $productId > 0 ? getProductImageRows($conn, $productId) : [];
    $images = [];

    foreach (["image_1", "image_2", "image_3", "image_4"] as $column) {
        if (!empty($product[$column])) $images[] = $product[$column];
    }

    foreach ($rows as $row) {
        if (!empty($row['image_path'])) $images[] = $row['image_path'];
    }

    if (!empty($product['image'])) {
        array_unshift($images, $product['image']);
    }

    if (!empty($product['gallery_images'])) {
        foreach (explode(',', $product['gallery_images']) as $image) {
            $image = trim($image);
            if ($image !== '') $images[] = $image;
        }
    }

    $images = array_values(array_unique(array_filter($images)));
    if (!$images) {
        $images[] = productImageFallback();
    }

    return $images;
}

function getProductCardImage($product) {
    foreach (["image_1", "image", "image_2", "image_3", "image_4"] as $column) {
        if (!empty($product[$column])) return $product[$column];
    }
    return productImageFallback();
}
?>
