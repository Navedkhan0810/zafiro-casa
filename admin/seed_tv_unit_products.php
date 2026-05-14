<?php
if (php_sapi_name() !== "cli") include("auth.php");
header("Content-Type: text/plain");
include(__DIR__ . "/../backend/config/db.php");
include_once(__DIR__ . "/../backend/includes/product_images.php");

$sourceDir = realpath(__DIR__ . "/../Categories furniture/tv unit/tv unit");
$uploadDir = realpath(__DIR__ . "/../uploads/products");
$category = "TV Unit";
$errors = [];
$inserted = 0;
$skipped = 0;

function tvUnitSlug($text) {
    return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
}
if (!$sourceDir || !$uploadDir) die("errors: source/upload folder missing\n");

$conn->query("INSERT IGNORE INTO categories (category_name, slug, description, status) VALUES ('TV Unit', 'tv-unit', 'Premium TV units, media consoles and entertainment storage.', 'active')");
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
    ["Aurelio Wall Mounted TV Unit", 54999, 47999, "wall mounted media unit"],
    ["Verona Walnut TV Console", 44999, 38999, "walnut finish TV console"],
    ["Milan Luxe Entertainment Unit", 69999, 60999, "luxury entertainment storage"],
    ["Nordic Oak TV Cabinet", 39999, 33999, "oak finish TV cabinet"],
    ["Imperial Marble Panel TV Unit", 79999, 69999, "marble panel TV unit"],
    ["Royal Crest Premium TV Console", 62999, 54999, "premium TV console"],
    ["Celeste Floating TV Unit", 36999, 30999, "floating TV unit"],
    ["Monarch Designer Entertainment Wall", 89999, 78999, "designer entertainment wall"],
    ["Regal Drawer TV Cabinet", 42999, 36999, "drawer storage TV cabinet"],
    ["Florence Modern Media Console", 34999, 28999, "modern media console"],
    ["Noble Wooden TV Unit", 38999, 32999, "wooden finish TV unit"],
    ["Eterno Luxe TV Wall Panel", 74999, 64999, "luxe TV wall panel"],
    ["Casa Royale Storage TV Unit", 57999, 49999, "storage TV unit"],
    ["Opulence Marble Media Unit", 84999, 73999, "marble media unit"],
    ["Serene Minimal TV Console", 31999, 25999, "minimal TV console"],
    ["Valencia Premium Entertainment Unit", 68999, 59999, "premium entertainment unit"],
    ["Aurelia Wall Panel TV Console", 62999, 53999, "wall panel TV console"],
    ["Milano Designer TV Cabinet", 48999, 41999, "designer TV cabinet"],
    ["Verona Modern TV Unit", 42999, 36999, "modern TV unit"],
    ["Imperial Floating Media Console", 45999, 38999, "floating media console"],
    ["Nordic Premium TV Storage", 39999, 33999, "premium TV storage"],
    ["Royal Crest Entertainment Cabinet", 72999, 62999, "entertainment cabinet"],
    ["Celeste Luxe Media Wall Unit", 78999, 67999, "luxe media wall unit"],
    ["Monarch Premium TV Console", 54999, 47999, "premium TV console"],
    ["Regal Marble Panel Console", 66999, 57999, "marble panel console"],
    ["Florence Wooden Media Unit", 46999, 39999, "wooden media unit"],
    ["Noble Designer TV Wall Unit", 75999, 65999, "designer TV wall unit"],
    ["Eterno Luxury TV Console", 58999, 50999, "luxury TV console"]
];

foreach ($files as $index => $file) {
    $item = $products[$index] ?? ["Zafiro Premium TV Unit " . ($index + 1), 39999 + ($index * 1600), 32999 + ($index * 1400), "premium TV unit"];
    [$name, $originalPrice, $discountPrice, $feature] = $item;
    $sku = "ZC-TVU-" . str_pad((string) ($index + 1), 3, "0", STR_PAD_LEFT);
    $check = $conn->prepare("SELECT id FROM products WHERE name = ? OR sku = ? LIMIT 1");
    $check->bind_param("ss", $name, $sku);
    $check->execute();
    if ($check->get_result()->num_rows > 0) { $skipped++; continue; }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $targetName = "tv_unit_" . time() . "_" . str_pad((string) ($index + 1), 2, "0", STR_PAD_LEFT) . "." . $ext;
    if (!copy($file, $uploadDir . DIRECTORY_SEPARATOR . $targetName)) { $errors[] = "copy failed: $file"; continue; }

    $image = "../uploads/products/" . $targetName;
    $price = $discountPrice;
    $stock = 4 + ($index % 12);
    $short = "$name adds premium media storage with $feature.";
    $full = "$short Designed for luxury living rooms with clean TV placement, shelves or drawers, refined wall panels, and Zafiro Casa premium finish.";
    $slug = tvUnitSlug($name);
    $brand = "Zafiro Casa";
    $specifications = "Premium TV unit, media console storage, wall panel or cabinet design, living room use.";
    $material = "Premium engineered wood, laminate, marble, glass or metal finish";
    $color = "Premium finish";
    $dimensions = "TV unit standard size";
    $weight = "Standard furniture weight";
    $seating = "";
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

$cat = $conn->query("SELECT id, slug FROM categories WHERE category_name='TV Unit' LIMIT 1")->fetch_assoc();
$total = $conn->query("SELECT COUNT(*) total FROM products WHERE category = 'TV Unit'")->fetch_assoc()["total"] ?? 0;
echo "tv_unit_category_id=" . ($cat["id"] ?? "not_found") . "\n";
echo "tv_unit_category_slug=" . ($cat["slug"] ?? "not_found") . "\n";
echo "source_images=" . count($files) . "\n";
echo "inserted=$inserted\n";
echo "skipped_duplicates=$skipped\n";
echo "tv_unit_total=$total\n";
echo "errors=" . ($errors ? implode(" | ", $errors) : "none") . "\n";
?>
