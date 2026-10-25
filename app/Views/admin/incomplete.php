<?php
/**
 * অসম্পূর্ণ তথ্য: কর্মীর বাকি তথ্য লাল চিহ্নে; মোবাইল/ইমেইল/ঠিকানা এখানেই বসানো যায়; ইমেইল থাকলে "তথ্য পূর্ণ করুন" ইমেইল।
 *
 * @var string $filter            '' (মূল চারটার যেকোনোটা) বা IncompleteInfo-র একটা কী
 * @var string $q
 * @var list<array<string,mixed>> $rows
 * @var int $total
 * @var int $page
 * @var int $pages
 * @var array<string,int> $counts  কী => সংখ্যা ('' = সব)
 */
use App\Models\IncompleteInfo;

$base = admin_base() . '/incomplete';
$url = static function (array $override = []) use ($base, $filter, $q, $page): string {
    $params = array_merge(['f' => $filter, 'q' => $q, 'p' => $page > 1 ? (string) $page : ''], $override);
    $params = array_filter($params, static fn ($v): bool => $v !== '' && $v !== null);

    return $base . ($params === [] ? '' : '?' . http_build_query($params));
};
$tabs = [['', 'সব', (int) ($counts[''] ?? 0)]];
foreach (IncompleteInfo::LABELS as $key => $label) {
    $tabs[] = [$key, $label . ' নেই', (int) ($counts[$key] ?? 0)];
}
?>
<p class="adm-muted">
    অনুমোদিত কর্মীদের মধ্যে যার তথ্য বাকি, তাকে এখানে লাল চিহ্নে দেখানো হয়। "সব" ট্যাবে মোবাইল, ইমেইল, ঠিকানা ও এলাকার যেকোনোটা বাকি থাকলে আসে;
    অভিজ্ঞতা ও পরিচিতি ঐচ্ছিক, তাই সেগুলো আলাদা ট্যাবে। মোবাইল, ইমেইল ও ঠিকানা এখানেই বসিয়ে "সংরক্ষণ" চাপুন; বাকিগুলো "সম্পাদনা"-তে।
</p>

<div class="adm-toprow">
    <nav class="adm-tabs" aria-label="কোন তথ্য বাকি">
        <?php foreach ($tabs as [$key, $label, $count]): ?>
            <a href="<?= e($url(['f' => $key, 'p' => ''])) ?>"<?= $filter === $key ? ' aria-current="true"' : '' ?>><?= e($label) ?> <b><?= e(bn($count)) ?></b></a>
        <?php endforeach; ?>
    </nav>
    <form class="adm-filter" action="<?= e($base) ?>" method="get">
        <?php if ($filter !== ''): ?><input type="hidden" name="f" value="<?= e($filter) ?>"><?php endif; ?>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="নাম, মোবাইল বা ইমেইল" maxlength="100">
        <button class="adm-small" type="submit">খুঁজুন</button>
        <?php if ($q !== '' || $filter !== ''): ?>
            <a class="adm-link" href="<?= e($base) ?>">সব ফিল্টার মুছুন</a>
        <?php endif; ?>
    </form>
</div>

<p class="adm-muted adm-count"><?= e(bn($total)) ?> জন পাওয়া গেছে</p>

<div class="adm-tablewrap">
    <table class="adm-table adm-table--incomplete">
        <thead>
            <tr><th>নাম</th><th>মোবাইল</th><th>ইমেইল</th><th>ঠিকানা</th><th>আরও বাকি</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <?php
            $missing = IncompleteInfo::missing($r);
            $has = static fn (string $key): bool => in_array($key, $missing, true);
            $formId = 'inc-' . (int) $r['id'];
            $others = array_values(array_diff($missing, IncompleteInfo::INLINE));
            $canFill = array_intersect($missing, IncompleteInfo::INLINE) !== [];
            ?>
            <tr>
                <td><a href="<?= admin_base() ?>/members/<?= (int) $r['id'] ?>"><?= e((string) $r['name']) ?></a></td>
                <td>
                    <?php if ($has('phone')): ?>
                        <input class="adm-missing" form="<?= e($formId) ?>" type="tel" name="phone" maxlength="14" inputmode="numeric" placeholder="মোবাইল নেই" aria-label="মোবাইল">
                        <?php if ((string) ($r['phone_raw'] ?? '') !== ''): ?><small class="adm-muted">(আগে লেখা ছিল: <?= e((string) $r['phone_raw']) ?>)</small><?php endif; ?>
                    <?php else: ?>
                        <?= e((string) $r['phone']) ?>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($has('email')): ?>
                        <input class="adm-missing" form="<?= e($formId) ?>" type="email" name="email" maxlength="150" placeholder="ইমেইল নেই" aria-label="ইমেইল">
                    <?php else: ?>
                        <?= e((string) $r['email']) ?>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($has('address')): ?>
                        <input class="adm-missing" form="<?= e($formId) ?>" type="text" name="address" maxlength="250" placeholder="ঠিকানা নেই" aria-label="ঠিকানা">
                    <?php else: ?>
                        <?= e((string) $r['address']) ?>
                    <?php endif; ?>
                </td>
                <td>
                    <?php foreach ($others as $key): ?><span class="adm-red adm-chip"><?= e(IncompleteInfo::LABELS[$key]) ?> নেই</span> <?php endforeach; ?>
                    <?php if ($others === []): ?><span class="adm-muted">—</span><?php endif; ?>
                </td>
                <td class="adm-actions">
                    <form id="<?= e($formId) ?>" action="<?= e($base) ?>/save" method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <input type="hidden" name="back" value="<?= e($url()) ?>">
                        <?php if ($canFill): ?><button class="adm-small adm-small--go" type="submit">সংরক্ষণ</button><?php endif; ?>
                        <?php if ((string) ($r['email'] ?? '') !== '' && $missing !== []): ?>
                            <button class="adm-small" type="submit" formaction="<?= e($base) ?>/mail" title="কর্মীকে ইমেইলে জানান কোন তথ্য বাকি">তথ্য পূর্ণ করুন ইমেইল</button>
                        <?php endif; ?>
                        <a class="adm-small" href="<?= admin_base() ?>/member-edit/<?= (int) $r['id'] ?>">সম্পাদনা</a>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($rows === []): ?>
            <tr><td colspan="6">এই শর্তে কাউকে পাওয়া যায়নি — সব তথ্য পূর্ণ।</td></tr>
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
