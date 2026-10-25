<?php
/**
 * সাইটের ছোট লেখা বা বার্তা: বাঁয়ে ভাগের তালিকা, ডানে সেই ভাগের সব লেখা (একসঙ্গে সংরক্ষণ), ওপরে খোঁজা।
 *
 * @var string $tab
 * @var list<array{title:string,keys:list<string>}> $sections
 * @var int $index
 * @var string $q
 * @var string $heading
 * @var list<array{key:string,value:string,original:string,changed:bool,placeholders:list<string>,markup:bool}> $rows
 */
$activeTab = $tab;
require __DIR__ . '/text-tabs.php';
$base = admin_base() . '/texts';
$here = $base . '?' . http_build_query(array_filter(['tab' => $tab, 's' => $q === '' ? (string) $index : '', 'q' => $q], static fn ($v): bool => $v !== ''));
?>
<p class="adm-muted">
    <?= $tab === 'msg'
        ? 'ফর্ম জমার পরের বার্তা, পপ-আপ, ঘরের নিচের ভুল-বার্তা ইত্যাদি। বাঁয়ে ভাগ বাছুন বা খুঁজুন, লেখা বদলে নিচের "সংরক্ষণ করুন" চাপুন।'
        : 'পেইজের শিরোনাম, ব্যানার, বাটন, সেকশনের লেখা ইত্যাদি। বাঁয়ে ভাগ বাছুন বা খুঁজুন, লেখা বদলে নিচের "সংরক্ষণ করুন" চাপুন।' ?>
    লাল রঙের <b>{name}</b> ধাঁচের অংশ থাকলে সেটা রেখে দিন: সেখানে সাইট নিজে নাম বা এলাকা বসায়।
</p>

<form class="adm-filter" action="<?= e($base) ?>" method="get">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="যে লেখা খুঁজছেন তার কয়েকটা শব্দ" maxlength="100">
    <button class="adm-small" type="submit">খুঁজুন</button>
    <?php if ($q !== ''): ?><a class="adm-link" href="<?= e($base . '?tab=' . $tab) ?>">খোঁজা মুছুন</a><?php endif; ?>
</form>

<div class="adm-texts">
    <nav class="adm-texts__sections" aria-label="ভাগ">
        <?php foreach ($sections as $i => $section): ?>
            <a href="<?= e($base . '?tab=' . $tab . '&s=' . $i) ?>"<?= $q === '' && $i === $index ? ' aria-current="true"' : '' ?>>
                <?= e($section['title']) ?> <small>(<?= e(bn(count($section['keys']))) ?>)</small>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="adm-texts__body">
        <h2 class="adm-card__title"><?= e($heading) ?></h2>
        <?php if ($rows === []): ?>
            <p>কিছু পাওয়া যায়নি।</p>
        <?php else: ?>
            <form id="texts-form" action="<?= e($base) ?>/save" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="back" value="<?= e($here) ?>">
                <?php foreach ($rows as $n => $r): ?>
                    <div class="adm-text">
                        <label for="tx-<?= $n ?>">
                            <?= e(mb_strimwidth($r['original'], 0, 90, '…')) ?>
                            <?php if ($r['changed']): ?><span class="adm-status is-approved">বদলানো</span><?php endif; ?>
                        </label>
                        <textarea id="tx-<?= $n ?>" name="text[<?= e($r['key']) ?>]" rows="<?= mb_strlen($r['value']) > 120 ? 4 : 2 ?>" maxlength="3000"><?= e($r['value']) ?></textarea>
                        <p class="adm-text__meta">
                            <code><?= e($r['key']) ?></code>
                            <?php foreach ($r['placeholders'] as $p): ?> <b class="adm-red"><?= e($p) ?></b><?php endforeach; ?>
                            <?php if ($r['markup']): ?>
                                · রং: <code>[[color:gold|শব্দ]]</code> (gold, amber, white, red), মোটা: <code>{শব্দ}</code>
                            <?php endif; ?>
                            <?php if ($r['changed']): ?>
                                · <button class="adm-link-button" type="submit" name="reset" value="<?= e($r['key']) ?>" form="texts-reset" formaction="<?= e($base) ?>/save">শুরুর লেখায় ফিরুন</button>
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endforeach; ?>
                <p class="adm-texts__save"><button class="adm-btn" type="submit">সংরক্ষণ করুন</button></p>
            </form>
            <?php // "শুরুর লেখায় ফিরুন" আলাদা ফর্মে যায়, যাতে অন্য ঘরে লেখা অর্ধেক বদল একসঙ্গে জমা না পড়ে ?>
            <form id="texts-reset" action="<?= e($base) ?>/save" method="post" data-confirm="এই লেখাটি শুরুর রূপে ফিরবে। ফেরাবেন?">
                <?= csrf_field() ?>
                <input type="hidden" name="back" value="<?= e($here) ?>">
            </form>
        <?php endif; ?>
    </div>
</div>
