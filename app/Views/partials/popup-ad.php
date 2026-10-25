<?php
/**
 * পপ-আপ বিজ্ঞাপন (App\Models\PopupAd): এই পেইজ বাছা থাকলে পুরো পর্দা হালকা ঘোলা হয়ে ঢাকে, আর ছবিটা মেনু-পট্টির ওপরের কিনারার ১০px নিচ
 * থেকে শুরু হয়ে নিচে নামে, পেইজের মাঝখানে নিজের মাপে। বন্ধ হলে তবেই হিরোর অ্যানিমেশন শুরু হয় (app.js)।
 * JavaScript ছাড়া লুকানো থাকে (বন্ধ করার উপায় নেই বলে); app.js দেখায়, (×) / Esc / অ্যাডমিনের দেওয়া সময় পরে সরিয়ে দেয়।
 */
use App\Models\PopupAd;

$popupAd = PopupAd::forCurrentPage();
if ($popupAd === null) {
    return;
}
$popupImg = '<img src="' . e($popupAd['image']) . '" alt="' . e($popupAd['title']) . '" width="' . (int) $popupAd['width'] . '" height="' . (int) $popupAd['height'] . '">';
?>
<div class="popup-ad" data-popup-ad data-seconds="<?= (int) $popupAd['seconds'] ?>" role="dialog" aria-modal="true" aria-label="<?= e($popupAd['title']) ?>" hidden>
    <div class="popup-ad__box">
        <button class="popup-ad__close" type="button" data-popup-close aria-label="বিজ্ঞাপন বন্ধ করুন">×</button>
        <?php if ($popupAd['link'] !== null): ?>
            <a href="<?= e($popupAd['link']) ?>" target="_blank" rel="noopener noreferrer nofollow sponsored"><?= $popupImg ?></a>
        <?php else: ?>
            <?= $popupImg ?>
        <?php endif; ?>
    </div>
</div>
