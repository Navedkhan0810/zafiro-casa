<?php
include("../backend/config/db.php");
include("../backend/includes/header.php");

$visibleCards = [];
foreach ($cards as $card) {
    $slug = $card["slug"] ?? "";
    $stmt = $conn->prepare("SELECT category_image, status FROM categories WHERE slug=? LIMIT 1");
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $categoryRow = $stmt->get_result()->fetch_assoc();
    if ($categoryRow && strtolower($categoryRow["status"]) !== "active") continue;
    if (!empty($categoryRow["category_image"])) $card["image"] = $categoryRow["category_image"];
    $visibleCards[] = $card;
}
?>

<main class="mega-page">
    <section class="mega-hero">
        <span>Zafiro Casa Luxury Living</span>
        <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
        <p><?php echo htmlspecialchars($pageIntro); ?></p>
    </section>

    <section class="mega-grid">
        <?php foreach ($visibleCards as $card): ?>
            <a class="mega-card" href="product-list.php?category=<?php echo urlencode($card['slug']); ?>">
                <img src="<?php echo htmlspecialchars($card['image']); ?>" alt="<?php echo htmlspecialchars($card['title']); ?>" loading="lazy" decoding="async" width="700" height="520">
                <div class="card-overlay">
                    <h3><?php echo htmlspecialchars($card['title']); ?></h3>
                </div>
            </a>
        <?php endforeach; ?>
    </section>
</main>

<?php include("../backend/includes/footer.php"); ?>
