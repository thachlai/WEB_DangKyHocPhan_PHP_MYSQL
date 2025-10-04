<?php
// Luôn bắt đầu session ở đầu file
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'conn.php';
include 'function.php';

check_login('/dkhp/'); // Đảm bảo người dùng đã đăng nhập

$base_url = '/dkhp/';
$upload_dir = 'uploads/'; // Thư mục lưu trữ ảnh, nằm trong dkhp/

$user_id = $_SESSION['ma_tk'] ?? 0;
$user_role_id = $_SESSION['vaitro'] ?? 0; // Lấy vai trò từ session: 0 Admin, 1 Sinhvien, 2 Giảng viên

$error = '';
$success = '';
$user_profile = null; // Biến để lưu trữ thông tin hiện tại của người dùng
$khoa_options = []; // Mảng để lưu danh sách các khoa (chỉ cho Giảng viên)
$current_avatar_path = ''; // Đường dẫn ảnh đại diện hiện tại (ví dụ: 'uploads/abc.png')
$display_img_path = $base_url . 'uploads/default_avatar.png'; // Đường dẫn ảnh sẽ hiển thị trên form

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

// --- Lấy thông tin hồ sơ hiện tại của người dùng để điền vào form ---
if ($user_id > 0) {
    $sql_fetch_profile = "SELECT
                            email,
                            ten_taikhoan,
                            gioitinh,
                            ngaysinh,
                            sdt,
                            ma_khoa,
                            img        -- Lấy cả trường img
                          FROM
                            taikhoan
                          WHERE
                            ma_tk = ?";
    $stmt_fetch_profile = $conn->prepare($sql_fetch_profile);
    if ($stmt_fetch_profile === false) {
        $error .= "Lỗi chuẩn bị truy vấn thông tin hồ sơ: " . $conn->error;
    } else {
        $stmt_fetch_profile->bind_param("i", $user_id);
        $stmt_fetch_profile->execute();
        $result_fetch_profile = $stmt_fetch_profile->get_result();
        if ($result_fetch_profile->num_rows > 0) {
            $user_profile = $result_fetch_profile->fetch_assoc();
            $current_avatar_path = $user_profile['img']; // Lưu đường dẫn ảnh hiện tại

            // Xác định đường dẫn ảnh để hiển thị
            if (!empty($current_avatar_path) && file_exists($_SERVER['DOCUMENT_ROOT'] . $base_url . $current_avatar_path)) {
                $display_img_path = $base_url . $current_avatar_path;
            } else {
                $display_img_path = $base_url . 'uploads/default_avatar.png'; // Ảnh mặc định nếu không có hoặc file không tồn tại
            }

        } else {
            $error .= "Không tìm thấy thông tin hồ sơ của bạn.";
        }
        $stmt_fetch_profile->close();
    }

    // --- Lấy danh sách các khoa để hiển thị trong dropdown (CHỈ CHO GIẢNG VIÊN) ---
    if ($user_role_id === 2) { // CHỈ Giảng viên mới cần load danh sách khoa
        $sql_khoa = "SELECT ma_khoa, ten_khoa FROM khoa ORDER BY ten_khoa ASC";
        $result_khoa = $conn->query($sql_khoa);
        if ($result_khoa) {
            while ($row_khoa = $result_khoa->fetch_assoc()) {
                $khoa_options[] = $row_khoa;
            }
        } else {
            $error .= "Lỗi khi lấy danh sách khoa: " . $conn->error;
        }
    }

} else {
    $error = "Bạn chưa đăng nhập hoặc thông tin tài khoản không hợp lệ.";
}

// --- Xử lý khi form được submit (POST request) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_profile) {
    $new_ten_taikhoan = $_POST['ten_taikhoan'] ?? '';
    $new_email = $_POST['email'] ?? '';
    $new_gioitinh = $_POST['gioitinh'] ?? '';
    $new_ngaysinh = $_POST['ngaysinh'] ?? '';
    $new_sdt = $_POST['sdt'] ?? '';
    // $new_ma_khoa chỉ lấy nếu vai trò là giảng viên
    $new_ma_khoa = ($user_role_id === 2) ? ($_POST['ma_khoa'] ?? null) : $user_profile['ma_khoa'];


    // Biến để lưu đường dẫn ảnh mới nếu có upload
    $new_img_path = $current_avatar_path; 

    // Kiểm tra và xử lý upload ảnh đại diện
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file_tmp_name = $_FILES['avatar']['tmp_name'];
        $file_name = $_FILES['avatar']['name'];
        $file_size = $_FILES['avatar']['size'];
        $file_type = $_FILES['avatar']['type'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        $max_file_size = 5 * 1024 * 1024; // 5MB

        if (!in_array($file_ext, $allowed_extensions)) {
            $error = "Chỉ cho phép tải lên ảnh định dạng JPG, JPEG, PNG, GIF.";
        } elseif ($file_size > $max_file_size) {
            $error = "Kích thước ảnh không được vượt quá 5MB.";
        } else {
            // Tạo tên file duy nhất để tránh trùng lặp
            $new_file_name = uniqid('avatar_') . '.' . $file_ext;
            $destination_path = $upload_dir . $new_file_name; // Đường dẫn tương đối từ dkhp/

            // Đường dẫn tuyệt đối trên server để move_uploaded_file
            $full_destination_path = $_SERVER['DOCUMENT_ROOT'] . $base_url . $destination_path;

            if (move_uploaded_file($file_tmp_name, $full_destination_path)) {
                // Xóa ảnh cũ nếu không phải là ảnh mặc định và tồn tại
                if (!empty($current_avatar_path) && $current_avatar_path !== 'uploads/default_avatar.png' && file_exists($_SERVER['DOCUMENT_ROOT'] . $base_url . $current_avatar_path)) {
                    unlink($_SERVER['DOCUMENT_ROOT'] . $base_url . $current_avatar_path);
                }
                $new_img_path = $destination_path; // Cập nhật đường dẫn ảnh mới
            } else {
                $error = "Có lỗi khi tải lên ảnh đại diện.";
            }
        }
    }

    // Các biến để lưu dữ liệu sẽ UPDATE
    $update_fields = [];
    $bind_types = '';
    $bind_values = [];

    // Luôn cho phép chỉnh sửa giới tính, ngày sinh, số điện thoại
    $update_fields[] = "gioitinh = ?";
    $bind_types .= "s";
    $bind_values[] = $new_gioitinh;

    $update_fields[] = "ngaysinh = ?";
    $bind_types .= "s";
    $bind_values[] = $new_ngaysinh;

    $update_fields[] = "sdt = ?";
    $bind_types .= "s";
    $bind_values[] = $new_sdt;

    // Cập nhật trường img
    $update_fields[] = "img = ?";
    $bind_types .= "s";
    $bind_values[] = $new_img_path;

    // --- Kiểm tra quyền và xử lý các trường tùy theo vai trò ---
    if (empty($error)) { // Chỉ tiếp tục nếu không có lỗi từ việc upload ảnh
        if ($user_role_id === 0) { // Admin có thể chỉnh sửa email, ten_taikhoan
            // Email
            if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                $error = "Email không hợp lệ.";
            } else {
                // Kiểm tra trùng email nếu email thay đổi
                if ($new_email !== $user_profile['email']) {
                    $sql_check_email_exist = "SELECT ma_tk FROM taikhoan WHERE email = ? AND ma_tk != ?";
                    $stmt_check_email_exist = $conn->prepare($sql_check_email_exist);
                    $stmt_check_email_exist->bind_param("si", $new_email, $user_id);
                    $stmt_check_email_exist->execute();
                    $result_check_email_exist = $stmt_check_email_exist->get_result();
                    if ($result_check_email_exist->num_rows > 0) {
                        $error = "Email này đã được sử dụng bởi một tài khoản khác.";
                    }
                    $stmt_check_email_exist->close();
                }
                if (empty($error)) {
                    $update_fields[] = "email = ?";
                    $bind_types .= "s";
                    $bind_values[] = $new_email;
                }
            }

            // Tên tài khoản
            if (empty($new_ten_taikhoan)) {
                $error = "Tên tài khoản không được để trống.";
            } else {
                $update_fields[] = "ten_taikhoan = ?";
                $bind_types .= "s";
                $bind_values[] = $new_ten_taikhoan;
            }
            
            // Admin KHÔNG CHỈNH SỬA ma_khoa cho bản thân qua đây.
            // Biến $new_ma_khoa đã được xử lý để giữ nguyên giá trị cũ nếu vai trò không phải GV.

        } elseif ($user_role_id === 2) { // Giảng viên: chỉnh sửa ten_taikhoan, ma_khoa
            // Tên tài khoản
            if (empty($new_ten_taikhoan)) {
                $error = "Tên tài khoản không được để trống.";
            } else {
                $update_fields[] = "ten_taikhoan = ?";
                $bind_types .= "s";
                $bind_values[] = $new_ten_taikhoan;
            }

            // Mã khoa (có thể là NULL nếu chọn rỗng)
            $update_fields[] = "ma_khoa = ?";
            $bind_types .= "i";
            $bind_values[] = $new_ma_khoa;

            // Email không được chỉnh sửa bởi Giảng viên
        }
        // Sinh viên (user_role_id === 1): chỉ chỉnh sửa gioitinh, ngaysinh, sdt, img (đã được thêm ở trên)
        // Email, ten_taikhoan, ma_khoa của sinh viên không được thêm vào update_fields
    }


    if (empty($error) && !empty($update_fields)) { // Nếu không có lỗi và có trường cần cập nhật
        $sql_update_profile = "UPDATE taikhoan SET " . implode(", ", $update_fields) . " WHERE ma_tk = ?";
        $bind_types .= "i"; // Thêm kiểu cho ma_tk
        $bind_values[] = $user_id; // Thêm giá trị ma_tk

        $stmt_update_profile = $conn->prepare($sql_update_profile);

        if ($stmt_update_profile === false) {
            $error .= "Lỗi chuẩn bị cập nhật hồ sơ: " . $conn->error;
        } else {
            // Dùng call_user_func_array để bind_param với mảng động
            call_user_func_array([$stmt_update_profile, 'bind_param'], array_merge([$bind_types], $bind_values));

            if ($stmt_update_profile->execute()) {
                $_SESSION['message'] = "Hồ sơ của bạn đã được cập nhật thành công!";
                $_SESSION['message_type'] = "success";
                
                // Cập nhật lại các biến session nếu có thay đổi
                if (($user_role_id === 0 || $user_role_id === 2) && $_SESSION['ten_taikhoan'] !== $new_ten_taikhoan) {
                    $_SESSION['ten_taikhoan'] = $new_ten_taikhoan;
                }
                if ($_SESSION['img'] !== $new_img_path) {
                    $_SESSION['img'] = $new_img_path; // Cập nhật ảnh trong session
                }

                header("Location: hoso.php"); // Chuyển hướng về trang hồ sơ
                exit();
            } else {
                $error = "Có lỗi xảy ra khi cập nhật hồ sơ: " . $stmt_update_profile->error;
            }
            $stmt_update_profile->close();
        }
    } elseif (empty($error) && empty($update_fields)) {
        $success = "Không có thông tin nào được thay đổi.";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chỉnh sửa hồ sơ</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>style.css">
    <?php
    // Bao gồm CSS menubar dựa trên vai trò của người dùng
    $user_role_id_css = $_SESSION['vaitro'] ?? 0; // Lấy vai trò từ session để chọn CSS
    if ($user_role_id_css == 0) : // Admin
    ?>
        <link rel="stylesheet" href="<?php echo $base_url; ?>admin/css/menubar.css">
    <?php elseif ($user_role_id_css == 2) : // Giảng viên ?>
        <link rel="stylesheet" href="<?php echo $base_url; ?>giangvien/css/menubar.css">
    <?php elseif ($user_role_id_css == 1) : // Sinh viên ?>
        <link rel="stylesheet" href="<?php echo $base_url; ?>sinhvien/css/menubar.css">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <style>
        /* Đảm bảo các CSS này không bị trùng lặp với style.css */
        body {
            background-color: #f4f7f6;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
        }

        .form-container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center; /* Căn giữa ảnh và tiêu đề */
        }
        .form-container h2 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        .profile-avatar-edit {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #007bff;
            margin: 0 auto 20px auto;
            display: block;
            cursor: pointer; /* Để người dùng biết có thể click vào */
        }
        .form-group {
            margin-bottom: 15px;
            text-align: left; /* Căn trái nội dung form */
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #555;
        }
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="date"],
        .form-group input[type="tel"],
        .form-group input[type="file"], /* Áp dụng cho input file */
        .form-group select {
            width: calc(100% - 22px);
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 1em;
            box-sizing: border-box;
        }
        .form-group input:focus,
        .form-group select:focus {
            border-color: #007bff;
            outline: none;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.25);
        }
        .form-group input[disabled], .form-group select[disabled] {
            background-color: #e9ecef;
            cursor: not-allowed;
            color: #6c757d;
        }
        .form-actions {
            text-align: center;
            margin-top: 20px;
        }
        .form-actions button {
            background-color: #007bff;
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
            background-color: #0056b3;
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
            <h2>Chỉnh sửa hồ sơ</h2>

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
                <form action="sua_hoso.php" method="POST" enctype="multipart/form-data">
                    <div class="form-group" style="text-align: center;">
                        <label for="avatar_input" style="cursor: pointer;">
                            <img src="<?php echo $display_img_path; ?>" alt="Ảnh đại diện" class="profile-avatar-edit" id="avatar_preview">
                            <br>
                            Click để thay đổi ảnh đại diện
                        </label>
                        <input type="file" id="avatar_input" name="avatar" accept="image/*" style="display: none;">
                    </div>

                    <div class="form-group">
                        <label for="ten_taikhoan">Tên tài khoản:</label>
                        <input type="text" id="ten_taikhoan" name="ten_taikhoan"
                               value="<?php echo htmlspecialchars($user_profile['ten_taikhoan'] ?? ''); ?>"
                               <?php echo ($user_role_id === 1) ? 'disabled' : ''; ?> required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email"
                               value="<?php echo htmlspecialchars($user_profile['email'] ?? ''); ?>"
                               <?php echo ($user_role_id === 1 || $user_role_id === 2) ? 'disabled' : ''; ?> required>
                    </div>
                    <div class="form-group">
                        <label for="gioitinh">Giới tính:</label>
                        <select id="gioitinh" name="gioitinh">
                            <option value="">-- Chọn giới tính --</option>
                            <option value="Nam" <?php echo ($user_profile['gioitinh'] === 'Nam') ? 'selected' : ''; ?>>Nam</option>
                            <option value="Nữ" <?php echo ($user_profile['gioitinh'] === 'Nữ') ? 'selected' : ''; ?>>Nữ</option>
                            <option value="Khác" <?php echo ($user_profile['gioitinh'] === 'Khác') ? 'selected' : ''; ?>>Khác</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="ngaysinh">Ngày sinh:</label>
                        <input type="date" id="ngaysinh" name="ngaysinh" value="<?php echo htmlspecialchars($user_profile['ngaysinh'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="sdt">Số điện thoại:</label>
                        <input type="tel" id="sdt" name="sdt" value="<?php echo htmlspecialchars($user_profile['sdt'] ?? ''); ?>">
                    </div>

                    <?php if ($user_role_id === 2) : // CHỈ Giảng viên mới hiển thị trường khoa ?>
                    <div class="form-group">
                        <label for="ma_khoa">Khoa:</label>
                        <select id="ma_khoa" name="ma_khoa">
                            <option value="">-- Chọn khoa --</option>
                            <?php foreach ($khoa_options as $khoa) : ?>
                                <option value="<?php echo htmlspecialchars($khoa['ma_khoa']); ?>"
                                    <?php echo ($user_profile['ma_khoa'] == $khoa['ma_khoa']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($khoa['ten_khoa']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="form-actions">
                        <button type="submit"><i class="fas fa-save"></i> Cập nhật hồ sơ</button>
                        <a href="<?php echo $base_url; ?>hoso.php"><i class="fas fa-times-circle"></i> Hủy</a>
                    </div>
                </form>
            <?php else : ?>
                <p>Không thể tải thông tin hồ sơ để chỉnh sửa.</p>
            <?php endif; ?>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Script ẩn thông báo
            document.querySelectorAll('.alert').forEach(alertDiv => {
                if (alertDiv.innerHTML.trim() !== "") {
                    setTimeout(() => {
                        alertDiv.style.display = 'none';
                    }, 5000);
                }
            });

            // Script xem trước ảnh đại diện
            const avatarInput = document.getElementById('avatar_input');
            const avatarPreview = document.getElementById('avatar_preview');

            if (avatarInput && avatarPreview) {
                avatarInput.addEventListener('change', function(event) {
                    const file = event.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            avatarPreview.src = e.target.result;
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
        });
    </script>
</body>
</html>