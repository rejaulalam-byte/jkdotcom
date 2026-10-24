<?php
/**
 * ড্যাশবোর্ড: মাঝখানে সাজানো স্বাগতম-ব্যানার; নিচে বাঁয়ে খালি জায়গা (পরে দুটি গ্রাফ), ডানে সংখ্যার কার্ড (কাজ অনুযায়ী ভাগ-করা)।
 *
 * @var array<string,mixed> $admin
 * @var array<string,array<string,int>> $counts  ['worker' => ['pending' => n, ...], 'agent' => [...]]
 * @var array<string,int> $stats                 AdminStats::overview()
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
?>
<section class="adm-hero">
    <p class="adm-hero__greet"><?= e($greeting) ?></p>
    <h2 class="adm-hero__name"><?= e((string) $admin['name']) ?></h2>
    <p><span class="adm-badge"><?= e(AdminAuth::roleLabel((string) $admin['role'])) ?></span></p>
    <p class="adm-hero__date"><?= e($today) ?></p>
    <p class="adm-hero__note">
        <?php if ($pendingTotal > 0): ?>
            <strong><?= e(bn($pendingTotal)) ?>টি</strong> নতুন আবেদন আপনার অপেক্ষায় আছে।
        <?php else: ?>
            এখন নতুন কোনো আবেদন অপেক্ষায় নেই।
        <?php endif; ?>
    </p>
</section>

<div class="adm-dash">
    <div class="adm-dash__main">
        <?php // এই জায়গায় পরে দুটি গ্রাফ বসবে (বিষয় ঠিক হলে) ?>
        <div class="adm-reserved" aria-hidden="true">এই জায়গায় দুটি গ্রাফ আসবে</div>
    </div>
    <aside class="adm-dash__side" aria-label="সংখ্যার সারাংশ">
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
    </aside>
</div>
