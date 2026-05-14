<?php
if (php_sapi_name() !== "cli") include("auth.php");
header("Content-Type: text/plain");
include(__DIR__ . "/../backend/config/db.php");
include_once(__DIR__ . "/../backend/includes/product_images.php");

$sourceDir = realpath(__DIR__ . "/../Categories furniture/dining table");
$uploadDir = realpath(__DIR__ . "/../uploads/products");
$category = "Dining Table";
$errors = [];
$inserted = 0;
$skipped = 0;

function diningTableSlug($text) {
    return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
}
if (!$sourceDir || !$uploadDir) die("errors: source/upload folder missing\n");

$conn->query("INSERT IGNORE INTO categories (category_name, slug, description, status) VALUES ('Dining Table', 'dining-table', 'Premium standalone dining tables with marble, wood, glass and designer finishes.', 'active')");
ensureProductImageColumns($conn);
ensureProductImagesSchema($conn);

$files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $fileInfo) {
    if ($fileInfo->isFile() && preg_match('/\.(jpg|jpeg|png|webp)$/i', $fileInfo->getFilename())) $files[] = $fileInfo->getPathname();
}

$products = [
    ["Aurelio Marble Top Dining Table", 52999, 46999, "marble top dining table"],
    ["Verona Premium Dining Table", 44999, 39999, "premium dining table with refined finish"],
    ["Milan Designer Dining Table", 58999, 52999, "designer dining table with luxury presence"],
    ["Nordic Round Dining Table", 39999, 34999, "round dining table for compact dining spaces"],
    ["Imperial Glass Top Dining Table", 62999, 55999, "glass top dining table with premium base"],
    ["Royal Crest Luxury Dining Table", 74999, 66999, "luxury dining table with statement styling"],
    ["Celeste Compact Dining Table", 32999, 28999, "compact dining table with elegant proportions"],
    ["Monarch Rectangular Dining Table", 49999, 43999, "rectangular dining table with durable frame"],
    ["Regal Statement Dining Table", 69999, 62999, "statement dining table for premium interiors"],
    ["Florence Elegant Dining Table", 45999, 40999, "elegant dining table with refined detailing"],
    ["Noble Premium Dining Table", 42999, 37999, "premium dining table for modern homes"],
    ["Eterno Luxe Dining Table", 57999, 51999, "luxe dining table with polished finish"],
    ["Casa Royale Dining Table", 79999, 71999, "royal dining table with luxury scale"],
    ["Opulence Designer Table", 68999, 61999, "designer dining table with opulent styling"],
    ["Serene Modern Dining Table", 36999, 31999, "modern dining table with clean finish"],
    ["Heritage Classic Dining Table", 48999, 42999, "classic dining table with timeless appeal"],
    ["Valencia Premium Table", 54999, 48999, "premium dining table with balanced proportions"],
    ["Aurelia Signature Dining Table", 64999, 57999, "signature dining table for luxury rooms"],
    ["Milano Grande Dining Table", 82999, 73999, "large dining table with premium finish"]
];

foreach ($files as $index => $file) {
    $item = $products[$index] ?? ["Zafiro Premium Dining Table " . ($index + 1), 44999 + ($index * 2200), 39999 + ($index * 1900), "premium dining table"];
    [$name, $originalPrice, $discountPrice, $feature] = $item;
    $sku = "ZC-DTABLE-" . str_pad((string) ($index + 1), 3, "0", STR_PAD_LEFT);
    $check = $conn->prepare("SELECT id FROM products WHERE name = ? OR sku = ? LIMIT 1");
    $check->bind_param("ss", $name, $sku);
    $check->execute();
    if ($check->get_result()->num_rows > 0) { $skipped++; continue; }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $targetName = "dining_table_" . time() . "_" . str_pad((string) ($index + 1), 2, "0", STR_PAD_LEFT) . "." . $ext;
    if (!copy($file, $uploadDir . DIRECTORY_SEPARATOR . $targetName)) { $errors[] = "copy failed: $file"; continue; }

    $image = "../uploads/products/" . $targetName;
    $price = $discountPrice;
    $stock = 3 + ($index % 9);
    $short = "$name brings refined dining style with $feature.";
    $full = "$short Crafted for premium dining rooms with durable construction, elegant tabletop presence, and Zafiro Casa luxury finish.";
    $slug = diningTableSlug($name);
    $brand = "Zafiro Casa";
    $specifications = "Premium dining table, durable frame, luxury tabletop finish, easy maintenance.";
    $material = "Premium wood, marble, glass or metal finish";
    $color = "Premium finish";
    $dimensions = "Dining table standard size";
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

$cat = $conn->query("SELECT id, slug FROM categories WHERE category_name='Dining Table' LIMIT 1")->fetch_assoc();
$total = $conn->query("SELECT COUNT(*) total FROM products WHERE category = 'Dining Table'")->fetch_assoc()["total"] ?? 0;
echo "dining_table_category_id=" . ($cat["id"] ?? "not_found") . "\n";
echo "dining_table_category_slug=" . ($cat["slug"] ?? "not_found") . "\n";
echo "source_images=" . count($files) . "\n";
echo "inserted=$inserted\n";
echo "skipped_duplicates=$skipped\n";
echo "dining_table_total=$total\n";
echo "errors=" . ($errors ? implode(" | ", $errors) : "none") . "\n";
?>
