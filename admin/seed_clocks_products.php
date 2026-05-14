<?php
if (php_sapi_name() !== "cli") include("auth.php");
header("Content-Type: text/plain");
include(__DIR__ . "/../backend/config/db.php");
include_once(__DIR__ . "/../backend/includes/product_images.php");

$sourceDir = realpath(__DIR__ . "/../Categories furniture/Clocks");
$uploadDir = realpath(__DIR__ . "/../uploads/products");
$category = "Clocks";
$errors = [];
$inserted = 0;
$skipped = 0;

function clockSlug($text) {
    return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
}

if (!$sourceDir || !$uploadDir) die("errors: source/upload folder missing\n");

$conn->query("INSERT IGNORE INTO categories (category_name, slug, description, status) VALUES ('Clocks', 'clocks', 'Premium wall clocks and designer decorative timepieces.', 'active')");
ensureProductImageColumns($conn);
ensureProductImagesSchema($conn);

$files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $fileInfo) {
    if ($fileInfo->isFile() && preg_match('/\.(jpg|jpeg|png|webp)$/i', $fileInfo->getFilename())) $files[] = $fileInfo->getPathname();
}

$products = [
    ["Aurelio Luxury Wall Clock", 6999, 5799, "luxury decorative wall clock design"],
    ["Verona Designer Round Clock", 5499, 4499, "round wall clock with premium finish"],
    ["Milan Luxury Round Clock", 6499, 5299, "designer round profile with silent movement"],
    ["Nordic Premium Wall Clock", 4999, 3999, "minimal wall clock with refined styling"],
    ["Imperial Metal Frame Clock", 7999, 6799, "metal frame wall clock with luxury presence"],
    ["Royal Crest Designer Clock", 8999, 7499, "statement decorative clock for premium interiors"],
    ["Celeste Silent Movement Clock", 4299, 3499, "silent movement wall clock for calm spaces"],
    ["Monarch Decorative Wall Clock", 5999, 4899, "decorative wall clock with elegant finish"],
    ["Regal Statement Clock", 8499, 6999, "statement clock for living and dining walls"],
    ["Florence Premium Timepiece", 5299, 4299, "premium wall timepiece with clean detailing"],
    ["Noble Designer Wall Clock", 6499, 5299, "designer wall clock with balanced proportions"],
    ["Eterno Luxe Clock", 7499, 6199, "luxury clock with refined decorative appeal"],
    ["Casa Royale Wall Clock", 8999, 7699, "royal style wall clock for luxury interiors"],
    ["Opulence Decorative Clock", 9999, 8499, "opulent decorative timepiece"],
    ["Serene Minimal Wall Clock", 3999, 3299, "minimal wall clock with soft luxury styling"],
    ["Heritage Classic Clock", 5799, 4799, "classic wall clock with timeless finish"]
];

foreach ($files as $index => $file) {
    $item = $products[$index] ?? ["Zafiro Luxe Wall Clock " . ($index + 1), 4999 + ($index * 300), 3999 + ($index * 250), "premium decorative wall clock"];
    [$name, $originalPrice, $discountPrice, $feature] = $item;
    $sku = "ZC-CLOCK-" . str_pad((string) ($index + 1), 3, "0", STR_PAD_LEFT);

    $check = $conn->prepare("SELECT id FROM products WHERE name = ? OR sku = ? LIMIT 1");
    $check->bind_param("ss", $name, $sku);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $skipped++;
        continue;
    }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $targetName = "clocks_" . time() . "_" . str_pad((string) ($index + 1), 2, "0", STR_PAD_LEFT) . "." . $ext;
    if (!copy($file, $uploadDir . DIRECTORY_SEPARATOR . $targetName)) {
        $errors[] = "copy failed: $file";
        continue;
    }

    $image = "../uploads/products/" . $targetName;
    $price = $discountPrice;
    $stock = 8 + ($index % 15);
    $short = "$name adds elegant timekeeping with $feature.";
    $full = "$short Designed as a premium decorative wall clock with reliable movement, refined finish, and Zafiro Casa luxury appeal.";
    $slug = clockSlug($name);
    $brand = "Zafiro Casa";
    $specifications = "Premium wall clock, decorative finish, silent movement, easy wall mount.";
    $material = "Premium decorative clock finish";
    $color = "Premium finish";
    $dimensions = "Wall clock standard size";
    $weight = "Lightweight decor";
    $seating = "";
    $roomType = "Living Room";
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

    if ($stmt->execute()) {
        syncProductImageColumnsToTable($conn, (int) $stmt->insert_id, [$image]);
        $inserted++;
    } else {
        $errors[] = "$name: " . $stmt->error;
    }
}

$total = $conn->query("SELECT COUNT(*) total FROM products WHERE category = 'Clocks'")->fetch_assoc()["total"] ?? 0;
echo "source_images=" . count($files) . "\n";
echo "inserted=$inserted\n";
echo "skipped_duplicates=$skipped\n";
echo "clocks_total=$total\n";
echo "errors=" . ($errors ? implode(" | ", $errors) : "none") . "\n";
?>
