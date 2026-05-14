<?php
include("auth.php");
include("../backend/config/db.php");

$message = "";
$messageType = "";

function homeEditorDeleteForm($type, $id, $anchor = "") {
    $anchorInput = $anchor !== "" ? '<input type="hidden" name="redirect_anchor" value="' . htmlspecialchars($anchor, ENT_QUOTES, 'UTF-8') . '">' : "";
    return '<form method="POST" action="manage_hero_slider.php" class="inline-admin-form home-editor-delete-form">'
        . '<input type="hidden" name="action" value="delete_section">'
        . '<input type="hidden" name="type" value="' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '">'
        . '<input type="hidden" name="id" value="' . (int) $id . '">'
        . $anchorInput
        . '<button type="submit" class="admin-link-button danger">Delete</button>'
        . '</form>';
}

$conn->query("CREATE TABLE IF NOT EXISTS hero_slides (
    id INT AUTO_INCREMENT PRIMARY KEY,
    image_path VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    status VARCHAR(20) DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("ALTER TABLE hero_slides ADD COLUMN IF NOT EXISTS title VARCHAR(160) DEFAULT 'Zafiro Casa Luxury Living' AFTER image_path");
$conn->query("ALTER TABLE hero_slides ADD COLUMN IF NOT EXISTS subtitle VARCHAR(255) DEFAULT 'Elegant sofas, beds, dining and decor crafted for modern homes.' AFTER title");
$conn->query("ALTER TABLE hero_slides ADD COLUMN IF NOT EXISTS button_text VARCHAR(80) DEFAULT 'Explore Collection' AFTER subtitle");
$conn->query("ALTER TABLE hero_slides ADD COLUMN IF NOT EXISTS button_link VARCHAR(255) DEFAULT '#categories' AFTER button_text");

$conn->query("CREATE TABLE IF NOT EXISTS homepage_discount_offers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    offer_title VARCHAR(180) NOT NULL,
    discount_text VARCHAR(120) DEFAULT '',
    link_type VARCHAR(20) DEFAULT 'category',
    product_id INT NULL,
    category_slug VARCHAR(160) DEFAULT '',
    start_date DATE NULL,
    end_date DATE NULL,
    image_path VARCHAR(255) DEFAULT '',
    sort_order INT DEFAULT 0,
    status VARCHAR(20) DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS homepage_why_points (
    id INT AUTO_INCREMENT PRIMARY KEY,
    icon_class VARCHAR(120) DEFAULT 'fa-solid fa-gem',
    title VARCHAR(160) NOT NULL,
    description TEXT,
    sort_order INT DEFAULT 0,
    status VARCHAR(20) DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS homepage_room_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(160) NOT NULL,
    image_path VARCHAR(255) DEFAULT '',
    link_url VARCHAR(255) DEFAULT '',
    sort_order INT DEFAULT 0,
    status VARCHAR(20) DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS homepage_feature_boxes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(160) NOT NULL,
    subtitle VARCHAR(255) DEFAULT '',
    button_text VARCHAR(80) DEFAULT '',
    button_link VARCHAR(255) DEFAULT '',
    image_path VARCHAR(255) DEFAULT '',
    sort_order INT DEFAULT 0,
    status VARCHAR(20) DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS homepage_video_banner (
    id INT AUTO_INCREMENT PRIMARY KEY,
    video_path VARCHAR(255) DEFAULT '',
    heading VARCHAR(160) DEFAULT 'Zafiro Casa Luxury Living',
    subtitle VARCHAR(255) DEFAULT 'Premium furniture crafted for modern homes',
    button_text VARCHAR(80) DEFAULT 'Explore Collection',
    button_link VARCHAR(255) DEFAULT 'product-list.php',
    sort_order INT DEFAULT 0,
    status VARCHAR(20) DEFAULT 'active',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("ALTER TABLE homepage_video_banner ADD COLUMN IF NOT EXISTS sort_order INT DEFAULT 0");

function uploadHomeImage($field, $folder) {
    $error = "";
    $path = zafiro_secure_upload($_FILES[$field] ?? [], "../uploads/" . $folder, "../uploads/" . $folder, ["jpg", "jpeg", "png", "webp"], ["image/jpeg", "image/png", "image/webp"], 4 * 1024 * 1024, $folder, $error);
    if ($error !== "") throw new RuntimeException($error);
    return $path;
}

function uploadHomeVideo($field) {
    $error = "";
    $path = zafiro_secure_upload($_FILES[$field] ?? [], "../uploads/homepage_video", "../uploads/homepage_video", ["mp4"], ["video/mp4", "application/mp4"], 60 * 1024 * 1024, "home_video", $error);
    if ($error !== "") throw new RuntimeException($error);
    return $path;
}

function statusValue($value) {
    return $value === "inactive" ? "inactive" : "active";
}

function defaultRightHeroBanners() {
    return [
        [
            "title" => "Living Room Comfort",
            "subtitle" => "New Arrivals",
            "button_text" => "",
            "button_link" => "living.php",
            "image_path" => "https://images.unsplash.com/photo-1600210492486-724fe5c67fb0?auto=format&fit=crop&w=1200&q=95",
            "sort_order" => 1
        ],
        [
            "title" => "Bedroom Essentials",
            "subtitle" => "Limited Offer",
            "button_text" => "",
            "button_link" => "bedroom.php",
            "image_path" => "https://images.unsplash.com/photo-1616594039964-ae9021a400a0?auto=format&fit=crop&w=1100&q=90",
            "sort_order" => 2
        ]
    ];
}

function insertRightHeroBanner($conn, $banner) {
    $status = "active";
    $stmt = $conn->prepare("INSERT INTO homepage_feature_boxes (title, subtitle, button_text, button_link, image_path, sort_order, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssis", $banner["title"], $banner["subtitle"], $banner["button_text"], $banner["button_link"], $banner["image_path"], $banner["sort_order"], $status);
    $stmt->execute();
}

function ensureRightHeroBanners($conn) {
    $defaults = defaultRightHeroBanners();
    $rows = [];
    $result = $conn->query("SELECT id, title FROM homepage_feature_boxes ORDER BY sort_order ASC, id ASC LIMIT 2");
    if ($result) while ($row = $result->fetch_assoc()) $rows[] = $row;

    if (count($rows) === 0) {
        foreach ($defaults as $banner) insertRightHeroBanner($conn, $banner);
        return;
    }

    if (count($rows) === 1) {
        $existingTitle = strtolower($rows[0]["title"] ?? "");
        $missing = strpos($existingTitle, "bedroom") !== false ? $defaults[0] : $defaults[1];
        insertRightHeroBanner($conn, $missing);
    }

    $conn->query("UPDATE homepage_feature_boxes SET sort_order = 1 WHERE (sort_order IS NULL OR sort_order < 1) ORDER BY id ASC LIMIT 1");
}

try {
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $action = $_POST["action"] ?? "";
        if ($action === "delete_section") {
            $id = (int) ($_POST["id"] ?? 0);
            $type = $_POST["type"] ?? "";
            $tables = [
                "hero" => "hero_slides",
                "offer" => "homepage_discount_offers",
                "why" => "homepage_why_points",
                "room" => "homepage_room_cards",
                "feature" => "homepage_feature_boxes",
                "video" => "homepage_video_banner"
            ];
            if ($id > 0 && isset($tables[$type])) {
                $stmt = $conn->prepare("DELETE FROM {$tables[$type]} WHERE id=?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
            }
            header("Location: manage_hero_slider.php" . ($_POST["redirect_anchor"] ?? ""));
            exit;
        }

        if ($action === "hero") {
            $id = (int) ($_POST["id"] ?? 0);
            $title = trim($_POST["title"] ?? "");
            $subtitle = trim($_POST["subtitle"] ?? "");
            $buttonText = trim($_POST["button_text"] ?? "");
            $buttonLink = trim($_POST["button_link"] ?? "");
            $sortOrder = (int) ($_POST["sort_order"] ?? 0);
            $status = statusValue($_POST["status"] ?? "active");
            $image = uploadHomeImage("image", "hero_slides");
            if ($title === "") throw new RuntimeException("Hero title is required.");
            if ($id > 0) {
                if ($image) {
                    $stmt = $conn->prepare("UPDATE hero_slides SET image_path=?, title=?, subtitle=?, button_text=?, button_link=?, sort_order=?, status=? WHERE id=?");
                    $stmt->bind_param("sssssisi", $image, $title, $subtitle, $buttonText, $buttonLink, $sortOrder, $status, $id);
                } else {
                    $stmt = $conn->prepare("UPDATE hero_slides SET title=?, subtitle=?, button_text=?, button_link=?, sort_order=?, status=? WHERE id=?");
                    $stmt->bind_param("ssssisi", $title, $subtitle, $buttonText, $buttonLink, $sortOrder, $status, $id);
                }
                $stmt->execute();
                $message = "Hero slider updated.";
            } else {
                if (!$image) throw new RuntimeException("Hero image is required.");
                $stmt = $conn->prepare("INSERT INTO hero_slides (image_path, title, subtitle, button_text, button_link, sort_order, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssis", $image, $title, $subtitle, $buttonText, $buttonLink, $sortOrder, $status);
                $stmt->execute();
                $message = "Hero slider added.";
            }
        }

        if ($action === "offer") {
            $id = (int) ($_POST["id"] ?? 0);
            $title = trim($_POST["offer_title"] ?? "");
            $discount = trim($_POST["discount_text"] ?? "");
            $linkType = ($_POST["link_type"] ?? "category") === "product" ? "product" : "category";
            $productId = (int) ($_POST["product_id"] ?? 0);
            $categorySlug = trim($_POST["category_slug"] ?? "");
            $startDate = $_POST["start_date"] ?: null;
            $endDate = $_POST["end_date"] ?: null;
            $sortOrder = (int) ($_POST["sort_order"] ?? 0);
            $status = statusValue($_POST["status"] ?? "active");
            $image = uploadHomeImage("image", "homepage_offers");
            if ($title === "") throw new RuntimeException("Offer title is required.");
            if ($id > 0 && $image) {
                $stmt = $conn->prepare("UPDATE homepage_discount_offers SET offer_title=?, discount_text=?, link_type=?, product_id=?, category_slug=?, start_date=?, end_date=?, image_path=?, sort_order=?, status=? WHERE id=?");
                $stmt->bind_param("sssissssisi", $title, $discount, $linkType, $productId, $categorySlug, $startDate, $endDate, $image, $sortOrder, $status, $id);
            } elseif ($id > 0) {
                $stmt = $conn->prepare("UPDATE homepage_discount_offers SET offer_title=?, discount_text=?, link_type=?, product_id=?, category_slug=?, start_date=?, end_date=?, sort_order=?, status=? WHERE id=?");
                $stmt->bind_param("sssisssisi", $title, $discount, $linkType, $productId, $categorySlug, $startDate, $endDate, $sortOrder, $status, $id);
            } else {
                $stmt = $conn->prepare("INSERT INTO homepage_discount_offers (offer_title, discount_text, link_type, product_id, category_slug, start_date, end_date, image_path, sort_order, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssissssis", $title, $discount, $linkType, $productId, $categorySlug, $startDate, $endDate, $image, $sortOrder, $status);
            }
            $stmt->execute();
            $message = "Discount offer saved.";
        }

        if ($action === "why") {
            $id = (int) ($_POST["id"] ?? 0);
            $icon = trim($_POST["icon_class"] ?? "fa-solid fa-gem");
            $title = trim($_POST["title"] ?? "");
            $description = trim($_POST["description"] ?? "");
            $sortOrder = (int) ($_POST["sort_order"] ?? 0);
            $status = statusValue($_POST["status"] ?? "active");
            if ($title === "") throw new RuntimeException("Why point title is required.");
            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE homepage_why_points SET icon_class=?, title=?, description=?, sort_order=?, status=? WHERE id=?");
                $stmt->bind_param("sssisi", $icon, $title, $description, $sortOrder, $status, $id);
            } else {
                $stmt = $conn->prepare("INSERT INTO homepage_why_points (icon_class, title, description, sort_order, status) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssis", $icon, $title, $description, $sortOrder, $status);
            }
            $stmt->execute();
            $message = "Why Choose point saved.";
        }

        if ($action === "room" || $action === "feature") {
            $id = (int) ($_POST["id"] ?? 0);
            $title = trim($_POST["title"] ?? "");
            $sortOrder = (int) ($_POST["sort_order"] ?? 0);
            $status = statusValue($_POST["status"] ?? "active");
            if ($title === "") throw new RuntimeException("Title is required.");

            if ($action === "room") {
                $link = trim($_POST["link_url"] ?? "");
                $image = uploadHomeImage("image", "homepage_room");
                if ($id > 0 && $image) {
                    $stmt = $conn->prepare("UPDATE homepage_room_cards SET title=?, image_path=?, link_url=?, sort_order=?, status=? WHERE id=?");
                    $stmt->bind_param("sssisi", $title, $image, $link, $sortOrder, $status, $id);
                } elseif ($id > 0) {
                    $stmt = $conn->prepare("UPDATE homepage_room_cards SET title=?, link_url=?, sort_order=?, status=? WHERE id=?");
                    $stmt->bind_param("ssisi", $title, $link, $sortOrder, $status, $id);
                } else {
                    $stmt = $conn->prepare("INSERT INTO homepage_room_cards (title, image_path, link_url, sort_order, status) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssis", $title, $image, $link, $sortOrder, $status);
                }
            } else {
                $subtitle = trim($_POST["subtitle"] ?? "");
                $buttonText = trim($_POST["button_text"] ?? "");
                $buttonLink = trim($_POST["button_link"] ?? "");
                $image = uploadHomeImage("image", "homepage_feature");
                if ($id > 0 && $image) {
                    $stmt = $conn->prepare("UPDATE homepage_feature_boxes SET title=?, subtitle=?, button_text=?, button_link=?, image_path=?, sort_order=?, status=? WHERE id=?");
                    $stmt->bind_param("sssssisi", $title, $subtitle, $buttonText, $buttonLink, $image, $sortOrder, $status, $id);
                } elseif ($id > 0) {
                    $stmt = $conn->prepare("UPDATE homepage_feature_boxes SET title=?, subtitle=?, button_text=?, button_link=?, sort_order=?, status=? WHERE id=?");
                    $stmt->bind_param("ssssisi", $title, $subtitle, $buttonText, $buttonLink, $sortOrder, $status, $id);
                } else {
                    $stmt = $conn->prepare("INSERT INTO homepage_feature_boxes (title, subtitle, button_text, button_link, image_path, sort_order, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssssis", $title, $subtitle, $buttonText, $buttonLink, $image, $sortOrder, $status);
                }
            }
            $stmt->execute();
            $message = $action === "room" ? "Room inspiration card saved." : "Right hero banner saved.";
        }

        if ($action === "video") {
            $id = (int) ($_POST["id"] ?? 0);
            $heading = trim($_POST["heading"] ?? "Zafiro Casa Luxury Living");
            $subtitle = trim($_POST["subtitle"] ?? "Premium furniture crafted for modern homes");
            $buttonText = trim($_POST["button_text"] ?? "Explore Collection");
            $buttonLink = trim($_POST["button_link"] ?? "product-list.php");
            $sortOrder = (int) ($_POST["sort_order"] ?? 0);
            $status = statusValue($_POST["status"] ?? "active");
            $video = uploadHomeVideo("video");
            if ($id > 0 && $video) {
                $stmt = $conn->prepare("UPDATE homepage_video_banner SET video_path=?, heading=?, subtitle=?, button_text=?, button_link=?, sort_order=?, status=? WHERE id=?");
                $stmt->bind_param("sssssisi", $video, $heading, $subtitle, $buttonText, $buttonLink, $sortOrder, $status, $id);
            } elseif ($id > 0) {
                $stmt = $conn->prepare("UPDATE homepage_video_banner SET heading=?, subtitle=?, button_text=?, button_link=?, sort_order=?, status=? WHERE id=?");
                $stmt->bind_param("ssssisi", $heading, $subtitle, $buttonText, $buttonLink, $sortOrder, $status, $id);
            } else {
                if (!$video) throw new RuntimeException("Video file is required.");
                $stmt = $conn->prepare("INSERT INTO homepage_video_banner (video_path, heading, subtitle, button_text, button_link, sort_order, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssis", $video, $heading, $subtitle, $buttonText, $buttonLink, $sortOrder, $status);
            }
            $stmt->execute();
            $message = "Home video saved.";
        }

        $messageType = "success";
    }
} catch (Throwable $e) {
    $message = $e->getMessage();
    $messageType = "error";
}

ensureRightHeroBanners($conn);

function editRow($conn, $table, $id) {
    if (!$id) return null;
    $stmt = $conn->prepare("SELECT * FROM {$table} WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

$editType = $_GET["edit_type"] ?? "";
$editId = (int) ($_GET["edit"] ?? 0);
$editHero = $editType === "hero" ? editRow($conn, "hero_slides", $editId) : null;
$editOffer = $editType === "offer" ? editRow($conn, "homepage_discount_offers", $editId) : null;
$editWhy = $editType === "why" ? editRow($conn, "homepage_why_points", $editId) : null;
$editRoom = $editType === "room" ? editRow($conn, "homepage_room_cards", $editId) : null;
$editFeature = $editType === "feature" ? editRow($conn, "homepage_feature_boxes", $editId) : null;
$editVideo = $editType === "video" ? editRow($conn, "homepage_video_banner", $editId) : null;
$videoBanners = $conn->query("SELECT * FROM homepage_video_banner ORDER BY sort_order ASC, id ASC");
$videoBanner = $editVideo ?: [
    "id" => 0,
    "video_path" => "",
    "heading" => "Zafiro Casa Luxury Living",
    "subtitle" => "Premium furniture crafted for modern homes",
    "button_text" => "Explore Collection",
    "button_link" => "product-list.php",
    "sort_order" => 0,
    "status" => "active"
];

$slides = $conn->query("SELECT * FROM hero_slides ORDER BY sort_order ASC, id DESC");
$offers = $conn->query("SELECT * FROM homepage_discount_offers ORDER BY sort_order ASC, id DESC");
$products = $conn->query("SELECT id, name FROM products WHERE LOWER(COALESCE(status, 'active'))='active' ORDER BY name ASC LIMIT 300");
$categories = $conn->query("SELECT slug, category_name FROM categories WHERE LOWER(COALESCE(status, 'active'))='active' ORDER BY category_name ASC");
$productOptions = [];
if ($products) while ($row = $products->fetch_assoc()) $productOptions[] = $row;
$categoryOptions = [];
if ($categories) while ($row = $categories->fetch_assoc()) $categoryOptions[] = $row;
$whyPoints = $conn->query("SELECT * FROM homepage_why_points ORDER BY sort_order ASC, id DESC");
$roomCards = $conn->query("SELECT * FROM homepage_room_cards ORDER BY sort_order ASC, id DESC");
$featureBoxes = $conn->query("SELECT * FROM homepage_feature_boxes ORDER BY sort_order ASC, id ASC LIMIT 2");

include("includes/admin_header.php");
include("includes/admin_sidebar.php");
?>
<main class="admin-main">
    <header class="admin-topbar admin-dark-topbar">
        <div><span>Zafiro Casa</span><h1>Home Page Editor</h1><p>Manage homepage sections in clean, separate cards.</p></div>
    </header>
    <?php if ($message): ?><div class="admin-popup <?php echo htmlspecialchars($messageType); ?>"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>

    <section class="homepage-editor-grid">
        <article class="admin-form-card home-editor-card">
            <h2>Hero Slider Section</h2>
            <p>Recommended size: 1400 x 700 px. Images auto-fit inside the banner.</p>
            <?php if ($editHero): ?><a class="admin-btn admin-btn-light home-editor-new" href="manage_hero_slider.php">+ Add New Hero Slider</a><?php endif; ?>
            <form method="POST" enctype="multipart/form-data" class="home-editor-form">
                <input type="hidden" name="action" value="hero">
                <input type="hidden" name="id" value="<?php echo (int) ($editHero["id"] ?? 0); ?>">
                <label>Upload Image<input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" <?php echo $editHero ? "" : "required"; ?>><small class="image-size-note">Recommended image size: 1400 x 700 px</small></label>
                <label>Title<input type="text" name="title" value="<?php echo htmlspecialchars($editHero["title"] ?? "Zafiro Casa Luxury Living"); ?>" required></label>
                <label>Subtitle<input type="text" name="subtitle" value="<?php echo htmlspecialchars($editHero["subtitle"] ?? ""); ?>"></label>
                <label>Button Text<input type="text" name="button_text" value="<?php echo htmlspecialchars($editHero["button_text"] ?? "Explore Collection"); ?>"></label>
                <label>Button Link<input type="text" name="button_link" value="<?php echo htmlspecialchars($editHero["button_link"] ?? "#categories"); ?>"></label>
                <label>Sort / Order<input type="number" name="sort_order" value="<?php echo htmlspecialchars($editHero["sort_order"] ?? "0"); ?>"></label>
                <label>Status<select name="status"><option value="active" <?php echo (($editHero["status"] ?? "active") === "active") ? "selected" : ""; ?>>Active</option><option value="inactive" <?php echo (($editHero["status"] ?? "") === "inactive") ? "selected" : ""; ?>>Inactive</option></select></label>
                <?php if (!empty($editHero["image_path"])): ?><img class="home-editor-preview" src="<?php echo htmlspecialchars($editHero["image_path"]); ?>" alt="Hero preview"><?php endif; ?>
                <button class="admin-btn" type="submit"><?php echo $editHero ? "Update Hero Slider" : "Add Hero Slider"; ?></button>
            </form>
            <table class="admin-table home-editor-table"><thead><tr><th>Image</th><th>Title</th><th>Order</th><th>Status</th><th>Actions</th></tr></thead><tbody>
            <?php while ($slide = $slides->fetch_assoc()): ?>
                <tr><td><img class="admin-hero-thumb" src="<?php echo htmlspecialchars($slide["image_path"]); ?>" alt=""></td><td><?php echo htmlspecialchars($slide["title"] ?? "Hero Slide"); ?></td><td><?php echo (int) $slide["sort_order"]; ?></td><td><?php echo htmlspecialchars($slide["status"]); ?></td><td><a href="?edit_type=hero&edit=<?php echo (int) $slide["id"]; ?>">Edit</a> <?php echo homeEditorDeleteForm("hero", $slide["id"]); ?></td></tr>
            <?php endwhile; ?>
            </tbody></table>
        </article>

        <article class="admin-form-card home-editor-card">
            <h2>Discount & Offers</h2>
            <p>Add multiple active offers. Expired or inactive offers are hidden on the homepage.</p>
            <?php if ($editOffer): ?><a class="admin-btn admin-btn-light home-editor-new" href="manage_hero_slider.php">+ Add New Offer</a><?php endif; ?>
            <form method="POST" enctype="multipart/form-data" class="home-editor-form">
                <input type="hidden" name="action" value="offer">
                <input type="hidden" name="id" value="<?php echo (int) ($editOffer["id"] ?? 0); ?>">
                <label>Offer Title<input type="text" name="offer_title" value="<?php echo htmlspecialchars($editOffer["offer_title"] ?? ""); ?>" required></label>
                <label>Discount Text / Percentage<input type="text" name="discount_text" value="<?php echo htmlspecialchars($editOffer["discount_text"] ?? ""); ?>" placeholder="Flat 20% Off"></label>
                <label>Link Type<select name="link_type"><option value="category" <?php echo (($editOffer["link_type"] ?? "category") === "category") ? "selected" : ""; ?>>Category</option><option value="product" <?php echo (($editOffer["link_type"] ?? "") === "product") ? "selected" : ""; ?>>Product</option></select></label>
                <label>Product<select name="product_id"><option value="0">Select product</option><?php foreach ($productOptions as $product): ?><option value="<?php echo (int) $product["id"]; ?>" <?php echo (int) ($editOffer["product_id"] ?? 0) === (int) $product["id"] ? "selected" : ""; ?>><?php echo htmlspecialchars($product["name"]); ?></option><?php endforeach; ?></select></label>
                <label>Category<select name="category_slug"><option value="">Select category</option><?php foreach ($categoryOptions as $category): ?><option value="<?php echo htmlspecialchars($category["slug"]); ?>" <?php echo (($editOffer["category_slug"] ?? "") === $category["slug"]) ? "selected" : ""; ?>><?php echo htmlspecialchars($category["category_name"]); ?></option><?php endforeach; ?></select></label>
                <label>Start Date<input type="date" name="start_date" value="<?php echo htmlspecialchars($editOffer["start_date"] ?? ""); ?>"></label>
                <label>End Date<input type="date" name="end_date" value="<?php echo htmlspecialchars($editOffer["end_date"] ?? ""); ?>"></label>
                <label>Offer Image / Banner<input type="file" name="image" accept=".jpg,.jpeg,.png,.webp"><small class="image-size-note">Recommended image size: 600 x 400 px</small></label>
                <label>Order<input type="number" name="sort_order" value="<?php echo htmlspecialchars($editOffer["sort_order"] ?? "0"); ?>"></label>
                <label>Status<select name="status"><option value="active" <?php echo (($editOffer["status"] ?? "active") === "active") ? "selected" : ""; ?>>Active</option><option value="inactive" <?php echo (($editOffer["status"] ?? "") === "inactive") ? "selected" : ""; ?>>Inactive</option></select></label>
                <?php if (!empty($editOffer["image_path"])): ?><img class="home-editor-preview" src="<?php echo htmlspecialchars($editOffer["image_path"]); ?>" alt="Offer preview"><?php endif; ?>
                <button class="admin-btn" type="submit"><?php echo $editOffer ? "Update Offer" : "Add Offer"; ?></button>
            </form>
            <table class="admin-table home-editor-table"><thead><tr><th>Image</th><th>Title</th><th>Linked To</th><th>Order</th><th>Status</th><th>Actions</th></tr></thead><tbody>
            <?php while ($offerRow = $offers->fetch_assoc()): ?>
                <tr><td><?php if ($offerRow["image_path"]): ?><img class="admin-hero-thumb" src="<?php echo htmlspecialchars($offerRow["image_path"]); ?>" alt=""><?php endif; ?></td><td><?php echo htmlspecialchars($offerRow["offer_title"]); ?><br><small><?php echo htmlspecialchars($offerRow["discount_text"]); ?></small></td><td><?php echo $offerRow["link_type"] === "product" ? "Product #" . (int) $offerRow["product_id"] : htmlspecialchars($offerRow["category_slug"]); ?></td><td><?php echo (int) $offerRow["sort_order"]; ?></td><td><?php echo htmlspecialchars($offerRow["status"]); ?></td><td><a href="?edit_type=offer&edit=<?php echo (int) $offerRow["id"]; ?>">Edit</a> <?php echo homeEditorDeleteForm("offer", $offerRow["id"]); ?></td></tr>
            <?php endwhile; ?>
            </tbody></table>
        </article>

        <article class="admin-form-card home-editor-card">
            <h2>Why Choose Zafiro Casa</h2>
            <form method="POST" class="home-editor-form">
                <input type="hidden" name="action" value="why">
                <input type="hidden" name="id" value="<?php echo (int) ($editWhy["id"] ?? 0); ?>">
                <label>Icon Class<input type="text" name="icon_class" value="<?php echo htmlspecialchars($editWhy["icon_class"] ?? "fa-solid fa-gem"); ?>"></label>
                <label>Title<input type="text" name="title" value="<?php echo htmlspecialchars($editWhy["title"] ?? ""); ?>" required></label>
                <label>Description<textarea name="description"><?php echo htmlspecialchars($editWhy["description"] ?? ""); ?></textarea></label>
                <label>Order<input type="number" name="sort_order" value="<?php echo htmlspecialchars($editWhy["sort_order"] ?? "0"); ?>"></label>
                <label>Status<select name="status"><option value="active" <?php echo (($editWhy["status"] ?? "active") === "active") ? "selected" : ""; ?>>Active</option><option value="inactive" <?php echo (($editWhy["status"] ?? "") === "inactive") ? "selected" : ""; ?>>Inactive</option></select></label>
                <button class="admin-btn" type="submit"><?php echo $editWhy ? "Update Point" : "Add Point"; ?></button>
            </form>
            <table class="admin-table home-editor-table"><thead><tr><th>Icon</th><th>Title</th><th>Order</th><th>Status</th><th>Actions</th></tr></thead><tbody>
            <?php while ($point = $whyPoints->fetch_assoc()): ?>
                <tr><td><i class="<?php echo htmlspecialchars($point["icon_class"]); ?>"></i></td><td><?php echo htmlspecialchars($point["title"]); ?></td><td><?php echo (int) $point["sort_order"]; ?></td><td><?php echo htmlspecialchars($point["status"]); ?></td><td><a href="?edit_type=why&edit=<?php echo (int) $point["id"]; ?>">Edit</a> <?php echo homeEditorDeleteForm("why", $point["id"]); ?></td></tr>
            <?php endwhile; ?>
            </tbody></table>
        </article>

        <article class="admin-form-card home-editor-card">
            <h2>Home Videos</h2>
            <p>Recommended video size: 1920 x 1080 px, MP4 format.</p>
            <?php if ($editVideo): ?><a class="admin-btn admin-btn-light home-editor-new" href="manage_hero_slider.php">+ Add New Video</a><?php endif; ?>
            <form method="POST" enctype="multipart/form-data" class="home-editor-form">
                <input type="hidden" name="action" value="video">
                <input type="hidden" name="id" value="<?php echo (int) ($videoBanner["id"] ?? 0); ?>">
                <label>Video File<input type="file" name="video" accept="video/mp4" <?php echo $editVideo ? "" : "required"; ?>><small class="image-size-note">Recommended video size: 1920 x 1080 px, MP4 format</small></label>
                <label>Heading<input type="text" name="heading" value="<?php echo htmlspecialchars($videoBanner["heading"] ?? "Zafiro Casa Luxury Living"); ?>" required></label>
                <label>Subtitle<input type="text" name="subtitle" value="<?php echo htmlspecialchars($videoBanner["subtitle"] ?? "Premium furniture crafted for modern homes"); ?>"></label>
                <label>Button Text<input type="text" name="button_text" value="<?php echo htmlspecialchars($videoBanner["button_text"] ?? "Explore Collection"); ?>"></label>
                <label>Button Link<input type="text" name="button_link" value="<?php echo htmlspecialchars($videoBanner["button_link"] ?? "product-list.php"); ?>"></label>
                <label>Sort / Order<input type="number" name="sort_order" value="<?php echo htmlspecialchars($videoBanner["sort_order"] ?? "0"); ?>"></label>
                <label>Status<select name="status"><option value="active" <?php echo (($videoBanner["status"] ?? "active") === "active") ? "selected" : ""; ?>>Active</option><option value="inactive" <?php echo (($videoBanner["status"] ?? "") === "inactive") ? "selected" : ""; ?>>Inactive</option></select></label>
                <?php if (!empty($videoBanner["video_path"])): ?><video class="home-editor-preview" src="<?php echo htmlspecialchars($videoBanner["video_path"]); ?>" muted controls></video><?php endif; ?>
                <button class="admin-btn" type="submit"><?php echo $editVideo ? "Update Video" : "Add Video"; ?></button>
            </form>
            <table class="admin-table home-editor-table"><thead><tr><th>Video</th><th>Heading</th><th>Order</th><th>Status</th><th>Actions</th></tr></thead><tbody>
            <?php if ($videoBanners): while ($videoRow = $videoBanners->fetch_assoc()): ?>
                <tr><td><?php if ($videoRow["video_path"]): ?><video class="admin-hero-thumb" src="<?php echo htmlspecialchars($videoRow["video_path"]); ?>" muted></video><?php endif; ?></td><td><?php echo htmlspecialchars($videoRow["heading"]); ?></td><td><?php echo (int) $videoRow["sort_order"]; ?></td><td><?php echo htmlspecialchars($videoRow["status"]); ?></td><td><a href="?edit_type=video&edit=<?php echo (int) $videoRow["id"]; ?>">Edit</a> <?php echo homeEditorDeleteForm("video", $videoRow["id"]); ?></td></tr>
            <?php endwhile; endif; ?>
            </tbody></table>
        </article>

        <article class="admin-form-card home-editor-card">
            <h2>Room Inspiration Gallery</h2>
            <form method="POST" enctype="multipart/form-data" class="home-editor-form">
                <input type="hidden" name="action" value="room">
                <input type="hidden" name="id" value="<?php echo (int) ($editRoom["id"] ?? 0); ?>">
                <label>Room Title<input type="text" name="title" value="<?php echo htmlspecialchars($editRoom["title"] ?? ""); ?>" required></label>
                <label>Image<input type="file" name="image" accept=".jpg,.jpeg,.png,.webp"><small class="image-size-note">Recommended image size: 800 x 600 px</small></label>
                <label>Linked Product / Category URL<input type="text" name="link_url" value="<?php echo htmlspecialchars($editRoom["link_url"] ?? ""); ?>" placeholder="product-list.php?category=sofa"></label>
                <label>Order<input type="number" name="sort_order" value="<?php echo htmlspecialchars($editRoom["sort_order"] ?? "0"); ?>"></label>
                <label>Status<select name="status"><option value="active" <?php echo (($editRoom["status"] ?? "active") === "active") ? "selected" : ""; ?>>Active</option><option value="inactive" <?php echo (($editRoom["status"] ?? "") === "inactive") ? "selected" : ""; ?>>Inactive</option></select></label>
                <?php if (!empty($editRoom["image_path"])): ?><img class="home-editor-preview" src="<?php echo htmlspecialchars($editRoom["image_path"]); ?>" alt="Room preview"><?php endif; ?>
                <button class="admin-btn" type="submit"><?php echo $editRoom ? "Update Room Card" : "Add Room Card"; ?></button>
            </form>
            <table class="admin-table home-editor-table"><thead><tr><th>Image</th><th>Title</th><th>Order</th><th>Status</th><th>Actions</th></tr></thead><tbody>
            <?php while ($room = $roomCards->fetch_assoc()): ?>
                <tr><td><?php if ($room["image_path"]): ?><img class="admin-hero-thumb" src="<?php echo htmlspecialchars($room["image_path"]); ?>" alt=""><?php endif; ?></td><td><?php echo htmlspecialchars($room["title"]); ?></td><td><?php echo (int) $room["sort_order"]; ?></td><td><?php echo htmlspecialchars($room["status"]); ?></td><td><a href="?edit_type=room&edit=<?php echo (int) $room["id"]; ?>">Edit</a> <?php echo homeEditorDeleteForm("room", $room["id"]); ?></td></tr>
            <?php endwhile; ?>
            </tbody></table>
        </article>

        <article class="admin-form-card home-editor-card" id="rightHeroBanners">
            <h2>Right Hero Banners</h2>
            <p>Edit the two small hero boxes shown beside the main slider.</p>
            <?php if ($editFeature): ?><a class="admin-btn admin-btn-light home-editor-new" href="manage_hero_slider.php#rightHeroBanners">Back to Right Banners</a><?php endif; ?>
            <form method="POST" enctype="multipart/form-data" class="home-editor-form">
                <input type="hidden" name="action" value="feature">
                <input type="hidden" name="id" value="<?php echo (int) ($editFeature["id"] ?? 0); ?>">
                <label>Title<input type="text" name="title" value="<?php echo htmlspecialchars($editFeature["title"] ?? ""); ?>" required></label>
                <label>Small Label Text<input type="text" name="subtitle" value="<?php echo htmlspecialchars($editFeature["subtitle"] ?? ""); ?>"></label>
                <label>Button Text<input type="text" name="button_text" value="<?php echo htmlspecialchars($editFeature["button_text"] ?? ""); ?>"></label>
                <label>Button Link<input type="text" name="button_link" value="<?php echo htmlspecialchars($editFeature["button_link"] ?? ""); ?>"></label>
                <label>Image / Background<input type="file" name="image" accept=".jpg,.jpeg,.png,.webp"><small class="image-size-note">Recommended image size: 700 x 330 px</small><small class="image-size-note">Feature Banner / Homepage Cards: 900 x 500 px</small></label>
                <label>Position / Order<input type="number" min="1" max="2" name="sort_order" value="<?php echo htmlspecialchars($editFeature["sort_order"] ?? "1"); ?>"></label>
                <label>Status<select name="status"><option value="active" <?php echo (($editFeature["status"] ?? "active") === "active") ? "selected" : ""; ?>>Active</option><option value="inactive" <?php echo (($editFeature["status"] ?? "") === "inactive") ? "selected" : ""; ?>>Inactive</option></select></label>
                <?php if (!empty($editFeature["image_path"])): ?><img class="home-editor-preview" src="<?php echo htmlspecialchars($editFeature["image_path"]); ?>" alt="Right banner preview"><?php endif; ?>
                <button class="admin-btn" type="submit"><?php echo $editFeature ? "Update Right Banner" : "Add Right Banner"; ?></button>
            </form>
            <table class="admin-table home-editor-table"><thead><tr><th>Position</th><th>Image</th><th>Title</th><th>Order</th><th>Status</th><th>Actions</th></tr></thead><tbody>
            <?php while ($box = $featureBoxes->fetch_assoc()): ?>
                <tr><td><?php echo ((int) $box["sort_order"] === 1) ? "Top Right Banner" : "Bottom Right Banner"; ?></td><td><?php if ($box["image_path"]): ?><img class="admin-hero-thumb" src="<?php echo htmlspecialchars($box["image_path"]); ?>" alt=""><?php endif; ?></td><td><?php echo htmlspecialchars($box["title"]); ?></td><td><?php echo (int) $box["sort_order"]; ?></td><td><?php echo htmlspecialchars($box["status"]); ?></td><td><a href="?edit_type=feature&edit=<?php echo (int) $box["id"]; ?>#rightHeroBanners">Edit</a> <?php echo homeEditorDeleteForm("feature", $box["id"], "#rightHeroBanners"); ?></td></tr>
            <?php endwhile; ?>
            </tbody></table>
        </article>
    </section>
<?php include("includes/admin_footer.php"); ?>
