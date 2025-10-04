<?php
// PHP_SESSION_NONE kiểm tra nếu session chưa được bắt đầu
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Đường dẫn tương đối từ admin/ ra thư mục gốc dkhp/
include '../conn.php';
include '../function.php';

// Gọi hàm kiểm tra quyền Admin
check_login();   // Đảm bảo người dùng đã đăng nhập
check_admin();
// Đường dẫn gốc của trang web (cho các liên kết CSS, JS, hình ảnh)
$base_url = '/dkhp/'; 

$message = ''; // Thông báo thành công/lỗi
$message_type = ''; // Loại thông báo (success, error)

// --- Xử lý xóa khoa ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $ma_khoa_to_delete = intval($_GET['id']);

    // Kiểm tra xem có sinh viên hoặc giảng viên nào thuộc khoa này không
    $check_related_stmt = $conn->prepare("SELECT 
                                            (SELECT COUNT(*) FROM taikhoan WHERE ma_khoa = ?) +
                                            (SELECT COUNT(*) FROM monhoc WHERE ma_khoa = ?) AS count_related");
    if ($check_related_stmt === false) {
        $_SESSION['message'] = "Lỗi chuẩn bị truy vấn kiểm tra liên kết: " . $conn->error;
        $_SESSION['message_type'] = "error";
    } else {
        $check_related_stmt->bind_param("ii", $ma_khoa_to_delete, $ma_khoa_to_delete);
        $check_related_stmt->execute();
        $check_related_stmt->bind_result($count_related);
        $check_related_stmt->fetch();
        $check_related_stmt->close();

        if ($count_related > 0) {
            $_SESSION['message'] = "Không thể xóa khoa này vì có tài khoản hoặc môn học đang thuộc khoa này. Vui lòng di chuyển hoặc xóa các mục liên quan trước.";
            $_SESSION['message_type'] = "error";
        } else {
            // Tiến hành xóa nếu không có liên kết
            $stmt_delete = $conn->prepare("DELETE FROM khoa WHERE ma_khoa = ?");
            if ($stmt_delete === false) {
                $_SESSION['message'] = "Lỗi chuẩn bị truy vấn xóa: " . $conn->error;
                $_SESSION['message_type'] = "error";
            } else {
                $stmt_delete->bind_param("i", $ma_khoa_to_delete);
                if ($stmt_delete->execute()) {
                    $_SESSION['message'] = "Khoa có mã: **" . htmlspecialchars($ma_khoa_to_delete) . "** đã được xóa thành công!";
                    $_SESSION['message_type'] = "success";
                } else {
                    $_SESSION['message'] = "Lỗi khi xóa khoa: " . $stmt_delete->error;
                    $_SESSION['message_type'] = "error";
                }
                $stmt_delete->close();
            }
        }
    }
    header("Location: khoa.php");
    exit();
}

// --- Lấy thông báo từ session (nếu có) ---
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'] ?? 'success'; // Mặc định là success nếu không có type
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// --- Logic Tìm kiếm và Phân trang ---
$search_term = $_GET['search'] ?? '';

// Khởi tạo các biến cho phân trang
$records_per_page = 10; // Số bản ghi mỗi trang
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// Bắt đầu truy vấn SQL cho dữ liệu
$sql_data = "SELECT ma_khoa, ten_khoa FROM khoa WHERE 1=1"; 

// Bắt đầu truy vấn SQL để đếm tổng số bản ghi (cho phân trang)
$sql_count = "SELECT COUNT(ma_khoa) FROM khoa WHERE 1=1";

$params = [];
$types = '';

// Thêm điều kiện tìm kiếm cho cả 2 câu truy vấn
if (!empty($search_term)) {
    $sql_data .= " AND ten_khoa LIKE ?";
    $sql_count .= " AND ten_khoa LIKE ?";
    $search_param = '%' . $search_term . '%';
    $params[] = $search_param;
    $types .= 's';
}

// Đếm tổng số bản ghi
$stmt_count = $conn->prepare($sql_count);
if ($stmt_count === false) {
    die("Lỗi chuẩn bị truy vấn đếm: " . $conn->error . " SQL: " . $sql_count);
}
if (!empty($params)) {
    $stmt_count->bind_param($types, ...$params); 
}
$stmt_count->execute();
$stmt_count->bind_result($total_records);
$stmt_count->fetch();
$stmt_count->close();

$total_pages = ceil($total_records / $records_per_page);

// Thêm LIMIT và OFFSET cho câu truy vấn dữ liệu chính
$sql_data .= " ORDER BY ten_khoa ASC LIMIT ?, ?"; // Sắp xếp theo tên khoa

// Chuẩn bị và thực thi truy vấn chính
$stmt_select = $conn->prepare($sql_data);

if ($stmt_select === false) {
    die("Lỗi chuẩn bị truy vấn dữ liệu khoa: " . $conn->error . " SQL: " . $sql_data);
}

// Tạo mảng params mới cho stmt_select vì có thêm 2 tham số LIMIT
$params_select = $params;
$params_select[] = $offset;
$params_select[] = $records_per_page;
$types_select = $types . 'ii'; // Thêm 'ii' cho LIMIT và OFFSET

if (!empty($params_select)) {
    $stmt_select->bind_param($types_select, ...$params_select); 
}
$stmt_select->execute();
$result = $stmt_select->get_result();
$khoas = $result->fetch_all(MYSQLI_ASSOC);
$stmt_select->close();

$conn->close(); // Đóng kết nối CSDL
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Khoa - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="<?php echo $base_url; ?>style.css"> 
    <link rel="stylesheet" href="<?php echo $base_url; ?>admin/css/menubar.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>admin/css/mainlist.css"> 
</head>
<body>
    <?php 
    // Include header và menubar
    include '../header.php'; // dkhp/header.php
    include 'menubar.php';   // dkhp/admin/menubar.php
    ?>

    <main>
        <div class="list-container">
            <h2>Danh sách Khoa</h2>

            <?php if (!empty($message)) : ?>
                <div class="alert <?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="action-bar">
                <form method="GET" action="khoa.php" class="search-form">
                    <input type="text" name="search" placeholder="Tìm kiếm theo tên khoa..." value="<?php echo htmlspecialchars($search_term); ?>">
                    
                    <button type="submit"><i class="fas fa-search"></i> Tìm kiếm</button>
                    <?php if (!empty($search_term)) : ?>
                        <a href="khoa.php" class="btn-secondary"><i class="fas fa-undo"></i> Reset</a>
                    <?php endif; ?>
                </form>
                <a href="them_khoa.php" class="btn-add"><i class="fas fa-plus"></i> Thêm khoa mới</a>
            </div>

            <div class="scroll-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Mã Khoa</th>
                            <th>Tên Khoa</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($khoas)) : ?>
                        <?php foreach ($khoas as $khoa) : ?>
                            <tr>
                                <td><?php echo htmlspecialchars($khoa['ma_khoa']); ?></td>
                                <td><?php echo htmlspecialchars($khoa['ten_khoa']); ?></td>
                                <td class="data-action-buttons">
                                    <a class="btn-edit" href="suakhoa.php?id=<?php echo htmlspecialchars($khoa['ma_khoa']); ?>"><i class="fas fa-edit"></i> Sửa</a>
                                    <a class="btn-delete" href="khoa.php?action=delete&id=<?php echo htmlspecialchars($khoa['ma_khoa']); ?>" onclick="return confirm('Bạn có chắc chắn muốn xóa khoa <?php echo htmlspecialchars($khoa['ten_khoa']); ?> (Mã: <?php echo htmlspecialchars($khoa['ma_khoa']); ?>) không? Lưu ý: Việc xóa khoa có thể ảnh hưởng đến các tài khoản hoặc môn học liên quan!');"><i class="fas fa-trash-alt"></i> Xóa</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="3" style="text-align:center;">Không tìm thấy khoa nào.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                <?php if ($total_pages > 1) : ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                        <?php 
                        $pagination_link = "khoa.php?page=" . $i;
                        // Giữ lại tham số tìm kiếm khi chuyển trang
                        if (!empty($search_term)) {
                            $pagination_link .= '&search=' . urlencode($search_term);
                        }
                        ?>
                        <a href="<?php echo htmlspecialchars($pagination_link); ?>" 
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
            // JS để đóng thông báo (sử dụng class 'alert' từ CSS bạn cung cấp)
            document.querySelectorAll('.alert').forEach(alertDiv => {
                if (alertDiv.innerHTML.trim() !== "") {
                    setTimeout(() => {
                        alertDiv.style.display = 'none';
                    }, 5000); // Ẩn sau 5 giây
                }
            });

            // ************************************************************
            // LƯU Ý QUAN TRỌNG:
            // Đoạn script cho menubar (sidebar toggle và accordion)
            // ĐÃ ĐƯỢC CHỨA TRONG FILE menubar.php HOẶC MỘT FILE JS RIÊNG ĐƯỢC INCLUDE.
            // KHÔNG NÊN THÊM LẠI VÀO ĐÂY ĐỂ TRÁNH TRÙNG LẶP VÀ GÂY LỖI.
            // ************************************************************
        });
    </script>
</body> 
</html>