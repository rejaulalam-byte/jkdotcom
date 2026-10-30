<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\AdminAuth;
use App\Core\Logger;

/**
 * কেউ "আগ্রহী" বলে নিজের নাম-মোবাইল দিয়ে কোনো কর্মীর বা কাজের বিজ্ঞপ্তির ফোন/ইমেইল দেখলে, সংশ্লিষ্ট ব্যক্তিকে ইমেইলে জানায়
 * (দর্শকের নাম ও মোবাইলসহ, যাতে তাঁরা কথা বলে ঠিক করতে পারেন; পপ-আপে দর্শককে এটা আগেই বলা হয়: `today.lead.notice`)।
 * ইমেইল না থাকলে কিছু যায় না (আগ্রহীর তালিকায় সবই থাকে; অ্যাডমিন ফোনে জানান)। লেখা অ্যাডমিন "ইমেইলের লেখা" থেকে বদলান
 * (lead_worker / lead_job)। সীমা: একই দর্শক একই জনের জন্য দিনে একবার; একজন দিনে সর্বোচ্চ MAX_PER_DAY টা।
 */
final class LeadNotice
{
    public const MAX_PER_DAY = 10;

    /**
     * ইমেইল পাঠায়; পাঠানো হলে true। লিড আগেই জমা পড়ে থাকতে হবে (গণনায় এটাও ধরা হয়)।
     *
     * @param 'worker'|'job' $type
     * @param array{name:string,gender:?string,email:string,label:string} $to label = কর্মীর পেশা বা বিজ্ঞপ্তির কাজের ধরন
     */
    public static function send(string $type, int $targetId, array $to, string $kind, string $visitorName, string $visitorPhone): bool
    {
        if ($to['email'] === '') {
            return false;
        }
        if (ContactTrackingRepository::leadsToday($type, $targetId, $visitorPhone) > 1
            || ContactTrackingRepository::leadsToday($type, $targetId) > self::MAX_PER_DAY) {
            return false;
        }

        $vars = [
            '{name}' => honorific_name($to['gender'], $to['name']),
            '{what}' => $kind === 'phone' ? 'ফোন নাম্বার' : 'ইমেইল ঠিকানা',
            '{visitor_name}' => $visitorName,
            '{visitor_phone}' => format_phone($visitorPhone),
            '{time}' => bn_datetime(time()),
            '{helpline}' => content('today.post.helpline'),
        ] + ($type === 'worker' ? ['{service}' => $to['label']] : ['{title}' => $to['label']]);
        $mail = MailTemplates::render($type === 'worker' ? 'lead_worker' : 'lead_job', $vars);
        if (!AdminAuth::mail($to['email'], $mail['subject'], $mail['body'])) {
            Logger::error('Lead notice e-mail could not be sent (' . $type . ' ' . $targetId . ')');

            return false;
        }

        return true;
    }
}
