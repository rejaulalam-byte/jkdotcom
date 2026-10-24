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
        ['/registration/professional/form', 'পেশাজীবীদের রেজিস্ট্রেশন'],
        ['/registration/agent/form',   'প্রতিনিধি রেজিস্ট্রেশন'],
        ['/login',                     'পেশাজীবী লগইন'],
        ['/login/agent',               'প্রতিনিধি লগইন'],
    ]],
    ['/contact',      'যোগাযোগ',           []],
];
$current = \App\Core\Router::currentPath();
$member = \App\Core\Auth::user();
// লগইন-করা সদস্যের নিজের ভূমিকার লগইন-আইটেম মেনুতে থাকে না (পেশাজীবী -> শুধু প্রতিনিধি লগইন, উল্টোটাও); লগআউটে ফিরে আসে
if ($member !== null) {
    $ownLogin = $member['role'] === 'agent' ? '/login/agent' : '/login';
    foreach ($menu as &$menuItem) {
        $menuItem[2] = array_values(array_filter($menuItem[2], static fn (array $sub): bool => $sub[0] !== $ownLogin));
    }
    unset($menuItem);
}
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
                        || ($href === '/' && in_array($current, ['/faq', '/privacy', '/terms'], true))
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
                <?php // লগইন-করা সদস্যের ছবি (Figma 2663-981): ক্লিকে ছবির গায়ে নাম, প্রোফাইল ও লগআউটের মেনু নামে ?>
                <details class="nav__user">
                    <summary class="nav__avatar" aria-label="<?= e((string) $member['name']) ?> — আমার অ্যাকাউন্ট">
                        <?php if ($avatar !== null): ?>
                            <img src="<?= e($avatar) ?>" alt="" width="58" height="58">
                        <?php else: ?>
                            <span aria-hidden="true"><?= e(mb_substr((string) $member['name'], 0, 1)) ?></span>
                        <?php endif; ?>
                    </summary>
                    <div class="user-menu">
                        <p class="user-menu__name"><?= e((string) $member['name']) ?></p>
                        <a class="user-menu__item" href="/account/password"><?= e(content('nav.password')) ?></a>
                        <a class="user-menu__item" href="/profile"><?= e(content('nav.profile')) ?></a>
                        <a class="user-menu__item" href="/profile#photo"><?= e(content('nav.photo')) ?></a>
                        <form action="/logout" method="post">
                            <?= csrf_field() ?>
                            <button class="user-menu__item user-menu__logout" type="submit"><?= e(content('nav.logout')) ?></button>
                        </form>
                    </div>
                </details>
            <?php endif; ?>
        </nav>
    </div>
</header>
