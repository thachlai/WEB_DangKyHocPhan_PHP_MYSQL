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
// check_teacher('/dkhp/');

$base_url = '/dkhp/';

$giang_vien_id = $_SESSION['ma_tk']; // Lấy ID tài khoản của giảng viên

$error = '';
$success = '';

// Lấy id_lop_hp và hocki_id từ URL
$id_lop_hp = isset($_GET['id_lop_hp']) ? intval($_GET['id_lop_hp']) : 0;
$hocki_id = isset($_GET['hocki_id']) ? intval($_GET['hocki_id']) : 0;

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

$lop_hoc_phan_info = null;
$danh_sach_sinh_vien = [];

if ($id_lop_hp > 0 && $hocki_id > 0) {
    // 1. Lấy thông tin chi tiết về lớp học phần
    $sql_lop_hp_info = "SELECT
                            lhp.ten_lop_hocphan,
                            m.ten_mon,
                            m.so_tin_chi,
                            hk.ten_hocki,
                            hk.nam_hoc,
                            ph.ten_phong,
                            tk.ten_taikhoan AS ten_giang_vien,
                            lhp.si_so_hien_tai,
                            lhp.si_so_toi_da,
                            lhp.trangthai AS lop_hp_trangthai
                        FROM
                            lop_hocphan lhp
                        JOIN
                            mon m ON lhp.id_mon = m.id_mon
                        JOIN
                            hocki hk ON lhp.id_hocki = hk.id_hocki
                        JOIN
                            phong_hoc ph ON lhp.id_phong = ph.id_phong
                        JOIN
                            taikhoan tk ON lhp.id_taikhoan = tk.ma_tk
                        WHERE
                            lhp.id_lop_hp = ? AND lhp.id_hocki = ? AND lhp.id_taikhoan = ?"; // Đảm bảo giảng viên chỉ xem lớp của mình

    $stmt_lop_hp_info = $conn->prepare($sql_lop_hp_info);
    if ($stmt_lop_hp_info === false) {
        $error .= "Lỗi chuẩn bị truy vấn thông tin lớp: " . $conn->error;
    } else {
        $stmt_lop_hp_info->bind_param("iii", $id_lop_hp, $hocki_id, $giang_vien_id);
        $stmt_lop_hp_info->execute();
        $result_lop_hp_info = $stmt_lop_hp_info->get_result();
        if ($result_lop_hp_info->num_rows > 0) {
            $lop_hoc_phan_info = $result_lop_hp_info->fetch_assoc();
        } else {
            $error .= "Không tìm thấy thông tin lớp học phần hoặc bạn không có quyền xem lớp này.";
        }
        $stmt_lop_hp_info->close();
    }

    // 2. Lấy danh sách sinh viên đã đăng ký lớp này
    if ($lop_hoc_phan_info) { // Chỉ truy vấn DS sinh viên nếu tìm thấy thông tin lớp
        $sql_dssv = "SELECT
                        tk.ma_tk,
                        tk.ten_taikhoan AS ten_sinh_vien,
                        tk.email,
                        tk.sdt,
                        dkhp.ngay_dang_ky
                    FROM
                        dangky_hocphan dkhp
                    JOIN
                        taikhoan tk ON dkhp.id_taikhoan = tk.ma_tk
                    WHERE
                        dkhp.id_lop_hp = ?
                    ORDER BY
                        tk.ten_taikhoan ASC";

        $stmt_dssv = $conn->prepare($sql_dssv);
        if ($stmt_dssv === false) {
            $error .= "Lỗi chuẩn bị truy vấn danh sách sinh viên: " . $conn->error;
        } else {
            $stmt_dssv->bind_param("i", $id_lop_hp);
            $stmt_dssv->execute();
            $result_dssv = $stmt_dssv->get_result();
            if ($result_dssv->num_rows > 0) {
                while ($row = $result_dssv->fetch_assoc()) {
                    $danh_sach_sinh_vien[] = $row;
                }
            } else {
                $success = "Lớp học phần này hiện chưa có sinh viên nào đăng ký.";
            }
            $stmt_dssv->close();
        }
    }
} else {
    $error = "Không có thông tin lớp học phần hoặc học kỳ được cung cấp.";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách Sinh viên của Lớp</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>style.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>giangvien/css/menubar.css">
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
        .class-info {
            background-color: #f0f8ff;
            padding: 15px 20px;
            border-radius: 8px;
            border: 1px solid #cceeff;
            margin-bottom: 20px;
        }
        .class-info p {
            margin: 5px 0;
            font-size: 1.05em;
        }
        .class-info p strong {
            color: #0056b3;
        }
        .student-list h3 {
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
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background-color: #6c757d;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            text-decoration: none;
            margin-bottom: 20px;
            transition: background-color 0.3s ease;
        }
        .back-button:hover {
            background-color: #5a6268;
        }
    </style>
</head>
<body>
    <?php include '../header.php'; ?>

    <main>
        <div class="form-container">
            <br><br><br>
            <h2>Danh sách Sinh viên của Lớp</h2>

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

            <a href="list_lophp.php?hocki_id=<?php echo htmlspecialchars($hocki_id); ?>" class="back-button">
                <i class="fas fa-arrow-alt-circle-left"></i> Quay lại Danh sách Lớp
            </a>

            <?php if ($lop_hoc_phan_info) : ?>
                <div class="class-info">
                    <p><strong>Lớp học phần:</strong> <?php echo htmlspecialchars($lop_hoc_phan_info['ten_lop_hocphan']); ?></p>
                    <p><strong>Môn học:</strong> <?php echo htmlspecialchars($lop_hoc_phan_info['ten_mon']); ?> (<?php echo htmlspecialchars($lop_hoc_phan_info['so_tin_chi']); ?> tín chỉ)</p>
                    <p><strong>Học kỳ:</strong> <?php echo htmlspecialchars($lop_hoc_phan_info['ten_hocki'] . ' - ' . $lop_hoc_phan_info['nam_hoc']); ?></p>
                    <p><strong>Phòng học:</strong> <?php echo htmlspecialchars($lop_hoc_phan_info['ten_phong']); ?></p>
                    <p><strong>Giảng viên:</strong> <?php echo htmlspecialchars($lop_hoc_phan_info['ten_giang_vien']); ?></p>
                    <p><strong>Sĩ số hiện tại:</strong> <?php echo htmlspecialchars($lop_hoc_phan_info['si_so_hien_tai'] ?? '0'); ?> / <?php echo htmlspecialchars($lop_hoc_phan_info['si_so_toi_da']); ?></p>
                    <p><strong>Trạng thái lớp:</strong>
                        <span class="lop-hp-trangthai
                            <?php
                            switch ($lop_hoc_phan_info['lop_hp_trangthai']) {
                                case 0: echo 'dang-mo'; break;
                                case 1: echo 'da-dong'; break;
                                case 2: echo 'da-chot'; break;
                                default: echo ''; break;
                            }
                            ?>">
                            <?php
                            switch ($lop_hoc_phan_info['lop_hp_trangthai']) {
                                case 0: echo 'Đang mở'; break;
                                case 1: echo 'Đã đóng'; break;
                                case 2: echo 'Đã chốt'; break;
                                default: echo 'Không xác định'; break;
                            }
                            ?>
                        </span>
                    </p>
                </div>

                <div class="student-list">
                    <h3>Danh sách sinh viên đã đăng ký</h3>
                    <table class="styled-table">
                        <thead>
                            <tr>
                                <th>STT</th>
                                <th>Mã Sinh viên</th>
                                <th>Tên Sinh viên</th>
                                <th>Email</th>
                                <th>Số điện thoại</th>
                                <th>Ngày đăng ký</th>
                                </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($danh_sach_sinh_vien)) : ?>
                                <?php $stt = 1; ?>
                                <?php foreach ($danh_sach_sinh_vien as $sv) : ?>
                                    <tr>
                                        <td><?php echo $stt++; ?></td>
                                        <td><?php echo htmlspecialchars($sv['ma_tk']); ?></td>
                                        <td><?php echo htmlspecialchars($sv['ten_sinh_vien']); ?></td>
                                        <td><?php echo htmlspecialchars($sv['email']); ?></td>
                                        <td><?php echo htmlspecialchars($sv['sdt']); ?></td>
                                        <td><?php echo htmlspecialchars($sv['ngay_dang_ky']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="6">Lớp học phần này hiện chưa có sinh viên nào đăng ký.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
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