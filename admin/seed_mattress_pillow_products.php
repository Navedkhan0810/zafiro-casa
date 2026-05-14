<?php
if (php_sapi_name() !== "cli") include("auth.php");
header("Content-Type: text/plain");
include(__DIR__ . "/../backend/config/db.php");
include_once(__DIR__ . "/../backend/includes/product_images.php");

$sourceDir = realpath(__DIR__ . "/../Categories furniture/matresses & pillow");
$uploadDir = realpath(__DIR__ . "/../uploads/products");
$category = "Mattress";
$errors = [];
$inserted = 0;
$skipped = 0;

function mattressPillowSlug($text) {
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
    ["Aurelio Memory Foam Mattress", 28999, 23999, "memory foam comfort mattress"],
    ["Verona Orthopedic Mattress", 31999, 26999, "orthopedic support mattress"],
    ["Milan Luxe Pillow Set", 5999, 4499, "luxury pillow set"],
    ["Nordic Comfort Pillow", 3499, 2699, "soft comfort pillow"],
    ["Imperial King Size Mattress", 42999, 36999, "king size mattress"],
    ["Royal Crest Premium Bedding Set", 18999, 14999, "premium bedding comfort set"],
    ["Celeste Queen Comfort Mattress", 34999, 29999, "queen size comfort mattress"],
    ["Monarch Breathable Pillow Pair", 4999, 3799, "breathable pillow pair"],
    ["Regal Plush Foam Mattress", 37999, 31999, "plush foam mattress"],
    ["Florence Hypoallergenic Pillow Set", 5499, 4199, "hypoallergenic pillow set"],
    ["Noble Soft Comfort Mattress", 29999, 24999, "soft comfort layer mattress"],
    ["Eterno Premium Sleep Mattress", 39999, 33999, "premium sleep mattress"],
    ["Casa Royale Mattress Pillow Combo", 45999, 38999, "mattress and pillow combo"],
    ["Opulence Cloud Comfort Pillow", 3999, 2999, "cloud comfort pillow"],
    ["Serene Support Foam Mattress", 26999, 21999, "support foam mattress"],
    ["Valencia Luxe Bedding Comfort", 20999, 16999, "designer bedding comfort"],
    ["Aurelia Premium Pillow Set", 6999, 5299, "premium cushioning pillow set"],
    ["Milano Orthopedic Sleep Mattress", 44999, 37999, "orthopedic sleep mattress"],
    ["Verona Elite Comfort Bedding Set", 24999, 19999, "elite comfort bedding set"]
];

foreach ($files as $index => $file) {
    $item = $products[$index] ?? ["Zafiro Premium Mattress Pillow " . ($index + 1), 12999 + ($index * 900), 9999 + ($index * 800), "premium bedding comfort"];
    [$name, $originalPrice, $discountPrice, $feature] = $item;
    $sku = "ZC-MP-" . str_pad((string) ($index + 1), 3, "0", STR_PAD_LEFT);
    $check = $conn->prepare("SELECT id FROM products WHERE name = ? OR sku = ? LIMIT 1");
    $check->bind_param("ss", $name, $sku);
    $check->execute();
    if ($check->get_result()->num_rows > 0) { $skipped++; continue; }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $targetName = "mattress_pillow_" . time() . "_" . str_pad((string) ($index + 1), 2, "0", STR_PAD_LEFT) . "." . $ext;
    if (!copy($file, $uploadDir . DIRECTORY_SEPARATOR . $targetName)) { $errors[] = "copy failed: $file"; continue; }

    $image = "../uploads/products/" . $targetName;
    $price = $discountPrice;
    $stock = 8 + ($index % 18);
    $short = "$name delivers premium sleep comfort with $feature.";
    $full = "$short Made for refined bedrooms with breathable fabric, supportive cushioning, soft comfort layers, and Zafiro Casa luxury finish.";
    $slug = mattressPillowSlug($name);
    $brand = "Zafiro Casa";
    $specifications = "Premium mattress or pillow, breathable comfort, soft support, bedroom use.";
    $material = "Premium foam, fabric or comfort fill";
    $color = "Premium finish";
    $dimensions = "Mattress or pillow standard size";
    $weight = "Standard bedding weight";
    $seating = "";
    $roomType = "Bedroom";
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

$cat = $conn->query("SELECT id, slug FROM categories WHERE category_name='Mattress' LIMIT 1")->fetch_assoc();
$total = $conn->query("SELECT COUNT(*) total FROM products WHERE category = 'Mattress'")->fetch_assoc()["total"] ?? 0;
echo "mattress_pillow_category_id=" . ($cat["id"] ?? "not_found") . "\n";
echo "mattress_pillow_category_slug=" . ($cat["slug"] ?? "not_found") . "\n";
echo "source_images=" . count($files) . "\n";
echo "inserted=$inserted\n";
echo "skipped_duplicates=$skipped\n";
echo "mattress_total=$total\n";
echo "errors=" . ($errors ? implode(" | ", $errors) : "none") . "\n";
?>
