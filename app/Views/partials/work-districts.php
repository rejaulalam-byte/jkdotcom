<?php
/**
 * "কোন জেলায় কাজ করতে আগ্রহী?" — সর্বোচ্চ ৫টি জেলা অথবা "সব জেলা" (পেশাজীবীর ফর্ম ও প্রোফাইল)।
 * খোঁজা-যায় এমন তালিকা থেকে বাছলে জেলাটা নিচে ট্যাগ হয়ে বসে (× দিয়ে বাদ); app.js [data-work-districts] এটা চালায়।
 * JavaScript ছাড়া তালিকা থেকে একটা জেলা (work_district_pick) বাছা যায়। সার্ভার GeoRepository::parseWorkDistricts-এ যাচাই করে।
 *
 * @var array $geo        GeoRepository::all()
 * @var list<int> $wdIds  আগে বাছা জেলার id
 * @var bool $wdAll       "সব জেলা" বাছা কি না
 */
$wdIds = $wdIds ?? [];
$wdAll = $wdAll ?? false;
$wdNames = [];
foreach ($geo['districts'] as $d) {
    $wdNames[$d['id']] = $d['name'];
}
?>
<div class="pf pf--select pf--chips" data-work-districts data-max="<?= \App\Models\GeoRepository::MAX_WORK_DISTRICTS ?>"
     data-all-label="<?= e(content('regform.wd.all')) ?>" data-remove="<?= e(content('regform.wd.remove')) ?>"
     data-msg-max="<?= e(content('regform.wd.max')) ?>">
    <label class="sr-only" for="rf-work-district"><?= t('regform.f.work_district') ?></label>
    <select id="rf-work-district" name="work_district_pick" data-search data-none="<?= e(content('geo.search.none')) ?>">
        <option value=""><?= e(content('regform.f.work_district')) ?></option>
        <option value="all"><?= e(content('regform.wd.all')) ?></option>
        <?php foreach ($geo['districts'] as $d): ?>
            <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <ul class="chips" data-chips>
        <?php if ($wdAll): ?>
            <li class="chips__item" data-all><span><?= t('regform.wd.all') ?></span>
                <button type="button" aria-label="<?= e(content('regform.wd.remove')) ?>">&times;</button>
                <input type="hidden" name="work_all" value="1">
            </li>
        <?php else: ?>
            <?php foreach ($wdIds as $id): ?>
                <?php if (isset($wdNames[(int) $id])): ?>
                    <li class="chips__item" data-id="<?= (int) $id ?>"><span><?= e($wdNames[(int) $id]) ?></span>
                        <button type="button" aria-label="<?= e(content('regform.wd.remove')) ?>">&times;</button>
                        <input type="hidden" name="work_districts[]" value="<?= (int) $id ?>">
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </ul>
    <p class="chips__hint" data-chips-hint hidden role="status"></p>
</div>
