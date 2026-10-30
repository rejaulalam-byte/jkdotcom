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
    'member_trash' => 'ডাস্টবিনে পাঠানো', 'member_restore' => 'ফিরিয়ে আনা', 'member_reopen' => 'নতুন আবেদনে ফেরানো', 'member_edit' => 'তথ্য সম্পাদনা', 'member_added' => 'নতুন কর্মী যোগ (অ্যাডমিন থেকে)', 'member_message' => 'সদস্যকে বার্তা', 'job_add' => 'নতুন কাজের বিজ্ঞপ্তি (অ্যাডমিন থেকে)', 'job_edit' => 'কাজের বিজ্ঞপ্তি সম্পাদনা', 'member_purged' => 'চিরতরে মোছা', 'sweep' => 'স্বয়ংক্রিয় ঝাড়া',
    'message_on' => 'বার্তা চালু', 'message_off' => 'বার্তা বন্ধ', 'message_style' => 'বার্তার বাক্সের ডিজাইন', 'mail_edit' => 'ইমেইলের লেখা বদল', 'mail_reset' => 'ইমেইলের লেখা শুরুতে ফেরা', 'mail_test' => 'নমুনা ইমেইল',
    'admin_role_changed' => 'অ্যাকাউন্টের ভূমিকা বদল', 'text_save' => 'লেখা বদল', 'text_reset' => 'লেখা শুরুতে ফেরা', 'page_save' => 'পেইজের লেখা সংরক্ষণ',
    'popup_save' => 'পপ-আপ বিজ্ঞাপন সংরক্ষণ', 'ad_create' => 'নতুন বিজ্ঞাপন', 'ad_update' => 'বিজ্ঞাপন বদল', 'ad_activate' => 'বিজ্ঞাপন চালু', 'ad_deactivate' => 'বিজ্ঞাপন বন্ধ', 'ad_delete' => 'বিজ্ঞাপন মোছা', 'ads_strip_on' => 'বিজ্ঞাপনের সারি দেখানো', 'ads_strip_off' => 'বিজ্ঞাপনের সারি লুকানো',
    'lock_release' => 'আটকানো লগইন খোলা', 'lock_release_device' => 'আটকানো ডিভাইস খোলা', 'lock_release_typed' => 'আটকানো লগইন খোলা (লিখে)',
    'legacy_common_password' => 'পুরনো কর্মীর কমন পাসওয়ার্ড', 'legacy_called' => 'পুরনো কর্মীকে ফোন করা হয়েছে', 'legacy_call_undone' => 'ফোনের টিক তোলা',
    'member_fill' => 'অসম্পূর্ণ তথ্য পূরণ', 'member_fill_mail' => 'তথ্য পূর্ণ করার ইমেইল',
    'feedback_read' => 'মতামত পড়া', 'feedback_unread' => 'মতামত আবার নতুন', 'feedback_keep' => 'মতামত রাখা', 'feedback_unkeep' => 'মতামতের "রাখা" তোলা', 'feedback_delete' => 'মতামত মোছা',  'subscriber_delete' => 'গ্রাহকের ইমেইল মোছা', 'subscribers_csv' => 'গ্রাহকের ইমেইলের CSV নামানো',  'jobs_days' => 'আজকের কাজ দেখানোর দিন বদলানো',  'report_csv' => 'রিপোর্টের CSV নামানো',
    'job_title' => 'কাজের ধরন ঠিক করা', 'job_hide' => 'কাজের বিজ্ঞপ্তি লুকানো', 'job_show' => 'কাজের বিজ্ঞপ্তি প্রকাশ', 'job_delete' => 'কাজের বিজ্ঞপ্তি মোছা',
    'service_add' => 'নতুন সেবা', 'service_rename' => 'সেবার নাম বদল', 'service_icon' => 'সেবার আইকন বদল', 'service_icon_reset' => 'সেবার আইকন শুরুতে ফেরা', 'service_on' => 'সেবা চালু', 'service_off' => 'সেবা বন্ধ', 'service_up' => 'সেবা ওপরে', 'service_down' => 'সেবা নিচে', 'service_custom_assign' => 'কর্মীর নিজের লেখা পেশা বসানো',
    'menu_save' => 'মেনু ও ফুটার সংরক্ষণ', 'menu_reset' => 'মেনু শুরুতে ফেরা', 'box_color' => 'ক্যাপসুলের রং', 'field_color' => 'ফর্মের ঘরের রং', 'field_hide' => 'ফর্মের ঘর লুকানো', 'field_show' => 'ফর্মের ঘর দেখানো',
    'custom_field_add' => 'ফর্মে নতুন ঘর', 'custom_field_update' => 'ফর্মের নতুন ঘর বদল', 'custom_field_delete' => 'ফর্মের নতুন ঘর মোছা', 'custom_field_up' => 'ফর্মের ঘর ওপরে', 'custom_field_down' => 'ফর্মের ঘর নিচে',
    'image_upload' => 'ছবি বদল', 'image_reset' => 'ছবি শুরুতে ফেরা', 'image_hide' => 'ছবি লুকানো', 'image_show' => 'ছবি দেখানো',
    'hero_save' => 'হিরো সেটিং', 'hero_image_removed' => 'হিরোর ছবি মোছা', 'popup_image_removed' => 'পপ-আপের ছবি মোছা', 'footer_removed' => 'ফুটারের লিংক মোছা', 'social_removed' => 'সামাজিক আইকন মোছা', 'service_letters' => 'সেবার বর্ণ-তালিকা বদল',
    'page_add' => 'নতুন পেইজ', 'page_update' => 'পেইজের নাম বদল', 'page_publish' => 'পেইজ প্রকাশ', 'page_hide' => 'পেইজ লুকানো', 'page_delete' => 'পেইজ মোছা',
    'home_section_up' => 'হোমের সেকশন ওপরে', 'home_section_down' => 'হোমের সেকশন নিচে', 'home_section_hide' => 'হোমের সেকশন লুকানো', 'home_section_show' => 'হোমের সেকশন দেখানো',
    'home_section_add_above' => 'হোমে নতুন সেকশন', 'home_section_add_below' => 'হোমে নতুন সেকশন', 'home_section_delete' => 'হোমের সেকশন মোছা', 'home_section_edit' => 'হোমের সেকশন সম্পাদনা', 'theme_colors' => 'থিমের রং', 'theme_sizes' => 'লেখার আকার', 'theme_font' => 'বাংলা ফন্ট',
];
// বিবরণ বাংলায়: পুরনো সারিতেও কাজ করে, কারণ লগে জমা লেখা না বদলে দেখানোর সময় বদলানো হয়
$detailText = static function (string $action, string $detail): string {
    if ($action === 'hero_save') {
        return strtr($detail, [
            ': scatter' => ': চারদিক থেকে', ': right' => ': ডান থেকে', ': left' => ': বাঁ থেকে',
            ': default' => ': সাধারণ', ': color' => ': নিজের রং',
        ]);
    }
    if ($action === 'text_save' || $action === 'text_reset') {
        // লেখার কী-র বদলে সেই লেখার শুরুটা, যাতে বোঝা যায় কোন লেখা
        $original = \App\Models\SiteTexts::original($detail);

        return $original !== '' ? mb_substr(strip_tags($original), 0, 60) . '…' : $detail;
    }
    if (str_starts_with($action, 'lock_release')) {
        return strtr($detail, ['login_worker_' => 'কর্মী: ', 'login_agent_' => 'প্রতিনিধি: ']);
    }

    return $detail;
};
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
                <td><?= e($detailText((string) $row['action'], (string) ($row['detail'] ?? ''))) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($entries === []): ?>
            <tr><td colspan="4">এখনও কিছু লেখা হয়নি।</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
