<?php
/**
 * কর্মী বা প্রতিনিধির তথ্য সম্পাদনা (অ্যাডমিন), অথবা নতুন কর্মী যোগ (id = 0, সব ঘর খালি; তখন মোবাইল আবশ্যক ও "লগইন" অংশ দেখায়)।
 * সম্পাদনায় খালি মোবাইল/ইমেইল রাখা যায় (পুরনো কর্মীদের জন্য)। বিভাগ → জেলা → উপজেলা একটা অন্যটাকে ছাঁকে (admin.js [data-geo])।
 *
 * @var array<string,mixed> $member    এখনকার সারি
 * @var array<string,mixed> $v         ফর্মের মান (ভুলের পর লেখা মান, নইলে সংরক্ষিত)
 * @var array{divisions:list<array<string,mixed>>,districts:list<array<string,mixed>>,upazilas:list<array<string,mixed>>} $geo
 * @var list<array{name:string,slug:string}> $services
 * @var ?string $photoUrl
 * @var int $extraSlots
 * @var int $districtSlots
 */
$isWorker = $member['role'] === 'worker';
$val = static fn (string $k): string => e((string) ($v[$k] ?? ''));
$selected = static fn (mixed $current, mixed $value): string => (string) $current === (string) $value ? ' selected' : '';
$extra = array_values((array) ($v['extra_services'] ?? []));
$workIds = array_values((array) ($v['work_district_ids'] ?? []));
$list = $isWorker ? admin_base() . '/workers' : admin_base() . '/agents';
$gender = (string) ($v['gender'] ?? '');
$isNew = (int) $member['id'] === 0;
$cancelUrl = $isNew ? $list : admin_base() . '/members/' . (int) $member['id'];
?>
<p><a class="adm-link" href="<?= e($cancelUrl) ?>"><?= $isNew ? '← ' . ($isWorker ? 'কর্মীর' : 'প্রতিনিধির') . ' তালিকায় ফিরুন (যোগ না করে)' : '← বিস্তারিত পেইজে ফিরুন (কিছু না বদলে)' ?></a></p>

<form class="adm-editform" action="<?= admin_base() ?>/<?= $isNew ? ($isWorker ? 'member-add' : 'member-add/agent') : 'member-edit' ?>" method="post" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $member['id'] ?>">

    <section class="adm-box">
        <h2>পরিচয় ও যোগাযোগ</h2>
        <div class="adm-formgrid">
            <label for="ed-name">নাম</label>
            <input id="ed-name" type="text" name="name" value="<?= $val('name') ?>" required maxlength="100">

            <label for="ed-gender">জেন্ডার</label>
            <select id="ed-gender" name="gender">
                <option value="">— জানা নেই —</option>
                <option value="male"<?= $selected($gender, 'male') ?>>পুরুষ</option>
                <option value="female"<?= $selected($gender, 'female') ?>>মহিলা</option>
                <option value="other"<?= $selected($gender, 'other') ?>>অন্যান্য</option>
            </select>

            <label for="ed-phone">মোবাইল (১১ সংখ্যা)<?= $isNew ? ' <span class="adm-red">*</span>' : '' ?></label>
            <div>
                <input id="ed-phone" type="text" name="phone" inputmode="numeric" maxlength="14" value="<?= $val('phone') ?>" placeholder="01XXXXXXXXX<?= $isNew ? '' : ' (না থাকলে ফাঁকা)' ?>">
                <?php if ((string) ($member['phone_raw'] ?? '') !== ''): ?>
                    <p class="adm-hint">পুরনো ডাটায় লেখা ছিল: <strong><?= e((string) $member['phone_raw']) ?></strong> (মোবাইল বসালে এই লেখা মুছে যাবে)</p>
                <?php endif; ?>
            </div>

            <label for="ed-email">ইমেইল</label>
            <input id="ed-email" type="email" name="email" value="<?= $val('email') ?>" maxlength="150" autocapitalize="none" placeholder="না থাকলে ফাঁকা">

            <?php if ($isNew): ?>
                <label for="ed-pass">প্রাথমিক পাসওয়ার্ড</label>
                <div>
                    <input id="ed-pass" type="text" name="password" maxlength="72" autocomplete="new-password" spellcheck="false" placeholder="কমপক্ষে ৮ অক্ষর">
                    <p class="adm-hint">ইমেইল দিলে এটা ফাঁকা রাখুন: কর্মী লগইন পেইজের "পাসওয়ার্ড মনে নেই" দিয়ে নিজে পাসওয়ার্ড বসাবেন। ইমেইল না থাকলে একটা পাসওয়ার্ড লিখে ফোনে জানান; প্রথম লগইনে তাঁকে বদলাতে হবে। সংরক্ষণের পরে এটা আর কোথাও দেখা যায় না।</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="adm-box">
        <h2>ঠিকানা</h2>
        <div class="adm-formgrid">
            <label for="ed-address">ঠিকানা (গ্রাম/এলাকা, রাস্তা)</label>
            <input id="ed-address" type="text" name="address" value="<?= $val('address') ?>" maxlength="250">

            <label for="ed-division">বিভাগ</label>
            <select id="ed-division" name="division_id" data-geo="division">
                <option value="">— বাছুন —</option>
                <?php foreach ($geo['divisions'] as $d): ?>
                    <option value="<?= (int) $d['id'] ?>"<?= $selected($v['division_id'] ?? '', $d['id']) ?>><?= e((string) $d['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="ed-district">জেলা</label>
            <select id="ed-district" name="district_id" data-geo="district">
                <option value="">— বাছুন —</option>
                <?php foreach ($geo['districts'] as $d): ?>
                    <option value="<?= (int) $d['id'] ?>" data-parent="<?= (int) $d['parent'] ?>"<?= $selected($v['district_id'] ?? '', $d['id']) ?>><?= e((string) $d['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="ed-upazila">উপজেলা / থানা</label>
            <select id="ed-upazila" name="upazila_id" data-geo="upazila">
                <option value="">— বাছুন —</option>
                <?php foreach ($geo['upazilas'] as $u): ?>
                    <option value="<?= (int) $u['id'] ?>" data-parent="<?= (int) $u['parent'] ?>"<?= $selected($v['upazila_id'] ?? '', $u['id']) ?>><?= e((string) $u['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </section>

    <?php if ($isWorker): ?>
        <section class="adm-box">
            <h2>পেশা ও কাজ</h2>
            <div class="adm-formgrid">
                <label for="ed-service">প্রধান পেশা (তালিকা থেকে)</label>
                <select id="ed-service" name="service_slug">
                    <option value="">— তালিকায় নেই —</option>
                    <?php foreach ($services as $s): ?>
                        <option value="<?= e($s['slug']) ?>"<?= $selected($v['service_slug'] ?? '', $s['slug']) ?>><?= e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="ed-custom">অথবা নিজের লেখা পেশা</label>
                <div>
                    <input id="ed-custom" type="text" name="service_custom" value="<?= $val('service_custom') ?>" maxlength="100">
                    <p class="adm-hint">তালিকা থেকে পেশা বাছলে এই ঘর কাজ করে না। কর্মীর লেখা পেশা ঠিক থাকলে সেবা-তালিকায় যোগ করে তালিকা থেকে বেছে দিন।</p>
                </div>

                <?php for ($i = 0; $i < $extraSlots; $i++): ?>
                    <label for="ed-extra<?= $i ?>">আরও পেশা <?= e(bn($i + 1)) ?></label>
                    <select id="ed-extra<?= $i ?>" name="extra_services[]">
                        <option value="">— নেই —</option>
                        <?php foreach ($services as $s): ?>
                            <option value="<?= e($s['slug']) ?>"<?= $selected($extra[$i] ?? '', $s['slug']) ?>><?= e($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endfor; ?>

                <label for="ed-exp">অভিজ্ঞতা (বছর)</label>
                <input id="ed-exp" type="text" name="experience" inputmode="numeric" maxlength="3" value="<?= e((string) ($v['experience_years'] ?? '')) ?>" placeholder="না জানলে ফাঁকা">

                <label for="ed-bio">কাজের বিবরণ</label>
                <textarea id="ed-bio" name="bio" rows="4" maxlength="2000"><?= $val('bio') ?></textarea>

                <label for="ed-ref1">রেফারেন্স ১</label>
                <textarea id="ed-ref1" name="reference_1" rows="2" maxlength="300"><?= $val('reference_1') ?></textarea>

                <label for="ed-ref2">রেফারেন্স ২</label>
                <textarea id="ed-ref2" name="reference_2" rows="2" maxlength="300"><?= $val('reference_2') ?></textarea>
            </div>
        </section>

        <section class="adm-box">
            <h2>কাজ করতে আগ্রহী জেলা</h2>
            <label class="adm-check"><input type="checkbox" name="work_all" value="1"<?= !empty($v['work_all_districts']) ? ' checked' : '' ?>> সব জেলা (এটা টিক দিলে নিচের তালিকা কাজ করে না)</label>
            <div class="adm-formgrid">
                <?php for ($i = 0; $i < $districtSlots; $i++): ?>
                    <label for="ed-wd<?= $i ?>">জেলা <?= e(bn($i + 1)) ?></label>
                    <select id="ed-wd<?= $i ?>" name="work_districts[]">
                        <option value="">— নেই —</option>
                        <?php foreach ($geo['districts'] as $d): ?>
                            <option value="<?= (int) $d['id'] ?>"<?= $selected($workIds[$i] ?? '', $d['id']) ?>><?= e((string) $d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endfor; ?>
            </div>
        </section>
    <?php else: ?>
        <section class="adm-box">
            <h2>প্রতিনিধির তথ্য</h2>
            <div class="adm-formgrid">
                <label for="ed-nid">জাতীয় পরিচয়পত্র নম্বর</label>
                <input id="ed-nid" type="text" name="nid" inputmode="numeric" maxlength="30" value="<?= e((string) ($v['nid_number'] ?? '')) ?>" placeholder="১০, ১৩ বা ১৭ সংখ্যা (না জানলে ফাঁকা)">
            </div>
        </section>
    <?php endif; ?>

    <section class="adm-box">
        <h2>ছবি</h2>
        <div class="adm-photoedit">
            <?php if ($photoUrl !== null): ?>
                <img src="<?= e($photoUrl) ?>" alt="এখনকার ছবি" width="120" height="132">
            <?php else: ?>
                <div class="adm-detail__nophoto adm-detail__nophoto--small">ছবি নেই</div>
            <?php endif; ?>
            <div>
                <label for="ed-photo">নতুন ছবি (JPG, PNG বা WebP; বড় হলে সাইট নিজে ছোট করবে)</label>
                <input id="ed-photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp">
                <?php if ($photoUrl !== null): ?>
                    <label class="adm-check"><input type="checkbox" name="photo_remove" value="1"> ছবি মুছে ফেলুন</label>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <p>
        <button class="adm-btn" type="submit"><?= $isNew ? ($isWorker ? 'কর্মী যোগ করুন' : 'প্রতিনিধি যোগ করুন') : 'সংরক্ষণ করুন' ?></button>
        <a class="adm-link" href="<?= e($cancelUrl) ?>"><?= $isNew ? 'বাতিল' : 'বাতিল (কিছু বদলাবেন না)' ?></a>
    </p>
</form>
