<?php
/**
 * Shared page frame: <head>, top bar, menu, page content, footer.
 *
 * @var string $content   page HTML (already escaped by its own view)
 * @var string|null $title
 * @var string|null $description
 */
$pageTitle = isset($title) && $title !== '' ? $title : content('site.title');
$pageDescription = $description ?? content('site.description');
?>
<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta name="theme-color" content="#125a6c">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDescription) ?>">
    <meta property="og:locale" content="bn_BD">
    <link rel="icon" type="image/png" href="<?= e(asset('img/site/logo-figma.png')) ?>">
    <link rel="preload" href="<?= e(asset('fonts/hind-siliguri-400.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body>
<a class="skip-link" href="#main">মূল অংশে যান</a>

<?php require BASE_PATH . '/app/Views/partials/topbar.php'; ?>
<?php require BASE_PATH . '/app/Views/partials/header.php'; ?>

<main id="main">
<?= $content ?>
</main>

<?php require BASE_PATH . '/app/Views/partials/footer.php'; ?>
</body>
</html>
