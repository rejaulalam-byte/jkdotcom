<?php
/**
 * লোকবলের রিপোর্ট: পাঁচটি দিকের প্রতিটির নিজের ঘর ও নিজের "দেখান" বোতাম; যে বোতাম চাপা হয় তার দিকের রিপোর্ট নিচে আসে।
 * পেশাজীবীর নামের ঘরে শুধু দুই বিকল্প: "বাছাই করুন" ও "সকল নাম"।
 *
 * @var string $by                  এখন যে দিকের রিপোর্ট দেখানো হচ্ছে ('' = এখনো কিছু চাপা হয়নি)
 * @var array<string,mixed> $picked GET-এর মান (ঘরে আগের বাছাই ধরে রাখতে)
 * @var array{head:list<string>,rows:list<array{cells:list<string>,type:string}>,summary:list<string>}|null $report
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
$csvQuery = $by !== '' ? http_build_query(['by' => $by, $by => (string) ($picked[$by] ?? ''), 'csv' => '1']) : '';
?>
<div class="adm-reporthead adm-reporthead--keep">
    <h2 class="adm-reporttitle">চাহিদাভিত্তিক বিভিন্ন সেবার লোকবলের তথ্য</h2>
    <a class="adm-btn adm-btn--close" href="<?= e(admin_base() . '/reports') ?>">✕ বন্ধ করুন</a>
</div>

<form class="adm-mp" action="<?= e($base) ?>" method="get">
    <?php foreach (ManpowerReport::DIMENSIONS as $dim => [$label, $allText, $buttonText]): ?>
        <?php $current = (string) ($picked[$dim] ?? ''); ?>
        <div class="adm-mp__row">
            <label for="mp-<?= e($dim) ?>"><?= e($label) ?></label>
            <select id="mp-<?= e($dim) ?>" name="<?= e($dim) ?>">
                <?php if ($dim === 'name'): ?>
                    <option value="">বাছাই করুন</option>
                    <option value="<?= e(ManpowerReport::NAME_ALL) ?>"<?= $current === ManpowerReport::NAME_ALL ? ' selected' : '' ?>>সকল নাম</option>
                <?php else: ?>
                    <option value=""><?= e($allText) ?></option>
                    <?php foreach ($options[$dim] as $id => $name): ?>
                        <option value="<?= e((string) $id) ?>"<?= $current === (string) $id ? ' selected' : '' ?>><?= e((string) $name) ?></option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
            <button class="adm-btn<?= $by === $dim ? ' adm-btn--on' : '' ?>" type="submit" name="by" value="<?= e($dim) ?>"><?= e($buttonText) ?></button>
        </div>
    <?php endforeach; ?>
</form>

<?php if ($report === null): ?>
    <p class="adm-muted">যে দিক থেকে দেখতে চান তার ঘরে বাছাই করে পাশের বোতাম চাপুন। "সব..." রাখলে ওই দিকের সবার তথ্য আসবে।</p>
<?php else: ?>
    <div class="adm-reportactions">
        <?php if ($report['rows'] !== []): ?>
            <a class="adm-small adm-small--go" href="<?= e($base . '?' . $csvQuery) ?>">⬇ CSV ডাউনলোড</a>
            <button class="adm-small" type="button" data-print>🖨 প্রিন্ট</button>
        <?php endif; ?>
    </div>
    <?php if ($report['summary'] !== []): ?>
        <ul class="adm-reportsummary">
            <?php foreach ($report['summary'] as $line): ?><li><?= e($line) ?></li><?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php if ($report['rows'] !== []): ?>
        <div class="adm-tablewrap">
            <table class="adm-table adm-reporttable">
                <thead><tr><?php foreach ($report['head'] as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr></thead>
                <tbody>
                    <?php foreach ($report['rows'] as $row): ?>
                        <tr class="adm-reportrow--<?= e($row['type']) ?>">
                            <?php foreach ($row['cells'] as $cell): ?><td><?= e($cell) ?></td><?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p><a class="adm-btn adm-btn--close" href="<?= e(admin_base() . '/reports') ?>">✕ বন্ধ করুন</a></p>
    <?php endif; ?>
<?php endif; ?>
