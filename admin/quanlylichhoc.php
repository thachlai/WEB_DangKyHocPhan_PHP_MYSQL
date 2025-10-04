<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../conn.php';
include '../function.php';

check_login();   // Đảm bảo người dùng đã đăng nhập
check_admin();

$base_url = '/dkhp/';
$message = '';
$message_type = '';

// Lấy id_lop_hp từ URL
$id_lop_hp = isset($_GET['id_lop_hp']) ? intval($_GET['id_lop_hp']) : 0;

if ($id_lop_hp === 0) {
    // Nếu không có id_lop_hp, chuyển hướng về trang danh sách lớp học phần
    $_SESSION['message'] = "Không tìm thấy lớp học phần để quản lý lịch học. Vui lòng chọn một lớp học phần.";
    $_SESSION['message_type'] = "error";
    header("Location: lophocphan.php");
    exit();
}

// Lấy thông tin chi tiết về lớp học phần
$lop_hp_info = null;
$stmt_lop_hp = $conn->prepare("SELECT lhp.ten_lop_hocphan, m.ten_mon, hk.ten_hocki, hk.nam_hoc
                               FROM lop_hocphan lhp
                               LEFT JOIN mon m ON lhp.id_mon = m.id_mon
                               LEFT JOIN hocki hk ON lhp.id_hocki = hk.id_hocki
                               WHERE lhp.id_lop_hp = ?");
if ($stmt_lop_hp) {
    $stmt_lop_hp->bind_param("i", $id_lop_hp);
    $stmt_lop_hp->execute();
    $result_lop_hp = $stmt_lop_hp->get_result();
    if ($result_lop_hp->num_rows > 0) {
        $lop_hp_info = $result_lop_hp->fetch_assoc();
    } else {
        $_SESSION['message'] = "Lớp học phần có ID " . htmlspecialchars($id_lop_hp) . " không tồn tại.";
        $_SESSION['message_type'] = "error";
        header("Location: lophocphan.php");
        exit();
    }
    $stmt_lop_hp->close();
} else {
    $_SESSION['message'] = "Lỗi chuẩn bị truy vấn thông tin lớp học phần: " . $conn->error;
    $_SESSION['message_type'] = "error";
    header("Location: lophocphan.php");
    exit();
}


// --- Xử lý Thêm/Sửa/Xóa Lịch Học ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Xử lý Thêm Lịch Học
    if ($action === 'add') {
        $ngay_trong_tuan = $_POST['ngay_trong_tuan'] ?? '';
        $tiet_bat_dau = $_POST['tiet_bat_dau'] ?? '';
        $tiet_ket_thuc = $_POST['tiet_ket_thuc'] ?? '';
        $ghi_chu = trim($_POST['ghi_chu'] ?? '');

        // Kiểm tra dữ liệu đầu vào
        if (empty($ngay_trong_tuan) || empty($tiet_bat_dau) || empty($tiet_ket_thuc) || !is_numeric($ngay_trong_tuan) || !is_numeric($tiet_bat_dau) || !is_numeric($tiet_ket_thuc)) {
            $message = "Vui lòng điền đầy đủ và đúng định dạng các thông tin lịch học.";
            $message_type = "error";
        } elseif ($tiet_bat_dau <= 0 || $tiet_ket_thuc <= 0 || $tiet_bat_dau > $tiet_ket_thuc || $ngay_trong_tuan < 1 || $ngay_trong_tuan > 7) {
            $message = "Thời gian hoặc ngày học không hợp lệ. Tiết bắt đầu phải nhỏ hơn hoặc bằng tiết kết thúc. Ngày trong tuần từ 1 đến 7.";
            $message_type = "error";
        } else {
            // Kiểm tra trùng lịch học (cùng lớp, cùng ngày, cùng tiết)
            $check_lich_stmt = $conn->prepare("SELECT id_lich_hoc FROM lich_hoc WHERE id_lop_hp = ? AND ngay_trong_tuan = ? AND ((tiet_bat_dau <= ? AND tiet_ket_thuc >= ?) OR (tiet_bat_dau <= ? AND tiet_ket_thuc >= ?))");
            if ($check_lich_stmt === false) {
                $message = "Lỗi chuẩn bị truy vấn kiểm tra trùng lịch: " . $conn->error;
                $message_type = "error";
            } else {
                $check_lich_stmt->bind_param("iiiiii", $id_lop_hp, $ngay_trong_tuan, $tiet_bat_dau, $tiet_bat_dau, $tiet_ket_thuc, $tiet_ket_thuc);
                $check_lich_stmt->execute();
                $check_result = $check_lich_stmt->get_result();
                if ($check_result->num_rows > 0) {
                    $message = "Đã có buổi học trùng lịch (cùng ngày, cùng thời gian) cho lớp học phần này.";
                    $message_type = "error";
                } else {
                    $stmt_add = $conn->prepare("INSERT INTO lich_hoc (id_lop_hp, ngay_trong_tuan, tiet_bat_dau, tiet_ket_thuc, ghi_chu) VALUES (?, ?, ?, ?, ?)");
                    if ($stmt_add === false) {
                        $message = "Lỗi chuẩn bị truy vấn thêm lịch học: " . $conn->error;
                        $message_type = "error";
                    } else {
                        $stmt_add->bind_param("iiiis", $id_lop_hp, $ngay_trong_tuan, $tiet_bat_dau, $tiet_ket_thuc, $ghi_chu);
                        if ($stmt_add->execute()) {
                            $message = "Thêm lịch học thành công!";
                            $message_type = "success";
                            // Chuyển hướng để xóa dữ liệu POST và cập nhật bảng
                            header("Location: quanlylichhoc.php?id_lop_hp=" . $id_lop_hp);
                            exit();
                        } else {
                            $message = "Lỗi khi thêm lịch học: " . $stmt_add->error;
                            $message_type = "error";
                        }
                        $stmt_add->close();
                    }
                }
                $check_lich_stmt->close();
            }
        }
    }
    // Xử lý Sửa Lịch Học
    elseif ($action === 'edit') {
        $id_lich_hoc = $_POST['id_lich_hoc'] ?? '';
        $ngay_trong_tuan = $_POST['ngay_trong_tuan'] ?? '';
        $tiet_bat_dau = $_POST['tiet_bat_dau'] ?? '';
        $tiet_ket_thuc = $_POST['tiet_ket_thuc'] ?? '';
        $ghi_chu = trim($_POST['ghi_chu'] ?? '');

        if (empty($id_lich_hoc) || empty($ngay_trong_tuan) || empty($tiet_bat_dau) || empty($tiet_ket_thuc) || !is_numeric($id_lich_hoc) || !is_numeric($ngay_trong_tuan) || !is_numeric($tiet_bat_dau) || !is_numeric($tiet_ket_thuc)) {
            $message = "Vui lòng điền đầy đủ và đúng định dạng các thông tin lịch học cần sửa.";
            $message_type = "error";
        } elseif ($tiet_bat_dau <= 0 || $tiet_ket_thuc <= 0 || $tiet_bat_dau > $tiet_ket_thuc || $ngay_trong_tuan < 1 || $ngay_trong_tuan > 7) {
            $message = "Thời gian hoặc ngày học không hợp lệ. Tiết bắt đầu phải nhỏ hơn hoặc bằng tiết kết thúc. Ngày trong tuần từ 1 đến 7.";
            $message_type = "error";
        } else {
            // Kiểm tra trùng lịch học, loại trừ chính lịch đang sửa
            $check_lich_stmt = $conn->prepare("SELECT id_lich_hoc FROM lich_hoc WHERE id_lop_hp = ? AND id_lich_hoc != ? AND ngay_trong_tuan = ? AND ((tiet_bat_dau <= ? AND tiet_ket_thuc >= ?) OR (tiet_bat_dau <= ? AND tiet_ket_thuc >= ?))");
            if ($check_lich_stmt === false) {
                $message = "Lỗi chuẩn bị truy vấn kiểm tra trùng lịch (sửa): " . $conn->error;
                $message_type = "error";
            } else {
                $check_lich_stmt->bind_param("iiiiiii", $id_lop_hp, $id_lich_hoc, $ngay_trong_tuan, $tiet_bat_dau, $tiet_bat_dau, $tiet_ket_thuc, $tiet_ket_thuc);
                $check_lich_stmt->execute();
                $check_result = $check_lich_stmt->get_result();
                if ($check_result->num_rows > 0) {
                    $message = "Buổi học này bị trùng lịch (cùng ngày, cùng thời gian) với một buổi học khác của lớp học phần này.";
                    $message_type = "error";
                } else {
                    $stmt_edit = $conn->prepare("UPDATE lich_hoc SET ngay_trong_tuan = ?, tiet_bat_dau = ?, tiet_ket_thuc = ?, ghi_chu = ? WHERE id_lich_hoc = ? AND id_lop_hp = ?");
                    if ($stmt_edit === false) {
                        $message = "Lỗi chuẩn bị truy vấn sửa lịch học: " . $conn->error;
                        $message_type = "error";
                    } else {
                        $stmt_edit->bind_param("iiiisi", $ngay_trong_tuan, $tiet_bat_dau, $tiet_ket_thuc, $ghi_chu, $id_lich_hoc, $id_lop_hp);
                        if ($stmt_edit->execute()) {
                            $message = "Cập nhật lịch học thành công!";
                            $message_type = "success";
                            // Chuyển hướng để xóa dữ liệu POST và cập nhật bảng
                            header("Location: quanlylichhoc.php?id_lop_hp=" . $id_lop_hp);
                            exit();
                        } else {
                            $message = "Lỗi khi cập nhật lịch học: " . $stmt_edit->error;
                            $message_type = "error";
                        }
                        $stmt_edit->close();
                    }
                }
                $check_lich_stmt->close();
            }
        }
    }
}
// Xử lý Xóa Lịch Học (qua GET request, cần xác nhận)
if (isset($_GET['action']) && $_GET['action'] === 'delete_lich' && isset($_GET['id_lich_hoc'])) {
    $id_lich_hoc_to_delete = intval($_GET['id_lich_hoc']);

    $stmt_delete_lich = $conn->prepare("DELETE FROM lich_hoc WHERE id_lich_hoc = ? AND id_lop_hp = ?");
    if ($stmt_delete_lich === false) {
        $_SESSION['message'] = "Lỗi chuẩn bị truy vấn xóa lịch học: " . $conn->error;
        $_SESSION['message_type'] = "error";
    } else {
        $stmt_delete_lich->bind_param("ii", $id_lich_hoc_to_delete, $id_lop_hp);
        if ($stmt_delete_lich->execute()) {
            $_SESSION['message'] = "Lịch học đã được xóa thành công!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Lỗi khi xóa lịch học: " . $stmt_delete_lich->error;
            $_SESSION['message_type'] = "error";
        }
        $stmt_delete_lich->close();
    }
    header("Location: quanlylichhoc.php?id_lop_hp=" . $id_lop_hp);
    exit();
}


// Lấy thông báo từ session (cho cả POST và GET)
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'] ?? 'success';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Lấy danh sách lịch học của lớp học phần này
$lich_hocs = [];
$sql_lich_hoc = "SELECT id_lich_hoc, ngay_trong_tuan, tiet_bat_dau, tiet_ket_thuc, ghi_chu
                 FROM lich_hoc
                 WHERE id_lop_hp = ?
                 ORDER BY ngay_trong_tuan ASC, tiet_bat_dau ASC";
$stmt_lich_hoc = $conn->prepare($sql_lich_hoc);
if ($stmt_lich_hoc) {
    $stmt_lich_hoc->bind_param("i", $id_lop_hp);
    $stmt_lich_hoc->execute();
    $result_lich_hoc = $stmt_lich_hoc->get_result();
    $lich_hocs = $result_lich_hoc->fetch_all(MYSQLI_ASSOC);
    $stmt_lich_hoc->close();
} else {
    $message = "Lỗi chuẩn bị truy vấn danh sách lịch học: " . $conn->error;
    $message_type = "error";
}

// Function to convert day number to Vietnamese day name
function get_day_name($day_num) {
    $days = [
        1 => 'Thứ 2',
        2 => 'Thứ 3',
        3 => 'Thứ 4',
        4 => 'Thứ 5',
        5 => 'Thứ 6',
        6 => 'Thứ 7',
        7 => 'Chủ Nhật'
    ];
    return $days[$day_num] ?? 'Không xác định';
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Lịch Học - <?php echo htmlspecialchars($lop_hp_info['ten_lop_hocphan'] ?? ''); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="<?php echo $base_url; ?>style.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>admin/css/menubar.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>admin/css/mainlist.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>admin/css/mainadd.css"> <style>
        .form-add-schedule {
            margin-top: 20px;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 8px;
            background-color: #f9f9f9;
        }
        .form-add-schedule h3 {
            margin-top: 0;
            color: #333;
        }
        .form-add-schedule label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-add-schedule input[type="number"],
        .form-add-schedule select,
        .form-add-schedule textarea {
            width: calc(100% - 22px); /* Adjust for padding and border */
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .form-add-schedule button {
            background-color: #007bff;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            margin-right: 10px;
        }
        .form-add-schedule button:hover {
            opacity: 0.9;
        }

        .data-table th, .data-table td {
            text-align: center;
            vertical-align: middle;
        }
        .data-table .data-action-buttons a {
            margin: 0 5px;
        }
        .info-box {
            background-color: #e9f7ef;
            border: 1px solid #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .info-box p {
            margin: 0;
            line-height: 1.5;
        }
        .info-box strong {
            color: #0d3617;
        }

        /* Modal Styles */
        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 1000; /* Sit on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
            padding-top: 60px;
        }
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto; /* 15% from the top and centered */
            padding: 20px;
            border: 1px solid #888;
            width: 80%; /* Could be more or less, depending on screen size */
            max-width: 500px;
            border-radius: 8px;
            position: relative;
        }
        .close-button {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }
        .close-button:hover,
        .close-button:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
        .modal-content button[type="submit"] {
            background-color: #28a745; /* Green for update */
        }
        .modal-content button[type="submit"].btn-add-modal {
            background-color: #007bff; /* Blue for add */
        }
    </style>
</head>
<body>
    <?php
    include '../header.php';
    include 'menubar.php';
    ?>

    <main>
        <div class="list-container">
            <h2>Quản lý Lịch Học cho Lớp Học Phần</h2>
            <?php if ($lop_hp_info) : ?>
                <div class="info-box">
                    <p><strong>Lớp Học Phần:</strong> <?php echo htmlspecialchars($lop_hp_info['ten_lop_hocphan']); ?></p>
                    <p><strong>Môn học:</strong> <?php echo htmlspecialchars($lop_hp_info['ten_mon']); ?></p>
                    <p><strong>Học kỳ:</strong> <?php echo htmlspecialchars($lop_hp_info['ten_hocki'] . ' - ' . $lop_hp_info['nam_hoc']); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($message)) : ?>
                <div class="alert <?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="form-add-schedule">
                <h3>Thêm Buổi Học Mới</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <label for="ngay_trong_tuan">Ngày trong tuần (2-CN):</label>
                    <select id="ngay_trong_tuan" name="ngay_trong_tuan" required>
                        <option value="">-- Chọn Ngày --</option>
                        <option value="2">Thứ 2</option>
                        <option value="3">Thứ 3</option>
                        <option value="4">Thứ 4</option>
                        <option value="5">Thứ 5</option>
                        <option value="6">Thứ 6</option>
                        <option value="7">Thứ 7</option>
                        <option value="1">Chủ Nhật</option>
                    </select>

                    <label for="tiet_bat_dau">Tiết bắt đầu:</label>
                    <input type="number" id="tiet_bat_dau" name="tiet_bat_dau" min="1" required>

                    <label for="tiet_ket_thuc">Tiết kết thúc:</label>
                    <input type="number" id="tiet_ket_thuc" name="tiet_ket_thuc" min="1" required>

                    <label for="ghi_chu">Ghi chú (Tùy chọn):</label>
                    <textarea id="ghi_chu" name="ghi_chu" rows="2"></textarea>

                    <button type="submit"><i class="fas fa-plus"></i> Thêm Buổi Học</button>
                </form>
            </div>

            <br>
            <h3>Danh sách các Buổi Học</h3>
            <div class="scroll-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Ngày học</th>
                            <th>Tiết học</th>
                            <th>Ghi chú</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($lich_hocs)) : ?>
                        <?php foreach ($lich_hocs as $lich) : ?>
                            <tr>
                                <td><?php echo htmlspecialchars($lich['id_lich_hoc']); ?></td>
                                <td><?php echo get_day_name($lich['ngay_trong_tuan']); ?></td>
                                <td>Tiết <?php echo htmlspecialchars($lich['tiet_bat_dau']); ?> - <?php echo htmlspecialchars($lich['tiet_ket_thuc']); ?></td>
                                <td><?php echo htmlspecialchars($lich['ghi_chu'] ?? 'N/A'); ?></td>
                                <td class="data-action-buttons">
                                    <a class="btn-edit edit-lich-btn" 
                                       data-id="<?php echo htmlspecialchars($lich['id_lich_hoc']); ?>"
                                       data-day="<?php echo htmlspecialchars($lich['ngay_trong_tuan']); ?>"
                                       data-start="<?php echo htmlspecialchars($lich['tiet_bat_dau']); ?>"
                                       data-end="<?php echo htmlspecialchars($lich['tiet_ket_thuc']); ?>"
                                       data-note="<?php echo htmlspecialchars($lich['ghi_chu']); ?>"
                                       href="#">
                                        <i class="fas fa-edit"></i> Sửa
                                    </a>
                                    <a class="btn-delete" href="quanlylichhoc.php?action=delete_lich&id_lop_hp=<?php echo htmlspecialchars($id_lop_hp); ?>&id_lich_hoc=<?php echo htmlspecialchars($lich['id_lich_hoc']); ?>" onclick="return confirm('Bạn có chắc chắn muốn xóa buổi học này không?');">
                                        <i class="fas fa-trash-alt"></i> Xóa
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="5" style="text-align:center;">Chưa có buổi học nào được thêm cho lớp học phần này.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <br>
            <a href="lophocphan.php" class="btn-back"><i class="fas fa-arrow-left"></i> Quay lại Danh sách Lớp Học Phần</a>
        </div>
    </main>

    <div id="editLichModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h3>Sửa Buổi Học</h3>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" id="edit_id_lich_hoc" name="id_lich_hoc">
                <label for="edit_ngay_trong_tuan">Ngày trong tuần (2-CN):</label>
                <select id="edit_ngay_trong_tuan" name="ngay_trong_tuan" required>
                    <option value="2">Thứ 2</option>
                    <option value="3">Thứ 3</option>
                    <option value="4">Thứ 4</option>
                    <option value="5">Thứ 5</option>
                    <option value="6">Thứ 6</option>
                    <option value="7">Thứ 7</option>
                    <option value="1">Chủ Nhật</option>
                </select>

                <label for="edit_tiet_bat_dau">Tiết bắt đầu:</label>
                <input type="number" id="edit_tiet_bat_dau" name="tiet_bat_dau" min="1" required>

                <label for="edit_tiet_ket_thuc">Tiết kết thúc:</label>
                <input type="number" id="edit_tiet_ket_thuc" name="tiet_ket_thuc" min="1" required>

                <label for="edit_ghi_chu">Ghi chú (Tùy chọn):</label>
                <textarea id="edit_ghi_chu" name="ghi_chu" rows="2"></textarea>

                <button type="submit"><i class="fas fa-save"></i> Cập nhật</button>
                <button type="button" class="btn-cancel" onclick="document.getElementById('editLichModal').style.display='none'">Hủy</button>
            </form>
        </div>
    </div>

    <script>
        // Automatic message hiding
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.alert').forEach(alertDiv => {
                if (alertDiv.innerHTML.trim() !== "") {
                    setTimeout(() => {
                        alertDiv.style.display = 'none';
                    }, 5000);
                }
            });

            // Modal logic for editing
            const modal = document.getElementById('editLichModal');
            const closeButton = document.querySelector('.close-button');
            const editButtons = document.querySelectorAll('.edit-lich-btn');

            editButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    document.getElementById('edit_id_lich_hoc').value = this.dataset.id;
                    document.getElementById('edit_ngay_trong_tuan').value = this.dataset.day;
                    document.getElementById('edit_tiet_bat_dau').value = this.dataset.start;
                    document.getElementById('edit_tiet_ket_thuc').value = this.dataset.end;
                    document.getElementById('edit_ghi_chu').value = this.dataset.note;
                    modal.style.display = 'block';
                });
            });

            closeButton.addEventListener('click', () => {
                modal.style.display = 'none';
            });

            window.addEventListener('click', (event) => {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>