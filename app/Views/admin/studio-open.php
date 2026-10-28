<?php
/**
 * খোলা পেইজের পুরো লেখার পূর্ণ এডিটর (studio.php-এর ফর্মের ভেতরে বসে)। JavaScript না থাকলে সাধারণ ঘরে HTML লেখা যায়।
 *
 * @var string $open
 * @var string $openHtml
 * @var bool $openChanged
 * @var bool|null $lazyEditor  true = লাইভ পেইজ এডিটের বড় বাক্সে (বাক্স খোলার পরে এডিটর শুরু)
 */
?>
<fieldset class="adm-part adm-part--open">
    <legend>পেইজের মূল লেখা (পূর্ণ এডিটর)</legend>
    <ul class="adm-rules">
        <li>লেখার ধরন: প্রথম ঘরে "অনুচ্ছেদ / বড় শিরোনাম / শিরোনাম / ছোট শিরোনাম"; পাশে ফন্ট, অক্ষরের আকার (১০–৭০px), লাইনের ফাঁক ও অনুচ্ছেদের নিচের ফাঁক।</li>
        <li>ছবি: ফাইল এডিটরে টেনে ছেড়ে দিন বা "ছবি" বাটন চাপুন (JPG, PNG বা WebP, সর্বোচ্চ ২ MB)। কোনার চারকোনা টেনে মাপ বদলান; বাঁয়ে/ডানে/মাঝে বসাতে সারিবদ্ধ-করার বাটন বা "ছবি" বাটনের "Class" (PNG-এর কিনারা ঘেঁষে লেখার বিকল্পও আছে)।</li>
        <li>সংরক্ষণের সময় নিরাপত্তার জন্য স্ক্রিপ্ট ও অচেনা জিনিস বাদ যায়।</li>
    </ul>
    <input type="hidden" name="open" value="<?= e($open) ?>">
    <label class="sr-only" for="rich-editor">পেইজের মূল লেখা</label>
    <textarea id="rich-editor" name="html" rows="30"<?= !empty($lazyEditor) ? ' data-lazy' : '' ?>
              data-upload="<?= e(admin_base()) ?>/studio/image"
              data-content-css="<?= e(asset('css/editor-content.css')) ?>"><?= e($openHtml) ?></textarea>
    <?php if ($openChanged): ?>
        <p><button class="adm-small adm-small--warn" type="submit" form="studio-reset" name="reset_open" value="<?= e($open) ?>">পুরো লেখা শুরুর রূপে ফিরুন</button></p>
    <?php endif; ?>
</fieldset>
