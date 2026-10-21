<?php
/**
 * লেখার ওপরের তিনটি রেজিস্ট্রেশন-নিয়মাবলী বাটন (ওভারভিউ / পেশাজীবী / প্রতিনিধি)।
 * খোলা পেইজের বাটন নিষ্ক্রিয় দেখায়; বাকি দুটো লিংক।
 *
 * @var string $regActive  'rules' | 'pro' | 'agent'
 */
$regTabs = [
    'rules' => ['/registration', 'reg.tab.rules'],
    'pro'   => ['/registration/professional', 'reg.tab.pro'],
    'agent' => ['/registration/agent', 'reg.tab.agent'],
];
?>
<nav class="reg-tabs" aria-label="রেজিস্ট্রেশনের নিয়মাবলী">
    <?php foreach ($regTabs as $key => [$href, $label]): ?>
        <?php if ($key === $regActive): ?>
            <span class="btn btn--teal-soft is-current" aria-current="page"><?= e(content($label)) ?></span>
        <?php else: ?>
            <a class="btn btn--teal-soft" href="<?= e($href) ?>"><?= e(content($label)) ?></a>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
