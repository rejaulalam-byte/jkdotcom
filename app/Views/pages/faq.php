<?php
/**
 * প্রশ্ন ও উত্তর পেইজ (Figma node 2883-79): শিরোনাম ও পরিচিতি-লেখার নিচে বাঁয়ে ২০টি প্রশ্ন-উত্তর,
 * ডানে মতামতের ফর্ম (পেইজ স্ক্রল করলেও পাশে আটকে থাকে)।
 *
 * @var string $heading
 * @var list<int> $faqKeys
 * @var string|null $feedbackOk
 * @var list<string> $feedbackErrors
 * @var array<string,string> $old
 */
$crumbs = [['/', 'হোম'], [null, 'প্রশ্ন ও উত্তর']];
require BASE_PATH . '/app/Views/partials/page-banner.php';
?>
<section class="section section--faq" aria-labelledby="faq-page-title">
    <div class="container">
        <h2 class="faq-page__title" id="faq-page-title"><?= e(content('home.faq.title')) ?></h2>
        <p class="faq-page__intro"><?= e(content('faq.intro')) ?></p>

        <div class="faq-page__grid">
            <?php $faqButton = false; require BASE_PATH . '/app/Views/partials/faq-list.php'; ?>
            <?php $feedbackReturn = '/faq#feedback'; $feedbackClass = 'feedback--faq'; require BASE_PATH . '/app/Views/partials/feedback-form.php'; ?>
        </div>
    </div>
</section>
