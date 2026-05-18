<aside class="admin-sidebar" id="adminSidebar">
    <div class="admin-brand">
        <strong>Zafiro Casa</strong>
        <span>Admin Panel</span>
    </div>
    <nav class="admin-menu">
        <a class="<?php echo $currentAdminPage === 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php"><i class="fa-solid fa-gauge-high"></i><span>Dashboard</span></a>
        <a class="<?php echo $currentAdminPage === 'manage_products.php' ? 'active' : ''; ?>" href="manage_products.php"><i class="fa-solid fa-boxes-stacked"></i><span>Manage Products</span></a>
        <a class="<?php echo $currentAdminPage === 'add_product.php' ? 'active' : ''; ?>" href="add_product.php"><i class="fa-solid fa-square-plus"></i><span>Add Product</span></a>
        <a class="<?php echo $currentAdminPage === 'manage_categories.php' ? 'active' : ''; ?>" href="manage_categories.php"><i class="fa-solid fa-layer-group"></i><span>Manage Categories</span></a>
        <a class="<?php echo $currentAdminPage === 'manage_subcategories.php' ? 'active' : ''; ?>" href="manage_subcategories.php"><i class="fa-solid fa-sitemap"></i><span>Manage Subcategories</span></a>
        <a class="<?php echo $currentAdminPage === 'manage_hero_slider.php' ? 'active' : ''; ?>" href="manage_hero_slider.php"><i class="fa-solid fa-house-chimney-window"></i><span>Home Page Editor</span></a>
        <a class="<?php echo $currentAdminPage === 'manage_orders.php' ? 'active' : ''; ?>" href="manage_orders.php"><i class="fa-solid fa-cart-shopping"></i><span>Manage Orders</span></a>
        <a class="<?php echo $currentAdminPage === 'manage_users.php' ? 'active' : ''; ?>" href="manage_users.php"><i class="fa-solid fa-users"></i><span>Manage Users</span></a>
        <a class="<?php echo $currentAdminPage === 'reviews.php' ? 'active' : ''; ?>" href="reviews.php"><i class="fa-solid fa-star-half-stroke"></i><span>Reviews</span></a>
        <a class="<?php echo $currentAdminPage === 'manage_notifications.php' ? 'active' : ''; ?>" href="manage_notifications.php"><i class="fa-solid fa-bell"></i><span>Notifications</span></a>
        <a class="<?php echo $currentAdminPage === 'reports.php' ? 'active' : ''; ?>" href="reports.php"><i class="fa-solid fa-file-lines"></i><span>Reports</span></a>
        <a class="<?php echo $currentAdminPage === 'settings.php' ? 'active' : ''; ?>" href="settings.php"><i class="fa-solid fa-gear"></i><span>Settings</span></a>
        <a class="logout-link" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a>
    </nav>
</aside>
