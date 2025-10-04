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
$search_query = ''; // Biến để lưu trữ từ khóa tìm kiếm

// Lấy thông báo từ session (nếu có)
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'] ?? 'success';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// --- Xử lý Xóa Phòng Học ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_phong_to_delete = intval($_GET['id']);

    // **QUAN TRỌNG:** Kiểm tra xem có lớp học phần nào thuộc phòng học này không
    $check_related_stmt = $conn->prepare("SELECT COUNT(*) FROM lop_hocphan WHERE id_phong = ?");
    if ($check_related_stmt === false) {
        $_SESSION['message'] = "Lỗi chuẩn bị truy vấn kiểm tra liên kết: " . $conn->error;
        $_SESSION['message_type'] = "error";
    } else {
        $check_related_stmt->bind_param("i", $id_phong_to_delete);
        $check_related_stmt->execute();
        $check_related_stmt->bind_result($count_related);
        $check_related_stmt->fetch();
        $check_related_stmt->close();

        if ($count_related > 0) {
            $_SESSION['message'] = "Không thể xóa phòng học này vì có lớp học phần đang sử dụng phòng này. Vui lòng di chuyển hoặc xóa các lớp học phần liên quan trước.";
            $_SESSION['message_type'] = "error";
        } else {
            // Xóa từ bảng phong_hoc
            $stmt_delete = $conn->prepare("DELETE FROM phong_hoc WHERE id_phong = ?");
            if ($stmt_delete === false) {
                $_SESSION['message'] = "Lỗi chuẩn bị truy vấn xóa: " . $conn->error;
                $_SESSION['message_type'] = "error";
            } else {
                $stmt_delete->bind_param("i", $id_phong_to_delete);
                if ($stmt_delete->execute()) {
                    $_SESSION['message'] = "Phòng học có ID: **" . htmlspecialchars($id_phong_to_delete) . "** đã được xóa thành công!";
                    $_SESSION['message_type'] = "success";
                } else {
                    $_SESSION['message'] = "Lỗi khi xóa phòng học: " . $stmt_delete->error;
                    $_SESSION['message_type'] = "error";
                }
                $stmt_delete->close();
            }
        }
    }
    header("Location: phong.php");
    exit();
}

// --- Xử lý Tìm kiếm ---
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_query = $_GET['search'];
}

// --- Logic Phân Trang ---
$records_per_page = 10;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// Xây dựng truy vấn SQL động dựa trên từ khóa tìm kiếm
$sql_data = "SELECT id_phong, ten_phong, suc_chua, loai_phong FROM phong_hoc";
$sql_count = "SELECT COUNT(id_phong) FROM phong_hoc";
$where_clause = '';
$bind_types = '';
$bind_values = []; // Sử dụng mảng này để lưu các giá trị, không phải tham chiếu ban đầu

if (!empty($search_query)) {
    $where_clause = " WHERE ten_phong LIKE ?";
    $bind_types = "s"; // 's' for string
    $bind_values[] = '%' . $search_query . '%'; // Lưu giá trị, không phải tham chiếu
}

$sql_data .= $where_clause . " ORDER BY ten_phong ASC LIMIT ?, ?";
$sql_count .= $where_clause;

// Đếm tổng số bản ghi (có tính đến tìm kiếm)
$stmt_count = $conn->prepare($sql_count);
if ($stmt_count === false) {
    die("Lỗi chuẩn bị truy vấn đếm: " . $conn->error . " SQL: " . $sql_count);
}

if (!empty($bind_values)) {
    // Để bind_param hoạt động với mảng giá trị, bạn cần chuyển chúng thành tham chiếu.
    // Cách này sẽ tạo ra các biến trung gian và truyền tham chiếu của chúng.
    $refs = [];
    foreach ($bind_values as $key => $value) {
        $refs[$key] = &$bind_values[$key];
    }
    // Dùng array_unshift để thêm $bind_types vào đầu mảng $refs
    array_unshift($refs, $bind_types);
    call_user_func_array([$stmt_count, 'bind_param'], $refs);
}

$stmt_count->execute();
$stmt_count->bind_result($total_records);
$stmt_count->fetch();
$stmt_count->close();

$total_pages = ceil($total_records / $records_per_page);

// Chuẩn bị và thực thi truy vấn chính (có tính đến tìm kiếm và phân trang)
$stmt_select = $conn->prepare($sql_data);

if ($stmt_select === false) {
    die("Lỗi chuẩn bị truy vấn dữ liệu phòng học: " . $conn->error . " SQL: " . $sql_data);
}

// Thêm offset và records_per_page vào bind_values, sau đó chuyển tất cả thành tham chiếu
$final_bind_values = array_merge($bind_values, [$offset, $records_per_page]);
$final_bind_types = $bind_types . "ii"; // Thêm 'ii' cho offset và records_per_page

$refs = [];
foreach ($final_bind_values as $key => $value) {
    $refs[$key] = &$final_bind_values[$key]; // Lấy tham chiếu của từng phần tử
}
array_unshift($refs, $final_bind_types); // Thêm chuỗi types vào đầu

call_user_func_array([$stmt_select, 'bind_param'], $refs);

$stmt_select->execute();
$result = $stmt_select->get_result();
$phongs = $result->fetch_all(MYSQLI_ASSOC);
$stmt_select->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Phòng Học - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="<?php echo $base_url; ?>style.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>admin/css/menubar.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>admin/css/mainlist.css">
    <style>
        /* Thêm CSS cho thanh tìm kiếm nếu cần */
        .search-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            align-items: center;
        }
        .search-bar input[type="text"] {
            flex-grow: 1;
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 1em;
        }
        .search-bar button {
            padding: 8px 15px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .search-bar button:hover {
            background-color: #0056b3;
        }
        /* Điều chỉnh action-bar để căn chỉnh tốt hơn */
        .action-bar {
            display: flex;
            justify-content: space-between; /* Đảm bảo nút thêm và search bar không dính vào nhau */
            align-items: center;
            margin-bottom: 20px;
        }
        .pagination a.current-page {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
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
            <h2>Danh sách Phòng Học</h2>

            <?php if (!empty($message)) : ?>
                <div class="alert <?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="action-bar">
                <div class="search-bar">
                    <form action="phong.php" method="GET" style="display: flex; gap: 10px;">
                        <input type="text" name="search" placeholder="Tìm theo tên phòng..."
                               value="<?php echo htmlspecialchars($search_query); ?>">
                        <button type="submit"><i class="fas fa-search"></i> Tìm kiếm</button>
                        <?php if (!empty($search_query)) : ?>
                            <a href="phong.php" class="btn-clear-search" style="display: flex; align-items: center; text-decoration: none; color: #dc3545;">
                                <i class="fas fa-times-circle"></i> Xóa tìm kiếm
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
                <a href="themphong.php" class="btn-add"><i class="fas fa-plus"></i> Thêm Phòng học mới</a>
            </div>

            <div class="scroll-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID Phòng</th>
                            <th>Tên Phòng</th>
                            <th>Sức chứa</th>
                            <th>Loại phòng</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($phongs)) : ?>
                        <?php foreach ($phongs as $phong) : ?>
                            <tr>
                                <td><?php echo htmlspecialchars($phong['id_phong']); ?></td>
                                <td><?php echo htmlspecialchars($phong['ten_phong']); ?></td>
                                <td><?php echo htmlspecialchars($phong['suc_chua']); ?></td>
                                <td><?php echo htmlspecialchars($phong['loai_phong']); ?></td>
                                <td class="data-action-buttons">
                                    <a class="btn-edit" href="suaphong.php?id=<?php echo htmlspecialchars($phong['id_phong']); ?>"><i class="fas fa-edit"></i> Sửa</a>
                                    <a class="btn-delete" href="phong.php?action=delete&id=<?php echo htmlspecialchars($phong['id_phong']); ?>" onclick="return confirm('Bạn có chắc chắn muốn xóa phòng học <?php echo htmlspecialchars($phong['ten_phong']); ?> không? Thao tác này có thể ảnh hưởng đến các lớp học phần liên quan!');"><i class="fas fa-trash-alt"></i> Xóa</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="5" style="text-align:center;">Không tìm thấy phòng học nào <?php echo !empty($search_query) ? "với từ khóa: '" . htmlspecialchars($search_query) . "'" : ""; ?>.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                <?php if ($total_pages > 1) : ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                        <a href="phong.php?page=<?php echo $i; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>"
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