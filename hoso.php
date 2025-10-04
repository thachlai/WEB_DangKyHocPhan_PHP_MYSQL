<?php
// Luôn bắt đầu session ở đầu file
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'conn.php';     // Kết nối CSDL
include 'function.php'; // Chứa các hàm hỗ trợ (ví dụ: check_login)

// Kiểm tra xem người dùng đã đăng nhập chưa.
// Hàm check_login() sẽ chuyển hướng nếu chưa đăng nhập.
check_login('/dkhp/'); // Sử dụng base_url để chuyển hướng đúng nếu người dùng chưa đăng nhập

$base_url = '/dkhp/';

$user_id = $_SESSION['ma_tk'] ?? 0; // Lấy ID tài khoản từ session
$user_role_id = $_SESSION['vaitro'] ?? 0; // Lấy vai trò từ session (đã đổi tên biến để dễ hiểu hơn)

$error = '';
$success = '';
$user_profile = null;
$role_name = 'Không xác định'; // Mặc định là không xác định
$khoa_name = 'Chưa cập nhật'; // Mặc định tên khoa

// Lấy thông báo từ session (nếu có)
if (isset($_SESSION['message'])) {
    if (isset($_SESSION['message_type']) && $_SESSION['message_type'] === 'success') {
        $success = $_SESSION['message'];
    } else {
        $error = $_SESSION['message'];
    }
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

if ($user_id > 0) {
    // CẬP NHẬT: Truy vấn thông tin tài khoản và JOIN với bảng 'khoa' để lấy tên khoa
    $sql_profile = "SELECT
                        tk.ma_tk,
                        tk.email,
                        tk.ten_taikhoan,
                        tk.gioitinh,
                        tk.ngaysinh,
                        tk.sdt,
                        tk.vaitro,
                        tk.ma_khoa,
                        tk.img,           -- Lấy cột img để hiển thị ảnh đại diện
                        k.ten_khoa        -- Lấy tên khoa
                    FROM
                        taikhoan tk
                    LEFT JOIN 
                        khoa k ON tk.ma_khoa = k.ma_khoa
                    WHERE
                        tk.ma_tk = ?";

    $stmt_profile = $conn->prepare($sql_profile);
    if ($stmt_profile === false) {
        $error .= "Lỗi chuẩn bị truy vấn hồ sơ: " . $conn->error;
    } else {
        $stmt_profile->bind_param("i", $user_id);
        $stmt_profile->execute();
        $result_profile = $stmt_profile->get_result();
        if ($result_profile->num_rows > 0) {
            $user_profile = $result_profile->fetch_assoc();
            
            // Xử lý tên vai trò
            switch ($user_profile['vaitro']) {
                case 0:
                    $role_name = 'Admin';
                    break;
                case 1:
                    $role_name = 'Sinh viên';
                    break;
                case 2:
                    $role_name = 'Giảng viên';
                    break;
                default:
                    $role_name = 'Không xác định';
                    break;
            }

            // Lấy tên khoa nếu có
            if (!empty($user_profile['ten_khoa'])) {
                $khoa_name = htmlspecialchars($user_profile['ten_khoa']);
            } else {
                // Nếu vai trò không phải SV/GV, thì không cần hiển thị khoa
                if ($user_profile['vaitro'] != 1 && $user_profile['vaitro'] != 2) {
                    $khoa_name = 'Không áp dụng';
                } else {
                    $khoa_name = 'Chưa cập nhật'; // Nếu là SV/GV mà không có khoa
                }
            }

            // Xử lý đường dẫn ảnh đại diện
            $current_avatar_path = $user_profile['img'];
            $display_img_path = $base_url . 'uploads/default_avatar.png'; // Ảnh mặc định
            if (!empty($current_avatar_path) && file_exists($_SERVER['DOCUMENT_ROOT'] . $base_url . $current_avatar_path)) {
                $display_img_path = $base_url . $current_avatar_path;
            }

        } else {
            $error .= "Không tìm thấy thông tin hồ sơ cho tài khoản này.";
        }
        $stmt_profile->close();
    }
} else {
    $error = "Bạn chưa đăng nhập hoặc thông tin tài khoản không hợp lệ.";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hồ sơ của tôi</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>style.css">
    <?php 
    // Load menubar.css phù hợp với vai trò
    if ($user_role_id == 0) : // Admin 
    ?>
        <link rel="stylesheet" href="<?php echo $base_url; ?>admin/css/menubar.css">
    <?php elseif ($user_role_id == 2) : // Giảng viên 
    ?>
        <link rel="stylesheet" href="<?php echo $base_url; ?>giangvien/css/menubar.css">
    <?php elseif ($user_role_id == 1) : // Sinh viên 
    ?>
        <link rel="stylesheet" href="<?php echo $base_url; ?>sinhvien/css/menubar.css">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <style>
        .profile-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center; /* Để căn giữa ảnh và tiêu đề */
        }
        .profile-container h2 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid #007bff; /* Viền xanh nổi bật */
            margin: 0 auto 20px auto; /* Căn giữa và tạo khoảng cách */
            display: block; /* Để margin auto hoạt động */
        }
        .profile-info {
            display: grid;
            grid-template-columns: 1fr; /* Mặc định 1 cột */
            gap: 15px 30px; /* Khoảng cách giữa các hàng và cột */
            padding: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            background-color: #f9f9f9;
            text-align: left; /* Đặt lại căn lề trái cho nội dung info */
        }
        @media (min-width: 600px) { /* Trên màn hình rộng hơn 600px, chia 2 cột */
            .profile-info {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        .info-item {
            display: flex;
            flex-direction: column;
        }
        .info-item label {
            font-weight: bold;
            color: #555;
            margin-bottom: 5px;
        }
        .info-item span {
            background-color: #e9ecef;
            padding: 10px;
            border-radius: 4px;
            color: #333;
            word-wrap: break-word; /* Đảm bảo văn bản dài không tràn ra ngoài */
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-weight: bold;
        }
        .alert.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .action-buttons {
            text-align: center;
            margin-top: 30px;
        }
        .action-buttons a {
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 1em;
            transition: background-color 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin: 0 5px;
        }
        .action-buttons a:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; // Đảm bảo header.php tồn tại và hoạt động đúng ?>

    <main>
        <div class="profile-container">
            <br><br><br>
            <h2>Hồ sơ của tôi</h2>

            <?php if ($success) : ?>
                <div class="alert success">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            <?php if ($error) : ?>
                <div class="alert error">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($user_profile) : ?>
                <img src="<?php echo $display_img_path; ?>" alt="Ảnh đại diện" class="profile-avatar">

                <div class="profile-info">
                    <div class="info-item">
                        <label>Mã tài khoản:</label>
                        <span><?php echo htmlspecialchars($user_profile['ma_tk']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Tên tài khoản:</label>
                        <span><?php echo htmlspecialchars($user_profile['ten_taikhoan']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Email:</label>
                        <span><?php echo htmlspecialchars($user_profile['email']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Giới tính:</label>
                        <span><?php echo htmlspecialchars($user_profile['gioitinh'] ?? 'Chưa cập nhật'); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Ngày sinh:</label>
                        <span><?php echo htmlspecialchars($user_profile['ngaysinh'] ?? 'Chưa cập nhật'); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Số điện thoại:</label>
                        <span><?php echo htmlspecialchars($user_profile['sdt'] ?? 'Chưa cập nhật'); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Vai trò:</label>
                        <span><?php echo htmlspecialchars($role_name); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Khoa:</label>
                        <span><?php echo $khoa_name; ?></span>
                    </div>
                </div>

                <div class="action-buttons">
                    <a href="<?php echo $base_url; ?>sua_hoso.php"><i class="fas fa-edit"></i> Chỉnh sửa hồ sơ</a>
                    <a href="<?php echo $base_url; ?>doi_matkhau.php"><i class="fas fa-key"></i> Đổi mật khẩu</a>
                </div>
            <?php else : ?>
                <p>Không thể tải thông tin hồ sơ. Vui lòng thử lại sau.</p>
            <?php endif; ?>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.alert').forEach(alertDiv => {
                if (alertDiv.innerHTML.trim() !== "") {
                    setTimeout(() => {
                        alertDiv.style.display = 'none';
                    }, 5000);
                }
            });
        });
    </script>
</body>
</html>