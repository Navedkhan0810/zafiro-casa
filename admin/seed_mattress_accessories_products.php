<?php
if (php_sapi_name() !== "cli") include("auth.php");
header("Content-Type: text/plain");
include(__DIR__ . "/../backend/config/db.php");
include_once(__DIR__ . "/../backend/includes/product_images.php");

$sourceDir = realpath(__DIR__ . "/../Categories furniture/mattress accessories");
$uploadDir = realpath(__DIR__ . "/../uploads/products");
$category = "Mattress Accessories";
$errors = [];
$inserted = 0;
$skipped = 0;

function mattressAccessorySlug($text) {
    return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
}
if (!$sourceDir || !$uploadDir) die("errors: source/upload folder missing\n");

$conn->query("INSERT IGNORE INTO categories (category_name, slug, description, status) VALUES ('Mattress Accessories', 'mattress-accessories', 'Premium mattress protectors, toppers, bedding pads and sleep comfort accessories.', 'active')");
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
    ["Aurelio Mattress Protector", 3999, 2999, "breathable mattress protector"],
    ["Verona Quilted Mattress Topper", 6999, 5499, "quilted comfort topper"],
    ["Milan Luxe Pillow Protector", 2499, 1899, "soft pillow protector"],
    ["Nordic Comfort Bed Wedge", 5499, 4299, "supportive bed wedge"],
    ["Imperial Waterproof Mattress Cover", 4499, 3399, "waterproof mattress cover"],
    ["Royal Crest Premium Bedding Pad", 5999, 4699, "premium bedding pad"],
    ["Celeste Anti-Slip Mattress Topper", 7499, 5999, "anti-slip mattress topper"],
    ["Monarch Breathable Comfort Layer", 4999, 3899, "breathable comfort layer"],
    ["Regal Quilted Bedding Protector", 4299, 3299, "quilted bedding protector"],
    ["Florence Luxury Sleep Pad", 6499, 4999, "luxury sleep comfort pad"],
    ["Noble Premium Mattress Cover", 3799, 2899, "premium mattress cover"],
    ["Eterno Designer Bedding Accessory", 5299, 4099, "designer sleep comfort accessory"],
    ["Casa Royale Foam Comfort Topper", 8499, 6799, "foam comfort topper"],
    ["Opulence Hypoallergenic Pillow Guard", 2999, 2299, "hypoallergenic pillow guard"]
];

foreach ($files as $index => $file) {
    $item = $products[$index] ?? ["Zafiro Premium Mattress Accessory " . ($index + 1), 3999 + ($index * 350), 2999 + ($index * 300), "premium mattress accessory"];
    [$name, $originalPrice, $discountPrice, $feature] = $item;
    $sku = "ZC-MACC-" . str_pad((string) ($index + 1), 3, "0", STR_PAD_LEFT);
    $check = $conn->prepare("SELECT id FROM products WHERE name = ? OR sku = ? LIMIT 1");
    $check->bind_param("ss", $name, $sku);
    $check->execute();
    if ($check->get_result()->num_rows > 0) { $skipped++; continue; }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $targetName = "mattress_accessories_" . time() . "_" . str_pad((string) ($index + 1), 2, "0", STR_PAD_LEFT) . "." . $ext;
    if (!copy($file, $uploadDir . DIRECTORY_SEPARATOR . $targetName)) { $errors[] = "copy failed: $file"; continue; }

    $image = "../uploads/products/" . $targetName;
    $price = $discountPrice;
    $stock = 12 + ($index % 20);
    $short = "$name adds premium sleep comfort with $feature.";
    $full = "$short Designed for cleaner, softer bedding with breathable fabric, comfort support, easy care, and Zafiro Casa luxury finish.";
    $slug = mattressAccessorySlug($name);
    $brand = "Zafiro Casa";
    $specifications = "Premium mattress accessory, breathable comfort, easy maintenance, bedroom use.";
    $material = "Premium fabric, foam or quilted comfort layer";
    $color = "Premium finish";
    $dimensions = "Mattress accessory standard size";
    $weight = "Lightweight bedding accessory";
    $seating = "";
    $roomType = "Bedroom";
    $assembly = "No";
    $featured = $index < 2 ? 1 : 0;
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

$cat = $conn->query("SELECT id, slug FROM categories WHERE category_name='Mattress Accessories' LIMIT 1")->fetch_assoc();
$total = $conn->query("SELECT COUNT(*) total FROM products WHERE category = 'Mattress Accessories'")->fetch_assoc()["total"] ?? 0;
echo "mattress_accessories_category_id=" . ($cat["id"] ?? "not_found") . "\n";
echo "mattress_accessories_category_slug=" . ($cat["slug"] ?? "not_found") . "\n";
echo "source_images=" . count($files) . "\n";
echo "inserted=$inserted\n";
echo "skipped_duplicates=$skipped\n";
echo "mattress_accessories_total=$total\n";
echo "errors=" . ($errors ? implode(" | ", $errors) : "none") . "\n";
?>
