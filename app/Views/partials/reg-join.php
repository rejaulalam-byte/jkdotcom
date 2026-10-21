<?php
/** "আপনার অংশগ্রহণ" ব্যান্ড ও দুটো ছবির কার্ড; দুই নির্দেশিকা-পেইজেই ব্যবহার হয় (Figma 2683-148 / 2683-305)। */
?>
<section class="section reg-join" aria-labelledby="reg-join-title">
    <div class="container">
        <p class="pill pill--olive">
            <img src="<?= e(asset('img/icons/badge-section.svg')) ?>" alt="" width="50" height="32">
            <span><?= e(content('reg.pro.badge')) ?></span>
        </p>
        <h2 id="reg-join-title" class="reg-join__title"><?= e(content('reg.pro.join.title')) ?></h2>
        <p class="reg-join__text"><?= e(content('reg.pro.join.text')) ?></p>
        <div class="reg-cards">
            <article class="reg-card">
                <img src="<?= e(asset('img/site/reg-professional.jpg')) ?>" alt="" width="686" height="432" loading="lazy">
                <h3><?= e(content('reg.pro.card1.title')) ?></h3>
                <p><?= e(content('reg.pro.card1.text')) ?></p>
                <a href="/registration/professional/form"><?= e(content('reg.pro.card1.link')) ?><img src="<?= e(asset('img/icons/arrow-card.svg')) ?>" alt="" width="18" height="18"></a>
            </article>
            <article class="reg-card">
                <img src="<?= e(asset('img/site/reg-job-poster.jpg')) ?>" alt="" width="686" height="432" loading="lazy">
                <h3><?= e(content('reg.pro.card2.title')) ?></h3>
                <p><?= e(content('reg.pro.card2.text')) ?></p>
                <a href="/today/post"><?= e(content('reg.pro.card2.link')) ?><img src="<?= e(asset('img/icons/arrow-card.svg')) ?>" alt="" width="18" height="18"></a>
            </article>
        </div>
    </div>
</section>
