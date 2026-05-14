<?php
if (php_sapi_name() !== "cli") include("auth.php");
header("Content-Type: text/plain");
include(__DIR__ . "/../backend/config/db.php");
include_once(__DIR__ . "/../backend/includes/product_images.php");

$sourceDir = realpath(__DIR__ . "/../Categories furniture/kitchen storage & organisers/kitchen organiser");
$uploadDir = realpath(__DIR__ . "/../uploads/products");
$category = "Kitchen Organiser";
$errors = [];
$inserted = 0;
$skipped = 0;

function kitchenOrganiserSlug($text) {
    return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
}
if (!$sourceDir || !$uploadDir) die("errors: source/upload folder missing\n");

$conn->query("INSERT IGNORE INTO categories (category_name, slug, description, status) VALUES ('Kitchen Organiser', 'kitchen-organisers', 'Premium spice racks, countertop organisers and modular kitchen utility racks.', 'active')");
ensureProductImageColumns($conn);
ensureProductImagesSchema($conn);

$files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $fileInfo) {
    if ($fileInfo->isFile() && preg_match('/\.(jpg|jpeg|png|webp)$/i', $fileInfo->getFilename())) $files[] = $fileInfo->getPathname();
}

$products = [
    ["Aurelio Spice Rack Organizer", 4999, 3999, "spice rack organizer"],
    ["Verona Modular Kitchen Organizer", 8999, 7499, "modular kitchen organizer"],
    ["Milan Countertop Storage Unit", 6499, 5299, "countertop storage unit"],
    ["Nordic Utility Organizer", 5499, 4499, "utility organizer"],
    ["Imperial Multi Layer Rack", 7999, 6499, "multi layer rack"],
    ["Royal Crest Kitchen Shelf Organizer", 6999, 5799, "kitchen shelf organizer"],
    ["Celeste Compact Kitchen Rack", 4499, 3499, "compact kitchen rack"],
    ["Monarch Premium Storage Organizer", 5999, 4899, "premium storage organizer"],
    ["Regal Countertop Organizer", 3999, 3199, "countertop organizer"],
    ["Florence Designer Kitchen Rack", 7499, 5999, "designer kitchen rack"],
    ["Noble Space Saving Organizer", 3499, 2799, "space-saving organizer"],
    ["Eterno Luxe Utility Rack", 6499, 5299, "utility rack"],
    ["Casa Royale Modular Organizer", 9999, 8499, "modular organizer"],
    ["Opulence Multi Compartment Rack", 8999, 7499, "multi-compartment rack"],
    ["Serene Kitchen Basket Organizer", 4299, 3499, "basket organizer"],
    ["Heritage Wooden Shelf Organizer", 7999, 6499, "wooden shelf organizer"],
    ["Valencia Premium Kitchen Stand", 5799, 4699, "premium kitchen stand"],
    ["Aurelia Counter Utility Rack", 4999, 3999, "counter utility rack"],
    ["Milano Luxe Kitchen Organizer", 6999, 5799, "luxury kitchen organizer"]
];

foreach ($files as $index => $file) {
    $item = $products[$index] ?? ["Zafiro Premium Kitchen Organiser " . ($index + 1), 4999 + ($index * 350), 3999 + ($index * 300), "premium kitchen organiser"];
    [$name, $originalPrice, $discountPrice, $feature] = $item;
    $sku = "ZC-KORG-" . str_pad((string) ($index + 1), 3, "0", STR_PAD_LEFT);
    $check = $conn->prepare("SELECT id FROM products WHERE name = ? OR sku = ? LIMIT 1");
    $check->bind_param("ss", $name, $sku);
    $check->execute();
    if ($check->get_result()->num_rows > 0) { $skipped++; continue; }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $targetName = "kitchen_organiser_" . time() . "_" . str_pad((string) ($index + 1), 2, "0", STR_PAD_LEFT) . "." . $ext;
    if (!copy($file, $uploadDir . DIRECTORY_SEPARATOR . $targetName)) { $errors[] = "copy failed: $file"; continue; }

    $image = "../uploads/products/" . $targetName;
    $price = $discountPrice;
    $stock = 8 + ($index % 16);
    $short = "$name improves kitchen utility with a refined $feature design.";
    $full = "$short Built for organized countertops, shelves and compact kitchens with practical compartments and Zafiro Casa premium finish.";
    $slug = kitchenOrganiserSlug($name);
    $brand = "Zafiro Casa";
    $specifications = "Premium kitchen organiser, space-saving design, utility compartments, easy maintenance.";
    $material = "Premium metal, wood or plastic organizer finish";
    $color = "Premium finish";
    $dimensions = "Kitchen organiser standard size";
    $weight = "Lightweight utility";
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

$cat = $conn->query("SELECT id, slug FROM categories WHERE category_name='Kitchen Organiser' LIMIT 1")->fetch_assoc();
$total = $conn->query("SELECT COUNT(*) total FROM products WHERE category = 'Kitchen Organiser'")->fetch_assoc()["total"] ?? 0;
echo "kitchen_organiser_category_id=" . ($cat["id"] ?? "not_found") . "\n";
echo "kitchen_organiser_category_slug=" . ($cat["slug"] ?? "not_found") . "\n";
echo "source_images=" . count($files) . "\n";
echo "inserted=$inserted\n";
echo "skipped_duplicates=$skipped\n";
echo "kitchen_organiser_total=$total\n";
echo "errors=" . ($errors ? implode(" | ", $errors) : "none") . "\n";
?>
