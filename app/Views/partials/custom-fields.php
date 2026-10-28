<?php
/**
 * ফর্মে সুপার অ্যাডমিনের যোগ করা নতুন ঘর (App\Models\CustomFields), সম্মতির টিকের ঠিক আগে, অ্যাডমিনের সাজানো ক্রমে।
 * মান যায় cf[<ঘর-id>] নামে। আবশ্যক ঘরের নামের পাশে *। লাইভ পেইজ এডিটের ফ্রেমে প্রতিটি ঘর চিহ্নসহ (data-jk-custom),
 * আর শেষে "নতুন ঘর যোগ করুন"-এর জায়গা (data-jk-custom-add; বাটনটা live-edit.js বসায়, শুধু সুপার অ্যাডমিন)।
 *
 * @var string $customForm  worker | agent | post
 * @var array<string,mixed> $old  আগের জমার মান (ভুল হলে ফিরে আসে), cf => [id => মান]
 */
$customFields = \App\Models\CustomFields::forForm($customForm);
$customOld = is_array($old['cf'] ?? null) ? $old['cf'] : [];
$live = live_preview();
if ($live) {
    \App\Core\LiveEdit::customForm($customForm, $customFields);
}
?>
<?php if ($customFields !== [] || $live): ?>
    <div class="cf-fields">
        <?php foreach ($customFields as $f): ?>
            <?php
            $cid = 'cf-' . $f['id'];
            $cname = 'cf[' . $f['id'] . ']';
            $cval = (string) ($customOld[$f['id']] ?? '');
            $clabel = $f['label'] . ($f['is_required'] ? ' *' : '');
            $creq = $f['is_required'] ? ' required' : '';
            ?>
            <div class="pf cf-field<?= $f['field_type'] === 'checkbox' ? ' cf-field--check' : '' ?><?= $f['field_type'] === 'textarea' ? ' pf--wide' : '' ?>"<?= $live ? ' data-jk-custom="' . e((string) $f['id']) . '"' : '' ?>>
                <?php if ($f['field_type'] === 'checkbox'): ?>
                    <label class="cf-check" for="<?= e($cid) ?>">
                        <input id="<?= e($cid) ?>" type="checkbox" name="<?= e($cname) ?>" value="1"<?= $cval !== '' ? ' checked' : '' ?><?= $creq ?>>
                        <span><?= e($clabel) ?></span>
                    </label>
                <?php elseif ($f['field_type'] === 'select'): ?>
                    <label class="sr-only" for="<?= e($cid) ?>"><?= e($clabel) ?></label>
                    <select id="<?= e($cid) ?>" name="<?= e($cname) ?>"<?= $creq ?>>
                        <option value=""><?= e($clabel) ?></option>
                        <?php foreach ($f['options'] as $opt): ?>
                            <option value="<?= e($opt) ?>"<?= $cval === $opt ? ' selected' : '' ?>><?= e($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ($f['field_type'] === 'textarea'): ?>
                    <label class="sr-only" for="<?= e($cid) ?>"><?= e($clabel) ?></label>
                    <textarea id="<?= e($cid) ?>" data-bn name="<?= e($cname) ?>" maxlength="2000" rows="4" placeholder="<?= e($clabel) ?>"<?= $creq ?>><?= e($cval) ?></textarea>
                <?php elseif ($f['field_type'] === 'date'): ?>
                    <label class="cf-label" for="<?= e($cid) ?>"><?= e($clabel) ?></label>
                    <input id="<?= e($cid) ?>" type="date" name="<?= e($cname) ?>" value="<?= e($cval) ?>"<?= $creq ?>>
                <?php elseif ($f['field_type'] === 'number'): ?>
                    <label class="sr-only" for="<?= e($cid) ?>"><?= e($clabel) ?></label>
                    <input id="<?= e($cid) ?>" type="text" inputmode="decimal" name="<?= e($cname) ?>" maxlength="20" placeholder="<?= e($clabel) ?>" value="<?= e($cval) ?>"<?= $creq ?>>
                <?php else: ?>
                    <label class="sr-only" for="<?= e($cid) ?>"><?= e($clabel) ?></label>
                    <input id="<?= e($cid) ?>" data-bn type="text" name="<?= e($cname) ?>" maxlength="300" placeholder="<?= e($clabel) ?>" value="<?= e($cval) ?>"<?= $creq ?>>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php if ($live): ?>
            <div class="cf-add" data-jk-custom-add="<?= e($customForm) ?>"></div>
        <?php endif; ?>
    </div>
<?php endif; ?>
