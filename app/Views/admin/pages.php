<?php
/**
 * খোলা পেইজের তালিকা: প্রতিটির নাম, পাবলিক ঠিকানা, বদলানো কি না, "সম্পাদনা" বাটন।
 *
 * @var list<array{slug:string,title:string,url:string,changed:bool}> $pages
 */
$activeTab = 'open';
require __DIR__ . '/text-tabs.php';
?>
<p class="adm-muted">
    এই পেইজগুলোর মূল লেখা একটাই খোলা লেখার জায়গা। "সম্পাদনা" চাপলে পূর্ণ ফরম্যাটিং এডিটর খোলে: শিরোনাম, রং, ফন্ট ও আকার,
    লাইনের ফাঁক, তালিকা, টেবিল, ছবি (টেনে আনা, মাপ বদলানো, লেখার পাশে বসানো) সবই সেখানে।
</p>
<div class="adm-tablewrap">
    <table class="adm-table">
        <thead>
            <tr><th>পেইজ</th><th>সাইটের ঠিকানা</th><th>অবস্থা</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($pages as $p): ?>
            <tr>
                <td><b><?= e($p['title']) ?></b></td>
                <td><a href="<?= e($p['url']) ?>" target="_blank" rel="noopener"><?= e($p['url']) ?></a></td>
                <td><?= $p['changed'] ? '<span class="adm-status is-approved">বদলানো লেখা</span>' : '<span class="adm-muted">শুরুর লেখা</span>' ?></td>
                <td class="adm-actions"><a class="adm-small adm-small--go" href="<?= e(admin_base()) ?>/pages/edit/<?= e($p['slug']) ?>">সম্পাদনা</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
