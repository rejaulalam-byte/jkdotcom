<?php
/**
 * হোম পেইজ — Figma ফ্রেম "JoruriKaj.Com-Home" অনুসরণ করে।
 *
 * @var list<array{id:int,title:string}> $jobs
 * @var list<array{title:string,image:string,width:int,height:int,link:?string}> $ads
 * @var list<array{name:string,icon:?string}> $services
 * @var string|null $feedbackOk
 * @var list<string> $feedbackErrors
 * @var array<string,string> $old
 */
$stepKeys = [1, 2, 3, 4];
$faqKeys = [1, 2, 3, 4, 5];
?>

<!-- 1. HERO ---------------------------------------------------------- -->
<section class="hero" aria-labelledby="hero-title">
    <div class="container hero__inner">
        <div class="hero__copy">
            <p class="pill pill--amber">
                <img src="<?= e(asset('img/icons/badge-hero.svg')) ?>" alt="" width="50" height="32">
                <span data-fly><?= e(content('home.hero.badge')) ?></span>
            </p>
            <h1 id="hero-title" class="hero__title">
                <span class="hero__title-1" data-fly><?= e(content('home.hero.title_1')) ?></span>
                <span class="hero__title-2" data-fly><?= e(content('home.hero.title_2')) ?></span>
            </h1>
            <p class="hero__text" data-fly><?= e(content('home.hero.text')) ?></p>
            <a class="btn btn--light" href="/services"><?= e(content('home.hero.button')) ?></a>
        </div>

        <aside class="today" aria-labelledby="today-title">
            <h2 id="today-title" class="today__head"><?= e(content('home.today.title')) ?></h2>
            <div class="today__body">
                <?php if ($jobs === []): ?>
                    <p class="today__empty"><?= e(content('home.today.empty')) ?></p>
                <?php else: ?>
                    <ol class="ticker" data-page-size="8" data-rise="4000" data-hold="5500">
                        <?php foreach ($jobs as $i => $job): ?>
                            <li><a href="/today#job-<?= e($job['id']) ?>"><span class="ticker__no"><?= e(bn($i + 1)) ?>.</span> <?= e($job['title']) ?></a></li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
                <p class="today__note"><span><?= rich(content('home.today.note')) ?></span></p>
            </div>
        </aside>
    </div>
</section>

<!-- 2. ADVERTISEMENTS + REPRESENTATIVE BAR --------------------------- -->
<section class="ads" aria-label="<?= e(content('home.ads.label')) ?>">
    <?php // সক্রিয় বিজ্ঞাপন থাকলে এবং অ্যাডমিন লুকিয়ে না রাখলে (home.ads.show) তবেই সাদা বক্স ও স্ট্রিপ ?>
    <?php if ($ads !== [] && content('home.ads.show', '1') !== '0'): ?>
    <div class="container ads__card">
        <div class="ad-strip" data-ad-strip>
            <ul class="ad-strip__track">
                <?php foreach ($ads as $ad): ?>
                    <li class="ad-strip__item">
                        <?php if ($ad['link'] !== null): ?>
                            <a href="<?= e($ad['link']) ?>" target="_blank" rel="noopener noreferrer nofollow sponsored">
                                <img src="<?= e($ad['image']) ?>" alt="<?= e($ad['title']) ?>" width="<?= e($ad['width']) ?>" height="<?= e($ad['height']) ?>">
                            </a>
                        <?php else: ?>
                            <img src="<?= e($ad['image']) ?>" alt="<?= e($ad['title']) ?>" width="<?= e($ad['width']) ?>" height="<?= e($ad['height']) ?>">
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>
    <div class="rep-bar" data-marquee>
        <p class="rep-bar__text"><span class="rep-bar__move"><?= colored(content('home.rep.text')) ?></span></p>
    </div>
</section>

<!-- 3. SERVICES ------------------------------------------------------- -->
<section class="section services" aria-labelledby="services-title">
    <div class="container section__head section__head--center">
        <p class="pill pill--olive">
            <img src="<?= e(asset('img/icons/badge-section.svg')) ?>" alt="" width="50" height="32">
            <span><?= e(content('home.services.badge')) ?></span>
        </p>
        <h2 id="services-title" class="section__title"><?= e(content('home.services.title')) ?></h2>
        <p class="section__text"><?= e(content('home.services.text_1')) ?> <?= e(content('home.services.text_2')) ?></p>
    </div>

    <?php
    // সব সেবা দুই সারিতে, নিজে নিজে ডান থেকে বাঁয়ে চলে (app.js);
    // JavaScript ছাড়া বা "reduce motion"-এ সাধারণ পাশে-স্ক্রল করা সারি।
    $serviceRows = $services === [] ? [] : array_chunk($services, (int) ceil(count($services) / 2));
    ?>
    <div class="service-rows">
        <?php foreach ($serviceRows as $r => $rowServices): ?>
            <div class="service-row" data-service-row="<?= e($r + 1) ?>">
                <ul class="service-row__track">
                    <?php foreach ($rowServices as $service): ?>
                        <li class="service-card">
                            <a href="/services/<?= e(rawurlencode($service['slug'])) ?>">
                                <span class="service-card__icon">
                                    <?php if ($service['icon'] !== null): ?>
                                        <img src="<?= e($service['icon']) ?>" alt="" width="44" height="44">
                                    <?php endif; ?>
                                </span>
                                <span class="service-card__name"><?= e($service['name']) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="container">
        <p class="center"><a class="btn btn--teal-soft" href="/services"><?= e(content('home.services.button')) ?></a></p>
    </div>
</section>

<!-- 4. ABOUT ---------------------------------------------------------- -->
<section class="section about" aria-labelledby="about-title">
    <div class="container section__head section__head--center">
        <p class="pill pill--olive">
            <img src="<?= e(asset('img/icons/badge-section.svg')) ?>" alt="" width="50" height="32">
            <span><?= e(content('home.about.badge')) ?></span>
        </p>
        <h2 id="about-title" class="section__title"><?= e(content('home.about.title')) ?></h2>
    </div>
    <div class="container about__grid">
        <div class="about__copy">
            <p><?= e(content('home.about.text')) ?></p>
            <p><a class="btn btn--teal-soft" href="/about"><?= e(content('home.about.button')) ?></a></p>
        </div>
        <img class="about__map" src="<?= e(asset('img/site/bangladesh-map.png')) ?>" alt="<?= e(content('home.about.map_alt')) ?>" width="400" height="510" loading="lazy">
    </div>
</section>

<!-- 5. HOW IT WORKS --------------------------------------------------- -->
<section class="section steps" aria-labelledby="steps-title">
    <div class="container section__head">
        <p class="pill pill--olive">
            <img src="<?= e(asset('img/icons/badge-section.svg')) ?>" alt="" width="50" height="32">
            <span><?= e(content('home.steps.badge')) ?></span>
        </p>
        <h2 id="steps-title" class="section__title"><?= e(content('home.steps.title')) ?></h2>
        <p class="section__text"><?= e(content('home.steps.text')) ?></p>
    </div>
    <div class="container">
        <ol class="step-grid">
            <?php foreach ($stepKeys as $n): ?>
                <li class="step-card">
                    <span class="step-card__no" aria-hidden="true"><?= e(bn($n)) ?></span>
                    <h3><?= e(content('home.steps.' . $n . '.title')) ?></h3>
                    <p><?= e(content('home.steps.' . $n . '.text')) ?></p>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>

<!-- 6. FAQ + FEEDBACK -------------------------------------------------- -->
<section class="section faq" id="faq" aria-labelledby="faq-title">
    <div class="container section__head section__head--center">
        <p class="pill pill--olive">
            <img src="<?= e(asset('img/icons/badge-section.svg')) ?>" alt="" width="50" height="32">
            <span><?= e(content('home.faq.badge')) ?></span>
        </p>
        <h2 id="faq-title" class="section__title"><?= e(content('home.faq.title')) ?></h2>
        <p class="section__text"><?= e(content('home.faq.text')) ?></p>
    </div>

    <div class="container faq__grid">
        <?php require BASE_PATH . '/app/Views/partials/faq-list.php'; ?>

        <?php $feedbackReturn = '/#feedback'; require BASE_PATH . '/app/Views/partials/feedback-form.php'; ?>
    </div>
</section>

<!-- 7. LOCATION MAP ---------------------------------------------------- -->
<section class="map" aria-label="<?= e(content('home.map.alt')) ?>">
    <a href="https://www.google.com/maps?q=<?= e(rawurlencode(str_replace(' ', '', content('site.coordinates')))) ?>" target="_blank" rel="noopener noreferrer">
        <img src="<?= e(asset('img/site/map-location.png')) ?>" alt="<?= e(content('home.map.alt')) ?>" width="1221" height="580" loading="lazy">
    </a>
</section>
