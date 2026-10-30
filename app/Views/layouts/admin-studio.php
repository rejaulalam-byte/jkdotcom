<?php
/**
 * "সাইটের লেখা"-র নিজস্ব পুরো পর্দা: মূল অ্যাডমিন মেনু নেই; ওপরে বাঁয়ে নাম, ডানে বাছা পেইজের কাজের লিংক ও "বাহির হোন" (মূল প্যানেলে ফেরা)।
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
    <title><?= e((string) ($title ?? 'লাইভ পেইজ এডিট')) ?> — অ্যাডমিন প্যানেল</title>
    <link rel="icon" type="image/png" href="<?= e(asset('img/site/logo-figma.png')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
    <script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</head>
<body class="adm-studio">
    <header class="adm-studio__bar">
        <p class="adm-studio__name"><?= e((string) ($title ?? 'লাইভ পেইজ এডিট')) ?></p>
        <?php // বাছা পেইজের কাজের লিংক ওপরের সারিতেই, যাতে নিচে লেখা বদলানোর জায়গা বেশি থাকে ?>
        <div class="adm-studio__actions">
            <?php if (!empty($live['all'])): ?>
                <?php $allCurrent = isset($node['id']) ? (string) $node['id'] : ''; ?>
                <form class="adm-studio__all" action="<?= e(admin_base() . '/studio') ?>" method="get" data-auto-submit>
                    <label for="live-pick-all">সকল পেইজ</label>
                    <select id="live-pick-all" name="p">
                        <option value="" disabled<?= in_array($allCurrent, array_column($live['all'], 'id'), true) ? '' : ' selected' ?>>পেইজ বাছুন…</option>
                        <?php foreach ($live['all'] as $p): ?>
                            <option value="<?= e((string) $p['id']) ?>"<?= $p['id'] === $allCurrent ? ' selected' : '' ?>><?= e((string) $p['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <noscript><button type="submit">দেখুন</button></noscript>
                </form>
            <?php endif; ?>
            <?php if (!empty($page) && empty($listView)): ?>
                <a href="<?= e((string) $page['url']) ?>" target="_blank" rel="noopener">সাইটে নতুন ট্যাবে খুলুন</a>
                <a href="<?= e(admin_base() . '/studio?p=' . $page['id'] . '&view=list') ?>">এই পেইজের লেখার তালিকা</a>
                <?php if (\App\Core\AdminAuth::can($admin, 'footer')): ?>
                    <button type="button" data-menu-open><?= \App\Core\AdminAuth::isSuper($admin) ? 'মেনু সম্পাদনা' : 'ফুটার ও সামাজিক মাধ্যম' ?></button>
                <?php endif; ?>
            <?php endif; ?>
            <a class="adm-studio__exit" href="<?= e(admin_base()) ?>">বাহির হোন</a>
        </div>
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
