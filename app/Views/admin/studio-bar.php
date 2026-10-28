<?php
/**
 * "লাইভ পেইজ এডিট"-এর দ্বিতীয় সারি: প্রথমে "সকল পেইজ", তারপর সাইটের মেনুর প্রতিটি আইটেমের ড্রপডাউন
 * (ওই আইটেমের পেইজ ও তার সঙ্গে লিংক-করা পেইজ), শেষে "অন্যান্য" (মেনুতে না-থাকা ও নতুন পেইজ)।
 * বাছা পেইজ যে ড্রপডাউনে আছে সেটা আলাদা রঙে। পেইজ বাছলেই খোলে (admin.js); JavaScript ছাড়া "দেখুন" বাটন।
 *
 * @var array{all:list<array<string,mixed>>,menus:list<array{label:string,pages:list<array<string,mixed>>}>,other:list<array<string,mixed>>} $live
 * @var array<string,mixed> $node
 * @var string $base
 */
$groups = array_merge(
    [['label' => 'সকল পেইজ', 'pages' => $live['all'], 'all' => true]],
    array_map(static fn (array $g): array => $g + ['all' => false], $live['menus']),
    [['label' => 'অন্যান্য', 'pages' => $live['other'], 'all' => false]]
);
$groups = array_filter($groups, static fn (array $g): bool => $g['pages'] !== []);
$currentId = (string) $node['id'];
?>
<nav class="adm-live__bar" aria-label="পেইজ বাছাই">
    <?php foreach ($groups as $i => $group): ?>
        <?php $has = in_array($currentId, array_column($group['pages'], 'id'), true); ?>
        <form class="adm-live__pick<?= $group['all'] ? ' adm-live__pick--all' : '' ?><?= $has && !$group['all'] ? ' is-current' : '' ?>" action="<?= e($base) ?>" method="get" data-auto-submit>
            <label for="live-pick-<?= $i ?>"><?= e($group['label']) ?></label>
            <select id="live-pick-<?= $i ?>" name="p">
                <option value=""<?= $has ? '' : ' selected' ?> disabled>পেইজ বাছুন…</option>
                <?php foreach ($group['pages'] as $p): ?>
                    <option value="<?= e((string) $p['id']) ?>"<?= $has && $p['id'] === $currentId ? ' selected' : '' ?>><?= e((string) $p['title']) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="adm-live__go" type="submit">দেখুন</button>
        </form>
    <?php endforeach; ?>
</nav>
