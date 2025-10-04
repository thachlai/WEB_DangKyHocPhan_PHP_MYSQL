<?php
// Luôn bắt đầu session ở đầu file
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../conn.php';
include '../function.php'; // Đảm bảo file function.php tồn tại và có hàm check_teacher()
check_giangvien();
check_login();

// Kiểm tra vai trò người dùng là giảng viên
// check_teacher('/dkhp/'); // Hàm này sẽ kiểm tra vai trò của người dùng là giảng viên

$base_url = '/dkhp/';

$giang_vien_id = $_SESSION['ma_tk']; // Lấy ID tài khoản của giảng viên đang đăng nhập

$error = '';
$success = '';
$selected_hocki_id = isset($_GET['hocki_id']) ? intval($_GET['hocki_id']) : 0;

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

// --- Lấy danh sách các học kỳ đang "Hiện" (trangthai = 0) hoặc "Đã chốt" (trangthai = 2) ---
// Giảng viên có thể muốn xem lớp của các học kỳ đã chốt để quản lý điểm, v.v.
$hocki_options = [];
$sql_hocki = "SELECT id_hocki, ten_hocki, nam_hoc FROM hocki WHERE trangthai IN (0, 2) ORDER BY nam_hoc DESC, ten_hocki ASC";
$result_hocki = $conn->query($sql_hocki);
if ($result_hocki && $result_hocki->num_rows > 0) {
    while ($row = $result_hocki->fetch_assoc()) {
        $hocki_options[] = $row;
    }
    // Nếu chưa chọn học kỳ nào, hoặc học kỳ đã chọn không tồn tại, chọn học kỳ đầu tiên mặc định
    if ($selected_hocki_id === 0 && !empty($hocki_options)) {
        $selected_hocki_id = $hocki_options[0]['id_hocki'];
    }
} else {
    $error = "Hiện không có học kỳ nào khả dụng.";
}

// --- Lấy danh sách lớp học phần mà giảng viên này đang giảng dạy ---
$lop_giang_day = [];
if ($giang_vien_id > 0 && $selected_hocki_id > 0) {
    $sql_lop_giang_day = "SELECT
                            lhp.id_lop_hp,
                            lhp.ten_lop_hocphan,
                            m.ten_mon,
                            m.so_tin_chi,
                            ph.ten_phong,
                            lhp.si_so_toi_da,
                            lhp.si_so_hien_tai,
                            lhp.trangthai AS lop_hp_trangthai,
                            GROUP_CONCAT(
                                CONCAT(
                                    'Thứ ', lh.ngay_trong_tuan + 1, ': Tiết ', lh.tiet_bat_dau, '-', lh.tiet_ket_thuc,
                                    IF(lh.ghi_chu IS NOT NULL AND lh.ghi_chu != '', CONCAT(' (', lh.ghi_chu, ')'), '')
                                )
                                ORDER BY lh.ngay_trong_tuan, lh.tiet_bat_dau
                                SEPARATOR '<br>'
                            ) AS lich_hoc_chi_tiet
                        FROM
                            lop_hocphan lhp
                        JOIN
                            mon m ON lhp.id_mon = m.id_mon
                        JOIN
                            phong_hoc ph ON lhp.id_phong = ph.id_phong
                        LEFT JOIN
                            lich_hoc lh ON lhp.id_lop_hp = lh.id_lop_hp
                        WHERE
                            lhp.id_taikhoan = ? AND lhp.id_hocki = ?
                        GROUP BY
                            lhp.id_lop_hp
                        ORDER BY
                            lhp.ten_lop_hocphan ASC";

    $stmt_lop_giang_day = $conn->prepare($sql_lop_giang_day);
    if ($stmt_lop_giang_day === false) {
        $error .= "Lỗi chuẩn bị truy vấn danh sách lớp giảng dạy: " . $conn->error;
    } else {
        $stmt_lop_giang_day->bind_param("ii", $giang_vien_id, $selected_hocki_id);
        $stmt_lop_giang_day->execute();
        $result_lop_giang_day = $stmt_lop_giang_day->get_result();
        if ($result_lop_giang_day->num_rows > 0) {
            while ($row = $result_lop_giang_day->fetch_assoc()) {
                $lop_giang_day[] = $row;
            }
        } else {
            $error .= "Bạn không có lớp học phần nào được phân công trong học kỳ này.";
        }
        $stmt_lop_giang_day->close();
    }
} else {
    // Nếu chưa chọn học kỳ hoặc giảng viên ID không hợp lệ, không truy vấn
    if ($giang_vien_id <= 0) {
        $error = "Không tìm thấy thông tin giảng viên.";
    }
}


$conn->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách Lớp Giảng Dạy</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>style.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>giangvien/css/menubar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <style>
        /* Giữ nguyên các CSS trước đó, chỉ thêm/sửa nếu cần thiết */
        .form-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .filter-section {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 5px;
            border: 1px solid #eee;
            display: flex; /* Dùng flexbox để căn chỉnh */
            align-items: center;
            gap: 15px; /* Khoảng cách giữa các phần tử */
        }
        .filter-section label {
            font-weight: bold;
            margin-right: 0; /* Đã có gap */
        }
        .filter-section select {
            padding: 8px 12px;
            border-radius: 5px;
            border: 1px solid #ccc;
            min-width: 200px; /* Đặt chiều rộng tối thiểu cho select */
        }
        /* Bỏ button trong filter-section nếu không dùng */

        .class-list {
            margin-top: 20px;
        }
        .class-list h3 {
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .styled-table {
            width: 100%;
            border-collapse: collapse;
            margin: 25px 0;
            font-size: 0.9em;
            min-width: 400px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.15);
        }
        .styled-table thead tr {
            background-color: #009879;
            color: #ffffff;
            text-align: left;
        }
        .styled-table th,
        .styled-table td {
            padding: 12px 15px;
        }
        .styled-table tbody tr {
            border-bottom: 1px solid #dddddd;
        }
        .styled-table tbody tr:nth-of-type(even) {
            background-color: #f3f3f3;
        }
        .styled-table tbody tr:last-of-type {
            border-bottom: 2px solid #009879;
        }
        .styled-table tbody tr.active-row {
            font-weight: bold;
            color: #009879;
        }
        .action-btn {
            background-color: #007bff; /* Mặc định màu xanh */
            color: white;
            padding: 8px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9em;
            transition: background-color 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .action-btn:hover {
            background-color: #0056b3;
        }
        .action-btn.green {
            background-color: #28a745;
        }
        .action-btn.green:hover {
            background-color: #218838;
        }
        /* Có thể thêm các màu khác cho các nút hành động khác nếu cần */

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
        .lop-hp-trangthai {
            font-weight: bold;
            padding: 3px 8px;
            border-radius: 4px;
            color: white;
            display: inline-block;
        }
        .lop-hp-trangthai.dang-mo {
            background-color: #28a745; /* green */
        }
        .lop-hp-trangthai.da-chot {
            background-color: #007bff; /* blue */
        }
        .lop-hp-trangthai.da-dong {
            background-color: #6c757d; /* gray */
        }
    </style>
</head>
<body>
    <?php
    // Bao gồm header chung
    include '../header.php';
    ?>

    <main>
        <div class="form-container">
            <br><br><br>
            <h2>Danh sách Lớp Giảng Dạy</h2>

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

            <div class="filter-section">
                <form method="GET" id="hockiForm">
                    <label for="hocki_select">Chọn Học kỳ:</label>
                    <select id="hocki_select" name="hocki_id" onchange="document.getElementById('hockiForm').submit()">
                        <option value="">-- Chọn Học kỳ --</option>
                        <?php foreach ($hocki_options as $hk) : ?>
                            <option value="<?php echo htmlspecialchars($hk['id_hocki']); ?>"
                                <?php echo ($selected_hocki_id == $hk['id_hocki']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($hk['ten_hocki'] . ' - ' . $hk['nam_hoc']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <?php if ($selected_hocki_id === 0) : ?>
                <p class="alert error">Vui lòng chọn một học kỳ để xem danh sách lớp giảng dạy.</p>
            <?php endif; ?>

            <div class="class-list">
                <h3>Các lớp học phần bạn đang giảng dạy</h3>
                <table class="styled-table">
                    <thead>
                        <tr>
                            <th>Tên lớp</th>
                            <th>Môn học</th>
                            <th>Tín chỉ</th>
                            <th>Phòng học</th>
                            <th>Lịch học</th>
                            <th>Sĩ số (hiện tại/tối đa)</th>
                            <th>Trạng thái lớp</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($lop_giang_day)) : ?>
                            <?php foreach ($lop_giang_day as $lop_hp) :
                                // Định dạng trạng thái lớp
                                $trang_thai_text = '';
                                $trang_thai_class = '';
                                switch ($lop_hp['lop_hp_trangthai']) {
                                    case 0:
                                        $trang_thai_text = 'Đang mở';
                                        $trang_thai_class = 'dang-mo';
                                        break;
                                    case 1:
                                        $trang_thai_text = 'Đã đóng';
                                        $trang_thai_class = 'da-dong';
                                        break;
                                    case 2:
                                        $trang_thai_text = 'Đã chốt';
                                        $trang_thai_class = 'da-chot';
                                        break;
                                    default:
                                        $trang_thai_text = 'Không xác định';
                                        $trang_thai_class = '';
                                        break;
                                }

                                $lich_hoc_display = !empty($lop_hp['lich_hoc_chi_tiet']) ? $lop_hp['lich_hoc_chi_tiet'] : 'Chưa có lịch';
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($lop_hp['ten_lop_hocphan']); ?></td>
                                    <td><?php echo htmlspecialchars($lop_hp['ten_mon']); ?></td>
                                    <td><?php echo htmlspecialchars($lop_hp['so_tin_chi']); ?></td>
                                    <td><?php echo htmlspecialchars($lop_hp['ten_phong']); ?></td>
                                    <td><?php echo $lich_hoc_display; ?></td>
                                    <td><?php echo htmlspecialchars($lop_hp['si_so_hien_tai'] ?? '0') . '/' . htmlspecialchars($lop_hp['si_so_toi_da']); ?></td>
                                    <td><span class="lop-hp-trangthai <?php echo $trang_thai_class; ?>"><?php echo $trang_thai_text; ?></span></td>
                                    <td>
                                        <a href="list_sinhvien.php?id_lop_hp=<?php echo htmlspecialchars($lop_hp['id_lop_hp']); ?>&hocki_id=<?php echo htmlspecialchars($selected_hocki_id); ?>" class="action-btn green">
                                            <i class="fas fa-users"></i> Xem DS Sinh viên
                                        </a>
                                        </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="8">Không có lớp học phần nào bạn đang giảng dạy trong học kỳ này.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
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