<?php
if (php_sapi_name() !== "cli") include("auth.php");
header("Content-Type: text/plain");
include(__DIR__ . "/../backend/config/db.php");
include_once(__DIR__ . "/../backend/includes/product_images.php");

$sourceDir = realpath(__DIR__ . "/../Categories furniture/Lamps");
$uploadDir = realpath(__DIR__ . "/../uploads/products");
$category = "Lamps";
$errors = [];
$inserted = 0;
$skipped = 0;

function lampSlug($text) {
    return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
}
if (!$sourceDir || !$uploadDir) die("errors: source/upload folder missing\n");

$conn->query("INSERT IGNORE INTO categories (category_name, slug, description, status) VALUES ('Lamps', 'lamps', 'Premium table lamps, floor lamps and designer decorative lighting.', 'active')");
ensureProductImageColumns($conn);
ensureProductImagesSchema($conn);

$files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $fileInfo) {
    if ($fileInfo->isFile() && preg_match('/\.(jpg|jpeg|png|webp)$/i', $fileInfo->getFilename())) $files[] = $fileInfo->getPathname();
}

$products = [
    ["Aurelio Designer Table Lamp", 6999, 5799, "designer table lamp with warm lighting"],
    ["Verona Premium Study Lamp", 3999, 3199, "premium study lamp"],
    ["Milan Cozy Table Lamp", 5499, 4499, "cozy table lamp for decor corners"],
    ["Nordic Luxury Table Lamp", 4999, 3999, "luxury table lamp"],
    ["Imperial Designer Lighting Lamp", 8999, 7499, "designer lighting lamp"],
    ["Royal Crest Bedside Lamp", 6499, 5299, "bedside lamp with premium glow"],
    ["Celeste Modern Luxury Lamp", 7499, 6199, "modern luxury finish lamp"],
    ["Monarch Premium Floor Lamp", 10999, 8999, "premium floor lamp"],
    ["Regal Decorative Lamp", 5999, 4899, "decorative lamp with elegant style"],
    ["Florence Warm Glow Lamp", 4499, 3599, "warm lighting lamp"],
    ["Noble Designer Shade Lamp", 7999, 6499, "designer shade lamp"],
    ["Eterno Luxe Table Lamp", 6999, 5799, "luxe table lamp"],
    ["Casa Royale Accent Lamp", 8499, 6999, "accent lamp for luxury interiors"],
    ["Opulence Bloom Lamp", 9999, 8499, "floral-inspired decorative lamp"],
    ["Serene Vortex Lamp", 7499, 6199, "geometric designer lamp"],
    ["Heritage Moonlit Decor Lamp", 5499, 4499, "moonlit decorative lamp"],
    ["Valencia Golden Tree Lamp", 8999, 7499, "golden tree-style LED lamp"],
    ["Aurelia Floral Wall Lamp", 7999, 6499, "floral wall lamp"],
    ["Milano Cherry Blossom Lamp", 6999, 5799, "cherry blossom night lamp"],
    ["Verona Ramadan Decor Lamp", 4999, 3999, "decorative lighting lamp"],
    ["Imperial Vortex Table Lamp", 7499, 6199, "unique geometric table lamp"],
    ["Nordic Premium Bedside Lamp", 4599, 3699, "premium bedside lamp"],
    ["Royal Crest Bloom Lamp", 8999, 7499, "luxury bloom lamp"],
    ["Celeste Soft Glow Lamp", 3999, 3199, "soft glow decorative lamp"],
    ["Monarch Modern Decor Lamp", 5499, 4499, "modern decor lamp"],
    ["Regal Moon Lamp", 6499, 5299, "moon-inspired lamp"],
    ["Florence Art Nouveau Lamp", 8999, 7499, "art nouveau flower lamp"]
];

foreach ($files as $index => $file) {
    $item = $products[$index] ?? ["Zafiro Premium Lamp " . ($index + 1), 4999 + ($index * 350), 3999 + ($index * 300), "premium lamp"];
    [$name, $originalPrice, $discountPrice, $feature] = $item;
    $sku = "ZC-LAMP-" . str_pad((string) ($index + 1), 3, "0", STR_PAD_LEFT);
    $check = $conn->prepare("SELECT id FROM products WHERE name = ? OR sku = ? LIMIT 1");
    $check->bind_param("ss", $name, $sku);
    $check->execute();
    if ($check->get_result()->num_rows > 0) { $skipped++; continue; }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $targetName = "lamps_" . time() . "_" . str_pad((string) ($index + 1), 2, "0", STR_PAD_LEFT) . "." . $ext;
    if (!copy($file, $uploadDir . DIRECTORY_SEPARATOR . $targetName)) { $errors[] = "copy failed: $file"; continue; }

    $image = "../uploads/products/" . $targetName;
    $price = $discountPrice;
    $stock = 8 + ($index % 18);
    $short = "$name adds refined ambience with $feature.";
    $full = "$short Designed for bedrooms, living rooms and decor corners with warm lighting, premium finish, and Zafiro Casa luxury styling.";
    $slug = lampSlug($name);
    $brand = "Zafiro Casa";
    $specifications = "Premium lamp, warm lighting, designer shade or frame, easy placement.";
    $material = "Premium lamp finish";
    $color = "Premium finish";
    $dimensions = "Lamp standard size";
    $weight = "Lightweight decor";
    $seating = "";
    $roomType = "Decor";
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

$cat = $conn->query("SELECT id, slug FROM categories WHERE category_name='Lamps' LIMIT 1")->fetch_assoc();
$total = $conn->query("SELECT COUNT(*) total FROM products WHERE category = 'Lamps'")->fetch_assoc()["total"] ?? 0;
echo "lamps_category_id=" . ($cat["id"] ?? "not_found") . "\n";
echo "lamps_category_slug=" . ($cat["slug"] ?? "not_found") . "\n";
echo "source_images=" . count($files) . "\n";
echo "inserted=$inserted\n";
echo "skipped_duplicates=$skipped\n";
echo "lamps_total=$total\n";
echo "errors=" . ($errors ? implode(" | ", $errors) : "none") . "\n";
?>
