<?php
/**
 * "সাইটের লেখা"-র নিজস্ব পুরো পর্দা: মূল অ্যাডমিন মেনু নেই; ওপরে বাঁয়ে নাম, ডানে "বাহির হোন" (মূল প্যানেলে ফেরা)।
 * পর্দাটা খোলার সময় ওপর থেকে নেমে আসে (নড়াচড়া কমানোর পছন্দ থাকলে নয়)।
 *
 * @var string $content
 * @var string|null $title
 * @var list<string> $errors
 * @var string|null $ok
 */
$errors = $errors ?? [];
$ok = $ok ?? null;
?>
<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e((string) ($title ?? 'সাইটের লেখা')) ?> — অ্যাডমিন প্যানেল</title>
    <link rel="icon" type="image/png" href="<?= e(asset('img/site/logo-figma.png')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
    <script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</head>
<body class="adm-studio">
    <header class="adm-studio__bar">
        <p class="adm-studio__name"><?= e((string) ($title ?? 'সাইটের লেখা')) ?></p>
        <a class="adm-studio__exit" href="<?= e(admin_base()) ?>">বাহির হোন</a>
    </header>
    <div class="adm-studio__body">
        <?php if ($errors !== []): ?>
            <ul class="adm-notice adm-notice--error" role="alert">
                <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($ok !== null && $ok !== ''): ?>
            <p class="adm-notice adm-notice--ok" role="status"><?= e($ok) ?></p>
        <?php endif; ?>
<?= $content ?>
    </div>
</body>
</html>
