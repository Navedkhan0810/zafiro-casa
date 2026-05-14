<?php
if (php_sapi_name() !== "cli") include("auth.php");
header("Content-Type: text/plain");
include(__DIR__ . "/../backend/config/db.php");
include_once(__DIR__ . "/../backend/includes/product_images.php");

$sourceDir = realpath(__DIR__ . "/../Categories furniture/wardrobe/wardrobe (1)/wardrobe");
$uploadDir = realpath(__DIR__ . "/../uploads/products");
$category = "Wardrobe";
$errors = [];
$inserted = 0;
$skipped = 0;

function wardrobeSlug($text) {
    return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
}
if (!$sourceDir || !$uploadDir) die("errors: source/upload folder missing\n");

$conn->query("INSERT IGNORE INTO categories (category_name, slug, description, status) VALUES ('Wardrobe', 'wardrobe', 'Premium wardrobes, closet storage and modular bedroom units.', 'active')");
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
    ["Aurelio Sliding Door Wardrobe", 79999, 69999, "sliding door wardrobe"],
    ["Verona Walnut Wardrobe", 69999, 59999, "walnut finish wardrobe"],
    ["Milan Luxe Mirror Wardrobe", 89999, 77999, "mirror wardrobe design"],
    ["Nordic Oak Storage Wardrobe", 74999, 64999, "oak storage wardrobe"],
    ["Imperial Multi Door Closet", 99999, 87999, "multi door closet"],
    ["Royal Crest Premium Wardrobe", 94999, 82999, "premium wardrobe unit"],
    ["Celeste Modular Wardrobe Unit", 84999, 73999, "modular wardrobe design"],
    ["Monarch Designer Wardrobe", 89999, 78999, "designer wardrobe unit"],
    ["Regal Soft-Close Wardrobe", 76999, 66999, "soft-close storage"],
    ["Florence Wooden Closet Storage", 69999, 59999, "wooden closet storage"],
    ["Noble Mirrored Closet Wardrobe", 87999, 76999, "mirrored closet wardrobe"],
    ["Eterno Luxe Wardrobe Cabinet", 82999, 71999, "luxe wardrobe cabinet"],
    ["Casa Royale Multi Shelf Wardrobe", 92999, 80999, "multi shelf wardrobe"],
    ["Opulence Premium Closet Storage", 109999, 96999, "premium closet storage"],
    ["Serene Minimal Wardrobe", 64999, 55999, "minimal wardrobe design"],
    ["Valencia Designer Wardrobe Unit", 89999, 77999, "designer wardrobe unit"],
    ["Aurelia Sliding Mirror Wardrobe", 94999, 82999, "sliding mirror wardrobe"],
    ["Milano Modern Wardrobe", 71999, 61999, "modern wardrobe finish"],
    ["Verona Multi Drawer Wardrobe", 84999, 73999, "multi drawer wardrobe"],
    ["Imperial Wooden Wardrobe", 79999, 69999, "wooden texture wardrobe"],
    ["Nordic Premium Closet Unit", 75999, 65999, "premium closet unit"],
    ["Royal Crest Mirror Closet", 99999, 87999, "mirror closet unit"],
    ["Celeste Soft-Close Wardrobe", 82999, 71999, "soft-close wardrobe"],
    ["Monarch Luxury Wardrobe", 104999, 92999, "luxury wardrobe"],
    ["Regal Modular Closet Unit", 89999, 77999, "modular closet unit"],
    ["Florence Premium Wardrobe", 78999, 68999, "premium wardrobe"],
    ["Noble Designer Closet", 84999, 73999, "designer closet storage"],
    ["Eterno Mirror Panel Wardrobe", 96999, 84999, "mirror panel wardrobe"],
    ["Casa Royale Wooden Wardrobe", 89999, 78999, "wooden wardrobe"],
    ["Opulence Sliding Wardrobe", 109999, 96999, "sliding wardrobe"],
    ["Serene Compact Wardrobe", 59999, 51999, "compact wardrobe"],
    ["Valencia Luxe Closet Storage", 92999, 80999, "luxe closet storage"],
    ["Aurelio Premium Wardrobe Unit", 87999, 76999, "premium wardrobe unit"],
    ["Milano Multi Door Wardrobe", 94999, 82999, "multi door wardrobe"],
    ["Verona Designer Storage Wardrobe", 82999, 71999, "designer storage wardrobe"],
    ["Imperial Luxe Mirror Wardrobe", 104999, 92999, "luxe mirror wardrobe"],
    ["Nordic Wooden Closet Unit", 69999, 59999, "wooden closet unit"],
    ["Royal Crest Modular Wardrobe", 99999, 87999, "modular wardrobe"],
    ["Celeste Premium Closet Storage", 84999, 73999, "premium closet storage"]
];

foreach ($files as $index => $file) {
    $item = $products[$index] ?? ["Zafiro Premium Wardrobe " . ($index + 1), 69999 + ($index * 1800), 59999 + ($index * 1600), "premium wardrobe"];
    [$name, $originalPrice, $discountPrice, $feature] = $item;
    $sku = "ZC-WARD-" . str_pad((string) ($index + 1), 3, "0", STR_PAD_LEFT);
    $check = $conn->prepare("SELECT id FROM products WHERE name = ? OR sku = ? LIMIT 1");
    $check->bind_param("ss", $name, $sku);
    $check->execute();
    if ($check->get_result()->num_rows > 0) { $skipped++; continue; }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $targetName = "wardrobe_" . time() . "_" . str_pad((string) ($index + 1), 2, "0", STR_PAD_LEFT) . "." . $ext;
    if (!copy($file, $uploadDir . DIRECTORY_SEPARATOR . $targetName)) { $errors[] = "copy failed: $file"; continue; }

    $image = "../uploads/products/" . $targetName;
    $price = $discountPrice;
    $stock = 4 + ($index % 10);
    $short = "$name adds premium bedroom storage with $feature.";
    $full = "$short Designed with shelves, hanging space, smooth doors, refined finish, and Zafiro Casa luxury modular styling.";
    $slug = wardrobeSlug($name);
    $brand = "Zafiro Casa";
    $specifications = "Premium wardrobe, closet storage, shelves or drawers, modular bedroom use.";
    $material = "Premium engineered wood, mirror, laminate or metal finish";
    $color = "Premium finish";
    $dimensions = "Wardrobe standard size";
    $weight = "Standard furniture weight";
    $seating = "";
    $roomType = "Bedroom";
    $assembly = "Yes";
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

$cat = $conn->query("SELECT id, slug FROM categories WHERE category_name='Wardrobe' LIMIT 1")->fetch_assoc();
$total = $conn->query("SELECT COUNT(*) total FROM products WHERE category = 'Wardrobe'")->fetch_assoc()["total"] ?? 0;
echo "wardrobe_category_id=" . ($cat["id"] ?? "not_found") . "\n";
echo "wardrobe_category_slug=" . ($cat["slug"] ?? "not_found") . "\n";
echo "source_images=" . count($files) . "\n";
echo "inserted=$inserted\n";
echo "skipped_duplicates=$skipped\n";
echo "wardrobe_total=$total\n";
echo "errors=" . ($errors ? implode(" | ", $errors) : "none") . "\n";
?>
