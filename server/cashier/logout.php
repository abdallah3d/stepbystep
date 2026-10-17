<?php
require __DIR__ . '/auth.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    staff_logout();
}
go('login.php');
