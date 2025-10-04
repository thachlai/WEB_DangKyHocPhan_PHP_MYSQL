<?php
// PHP_SESSION_NONE kiểm tra nếu session chưa được bắt đầu
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Đường dẫn tương đối từ admin/ ra thư mục gốc dkhp/
include '../conn.php';
include '../function.php';

// Gọi hàm kiểm tra quyền Admin
check_login();   // Đảm bảo người dùng đã đăng nhập
check_admin();
// Include header và menubar
include '../header.php'; // dkhp/header.php
include 'menubar.php';   // dkhp/admin/menubar.php

$success = '';
$error = '';

// Khởi tạo biến để giữ giá trị form sau khi submit thất bại hoặc lần đầu load trang
$email = '';
$ten_taikhoan = ''; 
$gioitinh = '';     
$ngaysinh = '';     
$sdt = '';          
$vaitro = '';       
$ma_khoa = '';     
$img_path_for_db = 'uploads/default_avatar.png'; // Mặc định nếu không có ảnh tải lên

// Lấy danh sách khoa từ CSDL (để đổ vào dropdown select)
$khoa_options = [];
$sql_khoa = "SELECT ma_khoa, ten_khoa FROM khoa ORDER BY ten_khoa";
$result_khoa = $conn->query($sql_khoa);
if ($result_khoa && $result_khoa->num_rows > 0) {
    while ($row = $result_khoa->fetch_assoc()) {
        $khoa_options[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lấy dữ liệu từ form và gán lại vào biến để hiển thị trên form nếu có lỗi
    $ten_taikhoan = trim($_POST['ten_taikhoan'] ?? ''); 
    $email = trim($_POST['email'] ?? '');
    $password_raw = trim($_POST['password'] ?? ''); 
    $gioitinh = $_POST['gioitinh'] ?? ''; 
    $sdt = trim($_POST['sdt'] ?? ''); 
    $ngaysinh = $_POST['ngaysinh'] ?? ''; 
    $vaitro = $_POST['vaitro'] ?? ''; 
    $ma_khoa = $_POST['ma_khoa'] ?? NULL; 

    // Mã hóa mật khẩu MD5
    $matkhau_hashed = md5($password_raw);

    // --- Validate dữ liệu đầu vào ---
    if (empty($email) || empty($password_raw) || empty($ten_taikhoan) || $vaitro === '') {
        $error = "Vui lòng điền đầy đủ các trường bắt buộc (Email, Mật khẩu, Tên tài khoản, Vai trò).";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email không hợp lệ. Vui lòng nhập đúng định dạng email.";
    } elseif (strlen($password_raw) < 6) { 
        $error = "Mật khẩu phải có ít nhất 6 ký tự.";
    } elseif (!empty($sdt) && !preg_match("/^[0-9]{10,11}$/", $sdt)) { // Kiểm tra số điện thoại (10 hoặc 11 chữ số)
        $error = "Số điện thoại không hợp lệ (chỉ chấp nhận 10 hoặc 11 chữ số).";
    }
    // Kiểm tra ma_khoa nếu vaitro là Sinh viên (1) hoặc Giảng viên (2)
    elseif (($vaitro == '1' || $vaitro == '2') && empty($ma_khoa)) {
        $error = "Vui lòng chọn khoa cho Sinh viên hoặc Giảng viên.";
    }
    else {
        // --- Xử lý tải lên ảnh ---
        if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] == 0) {
            $target_dir = "../uploads/"; // Thư mục uploads nằm ngang hàng với dkhp/admin/
                                       // Nên phải lùi lại 1 cấp: ../uploads/

            $image_file_type = strtolower(pathinfo($_FILES["avatar_file"]["name"], PATHINFO_EXTENSION));
            // Tạo tên file duy nhất: mã tài khoản + _ + unique_id + . + extension
            // ma_tk chưa có, nên dùng thời gian + random string
            $unique_file_name = time() . '_' . uniqid() . "." . $image_file_type; 
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
                if (file_exists($target_file)) { // Tránh trường hợp trùng tên (rất hiếm với uniqid())
                     $error = "Tên file ảnh đã tồn tại. Vui lòng thử lại.";
                     $upload_ok = 0;
                } elseif (move_uploaded_file($_FILES["avatar_file"]["tmp_name"], $target_file)) {
                    // Lưu đường dẫn tương đối từ gốc dự án vào CSDL
                    $img_path_for_db = 'uploads/' . $unique_file_name; 
                } else {
                    $error = "Có lỗi khi tải ảnh lên. Vui lòng thử lại.";
                }
            }
        } // End of file upload handling

        // Nếu không có lỗi từ phần upload hoặc các validate khác
        if (empty($error)) {
            // Kiểm tra trùng email trong bảng `taikhoan`
            $check = $conn->prepare("SELECT ma_tk FROM taikhoan WHERE email = ?"); 
            $check->bind_param("s", $email);
            $check->execute();
            $result = $check->get_result();

            if ($result->num_rows > 0) {
                $error = "Email đã tồn tại.";
            } else {
                // Chuẩn bị câu lệnh INSERT cho bảng `taikhoan` (có thêm cột img)
                $stmt = $conn->prepare("INSERT INTO taikhoan (email, matkhau, ten_taikhoan, gioitinh, ngaysinh, sdt, vaitro, ma_khoa, img)
                                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                // 'ssssssiis' vì gioitinh, ngaysinh, sdt có thể rỗng (string), vaitro, ma_khoa là int, img là string
                $stmt->bind_param("ssssssiis", 
                                  $email, 
                                  $matkhau_hashed, 
                                  $ten_taikhoan, 
                                  $gioitinh, 
                                  $ngaysinh, 
                                  $sdt, 
                                  $vaitro, 
                                  $ma_khoa,
                                  $img_path_for_db); // Thêm đường dẫn ảnh vào đây

                if ($stmt->execute()) {
                    $success = "Thêm tài khoản **" . htmlspecialchars($ten_taikhoan) . "** thành công!";
                    // Xóa dữ liệu form sau khi thêm thành công để người dùng thêm tài khoản mới
                    $email = $password_raw = $ten_taikhoan = $gioitinh = $ngaysinh = $sdt = '';
                    $vaitro = '';
                    $ma_khoa = NULL;
                    $img_path_for_db = 'uploads/default_avatar.png'; // Reset ảnh mặc định cho lần thêm tiếp theo
                } else {
                    $error = "Lỗi khi thêm tài khoản: " . $conn->error;
                }
                $stmt->close();
            }
            $check->close(); 
        }
    }
}
$conn->close(); // Đóng kết nối CSDL
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thêm Tài khoản Mới</title>
    <link rel="stylesheet" href="css/mainadd.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
</head>
<body>
    <main> 
        <div class="form-container">
            <br><br><br> <h2>Thêm Tài khoản Mới</h2>

            <?php if ($success) echo "<div class='alert success'>$success</div>"; ?>
            <?php if ($error) echo "<div class='alert error'>$error</div>"; ?>

            <form method="POST" enctype="multipart/form-data">
                <label for="ten_taikhoan">Tên Tài khoản:</label> 
                <input type="text" id="ten_taikhoan" name="ten_taikhoan" value="<?php echo htmlspecialchars($ten_taikhoan); ?>" required placeholder="Nhập tên tài khoản">

                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required placeholder="VD: example@ctu.edu.vn">

                <label for="password">Mật khẩu:</label>
                <input type="password" id="password" name="password" required placeholder="Tối thiểu 6 ký tự">

                <label for="gioitinh">Giới tính:</label> 
                <select id="gioitinh" name="gioitinh" title="Chọn giới tính của tài khoản">
                    <option value="">-- Chọn giới tính --</option>
                    <option value="Nam" <?php echo ($gioitinh == 'Nam') ? 'selected' : ''; ?>>Nam</option>
                    <option value="Nữ" <?php echo ($gioitinh == 'Nữ') ? 'selected' : ''; ?>>Nữ</option>
                    <option value="Khác" <?php echo ($gioitinh == 'Khác') ? 'selected' : ''; ?>>Khác</option>
                </select>

                <label for="sdt">Số điện thoại:</label> 
                <input type="text" id="sdt" name="sdt" value="<?php echo htmlspecialchars($sdt); ?>" pattern="\d{10,11}" placeholder="VD: 0123456789 (10 hoặc 11 chữ số)">

                <label for="ngaysinh">Ngày sinh:</label> 
                <input type="date" id="ngaysinh" name="ngaysinh" value="<?php echo htmlspecialchars($ngaysinh); ?>">

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
                <input type="file" name="avatar_file" id="avatar_file" accept="image/*" title="Chọn ảnh đại diện (JPG, PNG, GIF, tối đa 5MB)">

                <button type="submit"><i class="fas fa-plus"></i> Thêm Tài khoản</button>
                <a href="/dkhp/admin/taikhoan.php" class="btn-back"><i class="fas fa-arrow-left"></i> Quay lại Danh sách</a>
            </form>
        </div>
    </main>
    
    <script>
        // JS để đóng thông báo (không có trong code thầy bạn, nhưng tiện)
        document.querySelectorAll('.alert').forEach(alertDiv => {
            if (alertDiv.innerHTML.trim() !== "") { // Chỉ hiển thị nếu có nội dung
                setTimeout(() => {
                    alertDiv.style.display = 'none';
                }, 5000); // Ẩn sau 5 giây
            }
        });

        // Tùy chỉnh nhỏ: Bật/tắt trường 'Khoa' dựa trên 'Vai trò'
        document.addEventListener("DOMContentLoaded", () => {
            const vaitroSelect = document.getElementById('vaitro');
            const maKhoaSelect = document.getElementById('ma_khoa');
            // Đã bỏ maKhoaSmall vì không còn dùng thẻ <small>
            // const maKhoaSmall = maKhoaSelect.nextElementSibling;

            function toggleMaKhoaField() {
                const selectedRole = vaitroSelect.value;
                if (selectedRole === '1' || selectedRole === '2') { // Sinh viên hoặc Giảng viên
                    maKhoaSelect.disabled = false;
                    maKhoaSelect.style.backgroundColor = '#fff'; // Bật màu nền trắng
                    // if (maKhoaSmall) maKhoaSmall.style.display = 'block'; // Không cần nữa
                } else {
                    maKhoaSelect.disabled = true;
                    maKhoaSelect.value = ''; // Xóa giá trị đã chọn nếu không cần thiết
                    maKhoaSelect.style.backgroundColor = '#e9ecef'; // Tắt màu nền xám
                    // if (maKhoaSmall) maKhoaSmall.style.display = 'none'; // Không cần nữa
                }
            }

            vaitroSelect.addEventListener('change', toggleMaKhoaField);
            toggleMaKhoaField(); // Gọi lần đầu để thiết lập trạng thái ban đầu khi trang tải
        });
    </script>
</body>
</html>