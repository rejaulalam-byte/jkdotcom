<?php
/**
 * "লাইভ পেইজ এডিট": ওপরে পেইজ বাছাইয়ের সারি (studio-bar.php)। সাধারণভাবে নিচে বাছা পেইজটা সাইটে যেমন দেখায় তেমন (ফ্রেমে)।
 * "লেখার তালিকা"-য় ($listView): বাঁয়ে সাইটের পেইজের তালিকা (ভেতরের পেইজ একটু ভেতরে), ডানে বাছা পেইজের সব লেখা
 * পেইজের ক্রমে (খোলা পেইজে পূর্ণ এডিটরসহ), সবশেষে ওই পেইজের বার্তা। একটা "সংরক্ষণ করুন" সব জমা দেয়।
 *
 * @var list<array<string,mixed>> $tree
 * @var array<string,mixed> $node
 * @var string $q
 * @var list<array{title:string,keys:list<string>,messages:bool}> $parts
 * @var array<int,list<array<string,mixed>>> $rows
 * @var string|null $open        খোলা পেইজের slug
 * @var int $openAfter           পূর্ণ এডিটরের আগে কয়টি অংশ
 * @var string $openHtml
 * @var bool $openChanged
 * @var array<string,mixed> $live      LivePages::menus()
 * @var array<string,mixed>|null $page  বাছা পেইজ (id, title, url, note)
 * @var bool $listView
 * @var array<string,mixed> $admin
 */
$base = admin_base() . '/studio';
$here = $base . '?' . http_build_query(array_filter(['p' => (string) $node['id'], 'q' => $q, 'view' => $listView ? 'list' : ''], static fn ($v): bool => $v !== ''));

// বাঁয়ের তালিকা (ভেতরের পেইজ একটু ভেতরে)
$menu = static function (array $nodes, int $depth) use (&$menu, $base, $node, $q): string {
    $html = '';
    foreach ($nodes as $n) {
        $current = $q === '' && $n['id'] === $node['id'];
        $html .= '<a class="adm-studio__page adm-studio__page--d' . $depth . '" href="' . e($base . '?p=' . $n['id'] . '&view=list') . '"' . ($current ? ' aria-current="page"' : '') . '>'
            . e((string) $n['title']) . '</a>';
        $html .= $menu($n['children'] ?? [], $depth + 1);
    }

    return $html;
};

// একটা অংশের সব লেখার ঘর
$fields = static function (array $list) use ($base): string {
    $html = '';
    foreach ($list as $r) {
        static $n = 0;
        $n++;
        $id = 'st-' . $n;
        $html .= '<div class="adm-text"><label for="' . $id . '">'
            . ($r['label'] !== '' ? '<b>' . e($r['label']) . '</b> · ' : '')
            . '<span class="adm-text__orig">' . e(mb_strimwidth((string) $r['original'], 0, 90, '…')) . '</span>'
            . ($r['changed'] ? ' <span class="adm-status is-approved">বদলানো</span>' : '')
            . ($r['owner'] !== null ? ' <a class="adm-text__owner" href="' . e($base . '?p=' . $r['owner'][0]) . '">' . e($r['owner'][1]) . '</a>' : '')
            . '</label>'
            . '<textarea id="' . $id . '" name="text[' . e((string) $r['key']) . ']" rows="' . (mb_strlen((string) $r['value']) > 120 ? 4 : 2) . '" maxlength="3000">' . e((string) $r['value']) . '</textarea>'
            . '<p class="adm-text__meta">';
        foreach ($r['placeholders'] as $p) {
            $html .= '<b class="adm-red">' . e($p) . '</b> রেখে দিন (এখানে সাইট নাম/এলাকা বসায়) ';
        }
        if ($r['markup']) {
            $html .= 'রং: <code>[[color:gold|শব্দ]]</code> (' . e(implode(', ', array_keys(TEXT_COLORS))) . '), মোটা: <code>{শব্দ}</code>, বাঁকা: <code>[[i|শব্দ]]</code>, নিচে দাগ: <code>[[u|শব্দ]]</code> ';
        }
        if ($r['changed']) {
            $html .= '<button class="adm-link-button" type="submit" form="studio-reset" name="reset" value="' . e((string) $r['key']) . '">শুরুর লেখায় ফিরুন</button>';
        }
        $html .= '</p></div>';
    }

    return $html;
};
?>
<?php require __DIR__ . '/studio-bar.php'; ?>

<?php if (!$listView && $page !== null): ?>
    <?php $frame = (string) $page['url'] . (str_contains((string) $page['url'], '?') ? '&' : '?') . 'live=1'; ?>
    <section class="adm-live">
        <p class="adm-live__note"><strong class="adm-live__title"><?= e((string) $page['title']) ?></strong> — যে লেখার ওপর মাউস রাখলে কমলা দাগ আসে, সেটায় ক্লিক করুন: সেখানেই বাক্স খুলবে, বদলে "সংরক্ষণ করুন" চাপুন। লিংক ও বাটন এখানে কাজ করে না; অন্য পেইজ ওপরের ড্রপডাউন থেকে খুলুন।</p>
        <?php if ($open !== null): ?>
            <p class="adm-live__note">পেইজের মূল লেখার (বড় লেখা) যেকোনো জায়গায় ক্লিক করলে পূর্ণ এডিটর খুলবে।</p>
        <?php endif; ?>
        <iframe class="adm-live__frame" src="<?= e($frame) ?>" title="<?= e((string) $page['title']) ?> — সাইটে যেমন দেখায়" sandbox="allow-same-origin allow-scripts allow-modals allow-top-navigation-by-user-activation"></iframe>
    </section>
    <?php if (\App\Core\AdminAuth::can($admin, 'footer')): ?>
        <?php $menuHeader = \App\Core\AdminAuth::isSuper($admin); ?>
        <?php require __DIR__ . '/studio-menu.php'; ?>
    <?php endif; ?>
    <?php if ($open !== null): ?>
        <?php // পেইজের মূল লেখার পূর্ণ এডিটর: ফ্রেমের লেখায় ক্লিক করলে admin.js বাক্সটা খোলে ?>
        <dialog class="adm-rich-dialog" id="rich-dialog" aria-label="পেইজের মূল লেখা বদলান">
            <form id="studio-form" class="adm-editor" action="<?= e($base) ?>/save" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="back" value="<?= e($here) ?>">
                <?php $lazyEditor = true; require __DIR__ . '/studio-open.php'; ?>
                <p class="adm-rich-dialog__row">
                    <button class="adm-btn" type="submit">সংরক্ষণ করুন</button>
                    <button class="adm-small" type="button" data-rich-close>বন্ধ করুন</button>
                </p>
            </form>
            <form id="studio-reset" action="<?= e($base) ?>/save" method="post" data-confirm="পুরো লেখা শুরুর রূপে ফিরবে। ফেরাবেন?">
                <?= csrf_field() ?>
                <input type="hidden" name="back" value="<?= e($here) ?>">
            </form>
        </dialog>
    <?php endif; ?>
<?php else: ?>
<div class="adm-studio__layout">
    <nav class="adm-studio__pages" aria-label="সাইটের পেইজ">
        <form class="adm-studio__search" action="<?= e($base) ?>" method="get">
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="লেখা খুঁজুন" maxlength="100" aria-label="লেখা খুঁজুন">
        </form>
        <?= $menu($tree, 0) ?>
    </nav>

    <section class="adm-studio__main">
        <div class="adm-studio__head">
            <h1><?= e((string) $node['title']) ?></h1>
            <?php if ($page !== null && $page['url'] !== null && $q === ''): ?>
                <a class="adm-link" href="<?= e($base . '?p=' . $page['id']) ?>">লাইভ পেইজে দেখুন</a>
            <?php endif; ?>
        </div>
        <?php if ($page !== null && $page['note'] !== null && $q === ''): ?>
            <p class="adm-muted"><?= e((string) $page['note']) ?> এর লেখা নিচে বদলান।</p>
        <?php endif; ?>

        <?php if ($parts === [] && $open === null): ?>
            <p class="adm-muted">এই নামের নিচের পেইজগুলো বাঁয়ের তালিকা থেকে বাছুন।</p>
        <?php else: ?>
            <form id="studio-form" class="adm-editor" action="<?= e($base) ?>/save" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="back" value="<?= e($here) ?>">
                <?php foreach ($parts as $i => $part): ?>
                    <?php if ($open !== null && $i === $openAfter): ?>
                        <?php require __DIR__ . '/studio-open.php'; ?>
                    <?php endif; ?>
                    <fieldset class="adm-part<?= $part['messages'] ? ' adm-part--msg' : '' ?>">
                        <legend><?= e($part['title']) ?></legend>
                        <?= $fields($rows[$i] ?? []) ?>
                    </fieldset>
                <?php endforeach; ?>
                <?php if ($open !== null && $openAfter >= count($parts)): ?>
                    <?php require __DIR__ . '/studio-open.php'; ?>
                <?php endif; ?>
                <p class="adm-texts__save"><button class="adm-btn" type="submit">সংরক্ষণ করুন</button></p>
            </form>
            <?php // "শুরুর লেখায় ফিরুন" আলাদা ফর্মে, যাতে অন্য ঘরের অর্ধেক বদল একসঙ্গে জমা না পড়ে ?>
            <form id="studio-reset" action="<?= e($base) ?>/save" method="post" data-confirm="এই লেখাটি শুরুর রূপে ফিরবে। ফেরাবেন?">
                <?= csrf_field() ?>
                <input type="hidden" name="back" value="<?= e($here) ?>">
            </form>
        <?php endif; ?>
    </section>
</div>
<?php endif; ?>
<?php if ($open !== null): ?>
    <script src="<?= e(asset('vendor/tinymce/tinymce.min.js')) ?>"></script>
    <script src="<?= e(asset('js/admin-editor.js')) ?>"></script>
<?php endif; ?>
