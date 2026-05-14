<?php
if (php_sapi_name() !== "cli") include("auth.php");
header("Content-Type: text/plain");
include(__DIR__ . "/../backend/config/db.php");
include_once(__DIR__ . "/../backend/includes/product_images.php");

$sourceDir = realpath(__DIR__ . "/../Categories furniture/Room dividers");
$uploadDir = realpath(__DIR__ . "/../uploads/products");
$category = "Room Dividers";
$errors = [];
$inserted = 0;
$skipped = 0;

function roomDividerSlug($text) {
    return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
}
if (!$sourceDir || !$uploadDir) die("errors: source/upload folder missing\n");

$conn->query("UPDATE categories SET category_name='Room Dividers', slug='room-dividers' WHERE category_name='Room Divider' OR slug='room-dividers'");
$conn->query("INSERT IGNORE INTO categories (category_name, slug, description, status) VALUES ('Room Dividers', 'room-dividers', 'Premium room dividers, privacy screens and decorative partitions.', 'active')");
ensureProductImageColumns($conn);
ensureProductImagesSchema($conn);

$files = [];
$seenHashes = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile() || !preg_match('/\.(jpg|jpeg|png|webp)$/i', $fileInfo->getFilename())) continue;
    $hash = md5_file($fileInfo->getPathname());
    if (isset($seenHashes[$hash])) continue;
    $seenHashes[$hash] = true;
    $files[] = $fileInfo->getPathname();
}

$products = [
    ["Aurelio Wooden Room Divider", 24999, 20999, "wooden frame room divider"],
    ["Verona Folding Privacy Screen", 18999, 15499, "folding privacy screen"],
    ["Milan Luxe Panel Divider", 22999, 18999, "luxury panel divider"],
    ["Nordic Cane Room Partition", 26999, 22999, "cane texture room partition"],
    ["Imperial Gold Frame Divider", 29999, 24999, "gold frame divider"],
    ["Royal Crest Decorative Room Screen", 21999, 17999, "decorative room screen"],
    ["Celeste Patterned Privacy Divider", 19999, 16499, "patterned privacy divider"],
    ["Monarch Metal Frame Partition", 27999, 23999, "metal frame partition"],
    ["Regal Folding Panel Screen", 17999, 14499, "folding panel screen"],
    ["Florence Designer Room Partition", 23999, 19999, "designer room partition"],
    ["Noble Wooden Privacy Screen", 20999, 16999, "wooden privacy screen"],
    ["Eterno Luxe Decorative Divider", 25999, 21999, "decorative luxury divider"],
    ["Casa Royale Cane Divider", 28999, 24999, "cane room divider"],
    ["Opulence Carved Room Screen", 32999, 27999, "carved decorative screen"],
    ["Serene Minimal Panel Divider", 16999, 13499, "minimal panel divider"],
    ["Valencia Premium Room Partition", 24999, 20999, "premium room partition"],
    ["Aurelia Luxury Folding Screen", 21999, 17999, "luxury folding screen"],
    ["Milano Designer Divider Panel", 23999, 19999, "designer divider panel"],
    ["Verona Modern Privacy Partition", 19999, 15999, "modern privacy partition"],
    ["Imperial Decorative Screen", 26999, 22999, "decorative screen"],
    ["Nordic Textured Room Divider", 22999, 18999, "textured room divider"],
    ["Royal Crest Pattern Divider", 29999, 24999, "patterned luxury divider"],
    ["Celeste Premium Privacy Screen", 18999, 15499, "premium privacy screen"]
];

foreach ($files as $index => $file) {
    $item = $products[$index] ?? ["Zafiro Premium Room Divider " . ($index + 1), 18999 + ($index * 900), 14999 + ($index * 800), "premium room divider"];
    [$name, $originalPrice, $discountPrice, $feature] = $item;
    $sku = "ZC-RDIV-" . str_pad((string) ($index + 1), 3, "0", STR_PAD_LEFT);
    $check = $conn->prepare("SELECT id FROM products WHERE name = ? OR sku = ? LIMIT 1");
    $check->bind_param("ss", $name, $sku);
    $check->execute();
    if ($check->get_result()->num_rows > 0) { $skipped++; continue; }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $targetName = "room_divider_" . time() . "_" . str_pad((string) ($index + 1), 2, "0", STR_PAD_LEFT) . "." . $ext;
    if (!copy($file, $uploadDir . DIRECTORY_SEPARATOR . $targetName)) { $errors[] = "copy failed: $file"; continue; }

    $image = "../uploads/products/" . $targetName;
    $price = $discountPrice;
    $stock = 5 + ($index % 12);
    $short = "$name adds elegant separation with $feature.";
    $full = "$short Designed for premium interiors with privacy function, decorative presence, durable construction, and Zafiro Casa luxury finish.";
    $slug = roomDividerSlug($name);
    $brand = "Zafiro Casa";
    $specifications = "Premium room divider, decorative screen, privacy partition, easy placement.";
    $material = "Premium wood, cane, metal or patterned panel finish";
    $color = "Premium finish";
    $dimensions = "Room divider standard size";
    $weight = "Standard furniture weight";
    $seating = "";
    $roomType = "Decor";
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
    if ($stmt->execute()) { syncProductImageColumnsToTable($conn, (int) $stmt->insert_id, [$image]); $inserted++; }
    else { $errors[] = "$name: " . $stmt->error; }
}

$conn->query("UPDATE products SET category='Room Dividers' WHERE category='Room Divider'");
$cat = $conn->query("SELECT id, slug FROM categories WHERE category_name='Room Dividers' LIMIT 1")->fetch_assoc();
$total = $conn->query("SELECT COUNT(*) total FROM products WHERE category = 'Room Dividers'")->fetch_assoc()["total"] ?? 0;
echo "room_divider_category_id=" . ($cat["id"] ?? "not_found") . "\n";
echo "room_divider_category_slug=" . ($cat["slug"] ?? "not_found") . "\n";
echo "source_images=" . count($files) . "\n";
echo "inserted=$inserted\n";
echo "skipped_duplicates=$skipped\n";
echo "room_divider_total=$total\n";
echo "errors=" . ($errors ? implode(" | ", $errors) : "none") . "\n";
?>
