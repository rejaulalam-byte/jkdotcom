<?php
/**
 * সদস্যের প্রোফাইল: তথ্য দেখা ও বদলানো (Figma-তে এই পেইজের নকশা নেই; রেজিস্ট্রেশন ফর্মের বিন্যাসই)।
 * নাম ও ইমেইল (এবং প্রতিনিধির NID) শুধু দেখানো হয়, বদলানো যায় না।
 *
 * @var string $heading
 * @var array<string,mixed> $user
 * @var array<string,mixed> $old   ফর্মের শুরুর মান (সংরক্ষিত তথ্য, বা ভুল জমার পর লেখা মান)
 * @var string $role      'worker' | 'agent'
 * @var bool   $passwordChanged  এইমাত্র পাসওয়ার্ড বদলানো হয়েছে
 * @var bool   $setupDone        এইমাত্র প্রথম লগইনের তথ্য পূর্ণ করা হয়েছে (পুরনো কর্মী)
 * @var bool   $unlocked  ৫ ভুলের লক থাকা সত্ত্বেও সঠিক পাসওয়ার্ডে ঢুকেছেন (বিশেষ বিবেচনার বার্তা)
 * @var bool   $done      এইমাত্র সংরক্ষণ হয়েছে
 * @var list<string> $errors
 * @var array{divisions:list<array{id:int,name:string}>,districts:list<array{id:int,parent:int,name:string}>,upazilas:list<array{id:int,parent:int,name:string}>} $geo
 * @var list<array{name:string,slug:string,icon:?string}> $services
 * @var ?string $photoUrl  এখনকার ছবির ঠিকানা
 */
$crumbs = [['/', 'হোম'], [null, content('profile.crumb')]];
require BASE_PATH . '/app/Views/partials/page-banner.php';

$val = static fn (string $name): string => e((string) ($old[$name] ?? ''));
$sel = static fn (string $name, int $id): string => (int) ($old[$name] ?? 0) === $id ? ' selected' : '';
$ph = static fn (string $field, bool $required = true): string => e(content('regform.f.' . $field)) . ($required ? ' *' : '');
$helpTel = content('today.post.helpline_tel');
// ঘরের ঠিক ওপরের দৃশ্যমান নাম (ফাঁকা অবস্থায়ও কোন ঘরে কী তা বোঝা যায়); $required হলে লাল *
$label = static fn (string $for, string $key, bool $required = false): string =>
    '<label class="pf__label" for="' . e($for) . '">' . e(content('profile.l.' . $key)) . ($required ? ' <span class="pf__star">*</span>' : '') . '</label>';
?>
<section class="section section--post-job section--regform" aria-labelledby="pf-title">
    <div class="container">
        <h2 id="pf-title" class="section__title post-job__title"><?= t('profile.title') ?></h2>
        <p class="post-job__intro"><?= e(honorific_name($user['gender'] ?? null, (string) $user['name'])) ?>, <?= t('profile.intro') ?></p>

        <?php if ($setupDone): ?>
            <p class="notice notice--ok profile__done" role="status"><?= t('setup.done') ?></p>
        <?php endif; ?>
        <?php if ($passwordChanged): ?>
            <p class="notice notice--ok profile__done" role="status"><?= t('password.changed') ?></p>
        <?php endif; ?>
        <?php if ($unlocked): ?>
            <p class="notice notice--ok profile__done" role="status"><?= t('login.unlocked') ?></p>
        <?php endif; ?>
        <?php if ($done): ?>
            <p class="notice notice--ok profile__done" role="status"><?= t('profile.done') ?></p>
        <?php endif; ?>

        <div class="svc-notice post-job__note">
            <p><?= t('profile.locked') ?></p>
            <p><?= t('today.post.help_1') ?><a class="post-job__tel" href="tel:<?= e($helpTel) ?>"><?= t('today.post.helpline') ?></a><?= t('today.post.help_2') ?></p>
        </div>

        <form class="post-form reg-form" id="form" action="/profile" method="post" enctype="multipart/form-data" novalidate <?= form_msg_attrs() ?>>
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

            <div class="reg-form__grid">
                <div class="reg-form__col">
                    <?php // বদলানো-যায়-না তথ্য: ঘরের মতো দেখায় কিন্তু শুধু পড়া যায় ?>
                    <?= $label('pf-name', 'name') ?>
                    <div class="pf pf--locked">
                        <input id="pf-name" type="text" value="<?= e((string) $user['name']) ?>" readonly tabindex="-1">
                    </div>
                    <?= $label('pf-email', 'email') ?>
                    <div class="pf pf--locked">
                        <input id="pf-email" type="text" value="<?= e((string) ($user['email'] ?? '')) ?>" placeholder="<?= e(content('profile.no_email')) ?>" readonly tabindex="-1">
                    </div>

                    <?php if ($role === 'worker'): ?>
                        <?= $label('rf-service', 'service', true) ?>
                        <div class="pf pf--combo" data-combo data-none="<?= e(content('regform.service.none')) ?>" data-use="<?= e(content('regform.service.use')) ?>" data-hint="<?= e(content('regform.service.hint')) ?>">
                            <input id="rf-service" data-bn type="text" name="service_name" list="rf-services" maxlength="100" required autocomplete="off"
                                   placeholder="<?= $ph('service') ?>" value="<?= $val('service_name') ?>">
                            <datalist id="rf-services">
                                <?php foreach ($services as $service): ?>
                                    <option value="<?= e($service['name']) ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <?= $label('rf-extra-service', 'extra_service') ?>
                        <?php $esSlugs = array_map('strval', (array) ($old['extra_services'] ?? [])); require BASE_PATH . '/app/Views/partials/extra-services.php'; ?>
                        <?= $label('rf-exp', 'experience') ?>
                        <div class="pf">
                            <input id="rf-exp" type="number" name="experience" min="0" max="70" inputmode="numeric"
                                   placeholder="<?= $ph('experience', false) ?>" value="<?= $val('experience_years') ?>">
                        </div>
                    <?php else: ?>
                        <?= $label('pf-nid', 'nid') ?>
                        <div class="pf pf--locked">
                            <input id="pf-nid" type="text" value="<?= e((string) ($user['nid_number'] ?? '')) ?>" readonly tabindex="-1">
                        </div>
                    <?php endif; ?>

                    <?= $label('rf-phone', 'phone', true) ?>
                    <div class="pf pf--phone">
                        <span class="pf__code" aria-hidden="true">+৮৮</span>
                        <input id="rf-phone" type="tel" name="phone" maxlength="14" required autocomplete="tel-national" inputmode="numeric" pattern="01[3-9][0-9]{8}"
                               placeholder="<?= $ph('phone') ?>" value="<?= $val('phone') ?>">
                    </div>
                    <?= $label('pf-current', 'current') ?>
                    <div class="pf pf--eye">
                        <input id="pf-current" type="password" name="current_password" maxlength="72" autocomplete="new-password" data-no-autofill>
                        <?php // চোখ-বাটন লুকানো থাকে; app.js দেখায় (JavaScript ছাড়া পাসওয়ার্ড ঢাকাই থাকে) ?>
                        <button class="lg-eye" type="button" hidden aria-pressed="false" aria-controls="pf-current"
                                data-show="<?= e(content('login.f.show')) ?>" data-hide="<?= e(content('login.f.hide')) ?>"
                                aria-label="<?= e(content('login.f.show')) ?>"></button>
                    </div>
                    <?= $label('rf-address', 'address', true) ?>
                    <div class="pf">
                        <input id="rf-address" data-bn type="text" name="address" maxlength="250" required autocomplete="street-address"
                               placeholder="<?= $ph('address') ?>" value="<?= $val('address') ?>">
                    </div>
                    <?php // এলাকার তিন ড্রপডাউন: id-গুলো "কাজ সম্পর্কে জানান"-এর মতোই, app.js একই কোডে চালায় ?>
                    <?= $label('pj-upazila', 'upazila', true) ?>
                    <div class="pf pf--select">
                        <select id="pj-upazila" name="upazila_id" required data-search data-none="<?= e(content('geo.search.none')) ?>" data-all="<?= e(content('geo.all.upazila')) ?>">
                            <option value=""><?= $ph('upazila') ?></option>
                            <?php foreach ($geo['upazilas'] as $u): ?>
                                <option value="<?= $u['id'] ?>" data-parent="<?= $u['parent'] ?>" data-name="<?= e($u['short']) ?>"<?= $sel('upazila_id', $u['id']) ?>><?= e($u['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?= $label('pj-district', 'district', true) ?>
                    <div class="pf pf--select">
                        <select id="pj-district" name="district_id" required data-search data-none="<?= e(content('geo.search.none')) ?>" data-all="<?= e(content('geo.all.district')) ?>">
                            <option value=""><?= $ph('district') ?></option>
                            <?php foreach ($geo['districts'] as $d): ?>
                                <option value="<?= $d['id'] ?>" data-parent="<?= $d['parent'] ?>"<?= $sel('district_id', $d['id']) ?>><?= e($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?= $label('pj-division', 'division', true) ?>
                    <div class="pf pf--select">
                        <select id="pj-division" name="division_id" required data-search data-none="<?= e(content('geo.search.none')) ?>" data-all="<?= e(content('geo.all.division')) ?>">
                            <option value=""><?= $ph('division') ?></option>
                            <?php foreach ($geo['divisions'] as $v): ?>
                                <option value="<?= $v['id'] ?>"<?= $sel('division_id', $v['id']) ?>><?= e($v['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <?php // ছবি: নতুন ছবি বাছলে বদলায়, না বাছলে এখনকার ছবিই থাকে; app.js নাম ও প্রিভিউ দেখায় ও বড় ছবি ছোট করে ?>
                <div class="reg-form__col reg-photo" id="photo" data-photo
                     data-max="<?= \App\Core\PhotoUpload::MAX_BYTES ?>"
                     data-none="<?= e(content('profile.photo.none')) ?>" data-chosen="<?= e(content('regform.photo.chosen')) ?>"
                     data-too-big="<?= e(content('regform.photo.too_big')) ?>"
                     data-size-shrunk="<?= e(content('regform.photo.size_shrunk')) ?>" data-size-same="<?= e(content('regform.photo.size_same')) ?>" data-bad-type="<?= e(content('regform.photo.bad_type')) ?>">
                    <div class="reg-photo__pick">
                        <label class="reg-photo__label" for="rf-photo"><?= t('profile.photo.label') ?></label>
                        <span class="reg-photo__btn" aria-hidden="true"><?= t('regform.photo.browse') ?></span>
                        <input id="rf-photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp">
                    </div>
                    <p class="reg-photo__name" data-photo-name><?= t('profile.photo.none') ?></p>
                    <img class="reg-photo__preview" data-photo-preview src="<?= e($photoUrl ?? asset('img/icons/user-placeholder.svg')) ?>" alt="" width="249" height="273">
                    <p class="reg-photo__note"><?= t('regform.photo.note_1') ?><br><span data-photo-size><?= t('regform.photo.note_2') ?></span></p>
                    <p class="reg-photo__error" data-photo-error role="alert" hidden></p>
                </div>
            </div>

            <?php if ($role === 'worker'): ?>
                <?php // কাজ করতে আগ্রহী জেলা: সর্বোচ্চ ৫টি বা "সব জেলা" ?>
                <div class="pf--wide-block">
                    <?= $label('rf-work-district', 'work_district') ?>
                    <?php $wdIds = array_map('intval', (array) ($old['work_district_ids'] ?? [])); $wdAll = !empty($old['work_all_districts']); require BASE_PATH . '/app/Views/partials/work-districts.php'; ?>
                </div>
                <?php // দুইজন রেফারেন্স (দুটোই আবশ্যক) ?>
                <fieldset class="reg-refs">
                    <legend><?= t('regform.f.references') ?></legend>
                    <div class="reg-refs__box">
                        <div class="pf">
                            <label class="sr-only" for="rf-ref1"><?= t('regform.f.ref1') ?></label>
                            <textarea id="rf-ref1" data-bn name="reference_1" maxlength="300" required rows="3"
                                      placeholder="<?= $ph('ref1') ?>"><?= $val('reference_1') ?></textarea>
                        </div>
                        <div class="pf">
                            <label class="sr-only" for="rf-ref2"><?= t('regform.f.ref2') ?></label>
                            <textarea id="rf-ref2" data-bn name="reference_2" maxlength="300" required rows="3"
                                      placeholder="<?= $ph('ref2') ?>"><?= $val('reference_2') ?></textarea>
                        </div>
                    </div>
                </fieldset>
                <?= $label('rf-bio', 'bio') ?>
                <div class="pf pf--wide">
                    <textarea id="rf-bio" data-bn name="bio" maxlength="2000"
                              placeholder="<?= $ph('bio', false) ?>"><?= $val('bio') ?></textarea>
                </div>
            <?php endif; ?>

            <p class="post-form__submit">
                <button class="pf-submit" type="submit"><?= t('profile.submit') ?></button>
            </p>
            <p class="profile__pw"><a href="/account/password"><?= t('nav.password') ?></a></p>
        </form>
    </div>
</section>
