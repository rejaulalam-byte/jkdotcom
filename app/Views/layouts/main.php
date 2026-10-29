<?php
/**
 * সব পেইজের সাধারণ কাঠামো: <head>, ওপরের পট্টি, মেনু, পেইজের বিষয়বস্তু, ফুটার।
 *
 * @var string $content   পেইজের HTML (নিজের ভিউ-তেই escape করা)
 * @var string|null $title
 * @var string|null $description
 */
$pageTitle = isset($title) && $title !== '' ? $title : content('site.title');
$pageDescription = $description ?? content('site.description');
?>
<!doctype html>
<html lang="bn"<?= live_preview() ? ' data-live="1"' : '' ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta name="theme-color" content="<?= e(\App\Models\SiteTheme::color('teal')) ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDescription) ?>">
    <meta property="og:locale" content="bn_BD">
    <link rel="icon" type="image/png" href="<?= e(asset('img/site/logo-figma.png')) ?>">
    <?php // বাংলা ফন্ট আগেভাগে নামানো: অ্যাডমিনের বাছা ফন্ট (App\Models\SiteFonts), নইলে Hind Siliguri ?>
    <link rel="preload" href="<?= e(asset('fonts/' . \App\Models\SiteFonts::preloadFile())) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <?php if (\App\Models\SiteTheme::anyCustom()): // অ্যাডমিনের বাছা থিমের রং ও লেখার আকার (app.css-এর মান বদলে দেয়) ?>
        <link rel="stylesheet" href="/site-theme?v=<?= e(\App\Models\SiteTheme::version()) ?>">
    <?php endif; ?>
    <?php if (live_preview()): // অ্যাডমিনের লাইভ পেইজ এডিট: app.js-এর আগে চলে (লেখার চিহ্ন খুঁজে নিয়ে মুছে দেয়) ?>
        <link rel="stylesheet" href="<?= e(asset('css/live-edit.css')) ?>">
        <script src="<?= e(asset('js/live-edit.js')) ?>" defer></script>
    <?php endif; ?>
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body id="top"<?= !empty($bodyClass) ? ' class="' . e($bodyClass) . '"' : '' ?>>
<a class="skip-link" href="#main">মূল অংশে যান</a>

<?php require BASE_PATH . '/app/Views/partials/topbar.php'; ?>
<?php require BASE_PATH . '/app/Views/partials/header.php'; ?>

<main id="main">
<?php if (!live_preview()) { require BASE_PATH . '/app/Views/partials/popup-ad.php'; } ?>
<?= $content ?>
<?php if (live_preview()) { require BASE_PATH . '/app/Views/partials/live-messages.php'; } ?>
</main>

<?php require BASE_PATH . '/app/Views/partials/footer.php'; ?>

<?php // "back to top": দর্শক পেইজের মাঝামাঝি পেরোলে app.js এটা দেখায় ?>
<button class="to-top" type="button" aria-label="পেজের উপরে যান" data-to-top>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5"/><path d="M5 12l7-7 7 7"/></svg>
</button>
<?php if (live_preview()): // পেইজের সব লেখার আসল মান ও সংরক্ষণের ঠিকানা (চালানো হয় না, শুধু তথ্য) ?>
<script type="application/json" id="jk-live-data"><?= json_encode(\App\Core\LiveEdit::data(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?php endif; ?>
</body>
</html>
