<?php
if (php_sapi_name() !== "cli") include("auth.php");
header("Content-Type: text/plain");
include(__DIR__ . "/../backend/config/db.php");
include_once(__DIR__ . "/../backend/includes/product_images.php");

$sourceDir = realpath(__DIR__ . "/../Categories furniture/Wall decor/wall decor");
$uploadDir = realpath(__DIR__ . "/../uploads/products");
$category = "Wall Decor";
$errors = [];
$inserted = 0;
$skipped = 0;

function wallDecorSlug($text) {
    return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
}
if (!$sourceDir || !$uploadDir) die("errors: source/upload folder missing\n");

$conn->query("INSERT IGNORE INTO categories (category_name, slug, description, status) VALUES ('Wall Decor', 'wall-decor', 'Premium wall art, panels, sculptures and decorative accents.', 'active')");
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
    ["Aurelio Gold Wall Art", 8999, 6999, "gold wall art finish"],
    ["Verona Luxury Wall Panel", 12999, 9999, "luxury wall panel"],
    ["Milan Abstract Wall Decor", 7499, 5799, "abstract wall decor"],
    ["Nordic Wooden Wall Accent", 9999, 7999, "wooden wall accent"],
    ["Imperial Metal Wall Sculpture", 14999, 11999, "metal wall sculpture"],
    ["Royal Crest Designer Wall Art", 10999, 8499, "designer wall art"],
    ["Celeste Premium Wall Accent", 6999, 5299, "premium wall accent"],
    ["Monarch Framed Wall Decor", 8499, 6499, "framed wall decor"],
    ["Regal Patterned Wall Panel", 11999, 9299, "patterned wall panel"],
    ["Florence Modern Wall Art", 7999, 6199, "modern wall art"],
    ["Noble Luxury Wall Decor", 5999, 4599, "luxury wall decor"],
    ["Eterno Designer Wall Accent", 9499, 7499, "designer wall accent"],
    ["Casa Royale Textured Wall Art", 13999, 10999, "textured wall art"],
    ["Opulence Statement Wall Sculpture", 16999, 13999, "statement wall sculpture"],
    ["Serene Minimal Wall Decor", 5499, 4199, "minimal wall decor"],
    ["Valencia Premium Wall Art", 8999, 6999, "premium wall art"],
    ["Aurelia Decorative Wall Panel", 10999, 8499, "decorative wall panel"],
    ["Milano Luxe Wall Accent", 7999, 6199, "luxe wall accent"],
    ["Verona Artistic Wall Decor", 6999, 5299, "artistic wall decor"],
    ["Imperial Designer Wall Panel", 12999, 9999, "designer wall panel"],
    ["Nordic Premium Wall Accent", 6499, 4999, "premium wall accent"]
];

foreach ($files as $index => $file) {
    $item = $products[$index] ?? ["Zafiro Premium Wall Decor " . ($index + 1), 5999 + ($index * 500), 4499 + ($index * 450), "premium wall decor"];
    [$name, $originalPrice, $discountPrice, $feature] = $item;
    $sku = "ZC-WDEC-" . str_pad((string) ($index + 1), 3, "0", STR_PAD_LEFT);
    $check = $conn->prepare("SELECT id FROM products WHERE name = ? OR sku = ? LIMIT 1");
    $check->bind_param("ss", $name, $sku);
    $check->execute();
    if ($check->get_result()->num_rows > 0) { $skipped++; continue; }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $targetName = "wall_decor_" . time() . "_" . str_pad((string) ($index + 1), 2, "0", STR_PAD_LEFT) . "." . $ext;
    if (!copy($file, $uploadDir . DIRECTORY_SEPARATOR . $targetName)) { $errors[] = "copy failed: $file"; continue; }

    $image = "../uploads/products/" . $targetName;
    $price = $discountPrice;
    $stock = 8 + ($index % 18);
    $short = "$name adds refined wall styling with $feature.";
    $full = "$short Designed for premium interiors with balanced artwork presence, decorative texture, durable finish, and Zafiro Casa luxury styling.";
    $slug = wallDecorSlug($name);
    $brand = "Zafiro Casa";
    $specifications = "Premium wall decor, wall art or panel, decorative luxury finish, easy placement.";
    $material = "Premium wood, metal, canvas, resin or designer finish";
    $color = "Premium finish";
    $dimensions = "Wall decor standard size";
    $weight = "Lightweight decor";
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

$cat = $conn->query("SELECT id, slug FROM categories WHERE category_name='Wall Decor' LIMIT 1")->fetch_assoc();
$total = $conn->query("SELECT COUNT(*) total FROM products WHERE category = 'Wall Decor'")->fetch_assoc()["total"] ?? 0;
echo "wall_decor_category_id=" . ($cat["id"] ?? "not_found") . "\n";
echo "wall_decor_category_slug=" . ($cat["slug"] ?? "not_found") . "\n";
echo "source_images=" . count($files) . "\n";
echo "inserted=$inserted\n";
echo "skipped_duplicates=$skipped\n";
echo "wall_decor_total=$total\n";
echo "errors=" . ($errors ? implode(" | ", $errors) : "none") . "\n";
?>
