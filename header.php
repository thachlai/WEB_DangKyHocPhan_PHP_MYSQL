<?php
// Bắt đầu session nếu chưa được bắt đầu
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Bao gồm file kết nối CSDL (nằm cùng cấp với header.php)
include 'conn.php'; 

// --- Logic xác định base URL và kiểm tra đăng nhập ---

// Xác định đường dẫn gốc của trang web
$base_url = '/dkhp/'; // Điều chỉnh giá trị này cho phù hợp với môi trường của bạn

// Lấy thông tin người dùng từ session
$is_logged_in = isset($_SESSION['ma_tk']); // Kiểm tra xem 'ma_tk' có trong session không
$user_role = $_SESSION['vaitro'] ?? null; // Lấy vai trò, mặc định là null nếu chưa đăng nhập
$user_fullname = $_SESSION['ten_taikhoan'] ?? 'Khách'; // Lấy tên tài khoản, mặc định là 'Khách'

// CẬP NHẬT: Lấy đường dẫn ảnh từ session, tương tự như menubar.php
$avatar_path = $base_url . 'uploads/default_avatar.png'; // Mặc định là ảnh default
if (isset($_SESSION['img']) && !empty($_SESSION['img'])) {
    $temp_avatar_path = $base_url . $_SESSION['img'];
    // Kiểm tra xem file vật lý có tồn tại không
    $file_path_on_server = $_SERVER['DOCUMENT_ROOT'] . $temp_avatar_path;
    if (file_exists($file_path_on_server)) {
        $avatar_path = $temp_avatar_path;
    }
}

// Xác định đường dẫn trang chủ riêng cho từng vai trò
$home_page_url_for_role = $base_url . 'index.php'; 
if ($is_logged_in) {
    if ($user_role == 0) { // Admin
        $home_page_url_for_role = $base_url . 'admin/index.php'; 
    } elseif ($user_role == 1) { // Sinh viên
        $home_page_url_for_role = $base_url . 'sinhvien/index.php';
    } elseif ($user_role == 2) { // Giảng viên
        $home_page_url_for_role = $base_url . 'giangvien/index.php';
    }
}

// --- Kết thúc logic xác định base URL và kiểm tra đăng nhập ---
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Hệ thống Đăng ký Học phần</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="<?php echo $base_url; ?>style.css" /> 
    <style>
        /* CSS cho menu dropdown, bạn có thể chuyển vào style.css nếu muốn */
        .menu li {
            position: relative;
        }

        .menu li ul.dropdown-menu {
            display: none;
            position: absolute;
            background-color: #fff;
            min-width: 180px;
            z-index: 100;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            padding: 0;
            margin: 0;
            list-style: none;
            /* Căn chỉnh vị trí dropdown */
            right: 0; /* Đẩy dropdown sang phải để cân đối với user-icon */
        }

        .menu li:hover > ul.dropdown-menu {
            display: block;
        }

        .dropdown-menu li {
            list-style: none;
        }

        .dropdown-menu li a {
            display: block;
            padding: 10px;
            color: #333;
            text-decoration: none;
            white-space: nowrap;
        }

        .dropdown-menu li a:hover {
            background-color: #f5f5f5;
        }

        /* Basic header styling (Bạn có thể chuyển vào style.css) */
        header {
            background-color: #4CAF50;
            color: white;
            padding: 10px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .container_header {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }

        .logo a {
            text-decoration: none;
            color: white;
            font-size: 24px;
            font-weight: bold;
            display: flex; /* Dùng flex để căn chỉnh logo và chữ */
            align-items: center;
        }

        .logo img {
            height: 50px; /* Điều chỉnh lại theo kích thước mong muốn */
            vertical-align: middle;
            margin-right: 10px; /* Khoảng cách giữa logo và chữ */
        }

        nav ul.menu {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
        }

        nav ul.menu li a {
            display: block;
            padding: 15px 20px;
            color: white;
            text-decoration: none;
            transition: background-color 0.3s ease;
        }

        nav ul.menu li a:hover {
            background-color: #45a049;
        }

        .user-icon img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            vertical-align: middle;
            margin-right: 5px;
        }
        .user-info {
            text-align: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
    </style>
</head>
<body>
<header>
    <div class="container_header">
        <div class="logo">
            <a href="<?php echo $home_page_url_for_role; ?>">
                <img src="<?php echo $base_url; ?>uploads/logo.png" alt="Logo Trường" />
                Hệ thống Đăng ký Học phần
            </a>
        </div>
        <nav>
            <ul class="menu">
                <!-- <li><a href="<?php echo $home_page_url_for_role; ?>">Trang chủ</a></li> -->

                <?php if ($is_logged_in): ?>
                    <?php if ($user_role == 1): /* Sinh viên */ ?>
                        <li><a href="<?php echo $base_url; ?>sinhvien/dangkyhocphan.php">Đăng ký HP</a></li>
                        <li><a href="<?php echo $base_url; ?>sinhvien/tkb_canhan.php">TKB Cá nhân</a></li>
                    <?php elseif ($user_role == 2): /* Giảng viên */ ?>
                        <li><a href="<?php echo $base_url; ?>giangvien/list_lophp.php">Lớp HP của tôi</a></li>
                    <?php endif; ?>
                <?php endif; ?>

                <li class="user-icon">
                    <a href="#"><i class="fas fa-user"></i></a>
                    <ul class="dropdown-menu">
                        <?php if ($is_logged_in): ?>
                            <li class="user-info">
                                <img src="<?php echo $avatar_path; ?>" alt="Avatar" />
                                <br />
                                <strong><?php echo htmlspecialchars($user_fullname); ?></strong>
                                <br />
                                <small>
                                <?php
                                    if ($user_role == 0) echo 'Admin';
                                    elseif ($user_role == 1) echo 'Sinh viên';
                                    elseif ($user_role == 2) echo 'Giảng viên';
                                    else echo 'Khách'; // Trường hợp này thường không xảy ra nếu $is_logged_in là true
                                ?>
                                </small>
                            </li>
                            <li><a href="<?php echo $base_url; ?>hoso.php">Hồ sơ</a></li>
                            
                            <li><a href="<?php echo $base_url; ?>dangxuat.php">Đăng xuất</a></li>
                        <?php else: ?>
                            <li><a href="<?php echo $base_url; ?>dangnhap.php">Đăng nhập</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
            </ul>
        </nav>
    </div>
</header>