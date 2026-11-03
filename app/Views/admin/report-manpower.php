<?php
/**
 * লোকবলের রিপোর্ট: শিরোনামের নিচে এক লাইন, তার নিচে এক সারিতে পাঁচটি ঘর (বিভাগ, জেলা, উপজেলা/থানা, সেবাসমূহ, পেশাজীবির নাম),
 * "মোট সংখ্যা" ঘর আর একটাই "দেখান" বোতাম। একবারে একটা ঘর বাছা হয়; তার দিকের টেবিল নিচে আসে (অন্য ঘর ফাঁকা থাকে)।
 * টেবিলের হেডিং ও একটা খালি সারি সবসময় থাকে; একদম নিচে যোগফল-সারি।
 *
 * @var string $by                 এখন যে দিকের রিপোর্ট ('' = কিছু বাছা হয়নি)
 * @var string $value              ওই ঘরের বাছাই (ALL বা আইডি / slug)
 * @var bool $searched             "দেখান" চাপা হয়েছে কি না
 * @var array{head:list<string>,rows:list<array{cells:list<string>,type:string}>,summary:list<string>,people:int} $report
 * @var array{divisions:list<array{id:int,name:string}>,districts:list<array{id:int,parent:int,name:string}>,upazilas:list<array{id:int,parent:int,name:string}>} $geo
 * @var array<string,string> $services slug => নাম
 */
use App\Models\ManpowerReport;

$base = admin_base() . '/reports/manpower';
$options = [
    'division' => array_column($geo['divisions'], 'name', 'id'),
    'district' => array_column($geo['districts'], 'name', 'id'),
    'upazila' => array_column($geo['upazilas'], 'name', 'id'),
    'service' => $services,
];
$csvQuery = $by !== '' ? http_build_query(['go' => '1', $by => $value, 'csv' => '1']) : '';
$cols = count($report['head']);
?>
<div class="adm-reporthead adm-reporthead--keep">
    <h2 class="adm-reporttitle">চাহিদাভিত্তিক বিভিন্ন সেবার লোকবলের তথ্য</h2>
    <a class="adm-btn adm-btn--close" href="<?= e(admin_base() . '/reports') ?>">✕ বন্ধ করুন</a>
</div>
<p class="adm-reportnote">নিচের ঘর থেকে যে ঘরের তথ্য বাছাই করবেন তার ভিত্তিতে নিচের টেবিলে তথ্য দেখা যাবে</p>

<form class="adm-mp" action="<?= e($base) ?>" method="get">
    <input type="hidden" name="go" value="1">
    <?php foreach (ManpowerReport::DIMENSIONS as $dim => [$label, $allText]): ?>
        <?php $current = $by === $dim ? $value : ''; ?>
        <div class="adm-mp__field">
            <label for="mp-<?= e($dim) ?>"><?= e($label) ?></label>
            <select id="mp-<?= e($dim) ?>" name="<?= e($dim) ?>" data-mp>
                <option value="">বাছাই করুন</option>
                <option value="<?= e(ManpowerReport::ALL) ?>"<?= $current === ManpowerReport::ALL ? ' selected' : '' ?>><?= e($allText) ?></option>
                <?php if ($dim !== 'name'): ?>
                    <?php foreach ($options[$dim] as $id => $name): ?>
                        <option value="<?= e((string) $id) ?>"<?= $current === (string) $id ? ' selected' : '' ?>><?= e((string) $name) ?></option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>
    <?php endforeach; ?>
    <div class="adm-mp__field adm-mp__field--total">
        <span class="adm-mp__label">মোট সংখ্যা</span>
        <output class="adm-mp__total"><?= $by !== '' ? e(bn($report['people'])) . ' জন' : '—' ?></output>
    </div>
    <div class="adm-mp__field adm-mp__field--go">
        <button class="adm-btn" type="submit">দেখান</button>
    </div>
</form>

<div class="adm-reportactions">
    <?php if ($by !== ''): ?>
        <a class="adm-small adm-small--go" href="<?= e($base . '?' . $csvQuery) ?>">⬇ CSV ডাউনলোড</a>
        <button class="adm-small" type="button" data-print>🖨 প্রিন্ট</button>
    <?php endif; ?>
</div>

<?php if ($report['summary'] !== []): ?>
    <ul class="adm-reportsummary">
        <?php foreach ($report['summary'] as $line): ?><li><?= e($line) ?></li><?php endforeach; ?>
    </ul>
<?php endif; ?>

<div class="adm-tablewrap">
    <table class="adm-table adm-reporttable">
        <thead><tr><?php foreach ($report['head'] as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
            <?php foreach ($report['rows'] as $row): ?>
                <tr class="adm-reportrow--<?= e($row['type']) ?>">
                    <?php if ($row['type'] === 'sum'): ?>
                        <td colspan="<?= $cols - 1 ?>"><?= e($row['cells'][0]) ?></td>
                        <td><?= e($row['cells'][1]) ?></td>
                    <?php else: ?>
                        <?php foreach ($row['cells'] as $cell): ?><td><?= e($cell) ?></td><?php endforeach; ?>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
