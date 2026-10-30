<?php
/**
 * সাইট-জুড়ে খোঁজার পেইজ (/search): খোঁজার ঘর + ফল (পেইজ, প্রশ্ন-উত্তর, সেবা, এলাকা, পেশাজীবী)। কিছু না মিললে ভদ্র বার্তা।
 * লেখা সব সাইটের লেখার কী (search.*)।
 *
 * @var string $query
 * @var bool $tooShort
 * @var array<string,mixed>|null $result SiteSearch::run()-এর ফল (খোঁজা না হলে null)
 * @var list<array<string,mixed>> $workers এই পৃষ্ঠার পেশাজীবী
 * @var int $page
 * @var int $pages
 */
$crumbs = [['/', 'হোম'], [null, content('search.crumb')]];
require BASE_PATH . '/app/Views/partials/page-banner.php';
$link = static fn (int $n): string => '/search?q=' . rawurlencode($query) . ($n > 1 ? '&p=' . $n : '');
?>
<section class="section section--search-page">
    <div class="container">
        <p class="services-intro"><?= t('search.intro') ?></p>

        <form class="svc-search" action="/search" method="get" role="search">
            <label class="sr-only" for="site-q"><?= e(content('search.placeholder')) ?></label>
            <input id="site-q" data-bn type="search" name="q" value="<?= e($query) ?>" maxlength="100"
                   placeholder="<?= e(content('search.placeholder')) ?>" autocomplete="off"<?= $query === '' ? ' autofocus' : '' ?>>
            <button class="svc-search__btn" type="submit">
                <span><?= t('search.button') ?></span>
                <img src="<?= e(asset('img/icons/search-white.svg')) ?>" alt="" width="32" height="32">
            </button>
        </form>
        <?php require BASE_PATH . '/app/Views/partials/bn-switch.php'; ?>

        <?php if ($tooShort): ?>
            <p class="svc-result" role="status"><?= e(content('search.short')) ?></p>
        <?php elseif ($result !== null && $result['total'] === 0): ?>
            <p class="search-none" role="status"><?= e(content('search.none')) ?></p>
        <?php elseif ($result !== null): ?>
            <p class="svc-result" role="status">“<?= e($query) ?>” — <?= e(str_replace('{count}', bn($result['total']), content('search.found'))) ?></p>

            <?php if ($result['pages'] !== [] || $result['faq'] !== []): ?>
                <section class="search-group" aria-labelledby="sg-pages">
                    <h2 class="search-group__head" id="sg-pages"><?= t('search.g.pages') ?></h2>
                    <ul class="search-list">
                        <?php foreach (array_merge($result['pages'], $result['faq']) as $hit): ?>
                            <li>
                                <a href="<?= e($hit['url']) ?>"><?= e($hit['title']) ?></a>
                                <?php if ($hit['snippet'] !== ''): ?><p><?= e($hit['snippet']) ?></p><?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <?php if ($result['services'] !== []): ?>
                <section class="search-group" aria-labelledby="sg-services">
                    <h2 class="search-group__head" id="sg-services"><?= t('search.g.services') ?></h2>
                    <ul class="search-chips">
                        <?php foreach ($result['services'] as $s): ?>
                            <li><a href="/services/<?= e($s['slug']) ?>"><?= e($s['name']) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <?php if ($result['areas'] !== []): ?>
                <section class="search-group" aria-labelledby="sg-areas">
                    <h2 class="search-group__head" id="sg-areas"><?= t('search.g.areas') ?></h2>
                    <ul class="search-list">
                        <?php foreach ($result['areas'] as $a): ?>
                            <li>
                                <a href="/search?q=<?= e(rawurlencode($a['q'])) ?>"><?= e($a['name']) ?></a>
                                <span class="search-kind"><?= e(content('search.kind.' . $a['kind'])) ?></span>
                                <span class="search-count"><?= e(str_replace('{count}', bn($a['workers']), content('search.area.count'))) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <?php if ($result['workers'] !== []): ?>
                <section class="search-group" aria-labelledby="sg-workers">
                    <h2 class="search-group__head" id="sg-workers"><?= t('search.g.workers') ?> <span class="search-count">(<?= e(bn(count($result['workers']))) ?>)</span></h2>
                    <ul class="search-workers">
                        <?php foreach ($workers as $w):
                            $area = implode(', ', array_filter([$w['address'], $w['upazila'], $w['district'], $w['division']], static fn (string $x): bool => $x !== ''));
                            ?>
                            <li>
                                <a class="search-workers__name" href="/workers/<?= (int) $w['id'] ?>"><?= e($w['name']) ?></a>
                                <?php if ($w['profession'] !== ''): ?><span class="search-kind"><?= e($w['profession']) ?></span><?php endif; ?>
                                <p><?= $area !== '' ? e($area) : '—' ?><?= $w['phone'] !== '' ? ' · ' . e(mask_phone($w['phone'])) : '' ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php if ($pages > 1): ?>
                        <nav class="wk-pages" aria-label="<?= e(content('workers.pages_label')) ?>">
                            <?php
                            // প্রথম, শেষ ও এখনকার পৃষ্ঠার দুই পাশের নাম্বার; মাঝের ফাঁকে "…"
                            $shown = array_unique(array_filter(array_merge([1, $pages], range($page - 2, $page + 2)), static fn (int $n): bool => $n >= 1 && $n <= $pages));
                            sort($shown);
                            $prev = 0;
                            foreach ($shown as $n):
                                if ($prev !== 0 && $n - $prev > 1): ?>
                                    <span class="wk-pages__gap" aria-hidden="true">…</span>
                                <?php endif;
                                if ($n === $page): ?>
                                    <span class="wk-pages__item is-current" aria-current="page"><?= e(bn($n)) ?></span>
                                <?php else: ?>
                                    <a class="wk-pages__item" href="<?= e($link($n)) ?>" aria-label="<?= e(content('workers.page')) ?> <?= e(bn($n)) ?>"><?= e(bn($n)) ?></a>
                                <?php endif;
                                $prev = $n;
                            endforeach; ?>
                        </nav>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
