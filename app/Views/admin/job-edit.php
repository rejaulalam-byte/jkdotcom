<?php
/**
 * কাজের বিজ্ঞপ্তি যোগ বা সম্পাদনা (অ্যাডমিন): কাজের ধরন, পূর্ণ এডিটরে বিস্তারিত, যোগাযোগ, এলাকা, প্রচারের তারিখ, প্রকাশিত/লুকানো।
 * বিভাগ → জেলা → উপজেলা একটা অন্যটাকে ছাঁকে (admin.js [data-geo])।
 *
 * @var array<string,mixed> $v      ফর্মের মান (ভুলের পর লেখা মান, নইলে সংরক্ষিত)
 * @var int $id                     ০ = নতুন
 * @var string $html                এডিটরের লেখা
 * @var array{divisions:list<array<string,mixed>>,districts:list<array<string,mixed>>,upazilas:list<array<string,mixed>>} $geo
 * @var list<array{name:string,slug:string}> $services
 */
$val = static fn (string $k): string => e((string) ($v[$k] ?? ''));
$selected = static fn (mixed $current, mixed $value): string => (string) $current === (string) $value ? ' selected' : '';
$gender = (string) ($v['contact_gender'] ?? '');
$status = (string) ($v['status'] ?? 'approved');
?>
<p><a class="adm-link" href="<?= e(admin_base() . '/jobs') ?>">← বিজ্ঞপ্তির তালিকায় ফিরুন (সংরক্ষণ ছাড়া)</a></p>

<form class="adm-editor adm-editform" action="<?= e(admin_base() . '/job-save') ?>" method="post" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">

    <section class="adm-box">
        <h2 class="adm-card__title">কাজ</h2>
        <div class="adm-formgrid">
            <label for="je-title">কাজের ধরন <span class="adm-red">*</span></label>
            <div>
                <input id="je-title" type="text" name="title" value="<?= $val('title') ?>" maxlength="190" required list="je-kinds">
                <datalist id="je-kinds">
                    <?php foreach ($services as $s): ?><option value="<?= e($s['name']) ?>"></option><?php endforeach; ?>
                </datalist>
                <p class="adm-hint">তালিকা থেকে বাছতে পারেন, অথবা নিজে লিখতে পারেন।</p>
            </div>
            <label for="je-start">প্রচার শুরু <span class="adm-red">*</span></label>
            <input id="je-start" type="date" name="start_date" value="<?= $val('start_date') ?>" required>
            <label for="je-end">প্রচার শেষ</label>
            <div>
                <input id="je-end" type="date" name="end_date" value="<?= $val('end_date') ?>">
                <p class="adm-hint">খালি রাখলে শুরুর তারিখ থেকে নিয়ম অনুযায়ী (এখন ৩ দিন) দেখা যায়।</p>
            </div>
            <span>অবস্থা</span>
            <div>
                <label class="adm-radio"><input type="radio" name="status" value="approved"<?= $status === 'approved' ? ' checked' : '' ?>> প্রকাশিত</label>
                <label class="adm-radio"><input type="radio" name="status" value="pending"<?= $status === 'pending' ? ' checked' : '' ?>> লুকানো</label>
            </div>
        </div>
    </section>

    <section class="adm-box">
        <h2 class="adm-card__title">বিস্তারিত (পূর্ণ এডিটর) <span class="adm-red">*</span></h2>
        <p class="adm-hint">লেখা, ছবি (টেনে ছেড়ে দিন), টেবিল, তালিকা, রং, অক্ষরের মাপ — সব টুল ওপরের সারিতে। সংরক্ষণের সময় নিরাপত্তার জন্য স্ক্রিপ্ট ও অচেনা জিনিস বাদ যায়।</p>
        <label class="sr-only" for="rich-editor">বিস্তারিত</label>
        <textarea id="rich-editor" name="html" rows="18"
                  data-upload="<?= e(admin_base()) ?>/studio/image"
                  data-content-css="<?= e(asset('css/editor-content.css')) ?>"><?= e($html) ?></textarea>
    </section>

    <section class="adm-box">
        <h2 class="adm-card__title">যোগাযোগ</h2>
        <div class="adm-formgrid">
            <label for="je-name">নাম <span class="adm-red">*</span></label>
            <input id="je-name" type="text" name="contact_name" value="<?= $val('contact_name') ?>" maxlength="100" required>
            <label for="je-gender">জেন্ডার</label>
            <select id="je-gender" name="contact_gender">
                <option value="">— জানা নেই —</option>
                <option value="male"<?= $selected($gender, 'male') ?>>পুরুষ</option>
                <option value="female"<?= $selected($gender, 'female') ?>>মহিলা</option>
                <option value="other"<?= $selected($gender, 'other') ?>>অন্যান্য</option>
            </select>
            <label for="je-phone">মোবাইল (১১ সংখ্যা) <span class="adm-red">*</span></label>
            <input id="je-phone" type="text" name="phone" inputmode="numeric" maxlength="14" value="<?= $val('phone') ?>" placeholder="01XXXXXXXXX" required>
            <label for="je-email">ইমেইল</label>
            <input id="je-email" type="email" name="email" value="<?= $val('email') ?>" maxlength="150" autocapitalize="none" placeholder="না থাকলে ফাঁকা">
            <label for="je-address">ঠিকানা <span class="adm-red">*</span></label>
            <input id="je-address" type="text" name="address" value="<?= $val('address') ?>" maxlength="250" required>
            <label for="je-division">বিভাগ <span class="adm-red">*</span></label>
            <select id="je-division" name="division_id" data-geo="division">
                <option value="">— বাছুন —</option>
                <?php foreach ($geo['divisions'] as $d): ?>
                    <option value="<?= (int) $d['id'] ?>"<?= $selected($v['division_id'] ?? '', $d['id']) ?>><?= e((string) $d['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <label for="je-district">জেলা <span class="adm-red">*</span></label>
            <select id="je-district" name="district_id" data-geo="district">
                <option value="">— বাছুন —</option>
                <?php foreach ($geo['districts'] as $d): ?>
                    <option value="<?= (int) $d['id'] ?>" data-parent="<?= (int) $d['parent'] ?>"<?= $selected($v['district_id'] ?? '', $d['id']) ?>><?= e((string) $d['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <label for="je-upazila">উপজেলা / থানা <span class="adm-red">*</span></label>
            <select id="je-upazila" name="upazila_id" data-geo="upazila">
                <option value="">— বাছুন —</option>
                <?php foreach ($geo['upazilas'] as $u): ?>
                    <option value="<?= (int) $u['id'] ?>" data-parent="<?= (int) $u['parent'] ?>"<?= $selected($v['upazila_id'] ?? '', $u['id']) ?>><?= e((string) $u['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </section>

    <p class="adm-commonrow"><button class="adm-btn" type="submit"><?= $id === 0 ? 'বিজ্ঞপ্তি যোগ করুন' : 'সংরক্ষণ করুন' ?></button></p>
</form>

<script src="<?= e(asset('vendor/tinymce/tinymce.min.js')) ?>"></script>
<script src="<?= e(asset('js/admin-editor.js')) ?>"></script>
