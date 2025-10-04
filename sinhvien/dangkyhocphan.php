<?php
// Luôn bắt đầu session ở đầu file
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../conn.php';
include '../function.php'; // Đảm bảo file function.php tồn tại và có hàm check_student()
check_student();
check_login();


$base_url = '/dkhp/';

$sinh_vien_id = $_SESSION['ma_tk']; // Lấy ID tài khoản của sinh viên đang đăng nhập

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

// --- Lấy danh sách các học kỳ đang "Hiện" (trangthai = 0) ---
$hocki_active_options = [];
$sql_hocki_active = "SELECT id_hocki, ten_hocki, nam_hoc FROM hocki WHERE trangthai = 0 ORDER BY nam_hoc DESC, ten_hocki ASC";
$result_hocki_active = $conn->query($sql_hocki_active);
if ($result_hocki_active && $result_hocki_active->num_rows > 0) {
    while ($row = $result_hocki_active->fetch_assoc()) {
        $hocki_active_options[] = $row;
    }
    // Nếu chưa chọn học kỳ nào, hoặc học kỳ đã chọn không còn hoạt động, chọn học kỳ đầu tiên mặc định
    if ($selected_hocki_id === 0 && !empty($hocki_active_options)) {
        $selected_hocki_id = $hocki_active_options[0]['id_hocki'];
    }
} else {
    $error = "Hiện không có học kỳ nào đang mở đăng ký.";
}


// --- Lấy danh sách lớp học phần có thể đăng ký ---
$available_lop_hp = [];
if ($selected_hocki_id > 0) {
    $sql_available_lop_hp = "SELECT
                                lhp.id_lop_hp,
                                lhp.ten_lop_hocphan,
                                m.ten_mon,
                                m.so_tin_chi,
                                m.gia_tin_chi,
                                tk.ten_taikhoan,
                                ph.ten_phong,
                                lhp.si_so_toi_da,
                                lhp.si_so_hien_tai,
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
                                taikhoan tk ON lhp.id_taikhoan = tk.ma_tk
                            JOIN
                                phong_hoc ph ON lhp.id_phong = ph.id_phong
                            LEFT JOIN
                                lich_hoc lh ON lhp.id_lop_hp = lh.id_lop_hp -- LEFT JOIN VỚI BẢNG LỊCH HỌC
                            WHERE
                                lhp.id_hocki = ? AND lhp.trangthai = 0
                                AND lhp.si_so_hien_tai < lhp.si_so_toi_da
                            GROUP BY
                                lhp.id_lop_hp -- PHẢI GROUP BY KHI DÙNG GROUP_CONCAT
                            ORDER BY
                                lhp.ten_lop_hocphan ASC";

    $stmt_available_lop_hp = $conn->prepare($sql_available_lop_hp);
    if ($stmt_available_lop_hp === false) {
        $error .= "Lỗi chuẩn bị truy vấn lớp học phần: " . $conn->error;
    } else {
        $stmt_available_lop_hp->bind_param("i", $selected_hocki_id);
        $stmt_available_lop_hp->execute();
        $result_available_lop_hp = $stmt_available_lop_hp->get_result();
        if ($result_available_lop_hp->num_rows > 0) {
            while ($row = $result_available_lop_hp->fetch_assoc()) {
                $available_lop_hp[] = $row;
            }
        } else {
            $error .= "Không có lớp học phần nào đang mở đăng ký trong học kỳ này.";
        }
        $stmt_available_lop_hp->close();
    }
}


// --- Lấy danh sách lớp học phần sinh viên đã đăng ký ---
$registered_lop_hp = [];
if ($sinh_vien_id > 0) {
    $sql_registered_lop_hp = "SELECT
                                dkhp.id_dkhp,
                                lhp.id_lop_hp,
                                lhp.ten_lop_hocphan,
                                m.ten_mon,
                                m.so_tin_chi,
                                m.gia_tin_chi,
                                tk.ten_taikhoan,
                                ph.ten_phong,
                                dkhp.ngay_dang_ky,
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
                                dangky_hocphan dkhp
                            JOIN
                                lop_hocphan lhp ON dkhp.id_lop_hp = lhp.id_lop_hp
                            JOIN
                                mon m ON lhp.id_mon = m.id_mon
                            JOIN
                                taikhoan tk ON lhp.id_taikhoan = tk.ma_tk
                            JOIN
                                phong_hoc ph ON lhp.id_phong = ph.id_phong
                            LEFT JOIN
                                lich_hoc lh ON lhp.id_lop_hp = lh.id_lop_hp -- LEFT JOIN VỚI BẢNG LỊCH HỌC
                            WHERE
                                dkhp.id_taikhoan = ? AND lhp.id_hocki = ?
                            GROUP BY
                                dkhp.id_dkhp, lhp.id_lop_hp -- Cần Group By tất cả các cột không phải aggregate function
                            ORDER BY
                                dkhp.ngay_dang_ky DESC";
    $stmt_registered_lop_hp = $conn->prepare($sql_registered_lop_hp);
    if ($stmt_registered_lop_hp === false) {
        $error .= "Lỗi chuẩn bị truy vấn lớp đã đăng ký: " . $conn->error;
    } else {
        $stmt_registered_lop_hp->bind_param("ii", $sinh_vien_id, $selected_hocki_id);
        $stmt_registered_lop_hp->execute();
        $result_registered_lop_hp = $stmt_registered_lop_hp->get_result();
        if ($result_registered_lop_hp->num_rows > 0) {
            while ($row = $result_registered_lop_hp->fetch_assoc()) {
                $registered_lop_hp[] = $row;
            }
        }
        $stmt_registered_lop_hp->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký Học Phần</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>style.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>sinhvien/css/menubar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <style>
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
        }
        .filter-section label {
            font-weight: bold;
            margin-right: 10px;
        }
        .filter-section select, .filter-section button {
            padding: 8px 12px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
        .filter-section button {
            background-color: #007bff;
            color: white;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .filter-section button:hover {
            background-color: #0056b3;
        }
        .class-list, .registered-class-list {
            margin-top: 20px;
        }
        .class-list h3, .registered-class-list h3 {
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
        .register-btn, .cancel-btn {
            background-color: #28a745;
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
        .register-btn:hover {
            background-color: #218838;
        }
        .cancel-btn {
            background-color: #dc3545;
        }
        .cancel-btn:hover {
            background-color: #c82333;
        }
        /* Style cho nút "Đã chốt" */
        .cancel-btn:disabled, .cancel-btn[disabled] {
            background-color: #6c757d; /* Màu xám */
            cursor: not-allowed;
            opacity: 0.7;
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
    <?php
    include '../header.php';
    ?>

    <main>
        <div class="form-container">
            <br><br><br>
            <h2>Đăng ký Học Phần</h2>

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
                        <?php foreach ($hocki_active_options as $hk) : ?>
                            <option value="<?php echo htmlspecialchars($hk['id_hocki']); ?>"
                                <?php echo ($selected_hocki_id == $hk['id_hocki']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($hk['ten_hocki'] . ' - ' . $hk['nam_hoc']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <?php if ($selected_hocki_id === 0) : ?>
                <p class="alert error">Vui lòng chọn một học kỳ để xem danh sách lớp học phần.</p>
            <?php endif; ?>

            <div class="class-list">
                <h3>Các lớp học phần có thể đăng ký</h3>
                <table class="styled-table">
                    <thead>
                        <tr>
                            <th>Tên lớp</th>
                            <th>Môn học</th>
                            <th>Tín chỉ</th>
                            <th>Giáo viên</th>
                            <th>Phòng học</th>
                            <th>Lịch học</th> <th>Sĩ số (hiện tại/tối đa)</th>
                            <th>Tổng tiền</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($available_lop_hp)) : ?>
                            <?php foreach ($available_lop_hp as $lop_hp) :
                                $total_price = $lop_hp['so_tin_chi'] * $lop_hp['gia_tin_chi'];
                                // Kiểm tra xem sinh viên đã đăng ký lớp này chưa để vô hiệu hóa nút
                                $is_registered = false;
                                foreach ($registered_lop_hp as $reg_class) {
                                    if ($reg_class['id_lop_hp'] == $lop_hp['id_lop_hp']) {
                                        $is_registered = true;
                                        break;
                                    }
                                }
                                // Lịch học chi tiết đã được gom nhóm từ truy vấn SQL
                                $lich_hoc_display = !empty($lop_hp['lich_hoc_chi_tiet']) ? $lop_hp['lich_hoc_chi_tiet'] : 'Chưa có lịch';
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($lop_hp['ten_lop_hocphan']); ?></td>
                                    <td><?php echo htmlspecialchars($lop_hp['ten_mon']); ?></td>
                                    <td><?php echo htmlspecialchars($lop_hp['so_tin_chi']); ?></td>
                                    <td><?php echo htmlspecialchars($lop_hp['ten_taikhoan']); ?></td>
                                    <td><?php echo htmlspecialchars($lop_hp['ten_phong']); ?></td>
                                    <td><?php echo $lich_hoc_display; ?></td> <td><?php echo htmlspecialchars($lop_hp['si_so_hien_tai'] ?? '0') . '/' . htmlspecialchars($lop_hp['si_so_toi_da']); ?></td>
                                    <td><?php echo number_format($total_price, 0, ',', '.') . 'đ'; ?></td>
                                    <td>
                                        <?php if ($is_registered) : ?>
                                            <button class="register-btn" disabled>Đã Đăng ký</button>
                                        <?php else : ?>
                                            <a href="process_dangky.php?id_lop_hp=<?php echo htmlspecialchars($lop_hp['id_lop_hp']); ?>&hocki_id=<?php echo htmlspecialchars($selected_hocki_id); ?>" class="register-btn"><i class="fas fa-plus-circle"></i> Đăng ký</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="9">Không có lớp học phần nào khả dụng trong học kỳ này hoặc không có học kỳ nào được chọn.</td> </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="registered-class-list">
                <h3>Các lớp học phần đã đăng ký của bạn</h3>
                <table class="styled-table">
                    <thead>
                        <tr>
                            <th>Tên lớp</th>
                            <th>Môn học</th>
                            <th>Tín chỉ</th>
                            <th>Giáo viên</th>
                            <th>Phòng học</th>
                            <th>Lịch học</th> <th>Ngày đăng ký</th>
                            <th>Tổng tiền</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($registered_lop_hp)) : ?>
                            <?php foreach ($registered_lop_hp as $reg_lop_hp) :
                                $total_price_reg = $reg_lop_hp['so_tin_chi'] * $reg_lop_hp['gia_tin_chi'];
                                // Logic kiểm tra cho phép hủy dựa vào trạng thái của lớp học phần
                                // Nếu lop_hp_trangthai là 2 (chốt) thì không cho phép hủy
                                $can_cancel = ($reg_lop_hp['lop_hp_trangthai'] != 2);

                                // Lịch học chi tiết đã được gom nhóm từ truy vấn SQL
                                $lich_hoc_reg_display = !empty($reg_lop_hp['lich_hoc_chi_tiet']) ? $reg_lop_hp['lich_hoc_chi_tiet'] : 'Chưa có lịch';
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($reg_lop_hp['ten_lop_hocphan']); ?></td>
                                    <td><?php echo htmlspecialchars($reg_lop_hp['ten_mon']); ?></td>
                                    <td><?php echo htmlspecialchars($reg_lop_hp['so_tin_chi']); ?></td>
                                    <td><?php echo htmlspecialchars($reg_lop_hp['ten_taikhoan']); ?></td>
                                    <td><?php echo htmlspecialchars($reg_lop_hp['ten_phong']); ?></td>
                                    <td><?php echo $lich_hoc_reg_display; ?></td> <td><?php echo htmlspecialchars($reg_lop_hp['ngay_dang_ky']); ?></td>
                                    <td><?php echo number_format($total_price_reg, 0, ',', '.') . 'đ'; ?></td>
                                    <td>
                                        <?php if ($can_cancel) : ?>
                                            <a href="process_huydangky.php?id_dkhp=<?php echo htmlspecialchars($reg_lop_hp['id_dkhp']); ?>&hocki_id=<?php echo htmlspecialchars($selected_hocki_id); ?>" class="cancel-btn" onclick="return confirm('Bạn có chắc chắn muốn hủy đăng ký lớp học phần này không?');">
                                                <i class="fas fa-times-circle"></i> Hủy Đăng ký
                                            </a>
                                        <?php else : ?>
                                            <button class="cancel-btn" disabled title="Không thể hủy đăng ký lớp học phần này vì lớp đã chốt.">
                                                <i class="fas fa-lock"></i> Đã chốt
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="9">Bạn chưa đăng ký lớp học phần nào trong học kỳ này.</td> </tr>
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