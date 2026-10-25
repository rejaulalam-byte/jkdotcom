<?php
/**
 * পপ-আপ বিজ্ঞাপন (App\Models\PopupAd): এই পেইজ বাছা থাকলে মেনুর ঠিক নিচে মাঝখানে নিজের মাপে দেখায়।
 * JavaScript ছাড়া লুকানো থাকে (বন্ধ করার উপায় নেই বলে); app.js দেখায়, (×) বা ২০ সেকেন্ড পর সরিয়ে দেয়।
 */
use App\Models\PopupAd;

$popupAd = PopupAd::forCurrentPage();
if ($popupAd === null) {
    return;
}
$popupImg = '<img src="' . e($popupAd['image']) . '" alt="' . e($popupAd['title']) . '" width="' . (int) $popupAd['width'] . '" height="' . (int) $popupAd['height'] . '">';
?>
<div class="popup-ad" data-popup-ad data-seconds="<?= (int) PopupAd::SECONDS ?>" role="dialog" aria-label="<?= e($popupAd['title']) ?>" hidden>
    <button class="popup-ad__close" type="button" data-popup-close aria-label="বিজ্ঞাপন বন্ধ করুন">×</button>
    <?php if ($popupAd['link'] !== null): ?>
        <a href="<?= e($popupAd['link']) ?>" target="_blank" rel="noopener noreferrer nofollow sponsored"><?= $popupImg ?></a>
    <?php else: ?>
        <?= $popupImg ?>
    <?php endif; ?>
</div>
