<?php
if (php_sapi_name() !== "cli") {
    include("auth.php");
}
header("Content-Type: text/plain");
include(__DIR__ . "/../backend/config/db.php");
include_once(__DIR__ . "/../backend/includes/product_images.php");

$sourceDir = realpath(__DIR__ . "/../Categories furniture/Balcony furniture");
$uploadDir = realpath(__DIR__ . "/../uploads/products");
$category = "Balcony Furniture";
$errors = [];
$inserted = 0;
$skipped = 0;

function balconySlug($text) {
    return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
}
function balconyColumnExists($conn, $column) {
    $stmt = $conn->prepare("SELECT COUNT(*) total FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'products' AND column_name = ?");
    $stmt->bind_param("s", $column);
    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()["total"] ?? 0) > 0;
}

if (!$sourceDir || !$uploadDir) die("errors: source/upload folder missing\n");

$conn->query("INSERT IGNORE INTO categories (category_name, slug, description, status) VALUES ('Balcony Furniture', 'balcony-furniture', 'Compact premium furniture for balconies and outdoor corners.', 'active')");
ensureProductImageColumns($conn);
ensureProductImagesSchema($conn);

$files = array_values(array_filter(scandir($sourceDir), function ($file) use ($sourceDir) {
    return is_file($sourceDir . DIRECTORY_SEPARATOR . $file) && preg_match('/\.(jpg|jpeg|png|webp)$/i', $file);
}));

$names = [
    "Aurelia Compact Balcony Chair",
    "Valencia Two Seater Balcony Set",
    "Celeste Folding Balcony Table",
    "Monarch Outdoor Coffee Table",
    "Imperial Balcony Swing Chair",
    "Regal Patio Lounge Chair",
    "Florence Bistro Balcony Set",
    "Noble Rattan Balcony Chair",
    "Eterno Slim Balcony Bench",
    "Milano Balcony Side Table",
    "Verona Cane Patio Chair",
    "Serene Rooftop Seating Set"
];

foreach ($files as $index => $file) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $name = $names[$index] ?? ("Zafiro Premium Balcony Piece " . ($index + 1));
    $sku = "ZC-BAL-" . str_pad((string) ($index + 1), 3, "0", STR_PAD_LEFT);

    $check = $conn->prepare("SELECT id FROM products WHERE name = ? OR sku = ? LIMIT 1");
    $check->bind_param("ss", $name, $sku);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $skipped++;
        continue;
    }

    $targetName = "balcony_" . time() . "_" . str_pad((string) ($index + 1), 2, "0", STR_PAD_LEFT) . "." . $ext;
    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $targetName;
    if (!copy($sourceDir . DIRECTORY_SEPARATOR . $file, $targetPath)) {
        $errors[] = "copy failed: $file";
        continue;
    }

    $image = "../uploads/products/" . $targetName;
    $originalPrice = 6999 + ($index * 1450);
    $discountPrice = $originalPrice - (800 + (($index % 4) * 350));
    $price = $discountPrice;
    $stock = 6 + ($index % 12);
    $short = "$name crafted for elegant balcony comfort and compact luxury living.";
    $full = "$short Designed for modern apartments, outdoor corners, and relaxed evening seating with premium Zafiro Casa styling.";
    $slug = balconySlug($name);
    $brand = "Zafiro Casa";
    $specifications = "Premium outdoor-friendly finish, compact footprint, easy maintenance.";
    $material = "Premium rattan, metal, and engineered wood finish";
    $color = ["Walnut","Ivory","Charcoal","Mocha","Natural"][$index % 5];
    $dimensions = "Balcony friendly compact size";
    $weight = "Standard furniture weight";
    $seating = (($index % 3) + 1) . " Seater";
    $roomType = "Balcony";
    $assembly = "No";
    $featured = $index < 2 ? 1 : 0;
    $trending = $index % 5 === 0 ? 1 : 0;
    $inStock = 1;
    $status = "active";
    $gallery = "";

    $sql = "INSERT INTO products (name, price, category, image, image_1, image_2, image_3, image_4, description, slug, brand, sku, original_price, discount_price, stock_quantity, short_description, full_description, specifications, material, color, dimensions, weight, seating_capacity, room_type, assembly_required, featured, trending, in_stock, status, gallery_images) VALUES (?, ?, ?, ?, ?, NULL, NULL, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $types = "sd" . str_repeat("s", 7) . "ddi" . str_repeat("s", 10) . "iiiss";
    $stmt->bind_param($types, $name, $price, $category, $image, $image, $short, $slug, $brand, $sku, $originalPrice, $discountPrice, $stock, $short, $full, $specifications, $material, $color, $dimensions, $weight, $seating, $roomType, $assembly, $featured, $trending, $inStock, $status, $gallery);
    if ($stmt->execute()) {
        syncProductImageColumnsToTable($conn, (int) $stmt->insert_id, [$image]);
        $inserted++;
    } else {
        $errors[] = "$name: " . $stmt->error;
    }
}

$totalBalcony = $conn->query("SELECT COUNT(*) total FROM products WHERE category = 'Balcony Furniture'")->fetch_assoc()["total"] ?? 0;
echo "source_images=" . count($files) . "\n";
echo "inserted=$inserted\n";
echo "skipped_duplicates=$skipped\n";
echo "balcony_total=$totalBalcony\n";
echo "errors=" . ($errors ? implode(" | ", $errors) : "none") . "\n";
?>
