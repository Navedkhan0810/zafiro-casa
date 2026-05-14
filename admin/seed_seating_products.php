<?php
if (php_sapi_name() !== "cli") include("auth.php");
header("Content-Type: text/plain");
include(__DIR__ . "/../backend/config/db.php");
include_once(__DIR__ . "/../backend/includes/product_images.php");

$sourceDir = realpath(__DIR__ . "/../Categories furniture/seating/seating/seating");
$uploadDir = realpath(__DIR__ . "/../uploads/products");
$category = "Seating";
$errors = [];
$inserted = 0;
$skipped = 0;

function seatingSlug($text) {
    return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
}
if (!$sourceDir || !$uploadDir) die("errors: source/upload folder missing\n");

$conn->query("INSERT IGNORE INTO categories (category_name, slug, description, status) VALUES ('Seating', 'seating', 'Premium lounge seating, benches and upholstered seating units.', 'active')");
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
    ["Aurelio Lounge Seating", 28999, 23999, "lounge comfort"],
    ["Verona Upholstered Seating Chair", 24999, 20999, "upholstered finish"],
    ["Milan Luxe Seating Unit", 34999, 29999, "luxury seating unit"],
    ["Nordic Fabric Seating", 21999, 17999, "fabric cushioning"],
    ["Imperial Velvet Seating Bench", 31999, 26999, "velvet texture bench"],
    ["Royal Crest Premium Seating", 39999, 33999, "premium padding"],
    ["Celeste Designer Lounge Seating", 29999, 24999, "designer lounge seating"],
    ["Monarch Modern Seating Unit", 26999, 22999, "modern seating design"],
    ["Regal Cushioned Seating Bench", 23999, 19999, "cushioned seating bench"]
];

foreach ($files as $index => $file) {
    $item = $products[$index] ?? ["Zafiro Premium Seating " . ($index + 1), 21999 + ($index * 1000), 17999 + ($index * 900), "premium seating"];
    [$name, $originalPrice, $discountPrice, $feature] = $item;
    $sku = "ZC-SEAT-" . str_pad((string) ($index + 1), 3, "0", STR_PAD_LEFT);
    $check = $conn->prepare("SELECT id FROM products WHERE name = ? OR sku = ? LIMIT 1");
    $check->bind_param("ss", $name, $sku);
    $check->execute();
    if ($check->get_result()->num_rows > 0) { $skipped++; continue; }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $targetName = "seating_" . time() . "_" . str_pad((string) ($index + 1), 2, "0", STR_PAD_LEFT) . "." . $ext;
    if (!copy($file, $uploadDir . DIRECTORY_SEPARATOR . $targetName)) { $errors[] = "copy failed: $file"; continue; }

    $image = "../uploads/products/" . $targetName;
    $price = $discountPrice;
    $stock = 5 + ($index % 12);
    $short = "$name adds refined comfort with $feature.";
    $full = "$short Designed for premium living spaces with cushioned seating, refined upholstery, stable legs, and Zafiro Casa luxury finish.";
    $slug = seatingSlug($name);
    $brand = "Zafiro Casa";
    $specifications = "Premium seating, cushioned comfort, lounge or bench use, luxury finish.";
    $material = "Premium fabric, velvet, foam, wood or metal finish";
    $color = "Premium finish";
    $dimensions = "Seating standard size";
    $weight = "Standard furniture weight";
    $seating = "1-2 Seater";
    $roomType = "Living Room";
    $assembly = "No";
    $featured = $index < 2 ? 1 : 0;
    $trending = $index % 4 === 0 ? 1 : 0;
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

$cat = $conn->query("SELECT id, slug FROM categories WHERE category_name='Seating' LIMIT 1")->fetch_assoc();
$total = $conn->query("SELECT COUNT(*) total FROM products WHERE category = 'Seating'")->fetch_assoc()["total"] ?? 0;
echo "seating_category_id=" . ($cat["id"] ?? "not_found") . "\n";
echo "seating_category_slug=" . ($cat["slug"] ?? "not_found") . "\n";
echo "source_images=" . count($files) . "\n";
echo "inserted=$inserted\n";
echo "skipped_duplicates=$skipped\n";
echo "seating_total=$total\n";
echo "errors=" . ($errors ? implode(" | ", $errors) : "none") . "\n";
?>
