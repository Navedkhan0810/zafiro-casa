<?php
if (php_sapi_name() !== "cli") include("auth.php");
header("Content-Type: text/plain");
include(__DIR__ . "/../backend/config/db.php");
include_once(__DIR__ . "/../backend/includes/product_images.php");

$sourceDir = realpath(__DIR__ . "/../Categories furniture/table");
$uploadDir = realpath(__DIR__ . "/../uploads/products");
$category = "Table";
$errors = [];
$inserted = 0;
$skipped = 0;

function tableSlug($text) {
    return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
}
if (!$sourceDir || !$uploadDir) die("errors: source/upload folder missing\n");

$conn->query("INSERT IGNORE INTO categories (category_name, slug, description, status) VALUES ('Table', 'table', 'Premium accent, coffee and side tables for luxury interiors.', 'active')");
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
    ["Aurelio Marble Top Table", 24999, 20999, "marble top finish"],
    ["Verona Walnut Accent Table", 18999, 15499, "walnut accent design"],
    ["Milan Luxe Coffee Table", 29999, 24999, "luxury coffee table style"],
    ["Nordic Oak Side Table", 14999, 11999, "oak side table finish"],
    ["Imperial Glass Top Table", 21999, 17999, "glass top design"],
    ["Royal Crest Premium Table", 26999, 22999, "premium designer finish"],
    ["Celeste Modern Accent Table", 15999, 12499, "modern accent table"],
    ["Monarch Metal Leg Table", 19999, 16499, "metal leg structure"],
    ["Regal Designer Center Table", 27999, 23999, "designer center table"],
    ["Florence Luxury Side Table", 16999, 13499, "luxury side table"],
    ["Noble Wooden Coffee Table", 22999, 18999, "wooden finish table"],
    ["Eterno Premium Designer Table", 24999, 20999, "premium designer table"],
    ["Casa Royale Storage Table", 31999, 26999, "storage drawer table"],
    ["Opulence Sculpted Accent Table", 28999, 24999, "sculpted accent design"],
    ["Serene Minimal Coffee Table", 13999, 10999, "minimal coffee table"],
    ["Valencia Luxe Console Table", 25999, 21999, "luxe console style"],
    ["Aurelia Modern Round Table", 18999, 15499, "modern round table"],
    ["Milano Premium Accent Table", 20999, 16999, "premium accent table"]
];

foreach ($files as $index => $file) {
    $item = $products[$index] ?? ["Zafiro Premium Table " . ($index + 1), 14999 + ($index * 900), 11999 + ($index * 800), "premium table"];
    [$name, $originalPrice, $discountPrice, $feature] = $item;
    $sku = "ZC-TABLE-" . str_pad((string) ($index + 1), 3, "0", STR_PAD_LEFT);
    $check = $conn->prepare("SELECT id FROM products WHERE name = ? OR sku = ? LIMIT 1");
    $check->bind_param("ss", $name, $sku);
    $check->execute();
    if ($check->get_result()->num_rows > 0) { $skipped++; continue; }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $targetName = "table_" . time() . "_" . str_pad((string) ($index + 1), 2, "0", STR_PAD_LEFT) . "." . $ext;
    if (!copy($file, $uploadDir . DIRECTORY_SEPARATOR . $targetName)) { $errors[] = "copy failed: $file"; continue; }

    $image = "../uploads/products/" . $targetName;
    $price = $discountPrice;
    $stock = 6 + ($index % 14);
    $short = "$name adds refined utility with $feature.";
    $full = "$short Designed for premium interiors with balanced proportions, durable construction, elegant legs, and Zafiro Casa luxury finish.";
    $slug = tableSlug($name);
    $brand = "Zafiro Casa";
    $specifications = "Premium table, accent or coffee use, luxury finish, durable build.";
    $material = "Premium wood, marble, glass, metal or laminate finish";
    $color = "Premium finish";
    $dimensions = "Table standard size";
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

$cat = $conn->query("SELECT id, slug FROM categories WHERE category_name='Table' LIMIT 1")->fetch_assoc();
$total = $conn->query("SELECT COUNT(*) total FROM products WHERE category = 'Table'")->fetch_assoc()["total"] ?? 0;
echo "table_category_id=" . ($cat["id"] ?? "not_found") . "\n";
echo "table_category_slug=" . ($cat["slug"] ?? "not_found") . "\n";
echo "source_images=" . count($files) . "\n";
echo "inserted=$inserted\n";
echo "skipped_duplicates=$skipped\n";
echo "table_total=$total\n";
echo "errors=" . ($errors ? implode(" | ", $errors) : "none") . "\n";
?>
