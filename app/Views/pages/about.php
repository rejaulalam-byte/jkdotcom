<?php
/**
 * About page (Figma node 2621-103). All wording comes from content('about.*').
 *
 * @var string $heading
 */
$crumbs = [['/', 'হোম'], [null, 'আমাদের সম্পর্কে']];
require BASE_PATH . '/app/Views/partials/page-banner.php';

/** Splits a stored text into paragraphs (blank line = new paragraph). */
$paragraphs = static fn (string $text): array => array_values(array_filter(array_map('trim', preg_split('/\R{2,}/u', $text) ?: [])));
?>
<section class="section section--about-page" aria-labelledby="about-heading">
    <div class="container section__head section__head--center">
        <p class="pill pill--olive">
            <img src="<?= e(asset('img/icons/badge-section.svg')) ?>" alt="" width="50" height="32">
            <span><?= e(content('about.badge')) ?></span>
        </p>
        <h2 id="about-heading" class="section__title"><?= e(content('about.heading')) ?></h2>
    </div>

    <div class="container about__grid about-page__intro">
        <div class="about__copy about-page__copy">
            <?php foreach ($paragraphs(content('about.intro')) as $p): ?>
                <p><?= e($p) ?></p>
            <?php endforeach; ?>
        </div>
        <img class="about__map" src="<?= e(asset('img/site/bangladesh-map.png')) ?>" alt="<?= e(content('home.about.map_alt')) ?>" width="400" height="510" loading="lazy">
    </div>

    <div class="container about-page__sections">
        <?php foreach ([1, 2, 3, 4] as $n): ?>
            <article class="about-page__block">
                <h3><?= e(content("about.s{$n}.title")) ?></h3>
                <?php foreach ($paragraphs(content("about.s{$n}.body")) as $p): ?>
                    <p><?= e($p) ?></p>
                <?php endforeach; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>
