<?php
/**
 * হোমে অ্যাডমিনের যোগ করা নতুন সেকশন (App\Models\HomeSections), অন্য সেকশনের মতো একই নকশা: ছোট লোগোসহ ছোট শিরোনামের
 * ক্যাপসুল, বড় শিরোনাম (দুটো একসঙ্গে বাঁয়ে / মাঝে / ডানে), খোলা লেখা (পূর্ণ এডিটরের, ছাঁকা), আর ঐচ্ছিক বাটন (নিজের জায়গায়)।
 *
 * @var string $sectionId
 */

use App\Models\HomeSections;

$extra = HomeSections::data($sectionId);
if ($extra === null) {
    return;
}
$external = str_starts_with($extra['button_url'], 'http');
?>
<section class="section home-extra" id="section-<?= e($sectionId) ?>" aria-labelledby="hx-<?= e($sectionId) ?>">
    <div class="container section__head section__head--<?= e($extra['align']) ?>">
        <?php if ($extra['badge'] !== ''): ?>
            <p class="pill pill--olive">
                <img src="<?= e(HomeSections::iconUrl($extra)) ?>" alt="" width="50" height="32">
                <span><?= e($extra['badge']) ?></span>
            </p>
        <?php endif; ?>
        <h2 id="hx-<?= e($sectionId) ?>" class="section__title"><?= e($extra['heading']) ?></h2>
    </div>
    <div class="container rich home-extra__body">
        <?= HomeSections::html($sectionId) ?>
    </div>
    <?php if ($extra['button_text'] !== '' && $extra['button_url'] !== ''): ?>
        <div class="container home-extra__btn home-extra__btn--<?= e($extra['button_align']) ?>">
            <a class="btn btn--teal-soft" href="<?= e($extra['button_url']) ?>"<?= $external ? ' target="_blank" rel="noopener"' : '' ?>><?= e($extra['button_text']) ?></a>
        </div>
    <?php endif; ?>
</section>
