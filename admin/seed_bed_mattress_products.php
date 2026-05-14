<?php
if (php_sapi_name() !== "cli") include("auth.php");
header("Content-Type: text/plain");
include(__DIR__ . "/../backend/config/db.php");
include_once(__DIR__ . "/../backend/includes/product_images.php");

$sourceDir = realpath(__DIR__ . "/../Categories furniture/bed with matresses");
$uploadDir = realpath(__DIR__ . "/../uploads/products");
$category = "Bed with Mattress";
$errors = [];
$inserted = 0;
$skipped = 0;

function bedMattressSlug($text) {
    return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
}

if (!$sourceDir || !$uploadDir) die("errors: source/upload folder missing\n");

$conn->query("INSERT IGNORE INTO categories (category_name, slug, description, status) VALUES ('Bed with Mattress', 'bed-with-mattress', 'Luxury beds paired with premium mattresses for complete bedroom comfort.', 'active')");
ensureProductImageColumns($conn);
ensureProductImagesSchema($conn);

$files = array_values(array_filter(scandir($sourceDir), function ($file) use ($sourceDir) {
    return is_file($sourceDir . DIRECTORY_SEPARATOR . $file) && preg_match('/\.(jpg|jpeg|png|webp)$/i', $file);
}));

$products = [
    ["Verona King Bed with Plush Mattress", 68999, 61999, "Ivory", "upholstered headboard with plush mattress", "King Size"],
    ["Aurelio Upholstered Storage Bed", 74999, 66999, "Beige", "fabric upholstery with hydraulic storage", "King Size"],
    ["Nordic Walnut Queen Bed Set", 58999, 52999, "Walnut", "wooden frame with comfort mattress", "Queen Size"],
    ["Imperial Tufted Bed with Memory Foam Mattress", 82999, 73999, "Charcoal", "tufted headboard and memory foam mattress", "King Size"],
    ["Milan Luxe Hydraulic Bed", 79999, 71999, "Mocha", "hydraulic storage with premium mattress", "King Size"],
    ["Royal Crest Velvet Bed Frame", 69999, 62999, "Royal Purple", "velvet headboard with orthopedic mattress", "Queen Size"],
    ["Celeste White Platform Bed with Mattress", 54999, 48999, "White", "minimal platform frame with soft mattress", "Queen Size"],
    ["Monarch Panel Bed with Pocket Spring Mattress", 71999, 64999, "Walnut", "panel headboard and pocket spring mattress", "King Size"],
    ["Regal Kids Comfort Bed with Mattress", 42999, 37999, "Pastel", "kids bed with cozy foam mattress", "Single Size"],
    ["Florence Beige Wingback Bed Set", 76999, 68999, "Beige", "wingback upholstered headboard with luxury mattress", "King Size"],
    ["Noble Oak Storage Bed with Foam Mattress", 63999, 56999, "Oak", "storage base with high comfort foam mattress", "Queen Size"],
    ["Eterno Low Profile Bed with Mattress", 59999, 53999, "Grey", "low profile frame with premium mattress", "Queen Size"],
    ["Casa Royale Mirror Back Bed Set", 89999, 80999, "Champagne", "statement bedroom bed with plush mattress", "King Size"],
    ["Opulence Designer Bed with Orthopedic Mattress", 84999, 75999, "Cream", "designer headboard and orthopedic mattress", "King Size"],
    ["Serene Cozy Queen Bed with Mattress", 57999, 51999, "Soft Grey", "cozy queen frame with comfort mattress", "Queen Size"],
    ["Heritage Wooden King Bed Set", 69999, 62999, "Dark Walnut", "solid wooden frame with premium mattress", "King Size"],
    ["Valencia LED Headboard Bed with Mattress", 78999, 70999, "Ivory", "LED accent headboard with plush mattress", "King Size"],
    ["Aurelia Butterfly Kids Bed with Mattress", 45999, 40999, "Pink", "kids theme bed with soft mattress", "Single Size"],
    ["Milano Urban Storage Bed Set", 72999, 64999, "Graphite", "urban storage bed with foam mattress", "King Size"],
    ["Verona White Luxe Bed with Mattress", 68999, 61999, "White", "premium white frame with comfort mattress", "King Size"],
    ["Imperial Cream Tufted Queen Bed", 65999, 58999, "Cream", "tufted upholstered bed with mattress", "Queen Size"],
    ["Nordic Compact Bed with Mattress", 49999, 44999, "Natural Wood", "compact wooden bed with mattress", "Queen Size"],
    ["Royal Purple Bedroom Bed Set", 76999, 68999, "Purple", "luxury purple bedroom bed with mattress", "King Size"],
    ["Celeste Dreamy White Bed Set", 71999, 64999, "White", "dreamy white bed with plush mattress", "King Size"],
    ["Florence Romantic Upholstered Bed", 73999, 65999, "Blush Beige", "romantic upholstered frame with mattress", "Queen Size"],
    ["Monarch Premium Master Bed Set", 92999, 82999, "Walnut & Beige", "master bedroom bed with luxury mattress", "King Size"]
];

foreach ($files as $index => $file) {
    $item = $products[$index] ?? ["Zafiro Luxe Bed with Mattress " . ($index + 1), 59999 + ($index * 1800), 53999 + ($index * 1600), "Premium", "luxury frame with mattress", "Queen Size"];
    [$name, $originalPrice, $discountPrice, $color, $material, $size] = $item;
    $sku = "ZC-BEDMAT-" . str_pad((string) ($index + 1), 3, "0", STR_PAD_LEFT);

    $check = $conn->prepare("SELECT id FROM products WHERE name = ? OR sku = ? LIMIT 1");
    $check->bind_param("ss", $name, $sku);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $skipped++;
        continue;
    }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $targetName = "bed_mattress_" . time() . "_" . str_pad((string) ($index + 1), 2, "0", STR_PAD_LEFT) . "." . $ext;
    if (!copy($sourceDir . DIRECTORY_SEPARATOR . $file, $uploadDir . DIRECTORY_SEPARATOR . $targetName)) {
        $errors[] = "copy failed: $file";
        continue;
    }

    $image = "../uploads/products/" . $targetName;
    $price = $discountPrice;
    $stock = 4 + ($index % 9);
    $short = "$name pairs a refined bed frame with a premium mattress for complete luxury comfort.";
    $full = "$short Features $material, balanced bedroom proportions, durable support, and a showroom-grade Zafiro Casa finish for modern Indian homes.";
    $slug = bedMattressSlug($name);
    $brand = "Zafiro Casa";
    $specifications = "$size, premium mattress included, luxury finish, durable support frame.";
    $dimensions = "$size bedroom standard";
    $weight = "Heavy furniture weight";
    $seating = "";
    $roomType = "Bedroom";
    $assembly = "No";
    $featured = $index < 3 ? 1 : 0;
    $trending = $index % 6 === 0 ? 1 : 0;
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

$total = $conn->query("SELECT COUNT(*) total FROM products WHERE category = 'Bed with Mattress'")->fetch_assoc()["total"] ?? 0;
echo "source_images=" . count($files) . "\n";
echo "inserted=$inserted\n";
echo "skipped_duplicates=$skipped\n";
echo "bed_with_mattress_total=$total\n";
echo "errors=" . ($errors ? implode(" | ", $errors) : "none") . "\n";
?>
