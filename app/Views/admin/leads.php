<?php
/**
 * আগ্রহীর তালিকা: কে (নাম, মোবাইল) কোন বিজ্ঞপ্তি/কর্মীর কোন যোগাযোগ (ফোন/ইমেইল) দেখতে চেয়েছেন, কখন।
 *
 * @var array<string,string> $tabs
 * @var string $type
 * @var string $q
 * @var list<array<string,mixed>> $rows
 * @var int $total
 * @var int $page
 * @var int $pages
 * @var array<string,int> $counts
 */
$base = admin_base() . '/leads';
$url = static function (array $override = []) use ($base, $type, $q, $page): string {
    $params = array_merge(['t' => $type, 'q' => $q, 'p' => $page > 1 ? (string) $page : ''], $override);
    $params = array_filter($params, static fn ($v): bool => $v !== '' && $v !== null);

    return $base . ($params === [] ? '' : '?' . http_build_query($params));
};
?>
<p class="adm-muted">
    দর্শক ফোন বা ইমেইল দেখতে চাইলে "আমি আগ্রহী" বলে নিজের নাম ও মোবাইল রেখে যান। দেওয়া মোবাইল যাচাই করা নয়; দরকারে তাঁকে বা
    বিজ্ঞপ্তিদাতা/কর্মীকে ফোন করে জেনে নিন।
</p>

<div class="adm-toprow">
    <nav class="adm-tabs" aria-label="ধরন">
        <?php foreach ($tabs as $key => $label): ?>
            <a href="<?= e($url(['t' => $key, 'p' => ''])) ?>"<?= $type === $key ? ' aria-current="true"' : '' ?>><?= e($label) ?> <b><?= e(bn((int) ($counts[$key] ?? 0))) ?></b></a>
        <?php endforeach; ?>
    </nav>
    <form class="adm-filter" action="<?= e($base) ?>" method="get">
        <?php if ($type !== ''): ?><input type="hidden" name="t" value="<?= e($type) ?>"><?php endif; ?>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="আগ্রহীর নাম বা মোবাইল" maxlength="100">
        <button class="adm-small" type="submit">খুঁজুন</button>
        <?php if ($q !== '' || $type !== ''): ?>
            <a class="adm-link" href="<?= e($base) ?>">সব ফিল্টার মুছুন</a>
        <?php endif; ?>
    </form>
</div>

<p class="adm-muted adm-count"><?= e(bn($total)) ?> জন পাওয়া গেছে</p>

<div class="adm-tablewrap">
    <table class="adm-table">
        <thead>
            <tr><th>সময়</th><th>আগ্রহীর নাম</th><th>আগ্রহীর মোবাইল</th><th>কীসে আগ্রহী</th><th>কী দেখতে চেয়েছেন</th></tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= e((string) $r['created_at']) ?></td>
                <td><?= e((string) $r['visitor_name']) ?></td>
                <td><a href="tel:<?= e((string) $r['visitor_phone']) ?>"><?= e((string) $r['visitor_phone']) ?></a></td>
                <td>
                    <?php if ($r['target_type'] === 'worker'): ?>
                        কর্মী:
                        <?php if ((string) $r['target_title'] !== ''): ?>
                            <a href="<?= admin_base() ?>/members/<?= (int) $r['target_id'] ?>"><?= e((string) $r['target_title']) ?></a>
                        <?php else: ?>
                            <span class="adm-muted">(মোছা হয়েছে)</span>
                        <?php endif; ?>
                    <?php else: ?>
                        বিজ্ঞপ্তি:
                        <?php if ((string) $r['target_title'] !== ''): ?>
                            <a href="<?= admin_base() ?>/jobs?open=<?= (int) $r['target_id'] ?>"><?= e((string) $r['target_title']) ?></a>
                        <?php else: ?>
                            <span class="adm-muted">(মোছা হয়েছে)</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
                <td><?= $r['kind'] === 'email' ? 'ইমেইল' : 'ফোন নাম্বার' ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($rows === []): ?>
            <tr><td colspan="5">এই শর্তে কেউ নেই।</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($pages > 1): ?>
    <nav class="adm-pages" aria-label="পৃষ্ঠা">
        <?php
        $shown = array_unique(array_filter(array_merge([1, $pages], range($page - 2, $page + 2)), static fn (int $n): bool => $n >= 1 && $n <= $pages));
        sort($shown);
        $prev = 0;
        foreach ($shown as $n):
            if ($prev !== 0 && $n - $prev > 1): ?><span>…</span><?php endif;
            if ($n === $page): ?>
                <span class="is-current" aria-current="page"><?= e(bn($n)) ?></span>
            <?php else: ?>
                <a href="<?= e($url(['p' => $n > 1 ? (string) $n : ''])) ?>"><?= e(bn($n)) ?></a>
            <?php endif;
            $prev = $n;
        endforeach; ?>
    </nav>
<?php endif; ?>
