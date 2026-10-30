<?php
/**
 * ড্যাশবোর্ড: মাঝখানে সাজানো স্বাগতম-ব্যানার; নিচে বাঁয়ে তিনটি বার গ্রাফ (পাশাপাশি দুটি, নিচে চওড়া একটি), ডানে সংখ্যার কার্ড (কাজ অনুযায়ী ভাগ-করা)।
 *
 * @var array<string,mixed> $admin
 * @var array<string,array<string,int>> $counts  ['worker' => ['pending' => n, ...], 'agent' => [...]]
 * @var array<string,int> $stats                 AdminStats::overview()
 * @var array<string,mixed> $charts               AdminStats::charts()
 */
use App\Core\AdminAuth;

$n = static fn (string $role, string $status): int => (int) ($counts[$role][$status] ?? 0);
$pendingWorkers = $n('worker', 'pending');
$pendingAgents = $n('agent', 'pending');
$pendingTotal = $pendingWorkers + $pendingAgents;

// সময় অনুযায়ী শুভেচ্ছা
$hour = (int) date('G');
$greeting = match (true) {
    $hour >= 5 && $hour < 12 => 'শুভ সকাল',
    $hour >= 12 && $hour < 16 => 'শুভ দুপুর',
    $hour >= 16 && $hour < 18 => 'শুভ বিকাল',
    $hour >= 18 && $hour < 20 => 'শুভ সন্ধ্যা',
    default => 'শুভ রাত্রি',
};
$weekdays = ['রবিবার', 'সোমবার', 'মঙ্গলবার', 'বুধবার', 'বৃহস্পতিবার', 'শুক্রবার', 'শনিবার'];
$months = ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];
$today = $weekdays[(int) date('w')] . ', ' . bn((int) date('j')) . ' ' . $months[(int) date('n') - 1] . ' ' . bn((int) date('Y'));

// কার্ড: [শিরোনাম, [[লেখা, সংখ্যা, "মনোযোগ" চিহ্ন দেওয়া হবে কি না], ...]]
$cards = [
    ['কর্মী ও প্রতিনিধি', [
        ['অনুমোদিত কর্মী', $n('worker', 'approved'), false],
        ['অনুমোদিত প্রতিনিধি', $n('agent', 'approved'), false],
        ['হোল্ডে', $n('worker', 'suspended') + $n('agent', 'suspended'), false],
        ['বাতিল', $n('worker', 'rejected') + $n('agent', 'rejected'), false],
    ]],
    ['আপনার মনোযোগ দরকার', [
        ['নতুন আবেদন — কর্মী', $pendingWorkers, true],
        ['নতুন আবেদন — প্রতিনিধি', $pendingAgents, true],
        ['না-পড়া মতামত', (int) $stats['feedback_unread'], true],
    ]],
    ['আজকের হিসাব', [
        ['কর্মীর পেইজ দেখা হয়েছে', (int) $stats['visits_today'], false],
        ['ফোন/ইমেইলে ক্লিক', (int) $stats['clicks_today'], false],
        ['আগ্রহী দর্শক (নাম-মোবাইল দিয়েছেন)', (int) $stats['leads_today'], false],
    ]],
    ['এক নজরে', [
        ['সেবা (পেশা)', (int) $stats['services'], false],
        ['চালু বিজ্ঞাপন', (int) $stats['ads'], false],
        ['প্রকাশিত কাজের বিজ্ঞপ্তি', (int) $stats['jobs'], false],
        ['নিউজলেটারের গ্রাহক', (int) $stats['subscribers'], false],
    ]],
];
// গ্রাফের তারিখ বাংলায়: "২৯ অক্টোবর"
$dayLabel = static fn (string $date): string => bn((int) date('j', strtotime($date))) . ' ' . $months[(int) date('n', strtotime($date)) - 1];
$twoBars = static fn (int $visits, int $clicks): array => [
    ['label' => 'সেবায় ভিজিট', 'items' => [['kind' => 'visits', 'n' => $visits]]],
    ['label' => 'ফোন বা ইমেইলে ক্লিক', 'items' => [['kind' => 'clicks', 'n' => $clicks]]],
];
$chartList = [
    [
        'title' => 'গতকাল',
        'sub' => $dayLabel($charts['yesterday']['date']),
        'wide' => false,
        'groups' => $twoBars($charts['yesterday']['visits'], $charts['yesterday']['clicks']),
        'empty' => null,
    ],
    [
        'title' => 'গত মাস',
        'sub' => $months[(int) date('n', strtotime($charts['month']['first'])) - 1] . ' ' . bn((int) date('Y', strtotime($charts['month']['first']))),
        'wide' => false,
        'groups' => $twoBars($charts['month']['visits'], $charts['month']['clicks']),
        'empty' => null,
    ],
    [
        'title' => 'গত ৭ দিনে কোন সেবায় খোঁজ ও ফোন',
        'sub' => $dayLabel($charts['week']['from']) . ' থেকে ' . $dayLabel($charts['week']['to']) . ($charts['week']['more'] > 0 ? ' · সবচেয়ে বেশিগুলো; আরও ' . bn($charts['week']['more']) . 'টি সেবা আছে' : ''),
        'wide' => true,
        'groups' => array_map(static fn (array $r): array => [
            'label' => $r['name'],
            'items' => [['kind' => 'visits', 'n' => $r['visits']], ['kind' => 'clicks', 'n' => $r['clicks']]],
        ], $charts['week']['rows']),
        'empty' => 'গত ৭ দিনে এখনও কোনো সেবায় ভিজিট বা ক্লিক নেই।',
    ],
];
?>
<section class="adm-hero">
    <p class="adm-hero__greet"><?= e($greeting) ?></p>
    <p><span class="adm-badge"><?= e(AdminAuth::roleLabel((string) $admin['role'])) ?></span></p>
    <p class="adm-hero__date"><?= e($today) ?></p>

</section>

<div class="adm-dash">
    <div class="adm-dash__main">
        <div class="adm-charts">
            <?php foreach ($chartList as $chart): ?>
                <?php require __DIR__ . '/chart.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <aside class="adm-dash__side" aria-label="সংখ্যার সারাংশ">
        <?php // নতুন আবেদনের খবর: ডানের কার্ডগুলোর ওপরে আলাদা বাক্সে, যাতে চোখে পড়ে ?>
        <p class="adm-alert<?= $pendingTotal > 0 ? ' is-new' : '' ?>" role="status">
            <?php if ($pendingTotal > 0): ?>
                <strong><?= e(bn($pendingTotal)) ?>টি</strong> নতুন আবেদন আপনার অপেক্ষায় আছে।
            <?php else: ?>
                এখন নতুন কোনো আবেদন অপেক্ষায় নেই।
            <?php endif; ?>
        </p>
        <?php foreach ($cards as [$title, $rows]): ?>
            <section class="adm-panel">
                <h3><?= e($title) ?></h3>
                <ul>
                    <?php foreach ($rows as [$label, $count, $attention]): ?>
                        <li>
                            <span><?= e($label) ?></span>
                            <?php if ($attention && $count > 0): ?>
                                <b class="adm-pill"><?= e(bn($count)) ?></b>
                            <?php else: ?>
                                <b><?= e(bn($count)) ?></b>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
        <?php if (AdminAuth::isSuper($admin)): ?>
            <section class="adm-panel adm-panel--link">
                <a href="<?= e(admin_base()) ?>/log">কার্যলগ</a>
            </section>
        <?php endif; ?>
    </aside>
</div>
