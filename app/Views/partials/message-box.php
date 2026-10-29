<?php
/**
 * সফলতা/তথ্যের বার্তার বাক্স, অ্যাডমিনের বাছা ডিজাইনে (App\Models\MessageSwitch): কার্ড (সবুজ টিক), সাধারণ পপ-আপ বা পট্টি।
 * ডিজাইন, রং ও কত সেকেন্ড থাকবে — সুপার অ্যাডমিন লাইভ পেইজ এডিটে ঠিক করেন।
 *
 * @var string       $msgName   MessageSwitch-এর নাম (যেমন regform.worker.done)
 * @var list<string> $msgLines  দেখানোর লাইন, আগেই escape করা HTML
 * @var bool         $msgHome   বন্ধ হলে হোম পেইজে নিয়ে যাবে কি না (ফর্ম জমার পর)
 * @var string       $msgPlace  inline = পেইজের নিজের জায়গায় (পট্টি), overlay = পর্দার ওপরে ভাসে,
 *                              static = লাইভ এডিটের নমুনা (ভাসে না, নিজে সরে না)
 */

use App\Models\MessageSwitch;

$design = MessageSwitch::design($msgName);
$color = MessageSwitch::color($msgName);
$seconds = MessageSwitch::seconds($msgName);
$colorClass = $color !== '' ? ' msgc msgc--' . $color : '';
$static = $msgPlace === 'static';
// নিজে সরে যায়/✕ লাগে এমন বাক্সে JavaScript-এর জন্য চিহ্ন; নমুনায় কিছু বসে না, যাতে নমুনা সরে না যায়
$live = !$static && ($design !== 'strip' || $msgPlace === 'overlay');
$data = $live ? ' data-msg-box data-seconds="' . $seconds . '"' . ($msgHome ? ' data-home="1"' : '') : '';
$staticClass = $static ? ' msg-static' : '';
$close = '<button class="post-toast__close" type="button" aria-label="বন্ধ করুন"><span aria-hidden="true">&times;</span></button>';
?>
<?php if ($design === 'card'): ?>
<div class="post-toast post-toast--card<?= $colorClass . $staticClass ?>"<?= $data ?> role="status" aria-live="polite">
    <img class="post-toast__tick" src="<?= e(asset('img/icons/done-check.svg')) ?>" alt="" width="191" height="193">
    <?= $close ?>
    <?php foreach ($msgLines as $line): ?><p><?= $line ?></p><?php endforeach; ?>
</div>
<?php elseif ($design === 'popup'): ?>
<div class="post-toast post-toast--popup<?= $colorClass . $staticClass ?>"<?= $data ?> role="status" aria-live="polite">
    <?= $close ?>
    <?php foreach ($msgLines as $line): ?><p><?= $line ?></p><?php endforeach; ?>
</div>
<?php elseif ($msgPlace === 'overlay'): ?>
<div class="msg-strip msg-strip--bar<?= $colorClass ?>"<?= $data ?> role="status" aria-live="polite">
    <div class="container msg-strip__in">
        <div class="msg-strip__text"><?php foreach ($msgLines as $line): ?><p><?= $line ?></p><?php endforeach; ?></div>
        <?= str_replace('post-toast__close', 'post-toast__close msg-strip__close', $close) ?>
    </div>
</div>
<?php else: ?>
<div class="notice notice--ok msg-strip<?= $colorClass . $staticClass ?>" role="status"><?php foreach ($msgLines as $line): ?><p><?= $line ?></p><?php endforeach; ?></div>
<?php endif; ?>
