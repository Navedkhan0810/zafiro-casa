<?php
if (php_sapi_name() !== "cli") include("auth.php");
header("Content-Type: text/plain");
include(__DIR__ . "/../backend/config/db.php");
include_once(__DIR__ . "/../backend/includes/product_images.php");

$sourceDir = realpath(__DIR__ . "/../Categories furniture/bar furniture");
$uploadDir = realpath(__DIR__ . "/../uploads/products");
$category = "Bar Furniture";
$errors = [];
$inserted = 0;
$skipped = 0;

function barSlug($text) {
    return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
}

if (!$sourceDir || !$uploadDir) die("errors: source/upload folder missing\n");

$conn->query("INSERT IGNORE INTO categories (category_name, slug, description, status) VALUES ('Bar Furniture', 'bar-furniture', 'Luxury bar cabinets, stools, counters and wine storage furniture.', 'active')");
ensureProductImageColumns($conn);
ensureProductImagesSchema($conn);

$files = array_values(array_filter(scandir($sourceDir), function ($file) use ($sourceDir) {
    return is_file($sourceDir . DIRECTORY_SEPARATOR . $file) && preg_match('/\.(jpg|jpeg|png|webp)$/i', $file);
}));

$products = [
    ["Aurelio Luxe Bar Cabinet", 42999, 37999, "Walnut", "Premium wood finish"],
    ["Verona Gold Frame Bar Stool", 14999, 12999, "Gold & Ivory", "Metal frame with cushioned seat"],
    ["Nordic Walnut Mini Bar Unit", 35999, 31999, "Walnut", "Compact wood bar storage"],
    ["Imperial Marble Bar Counter", 58999, 52999, "White Marble", "Marble finish counter top"],
    ["Velvet Crest High Bar Chair", 16999, 14499, "Charcoal", "Velvet upholstered high chair"],
    ["Milan Oak Wine Storage Console", 39999, 34999, "Oak", "Wine bottle and glass storage"],
    ["Regal Brass Home Bar Cabinet", 46999, 41999, "Brass & Brown", "Luxury brass accent cabinet"],
    ["Celeste Curved Bar Counter", 54999, 48999, "Mocha", "Curved premium serving counter"],
    ["Monarch Leatherette Bar Chair", 18999, 15999, "Tan", "Padded leatherette bar seating"],
    ["Eterno Glass Door Bar Unit", 44999, 39999, "Smoked Glass", "Glass door display bar"],
    ["Florence Rattan Bar Trolley", 23999, 20499, "Natural Cane", "Mobile bar trolley design"],
    ["Noble Black Metal Bar Stool", 12999, 10999, "Matte Black", "Slim metal frame stool"],
    ["Casa Royale Cocktail Cabinet", 49999, 44999, "Champagne", "Premium cocktail storage unit"],
    ["Opulence Marble Wine Console", 52999, 47499, "Marble & Walnut", "Wine console with luxury top"],
    ["Serene Compact Bar Cart", 21999, 18499, "Walnut & Black", "Compact rolling bar cart"],
    ["Heritage Club Bar Storage", 57999, 51999, "Dark Walnut", "Statement club-style bar storage"]
];

foreach ($files as $index => $file) {
    $item = $products[$index] ?? ["Zafiro Signature Bar Furniture " . ($index + 1), 29999 + ($index * 1800), 26999 + ($index * 1600), "Walnut", "Premium bar furniture finish"];
    [$name, $originalPrice, $discountPrice, $color, $material] = $item;
    $sku = "ZC-BAR-" . str_pad((string) ($index + 1), 3, "0", STR_PAD_LEFT);

    $check = $conn->prepare("SELECT id FROM products WHERE name = ? OR sku = ? LIMIT 1");
    $check->bind_param("ss", $name, $sku);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $skipped++;
        continue;
    }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $targetName = "bar_" . time() . "_" . str_pad((string) ($index + 1), 2, "0", STR_PAD_LEFT) . "." . $ext;
    if (!copy($sourceDir . DIRECTORY_SEPARATOR . $file, $uploadDir . DIRECTORY_SEPARATOR . $targetName)) {
        $errors[] = "copy failed: $file";
        continue;
    }

    $image = "../uploads/products/" . $targetName;
    $price = $discountPrice;
    $stock = 5 + ($index % 10);
    $short = "$name designed for premium home bars, lounge corners, and refined entertaining spaces.";
    $full = "$short Built with luxury materials, elegant proportions, practical storage, and a polished Zafiro Casa finish for modern Indian interiors.";
    $slug = barSlug($name);
    $brand = "Zafiro Casa";
    $specifications = "Luxury bar furniture finish, durable frame, premium storage detailing.";
    $dimensions = "Showroom standard bar furniture size";
    $weight = "Standard furniture weight";
    $seating = str_contains(strtolower($name), "stool") || str_contains(strtolower($name), "chair") ? "1 Seater" : "";
    $roomType = "Bar";
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

$totalBar = $conn->query("SELECT COUNT(*) total FROM products WHERE category = 'Bar Furniture'")->fetch_assoc()["total"] ?? 0;
echo "source_images=" . count($files) . "\n";
echo "inserted=$inserted\n";
echo "skipped_duplicates=$skipped\n";
echo "bar_total=$totalBar\n";
echo "errors=" . ($errors ? implode(" | ", $errors) : "none") . "\n";
?>
