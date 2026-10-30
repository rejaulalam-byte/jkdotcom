<?php
/**
 * অ্যাকাউন্ট তৈরি: তালিকা ও নতুন অ্যাকাউন্ট। সুপার অ্যাডমিন সব ভূমিকা দেখেন ও বানান; মেইন অ্যাডমিন ও অ্যাডমিনিস্ট্রেটর শুধু সাব-অ্যাডমিন
 * (তালিকাও শুধু তাঁদের ও নিজের)। অনুমতি শুধু সাব-অ্যাডমিনের বদলানো যায়; সুপার ও মেইনের সব অনুমতি আছে।
 *
 * @var array<string,mixed> $admin
 * @var list<array<string,mixed>> $admins
 * @var bool $hasMain চালু মেইন অ্যাডমিন আছেন কি না
 */
use App\Core\AdminAuth;
use App\Models\AdminPermissions;
?>
<section class="adm-box">
    <h2>অ্যাকাউন্টের তালিকা</h2>
    <div class="adm-tablewrap">
        <table class="adm-table">
            <thead><tr><th>নাম</th><th>ইমেইল</th><th>ভূমিকা</th><th>অবস্থা</th><th>শেষ লগইন</th><th>কাজ</th></tr></thead>
            <tbody>
            <?php foreach ($admins as $a): ?>
                <?php $self = (int) $a['id'] === (int) $admin['id']; ?>
                <tr>
                    <td><?= e((string) $a['name']) ?><?= $self ? ' (আপনি)' : '' ?></td>
                    <td><?= e((string) $a['email']) ?></td>
                    <td><?= e(AdminAuth::roleLabel((string) $a['role'])) ?></td>
                    <td><?= $a['status'] === 'active' ? 'চালু' : 'বন্ধ' ?><?= (string) $a['password_hash'] === '' ? ' · পাসওয়ার্ড বসাননি' : '' ?></td>
                    <td><?= $a['last_login_at'] ? e((string) $a['last_login_at']) : '—' ?></td>
                    <td class="adm-actions">
                        <?php if (!$self): ?>
                            <form action="<?= admin_base() ?>/admins/status" method="post" data-confirm="<?= $a['status'] === 'active' ? 'এই অ্যাকাউন্ট বন্ধ করবেন? তিনি আর ঢুকতে পারবেন না।' : 'অ্যাকাউন্ট আবার চালু করবেন?' ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                <input type="hidden" name="action" value="<?= $a['status'] === 'active' ? 'disable' : 'enable' ?>">
                                <button class="adm-small" type="submit"><?= $a['status'] === 'active' ? 'বন্ধ করুন' : 'চালু করুন' ?></button>
                            </form>
                        <?php endif; ?>
                        <?php if ($a['status'] === 'active'): ?>
                            <form action="<?= admin_base() ?>/admins/invite" method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                <button class="adm-small" type="submit">পাসওয়ার্ডের লিংক পাঠান</button>
                            </form>
                        <?php endif; ?>
                        <?php if (!$self && AdminAuth::isSuper($admin)): // ভূমিকা বদল: সাব-অ্যাডমিন বাছলে নিচের অনুমতিগুলো কাজে লাগে ?>
                            <details>
                                <summary class="adm-small">ভূমিকা বদলান</summary>
                                <form action="<?= admin_base() ?>/admins/role" method="post" data-confirm="এই অ্যাকাউন্টের ভূমিকা বদলাবেন? পরের ক্লিক থেকেই নতুন ভূমিকা কাজ করবে।">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                    <label for="role-<?= (int) $a['id'] ?>">নতুন ভূমিকা</label>
                                    <select id="role-<?= (int) $a['id'] ?>" name="role">
                                        <?php foreach (['admin', 'administrator', 'main', 'super'] as $roleOption): ?>
                                            <?php if ($roleOption !== 'main' || !$hasMain || $a['role'] === 'main'): ?>
                                                <option value="<?= $roleOption ?>"<?= $a['role'] === $roleOption ? ' selected' : '' ?>><?= e(AdminAuth::roleLabel($roleOption)) ?></option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="adm-hint">সাব-অ্যাডমিন বাছলে এই অনুমতিগুলো পাবেন:</p>
                                    <?php foreach (AdminPermissions::ALL as $key => $label): ?>
                                        <label class="adm-check"><input type="checkbox" name="permissions[]" value="<?= e($key) ?>"<?= $a['permission_list'] === [] || in_array($key, $a['permission_list'], true) ? ' checked' : '' ?>> <?= e($label) ?></label>
                                    <?php endforeach; ?>
                                    <button class="adm-small" type="submit">ভূমিকা বদলান</button>
                                </form>
                            </details>
                        <?php endif; ?>
                        <?php if ($a['role'] === 'admin'): ?>
                            <details>
                                <summary class="adm-small">অনুমতি</summary>
                                <form action="<?= admin_base() ?>/admins/permissions" method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                    <?php foreach (AdminPermissions::ALL as $key => $label): ?>
                                        <label class="adm-check"><input type="checkbox" name="permissions[]" value="<?= e($key) ?>"<?= in_array($key, $a['permission_list'], true) ? ' checked' : '' ?>> <?= e($label) ?></label>
                                    <?php endforeach; ?>
                                    <button class="adm-small" type="submit">সংরক্ষণ</button>
                                </form>
                            </details>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="adm-box">
    <h2>নতুন অ্যাকাউন্ট তৈরি করুন</h2>
    <p>নতুন ব্যক্তির ইমেইলে পাসওয়ার্ড বসানোর লিংক যাবে (৩ দিন কাজ করবে)। পাসওয়ার্ড তিনি নিজে বসাবেন; আপনি জানবেন না।</p>
    <form action="<?= admin_base() ?>/admins/create" method="post" novalidate>
        <?= csrf_field() ?>
        <label for="na-name">নাম</label>
        <input id="na-name" type="text" name="name" required maxlength="100">
        <label for="na-email">ইমেইল</label>
        <input id="na-email" type="email" name="email" required autocapitalize="none" maxlength="150">
        <?php if (AdminAuth::isSuper($admin)): ?>
            <label for="na-role">ভূমিকা</label>
            <select id="na-role" name="role">
                <option value="admin">সাব-অ্যাডমিন (নিচের অনুমতিগুলো পাবেন)</option>
                <option value="administrator">অ্যাডমিনিস্ট্রেটর (মেইন অ্যাডমিনের সমান অনুমতি; যতজন দরকার)</option>
                <?php if (!$hasMain): ?>
                    <option value="main">মেইন অ্যাডমিন / ওনার (কাঠামো ছাড়া সব; একজনই)</option>
                <?php endif; ?>
                <option value="super">সুপার অ্যাডমিন (সব অনুমতি, কাঠামোও বদলাতে পারবেন)</option>
            </select>
        <?php else: ?>
            <input type="hidden" name="role" value="admin">
        <?php endif; ?>
        <fieldset>
            <legend>অনুমতি (সাব-অ্যাডমিনের জন্য; সব বা কয়েকটা)</legend>
            <?php foreach (AdminPermissions::ALL as $key => $label): ?>
                <label class="adm-check"><input type="checkbox" name="permissions[]" value="<?= e($key) ?>" checked> <?= e($label) ?></label>
            <?php endforeach; ?>
        </fieldset>
        <button class="adm-btn" type="submit">তৈরি করুন ও লিংক পাঠান</button>
    </form>
</section>
