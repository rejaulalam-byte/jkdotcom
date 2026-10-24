<?php
/**
 * একটা সেবার পেশাজীবী-তালিকা (Figma node 2841-673): খোঁজার ঘর, বিভাগের নাম, প্রতি-পৃষ্ঠার সংখ্যা,
 * টেবিল, পৃষ্ঠা-নাম্বার। "বিস্তারিত তথ্য: ক্লিক করুন" কর্মীর ব্যক্তিগত পেইজে (/workers/<id>) নেয়।
 *
 * @var string $heading                       ব্যানারের বড় শিরোনাম
 * @var array{name:string,slug:string} $service
 * @var string $query     দর্শকের খোঁজা ('' = খোঁজেননি)
 * @var int $perPage      প্রতি পৃষ্ঠায় কয়টা (0 = সব)
 * @var bool $byRating    true হলে রেটিং অনুযায়ী সাজানো (ডিফল্ট: ঘূর্ণি ক্রম)
 * @var int $total        এই সেবার মোট পেশাজীবী
 * @var int $found        খোঁজার সঙ্গে মেলা পেশাজীবী
 * @var list<string> $divisions  মেলা পেশাজীবীদের বিভাগের নাম
 * @var list<array<string,mixed>> $workers  এই পৃষ্ঠার সারি
 * @var int $offset       এই পৃষ্ঠার আগে কতজন (ক্রমিক নম্বরের জন্য)
 * @var int $page
 * @var int $pages
 * @var array<int,float> $ratings  কর্মীর id => গড় রেটিং (যার রেটিং নেই সে থাকে না)
 */
$crumbs = [['/', 'হোম'], ['/services', 'সেবাসমূহ'], [null, $service['name']]];
require BASE_PATH . '/app/Views/partials/page-banner.php';

$base = '/services/' . rawurlencode($service['slug']);
// খোঁজা, প্রতি-পৃষ্ঠার সংখ্যা ও পৃষ্ঠা-নাম্বার মিলিয়ে লিংক; ডিফল্ট মান লিংকে বসে না
$link = static function (int $p) use ($base, $query, $perPage, $byRating): string {
    $params = array_filter([
        'q' => $query,
        'o' => $byRating ? 'rating' : '',
        'n' => $perPage === 15 ? '' : ($perPage === 0 ? 'all' : (string) $perPage),
        'p' => $p > 1 ? (string) $p : '',
    ], static fn (string $v): bool => $v !== '');

    return $base . ($params === [] ? '' : '?' . http_build_query($params));
};
$divisionText = count($divisions) > 1
    ? implode(', ', array_slice($divisions, 0, -1)) . ' ও ' . end($divisions)
    : ($divisions[0] ?? '');
?>
<section class="section section--workers">
    <div class="container">
        <form class="svc-search wk-search" action="<?= e($base) ?>" method="get" role="search">
            <label class="sr-only" for="wk-q"><?= e(content('workers.search.placeholder')) ?></label>
            <input id="wk-q" data-bn type="search" name="q" value="<?= e($query) ?>" maxlength="100"
                   placeholder="<?= e(content('workers.search.placeholder')) ?>" autocomplete="off">
            <?php if ($perPage !== 15): ?>
                <input type="hidden" name="n" value="<?= $perPage === 0 ? 'all' : e((string) $perPage) ?>">
            <?php endif; ?>
            <?php if ($byRating): ?>
                <input type="hidden" name="o" value="rating">
            <?php endif; ?>
            <button class="svc-search__btn" type="submit">
                <span><?= e(content('workers.search.button')) ?></span>
                <img src="<?= e(asset('img/icons/search-white.svg')) ?>" alt="" width="32" height="32">
            </button>
        </form>
        <?php require BASE_PATH . '/app/Views/partials/bn-switch.php'; ?>

        <h2 class="wk-title"><?= e(content('workers.title')) ?>(<?= e($service['name']) ?>)</h2>

        <?php // বিভাগের লাইন, প্রতি-পৃষ্ঠা ও টেবিলের মাথা সবসময় থাকে; কেউ না থাকলে একটা খালি সারিতে বার্তা ?>
        <div class="wk-bar">
            <p class="wk-bar__division">
                <?= e(content('workers.division')) ?>
                <span><?= $divisionText !== '' ? e($divisionText) : '—' ?></span>
            </p>
            <form class="wk-bar__per" action="<?= e($base) ?>" method="get">
                <?php if ($query !== ''): ?><input type="hidden" name="q" value="<?= e($query) ?>"><?php endif; ?>
                <?php if ($byRating): ?><input type="hidden" name="o" value="rating"><?php endif; ?>
                <label for="wk-per"><?= e(content('workers.per_page')) ?></label>
                <select id="wk-per" name="n" data-autosubmit>
                    <?php foreach ([15, 25, 50, 0] as $n): ?>
                        <option value="<?= $n === 0 ? 'all' : $n ?>"<?= $n === $perPage ? ' selected' : '' ?>><?= $n === 0 ? e(content('workers.per_page_all')) : e(bn($n)) ?></option>
                    <?php endforeach; ?>
                </select>
                <span><?= e(content('workers.per_page_unit')) ?></span>
                <noscript><button type="submit">&#10003;</button></noscript>
            </form>
        </div>

        <?php
        // ক্রমের বদল: ডিফল্ট ঘূর্ণি ক্রম (সবাই সমান সুযোগ), ঐচ্ছিকভাবে রেটিং অনুযায়ী; খোঁজা ও প্রতি-পৃষ্ঠা অক্ষত থাকে
        $sortParams = array_filter([
            'q' => $query,
            'n' => $perPage === 15 ? '' : ($perPage === 0 ? 'all' : (string) $perPage),
            'o' => $byRating ? '' : 'rating',
        ], static fn (string $v): bool => $v !== '');
        $sortUrl = $base . ($sortParams === [] ? '' : '?' . http_build_query($sortParams));
        ?>
        <p class="wk-sort">
            <span><?= e(content($byRating ? 'workers.sort.by_rating' : 'workers.sort.rotate')) ?></span>
            <a class="wk-sort__btn" href="<?= e($sortUrl) ?>"><?= e(content($byRating ? 'workers.sort.rotate_btn' : 'workers.sort.rating_btn')) ?></a>
        </p>

        <div class="wk-tablewrap">
            <table class="wk-table">
                <thead>
                    <tr>
                        <th class="wk-no" scope="col"><?= e(content('workers.col.no')) ?></th>
                        <th class="wk-name" scope="col"><?= e(content('workers.col.name')) ?></th>
                        <th scope="col"><?= e(content('workers.col.area')) ?></th>
                        <th scope="col"><?= e(content('workers.col.upazila')) ?></th>
                        <th scope="col"><?= e(content('workers.col.district')) ?></th>
                        <th class="wk-rating" scope="col"><?= e(content('workers.col.rating')) ?></th>
                        <th class="wk-details" scope="col"><?= e(content('workers.col.details')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($workers === []): ?>
                        <tr class="wk-empty">
                            <td colspan="7">
                                <?php if ($total === 0): ?>
                                    <?= e(content('workers.empty')) ?>
                                <?php else: ?>
                                    “<?= e($query) ?>” — <?= e(content('workers.nomatch')) ?>
                                    <a href="<?= e($base) ?>"><?= e(content('workers.all')) ?></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($workers as $i => $w): ?>
                        <?php $serial = $offset + $i + 1; ?>
                        <tr>
                            <td class="wk-no" data-label="<?= e(content('workers.col.no')) ?>"><?= e(bn(str_pad((string) $serial, 2, '0', STR_PAD_LEFT))) ?>.</td>
                            <th class="wk-name" scope="row"><?= e($w['name']) ?></th>
                            <td data-label="<?= e(content('workers.col.area')) ?>"><?= $w['address'] !== '' ? e($w['address']) : '—' ?></td>
                            <td data-label="<?= e(content('workers.col.upazila')) ?>"><?= $w['upazila'] !== '' ? e($w['upazila']) : '—' ?></td>
                            <td data-label="<?= e(content('workers.col.district')) ?>"><?= $w['district'] !== '' ? e($w['district']) : '—' ?></td>
                            <td class="wk-rating" data-label="<?= e(content('workers.col.rating')) ?>"><?= isset($ratings[$w['id']]) ? e(bn(rtrim(rtrim(number_format($ratings[$w['id']], 1, '.', ''), '0'), '.'))) : e(content('workers.rating_none')) ?></td>
                            <td class="wk-details">
                                <a class="wk-open" href="/workers/<?= (int) $w['id'] ?>?s=<?= e(rawurlencode($service['slug'])) ?>"><?= e(content('workers.details_btn')) ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

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
    </div>

</section>
