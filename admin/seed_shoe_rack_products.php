<?php
if (php_sapi_name() !== "cli") include("auth.php");
header("Content-Type: text/plain");
include(__DIR__ . "/../backend/config/db.php");
include_once(__DIR__ . "/../backend/includes/product_images.php");

$sourceDir = realpath(__DIR__ . "/../Categories furniture/shoe rack/shoe rack");
$uploadDir = realpath(__DIR__ . "/../uploads/products");
$category = "Shoe Rack";
$errors = [];
$inserted = 0;
$skipped = 0;

function shoeRackSlug($text) {
    return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
}
if (!$sourceDir || !$uploadDir) die("errors: source/upload folder missing\n");

$conn->query("INSERT IGNORE INTO categories (category_name, slug, description, status) VALUES ('Shoe Rack', 'shoe-rack', 'Premium shoe racks, entryway organizers and shoe storage cabinets.', 'active')");
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
    ["Aurelio Wooden Shoe Rack", 12999, 9999, "wooden finish shoe rack"],
    ["Verona Multi Shelf Shoe Cabinet", 15999, 12499, "multi shelf shoe cabinet"],
    ["Milan Luxe Entryway Shoe Storage", 18999, 15499, "entryway shoe storage"],
    ["Nordic Oak Shoe Organizer", 13999, 10999, "oak finish shoe organizer"],
    ["Imperial Closed Door Shoe Rack", 17999, 14499, "closed door shoe rack"],
    ["Royal Crest Premium Shoe Cabinet", 21999, 17999, "premium shoe cabinet"],
    ["Celeste Compact Shoe Storage", 9999, 7799, "compact shoe storage"],
    ["Monarch Designer Shoe Rack", 14999, 11999, "designer shoe rack"],
    ["Regal Soft-Close Shoe Cabinet", 19999, 16499, "soft-close shoe cabinet"],
    ["Florence Modern Rack Unit", 11999, 9299, "modern rack design"],
    ["Noble Entryway Shoe Organizer", 10999, 8499, "entryway organizer"],
    ["Eterno Luxe Shoe Storage", 16999, 13499, "luxury shoe storage"],
    ["Casa Royale Wooden Shoe Cabinet", 23999, 19999, "wooden shoe cabinet"],
    ["Opulence Tall Shoe Rack", 24999, 20999, "tall shoe rack"],
    ["Serene Minimal Shoe Organizer", 8999, 6999, "minimal shoe organizer"],
    ["Valencia Premium Shoe Rack", 14999, 11999, "premium shoe rack"],
    ["Aurelia Drawer Shoe Cabinet", 18999, 15499, "drawer shoe cabinet"],
    ["Milano Compact Entryway Rack", 9999, 7799, "compact entryway rack"],
    ["Verona Designer Shoe Cabinet", 20999, 16999, "designer shoe cabinet"],
    ["Imperial Multi Tier Shoe Rack", 12999, 9999, "multi tier shoe rack"],
    ["Nordic Luxury Shoe Organizer", 15999, 12499, "luxury shoe organizer"]
];

foreach ($files as $index => $file) {
    $item = $products[$index] ?? ["Zafiro Premium Shoe Rack " . ($index + 1), 9999 + ($index * 700), 7499 + ($index * 600), "premium shoe rack"];
    [$name, $originalPrice, $discountPrice, $feature] = $item;
    $sku = "ZC-SHR-" . str_pad((string) ($index + 1), 3, "0", STR_PAD_LEFT);
    $check = $conn->prepare("SELECT id FROM products WHERE name = ? OR sku = ? LIMIT 1");
    $check->bind_param("ss", $name, $sku);
    $check->execute();
    if ($check->get_result()->num_rows > 0) { $skipped++; continue; }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $targetName = "shoe_rack_" . time() . "_" . str_pad((string) ($index + 1), 2, "0", STR_PAD_LEFT) . "." . $ext;
    if (!copy($file, $uploadDir . DIRECTORY_SEPARATOR . $targetName)) { $errors[] = "copy failed: $file"; continue; }

    $image = "../uploads/products/" . $targetName;
    $price = $discountPrice;
    $stock = 7 + ($index % 15);
    $short = "$name keeps footwear organized with $feature.";
    $full = "$short Designed for premium entryways with practical storage, clean proportions, durable finish, and Zafiro Casa luxury styling.";
    $slug = shoeRackSlug($name);
    $brand = "Zafiro Casa";
    $specifications = "Premium shoe rack, multi shelf or cabinet storage, durable finish, entryway use.";
    $material = "Premium engineered wood, metal or laminate finish";
    $color = "Premium finish";
    $dimensions = "Shoe rack standard size";
    $weight = "Standard furniture weight";
    $seating = "";
    $roomType = "Storage";
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

$cat = $conn->query("SELECT id, slug FROM categories WHERE category_name='Shoe Rack' LIMIT 1")->fetch_assoc();
$total = $conn->query("SELECT COUNT(*) total FROM products WHERE category = 'Shoe Rack'")->fetch_assoc()["total"] ?? 0;
echo "shoe_rack_category_id=" . ($cat["id"] ?? "not_found") . "\n";
echo "shoe_rack_category_slug=" . ($cat["slug"] ?? "not_found") . "\n";
echo "source_images=" . count($files) . "\n";
echo "inserted=$inserted\n";
echo "skipped_duplicates=$skipped\n";
echo "shoe_rack_total=$total\n";
echo "errors=" . ($errors ? implode(" | ", $errors) : "none") . "\n";
?>
