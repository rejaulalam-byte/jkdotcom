<?php
/**
 * রং ও আকার: থিমের চার মূল রং (App\Models\SiteTheme)। প্রতিটির রং-ঘর, শুরুর রং, কোথায় লাগে; ডানে সাইটের মতো ছোট নমুনা
 * (admin.js "থিমের নমুনা" রং বাছার সঙ্গে সঙ্গে বদলায়)। পড়া কঠিন হতে পারে এমন রঙে সতর্কতা।
 *
 * @var array<string,string> $colors   নাম => বর্তমান #rrggbb
 * @var list<string> $warnings
 */

use App\Models\SiteTheme;

$base = admin_base() . '/theme';
?>
<?php if ($warnings !== []): ?>
    <div class="adm-box adm-warn">
        <h2 class="adm-card__title">সতর্কতা: পড়তে অসুবিধা হতে পারে</h2>
        <ul><?php foreach ($warnings as $w): ?><li><?= e($w) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="adm-theme">
    <form class="adm-box" action="<?= e($base) ?>" method="post" data-theme-form>
        <?= csrf_field() ?>
        <h2 class="adm-card__title">সাইটের মূল রং</h2>
        <p class="adm-muted">একটা রং বদলালে পুরো সাইটে যেখানে যেখানে সেটা আছে সব বদলায় (কার্ডের হালকা রংও প্রধান রং থেকে আসে)। সংরক্ষণের পরে লাইভ পেইজ এডিটে বা সাইটে দেখে নিন; পছন্দ না হলে "শুরুর রঙে ফিরুন"।</p>
        <?php foreach (SiteTheme::COLORS as $name => [$label, $default, $where]): ?>
            <div class="adm-theme__row">
                <label class="adm-theme__label" for="tc-<?= e($name) ?>"><b><?= e($label) ?></b></label>
                <div class="adm-theme__pick">
                    <input id="tc-<?= e($name) ?>" type="color" name="color[<?= e($name) ?>]" value="<?= e($colors[$name]) ?>" data-theme-color="<?= e($name) ?>">
                    <input type="text" value="<?= e($colors[$name]) ?>" maxlength="7" pattern="#[0-9a-fA-F]{6}" aria-label="<?= e($label) ?> — রঙের কোড" data-theme-hex="<?= e($name) ?>">
                    <span class="adm-theme__orig">শুরুর রং: <span class="adm-theme__swatch" data-swatch="<?= e($default) ?>"></span> <?= e($default) ?></span>
                    <?php if ($colors[$name] !== $default): ?>
                        <button class="adm-small" type="submit" name="reset" value="<?= e($name) ?>" formnovalidate>শুরুর রঙে ফিরুন</button>
                    <?php endif; ?>
                </div>
                <p class="adm-hint"><?= e($where) ?></p>
            </div>
        <?php endforeach; ?>
        <p class="adm-commonrow">
            <button class="adm-btn" type="submit">সংরক্ষণ করুন</button>
            <button class="adm-small adm-small--warn" type="submit" name="reset_all" value="1" formnovalidate>সব রং শুরুতে ফিরুন</button>
            <a class="adm-link" href="<?= e(admin_base() . '/studio?p=home') ?>">লাইভ পেইজ এডিটে দেখুন</a>
        </p>
    </form>

    <aside class="adm-box adm-theme__preview" aria-label="নমুনা" data-theme-preview>
        <h2 class="adm-card__title">নমুনা (সংরক্ষণের আগে)</h2>
        <div class="tp-banner">
            <p class="tp-brand">Jorurikaj.com</p>
            <p class="tp-title">পেইজের ব্যানারের শিরোনাম</p>
            <p class="tp-crumb">হোম / পেইজ</p>
        </div>
        <div class="tp-body">
            <p class="tp-menu"><span>হোম</span> <span class="tp-menu__hover">সেবা সমূহ</span> <span>যোগাযোগ</span></p>
            <p class="tp-text">সাধারণ লেখা এমন দেখাবে। Jorurikaj.com-এ দক্ষ লোক খুঁজুন।</p>
            <div class="tp-card">হালকা টিলের কার্ড</div>
            <p><span class="tp-btn">টিল বাটন</span> <span class="tp-btn tp-btn--soft">হালকা বাটন</span></p>
        </div>
    </aside>
</div>
