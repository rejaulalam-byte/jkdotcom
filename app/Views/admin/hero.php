<?php
/**
 * হিরো সেটিং: লেখার অ্যানিমেশন (তিন ধরন), পটভূমি (সাধারণ / নিজের বাছা রং / ছবি), ডানের "আজকের কাজের তালিকা" দেখানো-লুকানো।
 * রং বাছতে ব্রাউজারের পুরো রঙের প্লেট (type="color") আর সরাসরি কোডের ঘর; নিচে সঙ্গে সঙ্গে নমুনা (admin.js "হিরোর পটভূমি")।
 *
 * @var string $anim
 * @var string $mode       এখন যা চালু (default|color|image)
 * @var string $bgSaved    সংরক্ষিত বাছাই (রং/ছবি না থাকলেও যা বাছা ছিল)
 * @var string $color1
 * @var string $color2     '' = একটাই রং
 * @var string $direction
 * @var string $image      /page-image/<hex> বা ''
 * @var int $overlay
 * @var bool $showToday
 */

use App\Models\SiteHero;

$bg = in_array($bgSaved, ['default', 'color', 'image'], true) ? $bgSaved : 'default';
?>
<p><a class="adm-link" href="<?= e(admin_base() . '/studio?p=home') ?>">← লাইভ পেইজ এডিটে হোমে ফিরুন</a></p>

<form class="adm-editor" action="<?= e(admin_base() . '/hero') ?>" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="adm-box">
        <h2 class="adm-card__title">১. লেখার অ্যানিমেশন</h2>
        <p class="adm-muted">হোম খুললে হিরোর শিরোনাম ও লেখা যেভাবে আসবে। যেকোনো একটা বাছুন।</p>
        <?php foreach (SiteHero::ANIMS as $key => $label): ?>
            <label class="adm-radio"><input type="radio" name="anim" value="<?= e($key) ?>"<?= $anim === $key ? ' checked' : '' ?>> <?= e($label) ?></label>
        <?php endforeach; ?>
    </div>

    <div class="adm-box" data-hero-bg>
        <h2 class="adm-card__title">২. পটভূমি</h2>
        <p class="adm-muted">হিরোর পেছনে কী থাকবে — যেকোনো একটা বাছুন।</p>

        <label class="adm-radio"><input type="radio" name="bg" value="default"<?= $bg === 'default' ? ' checked' : '' ?>> সাধারণ (সাইটের প্রধান রং, যেমন এখন আছে)</label>

        <label class="adm-radio"><input type="radio" name="bg" value="color"<?= $bg === 'color' ? ' checked' : '' ?>> নিজের পছন্দের রং</label>
        <div class="adm-hero-color">
            <div class="adm-theme__pick">
                <input type="color" name="color1" value="<?= e($color1) ?>" data-hero-color="1" aria-label="প্রথম রং">
                <input type="text" value="<?= e($color1) ?>" maxlength="7" pattern="#[0-9a-fA-F]{6}" aria-label="প্রথম রঙের কোড" data-hero-hex="1">
            </div>
            <label class="adm-radio"><input type="checkbox" name="use_color2" value="1"<?= $color2 !== '' ? ' checked' : '' ?> data-hero-use2> দ্বিতীয় একটা রংও মেশান (দুই রঙের গ্র্যাডিয়েন্ট)</label>
            <div class="adm-theme__pick">
                <input type="color" name="color2" value="<?= e($color2 !== '' ? $color2 : '#0d4250') ?>" data-hero-color="2" aria-label="দ্বিতীয় রং">
                <input type="text" value="<?= e($color2 !== '' ? $color2 : '#0d4250') ?>" maxlength="7" pattern="#[0-9a-fA-F]{6}" aria-label="দ্বিতীয় রঙের কোড" data-hero-hex="2">
                <label>দিক
                    <select name="dir" data-hero-dir>
                        <?php foreach (SiteHero::DIRECTIONS as $key => [$label]): ?>
                            <option value="<?= e($key) ?>"<?= $direction === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <p class="adm-hint">রঙের চারকোনায় ক্লিক করলে রঙের পুরো প্লেট খোলে, যেকোনো রং বাছা যায়। হালকা রং বাছলে হিরোর লেখা নিজে গাঢ় হয়ে যায়।</p>
            <div class="adm-hero-sample" data-hero-sample aria-label="নমুনা"><span>নমুনা: পটভূমি এমন দেখাবে</span></div>
        </div>

        <label class="adm-radio"><input type="radio" name="bg" value="image"<?= $bg === 'image' ? ' checked' : '' ?>> একটা ছবি</label>
        <div class="adm-hero-image">
            <?php if ($image !== ''): ?>
                <p class="adm-commonrow"><img class="adm-hero-thumb" src="<?= e($image) ?>" alt="এখনকার হিরোর ছবি" width="240" height="126"></p>
            <?php endif; ?>
            <label class="sr-only" for="hero-image">হিরোর ছবি বাছুন</label>
            <input id="hero-image" type="file" name="image" accept="image/png,image/jpeg,image/webp">
            <p class="adm-hint"><?= e(SiteHero::IMAGE_ADVICE) ?></p>
            <?php if ($image !== ''): ?>
                <label class="adm-radio"><input type="checkbox" name="remove_image" value="1"> এই ছবি সরান</label>
            <?php endif; ?>
            <label>ছবির ওপর কালো পর্দা (লেখা পড়ার সুবিধার জন্য)
                <select name="overlay">
                    <?php foreach (SiteHero::OVERLAYS as $pct): ?>
                        <option value="<?= $pct ?>"<?= $overlay === $pct ? ' selected' : '' ?>><?= $pct === 0 ? 'পর্দা নেই' : e(bn($pct)) . '%' ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
    </div>

    <div class="adm-box">
        <h2 class="adm-card__title">৩. ডানের "আজকের কাজের তালিকা"</h2>
        <p class="adm-muted">হিরোর ডান পাশের বাক্স। লুকালে দর্শক বাক্সটা দেখবেন না, আর হিরোর লেখা পুরো প্রস্থ নেবে।</p>
        <label class="adm-radio"><input type="radio" name="today" value="1"<?= $showToday ? ' checked' : '' ?>> সবসময় দেখান</label>
        <label class="adm-radio"><input type="radio" name="today" value="0"<?= $showToday ? '' : ' checked' ?>> লুকান</label>
    </div>

    <p class="adm-commonrow"><button class="adm-btn" type="submit">সংরক্ষণ করুন</button></p>
</form>
