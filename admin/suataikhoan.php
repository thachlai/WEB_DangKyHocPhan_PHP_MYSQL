<?php
// Luôn bắt đầu session ở đầu file
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Đường dẫn tương đối từ admin/ ra thư mục gốc dkhp/
include '../conn.php';
include '../function.php';

// Gọi hàm kiểm tra quyền Admin và đăng nhập
check_login();   // Đảm bảo người dùng đã đăng nhập
check_admin();
$ma_tk = $_GET['id'] ?? 0; // Lấy ma_tk từ URL, mặc định là 0 nếu không có
$ma_tk = intval($ma_tk); // Đảm bảo là số nguyên

$success = '';
$error = '';

// Khởi tạo các biến để giữ giá trị form (cho lần đầu load hoặc khi có lỗi)
$email = '';
$ten_taikhoan = '';
$gioitinh = '';
$ngaysinh = '';
$sdt = '';
$vaitro = '';
$ma_khoa = NULL; // NULL nếu không có khoa
$current_img_path = 'uploads/default_avatar.png'; // Đường dẫn ảnh hiện tại, mặc định là ảnh default

// Lấy thông tin tài khoản hiện có để điền vào form
if ($ma_tk > 0) {
    $stmt = $conn->prepare("SELECT email, ten_taikhoan, gioitinh, ngaysinh, sdt, vaitro, ma_khoa, img FROM taikhoan WHERE ma_tk = ?");
    $stmt->bind_param("i", $ma_tk);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $email = htmlspecialchars($row['email']);
        $ten_taikhoan = htmlspecialchars($row['ten_taikhoan']);
        $gioitinh = htmlspecialchars($row['gioitinh']);
        $ngaysinh = htmlspecialchars($row['ngaysinh']);
        $sdt = htmlspecialchars($row['sdt']);
        $vaitro = htmlspecialchars($row['vaitro']);
        $ma_khoa = $row['ma_khoa']; // Giữ nguyên dạng số hoặc NULL
        $current_img_path = htmlspecialchars($row['img'] ?? 'uploads/default_avatar.png'); // Lấy ảnh hiện tại
    } else {
        $_SESSION['error_message'] = "Không tìm thấy tài khoản với mã: " . $ma_tk;
        header("Location: taikhoan.php");
        exit();
    }
    $stmt->close();
} else {
    $_SESSION['error_message'] = "Mã tài khoản không hợp lệ.";
    header("Location: taikhoan.php");
    exit();
}

// Lấy danh sách khoa từ CSDL (để đổ vào dropdown select)
$khoa_options = [];
$sql_khoa = "SELECT ma_khoa, ten_khoa FROM khoa ORDER BY ten_khoa";
$result_khoa = $conn->query($sql_khoa);
if ($result_khoa && $result_khoa->num_rows > 0) {
    while ($row_khoa = $result_khoa->fetch_assoc()) {
        $khoa_options[] = $row_khoa;
    }
}

// Xử lý khi form được submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lấy dữ liệu từ form
    $ten_taikhoan_new = trim($_POST['ten_taikhoan'] ?? '');
    $email_new = trim($_POST['email'] ?? '');
    $password_raw_new = trim($_POST['password'] ?? ''); // Mật khẩu mới, có thể để trống
    $gioitinh_new = $_POST['gioitinh'] ?? '';
    $sdt_new = trim($_POST['sdt'] ?? '');
    $ngaysinh_new = $_POST['ngaysinh'] ?? '';
    $vaitro_new = $_POST['vaitro'] ?? '';
    $ma_khoa_new = $_POST['ma_khoa'] ?? NULL;

    // Cập nhật lại các biến hiển thị trên form nếu có lỗi
    $email = $email_new;
    $ten_taikhoan = $ten_taikhoan_new;
    $gioitinh = $gioitinh_new;
    $ngaysinh = $ngaysinh_new;
    $sdt = $sdt_new;
    $vaitro = $vaitro_new;
    $ma_khoa = $ma_khoa_new;

    // --- Validate dữ liệu đầu vào ---
    if (empty($email_new) || empty($ten_taikhoan_new) || $vaitro_new === '') {
        $error = "Vui lòng điền đầy đủ các trường bắt buộc (Email, Tên tài khoản, Vai trò).";
    } elseif (!filter_var($email_new, FILTER_VALIDATE_EMAIL)) {
        $error = "Email không hợp lệ. Vui lòng nhập đúng định dạng email.";
    } elseif (!empty($password_raw_new) && strlen($password_raw_new) < 6) {
        $error = "Mật khẩu mới (nếu có) phải có ít nhất 6 ký tự.";
    } elseif (!empty($sdt_new) && !preg_match("/^[0-9]{10,11}$/", $sdt_new)) {
        $error = "Số điện thoại không hợp lệ (chỉ chấp nhận 10 hoặc 11 chữ số).";
    } elseif (($vaitro_new == '1' || $vaitro_new == '2') && empty($ma_khoa_new)) {
        $error = "Vui lòng chọn khoa cho Sinh viên hoặc Giảng viên.";
    } else {
        // --- Xử lý tải lên ảnh mới ---
        $new_img_path_for_db = $current_img_path; // Mặc định giữ ảnh cũ
        if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] == 0) {
            $target_dir = "../uploads/"; // Thư mục uploads nằm ngang hàng với admin/

            $image_file_type = strtolower(pathinfo($_FILES["avatar_file"]["name"], PATHINFO_EXTENSION));
            // Tạo tên file duy nhất: ma_tk + _ + thời gian + . + extension
            $unique_file_name = $ma_tk . '_' . time() . "." . $image_file_type;
            $target_file = $target_dir . $unique_file_name;
            $upload_ok = 1;

            // Kiểm tra loại file
            $allowed_types = array('jpg', 'jpeg', 'png', 'gif');
            if (!in_array($image_file_type, $allowed_types)) {
                $error = "Chỉ cho phép file ảnh JPG, JPEG, PNG & GIF.";
                $upload_ok = 0;
            }

            // Kiểm tra kích thước file (ví dụ: không quá 5MB)
            if ($_FILES["avatar_file"]["size"] > 5000000) {
                $error = "Kích thước file ảnh quá lớn. Tối đa 5MB.";
                $upload_ok = 0;
            }

            if ($upload_ok == 1) {
                if (move_uploaded_file($_FILES["avatar_file"]["tmp_name"], $target_file)) {
                    // Xóa ảnh cũ nếu nó không phải là ảnh mặc định và không phải ảnh mới tải lên
                    if ($current_img_path != 'uploads/default_avatar.png' && file_exists('../' . $current_img_path)) {
                        unlink('../' . $current_img_path);
                    }
                    $new_img_path_for_db = 'uploads/' . $unique_file_name;
                } else {
                    $error = "Có lỗi khi tải ảnh lên. Vui lòng thử lại.";
                }
            }
        } else if (isset($_POST['remove_avatar']) && $_POST['remove_avatar'] == '1') {
            // Xử lý khi người dùng chọn xóa ảnh
            if ($current_img_path != 'uploads/default_avatar.png' && file_exists('../' . $current_img_path)) {
                unlink('../' . $current_img_path); // Xóa file ảnh cũ
            }
            $new_img_path_for_db = 'uploads/default_avatar.png'; // Đặt lại ảnh mặc định
        }

        // Nếu không có lỗi từ phần upload hoặc các validate khác
        if (empty($error)) {
            // Kiểm tra trùng email (trừ email của chính tài khoản đang sửa)
            $check_email_stmt = $conn->prepare("SELECT ma_tk FROM taikhoan WHERE email = ? AND ma_tk != ?");
            $check_email_stmt->bind_param("si", $email_new, $ma_tk);
            $check_email_stmt->execute();
            $check_email_result = $check_email_stmt->get_result();

            if ($check_email_result->num_rows > 0) {
                $error = "Email đã tồn tại cho một tài khoản khác.";
            } else {
                // Chuẩn bị câu lệnh UPDATE
                $sql_update = "UPDATE taikhoan SET email = ?, ten_taikhoan = ?, gioitinh = ?, ngaysinh = ?, sdt = ?, vaitro = ?, ma_khoa = ?, img = ?";
                $types_update = "sssssiis"; // ban đầu cho các trường cơ bản

                $params_update = [
                    $email_new,
                    $ten_taikhoan_new,
                    $gioitinh_new,
                    $ngaysinh_new,
                    $sdt_new,
                    $vaitro_new,
                    $ma_khoa_new,
                    $new_img_path_for_db
                ];

                // Nếu có mật khẩu mới, thêm vào câu lệnh UPDATE và types
                if (!empty($password_raw_new)) {
                    $matkhau_hashed_new = md5($password_raw_new);
                    $sql_update .= ", matkhau = ?";
                    $types_update .= "s";
                    $params_update[] = $matkhau_hashed_new;
                }

                $sql_update .= " WHERE ma_tk = ?";
                $types_update .= "i"; // Thêm kiểu cho ma_tk
                $params_update[] = $ma_tk; // Thêm ma_tk vào cuối params

                $stmt_update = $conn->prepare($sql_update);

                // Gán tham số cho câu lệnh prepared statement
                $stmt_update->bind_param($types_update, ...$params_update);

                if ($stmt_update->execute()) {
                    $success = "Cập nhật tài khoản **" . htmlspecialchars($ten_taikhoan_new) . "** thành công!";
                    // Cập nhật lại đường dẫn ảnh hiện tại sau khi lưu thành công
                    $current_img_path = $new_img_path_for_db;
                } else {
                    $error = "Lỗi khi cập nhật tài khoản: " . $conn->error;
                }
                $stmt_update->close();
            }
            $check_email_stmt->close();
        }
    }
}

// Lấy thông báo từ session (nếu có, ví dụ từ redirect)
if (isset($_SESSION['message'])) {
    $success = $_SESSION['message'];
    unset($_SESSION['message']);
}
if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

$conn->close(); // Đóng kết nối CSDL
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Sửa Tài khoản</title>
    <link rel="stylesheet" href="css/mainadd.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <style>
        .profile-img-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #ddd;
            margin-bottom: 10px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }
        .remove-avatar-checkbox {
            margin-top: 5px;
            margin-bottom: 15px;
            text-align: center;
        }
    </style>
</head>
<body>
    <?php
    include '../header.php';
    include 'menubar.php';
    ?>

    <main>
        <div class="form-container">
            <br><br><br> <h2>Sửa Tài khoản (Mã: <?php echo htmlspecialchars($ma_tk); ?>)</h2>

            <?php if ($success) echo "<div class='alert success'>$success</div>"; ?>
            <?php if ($error) echo "<div class='alert error'>$error</div>"; ?>

            <form method="POST" enctype="multipart/form-data">
                <label for="ten_taikhoan">Tên Tài khoản:</label>
                <input type="text" id="ten_taikhoan" name="ten_taikhoan" value="<?php echo $ten_taikhoan; ?>" required placeholder="Nhập tên tài khoản">

                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo $email; ?>" required placeholder="VD: example@ctu.edu.vn">

                <label for="password">Mật khẩu mới:</label>
                <input type="password" id="password" name="password" placeholder="Tối thiểu 6 ký tự (để trống nếu không đổi)">

                <label for="gioitinh">Giới tính:</label>
                <select id="gioitinh" name="gioitinh" title="Chọn giới tính của tài khoản">
                    <option value="">-- Chọn giới tính --</option>
                    <option value="Nam" <?php echo ($gioitinh == 'Nam') ? 'selected' : ''; ?>>Nam</option>
                    <option value="Nữ" <?php echo ($gioitinh == 'Nữ') ? 'selected' : ''; ?>>Nữ</option>
                    <option value="Khác" <?php echo ($gioitinh == 'Khác') ? 'selected' : ''; ?>>Khác</option>
                </select>

                <label for="sdt">Số điện thoại:</label>
                <input type="text" id="sdt" name="sdt" value="<?php echo $sdt; ?>" pattern="\d{10,11}" placeholder="VD: 0123456789 (10 hoặc 11 chữ số)">

                <label for="ngaysinh">Ngày sinh:</label>
                <input type="date" id="ngaysinh" name="ngaysinh" value="<?php echo $ngaysinh; ?>">

                <label for="vaitro">Vai trò:</label>
                <select id="vaitro" name="vaitro" title="Chọn vai trò của tài khoản">
                    <option value="">-- Chọn vai trò --</option>
                    <option value="0" <?php echo ($vaitro === '0') ? 'selected' : ''; ?>>Admin</option>
                    <option value="1" <?php echo ($vaitro === '1') ? 'selected' : ''; ?>>Sinh viên</option>
                    <option value="2" <?php echo ($vaitro === '2') ? 'selected' : ''; ?>>Giảng viên</option>
                </select>

                <label for="ma_khoa">Khoa:</label>
                <select id="ma_khoa" name="ma_khoa" title="Bắt buộc chọn nếu vai trò là Sinh viên hoặc Giảng viên">
                    <option value="">-- Chọn khoa (Chỉ cho Sinh viên/Giảng viên) --</option>
                    <?php foreach ($khoa_options as $khoa) : ?>
                        <option value="<?php echo htmlspecialchars($khoa['ma_khoa']); ?>"
                            <?php echo ($ma_khoa !== NULL && $ma_khoa == $khoa['ma_khoa']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($khoa['ten_khoa']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="avatar_file">Ảnh đại diện:</label>
                <?php
                    // Hiển thị ảnh hiện tại
                    $display_img_path = '../' . $current_img_path;
                    if (!file_exists($display_img_path) || empty($current_img_path)) {
                        $display_img_path = '../uploads/default_avatar.png';
                    }
                ?>
                <img src="<?php echo $display_img_path; ?>" alt="Ảnh đại diện" class="profile-img-preview">
                
                <input type="file" name="avatar_file" id="avatar_file" accept="image/*" title="Chọn ảnh đại diện mới (JPG, PNG, GIF, tối đa 5MB)">
                
                <div class="remove-avatar-checkbox">
                    <input type="checkbox" id="remove_avatar" name="remove_avatar" value="1">
                    <label for="remove_avatar">Xóa ảnh hiện tại (đặt lại ảnh mặc định)</label>
                </div>

                <button type="submit"><i class="fas fa-save"></i> Cập nhật Tài khoản</button>
                <a href="/dkhp/admin/taikhoan.php" class="btn-back"><i class="fas fa-arrow-left"></i> Quay lại Danh sách</a>
            </form>
        </div>
    </main>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            // JS để đóng thông báo
            document.querySelectorAll('.alert').forEach(alertDiv => {
                if (alertDiv.innerHTML.trim() !== "") {
                    setTimeout(() => {
                        alertDiv.style.display = 'none';
                    }, 5000); // Ẩn sau 5 giây
                }
            });

            // Tùy chỉnh nhỏ: Bật/tắt trường 'Khoa' dựa trên 'Vai trò'
            const vaitroSelect = document.getElementById('vaitro');
            const maKhoaSelect = document.getElementById('ma_khoa');

            function toggleMaKhoaField() {
                const selectedRole = vaitroSelect.value;
                if (selectedRole === '1' || selectedRole === '2') { // Sinh viên hoặc Giảng viên
                    maKhoaSelect.disabled = false;
                    maKhoaSelect.style.backgroundColor = '#fff';
                } else {
                    maKhoaSelect.disabled = true;
                    maKhoaSelect.value = ''; // Xóa giá trị đã chọn nếu không cần thiết
                    maKhoaSelect.style.backgroundColor = '#e9ecef';
                }
            }

            vaitroSelect.addEventListener('change', toggleMaKhoaField);
            toggleMaKhoaField(); // Gọi lần đầu để thiết lập trạng thái ban đầu khi trang tải

            // Ngăn người dùng vừa chọn file mới vừa chọn xóa ảnh
            const avatarFileInput = document.getElementById('avatar_file');
            const removeAvatarCheckbox = document.getElementById('remove_avatar');

            if (avatarFileInput && removeAvatarCheckbox) {
                avatarFileInput.addEventListener('change', () => {
                    if (avatarFileInput.files.length > 0) {
                        removeAvatarCheckbox.checked = false;
                        removeAvatarCheckbox.disabled = true; // Vô hiệu hóa checkbox xóa ảnh
                    } else {
                        removeAvatarCheckbox.disabled = false;
                    }
                });

                removeAvatarCheckbox.addEventListener('change', () => {
                    if (removeAvatarCheckbox.checked) {
                        avatarFileInput.value = ''; // Xóa lựa chọn file nếu checkbox được check
                        avatarFileInput.disabled = true; // Vô hiệu hóa input file
                    } else {
                        avatarFileInput.disabled = false;
                    }
                });
            }
        });
    </script>
</body>
</html>