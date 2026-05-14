<?php
if (php_sapi_name() !== "cli") include("auth.php");
header("Content-Type: text/plain");
include(__DIR__ . "/../backend/config/db.php");
include_once(__DIR__ . "/../backend/includes/product_images.php");

$sourceDir = realpath(__DIR__ . "/../Categories furniture/Bathroom accessories");
$uploadDir = realpath(__DIR__ . "/../uploads/products");
$category = "Bathroom Accessories";
$errors = [];
$inserted = 0;
$skipped = 0;

function bathSlug($text) {
    return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
}

if (!$sourceDir || !$uploadDir) die("errors: source/upload folder missing\n");

$conn->query("INSERT IGNORE INTO categories (category_name, slug, description, status) VALUES ('Bathroom Accessories', 'bathroom-accessories', 'Premium bathroom racks, mirrors, organizers and vanity accessories.', 'active')");
ensureProductImageColumns($conn);
ensureProductImagesSchema($conn);

$files = array_values(array_filter(scandir($sourceDir), function ($file) use ($sourceDir) {
    return is_file($sourceDir . DIRECTORY_SEPARATOR . $file) && preg_match('/\.(jpg|jpeg|png|webp)$/i', $file);
}));

$products = [
    ["Aurelia Gold Bath Accessory Set", 8999, 7499, "Gold", "Stainless steel and ceramic finish"],
    ["Verona Animal Accent Faucet", 12999, 10999, "Brass Gold", "Premium brass faucet"],
    ["Imperial Wall Mounted Towel Rack", 6999, 5799, "Champagne", "Light luxury metal rack"],
    ["Milano Matte Black Shower Drain", 3999, 3299, "Matte Black", "Anti-rust stainless steel"],
    ["Nordic Compact Black Toilet Unit", 21999, 18999, "Black", "Premium ceramic sanitary unit"],
    ["Celeste Marble Soap Dispenser", 3499, 2799, "Marble White", "Ceramic marble finish"],
    ["Regal Brass Vanity Organizer", 4999, 3999, "Brass", "Premium bathroom counter organizer"],
    ["LuxeWall Mirror Storage Cabinet", 15999, 13999, "Mirror & Oak", "Mirror cabinet with storage"],
    ["Florence Ceramic Bath Set", 5999, 4899, "Ivory", "Coordinated ceramic accessory set"],
    ["Noble Oak Vanity Shelf", 7499, 6299, "Oak", "Wall-mounted vanity shelf"],
    ["Eterno Chrome Shower Caddy", 5499, 4499, "Chrome", "Rust-resistant shower organizer"],
    ["Casa Royale Designer Wash Basin", 18999, 16499, "Gloss White", "Premium ceramic wash basin"],
    ["Opulence Gold Seven Piece Bath Kit", 9999, 8499, "Gold & Bamboo", "Luxury complete bath kit"]
];

foreach ($files as $index => $file) {
    $item = $products[$index] ?? ["Zafiro Luxe Bathroom Accessory " . ($index + 1), 4999 + ($index * 500), 3999 + ($index * 450), "Premium", "Luxury bathroom finish"];
    [$name, $originalPrice, $discountPrice, $color, $material] = $item;
    $sku = "ZC-BATH-" . str_pad((string) ($index + 1), 3, "0", STR_PAD_LEFT);

    $check = $conn->prepare("SELECT id FROM products WHERE name = ? OR sku = ? LIMIT 1");
    $check->bind_param("ss", $name, $sku);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $skipped++;
        continue;
    }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $targetName = "bathroom_" . time() . "_" . str_pad((string) ($index + 1), 2, "0", STR_PAD_LEFT) . "." . $ext;
    if (!copy($sourceDir . DIRECTORY_SEPARATOR . $file, $uploadDir . DIRECTORY_SEPARATOR . $targetName)) {
        $errors[] = "copy failed: $file";
        continue;
    }

    $image = "../uploads/products/" . $targetName;
    $price = $discountPrice;
    $stock = 8 + ($index % 14);
    $short = "$name adds refined utility and luxury detailing to modern bathroom spaces.";
    $full = "$short Crafted for premium washrooms with durable materials, elegant finish, and easy daily maintenance.";
    $slug = bathSlug($name);
    $brand = "Zafiro Casa";
    $specifications = "Premium bathroom accessory finish, durable build, easy-clean surface.";
    $dimensions = "Bathroom friendly compact size";
    $weight = "Lightweight accessory";
    $seating = "";
    $roomType = "Bathroom";
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

    if ($stmt->execute()) {
        syncProductImageColumnsToTable($conn, (int) $stmt->insert_id, [$image]);
        $inserted++;
    } else {
        $errors[] = "$name: " . $stmt->error;
    }
}

$total = $conn->query("SELECT COUNT(*) total FROM products WHERE category = 'Bathroom Accessories'")->fetch_assoc()["total"] ?? 0;
echo "source_images=" . count($files) . "\n";
echo "inserted=$inserted\n";
echo "skipped_duplicates=$skipped\n";
echo "bathroom_total=$total\n";
echo "errors=" . ($errors ? implode(" | ", $errors) : "none") . "\n";
?>
