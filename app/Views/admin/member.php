<?php
/**
 * একজন কর্মী বা প্রতিনিধির পুরো তথ্য (এই ধাপে শুধু দেখা; অনুমোদন, হোল্ড, মোছা, সম্পাদনা পরের ধাপে)।
 * খালি বা অসম্পূর্ণ তথ্য লাল দাগে দেখানো হয়, যাতে অ্যাডমিন ফোন করে পূর্ণ করতে পারেন।
 *
 * @var array<string,mixed> $member
 * @var array{upazila:string,district:string,division:string} $geo
 * @var string $serviceName
 * @var list<string> $extraServices
 * @var string $workText     কাজ করতে আগ্রহী জেলা ("ঢাকা, বরিশাল" বা "সব জেলা")
 * @var ?string $photoUrl
 */
use App\Models\MemberStatus;

$isWorker = $member['role'] === 'worker';
$gender = ['male' => 'পুরুষ', 'female' => 'মহিলা', 'other' => 'অন্যান্য'][(string) ($member['gender'] ?? '')] ?? '';
$list = $isWorker ? '/admin/workers' : '/admin/agents';
$missing = '<span class="adm-red">নেই</span>';
// [নাম, মান (HTML, escape করা), খালি কি না]
$val = static fn (string $v): string => $v === '' ? '' : e($v);
$rows = [
    'পরিচয়' => [
        ['নাম', $val((string) $member['name'])],
        ['জেন্ডার', $val($gender)],
        ['অবস্থা', '<span class="adm-status is-' . e((string) $member['status']) . '">' . e(MemberStatus::label((string) $member['status'])) . '</span>'],
        ['উৎস', (string) ($member['legacy_id'] ?? '') !== '' ? 'পুরনো সাইট থেকে আনা (পুরনো আইডি ' . e((string) $member['legacy_id']) . ')' : 'নতুন সাইটে নিবন্ধিত'],
    ],
    'যোগাযোগ' => [
        ['মোবাইল', (string) ($member['phone'] ?? '') !== '' ? e(format_phone((string) $member['phone'])) : ''],
        ['পুরনো ডাটায় মোবাইলের লেখা', (string) ($member['phone_raw'] ?? '') !== '' ? e((string) $member['phone_raw']) : '—'],
        ['ইমেইল', $val((string) ($member['email'] ?? ''))],
    ],
    'ঠিকানা' => [
        ['ঠিকানা', $val((string) $member['address'])],
        ['উপজেলা / থানা', $val($geo['upazila'])],
        ['জেলা', $val($geo['district'])],
        ['বিভাগ', $val($geo['division'])],
    ],
];
if ($isWorker) {
    $profession = (string) ($member['service_custom'] ?? '') !== ''
        ? e((string) $member['service_custom']) . ' <small>(তালিকায় নেই, কর্মীর নিজের লেখা — যাচাই করুন)</small>'
        : $val($serviceName);
    $rows['পেশা ও কাজ'] = [
        ['প্রধান পেশা', $profession],
        ['আরও পেশা', $extraServices !== [] ? e(implode(', ', $extraServices)) : '—'],
        ['অভিজ্ঞতা', $member['experience_years'] !== null ? e(bn((int) $member['experience_years'])) . ' বছর' : ''],
        ['কাজের বিবরণ', (string) ($member['bio'] ?? '') !== '' ? nl2br(e((string) $member['bio'])) : ''],
        ['কাজ করতে আগ্রহী জেলা', $val($workText)],
        ['রেফারেন্স ১', (string) ($member['reference_1'] ?? '') !== '' ? nl2br(e((string) $member['reference_1'])) : '—'],
        ['রেফারেন্স ২', (string) ($member['reference_2'] ?? '') !== '' ? nl2br(e((string) $member['reference_2'])) : '—'],
    ];
} else {
    $rows['প্রতিনিধির তথ্য'] = [['জাতীয় পরিচয়পত্র নম্বর', $val((string) ($member['nid_number'] ?? ''))]];
}
$rows['হিসাব'] = [
    ['আবেদন/নিবন্ধনের সময়', $val((string) ($member['created_at'] ?? ''))],
    ['অনুমোদনের সময়', (string) ($member['approved_at'] ?? '') !== '' ? e((string) $member['approved_at']) : '—'],
    ['শেষ লগইন', (string) ($member['last_login_at'] ?? '') !== '' ? e((string) $member['last_login_at']) : '—'],
    ['সম্মতির সময়', (string) ($member['consent_at'] ?? '') !== '' ? e((string) $member['consent_at']) : '—'],
    ['পাসওয়ার্ড বদলাতে হবে?', (int) ($member['must_change_password'] ?? 0) === 1 ? 'হ্যাঁ (প্রথম লগইনে)' : 'না'],
];
?>
<p><a class="adm-link" href="<?= e($list) ?>">← <?= $isWorker ? 'কর্মীর' : 'প্রতিনিধির' ?> তালিকায় ফিরুন</a></p>

<div class="adm-detail">
    <aside class="adm-detail__photo">
        <?php if ($photoUrl !== null): ?>
            <img src="<?= e($photoUrl) ?>" alt="" width="220" height="240">
        <?php else: ?>
            <div class="adm-detail__nophoto">ছবি নেই</div>
        <?php endif; ?>
        <?php if ($isWorker && (string) $member['status'] === 'approved'): ?>
            <p><a class="adm-small" href="/workers/<?= (int) $member['id'] ?>" target="_blank" rel="noopener">সাইটে যেভাবে দেখা যায়</a></p>
        <?php endif; ?>
    </aside>
    <div class="adm-detail__body">
        <?php foreach ($rows as $group => $items): ?>
            <section class="adm-box">
                <h2><?= e($group) ?></h2>
                <dl class="adm-dl">
                    <?php foreach ($items as [$label, $html]): ?>
                        <dt><?= e($label) ?></dt>
                        <dd><?= $html !== '' ? $html : $missing ?></dd>
                    <?php endforeach; ?>
                </dl>
            </section>
        <?php endforeach; ?>
    </div>
</div>
