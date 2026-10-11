<?php
/**
 * @var \Cake\View\View $this
 * @var string $appName
 * @var string $link
 * @var int $minutes
 */
?>
Someone asked to reset the password of your <?= $appName ?> account.

Open this link to choose a new password:

<?= $link ?>


The link works once and expires in <?= $minutes ?> minutes. Choosing a new password signs out
every other session. If you did not ask for this, you can ignore this message and your password
will not change.
