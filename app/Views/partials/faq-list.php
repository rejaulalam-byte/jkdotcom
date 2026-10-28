<?php
/**
 * প্রশ্ন-উত্তরের তালিকা (সব বন্ধ, একসঙ্গে একটাই খোলা)। হোম ও যোগাযোগ পেইজ একই তালিকা নেয়।
 *
 * @var list<int> $faqKeys    কোন কোন প্রশ্ন (faq.<n>.q / .a)
 * @var bool $faqButton      "আরো জানতে ভিজিট করুন" বাটন দেখাবে কি না (ডিফল্ট: দেখায়)
 */
?>
<div class="faq__list">
    <?php foreach ($faqKeys as $n): ?>
        <?php // লোডের সময় সব বন্ধ; name="faq" = একসঙ্গে একটাই খোলা (পুরনো ব্রাউজারের জন্য app.js একই কাজ করে) ?>
        <details class="faq-item" name="faq">
            <summary>
                <img src="<?= e(asset('img/icons/dot.svg')) ?>" alt="" width="14" height="14">
                <span><?= e(content('faq.' . $n . '.q')) ?></span>
            </summary>
            <p><?= e(content('faq.' . $n . '.a')) ?></p>
        </details>
    <?php endforeach; ?>
    <?php if ($faqButton ?? true): ?>
        <p><a class="btn btn--teal-soft" href="/faq"><?= t('home.faq.button') ?></a></p>
    <?php endif; ?>
</div>
