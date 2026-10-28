<?php
/**
 * "ইংরেজি অক্ষরে লিখলে বাংলা হবে" ছোট সুইচ (Avro ফোনেটিক, app.js)।
 * যে ঘরে data-bn অ্যাট্রিবিউট আছে সেটাই এই সুবিধা পায়। ব্রাউজারে পছন্দ মনে রাখা হয়।
 * নতুন ফর্মে প্রতিটি লেখার ঘরে data-bn দিন এবং ফর্মে এই partial একবার বসান।
 */
?>
<div class="bn-switch" data-bn-switch>
    <span class="bn-switch__text"><?= t('input.bn.label') ?></span>
    <button class="bn-switch__btn" type="button" aria-pressed="true" data-on="<?= e(content('input.bn.on')) ?>" data-off="<?= e(content('input.bn.off')) ?>"><?= t('input.bn.on') ?></button>
</div>
