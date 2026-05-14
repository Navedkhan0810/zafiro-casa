<?php
if (php_sapi_name() !== "cli") include("auth.php");
header("Content-Type: text/plain");
include(__DIR__ . "/../backend/config/db.php");
include_once(__DIR__ . "/../backend/includes/product_images.php");

$sourceDir = realpath(__DIR__ . "/../Categories furniture/chair");
$uploadDir = realpath(__DIR__ . "/../uploads/products");
$category = "Chairs";
$errors = [];
$inserted = 0;
$skipped = 0;

function chairsSlug($text) {
    return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
}

if (!$sourceDir || !$uploadDir) die("errors: source/upload folder missing\n");

$conn->query("INSERT IGNORE INTO categories (category_name, slug, description, status) VALUES ('Chairs', 'chairs', 'Premium accent chairs, lounge chairs, dining chairs and luxury seating.', 'active')");
ensureProductImageColumns($conn);
ensureProductImagesSchema($conn);

$conn->query("DELETE FROM product_images WHERE product_id IN (SELECT id FROM products WHERE sku LIKE 'ZC-CHAIR-%')");
$conn->query("DELETE FROM products WHERE sku LIKE 'ZC-CHAIR-%'");

$files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $fileInfo) {
    if ($fileInfo->isFile() && preg_match('/\.(jpg|jpeg|png|webp)$/i', $fileInfo->getFilename())) $files[] = $fileInfo->getPathname();
}

$products = [
    ["Aurelio Luxe Accent Chair", 18999, 15999, "accent chair profile with cushioned comfort"],
    ["Verona Premium Lounge Chair", 21999, 18999, "lounge profile with supportive comfort"],
    ["Milan Elegant Dining Chair", 14999, 12499, "dining chair styling with refined legs"],
    ["Nordic Luxury Armchair", 23999, 20999, "armrest design with premium finish"],
    ["Imperial Padded Chair", 26999, 22999, "padded backrest with luxury cushioning"],
    ["Royal Crest Statement Chair", 24999, 21499, "statement chair with premium appeal"],
    ["Celeste Curved Lounge Chair", 22999, 19499, "curved lounge silhouette with plush comfort"],
    ["Monarch Compact Accent Chair", 17999, 14999, "compact accent chair form"],
    ["Regal Designer Dining Chair", 15999, 13499, "designer dining chair style"],
    ["Florence Premium Armchair", 24999, 21999, "armchair layout with refined comfort"],
    ["Noble Compact Lounge Chair", 16999, 14499, "compact lounge style for modern interiors"],
    ["Eterno Designer Chair", 19999, 16999, "designer chair with premium finish"],
    ["Casa Royale Luxury Chair", 28999, 24999, "luxury seating with statement proportions"],
    ["Opulence Cushioned Chair", 18999, 15999, "cushioned seat with elegant profile"],
    ["Serene Modern Armchair", 21999, 18999, "modern armchair with balanced comfort"],
    ["Heritage Classic Accent Chair", 17999, 15499, "classic accent chair styling"],
    ["Valencia Premium Comfort Chair", 25999, 22499, "premium seating with durable frame"],
    ["Aurelia Elegant Dining Chair", 14999, 12999, "elegant dining chair with polished finish"],
    ["Milano Luxe Occasional Chair", 20999, 17999, "occasional chair for luxury corners"],
    ["Verona Comfort Arm Chair", 23999, 20499, "comfort arm chair with premium cushioning"]
];

foreach ($files as $index => $file) {
    $item = $products[$index] ?? ["Zafiro Luxe Chair " . ($index + 1), 16999 + ($index * 700), 14999 + ($index * 600), "premium chair design"];
    [$name, $originalPrice, $discountPrice, $feature] = $item;
    $sku = "ZC-CHAIR-" . str_pad((string) ($index + 1), 3, "0", STR_PAD_LEFT);

    $check = $conn->prepare("SELECT id FROM products WHERE name = ? OR sku = ? LIMIT 1");
    $check->bind_param("ss", $name, $sku);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $skipped++;
        continue;
    }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $targetName = "chairs_" . time() . "_" . str_pad((string) ($index + 1), 2, "0", STR_PAD_LEFT) . "." . $ext;
    if (!copy($file, $uploadDir . DIRECTORY_SEPARATOR . $targetName)) {
        $errors[] = "copy failed: $file";
        continue;
    }

    $image = "../uploads/products/" . $targetName;
    $price = $discountPrice;
    $stock = 6 + ($index % 12);
    $short = "$name brings premium seating comfort with $feature.";
    $full = "$short Crafted for refined living rooms, dining spaces, and accent corners with durable support and Zafiro Casa luxury finish.";
    $slug = chairsSlug($name);
    $brand = "Zafiro Casa";
    $specifications = "Premium chair, cushioned comfort, durable frame, luxury finish.";
    $material = "Premium upholstery, wood or metal frame finish";
    $color = "Premium finish";
    $dimensions = "Chair standard size";
    $weight = "Standard furniture weight";
    $seating = "1 Seater";
    $roomType = "Living Room";
    $assembly = "No";
    $featured = $index < 3 ? 1 : 0;
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

$total = $conn->query("SELECT COUNT(*) total FROM products WHERE category = 'Chairs'")->fetch_assoc()["total"] ?? 0;
echo "source_images=" . count($files) . "\n";
echo "inserted=$inserted\n";
echo "skipped_duplicates=$skipped\n";
echo "chairs_total=$total\n";
echo "errors=" . ($errors ? implode(" | ", $errors) : "none") . "\n";
?>
