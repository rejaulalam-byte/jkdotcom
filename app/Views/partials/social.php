<?php
/**
 * সোশ্যাল মিডিয়ার আইকন। settings.social_hide_empty = 0 থাকলে সব আইকন দেখায়; ১ হলে শুধু
 * ঠিকানা-আছে এমনগুলো (অ্যাডমিন: "site.social.*"); কোনো ঠিকানাই না থাকলে কিছুই ছাপে না।
 *
 * @var string $socialClass  তালিকার জন্য বাড়তি CSS ক্লাস
 */
$socialLinks = social_links();
?>
<?php if ($socialLinks !== []): ?>
<ul class="social <?= e($socialClass ?? '') ?>">
    <?php foreach ($socialLinks as $link): ?>
        <li>
            <a href="<?= e($link['href']) ?>"<?= $link['href'] !== '#' ? ' target="_blank" rel="noopener noreferrer me"' : '' ?> aria-label="<?= e($link['label']) ?>">
                <img src="<?= e(asset('img/icons/' . $link['key'] . '.svg')) ?>" alt="" width="25" height="25" loading="lazy">
            </a>
        </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>
