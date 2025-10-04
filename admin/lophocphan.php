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

$message = '';
$message_type = '';

// --- Xử lý Xóa Lớp Học Phần ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_lop_hp_to_delete = intval($_GET['id']);

    // Bước 1: Kiểm tra xem có sinh viên nào đã đăng ký lớp này không
    $check_dangky_stmt = $conn->prepare("SELECT COUNT(*) FROM dangky_hocphan WHERE id_lop_hp = ?");
    if ($check_dangky_stmt === false) {
        $_SESSION['message'] = "Lỗi chuẩn bị truy vấn kiểm tra đăng ký: " . $conn->error;
        $_SESSION['message_type'] = "error";
    } else {
        $check_dangky_stmt->bind_param("i", $id_lop_hp_to_delete);
        $check_dangky_stmt->execute();
        $check_dangky_stmt->bind_result($count_dangky);
        $check_dangky_stmt->fetch();
        $check_dangky_stmt->close();

        if ($count_dangky > 0) {
            $_SESSION['message'] = "Không thể xóa lớp học phần này vì có **" . htmlspecialchars($count_dangky) . "** sinh viên đang đăng ký. Vui lòng hủy đăng ký cho sinh viên trước.";
            $_SESSION['message_type'] = "error";
        } else {
            // Bước 2: Xóa lịch học liên quan trước (để tránh lỗi khóa ngoại nếu không có ON DELETE CASCADE)
            // Nếu bạn đã thiết lập ON DELETE CASCADE cho lich_hoc_ibfk_1 trên bảng lich_hoc
            // thì không cần bước này, nhưng làm vậy sẽ an toàn hơn.
            $stmt_delete_lich = $conn->prepare("DELETE FROM lich_hoc WHERE id_lop_hp = ?");
            if ($stmt_delete_lich === false) {
                 $_SESSION['message'] = "Lỗi chuẩn bị truy vấn xóa lịch học: " . $conn->error;
                 $_SESSION['message_type'] = "error";
                 header("Location: lophocphan.php");
                 exit();
            }
            $stmt_delete_lich->bind_param("i", $id_lop_hp_to_delete);
            $stmt_delete_lich->execute();
            $stmt_delete_lich->close();

            // Bước 3: Xóa lớp học phần
            $stmt_delete = $conn->prepare("DELETE FROM lop_hocphan WHERE id_lop_hp = ?");
            if ($stmt_delete === false) {
                $_SESSION['message'] = "Lỗi chuẩn bị truy vấn xóa: " . $conn->error;
                $_SESSION['message_type'] = "error";
            } else {
                $stmt_delete->bind_param("i", $id_lop_hp_to_delete);
                if ($stmt_delete->execute()) {
                    $_SESSION['message'] = "Lớp học phần có ID: **" . htmlspecialchars($id_lop_hp_to_delete) . "** đã được xóa thành công!";
                    $_SESSION['message_type'] = "success";
                } else {
                    $_SESSION['message'] = "Lỗi khi xóa lớp học phần: " . $stmt_delete->error;
                    $_SESSION['message_type'] = "error";
                }
                $stmt_delete->close();
            }
        }
    }
    header("Location: lophocphan.php");
    exit();
}

// --- Lấy thông báo từ session (nếu có) ---
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'] ?? 'success';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// --- Logic Lọc và Phân Trang ---
$records_per_page = 10;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

$search_query = trim($_GET['search'] ?? '');
$filter_mon = isset($_GET['filter_mon']) ? intval($_GET['filter_mon']) : 0;
$filter_hocki = isset($_GET['filter_hocki']) ? intval($_GET['filter_hocki']) : 0;
$filter_trangthai = isset($_GET['filter_trangthai']) ? $_GET['filter_trangthai'] : ''; // '' để bắt 'Tất cả' hoặc không có giá trị

$where_clauses = [];
$params = [];
$param_types = "";

if (!empty($search_query)) {
    $where_clauses[] = "(lhp.ten_lop_hocphan LIKE ? OR m.ten_mon LIKE ? OR tk.ten_taikhoan LIKE ? OR ph.ten_phong LIKE ?)";
    $params[] = '%' . $search_query . '%';
    $params[] = '%' . $search_query . '%';
    $params[] = '%' . $search_query . '%';
    $params[] = '%' . $search_query . '%';
    $param_types .= "ssss";
}
if ($filter_mon > 0) {
    $where_clauses[] = "lhp.id_mon = ?";
    $params[] = $filter_mon;
    $param_types .= "i";
}
if ($filter_hocki > 0) {
    $where_clauses[] = "lhp.id_hocki = ?";
    $params[] = $filter_hocki;
    $param_types .= "i";
}
// Thêm điều kiện lọc trạng thái (chấp nhận cả '0', '1', '2')
if ($filter_trangthai !== '') {
    $where_clauses[] = "lhp.trangthai = ?";
    $params[] = intval($filter_trangthai);
    $param_types .= "i";
}

$where_sql = count($where_clauses) > 0 ? " WHERE " . implode(" AND ", $where_clauses) : "";

// Truy vấn SQL cho dữ liệu lớp học phần
$sql_data = "SELECT
                lhp.id_lop_hp,
                lhp.ten_lop_hocphan,
                m.ten_mon,
                m.so_tin_chi,
                m.gia_tin_chi,
                tk.ten_taikhoan AS ten_gv,
                hk.ten_hocki,
                hk.nam_hoc,
                lhp.si_so_toi_da,
                ph.ten_phong,
                lhp.trangthai,
                -- Lấy sĩ số hiện tại bằng cách đếm số lượng sinh viên đăng ký lớp này
                (SELECT COUNT(*) FROM dangky_hocphan WHERE id_lop_hp = lhp.id_lop_hp) AS si_so_hien_tai,
                (SELECT COUNT(*) FROM lich_hoc WHERE id_lop_hp = lhp.id_lop_hp) AS so_buoi_hoc
            FROM
                lop_hocphan lhp
            LEFT JOIN
                mon m ON lhp.id_mon = m.id_mon
            LEFT JOIN
                taikhoan tk ON lhp.id_taikhoan = tk.ma_tk
            LEFT JOIN
                hocki hk ON lhp.id_hocki = hk.id_hocki
            LEFT JOIN
                phong_hoc ph ON lhp.id_phong = ph.id_phong
            " . $where_sql . "
            ORDER BY
                hk.nam_hoc DESC, hk.id_hocki DESC, m.ten_mon ASC, lhp.ten_lop_hocphan ASC
            LIMIT ?, ?";

// Truy vấn SQL để đếm tổng số bản ghi (cho phân trang)
$sql_count = "SELECT COUNT(lhp.id_lop_hp)
              FROM
                lop_hocphan lhp
              LEFT JOIN
                mon m ON lhp.id_mon = m.id_mon
              LEFT JOIN
                taikhoan tk ON lhp.id_taikhoan = tk.ma_tk
              LEFT JOIN
                hocki hk ON lhp.id_hocki = hk.id_hocki
              LEFT JOIN
                phong_hoc ph ON lhp.id_phong = ph.id_phong " . $where_sql;


// Đếm tổng số bản ghi
$stmt_count = $conn->prepare($sql_count);
if ($stmt_count === false) {
    die("Lỗi chuẩn bị truy vấn đếm: " . $conn->error . " SQL: " . $sql_count);
}
if (!empty($params)) {
    $stmt_count->bind_param($param_types, ...$params);
}
$stmt_count->execute();
$stmt_count->bind_result($total_records);
$stmt_count->fetch();
$stmt_count->close();

$total_pages = ceil($total_records / $records_per_page);

// Chuẩn bị và thực thi truy vấn chính
$stmt_select = $conn->prepare($sql_data);

if ($stmt_select === false) {
    die("Lỗi chuẩn bị truy vấn dữ liệu lớp học phần: " . $conn->error . " SQL: " . $sql_data);
}

// Thêm offset và records_per_page vào params cuối cùng
$params_with_limit = array_merge($params, [$offset, $records_per_page]);
$param_types_with_limit = $param_types . "ii"; // Thêm 2 kiểu 'i' cho offset và records_per_page

$stmt_select->bind_param($param_types_with_limit, ...$params_with_limit);
$stmt_select->execute();
$result = $stmt_select->get_result();
$lophocphans = $result->fetch_all(MYSQLI_ASSOC);
$stmt_select->close();

// Fetch options for filters (Môn học, Học kỳ)
$sql_mon_filter = "SELECT id_mon, ten_mon FROM mon ORDER BY ten_mon";
$result_mon_filter = $conn->query($sql_mon_filter);
$mon_filter_options = [];
if ($result_mon_filter) {
    while ($row = $result_mon_filter->fetch_assoc()) {
        $mon_filter_options[] = $row;
    }
}

$sql_hocki_filter = "SELECT id_hocki, ten_hocki, nam_hoc FROM hocki ORDER BY nam_hoc DESC, id_hocki DESC";
$result_hocki_filter = $conn->query($sql_hocki_filter);
$hocki_filter_options = [];
if ($result_hocki_filter) {
    while ($row = $result_hocki_filter->fetch_assoc()) {
        $hocki_filter_options[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Lớp Học Phần - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="<?php echo $base_url; ?>style.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>admin/css/menubar.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>admin/css/mainlist.css">
    <style>
        /* Styles cho các nút Lịch học */
        .lich-hoc-action a {
            display: inline-block;
            margin: 2px 0;
            padding: 5px 8px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.9em;
            text-align: center;
        }
        .lich-hoc-action .btn-create-schedule {
            background-color: #007bff; /* Blue */
            color: white;
        }
        .lich-hoc-action .btn-view-schedule {
            background-color: #6c757d; /* Gray */
            color: white;
        }
        /* CSS cho form filter và search */
        .action-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
            align-items: center;
        }
        .search-filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            flex-grow: 1;
        }
        .search-filter-form input[type="text"],
        .search-filter-form select {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .search-filter-form button,
        .action-bar .btn-clear-filter,
        .action-bar .btn-add {
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            color: white;
            text-align: center;
            display: inline-flex; /* Dùng flex để căn giữa icon và text */
            align-items: center;
            justify-content: center;
            gap: 5px; /* Khoảng cách giữa icon và text */
        }
        .search-filter-form button {
            background-color: #28a745; /* Green */
        }
        .action-bar .btn-clear-filter {
            background-color: #ffc107; /* Yellow */
            color: #333;
        }
        .action-bar .btn-add {
            background-color: #007bff; /* Blue */
            margin-left: auto;
        }
        /* Cải thiện hiển thị cột trạng thái */
        .status-badge {
            padding: 4px 8px;
            border-radius: 5px;
            font-weight: bold;
            color: white;
            display: inline-block;
            min-width: 60px; /* Đảm bảo độ rộng tối thiểu */
            text-align: center;
        }
        .status-badge.hien { background-color: #28a745; } /* Green */
        .status-badge.an { background-color: #dc3545; } /* Red */
        .status-badge.chot { background-color: #007bff; } /* Blue for Chốt */

    </style>
</head>
<body>
    <?php
    include '../header.php';
    include 'menubar.php';
    ?>

    <main>
        <div class="list-container">
            <h2>Danh sách Lớp Học Phần</h2>

            <?php if (!empty($message)) : ?>
                <div class="alert <?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="action-bar">
                <form method="GET" class="search-filter-form">
                    <input type="text" name="search" placeholder="Tìm kiếm tên lớp, môn, GV, phòng..." value="<?php echo htmlspecialchars($search_query); ?>">
                    
                    <select name="filter_mon">
                        <option value="0">-- Lọc theo Môn học --</option>
                        <?php foreach ($mon_filter_options as $mon_f) : ?>
                            <option value="<?php echo htmlspecialchars($mon_f['id_mon']); ?>"
                                <?php echo ($filter_mon == $mon_f['id_mon']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($mon_f['ten_mon']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="filter_hocki">
                        <option value="0">-- Lọc theo Học kỳ --</option>
                        <?php foreach ($hocki_filter_options as $hocki_f) : ?>
                            <option value="<?php echo htmlspecialchars($hocki_f['id_hocki']); ?>"
                                <?php echo ($filter_hocki == $hocki_f['id_hocki']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($hocki_f['ten_hocki'] . ' - ' . $hocki_f['nam_hoc']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="filter_trangthai">
                        <option value="">-- Lọc theo Trạng thái --</option>
                        <option value="0" <?php if ($filter_trangthai === '0') echo 'selected'; ?>>Hiện</option>
                        <option value="1" <?php if ($filter_trangthai === '1') echo 'selected'; ?>>Ẩn</option>
                        <option value="2" <?php if ($filter_trangthai === '2') echo 'selected'; ?>>Đã chốt lớp</option>
                    </select>

                    <button type="submit"><i class="fas fa-filter"></i> Lọc</button>
                    <a href="lophocphan.php" class="btn-clear-filter"><i class="fas fa-sync"></i> Xóa lọc</a>
                </form>
                <a href="themlophocphan.php" class="btn-add"><i class="fas fa-plus"></i> Thêm Lớp HP mới</a>
            </div>

            <div class="scroll-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tên Lớp HP</th>
                            <th>Môn học (Tín chỉ)</th>
                            <th>Giáo viên</th>
                            <th>Học kỳ</th>
                            <th>Sĩ số (Hiện tại/Tối đa)</th>
                            <th>Phòng học</th>
                            <th>Trạng thái</th>
                            <th>Lịch học</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($lophocphans)) : ?>
                        <?php foreach ($lophocphans as $lhp) : ?>
                            <tr>
                                <td><?php echo htmlspecialchars($lhp['id_lop_hp']); ?></td>
                                <td><?php echo htmlspecialchars($lhp['ten_lop_hocphan']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($lhp['ten_mon'] ?? 'N/A'); ?>
                                    (<?php echo htmlspecialchars($lhp['so_tin_chi'] ?? 0); ?> TC)
                                    <br>
                                    <small>(Giá: <?php echo number_format(htmlspecialchars($lhp['gia_tin_chi'] ?? 0), 0, ',', '.'); ?>/TC)</small>
                                </td>
                                <td><?php echo htmlspecialchars($lhp['ten_gv'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars(($lhp['ten_hocki'] ?? 'N/A') . ' - ' . ($lhp['nam_hoc'] ?? 'N/A')); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($lhp['si_so_hien_tai'] ?? 0) . '/' . htmlspecialchars($lhp['si_so_toi_da']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($lhp['ten_phong'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php
                                        $status_text = '';
                                        $status_class = '';
                                        switch ($lhp['trangthai']) {
                                            case 0:
                                                $status_text = 'Hiện';
                                                $status_class = 'hien';
                                                break;
                                            case 1:
                                                $status_text = 'Ẩn';
                                                $status_class = 'an';
                                                break;
                                            case 2:
                                                $status_text = 'Đã chốt lớp';
                                                $status_class = 'chot';
                                                break;
                                            default:
                                                $status_text = 'Không xác định';
                                                $status_class = '';
                                                break;
                                        }
                                    ?>
                                    <span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                </td>
                                <td class="lich-hoc-action">
                                    <?php if ($lhp['so_buoi_hoc'] > 0) : ?>
                                        <a href="quanlylichhoc.php?id_lop_hp=<?php echo htmlspecialchars($lhp['id_lop_hp']); ?>" class="btn-view-schedule">
                                            <i class="fas fa-calendar-alt"></i> Xem Lịch (<?php echo htmlspecialchars($lhp['so_buoi_hoc']); ?>)
                                        </a>
                                    <?php else : ?>
                                        <a href="themlichhoc.php?id_lop_hp=<?php echo htmlspecialchars($lhp['id_lop_hp']); ?>" class="btn-create-schedule">
                                            <i class="fas fa-plus-circle"></i> Tạo Lịch Học
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td class="data-action-buttons">
                                    <a class="btn-edit" href="sualophocphan.php?id=<?php echo htmlspecialchars($lhp['id_lop_hp']); ?>"><i class="fas fa-edit"></i> Sửa</a>
                                    <a class="btn-delete" href="lophocphan.php?action=delete&id=<?php echo htmlspecialchars($lhp['id_lop_hp']); ?>" onclick="return confirm('Bạn có chắc chắn muốn xóa lớp học phần <?php echo htmlspecialchars($lhp['ten_lop_hocphan']); ?> không? (Thao tác này cũng sẽ xóa tất cả đăng ký của sinh viên và lịch học của lớp này!)');"><i class="fas fa-trash-alt"></i> Xóa</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="10" style="text-align:center;">Không tìm thấy lớp học phần nào.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                <?php if ($total_pages > 1) : ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                        <?php
                        $pagination_params = $_GET;
                        $pagination_params['page'] = $i;
                        $pagination_query = http_build_query($pagination_params);
                        ?>
                        <a href="lophocphan.php?<?php echo htmlspecialchars($pagination_query); ?>"
                           class="<?php echo ($i == $current_page) ? 'current-page' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                <?php endif; ?>
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