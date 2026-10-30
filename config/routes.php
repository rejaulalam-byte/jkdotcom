<?php

declare(strict_types=1);

use App\Controllers\Admin\AccessController as AdminAccess;
use App\Controllers\Admin\AdsController as AdminAds;
use App\Controllers\Admin\FeedbackInboxController as AdminFeedback;
use App\Controllers\Admin\HeroController as AdminHero;
use App\Controllers\Admin\HomeSectionsController as AdminHomeSections;
use App\Controllers\Admin\IncompleteController as AdminIncomplete;
use App\Controllers\Admin\JobsController as AdminJobs;
use App\Controllers\Admin\LeadsController as AdminLeads;
use App\Controllers\Admin\LegacyController as AdminLegacy;
use App\Controllers\Admin\LocksController as AdminLocks;
use App\Controllers\Admin\MailsController as AdminMails;
use App\Controllers\Admin\MemberEditController as AdminMemberEdit;
use App\Controllers\Admin\StudioController as AdminStudio;
use App\Controllers\Admin\MembersController as AdminMembers;
use App\Controllers\Admin\PagesController as AdminPages;
use App\Controllers\Admin\PanelController as AdminPanel;
use App\Controllers\Admin\ReportsController as AdminReports;
use App\Controllers\Admin\ServicesController as AdminServices;
use App\Controllers\Admin\SubscribersController as AdminSubscribers;
use App\Controllers\Admin\ThemeController as AdminTheme;
use App\Controllers\AccountSetupController;
use App\Controllers\AdImageController;
use App\Controllers\PageImageController;
use App\Controllers\AuthController;
use App\Controllers\FeedbackController;
use App\Controllers\HomeController;
use App\Controllers\LegacyRedirectController;
use App\Controllers\PasswordResetController;
use App\Controllers\PageController;
use App\Controllers\ProfileController;
use App\Controllers\RegistrationController;
use App\Controllers\SearchController;
use App\Controllers\ServiceController;
use App\Controllers\SubscribeController;
use App\Controllers\ThemeController;
use App\Controllers\TodayController;
use App\Controllers\WorkerController;

/*
 * পুরনো সাইটের নাম্বার-ঠিকানার স্থায়ী রিডাইরেক্ট (SEO ও সংরক্ষিত লিংকের জন্য): /2001, /3000, /3001?service_id=N, /5001, /6002–/6005, /7000;
 * প্রতিটি /index/<নাম্বার> রূপেও, আর /index নিজে হোমে।
 */
// নিজের ফাংশনের ভেতরে, কারণ এই ফাইল Router::dispatch()-এর ভেতরে require হয়; বাইরের ভেরিয়েবল ($path ইত্যাদি) বদলে গেলে রাউটার ভুল পথ দেখে।
$legacyRoutes = (static function (): array {
    $list = [['GET', '/index', [LegacyRedirectController::class, 'go', ['to' => '/']]]];
    foreach (array_merge(LegacyRedirectController::MAP, ['/3001' => '']) as $old => $new) {
        foreach ([$old, '/index' . $old] as $from) {
            $list[] = ['GET', $from, $new === ''
                ? [LegacyRedirectController::class, 'service']
                : [LegacyRedirectController::class, 'go', ['to' => $new]]];
        }
    }

    return $list;
})();

/*
 * ওয়েবসাইটের প্রতিটি সর্বজনীন URL।
 * ধরন: [HTTP মেথড, পাথ, [Controller::class, 'মেথড', ঐচ্ছিক বাড়তি তথ্য]]
 * (বাড়তি তথ্য যেমন ['role' => 'agent'] কন্ট্রোলারের মেথডে $meta হয়ে পৌঁছায়।)
 * নতুন পেইজ যোগ করতে: কন্ট্রোলারের মেথড + ভিউ বানান, তারপর এখানে এক লাইন যোগ করুন।
 */
return array_merge($legacyRoutes, [
    ['GET',  '/',                [HomeController::class, 'index']],
    ['POST', '/feedback',        [FeedbackController::class, 'store']],
    ['POST', '/subscribe',       [SubscribeController::class, 'store']],

    // Figma-তে নকশা করা পেইজ; একটা একটা করে বানানো হচ্ছে (PROGRESS.md দেখুন)
    ['GET',  '/about',           [PageController::class, 'about']],
    ['GET',  '/page/{slug}',     [PageController::class, 'custom']],
    ['GET',  '/site-theme',      [ThemeController::class, 'css']],
    ['GET',  '/search',          [SearchController::class, 'index']],
    ['GET',  '/services',        [ServiceController::class, 'index']],
    ['GET',  '/services/{slug}', [ServiceController::class, 'show']],
    ['GET',  '/workers/{slug}',  [WorkerController::class, 'show']],
    ['POST', '/workers/rate',    [WorkerController::class, 'rate']],
    ['POST', '/workers/contact', [WorkerController::class, 'contact']],
    ['GET',  '/today',           [TodayController::class, 'index']],
    ['POST', '/today/contact',   [TodayController::class, 'contact']],
    ['GET',  '/today/post',      [TodayController::class, 'postForm']],
    ['POST', '/today/post',      [TodayController::class, 'postStore']],
    ['GET',  '/registration',    [PageController::class, 'registrationRules']],
    ['GET',  '/registration/professional', [PageController::class, 'registrationProfessional']],
    ['GET',  '/registration/professional/form', [RegistrationController::class, 'form', ['role' => 'worker']]],
    ['POST', '/registration/professional/form', [RegistrationController::class, 'store', ['role' => 'worker']]],
    ['GET',  '/registration/agent',        [PageController::class, 'registrationAgent']],
    ['GET',  '/registration/agent/form',   [RegistrationController::class, 'form', ['role' => 'agent']]],
    ['POST', '/registration/agent/form',   [RegistrationController::class, 'store', ['role' => 'agent']]],
    ['GET',  '/login',                     [AuthController::class, 'loginForm', ['role' => 'worker']]],
    ['POST', '/login',                     [AuthController::class, 'loginStore', ['role' => 'worker']]],
    ['GET',  '/login/agent',               [AuthController::class, 'loginForm', ['role' => 'agent']]],
    ['POST', '/login/agent',               [AuthController::class, 'loginStore', ['role' => 'agent']]],
    ['POST', '/logout',                    [AuthController::class, 'logout']],
    ['POST', '/password/forgot',           [PasswordResetController::class, 'request']],
    ['POST', '/password/verify',           [PasswordResetController::class, 'verify']],
    ['POST', '/password/reset',            [PasswordResetController::class, 'reset']],
    ['GET',  '/welcome',                   [AuthController::class, 'welcome']],
    ['GET',  '/account/password',          [AuthController::class, 'passwordForm']],
    ['POST', '/account/password',          [AuthController::class, 'passwordStore']],
    ['GET',  '/account/setup',             [AccountSetupController::class, 'form']],
    ['POST', '/account/setup',             [AccountSetupController::class, 'store']],
    ['GET',  '/photo/{slug}',              [AuthController::class, 'photo']],
    ['GET',  '/ad-image/{slug}',           [AdImageController::class, 'show']],
    ['GET',  '/page-image/{slug}',         [PageImageController::class, 'show']],
    ['GET',  '/profile',                   [ProfileController::class, 'form']],
    ['POST', '/profile',                   [ProfileController::class, 'update']],
    ['GET',  '/dashboard',                 [ProfileController::class, 'dashboardRedirect']],
    ['GET',  '/contact',         [PageController::class, 'contact']],
    ['GET',  '/faq',             [PageController::class, 'faq']],

    // ---- অ্যাডমিন প্যানেল (লগইন ও ইমেইল-কোড: AdminAccess; ভেতরের পেইজ: AdminPanel) ----
    ['GET',  admin_route_base() . '/setup',               [AdminAccess::class, 'setupForm']],
    ['POST', admin_route_base() . '/setup',               [AdminAccess::class, 'setupStore']],
    ['GET',  admin_route_base() . '/login',               [AdminAccess::class, 'loginForm']],
    ['POST', admin_route_base() . '/login',               [AdminAccess::class, 'loginStore']],
    ['GET',  admin_route_base() . '/verify',              [AdminAccess::class, 'verifyForm']],
    ['POST', admin_route_base() . '/verify',              [AdminAccess::class, 'verifyStore']],
    ['POST', admin_route_base() . '/verify/resend',       [AdminAccess::class, 'verifyResend']],
    ['POST', admin_route_base() . '/logout',              [AdminAccess::class, 'logout']],
    ['GET',  admin_route_base() . '/forgot',              [AdminAccess::class, 'forgotForm']],
    ['POST', admin_route_base() . '/forgot',              [AdminAccess::class, 'forgotStore']],
    ['GET',  admin_route_base() . '/set-password/{slug}', [AdminAccess::class, 'setPasswordForm']],
    ['POST', admin_route_base() . '/set-password/{slug}', [AdminAccess::class, 'setPasswordStore']],
    ['GET',  admin_route_base(),                     [AdminPanel::class, 'dashboard']],
    ['GET',  admin_route_base() . '/account',             [AdminPanel::class, 'account']],
    ['POST', admin_route_base() . '/account/name',        [AdminPanel::class, 'accountName']],
    ['POST', admin_route_base() . '/account/password',    [AdminPanel::class, 'accountPassword']],
    ['POST', admin_route_base() . '/account/email',       [AdminPanel::class, 'accountEmail']],
    ['POST', admin_route_base() . '/account/email-confirm', [AdminPanel::class, 'accountEmailConfirm']],
    ['GET',  admin_route_base() . '/admins',              [AdminPanel::class, 'admins']],
    ['POST', admin_route_base() . '/admins/create',       [AdminPanel::class, 'adminsCreate']],
    ['POST', admin_route_base() . '/admins/invite',       [AdminPanel::class, 'adminsInvite']],
    ['POST', admin_route_base() . '/admins/status',       [AdminPanel::class, 'adminsStatus']],
    ['POST', admin_route_base() . '/admins/permissions',  [AdminPanel::class, 'adminsPermissions']],
    ['POST', admin_route_base() . '/admins/role',         [AdminPanel::class, 'adminsRole']],
    ['GET',  admin_route_base() . '/log',                 [AdminPanel::class, 'log']],
    ['GET',  admin_route_base() . '/workers',             [AdminMembers::class, 'list', ['role' => 'worker']]],
    ['GET',  admin_route_base() . '/agents',              [AdminMembers::class, 'list', ['role' => 'agent']]],
    ['GET',  admin_route_base() . '/members/{slug}',      [AdminMembers::class, 'show']],
    ['POST', admin_route_base() . '/members/action',      [AdminMembers::class, 'action']],
    ['POST', admin_route_base() . '/members/message',     [AdminMembers::class, 'message']],
    ['GET',  admin_route_base() . '/member-add',          [AdminMemberEdit::class, 'addForm']],
    ['POST', admin_route_base() . '/member-add',          [AdminMemberEdit::class, 'addSave']],
    ['GET',  admin_route_base() . '/member-add/agent',    [AdminMemberEdit::class, 'addForm', ['role' => 'agent']]],
    ['POST', admin_route_base() . '/member-add/agent',    [AdminMemberEdit::class, 'addSave', ['role' => 'agent']]],
    ['GET',  admin_route_base() . '/member-edit/{slug}',  [AdminMemberEdit::class, 'form']],
    ['POST', admin_route_base() . '/member-edit',         [AdminMemberEdit::class, 'save']],
    ['GET',  admin_route_base() . '/old-workers',         [AdminLegacy::class, 'index']],
    ['POST', admin_route_base() . '/old-workers/call',    [AdminLegacy::class, 'call']],
    ['POST', admin_route_base() . '/old-workers/password', [AdminLegacy::class, 'password']],
    ['GET',  admin_route_base() . '/incomplete',          [AdminIncomplete::class, 'index']],
    ['POST', admin_route_base() . '/incomplete/save',     [AdminIncomplete::class, 'save']],
    ['POST', admin_route_base() . '/incomplete/mail',     [AdminIncomplete::class, 'mail']],
    ['GET',  admin_route_base() . '/feedback',            [AdminFeedback::class, 'index']],
    ['POST', admin_route_base() . '/feedback/action',     [AdminFeedback::class, 'action']],
    ['GET',  admin_route_base() . '/subscribers',         [AdminSubscribers::class, 'index']],
    ['POST', admin_route_base() . '/subscribers/delete',  [AdminSubscribers::class, 'delete']],
    ['GET',  admin_route_base() . '/leads',               [AdminLeads::class, 'index']],
    ['GET',  admin_route_base() . '/jobs',                [AdminJobs::class, 'index']],
    ['POST', admin_route_base() . '/jobs/action',         [AdminJobs::class, 'action']],
    ['POST', admin_route_base() . '/jobs/days',           [AdminJobs::class, 'days']],
    ['GET',  admin_route_base() . '/job-add',             [AdminJobs::class, 'edit']],
    ['GET',  admin_route_base() . '/job-edit/{slug}',     [AdminJobs::class, 'edit']],
    ['POST', admin_route_base() . '/job-save',            [AdminJobs::class, 'save']],
    ['GET',  admin_route_base() . '/locks',               [AdminLocks::class, 'index']],
    ['POST', admin_route_base() . '/locks/release',       [AdminLocks::class, 'release']],
    ['GET',  admin_route_base() . '/ads',                 [AdminAds::class, 'index']],
    ['POST', admin_route_base() . '/ads/save',            [AdminAds::class, 'save']],
    ['POST', admin_route_base() . '/ads/action',          [AdminAds::class, 'action']],
    ['POST', admin_route_base() . '/ads/show',            [AdminAds::class, 'show']],
    ['POST', admin_route_base() . '/ads/popup',           [AdminAds::class, 'popup']],
    ['GET',  admin_route_base() . '/services',            [AdminServices::class, 'index']],
    ['POST', admin_route_base() . '/services/action',     [AdminServices::class, 'action']],
    ['GET',  admin_route_base() . '/mails',               [AdminMails::class, 'index']],
    ['POST', admin_route_base() . '/mails',               [AdminMails::class, 'action']],
    ['GET',  admin_route_base() . '/pages',               [AdminPages::class, 'index']],
    ['POST', admin_route_base() . '/pages/action',        [AdminPages::class, 'action']],
    ['POST', admin_route_base() . '/studio/home-section', [AdminHomeSections::class, 'live']],
    ['GET',  admin_route_base() . '/home-section/{slug}', [AdminHomeSections::class, 'edit']],
    ['POST', admin_route_base() . '/home-section/{slug}', [AdminHomeSections::class, 'save']],
    ['GET',  admin_route_base() . '/reports',             [AdminReports::class, 'index']],
    ['GET',  admin_route_base() . '/reports/{slug}',      [AdminReports::class, 'show']],
    ['GET',  admin_route_base() . '/hero',                [AdminHero::class, 'index']],
    ['POST', admin_route_base() . '/hero',                [AdminHero::class, 'save']],
    ['GET',  admin_route_base() . '/theme',               [AdminTheme::class, 'index']],
    ['POST', admin_route_base() . '/theme',               [AdminTheme::class, 'save']],
    ['GET',  admin_route_base() . '/studio',              [AdminStudio::class, 'index']],
    ['POST', admin_route_base() . '/studio/save',         [AdminStudio::class, 'save']],
    ['POST', admin_route_base() . '/studio/image',        [AdminStudio::class, 'image']],
    ['POST', admin_route_base() . '/studio/live-save',    [AdminStudio::class, 'liveSave']],
    ['POST', admin_route_base() . '/studio/live-image',   [AdminStudio::class, 'liveImage']],
    ['POST', admin_route_base() . '/studio/live-box',     [AdminStudio::class, 'liveBox']],
    ['POST', admin_route_base() . '/studio/live-field',   [AdminStudio::class, 'liveField']],
    ['POST', admin_route_base() . '/studio/live-message', [AdminStudio::class, 'liveMessage']],
    ['POST', admin_route_base() . '/studio/live-custom',  [AdminStudio::class, 'liveCustom']],
    ['POST', admin_route_base() . '/studio/menu',         [AdminStudio::class, 'menu']],
    ['GET',  '/stats',           [PageController::class, 'soon', ['title' => 'এক নজরে আমাদের পরিসংখ্যান', 'notice' => 'শিগগিরই যুক্ত হচ্ছে', 'text' => 'এই পেইজে Jorurikaj.com-এর পরিসংখ্যান শিগগিরই যুক্ত হচ্ছে। ততক্ষণ হোম পেইজ ঘুরে দেখুন।']]],
    ['GET',  '/privacy',         [PageController::class, 'legal', ['page' => 'privacy']]],
    ['GET',  '/terms',           [PageController::class, 'legal', ['page' => 'terms']]],
]);
