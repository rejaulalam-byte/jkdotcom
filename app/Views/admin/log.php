<?php
/**
 * কার্যলগ: কে কখন কী করেছে (সবচেয়ে নতুন আগে, সর্বশেষ ২০০টি)। শুধু সুপার অ্যাডমিন।
 *
 * @var list<array<string,mixed>> $entries
 */
$labels = [
    'setup' => 'প্রথম সুপার অ্যাডমিন তৈরি', 'login' => 'লগইন', 'logout' => 'লগআউট', 'forgot_password' => 'পাসওয়ার্ড ভুলে যাওয়া',
    'password_set' => 'পাসওয়ার্ড বসানো', 'password_changed' => 'পাসওয়ার্ড বদল', 'name_changed' => 'নাম বদল', 'email_changed' => 'ইমেইল বদল',
    'admin_created' => 'নতুন অ্যাডমিন', 'admin_disabled' => 'অ্যাডমিন বন্ধ', 'admin_enabled' => 'অ্যাডমিন চালু', 'permissions_changed' => 'অনুমতি বদল',
    'member_approve' => 'অনুমোদন', 'member_hold' => 'হোল্ড', 'member_reject' => 'বাতিল', 'member_reserve' => 'মোছা (সংরক্ষিতে)',
    'member_trash' => 'ডাস্টবিনে পাঠানো', 'member_restore' => 'ফিরিয়ে আনা', 'member_reopen' => 'নতুন আবেদনে ফেরানো', 'member_edit' => 'তথ্য সম্পাদনা', 'member_purged' => 'চিরতরে মোছা', 'sweep' => 'স্বয়ংক্রিয় ঝাড়া',
    'message_on' => 'বার্তা চালু', 'message_off' => 'বার্তা বন্ধ', 'message_style' => 'বার্তার বাক্সের ডিজাইন',
];
?>
<div class="adm-tablewrap">
    <table class="adm-table">
        <thead><tr><th>সময়</th><th>কে</th><th>কী করেছেন</th><th>বিবরণ</th></tr></thead>
        <tbody>
        <?php foreach ($entries as $row): ?>
            <tr>
                <td><?= e((string) $row['created_at']) ?></td>
                <td><?= e((string) $row['admin_name']) ?></td>
                <td><?= e($labels[(string) $row['action']] ?? (string) $row['action']) ?></td>
                <td><?= e((string) ($row['detail'] ?? '')) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($entries === []): ?>
            <tr><td colspan="4">এখনও কিছু লেখা হয়নি।</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
