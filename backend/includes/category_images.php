<?php
function zafiroCategorySlug($slug) {
    $slug = strtolower(trim((string) $slug));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    $aliases = [
        "sofas" => "sofa",
        "dinning" => "dining",
        "dinning-sets" => "dining-sets",
        "dinning-chair" => "dining-chair",
        "dinning-table" => "dining-table",
        "decor" => "decor-furnishing",
        "decor-furnishings" => "decor-furnishing",
        "study" => "study-office",
        "office" => "study-office",
        "kitchen-organiser" => "kitchen-organisers",
        "room-divider" => "room-dividers"
    ];
    return $aliases[$slug] ?? $slug;
}

function zafiroCategoryImageFallback($slug) {
    $slug = zafiroCategorySlug($slug);
    $map = [
        "sofa" => "../Categories furniture/sofas/sofas/sofas/sofas-01.jpg",
        "sofas" => "../Categories furniture/sofas/sofas/sofas/sofas-01.jpg",
        "living" => "../Categories furniture/sofas/sofas/sofas/sofas-01.jpg",
        "bedroom" => "../Categories furniture/Beds/beds-01.jpg",
        "beds" => "../Categories furniture/Beds/beds-01.jpg",
        "mattress" => "../Categories furniture/mattress & pillow/matresses-pillow-01.jpg",
        "mattress-accessories" => "../Categories furniture/mattress accessories/mattress accessories/mattress accessories/mattress-accessories-01.jpg",
        "bed-with-mattress" => "../Categories furniture/bed with matresses/bed-with-matresses-01.jpg",
        "bedroom-table" => "../Categories furniture/bedroom table/bedroom-table-01.jpg",
        "dining" => "../Categories furniture/dinning sets/dining-sets-01.jpg",
        "dining-sets" => "../Categories furniture/dinning sets/dining-sets-01.jpg",
        "dining-chair" => "../Categories furniture/dinning chair%F0%9F%92%BA/dining-chair-01.jpg",
        "dining-table" => "../Categories furniture/dinning table/dining-table-01.jpg",
        "modular-kitchen" => "../Categories furniture/Modular kitchen/modular-kitchen-01.jpg",
        "kitchen-storage" => "../Categories furniture/kitchen storage/kitchen storage/kitchen storage/kitchen-storage-01.jpg",
        "kitchen-organisers" => "../Categories furniture/kitchen organiser/kitchen-storage-organise-20.jpg",
        "mirrors" => "../Categories furniture/Mirrors/mirrors-01.jpg",
        "bathroom-accessories" => "../Categories furniture/Bathroom accessories/bathroom-accessories-01.jpg",
        "decor-furnishing" => "../Categories furniture/Wall decor/wall-decor-01.jpg",
        "wall-decor" => "../Categories furniture/Wall decor/wall-decor-01.jpg",
        "lamps" => "../Categories furniture/Lamps/lamps-01.jpg",
        "lights" => "../Categories furniture/Lights/lights-01.jpg",
        "clocks" => "../Categories furniture/clocks%F0%9F%95%9C/clocks-01.jpg",
        "study" => "../Categories furniture/study and office tables/study-and-office-tables-22.jpg",
        "study-office" => "../Categories furniture/study and office tables/study-and-office-tables-22.jpg",
        "study-table" => "../Categories furniture/study and office tables/study-and-office-tables-22.jpg",
        "study-office-chair" => "../Categories furniture/study chair/study-chair-01.jpg",
        "study-storage" => "../Categories furniture/study storage/study-storage-01.jpg",
        "outdoor" => "../Categories furniture/outdoor furniture/outdoor-furniture-01.jpg",
        "outdoor-furniture" => "../Categories furniture/outdoor furniture/outdoor-furniture-01.jpg",
        "storage" => "../Categories furniture/living storage unit/living-storage-01.jpg",
        "living-storage" => "../Categories furniture/living storage unit/living-storage-01.jpg",
        "bedroom-storage" => "../Categories furniture/bedroom storage/bedroom-storage-01.jpg",
        "bar-furniture" => "../Categories furniture/bar furniture/bar-furniture-01.jpg",
        "shoe-rack" => "../Categories furniture/shoe rack/shoe rack/shoe rack/shoe-rack-01.jpg",
        "tv-unit" => "../Categories furniture/tv unit/tv-unit-01.jpg",
        "wardrobe" => "../Categories furniture/wardrobe/wardrobe-01.jpg",
        "table" => "../Categories furniture/table/table/table/table-01.jpg",
        "table-decor" => "../Categories furniture/Table decor/table decor/table decor/table-decor-01.jpg",
        "room-dividers" => "../Categories furniture/room divider/room-dividers-01.jpg",
        "seating" => "../Categories furniture/seating/seating/seating/seating-01.jpg",
        "chair" => "../Categories furniture/chair/chairs%20%F0%9F%AA%91/chair%20%F0%9F%AA%91/chair-01.jpg",
        "chairs" => "../Categories furniture/chair/chairs%20%F0%9F%AA%91/chair%20%F0%9F%AA%91/chair-01.jpg",
        "sofa-cum-bed" => "../Categories furniture/sofa cum bed/sofa cum bed/sofa cum bed/sofa-cum-bed-01.jpg",
        "cover" => "../Categories furniture/cover/cover-01.jpg",
        "balcony-furniture" => "../Categories furniture/Balcony furniture/balcony-furniture-01.jpg",
    ];
    return $map[$slug] ?? "../uploads/placeholder-product.jpg";
}

function zafiroCategoryUrl($slug) {
    return "product-list.php?category=" . urlencode(zafiroCategorySlug($slug));
}
?>
