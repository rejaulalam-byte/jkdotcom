<?php
/**
 * জেন্ডারের ড্রপডাউন (পুরুষ / মহিলা / অন্যান্য), ফর্মে নামের ঠিক পরে বসে।
 * পুরুষ হলে ধন্যবাদ-বার্তায় "মি:", মহিলা হলে "মিস", অন্যান্য হলে শুধু নাম (honorific_name())।
 *
 * @var string $genderId     select-এর id
 * @var string $genderName   ফর্মের ঘরের নাম (POST)
 * @var string $genderLabel  ইঙ্গিত-লেখা (লেবেলও)
 * @var string $genderValue  আগে বাছা মান (জমা ব্যর্থ হলে ফেরত আসে)
 */
?>
<div class="pf pf--select">
    <label class="sr-only" for="<?= e($genderId) ?>"><?= e($genderLabel) ?></label>
    <select id="<?= e($genderId) ?>" name="<?= e($genderName) ?>" required>
        <option value=""><?= e($genderLabel) ?> *</option>
        <?php foreach (['male', 'female', 'other'] as $option): ?>
            <option value="<?= $option ?>"<?= $genderValue === $option ? ' selected' : '' ?>><?= e(content('gender.' . $option)) ?></option>
        <?php endforeach; ?>
    </select>
</div>
