<?php
/**
 * "সেবা সমূহ" পেইজ (Figma node 2642-54): নোটিস, খোঁজার ঘর, সব সেবা বর্ণানুক্রমে ৩ কলামে।
 *
 * @var string $heading
 * @var string $query    দর্শক কী খুঁজেছেন ('' = খোঁজেননি)
 * @var list<list<array{letter:string,items:list<array{name:string,slug:string}>}>> $columns
 * @var int $found       দেখানো সেবার সংখ্যা
 */
$crumbs = [['/', 'হোম'], [null, 'সেবাসমূহ']];
require BASE_PATH . '/app/Views/partials/page-banner.php';
?>
<section class="section section--services-page">
    <div class="container">
        <p class="services-intro"><?= e(content('services.intro')) ?></p>

        <div class="svc-notice">
            <ul>
                <?php foreach ([1, 2, 3] as $n): ?>
                    <li><?= e(content("services.note.{$n}")) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <form class="svc-search" action="/services" method="get" role="search">
            <label class="sr-only" for="svc-q"><?= e(content('services.search.placeholder')) ?></label>
            <input id="svc-q" data-bn type="search" name="q" value="<?= e($query) ?>" maxlength="100"
                   placeholder="<?= e(content('services.search.placeholder')) ?>" autocomplete="off">
            <button class="svc-search__btn" type="submit">
                <span><?= e(content('services.search.button')) ?></span>
                <img src="<?= e(asset('img/icons/search-white.svg')) ?>" alt="" width="32" height="32">
            </button>
        </form>

        <?php require BASE_PATH . '/app/Views/partials/bn-switch.php'; ?>

        <?php if ($query !== ''): ?>
            <p class="svc-result" role="status">
                <?php if ($found > 0): ?>
                    “<?= e($query) ?>” — <?= e((string) $found) ?>টি সেবা পাওয়া গেছে।
                <?php else: ?>
                    “<?= e($query) ?>” নামে কোনো সেবা পাওয়া যায়নি।
                <?php endif; ?>
                <a href="/services">সব সেবা দেখুন</a>
            </p>
            <p class="svc-result svc-result--note"><?= e(content('services.search.note')) ?></p>
        <?php endif; ?>

        <div class="svc-columns">
            <?php foreach ($columns as $column): ?>
                <?php if ($column === []) {
                    continue;
                } ?>
                <div class="svc-col">
                    <?php foreach ($column as $group): ?>
                        <section class="svc-group" aria-labelledby="svc-<?= e($group['letter']) ?>">
                            <h2 class="svc-group__head" id="svc-<?= e($group['letter']) ?>"><?= e($group['letter']) ?></h2>
                            <ul>
                                <?php foreach ($group['items'] as $service): ?>
                                    <li><a href="/services/<?= e($service['slug']) ?>"><?= e($service['name']) ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        </section>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
