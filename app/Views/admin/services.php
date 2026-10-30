<?php
/**
 * সেবার তালিকা: ওপরে নতুন সেবা যোগ, ট্যাব (সব / চালু / বন্ধ / কর্মীদের নিজের লেখা পেশা), খোঁজা, আর প্রতিটি সেবার সারিতে
 * আইকন, নাম বদল, আইকন বদল, চালু/বন্ধ ও ক্রম। "নিজের লেখা পেশা" ট্যাবে প্রতিটি লেখা কতজনের, আর তাদের নতুন সেবায় বা
 * আগের কোনো সেবায় বসানোর ফর্ম।
 *
 * @var string $tab
 * @var string $q
 * @var list<array<string,mixed>> $list
 * @var list<array<string,mixed>> $all
 * @var array<string,int> $counts
 * @var array<string,int> $workers   slug => অনুমোদিত কর্মী
 * @var list<array{text:string,members:list<array{id:int,name:string,status:string}>}> $custom
 */
$base = admin_base() . '/services';
$here = $base . '?' . http_build_query(array_filter(['t' => $tab, 'q' => $q], static fn ($v): bool => $v !== ''));
$tabs = [['', 'সব'], ['on', 'চালু'], ['off', 'বন্ধ'], ['custom', 'কর্মীদের নিজের লেখা পেশা']];
$hidden = static fn (): string => csrf_field() . '<input type="hidden" name="back" value="' . e($here) . '">';
?>
<div class="adm-box">
    <h2 class="adm-card__title">নতুন সেবা যোগ করুন</h2>
    <form class="adm-inline" action="<?= e($base) ?>/action" method="post">
        <?= $hidden() ?>
        <input type="hidden" name="do" value="add">
        <div class="adm-commonrow">
            <input type="text" name="name" maxlength="120" required placeholder="সেবার নাম (বাংলায়), যেমন টাইলস মিস্ত্রি" aria-label="সেবার নাম">
            <input type="text" name="slug" maxlength="80" placeholder="ঠিকানার নাম, ইংরেজিতে (ঐচ্ছিক), যেমন tiles-mistri" aria-label="ঠিকানার নাম">
            <button class="adm-small adm-small--go" type="submit">যোগ করুন</button>
        </div>
        <label class="adm-check"><input type="checkbox" name="confirm_similar" value="1"> তবুও আলাদা সেবা হিসেবে যোগ করুন (শুধু কাছাকাছি নামের সতর্কতা এলে টিক দিন)</label>
        <p class="adm-muted">নতুন সেবা তালিকার শেষে, চালু অবস্থায় বসে (নাম আগের কোনো সেবার সঙ্গে হুবহু বা প্রায় মিললে সতর্ক করে); হোমের চলমান কার্ডে, সেবার তালিকায় ও ফর্মের পেশার তালিকায় সঙ্গে সঙ্গে আসে। ঠিকানার নাম (সাইটের ঠিকানা /services/…) একবার বসলে আর বদলায় না; খালি রাখলে নিজে বানানো হয়। আইকন যোগের পরে নিচের তালিকা থেকে দিন।</p>
    </form>
</div>

<details class="adm-box">
    <summary class="adm-card__title">"সেবা সমূহ" পেইজের বর্ণের তালিকা (অ, আ … ক, খ …)</summary>
    <form action="<?= e($base) ?>/action" method="post">
        <?= $hidden() ?>
        <input type="hidden" name="do" value="letters">
        <label class="sr-only" for="svc-letters">বর্ণের তালিকা</label>
        <textarea id="svc-letters" name="letters" rows="3" maxlength="500"><?= e(implode(' ', \App\Models\ServiceDirectory::letters())) ?></textarea>
        <p class="adm-muted">প্রতিটি বর্ণের মাঝে একটা ফাঁক দিন; এই ক্রমেই পেইজে শিরোনাম সাজে। যে সেবার নামের প্রথম অক্ষর এই তালিকায় নেই, সে পেইজের একদম শেষে "অন্যান্য" শিরোনামে যায়; তাই নতুন বর্ণের সেবা থাকলে বর্ণটা এখানে যোগ করুন।</p>
        <button class="adm-small adm-small--go" type="submit">সংরক্ষণ করুন</button>
    </form>
</details>

<nav class="adm-tabs" aria-label="ভাগ">
    <?php foreach ($tabs as [$key, $label]): ?>
        <a href="<?= e($base . ($key !== '' ? '?t=' . $key : '')) ?>"<?= $tab === $key ? ' aria-current="true"' : '' ?>><?= e($label) ?> <b><?= e(bn((int) $counts[$key])) ?></b></a>
    <?php endforeach; ?>
</nav>

<?php if ($tab === 'custom'): ?>
    <?php if ($custom === []): ?>
        <p class="adm-muted">এখন কোনো কর্মীর নিজের লেখা পেশা যাচাইয়ের অপেক্ষায় নেই।</p>
    <?php else: ?>
        <p class="adm-muted">রেজিস্ট্রেশনে তালিকায় না পেয়ে কর্মীরা নিজে যে পেশা লিখেছেন। বানান দেখে ঠিক করে নতুন সেবা বানান, অথবা আগের কোনো সেবায় বসান। বসানোর পর ওই কর্মীরা সেই সেবার তালিকায় দেখা যাবেন।</p>
        <?php foreach ($custom as $i => $c): ?>
            <section class="adm-box">
                <h3>"<?= e($c['text']) ?>" <span class="adm-muted">— <?= e(bn(count($c['members']))) ?> জন</span></h3>
                <p class="adm-muted">
                    <?php foreach ($c['members'] as $j => $m): ?>
                        <?= $j > 0 ? ', ' : '' ?><a href="<?= admin_base() ?>/members/<?= (int) $m['id'] ?>"><?= e($m['name']) ?></a>
                    <?php endforeach; ?>
                </p>
                <form class="adm-inline" action="<?= e($base) ?>/action" method="post">
                    <?= $hidden() ?>
                    <input type="hidden" name="do" value="custom_new">
                    <input type="hidden" name="custom" value="<?= e($c['text']) ?>">
                    <label for="cn-<?= $i ?>">নতুন সেবা হিসেবে যোগ করুন (দরকার হলে বানান ঠিক করুন)</label>
                    <div class="adm-commonrow">
                        <input id="cn-<?= $i ?>" type="text" name="name" maxlength="120" required value="<?= e($c['text']) ?>">
                        <input type="text" name="slug" maxlength="80" placeholder="ঠিকানার নাম, ইংরেজিতে (ঐচ্ছিক)" aria-label="ঠিকানার নাম">
                        <button class="adm-small adm-small--go" type="submit">নতুন সেবা বানান</button>
                    </div>
                    <label class="adm-check"><input type="checkbox" name="confirm_similar" value="1"> তবুও আলাদা সেবা হিসেবে যোগ করুন (কাছাকাছি নামের সতর্কতা এলে)</label>
                </form>
                <form class="adm-inline" action="<?= e($base) ?>/action" method="post">
                    <?= $hidden() ?>
                    <input type="hidden" name="do" value="custom_assign">
                    <input type="hidden" name="custom" value="<?= e($c['text']) ?>">
                    <label for="ca-<?= $i ?>">অথবা আগের কোনো সেবায় বসান</label>
                    <div class="adm-commonrow">
                        <select id="ca-<?= $i ?>" name="id" required>
                            <option value="">সেবা বাছুন</option>
                            <?php foreach ($all as $s): ?>
                                <option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?><?= $s['is_active'] ? '' : ' (বন্ধ)' ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="adm-small" type="submit">এই সেবায় বসান</button>
                    </div>
                </form>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
<?php else: ?>
    <form class="adm-filter" action="<?= e($base) ?>" method="get">
        <?php if ($tab !== ''): ?><input type="hidden" name="t" value="<?= e($tab) ?>"><?php endif; ?>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="সেবার নাম বা ঠিকানার নাম" maxlength="100">
        <button class="adm-small" type="submit">খুঁজুন</button>
        <?php if ($q !== ''): ?><a class="adm-link" href="<?= e($base . ($tab !== '' ? '?t=' . $tab : '')) ?>">খোঁজা মুছুন</a><?php endif; ?>
    </form>
    <p class="adm-muted">আইকন: হোমের কার্ডে গাঢ় টিল বৃত্তের ভেতরে বসে, তাই সাদা রঙের স্বচ্ছ পটভূমির PNG (বা WebP) সবচেয়ে ভালো, প্রায় ১০০×১০০ পিক্সেল, সর্বোচ্চ ২ MB। বন্ধ সেবা সাইটে কোথাও দেখায় না; ওই পেশার কর্মীদের তথ্য মোছে না, আবার চালু করলে ফিরে আসে।</p>
    <div class="adm-tablewrap">
        <table class="adm-table adm-services">
            <thead>
                <tr><th>ক্রম</th><th>আইকন</th><th>নাম</th><th>ঠিকানা</th><th>কর্মী</th><th>অবস্থা</th><th>আইকন বদল</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($list as $s): ?>
                <tr<?= $s['is_active'] ? '' : ' class="is-off"' ?>>
                    <td class="adm-services__order">
                        <form action="<?= e($base) ?>/action" method="post"><?= $hidden() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                            <button class="adm-small" type="submit" name="do" value="up" aria-label="ওপরে">▲</button>
                            <button class="adm-small" type="submit" name="do" value="down" aria-label="নিচে">▼</button>
                        </form>
                    </td>
                    <td><?php if ($s['icon'] !== null): ?><span class="adm-services__icon"><img src="<?= e((string) $s['icon']) ?>" alt="" width="36" height="36"></span><?php else: ?><span class="adm-red">নেই</span><?php endif; ?></td>
                    <td>
                        <form class="adm-commonrow" action="<?= e($base) ?>/action" method="post"><?= $hidden() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><input type="hidden" name="do" value="rename">
                            <input type="text" name="name" value="<?= e((string) $s['name']) ?>" maxlength="120" required aria-label="সেবার নাম">
                            <button class="adm-small" type="submit">নাম সংরক্ষণ</button>
                        </form>
                    </td>
                    <td><a class="adm-link" href="/services/<?= e(rawurlencode((string) $s['slug'])) ?>" target="_blank" rel="noopener"><?= e((string) $s['slug']) ?></a></td>
                    <td><?= e(bn((int) ($workers[$s['slug']] ?? 0))) ?></td>
                    <td>
                        <form action="<?= e($base) ?>/action" method="post"><?= $hidden() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                            <?php if ($s['is_active']): ?>
                                <span class="adm-status is-approved">চালু</span> <button class="adm-small" type="submit" name="do" value="off">বন্ধ করুন</button>
                            <?php else: ?>
                                <span class="adm-status is-rejected">বন্ধ</span> <button class="adm-small adm-small--go" type="submit" name="do" value="on">চালু করুন</button>
                            <?php endif; ?>
                        </form>
                    </td>
                    <td>
                        <form action="<?= e($base) ?>/action" method="post" enctype="multipart/form-data"><?= $hidden() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><input type="hidden" name="do" value="icon">
                            <input type="file" name="icon" accept="image/png,image/webp,image/jpeg" required aria-label="নতুন আইকন">
                            <button class="adm-small" type="submit">বসান</button>
                        </form>
                    </td>
                    <td>
                        <?php if ($s['icon_path'] !== null && str_starts_with((string) $s['icon_path'], '/page-image/')): ?>
                            <form action="<?= e($base) ?>/action" method="post"><?= $hidden() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                <button class="adm-small" type="submit" name="do" value="icon_reset">সাইটের আইকনে ফিরুন</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
