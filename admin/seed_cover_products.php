<?php
if (php_sapi_name() !== "cli") include("auth.php");
header("Content-Type: text/plain");
include(__DIR__ . "/../backend/config/db.php");
include_once(__DIR__ . "/../backend/includes/product_images.php");

$sourceDir = realpath(__DIR__ . "/../Categories furniture/cover");
$uploadDir = realpath(__DIR__ . "/../uploads/products");
$category = "Cover";
$errors = [];
$inserted = 0;
$skipped = 0;

function coverSlug($text) {
    return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
}
if (!$sourceDir || !$uploadDir) die("errors: source/upload folder missing\n");

$conn->query("INSERT IGNORE INTO categories (category_name, slug, description, status) VALUES ('Cover', 'cover', 'Premium cushion, sofa, chair and bed covers for refined interiors.', 'active')");
ensureProductImageColumns($conn);
ensureProductImagesSchema($conn);

$files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $fileInfo) {
    if ($fileInfo->isFile() && preg_match('/\.(jpg|jpeg|png|webp)$/i', $fileInfo->getFilename())) $files[] = $fileInfo->getPathname();
}

$products = [
    ["Aurelio Velvet Cushion Cover", 2499, 1999, "velvet-style premium cushion cover"],
    ["Verona Jacquard Sofa Cover", 5499, 4699, "jacquard-inspired sofa cover"],
    ["Nordic Cotton Pillow Cover", 1999, 1599, "soft cotton pillow cover"],
    ["Milan Luxe Dining Chair Cover", 2999, 2399, "premium dining chair cover"],
    ["Royal Crest Quilted Bed Cover", 6999, 5999, "quilted bed cover with luxury texture"],
    ["Imperial Textured Cushion Cover", 2499, 1999, "textured cushion cover"],
    ["Celeste Premium Fabric Cover", 3499, 2799, "premium fabric cover for refined decor"],
    ["Monarch Designer Sofa Cover", 5999, 4999, "designer sofa cover"],
    ["Regal Luxury Cover Set", 4499, 3699, "coordinated luxury cover set"],
    ["Florence Elegant Pillow Cover", 1999, 1499, "elegant pillow cover"],
    ["Noble Patterned Chair Cover", 2799, 2199, "patterned chair cover"],
    ["Eterno Soft Fabric Cover", 3299, 2599, "soft fabric cover"],
    ["Casa Royale Bed Cover", 7499, 6499, "royal bed cover for premium bedrooms"],
    ["Opulence Designer Cushion Cover", 2999, 2399, "designer cushion cover"],
    ["Serene Premium Decor Cover", 2499, 1899, "premium decor cover"]
];

foreach ($files as $index => $file) {
    $item = $products[$index] ?? ["Zafiro Premium Cover " . ($index + 1), 2499 + ($index * 250), 1999 + ($index * 220), "premium cover"];
    [$name, $originalPrice, $discountPrice, $feature] = $item;
    $sku = "ZC-COVER-" . str_pad((string) ($index + 1), 3, "0", STR_PAD_LEFT);
    $check = $conn->prepare("SELECT id FROM products WHERE name = ? OR sku = ? LIMIT 1");
    $check->bind_param("ss", $name, $sku);
    $check->execute();
    if ($check->get_result()->num_rows > 0) { $skipped++; continue; }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $targetName = "cover_" . time() . "_" . str_pad((string) ($index + 1), 2, "0", STR_PAD_LEFT) . "." . $ext;
    if (!copy($file, $uploadDir . DIRECTORY_SEPARATOR . $targetName)) { $errors[] = "copy failed: $file"; continue; }

    $image = "../uploads/products/" . $targetName;
    $price = $discountPrice;
    $stock = 10 + ($index % 20);
    $short = "$name crafted as a $feature for elegant Zafiro Casa interiors.";
    $full = "$short Made for premium home styling with comfortable fabric feel, refined texture, and easy everyday use.";
    $slug = coverSlug($name);
    $brand = "Zafiro Casa";
    $specifications = "Premium cover, refined fabric texture, easy maintenance, luxury decor finish.";
    $material = "Premium fabric";
    $color = "Premium finish";
    $dimensions = "Standard cover size";
    $weight = "Lightweight furnishing";
    $seating = "";
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

$cat = $conn->query("SELECT id, slug FROM categories WHERE category_name='Cover' LIMIT 1")->fetch_assoc();
$total = $conn->query("SELECT COUNT(*) total FROM products WHERE category = 'Cover'")->fetch_assoc()["total"] ?? 0;
echo "cover_category_id=" . ($cat["id"] ?? "not_found") . "\n";
echo "cover_category_slug=" . ($cat["slug"] ?? "not_found") . "\n";
echo "source_images=" . count($files) . "\n";
echo "inserted=$inserted\n";
echo "skipped_duplicates=$skipped\n";
echo "cover_total=$total\n";
echo "errors=" . ($errors ? implode(" | ", $errors) : "none") . "\n";
?>
