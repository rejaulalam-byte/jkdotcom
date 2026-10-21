<?php
/**
 * Small switch for "type in English letters, get Bangla" (Avro phonetic, app.js).
 * Fields opt in with the data-bn attribute. The choice is remembered in the browser.
 */
?>
<div class="bn-switch" data-bn-switch>
    <span class="bn-switch__text"><?= e(content('input.bn.label')) ?></span>
    <button class="bn-switch__btn" type="button" aria-pressed="true" data-on="<?= e(content('input.bn.on')) ?>" data-off="<?= e(content('input.bn.off')) ?>"><?= e(content('input.bn.on')) ?></button>
</div>
