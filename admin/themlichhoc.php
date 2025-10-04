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
$id_lop_hp = isset($_GET['id_lop_hp']) ? intval($_GET['id_lop_hp']) : 0;
$lop_hp_info = null;

// Khởi tạo biến để giữ giá trị form
$ngay_trong_tuan = '';
$tiet_bat_dau = '';
$tiet_ket_thuc = '';
$ghi_chu = '';

// Fetch lop_hocphan info to display
if ($id_lop_hp > 0) {
    $stmt_lop_hp = $conn->prepare("SELECT ten_lop_hocphan, m.ten_mon FROM lop_hocphan lhp JOIN mon m ON lhp.id_mon = m.id_mon WHERE id_lop_hp = ?");
    if ($stmt_lop_hp) {
        $stmt_lop_hp->bind_param("i", $id_lop_hp);
        $stmt_lop_hp->execute();
        $result_lop_hp = $stmt_lop_hp->get_result();
        if ($result_lop_hp->num_rows > 0) {
            $lop_hp_info = $result_lop_hp->fetch_assoc();
        } else {
            $error = "Không tìm thấy lớp học phần này.";
            $id_lop_hp = 0; // Invalid ID
        }
        $stmt_lop_hp->close();
    } else {
        $error = "Lỗi chuẩn bị truy vấn thông tin lớp học phần: " . $conn->error;
    }
} else {
    $error = "Vui lòng chọn một lớp học phần để thêm lịch học.";
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id_lop_hp > 0) {
    // Lấy dữ liệu từ form
    $ngay_trong_tuan = trim($_POST['ngay_trong_tuan'] ?? '');
    $tiet_bat_dau = trim($_POST['tiet_bat_dau'] ?? '');
    $tiet_ket_thuc = trim($_POST['tiet_ket_thuc'] ?? '');
    $ghi_chu = trim($_POST['ghi_chu'] ?? '');

    // --- Kiểm tra dữ liệu đầu vào ---
    if (empty($ngay_trong_tuan) || !is_numeric($ngay_trong_tuan) || $ngay_trong_tuan < 1 || $ngay_trong_tuan > 7 ||
        empty($tiet_bat_dau) || !is_numeric($tiet_bat_dau) || $tiet_bat_dau < 1 ||
        empty($tiet_ket_thuc) || !is_numeric($tiet_ket_thuc) || $tiet_ket_thuc < $tiet_bat_dau) {
        $error = "Vui lòng điền đầy đủ và chính xác thông tin lịch học (Thứ trong tuần từ 1-7, Tiết bắt đầu/kết thúc hợp lệ).";
    } else {
        // Chuẩn bị truy vấn INSERT vào bảng lich_hoc
        $stmt = $conn->prepare("INSERT INTO lich_hoc (id_lop_hp, ngay_trong_tuan, tiet_bat_dau, tiet_ket_thuc, ghi_chu) VALUES (?, ?, ?, ?, ?)");
        if ($stmt === false) {
            $error = "Lỗi chuẩn bị truy vấn thêm lịch học: " . $conn->error;
        } else {
            $stmt->bind_param("iiiis", $id_lop_hp, $ngay_trong_tuan, $tiet_bat_dau, $tiet_ket_thuc, $ghi_chu);

            if ($stmt->execute()) {
                $success = "Thêm lịch học thành công cho lớp **" . htmlspecialchars($lop_hp_info['ten_lop_hocphan']) . "**!";
                // Xóa dữ liệu form sau khi thêm thành công
                $ngay_trong_tuan = '';
                $tiet_bat_dau = '';
                $tiet_ket_thuc = '';
                $ghi_chu = '';
            } else {
                $error = "Lỗi khi thêm lịch học: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Fetch existing schedules for this lop_hocphan
$lich_hocs = [];
if ($id_lop_hp > 0) {
    $stmt_lich = $conn->prepare("SELECT id_lich_hoc, ngay_trong_tuan, tiet_bat_dau, tiet_ket_thuc, ghi_chu FROM lich_hoc WHERE id_lop_hp = ? ORDER BY ngay_trong_tuan, tiet_bat_dau");
    if ($stmt_lich) {
        $stmt_lich->bind_param("i", $id_lop_hp);
        $stmt_lich->execute();
        $result_lich = $stmt_lich->get_result();
        while ($row = $result_lich->fetch_assoc()) {
            $lich_hocs[] = $row;
        }
        $stmt_lich->close();
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
    <title>Thêm Lịch Học cho Lớp Học Phần</title>
    <link rel="stylesheet" href="css/mainadd.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <style>
        .schedule-list {
            margin-top: 20px;
            border-top: 1px solid #ccc;
            padding-top: 15px;
        }
        .schedule-item {
            background-color: #f0f8ff;
            border: 1px solid #cceeff;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .schedule-item span {
            flex-grow: 1;
        }
        .schedule-item .actions a {
            margin-left: 10px;
            color: #007bff;
            text-decoration: none;
        }
        .schedule-item .actions a.delete {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <main>
        <div class="form-container">
            <br><br><br>
            <h2>Thêm Lịch Học cho Lớp Học Phần</h2>

            <?php if ($lop_hp_info) : ?>
                <p><strong>Lớp Học Phần:</strong> <?php echo htmlspecialchars($lop_hp_info['ten_lop_hocphan']); ?></p>
                <p><strong>Môn Học:</strong> <?php echo htmlspecialchars($lop_hp_info['ten_mon']); ?></p>
                <hr>
            <?php endif; ?>

            <?php if ($success) echo "<div class='alert success'>$success</div>"; ?>
            <?php if ($error) echo "<div class='alert error'>$error</div>"; ?>

            <?php if ($id_lop_hp > 0 && $lop_hp_info) : ?>
                <form method="POST">
                    <label for="ngay_trong_tuan">Ngày trong tuần:</label>
                    <select id="ngay_trong_tuan" name="ngay_trong_tuan" required>
                        <option value="">-- Chọn ngày --</option>
                        <option value="1" <?php echo ($ngay_trong_tuan == 1) ? 'selected' : ''; ?>>Thứ 2</option>
                        <option value="2" <?php echo ($ngay_trong_tuan == 2) ? 'selected' : ''; ?>>Thứ 3</option>
                        <option value="3" <?php echo ($ngay_trong_tuan == 3) ? 'selected' : ''; ?>>Thứ 4</option>
                        <option value="4" <?php echo ($ngay_trong_tuan == 4) ? 'selected' : ''; ?>>Thứ 5</option>
                        <option value="5" <?php echo ($ngay_trong_tuan == 5) ? 'selected' : ''; ?>>Thứ 6</option>
                        <option value="6" <?php echo ($ngay_trong_tuan == 6) ? 'selected' : ''; ?>>Thứ 7</option>
                        <option value="7" <?php echo ($ngay_trong_tuan == 7) ? 'selected' : ''; ?>>Chủ nhật</option>
                    </select>

                    <label for="tiet_bat_dau">Tiết bắt đầu:</label>
                    <input type="number" id="tiet_bat_dau" name="tiet_bat_dau" value="<?php echo htmlspecialchars($tiet_bat_dau); ?>" min="1" required>

                    <label for="tiet_ket_thuc">Tiết kết thúc:</label>
                    <input type="number" id="tiet_ket_thuc" name="tiet_ket_thuc" value="<?php echo htmlspecialchars($tiet_ket_thuc); ?>" min="1" required>

                    <label for="ghi_chu">Ghi chú (Tùy chọn):</label>
                    <input type="text" id="ghi_chu" name="ghi_chu" value="<?php echo htmlspecialchars($ghi_chu); ?>">

                    <button type="submit"><i class="fas fa-plus"></i> Thêm Lịch Học</button>
                </form>

                <div class="schedule-list">
                    <h3>Lịch Học Hiện Tại:</h3>
                    <?php if (!empty($lich_hocs)) : ?>
                        <?php foreach ($lich_hocs as $lich) : ?>
                            <div class="schedule-item">
                                <span>
                                    <?php echo getDayOfWeekText($lich['ngay_trong_tuan']); ?>, Tiết <?php echo htmlspecialchars($lich['tiet_bat_dau']); ?>-<?php echo htmlspecialchars($lich['tiet_ket_thuc']); ?>
                                    <?php if (!empty($lich['ghi_chu'])) : ?>
                                        (<?php echo htmlspecialchars($lich['ghi_chu']); ?>)
                                    <?php endif; ?>
                                </span>
                                <span class="actions">
                                    <a href="sualichhoc.php?id=<?php echo htmlspecialchars($lich['id_lich_hoc']); ?>" title="Sửa"><i class="fas fa-edit"></i></a>
                                    <a href="themlichhoc.php?id_lop_hp=<?php echo htmlspecialchars($id_lop_hp); ?>&action=delete&id_lich_hoc=<?php echo htmlspecialchars($lich['id_lich_hoc']); ?>" class="delete" onclick="return confirm('Bạn có chắc chắn muốn xóa buổi học này?');" title="Xóa"><i class="fas fa-trash-alt"></i></a>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <p>Chưa có lịch học nào được thêm cho lớp này.</p>
                    <?php endif; ?>
                </div>

            <?php else : ?>
                <p>Không thể thêm lịch học. Vui lòng quay lại <a href="lophocphan.php">Danh sách Lớp Học Phần</a> và chọn một lớp để thêm lịch.</p>
            <?php endif; ?>

            <a href="/dkhp/admin/lophocphan.php" class="btn-back" style="margin-top: 20px;"><i class="fas fa-arrow-left"></i> Quay lại Danh sách Lớp Học Phần</a>
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