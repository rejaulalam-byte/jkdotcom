<?php
/**
 * "লাইভ পেইজ এডিট"-এর "মেনু সম্পাদনা" বাক্স: ওপরের মেনু (সাব-মেনুসহ; শুধু সুপার অ্যাডমিন), ফুটারের লিংকের কলাম ও
 * সামাজিক মাধ্যম (ক্রম, ঠিকানা, আইকন; অনুমতি "footer"), এক ফর্মে। সারি যোগ / মোছা / ওপরে-নিচে admin.js করে ("মেনু সম্পাদনা" অংশ);
 * জমার আগে সব ঘরের নাম পর্দার ক্রমে নতুন করে নম্বর পায়। JavaScript ছাড়া প্রতিটি তালিকার শেষে একটা খালি সারি থাকে।
 *
 * @var string $base  /<admin>/studio
 * @var string $here  ফেরার ঠিকানা
 * @var bool $menuHeader ওপরের মেনুর অংশ দেখাবে কি না
 */
use App\Models\SiteMenus;

$header = SiteMenus::header();
$footer = SiteMenus::footer();
$hideEmpty = \App\Core\Content::get('settings.social_hide_empty', '0') === '1';

// একটা লিংক-সারি: নাম, লিংক, ওপরে/নিচে/মুছুন
$row = static function (string $name, string $label, string $href, string $kind): string {
    return '<div class="adm-menu__row" data-menu-row="' . e($kind) . '">'
        . '<input type="text" name="' . e($name) . '[label]" value="' . e($label) . '" maxlength="80" placeholder="নাম" aria-label="নাম">'
        . '<input type="text" name="' . e($name) . '[href]" value="' . e($href) . '" maxlength="200" placeholder="লিংক, যেমন /contact" aria-label="লিংক">'
        . '<span class="adm-menu__tools" hidden>'
        . '<button class="adm-small" type="button" data-menu-act="up" aria-label="ওপরে">▲</button>'
        . '<button class="adm-small" type="button" data-menu-act="down" aria-label="নিচে">▼</button>'
        . '<button class="adm-small adm-small--warn" type="button" data-menu-act="remove">মুছুন</button>'
        . '</span></div>';
};
// সামাজিক মাধ্যমের একটা সারি: আইকন, নাম, ঠিকানা, নতুন আইকন, ওপরে/নিচে/মুছুন
$socialRow = static function (int $i, array $item): string {
    $icon = $item['key'] !== '' ? '<img class="adm-menu__icon" src="' . e(SiteMenus::socialIcon($item)) . '" alt="" width="32" height="32">' : '<span class="adm-menu__icon"></span>';

    return '<div class="adm-menu__row adm-menu__row--social" data-menu-row="social">' . $icon
        . '<input type="hidden" data-f="key" name="social[' . $i . '][key]" value="' . e($item['key']) . '">'
        . '<input type="hidden" data-f="icon" name="social[' . $i . '][icon]" value="' . e($item['icon']) . '">'
        . '<input type="text" data-f="name" name="social[' . $i . '][name]" value="' . e($item['name']) . '" maxlength="40" placeholder="নাম (যেমন ইউটিউব)" aria-label="নাম">'
        . '<input type="url" data-f="url" name="social[' . $i . '][url]" value="' . e($item['url'] !== '#' ? $item['url'] : '') . '" maxlength="300" placeholder="https://… (খালি = লিংক নেই)" aria-label="ঠিকানা">'
        . '<input type="file" data-f="file" name="social_icon[' . $i . ']" accept="image/png,image/jpeg,image/webp" aria-label="নতুন আইকন">'
        . '<span class="adm-menu__tools" hidden>'
        . '<button class="adm-small" type="button" data-menu-act="up" aria-label="ওপরে">▲</button>'
        . '<button class="adm-small" type="button" data-menu-act="down" aria-label="নিচে">▼</button>'
        . '<button class="adm-small adm-small--warn" type="button" data-menu-act="remove">মুছুন</button>'
        . '</span></div>';
};
$social = SiteMenus::social();
?>
<dialog class="adm-rich-dialog adm-menu" id="menu-dialog" aria-label="<?= $menuHeader ? 'মেনু সম্পাদনা' : 'ফুটার ও সামাজিক মাধ্যম' ?>">
    <form id="menu-form" action="<?= e($base) ?>/menu" method="post" enctype="multipart/form-data" data-menu-form>
        <?= csrf_field() ?>
        <input type="hidden" name="back" value="<?= e($here) ?>">
        <h2 class="adm-card__title"><?= $menuHeader ? 'মেনু সম্পাদনা' : 'ফুটার ও সামাজিক মাধ্যম' ?></h2>
        <ul class="adm-rules">
            <li>লিংক: সাইটের ভেতরের পেইজ / দিয়ে শুরু (যেমন <code>/contact</code>, <code>/services/electrician</code>), বাইরের সাইট <code>https://</code> দিয়ে। নাম ও লিংক দুটোই খালি রাখা সারি বাদ যায়।</li>
            <?php if ($menuHeader): ?>
                <li>যে মেনু-আইটেমের নিচে সাব-মেনু আছে, তার নামে ক্লিক করলে পেইজ খোলে না, শুধু সাব-মেনু দেখায়; তার লিংক শুধু কোন পেইজে আইটেমটা রঙিন হবে তা চেনায়।</li>
            <?php endif; ?>
        </ul>

        <?php if ($menuHeader): ?>
        <fieldset class="adm-part">
            <legend>ওপরের মেনু (সর্বোচ্চ <?= e(bn(SiteMenus::MAX_TOP)) ?>টি, প্রতিটির নিচে সর্বোচ্চ <?= e(bn(SiteMenus::MAX_SUB)) ?>টি সাব-মেনু)</legend>
            <div class="adm-menu__list" data-menu-list="top">
                <?php foreach ($header as $i => [$href, $label, $children]): ?>
                    <div class="adm-menu__item" data-menu-item>
                        <?= $row("header[$i]", (string) $label, (string) $href, 'top') ?>
                        <div class="adm-menu__sub" data-menu-list="sub">
                            <?php foreach ($children as $j => [$sHref, $sLabel]): ?>
                                <?= $row("header[$i][sub][$j]", (string) $sLabel, (string) $sHref, 'sub') ?>
                            <?php endforeach; ?>
                            <?= $row("header[$i][sub][" . count($children) . "]", '', '', 'sub') ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="adm-menu__item" data-menu-item>
                    <?= $row('header[' . count($header) . ']', '', '', 'top') ?>
                    <div class="adm-menu__sub" data-menu-list="sub"><?= $row('header[' . count($header) . '][sub][0]', '', '', 'sub') ?></div>
                </div>
            </div>
            <p class="adm-menu__adders" hidden><button class="adm-small adm-small--go" type="button" data-menu-add="top">+ মেনু-আইটেম যোগ করুন</button></p>
        </fieldset>
        <?php endif; ?>

        <fieldset class="adm-part">
            <legend>ফুটারের লিংকের কলাম (সর্বোচ্চ <?= e(bn(SiteMenus::MAX_COLUMNS)) ?>টি; প্রতিটির প্রথম লিংক মোটা শিরোনাম হয়)</legend>
            <div class="adm-menu__cols">
                <?php for ($c = 0; $c < SiteMenus::MAX_COLUMNS; $c++): ?>
                    <?php $links = $footer[$c] ?? []; ?>
                    <div class="adm-menu__col">
                        <h3><?= e(bn($c + 1)) ?> নম্বর কলাম</h3>
                        <div class="adm-menu__list" data-menu-list="footer-<?= $c ?>">
                            <?php foreach ($links as $j => [$href, $label]): ?>
                                <?= $row("footer[$c][$j]", (string) $label, (string) $href, 'footer-' . $c) ?>
                            <?php endforeach; ?>
                            <?= $row("footer[$c][" . count($links) . "]", '', '', 'footer-' . $c) ?>
                        </div>
                        <p class="adm-menu__adders" hidden><button class="adm-small" type="button" data-menu-add="footer-<?= $c ?>">+ লিংক যোগ করুন</button></p>
                    </div>
                <?php endfor; ?>
            </div>
        </fieldset>

        <fieldset class="adm-part">
            <legend>সামাজিক মাধ্যম (ফুটার ও ওপরের পট্টির আইকন; সর্বোচ্চ <?= e(bn(SiteMenus::MAX_SOCIAL)) ?>টি, এই ক্রমেই সাইটে দেখায়)</legend>
            <p class="adm-hint">নতুন আইকন: PNG/WebP/JPG, প্রায় ৫০×৫০ পিক্সেল, স্বচ্ছ পটভূমি ভালো, সর্বোচ্চ ২ MB। আইকন না বদলালে ফাইলের ঘর খালি রাখুন।</p>
            <div class="adm-menu__list" data-menu-list="social">
                <?php foreach ($social as $i => $item): ?>
                    <?= $socialRow((int) $i, $item) ?>
                <?php endforeach; ?>
            </div>
            <p class="adm-menu__adders" hidden><button class="adm-small" type="button" data-menu-add="social">+ নতুন মাধ্যম যোগ করুন</button></p>
            <label class="adm-check"><input type="checkbox" name="social_hide" value="1"<?= $hideEmpty ? ' checked' : '' ?>> যে আইকনের লিংক নেই, সেটা সাইটে লুকান</label>
        </fieldset>

        <p class="adm-rich-dialog__row">
            <button class="adm-btn" type="submit">সংরক্ষণ করুন</button>
            <button class="adm-small" type="button" data-menu-close>বন্ধ করুন</button>
            <?php if ($menuHeader ? SiteMenus::isChanged() : SiteMenus::isFooterChanged()): ?>
                <button class="adm-small adm-small--warn" type="submit" form="menu-reset"><?= $menuHeader ? 'শুরুর মেনু ও ফুটারে ফিরুন' : 'শুরুর ফুটারে ফিরুন' ?></button>
            <?php endif; ?>
        </p>
    </form>
    <form id="menu-reset" action="<?= e($base) ?>/menu" method="post" data-confirm="<?= $menuHeader ? 'ওপরের মেনু ও ফুটারের কলাম' : 'ফুটারের কলাম' ?> সাইটের সঙ্গে আসা রূপে ফিরবে (সামাজিক মাধ্যম যেমন আছে থাকবে)। ফেরাবেন?">
        <?= csrf_field() ?>
        <input type="hidden" name="back" value="<?= e($here) ?>">
        <input type="hidden" name="reset" value="1">
    </form>
    <template id="menu-row-template"><?= $row('__', '', '', '') ?></template>
    <template id="menu-social-template"><?= $socialRow(0, ['key' => '', 'name' => '', 'url' => '', 'icon' => '']) ?></template>
</dialog>
