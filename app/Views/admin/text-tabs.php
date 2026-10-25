<?php
/**
 * "সাইটের লেখা"-র তিন ট্যাব: খোলা পেইজ (/pages), পেইজের ছোট লেখা ও বার্তা (/texts)।
 *
 * @var string $activeTab  'open' | 'page' | 'msg'
 */
$tabs = [
    'open' => [admin_base() . '/pages', 'খোলা পেইজ (পুরো লেখা)'],
    'page' => [admin_base() . '/texts?tab=page', 'পেইজের ছোট লেখা'],
    'msg' => [admin_base() . '/texts?tab=msg', 'বার্তা'],
];
?>
<nav class="adm-tabs" aria-label="সাইটের লেখার ভাগ">
    <?php foreach ($tabs as $key => [$href, $label]): ?>
        <a href="<?= e($href) ?>"<?= $activeTab === $key ? ' aria-current="true"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>
