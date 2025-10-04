<?php
// Bắt đầu session nếu chưa được bắt đầu
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Đường dẫn gốc của trang web (vẫn cần thiết cho các liên kết khác trong HTML, ví dụ: CSS)
$base_url = '/dkhp/'; 

// Khai báo và khởi tạo biến $email để tránh lỗi undefined khi form chưa được submit
$email = ''; 

// Nếu người dùng đã đăng nhập, chuyển hướng về trang dashboard phù hợp
if (isset($_SESSION['ma_tk'])) {
    switch ($_SESSION['vaitro']) {
        case 0: // Admin
            header("Location: admin/index.php"); 
            break;
        case 1: // Sinh viên
            header("Location: sinhvien/index.php"); 
            break;
        case 2: // Giảng viên
            header("Location: giangvien/index.php"); 
            break;
        default:
            // Nếu vai trò không xác định, chuyển hướng về trang chủ chung
            header("Location: index.php"); // Hoặc một trang lỗi
            break;
    }
    exit();
}

// Bao gồm file kết nối CSDL (nằm cùng cấp với dangnhap.php)
include 'conn.php';

$error_message = ''; // Khởi tạo biến thông báo lỗi

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? ''); 
    $password_raw = trim($_POST['password'] ?? '');
    $password_hashed = md5($password_raw); 

    if (empty($email) || empty($password_raw)) {
        $error_message = "Vui lòng nhập đầy đủ email và mật khẩu.";
    } else {
        // CẬP NHẬT: Thêm cột 'img' vào câu truy vấn SELECT
        $sql = "SELECT ma_tk, email, ten_taikhoan, vaitro, img FROM taikhoan WHERE email = ? AND matkhau = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            $error_message = "Lỗi chuẩn bị truy vấn: " . $conn->error;
        } else {
            $stmt->bind_param("ss", $email, $password_hashed);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                
                $_SESSION['ma_tk'] = $user['ma_tk'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['ten_taikhoan'] = $user['ten_taikhoan']; 
                $_SESSION['vaitro'] = $user['vaitro']; 
                $_SESSION['img'] = $user['img']; // <-- DÒNG MỚI QUAN TRỌNG NHẤT!

                // PHẦN CHUYỂN HƯỚNG THEO VAI TRÒ
                switch ($user['vaitro']) {
                    case 0: // Admin
                        header("Location: admin/index.php"); 
                        break;
                    case 1: // Sinh viên
                        header("Location: sinhvien/index.php"); 
                        break;
                    case 2: // Giảng viên
                        header("Location: giangvien/index.php"); 
                        break;
                    default:
                        header("Location: index.php"); 
                        break;
                }
                exit(); 
            } else {
                $error_message = "Email hoặc mật khẩu không đúng.";
            }
            $stmt->close();
        }
    }
}

$conn->close(); 
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập - Hệ thống Đăng ký Học phần</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="<?php echo $base_url; ?>dangnhap.css"> 
</head>
<body>
    <?php include 'header.php'; ?> 

    <div class="login-container">
        <div class="login-box">
            <h2 class="p-center">Đăng nhập</h2>
            <?php if (!empty($error_message)) : ?>
                <div class="error-message"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <form action="" method="POST">
                <div class="form-group">
                    <input type="email" name="email" required class="form-input" placeholder="Nhập email *" value="<?php echo htmlspecialchars($email); ?>">
                </div>
                <div class="form-group password-toggle">
                    <input type="password" id="password" name="password" required class="form-input" placeholder="Nhập mật khẩu *">
                    <span class="toggle-btn" onclick="togglePassword()"><i class="fas fa-eye"></i></span>
                </div>
                <div class="form-group">
                    <button type="submit" class="login-button">Đăng nhập</button>
                </div>
                <div class="form-group forgot-password">
                    <p class="p-center">Bạn quên mật khẩu? <a class="color-turquoise" href="<?php echo $base_url; ?>quenmatkhau.php">Quên mật khẩu</a></p>
                </div>
            </form>
        </div>
    </div>

    <script>
        function togglePassword() {
            var passwordInput = document.getElementById("password");
            var toggleIcon = document.querySelector(".toggle-btn i"); 

            var isPasswordVisible = passwordInput.type === "text";
            passwordInput.type = isPasswordVisible ? "password" : "text";

            toggleIcon.classList.toggle("fa-eye", !isPasswordVisible);
            toggleIcon.classList.toggle("fa-eye-slash", isPasswordVisible);
        }
    </script>
</body>
</html>