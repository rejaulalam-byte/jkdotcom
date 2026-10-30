<?php
/**
 * কাজের বিজ্ঞপ্তি: ট্যাব (সব / প্রকাশিত / লুকানো / নিজের লেখা ধরন), খোঁজা, প্রতিটি বিজ্ঞপ্তি খুলে পূর্ণ তথ্য (ফোন-ইমেইলসহ),
 * "কাজের ধরন" ঠিক করা, লুকানো/প্রকাশ, মোছা।
 *
 * @var string $tab
 * @var string $q
 * @var list<array<string,mixed>> $rows
 * @var int $total
 * @var int $page
 * @var int $pages
 * @var array<string,int> $counts
 * @var int $open
 */
use App\Models\GeoRepository;
use App\Models\JobPostRepository;

$base = admin_base() . '/jobs';
$url = static function (array $override = []) use ($base, $tab, $q, $page): string {
    $params = array_merge(['t' => $tab, 'q' => $q, 'p' => $page > 1 ? (string) $page : ''], $override);
    $params = array_filter($params, static fn ($v): bool => $v !== '' && $v !== null);

    return $base . ($params === [] ? '' : '?' . http_build_query($params));
};
?>
<p class="adm-muted">
    বিজ্ঞপ্তি জমা দেওয়ামাত্র সাইটে প্রকাশ হয়ে যায়। অনুচিত বা ভুল কিছু হলে এখানে "লুকান" দিলে তালিকা থেকে সরে যায় (তথ্য থাকে)।
    দর্শক নিজের লেখা "কাজের ধরন" দিলে সেটা "নিজের লেখা ধরন" ট্যাবে আসে; বানান দেখে ঠিক করে নিতে পারেন।
</p>

<p><a class="adm-small adm-small--go" href="<?= e(admin_base() . '/job-add') ?>">+ নতুন বিজ্ঞপ্তি যোগ করুন</a></p>

<div class="adm-toprow">
    <nav class="adm-tabs" aria-label="বিজ্ঞপ্তির ট্যাব">
        <?php foreach (JobPostRepository::TABS as $key => $label): ?>
            <a href="<?= e($url(['t' => $key, 'p' => ''])) ?>"<?= $tab === $key ? ' aria-current="true"' : '' ?>><?= e($label) ?> <b><?= e(bn((int) ($counts[$key] ?? 0))) ?></b></a>
        <?php endforeach; ?>
    </nav>
    <form class="adm-filter" action="<?= e($base) ?>" method="get">
        <?php if ($tab !== ''): ?><input type="hidden" name="t" value="<?= e($tab) ?>"><?php endif; ?>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="ধরন, নাম, মোবাইল বা বিবরণ" maxlength="100">
        <button class="adm-small" type="submit">খুঁজুন</button>
        <?php if ($q !== '' || $tab !== ''): ?>
            <a class="adm-link" href="<?= e($base) ?>">সব ফিল্টার মুছুন</a>
        <?php endif; ?>
    </form>
</div>

<p class="adm-muted adm-count"><?= e(bn($total)) ?>টি বিজ্ঞপ্তি পাওয়া গেছে</p>

<?php foreach ($rows as $r): ?>
    <?php
    $hidden = (string) ($r['status'] ?? 'approved') !== 'approved';
    $area = GeoRepository::names((int) ($r['upazila_id'] ?? 0), (int) ($r['district_id'] ?? 0), (int) ($r['division_id'] ?? 0));
    $action = static function (string $do, string $label, string $class = '', string $confirm = '') use ($base, $r, $url): string {
        return '<form action="' . e($base) . '/action" method="post"' . ($confirm !== '' ? ' data-confirm="' . e($confirm) . '"' : '') . '>'
            . csrf_field() . '<input type="hidden" name="id" value="' . (int) $r['id'] . '"><input type="hidden" name="back" value="' . e($url()) . '">'
            . '<button class="adm-small' . ($class !== '' ? ' ' . $class : '') . '" type="submit" name="do" value="' . e($do) . '">' . e($label) . '</button></form>';
    };
    ?>
    <details class="adm-msg<?= (int) ($r['title_custom'] ?? 0) === 1 ? ' is-unread' : '' ?>"<?= $open === (int) $r['id'] ? ' open' : '' ?>>
        <summary>
            <span class="adm-msg__subject"><?= e((string) $r['title']) ?></span>
            <span class="adm-msg__meta"><?= e((string) $r['contact_name']) ?> · <?= e((string) $r['created_at']) ?></span>
            <?php if ($hidden): ?><span class="adm-status is-rejected">লুকানো</span><?php else: ?><span class="adm-status is-approved">প্রকাশিত</span><?php endif; ?>
            <?php if ((int) ($r['title_custom'] ?? 0) === 1): ?><span class="adm-status is-pending">নিজের লেখা ধরন</span><?php endif; ?>
        </summary>
        <div class="adm-msg__body">
            <p class="adm-msg__who">
                <b><?= e((string) $r['contact_name']) ?></b>
                · <a href="tel:<?= e((string) $r['phone']) ?>"><?= e((string) $r['phone']) ?></a>
                <?php if ((string) ($r['email'] ?? '') !== ''): ?> · <a href="mailto:<?= e((string) $r['email']) ?>"><?= e((string) $r['email']) ?></a><?php endif; ?>
            </p>
            <p class="adm-muted">
                ঠিকানা: <?= e(implode(', ', array_filter([(string) $r['address'], $area['upazila'], $area['district'], $area['division']]))) ?>
                · প্রচার: <?= e((string) ($r['start_date'] ?? '—')) ?><?= (string) ($r['end_date'] ?? '') !== '' ? ' থেকে ' . e((string) $r['end_date']) : '' ?>
            </p>
            <p class="adm-msg__text"><?= nl2br(e((string) $r['details'])) ?></p>
            <?php // অ্যাডমিনের যোগ করা নতুন ঘরে পূরণ করা তথ্য (থাকলে) ?>
            <?php foreach (\App\Models\CustomFields::valuesFor('post', (int) $r['id']) as $cv): ?>
                <p class="adm-muted"><?= e($cv['label']) ?>: <?= nl2br(e($cv['value'])) ?></p>
            <?php endforeach; ?>

            <form class="adm-inline" action="<?= e($base) ?>/action" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <input type="hidden" name="back" value="<?= e($url()) ?>">
                <input type="hidden" name="do" value="title">
                <label for="jt-<?= (int) $r['id'] ?>">কাজের ধরন ঠিক করুন</label>
                <div class="adm-commonrow">
                    <input id="jt-<?= (int) $r['id'] ?>" type="text" name="title" value="<?= e((string) $r['title']) ?>" maxlength="190" required>
                    <button class="adm-small" type="submit">ধরন সংরক্ষণ</button>
                </div>
            </form>

            <div class="adm-actions">
                <a class="adm-small" href="<?= e(admin_base() . '/job-edit/' . (int) $r['id']) ?>">✎ সম্পাদনা (পূর্ণ এডিটর)</a>
                <?= $hidden ? $action('show', 'আবার প্রকাশ করুন', 'adm-small--go') : $action('hide', 'লুকান', '', 'বিজ্ঞপ্তিটি তালিকা থেকে সরে যাবে (তথ্য থাকবে)। লুকাবেন?') ?>
                <?= $action('delete', 'চিরতরে মুছুন', 'adm-small--warn', 'এই বিজ্ঞপ্তি চিরতরে মুছে যাবে, ফেরানো যাবে না। মুছবেন?') ?>
            </div>
        </div>
    </details>
<?php endforeach; ?>
<?php if ($rows === []): ?>
    <p>এই শর্তে কোনো বিজ্ঞপ্তি নেই।</p>
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
