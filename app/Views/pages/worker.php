<?php
/**
 * কর্মীর ব্যক্তিগত পেইজ "পেশাজীবীর বিস্তারিত তথ্য" (Figma node 2845-920): ছবি ও তথ্য, ফোন/ইমেইল লুকানো
 * (ক্লিকে "আগ্রহী?" পপ-আপ), কাজের মূল্যায়ন (রেটিং), ডানে "সতর্কতা" বক্স, নিচে ফিরে যাওয়ার বাটন।
 *
 * @var string $heading                      ব্যানারের শিরোনাম
 * @var int $id
 * @var string $name
 * @var ?string $photo                       ছবির ঠিকানা (না থাকলে null)
 * @var string $profession
 * @var string $professions                  প্রধান পেশা ও আরও পেশা, কমা দিয়ে
 * @var string $listUrl                      এই পেশার তালিকা-পেইজ
 * @var ?int $experience                     বছর
 * @var string $bio
 * @var string $address                      পুরো ঠিকানা
 * @var string $phoneMasked
 * @var string $emailMasked                  ইমেইল না থাকলে ''
 * @var array{avg:?float,count:int} $rating
 * @var list<float> $choices                 রেটিং-বাছাই
 * @var ?array{0:string,1:string} $ratingMessage  ['ok'|'error', লেখা] রেটিং জমার ফল
 * @var string $token                        যোগাযোগ-অনুরোধের CSRF টোকেন
 */
$crumbs = [['/', 'হোম'], ['/services', 'সেবাসমূহ'], [$listUrl, $profession !== '' ? $profession : 'পেশাজীবী'], [null, $name]];
require BASE_PATH . '/app/Views/partials/page-banner.php';

// রেটিং: ৪.০ → "৪"; ৪.৫ → "৪.৫" (Figma-র বাছাইয়ের মতো)
$fmt = static fn (float $n): string => bn(rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.'));
?>
<section class="section section--worker" data-lead-root data-token="<?= e($token) ?>" data-contact-url="/workers/contact"
         data-limit="<?= e(content('today.list.too_many')) ?>">
    <div class="container">
        <h2 class="wk-heading"><?= e(content('worker.title')) ?></h2>

        <div class="wk-layout">
            <div class="wk-card">
                <div class="wk-photo">
                    <img src="<?= e($photo ?? asset('img/icons/user-placeholder.svg')) ?>" alt="<?= e($name) ?>" width="142" height="160" decoding="async">
                </div>

                <dl class="wk-info">
                    <div class="wk-info__row wk-info__row--short"><dt><?= e(content('worker.l.name')) ?></dt><dd><?= e($name) ?>।</dd></div>
                    <div class="wk-info__row wk-info__row--short"><dt><?= e(content('worker.l.profession')) ?></dt><dd><?= $professions !== '' ? e($professions) . '।' : '—' ?></dd></div>
                    <div class="wk-info__row wk-info__row--short"><dt><?= e(content('worker.l.experience')) ?></dt><dd><?= $experience !== null ? e(bn(str_pad((string) $experience, 2, '0', STR_PAD_LEFT))) . ' ' . e(content('worker.experience_unit')) . '।' : '—' ?></dd></div>
                    <div class="wk-info__row"><dt><?= e(content('worker.l.bio')) ?></dt><dd><?= $bio !== '' ? nl2br(e($bio)) : '—' ?></dd></div>
                    <div class="wk-info__row"><dt><?= e(content('worker.l.address')) ?></dt><dd><?= e($address) ?></dd></div>
                    <div class="wk-info__row wk-info__row--contact"><dt><?= e(content('worker.l.phone')) ?></dt><dd><button class="job-reveal" type="button" data-id="<?= $id ?>" data-kind="phone"><?= e($phoneMasked) ?></button></dd></div>
                    <div class="wk-info__row wk-info__row--contact"><dt><?= e(content('worker.l.email')) ?></dt><dd><?php if ($emailMasked !== ''): ?><button class="job-reveal" type="button" data-id="<?= $id ?>" data-kind="email"><?= e($emailMasked) ?></button><?php else: ?>—<?php endif; ?></dd></div>
                </dl>

                <p class="wk-hint">
                    <?= e(content('worker.hint.before')) ?><strong><?= e(content('worker.hint.phone')) ?></strong><?= e(content('worker.hint.or')) ?><strong><?= e(content('worker.hint.email')) ?></strong><?= e(content('worker.hint.after')) ?>
                    <br><?= e(content('worker.hint.line2')) ?>
                </p>

                <div class="wk-note">
                    <strong><?= e(content('worker.note.title')) ?></strong>
                    <p><?= colored(content('worker.note.text')) ?></p>
                </div>

                <form class="wk-rate" id="rating" action="/workers/rate" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <?php if ($ratingMessage !== null): ?>
                        <p class="wk-rate__msg is-<?= e($ratingMessage[0]) ?>" role="status"><?= e($ratingMessage[1]) ?></p>
                    <?php endif; ?>
                    <div class="wk-rate__line">
                        <label class="wk-rate__label" for="wk-rating"><?= e(content('worker.rate.label')) ?></label>
                        <select id="wk-rating" name="rating">
                            <?php foreach ($choices as $choice): ?>
                                <option value="<?= e(number_format($choice, 1, '.', '')) ?>"><?= e($fmt($choice)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="wk-rate__submit" type="submit"><?= e(content('worker.rate.submit')) ?></button>
                        <p class="wk-rate__current">
                            <?= e(content('worker.rate.current')) ?>
                            <span class="wk-rate__badge"><?= $rating['avg'] !== null ? e($fmt($rating['avg'])) : '—' ?></span>
                        </p>
                    </div>
                    <p class="wk-rate__legend"><?= e(content('worker.rate.legend')) ?></p>
                </form>
            </div>

            <aside class="wk-warn" aria-labelledby="wk-warn-title">
                <p class="wk-warn__title" id="wk-warn-title"><?= e(content('worker.warn.title')) ?></p>
                <ul>
                    <?php foreach ([1, 2, 3] as $n): ?>
                        <li><?= e(content("worker.warn.{$n}")) ?></li>
                    <?php endforeach; ?>
                </ul>
            </aside>
        </div>

        <p class="wk-back"><a class="wk-back__btn" href="<?= e($listUrl) ?>" data-back><?= e(content('worker.back')) ?></a></p>
    </div>
</section>

<?php $leadPrefix = 'workers.lead'; require BASE_PATH . '/app/Views/partials/lead-dialog.php'; ?>
