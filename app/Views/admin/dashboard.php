<?php
/**
 * ড্যাশবোর্ড: কর্মী ও প্রতিনিধির আবেদনের সংখ্যা (পরের ধাপে এখান থেকে তালিকায় যাওয়া যাবে)।
 *
 * @var array<string,mixed> $admin
 * @var array<string,array<string,int>> $counts  ['worker' => ['pending' => n, ...], 'agent' => [...]]
 */
use App\Core\AdminAuth;

$n = static fn (string $role, string $status): int => (int) ($counts[$role][$status] ?? 0);
// [শিরোনাম, সংখ্যা, বাড়তি শ্রেণি]
$cards = [
    ['নতুন আবেদন — কর্মী', $n('worker', 'pending'), 'is-new'],
    ['নতুন আবেদন — প্রতিনিধি', $n('agent', 'pending'), 'is-new'],
    ['অনুমোদিত কর্মী', $n('worker', 'approved'), ''],
    ['অনুমোদিত প্রতিনিধি', $n('agent', 'approved'), ''],
    ['হোল্ডে (কর্মী + প্রতিনিধি)', $n('worker', 'suspended') + $n('agent', 'suspended'), ''],
    ['বাতিল (কর্মী + প্রতিনিধি)', $n('worker', 'rejected') + $n('agent', 'rejected'), ''],
];
?>
<p>স্বাগতম, <?= e((string) $admin['name']) ?>। আপনার ভূমিকা: <strong><?= e(AdminAuth::roleLabel((string) $admin['role'])) ?></strong>।</p>
<div class="adm-cards">
    <?php foreach ($cards as [$label, $count, $class]): ?>
        <div class="adm-stat <?= e($class) ?>">
            <span class="adm-stat__n"><?= e(bn($count)) ?></span>
            <span class="adm-stat__l"><?= e($label) ?></span>
        </div>
    <?php endforeach; ?>
</div>
<p class="adm-muted">পরের ধাপে কর্মী ও প্রতিনিধির তালিকা, অনুমোদন, হোল্ড ও বাকি কাজ এই প্যানেলে আসছে।</p>
