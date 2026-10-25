<?php
/**
 * একটা খোলা পেইজের লেখা পূর্ণ ফরম্যাটিং এডিটরে (TinyMCE, public/assets/vendor/tinymce) সম্পাদনা।
 * JavaScript ছাড়া এডিটর আসে না; তখন ঘরে সরাসরি HTML লেখা যায় (সংরক্ষণের সময় একই ছাঁকনি চলে)।
 *
 * @var string $slug
 * @var array{title:string,url:string} $page
 * @var string $html     এডিটরে দেখানোর লেখা
 * @var bool $changed    অ্যাডমিন আগে বদলেছেন কি না
 */
?>
<p>
    <a class="adm-link" href="<?= e(admin_base()) ?>/pages">← পেইজের তালিকায় ফিরুন</a>
    · <a class="adm-link" href="<?= e($page['url']) ?>" target="_blank" rel="noopener">সাইটে পেইজটি দেখুন (নতুন ট্যাবে)</a>
</p>
<ul class="adm-rules">
    <li>লেখার ধরন: বাঁয়ের প্রথম ঘরে "অনুচ্ছেদ / বড় শিরোনাম / শিরোনাম / ছোট শিরোনাম"। তার পাশে ফন্ট, অক্ষরের আকার (১০–৭০px), লাইনের ফাঁক ও অনুচ্ছেদের নিচের ফাঁক।</li>
    <li>ছবি: ফাইল এডিটরে টেনে ছেড়ে দিন বা "ছবি" বাটন চাপুন (JPG, PNG বা WebP, সর্বোচ্চ ২ MB)। ছবিতে ক্লিক করে কোনার চারকোনা টেনে মাপ বদলান। ছবি বাঁয়ে/ডানে/মাঝে বসাতে ছবিতে ক্লিক করে সারিবদ্ধ-করার বাটন, অথবা "ছবি" বাটনের "Class" থেকে বাছুন (PNG-এর কিনারা ঘেঁষে লেখা মোড়ানোর বিকল্পও আছে)।</li>
    <li>সংরক্ষণের সময় নিরাপত্তার জন্য স্ক্রিপ্ট ও অচেনা জিনিস বাদ যায়; সাইটে যা দেখাবে তা পেইজে গিয়ে দেখে নিন।</li>
</ul>

<form class="adm-editor" action="<?= e(admin_base()) ?>/pages/save" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="slug" value="<?= e($slug) ?>">
    <label class="sr-only" for="rich-editor">পেইজের লেখা</label>
    <textarea id="rich-editor" name="html" rows="30"
              data-upload="<?= e(admin_base()) ?>/pages/image"
              data-content-css="<?= e(asset('css/editor-content.css')) ?>"><?= e($html) ?></textarea>
    <p class="adm-editor__buttons">
        <button class="adm-btn" type="submit" name="do" value="save">সংরক্ষণ করুন</button>
    </p>
</form>

<?php if ($changed): ?>
    <form action="<?= e(admin_base()) ?>/pages/save" method="post" data-confirm="এডিটরে করা সব বদল মুছে সাইটের শুরুর লেখায় ফিরবে। ফেরাবেন?">
        <?= csrf_field() ?>
        <input type="hidden" name="slug" value="<?= e($slug) ?>">
        <button class="adm-small adm-small--warn" type="submit" name="do" value="reset">শুরুর লেখায় ফিরুন</button>
    </form>
<?php endif; ?>

<script src="<?= e(asset('vendor/tinymce/tinymce.min.js')) ?>"></script>
<script src="<?= e(asset('js/admin-editor.js')) ?>"></script>
