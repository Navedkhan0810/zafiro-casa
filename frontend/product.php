<?php
$productId = (int) ($_GET['id'] ?? 0);
header("Location: product-view.php?id=" . $productId);
exit;
?>
