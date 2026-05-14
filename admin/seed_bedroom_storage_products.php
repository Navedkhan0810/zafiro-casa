<?php
if (php_sapi_name() !== "cli") include("auth.php");
header("Content-Type: text/plain");
include(__DIR__ . "/../backend/config/db.php");
include_once(__DIR__ . "/../backend/includes/product_images.php");

$sourceDir = realpath(__DIR__ . "/../Categories furniture/bedroom storage/extracted");
$uploadDir = realpath(__DIR__ . "/../uploads/products");
$category = "Bedroom Storage";
$errors = [];
$inserted = 0;
$skipped = 0;

function bedroomStorageSlug($text) {
    return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
}

if (!$sourceDir || !$uploadDir) die("errors: source/upload folder missing\n");

$conn->query("INSERT IGNORE INTO categories (category_name, slug, description, status) VALUES ('Bedroom Storage', 'bedroom-storage', 'Premium wardrobes, closets, cabinets and bedroom organizer units.', 'active')");
ensureProductImageColumns($conn);
ensureProductImagesSchema($conn);

$files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $fileInfo) {
    if ($fileInfo->isFile() && preg_match('/\.(jpg|jpeg|png|webp)$/i', $fileInfo->getFilename())) {
        $files[] = $fileInfo->getPathname();
    }
}

$products = [
    ["Verona Walnut Wardrobe", 58999, 52999, "Walnut", "wooden texture wardrobe with luxury laminate finish"],
    ["Aurelio Sliding Door Closet", 74999, 66999, "Champagne", "sliding door closet with soft-close storage"],
    ["Milan Luxe Storage Cabinet", 46999, 41999, "Mocha", "premium modular bedroom cabinet"],
    ["Nordic Oak Chest Drawer", 32999, 28999, "Oak", "multi-drawer chest with natural wood finish"],
    ["Imperial Mirror Wardrobe Unit", 82999, 73999, "Mirror & Walnut", "mirrored wardrobe with modular storage"],
    ["Royal Crest Bedroom Organizer", 39999, 34999, "Ivory", "organized bedroom storage with refined detailing"],
    ["Celeste Soft-Close Drawer Cabinet", 35999, 31999, "White", "soft-close multi-drawer setup"],
    ["Monarch Modular Wardrobe", 69999, 62999, "Dark Walnut", "modular wardrobe with luxury laminate finish"],
    ["Regal Glass Door Closet", 78999, 70999, "Smoked Glass", "glass door closet with premium shelving"],
    ["Florence Compact Bedroom Cabinet", 27999, 23999, "Beige", "compact cabinet for elegant bedroom storage"],
    ["Noble Tall Storage Wardrobe", 54999, 48999, "Natural Wood", "tall wardrobe with wooden texture"],
    ["Eterno Luxe Drawer Chest", 36999, 32999, "Charcoal", "luxury drawer chest with smooth runners"],
    ["Casa Royale Dressing Storage Unit", 64999, 57999, "Cream", "dressing storage with premium compartments"],
    ["Opulence Mirror Panel Closet", 89999, 79999, "Mirror Gold", "mirror panel closet with statement finish"]
];

foreach ($files as $index => $file) {
    $item = $products[$index] ?? ["Zafiro Luxe Bedroom Storage " . ($index + 1), 39999 + ($index * 2200), 35999 + ($index * 1900), "Premium", "luxury bedroom storage finish"];
    [$name, $originalPrice, $discountPrice, $color, $material] = $item;
    $sku = "ZC-BEDST-" . str_pad((string) ($index + 1), 3, "0", STR_PAD_LEFT);

    $check = $conn->prepare("SELECT id FROM products WHERE name = ? OR sku = ? LIMIT 1");
    $check->bind_param("ss", $name, $sku);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $skipped++;
        continue;
    }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $targetName = "bedroom_storage_" . time() . "_" . str_pad((string) ($index + 1), 2, "0", STR_PAD_LEFT) . "." . $ext;
    if (!copy($file, $uploadDir . DIRECTORY_SEPARATOR . $targetName)) {
        $errors[] = "copy failed: $file";
        continue;
    }

    $image = "../uploads/products/" . $targetName;
    $price = $discountPrice;
    $stock = 4 + ($index % 10);
    $short = "$name offers premium bedroom organization with refined storage and a luxury finish.";
    $full = "$short Built with $material, practical compartments, durable construction, and polished Zafiro Casa styling for modern bedrooms.";
    $slug = bedroomStorageSlug($name);
    $brand = "Zafiro Casa";
    $specifications = "Premium bedroom storage, durable frame, smooth storage access, luxury finish.";
    $dimensions = "Bedroom storage standard size";
    $weight = "Heavy furniture weight";
    $seating = "";
    $roomType = "Bedroom";
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

$total = $conn->query("SELECT COUNT(*) total FROM products WHERE category = 'Bedroom Storage'")->fetch_assoc()["total"] ?? 0;
echo "source_images=" . count($files) . "\n";
echo "inserted=$inserted\n";
echo "skipped_duplicates=$skipped\n";
echo "bedroom_storage_total=$total\n";
echo "errors=" . ($errors ? implode(" | ", $errors) : "none") . "\n";
?>
