<?php
if (php_sapi_name() !== "cli") include("auth.php");
header("Content-Type: text/plain");
include(__DIR__ . "/../backend/config/db.php");
include_once(__DIR__ . "/../backend/includes/product_images.php");

$sourceDir = realpath(__DIR__ . "/../Categories furniture/Mirrors");
$uploadDir = realpath(__DIR__ . "/../uploads/products");
$category = "Mirrors";
$errors = [];
$inserted = 0;
$skipped = 0;

function mirrorSlug($text) {
    return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
}
if (!$sourceDir || !$uploadDir) die("errors: source/upload folder missing\n");

$conn->query("INSERT IGNORE INTO categories (category_name, slug, description, status) VALUES ('Mirrors', 'mirrors', 'Premium wall, vanity, arch and decorative mirrors for luxury interiors.', 'active')");
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
    ["Aurelio Gold Frame Mirror", 15999, 12999, "gold frame wall mirror"],
    ["Verona Luxury Wall Mirror", 13999, 10999, "luxury wall mounted mirror"],
    ["Milan Round Decorative Mirror", 11999, 9299, "round decorative mirror"],
    ["Nordic Wooden Frame Mirror", 14999, 11999, "wooden border mirror"],
    ["Imperial Designer Vanity Mirror", 18999, 15499, "designer vanity mirror"],
    ["Royal Crest Arch Mirror", 21999, 17999, "arch frame mirror"],
    ["Celeste Full Length Mirror", 24999, 20999, "full length mirror"],
    ["Monarch Sculptural Wavy Mirror", 22999, 18999, "sculptural wavy mirror"],
    ["Regal Frameless LED Mirror", 17999, 14499, "frameless LED mirror"],
    ["Florence Organic Statement Mirror", 19999, 16499, "organic statement mirror"],
    ["Noble Hallway Accent Mirror", 12999, 9999, "hallway accent mirror"],
    ["Eterno Premium Decorative Mirror", 10999, 8499, "premium decorative mirror"],
    ["Casa Royale Dressing Mirror", 26999, 22999, "dressing room mirror"],
    ["Opulence Modern Gold Mirror", 23999, 19999, "modern gold statement mirror"],
    ["Serene Minimalist Wall Mirror", 9999, 7799, "minimalist wall mirror"],
    ["Valencia Bent Floor Mirror", 28999, 24999, "bent floor swivel mirror"],
    ["Aurelia Luxury Vanity Mirror", 15999, 12999, "luxury vanity mirror"],
    ["Milano Designer Wall Mirror", 16999, 13999, "designer wall mirror"],
    ["Verona Sculpted Frame Mirror", 20999, 16999, "sculpted frame mirror"],
    ["Imperial Wooden Accent Mirror", 17999, 14499, "wooden accent mirror"],
    ["Nordic Soft Edge Mirror", 12999, 9999, "soft edge mirror"],
    ["Royal Crest Organic Mirror", 21999, 17999, "organic luxury mirror"],
    ["Celeste Boutique Display Mirror", 19999, 15999, "boutique display mirror"],
    ["Monarch Premium Wall Mirror", 14999, 11999, "premium wall mirror"]
];

foreach ($files as $index => $file) {
    $item = $products[$index] ?? ["Zafiro Premium Mirror " . ($index + 1), 10999 + ($index * 700), 8499 + ($index * 600), "premium mirror"];
    [$name, $originalPrice, $discountPrice, $feature] = $item;
    $sku = "ZC-MIR-" . str_pad((string) ($index + 1), 3, "0", STR_PAD_LEFT);
    $check = $conn->prepare("SELECT id FROM products WHERE name = ? OR sku = ? LIMIT 1");
    $check->bind_param("ss", $name, $sku);
    $check->execute();
    if ($check->get_result()->num_rows > 0) { $skipped++; continue; }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $targetName = "mirrors_" . time() . "_" . str_pad((string) ($index + 1), 2, "0", STR_PAD_LEFT) . "." . $ext;
    if (!copy($file, $uploadDir . DIRECTORY_SEPARATOR . $targetName)) { $errors[] = "copy failed: $file"; continue; }

    $image = "../uploads/products/" . $targetName;
    $price = $discountPrice;
    $stock = 6 + ($index % 15);
    $short = "$name adds refined reflection with $feature.";
    $full = "$short Designed for premium homes with balanced proportions, clear reflection, decorative luxury finish, and Zafiro Casa styling.";
    $slug = mirrorSlug($name);
    $brand = "Zafiro Casa";
    $specifications = "Premium mirror, wall or floor placement, decorative luxury finish, easy maintenance.";
    $material = "Premium mirror glass, wood, metal or frameless finish";
    $color = "Premium finish";
    $dimensions = "Mirror standard size";
    $weight = "Standard decor weight";
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

$cat = $conn->query("SELECT id, slug FROM categories WHERE category_name='Mirrors' LIMIT 1")->fetch_assoc();
$total = $conn->query("SELECT COUNT(*) total FROM products WHERE category = 'Mirrors'")->fetch_assoc()["total"] ?? 0;
echo "mirrors_category_id=" . ($cat["id"] ?? "not_found") . "\n";
echo "mirrors_category_slug=" . ($cat["slug"] ?? "not_found") . "\n";
echo "source_images=" . count($files) . "\n";
echo "inserted=$inserted\n";
echo "skipped_duplicates=$skipped\n";
echo "mirrors_total=$total\n";
echo "errors=" . ($errors ? implode(" | ", $errors) : "none") . "\n";
?>
