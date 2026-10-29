<?php
/**
 * ইমেইলের লেখা: সদস্যদের কাছে যাওয়া ছয় রকম ইমেইল। প্রতিটির বিষয় ও লেখা বদলানো যায়; {…} চিহ্নের জায়গায় পাঠানোর সময় আসল
 * নাম, ঠিকানা ইত্যাদি বসে। নিচের বাটনে নমুনা ইমেইল নিজের ঠিকানায় পাঠিয়ে দেখা যায়।
 *
 * @var array<string,array<string,mixed>> $items  নাম => টেমপ্লেটের তথ্য, current, original, changed
 */
$action = admin_base() . '/mails';
?>
<div class="adm-box">
    <h2 class="adm-card__title">সদস্যদের ইমেইলের লেখা</h2>
    <p class="adm-muted">নিচের ছয় রকম ইমেইল সদস্যদের কাছে যায়। বিষয় ও লেখা বদলাতে পারবেন। {name}-ধাঁচের শব্দগুলো (বন্ধনীসহ) যেমন আছে তেমন রাখুন; পাঠানোর সময় সেখানে আসল নাম, ঠিকানা বা কোড বসে। পাসওয়ার্ড কোনো ইমেইলে যায় না।
        "নমুনা ইমেইল পাঠান" চাপলে এই ঘরের লেখা (সংরক্ষণ না করেও) আপনার নিজের ইমেইলে নমুনা মান বসিয়ে যাবে।</p>
</div>

<?php foreach ($items as $id => $item): ?>
    <section class="adm-box" id="m-<?= e($id) ?>">
        <h2 class="adm-card__title"><?= e($item['title']) ?><?= $item['changed'] ? ' <span class="adm-badge">বদলানো</span>' : '' ?></h2>
        <p class="adm-muted"><b>কখন যায়:</b> <?= e($item['when']) ?></p>
        <form action="<?= e($action) ?>" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= e($id) ?>">

            <label for="m-<?= e($id) ?>-s"><b>বিষয়</b></label>
            <input id="m-<?= e($id) ?>-s" type="text" name="subject" maxlength="200" required value="<?= e($item['current']['subject']) ?>">

            <label for="m-<?= e($id) ?>-b"><b>লেখা</b></label>
            <textarea id="m-<?= e($id) ?>-b" name="body" rows="<?= max(8, substr_count((string) $item['current']['body'], "\n") + 3) ?>" maxlength="3000" required><?= e($item['current']['body']) ?></textarea>

            <p class="adm-hint"><b>বসানোর চিহ্ন:</b>
                <?php foreach ($item['vars'] as $var => $meaning): ?>
                    <code><?= e($var) ?></code> = <?= e($meaning) ?><?= in_array($var, $item['required'], true) ? ' <b>(থাকতেই হবে)</b>' : '' ?>;
                <?php endforeach; ?>
            </p>

            <div class="adm-commonrow">
                <button class="adm-small adm-small--go" type="submit" name="do" value="save">সংরক্ষণ করুন</button>
                <button class="adm-small" type="submit" name="do" value="test">নমুনা ইমেইল পাঠান</button>
                <?php if ($item['changed']): ?>
                    <button class="adm-small adm-small--warn" type="submit" name="do" value="reset" formnovalidate>শুরুর লেখায় ফিরুন</button>
                <?php endif; ?>
            </div>
        </form>
    </section>
<?php endforeach; ?>
