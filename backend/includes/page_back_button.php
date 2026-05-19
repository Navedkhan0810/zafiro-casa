<?php
$pageBackText = $pageBackText ?? "Back";
$pageBackHref = $pageBackHref ?? "javascript:void(0)";
$pageBackHistory = !empty($pageBackHistory);
?>
<div class="profile-page-back-wrap zafiro-page-back-wrap">
    <a class="profile-back-btn zafiro-back-btn zc-back-btn" href="<?php echo htmlspecialchars($pageBackHref); ?>"<?php echo $pageBackHistory ? ' data-history-back="true"' : ''; ?>>
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        <span><?php echo htmlspecialchars($pageBackText); ?></span>
    </a>
</div>
<?php unset($pageBackText, $pageBackHref, $pageBackHistory); ?>
