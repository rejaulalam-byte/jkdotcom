<?php
/**
 * লোগো + প্রধান মেনু। খোলা পেইজ হাইলাইট থাকে।
 * মেনুতে নতুন আইটেম: নিচের $menu তালিকায় [পাথ, নাম, [সাব-মেনু]] বসান।
 * সাব-মেনু আছে এমন আইটেমের নামে ক্লিকে পেইজ খোলে না, শুধু সাব-মেনু দেখায় (app.js)।
 */
$menu = [
    ['/',             'হোম',               []],
    ['/about',        'আমাদের সম্পর্কে',     []],
    ['/services',     'সেবা সমূহ',          []],
    ['/today',        'আজকের প্রকাশিত',    [
        ['/today/post', 'কাজ সম্পর্কে জানান'],
        ['/today',      'কাজ সম্পর্কে জানুন'],
    ]],
    ['/registration', 'রেজিস্ট্রেশন',        [
        ['/registration',              'রেজিস্ট্রেশন সম্পর্কিত তথ্যাবলী'],
        ['/registration/professional', 'আগ্রহী পেশাদার কর্মী রেজিস্ট্রেশন'],
        ['/registration/agent',        'প্রতিনিধি রেজিস্ট্রেশন'],
        ['/login',                     'পেশাজীবী লগইন'],
        ['/login/agent',               'প্রতিনিধি লগইন'],
        ['/dashboard',                 'ড্যাশবোর্ড'],
    ]],
    ['/contact',      'যোগাযোগ',           []],
];
$current = \App\Core\Router::currentPath();
$member = \App\Core\Auth::user();
// লগইন ও স্বাগত পেইজেও "রেজিস্ট্রেশন" মেনু হাইলাইট থাকে (Figma)
$registrationPaths = ['/login', '/welcome'];
?>
<header class="site-header">
    <div class="container header__inner">
        <a class="brand" href="/" aria-label="Jorurikaj.com — হোম পেজ">
            <?php // মেনুর লোগো = নাম ও ট্যাগলাইনসহ একটাই ছবি; ফুটারের নিজের লোগো আছে ?>
            <img class="brand__logo" src="<?= e(asset('img/site/logo-header.png')) ?>" alt="Jorurikaj.com — <?= e(content('site.tagline')) ?>" width="760" height="272">
        </a>

        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav">
            <span class="sr-only">মেনু খুলুন বা বন্ধ করুন</span>
            <span class="nav-toggle__bars" aria-hidden="true"></span>
        </button>

        <nav id="site-nav" class="nav" aria-label="প্রধান মেনু">
            <ul class="nav__list">
                <?php foreach ($menu as [$href, $label, $children]):
                    $active = $current === $href || ($href !== '/' && str_starts_with($current, $href . '/'))
                        || ($href === '/registration' && (in_array($current, $registrationPaths, true) || str_starts_with($current, '/login/')));
                    ?>
                    <li class="nav__item<?= $children ? ' has-sub' : '' ?>">
                        <a class="nav__link<?= $active ? ' is-active' : '' ?>" href="<?= e($href) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
                        <?php if ($children): ?>
                            <button class="sub-toggle" type="button" aria-expanded="false">
                                <span class="sr-only"><?= e($label) ?> — সাব-মেনু</span>
                            </button>
                            <ul class="sub">
                                <?php foreach ($children as [$subHref, $subLabel]): ?>
                                    <li><a href="<?= e($subHref) ?>"><?= e($subLabel) ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <a class="nav__search" href="/services" aria-label="সেবা খুঁজুন">
                <img src="<?= e(asset('img/icons/search.svg')) ?>" alt="" width="42" height="42">
            </a>
            <?php if ($member !== null):
                $avatar = \App\Core\PhotoUpload::url($member['photo'] ?? null); ?>
                <?php // লগইন-করা সদস্যের ছবি (Figma 2663-981): ক্লিকে স্বাগত পেইজ ?>
                <a class="nav__avatar" href="/welcome" aria-label="<?= e((string) $member['name']) ?> — আমার অ্যাকাউন্ট">
                    <?php if ($avatar !== null): ?>
                        <img src="<?= e($avatar) ?>" alt="" width="58" height="58">
                    <?php else: ?>
                        <span aria-hidden="true"><?= e(mb_substr((string) $member['name'], 0, 1)) ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
        </nav>
    </div>
</header>
