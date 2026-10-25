<?php
/**
 * হোমের বিজ্ঞাপন-স্ট্রিপ: "স্ট্রিপ দেখাও" সুইচ, নতুন বিজ্ঞাপন যোগ (ছবির নিয়মসহ), এখনকার বিজ্ঞাপনের তালিকা ও বদলানো।
 *
 * @var list<array<string,mixed>> $ads
 * @var bool $stripOn
 * @var array{active:bool,image:string,width:int,height:int,link:string,title:string,pages:list<string>,seconds:int} $popup
 * @var array<string,mixed> $old   জমা ব্যর্থ হলে আগের লেখা (id, title, link, start, end)
 */
use App\Core\AdImage;
use App\Models\AdRepository;

$stateLabel = ['live' => ['দেখাচ্ছে', 'is-approved'], 'upcoming' => ['শুরু হয়নি', 'is-pending'], 'expired' => ['মেয়াদ শেষ', 'is-rejected']];
$post = static fn (string $path): string => e(admin_base()) . '/ads/' . $path;

// ছবির নিয়ম: ফর্মের ছবি-ঘরের ঠিক নিচে (AdImage-এর ধ্রুবক থেকে, তাই যাচাইয়ের সঙ্গে মিলে থাকে)
$rules = static function (): string {
    return '<ul class="adm-rules">'
        . '<li><b>উচ্চতা স্থির:</b> ঠিক ' . e(bn(AdImage::HEIGHT)) . ' পিক্সেল (ধারালো দেখাতে চাইলে দ্বিগুণ, ' . e(bn(AdImage::HEIGHT_2X)) . ' পিক্সেলও চলবে)। সাইটে সব বিজ্ঞাপন একই উচ্চতায় দেখায়; ছোট পর্দায় সব একসাথে সমান অনুপাতে ছোট হয়।</li>'
        . '<li><b>চওড়া স্থির নয়:</b> ' . e(bn(AdImage::MIN_WIDTH)) . ' থেকে ' . e(bn(AdImage::MAX_WIDTH)) . ' পিক্সেল (' . e(bn(AdImage::HEIGHT_2X)) . ' উচ্চতার ছবিতে ' . e(bn(AdImage::MIN_WIDTH * 2)) . ' থেকে ' . e(bn(AdImage::MAX_WIDTH * 2)) . ')। ছবি যত চওড়া, ব্যানারও তত চওড়া হয়ে স্ট্রিপে বসে।</li>'
        . '<li><b>ফরমেট:</b> JPG, PNG বা WebP। ফাইল সাইজ সর্বোচ্চ ' . e(bn((int) (AdImage::MAX_BYTES / 1024))) . ' KB।</li>'
        . '</ul>';
};
// তারিখের ঘরের মান: "Y-m-d H:i:s" থেকে "Y-m-d"
$day = static fn (mixed $v): string => (string) $v !== '' ? substr((string) $v, 0, 10) : '';
?>
<div class="adm-box">
    <h2 class="adm-card__title">পপ-আপ বিজ্ঞাপন</h2>
    <p>
        অবস্থা: <b><?= $popup['active'] && $popup['image'] !== '' ? 'চালু' : 'বন্ধ' ?></b>
        <span class="adm-muted">— বাছা পেইজ খুললেই (প্রতিবার, রিফ্রেশেও) মেনুর ঠিক নিচে পেইজের মাঝখানে দেখায়; ওপরের ডান কোনার (×) চাপলে বা নিচে দেওয়া সময় (এখন <?= e(bn($popup['seconds'])) ?> সেকেন্ড) পরে নিজে সরে যায়।</span>
    </p>
    <form action="<?= $post('popup') ?>" method="post" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <?php if ($popup['image'] !== ''): ?>
            <p><img class="adm-ad__thumb" src="<?= e($popup['image']) ?>" alt="" height="90"> <span class="adm-muted">এখনকার ছবি: <?= e(bn($popup['width'])) ?>×<?= e(bn($popup['height'])) ?> পিক্সেল</span></p>
            <label class="adm-check"><input type="checkbox" name="remove_image" value="1"> ছবিটা সরিয়ে দিন</label>
        <?php endif; ?>
        <label for="pp-image"><?= $popup['image'] !== '' ? 'ছবি বদলাতে নতুন ছবি দিন (না দিলে এখনকারটাই থাকবে)' : 'ছবি' ?></label>
        <input id="pp-image" type="file" name="image" accept="image/jpeg,image/png,image/webp">
        <ul class="adm-rules">
            <li><b>মাপ:</b> ছবি যত বড়, পেইজে ঠিক তত বড়ই দেখায় (মেনুর নিচ থেকে নিচের দিকে)। হোমের ওপরের বড় অংশ (হিরো) ১৯২০ পিক্সেল চওড়া পর্দায় প্রায় ৯৫০ পিক্সেল উঁচু, তাই ছবির উচ্চতা তার চেয়ে কম রাখুন। পরামর্শ: চওড়া ৬০০–১২০০, উচ্চতা ৪০০–৮৫০ পিক্সেল।</li>
            <li><b>ছোট পর্দা:</b> পর্দার চেয়ে চওড়া হলে ছবি অনুপাত ঠিক রেখে নিজে ছোট হয়।</li>
            <li><b>ফরমেট:</b> JPG, PNG বা WebP। ফাইল সাইজ সর্বোচ্চ ২ MB।</li>
        </ul>

        <label for="pp-title">বিজ্ঞাপনের নাম (বিজ্ঞাপনদাতার নাম; ছবির বিকল্প লেখা হিসেবেও যায়)</label>
        <input id="pp-title" type="text" name="title" maxlength="150" value="<?= e($popup['title']) ?>">
        <label for="pp-link">লিংক (ঐচ্ছিক — ছবিতে ক্লিক করলে বিজ্ঞাপনদাতার ওয়েবসাইট নতুন ট্যাবে খোলে)</label>
        <input id="pp-link" type="text" name="link" maxlength="500" placeholder="https://" value="<?= e($popup['link']) ?>">

        <fieldset>
            <legend>কোন পেইজে দেখাবে</legend>
            <?php foreach (App\Models\PopupAd::PAGES as $id => [$label]): ?>
                <label class="adm-check"><input type="checkbox" name="pages[]" value="<?= e($id) ?>"<?= in_array($id, $popup['pages'], true) ? ' checked' : '' ?>> <?= e($label) ?></label>
            <?php endforeach; ?>
        </fieldset>
        <label for="pp-seconds">কত সেকেন্ড দেখাবে (কেউ বন্ধ না করলে; <?= e(bn(App\Models\PopupAd::MIN_SECONDS)) ?> থেকে <?= e(bn(App\Models\PopupAd::MAX_SECONDS)) ?>)</label>
        <input id="pp-seconds" class="adm-short" type="number" name="seconds" min="<?= (int) App\Models\PopupAd::MIN_SECONDS ?>" max="<?= (int) App\Models\PopupAd::MAX_SECONDS ?>" value="<?= (int) $popup['seconds'] ?>" required>
        <label class="adm-check"><input type="checkbox" name="active" value="1"<?= $popup['active'] ? ' checked' : '' ?>> চালু (টিক তুলে দিলে বন্ধ; ছবি ও পছন্দগুলো থাকে)</label>
        <button class="adm-btn" type="submit">পপ-আপ বিজ্ঞাপন সংরক্ষণ করুন</button>
    </form>
</div>

<div class="adm-box">
    <h2 class="adm-card__title">হোমের বিজ্ঞাপন-স্ট্রিপ</h2>
    <p>
        অবস্থা: <b><?= $stripOn ? 'চালু — সক্রিয় বিজ্ঞাপন থাকলে হোমে দেখাবে' : 'বন্ধ — হোমে কিছুই দেখাবে না' ?></b>
        <span class="adm-muted">(সক্রিয় বিজ্ঞাপন একটাও না থাকলে সাদা বক্স নিজেই থাকে না।)</span>
    </p>
    <form action="<?= $post('show') ?>" method="post">
        <?= csrf_field() ?>
        <?php if ($stripOn): ?>
            <button class="adm-small adm-small--warn" type="submit" name="on" value="0">স্ট্রিপ লুকান</button>
        <?php else: ?>
            <button class="adm-small adm-small--go" type="submit" name="on" value="1">স্ট্রিপ দেখান</button>
        <?php endif; ?>
    </form>
</div>

<div class="adm-box">
    <h2 class="adm-card__title">নতুন বিজ্ঞাপন যোগ করুন</h2>
    <form action="<?= $post('save') ?>" method="post" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="0">
        <label for="ad-new-title">বিজ্ঞাপনের নাম (বিজ্ঞাপনদাতার নাম)</label>
        <input id="ad-new-title" type="text" name="title" maxlength="150" required value="<?= e((int) ($old['id'] ?? -1) === 0 ? (string) ($old['title'] ?? '') : '') ?>">

        <label for="ad-new-image">ছবি</label>
        <input id="ad-new-image" type="file" name="image" accept="image/jpeg,image/png,image/webp" required>
        <?= $rules() ?>

        <label for="ad-new-link">লিংক (ঐচ্ছিক — বিজ্ঞাপনদাতার ওয়েবসাইট; ছবিতে ক্লিক করলে সেখানে যাবে)</label>
        <input id="ad-new-link" type="text" name="link" maxlength="500" placeholder="https://" value="<?= e((int) ($old['id'] ?? -1) === 0 ? (string) ($old['link'] ?? '') : '') ?>">

        <div class="adm-commonrow">
            <div>
                <label for="ad-new-start">দেখানো শুরু (ঐচ্ছিক)</label>
                <input id="ad-new-start" type="date" name="start" value="<?= e((int) ($old['id'] ?? -1) === 0 ? (string) ($old['start'] ?? '') : '') ?>">
            </div>
            <div>
                <label for="ad-new-end">দেখানো শেষ (ঐচ্ছিক)</label>
                <input id="ad-new-end" type="date" name="end" value="<?= e((int) ($old['id'] ?? -1) === 0 ? (string) ($old['end'] ?? '') : '') ?>">
            </div>
        </div>
        <label class="adm-check"><input type="checkbox" name="active" value="1" checked> সঙ্গে সঙ্গে চালু</label>
        <button class="adm-btn" type="submit">বিজ্ঞাপন যোগ করুন</button>
    </form>
</div>

<h2 class="adm-card__title">এখনকার বিজ্ঞাপন (<?= e(bn(count($ads))) ?>টি)</h2>
<p class="adm-muted">ক্রম ছোট সংখ্যা আগে। একটা বিজ্ঞাপন হোমে তখনই দেখায় যখন সেটা চালু এবং তারিখের মধ্যে।</p>

<?php foreach ($ads as $ad): ?>
    <?php
    $id = (int) $ad['id'];
    $state = AdRepository::state($ad);
    $isActive = (int) $ad['is_active'] === 1;
    $mine = (int) ($old['id'] ?? -1) === $id;
    $action = static function (string $do, string $label, string $class = '', string $confirm = '') use ($id, $post): string {
        return '<form action="' . $post('action') . '" method="post"' . ($confirm !== '' ? ' data-confirm="' . e($confirm) . '"' : '') . '>'
            . csrf_field() . '<input type="hidden" name="id" value="' . $id . '">'
            . '<button class="adm-small' . ($class !== '' ? ' ' . $class : '') . '" type="submit" name="do" value="' . e($do) . '">' . e($label) . '</button></form>';
    };
    ?>
    <div class="adm-ad">
        <div class="adm-ad__row">
            <img class="adm-ad__thumb" src="<?= e(AdImage::url((string) $ad['image_path'])) ?>" alt="<?= e((string) $ad['title']) ?>" height="90">
            <div class="adm-ad__info">
                <b><?= e((string) $ad['title']) ?></b>
                <?php if ($isActive): ?>
                    <span class="adm-status <?= e($stateLabel[$state][1]) ?>"><?= e($stateLabel[$state][0]) ?></span>
                <?php else: ?>
                    <span class="adm-status is-reserved">বন্ধ</span>
                <?php endif; ?>
                <br>
                <span class="adm-muted">
                    ক্রম <?= e(bn((int) $ad['sort_order'])) ?>
                    · ছবি <?= e(bn((int) $ad['width'])) ?>×<?= e(bn((int) $ad['height'])) ?>
                    · <?= (string) $ad['link_url'] !== '' ? 'লিংক: ' . e((string) $ad['link_url']) : 'লিংক নেই' ?>
                    · <?= $day($ad['starts_at']) !== '' || $day($ad['ends_at']) !== '' ? 'তারিখ: ' . e($day($ad['starts_at']) ?: '—') . ' থেকে ' . e($day($ad['ends_at']) ?: '—') : 'তারিখের সীমা নেই' ?>
                </span>
            </div>
            <div class="adm-actions adm-ad__buttons">
                <?= $isActive ? $action('deactivate', 'বন্ধ করুন') : $action('activate', 'চালু করুন', 'adm-small--go') ?>
                <?= $action('delete', 'মুছুন', 'adm-small--warn', 'এই বিজ্ঞাপন ও তার ছবি চিরতরে মুছে যাবে। মুছবেন?') ?>
            </div>
        </div>
        <details class="adm-ad__edit"<?= $mine ? ' open' : '' ?>>
            <summary class="adm-small">বদলান</summary>
            <form action="<?= $post('save') ?>" method="post" enctype="multipart/form-data" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <label for="ad-t-<?= $id ?>">বিজ্ঞাপনের নাম</label>
                <input id="ad-t-<?= $id ?>" type="text" name="title" maxlength="150" required value="<?= e($mine ? (string) ($old['title'] ?? '') : (string) $ad['title']) ?>">

                <label for="ad-i-<?= $id ?>">ছবি বদলাতে চাইলে নতুন ছবি দিন (না দিলে এখনকারটাই থাকবে)</label>
                <input id="ad-i-<?= $id ?>" type="file" name="image" accept="image/jpeg,image/png,image/webp">
                <?= $rules() ?>

                <label for="ad-l-<?= $id ?>">লিংক (ঐচ্ছিক)</label>
                <input id="ad-l-<?= $id ?>" type="text" name="link" maxlength="500" placeholder="https://" value="<?= e($mine ? (string) ($old['link'] ?? '') : (string) $ad['link_url']) ?>">

                <div class="adm-commonrow">
                    <div>
                        <label for="ad-s-<?= $id ?>">দেখানো শুরু</label>
                        <input id="ad-s-<?= $id ?>" type="date" name="start" value="<?= e($mine ? (string) ($old['start'] ?? '') : $day($ad['starts_at'])) ?>">
                    </div>
                    <div>
                        <label for="ad-e-<?= $id ?>">দেখানো শেষ</label>
                        <input id="ad-e-<?= $id ?>" type="date" name="end" value="<?= e($mine ? (string) ($old['end'] ?? '') : $day($ad['ends_at'])) ?>">
                    </div>
                    <div>
                        <label for="ad-o-<?= $id ?>">ক্রম (ছোট আগে)</label>
                        <input id="ad-o-<?= $id ?>" type="number" name="sort" min="0" max="9999" value="<?= (int) $ad['sort_order'] ?>">
                    </div>
                </div>
                <button class="adm-btn" type="submit">সংরক্ষণ করুন</button>
            </form>
        </details>
    </div>
<?php endforeach; ?>
<?php if ($ads === []): ?>
    <p>এখনও কোনো বিজ্ঞাপন নেই। ওপরের ফর্ম থেকে প্রথমটা যোগ করুন।</p>
<?php endif; ?>
