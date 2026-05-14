<?php
include("auth.php");
include("../backend/config/db.php");

$message = "";
$messageType = "";

function reviewTableExists($conn, $table) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->bind_param("s", $table);
    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()["total"] ?? 0) > 0;
}

function reviewColumnExists($conn, $table, $column) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()["total"] ?? 0) > 0;
}

function adminUserAvatarPath($path) {
    $path = trim((string) $path);
    if ($path === "") return "";
    if (preg_match('/^(https?:)?\/\//', $path) || str_starts_with($path, "data:")) return $path;
    $path = ltrim($path, "/");
    if (str_starts_with($path, "../")) return $path;
    if (str_starts_with($path, "uploads/")) return "../" . $path;
    return $path;
}

function ensureReviewsSchema($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        product_id INT NOT NULL,
        order_id VARCHAR(60) NOT NULL,
        rating INT DEFAULT 5,
        review_text TEXT NULL,
        status VARCHAR(20) DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_order_product_review (user_id, product_id, order_id)
    )");

    $columns = [
        "user_id" => "INT NOT NULL",
        "product_id" => "INT NOT NULL",
        "order_id" => "VARCHAR(60) NOT NULL",
        "rating" => "INT DEFAULT 5",
        "review_text" => "TEXT NULL",
        "status" => "VARCHAR(20) DEFAULT 'pending'",
        "created_at" => "DATETIME DEFAULT CURRENT_TIMESTAMP"
    ];

    foreach ($columns as $column => $definition) {
        if (!reviewColumnExists($conn, "reviews", $column)) {
            $conn->query("ALTER TABLE reviews ADD COLUMN `$column` $definition");
        }
    }
}

function reviewCountValue($conn, $sql) {
    $result = $conn->query($sql);
    return (int) (($result ? $result->fetch_assoc() : [])["total"] ?? 0);
}

ensureReviewsSchema($conn);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    $reviewId = (int) ($_POST["review_id"] ?? 0);
    $userId = (int) ($_POST["user_id"] ?? 0);

    if ($reviewId > 0 && $action === "approve_review") {
        $stmt = $conn->prepare("UPDATE reviews SET status = 'approved' WHERE id = ?");
        $stmt->bind_param("i", $reviewId);
        $message = $stmt->execute() ? "Review approved successfully." : "Review could not be approved.";
        $messageType = $stmt->affected_rows >= 0 ? "success" : "error";
    }

    if ($reviewId > 0 && $action === "reject_review") {
        $stmt = $conn->prepare("UPDATE reviews SET status = 'rejected' WHERE id = ?");
        $stmt->bind_param("i", $reviewId);
        $message = $stmt->execute() ? "Review rejected successfully." : "Review could not be rejected.";
        $messageType = $stmt->affected_rows >= 0 ? "success" : "error";
    }

    if ($reviewId > 0 && $action === "delete_review") {
        $stmt = $conn->prepare("DELETE FROM reviews WHERE id = ?");
        $stmt->bind_param("i", $reviewId);
        $message = $stmt->execute() ? "Review removed successfully." : "Review could not be removed.";
        $messageType = $stmt->affected_rows >= 0 ? "success" : "error";
    }

    if ($userId > 0 && $action === "block_user" && reviewTableExists($conn, "users")) {
        if (!reviewColumnExists($conn, "users", "is_blocked")) {
            $conn->query("ALTER TABLE users ADD COLUMN is_blocked TINYINT(1) DEFAULT 0");
        }
        $stmt = $conn->prepare("UPDATE users SET is_blocked = 1 WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $message = $stmt->execute() ? "User blocked successfully." : "User could not be blocked.";
        $messageType = $stmt->affected_rows >= 0 ? "success" : "error";
    }
}

$search = trim($_GET["search"] ?? "");
$ratingFilter = trim($_GET["rating"] ?? "");
$statusFilter = trim($_GET["status"] ?? "");
$page = max(1, (int) ($_GET["page"] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];
$types = "";

if ($search !== "") {
    $where[] = "(COALESCE(u.full_name, u.username) LIKE ? OR p.name LIKE ? OR r.review_text LIKE ?)";
    $term = "%" . $search . "%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $types .= "sss";
}

if ($ratingFilter === "5") $where[] = "r.rating = 5";
if ($ratingFilter === "4") $where[] = "r.rating = 4";
if ($ratingFilter === "3") $where[] = "r.rating = 3";
if ($ratingFilter === "low") $where[] = "r.rating <= 2";

if ($statusFilter === "Approved") $where[] = "LOWER(r.status) = 'approved'";
if ($statusFilter === "Pending") $where[] = "LOWER(r.status) = 'pending'";
if ($statusFilter === "Reported") $where[] = "LOWER(r.status) = 'reported'";

$stats = [
    "Total Reviews" => reviewCountValue($conn, "SELECT COUNT(*) AS total FROM reviews"),
    "Positive Reviews" => reviewCountValue($conn, "SELECT COUNT(*) AS total FROM reviews WHERE rating >= 4"),
    "Negative Reviews" => reviewCountValue($conn, "SELECT COUNT(*) AS total FROM reviews WHERE rating <= 2"),
    "Pending Reviews" => reviewCountValue($conn, "SELECT COUNT(*) AS total FROM reviews WHERE LOWER(status) = 'pending'"),
    "Reported Reviews" => reviewCountValue($conn, "SELECT COUNT(*) AS total FROM reviews WHERE LOWER(status) = 'reported'")
];

$countSql = "SELECT COUNT(*) AS total FROM reviews r
        LEFT JOIN users u ON u.id = r.user_id
        LEFT JOIN products p ON p.id = r.product_id"
        . ($where ? " WHERE " . implode(" AND ", $where) : "");
$countStmt = $conn->prepare($countSql);
if ($params) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalReviews = (int) ($countStmt->get_result()->fetch_assoc()["total"] ?? 0);

$sql = "SELECT r.*,
        COALESCE(u.full_name, u.username, 'Customer') AS display_name,
        u.profile_image,
        u.profile_image_position_x,
        u.profile_image_position_y,
        u.profile_image_zoom,
        COALESCE(p.name, 'Product') AS display_product
        FROM reviews r
        LEFT JOIN users u ON u.id = r.user_id
        LEFT JOIN products p ON p.id = r.product_id"
        . ($where ? " WHERE " . implode(" AND ", $where) : "")
        . " ORDER BY r.created_at DESC, r.id DESC LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$queryParams = $params;
$queryParams[] = $perPage;
$queryParams[] = $offset;
$stmt->bind_param($types . "ii", ...$queryParams);
$stmt->execute();
$reviews = $stmt->get_result();

include("includes/admin_header.php");
include("includes/admin_sidebar.php");
?>
<main class="admin-main">
    <header class="admin-topbar admin-dark-topbar">
        <div>
            <span>Zafiro Casa Luxury Living</span>
            <h1>Reviews</h1>
            <p>View and manage customer product reviews and ratings.</p>
        </div>
    </header>

    <?php if ($message): ?>
        <div class="admin-popup <?php echo $messageType === 'success' ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <section class="admin-dashboard-grid order-stats-grid">
        <?php foreach ($stats as $label => $value): ?>
            <article class="admin-stat-card">
                <span><?php echo htmlspecialchars($label); ?></span>
                <strong><?php echo htmlspecialchars((string) $value); ?></strong>
                <p>Customer review data</p>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="admin-form-card manage-filter-card">
        <form method="GET" action="reviews.php" class="admin-reviews-filter">
            <label class="admin-review-search-field">
                <input type="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search reviews">
                <i class="fas fa-search"></i>
            </label>
            <select name="rating">
                <option value="">All Ratings</option>
                <option value="5" <?php echo $ratingFilter === "5" ? "selected" : ""; ?>>5 Star</option>
                <option value="4" <?php echo $ratingFilter === "4" ? "selected" : ""; ?>>4 Star</option>
                <option value="3" <?php echo $ratingFilter === "3" ? "selected" : ""; ?>>3 Star</option>
                <option value="low" <?php echo $ratingFilter === "low" ? "selected" : ""; ?>>Low Ratings</option>
            </select>
            <select name="status">
                <option value="">All Status</option>
                <option value="Approved" <?php echo $statusFilter === "Approved" ? "selected" : ""; ?>>Approved</option>
                <option value="Pending" <?php echo $statusFilter === "Pending" ? "selected" : ""; ?>>Pending</option>
                <option value="Reported" <?php echo $statusFilter === "Reported" ? "selected" : ""; ?>>Reported</option>
            </select>
            <button type="submit" class="admin-btn">Apply</button>
            <a class="admin-btn admin-btn-light" href="reviews.php">Reset</a>
        </form>
    </section>

    <section class="admin-review-list">
        <?php if ($reviews && $reviews->num_rows > 0): ?>
            <?php while ($review = $reviews->fetch_assoc()): ?>
                <?php
                $status = $review["status"] ?: "pending";
                $rating = max(1, min(5, (int) ($review["rating"] ?? 5)));
                $avatar = adminUserAvatarPath($review["profile_image"] ?? "");
                $avatarX = htmlspecialchars($review["profile_image_position_x"] ?? "50");
                $avatarY = htmlspecialchars($review["profile_image_position_y"] ?? "50");
                $avatarZoom = htmlspecialchars($review["profile_image_zoom"] ?? "1");
                ?>
                <article class="admin-review-card">
                    <div class="admin-review-customer">
                        <?php if ($avatar): ?>
                            <div class="admin-user-avatar-frame">
                                <img class="review-user-avatar" src="<?php echo htmlspecialchars($avatar); ?>" alt="<?php echo htmlspecialchars($review["display_name"]); ?>" loading="lazy" decoding="async" width="72" height="72" data-profile-adjust-preview data-position-x="<?php echo $avatarX; ?>" data-position-y="<?php echo $avatarY; ?>" data-zoom="<?php echo $avatarZoom; ?>" onerror="this.style.display='none';this.closest('.admin-user-avatar-frame').classList.remove('has-image');this.closest('.admin-user-avatar-frame').nextElementSibling.style.display='grid';">
                            </div>
                            <div class="admin-user-avatar" style="display:none;"><?php echo htmlspecialchars(strtoupper(substr($review["display_name"], 0, 1))); ?></div>
                        <?php else: ?>
                            <div class="admin-user-avatar"><?php echo htmlspecialchars(strtoupper(substr($review["display_name"], 0, 1))); ?></div>
                        <?php endif; ?>
                        <div>
                            <h2><?php echo htmlspecialchars($review["display_name"]); ?></h2>
                            <p><?php echo htmlspecialchars($review["display_product"]); ?></p>
                        </div>
                    </div>

                    <div class="admin-review-body">
                        <div class="admin-review-stars" aria-label="<?php echo $rating; ?> star rating">
                            <?php echo str_repeat("★", $rating) . str_repeat("☆", 5 - $rating); ?>
                        </div>
                        <p><?php echo htmlspecialchars($review["review_text"] ?: "No review message provided."); ?></p>
                        <small><?php echo htmlspecialchars($review["created_at"] ?: "N/A"); ?></small>
                    </div>

                    <div class="admin-review-status">
                        <span class="status-pill"><?php echo htmlspecialchars($status); ?></span>
                    </div>

                    <div class="admin-review-actions">
                        <button type="button" class="admin-action-link view-review-btn" data-review="<?php echo (int) $review["id"]; ?>">View Review</button>
                        <form method="POST" action="reviews.php">
                            <input type="hidden" name="action" value="approve_review">
                            <input type="hidden" name="review_id" value="<?php echo (int) $review["id"]; ?>">
                            <button type="submit" class="admin-action-link edit">Approve</button>
                        </form>
                        <form method="POST" action="reviews.php">
                            <input type="hidden" name="action" value="reject_review">
                            <input type="hidden" name="review_id" value="<?php echo (int) $review["id"]; ?>">
                            <button type="submit" class="admin-action-link danger">Reject</button>
                        </form>
                        <form method="POST" action="reviews.php" class="delete-review-form">
                            <input type="hidden" name="action" value="delete_review">
                            <input type="hidden" name="review_id" value="<?php echo (int) $review["id"]; ?>">
                            <button type="submit" class="admin-action-link danger">Delete</button>
                        </form>
                    </div>

                    <div class="admin-review-modal-data" id="reviewData-<?php echo (int) $review["id"]; ?>">
                        <h2><?php echo htmlspecialchars($review["display_product"]); ?></h2>
                        <p><strong>Customer:</strong> <?php echo htmlspecialchars($review["display_name"]); ?></p>
                        <p><strong>Rating:</strong> <?php echo $rating; ?> / 5</p>
                        <p><strong>Status:</strong> <?php echo htmlspecialchars($status); ?></p>
                        <p><strong>Date:</strong> <?php echo htmlspecialchars($review["created_at"] ?: "N/A"); ?></p>
                        <p><strong>Review:</strong> <?php echo htmlspecialchars($review["review_text"] ?: "No review message provided."); ?></p>
                    </div>
                </article>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-admin-state">
                <h2>No customer reviews available.</h2>
                <p>Customer product reviews will appear here after submission.</p>
            </div>
        <?php endif; ?>
    </section>
    <?php if ($totalReviews > $perPage): ?>
        <nav class="admin-pagination">
            <?php for ($i = 1, $pages = (int) ceil($totalReviews / $perPage); $i <= $pages; $i++): ?>
                <?php $pageUrl = "reviews.php?" . http_build_query(array_merge($_GET, ["page" => $i])); ?>
                <a class="<?php echo $i === $page ? "active" : ""; ?>" href="<?php echo htmlspecialchars($pageUrl); ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>

    <div class="admin-modal" id="reviewDetailsModal">
        <div class="admin-modal-card">
            <button type="button" class="admin-search-close" id="closeReviewDetailsModal">&times;</button>
            <div id="reviewDetailsContent"></div>
        </div>
    </div>
<?php include("includes/admin_footer.php"); ?>
