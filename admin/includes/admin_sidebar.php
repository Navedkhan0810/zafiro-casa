<aside class="admin-sidebar" id="adminSidebar">
    <div class="admin-brand">
        <strong>Zafiro Casa</strong>
        <span>Admin Panel</span>
    </div>
    <nav class="admin-menu">
        <a class="<?php echo $currentAdminPage === 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">Dashboard</a>
        <a class="<?php echo $currentAdminPage === 'manage_products.php' ? 'active' : ''; ?>" href="manage_products.php">Manage Products</a>
        <a class="<?php echo $currentAdminPage === 'add_product.php' ? 'active' : ''; ?>" href="add_product.php">Add Product</a>
        <a class="<?php echo $currentAdminPage === 'manage_categories.php' ? 'active' : ''; ?>" href="manage_categories.php">Manage Categories</a>
        <a class="<?php echo $currentAdminPage === 'manage_subcategories.php' ? 'active' : ''; ?>" href="manage_subcategories.php">Manage Subcategories</a>
        <a class="<?php echo $currentAdminPage === 'manage_hero_slider.php' ? 'active' : ''; ?>" href="manage_hero_slider.php">Home Page Editor</a>
        <a class="<?php echo $currentAdminPage === 'manage_orders.php' ? 'active' : ''; ?>" href="manage_orders.php">Manage Orders</a>
        <a class="<?php echo $currentAdminPage === 'manage_users.php' ? 'active' : ''; ?>" href="manage_users.php">Manage Users</a>
        <a class="<?php echo $currentAdminPage === 'reviews.php' ? 'active' : ''; ?>" href="reviews.php">Reviews</a>
        <a class="<?php echo $currentAdminPage === 'manage_notifications.php' ? 'active' : ''; ?>" href="manage_notifications.php">Notifications</a>
        <a class="<?php echo $currentAdminPage === 'settings.php' ? 'active' : ''; ?>" href="settings.php">Settings</a>
        <a class="logout-link" href="logout.php">Logout</a>
    </nav>
</aside>
