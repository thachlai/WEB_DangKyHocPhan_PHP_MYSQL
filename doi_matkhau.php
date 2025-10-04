<?php
// Luôn bắt đầu session ở đầu file
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'conn.php';
include 'function.php';

check_login('/dkhp/'); // Đảm bảo người dùng đã đăng nhập

$base_url = '/dkhp/';

$user_id = $_SESSION['ma_tk'] ?? 0;

$error = '';
$success = '';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_new_password = $_POST['confirm_new_password'] ?? '';

    // Mã hóa mật khẩu cũ và mật khẩu mới để so sánh
    // CHÚ Ý: MD5 không còn được khuyến nghị cho mật khẩu trong môi trường production thực tế
    // nhưng được sử dụng ở đây theo yêu cầu của bạn.
    $current_password_md5 = md5($current_password);
    $new_password_md5 = md5($new_password);

    if (empty($current_password) || empty($new_password) || empty($confirm_new_password)) {
        $error = "Vui lòng điền đầy đủ tất cả các trường.";
    } elseif ($new_password !== $confirm_new_password) {
        $error = "Mật khẩu mới và xác nhận mật khẩu mới không khớp.";
    } elseif (strlen($new_password) < 6) { // Yêu cầu mật khẩu tối thiểu 6 ký tự
        $error = "Mật khẩu mới phải có ít nhất 6 ký tự.";
    } else {
        // Lấy mật khẩu hiện tại của người dùng từ CSDL
        $sql_check_password = "SELECT matkhau FROM taikhoan WHERE ma_tk = ?";
        $stmt_check_password = $conn->prepare($sql_check_password);
        if ($stmt_check_password === false) {
            $error .= "Lỗi chuẩn bị truy vấn mật khẩu: " . $conn->error;
        } else {
            $stmt_check_password->bind_param("i", $user_id);
            $stmt_check_password->execute();
            $result_check_password = $stmt_check_password->get_result();
            if ($result_check_password->num_rows > 0) {
                $row = $result_check_password->fetch_assoc();
                $stored_password_md5 = $row['matkhau'];

                if ($current_password_md5 === $stored_password_md5) {
                    // Mật khẩu cũ khớp, tiến hành cập nhật mật khẩu mới
                    $sql_update_password = "UPDATE taikhoan SET matkhau = ? WHERE ma_tk = ?";
                    $stmt_update_password = $conn->prepare($sql_update_password);
                    if ($stmt_update_password === false) {
                        $error .= "Lỗi chuẩn bị cập nhật mật khẩu: " . $conn->error;
                    } else {
                        $stmt_update_password->bind_param("si", $new_password_md5, $user_id);
                        if ($stmt_update_password->execute()) {
                            $_SESSION['message'] = "Mật khẩu của bạn đã được thay đổi thành công!";
                            $_SESSION['message_type'] = "success";
                            header("Location: hoso.php"); // Chuyển hướng về trang hồ sơ
                            exit();
                        } else {
                            $error = "Có lỗi xảy ra khi cập nhật mật khẩu: " . $stmt_update_password->error;
                        }
                        $stmt_update_password->close();
                    }
                } else {
                    $error = "Mật khẩu hiện tại không đúng.";
                }
            } else {
                $error = "Không tìm thấy tài khoản của bạn.";
            }
            $stmt_check_password->close();
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
    <title>Đổi mật khẩu</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>style.css">
    <?php
    // Bao gồm CSS menubar dựa trên vai trò của người dùng
    $user_role_id = $_SESSION['vaitro'] ?? 0; // Lấy vai trò từ session
    if ($user_role_id == 0) : // Admin
    ?>
        <link rel="stylesheet" href="<?php echo $base_url; ?>admin/css/menubar.css">
    <?php elseif ($user_role_id == 2) : // Giảng viên ?>
        <link rel="stylesheet" href="<?php echo $base_url; ?>giangvien/css/menubar.css">
    <?php elseif ($user_role_id == 1) : // Sinh viên ?>
        <link rel="stylesheet" href="<?php echo $base_url; ?>sinhvien/css/menubar.css">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <style>
        .form-container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-container h2 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #555;
        }
        .form-group input[type="password"] {
            width: calc(100% - 22px); /* Kích thước trừ padding và border */
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 1em;
            box-sizing: border-box; /* Đảm bảo padding không làm tràn width */
        }
        .form-group input[type="password"]:focus {
            border-color: #007bff;
            outline: none;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.25);
        }
        .form-actions {
            text-align: center;
            margin-top: 20px;
        }
        .form-actions button {
            background-color: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1.1em;
            transition: background-color 0.3s ease;
            margin: 0 5px;
        }
        .form-actions button:hover {
            background-color: #218838;
        }
        .form-actions a {
            background-color: #6c757d;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 1.1em;
            transition: background-color 0.3s ease;
            margin: 0 5px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .form-actions a:hover {
            background-color: #5a6268;
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
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <main>
        <div class="form-container">
            <br><br><br>
            <h2>Đổi mật khẩu</h2>

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

            <form action="doi_matkhau.php" method="POST">
                <div class="form-group">
                    <label for="current_password">Mật khẩu hiện tại:</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label for="new_password">Mật khẩu mới:</label>
                    <input type="password" id="new_password" name="new_password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_new_password">Xác nhận mật khẩu mới:</label>
                    <input type="password" id="confirm_new_password" name="confirm_new_password" required>
                </div>
                <div class="form-actions">
                    <button type="submit"><i class="fas fa-save"></i> Cập nhật mật khẩu</button>
                    <a href="hoso.php"><i class="fas fa-times-circle"></i> Hủy</a>
                </div>
            </form>
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