<?php
/**
 * প্রতিটি ভেতরের পেইজের ওপরের টিল শিরোনাম-ব্যানার।
 *
 * @var string $heading                     বড় সাদা শিরোনাম
 * @var list<array{0:?string,1:string}> $crumbs  ব্রেডক্রাম্ব: [url|null, নাম]
 */
?>
<section class="page-banner" aria-labelledby="page-title">
    <div class="container">
        <h1 id="page-title" class="page-banner__title"><?= e($heading) ?></h1>
        <nav class="crumbs" aria-label="ব্রেডক্রাম্ব">
            <ol>
                <?php foreach ($crumbs as [$href, $label]): ?>
                    <li><?php if ($href !== null): ?><a href="<?= e($href) ?>"><?= e($label) ?></a><?php else: ?><span aria-current="page"><?= e($label) ?></span><?php endif; ?></li>
                <?php endforeach; ?>
            </ol>
        </nav>
    </div>
</section>
