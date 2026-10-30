<?php
/**
 * ড্যাশবোর্ডের একটা বার গ্রাফ (SVG, সার্ভারেই আঁকা; স্টাইল admin.css-এ, তাই CSP আটকায় না)। মাউস রাখলে সংখ্যা দেখায় (<title>)।
 * ছোট গ্রাফে বারের নিচে আড়াআড়ি নাম ও ওপরে সংখ্যা; চওড়া গ্রাফে সেবার নাম নিচে ও সংখ্যা বারের ওপরে খাড়া (শুরু নিচে, শেষ ওপরে); সংখ্যা নিচের টেবিলেও।
 *
 * @var array{title:string,sub:string,wide:bool,groups:list<array{label:string,items:list<array{kind:string,n:int}>}>,empty:?string} $chart
 */
$kinds = ['visits' => 'ভিজিট', 'clicks' => 'ক্লিক'];
$wide = $chart['wide'];
$groups = $chart['groups'];

// মাপ: প্রতিটি দলের জায়গা ঠিক রেখে চওড়া গ্রাফ নাম অনুযায়ী বড় হয়
$bar = $wide ? 16 : 56;      // বারের চওড়া
$gap = 2;                    // একই দলের দুই বারের ফাঁক
$left = 44;                  // বাঁয়ের সংখ্যার জায়গা
$width = $wide ? 720 : 380;
// একটা দলের পুরো জায়গা: চওড়া গ্রাফে সেবা কম থাকলে ছড়িয়ে বসে, বেশি হলে গ্রাফ চওড়া হয়
$band = $wide ? max(32, min(90, ($width - $left) / max(1, count($groups)))) : 150;
$width = max($width, (int) ceil($left + count($groups) * $band + 8));
$top = $wide ? 52 : 26;   // চওড়া গ্রাফে বারের ওপরে খাড়া সংখ্যার জায়গা
$plot = 170;                 // বারের উচ্চতার সীমা
$below = $wide ? 190 : 34;   // নিচের নামের জায়গা
$height = $top + $plot + $below;
$base = $top + $plot;

// y-অক্ষ: সবচেয়ে বড় মানের ওপরে গোল ধাপ (চারটি দাগ)
$max = 0;
foreach ($groups as $g) {
    foreach ($g['items'] as $it) {
        $max = max($max, $it['n']);
    }
}
$step = 1;
for ($scale = 1; $step * 4 < $max; $scale *= 10) {
    foreach ([1, 2, 5] as $unit) {
        $step = $unit * $scale;
        if ($step * 4 >= $max) {
            break;
        }
    }
}
$yMax = $step * 4;
$y = static fn (int $n): float => $base - $plot * $n / $yMax;
?>
<figure class="adm-chart<?= $wide ? ' adm-chart--wide' : '' ?>">
    <figcaption>
        <h3><?= e($chart['title']) ?></h3>
        <p><?= e($chart['sub']) ?></p>
    </figcaption>
    <?php if ($wide): ?>
        <ul class="adm-chart__legend">
            <li><span class="adm-chart__key adm-chart__key--visits"></span>সেবার পেইজে ভিজিট</li>
            <li><span class="adm-chart__key adm-chart__key--clicks"></span>ফোন বা ইমেইলে ক্লিক</li>
        </ul>
    <?php endif; ?>
    <?php if ($groups === []): ?>
        <p class="adm-chart__empty"><?= e((string) $chart['empty']) ?></p>
    <?php else: ?>
        <svg viewBox="0 0 <?= $width ?> <?= $height ?>" role="img" aria-label="<?= e($chart['title'] . ' — ' . $chart['sub']) ?>">
            <?php for ($i = 0; $i <= 4; $i++): $value = $step * $i; $ty = $y($value); ?>
                <line class="adm-chart__grid<?= $i === 0 ? ' adm-chart__grid--base' : '' ?>" x1="<?= $left ?>" x2="<?= $width ?>" y1="<?= $ty ?>" y2="<?= $ty ?>"></line>
                <text class="adm-chart__axis" x="<?= $left - 8 ?>" y="<?= $ty + 5 ?>" text-anchor="end"><?= e(bn($value)) ?></text>
            <?php endfor; ?>
            <?php foreach ($groups as $g => $group): ?>
                <?php
                $count = count($group['items']);
                $groupWidth = $count * $bar + ($count - 1) * $gap;
                $x0 = $left + $band * $g + ($band - $groupWidth) / 2;
                $center = $left + $band * $g + $band / 2;
                ?>
                <g class="adm-chart__group">
                    <?php foreach ($group['items'] as $i => $item): ?>
                        <?php
                        $x = $x0 + $i * ($bar + $gap);
                        $by = $y($item['n']);
                        $r = min(4, $base - $by);
                        ?>
                        <?php if ($item['n'] > 0): // ওপরের কোণ গোল, নিচে সোজা ?>
                            <path class="adm-chart__bar--<?= e($item['kind']) ?>" d="M<?= $x ?> <?= $base ?>V<?= $by + $r ?>Q<?= $x ?> <?= $by ?> <?= $x + $r ?> <?= $by ?>H<?= $x + $bar - $r ?>Q<?= $x + $bar ?> <?= $by ?> <?= $x + $bar ?> <?= $by + $r ?>V<?= $base ?>Z"></path>
                        <?php endif; ?>
                        <?php if (!$wide): ?>
                            <text class="adm-chart__value" x="<?= $x + $bar / 2 ?>" y="<?= $by - 8 ?>" text-anchor="middle"><?= e(bn($item['n'])) ?></text>
                        <?php else: // চওড়া গ্রাফে সংখ্যা বারের ওপরে খাড়া (নিচ থেকে ওপরে পড়া), নামের মতো ?>
                            <text class="adm-chart__value" x="<?= $x + $bar / 2 + 4 ?>" y="<?= $by - 6 ?>" text-anchor="start" transform="rotate(-90 <?= $x + $bar / 2 + 4 ?> <?= $by - 6 ?>)"><?= e(bn($item['n'])) ?></text>
                        <?php endif; ?>
                        <?php // মাউসের জায়গা বারের চেয়ে বড়, যাতে ছোট বারেও সংখ্যা দেখা যায় ?>
                        <rect class="adm-chart__hit" x="<?= $x - 1 ?>" y="<?= $top ?>" width="<?= $bar + $gap ?>" height="<?= $plot ?>"><title><?= e($group['label'] . ': ' . bn($item['n']) . ' ' . ($kinds[$item['kind']] ?? '')) ?></title></rect>
                    <?php endforeach; ?>
                    <?php if ($wide): ?>
                        <text class="adm-chart__label" x="<?= $center ?>" y="<?= $base + 10 ?>" text-anchor="end" dominant-baseline="middle" transform="rotate(-90 <?= $center ?> <?= $base + 10 ?>)"><?= e($group['label']) ?></text>
                    <?php else: ?>
                        <text class="adm-chart__label" x="<?= $center ?>" y="<?= $base + 24 ?>" text-anchor="middle"><?= e($group['label']) ?></text>
                    <?php endif; ?>
                </g>
            <?php endforeach; ?>
        </svg>
        <?php if ($wide): ?>
            <details class="adm-chart__table">
                <summary>সংখ্যায় দেখুন</summary>
                <table class="adm-table">
                    <thead><tr><th>সেবা</th><th>ভিজিট</th><th>ক্লিক</th></tr></thead>
                    <tbody>
                        <?php foreach ($groups as $group): ?>
                            <tr><td><?= e($group['label']) ?></td><?php foreach ($group['items'] as $item): ?><td><?= e(bn($item['n'])) ?></td><?php endforeach; ?></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </details>
        <?php endif; ?>
    <?php endif; ?>
</figure>
