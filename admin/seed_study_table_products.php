<?php
if (php_sapi_name() !== "cli") include("auth.php");
header("Content-Type: text/plain");
include(__DIR__ . "/../backend/config/db.php");
include_once(__DIR__ . "/../backend/includes/product_images.php");

$sourceDir = realpath(__DIR__ . "/../Categories furniture/study and office tables/study and office tables");
$uploadDir = realpath(__DIR__ . "/../uploads/products");
$category = "Study Table";
$errors = [];
$inserted = 0;
$skipped = 0;

function studyTableSlug($text) {
    return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
}
if (!$sourceDir || !$uploadDir) die("errors: source/upload folder missing\n");

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
    ["Aurelio Wooden Study Table", 24999, 20999, "wooden finish study desk"],
    ["Verona Minimal Study Desk", 18999, 15499, "minimal study desk"],
    ["Milan Luxe Office Study Table", 32999, 27999, "luxury office study table"],
    ["Nordic Oak Workstation Desk", 21999, 17999, "oak workstation desk"],
    ["Imperial Storage Study Table", 34999, 29999, "storage drawers"],
    ["Royal Crest Premium Study Desk", 28999, 23999, "premium study desk"],
    ["Celeste Compact Workstation Table", 16999, 13499, "compact workstation"],
    ["Monarch Designer Study Table", 26999, 22999, "designer workstation table"],
    ["Regal Bookshelf Study Desk", 31999, 26999, "bookshelf attachment"],
    ["Florence Modern Study Table", 19999, 16499, "modern desk setup"],
    ["Noble Drawer Study Desk", 22999, 18999, "drawer storage"],
    ["Eterno Luxe Workstation Desk", 29999, 24999, "luxe workstation"],
    ["Casa Royale Organizer Study Table", 35999, 30999, "study organizer shelves"],
    ["Opulence Premium Office Desk", 38999, 33999, "premium office desk"],
    ["Serene Minimal Work Desk", 14999, 11999, "minimal work desk"],
    ["Valencia Wooden Study Desk", 23999, 19999, "wooden study desk"],
    ["Aurelia Ergonomic Study Table", 27999, 22999, "ergonomic desk design"],
    ["Milano Premium Workstation Table", 32999, 27999, "premium workstation"],
    ["Verona Compact Study Desk", 16999, 13499, "compact study desk"],
    ["Imperial Luxe Study Table", 34999, 29999, "luxury study table"],
    ["Nordic Storage Work Desk", 24999, 20999, "storage work desk"]
];

foreach ($files as $index => $file) {
    $item = $products[$index] ?? ["Zafiro Premium Study Table " . ($index + 1), 18999 + ($index * 900), 14999 + ($index * 800), "premium study table"];
    [$name, $originalPrice, $discountPrice, $feature] = $item;
    $sku = "ZC-STBL-" . str_pad((string) ($index + 1), 3, "0", STR_PAD_LEFT);
    $check = $conn->prepare("SELECT id FROM products WHERE name = ? OR sku = ? LIMIT 1");
    $check->bind_param("ss", $name, $sku);
    $check->execute();
    if ($check->get_result()->num_rows > 0) { $skipped++; continue; }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $targetName = "study_table_" . time() . "_" . str_pad((string) ($index + 1), 2, "0", STR_PAD_LEFT) . "." . $ext;
    if (!copy($file, $uploadDir . DIRECTORY_SEPARATOR . $targetName)) { $errors[] = "copy failed: $file"; continue; }

    $image = "../uploads/products/" . $targetName;
    $price = $discountPrice;
    $stock = 5 + ($index % 14);
    $short = "$name supports focused work with $feature.";
    $full = "$short Designed for study and office spaces with practical storage, refined proportions, durable finish, and Zafiro Casa premium styling.";
    $slug = studyTableSlug($name);
    $brand = "Zafiro Casa";
    $specifications = "Premium study table, workstation use, storage or shelf design, durable finish.";
    $material = "Premium engineered wood, laminate, metal or wood finish";
    $color = "Premium finish";
    $dimensions = "Study table standard size";
    $weight = "Standard furniture weight";
    $seating = "";
    $roomType = "Study";
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

$cat = $conn->query("SELECT id, slug FROM categories WHERE category_name='Study Table' LIMIT 1")->fetch_assoc();
$total = $conn->query("SELECT COUNT(*) total FROM products WHERE category = 'Study Table'")->fetch_assoc()["total"] ?? 0;
echo "study_table_category_id=" . ($cat["id"] ?? "not_found") . "\n";
echo "study_table_category_slug=" . ($cat["slug"] ?? "not_found") . "\n";
echo "source_images=" . count($files) . "\n";
echo "inserted=$inserted\n";
echo "skipped_duplicates=$skipped\n";
echo "study_table_total=$total\n";
echo "errors=" . ($errors ? implode(" | ", $errors) : "none") . "\n";
?>
