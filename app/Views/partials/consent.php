<?php
/**
 * সম্মতির অংশ (তিন ফর্মে: পেশাজীবী, প্রতিনিধি, কাজ প্রকাশ): ঘোষণার লেখা + টিক-বক্স।
 * টিক ছাড়া জমা হয় না (ব্রাউজারে app.js বার্তা দেখায়, সার্ভারও যাচাই করে)।
 *
 * @var string $consentKey   'worker' | 'agent' | 'post' (লেখার কী: consent.<কী>.text / .check)
 * @var bool $consentChecked আগের চেষ্টায় টিক ছিল কি না
 */
// টিকের লেখায় দুই নীতিমালার নাম লিংক হয়ে যায় (নতুন ট্যাবে খোলে, ফর্ম হারায় না)
$check = strtr(e(content("consent.{$consentKey}.check")), [
    'গোপনীয়তা নীতিমালা' => '<a href="/privacy" target="_blank" rel="noopener">গোপনীয়তা নীতিমালা</a>',
    'ব্যবহারের শর্তাবলি' => '<a href="/terms" target="_blank" rel="noopener">ব্যবহারের শর্তাবলি</a>',
]);
?>
<div class="consent">
    <p class="consent__text"><?= e(content("consent.{$consentKey}.text")) ?></p>
    <div class="consent__box">
        <label class="consent__label">
            <input type="checkbox" name="consent" value="1" required<?= !empty($consentChecked) ? ' checked' : '' ?>>
            <span><?= $check ?></span>
        </label>
    </div>
</div>
