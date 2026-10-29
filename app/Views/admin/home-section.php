<?php
/**
 * হোমের নতুন সেকশন সম্পাদনা: ছোট শিরোনাম ও তার লোগো, বড় শিরোনাম, শিরোনামের জায়গা (বাঁয়ে / মাঝে / ডানে), পূর্ণ এডিটরে
 * খোলা লেখা (ছবি, টেবিল…), আর ঐচ্ছিক বাটন (লেখা, লিংক, জায়গা)। লাইভ পেইজ এডিটের সেকশন-পট্টির "সম্পাদনা" থেকে আসে।
 *
 * @var string $id
 * @var array{badge:string,heading:string,align:string,icon:string,pill:string,bg:string,button_text:string,button_url:string,button_align:string,button_style:string} $data
 * @var string $html
 */

use App\Models\HomeSections;
use App\Models\SiteBoxes;

$radios = static function (string $name, string $current): string {
    $out = '';
    foreach (HomeSections::ALIGNS as $value => $label) {
        $out .= '<label class="adm-radio"><input type="radio" name="' . e($name) . '" value="' . e($value) . '"' . ($current === $value ? ' checked' : '') . '> ' . e($label) . '</label>';
    }

    return $out;
};
/** @param array<string,string> $options মান => নাম */
$select = static function (string $name, string $id, array $options, string $current): string {
    $out = '<select id="' . e($id) . '" name="' . e($name) . '">';
    foreach ($options as $value => $label) {
        $out .= '<option value="' . e((string) $value) . '"' . ((string) $value === $current ? ' selected' : '') . '>' . e($label) . '</option>';
    }

    return $out . '</select>';
};
?>
<p><a class="adm-link" href="<?= e(admin_base() . '/studio?p=home') ?>">← লাইভ পেইজ এডিটে হোমে ফিরুন (সংরক্ষণ ছাড়া)</a></p>

<form class="adm-editor" action="<?= e(admin_base() . '/home-section/' . $id) ?>" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="adm-box">
        <h2 class="adm-card__title">ওপরের শিরোনাম</h2>
        <div class="adm-formgrid">
            <label for="hs-badge">ছোট শিরোনাম (লোগোর পাশে)</label>
            <input id="hs-badge" type="text" name="badge" maxlength="80" value="<?= e($data['badge']) ?>" placeholder="খালি রাখলে লোগোসহ ছোট শিরোনাম দেখায় না">

            <span>ছোট শিরোনামের লোগো</span>
            <div>
                <p class="adm-commonrow"><img class="adm-hs-icon" src="<?= e(HomeSections::iconUrl($data)) ?>" alt="এখনকার লোগো" width="50" height="32">
                    <?= $data['icon'] !== '' ? 'আপনার তোলা লোগো' : 'সাধারণ লোগো (অন্য সেকশনের মতো)' ?></p>
                <input type="file" name="icon" accept="image/png,image/jpeg,image/webp" aria-label="নতুন লোগো">
                <p class="adm-hint">স্বচ্ছ পটভূমির PNG ভালো, প্রায় ৫০×৩২ পিক্সেল, সর্বোচ্চ ২ MB।</p>
                <?php if ($data['icon'] !== ''): ?>
                    <label class="adm-radio"><input type="checkbox" name="icon_reset" value="1"> সাধারণ লোগোয় ফিরুন</label>
                <?php endif; ?>
            </div>

            <label for="hs-heading">বড় শিরোনাম <span class="adm-red">*</span></label>
            <input id="hs-heading" type="text" name="heading" maxlength="200" required value="<?= e($data['heading']) ?>">

            <span>শিরোনামের জায়গা</span>
            <div><?= $radios('align', $data['align']) ?></div>

            <label for="hs-pill">ছোট শিরোনামের ক্যাপসুলের রং</label>
            <?= $select('pill', 'hs-pill', array_map(static fn (array $c): string => $c[0], SiteBoxes::COLORS), $data['pill']) ?>

            <label for="hs-bg">সেকশনের পটভূমির রং</label>
            <?= $select('bg', 'hs-bg', HomeSections::BACKGROUNDS, $data['bg']) ?>
        </div>
    </div>

    <div class="adm-box">
        <h2 class="adm-card__title">সেকশনের লেখা (পূর্ণ এডিটর)</h2>
        <p class="adm-hint">লেখা, ছবি (টেনে ছেড়ে দিন), টেবিল, তালিকা, রং, অক্ষরের মাপ — সব টুল ওপরের সারিতে। সংরক্ষণের সময় নিরাপত্তার জন্য স্ক্রিপ্ট ও অচেনা জিনিস বাদ যায়।</p>
        <label class="sr-only" for="rich-editor">সেকশনের লেখা</label>
        <textarea id="rich-editor" name="html" rows="24"
                  data-upload="<?= e(admin_base()) ?>/studio/image"
                  data-content-css="<?= e(asset('css/editor-content.css')) ?>"><?= e($html) ?></textarea>
    </div>

    <div class="adm-box">
        <h2 class="adm-card__title">নিচের বাটন (ঐচ্ছিক)</h2>
        <div class="adm-formgrid">
            <label for="hs-btext">বাটনের লেখা</label>
            <input id="hs-btext" type="text" name="button_text" maxlength="60" value="<?= e($data['button_text']) ?>" placeholder="খালি রাখলে বাটন দেখায় না">
            <label for="hs-burl">বাটনের লিংক</label>
            <input id="hs-burl" type="text" name="button_url" maxlength="300" value="<?= e($data['button_url']) ?>" placeholder="যেমন /services বা /page/eid-offer বা https://…">
            <span>বাটনের জায়গা</span>
            <div><?= $radios('button_align', $data['button_align']) ?></div>
            <label for="hs-bstyle">বাটনের রং</label>
            <?= $select('button_style', 'hs-bstyle', HomeSections::BUTTONS, $data['button_style']) ?>
        </div>
    </div>

    <p class="adm-commonrow"><button class="adm-btn" type="submit">সংরক্ষণ করুন</button></p>
</form>

<script src="<?= e(asset('vendor/tinymce/tinymce.min.js')) ?>"></script>
<script src="<?= e(asset('js/admin-editor.js')) ?>"></script>
