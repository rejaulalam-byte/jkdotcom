<?php
/**
 * পুরনো সাইট থেকে আনা কর্মী সক্রিয় করা: কমন প্রাথমিক পাসওয়ার্ড বসানোর বাক্স, অবস্থার ট্যাব, খোঁজা, তালিকা ও "ফোন করা হয়েছে" টিক।
 *
 * @var array{state:string,q:string} $filter
 * @var list<array<string,mixed>> $rows
 * @var int $total
 * @var int $page
 * @var int $pages
 * @var array<string,int> $counts  অবস্থা => সংখ্যা
 * @var array{updated_at:string,updated_by:string}|null $common
 * @var int $minPassword
 */
use App\Models\LegacyActivation;

$base = admin_base() . '/old-workers';
$url = static function (array $override = []) use ($base, $filter, $page): string {
    $params = array_merge(['state' => $filter['state'], 'q' => $filter['q'], 'p' => $page > 1 ? (string) $page : ''], $override);
    $params = array_filter($params, static fn ($v): bool => $v !== '' && $v !== null);

    return $base . ($params === [] ? '' : '?' . http_build_query($params));
};
$tabs = [['', 'সব', array_sum($counts)]];
foreach (LegacyActivation::STATES as $key => $label) {
    $tabs[] = [$key, $label, (int) ($counts[$key] ?? 0)];
}
$statusClass = ['todo' => 'is-pending', 'called' => 'is-suspended', 'expired' => 'is-rejected', 'active' => 'is-approved'];
?>
<div class="adm-box">
    <h2 class="adm-card__title">কমন প্রাথমিক পাসওয়ার্ড</h2>
    <p class="adm-muted">
        পুরনো সাইটের সব কর্মীর জন্য একটাই প্রাথমিক পাসওয়ার্ড। কর্মীকে ফোনে জানানোর পর নিচে তার পাশে "ফোন করা হয়েছে" টিক দিন:
        টিকের পর <?= e(bn(LegacyActivation::DAYS)) ?> দিন এই পাসওয়ার্ডে (মোবাইল নাম্বার দিয়ে) লগইন চলবে, তারপর কর্মী নিজের পাসওয়ার্ড বসাবেন।
        টিক না থাকলে বা মেয়াদ শেষ হলে এই পাসওয়ার্ড কাজ করে না।
    </p>
    <p>
        <?php if ($common !== null): ?>
            অবস্থা: <b>বসানো আছে</b> — শেষ বদল <?= e((string) $common['updated_at']) ?>, করেছেন <?= e((string) $common['updated_by']) ?>।
            <span class="adm-muted">(পাসওয়ার্ড নিজে আর দেখা যায় না।)</span>
        <?php else: ?>
            অবস্থা: <span class="adm-red">এখনও বসানো হয়নি</span> — আগে এটা বসান।
        <?php endif; ?>
    </p>
    <form action="<?= e($base) ?>/password" method="post" data-confirm="<?= $common !== null ? 'নতুন পাসওয়ার্ড বসালে আগেরটা আর কাজ করবে না। বদলাবেন?' : 'এই পাসওয়ার্ড বসাবেন?' ?>" novalidate>
        <?= csrf_field() ?>
        <label for="common-password"><?= $common !== null ? 'নতুন কমন পাসওয়ার্ড' : 'কমন পাসওয়ার্ড' ?> (কমপক্ষে <?= e(bn($minPassword)) ?> অক্ষর)</label>
        <input type="password" id="common-password" name="common_password" minlength="<?= (int) $minPassword ?>" maxlength="72" autocomplete="new-password" required>
        <button class="adm-btn" type="submit"><?= $common !== null ? 'পাসওয়ার্ড বদলান' : 'পাসওয়ার্ড বসান' ?></button>
    </form>
</div>

<nav class="adm-tabs" aria-label="অবস্থা">
    <?php foreach ($tabs as [$key, $label, $count]): ?>
        <a href="<?= e($url(['state' => $key, 'p' => ''])) ?>"<?= $filter['state'] === $key ? ' aria-current="true"' : '' ?>><?= e($label) ?> <b><?= e(bn($count)) ?></b></a>
    <?php endforeach; ?>
</nav>

<form class="adm-filter" action="<?= e($base) ?>" method="get">
    <?php if ($filter['state'] !== ''): ?><input type="hidden" name="state" value="<?= e($filter['state']) ?>"><?php endif; ?>
    <input type="search" name="q" value="<?= e($filter['q']) ?>" placeholder="নাম, মোবাইল বা ইমেইল" maxlength="100">
    <button class="adm-small" type="submit">খুঁজুন</button>
    <?php if ($filter['q'] !== '' || $filter['state'] !== ''): ?>
        <a class="adm-link" href="<?= e($base) ?>">সব ফিল্টার মুছুন</a>
    <?php endif; ?>
</form>

<p class="adm-muted adm-count"><?= e(bn($total)) ?> জন পাওয়া গেছে</p>

<div class="adm-tablewrap">
    <table class="adm-table">
        <thead>
            <tr><th>নাম</th><th>মোবাইল</th><th>ইমেইল</th><th>অবস্থা</th><th>ফোন করা হয়েছে</th></tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <?php $state = LegacyActivation::state($r); ?>
            <tr>
                <td><a href="<?= admin_base() ?>/members/<?= (int) $r['id'] ?>"><?= e((string) $r['name']) ?></a></td>
                <td>
                    <?php if ((string) ($r['phone'] ?? '') !== ''): ?>
                        <?= e((string) $r['phone']) ?>
                    <?php else: ?>
                        <span class="adm-red">নেই</span>
                    <?php endif; ?>
                </td>
                <td><?= (string) ($r['email'] ?? '') !== '' ? e((string) $r['email']) : '<span class="adm-muted">—</span>' ?></td>
                <td>
                    <span class="adm-status <?= e($statusClass[$state] ?? '') ?>"><?= e(LegacyActivation::STATES[$state] ?? '') ?></span>
                    <?php if ($state === 'called'): ?><small>(<?= e(bn(LegacyActivation::daysLeft($r))) ?> দিন বাকি)</small><?php endif; ?>
                </td>
                <td class="adm-actions">
                    <?php if ($state === 'active'): ?>
                        <span class="adm-muted">—</span>
                    <?php else: ?>
                        <form action="<?= e($base) ?>/call" method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                            <input type="hidden" name="back" value="<?= e($url()) ?>">
                            <?php if ($state === 'called'): ?>
                                <button class="adm-small" type="submit" name="do" value="call">আবার ১৫ দিন</button>
                                <button class="adm-small adm-small--warn" type="submit" name="do" value="undo">টিক তুলুন</button>
                            <?php else: ?>
                                <button class="adm-small adm-small--go" type="submit" name="do" value="call">ফোন করা হয়েছে</button>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($rows === []): ?>
            <tr><td colspan="5">এই শর্তে কাউকে পাওয়া যায়নি।</td></tr>
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
