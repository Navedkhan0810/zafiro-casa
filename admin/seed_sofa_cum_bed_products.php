<?php
if (php_sapi_name() !== "cli") include("auth.php");
header("Content-Type: text/plain");
include(__DIR__ . "/../backend/config/db.php");
include_once(__DIR__ . "/../backend/includes/product_images.php");

$sourceDir = realpath(__DIR__ . "/../Categories furniture/sofa cum bed/sofa cum bed");
$uploadDir = realpath(__DIR__ . "/../uploads/products");
$category = "Sofa Cum Bed";
$errors = [];
$inserted = 0;
$skipped = 0;

function sofaCumBedSlug($text) {
    return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
}
if (!$sourceDir || !$uploadDir) die("errors: source/upload folder missing\n");

$conn->query("INSERT IGNORE INTO categories (category_name, slug, description, status) VALUES ('Sofa Cum Bed', 'sofa-cum-bed', 'Premium convertible sofa beds for compact luxury living.', 'active')");
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
    ["Aurelio Convertible Sofa Cum Bed", 49999, 42999, "convertible design"],
    ["Verona Upholstered Sofa Bed", 45999, 38999, "upholstered finish"],
    ["Milan Luxe Pull Out Sofa Bed", 57999, 49999, "pull-out bed mechanism"],
    ["Nordic Fabric Sofa Cum Bed", 42999, 35999, "fabric cushioned seating"],
    ["Imperial Storage Sofa Bed", 64999, 56999, "storage sofa bed"],
    ["Royal Crest Premium Sofa Bed", 69999, 60999, "premium sofa bed comfort"],
    ["Celeste Compact Sofa Cum Bed", 39999, 32999, "compact living room design"],
    ["Monarch Designer Sofa Bed", 54999, 46999, "designer sofa bed"],
    ["Regal Foldable Sofa Bed", 44999, 37999, "foldable mechanism"],
    ["Florence Cushioned Sofa Cum Bed", 48999, 41999, "deep cushioned seating"],
    ["Noble Premium Convertible Sofa", 52999, 44999, "premium convertible sofa"],
    ["Eterno Luxe Sofa Bed", 59999, 51999, "luxury sofa bed finish"],
    ["Casa Royale Pull Out Sofa", 62999, 54999, "pull-out sofa bed"],
    ["Opulence Living Sofa Bed", 71999, 62999, "luxury living sofa bed"],
    ["Serene Modern Sofa Cum Bed", 41999, 34999, "modern sofa cum bed"],
    ["Valencia Upholstered Daybed Sofa", 56999, 48999, "upholstered daybed style"],
    ["Aurelia Premium Sofa Sleeper", 58999, 50999, "premium sofa sleeper"],
    ["Milano Compact Sofa Bed", 39999, 32999, "compact sofa bed"]
];

foreach ($files as $index => $file) {
    $item = $products[$index] ?? ["Zafiro Premium Sofa Cum Bed " . ($index + 1), 42999 + ($index * 1500), 35999 + ($index * 1300), "premium sofa cum bed"];
    [$name, $originalPrice, $discountPrice, $feature] = $item;
    $sku = "ZC-SCB-" . str_pad((string) ($index + 1), 3, "0", STR_PAD_LEFT);
    $check = $conn->prepare("SELECT id FROM products WHERE name = ? OR sku = ? LIMIT 1");
    $check->bind_param("ss", $name, $sku);
    $check->execute();
    if ($check->get_result()->num_rows > 0) { $skipped++; continue; }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $targetName = "sofa_cum_bed_" . time() . "_" . str_pad((string) ($index + 1), 2, "0", STR_PAD_LEFT) . "." . $ext;
    if (!copy($file, $uploadDir . DIRECTORY_SEPARATOR . $targetName)) { $errors[] = "copy failed: $file"; continue; }

    $image = "../uploads/products/" . $targetName;
    $price = $discountPrice;
    $stock = 4 + ($index % 10);
    $short = "$name offers premium comfort with $feature.";
    $full = "$short Designed for modern homes with convertible utility, cushioned seating, practical sleeping comfort, and Zafiro Casa luxury finish.";
    $slug = sofaCumBedSlug($name);
    $brand = "Zafiro Casa";
    $specifications = "Premium sofa cum bed, convertible design, cushioned seating, compact living room use.";
    $material = "Premium fabric, engineered wood, foam or upholstery finish";
    $color = "Premium finish";
    $dimensions = "Sofa cum bed standard size";
    $weight = "Standard furniture weight";
    $seating = "3 Seater";
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
    if ($stmt->execute()) { syncProductImageColumnsToTable($conn, (int) $stmt->insert_id, [$image]); $inserted++; }
    else { $errors[] = "$name: " . $stmt->error; }
}

$cat = $conn->query("SELECT id, slug FROM categories WHERE category_name='Sofa Cum Bed' LIMIT 1")->fetch_assoc();
$total = $conn->query("SELECT COUNT(*) total FROM products WHERE category = 'Sofa Cum Bed'")->fetch_assoc()["total"] ?? 0;
echo "sofa_cum_bed_category_id=" . ($cat["id"] ?? "not_found") . "\n";
echo "sofa_cum_bed_category_slug=" . ($cat["slug"] ?? "not_found") . "\n";
echo "source_images=" . count($files) . "\n";
echo "inserted=$inserted\n";
echo "skipped_duplicates=$skipped\n";
echo "sofa_cum_bed_total=$total\n";
echo "errors=" . ($errors ? implode(" | ", $errors) : "none") . "\n";
?>
