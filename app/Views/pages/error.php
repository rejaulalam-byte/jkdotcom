<?php
/**
 * @var string $title
 * @var int $code
 */
$heading = $title;
$crumbs = [['/', 'হোম'], [null, $title]];
require BASE_PATH . '/app/Views/partials/page-banner.php';
?>
<section class="section section--plain">
    <div class="container center">
        <p class="error-code" aria-hidden="true"><?= e(bn($code)) ?></p>
        <h2 class="section__title"><?= e($title) ?></h2>
        <p class="section__text">আপনি যে পেজটি খুঁজছেন তা এখানে নেই, অথবা সাময়িক সমস্যা হয়েছে। অনুগ্রহ করে হোম পেজ থেকে আবার চেষ্টা করুন।</p>
        <p><a class="btn btn--teal-soft" href="/">হোম পেজে ফিরে যান</a></p>
    </div>
</section>
