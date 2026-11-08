<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\PageVisits;

/**
 * পেইজ-ভিজিটের নিশ্চিতকরণ: পেইজ খোলার পর ব্রাউজারের JavaScript এখানে একটা ছোট অনুরোধ পাঠায়।
 * শুধু তখনই ভিজিট গোনা হয়; কুকি ও JavaScript না চালানো বট এখানে পৌঁছায় না, তাই গোনায় ঢোকে না।
 */
final class VisitController
{
    public function confirm(): void
    {
        PageVisits::confirm();
        http_response_code(204);
    }
}
