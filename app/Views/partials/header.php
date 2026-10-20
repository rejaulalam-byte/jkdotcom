<?php
/** Logo + main menu. The active page is highlighted. */
$menu = [
    ['/',             'হোম',               []],
    ['/about',        'আমাদের সম্পর্কে',     []],
    ['/services',     'সেবা সমূহ',          []],
    ['/today',        'আজকের প্রকাশিত',    [
        ['/today/post', 'কাজ সম্পর্কে জানান'],
        ['/today',      'কাজ সম্পর্কে জানুন'],
    ]],
    ['/registration', 'রেজিস্ট্রেশন',        []],
    ['/contact',      'যোগাযোগ',           []],
];
$current = \App\Core\Router::currentPath();
?>
<header class="site-header">
    <div class="container header__inner">
        <a class="brand" href="/" aria-label="Jorurikaj.com — হোম পেজ">
            <?php // menu logo = one picture with the name and tagline in it (owner 2026-10-20); the footer keeps its own logo ?>
            <img class="brand__logo" src="<?= e(asset('img/site/logo-header.png')) ?>" alt="Jorurikaj.com — <?= e(content('site.tagline')) ?>" width="760" height="272">
        </a>

        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav">
            <span class="sr-only">মেনু খুলুন বা বন্ধ করুন</span>
            <span class="nav-toggle__bars" aria-hidden="true"></span>
        </button>

        <nav id="site-nav" class="nav" aria-label="প্রধান মেনু">
            <ul class="nav__list">
                <?php foreach ($menu as [$href, $label, $children]):
                    $active = $current === $href || ($href !== '/' && str_starts_with($current, $href . '/'));
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
        </nav>
    </div>
</header>
