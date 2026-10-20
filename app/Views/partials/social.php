<?php
/**
 * Social media icons. Addresses come from the editable keys "site.social.*".
 *
 * @var string $socialClass  extra CSS class for the list
 */
use App\Core\Security;

$socials = [
    'facebook'  => 'ফেসবুক',
    'whatsapp'  => 'হোয়াটসঅ্যাপ',
    'instagram' => 'ইনস্টাগ্রাম',
    'telegram'  => 'টেলিগ্রাম',
    'twitter'   => 'টুইটার',
    'linkedin'  => 'লিংকডইন',
];
?>
<ul class="social <?= e($socialClass ?? '') ?>">
    <?php foreach ($socials as $key => $label):
        $href = Security::safeUrl(content('site.social.' . $key));
        ?>
        <li>
            <a href="<?= e($href ?? '#') ?>"<?= $href !== null ? ' target="_blank" rel="noopener noreferrer me"' : '' ?> aria-label="<?= e($label) ?>">
                <img src="<?= e(asset('img/icons/' . $key . '.svg')) ?>" alt="" width="25" height="25" loading="lazy">
            </a>
        </li>
    <?php endforeach; ?>
</ul>
