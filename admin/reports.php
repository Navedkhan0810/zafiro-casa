<?php
include("auth.php");
include("../backend/config/db.php");
include_once("../backend/includes/admin_reports.php");

ensureAdminReportsTable($conn);

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "clear_reports") {
    $conn->query("DELETE FROM admin_reports");
    adminReportLog($conn, "clear_reports", "Cleared admin activity reports.", "admin_reports");
    header("Location: reports.php");
    exit;
}

$actionType = trim($_GET["action_type"] ?? "");
$dateFrom = trim($_GET["date_from"] ?? "");
$dateTo = trim($_GET["date_to"] ?? "");
$search = trim($_GET["search"] ?? "");
$export = ($_GET["export"] ?? "") === "csv";

$where = [];
$params = [];
$types = "";

if ($actionType !== "") {
    $where[] = "action_type = ?";
    $params[] = $actionType;
    $types .= "s";
}
if ($dateFrom !== "") {
    $where[] = "DATE(created_at) >= ?";
    $params[] = $dateFrom;
    $types .= "s";
}
if ($dateTo !== "") {
    $where[] = "DATE(created_at) <= ?";
    $params[] = $dateTo;
    $types .= "s";
}
if ($search !== "") {
    $where[] = "(description LIKE ? OR admin_name LIKE ? OR admin_email LIKE ? OR item_name LIKE ? OR item_id LIKE ?)";
    $term = "%" . $search . "%";
    array_push($params, $term, $term, $term, $term, $term);
    $types .= "sssss";
}

$sql = "SELECT * FROM admin_reports" . ($where ? " WHERE " . implode(" AND ", $where) : "") . " ORDER BY created_at DESC, id DESC";
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$reports = $stmt->get_result();
$reportRows = [];
while ($reports && $row = $reports->fetch_assoc()) {
    $reportRows[] = $row;
}

if ($export) {
    header("Content-Type: text/csv; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"admin-reports-" . date("Y-m-d") . ".csv\"");
    $out = fopen("php://output", "w");
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ["Action", "Description", "Admin", "Affected Item", "Date/Time", "IP Address"]);
    foreach ($reportRows as $row) {
        fputcsv($out, [$row["action_type"], $row["description"], trim($row["admin_name"] . " " . $row["admin_email"]), trim($row["item_name"] . " #" . $row["item_id"]), $row["created_at"], $row["ip_address"]]);
    }
    exit;
}

$defaultActions = [
    "admin_login", "admin_logout", "add_product", "edit_product", "delete_product", "change_product_status",
    "add_category", "edit_category", "delete_category", "add_subcategory", "edit_subcategory", "delete_subcategory",
    "update_order_status", "approve_review", "reject_review", "delete_review", "send_notification", "update_settings", "clear_reports"
];
$actionOptions = $defaultActions;
$actions = $conn->query("SELECT DISTINCT action_type FROM admin_reports WHERE action_type IS NOT NULL AND action_type <> '' ORDER BY action_type ASC");
while ($actions && $row = $actions->fetch_assoc()) {
    $actionOptions[] = $row["action_type"];
}
$actionOptions = array_values(array_unique($actionOptions));
sort($actionOptions);
$totalReports = (int) (($conn->query("SELECT COUNT(*) AS total FROM admin_reports")->fetch_assoc())["total"] ?? 0);
$displayedReports = count($reportRows);
$todayReports = (int) (($conn->query("SELECT COUNT(*) AS total FROM admin_reports WHERE DATE(created_at) = CURDATE()")->fetch_assoc())["total"] ?? 0);
$productReports = (int) (($conn->query("SELECT COUNT(*) AS total FROM admin_reports WHERE action_type LIKE '%product%'")->fetch_assoc())["total"] ?? 0);
$securityReports = (int) (($conn->query("SELECT COUNT(*) AS total FROM admin_reports WHERE action_type IN ('admin_login','admin_logout')")->fetch_assoc())["total"] ?? 0);
$overviewLabels = [];
$overviewValues = [];
$monthLabels = [];
$monthValues = [];
$heatmap = [];
$distribution = ["Product" => 0, "Security" => 0, "Clear" => 0, "Updates" => 0, "Other" => 0];

for ($i = 6; $i >= 0; $i--) {
    $day = date("Y-m-d", strtotime("-$i days"));
    $overviewLabels[] = date("d M", strtotime($day));
    $overviewValues[$day] = 0;
}
$overviewResult = $conn->query("SELECT DATE(created_at) AS report_day, COUNT(*) AS total FROM admin_reports WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at)");
while ($overviewResult && $row = $overviewResult->fetch_assoc()) {
    if (isset($overviewValues[$row["report_day"]])) $overviewValues[$row["report_day"]] = (int) $row["total"];
}

$daysInMonth = (int) date("t");
for ($i = 1; $i <= $daysInMonth; $i++) {
    $day = date("Y-m-") . str_pad((string) $i, 2, "0", STR_PAD_LEFT);
    $monthLabels[] = date("d M", strtotime($day));
    $monthValues[$day] = 0;
}
$monthResult = $conn->query("SELECT DATE(created_at) AS report_day, COUNT(*) AS total FROM admin_reports WHERE YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE()) GROUP BY DATE(created_at)");
while ($monthResult && $row = $monthResult->fetch_assoc()) {
    if (isset($monthValues[$row["report_day"]])) $monthValues[$row["report_day"]] = (int) $row["total"];
}

$distResult = $conn->query("SELECT action_type, COUNT(*) AS total FROM admin_reports GROUP BY action_type");
while ($distResult && $row = $distResult->fetch_assoc()) {
    $action = $row["action_type"];
    $count = (int) $row["total"];
    if (str_contains($action, "product")) $distribution["Product"] += $count;
    elseif (in_array($action, ["admin_login", "admin_logout"], true)) $distribution["Security"] += $count;
    elseif ($action === "clear_reports") $distribution["Clear"] += $count;
    elseif (str_contains($action, "update") || str_contains($action, "edit")) $distribution["Updates"] += $count;
    else $distribution["Other"] += $count;
}

foreach (["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"] as $day) {
    foreach ([0, 4, 8, 12, 16, 20] as $hour) $heatmap[$day][$hour] = 0;
}
$heatResult = $conn->query("SELECT DAYOFWEEK(created_at) AS day_num, FLOOR(HOUR(created_at) / 4) * 4 AS hour_block, COUNT(*) AS total FROM admin_reports WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) GROUP BY DAYOFWEEK(created_at), FLOOR(HOUR(created_at) / 4)");
$dayNames = [1 => "Sun", 2 => "Mon", 3 => "Tue", 4 => "Wed", 5 => "Thu", 6 => "Fri", 7 => "Sat"];
while ($heatResult && $row = $heatResult->fetch_assoc()) {
    $heatmap[$dayNames[(int) $row["day_num"]]][(int) $row["hour_block"]] += (int) $row["total"];
}

include("includes/admin_header.php");
include("includes/admin_sidebar.php");
?>
<main class="admin-main admin-reports-main">
    <header class="admin-topbar admin-dark-topbar reports-hero">
        <div>
            <span><i class="fa-solid fa-shield-halved"></i> Zafiro Casa</span>
            <h1>Reports</h1>
            <p>Admin activity report and change history.</p>
        </div>
    </header>

    <section class="reports-summary-grid">
        <article><i class="fa-solid fa-file-lines"></i><span>Total Reports</span><strong><?php echo $totalReports; ?></strong><small>All recorded activity</small></article>
        <article><i class="fa-solid fa-list-check"></i><span>Today's Actions</span><strong><?php echo $todayReports; ?></strong><small>Actions logged today</small></article>
        <article><i class="fa-solid fa-bag-shopping"></i><span>Product Changes</span><strong><?php echo $productReports; ?></strong><small>Catalog activity</small></article>
        <article><i class="fa-solid fa-shield-halved"></i><span>Security/Login Logs</span><strong><?php echo $securityReports; ?></strong><small>Login and logout logs</small></article>
    </section>

    <section class="admin-form-card reports-filter-card">
        <form method="GET" action="reports.php" class="reports-filter-form">
            <div class="reports-filter-row">
                <label class="reports-field">Search<input class="reports-control" type="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search reports..."></label>
                <label class="reports-field">Action<span class="reports-select-wrap"><select name="action_type" class="reports-control reports-action-select">
                    <option value="">All Actions</option>
                    <?php foreach ($actionOptions as $option): ?>
                        <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $actionType === $option ? "selected" : ""; ?>><?php echo htmlspecialchars($option); ?></option>
                    <?php endforeach; ?>
                </select></span></label>
                <label class="reports-field">From<span class="reports-date-wrap"><input class="reports-control" type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>"></span></label>
                <label class="reports-field">To<span class="reports-date-wrap"><input class="reports-control" type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>"></span></label>
                <div class="reports-filter-actions">
                    <button type="submit" class="admin-btn"><i class="fa-solid fa-magnifying-glass"></i> Apply</button>
                    <a class="admin-btn admin-btn-light" href="reports.php">Reset</a>
                </div>
            </div>
        </form>
        <div class="reports-tool-actions">
            <a class="reports-mini-btn" href="reports.php?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, ["export" => "csv"]))); ?>"><i class="fa-solid fa-download"></i> Export</a>
            <form method="POST">
                <input type="hidden" name="action" value="clear_reports">
                <button type="submit" class="reports-mini-btn danger"><i class="fa-solid fa-trash"></i> Clear Reports</button>
            </form>
        </div>
    </section>

    <section class="reports-charts">
        <article class="admin-panel-card reports-chart-card reports-overview-card">
            <div class="reports-card-head"><div><span>Overview</span><h2>Reports Over Time</h2></div><select id="reportsOverviewRange"><option value="week">Last 7 Days</option><option value="month">This Month</option></select></div>
            <canvas id="reportsOverviewChart" data-week-labels="<?php echo htmlspecialchars(json_encode($overviewLabels)); ?>" data-week-values="<?php echo htmlspecialchars(json_encode(array_values($overviewValues))); ?>" data-month-labels="<?php echo htmlspecialchars(json_encode($monthLabels)); ?>" data-month-values="<?php echo htmlspecialchars(json_encode(array_values($monthValues))); ?>"></canvas>
        </article>
        <div class="reports-bottom-charts">
        <article class="admin-panel-card reports-chart-card actions-distribution-card">
            <div class="reports-card-head"><div><span>Actions</span><h2>Distribution</h2></div></div>
            <canvas id="reportsDistributionChart" data-labels="<?php echo htmlspecialchars(json_encode(array_keys($distribution))); ?>" data-values="<?php echo htmlspecialchars(json_encode(array_values($distribution))); ?>"></canvas>
        </article>
    
    <article class="admin-panel-card reports-heatmap-card activity-heatmap-card">
        <div class="reports-card-head"><div><span>Heatmap</span><h2>Activity by Day & Time</h2></div></div>
        <div class="reports-heatmap">
            <div class="reports-heat-head"><span></span><span>12 AM</span><span>4 AM</span><span>8 AM</span><span>12 PM</span><span>4 PM</span><span>8 PM</span></div>
            <?php foreach ($heatmap as $day => $slots): ?>
                <div class="reports-heat-row"><strong><?php echo htmlspecialchars($day); ?></strong><?php foreach ($slots as $hour => $count): ?><?php $label = date("g A", mktime((int) $hour, 0)); ?><span class="level-<?php echo min(4, (int) $count); ?>" data-report-tip="<?php echo htmlspecialchars($day . " • " . $label . " • " . (int) $count . " actions"); ?>"></span><?php endforeach; ?></div>
            <?php endforeach; ?>
            <div class="reports-heat-legend"><span>Low</span><i class="level-0"></i><i class="level-1"></i><i class="level-2"></i><i class="level-4"></i><span>High</span></div>
        </div>
    </article>
        </div>
    </section>

    <section class="admin-table-card reports-table-card">
        <div class="reports-details-head">
            <div>
                <span>Reports Details</span>
                <h2>Activity Details</h2>
            </div>
            <strong><?php echo (int) $displayedReports; ?> shown</strong>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table reports-table">
                <thead><tr><th>Action</th><th>Description / What changed</th><th>Admin</th><th>Affected Item</th><th>Date & Time</th><th>IP Address</th></tr></thead>
                <tbody>
                <?php if (count($reportRows) > 0): ?>
                    <?php foreach ($reportRows as $row): ?>
                        <tr>
                            <td><span class="reports-action-badge"><?php echo htmlspecialchars($row["action_type"]); ?></span></td>
                            <td><?php echo htmlspecialchars($row["description"]); ?></td>
                            <td><?php echo htmlspecialchars(trim($row["admin_name"] . " / " . $row["admin_email"], " /")); ?></td>
                            <td><?php echo htmlspecialchars(trim($row["item_name"] . " #" . $row["item_id"], " #")); ?></td>
                            <td><?php echo htmlspecialchars($row["created_at"]); ?></td>
                            <td><?php echo htmlspecialchars($row["ip_address"]); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6">No report details found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php include("includes/admin_footer.php"); ?>
