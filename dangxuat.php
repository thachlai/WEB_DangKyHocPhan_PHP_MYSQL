<?php
// Bắt đầu session nếu chưa được bắt đầu.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Xóa tất cả các biến session
$_SESSION = array();

// Nếu muốn hủy hoàn toàn session, cũng xóa cookie session.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Cuối cùng, hủy session
session_destroy();

// Chuyển hướng người dùng về trang đăng nhập
// Vì dangnhap.php nằm cùng cấp với dangxuat.php, chỉ cần tên file
header("Location: dangnhap.php"); // Đã bỏ $base_url và tiền tố thư mục con
exit(); 
?>