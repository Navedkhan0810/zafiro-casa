<?php
if (!function_exists('homepageContentDefaults')) {
    function homepageContentDefaults(): array {
        return [
            'home_decor' => ['title' => 'Home Decor', 'subtitle' => 'Discover every detail that matters', 'button_text' => 'View All', 'button_link' => 'decor-furnishing.php'],
            'featured_categories' => ['title' => 'Featured Categories', 'subtitle' => 'Curated For You', 'button_text' => '', 'button_link' => ''],
            'style_gallery' => ['title' => 'Style Gallery', 'subtitle' => 'Luxury Details', 'button_text' => '', 'button_link' => '']
        ];
    }
}

if (!function_exists('homepageContentSeedItems')) {
    function homepageContentSeedItems(): array {
        $categoryImage = function ($slug) {
            return function_exists('zafiroCategoryImageFallback') ? zafiroCategoryImageFallback($slug) : '';
        };
        $categoryUrl = function ($slug) {
            return function_exists('zafiroCategoryUrl') ? zafiroCategoryUrl($slug) : 'product-list.php?category=' . urlencode($slug);
        };

        return [
            'featured_categories' => [
                ['Sofa', '', '', '', $categoryUrl('sofa'), $categoryImage('sofa'), 1],
                ['Beds', '', '', '', $categoryUrl('beds'), $categoryImage('beds'), 2],
                ['Table', '', '', '', $categoryUrl('table'), $categoryImage('table'), 3],
                ['Chair', '', '', '', $categoryUrl('chair'), $categoryImage('chair'), 4],
                ['Luxury Tables', '', '', '', $categoryUrl('table'), $categoryImage('table'), 5],
                ['Premium Chairs', '', '', '', $categoryUrl('chair'), $categoryImage('chair'), 6]
            ],
            'style_gallery' => [
                ['Mirrors', '', '', '', $categoryUrl('mirrors'), $categoryImage('mirrors'), 1],
                ['Bathroom Accessories', '', '', '', $categoryUrl('bathroom-accessories'), $categoryImage('bathroom-accessories'), 2],
                ['Bedroom', '', '', '', $categoryUrl('bedroom'), $categoryImage('bedroom'), 3],
                ['Decor & Furnishing', '', '', '', $categoryUrl('decor-furnishing'), $categoryImage('decor-furnishing'), 4],
                ['Study', '', '', '', $categoryUrl('study-office'), $categoryImage('study-office'), 5],
                ['Modular Kitchen', '', '', '', $categoryUrl('modular-kitchen'), $categoryImage('modular-kitchen'), 6],
                ['Dining', '', '', '', $categoryUrl('dining'), $categoryImage('dining'), 7],
                ['Living', '', '', '', $categoryUrl('living'), $categoryImage('living'), 8]
            ]
        ];
    }
}

if (!function_exists('ensureHomepageContentTables')) {
    function ensureHomepageContentTables(mysqli $conn): void {
        $conn->query("CREATE TABLE IF NOT EXISTS homepage_content_sections (
            section_key VARCHAR(80) PRIMARY KEY,
            title VARCHAR(160) NOT NULL,
            subtitle VARCHAR(255) DEFAULT '',
            button_text VARCHAR(80) DEFAULT '',
            button_link VARCHAR(255) DEFAULT '',
            status VARCHAR(20) DEFAULT 'active',
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->query("CREATE TABLE IF NOT EXISTS homepage_content_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            section_key VARCHAR(80) NOT NULL,
            title VARCHAR(180) NOT NULL,
            subtitle VARCHAR(255) DEFAULT '',
            price_text VARCHAR(80) DEFAULT '',
            button_text VARCHAR(80) DEFAULT '',
            button_link VARCHAR(255) DEFAULT '',
            image_path VARCHAR(255) DEFAULT '',
            sort_order INT DEFAULT 0,
            status VARCHAR(20) DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_homepage_content_section (section_key, status, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        foreach (homepageContentDefaults() as $key => $section) {
            $stmt = $conn->prepare("INSERT IGNORE INTO homepage_content_sections (section_key, title, subtitle, button_text, button_link, status) VALUES (?, ?, ?, ?, ?, 'active')");
            $stmt->bind_param('sssss', $key, $section['title'], $section['subtitle'], $section['button_text'], $section['button_link']);
            $stmt->execute();
        }

        foreach (homepageContentSeedItems() as $sectionKey => $items) {
            $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM homepage_content_items WHERE section_key = ?");
            $countStmt->bind_param('s', $sectionKey);
            $countStmt->execute();
            $count = (int) ($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
            if ($count > 0) continue;

            $insert = $conn->prepare("INSERT INTO homepage_content_items (section_key, title, subtitle, price_text, button_text, button_link, image_path, sort_order, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
            foreach ($items as $item) {
                [$title, $subtitle, $priceText, $buttonText, $buttonLink, $imagePath, $sortOrder] = $item;
                $insert->bind_param('sssssssi', $sectionKey, $title, $subtitle, $priceText, $buttonText, $buttonLink, $imagePath, $sortOrder);
                $insert->execute();
            }
        }
    }
}

if (!function_exists('homepageContentSection')) {
    function homepageContentSection(mysqli $conn, string $sectionKey): array {
        $defaults = homepageContentDefaults();
        $fallback = $defaults[$sectionKey] ?? ['title' => '', 'subtitle' => '', 'button_text' => '', 'button_link' => ''];
        $stmt = $conn->prepare("SELECT * FROM homepage_content_sections WHERE section_key = ? LIMIT 1");
        $stmt->bind_param('s', $sectionKey);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: $fallback;
    }
}

if (!function_exists('homepageContentItems')) {
    function homepageContentItems(mysqli $conn, string $sectionKey, int $limit = 0): array {
        $sql = "SELECT * FROM homepage_content_items WHERE section_key = ? AND status = 'active' ORDER BY sort_order ASC, id ASC";
        if ($limit > 0) $sql .= " LIMIT " . (int) $limit;
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $sectionKey);
        $stmt->execute();
        $items = [];
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $items[] = $row;
        return $items;
    }
}
?>