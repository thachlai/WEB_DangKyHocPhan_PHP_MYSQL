<?php
// Luôn bắt đầu session ở đầu file
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

$id_mon = isset($_GET['id']) ? intval($_GET['id']) : 0;
$mon_data = null; // Biến để lưu dữ liệu môn học hiện tại
$error = '';
$success = '';

// Lấy danh sách các khoa từ database (dùng cho dropdown)
$sql_khoa = "SELECT ma_khoa, ten_khoa FROM khoa ORDER BY ten_khoa ASC";
$result_khoa = $conn->query($sql_khoa);
$khoa_options = [];
if ($result_khoa) {
    while ($row = $result_khoa->fetch_assoc()) {
        $khoa_options[] = $row;
    }
} else {
    $error = "Lỗi khi lấy danh sách khoa: " . $conn->error;
}

// --- Xử lý POST request (Cập nhật thông tin môn học) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_mon_post = intval($_POST['id_mon'] ?? 0); // Lấy lại ID môn từ hidden field
    $ten_mon_moi = trim($_POST['ten_mon'] ?? '');
    $so_tin_chi_moi = intval($_POST['so_tin_chi'] ?? 0);
    $gia_tin_chi_moi = intval($_POST['gia_tin_chi'] ?? 0);
    $id_khoa_moi = intval($_POST['id_khoa'] ?? 0);

    // Giữ lại dữ liệu đã nhập để người dùng không phải nhập lại nếu có lỗi
    $mon_data = [
        'id_mon' => $id_mon_post,
        'ten_mon' => $ten_mon_moi,
        'so_tin_chi' => $so_tin_chi_moi,
        'gia_tin_chi' => $gia_tin_chi_moi,
        'id_khoa' => $id_khoa_moi,
        'ten_khoa' => '' // Sẽ cập nhật nếu cần
    ];
    // Tìm tên khoa tương ứng với id_khoa_moi để hiển thị lại
    foreach ($khoa_options as $khoa) {
        if ($khoa['ma_khoa'] == $id_khoa_moi) {
            $mon_data['ten_khoa'] = $khoa['ten_khoa'];
            break;
        }
    }


    // Validate input data
    if ($id_mon_post === 0 || empty($ten_mon_moi) || $so_tin_chi_moi <= 0 || $gia_tin_chi_moi <= 0 || $id_khoa_moi <= 0) {
        $_SESSION['message'] = "Vui lòng điền đầy đủ thông tin môn học và đảm bảo các giá trị số hợp lệ (Số tín chỉ, Giá tín chỉ, Khoa).";
        $_SESSION['message_type'] = "error";
    } else {
        // Kiểm tra xem tên môn mới có bị trùng với môn khác không (ngoại trừ môn đang chỉnh sửa)
        $check_stmt = $conn->prepare("SELECT id_mon FROM mon WHERE ten_mon = ? AND id_mon <> ?");
        if ($check_stmt === false) {
            $_SESSION['message'] = "Lỗi chuẩn bị truy vấn kiểm tra trùng lặp: " . $conn->error;
            $_SESSION['message_type'] = "error";
        } else {
            $check_stmt->bind_param("si", $ten_mon_moi, $id_mon_post);
            $check_stmt->execute();
            $result = $check_stmt->get_result();

            if ($result->num_rows > 0) {
                $_SESSION['message'] = "Tên môn **" . htmlspecialchars($ten_mon_moi) . "** đã tồn tại. Vui lòng chọn tên khác.";
                $_SESSION['message_type'] = "error";
            } else {
                // Chuẩn bị câu lệnh UPDATE
                $stmt = $conn->prepare("UPDATE mon SET ten_mon = ?, so_tin_chi = ?, gia_tin_chi = ?, id_khoa = ? WHERE id_mon = ?");
                if ($stmt === false) {
                    $_SESSION['message'] = "Lỗi chuẩn bị truy vấn cập nhật: " . $conn->error;
                    $_SESSION['message_type'] = "error";
                } else {
                    $stmt->bind_param("siiii", $ten_mon_moi, $so_tin_chi_moi, $gia_tin_chi_moi, $id_khoa_moi, $id_mon_post);

                    if ($stmt->execute()) {
                        $_SESSION['message'] = "Cập nhật môn học **" . htmlspecialchars($ten_mon_moi) . "** thành công!";
                        $_SESSION['message_type'] = "success";
                        header("Location: mon.php"); // Chuyển hướng về trang danh sách môn học
                        exit();
                    } else {
                        $_SESSION['message'] = "Lỗi khi cập nhật môn học: " . $stmt->error;
                        $_SESSION['message_type'] = "error";
                    }
                    $stmt->close();
                }
            }
            $check_stmt->close();
        }
    }
    // Gán lại ID để tải dữ liệu nếu có lỗi POST
    $id_mon = $id_mon_post;
}

// --- Logic Lấy dữ liệu môn học để hiển thị lên form (GET request hoặc sau lỗi POST) ---
// Chỉ lấy dữ liệu nếu biến $mon_data chưa được set (tức là không phải từ lỗi POST)
if ($mon_data === null && $id_mon > 0) {
    $stmt = $conn->prepare("SELECT m.id_mon, m.ten_mon, m.so_tin_chi, m.gia_tin_chi, m.id_khoa, k.ten_khoa FROM mon m LEFT JOIN khoa k ON m.id_khoa = k.ma_khoa WHERE m.id_mon = ?");
    if ($stmt === false) {
        $error = "Lỗi chuẩn bị truy vấn lấy dữ liệu môn học: " . $conn->error;
    } else {
        $stmt->bind_param("i", $id_mon);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $mon_data = $result->fetch_assoc();
        } else {
            $error = "Không tìm thấy môn học với ID: " . htmlspecialchars($id_mon);
            $id_mon = 0; // Đặt về 0 để tránh hiển thị form rỗng nếu không tìm thấy
        }
        $stmt->close();
    }
} elseif ($id_mon === 0) {
    $error = "Không có ID môn học được cung cấp để chỉnh sửa.";
}

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

$conn->close(); // Đóng kết nối CSDL
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chỉnh sửa Môn Học</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>style.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>admin/css/menubar.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>admin/css/mainedit.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
</head>
<body>
    <?php
    include '../header.php';
    include 'menubar.php';
    ?>

    <main>
        <div class="form-container">
            <br><br><br>
            <h2>Chỉnh sửa Môn Học</h2>

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

            <?php if ($mon_data) : // Chỉ hiển thị form nếu tìm thấy dữ liệu môn học ?>
                <form method="POST">
                    <input type="hidden" name="id_mon" value="<?php echo htmlspecialchars($mon_data['id_mon']); ?>">

                    <label for="ten_mon">Tên Môn:</label>
                    <input type="text" id="ten_mon" name="ten_mon" value="<?php echo htmlspecialchars($mon_data['ten_mon']); ?>" required>

                    <label for="so_tin_chi">Số Tín chỉ:</label>
                    <input type="number" id="so_tin_chi" name="so_tin_chi" value="<?php echo htmlspecialchars($mon_data['so_tin_chi']); ?>" min="1" required>

                    <label for="gia_tin_chi">Giá Tín chỉ (VNĐ):</label>
                    <input type="number" id="gia_tin_chi" name="gia_tin_chi" value="<?php echo htmlspecialchars($mon_data['gia_tin_chi']); ?>" min="0" required>

                    <label for="id_khoa">Khoa:</label>
                    <select id="id_khoa" name="id_khoa" required>
                        <option value="">-- Chọn Khoa --</option>
                        <?php foreach ($khoa_options as $khoa) : ?>
                            <option value="<?php echo htmlspecialchars($khoa['ma_khoa']); ?>"
                                <?php echo ($mon_data['id_khoa'] == $khoa['ma_khoa']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($khoa['ten_khoa']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit"><i class="fas fa-save"></i> Cập nhật Môn Học</button>
                    <a href="/dkhp/admin/mon.php" class="btn-back"><i class="fas fa-arrow-left"></i> Quay lại Danh sách</a>
                </form>
            <?php else : ?>
                <p>Không thể tải thông tin môn học hoặc ID môn không hợp lệ.</p>
                <a href="/dkhp/admin/mon.php" class="btn-back"><i class="fas fa-arrow-left"></i> Quay lại Danh sách</a>
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