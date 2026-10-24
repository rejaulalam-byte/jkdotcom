<?php
/**
 * কর্মী বা প্রতিনিধির তালিকা: অবস্থার ট্যাব (সংখ্যাসহ), খোঁজা, বাড়তি ফিল্টার, টেবিল, পৃষ্ঠা-নাম্বার।
 *
 * @var string $role                   'worker' | 'agent'
 * @var array{status:string,q:string,source:string,nophone:bool} $filter
 * @var list<array<string,mixed>> $rows
 * @var int $total
 * @var int $page
 * @var int $pages
 * @var array<string,int> $counts      অবস্থা => সংখ্যা (এই ভূমিকার)
 * @var array<string,string> $services slug => পেশার নাম
 */
use App\Models\GeoRepository;
use App\Models\MemberStatus;
use App\Models\UserRepository;

$base = $role === 'agent' ? '/admin/agents' : '/admin/workers';
// বর্তমান ফিল্টার ধরে রেখে লিংক বানায়; $override-এর মান বদলে যায়, খালি মান বাদ
$url = static function (array $override = []) use ($base, $filter, $page): string {
    $params = array_merge(['status' => $filter['status'], 'q' => $filter['q'], 'source' => $filter['source'], 'nophone' => $filter['nophone'] ? '1' : '', 'p' => $page > 1 ? (string) $page : ''], $override);
    $params = array_filter($params, static fn ($v): bool => $v !== '' && $v !== null);

    return $base . ($params === [] ? '' : '?' . http_build_query($params));
};
$allCount = array_sum(array_intersect_key($counts, array_flip(UserRepository::LISTED_STATUSES)));
$tabs = [['', 'সব', $allCount]];
foreach (MemberStatus::LABELS as $key => $label) {
    $tabs[] = [$key, $label, (int) ($counts[$key] ?? 0)];
}
?>
<nav class="adm-tabs" aria-label="অবস্থা">
    <?php foreach ($tabs as [$key, $label, $count]): ?>
        <a href="<?= e($url(['status' => $key, 'p' => ''])) ?>"<?= $filter['status'] === $key ? ' aria-current="true"' : '' ?>><?= e($label) ?> <b><?= e(bn($count)) ?></b></a>
    <?php endforeach; ?>
</nav>

<form class="adm-filter" action="<?= e($base) ?>" method="get">
    <?php if ($filter['status'] !== ''): ?><input type="hidden" name="status" value="<?= e($filter['status']) ?>"><?php endif; ?>
    <input type="search" name="q" value="<?= e($filter['q']) ?>" placeholder="নাম, মোবাইল, ইমেইল বা ঠিকানা" maxlength="100">
    <select name="source" aria-label="কোথা থেকে এসেছে">
        <option value="">সব উৎস</option>
        <option value="new"<?= $filter['source'] === 'new' ? ' selected' : '' ?>>নতুন সাইটে নিবন্ধিত</option>
        <option value="legacy"<?= $filter['source'] === 'legacy' ? ' selected' : '' ?>>পুরনো সাইট থেকে আনা</option>
    </select>
    <label class="adm-check"><input type="checkbox" name="nophone" value="1"<?= $filter['nophone'] ? ' checked' : '' ?>> মোবাইল নেই</label>
    <button class="adm-small" type="submit">খুঁজুন</button>
    <?php if ($filter['q'] !== '' || $filter['source'] !== '' || $filter['nophone'] || $filter['status'] !== ''): ?>
        <a class="adm-link" href="<?= e($base) ?>">সব ফিল্টার মুছুন</a>
    <?php endif; ?>
</form>

<p class="adm-muted adm-count"><?= e(bn($total)) ?> জন পাওয়া গেছে</p>

<div class="adm-tablewrap">
    <table class="adm-table">
        <thead>
            <tr>
                <th>নাম</th><th>মোবাইল</th><th>ইমেইল</th>
                <?php if ($role === 'worker'): ?><th>পেশা</th><?php endif; ?>
                <th>এলাকা</th><th>অবস্থা</th><th>উৎস</th><th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <?php
            $area = GeoRepository::names((int) $r['upazila_id'], (int) $r['district_id'], (int) $r['division_id']);
            $profession = (string) ($r['service_custom'] ?? '') !== '' ? $r['service_custom'] . ' (নিজের লেখা)' : ($services[(string) ($r['service_slug'] ?? '')] ?? '—');
            ?>
            <tr>
                <td><a href="/admin/members/<?= (int) $r['id'] ?>"><?= e((string) $r['name']) ?></a></td>
                <td>
                    <?php if ((string) ($r['phone'] ?? '') !== ''): ?>
                        <?= e((string) $r['phone']) ?>
                    <?php else: ?>
                        <span class="adm-red">নেই</span><?php if ((string) ($r['phone_raw'] ?? '') !== ''): ?> <small>(লেখা ছিল: <?= e((string) $r['phone_raw']) ?>)</small><?php endif; ?>
                    <?php endif; ?>
                </td>
                <td><?= (string) ($r['email'] ?? '') !== '' ? e((string) $r['email']) : '<span class="adm-muted">—</span>' ?></td>
                <?php if ($role === 'worker'): ?><td><?= e((string) $profession) ?></td><?php endif; ?>
                <td><?= e(implode(', ', array_filter([$area['upazila'], $area['district']]))) ?></td>
                <td><span class="adm-status is-<?= e((string) $r['status']) ?>"><?= e(MemberStatus::label((string) $r['status'])) ?></span></td>
                <td><?= (string) ($r['legacy_id'] ?? '') !== '' ? 'পুরনো সাইট' : 'নতুন' ?></td>
                <td class="adm-actions">
                    <a class="adm-small" href="/admin/members/<?= (int) $r['id'] ?>">দেখুন</a>
                    <?php if ($r['status'] === 'pending'): ?>
                        <form action="/admin/members/action" method="post" data-confirm="<?= $role === 'worker' ? 'অনুমোদন দিলে সদস্যকে এখনই ইমেইল চলে যাবে (ইমেইল থাকলে) এবং নাম সাইটে প্রকাশ হবে। অনুমোদন দেবেন?' : 'তাকে অনুমোদন দেবার আগে দেখে নিন তার অনুমোদন দেবার সকল কাজ সম্পন্ন হয়েছে কিনা। যদি হয়ে থাকে তাহলে হ্যাঁ চাপুন।' ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="back" value="<?= e($url()) ?>">
                            <button class="adm-small adm-small--go" type="submit">অনুমোদন</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($rows === []): ?>
            <tr><td colspan="<?= $role === 'worker' ? 8 : 7 ?>">এই শর্তে কাউকে পাওয়া যায়নি।</td></tr>
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
