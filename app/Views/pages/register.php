<?php
/**
 * রেজিস্ট্রেশন ফর্ম: পেশাজীবী কর্মী (Figma node 2658-504) ও প্রতিনিধি (Figma node 2682-2), একই বিন্যাস।
 * ঘরের ইঙ্গিত-লেখাই তার লেবেল (Figma নকশার মতো); ডান পাশে ছবির অংশ; এলাকার তিনটি ড্রপডাউন
 * "কাজ সম্পর্কে জানান"-এর মতোই app.js-এ একসঙ্গে কাজ করে এবং সার্ভারে আবার যাচাই হয়।
 *
 * @var string $heading
 * @var string $crumb
 * @var string $role      'worker' | 'agent'
 * @var string $action
 * @var bool $done        সফলভাবে জমা দেওয়ার ঠিক পরে true (১০ সেকেন্ডের জন্য বার্তা-বক্স দেখায়)
 * @var list<string> $errors
 * @var array<string,mixed> $old
 * @var array{divisions:list<array{id:int,name:string}>,districts:list<array{id:int,parent:int,name:string}>,upazilas:list<array{id:int,parent:int,name:string}>} $geo
 * @var list<array{name:string,slug:string,icon:?string}> $services  কর্মীর ফর্মে পেশার তালিকা
 */
$crumbs = [['/', 'হোম'], ['/registration', 'রেজিস্ট্রেশন'], [null, $crumb]];
require BASE_PATH . '/app/Views/partials/page-banner.php';

$key = 'regform.' . $role . '.';
$val = static fn (string $name): string => e((string) ($old[$name] ?? ''));
$sel = static fn (string $name, int $id): string => (int) ($old[$name] ?? 0) === $id ? ' selected' : '';
$ph = static fn (string $field, bool $required = true): string => e(content('regform.f.' . $field)) . ($required ? ' *' : '');
$helpTel = content('today.post.helpline_tel');
?>
<section class="section section--post-job section--regform" aria-labelledby="reg-title">
    <div class="container">
        <h2 id="reg-title" class="section__title post-job__title"><?= e(content($key . 'title')) ?></h2>
        <p class="post-job__intro"><?= e(content($key . 'intro')) ?></p>

        <div class="svc-notice post-job__note">
            <p><?= e(content('today.post.help_1')) ?><a class="post-job__tel" href="tel:<?= e($helpTel) ?>"><?= e(content('today.post.helpline')) ?></a><?= e(content('today.post.help_2')) ?></p>
            <p><?= rich(content('today.post.required')) ?></p>
        </div>

        <form class="post-form reg-form" id="form" action="<?= e($action) ?>" method="post" enctype="multipart/form-data" novalidate>
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
                    <div class="pf">
                        <label class="sr-only" for="rf-name"><?= e(content('regform.f.name')) ?></label>
                        <input id="rf-name" data-bn type="text" name="name" maxlength="100" required autocomplete="name"
                               placeholder="<?= $ph('name') ?>" value="<?= $val('name') ?>">
                    </div>

                    <?php if ($role === 'worker'): ?>
                        <div class="pf pf--select">
                            <label class="sr-only" for="rf-service"><?= e(content('regform.f.service')) ?></label>
                            <select id="rf-service" name="service" required>
                                <option value=""><?= $ph('service') ?></option>
                                <?php foreach ($services as $service): ?>
                                    <option value="<?= e($service['slug']) ?>"<?= (string) ($old['service_slug'] ?? '') === $service['slug'] ? ' selected' : '' ?>><?= e($service['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="pf">
                            <label class="sr-only" for="rf-exp"><?= e(content('regform.f.experience')) ?></label>
                            <input id="rf-exp" type="number" name="experience" min="0" max="70" inputmode="numeric"
                                   placeholder="<?= $ph('experience', false) ?>" value="<?= $val('experience_years') ?>">
                        </div>
                    <?php else: ?>
                        <div class="pf">
                            <label class="sr-only" for="rf-nid"><?= e(content('regform.f.nid')) ?></label>
                            <input id="rf-nid" type="text" name="nid" maxlength="30" required inputmode="numeric" autocomplete="off"
                                   placeholder="<?= $ph('nid') ?>" value="<?= $val('nid_number') ?>">
                        </div>
                    <?php endif; ?>

                    <div class="pf pf--phone">
                        <label class="sr-only" for="rf-phone"><?= e(content('regform.f.phone')) ?></label>
                        <span class="pf__code" aria-hidden="true">+৮৮</span>
                        <input id="rf-phone" type="tel" name="phone" maxlength="11" required autocomplete="tel-national" inputmode="numeric" pattern="01[3-9][0-9]{8}"
                               placeholder="<?= $ph('phone') ?>" value="<?= $val('phone') ?>">
                    </div>
                    <div class="pf">
                        <label class="sr-only" for="rf-address"><?= e(content('regform.f.address')) ?></label>
                        <input id="rf-address" data-bn type="text" name="address" maxlength="250" required autocomplete="street-address"
                               placeholder="<?= $ph('address') ?>" value="<?= $val('address') ?>">
                    </div>
                    <?php // এলাকার তিন ড্রপডাউন: id-গুলো "কাজ সম্পর্কে জানান"-এর মতোই, app.js একই কোডে চালায় ?>
                    <div class="pf pf--select">
                        <label class="sr-only" for="pj-upazila"><?= e(content('regform.f.upazila')) ?></label>
                        <select id="pj-upazila" name="upazila_id" required>
                            <option value=""><?= $ph('upazila') ?></option>
                            <?php foreach ($geo['upazilas'] as $u): ?>
                                <option value="<?= $u['id'] ?>" data-parent="<?= $u['parent'] ?>" data-name="<?= e($u['short']) ?>"<?= $sel('upazila_id', $u['id']) ?>><?= e($u['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="pf pf--select">
                        <label class="sr-only" for="pj-district"><?= e(content('regform.f.district')) ?></label>
                        <select id="pj-district" name="district_id" required>
                            <option value=""><?= $ph('district') ?></option>
                            <?php foreach ($geo['districts'] as $d): ?>
                                <option value="<?= $d['id'] ?>" data-parent="<?= $d['parent'] ?>"<?= $sel('district_id', $d['id']) ?>><?= e($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="pf pf--select">
                        <label class="sr-only" for="pj-division"><?= e(content('regform.f.division')) ?></label>
                        <select id="pj-division" name="division_id" required>
                            <option value=""><?= $ph('division') ?></option>
                            <?php foreach ($geo['divisions'] as $v): ?>
                                <option value="<?= $v['id'] ?>"<?= $sel('division_id', $v['id']) ?>><?= e($v['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="pf">
                        <label class="sr-only" for="rf-pass"><?= e(content('regform.f.password')) ?></label>
                        <input id="rf-pass" type="password" name="password" minlength="8" maxlength="72" required autocomplete="new-password"
                               placeholder="<?= $ph('password') ?>">
                    </div>
                    <p class="reg-form__note"><?= e(content('regform.password_note')) ?></p>
                    <div class="pf">
                        <label class="sr-only" for="rf-pass2"><?= e(content('regform.f.password2')) ?></label>
                        <input id="rf-pass2" type="password" name="password_confirm" minlength="8" maxlength="72" required autocomplete="new-password"
                               placeholder="<?= $ph('password2') ?>">
                    </div>
                </div>

                <?php // ছবি: ঘরটা ক্লিকে ফাইল-বাছাই খোলে; app.js নাম ও প্রিভিউ দেখায় এবং ১৫০ KB / ধরন যাচাই করে ?>
                <div class="reg-form__col reg-photo" data-photo
                     data-max="<?= \App\Core\PhotoUpload::MAX_BYTES ?>"
                     data-none="<?= e(content('regform.photo.none')) ?>" data-chosen="<?= e(content('regform.photo.chosen')) ?>"
                     data-too-big="<?= e(content('regform.photo.too_big')) ?>" data-bad-type="<?= e(content('regform.photo.bad_type')) ?>">
                    <div class="reg-photo__pick">
                        <label class="reg-photo__label" for="rf-photo"><?= e(content('regform.photo.label')) ?></label>
                        <span class="reg-photo__btn" aria-hidden="true"><?= e(content('regform.photo.browse')) ?></span>
                        <input id="rf-photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp">
                    </div>
                    <p class="reg-photo__name" data-photo-name><?= e(content('regform.photo.none')) ?></p>
                    <img class="reg-photo__preview" data-photo-preview src="<?= e(asset('img/icons/user-placeholder.svg')) ?>" alt="" width="249" height="273">
                    <p class="reg-photo__note"><?= e(content('regform.photo.note_1')) ?><br><?= e(content('regform.photo.note_2')) ?></p>
                    <p class="reg-photo__error" data-photo-error role="alert" hidden></p>
                </div>
            </div>

            <?php if ($role === 'worker'): ?>
                <div class="pf pf--wide">
                    <label class="sr-only" for="rf-bio"><?= e(content('regform.f.bio')) ?></label>
                    <textarea id="rf-bio" data-bn name="bio" maxlength="2000"
                              placeholder="<?= $ph('bio', false) ?>"><?= $val('bio') ?></textarea>
                </div>
            <?php endif; ?>

            <p class="post-form__submit">
                <button class="pf-submit" type="submit"><?= e(content('regform.submit')) ?></button>
            </p>
        </form>
    </div>
</section>

<?php if ($done): ?>
<div class="post-toast" id="post-toast" role="status" aria-live="polite">
    <button class="post-toast__close" type="button" aria-label="বন্ধ করুন"><span aria-hidden="true">&times;</span></button>
    <img class="post-toast__icon" src="<?= e(asset('img/icons/check-circle.svg')) ?>" alt="" width="64" height="64">
    <h3><?= e(content('regform.done.title')) ?></h3>
    <p><?= e(content('regform.done.text_1')) ?></p>
    <p><?= e(content('regform.done.text_2')) ?></p>
</div>
<?php endif; ?>
