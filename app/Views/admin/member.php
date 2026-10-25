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
use App\Core\AdminAuth;
use App\Models\MemberStatus;
use App\Models\UserRepository;

$isWorker = $member['role'] === 'worker';
$gender = ['male' => 'পুরুষ', 'female' => 'মহিলা', 'other' => 'অন্যান্য'][(string) ($member['gender'] ?? '')] ?? '';
$list = $isWorker ? admin_base() . '/workers' : admin_base() . '/agents';
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

<?php
// অবস্থা-বদলের বাক্স: এখনকার অবস্থা থেকে যে কাজগুলো চলে শুধু সেগুলো (MemberStatus::ACTIONS); চিরতরে মোছা শুধু সুপার অ্যাডমিন ও ডাস্টবিনের সদস্যে
$current = (string) $member['status'];
$btn = [
    'approve' => [$current === 'suspended' ? 'অনুমতি দিন (অনুমোদন)' : 'অনুমোদন দিন', 'adm-small--go', $isWorker ? 'অনুমোদন দিলে সদস্যকে এখনই ইমেইল চলে যাবে (ইমেইল থাকলে) এবং নাম সাইটে প্রকাশ হবে। অনুমোদন দেবেন?' : 'তাকে অনুমোদন দেবার আগে দেখে নিন তার অনুমোদন দেবার সকল কাজ সম্পন্ন হয়েছে কিনা। যদি হয়ে থাকে তাহলে হ্যাঁ চাপুন।'],
    'hold' => ['হোল্ডে রাখুন', '', 'এই সদস্য সাইটে দেখা যাবে না ও লগইন করতে পারবে না, যতক্ষণ না আপনি আবার অনুমতি দেন। হোল্ডে রাখবেন?'],
    'reject' => ['বাতিল করুন', 'adm-small--warn', 'আবেদন বাতিল করবেন? সদস্যের ইমেইল থাকলে তাঁকে একটি ভদ্র বাতিলের ইমেইল চলে যাবে।'],
    'reserve' => ['মুছুন (সংরক্ষিতে পাঠান)', 'adm-small--warn', 'সদস্য সাইট থেকে সরে সংরক্ষিতে যাবে (সব তথ্য থাকবে, ফেরানো যাবে)। মুছবেন?'],
    'trash' => ['ডাস্টবিনে পাঠান', 'adm-small--warn', 'সদস্যকে ডাস্টবিনে পাঠাবেন?'],
    'restore' => ['ফিরিয়ে আনুন', 'adm-small--go', ''],
    'reopen' => ['নতুন আবেদনে ফিরিয়ে নিন', '', (string) ($member['approved_at'] ?? '') !== '' ? 'এই সদস্য আগে অনুমোদিত হয়েছিলেন, তাই অনুমোদনের ইমেইল পেয়ে থাকতে পারেন। ফিরিয়ে নিলে তাঁকে আর কোনো ইমেইল যাবে না, ফোনে জানিয়ে দিন। ফিরিয়ে নেবেন?' : 'আবেদনটি আবার "নতুন আবেদন"-এ ফিরিয়ে নেবেন?'],
];
$allowed = [];
foreach (MemberStatus::ACTIONS as $key => [$from]) {
    if (in_array($current, $from, true)) {
        $allowed[] = $key;
    }
}
$canPurge = $current === 'trashed' && AdminAuth::isSuper($admin);
$daysLeft = null;
if (in_array($current, ['reserved', 'trashed'], true) && (string) ($member['status_changed_at'] ?? '') !== '') {
    $daysLeft = max(0, UserRepository::RESERVE_DAYS - (int) floor((time() - strtotime((string) $member['status_changed_at'])) / 86400));
}
$back = admin_base() . '/members/' . (int) $member['id'];
?>
<section class="adm-box adm-actionbox">
    <h2>অবস্থা: <span class="adm-status is-<?= e($current) ?>"><?= e(MemberStatus::label($current)) ?></span></h2>
    <?php if ((string) ($member['status_note'] ?? '') !== ''): ?>
        <p class="adm-muted">কারণ/নোট: <?= e((string) $member['status_note']) ?></p>
    <?php endif; ?>
    <?php if ($daysLeft !== null): ?>
        <p class="adm-muted"><?= $current === 'reserved' ? 'আর ' . e(bn($daysLeft)) . ' দিন পর নিজে ডাস্টবিনে যাবে।' : 'আর ' . e(bn($daysLeft)) . ' দিন পর চিরতরে মুছে যাবে।' ?> (এর মধ্যে "ফিরিয়ে আনুন" চাপলে ফেরত আসবে।)</p>
    <?php endif; ?>
    <?php
    // গ্রিড: বাঁ কলামে বোতাম, ডান কলামে দুই লেখার ঘর (দুটোই "বাতিল" ফর্মের অংশ, form="reject-form" দিয়ে জোড়া, তাই একই বরাবর থাকে)।
    // প্রথম সারি: অনুমোদন / হোল্ড / বাতিল (যেগুলো চলে) + নিজের নোট; দ্বিতীয় সারি: মোছা, ডাস্টবিন, ফিরিয়ে আনা ইত্যাদি + সদস্যকে জানানোর কারণ
    $first = array_values(array_intersect(['approve', 'hold', 'reject'], $allowed));
    $second = array_values(array_diff($allowed, $first));
    $hasReject = in_array('reject', $allowed, true);
    $form = static function (string $action) use ($btn, $member, $back): void {
        [$label, $class, $confirm] = $btn[$action];
        ?>
        <form<?= $action === 'reject' ? ' id="reject-form"' : '' ?> action="<?= admin_base() ?>/members/action" method="post"<?= $confirm !== '' ? ' data-confirm="' . e($confirm) . '"' : '' ?>>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $member['id'] ?>">
            <input type="hidden" name="action" value="<?= e($action) ?>">
            <input type="hidden" name="back" value="<?= e($back) ?>">
            <button class="adm-small <?= e($class) ?>" type="submit"><?= e($label) ?></button>
        </form>
        <?php
    };
    ?>
    <div class="adm-ag">
        <div class="adm-ag__left adm-ag__r1"><?php foreach ($first as $action): $form($action); endforeach; ?></div>
        <div class="adm-ag__right adm-ag__r1">
            <?php if ($hasReject): ?>
                <input class="adm-note" form="reject-form" type="text" name="note" maxlength="255" placeholder="কারণ — নিজের নোট (ঐচ্ছিক)" aria-label="কারণ, নিজের নোট">
            <?php endif; ?>
        </div>
        <div class="adm-ag__left adm-ag__r2">
            <?php foreach ($second as $action): $form($action); endforeach; ?>
            <?php if ($canPurge): ?>
                <form action="<?= admin_base() ?>/members/action" method="post" data-confirm="চিরতরে মুছলে এই সদস্যের সব তথ্য ও ছবি চলে যাবে, আর কখনও ফেরানো যাবে না। সত্যিই মুছবেন?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $member['id'] ?>">
                    <input type="hidden" name="action" value="purge">
                    <input type="hidden" name="back" value="<?= e($back) ?>">
                    <button class="adm-small adm-small--warn" type="submit">চিরতরে মুছুন</button>
                </form>
            <?php endif; ?>
        </div>
        <div class="adm-ag__right adm-ag__r2">
            <?php if ($hasReject): ?>
                <input class="adm-note" form="reject-form" type="text" name="member_reason" maxlength="255" placeholder="সদস্যকে জানানোর কারণ (ঐচ্ছিক)" aria-label="সদস্যকে জানানোর কারণ">
            <?php endif; ?>
        </div>
    </div>
</section>
<div class="adm-detail">
    <aside class="adm-detail__photo">
        <p><a class="adm-btn adm-btn--edit" href="<?= admin_base() ?>/member-edit/<?= (int) $member['id'] ?>">✎ সম্পাদনা করুন</a></p>
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
