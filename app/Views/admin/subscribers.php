<?php
/**
 * ফুটারের ইমেইল-বাক্সে জমা পড়া ইমেইলের তালিকা: খোঁজা, CSV ডাউনলোড, একটা মোছা।
 *
 * @var string $q
 * @var list<array<string,mixed>> $rows
 * @var int $total
 * @var int $page
 * @var int $pages
 */
$base = admin_base() . '/subscribers';
$url = static function (array $override = []) use ($base, $q, $page): string {
    $params = array_merge(['q' => $q, 'p' => $page > 1 ? (string) $page : ''], $override);
    $params = array_filter($params, static fn ($v): bool => $v !== '' && $v !== null);

    return $base . ($params === [] ? '' : '?' . http_build_query($params));
};
?>
<div class="adm-toprow">
    <form class="adm-filter" action="<?= e($base) ?>" method="get">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="ইমেইলের অংশ" maxlength="100">
        <button class="adm-small" type="submit">খুঁজুন</button>
        <?php if ($q !== ''): ?><a class="adm-link" href="<?= e($base) ?>">খোঁজা মুছুন</a><?php endif; ?>
    </form>
    <a class="adm-small adm-small--go" href="<?= e($url(['csv' => '1', 'p' => ''])) ?>">⬇ CSV ডাউনলোড<?= $q !== '' ? ' (এই খোঁজার)' : '' ?></a>
</div>

<p class="adm-muted adm-count"><?= e(bn($total)) ?>টি ইমেইল জমা আছে। এগুলো ফুটারের "আপনার ইমেইল" বাক্স থেকে এসেছে।</p>

<div class="adm-tablewrap">
    <table class="adm-table">
        <thead><tr><th>ক্রম</th><th>ইমেইল</th><th>কবে জমা পড়েছে</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($rows as $i => $r): ?>
                <tr>
                    <td><?= e(bn(($page - 1) * 50 + $i + 1)) ?></td>
                    <td><a href="mailto:<?= e((string) $r['email']) ?>"><?= e((string) $r['email']) ?></a></td>
                    <td><?= e((string) $r['created_at']) ?></td>
                    <td>
                        <form action="<?= e($base) ?>/delete" method="post" data-confirm="এই ইমেইল চিরতরে মুছে যাবে। মুছবেন?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                            <button class="adm-small adm-small--warn" type="submit">মুছুন</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?>
                <tr><td colspan="4">এই শর্তে কোনো ইমেইল নেই।</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($pages > 1): ?>
    <nav class="adm-pages" aria-label="পৃষ্ঠা">
        <?php for ($n = 1; $n <= $pages; $n++): ?>
            <?php if ($n === $page): ?>
                <span class="is-current" aria-current="page"><?= e(bn($n)) ?></span>
            <?php elseif ($n === 1 || $n === $pages || abs($n - $page) <= 2): ?>
                <a href="<?= e($url(['p' => $n > 1 ? (string) $n : ''])) ?>"><?= e(bn($n)) ?></a>
            <?php elseif (abs($n - $page) === 3): ?>
                <span>…</span>
            <?php endif; ?>
        <?php endfor; ?>
    </nav>
<?php endif; ?>
