<?php
if (php_sapi_name() !== "cli") include("auth.php");
header("Content-Type: text/plain");
include(__DIR__ . "/../backend/config/db.php");
include_once(__DIR__ . "/../backend/includes/product_images.php");

$sourceDir = realpath(__DIR__ . "/../Categories furniture/kitchen storage");
$uploadDir = realpath(__DIR__ . "/../uploads/products");
$category = "Kitchen Storage";
$errors = [];
$inserted = 0;
$skipped = 0;

function kitchenStorageSlug($text) {
    return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
}
if (!$sourceDir || !$uploadDir) die("errors: source/upload folder missing\n");

$conn->query("INSERT IGNORE INTO categories (category_name, slug, description, status) VALUES ('Kitchen Storage', 'kitchen-storage', 'Premium pantry units, organizers, racks and modular kitchen storage.', 'active')");
ensureProductImageColumns($conn);
ensureProductImagesSchema($conn);

$files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $fileInfo) {
    if ($fileInfo->isFile() && preg_match('/\.(jpg|jpeg|png|webp)$/i', $fileInfo->getFilename())) $files[] = $fileInfo->getPathname();
}

$products = [
    ["Aurelio Modular Kitchen Cabinet", 32999, 28999, "modular storage cabinet"],
    ["Verona Premium Storage Rack", 14999, 12499, "multi-shelf kitchen rack"],
    ["Milan Luxe Pantry Unit", 42999, 37999, "pantry storage unit"],
    ["Nordic Kitchen Organizer", 11999, 9499, "compact kitchen organizer"],
    ["Imperial Multi Shelf Cabinet", 28999, 24999, "multi shelf storage cabinet"],
    ["Royal Crest Kitchen Utility Unit", 35999, 31999, "kitchen utility unit"],
    ["Celeste Compact Pantry Cabinet", 26999, 22999, "compact pantry cabinet"],
    ["Monarch Modular Storage Unit", 39999, 34999, "modular kitchen storage"],
    ["Regal Kitchen Drawer Cabinet", 31999, 27999, "utility drawer cabinet"],
    ["Florence Premium Kitchen Rack", 15999, 12999, "premium kitchen rack"],
    ["Noble Slim Kitchen Organizer", 10999, 8999, "slim organizer unit"],
    ["Eterno Luxe Pantry Storage", 44999, 39999, "luxe pantry storage"],
    ["Casa Royale Kitchen Cabinet", 49999, 44999, "royal kitchen cabinet"],
    ["Opulence Soft-Close Cabinet", 52999, 46999, "soft-close cabinet storage"],
    ["Serene Countertop Organizer", 8999, 6999, "countertop organizer"],
    ["Heritage Wooden Storage Unit", 28999, 24999, "wooden finish storage unit"],
    ["Valencia Premium Pantry Rack", 18999, 15499, "premium pantry rack"],
    ["Aurelia Kitchen Shelf Unit", 13999, 10999, "multi shelf kitchen unit"],
    ["Milano Designer Kitchen Storage", 37999, 32999, "designer kitchen storage"],
    ["Verona Compact Utility Cabinet", 23999, 19999, "compact utility cabinet"],
    ["Imperial Modular Pantry", 46999, 41999, "modular pantry design"],
    ["Nordic Luxe Kitchen Cabinet", 34999, 30999, "luxe kitchen cabinet"]
];

foreach ($files as $index => $file) {
    $item = $products[$index] ?? ["Zafiro Premium Kitchen Storage " . ($index + 1), 19999 + ($index * 1200), 16999 + ($index * 1000), "premium kitchen storage"];
    [$name, $originalPrice, $discountPrice, $feature] = $item;
    $sku = "ZC-KSTOR-" . str_pad((string) ($index + 1), 3, "0", STR_PAD_LEFT);
    $check = $conn->prepare("SELECT id FROM products WHERE name = ? OR sku = ? LIMIT 1");
    $check->bind_param("ss", $name, $sku);
    $check->execute();
    if ($check->get_result()->num_rows > 0) { $skipped++; continue; }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $targetName = "kitchen_storage_" . time() . "_" . str_pad((string) ($index + 1), 2, "0", STR_PAD_LEFT) . "." . $ext;
    if (!copy($file, $uploadDir . DIRECTORY_SEPARATOR . $targetName)) { $errors[] = "copy failed: $file"; continue; }

    $image = "../uploads/products/" . $targetName;
    $price = $discountPrice;
    $stock = 5 + ($index % 14);
    $short = "$name brings premium kitchen organization with $feature.";
    $full = "$short Designed for modern kitchens with practical shelves, utility storage, durable construction, and Zafiro Casa luxury finish.";
    $slug = kitchenStorageSlug($name);
    $brand = "Zafiro Casa";
    $specifications = "Premium kitchen storage, modular utility, durable frame, easy maintenance.";
    $material = "Premium engineered wood, metal or laminate finish";
    $color = "Premium finish";
    $dimensions = "Kitchen storage standard size";
    $weight = "Standard furniture weight";
    $seating = "";
    $roomType = "Kitchen";
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

$cat = $conn->query("SELECT id, slug FROM categories WHERE category_name='Kitchen Storage' LIMIT 1")->fetch_assoc();
$total = $conn->query("SELECT COUNT(*) total FROM products WHERE category = 'Kitchen Storage'")->fetch_assoc()["total"] ?? 0;
echo "kitchen_storage_category_id=" . ($cat["id"] ?? "not_found") . "\n";
echo "kitchen_storage_category_slug=" . ($cat["slug"] ?? "not_found") . "\n";
echo "source_images=" . count($files) . "\n";
echo "inserted=$inserted\n";
echo "skipped_duplicates=$skipped\n";
echo "kitchen_storage_total=$total\n";
echo "errors=" . ($errors ? implode(" | ", $errors) : "none") . "\n";
?>
