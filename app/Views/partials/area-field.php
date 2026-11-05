<?php
/**
 * "এলাকার নাম" ঘর (শুধু এলাকার ছোট নাম, যেমন আগারগাঁও): লেখার সঙ্গে সাজেশন আসে (datalist, app.js [data-area-field] ভরে),
 * তালিকায় না থাকলে নিজে লেখা যায় — অ্যাডমিন যাচাই করে তালিকায় যোগ করেন। রেজিস্ট্রেশন ও প্রোফাইলে একই।
 *
 * @var string $areaInput    ঘরের id
 * @var string $areaPh       ইঙ্গিত-লেখা (আবশ্যক হলে সঙ্গে " *")
 * @var string $areaValue    এখনকার মান (escape করা)
 * @var bool   $areaRequired আবশ্যক কি না
 * @var bool   $areaOwnLabel true হলে দৃশ্যমান লেবেল ঘরের বাইরে আলাদা দেওয়া আছে (প্রোফাইল), তাই লুকানো লেবেল বসে না
 */
$areaOwnLabel ??= false;
?>
<div class="pf" data-area-field>
    <?php if (!$areaOwnLabel): ?>
        <label class="sr-only" for="<?= e($areaInput) ?>"><?= t('regform.f.area') ?></label>
    <?php endif; ?>
    <input id="<?= e($areaInput) ?>" data-bn type="text" name="area_name" list="<?= e($areaInput) ?>-list" maxlength="100"<?= $areaRequired ? ' required' : '' ?> autocomplete="off"
           placeholder="<?= $areaPh ?>" value="<?= $areaValue ?>">
    <datalist id="<?= e($areaInput) ?>-list"></datalist>
</div>
