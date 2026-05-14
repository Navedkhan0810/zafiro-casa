<?php
if (php_sapi_name() !== "cli") include("auth.php");
header("Content-Type: text/plain");
include(__DIR__ . "/../backend/config/db.php");
include_once(__DIR__ . "/../backend/includes/product_images.php");

$sourceDir = realpath(__DIR__ . "/../Categories furniture/chair/chairs 🪑/chair 🪑");
$uploadDir = realpath(__DIR__ . "/../uploads/products");
$category = "Chair";
$errors = [];
$inserted = 0;
$skipped = 0;

function chairSlug($text) {
    return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
}
if (!$sourceDir || !$uploadDir) die("errors: source/upload folder missing\n");

$conn->query("INSERT IGNORE INTO categories (category_name, slug, description, status) VALUES ('Chair', 'chair', 'Premium accent, lounge and upholstered chairs.', 'active')");
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
    ["Aurelio Accent Chair", 18999, 15499, "accent chair styling"],
    ["Verona Wooden Armchair", 22999, 18999, "wooden armrest design"],
    ["Milan Luxe Lounge Chair", 26999, 22999, "lounge comfort"],
    ["Nordic Fabric Chair", 14999, 11999, "fabric upholstery"],
    ["Imperial Tufted Chair", 24999, 20999, "tufted cushion finish"],
    ["Royal Crest Premium Chair", 28999, 23999, "premium chair comfort"],
    ["Celeste Designer Chair", 17999, 14499, "designer chair profile"],
    ["Monarch Cushioned Chair", 15999, 12499, "cushioned seating"],
    ["Regal Modern Armchair", 21999, 17999, "modern armchair style"],
    ["Florence Lounge Chair", 23999, 19999, "lounge seating comfort"],
    ["Noble Upholstered Chair", 16999, 13499, "upholstered finish"],
    ["Eterno Luxe Chair", 25999, 21999, "luxury chair finish"],
    ["Casa Royale Accent Armchair", 29999, 24999, "accent armchair"],
    ["Opulence Premium Lounge Chair", 32999, 27999, "premium lounge chair"],
    ["Serene Compact Chair", 12999, 9999, "compact chair design"],
    ["Valencia Designer Armchair", 24999, 20999, "designer armchair"],
    ["Aurelia Fabric Accent Chair", 18999, 15499, "fabric accent chair"],
    ["Milano Premium Chair", 20999, 16999, "premium chair"],
    ["Verona Luxe Lounge Chair", 27999, 23999, "luxe lounge chair"],
    ["Imperial Modern Chair", 19999, 16499, "modern chair design"]
];

foreach ($files as $index => $file) {
    $item = $products[$index] ?? ["Zafiro Premium Chair " . ($index + 1), 14999 + ($index * 700), 11999 + ($index * 650), "premium chair"];
    [$name, $originalPrice, $discountPrice, $feature] = $item;
    $sku = "ZC-CHMAIN-" . str_pad((string) ($index + 1), 3, "0", STR_PAD_LEFT);
    $check = $conn->prepare("SELECT id FROM products WHERE name = ? OR sku = ? LIMIT 1");
    $check->bind_param("ss", $name, $sku);
    $check->execute();
    if ($check->get_result()->num_rows > 0) { $skipped++; continue; }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $targetName = "chair_" . time() . "_" . str_pad((string) ($index + 1), 2, "0", STR_PAD_LEFT) . "." . $ext;
    if (!copy($file, $uploadDir . DIRECTORY_SEPARATOR . $targetName)) { $errors[] = "copy failed: $file"; continue; }

    $image = "../uploads/products/" . $targetName;
    $price = $discountPrice;
    $stock = 6 + ($index % 14);
    $short = "$name adds refined seating with $feature.";
    $full = "$short Designed for living spaces with supportive cushioning, premium finish, balanced legs or armrests, and Zafiro Casa luxury styling.";
    $slug = chairSlug($name);
    $brand = "Zafiro Casa";
    $specifications = "Premium chair, cushioned comfort, accent or lounge use, luxury finish.";
    $material = "Premium fabric, foam, wood or metal finish";
    $color = "Premium finish";
    $dimensions = "Chair standard size";
    $weight = "Standard chair weight";
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
    if ($stmt->execute()) { syncProductImageColumnsToTable($conn, (int) $stmt->insert_id, [$image]); $inserted++; }
    else { $errors[] = "$name: " . $stmt->error; }
}

$cat = $conn->query("SELECT id, slug FROM categories WHERE category_name='Chair' LIMIT 1")->fetch_assoc();
$total = $conn->query("SELECT COUNT(*) total FROM products WHERE category = 'Chair'")->fetch_assoc()["total"] ?? 0;
echo "chair_category_id=" . ($cat["id"] ?? "not_found") . "\n";
echo "chair_category_slug=" . ($cat["slug"] ?? "not_found") . "\n";
echo "source_images=" . count($files) . "\n";
echo "inserted=$inserted\n";
echo "skipped_duplicates=$skipped\n";
echo "chair_total=$total\n";
echo "errors=" . ($errors ? implode(" | ", $errors) : "none") . "\n";
?>
