<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
admin_logout();
header('Location: login.php');
