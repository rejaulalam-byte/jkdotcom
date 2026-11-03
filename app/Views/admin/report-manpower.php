<?php
/**
 * লোকবলের রিপোর্ট: শিরোনামের নিচে এক লাইন, তার নিচে এক সারিতে পাঁচটি ঘর (বিভাগ, জেলা, উপজেলা/থানা, সেবাসমূহ, পেশাজীবির নাম),
 * "মোট সংখ্যা" ঘর আর একটাই "দেখান" বোতাম। বিভাগ → জেলা → উপজেলা/থানা একটার সাথে আরেকটা জোড়া (admin.js `data-mp`):
 * বিভাগ বাছলে জেলার ঘরে শুধু তার জেলা, জেলা বাছলে উপজেলার ঘরে শুধু তার উপজেলা আসে; আগের বাছাই ছুটে যায় না।
 * টেবিলের হেডিং ও একটা খালি সারি সবসময় থাকে; উপজেলা শেষে মোট-সারি; একদম নিচে যোগফল-সারি।
 *
 * @var array<string,string> $filters ঘরের বাছাই (ফাঁকা / ALL / আইডি / slug)
 * @var bool $chosen                  অন্তত একটা ঘর বাছা হয়েছে কি না
 * @var array{head:list<string>,rows:list<array{cells:list<string>,type:string}>,summary:list<string>,people:int} $report
 * @var array{divisions:list<array{id:int,name:string}>,districts:list<array{id:int,parent:int,name:string}>,upazilas:list<array{id:int,parent:int,name:string}>} $geo
 * @var array<string,string> $services slug => নাম
 */
use App\Models\ManpowerReport;

$base = admin_base() . '/reports/manpower';
$districtDivision = array_column($geo['districts'], 'parent', 'id');
$cols = count($report['head']);
$csvQuery = http_build_query(array_filter($filters, static fn (string $v): bool => $v !== '') + ['go' => '1', 'csv' => '1']);
$option = static function (string $value, string $text, string $current, array $attrs = []): string {
    $extra = '';
    foreach ($attrs as $k => $v) {
        $extra .= ' ' . $k . '="' . e((string) $v) . '"';
    }

    return '<option value="' . e($value) . '"' . $extra . ($value === $current ? ' selected' : '') . '>' . e($text) . '</option>';
};
?>
<div class="adm-reporthead adm-reporthead--keep">
    <h2 class="adm-reporttitle">চাহিদাভিত্তিক বিভিন্ন সেবার লোকবলের তথ্য</h2>
    <a class="adm-btn adm-btn--close" href="<?= e(admin_base() . '/reports') ?>">✕ বন্ধ করুন</a>
</div>
<p class="adm-reportnote">নিচের ঘর থেকে যে ঘরের তথ্য বাছাই করবেন তার ভিত্তিতে নিচের টেবিলে তথ্য দেখা যাবে</p>

<form class="adm-mp" action="<?= e($base) ?>" method="get">
    <input type="hidden" name="go" value="1">
    <?php foreach (ManpowerReport::DIMENSIONS as $dim => [$label, $allText]): ?>
        <?php $current = $filters[$dim]; ?>
        <div class="adm-mp__field">
            <label for="mp-<?= e($dim) ?>"><?= e($label) ?></label>
            <select id="mp-<?= e($dim) ?>" name="<?= e($dim) ?>" data-mp="<?= e($dim) ?>">
                <?= $option('', 'বাছাই করুন', $current) ?>
                <?= $option(ManpowerReport::ALL, $allText, $current) ?>
                <?php if ($dim === 'division'): ?>
                    <?php foreach ($geo['divisions'] as $d): ?><?= $option((string) $d['id'], $d['name'], $current) ?><?php endforeach; ?>
                <?php elseif ($dim === 'district'): ?>
                    <?php foreach ($geo['districts'] as $d): ?><?= $option((string) $d['id'], $d['name'], $current, ['data-division' => $d['parent']]) ?><?php endforeach; ?>
                <?php elseif ($dim === 'upazila'): ?>
                    <?php foreach ($geo['upazilas'] as $u): ?><?= $option((string) $u['id'], $u['name'], $current, ['data-district' => $u['parent'], 'data-division' => $districtDivision[$u['parent']] ?? 0]) ?><?php endforeach; ?>
                <?php elseif ($dim === 'service'): ?>
                    <?php foreach ($services as $slug => $name): ?><?= $option((string) $slug, $name, $current) ?><?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>
    <?php endforeach; ?>
    <div class="adm-mp__field adm-mp__field--total">
        <span class="adm-mp__label">মোট সংখ্যা</span>
        <output class="adm-mp__total"><?= $chosen ? e(bn($report['people'])) . ' জন' : '—' ?></output>
    </div>
    <div class="adm-mp__field adm-mp__field--go">
        <button class="adm-btn" type="submit">দেখান</button>
    </div>
</form>

<div class="adm-reportactions">
    <?php if ($chosen): ?>
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
                    <?php if ($row['type'] === 'sum' || $row['type'] === 'sub'): ?>
                        <td colspan="<?= $cols - 1 ?>"><?= e($row['cells'][0]) ?></td>
                        <td><?= e($row['type'] === 'sum' ? $row['cells'][1] : $row['cells'][$cols - 1]) ?></td>
                    <?php else: ?>
                        <?php foreach ($row['cells'] as $cell): ?><td><?= e($cell) ?></td><?php endforeach; ?>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
