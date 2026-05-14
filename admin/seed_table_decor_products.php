<?php
if (php_sapi_name() !== "cli") include("auth.php");
header("Content-Type: text/plain");
include(__DIR__ . "/../backend/config/db.php");
include_once(__DIR__ . "/../backend/includes/product_images.php");

$sourceDir = realpath(__DIR__ . "/../Categories furniture/Table decor/table decor");
$uploadDir = realpath(__DIR__ . "/../uploads/products");
$category = "Table Decor";
$errors = [];
$inserted = 0;
$skipped = 0;

function tableDecorSlug($text) {
    return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
}
if (!$sourceDir || !$uploadDir) die("errors: source/upload folder missing\n");

$conn->query("INSERT IGNORE INTO categories (category_name, slug, description, status) VALUES ('Table Decor', 'table-decor', 'Premium tabletop decor, centerpieces and decorative accents.', 'active')");
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
    ["Aurelio Marble Table Decor", 5999, 4499, "marble finish tabletop accent"],
    ["Verona Crystal Centerpiece", 7999, 6299, "crystal centerpiece"],
    ["Milan Luxe Decorative Tray", 6999, 5499, "luxury decorative tray"],
    ["Nordic Wooden Table Accent", 4499, 3499, "wooden table accent"],
    ["Imperial Gold Table Sculpture", 9999, 7999, "gold metal accent sculpture"],
    ["Royal Crest Premium Table Decor", 7499, 5999, "premium table decor"],
    ["Celeste Designer Table Accessory", 4999, 3899, "designer table accessory"],
    ["Monarch Modern Decorative Piece", 6499, 4999, "modern decorative piece"],
    ["Regal Luxury Table Centerpiece", 8999, 7199, "luxury tabletop centerpiece"],
    ["Florence Premium Decorative Accent", 5499, 4299, "premium decorative accent"],
    ["Noble Crystal Table Accent", 6999, 5499, "crystal decor accent"],
    ["Eterno Marble Decor Tray", 8499, 6799, "marble decor tray"],
    ["Casa Royale Metal Table Decor", 7999, 6299, "metal accent table decor"],
    ["Opulence Sculptural Table Decor", 10999, 8999, "sculptural tabletop design"],
    ["Serene Minimal Table Accent", 3999, 2999, "minimal decorative accent"],
    ["Valencia Luxe Centerpiece", 9499, 7599, "luxe centerpiece"],
    ["Aurelia Designer Decorative Tray", 6499, 4999, "designer decorative tray"],
    ["Milano Premium Table Sculpture", 8999, 7199, "premium table sculpture"],
    ["Verona Modern Table Decor", 5299, 4099, "modern table decor"],
    ["Imperial Luxury Decorative Accent", 7499, 5999, "luxury decorative accent"],
    ["Nordic Premium Table Accessory", 4499, 3499, "premium table accessory"]
];

foreach ($files as $index => $file) {
    $item = $products[$index] ?? ["Zafiro Premium Table Decor " . ($index + 1), 4499 + ($index * 350), 3499 + ($index * 300), "premium table decor"];
    [$name, $originalPrice, $discountPrice, $feature] = $item;
    $sku = "ZC-TDEC-" . str_pad((string) ($index + 1), 3, "0", STR_PAD_LEFT);
    $check = $conn->prepare("SELECT id FROM products WHERE name = ? OR sku = ? LIMIT 1");
    $check->bind_param("ss", $name, $sku);
    $check->execute();
    if ($check->get_result()->num_rows > 0) { $skipped++; continue; }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $targetName = "table_decor_" . time() . "_" . str_pad((string) ($index + 1), 2, "0", STR_PAD_LEFT) . "." . $ext;
    if (!copy($file, $uploadDir . DIRECTORY_SEPARATOR . $targetName)) { $errors[] = "copy failed: $file"; continue; }

    $image = "../uploads/products/" . $targetName;
    $price = $discountPrice;
    $stock = 10 + ($index % 18);
    $short = "$name adds refined tabletop style with $feature.";
    $full = "$short Designed for dining tables, consoles and living spaces with premium texture, modern decorative presence, and Zafiro Casa luxury finish.";
    $slug = tableDecorSlug($name);
    $brand = "Zafiro Casa";
    $specifications = "Premium table decor, centerpiece or decorative accent, luxury tabletop use.";
    $material = "Premium marble, crystal, metal, wood or designer finish";
    $color = "Premium finish";
    $dimensions = "Table decor standard size";
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

$cat = $conn->query("SELECT id, slug FROM categories WHERE category_name='Table Decor' LIMIT 1")->fetch_assoc();
$total = $conn->query("SELECT COUNT(*) total FROM products WHERE category = 'Table Decor'")->fetch_assoc()["total"] ?? 0;
echo "table_decor_category_id=" . ($cat["id"] ?? "not_found") . "\n";
echo "table_decor_category_slug=" . ($cat["slug"] ?? "not_found") . "\n";
echo "source_images=" . count($files) . "\n";
echo "inserted=$inserted\n";
echo "skipped_duplicates=$skipped\n";
echo "table_decor_total=$total\n";
echo "errors=" . ($errors ? implode(" | ", $errors) : "none") . "\n";
?>
