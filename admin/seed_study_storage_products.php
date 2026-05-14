<?php
if (php_sapi_name() !== "cli") include("auth.php");
header("Content-Type: text/plain");
include(__DIR__ . "/../backend/config/db.php");
include_once(__DIR__ . "/../backend/includes/product_images.php");

$sourceDir = realpath(__DIR__ . "/../Categories furniture/study storage");
$uploadDir = realpath(__DIR__ . "/../uploads/products");
$category = "Study Storage";
$errors = [];
$inserted = 0;
$skipped = 0;

function studyStorageSlug($text) {
    return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
}
if (!$sourceDir || !$uploadDir) die("errors: source/upload folder missing\n");

$conn->query("INSERT IGNORE INTO categories (category_name, slug, description, status) VALUES ('Study Storage', 'study-storage', 'Premium study cabinets, bookshelves and office organizers.', 'active')");
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
    ["Aurelio Study Storage Cabinet", 24999, 20999, "study storage cabinet"],
    ["Verona Wooden Study Organizer", 18999, 15499, "wooden study organizer"],
    ["Milan Luxe Book Storage Unit", 32999, 27999, "book storage unit"],
    ["Nordic Oak Study Shelf", 16999, 13499, "oak finish study shelf"],
    ["Imperial Multi Drawer Study Cabinet", 28999, 23999, "multi drawer cabinet"],
    ["Royal Crest Premium Study Storage", 34999, 29999, "premium study storage"],
    ["Celeste Modular Study Organizer", 21999, 17999, "modular storage"],
    ["Monarch Designer Study Cabinet", 26999, 22999, "designer study cabinet"],
    ["Regal Bookshelf Storage Unit", 19999, 16499, "bookshelf design"],
    ["Florence Compact Study Storage", 14999, 11999, "compact storage"],
    ["Noble Wooden Book Cabinet", 23999, 19999, "wooden shelves"],
    ["Eterno Luxe Study Shelf", 17999, 14499, "luxury study shelf"],
    ["Casa Royale Drawer Organizer", 29999, 24999, "drawer organizer"],
    ["Opulence Study Display Cabinet", 37999, 32999, "display cabinet"],
    ["Serene Minimal Study Storage", 13999, 10999, "minimal storage"],
    ["Valencia Premium Study Rack", 15999, 12499, "premium study rack"],
    ["Aurelia Office Book Storage", 22999, 18999, "office book storage"],
    ["Milano Modern Study Cabinet", 26999, 22999, "modern study setup"],
    ["Verona Study Shelf Unit", 16999, 13499, "study shelf unit"],
    ["Imperial Wooden Storage Shelf", 21999, 17999, "wooden storage shelf"],
    ["Nordic Compact Book Organizer", 14999, 11999, "compact book organizer"],
    ["Royal Crest Drawer Cabinet", 28999, 23999, "drawer cabinet"],
    ["Celeste Premium Bookshelf", 19999, 16499, "premium bookshelf"],
    ["Monarch Modular Study Unit", 32999, 27999, "modular study unit"],
    ["Regal Office Storage Cabinet", 24999, 20999, "office storage cabinet"],
    ["Florence Designer Book Shelf", 17999, 14499, "designer bookshelf"],
    ["Noble Study Organizer Cabinet", 22999, 18999, "study organizer cabinet"],
    ["Eterno Premium Storage Shelf", 19999, 16499, "premium storage shelf"],
    ["Casa Royale Study Cabinet", 29999, 24999, "study cabinet"],
    ["Opulence Multi Shelf Study Unit", 34999, 29999, "multi shelf study unit"],
    ["Serene Study Book Rack", 12999, 9999, "study book rack"],
    ["Valencia Luxe Study Organizer", 21999, 17999, "luxe study organizer"],
    ["Aurelio Premium Book Cabinet", 26999, 22999, "premium book cabinet"]
];

foreach ($files as $index => $file) {
    $item = $products[$index] ?? ["Zafiro Premium Study Storage " . ($index + 1), 14999 + ($index * 900), 11999 + ($index * 800), "premium study storage"];
    [$name, $originalPrice, $discountPrice, $feature] = $item;
    $sku = "ZC-SSTOR-" . str_pad((string) ($index + 1), 3, "0", STR_PAD_LEFT);
    $check = $conn->prepare("SELECT id FROM products WHERE name = ? OR sku = ? LIMIT 1");
    $check->bind_param("ss", $name, $sku);
    $check->execute();
    if ($check->get_result()->num_rows > 0) { $skipped++; continue; }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $targetName = "study_storage_" . time() . "_" . str_pad((string) ($index + 1), 2, "0", STR_PAD_LEFT) . "." . $ext;
    if (!copy($file, $uploadDir . DIRECTORY_SEPARATOR . $targetName)) { $errors[] = "copy failed: $file"; continue; }

    $image = "../uploads/products/" . $targetName;
    $price = $discountPrice;
    $stock = 5 + ($index % 14);
    $short = "$name improves workspace organization with $feature.";
    $full = "$short Designed for study and office spaces with practical shelves, drawers, durable finish, and Zafiro Casa luxury styling.";
    $slug = studyStorageSlug($name);
    $brand = "Zafiro Casa";
    $specifications = "Premium study storage, shelves or drawers, compact organizer, modern study use.";
    $material = "Premium engineered wood, laminate or metal finish";
    $color = "Premium finish";
    $dimensions = "Study storage standard size";
    $weight = "Standard furniture weight";
    $seating = "";
    $roomType = "Study";
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

$cat = $conn->query("SELECT id, slug FROM categories WHERE category_name='Study Storage' LIMIT 1")->fetch_assoc();
$total = $conn->query("SELECT COUNT(*) total FROM products WHERE category = 'Study Storage'")->fetch_assoc()["total"] ?? 0;
echo "study_storage_category_id=" . ($cat["id"] ?? "not_found") . "\n";
echo "study_storage_category_slug=" . ($cat["slug"] ?? "not_found") . "\n";
echo "source_images=" . count($files) . "\n";
echo "inserted=$inserted\n";
echo "skipped_duplicates=$skipped\n";
echo "study_storage_total=$total\n";
echo "errors=" . ($errors ? implode(" | ", $errors) : "none") . "\n";
?>
