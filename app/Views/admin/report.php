<?php
/**
 * একটা রিপোর্টের পেইজ: তারিখ (থেকে-পর্যন্ত, শুরুতে আজ), সারাংশের লাইন, টেবিল (সারি, সারাংশ-সারি ও মোট-সারি আলাদা ঢঙে)
 * আর "বন্ধ করুন" (রিপোর্টের বাক্সের পেইজে ফেরা)।
 *
 * @var string $id
 * @var string $from
 * @var string $to
 * @var string $today
 * @var array{head:list<string>,rows:list<array{cells:list<string>,type:string}>,summary:list<string>} $report
 */
use App\Models\ReportData;

$base = admin_base() . '/reports/' . $id;
$isToday = $from === $today && $to === $today;
?>
<div class="adm-reporthead">
    <form class="adm-reportdates" action="<?= e($base) ?>" method="get">
        <label>থেকে <input type="date" name="from" value="<?= e($from) ?>" max="<?= e($today) ?>"></label>
        <label>পর্যন্ত <input type="date" name="to" value="<?= e($to) ?>" max="<?= e($today) ?>"></label>
        <button class="adm-small adm-small--go" type="submit">দেখান</button>
        <?php if (!$isToday): ?><a class="adm-small" href="<?= e($base) ?>">আজকের</a><?php endif; ?>
    </form>
    <a class="adm-btn adm-btn--close" href="<?= e(admin_base() . '/reports') ?>">✕ বন্ধ করুন</a>
</div>

<p class="adm-reportperiod">
    <?php if ($from === $to): ?>
        <b><?= e(ReportData::dayLabel($from)) ?></b><?= $isToday ? ' (আজ)' : '' ?>-এর রিপোর্ট
    <?php else: ?>
        <b><?= e(ReportData::dayLabel($from)) ?></b> থেকে <b><?= e(ReportData::dayLabel($to)) ?></b> পর্যন্ত রিপোর্ট
    <?php endif; ?>
</p>

<?php if ($report['summary'] !== []): ?>
    <ul class="adm-reportsummary">
        <?php foreach ($report['summary'] as $line): ?><li><?= e($line) ?></li><?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php // টেবিল সবসময় আঁকা হয় (কলামের নামসহ); তথ্য না থাকলে এক সারিতে সেটা লেখা থাকে ?>
<div class="adm-tablewrap">
    <table class="adm-table adm-reporttable">
        <thead><tr><?php foreach ($report['head'] as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
            <?php foreach ($report['rows'] as $row): ?>
                <tr class="adm-reportrow--<?= e($row['type']) ?>">
                    <?php foreach ($row['cells'] as $cell): ?><td><?= e($cell) ?></td><?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            <?php if ($report['rows'] === []): ?>
                <tr class="adm-reportrow--empty"><td colspan="<?= count($report['head']) ?>">এই তারিখে এই রিপোর্টে দেখানোর মতো কিছু নেই। ওপরের "থেকে" ও "পর্যন্ত" ঘরে অন্য তারিখ বেছে "দেখান" চাপুন।</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<p><a class="adm-btn adm-btn--close" href="<?= e(admin_base() . '/reports') ?>">✕ বন্ধ করুন</a></p>
