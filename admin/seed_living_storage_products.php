<?php
if (php_sapi_name() !== "cli") include("auth.php");
header("Content-Type: text/plain");
include(__DIR__ . "/../backend/config/db.php");
include_once(__DIR__ . "/../backend/includes/product_images.php");

$sourceDir = realpath(__DIR__ . "/../Categories furniture/living storage");
$uploadDir = realpath(__DIR__ . "/../uploads/products");
$category = "Living Storage";
$errors = [];
$inserted = 0;
$skipped = 0;

function livingStorageSlug($text) {
    return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
}
if (!$sourceDir || !$uploadDir) die("errors: source/upload folder missing\n");

$conn->query("INSERT IGNORE INTO categories (category_name, slug, description, status) VALUES ('Living Storage', 'living-storage', 'Premium living room cabinets, sideboards, organizers and display storage.', 'active')");
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
    ["Aurelio Living Room Storage Unit", 38999, 33999, "modular living storage unit"],
    ["Verona Walnut Console Cabinet", 32999, 27999, "console cabinet with premium finish"],
    ["Milan Luxe Storage Sideboard", 45999, 39999, "luxury storage sideboard"],
    ["Nordic Oak Display Cabinet", 41999, 35999, "display cabinet with shelves"],
    ["Imperial Multi Shelf Storage Unit", 36999, 31999, "multi shelf storage unit"],
    ["Royal Crest Living Organizer", 29999, 24999, "living room organizer"],
    ["Celeste Designer Console Storage", 34999, 29999, "designer console storage"],
    ["Monarch Premium Display Cabinet", 48999, 42999, "premium display cabinet"],
    ["Regal Drawer Storage Sideboard", 39999, 34999, "drawer storage sideboard"],
    ["Florence Luxury Storage Cabinet", 31999, 26999, "luxury storage cabinet"],
    ["Noble Modern Living Cabinet", 27999, 22999, "modern living cabinet"],
    ["Eterno Luxe Organizer Unit", 35999, 30999, "luxe organizer unit"],
    ["Casa Royale Wooden Sideboard", 52999, 46999, "wooden finish sideboard"],
    ["Opulence Modular Storage Cabinet", 44999, 38999, "modular storage cabinet"]
];

foreach ($files as $index => $file) {
    $item = $products[$index] ?? ["Zafiro Premium Living Storage " . ($index + 1), 29999 + ($index * 1200), 24999 + ($index * 1000), "premium living storage"];
    [$name, $originalPrice, $discountPrice, $feature] = $item;
    $sku = "ZC-LSTOR-" . str_pad((string) ($index + 1), 3, "0", STR_PAD_LEFT);
    $check = $conn->prepare("SELECT id FROM products WHERE name = ? OR sku = ? LIMIT 1");
    $check->bind_param("ss", $name, $sku);
    $check->execute();
    if ($check->get_result()->num_rows > 0) { $skipped++; continue; }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $targetName = "living_storage_" . time() . "_" . str_pad((string) ($index + 1), 2, "0", STR_PAD_LEFT) . "." . $ext;
    if (!copy($file, $uploadDir . DIRECTORY_SEPARATOR . $targetName)) { $errors[] = "copy failed: $file"; continue; }

    $image = "../uploads/products/" . $targetName;
    $price = $discountPrice;
    $stock = 5 + ($index % 14);
    $short = "$name adds refined living room organization with $feature.";
    $full = "$short Designed for premium interiors with practical shelves, cabinet storage, clean proportions, and Zafiro Casa luxury styling.";
    $slug = livingStorageSlug($name);
    $brand = "Zafiro Casa";
    $specifications = "Premium living storage, cabinet or shelf design, durable finish, modern luxury styling.";
    $material = "Premium engineered wood, metal or laminate finish";
    $color = "Premium finish";
    $dimensions = "Living storage standard size";
    $weight = "Standard furniture weight";
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
    if ($stmt->execute()) { syncProductImageColumnsToTable($conn, (int) $stmt->insert_id, [$image]); $inserted++; }
    else { $errors[] = "$name: " . $stmt->error; }
}

$cat = $conn->query("SELECT id, slug FROM categories WHERE category_name='Living Storage' LIMIT 1")->fetch_assoc();
$total = $conn->query("SELECT COUNT(*) total FROM products WHERE category = 'Living Storage'")->fetch_assoc()["total"] ?? 0;
echo "living_storage_category_id=" . ($cat["id"] ?? "not_found") . "\n";
echo "living_storage_category_slug=" . ($cat["slug"] ?? "not_found") . "\n";
echo "source_images=" . count($files) . "\n";
echo "inserted=$inserted\n";
echo "skipped_duplicates=$skipped\n";
echo "living_storage_total=$total\n";
echo "errors=" . ($errors ? implode(" | ", $errors) : "none") . "\n";
?>
