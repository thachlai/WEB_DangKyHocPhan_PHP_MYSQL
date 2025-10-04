<?php
session_start();
include '../conn.php';
include '../function.php';
check_login();   // Đảm bảo người dùng đã đăng nhập
check_admin('/dkhp/');
include '../header.php';
include 'menubar.php';

?>