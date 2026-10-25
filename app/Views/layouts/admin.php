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
    [admin_base(), 'ড্যাশবোর্ড', false, null],
    [admin_base() . '/workers', 'কর্মী', false, 'registrations'],
    [admin_base() . '/agents', 'প্রতিনিধি', false, 'registrations'],
    [admin_base() . '/old-workers', 'পুরনো কর্মী সক্রিয় করা', false, 'registrations'],
    [admin_base() . '/incomplete', 'অসম্পূর্ণ তথ্য', false, 'registrations'],
    [admin_base() . '/feedback', 'মতামত', false, 'inbox'],
    [admin_base() . '/leads', 'আগ্রহীর তালিকা', false, 'inbox'],
    [admin_base() . '/jobs', 'কাজের বিজ্ঞপ্তি', false, 'inbox'],
    [admin_base() . '/ads', 'বিজ্ঞাপন', false, 'ads'],
    [admin_base() . '/studio', 'সাইটের লেখা', false, 'pages'],
    [admin_base() . '/locks', 'আটকানো লগইন', false, 'locks'],
    [admin_base() . '/account', 'আমার অ্যাকাউন্ট', false, null],
    [admin_base() . '/admins', 'অ্যাডমিন', true, null],
    [admin_base() . '/log', 'কার্যলগ', true, null],
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
    <aside class="adm-side">
        <p class="adm-brand">Jorurikaj.com<span>অ্যাডমিন প্যানেল</span></p>
        <nav aria-label="প্যানেলের মেনু">
            <?php foreach ($nav as [$href, $label, $superOnly, $need]): ?>
                <?php if ((!$superOnly || AdminAuth::isSuper($admin)) && ($need === null || AdminAuth::can($admin, $need))): ?>
                    <a href="<?= e($href) ?>"<?= is_current($href) ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <div class="adm-user">
            <p><strong><?= e((string) $admin['name']) ?></strong><br><small><?= e(AdminAuth::roleLabel((string) $admin['role'])) ?></small></p>
            <form action="<?= admin_base() ?>/logout" method="post"><?= csrf_field() ?><button type="submit">লগআউট</button></form>
        </div>
    </aside>
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
