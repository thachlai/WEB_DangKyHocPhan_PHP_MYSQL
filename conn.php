<?php
// Thông tin kết nối CSDL
$servername = "localhost"; // Tên máy chủ MySQL (thường là localhost với XAMPP)
$username = "root";      // Tên người dùng MySQL mặc định của XAMPP
$password = "";          // Mật khẩu MySQL mặc định của XAMPP (thường là trống)
$dbname = "dkhp";        // Tên cơ sở dữ liệu của bạn

// Tạo kết nối
$conn = new mysqli($servername, $username, $password, $dbname);

// Kiểm tra kết nối
if ($conn->connect_error) {
    die("Kết nối CSDL thất bại: " . $conn->connect_error);
}

// Thiết lập bộ ký tự UTF-8 để hỗ trợ tiếng Việt
$conn->set_charset("utf8mb4");

// Bạn có thể tùy chọn in ra thông báo kết nối thành công (chỉ để debug, nên xóa khi deploy)
// echo "Kết nối CSDL thành công!";
?>