<?php
include("auth.php");
include("../backend/config/db.php");

$message = "";
$messageType = "";

function userColumnExists($conn, $table, $column) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()["total"] ?? 0) > 0;
}

function userTableExists($conn, $table) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->bind_param("s", $table);
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

function ensureUsersSchema($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(120) NOT NULL,
        username VARCHAR(80) NOT NULL UNIQUE,
        email VARCHAR(160) NOT NULL UNIQUE,
        phone VARCHAR(30) NULL,
        password VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $columns = [
        "gender" => "VARCHAR(30) NULL",
        "profile_image" => "VARCHAR(255) NULL",
        "profile_image_position_x" => "DECIMAL(5,2) DEFAULT 50",
        "profile_image_position_y" => "DECIMAL(5,2) DEFAULT 50",
        "profile_image_zoom" => "DECIMAL(4,2) DEFAULT 1",
        "address" => "TEXT NULL",
        "city" => "VARCHAR(80) NULL",
        "state" => "VARCHAR(80) NULL",
        "pincode" => "VARCHAR(20) NULL",
        "status" => "VARCHAR(20) DEFAULT 'active'",
        "is_blocked" => "TINYINT(1) DEFAULT 0",
        "is_deleted" => "TINYINT(1) DEFAULT 0",
        "deleted_at" => "DATETIME NULL",
        "last_login" => "DATETIME NULL"
    ];

    foreach ($columns as $column => $definition) {
        if (!userColumnExists($conn, "users", $column)) {
            $conn->query("ALTER TABLE users ADD COLUMN `$column` $definition");
        }
    }

    $conn->query("UPDATE users SET status = 'active' WHERE status IS NULL OR status = ''");
}

function userCountValue($conn, $sql) {
    $result = $conn->query($sql);
    return (int) (($result ? $result->fetch_assoc() : [])["total"] ?? 0);
}

function formatUserAddress($row) {
    $parts = array_filter([
        $row["house_no"] ?? "",
        $row["street_area"] ?? "",
        $row["city"] ?? "",
        $row["state"] ?? "",
        $row["pincode"] ?? ""
    ]);
    return implode(", ", $parts);
}

ensureUsersSchema($conn);
$conn->query("CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(40) NULL,
    order_code VARCHAR(40) NULL,
    user_id INT NULL,
    total_amount DECIMAL(10,2) DEFAULT 0,
    total DECIMAL(10,2) DEFAULT 0,
    order_status VARCHAR(50) DEFAULT 'Pending',
    order_date DATETIME DEFAULT CURRENT_TIMESTAMP
)");
$orderColumns = [
    "order_id" => "VARCHAR(40) NULL",
    "order_code" => "VARCHAR(40) NULL",
    "user_id" => "INT NULL",
    "total_amount" => "DECIMAL(10,2) DEFAULT 0",
    "total" => "DECIMAL(10,2) DEFAULT 0",
    "order_status" => "VARCHAR(50) DEFAULT 'Pending'",
    "order_date" => "DATETIME DEFAULT CURRENT_TIMESTAMP"
];
foreach ($orderColumns as $column => $definition) {
    if (!userColumnExists($conn, "orders", $column)) {
        $conn->query("ALTER TABLE orders ADD COLUMN `$column` $definition");
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    $userId = (int) ($_POST["user_id"] ?? 0);

    if ($userId > 0 && $action === "toggle_status") {
        $status = $_POST["status"] === "active" ? "active" : "inactive";
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $userId);
        $message = $stmt->execute() ? "User status updated." : "User status could not be updated.";
        $messageType = $stmt->affected_rows >= 0 ? "success" : "error";
    }

    if ($userId > 0 && $action === "toggle_block") {
        $blocked = (int) ($_POST["is_blocked"] ?? 0);
        $stmt = $conn->prepare("UPDATE users SET is_blocked = ? WHERE id = ?");
        $stmt->bind_param("ii", $blocked, $userId);
        $message = $stmt->execute() ? "User block status updated." : "User block status could not be updated.";
        $messageType = $stmt->affected_rows >= 0 ? "success" : "error";
    }

    if ($userId > 0 && $action === "delete_user") {
        $stmt = $conn->prepare("UPDATE users SET is_deleted = 1, status = 'deleted', deleted_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $message = $stmt->execute() ? "User deleted safely." : "User could not be deleted.";
        $messageType = $stmt->affected_rows >= 0 ? "success" : "error";
    }

    if ($userId > 0 && $action === "edit_user") {
        $fullName = trim($_POST["full_name"] ?? "");
        $username = trim($_POST["username"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $phone = trim($_POST["phone"] ?? "");
        $gender = trim($_POST["gender"] ?? "");
        if ($fullName === "" || $username === "" || $email === "") {
            $message = "Full name, username, and email are required.";
            $messageType = "error";
        } else {
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, username = ?, email = ?, phone = ?, gender = ? WHERE id = ?");
            $stmt->bind_param("sssssi", $fullName, $username, $email, $phone, $gender, $userId);
            $message = $stmt->execute() ? "User updated successfully." : "User could not be updated.";
            $messageType = $stmt->affected_rows >= 0 ? "success" : "error";
        }
    }
}

$search = trim($_GET["search"] ?? "");
$filter = trim($_GET["filter"] ?? "");
$sort = trim($_GET["sort"] ?? "newest");
$page = max(1, (int) ($_GET["page"] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];
$types = "";

if ($search !== "") {
    $where[] = "(u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $term = "%" . $search . "%";
    for ($i = 0; $i < 4; $i++) $params[] = $term;
    $types .= "ssss";
}

if ($filter === "active") $where[] = "LOWER(u.status) = 'active' AND COALESCE(u.is_blocked, 0) = 0 AND COALESCE(u.is_deleted, 0) = 0";
if ($filter === "blocked") $where[] = "COALESCE(u.is_blocked, 0) = 1";
if ($filter === "deleted") $where[] = "(COALESCE(u.is_deleted, 0) = 1 OR u.deleted_at IS NOT NULL OR LOWER(u.status) = 'deleted')";
if ($filter === "new") $where[] = "MONTH(u.created_at) = MONTH(CURDATE()) AND YEAR(u.created_at) = YEAR(CURDATE())";
if ($filter === "with_orders") $where[] = "EXISTS (SELECT 1 FROM orders o WHERE o.user_id = u.id)";
if ($filter === "without_orders") $where[] = "NOT EXISTS (SELECT 1 FROM orders o WHERE o.user_id = u.id)";

$orderBy = "u.created_at DESC, u.id DESC";
if ($sort === "oldest") $orderBy = "u.created_at ASC, u.id ASC";
if ($sort === "high") $orderBy = "total_spending DESC";
if ($sort === "low") $orderBy = "total_spending ASC";

$addressSelect = userTableExists($conn, "user_addresses")
    ? "(SELECT CONCAT_WS(', ', ua.house_no, ua.street_area, ua.city, ua.state, ua.pincode) FROM user_addresses ua WHERE ua.user_id = u.id ORDER BY ua.id DESC LIMIT 1) AS saved_address"
    : "u.address AS saved_address";

$countSql = "SELECT COUNT(*) AS total FROM users u" . ($where ? " WHERE " . implode(" AND ", $where) : "");
$countStmt = $conn->prepare($countSql);
if ($params) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalUsers = (int) ($countStmt->get_result()->fetch_assoc()["total"] ?? 0);

$sql = "SELECT u.*,
        $addressSelect,
        (SELECT COUNT(DISTINCT COALESCE(NULLIF(o.order_id, ''), NULLIF(o.order_code, ''), o.id)) FROM orders o WHERE o.user_id = u.id) AS total_orders,
        (SELECT COALESCE(SUM(CASE WHEN LOWER(o.order_status) = 'delivered' THEN CASE WHEN o.total_amount > 0 THEN o.total_amount ELSE o.total END ELSE 0 END), 0) FROM orders o WHERE o.user_id = u.id) AS total_spending
        FROM users u" . ($where ? " WHERE " . implode(" AND ", $where) : "") . " ORDER BY $orderBy LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$queryParams = $params;
$queryParams[] = $perPage;
$queryParams[] = $offset;
$stmt->bind_param($types . "ii", ...$queryParams);
$stmt->execute();
$users = $stmt->get_result();

$stats = [
    "Total Users" => userCountValue($conn, "SELECT COUNT(*) AS total FROM users"),
    "Active Users" => userCountValue($conn, "SELECT COUNT(*) AS total FROM users WHERE LOWER(status) = 'active' AND COALESCE(is_blocked, 0) = 0 AND COALESCE(is_deleted, 0) = 0"),
    "Blocked Users" => userCountValue($conn, "SELECT COUNT(*) AS total FROM users WHERE COALESCE(is_blocked, 0) = 1"),
    "Deleted Users" => userCountValue($conn, "SELECT COUNT(*) AS total FROM users WHERE COALESCE(is_deleted, 0) = 1 OR deleted_at IS NOT NULL OR LOWER(status) = 'deleted'"),
    "New Users This Month" => userCountValue($conn, "SELECT COUNT(*) AS total FROM users WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")
];

include("includes/admin_header.php");
include("includes/admin_sidebar.php");
?>
<main class="admin-main">
    <header class="admin-topbar admin-dark-topbar">
        <div>
            <span>Zafiro Casa Luxury Living</span>
            <h1>Manage Users</h1>
            <p>View and manage customer accounts and activity.</p>
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
                <p>Customer account data</p>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="admin-form-card manage-filter-card">
        <form method="GET" action="manage_users.php" class="manage-users-filter">
            <button type="button" class="admin-search-toggle" id="adminUserSearchToggle" aria-label="Open user search"><i class="fas fa-search"></i></button>
            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
            <select name="filter">
                <option value="">All Users</option>
                <option value="active" <?php echo $filter === "active" ? "selected" : ""; ?>>Active Users</option>
                <option value="blocked" <?php echo $filter === "blocked" ? "selected" : ""; ?>>Blocked Users</option>
                <option value="deleted" <?php echo $filter === "deleted" ? "selected" : ""; ?>>Deleted Users</option>
                <option value="new" <?php echo $filter === "new" ? "selected" : ""; ?>>New Users</option>
                <option value="with_orders" <?php echo $filter === "with_orders" ? "selected" : ""; ?>>Users with Orders</option>
                <option value="without_orders" <?php echo $filter === "without_orders" ? "selected" : ""; ?>>Users without Orders</option>
            </select>
            <select name="sort">
                <option value="newest" <?php echo $sort === "newest" ? "selected" : ""; ?>>Newest First</option>
                <option value="oldest" <?php echo $sort === "oldest" ? "selected" : ""; ?>>Oldest First</option>
                <option value="high" <?php echo $sort === "high" ? "selected" : ""; ?>>Highest Spending</option>
                <option value="low" <?php echo $sort === "low" ? "selected" : ""; ?>>Lowest Spending</option>
            </select>
            <button type="submit" class="admin-btn">Apply</button>
            <a class="admin-btn admin-btn-light" href="manage_users.php">Reset</a>
        </form>
        <div class="admin-search-popup" id="adminUserSearchPopup">
            <form method="GET" action="manage_users.php" class="admin-search-popup-card">
                <button type="button" class="admin-search-close" id="adminUserSearchClose" aria-label="Close search">&times;</button>
                <h3>Search Users</h3>
                <p>Search by name, username, email, or phone.</p>
                <input type="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Type user keyword...">
                <button type="submit" class="admin-btn">Search</button>
            </form>
        </div>
    </section>

    <section class="user-card-list">
        <?php if ($users && $users->num_rows > 0): ?>
            <?php while ($user = $users->fetch_assoc()): ?>
                <?php
                $status = strtolower($user["status"] ?? "active");
                $isBlocked = (int) ($user["is_blocked"] ?? 0) === 1;
                $isDeleted = (int) ($user["is_deleted"] ?? 0) === 1 || !empty($user["deleted_at"]) || $status === "deleted";
                $statusLabel = $isDeleted ? "Deleted" : ($isBlocked ? "Blocked" : ucfirst($status ?: "Active"));
                $avatar = adminUserAvatarPath($user["profile_image"] ?? "");
                $avatarX = htmlspecialchars($user["profile_image_position_x"] ?? "50");
                $avatarY = htmlspecialchars($user["profile_image_position_y"] ?? "50");
                $avatarZoom = htmlspecialchars($user["profile_image_zoom"] ?? "1");
                ?>
                <article class="admin-user-card">
                    <div class="admin-user-profile">
                        <?php if ($avatar): ?>
                            <div class="admin-user-avatar-frame has-image">
                                <img class="user-avatar" src="<?php echo htmlspecialchars($avatar); ?>" alt="<?php echo htmlspecialchars($user["full_name"]); ?>" loading="lazy" decoding="async" width="95" height="95" data-profile-adjust-preview data-position-x="<?php echo $avatarX; ?>" data-position-y="<?php echo $avatarY; ?>" data-zoom="<?php echo $avatarZoom; ?>" onerror="this.style.display='none';this.closest('.admin-user-avatar-frame').classList.remove('has-image');this.closest('.admin-user-avatar-frame').nextElementSibling.style.display='grid';">
                            </div>
                            <div class="admin-user-avatar" style="display:none;"><?php echo htmlspecialchars(strtoupper(substr($user["full_name"] ?: $user["username"], 0, 1))); ?></div>
                        <?php else: ?>
                            <div class="admin-user-avatar"><?php echo htmlspecialchars(strtoupper(substr($user["full_name"] ?: $user["username"], 0, 1))); ?></div>
                        <?php endif; ?>
                        <div class="admin-user-identity">
                            <span class="status-pill"><?php echo htmlspecialchars($statusLabel); ?></span>
                            <h2><?php echo htmlspecialchars($user["full_name"]); ?></h2>
                            <p>@<?php echo htmlspecialchars($user["username"]); ?></p>
                        </div>
                    </div>

                    <div class="admin-user-details">
                        <span class="admin-user-info-box admin-user-email-box"><strong>Email</strong><?php echo htmlspecialchars($user["email"]); ?></span>
                        <span><strong>Phone</strong><?php echo htmlspecialchars($user["phone"] ?: "N/A"); ?></span>
                        <span><strong>Gender</strong><?php echo htmlspecialchars($user["gender"] ?: "N/A"); ?></span>
                        <span><strong>Registered</strong><?php echo htmlspecialchars($user["created_at"] ?: "N/A"); ?></span>
                        <span><strong>Total Orders</strong><?php echo (int) $user["total_orders"]; ?></span>
                        <span><strong>Total Spending</strong>₹<?php echo number_format((float) $user["total_spending"], 2); ?></span>
                        <span><strong>Last Login</strong><?php echo htmlspecialchars($user["last_login"] ?: "Never"); ?></span>
                        <span class="admin-user-info-box admin-user-address-box"><strong>Saved Address</strong><?php echo htmlspecialchars($user["saved_address"] ?: "No address saved"); ?></span>
                    </div>

                    <div class="admin-user-actions">
                        <button type="button" class="admin-action-link view-user-btn" data-user="<?php echo (int) $user["id"]; ?>">View Profile</button>
                        <button type="button" class="admin-action-link edit edit-user-btn"
                            data-id="<?php echo (int) $user["id"]; ?>"
                            data-name="<?php echo htmlspecialchars($user["full_name"]); ?>"
                            data-username="<?php echo htmlspecialchars($user["username"]); ?>"
                            data-email="<?php echo htmlspecialchars($user["email"]); ?>"
                            data-phone="<?php echo htmlspecialchars($user["phone"]); ?>"
                            data-gender="<?php echo htmlspecialchars($user["gender"]); ?>">Edit User</button>
                        <form method="POST" action="manage_users.php">
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="user_id" value="<?php echo (int) $user["id"]; ?>">
                            <input type="hidden" name="status" value="<?php echo $status === "active" ? "inactive" : "active"; ?>">
                            <button type="submit" class="admin-action-link"><?php echo $status === "active" ? "Deactivate" : "Activate"; ?></button>
                        </form>
                        <form method="POST" action="manage_users.php">
                            <input type="hidden" name="action" value="toggle_block">
                            <input type="hidden" name="user_id" value="<?php echo (int) $user["id"]; ?>">
                            <input type="hidden" name="is_blocked" value="<?php echo $isBlocked ? 0 : 1; ?>">
                            <button type="submit" class="admin-action-link"><?php echo $isBlocked ? "Unblock" : "Block"; ?></button>
                        </form>
                        <a class="admin-action-link" href="manage_orders.php?search=<?php echo urlencode($user["email"]); ?>">View Orders</a>
                        <form method="POST" action="manage_users.php" class="delete-user-form">
                            <input type="hidden" name="action" value="delete_user">
                            <input type="hidden" name="user_id" value="<?php echo (int) $user["id"]; ?>">
                            <button type="submit" class="admin-action-link danger">Delete User</button>
                        </form>
                    </div>

                    <div class="admin-user-modal-data" id="userData-<?php echo (int) $user["id"]; ?>">
                        <h2><?php echo htmlspecialchars($user["full_name"]); ?></h2>
                        <p><strong>Username:</strong> @<?php echo htmlspecialchars($user["username"]); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($user["email"]); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($user["phone"] ?: "N/A"); ?></p>
                        <p><strong>Gender:</strong> <?php echo htmlspecialchars($user["gender"] ?: "N/A"); ?></p>
                        <p><strong>Address:</strong> <?php echo htmlspecialchars($user["saved_address"] ?: "No address saved"); ?></p>
                        <p><strong>Total Orders:</strong> <?php echo (int) $user["total_orders"]; ?></p>
                        <p><strong>Total Spending:</strong> ₹<?php echo number_format((float) $user["total_spending"], 2); ?></p>
                        <p><strong>Wishlist Items:</strong> Local browser data</p>
                        <p><strong>Cart Items:</strong> Local browser data</p>
                        <p><strong>Status:</strong> <?php echo htmlspecialchars($statusLabel); ?></p>
                        <p><strong>Registered:</strong> <?php echo htmlspecialchars($user["created_at"] ?: "N/A"); ?></p>
                        <p><strong>Last Login:</strong> <?php echo htmlspecialchars($user["last_login"] ?: "Never"); ?></p>
                    </div>
                </article>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-admin-state">
                <h2>No real users found.</h2>
                <p>Registered customers will appear here.</p>
            </div>
        <?php endif; ?>
    </section>
    <?php if ($totalUsers > $perPage): ?>
        <nav class="admin-pagination">
            <?php for ($i = 1, $pages = (int) ceil($totalUsers / $perPage); $i <= $pages; $i++): ?>
                <?php $pageUrl = "manage_users.php?" . http_build_query(array_merge($_GET, ["page" => $i])); ?>
                <a class="<?php echo $i === $page ? "active" : ""; ?>" href="<?php echo htmlspecialchars($pageUrl); ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>

    <div class="admin-modal" id="userProfileModal">
        <div class="admin-modal-card">
            <button type="button" class="admin-search-close" id="closeUserProfileModal">&times;</button>
            <div id="userProfileContent"></div>
        </div>
    </div>

    <div class="admin-modal" id="editUserModal">
        <form method="POST" action="manage_users.php" class="admin-modal-card">
            <button type="button" class="admin-search-close" id="closeEditUserModal">&times;</button>
            <h2>Edit User</h2>
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="user_id" id="editUserId">
            <div class="admin-form-grid">
                <label>Full Name<input type="text" name="full_name" id="editUserFullName" required></label>
                <label>Username<input type="text" name="username" id="editUserUsername" required></label>
                <label>Email<input type="email" name="email" id="editUserEmail" required></label>
                <label>Phone<input type="text" name="phone" id="editUserPhone"></label>
                <label>Gender<input type="text" name="gender" id="editUserGender"></label>
            </div>
            <div class="modal-actions">
                <button type="submit" class="admin-btn">Save User</button>
                <button type="button" class="admin-btn admin-btn-light" id="cancelEditUserModal">Cancel</button>
            </div>
        </form>
    </div>
<?php include("includes/admin_footer.php"); ?>
