<?php
require dirname($_SERVER['DOCUMENT_ROOT']) . '/civilina_private/civilina_auth.php';

civilina_logout();
header('Location: /login');
exit;
