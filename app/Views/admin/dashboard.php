<?php
/**
 * ড্যাশবোর্ড: মাঝখানে সাজানো স্বাগতম-ব্যানার; নিচে বাঁয়ে তিনটি বার গ্রাফ (পাশাপাশি দুটি, নিচে চওড়া একটি), ডানে সংখ্যার কার্ড (কাজ অনুযায়ী ভাগ-করা)।
 *
 * @var array<string,mixed> $admin
 * @var array<string,array<string,int>> $counts  ['worker' => ['pending' => n, ...], 'agent' => [...]]
 * @var array<string,int> $stats                 AdminStats::overview()
 * @var array<string,mixed> $charts               AdminStats::charts()
 * @var string|null $pickedDay    প্রথম গ্রাফের বেছে নেওয়া দিন ("Y-m-d"), না বাছলে null (= গতকাল)
 * @var string|null $pickedMonth  দ্বিতীয় গ্রাফের বেছে নেওয়া মাস ("Y-m"), না বাছলে null (= গত মাস)
 * @var string|null $pickedFrom   তৃতীয় গ্রাফের বেছে নেওয়া শুরুর তারিখ, না বাছলে null (= ৭ দিন আগে)
 * @var string|null $pickedTo     তৃতীয় গ্রাফের বেছে নেওয়া শেষ তারিখ, না বাছলে null (= গতকাল)
 * @var array{visitors:int,home_only:int,many:int,services_page:int,services_seen:int} $visitsToday PageVisits::today()
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
    ['এক নজরে', [
        ['সেবা (পেশা) নামের সংখ্যা', (int) $stats['services'], false],
        ['চালু বিজ্ঞাপন', (int) $stats['ads'], false],
        ['প্রকাশিত কাজের বিজ্ঞপ্তি', (int) $stats['jobs'], false],
        ['নিউজলেটারের গ্রাহক', (int) $stats['subscribers'], false],
    ]],
];
// গ্রাফের তারিখ বাংলায়: "২৯ অক্টোবর"
$dayLabel = static fn (string $date): string => bn((int) date('j', strtotime($date))) . ' ' . $months[(int) date('n', strtotime($date)) - 1];
$twoBars = static fn (int $visits, int $clicks): array => [
    ['label' => 'সেবায় ভিজিট', 'items' => [['kind' => 'visits', 'n' => $visits]]],
    ['label' => 'মোবাইল বা ইমেইলে ক্লিক', 'items' => [['kind' => 'clicks', 'n' => $clicks]]],
];

// গ্রাফে দিন, মাস ও থেকে–পর্যন্ত বাছার ঘর। একটা ফর্মে বাছলে অন্যগুলোর বাছাই লুকানো ঘরে সঙ্গে যায়
$dashUrl = admin_base();
$chosen = [
    'day' => (string) ($pickedDay ?? ''),
    'month' => (string) ($pickedMonth ?? ''),
    'from' => (string) ($pickedFrom ?? ''),
    'to' => (string) ($pickedTo ?? ''),
];
// এই ফর্ম বাদে অন্যগুলোর বাছাই লুকানো ঘর হয়ে ফর্মের সঙ্গে যায়
$keep = static function (array $own) use ($chosen): string {
    $html = '';
    foreach ($chosen as $name => $value) {
        if ($value !== '' && !in_array($name, $own, true)) {
            $html .= '<input type="hidden" name="' . e($name) . '" value="' . e($value) . '">';
        }
    }

    return $html;
};
// "ফিরুন" লিংকের ঠিকানা: এই ফর্মের বাছাই বাদ, অন্যগুলো থাকে
$backUrl = static function (array $own) use ($chosen, $dashUrl): string {
    $params = array_filter(array_diff_key($chosen, array_flip($own)), static fn (string $v): bool => $v !== '');

    return $dashUrl . ($params === [] ? '' : '?' . http_build_query($params));
};
$todayYmd = date('Y-m-d');
$yesterdayYmd = date('Y-m-d', strtotime('-1 day'));
$thisMonth = date('Y-m');
$lastMonth = date('Y-m', strtotime('first day of last month'));
$shownDay = $charts['yesterday']['date'];
$shownMonth = date('Y-m', strtotime($charts['month']['first']));
$shownFrom = $charts['week']['from'];
$shownTo = $charts['week']['to'];
$rangeIsDefault = $shownFrom === date('Y-m-d', strtotime('-7 days')) && $shownTo === $yesterdayYmd;
$monthName = static fn (string $ym): string => $months[(int) substr($ym, 5, 2) - 1] . ' ' . bn(substr($ym, 0, 4));
$fullDay = static fn (string $date): string => $dayLabel($date) . ' ' . bn((int) date('Y', strtotime($date)));

ob_start(); ?>
<form class="adm-chart__pick" action="<?= e($dashUrl) ?>" method="get" data-autosubmit>
    <label>অন্য দিন <input type="date" name="day" value="<?= e($shownDay) ?>" min="2020-01-01" max="<?= e($todayYmd) ?>"></label>
    <?= $keep(['day']) ?>
    <button class="adm-small" type="submit">দেখুন</button>
    <?php if ($chosen['day'] !== '' && $shownDay !== $yesterdayYmd): ?><a class="adm-link" href="<?= e($backUrl(['day'])) ?>">গতকালে ফিরুন</a><?php endif; ?>
</form>
<?php $dayPicker = (string) ob_get_clean();

ob_start(); ?>
<form class="adm-chart__pick" action="<?= e($dashUrl) ?>" method="get" data-autosubmit>
    <label>অন্য মাস
        <select name="month">
            <?php for ($i = 0; $i < 24; $i++): $ym = date('Y-m', strtotime('first day of this month -' . $i . ' months')); ?>
                <option value="<?= e($ym) ?>"<?= $ym === $shownMonth ? ' selected' : '' ?>><?= e($monthName($ym)) ?></option>
            <?php endfor; ?>
        </select>
    </label>
    <?= $keep(['month']) ?>
    <button class="adm-small" type="submit">দেখুন</button>
    <?php if ($chosen['month'] !== '' && $shownMonth !== $lastMonth): ?><a class="adm-link" href="<?= e($backUrl(['month'])) ?>">গত মাসে ফিরুন</a><?php endif; ?>
</form>
<?php $monthPicker = (string) ob_get_clean();

// তৃতীয় গ্রাফ: দুই তারিখ। দুটো বাছা শেষ হলে "দেখুন" চাপলেই যায় (আপনা-আপনি জমা দেওয়া হয় না, নইলে প্রথম তারিখ বাছতেই আধা-বাছাই চলে যেত)
ob_start(); ?>
<form class="adm-chart__pick adm-chart__pick--range" action="<?= e($dashUrl) ?>" method="get">
    <label>কত তারিখ থেকে <input type="date" name="from" value="<?= e($shownFrom) ?>" min="2020-01-01" max="<?= e($todayYmd) ?>"></label>
    <label>কত তারিখ পর্যন্ত <input type="date" name="to" value="<?= e($shownTo) ?>" min="2020-01-01" max="<?= e($todayYmd) ?>"></label>
    <?= $keep(['from', 'to']) ?>
    <button class="adm-small" type="submit">দেখুন</button>
    <?php if (!$rangeIsDefault): ?><a class="adm-link" href="<?= e($backUrl(['from', 'to'])) ?>">গত ৭ দিনে ফিরুন</a><?php endif; ?>
    <span class="adm-muted adm-hint">(সর্বোচ্চ <?= e(bn(\App\Models\AdminStats::MAX_RANGE_DAYS)) ?> দিন)</span>
</form>
<?php $rangePicker = (string) ob_get_clean();

$dayTitle = $shownDay === $yesterdayYmd ? 'গতকাল' : ($shownDay === $todayYmd ? 'আজ (এ পর্যন্ত)' : 'বেছে নেওয়া দিন');
$monthTitle = $shownMonth === $lastMonth ? 'গত মাস' : ($shownMonth === $thisMonth ? 'এই মাস (এ পর্যন্ত)' : 'বেছে নেওয়া মাস');
$rangeTitle = $rangeIsDefault ? 'গত ৭ দিনে কোন কোন সেবায় খোঁজ ও মোবাইল বা ইমেইলে ক্লিক করেছে' : 'কোন কোন সেবায় খোঁজ ও মোবাইল বা ইমেইলে ক্লিক করেছে';
$chartList = [
    [
        'title' => $dayTitle,
        'sub' => '',
        'note' => $fullDay($shownDay),
        'wide' => false,
        'groups' => $twoBars($charts['yesterday']['visits'], $charts['yesterday']['clicks']),
        'empty' => null,
        'picker' => $dayPicker,
    ],
    [
        'title' => $monthTitle,
        'sub' => '',
        'note' => $monthName($shownMonth),
        'wide' => false,
        'groups' => $twoBars($charts['month']['visits'], $charts['month']['clicks']),
        'empty' => null,
        'picker' => $monthPicker,
    ],
    [
        'title' => $rangeTitle,
        'sub' => $fullDay($shownFrom) . ' থেকে ' . $fullDay($shownTo) . ($charts['week']['more'] > 0 ? ' · সবচেয়ে বেশিগুলো; আরও ' . bn($charts['week']['more']) . 'টি সেবা আছে' : ''),
        'wide' => true,
        'groups' => array_map(static fn (array $r): array => [
            'label' => $r['name'],
            'items' => [['kind' => 'visits', 'n' => $r['visits']], ['kind' => 'clicks', 'n' => $r['clicks']]],
        ], $charts['week']['rows']),
        'empty' => $rangeIsDefault ? 'গত ৭ দিনে এখনও কোনো সেবায় ভিজিট বা ক্লিক নেই।' : 'এই সময়ে কোনো সেবায় ভিজিট বা ক্লিক নেই।',
        'picker' => $rangePicker,
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
        <section class="adm-panel adm-today" aria-label="আজকের দর্শক">
            <h3>আজকের দর্শক <span class="adm-muted">(এ পর্যন্ত, ঢাকার সময়ে আজ রাত ১২টা থেকে; ব্রাউজার/ফোন ধরে গোনা)</span></h3>
            <ul>
                <li><span>আজ মোট দর্শক <small class="adm-hint">(বিভিন্ন ব্রাউজার ও মোবাইল দিয়ে)</small></span><b><?= e(bn((int) $visitsToday['visitors'])) ?></b></li>
                <li><span>আজ শুধু হোম পেইজে এসেছেন কতজন</span><b><?= e(bn((int) $visitsToday['home_only'])) ?></b></li>
                <li><span>আজ একাধিক পেইজে ভিজিট করেছেন কতজন</span><b><?= e(bn((int) $visitsToday['many'])) ?></b></li>
                <li><span>আজ সেবা পেইজে ভিজিট করেছেন কতজন</span><b><?= e(bn((int) $visitsToday['services_page'])) ?></b></li>
                <li><span>আজ সেবাসমূহ থেকে কতটা সেবার পেইজ দেখা হয়েছে</span><b><?= e(bn((int) $visitsToday['services_seen'])) ?></b></li>
                <?php // আগের ডান পাশের "আজকের হিসাব" ঘর: এখন গ্রাফের নিচে একই তালিকায় ?>
                <li><span>আজকে কর্মীর পেইজ দেখা হয়েছে</span><b><?= e(bn((int) $stats['visits_today'])) ?></b></li>
                <li><span>আজকে মোবাইল/ইমেইলে ক্লিক করেছেন</span><b><?= e(bn((int) $stats['clicks_today'])) ?></b></li>
                <li><span>আজকের আগ্রহী দর্শক <small class="adm-hint">(নাম-মোবাইল দিয়েছেন)</small></span><b><?= e(bn((int) $stats['leads_today'])) ?></b></li>
            </ul>
        </section>
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
