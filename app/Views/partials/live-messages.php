<?php
/**
 * শুধু "লাইভ পেইজ এডিট"-এর ফ্রেমে, পেইজের একদম নিচে: এই পেইজের বার্তাগুলো তাদের আসল বাক্সের চেহারায়
 * (App\Models\LiveMessages)। লেখাগুলো content() দিয়ে, তাই ক্লিক করে অন্য লেখার মতোই বদলানো যায়।
 * ধন্যবাদ-কার্ডের লাইনগুলো (একই কী-র শুরু) একটা কার্ডে একসঙ্গে দেখায়।
 */

use App\Core\AdminAuth;
use App\Models\LiveMessages;
use App\Models\MessageSwitch;

$liveGroups = LiveMessages::groups(LiveMessages::pageFor((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH)));
if ($liveGroups === []) {
    return;
}
$liveAdmin = AdminAuth::current();
$liveSuper = $liveAdmin !== null && AdminAuth::isSuper($liveAdmin);

/** একটা বার্তা (বা একসঙ্গে কয়েক লাইনের ধন্যবাদ-কার্ড) তার বাক্সে। @param list<string> $keys */
$liveBox = static function (string $kind, array $keys): string {
    $lines = array_map(static fn (string $k): string => e(content($k)), $keys);

    return match ($kind) {
        'error' => '<ul class="notice notice--error live-msgs__box"><li>' . implode('</li><li>', $lines) . '</li></ul>',
        'ok' => '<p class="notice notice--ok live-msgs__box">' . implode('<br>', $lines) . '</p>',
        'card' => '<div class="post-toast post-toast--card live-msgs__card"><img class="post-toast__tick" src="'
            . e(asset('img/icons/done-check.svg')) . '" alt="" width="191" height="193"><p>' . implode('</p><p>', $lines) . '</p></div>',
        'field' => '<p class="pf__msg live-msgs__box">' . implode('<br>', $lines) . '</p>',
        default => '<p class="live-msgs__plain">' . implode('<br>', $lines) . '</p>',
    };
};
?>
<section class="live-msgs" aria-label="এই পেইজের বার্তা">
    <div class="container">
        <p class="live-msgs__hint">নিচের বার্তাগুলো দর্শক শুধু কোনো কাজের পরে দেখেন (ভুল হলে, জমা দিলে, পপ-আপে)। এখানে সব একসঙ্গে আসল চেহারায় দেখানো হলো; যেকোনো লেখায় ক্লিক করে বদলান। {name}-ধাঁচের শব্দ থাকলে রাখুন, সেখানে দর্শকের নাম বসে। সফলতার বার্তা ও ধন্যবাদ-কার্ড সুপার অ্যাডমিন চালু-বন্ধ করতে পারেন; ভুল-বার্তা সবসময় চালু থাকে। এই অংশ সাধারণ দর্শক দেখেন না।</p>
        <?php foreach ($liveGroups as $group): ?>
            <?php if ($group['folded']): ?><details class="live-msgs__group"><summary class="live-msgs__title"><?= e($group['title']) ?></summary>
            <?php else: ?><div class="live-msgs__group"><h2 class="live-msgs__title"><?= e($group['title']) ?></h2><?php endif; ?>
            <?php
            // একই ধরনের পরপর ধন্যবাদ-কার্ডের লাইন (যেমন regform.worker.done.text_1, text_2) এক কার্ডে
            $runs = [];
            foreach ($group['items'] as [$key, $kind]) {
                $stem = substr($key, 0, (int) strrpos($key, '.'));
                $last = array_key_last($runs);
                if ($kind === 'card' && $last !== null && $runs[$last]['kind'] === 'card' && $runs[$last]['stem'] === $stem) {
                    $runs[$last]['keys'][] = $key;
                } else {
                    $runs[] = ['kind' => $kind, 'stem' => $stem, 'keys' => [$key]];
                }
            }
            ?>
            <?php foreach ($runs as $run):
                // চালু-বন্ধের নাম: ধন্যবাদ-কার্ডে লাইনগুলোর শুরু, অন্যটিতে কী নিজেই
                $switch = $run['kind'] === 'card' ? $run['stem'] : $run['keys'][0];
                $switchable = MessageSwitch::switchable($switch);
                $on = MessageSwitch::isOn($switch);
            ?>
                <div class="live-msgs__item<?= $on ? '' : ' is-off' ?>">
                    <span class="live-msgs__kind"><?= e(LiveMessages::KINDS[$run['kind']]) ?></span>
                    <?php if ($switchable): ?>
                        <span class="live-msgs__state"><?= $on ? 'চালু' : 'বন্ধ — দর্শক এটা দেখেন না' ?></span>
                        <?php if ($liveSuper): ?>
                            <button type="button" class="live-msgs__switch" data-jk-msg-switch="<?= e($switch) ?>" data-on="<?= $on ? '0' : '1' ?>"
                                <?= $on ? 'data-warn="' . e(MessageSwitch::SWITCHABLE[$switch]) . '"' : '' ?>><?= $on ? 'বার্তা বন্ধ করুন' : 'বার্তা চালু করুন' ?></button>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if (MessageSwitch::designable($switch)): ?>
                        <?php if ($liveSuper): ?>
                            <div class="live-msgs__style" data-jk-msg-style="<?= e($switch) ?>">
                                <label>ডিজাইন
                                    <select name="design">
                                        <?php foreach (MessageSwitch::DESIGNS as $v => $label): ?>
                                            <option value="<?= e($v) ?>"<?= MessageSwitch::design($switch) === $v ? ' selected' : '' ?>><?= e($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label>রং
                                    <select name="color">
                                        <?php foreach (MessageSwitch::COLORS as $v => $label): ?>
                                            <option value="<?= e($v) ?>"<?= MessageSwitch::color($switch) === $v ? ' selected' : '' ?>><?= e($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label>কতক্ষণ থাকবে
                                    <select name="seconds">
                                        <?php foreach (MessageSwitch::SECONDS as $v): ?>
                                            <option value="<?= $v ?>"<?= MessageSwitch::seconds($switch) === $v ? ' selected' : '' ?>><?= $v === 0 ? 'নিজে সরবে না (শুধু ✕)' : bn($v) . ' সেকেন্ড' ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <button type="button" class="live-msgs__switch" data-jk-msg-style-save>প্রয়োগ করুন</button>
                            </div>
                        <?php endif; ?>
                        <?php
                        // নমুনা: দর্শক যে ডিজাইনে দেখবেন (ভাসে না, নিজে সরে না)
                        $msgName = $switch;
                        $msgLines = array_map(static fn (string $k): string => e(content($k)), $run['keys']);
                        $msgHome = false;
                        $msgPlace = 'static';
                        require BASE_PATH . '/app/Views/partials/message-box.php';
                        ?>
                    <?php else: ?>
                        <?= $liveBox($run['kind'], $run['keys']) ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?= $group['folded'] ? '</details>' : '</div>' ?>
        <?php endforeach; ?>
    </div>
</section>
