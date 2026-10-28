<?php
/**
 * "পাসওয়ার্ড ভুলে গেলে…" সাহায্য-লাইন (লগইন ও পাসওয়ার্ড-পরিবর্তন পেইজে একই লেখা)।
 * "পাসওয়ার্ড মনে নেই" বাটন ইমেইলে-কোড পপ-আপ খোলে (partials/forgot-dialog.php, app.js)।
 */
$helpTel = content('today.post.helpline_tel');
?>
<p class="login__help"><?= t('login.help_1') ?><button class="login__forgot" type="button" data-forgot><?= t('login.help_link') ?></button><?= t('login.help_2') ?><a class="login__tel" href="tel:<?= e($helpTel) ?>"><?= t('today.post.helpline') ?></a></p>
