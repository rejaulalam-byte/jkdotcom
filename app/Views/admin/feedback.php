<?php
/**
 * মতামতের ইনবক্স: ট্যাব (সব / নতুন / রাখা), খোঁজা, প্রতিটি মতামত খুলে পড়া যায় (<details>), পড়া/রাখা/মোছার বাটন।
 *
 * @var string $tab        '' | 'new' | 'kept'
 * @var string $q
 * @var list<array<string,mixed>> $rows
 * @var int $total
 * @var int $page
 * @var int $pages
 * @var array<string,int> $counts
 * @var int $open          যে মতামতটা খোলা অবস্থায় দেখাতে হবে (০ = কোনোটা নয়)
 */
use App\Models\FeedbackRepository;

$base = admin_base() . '/feedback';
$url = static function (array $override = []) use ($base, $tab, $q, $page): string {
    $params = array_merge(['t' => $tab, 'q' => $q, 'p' => $page > 1 ? (string) $page : ''], $override);
    $params = array_filter($params, static fn ($v): bool => $v !== '' && $v !== null);

    return $base . ($params === [] ? '' : '?' . http_build_query($params));
};
?>
<div class="adm-toprow">
    <nav class="adm-tabs" aria-label="মতামতের ট্যাব">
        <?php foreach (FeedbackRepository::TABS as $key => $label): ?>
            <a href="<?= e($url(['t' => $key, 'p' => ''])) ?>"<?= $tab === $key ? ' aria-current="true"' : '' ?>><?= e($label) ?> <b><?= e(bn((int) ($counts[$key] ?? 0))) ?></b></a>
        <?php endforeach; ?>
    </nav>
    <form class="adm-filter" action="<?= e($base) ?>" method="get">
        <?php if ($tab !== ''): ?><input type="hidden" name="t" value="<?= e($tab) ?>"><?php endif; ?>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="নাম, ইমেইল, বিষয় বা লেখা" maxlength="100">
        <button class="adm-small" type="submit">খুঁজুন</button>
        <?php if ($q !== '' || $tab !== ''): ?>
            <a class="adm-link" href="<?= e($base) ?>">সব ফিল্টার মুছুন</a>
        <?php endif; ?>
    </form>
</div>

<p class="adm-muted adm-count"><?= e(bn($total)) ?>টি মতামত পাওয়া গেছে</p>

<?php foreach ($rows as $r): ?>
    <?php
    $unread = (int) $r['is_read'] === 0;
    $kept = (int) $r['is_kept'] === 1;
    $action = static function (string $do, string $label, string $class = '', string $confirm = '') use ($base, $r, $url): string {
        return '<form action="' . e($base) . '/action" method="post"' . ($confirm !== '' ? ' data-confirm="' . e($confirm) . '"' : '') . '>'
            . csrf_field() . '<input type="hidden" name="id" value="' . (int) $r['id'] . '"><input type="hidden" name="back" value="' . e($url()) . '">'
            . '<button class="adm-small' . ($class !== '' ? ' ' . $class : '') . '" type="submit" name="do" value="' . e($do) . '">' . e($label) . '</button></form>';
    };
    ?>
    <details class="adm-msg<?= $unread ? ' is-unread' : '' ?>"<?= $open === (int) $r['id'] ? ' open' : '' ?>>
        <summary>
            <span class="adm-msg__subject"><?= e((string) ($r['subject'] !== '' ? $r['subject'] : '(বিষয় নেই)')) ?></span>
            <span class="adm-msg__meta"><?= e((string) $r['name']) ?> · <?= e((string) $r['created_at']) ?></span>
            <?php if ($unread): ?><span class="adm-status is-pending">নতুন</span><?php endif; ?>
            <?php if ($kept): ?><span class="adm-status is-approved">রাখা</span><?php endif; ?>
        </summary>
        <div class="adm-msg__body">
            <p class="adm-msg__who">
                <b><?= e((string) $r['name']) ?></b>
                <?php if ((string) $r['email'] !== ''): ?> · <a href="mailto:<?= e((string) $r['email']) ?>"><?= e((string) $r['email']) ?></a><?php endif; ?>
                <?php if ((string) $r['phone'] !== ''): ?> · <a href="tel:<?= e((string) $r['phone']) ?>"><?= e((string) $r['phone']) ?></a><?php endif; ?>
            </p>
            <p class="adm-msg__text"><?= nl2br(e((string) $r['message'])) ?></p>
            <div class="adm-actions">
                <?= $unread ? $action('read', 'পড়া হয়েছে', 'adm-small--go') : $action('unread', 'আবার নতুন করুন') ?>
                <?= $kept ? $action('unkeep', '"রাখা" তুলুন') : $action('keep', 'রাখুন') ?>
                <?= $action('delete', 'চিরতরে মুছুন', 'adm-small--warn', 'এই মতামত চিরতরে মুছে যাবে, ফেরানো যাবে না। মুছবেন?') ?>
            </div>
        </div>
    </details>
<?php endforeach; ?>
<?php if ($rows === []): ?>
    <p>এই শর্তে কোনো মতামত নেই।</p>
<?php endif; ?>

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
