<?php
// Luôn bắt đầu session ở đầu file
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../conn.php';
include '../function.php';

check_login();   // Đảm bảo người dùng đã đăng nhập
check_admin();
$base_url = '/dkhp/';

$id_lop_hp = isset($_GET['id']) ? intval($_GET['id']) : 0;
$lop_hp_data = null; // Biến để lưu dữ liệu lớp học phần hiện tại
$error = '';
$success = '';

// --- Lấy dữ liệu cho các dropdown (Môn học, Giáo viên, Học kỳ, Phòng học) ---
// Môn học
$mon_options = [];
$sql_mon = "SELECT id_mon, ten_mon, so_tin_chi, gia_tin_chi FROM mon ORDER BY ten_mon ASC";
$result_mon = $conn->query($sql_mon);
if ($result_mon && $result_mon->num_rows > 0) {
    while ($row = $result_mon->fetch_assoc()) {
        $mon_options[] = $row;
    }
}

// Giáo viên (Tài khoản có vai trò 'GV' - Giá trị là 2 trong CSDL)
$gv_options = [];
$sql_gv = "SELECT ma_tk, ten_taikhoan FROM taikhoan WHERE vaitro = 2 ORDER BY ten_taikhoan ASC";
$result_gv = $conn->query($sql_gv);
if ($result_gv && $result_gv->num_rows > 0) {
    while ($row = $result_gv->fetch_assoc()) {
        $gv_options[] = $row;
    }
}

// Học kỳ
$hocki_options = [];
$sql_hocki = "SELECT id_hocki, ten_hocki, nam_hoc FROM hocki ORDER BY nam_hoc DESC, ten_hocki ASC";
$result_hocki = $conn->query($sql_hocki);
if ($result_hocki && $result_hocki->num_rows > 0) {
    while ($row = $result_hocki->fetch_assoc()) {
        $hocki_options[] = $row;
    }
}

// Phòng học
$phong_options = [];
$sql_phong = "SELECT id_phong, ten_phong FROM phong_hoc ORDER BY ten_phong ASC";
$result_phong = $conn->query($sql_phong);
if ($result_phong && $result_phong->num_rows > 0) {
    while ($row = $result_phong->fetch_assoc()) {
        $phong_options[] = $row;
    }
}


// --- Xử lý POST request (Cập nhật thông tin lớp học phần) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_lop_hp_post = intval($_POST['id_lop_hp'] ?? 0); // Lấy lại ID lớp học phần từ hidden field
    $ten_lop_hocphan_moi = trim($_POST['ten_lop_hocphan'] ?? '');
    $id_mon_moi = intval($_POST['id_mon'] ?? 0);
    $id_taikhoan_moi = intval($_POST['id_taikhoan'] ?? 0);
    $id_hocki_moi = intval($_POST['id_hocki'] ?? 0);
    $si_so_toi_da_moi = intval($_POST['si_so_toi_da'] ?? 0);
    $id_phong_moi = intval($_POST['id_phong'] ?? 0);
    $trangthai_moi = intval($_POST['trangthai'] ?? 0); // Lấy giá trị trạng thái mới (Tên cột: trangthai)

    // Gán lại dữ liệu đã nhập vào $lop_hp_data để hiển thị lại form nếu có lỗi
    $lop_hp_data = [
        'id_lop_hp' => $id_lop_hp_post,
        'ten_lop_hocphan' => $ten_lop_hocphan_moi,
        'id_mon' => $id_mon_moi,
        'id_taikhoan' => $id_taikhoan_moi,
        'id_hocki' => $id_hocki_moi,
        'si_so_toi_da' => $si_so_toi_da_moi,
        'id_phong' => $id_phong_moi,
        'trangthai' => $trangthai_moi // Cập nhật tên key: trangthai
    ];

    // Validate input data
    if ($id_lop_hp_post === 0 || empty($ten_lop_hocphan_moi) || $id_mon_moi === 0 || $id_taikhoan_moi === 0 || $id_hocki_moi === 0 || $si_so_toi_da_moi <= 0 || $id_phong_moi === 0) {
        $_SESSION['message'] = "Vui lòng điền đầy đủ và chính xác tất cả các trường.";
        $_SESSION['message_type'] = "error";
    } else {
        // Kiểm tra trùng tên lớp học phần trong cùng một học kỳ
        $check_duplicate_stmt = $conn->prepare("SELECT id_lop_hp FROM lop_hocphan WHERE ten_lop_hocphan = ? AND id_hocki = ? AND id_lop_hp <> ?");
        if ($check_duplicate_stmt === false) {
            $_SESSION['message'] = "Lỗi chuẩn bị truy vấn kiểm tra trùng lặp: " . $conn->error;
            $_SESSION['message_type'] = "error";
        } else {
            $check_duplicate_stmt->bind_param("sii", $ten_lop_hocphan_moi, $id_hocki_moi, $id_lop_hp_post);
            $check_duplicate_stmt->execute();
            $duplicate_result = $check_duplicate_stmt->get_result();

            if ($duplicate_result->num_rows > 0) {
                $_SESSION['message'] = "Tên lớp học phần **" . htmlspecialchars($ten_lop_hocphan_moi) . "** đã tồn tại trong học kỳ này.";
                $_SESSION['message_type'] = "error";
            } else {
                // Chuẩn bị câu lệnh UPDATE
                $stmt_update = $conn->prepare("UPDATE lop_hocphan SET
                                                    ten_lop_hocphan = ?,
                                                    id_mon = ?,
                                                    id_taikhoan = ?,
                                                    id_hocki = ?,
                                                    si_so_toi_da = ?,
                                                    id_phong = ?,
                                                    trangthai = ? -- ĐỔI TÊN CỘT Ở ĐÂY
                                                WHERE id_lop_hp = ?");

                if ($stmt_update === false) {
                    $_SESSION['message'] = "Lỗi chuẩn bị truy vấn cập nhật: " . $conn->error;
                    $_SESSION['message_type'] = "error";
                } else {
                    // Thêm 'i' vào param types cho trangthai_moi
                    $stmt_update->bind_param("siiiiiii",
                        $ten_lop_hocphan_moi,
                        $id_mon_moi,
                        $id_taikhoan_moi,
                        $id_hocki_moi,
                        $si_so_toi_da_moi,
                        $id_phong_moi,
                        $trangthai_moi, // Tham số trạng thái
                        $id_lop_hp_post
                    );

                    if ($stmt_update->execute()) {
                        $_SESSION['message'] = "Cập nhật lớp học phần **" . htmlspecialchars($ten_lop_hocphan_moi) . "** thành công!";
                        $_SESSION['message_type'] = "success";
                        header("Location: lophocphan.php"); // Chuyển hướng về trang danh sách
                        exit();
                    } else {
                        $_SESSION['message'] = "Lỗi khi cập nhật lớp học phần: " . $stmt_update->error;
                        $_SESSION['message_type'] = "error";
                    }
                    $stmt_update->close();
                }
            }
            $check_duplicate_stmt->close();
        }
    }
    // Gán lại ID để tải dữ liệu nếu có lỗi POST, đảm bảo form vẫn hiển thị dữ liệu chính xác
    $id_lop_hp = $id_lop_hp_post;
}

// --- Logic Lấy dữ liệu lớp học phần để hiển thị lên form (GET request hoặc sau lỗi POST) ---
// Chỉ lấy dữ liệu nếu biến $lop_hp_data chưa được set (tức là không phải từ lỗi POST)
if ($lop_hp_data === null && $id_lop_hp > 0) {
    $stmt_select = $conn->prepare("SELECT
                                    id_lop_hp,
                                    ten_lop_hocphan,
                                    id_mon,
                                    id_taikhoan,
                                    id_hocki,
                                    si_so_toi_da,
                                    id_phong,
                                    trangthai -- ĐỔI TÊN CỘT Ở ĐÂY
                                FROM
                                    lop_hocphan
                                WHERE id_lop_hp = ?");
    if ($stmt_select === false) {
        $error = "Lỗi chuẩn bị truy vấn lấy dữ liệu lớp học phần: " . $conn->error;
    } else {
        $stmt_select->bind_param("i", $id_lop_hp);
        $stmt_select->execute();
        $result = $stmt_select->get_result();
        if ($result->num_rows > 0) {
            $lop_hp_data = $result->fetch_assoc();
        } else {
            $error = "Không tìm thấy lớp học phần với ID: " . htmlspecialchars($id_lop_hp);
            $id_lop_hp = 0; // Đặt về 0 để tránh hiển thị form rỗng nếu không tìm thấy
        }
        $stmt_select->close();
    }
} elseif ($id_lop_hp === 0) {
    $error = "Không có ID lớp học phần được cung cấp để chỉnh sửa.";
}

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

$conn->close(); // Đóng kết nối CSDL
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chỉnh sửa Lớp Học Phần</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>style.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>admin/css/menubar.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>admin/css/mainedit.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
</head>
<body>
    <?php
    include '../header.php';
    include 'menubar.php';
    ?>

    <main>
        <div class="form-container">
            <br><br><br>
            <h2>Chỉnh sửa Lớp Học Phần</h2>

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

            <?php if ($lop_hp_data) : // Chỉ hiển thị form nếu tìm thấy dữ liệu lớp học phần ?>
                <form method="POST">
                    <input type="hidden" name="id_lop_hp" value="<?php echo htmlspecialchars($lop_hp_data['id_lop_hp']); ?>">

                    <label for="ten_lop_hocphan">Tên Lớp Học Phần:</label>
                    <input type="text" id="ten_lop_hocphan" name="ten_lop_hocphan" value="<?php echo htmlspecialchars($lop_hp_data['ten_lop_hocphan']); ?>" required>

                    <label for="id_mon">Môn học:</label>
                    <select id="id_mon" name="id_mon" required>
                        <option value="">-- Chọn Môn học --</option>
                        <?php foreach ($mon_options as $mon) : ?>
                            <option value="<?php echo htmlspecialchars($mon['id_mon']); ?>"
                                <?php echo ($lop_hp_data['id_mon'] == $mon['id_mon']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($mon['ten_mon'] . ' (' . $mon['so_tin_chi'] . ' TC - ' . number_format($mon['gia_tin_chi'], 0, ',', '.') . 'đ/TC)'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="id_taikhoan">Giáo viên:</label>
                    <select id="id_taikhoan" name="id_taikhoan" required>
                        <option value="">-- Chọn Giáo viên --</option>
                        <?php foreach ($gv_options as $gv) : ?>
                            <option value="<?php echo htmlspecialchars($gv['ma_tk']); ?>"
                                <?php echo ($lop_hp_data['id_taikhoan'] == $gv['ma_tk']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($gv['ten_taikhoan']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="id_hocki">Học kỳ:</label>
                    <select id="id_hocki" name="id_hocki" required>
                        <option value="">-- Chọn Học kỳ --</option>
                        <?php foreach ($hocki_options as $hocki) : ?>
                            <option value="<?php echo htmlspecialchars($hocki['id_hocki']); ?>"
                                <?php echo ($lop_hp_data['id_hocki'] == $hocki['id_hocki']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($hocki['ten_hocki'] . ' - ' . $hocki['nam_hoc']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="id_phong">Phòng học:</label>
                    <select id="id_phong" name="id_phong" required>
                        <option value="">-- Chọn Phòng học --</option>
                        <?php foreach ($phong_options as $phong) : ?>
                            <option value="<?php echo htmlspecialchars($phong['id_phong']); ?>"
                                <?php echo ($lop_hp_data['id_phong'] == $phong['id_phong']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($phong['ten_phong']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="si_so_toi_da">Sĩ số tối đa:</label>
                    <input type="number" id="si_so_toi_da" name="si_so_toi_da" value="<?php echo htmlspecialchars($lop_hp_data['si_so_toi_da']); ?>" min="1" required>

                        <label for="trangthai">Trạng thái:</label>
                        <select id="trangthai" name="trangthai" required>
                            <option value="0" <?php echo (isset($lhp_data['trangthai']) && $lhp_data['trangthai'] == 0) ? 'selected' : ''; ?>>Hiện</option>
                            <option value="1" <?php echo (isset($lhp_data['trangthai']) && $lhp_data['trangthai'] == 1) ? 'selected' : ''; ?>>Ẩn</option>
                            <option value="2" <?php echo (isset($lhp_data['trangthai']) && $lhp_data['trangthai'] == 2) ? 'selected' : ''; ?>>Đã chốt lớp</option>
                        </select>

                    <button type="submit"><i class="fas fa-save"></i> Cập nhật Lớp HP</button>
                    <a href="/dkhp/admin/lophocphan.php" class="btn-back"><i class="fas fa-arrow-left"></i> Quay lại Danh sách</a>
                </form>
            <?php else : ?>
                <p>Không thể tải thông tin lớp học phần hoặc ID lớp học phần không hợp lệ.</p>
                <a href="/dkhp/admin/lophocphan.php" class="btn-back"><i class="fas fa-arrow-left"></i> Quay lại Danh sách</a>
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