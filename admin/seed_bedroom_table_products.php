<?php
if (php_sapi_name() !== "cli") include("auth.php");
header("Content-Type: text/plain");
include(__DIR__ . "/../backend/config/db.php");
include_once(__DIR__ . "/../backend/includes/product_images.php");

$sourceDir = realpath(__DIR__ . "/../Categories furniture/bedroom table");
$uploadDir = realpath(__DIR__ . "/../uploads/products");
$category = "Bedroom Table";
$errors = [];
$inserted = 0;
$skipped = 0;

function bedroomTableSlug($text) {
    return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
}

if (!$sourceDir || !$uploadDir) die("errors: source/upload folder missing\n");

$conn->query("INSERT IGNORE INTO categories (category_name, slug, description, status) VALUES ('Bedroom Table', 'bedroom-table', 'Premium bedside tables, nightstands and elegant bedroom side tables.', 'active')");
ensureProductImageColumns($conn);
ensureProductImagesSchema($conn);

$files = array_values(array_filter(scandir($sourceDir), function ($file) use ($sourceDir) {
    return is_file($sourceDir . DIRECTORY_SEPARATOR . $file) && preg_match('/\.(jpg|jpeg|png|webp)$/i', $file);
}));

$products = [
    ["Arbore Luxe Bedroom Credenza", 24999, 21999, "Premium sculpted finish", "Elegant bedroom credenza with premium storage presence"],
    ["Biomorphic Designer Bedroom Sideboard", 27999, 24499, "Designer curved finish", "Statement bedroom sideboard with artistic form"],
    ["Aurelio Luxe Bedside Table", 14999, 12999, "Premium matte finish", "Compact bedside table with refined luxury styling"],
    ["Verona Premium Nightstand", 16999, 14499, "Luxury laminate finish", "Premium nightstand for modern bedroom interiors"],
    ["Milan Elegant Bedroom Side Table", 13999, 11999, "Smooth premium finish", "Elegant side table for bedside utility"],
    ["Nordic Compact Bedside Cabinet", 17999, 15499, "Minimal luxury finish", "Compact bedside cabinet with storage"],
    ["Imperial Drawer Bedside Table", 18999, 16499, "Soft-close drawer finish", "Bedside table with drawer storage"],
    ["Royal Crest Bedroom Accent Table", 15999, 13499, "Premium accent finish", "Luxury bedroom accent table"],
    ["Celeste Luxe Night Table", 12999, 10999, "Clean matte finish", "Refined night table for compact bedrooms"],
    ["Monarch Storage Bedside Unit", 21999, 18999, "Premium storage finish", "Bedside storage unit with practical compartments"],
    ["Regal Designer Side Table", 14999, 12499, "Designer furniture finish", "Designer side table for bedroom corners"],
    ["Florence Modern Nightstand", 17999, 15499, "Smooth luxury finish", "Modern nightstand with premium detailing"],
    ["Noble Compact Bedroom Table", 11999, 9999, "Compact premium finish", "Space-saving bedroom table"],
    ["Eterno Luxe Drawer Nightstand", 19999, 17499, "Drawer storage finish", "Luxury nightstand with drawer storage"],
    ["Casa Royale Bedside Console", 22999, 19999, "Premium console finish", "Elegant bedside console with storage"],
    ["Opulence Concrete Finish Side Table", 18999, 15999, "Lightweight concrete finish", "Bedroom side table with warm natural texture"],
    ["Serene Wood Drawer Nightstand", 20999, 17999, "Wood finish", "Wooden nightstand with drawer storage"],
    ["Heritage Premium Bedside Table", 16999, 14499, "Classic premium finish", "Timeless bedside table for luxury bedrooms"]
];

foreach ($files as $index => $file) {
    $item = $products[$index] ?? ["Zafiro Luxe Bedroom Table " . ($index + 1), 14999 + ($index * 700), 12999 + ($index * 600), "Premium finish", "Luxury bedroom table"];
    [$name, $originalPrice, $discountPrice, $material, $descBase] = $item;
    $sku = "ZC-BTBL-" . str_pad((string) ($index + 1), 3, "0", STR_PAD_LEFT);

    $check = $conn->prepare("SELECT id FROM products WHERE name = ? OR sku = ? LIMIT 1");
    $check->bind_param("ss", $name, $sku);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $skipped++;
        continue;
    }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $targetName = "bedroom_table_" . time() . "_" . str_pad((string) ($index + 1), 2, "0", STR_PAD_LEFT) . "." . $ext;
    if (!copy($sourceDir . DIRECTORY_SEPARATOR . $file, $uploadDir . DIRECTORY_SEPARATOR . $targetName)) {
        $errors[] = "copy failed: $file";
        continue;
    }

    $image = "../uploads/products/" . $targetName;
    $price = $discountPrice;
    $stock = 6 + ($index % 12);
    $short = "$name adds premium bedside utility with refined bedroom styling.";
    $full = "$short $descBase, crafted with $material, balanced proportions, and Zafiro Casa luxury detailing.";
    $slug = bedroomTableSlug($name);
    $brand = "Zafiro Casa";
    $specifications = "Premium bedroom table, durable build, compact bedside design, easy maintenance.";
    $color = "Premium";
    $dimensions = "Bedroom table standard size";
    $weight = "Standard furniture weight";
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

$total = $conn->query("SELECT COUNT(*) total FROM products WHERE category = 'Bedroom Table'")->fetch_assoc()["total"] ?? 0;
echo "source_images=" . count($files) . "\n";
echo "inserted=$inserted\n";
echo "skipped_duplicates=$skipped\n";
echo "bedroom_table_total=$total\n";
echo "errors=" . ($errors ? implode(" | ", $errors) : "none") . "\n";
?>
