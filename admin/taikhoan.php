<?php
// Luôn bắt đầu session ở đầu file
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Đường dẫn tương đối từ admin/ ra thư mục gốc dkhp/
include '../conn.php';
include '../function.php'; 

// Gọi hàm kiểm tra quyền Admin và đăng nhập
// Lưu ý: check_admin() đã tự động gọi redirect_to_role_home() nếu không có quyền
// nên không cần tham số $base_url ở đây.
// check_login() cũng thường được gọi bên trong check_admin() hoặc check_role(), 
// nhưng nếu bạn muốn chắc chắn thì để cả hai cũng không sao.
check_login();   // Đảm bảo người dùng đã đăng nhập
check_admin(); 
// check_login(); // Có thể bỏ nếu check_admin() đã đảm bảo user logged in

$message = ''; // Thông báo thành công
$error_message = ''; // Thông báo lỗi

// --- Xử lý xóa tài khoản ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $ma_tk_to_delete = intval($_GET['id']);

    // Ngăn admin tự xóa tài khoản của mình
    if (isset($_SESSION['ma_tk']) && $_SESSION['ma_tk'] == $ma_tk_to_delete) {
        $_SESSION['error_message'] = "Bạn không thể tự xóa tài khoản của mình!";
    } else {
        // Lấy đường dẫn ảnh để xóa file ảnh cũ (nếu có)
        $stmt_get_img = $conn->prepare("SELECT img FROM taikhoan WHERE ma_tk = ?");
        $stmt_get_img->bind_param("i", $ma_tk_to_delete);
        $stmt_get_img->execute();
        $result_img = $stmt_get_img->get_result();
        $img_to_delete = null;
        if ($result_img->num_rows > 0) {
            $row_img = $result_img->fetch_assoc();
            $img_to_delete = $row_img['img'];
        }
        $stmt_get_img->close();

        // Thực hiện xóa tài khoản
        $stmt_delete = $conn->prepare("DELETE FROM taikhoan WHERE ma_tk = ?");
        $stmt_delete->bind_param("i", $ma_tk_to_delete);

        if ($stmt_delete->execute()) {
            // Xóa file ảnh trên server nếu đó không phải ảnh mặc định
            if ($img_to_delete && $img_to_delete != 'uploads/default_avatar.png') {
                $file_path = '../' . $img_to_delete; // Đường dẫn từ thư mục admin/
                if (file_exists($file_path)) {
                    unlink($file_path); // Xóa file
                }
            }
            $_SESSION['message'] = "Tài khoản có mã: **" . htmlspecialchars($ma_tk_to_delete) . "** đã được xóa thành công!";
        } else {
            $_SESSION['error_message'] = "Lỗi khi xóa tài khoản: " . $stmt_delete->error;
        }
        $stmt_delete->close();
    }
    // Chuyển hướng để xóa các tham số GET khỏi URL và hiển thị thông báo
    header("Location: taikhoan.php");
    exit();
}

// --- Lấy thông báo từ session (nếu có) ---
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// --- Logic Tìm kiếm và Lọc ---
$search_term = $_GET['search'] ?? '';
$filter_vaitro = $_GET['vaitro_filter'] ?? '';
$filter_khoa = $_GET['khoa_filter'] ?? '';

// Thêm cột 'img' vào SELECT statement
$sql = "SELECT tk.ma_tk, tk.email, tk.ten_taikhoan, tk.gioitinh, tk.ngaysinh, tk.sdt, tk.vaitro, k.ten_khoa, tk.img 
        FROM taikhoan tk
        LEFT JOIN khoa k ON tk.ma_khoa = k.ma_khoa
        WHERE 1=1"; 

$params = [];
$types = '';

if (!empty($search_term)) {
    $sql .= " AND (tk.ten_taikhoan LIKE ? OR tk.email LIKE ? OR tk.sdt LIKE ?)";
    $search_param = '%' . $search_term . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if ($filter_vaitro !== '') { 
    $sql .= " AND tk.vaitro = ?";
    $params[] = $filter_vaitro;
    $types .= 'i';
}

if ($filter_khoa !== '') { 
    $sql .= " AND tk.ma_khoa = ?";
    $params[] = $filter_khoa;
    $types .= 'i';
}

$sql .= " ORDER BY tk.ma_tk DESC"; 

$stmt_select = $conn->prepare($sql);

if (!empty($params)) {
    // Sử dụng toán tử Splat (...) để unpack mảng $params thành các tham chiếu riêng lẻ
    $stmt_select->bind_param($types, ...$params); 
}
$stmt_select->execute();
$result = $stmt_select->get_result();

// Lấy danh sách khoa để hiển thị trong bộ lọc
$khoa_options = [];
$sql_khoa = "SELECT ma_khoa, ten_khoa FROM khoa ORDER BY ten_khoa";
$result_khoa_filter = $conn->query($sql_khoa); // Đổi tên biến để tránh trùng
if ($result_khoa_filter && $result_khoa_filter->num_rows > 0) {
    while ($row = $result_khoa_filter->fetch_assoc()) {
        $khoa_options[] = $row;
    }
}
$conn->close(); // Đóng kết nối CSDL sau khi hoàn tất truy vấn
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Tài khoản</title>
    <link rel="stylesheet" href="css/mainlist.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <style>
        /* Tùy chỉnh CSS cho ảnh đại diện trong bảng */
        .profile-img-thumb {
            width: 50px; /* Kích thước ảnh nhỏ */
            height: 50px;
            border-radius: 50%; /* Bo tròn ảnh */
            object-fit: cover; /* Đảm bảo ảnh không bị bóp méo */
            border: 1px solid #ddd;
            vertical-align: middle; /* Căn giữa theo chiều dọc */
        }
        .data-table th, .data-table td {
            vertical-align: middle; /* Căn giữa nội dung ô theo chiều dọc */
        }
    </style>
</head>
<body>
    <?php 
    // Header và menubar thường sẽ chứa <body> mở và đóng nếu đó là file riêng biệt
    // Nếu chúng là một phần của bố cục chính, bạn cần đảm bảo cấu trúc HTML đúng.
    include '../header.php'; 
    include 'menubar.php';   
    ?>

    <main>
        <div class="list-container">
            <h2>Quản lý Tài khoản</h2>

            <?php if (!empty($message)) : ?>
                <div class="alert success">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($error_message)) : ?>
                <div class="alert error">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <div class="action-bar">
                <form method="GET" action="taikhoan.php" class="search-form">
                    <input type="text" name="search" placeholder="Tìm kiếm theo tên, email, SĐT..." value="<?php echo htmlspecialchars($search_term); ?>">
                    
                    <select name="vaitro_filter" class="filter-select">
                        <option value="">-- Lọc theo Vai trò --</option>
                        <option value="0" <?php echo ($filter_vaitro === '0') ? 'selected' : ''; ?>>Admin</option>
                        <option value="1" <?php echo ($filter_vaitro === '1') ? 'selected' : ''; ?>>Sinh viên</option>
                        <option value="2" <?php echo ($filter_vaitro === '2') ? 'selected' : ''; ?>>Giảng viên</option>
                    </select>

                    <select name="khoa_filter" class="filter-select">
                        <option value="">-- Lọc theo Khoa --</option>
                        <?php foreach ($khoa_options as $khoa) : ?>
                            <option value="<?php echo htmlspecialchars($khoa['ma_khoa']); ?>" 
                                <?php echo ($filter_khoa !== '' && $filter_khoa == $khoa['ma_khoa']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($khoa['ten_khoa']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit"><i class="fas fa-filter"></i> Lọc & Tìm</button>
                    <?php if (!empty($search_term) || $filter_vaitro !== '' || $filter_khoa !== '') : ?>
                        <a href="taikhoan.php" class="btn btn-secondary"><i class="fas fa-undo"></i> Reset</a>
                    <?php endif; ?>
                </form>
                <a href="add_taikhoan.php" class="btn-add"><i class="fas fa-plus"></i> Thêm tài khoản mới</a>
            </div>

            <div class="scroll-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Mã TK</th>
                            <th>Ảnh đại diện</th> <th>Tên tài khoản</th>
                            <th>Email</th>
                            <th>Giới tính</th>
                            <th>Ngày sinh</th>
                            <th>SĐT</th>
                            <th>Vai trò</th>
                            <th>Khoa</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($result && $result->num_rows > 0) : ?>
                        <?php while ($row = $result->fetch_assoc()) : ?>
                            <tr>
                                <td><div class='cell-content'><?php echo htmlspecialchars($row['ma_tk']); ?></div></td>
                                <td>
                                    <div class='cell-content'>
                                        <?php 
                                            // Đường dẫn ảnh từ CSDL có dạng 'uploads/ten_file.jpg'
                                            // Để hiển thị, cần đường dẫn tương đối từ gốc webserver
                                            // Vì đang ở admin/, cần lùi lại 1 cấp: ../
                                            $image_path = '../' . htmlspecialchars($row['img'] ?? 'uploads/default_avatar.png'); 
                                            // Kiểm tra nếu file ảnh không tồn tại, dùng ảnh mặc định
                                            if (!file_exists($image_path) || empty($row['img'])) {
                                                $image_path = '../uploads/default_avatar.png';
                                            }
                                        ?>
                                        <img src="<?php echo $image_path; ?>" alt="Ảnh đại diện" class="profile-img-thumb">
                                    </div>
                                </td>
                                <td><div class='cell-content'><?php echo htmlspecialchars($row['ten_taikhoan']); ?></div></td>
                                <td><div class='cell-content'><?php echo htmlspecialchars($row['email']); ?></div></td>
                                <td><div class='cell-content'><?php echo htmlspecialchars($row['gioitinh']); ?></div></td>
                                <td><div class='cell-content'><?php echo htmlspecialchars($row['ngaysinh']); ?></div></td>
                                <td><div class='cell-content'><?php echo htmlspecialchars($row['sdt']); ?></div></td>
                                <td><div class='cell-content'>
                                    <?php 
                                    if ($row['vaitro'] == 0) echo 'Admin';
                                    else if ($row['vaitro'] == 1) echo 'Sinh viên';
                                    else if ($row['vaitro'] == 2) echo 'Giảng viên';
                                    else echo 'Không xác định';
                                    ?>
                                </div></td>
                                <td><div class='cell-content'><?php echo htmlspecialchars($row['ten_khoa'] ?? 'N/A'); ?></div></td>
                                <td class='data-action-buttons'>
                                    <a class='btn-edit' href='suataikhoan.php?id=<?php echo htmlspecialchars($row['ma_tk']); ?>'><i class='fas fa-edit'></i> Sửa</a>
                                    <a class='btn-delete' href='taikhoan.php?action=delete&id=<?php echo htmlspecialchars($row['ma_tk']); ?>' onclick="return confirm('Bạn có chắc chắn muốn xóa tài khoản này không?');"><i class='fas fa-trash-alt'></i> Xóa</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="10" style="text-align:center;">Không tìm thấy tài khoản nào.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // JS để đóng thông báo
            document.querySelectorAll('.alert').forEach(alertDiv => {
                if (alertDiv.innerHTML.trim() !== "") {
                    setTimeout(() => {
                        alertDiv.style.display = 'none';
                    }, 5000); // Ẩn sau 5 giây
                }
            });
        });
    </script>
</body> 
</html>