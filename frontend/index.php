<?php
include("../backend/includes/header.php");
include_once("../backend/includes/product_images.php");
include_once("../backend/includes/category_images.php");

if (isset($conn) && $conn instanceof mysqli) {
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
}

$defaultHeroSlides = [[
    "image_path" => "https://images.unsplash.com/photo-1600210491369-e753d80a41f3?auto=format&fit=crop&w=1600&q=85",
    "title" => "Zafiro Casa Luxury Living",
    "subtitle" => "Elegant sofas, beds, dining and decor crafted for modern homes.",
    "button_text" => "Explore Collection",
    "button_link" => "#categories"
], [
    "image_path" => "https://images.unsplash.com/photo-1600607687920-4e2a09cf159d?auto=format&fit=crop&w=1600&q=85",
    "title" => "Modern Luxury Interiors",
    "subtitle" => "Premium living room collections with refined comfort.",
    "button_text" => "Shop Sofas",
    "button_link" => "sofas.php"
], [
    "image_path" => "https://images.unsplash.com/photo-1616486338812-3dadae4b4ace?auto=format&fit=crop&w=1600&q=85",
    "title" => "Elegant Bedroom Essentials",
    "subtitle" => "Warm, timeless bedroom furniture for luxury living.",
    "button_text" => "Explore Bedroom",
    "button_link" => "bedroom.php"
]];
$defaultFeatureBoxes = [
    ["title" => "Living Room Comfort", "subtitle" => "New Arrivals", "button_text" => "", "button_link" => "living.php", "image_path" => "https://images.unsplash.com/photo-1600210492486-724fe5c67fb0?auto=format&fit=crop&w=1200&q=95"],
    ["title" => "Bedroom Essentials", "subtitle" => "Limited Offer", "button_text" => "", "button_link" => "bedroom.php", "image_path" => "https://images.unsplash.com/photo-1616594039964-ae9021a400a0?auto=format&fit=crop&w=1100&q=90"]
];
$heroSlides = $defaultHeroSlides;
$discountOffers = [];
$defaultDiscountOffers = [
    ["offer_title" => "Luxury Living Room Sale", "discount_text" => "Up to 60% Off", "link_type" => "category", "product_id" => 0, "category_slug" => "sofa", "image_path" => "https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=900&q=90"],
    ["offer_title" => "Bedroom Elegance Deals", "discount_text" => "Flat 45% Off", "link_type" => "category", "product_id" => 0, "category_slug" => "beds", "image_path" => "https://images.unsplash.com/photo-1615874694520-474822394e73?auto=format&fit=crop&w=900&q=90"],
    ["offer_title" => "Dining Luxury Picks", "discount_text" => "Save 35%", "link_type" => "category", "product_id" => 0, "category_slug" => "dining-sets", "image_path" => "https://images.unsplash.com/photo-1617806118233-18e1de247200?auto=format&fit=crop&w=900&q=90"],
    ["offer_title" => "Premium Decor Offers", "discount_text" => "New Season Deals", "link_type" => "category", "product_id" => 0, "category_slug" => "decor-furnishing", "image_path" => "https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?auto=format&fit=crop&w=900&q=90"]
];
$featureBoxes = [];
$roomCards = [];
$whyPoints = [];
$homeVideos = [];
function defaultHomeVideos() {
    $videos = [];
    foreach (glob(__DIR__ . "/../Categories furniture/videos/*.mp4") ?: [] as $index => $path) {
        $videos[] = [
            "video_path" => "../Categories%20furniture/videos/" . rawurlencode(basename($path)),
            "heading" => "Zafiro Casa Luxury Living",
            "subtitle" => "Premium furniture crafted for modern homes",
            "button_text" => "Explore Collection",
            "button_link" => "product-list.php",
            "sort_order" => $index + 1
        ];
    }
    return $videos;
}
if (isset($conn) && $conn instanceof mysqli) {
    $stmt = $conn->prepare("SELECT image_path, title, subtitle, button_text, button_link FROM hero_slides WHERE status = 'active' ORDER BY sort_order ASC, id ASC");
    if ($stmt && $stmt->execute()) {
        $result = $stmt->get_result();
        $dbSlides = [];
        while ($row = $result->fetch_assoc()) {
            $dbSlides[] = $row;
        }
        if ($dbSlides) $heroSlides = $dbSlides;
    }
    $offerResult = $conn->query("SELECT * FROM homepage_discount_offers WHERE status='active' AND (start_date IS NULL OR start_date <= CURDATE()) AND (end_date IS NULL OR end_date >= CURDATE()) ORDER BY sort_order ASC, id DESC LIMIT 6");
    if ($offerResult) while ($row = $offerResult->fetch_assoc()) $discountOffers[] = $row;
    $featureResult = $conn->query("SELECT * FROM homepage_feature_boxes WHERE status='active' ORDER BY sort_order ASC, id ASC LIMIT 2");
    if ($featureResult) while ($row = $featureResult->fetch_assoc()) $featureBoxes[] = $row;
    $roomResult = $conn->query("SELECT * FROM homepage_room_cards WHERE status='active' ORDER BY sort_order ASC, id ASC LIMIT 4");
    if ($roomResult) while ($row = $roomResult->fetch_assoc()) $roomCards[] = $row;
    $whyResult = $conn->query("SELECT * FROM homepage_why_points WHERE status='active' ORDER BY sort_order ASC, id ASC LIMIT 4");
    if ($whyResult) while ($row = $whyResult->fetch_assoc()) $whyPoints[] = $row;
    $videoResult = $conn->query("SELECT * FROM homepage_video_banner WHERE status='active' ORDER BY sort_order ASC, id ASC");
    if ($videoResult) while ($row = $videoResult->fetch_assoc()) $homeVideos[] = $row;
}
if (!$homeVideos) $homeVideos = defaultHomeVideos();

if (count($featureBoxes) < 2) {
    $existingFeatureTitles = array_map(fn($box) => strtolower($box["title"] ?? ""), $featureBoxes);
    foreach ($defaultFeatureBoxes as $defaultBox) {
        if (in_array(strtolower($defaultBox["title"]), $existingFeatureTitles, true)) continue;
        $featureBoxes[] = $defaultBox;
        if (count($featureBoxes) >= 2) break;
    }
}
if (!$discountOffers) $discountOffers = $defaultDiscountOffers;
if (!$roomCards) {
    $roomCards = [
        ["title" => "Sofa", "image_path" => zafiroCategoryImageFallback("sofa"), "link_url" => zafiroCategoryUrl("sofa")],
        ["title" => "Beds", "image_path" => zafiroCategoryImageFallback("beds"), "link_url" => zafiroCategoryUrl("beds")],
        ["title" => "Study Table", "image_path" => zafiroCategoryImageFallback("study-table"), "link_url" => zafiroCategoryUrl("study-table")],
        ["title" => "Shoe Rack", "image_path" => zafiroCategoryImageFallback("shoe-rack"), "link_url" => zafiroCategoryUrl("shoe-rack")]
    ];
}
if (!$whyPoints) {
    $whyPoints = [
        ["icon_class" => "fa-solid fa-gem", "title" => "Premium Materials", "description" => "Selected finishes and textures for long-lasting luxury."],
        ["icon_class" => "fa-solid fa-crown", "title" => "Luxury Design", "description" => "Refined silhouettes crafted for modern Indian homes."],
        ["icon_class" => "fa-solid fa-truck-fast", "title" => "Secure Delivery", "description" => "Careful handling from dispatch to doorstep."],
        ["icon_class" => "fa-solid fa-pen-ruler", "title" => "Modern Craftsmanship", "description" => "Clean detailing with practical comfort and utility."]
    ];
}

$featuredProducts = null;
$newProducts = null;
$homeDecorProducts = null;
if (isset($conn) && $conn instanceof mysqli) {
    ensureProductImageColumns($conn);
    $featuredProducts = $conn->query("SELECT * FROM products WHERE LOWER(COALESCE(status, 'active')) = 'active' ORDER BY featured DESC, trending DESC, id DESC LIMIT 8");
    $newProducts = $conn->query("SELECT * FROM products WHERE LOWER(COALESCE(status, 'active')) = 'active' ORDER BY id DESC LIMIT 8");
    $homeDecorProducts = $conn->query("SELECT * FROM products WHERE LOWER(COALESCE(status, 'active')) = 'active' AND LOWER(category) IN ('clocks','cover','lamps','lights','mirrors','table decor','wall decor','decor & furnishing','decor furnishing') ORDER BY id DESC LIMIT 8");
}
?>

<main>
    <section class="hero-grid">
        <?php $firstHero = $heroSlides[0]; ?>
        <div class="hero-panel hero-large" id="mainHeroSlider" data-hero-slides="<?php echo htmlspecialchars(json_encode($heroSlides)); ?>">
            <img src="<?php echo htmlspecialchars($firstHero["image_path"]); ?>" alt="Premium furniture collection" id="leftHeroSlideImage">
            <div class="hero-content">
                <span>Premium Furniture Sale</span>
                <h1 id="heroSlideTitle"><?php echo htmlspecialchars($firstHero["title"]); ?></h1>
                <p id="heroSlideSubtitle"><?php echo htmlspecialchars($firstHero["subtitle"]); ?></p>
                <a href="<?php echo htmlspecialchars($firstHero["button_link"]); ?>" class="hero-btn" id="heroSlideButton"><?php echo htmlspecialchars($firstHero["button_text"]); ?></a>
            </div>
        </div>

        <div class="hero-side">
            <?php foreach ($featureBoxes as $box): ?>
            <div class="hero-panel promo-panel">
                <img src="<?php echo htmlspecialchars($box["image_path"]); ?>" alt="<?php echo htmlspecialchars($box["title"]); ?>">
                <div class="hero-content">
                    <span><?php echo htmlspecialchars($box["subtitle"] ?? ""); ?></span>
                    <h2><?php echo htmlspecialchars($box["title"]); ?></h2>
                    <?php if (!empty($box["button_text"]) && !empty($box["button_link"])): ?><a class="hero-btn" href="<?php echo htmlspecialchars($box["button_link"]); ?>"><?php echo htmlspecialchars($box["button_text"]); ?></a><?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="section-block room-inspiration-section premium-fade">
        <div class="section-title">
            <span>Curated Interiors</span>
            <h2>Room Inspiration Gallery</h2>
        </div>
        <div class="room-inspiration-grid">
            <?php foreach ($roomCards as $card): ?>
            <?php
                $roomLink = trim($card["link_url"] ?? "");
                if ($roomLink === "") {
                    $roomSlug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $card["title"] ?? ""), "-"));
                    $roomSlugMap = ["sofas" => "sofa", "study" => "study-office"];
                    $roomLink = "product-list.php?category=" . ($roomSlugMap[$roomSlug] ?? $roomSlug);
                }
                $roomImage = !empty($card["image_path"]) ? $card["image_path"] : zafiroCategoryImageFallback($card["title"] ?? "");
            ?>
            <a class="room-inspiration-card premium-parallax-card" href="<?php echo htmlspecialchars($roomLink); ?>">
                <img src="<?php echo htmlspecialchars($roomImage); ?>" alt="<?php echo htmlspecialchars($card["title"]); ?>" loading="lazy" decoding="async" width="800" height="600">
                <div>
                    <h3><?php echo htmlspecialchars($card["title"]); ?></h3>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>

    <?php if ($discountOffers): ?>
    <section class="section-block discount-offers-section premium-fade">
        <div class="discount-offers-layout">
            <article class="discount-offers-lead">
                <span>Limited Time Luxury Sale</span>
                <h2>Big Discounts That Transform Your Space</h2>
                <p>Curated furniture offers for refined homes, modern comfort, and timeless interiors.</p>
                <strong>Up to 60% Off</strong>
                <a class="hero-btn" href="#categories">Explore Offers</a>
            </article>
            <div class="discount-offers-grid">
                <?php foreach ($discountOffers as $offer): ?>
                    <?php
                    $offerLink = $offer["link_type"] === "product" && !empty($offer["product_id"])
                        ? "product.php?id=" . (int) $offer["product_id"]
                        : "product-list.php?category=" . urlencode($offer["category_slug"]);
                    ?>
                    <a class="discount-offer-card" href="<?php echo htmlspecialchars($offerLink); ?>">
                        <?php if (!empty($offer["image_path"])): ?><img src="<?php echo htmlspecialchars($offer["image_path"]); ?>" alt="<?php echo htmlspecialchars($offer["offer_title"]); ?>" loading="lazy" decoding="async" width="600" height="400"><?php endif; ?>
                        <div>
                            <span><?php echo htmlspecialchars($offer["discount_text"]); ?></span>
                            <h3><?php echo htmlspecialchars($offer["offer_title"]); ?></h3>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section class="section-block why-zafiro-section premium-fade">
        <div class="section-title">
            <span>Zafiro Standard</span>
            <h2>Why Choose Zafiro Casa</h2>
        </div>
        <div class="why-zafiro-grid">
            <?php foreach ($whyPoints as $point): ?>
                <article><i class="<?php echo htmlspecialchars($point["icon_class"]); ?>"></i><h3><?php echo htmlspecialchars($point["title"]); ?></h3><p><?php echo htmlspecialchars($point["description"]); ?></p></article>
            <?php endforeach; ?>
        </div>
    </section>

    <?php
        $styleGalleryCards = [
            ["title" => "Mirrors", "slug" => "mirrors"],
            ["title" => "Bathroom Accessories", "slug" => "bathroom-accessories"],
            ["title" => "Bedroom", "slug" => "bedroom"],
            ["title" => "Decor & Furnishing", "slug" => "decor-furnishing"],
            ["title" => "Study", "slug" => "study-office"],
            ["title" => "Modular Kitchen", "slug" => "modular-kitchen"],
            ["title" => "Dining", "slug" => "dining"],
            ["title" => "Living", "slug" => "living"],
        ];
    ?>
    <section class="section-block instagram-style-section premium-fade">
        <div class="section-title">
            <span>Luxury Details</span>
            <h2>Style Gallery</h2>
        </div>
        <div class="instagram-style-grid">
            <?php foreach ($styleGalleryCards as $galleryCard): ?>
                <a href="<?php echo htmlspecialchars(zafiroCategoryUrl($galleryCard["slug"])); ?>"><img src="<?php echo htmlspecialchars(zafiroCategoryImageFallback($galleryCard["slug"])); ?>" alt="<?php echo htmlspecialchars($galleryCard["title"]); ?>" loading="lazy" decoding="async" width="420" height="420"><span><strong><?php echo htmlspecialchars($galleryCard["title"]); ?></strong></span></a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="section-block categories" id="categories">
        <div class="section-title">
            <span>Curated For You</span>
            <h2>Featured Categories</h2>
        </div>

        <div class="grid">
            <a href="product-list.php?category=sofa" class="category-link">
                <div class="card category-card">
                    <img src="<?php echo htmlspecialchars(zafiroCategoryImageFallback("sofa")); ?>" alt="Sofas" loading="lazy" decoding="async" width="600" height="400">
                    <div class="card-overlay"><h3>Sofa</h3></div>
                </div>
            </a>

            <a href="product-list.php?category=beds" class="category-link">
                <div class="card category-card">
                    <img src="<?php echo htmlspecialchars(zafiroCategoryImageFallback("beds")); ?>" alt="Beds" loading="lazy" decoding="async" width="600" height="400">
                    <div class="card-overlay"><h3>Beds</h3></div>
                </div>
            </a>

            <a href="product-list.php?category=table" class="category-link">
                <div class="card category-card">
                    <img src="<?php echo htmlspecialchars(zafiroCategoryImageFallback("table")); ?>" alt="Tables" loading="lazy" decoding="async" width="600" height="400">
                    <div class="card-overlay"><h3>Table</h3></div>
                </div>
            </a>

            <a href="product-list.php?category=chair" class="category-link">
                <div class="card category-card">
                    <img src="<?php echo htmlspecialchars(zafiroCategoryImageFallback("chair")); ?>" alt="Chairs" loading="lazy" decoding="async" width="600" height="400">
                    <div class="card-overlay"><h3>Chair</h3></div>
                </div>
            </a>

            <a href="product-list.php?category=table" class="category-link">
                <div class="card category-card">
                    <img src="<?php echo htmlspecialchars(zafiroCategoryImageFallback("table")); ?>" alt="Luxury Tables" loading="lazy" decoding="async" width="600" height="400">
                    <div class="card-overlay"><h3>Luxury Tables</h3></div>
                </div>
            </a>

            <a href="product-list.php?category=chair" class="category-link">
                <div class="card category-card">
                    <img src="<?php echo htmlspecialchars(zafiroCategoryImageFallback("chair")); ?>" alt="Premium Chairs" loading="lazy" decoding="async" width="600" height="400">
                    <div class="card-overlay"><h3>Premium Chairs</h3></div>
                </div>
            </a>
        </div>
    </section>

    <section class="section-block products">
        <div class="section-title">
            <span>Customer Favorites</span>
            <h2>Best Selling Products</h2>
        </div>

        <div class="grid">
            <?php if ($featuredProducts && $featuredProducts->num_rows > 0): ?>
                <?php while ($row = $featuredProducts->fetch_assoc()): ?>
                    <?php $cardImage = getProductCardImage($row); ?>
                    <div class="card product-card" data-product-id="<?php echo (int) $row['id']; ?>" data-product-name="<?php echo htmlspecialchars($row['name']); ?>" data-product-price="<?php echo htmlspecialchars($row['price']); ?>" data-product-image="<?php echo htmlspecialchars($cardImage); ?>" data-product-url="product.php?id=<?php echo (int) $row['id']; ?>">
                        <a class="product-card-link" href="product.php?id=<?php echo (int) $row['id']; ?>">
                            <img src="<?php echo htmlspecialchars($cardImage); ?>" alt="<?php echo htmlspecialchars($row['name']); ?>" loading="lazy" decoding="async" width="600" height="420">
                            <p><?php echo htmlspecialchars($row['name']); ?></p>
                            <span class="price">&#8377;<?php echo htmlspecialchars($row['price']); ?></span>
                            <span class="product-card-cta">View Details</span>
                        </a>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </section>

    <?php if (!empty($homeVideos)): ?>
    <section class="section-block home-video-section">
        <div class="home-video-head">
            <span>Stories Behind the Style</span>
            <h2>Zafiro Casa Luxury Videos</h2>
        </div>
        <div class="home-video-grid">
            <?php foreach ($homeVideos as $videoRow): ?>
            <article class="home-video-card">
                <video autoplay muted loop playsinline preload="metadata">
                    <source src="<?php echo htmlspecialchars($videoRow["video_path"]); ?>" type="video/mp4">
                </video>
                <span class="home-video-label">Zafiro Casa</span>
            </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <section class="section-block products">
        <div class="section-title">
            <span>Fresh Designs</span>
            <h2>New Arrivals</h2>
        </div>

        <div class="grid">
            <?php if ($newProducts && $newProducts->num_rows > 0): ?>
                <?php while ($row = $newProducts->fetch_assoc()): ?>
                    <?php $cardImage = getProductCardImage($row); ?>
                    <div class="card product-card" data-product-id="<?php echo (int) $row['id']; ?>" data-product-name="<?php echo htmlspecialchars($row['name']); ?>" data-product-price="<?php echo htmlspecialchars($row['price']); ?>" data-product-image="<?php echo htmlspecialchars($cardImage); ?>" data-product-url="product.php?id=<?php echo (int) $row['id']; ?>">
                        <a class="product-card-link" href="product.php?id=<?php echo (int) $row['id']; ?>">
                            <img src="<?php echo htmlspecialchars($cardImage); ?>" alt="<?php echo htmlspecialchars($row['name']); ?>" loading="lazy" decoding="async" width="600" height="420">
                            <p><?php echo htmlspecialchars($row['name']); ?></p>
                            <span class="price">&#8377;<?php echo htmlspecialchars($row['price']); ?></span>
                            <span class="product-card-cta">View Details</span>
                        </a>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($homeDecorProducts && $homeDecorProducts->num_rows > 0): ?>
    <section class="section-block home-decor-section">
        <div class="home-decor-head">
            <div>
                <span>Luxury Details</span>
                <h2>Home Decor</h2>
                <p>Discover every detail that matters</p>
            </div>
            <a class="home-decor-view" href="decor-furnishing.php">View All</a>
        </div>

        <div class="home-decor-grid">
            <?php while ($row = $homeDecorProducts->fetch_assoc()): ?>
                <?php
                $cardImage = getProductCardImage($row);
                $displayPrice = !empty($row["discount_price"]) && (float) $row["discount_price"] > 0 ? $row["discount_price"] : $row["price"];
                ?>
                <a class="home-decor-card" href="product.php?id=<?php echo (int) $row["id"]; ?>">
                    <img src="<?php echo htmlspecialchars($cardImage); ?>" alt="<?php echo htmlspecialchars($row["name"]); ?>" loading="lazy" decoding="async" width="600" height="420">
                    <div>
                        <h3><?php echo htmlspecialchars($row["name"]); ?></h3>
                        <span>Starting from &#8377;<?php echo htmlspecialchars($displayPrice); ?></span>
                    </div>
                </a>
            <?php endwhile; ?>
        </div>
    </section>
    <?php endif; ?>

    <section class="contact" id="contact">
        <div class="contact-container">
            <div class="contact-info">
                <h2>Zafiro Casa</h2>
                <p class="contact-tagline">Luxury Living Starts Here</p>
                <div class="contact-detail-list">
                    <p><i class="fa-solid fa-location-dot"></i><span>Ujjain, India</span></p>
                    <p><i class="fa-solid fa-phone"></i><span>+91 91716 17974</span></p>
                    <p><i class="fa-solid fa-envelope"></i><span>zafirocasaadmin@gmail.com</span></p>
                </div>
                <div class="contact-socials">
                    <h3>Follow Us</h3>
                    <a href="#"><i class="fa-brands fa-instagram"></i><span>@zafirocasa.living</span></a>
                    <a href="#"><i class="fa-brands fa-facebook-f"></i><span>Zafiro Casa Luxury</span></a>
                    <a href="#"><i class="fa-brands fa-pinterest-p"></i><span>@zafirointeriors</span></a>
                    <a href="#"><i class="fa-brands fa-whatsapp"></i><span>+91 98765 43210</span></a>
                </div>
            </div>

            <div class="contact-form">
                <h2>Get In Touch</h2>
                <form action="../backend/contact/save.php" method="POST">
                    <input type="hidden" name="name" id="contactFullName">
                    <div class="name-row">
                        <label class="contact-field"><i class="fa-regular fa-user"></i><input type="text" name="first_name" placeholder="First Name" required></label>
                        <label class="contact-field"><input type="text" name="last_name" placeholder="Last Name" required></label>
                    </div>
                    <label class="contact-field"><i class="fa-regular fa-envelope"></i><input type="email" name="email" placeholder="Your Email" required></label>
                    <label class="contact-field textarea-field"><i class="fa-regular fa-message"></i><textarea name="message" placeholder="Your Message"></textarea></label>
                    <button type="submit">Send Message</button>
                </form>
            </div>
        </div>
    </section>
</main>

<?php include("../backend/includes/footer.php"); ?>
