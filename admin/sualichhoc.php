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
$id_lich_hoc_edit = isset($_GET['id']) ? intval($_GET['id']) : 0;
$id_lop_hp_parent = 0; // To redirect back to the correct lop_hocphan

// Khởi tạo biến để giữ giá trị form
$ngay_trong_tuan_edit = '';
$tiet_bat_dau_edit = '';
$tiet_ket_thuc_edit = '';
$ghi_chu_edit = '';
$lop_hp_info = null;

if ($id_lich_hoc_edit > 0) {
    // Fetch current lich_hoc data
    $stmt_fetch = $conn->prepare("SELECT id_lop_hp, ngay_trong_tuan, tiet_bat_dau, tiet_ket_thuc, ghi_chu FROM lich_hoc WHERE id_lich_hoc = ?");
    if ($stmt_fetch === false) {
        $error = "Lỗi chuẩn bị truy vấn lấy dữ liệu lịch học: " . $conn->error;
    } else {
        $stmt_fetch->bind_param("i", $id_lich_hoc_edit);
        $stmt_fetch->execute();
        $result_fetch = $stmt_fetch->get_result();
        if ($result_fetch->num_rows === 1) {
            $lich_data = $result_fetch->fetch_assoc();
            $id_lop_hp_parent = $lich_data['id_lop_hp'];
            $ngay_trong_tuan_edit = $lich_data['ngay_trong_tuan'];
            $tiet_bat_dau_edit = $lich_data['tiet_bat_dau'];
            $tiet_ket_thuc_edit = $lich_data['tiet_ket_thuc'];
            $ghi_chu_edit = $lich_data['ghi_chu'];

            // Fetch parent lop_hocphan info
            $stmt_lop_hp = $conn->prepare("SELECT ten_lop_hocphan, m.ten_mon FROM lop_hocphan lhp JOIN mon m ON lhp.id_mon = m.id_mon WHERE id_lop_hp = ?");
            if ($stmt_lop_hp) {
                $stmt_lop_hp->bind_param("i", $id_lop_hp_parent);
                $stmt_lop_hp->execute();
                $result_lop_hp = $stmt_lop_hp->get_result();
                if ($result_lop_hp->num_rows > 0) {
                    $lop_hp_info = $result_lop_hp->fetch_assoc();
                }
                $stmt_lop_hp->close();
            }
        } else {
            $error = "Không tìm thấy lịch học với ID này.";
            $id_lich_hoc_edit = 0; // Mark as invalid
        }
        $stmt_fetch->close();
    }
} else {
    $error = "Không có ID lịch học được cung cấp.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id_lich_hoc_edit > 0) {
    $id_lich_hoc_edit = intval($_POST['id_lich_hoc'] ?? '');
    $id_lop_hp_parent = intval($_POST['id_lop_hp_parent'] ?? '');
    $ngay_trong_tuan_edit = trim($_POST['ngay_trong_tuan'] ?? '');
    $tiet_bat_dau_edit = trim($_POST['tiet_bat_dau'] ?? '');
    $tiet_ket_thuc_edit = trim($_POST['tiet_ket_thuc'] ?? '');
    $ghi_chu_edit = trim($_POST['ghi_chu'] ?? '');

    if (empty($ngay_trong_tuan_edit) || !is_numeric($ngay_trong_tuan_edit) || $ngay_trong_tuan_edit < 1 || $ngay_trong_tuan_edit > 7 ||
        empty($tiet_bat_dau_edit) || !is_numeric($tiet_bat_dau_edit) || $tiet_bat_dau_edit < 1 ||
        empty($tiet_ket_thuc_edit) || !is_numeric($tiet_ket_thuc_edit) || $tiet_ket_thuc_edit < $tiet_bat_dau_edit) {
        $error = "Vui lòng điền đầy đủ và chính xác thông tin lịch học (Thứ trong tuần từ 1-7, Tiết bắt đầu/kết thúc hợp lệ).";
    } else {
        // Cập nhật thông tin lịch học
        $stmt_update = $conn->prepare("UPDATE lich_hoc SET ngay_trong_tuan = ?, tiet_bat_dau = ?, tiet_ket_thuc = ?, ghi_chu = ? WHERE id_lich_hoc = ?");
        if ($stmt_update === false) {
            $error = "Lỗi chuẩn bị truy vấn cập nhật: " . $conn->error;
        } else {
            $stmt_update->bind_param("iiiis", $ngay_trong_tuan_edit, $tiet_bat_dau_edit, $tiet_ket_thuc_edit, $ghi_chu_edit, $id_lich_hoc_edit);
            if ($stmt_update->execute()) {
                $_SESSION['message'] = "Cập nhật lịch học thành công!";
                $_SESSION['message_type'] = "success";
                // Redirect back to themlichhoc.php for the parent lop_hocphan
                header("Location: themlichhoc.php?id_lop_hp=" . $id_lop_hp_parent);
                exit();
            } else {
                $error = "Lỗi khi cập nhật lịch học: " . $stmt_update->error;
            }
            $stmt_update->close();
        }
    }
}
$conn->close();

// Function to convert day number to Vietnamese text
function getDayOfWeekText($dayNum) {
    $days = [
        1 => 'Thứ 2',
        2 => 'Thứ 3',
        3 => 'Thứ 4',
        4 => 'Thứ 5',
        5 => 'Thứ 6',
        6 => 'Thứ 7',
        7 => 'Chủ nhật'
    ];
    return $days[$dayNum] ?? 'Không xác định';
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Sửa Lịch Học</title>
    <link rel="stylesheet" href="css/mainadd.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
</head>
<body>
    <main>
        <div class="form-container">
            <br><br><br>
            <h2>Sửa Lịch Học</h2>

            <?php if ($lop_hp_info) : ?>
                <p><strong>Lớp Học Phần:</strong> <?php echo htmlspecialchars($lop_hp_info['ten_lop_hocphan']); ?></p>
                <p><strong>Môn Học:</strong> <?php echo htmlspecialchars($lop_hp_info['ten_mon']); ?></p>
                <hr>
            <?php endif; ?>

            <?php if ($success) echo "<div class='alert success'>$success</div>"; ?>
            <?php if ($error) echo "<div class='alert error'>$error</div>"; ?>

            <?php if ($id_lich_hoc_edit > 0) : ?>
                <form method="POST">
                    <input type="hidden" name="id_lich_hoc" value="<?php echo htmlspecialchars($id_lich_hoc_edit); ?>">
                    <input type="hidden" name="id_lop_hp_parent" value="<?php echo htmlspecialchars($id_lop_hp_parent); ?>">

                    <label for="ngay_trong_tuan">Ngày trong tuần:</label>
                    <select id="ngay_trong_tuan" name="ngay_trong_tuan" required>
                        <option value="">-- Chọn ngày --</option>
                        <option value="1" <?php echo ($ngay_trong_tuan_edit == 1) ? 'selected' : ''; ?>>Thứ 2</option>
                        <option value="2" <?php echo ($ngay_trong_tuan_edit == 2) ? 'selected' : ''; ?>>Thứ 3</option>
                        <option value="3" <?php echo ($ngay_trong_tuan_edit == 3) ? 'selected' : ''; ?>>Thứ 4</option>
                        <option value="4" <?php echo ($ngay_trong_tuan_edit == 4) ? 'selected' : ''; ?>>Thứ 5</option>
                        <option value="5" <?php echo ($ngay_trong_tuan_edit == 5) ? 'selected' : ''; ?>>Thứ 6</option>
                        <option value="6" <?php echo ($ngay_trong_tuan_edit == 6) ? 'selected' : ''; ?>>Thứ 7</option>
                        <option value="7" <?php echo ($ngay_trong_tuan_edit == 7) ? 'selected' : ''; ?>>Chủ nhật</option>
                    </select>

                    <label for="tiet_bat_dau">Tiết bắt đầu:</label>
                    <input type="number" id="tiet_bat_dau" name="tiet_bat_dau" value="<?php echo htmlspecialchars($tiet_bat_dau_edit); ?>" min="1" required>

                    <label for="tiet_ket_thuc">Tiết kết thúc:</label>
                    <input type="number" id="tiet_ket_thuc" name="tiet_ket_thuc" value="<?php echo htmlspecialchars($tiet_ket_thuc_edit); ?>" min="1" required>

                    <label for="ghi_chu">Ghi chú (Tùy chọn):</label>
                    <input type="text" id="ghi_chu" name="ghi_chu" value="<?php echo htmlspecialchars($ghi_chu_edit); ?>">

                    <button type="submit"><i class="fas fa-save"></i> Cập nhật</button>
                    <a href="themlichhoc.php?id_lop_hp=<?php echo htmlspecialchars($id_lop_hp_parent); ?>" class="btn-back"><i class="fas fa-arrow-left"></i> Quay lại Lịch học</a>
                </form>
            <?php else : ?>
                <p>Không thể sửa lịch học. ID không hợp lệ hoặc không được cung cấp.</p>
                <a href="/dkhp/admin/lophocphan.php" class="btn-back"><i class="fas fa-arrow-left"></i> Quay lại Danh sách Lớp Học Phần</a>
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