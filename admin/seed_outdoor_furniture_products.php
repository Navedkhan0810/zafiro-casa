<?php
if (php_sapi_name() !== "cli") include("auth.php");
header("Content-Type: text/plain");
include(__DIR__ . "/../backend/config/db.php");
include_once(__DIR__ . "/../backend/includes/product_images.php");

$sourceDir = realpath(__DIR__ . "/../Categories furniture/outdoor furniture");
$uploadDir = realpath(__DIR__ . "/../uploads/products");
$category = "Outdoor Furniture";
$errors = [];
$inserted = 0;
$skipped = 0;

function outdoorFurnitureSlug($text) {
    return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
}
if (!$sourceDir || !$uploadDir) die("errors: source/upload folder missing\n");

$conn->query("INSERT IGNORE INTO categories (category_name, slug, description, status) VALUES ('Outdoor Furniture', 'outdoor-furniture', 'Premium patio, garden and outdoor seating furniture.', 'active')");
ensureProductImageColumns($conn);
ensureProductImagesSchema($conn);

$files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $fileInfo) {
    if ($fileInfo->isFile() && preg_match('/\.(jpg|jpeg|png|webp)$/i', $fileInfo->getFilename())) $files[] = $fileInfo->getPathname();
}

$products = [
    ["Aurelio Outdoor Lounge Set", 54999, 48999, "premium outdoor lounge seating"],
    ["Verona Rattan Patio Chair", 18999, 15999, "rattan-style patio chair"],
    ["Milan Garden Coffee Table", 16999, 13999, "garden coffee table"],
    ["Nordic Patio Seating Set", 42999, 37999, "compact patio seating set"],
    ["Imperial Outdoor Dining Set", 74999, 66999, "outdoor dining set"],
    ["Royal Crest Patio Sofa Set", 69999, 62999, "patio sofa set"],
    ["Celeste Garden Lounge Chair", 21999, 18499, "garden lounge chair"],
    ["Monarch Outdoor Bench", 24999, 20999, "outdoor bench seating"],
    ["Regal Terrace Seating Set", 49999, 44999, "terrace seating set"],
    ["Florence Premium Patio Set", 57999, 51999, "premium patio set"],
    ["Noble Garden Accent Chair", 17999, 14999, "garden accent chair"],
    ["Eterno Outdoor Coffee Set", 38999, 33999, "outdoor coffee seating set"],
    ["Casa Royale Garden Sofa", 64999, 57999, "garden sofa with luxury comfort"],
    ["Opulence Patio Lounger", 29999, 25999, "premium patio lounger"],
    ["Serene Outdoor Side Table", 13999, 10999, "outdoor side table"],
    ["Heritage Garden Seating Set", 45999, 39999, "garden seating set"],
    ["Valencia Patio Dining Set", 79999, 71999, "patio dining set"],
    ["Aurelia Designer Outdoor Chair", 22999, 18999, "designer outdoor chair"],
    ["Milano Luxury Outdoor Set", 68999, 61999, "luxury outdoor furniture set"],
    ["Verona Premium Garden Set", 52999, 46999, "premium garden furniture set"]
];

foreach ($files as $index => $file) {
    $item = $products[$index] ?? ["Zafiro Premium Outdoor Furniture " . ($index + 1), 39999 + ($index * 1800), 34999 + ($index * 1600), "premium outdoor furniture"];
    [$name, $originalPrice, $discountPrice, $feature] = $item;
    $sku = "ZC-OUTF-" . str_pad((string) ($index + 1), 3, "0", STR_PAD_LEFT);
    $check = $conn->prepare("SELECT id FROM products WHERE name = ? OR sku = ? LIMIT 1");
    $check->bind_param("ss", $name, $sku);
    $check->execute();
    if ($check->get_result()->num_rows > 0) { $skipped++; continue; }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $targetName = "outdoor_furniture_" . time() . "_" . str_pad((string) ($index + 1), 2, "0", STR_PAD_LEFT) . "." . $ext;
    if (!copy($file, $uploadDir . DIRECTORY_SEPARATOR . $targetName)) { $errors[] = "copy failed: $file"; continue; }

    $image = "../uploads/products/" . $targetName;
    $price = $discountPrice;
    $stock = 4 + ($index % 10);
    $short = "$name brings refined outdoor comfort with $feature.";
    $full = "$short Designed for patios, gardens and terraces with weather-friendly usability, durable construction, and Zafiro Casa luxury finish.";
    $slug = outdoorFurnitureSlug($name);
    $brand = "Zafiro Casa";
    $specifications = "Premium outdoor furniture, durable frame, weather-friendly use, luxury finish.";
    $material = "Premium outdoor-friendly furniture finish";
    $color = "Premium finish";
    $dimensions = "Outdoor furniture standard size";
    $weight = "Standard furniture weight";
    $seating = ($index % 4 + 1) . " Seater";
    $roomType = "Outdoor";
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

$cat = $conn->query("SELECT id, slug FROM categories WHERE category_name='Outdoor Furniture' LIMIT 1")->fetch_assoc();
$total = $conn->query("SELECT COUNT(*) total FROM products WHERE category = 'Outdoor Furniture'")->fetch_assoc()["total"] ?? 0;
echo "outdoor_furniture_category_id=" . ($cat["id"] ?? "not_found") . "\n";
echo "outdoor_furniture_category_slug=" . ($cat["slug"] ?? "not_found") . "\n";
echo "source_images=" . count($files) . "\n";
echo "inserted=$inserted\n";
echo "skipped_duplicates=$skipped\n";
echo "outdoor_furniture_total=$total\n";
echo "errors=" . ($errors ? implode(" | ", $errors) : "none") . "\n";
?>
