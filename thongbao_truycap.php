<?php
// BƯỚC 1: Include function.php để có session_start(), get_dkhp_base_url() và display_message()
include 'function.php'; // Đảm bảo đường dẫn này đúng (từ thongbao_truycap.php đến function.php)

// BƯỚC 2: Kiểm tra xem có thông báo lỗi nào trong session không
// Nếu không có, có thể chuyển hướng về trang chủ chung hoặc trang đăng nhập
if (!isset($_SESSION['message']) || $_SESSION['message_type'] !== 'error') {
    // Có thể chuyển hướng về trang chủ chung nếu không có thông báo lỗi cụ thể
    // hoặc để trống để người dùng vẫn thấy trang thông báo chung.
    // Tùy theo mức độ nghiêm trọng bạn muốn xử lý.
    // redirect_to_dkhp_root('index.php'); 
}

// BƯỚC 3: Bao gồm header.php để hiển thị cấu trúc trang
include 'header.php'; // Đảm bảo đường dẫn này đúng (từ thongbao_truycap.php đến header.php)
?>

<div style="text-align: center; margin-top: 50px;">
    <h2>Truy Cập Bất Hợp Pháp!</h2>
    <p style="color: red; font-weight: bold;">
        <?php 
        // Hiển thị thông báo chi tiết từ session (nếu có)
        display_message(); 
        ?>
    </p>
    <p>Bạn không có quyền truy cập vào khu vực này.</p>
    <br>
    <?php
    // Nút/liên kết để quay về trang chủ dựa trên vai trò
    $base_url = get_dkhp_base_url();
    $return_path = 'dangnhap.php'; // Mặc định về trang đăng nhập

    // Nếu đã đăng nhập, xác định trang chủ của vai trò
    if (isset($_SESSION['vaitro'])) {
        $vaitro = $_SESSION['vaitro'];
        if ($vaitro == 0) { // Admin
            $return_path = 'admin/index.php';
        } elseif ($vaitro == 1) { // Sinh viên
            $return_path = 'sinhvien/index.php';
        } elseif ($vaitro == 2) { // Giảng viên
            $return_path = 'giangvien/index.php';
        } else {
             // Vai trò không xác định, có thể đăng xuất hoặc về trang đăng nhập
             session_destroy();
             $return_path = 'dangnhap.php';
        }
    }
    ?>
    <a href="<?php echo $base_url . $return_path; ?>" 
       style="display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px;">
        Quay về trang chính
    </a>
</div>

<?php

?>