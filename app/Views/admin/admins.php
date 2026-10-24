<?php
/**
 * অ্যাডমিনদের তালিকা ও নতুন অ্যাডমিন (শুধু সুপার অ্যাডমিন)। অনুমতি শুধু অ্যাডমিনিস্ট্রেটরের বদলানো যায়;
 * সুপার অ্যাডমিনের সব অনুমতি আছে।
 *
 * @var array<string,mixed> $admin
 * @var list<array<string,mixed>> $admins
 */
use App\Core\AdminAuth;
use App\Models\AdminPermissions;
?>
<section class="adm-box">
    <h2>অ্যাডমিন তালিকা</h2>
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
                            <form action="/admin/admins/status" method="post" data-confirm="<?= $a['status'] === 'active' ? 'এই অ্যাকাউন্ট বন্ধ করবেন? তিনি আর ঢুকতে পারবেন না।' : 'অ্যাকাউন্ট আবার চালু করবেন?' ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                <input type="hidden" name="action" value="<?= $a['status'] === 'active' ? 'disable' : 'enable' ?>">
                                <button class="adm-small" type="submit"><?= $a['status'] === 'active' ? 'বন্ধ করুন' : 'চালু করুন' ?></button>
                            </form>
                        <?php endif; ?>
                        <?php if ($a['status'] === 'active'): ?>
                            <form action="/admin/admins/invite" method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                <button class="adm-small" type="submit">পাসওয়ার্ডের লিংক পাঠান</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($a['role'] === 'admin'): ?>
                            <details>
                                <summary class="adm-small">অনুমতি</summary>
                                <form action="/admin/admins/permissions" method="post">
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
    <h2>নতুন অ্যাডমিন যোগ করুন</h2>
    <p>নতুন ব্যক্তির ইমেইলে পাসওয়ার্ড বসানোর লিংক যাবে (৩ দিন কাজ করবে)। পাসওয়ার্ড তিনি নিজে বসাবেন; আপনি জানবেন না।</p>
    <form action="/admin/admins/create" method="post" novalidate>
        <?= csrf_field() ?>
        <label for="na-name">নাম</label>
        <input id="na-name" type="text" name="name" required maxlength="100">
        <label for="na-email">ইমেইল</label>
        <input id="na-email" type="email" name="email" required autocapitalize="none" maxlength="150">
        <label for="na-role">ভূমিকা</label>
        <select id="na-role" name="role">
            <option value="admin">অ্যাডমিনিস্ট্রেটর (নিচের অনুমতিগুলো পাবেন)</option>
            <option value="super">সুপার অ্যাডমিন (সব অনুমতি, কাঠামোও বদলাতে পারবেন)</option>
        </select>
        <fieldset>
            <legend>অনুমতি (অ্যাডমিনিস্ট্রেটরের জন্য)</legend>
            <?php foreach (AdminPermissions::ALL as $key => $label): ?>
                <label class="adm-check"><input type="checkbox" name="permissions[]" value="<?= e($key) ?>" checked> <?= e($label) ?></label>
            <?php endforeach; ?>
        </fieldset>
        <button class="adm-btn" type="submit">তৈরি করুন ও লিংক পাঠান</button>
    </form>
</section>
