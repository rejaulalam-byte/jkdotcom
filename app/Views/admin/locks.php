<?php
/**
 * আটকানো লগইন: কর্মী/প্রতিনিধির লগইন-আইডি অনুযায়ী ও ডিভাইস অনুযায়ী ভুল-চেষ্টার হিসাব; "আটকানো খুলুন" বাটন।
 *
 * @var list<array<string,mixed>> $ids      bucket, role, login_id, devices, max, last, free_at
 * @var list<array<string,mixed>> $devices  file, count, last, free_at
 * @var int $soft        নরম লকের সীমা (৫)
 * @var int $hard        কড়া লকের সীমা (১০)
 * @var int $deviceHard  ডিভাইস আটকানোর সীমা (২০)
 */
$when = static fn (int $t): string => $t > 0 ? date('Y-m-d H:i', $t) : '—';
$release = static function (string $name, string $value, string $label) use ($ids): string {
    return '<form action="' . e(admin_base()) . '/locks/release" method="post" data-confirm="আটকানো খুলে দেবেন?">'
        . csrf_field() . '<input type="hidden" name="' . e($name) . '" value="' . e($value) . '">'
        . '<button class="adm-small adm-small--go" type="submit">' . e($label) . '</button></form>';
};
?>
<p class="adm-muted">
    কেউ ভুল পাসওয়ার্ড দিলে সাইট তা গুনে রাখে। একই আইডিতে <?= e(bn($soft)) ?> বার ভুল হলে <b>নরম লক</b> (সঠিক পাসওয়ার্ড দিলে তখনও ঢোকা যায়),
    <?= e(bn($hard)) ?> বার ভুল হলে <b>কড়া লক</b> (সঠিক পাসওয়ার্ডেও ঢোকা যায় না)। এক ডিভাইস থেকে <?= e(bn($deviceHard)) ?> বার ভুল হলে সেই ডিভাইস আটকে যায়।
    সবই ১৫ মিনিটে নিজে খোলে; কেউ জরুরি হলে এখানে "আটকানো খুলুন" চাপুন। ভুল চেষ্টার হিসাব শুধু শেষ ১৫ মিনিটের।
</p>

<h2 class="adm-card__title">লগইন-আইডি অনুযায়ী</h2>
<div class="adm-tablewrap">
    <table class="adm-table">
        <thead>
            <tr><th>লগইন আইডি</th><th>কোন পাশে</th><th>ভুল চেষ্টা</th><th>অবস্থা</th><th>শেষ ভুল চেষ্টা</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($ids as $r): ?>
            <tr>
                <td><?= e((string) $r['login_id']) ?></td>
                <td><?= $r['role'] === 'agent' ? 'প্রতিনিধি' : 'কর্মী (পেশাজীবী)' ?></td>
                <td><?= e(bn((int) $r['max'])) ?><?= (int) $r['devices'] > 1 ? ' <small class="adm-muted">(' . e(bn((int) $r['devices'])) . 'টি ডিভাইস থেকে)</small>' : '' ?></td>
                <td>
                    <?php if ((int) $r['max'] >= $hard): ?>
                        <span class="adm-status is-rejected">কড়া লক</span>
                        <small class="adm-muted">খুলবে আনুমানিক <?= e($when((int) $r['free_at'])) ?></small>
                    <?php elseif ((int) $r['max'] >= $soft): ?>
                        <span class="adm-status is-pending">নরম লক</span>
                    <?php else: ?>
                        <span class="adm-muted">সাধারণ</span>
                    <?php endif; ?>
                </td>
                <td><?= e($when((int) $r['last'])) ?></td>
                <td class="adm-actions"><?= $release('bucket', (string) $r['bucket'], 'আটকানো খুলুন') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($ids === []): ?>
            <tr><td colspan="6">এখন কোনো আইডির ভুল চেষ্টা নেই।</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<h2 class="adm-card__title adm-gap-top">ডিভাইস অনুযায়ী</h2>
<p class="adm-muted">ডিভাইসের পরিচয় গোপন (আইপি দেখানো হয় না); শুধু কতবার ভুল হয়েছে দেখা যায়।</p>
<div class="adm-tablewrap">
    <table class="adm-table">
        <thead>
            <tr><th>ভুল চেষ্টা (সব আইডি মিলিয়ে)</th><th>অবস্থা</th><th>শেষ ভুল চেষ্টা</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($devices as $d): ?>
            <tr>
                <td><?= e(bn((int) $d['count'])) ?></td>
                <td>
                    <?php if ((int) $d['count'] >= $deviceHard): ?>
                        <span class="adm-status is-rejected">ডিভাইস আটকানো</span>
                        <small class="adm-muted">খুলবে আনুমানিক <?= e($when((int) $d['free_at'])) ?></small>
                    <?php else: ?>
                        <span class="adm-muted">সাধারণ</span>
                    <?php endif; ?>
                </td>
                <td><?= e($when((int) $d['last'])) ?></td>
                <td class="adm-actions"><?= $release('file', (string) $d['file'], 'আটকানো খুলুন') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($devices === []): ?>
            <tr><td colspan="4">এখন কোনো ডিভাইসের ভুল চেষ্টা নেই।</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
