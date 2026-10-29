<?php
/**
 * নতুন পেইজ: ওপরে নতুন পেইজ বানানোর ঘর, নিচে প্রতিটি পেইজের বাক্স — নাম, ব্যানারের শিরোনাম ও বর্ণনা বদল, লেখা লেখার
 * লিংক (লাইভ পেইজ এডিট), প্রকাশ/লুকানো ও মোছা। নতুন পেইজ লুকানো অবস্থায় জন্মায়, যাতে আগে লেখা হয়ে যায়।
 *
 * @var list<array{slug:string,name:string,heading:string,description:string,hidden:bool,created:string}> $pages
 */

use App\Models\CustomPages;

$base = admin_base() . '/pages';
?>
<div class="adm-box">
    <h2 class="adm-card__title">নতুন পেইজ বানান</h2>
    <form action="<?= e($base) ?>/action" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="add">
        <div class="adm-formgrid">
            <label for="np-name">পেইজের নাম <span class="adm-red">*</span></label>
            <input id="np-name" type="text" name="name" maxlength="80" required placeholder="যেমন ঈদের বিশেষ সেবা">
            <label for="np-heading">ব্যানারের বড় শিরোনাম <span class="adm-red">*</span></label>
            <input id="np-heading" type="text" name="heading" maxlength="150" required placeholder="যেমন ঈদে ঘরে বসেই দক্ষ লোক">
            <label for="np-address">ঠিকানার নাম, ইংরেজিতে</label>
            <input id="np-address" type="text" name="address" maxlength="60" placeholder="যেমন eid-offer (খালি রাখলে নিজে বসে)">
            <label for="np-desc">ছোট বর্ণনা (গুগলের জন্য)</label>
            <input id="np-desc" type="text" name="description" maxlength="300" placeholder="ঐচ্ছিক, এক লাইনে পেইজটা কী নিয়ে">
        </div>
        <p class="adm-hint">পেইজের নাম বসে পথ-নির্দেশে (হোম / নাম) আর ব্রাউজারের ট্যাবে; ব্যানারের শিরোনাম বসে টিল ব্যানারে। ঠিকানা হবে <code>/page/ঠিকানার-নাম</code>, একবার বসলে আর বদলায় না। নতুন পেইজ প্রথমে লুকানো থাকে।</p>
        <p><button class="adm-small adm-small--go" type="submit">পেইজ বানান</button></p>
    </form>
</div>

<?php if ($pages === []): ?>
    <div class="adm-box"><p class="adm-muted">এখনও কোনো নতুন পেইজ বানানো হয়নি।</p></div>
<?php endif; ?>

<?php foreach (array_reverse($pages) as $p):
    $url = CustomPages::url($p['slug']);
    $hidden = csrf_field() . '<input type="hidden" name="slug" value="' . e($p['slug']) . '">';
?>
    <section class="adm-box" id="p-<?= e($p['slug']) ?>">
        <h2 class="adm-card__title"><?= e($p['name']) ?>
            <?= $p['hidden'] ? '<span class="adm-badge">লুকানো</span>' : '<span class="adm-badge adm-badge--ok">প্রকাশিত</span>' ?></h2>
        <p class="adm-muted">ঠিকানা: <?php if ($p['hidden']): ?><code><?= e($url) ?></code> (লুকানো, তাই দর্শক পান না)<?php else: ?><a class="adm-link" href="<?= e($url) ?>" target="_blank" rel="noopener"><?= e($url) ?></a><?php endif; ?>
            <?= $p['created'] !== '' ? ' · বানানো: ' . e($p['created']) : '' ?></p>

        <div class="adm-commonrow">
            <a class="adm-small adm-small--go" href="<?= e(admin_base() . '/studio?p=cp-' . $p['slug']) ?>">লেখা লিখুন (লাইভ পেইজ এডিট)</a>
            <form action="<?= e($base) ?>/action" method="post"><?= $hidden ?>
                <?php if ($p['hidden']): ?>
                    <button class="adm-small" type="submit" name="do" value="publish">প্রকাশ করুন</button>
                <?php else: ?>
                    <button class="adm-small" type="submit" name="do" value="hide">লুকান</button>
                <?php endif; ?>
            </form>
            <form action="<?= e($base) ?>/action" method="post" data-confirm="&quot;<?= e($p['name']) ?>&quot; পেইজ ও তার পুরো লেখা চিরতরে মুছে যাবে; ফেরানো যাবে না। মুছবেন?"><?= $hidden ?>
                <button class="adm-small adm-small--warn" type="submit" name="do" value="delete">মুছুন</button>
            </form>
        </div>

        <details class="adm-details">
            <summary>নাম, শিরোনাম ও বর্ণনা বদলান</summary>
            <form action="<?= e($base) ?>/action" method="post"><?= $hidden ?>
                <input type="hidden" name="do" value="update">
                <div class="adm-formgrid">
                    <label for="pn-<?= e($p['slug']) ?>">পেইজের নাম</label>
                    <input id="pn-<?= e($p['slug']) ?>" type="text" name="name" maxlength="80" required value="<?= e($p['name']) ?>">
                    <label for="ph-<?= e($p['slug']) ?>">ব্যানারের বড় শিরোনাম</label>
                    <input id="ph-<?= e($p['slug']) ?>" type="text" name="heading" maxlength="150" required value="<?= e($p['heading']) ?>">
                    <label for="pd-<?= e($p['slug']) ?>">ছোট বর্ণনা</label>
                    <input id="pd-<?= e($p['slug']) ?>" type="text" name="description" maxlength="300" value="<?= e($p['description']) ?>">
                </div>
                <p><button class="adm-small adm-small--go" type="submit">সংরক্ষণ করুন</button></p>
            </form>
        </details>
    </section>
<?php endforeach; ?>

<div class="adm-box">
    <p class="adm-muted">মেনুতে বসাতে: লাইভ পেইজ এডিটের "মেনু সম্পাদনা"-য় নতুন আইটেম যোগ করে লিংকে পেইজের ঠিকানা (<code>/page/…</code>) লিখুন। মেনুতে না থাকলে পেইজটি লাইভ পেইজ এডিটের "অন্যান্য" তালিকায় পাওয়া যায়।</p>
</div>
