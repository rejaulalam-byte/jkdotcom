<?php
/**
 * "আরও কোন কোন পেশায় অভিজ্ঞ?" — প্রধান পেশার বাইরে সর্বোচ্চ ৪টি (পেশাজীবীর ফর্ম ও প্রোফাইল)।
 * খোঁজা-যায় এমন তালিকা থেকে বাছলে পেশাটা নিচে ট্যাগ হয়ে বসে (× দিয়ে বাদ); app.js [data-chip-select] এটা চালায়।
 * JavaScript ছাড়া তালিকা থেকে একটা পেশা (extra_service_pick) বাছা যায়। সার্ভার ServiceRepository::parseExtra-তে যাচাই করে।
 *
 * @var list<array{name:string,slug:string,icon:?string}> $services  সেবার তালিকা
 * @var list<string> $esSlugs  আগে বাছা পেশার slug
 */
$esSlugs = $esSlugs ?? [];
$esNames = [];
foreach ($services as $s) {
    $esNames[$s['slug']] = $s['name'];
}
?>
<div class="pf pf--select pf--chips" data-chip-select data-name="extra_services[]" data-max="<?= \App\Models\ServiceRepository::MAX_EXTRA ?>"
     data-remove="<?= e(content('regform.wd.remove')) ?>" data-msg-max="<?= e(content('regform.es.max')) ?>">
    <label class="sr-only" for="rf-extra-service"><?= e(content('regform.f.extra_service')) ?></label>
    <select id="rf-extra-service" name="extra_service_pick" data-search data-none="<?= e(content('geo.search.none')) ?>">
        <option value=""><?= e(content('regform.f.extra_service')) ?></option>
        <?php foreach ($services as $s): ?>
            <option value="<?= e($s['slug']) ?>"><?= e($s['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <ul class="chips" data-chips>
        <?php foreach ($esSlugs as $slug): ?>
            <?php if (isset($esNames[$slug])): ?>
                <li class="chips__item" data-id="<?= e($slug) ?>"><span><?= e($esNames[$slug]) ?></span>
                    <button type="button" aria-label="<?= e(content('regform.wd.remove')) ?>">&times;</button>
                    <input type="hidden" name="extra_services[]" value="<?= e($slug) ?>">
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>
    <p class="chips__hint" data-chips-hint hidden role="status"></p>
</div>
