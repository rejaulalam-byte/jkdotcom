<?php
/**
 * "কাজ সম্পর্কে জানান" (Figma node 2674-7): দর্শক যে কাজ করাতে চান তা জানান।
 * ঘরের ইঙ্গিত-লেখাই তার লেবেল (Figma নকশার মতো); এলাকার তিনটি ড্রপডাউন app.js-এ একসঙ্গে
 * কাজ করে এবং সার্ভারে আবার যাচাই হয়।
 *
 * @var string $heading
 * @var array{name:string,area:string}|null $done  সফলভাবে জমা দেওয়ার ঠিক পরে জানানোকারীর নাম ও এলাকা (২০ সেকেন্ডের জন্য বার্তা-বক্স দেখায়)
 * @var list<string> $errors
 * @var array<string,mixed> $old
 * @var array{divisions:list<array{id:int,name:string}>,districts:list<array{id:int,parent:int,name:string}>,upazilas:list<array{id:int,parent:int,name:string}>} $geo
 * @var list<array{name:string,slug:string,icon:?string}> $services
 */
$crumbs = [['/', 'হোম'], ['/today', 'আজকের প্রকাশিত'], [null, 'কাজ সম্পর্কে জানান']];
require BASE_PATH . '/app/Views/partials/page-banner.php';

$val = static fn (string $key): string => e((string) ($old[$key] ?? ''));
$sel = static fn (string $key, int $id): string => (int) ($old[$key] ?? 0) === $id ? ' selected' : '';
$ph = static fn (string $key, bool $required = true): string => e(content('today.post.f.' . $key)) . ($required ? ' *' : '');
$helpTel = content('today.post.helpline_tel');
?>
<section class="section section--post-job" aria-labelledby="post-title">
    <div class="container">
        <h2 id="post-title" class="section__title post-job__title"><?= e(content('today.post.heading')) ?></h2>
        <p class="post-job__intro"><?= e(content('today.post.intro')) ?></p>

            <div class="svc-notice post-job__note">
                <p><?= e(content('today.post.help_1')) ?><a class="post-job__tel" href="tel:<?= e($helpTel) ?>"><?= e(content('today.post.helpline')) ?></a><?= e(content('today.post.help_2')) ?></p>
                <p><?= rich(content('today.post.required')) ?></p>
            </div>

            <form class="post-form" id="form" action="/today/post" method="post" novalidate <?= form_msg_attrs() ?>>
                <?php if ($errors): ?>
                    <ul class="notice notice--error post-form__errors" role="alert">
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?= csrf_field() ?>
                <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">

                <?php require BASE_PATH . '/app/Views/partials/bn-switch.php'; ?>

                <div class="post-form__grid">
                    <div class="post-form__col">
                        <?php // JavaScript ছাড়া এটা পরামর্শসহ সাধারণ লেখার ঘর (datalist); app.js একে খোঁজা-যায় এমন ড্রপডাউন বানায় ?>
                        <div class="pf pf--combo" data-combo data-none="<?= e(content('today.post.combo.none')) ?>" data-use="<?= e(content('today.post.combo.use')) ?>" data-hint="<?= e(content('today.post.combo.hint')) ?>">
                            <label class="sr-only" for="pj-title"><?= e(content('today.post.f.title')) ?></label>
                            <input id="pj-title" data-bn type="text" name="title" list="pj-types" maxlength="120" required autocomplete="off"
                                   placeholder="<?= $ph('title') ?>" value="<?= $val('title') ?>">
                            <?php // ইঙ্গিতটা খালি ঘরের ডান প্রান্তে, ফ্যাকাশে ইঙ্গিত-লেখার চেয়ে গাঢ় (বাটনের মতো কাজ করে) ?>
                            <span class="pf__aside" aria-hidden="true"><?= e(content('today.post.f.title_hint')) ?></span>
                            <datalist id="pj-types">
                                <?php foreach ($services as $service): ?>
                                    <option value="<?= e($service['name']) ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="pf">
                            <label class="sr-only" for="pj-name"><?= e(content('today.post.f.name')) ?></label>
                            <input id="pj-name" data-bn type="text" name="contact_name" maxlength="100" required autocomplete="name"
                                   placeholder="<?= $ph('name') ?>" value="<?= $val('contact_name') ?>">
                        </div>
                        <?php
                        $genderId = 'pj-gender'; $genderName = 'contact_gender'; $genderLabel = content('today.post.f.gender'); $genderValue = (string) ($old['contact_gender'] ?? '');
                        require BASE_PATH . '/app/Views/partials/gender-field.php';
                        ?>
                        <div class="pf pf--phone">
                            <label class="sr-only" for="pj-phone"><?= e(content('today.post.f.phone')) ?></label>
                            <span class="pf__code" aria-hidden="true">+৮৮</span>
                            <input id="pj-phone" type="tel" name="phone" maxlength="14" required autocomplete="tel-national" inputmode="numeric" pattern="01[3-9][0-9]{8}"
                                   placeholder="<?= $ph('phone') ?>" value="<?= $val('phone') ?>">
                        </div>
                        <div class="pf">
                            <label class="sr-only" for="pj-email"><?= e(content('today.post.f.email')) ?></label>
                            <input id="pj-email" type="email" name="email" maxlength="150" autocomplete="email"
                                   placeholder="<?= $ph('email', false) ?>" value="<?= $val('email') ?>">
                        </div>
                        <div class="pf pf--date">
                            <label for="pj-start"><?= $ph('start') ?></label>
                            <input id="pj-start" type="date" name="start_date" required min="<?= e(date('Y-m-d')) ?>" value="<?= $val('start_date') ?>">
                        </div>
                    </div>

                    <div class="post-form__col">
                        <div class="pf">
                            <label class="sr-only" for="pj-address"><?= e(content('today.post.f.address')) ?></label>
                            <input id="pj-address" data-bn type="text" name="address" maxlength="250" required autocomplete="street-address"
                                   placeholder="<?= $ph('address') ?>" value="<?= $val('address') ?>">
                        </div>
                        <?php // Figma-র ক্রম: উপজেলা, জেলা, বিভাগ। app.js-এ তিনটির যেকোনোটা আগে বাছা যায়। ?>
                        <div class="pf pf--select">
                            <label class="sr-only" for="pj-upazila"><?= e(content('today.post.f.upazila')) ?></label>
                            <select id="pj-upazila" name="upazila_id" required data-search data-none="<?= e(content('geo.search.none')) ?>" data-all="<?= e(content('geo.all.upazila')) ?>">
                                <option value=""><?= $ph('upazila') ?></option>
                                <?php foreach ($geo['upazilas'] as $u): ?>
                                    <option value="<?= $u['id'] ?>" data-parent="<?= $u['parent'] ?>" data-name="<?= e($u['short']) ?>"<?= $sel('upazila_id', $u['id']) ?>><?= e($u['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="pf pf--select">
                            <label class="sr-only" for="pj-district"><?= e(content('today.post.f.district')) ?></label>
                            <select id="pj-district" name="district_id" required data-search data-none="<?= e(content('geo.search.none')) ?>" data-all="<?= e(content('geo.all.district')) ?>">
                                <option value=""><?= $ph('district') ?></option>
                                <?php foreach ($geo['districts'] as $d): ?>
                                    <option value="<?= $d['id'] ?>" data-parent="<?= $d['parent'] ?>"<?= $sel('district_id', $d['id']) ?>><?= e($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="pf pf--select">
                            <label class="sr-only" for="pj-division"><?= e(content('today.post.f.division')) ?></label>
                            <select id="pj-division" name="division_id" required data-search data-none="<?= e(content('geo.search.none')) ?>" data-all="<?= e(content('geo.all.division')) ?>">
                                <option value=""><?= $ph('division') ?></option>
                                <?php foreach ($geo['divisions'] as $v): ?>
                                    <option value="<?= $v['id'] ?>"<?= $sel('division_id', $v['id']) ?>><?= e($v['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="pf pf--date">
                            <label for="pj-end"><?= $ph('end', false) ?></label>
                            <input id="pj-end" type="date" name="end_date" min="<?= e(date('Y-m-d')) ?>" value="<?= $val('end_date') ?>">
                        </div>
                    </div>
                </div>

                <div class="pf pf--wide">
                    <label class="sr-only" for="pj-details"><?= e(content('today.post.f.details')) ?></label>
                    <textarea id="pj-details" data-bn name="details" maxlength="3000" required
                              placeholder="<?= $ph('details') ?>"><?= $val('details') ?></textarea>
                </div>

                <?php $consentKey = 'post'; $consentChecked = !empty($old['consent']); require BASE_PATH . '/app/Views/partials/consent.php'; ?>

                <p class="post-form__submit">
                    <button class="pf-submit" type="submit"><?= e(content('today.post.submit')) ?></button>
                </p>
            </form>
    </div>
</section>

<?php if ($done !== null):
    $say = static fn (string $key): string => e(strtr(content('today.post.done.' . $key), ['{name}' => (string) $done['name'], '{area}' => (string) $done['area']]));
?>
<?php // সফল জমার বার্তা-বক্স (Figma 2830-465): রেজিস্ট্রেশনের বক্সের মতোই কার্ড-ধরন; ২০ সেকেন্ড পরে সরে যায় ?>
<div class="post-toast post-toast--card" id="post-toast" role="status" aria-live="polite">
    <img class="post-toast__tick" src="<?= e(asset('img/icons/done-check.svg')) ?>" alt="" width="191" height="193">
    <button class="post-toast__close" type="button" aria-label="বন্ধ করুন"><span aria-hidden="true">&times;</span></button>
    <p><?= $say('text_0') ?></p>
    <p><?= $say('text_1') ?></p>
    <p><?= $say('text_2') ?></p>
    <p><?= $say('text_3') ?></p>
</div>
<?php endif; ?>
