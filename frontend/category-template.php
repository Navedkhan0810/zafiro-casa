<?php include("../backend/includes/header.php"); ?>
<?php include_once("../backend/includes/product_images.php"); ?>
<link rel="stylesheet" href="../assets/css/category.css">

<section class="page-banner <?php echo strtolower(trim($pageTitle)) === 'luxury tables' ? 'luxury-tables-banner' : ''; ?>">
    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
    <p><?php echo htmlspecialchars($pageSubtitle); ?></p>
</section>

<section class="products">
    <h2><?php echo htmlspecialchars($heading); ?></h2>

    <div class="grid">
        <?php
        $sql = "SELECT * FROM products WHERE category IN ($categories) AND LOWER(COALESCE(status, 'active')) = 'active' LIMIT 12";
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $cardImage = getProductCardImage($row);
        ?>
            <div class="card product-card" data-product-id="<?php echo htmlspecialchars($row['id']); ?>" data-product-name="<?php echo htmlspecialchars($row['name']); ?>" data-product-price="<?php echo htmlspecialchars($row['price']); ?>" data-product-image="<?php echo htmlspecialchars($cardImage); ?>" data-product-url="product.php?id=<?php echo htmlspecialchars($row['id']); ?>">
                <a class="product-card-link" href="product.php?id=<?php echo htmlspecialchars($row['id']); ?>">
                    <img src="<?php echo htmlspecialchars($cardImage); ?>" alt="<?php echo htmlspecialchars($row['name']); ?>" loading="lazy" decoding="async" width="600" height="420">
                    <p><?php echo htmlspecialchars($row['name']); ?></p>
                    <span class="price">&#8377;<?php echo htmlspecialchars($row['price']); ?></span>
                    <p class="short-desc"><?php echo htmlspecialchars(substr($row['description'], 0, 80)); ?></p>
                </a>
                <button type="button" class="product-btn add-cart-btn">Add to Cart</button>
            </div>
        <?php
            }
        } else {
            echo "<p>No products available in this category.</p>";
        }
        ?>
    </div>
</section>

<?php include("../backend/includes/footer.php"); ?>

<?php $conn->close(); ?>
