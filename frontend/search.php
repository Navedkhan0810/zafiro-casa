<?php
include("../backend/config/db.php");
include("../backend/includes/header.php");
include_once("../backend/includes/product_images.php");

$rawQuery = $_GET['q'] ?? ($_GET['query'] ?? '');
$q = preg_replace('/\s+/', ' ', strtolower(trim($rawQuery)));
$displayQuery = trim(preg_replace('/\s+/', ' ', $rawQuery));
$result = null;
$message = '';
$stmt = null;

if ($q === '') {
    $message = 'Please enter a search term.';
} else {
    $nameLike = '%' . $q . '%';
    $prefixLike = $q . '%';
    $statusSql = "LOWER(COALESCE(p.status, 'active')) = 'active'";

    if (in_array($q, ['bed', 'beds'], true)) {
        $bedTerms = ['bed', 'beds', 'bed with mattress', 'bed with matresses'];
        $bedNameRegex = '(^|[^a-z0-9])beds?([^a-z0-9]|$)';
        $placeholders = implode(',', array_fill(0, count($bedTerms), '?'));
        $sql = "SELECT p.*, c.category_name AS resolved_category_name, sc.subcategory_name AS resolved_subcategory_name
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                LEFT JOIN subcategories sc ON sc.id = p.subcategory_id
                WHERE $statusSql
                  AND LOWER(p.name) NOT LIKE '%sofa%'
                  AND (
                    LOWER(p.name) REGEXP ?
                    OR LOWER(COALESCE(sc.subcategory_name, '')) IN ($placeholders)
                    OR LOWER(COALESCE(sc.slug, '')) IN ($placeholders)
                    OR LOWER(COALESCE(c.category_name, '')) IN ($placeholders)
                    OR LOWER(COALESCE(c.slug, '')) IN ($placeholders)
                    OR LOWER(COALESCE(p.category, '')) IN ($placeholders)
                  )
                ORDER BY
                  CASE
                    WHEN LOWER(p.name) = ? THEN 1
                    WHEN LOWER(p.name) REGEXP ? THEN 2
                    WHEN LOWER(COALESCE(sc.subcategory_name, '')) IN ($placeholders) THEN 3
                    WHEN LOWER(COALESCE(c.category_name, '')) IN ($placeholders) OR LOWER(COALESCE(p.category, '')) IN ($placeholders) THEN 4
                    ELSE 5
                  END,
                  p.name ASC
                LIMIT 48";

        $params = array_merge(
            [$bedNameRegex],
            $bedTerms,
            $bedTerms,
            $bedTerms,
            $bedTerms,
            $bedTerms,
            [$q, $bedNameRegex],
            $bedTerms,
            $bedTerms,
            $bedTerms
        );
        $stmt = $conn->prepare($sql);
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    } else {
        $sql = "SELECT p.*, c.category_name AS resolved_category_name, sc.subcategory_name AS resolved_subcategory_name
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                LEFT JOIN subcategories sc ON sc.id = p.subcategory_id
                WHERE $statusSql
                  AND (
                    LOWER(p.name) LIKE ?
                    OR LOWER(COALESCE(sc.subcategory_name, '')) LIKE ?
                    OR LOWER(COALESCE(sc.slug, '')) LIKE ?
                    OR LOWER(COALESCE(c.category_name, '')) LIKE ?
                    OR LOWER(COALESCE(c.slug, '')) LIKE ?
                    OR LOWER(COALESCE(p.category, '')) LIKE ?
                  )
                ORDER BY
                  CASE
                    WHEN LOWER(p.name) = ? THEN 1
                    WHEN LOWER(p.name) LIKE ? THEN 2
                    WHEN LOWER(COALESCE(sc.subcategory_name, '')) LIKE ? OR LOWER(COALESCE(sc.slug, '')) LIKE ? THEN 3
                    WHEN LOWER(COALESCE(c.category_name, '')) LIKE ? OR LOWER(COALESCE(c.slug, '')) LIKE ? OR LOWER(COALESCE(p.category, '')) LIKE ? THEN 4
                    ELSE 5
                  END,
                  p.name ASC
                LIMIT 48";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            'sssssssssssss',
            $nameLike,
            $nameLike,
            $prefixLike,
            $prefixLike,
            $prefixLike,
            $prefixLike,
            $q,
            $nameLike,
            $nameLike,
            $prefixLike,
            $prefixLike,
            $prefixLike,
            $prefixLike
        );
    }

    $stmt->execute();
    $result = $stmt->get_result();
}
?>

<link rel="stylesheet" href="../assets/css/category.css">

<section class="products search-results-section">
    <h2>Search Results</h2>
    <?php if ($displayQuery !== ''): ?>
        <p class="search-results-query">Showing results for "<?php echo htmlspecialchars($displayQuery); ?>"</p>
    <?php endif; ?>

    <?php if ($message): ?>
        <p class="search-empty-state"><?php echo htmlspecialchars($message); ?></p>
    <?php elseif ($result && $result->num_rows > 0): ?>
        <div class="grid search-results-grid">
            <?php while ($row = $result->fetch_assoc()): ?>
                <?php $cardImage = getProductCardImage($row); ?>
                <div class="card product-card search-result-card" data-product-id="<?php echo htmlspecialchars($row['id']); ?>" data-product-name="<?php echo htmlspecialchars($row['name']); ?>" data-product-price="<?php echo htmlspecialchars($row['price']); ?>" data-product-image="<?php echo htmlspecialchars($cardImage); ?>" data-product-url="product.php?id=<?php echo htmlspecialchars($row['id']); ?>">
                    <a class="product-card-link" href="product.php?id=<?php echo htmlspecialchars($row['id']); ?>">
                        <img src="<?php echo htmlspecialchars($cardImage); ?>" alt="<?php echo htmlspecialchars($row['name']); ?>" loading="lazy" decoding="async" width="600" height="420">
                        <p><?php echo htmlspecialchars($row['name']); ?></p>
                        <span class="price">&#8377;<?php echo htmlspecialchars($row['price']); ?></span>
                        <p class="short-desc"><?php echo htmlspecialchars($row['resolved_subcategory_name'] ?? $row['resolved_category_name'] ?? $row['category'] ?? ''); ?></p>
                    </a>
                    <button type="button" class="product-btn add-cart-btn">Add to Cart</button>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p class="search-empty-state">No products found for your search.</p>
    <?php endif; ?>
</section>

<?php include("../backend/includes/footer.php"); ?>

<?php
if ($stmt) $stmt->close();
$conn->close();
?>