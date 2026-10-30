<?php
/**
 * হোমের একটা সেকশন তার জায়গায় (pages/home.php-এর ক্রমের লুপ থেকে)। সাইটের নিজের সেকশনের HTML আগেই $blocks-এ ধরা;
 * নতুন সেকশন partials/home-extra.php দিয়ে আঁকা। লাইভ পেইজ এডিটের ফ্রেমে সুপার অ্যাডমিনের জন্য ওপরে নিয়ন্ত্রণ-পট্টি
 * (live-edit.js "হোমের সেকশন"), আর লুকানো সেকশন ঝাপসা হয়ে দেখায়; সাধারণ দর্শক লুকানো সেকশন পান না।
 *
 * @var array{id:string,hidden:bool,builtin:bool} $section
 * @var array<string,string> $blocks
 */

use App\Core\AdminAuth;
use App\Models\HomeSections;

$slotAdmin = live_preview() ? AdminAuth::current() : null;
$slotSuper = $slotAdmin !== null && AdminAuth::isSuper($slotAdmin);
if ($section['hidden'] && !$slotSuper) {
    return;
}
$sectionId = $section['id'];
$sectionHtml = static function () use ($section, $blocks, $sectionId): void {
    if ($section['builtin']) {
        echo $blocks[$sectionId] ?? '';
    } else {
        require BASE_PATH . '/app/Views/partials/home-extra.php';
    }
};
$isHero = $sectionId === 'hero';
if (!$slotSuper) {
    // মেইন/সাব-অ্যাডমিন: কাঠামোর বাটন নেই, শুধু এই সেকশনের লেখা-ছবি বদলানোর পাতায় যাওয়ার বাটন
    $editUrl = null;
    $editLabel = '';
    if ($slotAdmin !== null && $isHero && AdminAuth::can($slotAdmin, 'hero')) {
        $editUrl = admin_base() . '/hero';
        $editLabel = '✎ হিরো সেটিং (অ্যানিমেশন, পটভূমি, তালিকা)';
    } elseif ($slotAdmin !== null && !$section['builtin'] && AdminAuth::can($slotAdmin, 'pages')) {
        $editUrl = admin_base() . '/home-section/' . $sectionId;
        $editLabel = '✎ সম্পাদনা';
    }
    if ($editUrl === null) {
        $sectionHtml();
        return;
    }
    ?>
    <div class="jk-home-slot" data-jk-home="<?= e($sectionId) ?>">
        <div class="jk-home-bar" role="toolbar" aria-label="সেকশনের লেখা">
            <span class="jk-home-bar__name"><?= e(HomeSections::label($sectionId)) ?></span>
            <button type="button" data-act="edit" data-url="<?= e($editUrl) ?>"><?= e($editLabel) ?></button>
        </div>
        <?php $sectionHtml(); ?>
    </div>
    <?php
    return;
}
?>
<div class="jk-home-slot<?= $section['hidden'] ? ' is-jk-hidden' : '' ?>" data-jk-home="<?= e($sectionId) ?>">
    <div class="jk-home-bar" role="toolbar" aria-label="সেকশন সাজান">
        <span class="jk-home-bar__name"><?= e(HomeSections::label($sectionId)) ?><?= $section['hidden'] ? ' — লুকানো, দর্শক দেখেন না' : '' ?></span>
        <?php if (!$isHero): ?>
            <button type="button" data-act="up" title="ওপরে সরান">▲</button>
            <button type="button" data-act="down" title="নিচে সরান">▼</button>
        <?php endif; ?>
        <button type="button" data-act="<?= $section['hidden'] ? 'show' : 'hide' ?>"><?= $section['hidden'] ? 'দেখান' : 'লুকান' ?></button>
        <?php if (!$isHero): ?>
            <button type="button" data-act="add-above">＋ ওপরে নতুন সেকশন</button>
        <?php endif; ?>
        <button type="button" data-act="add-below">＋ নিচে নতুন সেকশন</button>
        <?php if ($isHero): ?>
            <button type="button" data-act="edit" data-url="<?= e(admin_base() . '/hero') ?>">✎ হিরো সেটিং (অ্যানিমেশন, পটভূমি, তালিকা)</button>
        <?php endif; ?>
        <?php if (!$section['builtin']): ?>
            <button type="button" data-act="edit" data-url="<?= e(admin_base() . '/home-section/' . $sectionId) ?>">✎ সম্পাদনা</button>
            <button type="button" data-act="delete" data-confirm="এই সেকশন ও তার পুরো লেখা চিরতরে মুছে যাবে। মুছবেন?">মুছুন</button>
        <?php endif; ?>
    </div>
    <?php $sectionHtml(); ?>
</div>
