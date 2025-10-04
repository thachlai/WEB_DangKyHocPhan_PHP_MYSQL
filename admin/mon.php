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

// --- Xử lý Xóa Môn học ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_mon_to_delete = intval($_GET['id']);

    // **QUAN TRỌNG:** Kiểm tra xem có lớp học phần nào sử dụng môn này không trước khi xóa
    // Nếu có, không cho phép xóa để tránh lỗi khóa ngoại.
    $check_lop_hp_stmt = $conn->prepare("SELECT COUNT(*) FROM lop_hocphan WHERE id_mon = ?");
    if ($check_lop_hp_stmt === false) {
        $_SESSION['message'] = "Lỗi chuẩn bị truy vấn kiểm tra lớp học phần: " . $conn->error;
        $_SESSION['message_type'] = "error";
    } else {
        $check_lop_hp_stmt->bind_param("i", $id_mon_to_delete);
        $check_lop_hp_stmt->execute();
        $check_lop_hp_stmt->bind_result($count_lop_hp);
        $check_lop_hp_stmt->fetch();
        $check_lop_hp_stmt->close();

        if ($count_lop_hp > 0) {
            $_SESSION['message'] = "Không thể xóa môn học này vì có **" . htmlspecialchars($count_lop_hp) . "** lớp học phần đang sử dụng. Vui lòng xóa các lớp học phần liên quan trước.";
            $_SESSION['message_type'] = "error";
        } else {
            // Nếu không có lớp học phần nào sử dụng, tiến hành xóa môn học
            $stmt_delete = $conn->prepare("DELETE FROM mon WHERE id_mon = ?");
            if ($stmt_delete === false) {
                $_SESSION['message'] = "Lỗi chuẩn bị truy vấn xóa: " . $conn->error;
                $_SESSION['message_type'] = "error";
            } else {
                $stmt_delete->bind_param("i", $id_mon_to_delete);
                if ($stmt_delete->execute()) {
                    $_SESSION['message'] = "Môn học có mã: **" . htmlspecialchars($id_mon_to_delete) . "** đã được xóa thành công!";
                    $_SESSION['message_type'] = "success";
                } else {
                    $_SESSION['message'] = "Lỗi khi xóa môn học: " . $stmt_delete->error;
                    $_SESSION['message_type'] = "error";
                }
                $stmt_delete->close();
            }
        }
    }
    header("Location: mon.php");
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

$search_term = trim($_GET['search'] ?? '');
$filter_khoa = isset($_GET['khoa_filter']) ? intval($_GET['khoa_filter']) : 0; // Đảm bảo là số nguyên

$where_clauses = ["1=1"]; // Bắt đầu với điều kiện luôn đúng để dễ dàng nối AND
$params = [];
$param_types = "";

if (!empty($search_term)) {
    // Tìm kiếm cả tên môn và tên khoa
    $where_clauses[] = "(m.ten_mon LIKE ? OR k.ten_khoa LIKE ?)";
    $params[] = '%' . $search_term . '%';
    $params[] = '%' . $search_term . '%';
    $param_types .= "ss";
}
if ($filter_khoa > 0) { // Chỉ thêm điều kiện nếu có khoa được chọn (ID > 0)
    $where_clauses[] = "m.id_khoa = ?";
    $params[] = $filter_khoa;
    $param_types .= "i";
}

$where_sql = implode(" AND ", $where_clauses);

// Truy vấn SQL cho dữ liệu môn học
// Đã thêm so_tin_chi và gia_tin_chi
$sql_data = "SELECT
                m.id_mon,
                m.ten_mon,
                m.so_tin_chi,
                m.gia_tin_chi,
                k.ten_khoa
            FROM
                mon m
            LEFT JOIN
                khoa k ON m.id_khoa = k.ma_khoa
            WHERE " . $where_sql . "
            ORDER BY
                k.ten_khoa ASC, m.ten_mon ASC
            LIMIT ?, ?";

// Truy vấn SQL để đếm tổng số bản ghi (cho phân trang)
$sql_count = "SELECT COUNT(m.id_mon)
              FROM
                mon m
              LEFT JOIN
                khoa k ON m.id_khoa = k.ma_khoa
              WHERE " . $where_sql;


// Đếm tổng số bản ghi
$stmt_count = $conn->prepare($sql_count);
if ($stmt_count === false) {
    die("Lỗi chuẩn bị truy vấn đếm: " . $conn->error . " SQL: " . $sql_count);
}
if (!empty($params)) {
    // Sử dụng call_user_func_array để bind_param với mảng tham số động
    $bind_params_count = array_merge([$param_types], $params);
    call_user_func_array([$stmt_count, 'bind_param'], ref_values($bind_params_count));
}
$stmt_count->execute();
$stmt_count->bind_result($total_records);
$stmt_count->fetch();
$stmt_count->close();

$total_pages = ceil($total_records / $records_per_page);

// Chuẩn bị và thực thi truy vấn chính để lấy dữ liệu
$stmt_select = $conn->prepare($sql_data);

if ($stmt_select === false) {
    die("Lỗi chuẩn bị truy vấn dữ liệu môn học: " . $conn->error . " SQL: " . $sql_data);
}

// Thêm offset và records_per_page vào params cuối cùng cho truy vấn dữ liệu
$params_select = array_merge($params, [$offset, $records_per_page]);
$param_types_select = $param_types . "ii"; // Thêm 2 kiểu 'i' cho offset và records_per_page

// Sử dụng call_user_func_array để bind_param với mảng tham số động
if (!empty($params_select)) {
    $bind_params_select = array_merge([$param_types_select], $params_select);
    call_user_func_array([$stmt_select, 'bind_param'], ref_values($bind_params_select));
}

$stmt_select->execute();
$result = $stmt_select->get_result();
$mons = $result->fetch_all(MYSQLI_ASSOC);
$stmt_select->close();

// Get list of departments from the database (for the filter dropdown)
$sql_khoa_filter = "SELECT ma_khoa, ten_khoa FROM khoa ORDER BY ten_khoa";
$result_khoa_filter = $conn->query($sql_khoa_filter);
$khoa_filter_options = [];
if ($result_khoa_filter) {
    while ($row = $result_khoa_filter->fetch_assoc()) {
        $khoa_filter_options[] = $row;
    }
}

$conn->close();

// Function to pass parameters by reference for call_user_func_array
function ref_values($arr){
    $refs = array();
    foreach($arr as $key => $value)
        $refs[$key] = &$arr[$key];
    return $refs;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Môn học - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="<?php echo $base_url; ?>style.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>admin/css/menubar.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>admin/css/mainlist.css">
</head>
<body>
    <?php
    include '../header.php';
    include 'menubar.php';
    ?>

    <main>
        <div class="list-container">
            <h2>Danh sách Môn học</h2>

            <?php if (!empty($message)) : ?>
                <div class="alert <?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="action-bar">
                <form method="GET" action="mon.php" class="search-filter-form">
                    <input type="text" name="search" placeholder="Tìm kiếm tên môn, khoa..." value="<?php echo htmlspecialchars($search_term); ?>">
                    <select name="khoa_filter">
                        <option value="0">-- Lọc theo Khoa --</option>
                        <?php foreach ($khoa_filter_options as $khoa) : ?>
                            <option value="<?php echo htmlspecialchars($khoa['ma_khoa']); ?>"
                                <?php echo ($filter_khoa == $khoa['ma_khoa']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($khoa['ten_khoa']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit"><i class="fas fa-filter"></i> Lọc & Tìm</button>
                    <?php if (!empty($search_term) || $filter_khoa > 0) : ?>
                        <a href="mon.php" class="btn-clear-filter"><i class="fas fa-sync"></i> Xóa lọc</a>
                    <?php endif; ?>
                </form>
                <a href="themmon.php" class="btn-add"><i class="fas fa-plus"></i> Thêm Môn học mới</a>
            </div>

            <div class="scroll-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID Môn</th>
                            <th>Tên Môn</th>
                            <th>Khoa</th>
                            <th>Số Tín chỉ</th> <th>Giá Tín chỉ</th> <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($mons)) : ?>
                        <?php foreach ($mons as $mon) : ?>
                            <tr>
                                <td><?php echo htmlspecialchars($mon['id_mon']); ?></td>
                                <td><?php echo htmlspecialchars($mon['ten_mon']); ?></td>
                                <td><?php echo htmlspecialchars($mon['ten_khoa'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($mon['so_tin_chi'] ?? 0); ?></td>
                                <td><?php echo number_format(htmlspecialchars($mon['gia_tin_chi'] ?? 0), 0, ',', '.'); ?> VNĐ</td>
                                <td class="data-action-buttons">
                                    <a class="btn-edit" href="suamon.php?id=<?php echo htmlspecialchars($mon['id_mon']); ?>"><i class="fas fa-edit"></i> Sửa</a>
                                    <a class="btn-delete" href="mon.php?action=delete&id=<?php echo htmlspecialchars($mon['id_mon']); ?>" onclick="return confirm('Bạn có chắc chắn muốn xóa môn học <?php echo htmlspecialchars($mon['ten_mon']); ?> (ID: <?php echo htmlspecialchars($mon['id_mon']); ?>) không? Thao tác này có thể ảnh hưởng đến các lớp học phần liên quan.');"><i class="fas fa-trash-alt"></i> Xóa</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="6" style="text-align:center;">Không tìm thấy môn học nào.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                <?php if ($total_pages > 1) : ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                        <?php
                        $pagination_params = $_GET; // Lấy tất cả các tham số hiện có
                        $pagination_params['page'] = $i; // Cập nhật trang hiện tại
                        $pagination_query = http_build_query($pagination_params); // Tạo lại chuỗi query
                        ?>
                        <a href="mon.php?<?php echo htmlspecialchars($pagination_query); ?>"
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
            // JS to hide alerts automatically
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