<?php
if (php_sapi_name() !== "cli") include("auth.php");
header("Content-Type: text/plain");
include(__DIR__ . "/../backend/config/db.php");
include_once(__DIR__ . "/../backend/includes/product_images.php");

$sourceDir = realpath(__DIR__ . "/../Categories furniture/Modular kitchen");
$uploadDir = realpath(__DIR__ . "/../uploads/products");
$category = "Modular Kitchen";
$errors = [];
$inserted = 0;
$skipped = 0;

function modularKitchenSlug($text) {
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
    ["Aurelio Luxury Modular Kitchen", 249999, 219999, "luxury modular kitchen setup"],
    ["Verona Matte Finish Kitchen Unit", 189999, 164999, "matte finish cabinet design"],
    ["Milan Marble Counter Modular Kitchen", 279999, 239999, "marble countertop kitchen"],
    ["Nordic Oak Modular Kitchen", 229999, 199999, "wooden laminate modular kitchen"],
    ["Imperial L-Shaped Kitchen Design", 259999, 224999, "L-shaped kitchen layout"],
    ["Royal Crest Premium Kitchen Setup", 299999, 259999, "premium modular design"],
    ["Celeste Glass Cabinet Kitchen", 239999, 209999, "glass cabinet kitchen setup"],
    ["Monarch Island Counter Kitchen", 329999, 289999, "island counter kitchen"],
    ["Regal Parallel Modular Kitchen", 219999, 189999, "parallel kitchen layout"],
    ["Florence Soft Blue Kitchen Unit", 199999, 174999, "soft blue modular finish"],
    ["Noble Black Luxury Kitchen", 349999, 309999, "black luxury modular kitchen"],
    ["Eterno Wooden Modular Kitchen", 269999, 229999, "wooden finish kitchen setup"],
    ["Casa Royale Marble Wall Kitchen", 319999, 279999, "marble wall kitchen design"],
    ["Opulence LED Modular Kitchen", 289999, 249999, "LED accent modular kitchen"],
    ["Serene Minimal Kitchen Setup", 179999, 154999, "minimal premium kitchen setup"],
    ["Valencia Turquoise Kitchen Design", 229999, 199999, "turquoise kitchen finish"],
    ["Aurelia Modern Cabinet Kitchen", 209999, 184999, "modern cabinet kitchen"],
    ["Milano Luxe Counter Kitchen", 259999, 224999, "luxe counter kitchen"],
    ["Verona Functional Kitchen Unit", 189999, 164999, "functional modular storage"],
    ["Imperial Dramatic Onyx Kitchen", 359999, 319999, "dramatic onyx kitchen finish"],
    ["Nordic Soft Tone Modular Kitchen", 199999, 174999, "soft tone modular kitchen"],
    ["Royal Crest Designer Kitchen", 299999, 259999, "designer kitchen setup"],
    ["Celeste Premium Storage Kitchen", 219999, 189999, "soft-close storage kitchen"],
    ["Monarch Sleek Glass Kitchen", 249999, 219999, "sleek glass cabinet kitchen"],
    ["Regal Contemporary Kitchen Setup", 239999, 209999, "contemporary modular design"],
    ["Florence Elegant Modular Kitchen", 229999, 199999, "elegant modular kitchen"],
    ["Noble Compact Luxury Kitchen", 179999, 154999, "compact luxury kitchen"]
];

foreach ($files as $index => $file) {
    $item = $products[$index] ?? ["Zafiro Premium Modular Kitchen " . ($index + 1), 189999 + ($index * 8000), 159999 + ($index * 7000), "premium modular kitchen"];
    [$name, $originalPrice, $discountPrice, $feature] = $item;
    $sku = "ZC-MK-" . str_pad((string) ($index + 1), 3, "0", STR_PAD_LEFT);
    $check = $conn->prepare("SELECT id FROM products WHERE name = ? OR sku = ? LIMIT 1");
    $check->bind_param("ss", $name, $sku);
    $check->execute();
    if ($check->get_result()->num_rows > 0) { $skipped++; continue; }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $targetName = "modular_kitchen_" . time() . "_" . str_pad((string) ($index + 1), 2, "0", STR_PAD_LEFT) . "." . $ext;
    if (!copy($file, $uploadDir . DIRECTORY_SEPARATOR . $targetName)) { $errors[] = "copy failed: $file"; continue; }

    $image = "../uploads/products/" . $targetName;
    $price = $discountPrice;
    $stock = 3 + ($index % 8);
    $short = "$name creates a premium kitchen experience with $feature.";
    $full = "$short Designed with efficient storage, refined cabinetry, durable counters, soft-close utility, and Zafiro Casa luxury modular styling.";
    $slug = modularKitchenSlug($name);
    $brand = "Zafiro Casa";
    $specifications = "Premium modular kitchen, cabinet storage, countertop setup, soft-close design, custom installation.";
    $material = "Premium laminate, engineered wood, glass, marble or metal finish";
    $color = "Premium finish";
    $dimensions = "Custom modular kitchen size";
    $weight = "Custom installation";
    $seating = "";
    $roomType = "Kitchen";
    $assembly = "Yes";
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

$cat = $conn->query("SELECT id, slug FROM categories WHERE category_name='Modular Kitchen' LIMIT 1")->fetch_assoc();
$total = $conn->query("SELECT COUNT(*) total FROM products WHERE category = 'Modular Kitchen'")->fetch_assoc()["total"] ?? 0;
echo "modular_kitchen_category_id=" . ($cat["id"] ?? "not_found") . "\n";
echo "modular_kitchen_category_slug=" . ($cat["slug"] ?? "not_found") . "\n";
echo "source_images=" . count($files) . "\n";
echo "inserted=$inserted\n";
echo "skipped_duplicates=$skipped\n";
echo "modular_kitchen_total=$total\n";
echo "errors=" . ($errors ? implode(" | ", $errors) : "none") . "\n";
?>
