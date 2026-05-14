<?php
if (php_sapi_name() !== "cli") include("auth.php");
header("Content-Type: text/plain");
include(__DIR__ . "/../backend/config/db.php");
include_once(__DIR__ . "/../backend/includes/product_images.php");

$sourceDir = realpath(__DIR__ . "/../Categories furniture/sofas/sofas");
$uploadDir = realpath(__DIR__ . "/../uploads/products");
$category = "Sofa";
$errors = [];
$inserted = 0;
$skipped = 0;

function sofaSlug($text) {
    return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
}
if (!$sourceDir || !$uploadDir) die("errors: source/upload folder missing\n");

$conn->query("INSERT IGNORE INTO categories (category_name, slug, description, status) VALUES ('Sofa', 'sofa', 'Premium sofas for luxury living rooms.', 'active')");
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
    ["Aurelio Velvet Sofa", 54999, 46999, "velvet upholstery"],
    ["Verona L-Shaped Sofa", 74999, 64999, "L-shaped seating"],
    ["Milan Luxe Sectional Sofa", 89999, 77999, "sectional sofa layout"],
    ["Nordic Fabric Sofa", 45999, 38999, "fabric upholstery"],
    ["Imperial Chesterfield Sofa", 82999, 71999, "chesterfield styling"],
    ["Royal Crest Premium Sofa", 69999, 59999, "premium sofa comfort"],
    ["Celeste Designer Sofa", 58999, 49999, "designer living room look"],
    ["Monarch Cushioned Sofa", 52999, 44999, "deep cushioned seating"],
    ["Regal Modern Sofa", 49999, 41999, "modern sofa finish"],
    ["Florence Luxe Three Seater Sofa", 57999, 48999, "three seater comfort"],
    ["Noble Premium Sofa", 44999, 37999, "premium compact sofa"],
    ["Eterno Luxury Sofa", 64999, 54999, "luxury sofa finish"],
    ["Casa Royale Lounge Sofa", 79999, 68999, "lounge sofa comfort"],
    ["Opulence Statement Sofa", 92999, 81999, "statement living sofa"],
    ["Serene Minimal Sofa", 39999, 32999, "minimal sofa design"],
    ["Valencia Upholstered Sofa", 55999, 47999, "upholstered finish"],
    ["Aurelia Premium Loveseat Sofa", 42999, 35999, "loveseat seating"],
    ["Milano Designer Sofa", 61999, 52999, "designer sofa"],
    ["Verona Comfort Sofa", 48999, 40999, "comfort seating"],
    ["Imperial Tufted Sofa", 75999, 65999, "tufted cushion style"],
    ["Nordic Modern Fabric Sofa", 46999, 39999, "modern fabric finish"],
    ["Royal Crest Sectional Sofa", 84999, 73999, "sectional seating"],
    ["Celeste Premium Sofa", 52999, 44999, "premium sofa"],
    ["Monarch Luxe Sofa", 68999, 58999, "luxe living sofa"],
    ["Regal Designer Couch", 49999, 41999, "designer couch style"],
    ["Florence Plush Sofa", 59999, 50999, "plush cushioning"],
    ["Noble Living Room Sofa", 45999, 38999, "living room sofa"],
    ["Eterno Premium Couch", 54999, 46999, "premium couch"],
    ["Casa Royale Fabric Sofa", 62999, 53999, "fabric sofa finish"],
    ["Opulence Curved Sofa", 94999, 82999, "curved sofa style"],
    ["Serene Compact Sofa", 38999, 31999, "compact sofa"],
    ["Valencia Luxe Sofa", 66999, 56999, "luxury sofa"],
    ["Aurelio Grand Sectional Sofa", 99999, 87999, "grand sectional design"]
];

foreach ($files as $index => $file) {
    $item = $products[$index] ?? ["Zafiro Premium Sofa " . ($index + 1), 44999 + ($index * 1500), 37999 + ($index * 1300), "premium sofa"];
    [$name, $originalPrice, $discountPrice, $feature] = $item;
    $sku = "ZC-SOFA-" . str_pad((string) ($index + 1), 3, "0", STR_PAD_LEFT);
    $check = $conn->prepare("SELECT id FROM products WHERE name = ? OR sku = ? LIMIT 1");
    $check->bind_param("ss", $name, $sku);
    $check->execute();
    if ($check->get_result()->num_rows > 0) { $skipped++; continue; }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $targetName = "sofa_" . time() . "_" . str_pad((string) ($index + 1), 2, "0", STR_PAD_LEFT) . "." . $ext;
    if (!copy($file, $uploadDir . DIRECTORY_SEPARATOR . $targetName)) { $errors[] = "copy failed: $file"; continue; }

    $image = "../uploads/products/" . $targetName;
    $price = $discountPrice;
    $stock = 4 + ($index % 12);
    $short = "$name brings refined living room comfort with $feature.";
    $full = "$short Designed with premium upholstery, supportive cushioning, balanced proportions, and Zafiro Casa luxury finish.";
    $slug = sofaSlug($name);
    $brand = "Zafiro Casa";
    $specifications = "Premium sofa, cushioned seating, luxury upholstery, living room use.";
    $material = "Premium fabric, velvet, foam, wood or metal finish";
    $color = "Premium finish";
    $dimensions = "Sofa standard size";
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

$cat = $conn->query("SELECT id, slug FROM categories WHERE category_name='Sofa' LIMIT 1")->fetch_assoc();
$total = $conn->query("SELECT COUNT(*) total FROM products WHERE category = 'Sofa'")->fetch_assoc()["total"] ?? 0;
echo "sofa_category_id=" . ($cat["id"] ?? "not_found") . "\n";
echo "sofa_category_slug=" . ($cat["slug"] ?? "not_found") . "\n";
echo "source_images=" . count($files) . "\n";
echo "inserted=$inserted\n";
echo "skipped_duplicates=$skipped\n";
echo "sofa_total=$total\n";
echo "errors=" . ($errors ? implode(" | ", $errors) : "none") . "\n";
?>
