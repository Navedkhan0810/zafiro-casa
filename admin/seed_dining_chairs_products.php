<?php
if (php_sapi_name() !== "cli") include("auth.php");
header("Content-Type: text/plain");
include(__DIR__ . "/../backend/config/db.php");
include_once(__DIR__ . "/../backend/includes/product_images.php");

$sourceDir = realpath(__DIR__ . "/../Categories furniture/dining chair");
$uploadDir = realpath(__DIR__ . "/../uploads/products");
$category = "Dining Chairs";
$errors = [];
$inserted = 0;
$skipped = 0;

function diningChairSlug($text) {
    return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
}
if (!$sourceDir || !$uploadDir) die("errors: source/upload folder missing\n");

$conn->query("INSERT IGNORE INTO categories (category_name, slug, description, status) VALUES ('Dining Chairs', 'dining-chair', 'Premium dining chairs with upholstered, wooden and designer finishes.', 'active')");
ensureProductImageColumns($conn);
ensureProductImagesSchema($conn);

$files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $fileInfo) {
    if ($fileInfo->isFile() && preg_match('/\.(jpg|jpeg|png|webp)$/i', $fileInfo->getFilename())) $files[] = $fileInfo->getPathname();
}

$products = [
    ["Aurelio Premium Upholstered Dining Chair", 15999, 13499, "premium upholstered dining chair"],
    ["Verona Designer Dining Chair", 14999, 12499, "designer dining chair with refined finish"],
    ["Milan Luxe Dining Chair", 16999, 14499, "luxury dining chair with cushioned comfort"],
    ["Nordic Dining Arm Chair", 18999, 15999, "dining arm chair with supportive comfort"],
    ["Imperial Tufted Dining Chair", 21999, 18499, "tufted dining chair with padded back"],
    ["Royal Crest Luxury Dining Chair", 22999, 19499, "luxury dining chair for premium table settings"],
    ["Celeste Elegant Dining Chair", 13999, 11999, "elegant dining chair with balanced proportions"],
    ["Monarch Cushioned Dining Chair", 15999, 13499, "cushioned dining chair for daily comfort"],
    ["Regal Premium Dining Seat", 14999, 12499, "premium dining seat with durable frame"],
    ["Florence Designer Dining Chair", 17999, 15499, "designer dining chair for refined interiors"],
    ["Noble Compact Dining Chair", 12999, 10999, "compact dining chair with premium look"],
    ["Eterno Luxe Dining Chair", 18999, 15999, "luxe dining chair with polished detailing"],
    ["Casa Royale Dining Chair", 21999, 18999, "royal dining chair with statement finish"],
    ["Opulence Upholstered Dining Chair", 19999, 16999, "upholstered dining chair with luxury feel"],
    ["Serene Modern Dining Chair", 14999, 12999, "modern dining chair with clean comfort"],
    ["Heritage Classic Dining Chair", 16999, 14499, "classic dining chair with timeless styling"]
];

foreach ($files as $index => $file) {
    $item = $products[$index] ?? ["Zafiro Luxe Dining Chair " . ($index + 1), 14999 + ($index * 650), 12999 + ($index * 550), "premium dining chair"];
    [$name, $originalPrice, $discountPrice, $feature] = $item;
    $sku = "ZC-DCHAIR-" . str_pad((string) ($index + 1), 3, "0", STR_PAD_LEFT);
    $check = $conn->prepare("SELECT id FROM products WHERE name = ? OR sku = ? LIMIT 1");
    $check->bind_param("ss", $name, $sku);
    $check->execute();
    if ($check->get_result()->num_rows > 0) { $skipped++; continue; }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $targetName = "dining_chair_" . time() . "_" . str_pad((string) ($index + 1), 2, "0", STR_PAD_LEFT) . "." . $ext;
    if (!copy($file, $uploadDir . DIRECTORY_SEPARATOR . $targetName)) { $errors[] = "copy failed: $file"; continue; }

    $image = "../uploads/products/" . $targetName;
    $price = $discountPrice;
    $stock = 6 + ($index % 12);
    $short = "$name adds refined table seating with $feature.";
    $full = "$short Built for premium dining rooms with cushioned support, durable frame quality, and Zafiro Casa luxury finish.";
    $slug = diningChairSlug($name);
    $brand = "Zafiro Casa";
    $specifications = "Premium dining chair, cushioned seat, durable frame, luxury finish.";
    $material = "Premium upholstery, wood or metal frame finish";
    $color = "Premium finish";
    $dimensions = "Dining chair standard size";
    $weight = "Standard furniture weight";
    $seating = "1 Seater";
    $roomType = "Dining";
    $assembly = "No";
    $featured = $index < 2 ? 1 : 0;
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

$cat = $conn->query("SELECT id, slug FROM categories WHERE category_name='Dining Chairs' LIMIT 1")->fetch_assoc();
$total = $conn->query("SELECT COUNT(*) total FROM products WHERE category = 'Dining Chairs'")->fetch_assoc()["total"] ?? 0;
echo "dining_chairs_category_id=" . ($cat["id"] ?? "not_found") . "\n";
echo "dining_chairs_category_slug=" . ($cat["slug"] ?? "not_found") . "\n";
echo "source_images=" . count($files) . "\n";
echo "inserted=$inserted\n";
echo "skipped_duplicates=$skipped\n";
echo "dining_chairs_total=$total\n";
echo "errors=" . ($errors ? implode(" | ", $errors) : "none") . "\n";
?>
