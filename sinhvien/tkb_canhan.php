<?php
// Luôn bắt đầu session ở đầu file
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../conn.php';
include '../function.php'; 

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
$hocki_options = [];
$sql_hocki = "SELECT id_hocki, ten_hocki, nam_hoc FROM hocki WHERE trangthai = 0 ORDER BY nam_hoc DESC, ten_hocki ASC";
$result_hocki = $conn->query($sql_hocki);
if ($result_hocki && $result_hocki->num_rows > 0) {
    while ($row = $result_hocki->fetch_assoc()) {
        $hocki_options[] = $row;
    }
    // Nếu chưa chọn học kỳ nào, hoặc học kỳ đã chọn không còn hoạt động, chọn học kỳ đầu tiên mặc định
    if ($selected_hocki_id === 0 && !empty($hocki_options)) {
        $selected_hocki_id = $hocki_options[0]['id_hocki'];
    }
} else {
    $error = "Hiện không có học kỳ nào khả dụng để xem thời khóa biểu.";
}


// --- Lấy dữ liệu Thời khóa biểu của sinh viên ---
$tkb_data = []; // Mảng để lưu trữ dữ liệu thời khóa biểu (ví dụ: $tkb_data[thứ][tiết] = thông tin lớp)
$lop_hoc_phan_detail = []; // Mảng để lưu thông tin chi tiết của các lớp học phần đã đăng ký

if ($selected_hocki_id > 0 && $sinh_vien_id > 0) {
    $sql_tkb = "SELECT
                    lhp.id_lop_hp,
                    lhp.ten_lop_hocphan,
                    m.ten_mon,
                    tk.ten_taikhoan AS ten_giang_vien,
                    ph.ten_phong,
                    lh.ngay_trong_tuan,
                    lh.tiet_bat_dau,
                    lh.tiet_ket_thuc
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
                JOIN
                    lich_hoc lh ON lhp.id_lop_hp = lh.id_lop_hp
                WHERE
                    dkhp.id_taikhoan = ? AND lhp.id_hocki = ?
                ORDER BY
                    lh.ngay_trong_tuan ASC, lh.tiet_bat_dau ASC";

    $stmt_tkb = $conn->prepare($sql_tkb);
    if ($stmt_tkb === false) {
        $error .= "Lỗi chuẩn bị truy vấn thời khóa biểu: " . $conn->error;
    } else {
        $stmt_tkb->bind_param("ii", $sinh_vien_id, $selected_hocki_id);
        $stmt_tkb->execute();
        $result_tkb = $stmt_tkb->get_result();

        if ($result_tkb->num_rows > 0) {
            while ($row = $result_tkb->fetch_assoc()) {
                // Lưu chi tiết lớp học phần để dùng sau này (ví dụ: tooltip, chi tiết)
                $lop_hoc_phan_detail[$row['id_lop_hp']] = [
                    'ten_lop_hocphan' => $row['ten_lop_hocphan'],
                    'ten_mon' => $row['ten_mon'],
                    'ten_giang_vien' => $row['ten_giang_vien'],
                    'ten_phong' => $row['ten_phong']
                ];

                // Đổ dữ liệu vào mảng TKB
                for ($tiet = $row['tiet_bat_dau']; $tiet <= $row['tiet_ket_thuc']; $tiet++) {
                    // Cần kiểm tra xem key có tồn tại trước khi thêm, tránh undefined index
                    if (!isset($tkb_data[$row['ngay_trong_tuan']])) {
                        $tkb_data[$row['ngay_trong_tuan']] = [];
                    }
                    if (!isset($tkb_data[$row['ngay_trong_tuan']][$tiet])) {
                        $tkb_data[$row['ngay_trong_tuan']][$tiet] = [];
                    }
                    $tkb_data[$row['ngay_trong_tuan']][$tiet][] = $row['id_lop_hp'];
                }
            }
        } else {
            $error .= "Bạn chưa đăng ký lớp học phần nào trong học kỳ này hoặc không có lịch học cho các lớp đã đăng ký.";
        }
        $stmt_tkb->close();
    }
}

// Định nghĩa các tiết học và ngày trong tuần
$tiet_hoc = [
    1 => 'Tiết 1', 2 => 'Tiết 2', 3 => 'Tiết 3', 4 => 'Tiết 4', 5 => 'Tiết 5',
    6 => 'Tiết 6', 7 => 'Tiết 7', 8 => 'Tiết 8', 9 => 'Tiết 9', 10 => 'Tiết 10',
    11 => 'Tiết 11', 12 => 'Tiết 12'
];

$ngay_trong_tuan = [
    1 => 'Thứ 2', 2 => 'Thứ 3', 3 => 'Thứ 4', 4 => 'Thứ 5', 5 => 'Thứ 6',
    6 => 'Thứ 7', 7 => 'Chủ Nhật'
];

$conn->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thời khóa biểu cá nhân</title>
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
        .filter-section select {
            padding: 8px 12px;
            border-radius: 5px;
            border: 1px solid #ccc;
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
        .alert.info { /* Thêm kiểu cho alert info */
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .tkb-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .tkb-table th, .tkb-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
            vertical-align: top;
        }
        .tkb-table th {
            background-color: #007bff;
            color: white;
        }
        .tkb-table td {
            background-color: #fefefe;
            min-width: 100px; /* Đảm bảo đủ rộng cho nội dung */
            height: 80px; /* Chiều cao mỗi ô tiết */
        }
        .tkb-cell-content {
            font-size: 0.85em;
            line-height: 1.3;
        }
        .tkb-cell-content strong {
            display: block;
            margin-bottom: 3px;
        }
        .no-class {
            background-color: #f0f0f0;
            color: #aaa;
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
            <h2>Thời khóa biểu cá nhân</h2>

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
                <form method="GET" id="hockiTKBForm">
                    <label for="hocki_select_tkb">Chọn Học kỳ:</label>
                    <select id="hocki_select_tkb" name="hocki_id" onchange="document.getElementById('hockiTKBForm').submit()">
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
                <p class="alert error">Vui lòng chọn một học kỳ để xem thời khóa biểu.</p>
            <?php elseif (empty($tkb_data)) : ?>
                <p class="alert info">Không có dữ liệu thời khóa biểu cho học kỳ này.</p>
            <?php else : ?>
                <table class="tkb-table">
                    <thead>
                        <tr>
                            <th>Tiết / Thứ</th>
                            <?php foreach ($ngay_trong_tuan as $thu) : ?>
                                <th><?php echo $thu; ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tiet_hoc as $tiet_num => $tiet_name) : ?>
                            <tr>
                                <td><?php echo $tiet_name; ?></td>
                                <?php for ($thu_num = 1; $thu_num <= 7; $thu_num++) : ?>
                                    <td class="<?php echo empty($tkb_data[$thu_num][$tiet_num]) ? 'no-class' : ''; ?>">
                                        <?php
                                        if (!empty($tkb_data[$thu_num][$tiet_num])) {
                                            $classes_at_this_time = $tkb_data[$thu_num][$tiet_num];
                                            foreach ($classes_at_this_time as $class_id) {
                                                if (isset($lop_hoc_phan_detail[$class_id])) {
                                                    $detail = $lop_hoc_phan_detail[$class_id];
                                                    echo "<div class='tkb-cell-content'>";
                                                    echo "<strong>" . htmlspecialchars($detail['ten_mon']) . "</strong>";
                                                    echo "<span>" . htmlspecialchars($detail['ten_phong']) . "</span><br>";
                                                    echo "<span>" . htmlspecialchars($detail['ten_giang_vien']) . "</span>";
                                                    echo "</div>";
                                                }
                                            }
                                        } else {
                                            echo "&nbsp;"; // Ô trống
                                        }
                                        ?>
                                    </td>
                                <?php endfor; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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