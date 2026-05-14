<?php
if (php_sapi_name() !== "cli") include("auth.php");
header("Content-Type: text/plain");
include(__DIR__ . "/../backend/config/db.php");
include_once(__DIR__ . "/../backend/includes/product_images.php");

$sourceDir = realpath(__DIR__ . "/../Categories furniture/Beds");
$uploadDir = realpath(__DIR__ . "/../uploads/products");
$category = "Beds";
$errors = [];
$inserted = 0;
$skipped = 0;

function bedsSlug($text) {
    return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
}

if (!$sourceDir || !$uploadDir) die("errors: source/upload folder missing\n");

$conn->query("INSERT IGNORE INTO categories (category_name, slug, description, status) VALUES ('Beds', 'beds', 'Premium beds, upholstered frames, storage beds and luxury bedroom centerpieces.', 'active')");
ensureProductImageColumns($conn);
ensureProductImagesSchema($conn);

$files = array_values(array_filter(scandir($sourceDir), function ($file) use ($sourceDir) {
    return is_file($sourceDir . DIRECTORY_SEPARATOR . $file) && preg_match('/\.(jpg|jpeg|png|webp)$/i', $file);
}));

$products = [
    ["Verona Upholstered King Bed", 64999, 57999, "upholstered headboard with luxury finish"],
    ["Aurelio Premium Bed Frame", 58999, 52999, "premium bed frame with refined detailing"],
    ["Milan Luxe Platform Bed", 54999, 48999, "modern platform design with clean proportions"],
    ["Nordic Queen Bed Frame", 49999, 44999, "queen size frame with calm modern styling"],
    ["Imperial Tufted Bed", 73999, 65999, "tufted headboard with padded comfort"],
    ["Royal Crest Storage Bed", 78999, 69999, "storage bed with premium bedroom utility"],
    ["Celeste Soft Padded Bed", 62999, 55999, "soft padded headboard and luxury bedroom finish"],
    ["Monarch Designer King Bed", 84999, 75999, "designer king size frame with statement presence"],
    ["Regal Modern Panel Bed", 59999, 53999, "modern panel headboard with durable support"],
    ["Florence Luxury Queen Bed", 56999, 50999, "queen size bed with premium finish"],
    ["Noble Low Profile Bed", 51999, 45999, "low profile frame with contemporary styling"],
    ["Eterno Master Bedroom Bed", 89999, 79999, "master bedroom bed with grand proportions"],
    ["Casa Royale Statement Bed", 92999, 82999, "statement bed for luxury bedroom interiors"],
    ["Opulence Wingback Bed", 76999, 68999, "wingback headboard with plush bedroom styling"],
    ["Serene Minimal Platform Bed", 47999, 42999, "minimal platform frame for refined bedrooms"],
    ["Heritage Classic Bed Frame", 58999, 52999, "classic frame with timeless finish"],
    ["Valencia Luxury Headboard Bed", 69999, 62999, "large padded headboard with premium detailing"],
    ["Aurelia Dream Bed Frame", 61999, 54999, "dreamy bedroom frame with soft luxury styling"],
    ["Milano Urban King Bed", 67999, 60999, "urban king bed with polished finish"],
    ["Verona Comfort Queen Bed", 52999, 46999, "comfort queen bed with balanced support"],
    ["Imperial Palace Bed", 99999, 89999, "palace inspired luxury bed design"],
    ["Nordic Compact Bed", 44999, 39999, "compact bed frame for modern homes"],
    ["Royal Scalloped Headboard Bed", 81999, 72999, "scalloped headboard with premium bedroom appeal"],
    ["Celeste Cloud Bedroom Bed", 74999, 66999, "cloud-inspired soft headboard bed"],
    ["Florence Grand Bed Frame", 87999, 77999, "grand frame with luxury bedroom presence"],
    ["Monarch Premium Double Bed", 64999, 57999, "premium double bed with sturdy frame"],
    ["Regal Princess Bed", 89999, 80999, "royal bedroom bed with elegant detailing"],
    ["Casa Royale Palace Bed", 109999, 97999, "palace-style bed with statement luxury finish"],
    ["Opulence Master Bed", 94999, 84999, "master bed with upscale comfort styling"],
    ["Serene Smart Bedroom Bed", 69999, 62999, "smart elegant bed design for premium interiors"],
    ["Heritage Luxury Bedstead", 61999, 54999, "luxury bedstead with timeless bedroom style"],
    ["Valencia Modern Bedroom Bed", 58999, 52999, "modern bedroom bed with refined finish"],
    ["Aurelio Signature King Bed", 78999, 69999, "signature king bed with premium support"]
];

foreach ($files as $index => $file) {
    $item = $products[$index] ?? ["Zafiro Signature Bed " . ($index + 1), 59999 + ($index * 1800), 53999 + ($index * 1600), "premium bed design"];
    [$name, $originalPrice, $discountPrice, $feature] = $item;
    $sku = "ZC-BEDS-" . str_pad((string) ($index + 1), 3, "0", STR_PAD_LEFT);

    $check = $conn->prepare("SELECT id FROM products WHERE name = ? OR sku = ? LIMIT 1");
    $check->bind_param("ss", $name, $sku);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $skipped++;
        continue;
    }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $targetName = "beds_" . time() . "_" . str_pad((string) ($index + 1), 2, "0", STR_PAD_LEFT) . "." . $ext;
    if (!copy($sourceDir . DIRECTORY_SEPARATOR . $file, $uploadDir . DIRECTORY_SEPARATOR . $targetName)) {
        $errors[] = "copy failed: $file";
        continue;
    }

    $image = "../uploads/products/" . $targetName;
    $price = $discountPrice;
    $stock = 3 + ($index % 10);
    $short = "$name brings premium bedroom comfort with $feature.";
    $full = "$short Built for modern Indian homes with durable construction, refined proportions, luxury finish, and reliable everyday support.";
    $slug = bedsSlug($name);
    $brand = "Zafiro Casa";
    $specifications = "Premium bed frame, luxury finish, durable support, bedroom centerpiece design.";
    $material = "Premium wood, upholstery, and engineered support";
    $color = "Premium finish";
    $dimensions = ($index % 3 === 0 ? "King Size" : "Queen Size") . " bedroom standard";
    $weight = "Heavy furniture weight";
    $seating = "";
    $roomType = "Bedroom";
    $assembly = "No";
    $featured = $index < 4 ? 1 : 0;
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

$total = $conn->query("SELECT COUNT(*) total FROM products WHERE category = 'Beds'")->fetch_assoc()["total"] ?? 0;
echo "source_images=" . count($files) . "\n";
echo "inserted=$inserted\n";
echo "skipped_duplicates=$skipped\n";
echo "beds_total=$total\n";
echo "errors=" . ($errors ? implode(" | ", $errors) : "none") . "\n";
?>
