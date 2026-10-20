<?php
/**
 * Social media icons. Only the ones that have an address (admin: "site.social.*") are
 * shown; with no address at all nothing is printed.
 *
 * @var string $socialClass  extra CSS class for the list
 */
$socialLinks = social_links();
?>
<?php if ($socialLinks !== []): ?>
<ul class="social <?= e($socialClass ?? '') ?>">
    <?php foreach ($socialLinks as $link): ?>
        <li>
            <a href="<?= e($link['href']) ?>" target="_blank" rel="noopener noreferrer me" aria-label="<?= e($link['label']) ?>">
                <img src="<?= e(asset('img/icons/' . $link['key'] . '.svg')) ?>" alt="" width="25" height="25" loading="lazy">
            </a>
        </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>
