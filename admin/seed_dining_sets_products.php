<?php
if (php_sapi_name() !== "cli") include("auth.php");
header("Content-Type: text/plain");
include(__DIR__ . "/../backend/config/db.php");
include_once(__DIR__ . "/../backend/includes/product_images.php");

$sourceDir = realpath(__DIR__ . "/../Categories furniture/dining sets");
$uploadDir = realpath(__DIR__ . "/../uploads/products");
$category = "Dining Sets";
$errors = [];
$inserted = 0;
$skipped = 0;

function diningSetSlug($text) {
    return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
}
if (!$sourceDir || !$uploadDir) die("errors: source/upload folder missing\n");

$conn->query("INSERT IGNORE INTO categories (category_name, slug, description, status) VALUES ('Dining Sets', 'dining-sets', 'Premium dining table sets with coordinated chairs and luxury finishes.', 'active')");
ensureProductImageColumns($conn);
ensureProductImagesSchema($conn);

$files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $fileInfo) {
    if ($fileInfo->isFile() && preg_match('/\.(jpg|jpeg|png|webp)$/i', $fileInfo->getFilename())) $files[] = $fileInfo->getPathname();
}

$products = [
    ["Aurelio 6-Seater Dining Set", 58999, 52999, "6-seater dining table set"],
    ["Verona Premium Dining Table Set", 64999, 57999, "premium dining table with coordinated chairs"],
    ["Milan Luxe Dining Set", 72999, 64999, "luxury dining set for modern interiors"],
    ["Nordic 4-Seater Dining Set", 44999, 39999, "compact 4-seater dining set"],
    ["Imperial Designer Dining Set", 84999, 75999, "designer dining set with statement appeal"],
    ["Royal Crest Luxury Dining Set", 92999, 82999, "luxury dining set with refined proportions"],
    ["Celeste Family Dining Set", 54999, 48999, "family dining set with balanced comfort"],
    ["Monarch Premium Dining Set", 69999, 61999, "premium dining set for elegant dining rooms"],
    ["Regal Statement Dining Set", 87999, 78999, "statement dining table set"],
    ["Florence Elegant Dining Set", 59999, 53999, "elegant dining set with premium finish"],
    ["Noble Compact Dining Set", 42999, 37999, "compact dining table set"],
    ["Eterno Luxe Dining Table Set", 74999, 66999, "luxe dining table set"],
    ["Casa Royale Dining Set", 99999, 89999, "royal dining set for premium homes"],
    ["Opulence Designer Dining Set", 89999, 79999, "designer dining set with luxury presence"]
];

foreach ($files as $index => $file) {
    $item = $products[$index] ?? ["Zafiro Premium Dining Set " . ($index + 1), 59999 + ($index * 2500), 52999 + ($index * 2200), "premium dining set"];
    [$name, $originalPrice, $discountPrice, $feature] = $item;
    $sku = "ZC-DSET-" . str_pad((string) ($index + 1), 3, "0", STR_PAD_LEFT);
    $check = $conn->prepare("SELECT id FROM products WHERE name = ? OR sku = ? LIMIT 1");
    $check->bind_param("ss", $name, $sku);
    $check->execute();
    if ($check->get_result()->num_rows > 0) { $skipped++; continue; }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $targetName = "dining_set_" . time() . "_" . str_pad((string) ($index + 1), 2, "0", STR_PAD_LEFT) . "." . $ext;
    if (!copy($file, $uploadDir . DIRECTORY_SEPARATOR . $targetName)) { $errors[] = "copy failed: $file"; continue; }

    $image = "../uploads/products/" . $targetName;
    $price = $discountPrice;
    $stock = 3 + ($index % 8);
    $short = "$name offers a complete premium dining experience with $feature.";
    $full = "$short Built for refined family dining with durable construction, coordinated seating, elegant finish, and Zafiro Casa luxury styling.";
    $slug = diningSetSlug($name);
    $brand = "Zafiro Casa";
    $specifications = "Premium dining table set, coordinated chairs, durable frame, luxury finish.";
    $material = "Premium wood, upholstery, marble or metal finish";
    $color = "Premium finish";
    $dimensions = "Dining set standard size";
    $weight = "Heavy furniture weight";
    $seating = ($index % 3 === 0 ? "6 Seater" : "4 Seater");
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

$cat = $conn->query("SELECT id, slug FROM categories WHERE category_name='Dining Sets' LIMIT 1")->fetch_assoc();
$total = $conn->query("SELECT COUNT(*) total FROM products WHERE category = 'Dining Sets'")->fetch_assoc()["total"] ?? 0;
echo "dining_sets_category_id=" . ($cat["id"] ?? "not_found") . "\n";
echo "dining_sets_category_slug=" . ($cat["slug"] ?? "not_found") . "\n";
echo "source_images=" . count($files) . "\n";
echo "inserted=$inserted\n";
echo "skipped_duplicates=$skipped\n";
echo "dining_sets_total=$total\n";
echo "errors=" . ($errors ? implode(" | ", $errors) : "none") . "\n";
?>
