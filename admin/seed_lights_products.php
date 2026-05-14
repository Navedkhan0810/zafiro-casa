<?php
if (php_sapi_name() !== "cli") include("auth.php");
header("Content-Type: text/plain");
include(__DIR__ . "/../backend/config/db.php");
include_once(__DIR__ . "/../backend/includes/product_images.php");

$sourceDir = realpath(__DIR__ . "/../Categories furniture/Lights");
$uploadDir = realpath(__DIR__ . "/../uploads/products");
$category = "Lights";
$errors = [];
$inserted = 0;
$skipped = 0;

function lightSlug($text) {
    return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
}

if (!$sourceDir || !$uploadDir) die("errors: source/upload folder missing\n");

$conn->query("INSERT IGNORE INTO categories (category_name, slug, description, status) VALUES ('Lights', 'lights', 'Premium ceiling, wall, pendant and chandelier lights for luxury interiors.', 'active')");
ensureProductImageColumns($conn);
ensureProductImagesSchema($conn);

$files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $fileInfo) {
    if ($fileInfo->isFile() && preg_match('/\.(jpg|jpeg|png|webp)$/i', $fileInfo->getFilename())) $files[] = $fileInfo->getPathname();
}

$products = [
    ["Aurelio Gold Ceiling Light", 12999, 9999, "gold finish ceiling light"],
    ["Verona Crystal Chandelier Light", 25999, 21999, "crystal chandelier for statement interiors"],
    ["Milan Luxe Wall Light", 8999, 6999, "luxury wall light with warm ambient glow"],
    ["Nordic Pendant Light", 7499, 5799, "modern pendant light"],
    ["Imperial LED Hanging Light", 11999, 9299, "LED hanging light with refined finish"],
    ["Royal Crest Designer Light", 9999, 7999, "designer decorative light"],
    ["Celeste Warm Ambient Wall Light", 8499, 6499, "soft ambient wall light"],
    ["Monarch Crystal Ceiling Light", 17999, 14999, "crystal ceiling light"],
    ["Regal Staircase Accent Light", 6999, 5499, "staircase accent light"],
    ["Florence Soft Glow Wall Light", 5999, 4599, "soft glow wall light"],
    ["Noble Modern Pendant Light", 7999, 6199, "modern pendant light"],
    ["Eterno Designer Ceiling Light", 10999, 8499, "designer ceiling light"],
    ["Casa Royale Spiral Chandelier", 28999, 23999, "spiral chandelier for high ceilings"],
    ["Opulence High Ceiling Chandelier", 32999, 27999, "large high ceiling chandelier"],
    ["Serene Wall Niche Light", 5499, 4299, "minimal wall niche lighting"],
    ["Valencia Premium Designer Light", 9499, 7499, "premium designer light"]
];

foreach ($files as $index => $file) {
    $item = $products[$index] ?? ["Zafiro Premium Designer Light " . ($index + 1), 6999 + ($index * 400), 5499 + ($index * 350), "premium designer light"];
    [$name, $originalPrice, $discountPrice, $feature] = $item;
    $sku = "ZC-LIGHT-" . str_pad((string) ($index + 1), 3, "0", STR_PAD_LEFT);
    $check = $conn->prepare("SELECT id FROM products WHERE name = ? OR sku = ? LIMIT 1");
    $check->bind_param("ss", $name, $sku);
    $check->execute();
    if ($check->get_result()->num_rows > 0) { $skipped++; continue; }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $targetName = "lights_" . time() . "_" . str_pad((string) ($index + 1), 2, "0", STR_PAD_LEFT) . "." . $ext;
    if (!copy($file, $uploadDir . DIRECTORY_SEPARATOR . $targetName)) { $errors[] = "copy failed: $file"; continue; }

    $image = "../uploads/products/" . $targetName;
    $price = $discountPrice;
    $stock = 8 + ($index % 16);
    $short = "$name brings a premium lighting statement with $feature.";
    $full = "$short Crafted for luxury homes with warm illumination, balanced proportions, and an elegant Zafiro Casa finish for ceilings, walls, foyers, or decor corners.";
    $slug = lightSlug($name);
    $brand = "Zafiro Casa";
    $specifications = "Premium light, warm ambient glow, designer frame or shade, luxury interior use.";
    $material = "Premium metal, crystal, glass or LED finish";
    $color = "Premium finish";
    $dimensions = "Designer lighting standard size";
    $weight = "Standard lighting weight";
    $seating = "";
    $roomType = "Decor";
    $assembly = "No";
    $featured = $index < 3 ? 1 : 0;
    $trending = $index % 4 === 0 ? 1 : 0;
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

$cat = $conn->query("SELECT id, slug FROM categories WHERE category_name='Lights' LIMIT 1")->fetch_assoc();
$total = $conn->query("SELECT COUNT(*) total FROM products WHERE category = 'Lights'")->fetch_assoc()["total"] ?? 0;
echo "lights_category_id=" . ($cat["id"] ?? "not_found") . "\n";
echo "lights_category_slug=" . ($cat["slug"] ?? "not_found") . "\n";
echo "source_images=" . count($files) . "\n";
echo "inserted=$inserted\n";
echo "skipped_duplicates=$skipped\n";
echo "lights_total=$total\n";
echo "errors=" . ($errors ? implode(" | ", $errors) : "none") . "\n";
?>
