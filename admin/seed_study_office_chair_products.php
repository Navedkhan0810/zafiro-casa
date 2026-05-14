<?php
if (php_sapi_name() !== "cli") include("auth.php");
header("Content-Type: text/plain");
include(__DIR__ . "/../backend/config/db.php");
include_once(__DIR__ . "/../backend/includes/product_images.php");

$sourceDir = realpath(__DIR__ . "/../Categories furniture/study chair/study chair");
$uploadDir = realpath(__DIR__ . "/../uploads/products");
$category = "Study & Office Chair";
$errors = [];
$inserted = 0;
$skipped = 0;

function studyOfficeChairSlug($text) {
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
    ["Aurelio Ergonomic Office Chair", 18999, 15499, "ergonomic support"],
    ["Verona Executive Study Chair", 24999, 20999, "executive design"],
    ["Milan Luxe Mesh Office Chair", 16999, 13499, "mesh back comfort"],
    ["Nordic Adjustable Study Chair", 14999, 11999, "adjustable height"],
    ["Imperial High Back Office Chair", 28999, 23999, "high back support"],
    ["Royal Crest Premium Work Chair", 21999, 17999, "premium work chair"],
    ["Celeste Lumbar Support Chair", 15999, 12499, "lumbar support"],
    ["Monarch Rolling Office Chair", 17999, 14499, "rolling wheels"],
    ["Regal Cushioned Study Chair", 13999, 10999, "cushioned seating"],
    ["Florence Designer Work Chair", 19999, 16499, "designer work chair"],
    ["Noble Mesh Study Chair", 12999, 9999, "mesh back"],
    ["Eterno Luxe Office Chair", 23999, 19999, "luxury office chair"],
    ["Casa Royale Executive Chair", 26999, 22999, "executive seating"],
    ["Opulence Premium Office Chair", 29999, 24999, "premium office chair"],
    ["Serene Compact Study Chair", 11999, 8999, "compact study chair"],
    ["Valencia Adjustable Work Chair", 16999, 13499, "adjustable work chair"],
    ["Aurelia Ergonomic Study Chair", 15999, 12499, "ergonomic study chair"],
    ["Milano Premium Task Chair", 18999, 15499, "premium task chair"],
    ["Verona Designer Office Chair", 21999, 17999, "designer office chair"],
    ["Imperial Luxe Work Chair", 24999, 20999, "luxe work chair"]
];

foreach ($files as $index => $file) {
    $item = $products[$index] ?? ["Zafiro Premium Study Office Chair " . ($index + 1), 12999 + ($index * 700), 9999 + ($index * 650), "premium study chair"];
    [$name, $originalPrice, $discountPrice, $feature] = $item;
    $sku = "ZC-SOCHR-" . str_pad((string) ($index + 1), 3, "0", STR_PAD_LEFT);
    $check = $conn->prepare("SELECT id FROM products WHERE name = ? OR sku = ? LIMIT 1");
    $check->bind_param("ss", $name, $sku);
    $check->execute();
    if ($check->get_result()->num_rows > 0) { $skipped++; continue; }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $targetName = "study_office_chair_" . time() . "_" . str_pad((string) ($index + 1), 2, "0", STR_PAD_LEFT) . "." . $ext;
    if (!copy($file, $uploadDir . DIRECTORY_SEPARATOR . $targetName)) { $errors[] = "copy failed: $file"; continue; }

    $image = "../uploads/products/" . $targetName;
    $price = $discountPrice;
    $stock = 6 + ($index % 14);
    $short = "$name supports focused work with $feature.";
    $full = "$short Designed for study and office use with cushioned seating, stable base, ergonomic comfort, and Zafiro Casa premium finish.";
    $slug = studyOfficeChairSlug($name);
    $brand = "Zafiro Casa";
    $specifications = "Premium study office chair, ergonomic seating, work or study use, durable base.";
    $material = "Premium fabric, mesh, foam, metal or engineered frame";
    $color = "Premium finish";
    $dimensions = "Office chair standard size";
    $weight = "Standard chair weight";
    $seating = "1 Seater";
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

$cat = $conn->query("SELECT id, slug FROM categories WHERE category_name='Study & Office Chair' LIMIT 1")->fetch_assoc();
$total = $conn->query("SELECT COUNT(*) total FROM products WHERE category = 'Study & Office Chair'")->fetch_assoc()["total"] ?? 0;
echo "study_office_chair_category_id=" . ($cat["id"] ?? "not_found") . "\n";
echo "study_office_chair_category_slug=" . ($cat["slug"] ?? "not_found") . "\n";
echo "source_images=" . count($files) . "\n";
echo "inserted=$inserted\n";
echo "skipped_duplicates=$skipped\n";
echo "study_office_chair_total=$total\n";
echo "errors=" . ($errors ? implode(" | ", $errors) : "none") . "\n";
?>
