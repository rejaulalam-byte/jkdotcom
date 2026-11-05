<?php
/**
 * "এলাকার নাম": ট্যাব (যাচাইয়ের অপেক্ষায় / অনুমোদিত অতিরিক্ত নাম), প্রতি নামের কাজ, আর নতুন নাম যোগ।
 *
 * @var string $tab
 * @var list<array{id:int,name:string,upazila_id:int,district_id:int,status:string,created_at:string,users:int}> $rows
 * @var int $pendingCount
 * @var array{districts:list<array{id:int,parent:int,name:string}>,upazilas:list<array{id:int,parent:int,name:string}>} $geo
 */
$base = admin_base() . '/area-names';
$districtName = array_column($geo['districts'], 'name', 'id');
$upazilaName = array_column($geo['upazilas'], 'name', 'id');
$act = static function (int $id, string $do, string $label, string $class, string $tab, string $confirm = '') use ($base): string {
    return '<form action="' . e($base) . '/action" method="post"' . ($confirm !== '' ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field()
        . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="do" value="' . e($do) . '"><input type="hidden" name="tab" value="' . e($tab) . '">'
        . '<button class="adm-small ' . e($class) . '" type="submit">' . e($label) . '</button></form>';
};
?>
<p class="adm-muted">কর্মীরা এলাকার নাম ফর্মে বাছেন বা লেখেন। শুরুর তালিকায় (গ্রামের ইউনিয়ন ও বড় শহরের পরিচিত এলাকা) যা নেই, সেটা কেউ লিখলে এখানে "যাচাইয়ের অপেক্ষায়" আসে। আপনি ঠিক মনে করলে অনুমোদন দিন, বানান ভুল হলে ঠিক করে সংরক্ষণ করুন। অনুমোদিত নাম সবার সাজেশনে আসে।</p>

<nav class="adm-tabs" aria-label="এলাকার নামের ট্যাব">
    <a href="<?= e($base) ?>"<?= $tab === 'pending' ? ' aria-current="page"' : '' ?>>যাচাইয়ের অপেক্ষায় <b><?= e(bn($pendingCount)) ?></b></a>
    <a href="<?= e($base . '?tab=approved') ?>"<?= $tab === 'approved' ? ' aria-current="page"' : '' ?>>অনুমোদিত অতিরিক্ত নাম</a>
</nav>

<div class="adm-tablewrap">
    <table class="adm-table">
        <thead><tr><th>এলাকার নাম</th><th>উপজেলা / জেলা</th><th>কতজন ব্যবহার করছেন</th><th>কবে</th><th>কাজ</th></tr></thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
                <?php $where = implode(', ', array_filter([$upazilaName[$r['upazila_id']] ?? '', $districtName[$r['district_id']] ?? ''])); ?>
                <tr>
                    <td><b><?= e($r['name']) ?></b></td>
                    <td><?= $where !== '' ? e($where) : '—' ?></td>
                    <td><?= e(bn($r['users'])) ?></td>
                    <td><?= e(substr($r['created_at'], 0, 10)) ?></td>
                    <td>
                        <div class="adm-actions">
                            <?php if ($tab === 'pending'): ?><?= $act($r['id'], 'approve', 'অনুমোদন', 'adm-small--go', $tab) ?><?php endif; ?>
                            <form class="adm-inline" action="<?= e($base) ?>/action" method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="do" value="rename"><input type="hidden" name="tab" value="<?= e($tab) ?>">
                                <input type="text" name="name" value="<?= e($r['name']) ?>" maxlength="100" aria-label="ঠিক নাম">
                                <button class="adm-small" type="submit">ঠিক করে সংরক্ষণ</button>
                            </form>
                            <?= $act($r['id'], 'delete', 'বাদ দিন', 'adm-small--warn', $tab, 'এই নাম সাজেশনের তালিকা থেকে বাদ যাবে। বাদ দেবেন?') ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?>
                <tr><td colspan="5" class="adm-muted"><?= $tab === 'pending' ? 'এখন যাচাইয়ের অপেক্ষায় কোনো নাম নেই।' : 'অতিরিক্ত অনুমোদিত নাম এখনও নেই।' ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<section class="adm-box">
    <h2>নতুন নাম যোগ করুন</h2>
    <form action="<?= e($base) ?>/action" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="add"><input type="hidden" name="tab" value="approved">
        <label for="an-name">এলাকার নাম</label>
        <input id="an-name" type="text" name="name" maxlength="100" required placeholder="যেমন- আগারগাঁও">
        <label for="an-district">জেলা (ঐচ্ছিক; দিলে ওই জেলায় সাজেশনে আগে আসে)</label>
        <select id="an-district" name="district_id">
            <option value="0">জেলা নেই</option>
            <?php foreach ($geo['districts'] as $d): ?><option value="<?= (int) $d['id'] ?>"><?= e($d['name']) ?></option><?php endforeach; ?>
        </select>
        <button class="adm-btn" type="submit">যোগ করুন</button>
    </form>
</section>
