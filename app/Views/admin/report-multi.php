<?php
/**
 * "একজন পেশাজীবির একাধিক স্থান ও সেবার নামের তালিকা": শিরোনাম + বন্ধ করুন, এক লাইন, CSV/প্রিন্ট, টেবিল (হেডিং সবসময়,
 * একদম নিচে যোগফল-সারি)। প্রতি পেশাজীবীর প্রথম সারিতে নাম ও সংখ্যা, নিচের সারিগুলোতে বাকি সেবার নাম।
 *
 * @var array{head:list<string>,rows:list<array{cells:list<string>,type:string}>,summary:list<string>} $report
 */
$base = admin_base() . '/reports/multi';
$cols = count($report['head']);
?>
<div class="adm-reporthead adm-reporthead--keep">
    <h2 class="adm-reporttitle">একজন পেশাজীবির একাধিক স্থান ও সেবার নামের তালিকা</h2>
    <a class="adm-btn adm-btn--close" href="<?= e(admin_base() . '/reports') ?>">✕ বন্ধ করুন</a>
</div>
<p class="adm-reportnote">কে কয়টি বিভাগ, জেলা, উপজেলা/থানা ও সেবার জন্য নাম দিয়েছেন তার তালিকা; প্রধান সেবা ওপরে, নিচে বাকিগুলো।</p>

<div class="adm-reportactions">
    <a class="adm-small adm-small--go" href="<?= e($base . '?csv=1') ?>">⬇ CSV ডাউনলোড</a>
    <button class="adm-small" type="button" data-print>🖨 প্রিন্ট</button>
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