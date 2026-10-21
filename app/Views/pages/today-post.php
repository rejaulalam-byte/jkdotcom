<?php
/**
 * "কাজ সম্পর্কে জানান" (Figma node 2674-7): a visitor posts the work they need done.
 * Fields use the placeholder as their label (as in the Figma design); the three area
 * selects work together in app.js and are checked again on the server.
 *
 * @var string $heading
 * @var bool $done        true right after a successful submit (shows the thank-you card)
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

        <?php if ($done): ?>
            <div class="post-done" role="status">
                <img class="post-done__icon" src="<?= e(asset('img/icons/check-circle.svg')) ?>" alt="" width="96" height="96">
                <h3><?= e(content('today.post.done.title')) ?></h3>
                <p><?= e(content('today.post.done.text_1')) ?></p>
                <p><?= e(content('today.post.done.text_2')) ?></p>
                <p><?= e(content('today.post.done.text_3')) ?>
                    <a class="post-done__tel" href="tel:<?= e($helpTel) ?>"><?= e(content('today.post.helpline')) ?></a></p>
                <p class="post-done__actions">
                    <a class="btn btn--teal-soft" href="/today"><?= e(content('today.post.done.list')) ?></a>
                    <a class="btn btn--outline" href="/today/post"><?= e(content('today.post.done.again')) ?></a>
                </p>
            </div>
        <?php else: ?>
            <div class="svc-notice post-job__note">
                <p><?= e(content('today.post.help_1')) ?><a class="post-job__tel" href="tel:<?= e($helpTel) ?>"><?= e(content('today.post.helpline')) ?></a><?= e(content('today.post.help_2')) ?></p>
                <p><?= rich(content('today.post.required')) ?></p>
            </div>

            <form class="post-form" id="form" action="/today/post" method="post" novalidate>
                <?php if ($errors): ?>
                    <ul class="notice notice--error post-form__errors" role="alert">
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?= csrf_field() ?>
                <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">

                <div class="post-form__grid">
                    <div class="post-form__col">
                        <?php // without JavaScript this is a plain text box with suggestions (datalist); app.js makes it a searchable drop-down ?>
                        <div class="pf pf--combo" data-combo data-none="<?= e(content('today.post.combo.none')) ?>" data-use="<?= e(content('today.post.combo.use')) ?>" data-hint="<?= e(content('today.post.combo.hint')) ?>">
                            <label class="sr-only" for="pj-title"><?= e(content('today.post.f.title')) ?></label>
                            <input id="pj-title" type="text" name="title" list="pj-types" maxlength="120" required autocomplete="off"
                                   placeholder="<?= $ph('title') ?>   <?= e(content('today.post.f.title_hint')) ?>" value="<?= $val('title') ?>">
                            <datalist id="pj-types">
                                <?php foreach ($services as $service): ?>
                                    <option value="<?= e($service['name']) ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="pf">
                            <label class="sr-only" for="pj-name"><?= e(content('today.post.f.name')) ?></label>
                            <input id="pj-name" type="text" name="contact_name" maxlength="100" required autocomplete="name"
                                   placeholder="<?= $ph('name') ?>" value="<?= $val('contact_name') ?>">
                        </div>
                        <div class="pf">
                            <label class="sr-only" for="pj-phone"><?= e(content('today.post.f.phone')) ?></label>
                            <input id="pj-phone" type="tel" name="phone" maxlength="20" required autocomplete="tel" inputmode="tel"
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
                            <input id="pj-address" type="text" name="address" maxlength="250" required autocomplete="street-address"
                                   placeholder="<?= $ph('address') ?>" value="<?= $val('address') ?>">
                        </div>
                        <?php // Figma order: upazila, district, division. app.js lets any of the three be picked first. ?>
                        <div class="pf pf--select">
                            <label class="sr-only" for="pj-upazila"><?= e(content('today.post.f.upazila')) ?></label>
                            <select id="pj-upazila" name="upazila_id" required>
                                <option value=""><?= $ph('upazila') ?></option>
                                <?php foreach ($geo['upazilas'] as $u): ?>
                                    <option value="<?= $u['id'] ?>" data-parent="<?= $u['parent'] ?>" data-name="<?= e($u['short']) ?>"<?= $sel('upazila_id', $u['id']) ?>><?= e($u['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="pf pf--select">
                            <label class="sr-only" for="pj-district"><?= e(content('today.post.f.district')) ?></label>
                            <select id="pj-district" name="district_id" required>
                                <option value=""><?= $ph('district') ?></option>
                                <?php foreach ($geo['districts'] as $d): ?>
                                    <option value="<?= $d['id'] ?>" data-parent="<?= $d['parent'] ?>"<?= $sel('district_id', $d['id']) ?>><?= e($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="pf pf--select">
                            <label class="sr-only" for="pj-division"><?= e(content('today.post.f.division')) ?></label>
                            <select id="pj-division" name="division_id" required>
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
                    <textarea id="pj-details" name="details" maxlength="3000" required
                              placeholder="<?= $ph('details') ?>"><?= $val('details') ?></textarea>
                </div>

                <p class="post-form__submit">
                    <button class="pf-submit" type="submit"><?= e(content('today.post.submit')) ?></button>
                </p>
            </form>
        <?php endif; ?>
    </div>
</section>
