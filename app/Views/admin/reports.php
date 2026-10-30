<?php
/**
 * রিপোর্টের পেইজ: প্রতিটি রিপোর্ট একটা বাক্স (শিরোনাম + এক লাইনের বিবরণ); ক্লিকে ওই রিপোর্টের আলাদা পেইজ খোলে।
 *
 * @var array<string,array{0:string,1:string}> $reports
 */
$n = 0;
?>
<p class="adm-muted">যে রিপোর্ট দেখতে চান তার বাক্সে ক্লিক করুন। প্রতিটি আলাদা পেইজে খোলে, সেখানে তারিখ বেছে নেওয়া যায়।</p>
<div class="adm-reportgrid">
    <?php foreach ($reports as $id => [$title, $about]): ?>
        <a class="adm-reportbox" href="<?= e(admin_base() . '/reports/' . $id) ?>">
            <span class="adm-reportbox__no"><?= e(bn(++$n)) ?></span>
            <span class="adm-reportbox__title"><?= e($title) ?></span>
            <span class="adm-reportbox__about"><?= e($about) ?></span>
        </a>
    <?php endforeach; ?>
</div>
