<?php
/**
 * অ্যাডমিন প্যানেলের সাধারণ কাঠামো। $admin থাকলে বাঁয়ে মেনুসহ প্যানেল, না থাকলে (লগইন, সেটআপ ইত্যাদি) মাঝখানে একটা কার্ড।
 * সাইটের পাবলিক CSS/JS এখানে লাগে না; আলাদা admin.css ও admin.js। সার্চ-ইঞ্জিনকে ঢুকতে নিষেধ (noindex)।
 *
 * @var string $content
 * @var string|null $title
 * @var bool|null $hideTitle  true হলে পেইজের ওপরের বড় শিরোনাম দেখানো হয় না (ড্যাশবোর্ডে নিজের ব্যানার আছে)
 * @var array<string,mixed>|null $admin
 * @var list<string> $errors
 * @var string|null $ok
 */
use App\Core\AdminAuth;

$admin = $admin ?? null;
$errors = $errors ?? [];
$ok = $ok ?? null;
$pageTitle = ($title ?? 'অ্যাডমিন') . ' — অ্যাডমিন প্যানেল';
// [লিংক, নাম, শুধু সুপার অ্যাডমিন, (ঐচ্ছিক) দরকারি অনুমতি]
$nav = [
    [admin_base() . '/workers', 'কর্মী', false, 'registrations'],
    [admin_base() . '/agents', 'প্রতিনিধি', false, 'registrations'],
    [admin_base() . '/old-workers', 'পুরনো কর্মী সক্রিয় করা', false, 'registrations'],
    [admin_base() . '/incomplete', 'অসম্পূর্ণ তথ্য', false, 'registrations'],
    [admin_base() . '/feedback', 'মতামত', false, 'inbox'],
    [admin_base() . '/leads', 'আগ্রহীর তালিকা', false, 'inbox'],
    [admin_base() . '/jobs', 'কাজের বিজ্ঞপ্তি', false, 'inbox'],
    [admin_base() . '/ads', 'বিজ্ঞাপন', false, 'ads'],
    [admin_base() . '/services', 'সেবার তালিকা', false, 'services'],
    [admin_base() . '/studio', 'লাইভ পেইজ এডিট', false, 'pages'],
    [admin_base() . '/pages', 'নতুন পেইজ', false, 'pages'],
    [admin_base() . '/mails', 'ইমেইলের লেখা', false, 'email'],
    [admin_base() . '/locks', 'আটকানো লগইন', false, 'locks'],
];
// ওপরের সারি: বাঁয়ে ড্যাশবোর্ড; ডানে নিজের জিনিস (অ্যাকাউন্ট, অ্যাডমিন)। কার্যলগ ড্যাশবোর্ডের ডান পাশের আলাদা বাক্সে
$top = [
    [admin_base(), 'ড্যাশবোর্ড', false],
];
$mine = [
    [admin_base() . '/account', 'আমার অ্যাকাউন্ট', false],
    [admin_base() . '/admins', 'অ্যাডমিন', true],
];
?>
<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" type="image/png" href="<?= e(asset('img/site/logo-figma.png')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
    <script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</head>
<body class="<?= $admin !== null ? 'adm-shell' : 'adm-plain' ?>">
<?php if ($admin !== null): ?>
    <header class="adm-top">
        <div class="adm-top__row">
            <p class="adm-brand adm-brand--inline">Jorurikaj.com<span>অ্যাডমিন প্যানেল</span></p>
            <nav class="adm-top__nav" aria-label="প্যানেলের মূল মেনু">
                <?php foreach ($top as [$href, $label, $superOnly]): ?>
                    <?php if (!$superOnly || AdminAuth::isSuper($admin)): ?>
                        <a href="<?= e($href) ?>"<?= is_current($href) ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
            <div class="adm-top__user">
                <nav class="adm-top__nav" aria-label="আমার জিনিস">
                    <?php foreach ($mine as [$href, $label, $superOnly]): ?>
                        <?php if (!$superOnly || AdminAuth::isSuper($admin)): ?>
                            <a href="<?= e($href) ?>"<?= is_current($href) ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>
                <small><?= e(AdminAuth::roleLabel((string) $admin['role'])) ?></small>
                <form action="<?= admin_base() ?>/logout" method="post"><?= csrf_field() ?><button type="submit">লগআউট</button></form>
            </div>
        </div>
        <nav class="adm-top__work" aria-label="কাজের মেনু">
            <?php foreach ($nav as [$href, $label, $superOnly, $need]): ?>
                <?php if ((!$superOnly || AdminAuth::isSuper($admin)) && ($need === null || AdminAuth::can($admin, $need))): ?>
                    <a href="<?= e($href) ?>"<?= is_current($href) ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    </header>
    <main class="adm-main">
        <?php if (empty($hideTitle)): ?>
            <h1><?= e((string) ($title ?? '')) ?></h1>
        <?php endif; ?>
<?php else: ?>
    <main class="adm-card">
        <p class="adm-brand adm-brand--center">Jorurikaj.com<span>অ্যাডমিন প্যানেল</span></p>
<?php endif; ?>
        <?php if ($errors !== []): ?>
            <ul class="adm-notice adm-notice--error" role="alert">
                <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($ok !== null && $ok !== ''): ?>
            <p class="adm-notice adm-notice--ok" role="status"><?= e($ok) ?></p>
        <?php endif; ?>
<?= $content ?>
    </main>
</body>
</html>
