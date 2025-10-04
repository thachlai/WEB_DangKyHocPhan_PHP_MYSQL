<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../conn.php';
include '../function.php';

check_login();   // Đảm bảo người dùng đã đăng nhập
check_admin();
include '../header.php';
include 'menubar.php';

$success = '';
$error = '';

// Initialize form variables to keep values after submission if there's an error
$ten_lop_hocphan = '';
$id_mon = '';
$id_taikhoan = ''; // Teacher's ID
$id_hocki = '';
$si_so_toi_da = '';
$id_phong = ''; // Variable to store selected room ID

// Fetch data for dropdowns (Môn học, Giáo viên, Học kỳ, Phòng học)
$mon_options = [];
$gv_options = [];
$hocki_options = [];
$phong_options = [];

// Fetch Subjects (id_mon, ten_mon)
$sql_mon = "SELECT id_mon, ten_mon FROM mon ORDER BY ten_mon";
$result_mon = $conn->query($sql_mon);
if ($result_mon && $result_mon->num_rows > 0) {
    while ($row = $result_mon->fetch_assoc()) {
        $mon_options[] = $row;
    }
}

// Fetch Teachers (Vai Tro = 2) (ma_tk, ten_taikhoan)
$sql_gv = "SELECT ma_tk, ten_taikhoan AS ho_ten FROM taikhoan WHERE vaitro = 2 ORDER BY ten_taikhoan";
$result_gv = $conn->query($sql_gv);
if ($result_gv && $result_gv->num_rows > 0) {
    while ($row = $result_gv->fetch_assoc()) {
        $gv_options[] = $row;
    }
} else {
    // Only set error if no teachers are found, but don't stop execution
    if (empty($gv_options)) {
        $error = "Không tìm thấy giáo viên nào trong hệ thống. Vui lòng thêm tài khoản giáo viên với vai trò 'giangvien' (vaitro = 2).";
    }
}

// Fetch Semesters (id_hocki, ten_hocki, nam_hoc)
$sql_hocki = "SELECT id_hocki, ten_hocki, nam_hoc FROM hocki ORDER BY nam_hoc DESC, id_hocki DESC";
$result_hocki = $conn->query($sql_hocki);
if ($result_hocki && $result_hocki->num_rows > 0) {
    while ($row = $result_hocki->fetch_assoc()) {
        $hocki_options[] = $row;
    }
} else {
    // Only set error if no semesters are found
    if (empty($hocki_options)) {
        $error = "Không tìm thấy học kỳ nào trong hệ thống. Vui lòng thêm học kỳ.";
    }
}

// Fetch Rooms (id_phong, ten_phong from phong_hoc)
$sql_phong = "SELECT id_phong, ten_phong FROM phong_hoc ORDER BY ten_phong";
$result_phong = $conn->query($sql_phong);
if ($result_phong && $result_phong->num_rows > 0) {
    while ($row = $result_phong->fetch_assoc()) {
        $phong_options[] = $row;
    }
} else {
    // Only set error if no rooms are found
    if (empty($phong_options)) {
        $error = "Không tìm thấy phòng học nào trong hệ thống. Vui lòng thêm phòng học.";
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get data from the form
    $ten_lop_hocphan = trim($_POST['ten_lop_hocphan'] ?? '');
    $id_mon = $_POST['id_mon'] ?? '';
    $id_taikhoan = $_POST['id_taikhoan'] ?? '';
    $id_hocki = $_POST['id_hocki'] ?? '';
    $si_so_toi_da = $_POST['si_so_toi_da'] ?? '';
    $id_phong = $_POST['id_phong'] ?? '';

    // --- Input data validation ---
    if (empty($ten_lop_hocphan) || empty($id_mon) || empty($id_taikhoan) || empty($id_hocki) || empty($id_phong)) {
        $error = "Vui lòng điền đầy đủ các thông tin bắt buộc (Tên Lớp HP, Môn học, Giáo viên, Học kỳ, Phòng học).";
    } elseif (!is_numeric($si_so_toi_da) || $si_so_toi_da <= 0) {
        $error = "Sĩ số tối đa phải là một số nguyên dương.";
        $si_so_toi_da = ''; // Clear invalid input
    } else {
        // Check for duplicate lop_hocphan name for the same subject and semester
        $check_stmt = $conn->prepare("SELECT id_lop_hp FROM lop_hocphan WHERE ten_lop_hocphan = ? AND id_mon = ? AND id_hocki = ?");
        if ($check_stmt === false) {
            $error = "Lỗi chuẩn bị truy vấn kiểm tra trùng lặp: " . $conn->error;
        } else {
            $check_stmt->bind_param("sii", $ten_lop_hocphan, $id_mon, $id_hocki);
            $check_stmt->execute();
            $result = $check_stmt->get_result();

            if ($result->num_rows > 0) {
                $error = "Lớp học phần **" . htmlspecialchars($ten_lop_hocphan) . "** đã tồn tại cho môn này trong học kỳ này.";
            } else {
                // Prepare INSERT statement for `lop_hocphan`
                // Columns: ten_lop_hocphan, id_mon, id_taikhoan, id_hocki, si_so_toi_da, id_phong
                $stmt = $conn->prepare("INSERT INTO lop_hocphan (ten_lop_hocphan, id_mon, id_taikhoan, id_hocki, si_so_toi_da, id_phong) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt === false) {
                    $error = "Lỗi chuẩn bị truy vấn thêm lớp học phần: " . $conn->error;
                } else {
                    // Corrected bind_param: ten_lop_hocphan (s), id_mon (i), id_taikhoan (i), id_hocki (i), si_so_toi_da (i), id_phong (i)
                    $stmt->bind_param("siiiii", $ten_lop_hocphan, $id_mon, $id_taikhoan, $id_hocki, $si_so_toi_da, $id_phong);

                    if ($stmt->execute()) {
                        // Store success message in session for display after redirect
                        $_SESSION['message'] = "Thêm lớp học phần **" . htmlspecialchars($ten_lop_hocphan) . "** thành công!";
                        $_SESSION['message_type'] = 'success';
                        
                        // Redirect to the list page or back to this page to clear POST data
                        header("Location: lophocphan.php"); // Redirect to the list of class sections
                        exit(); // Always exit after header redirect
                    } else {
                        $error = "Lỗi khi thêm lớp học phần: " . $stmt->error;
                    }
                    $stmt->close();
                }
            }
            $check_stmt->close();
        }
    }
}

// Retrieve message from session (if redirected from a successful submission)
// This section should be outside the POST block but before HTML output
if (isset($_SESSION['message'])) {
    $success = $_SESSION['message'];
    $message_type = $_SESSION['message_type'] ?? 'success';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thêm Lớp Học Phần Mới</title>
    <link rel="stylesheet" href="css/mainadd.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
</head>
<body>
    <main>
        <div class="form-container">
            <br><br><br>
            <h2>Thêm Lớp Học Phần Mới</h2>

            <?php if ($success) : ?>
                <div class="alert success">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            <?php if ($error) echo "<div class='alert error'>$error</div>"; ?>

            <form method="POST">
                <label for="ten_lop_hocphan">Tên Lớp Học Phần:</label>
                <input type="text" id="ten_lop_hocphan" name="ten_lop_hocphan" value="<?php echo htmlspecialchars($ten_lop_hocphan); ?>" required>

                <label for="id_mon">Môn học:</label>
                <select id="id_mon" name="id_mon" required>
                    <option value="">-- Chọn Môn học --</option>
                    <?php foreach ($mon_options as $mon) : ?>
                        <option value="<?php echo htmlspecialchars($mon['id_mon']); ?>"
                            <?php echo ($id_mon == $mon['id_mon']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($mon['ten_mon']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="id_taikhoan">Giáo viên phụ trách:</label>
                <select id="id_taikhoan" name="id_taikhoan" required>
                    <option value="">-- Chọn Giáo viên --</option>
                    <?php foreach ($gv_options as $gv) : ?>
                        <option value="<?php echo htmlspecialchars($gv['ma_tk']); ?>"
                            <?php echo ($id_taikhoan == $gv['ma_tk']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($gv['ho_ten']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="id_hocki">Học kỳ:</label>
                <select id="id_hocki" name="id_hocki" required>
                    <option value="">-- Chọn Học kỳ --</option>
                    <?php foreach ($hocki_options as $hocki) : ?>
                        <option value="<?php echo htmlspecialchars($hocki['id_hocki']); ?>"
                            <?php echo ($id_hocki == $hocki['id_hocki']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($hocki['ten_hocki'] . ' - ' . $hocki['nam_hoc']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <label for="si_so_toi_da">Sĩ số tối đa:</label>
                <input type="number" id="si_so_toi_da" name="si_so_toi_da" value="<?php echo htmlspecialchars($si_so_toi_da); ?>" min="1">

                <label for="id_phong">Phòng học:</label>
                <select id="id_phong" name="id_phong" required>
                    <option value="">-- Chọn Phòng học --</option>
                    <?php foreach ($phong_options as $phong) : ?>
                        <option value="<?php echo htmlspecialchars($phong['id_phong']); ?>"
                            <?php echo ($id_phong == $phong['id_phong']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($phong['ten_phong']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit"><i class="fas fa-plus"></i> Thêm Lớp Học Phần</button>
                <a href="/dkhp/admin/lophocphan.php" class="btn-back"><i class="fas fa-arrow-left"></i> Quay lại Danh sách</a>
            </form>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.alert').forEach(alertDiv => {
                if (alertDiv.innerHTML.trim() !== "") {
                    setTimeout(() => {
                        alertDiv.style.display = 'none';
                    }, 5000); // Hide after 5 seconds
                }
            });
        });
    </script>
</body>
</html>