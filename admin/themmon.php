<?php
// PHP_SESSION_NONE checks if a session has not been started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Relative path from admin/ to the dkhp/ root directory
include '../conn.php';
include '../function.php';

// Call the Admin access check function
check_login();   // Đảm bảo người dùng đã đăng nhập
check_admin();
// Initialize variables to retain form values after a failed submission or on initial page load
$ten_mon = '';
$id_khoa = '';
$so_tin_chi = ''; // New field
$gia_tin_chi = ''; // New field

// Fetch Khoa options (this part needs to be before any HTML output)
$khoa_options = [];
$sql_khoa = "SELECT ma_khoa, ten_khoa FROM khoa ORDER BY ten_khoa";
$result_khoa = $conn->query($sql_khoa);
if ($result_khoa && $result_khoa->num_rows > 0) {
    while ($row = $result_khoa->fetch_assoc()) {
        $khoa_options[] = $row;
    }
}

// --- Xử lý POST request (luôn đặt ở đầu file PHP, trước mọi output HTML) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get data from the form
    $ten_mon = trim($_POST['ten_mon'] ?? '');
    $id_khoa = $_POST['id_khoa'] ?? '';
    $so_tin_chi = $_POST['so_tin_chi'] ?? ''; // Get new field value
    $gia_tin_chi = $_POST['gia_tin_chi'] ?? ''; // Get new field value

    // --- Input data validation ---
    if (empty($ten_mon) || empty($id_khoa) || $so_tin_chi === '' || $gia_tin_chi === '') {
        $_SESSION['message'] = "Vui lòng điền đầy đủ Tên môn, Khoa, Số tín chỉ và Giá tín chỉ.";
        $_SESSION['message_type'] = "error";
    } elseif (!is_numeric($so_tin_chi) || $so_tin_chi <= 0) {
        $_SESSION['message'] = "Số tín chỉ phải là một số nguyên dương.";
        $_SESSION['message_type'] = "error";
        $so_tin_chi = ''; // Clear invalid input for display
    } elseif (!is_numeric($gia_tin_chi) || $gia_tin_chi < 0) {
        $_SESSION['message'] = "Giá tín chỉ phải là một số không âm.";
        $_SESSION['message_type'] = "error";
        $gia_tin_chi = ''; // Clear invalid input for display
    } else {
        // Check for duplicate subject name within the same department
        $check_stmt = $conn->prepare("SELECT id_mon FROM mon WHERE ten_mon = ? AND id_khoa = ?");
        if ($check_stmt === false) {
            $_SESSION['message'] = "Lỗi chuẩn bị truy vấn kiểm tra trùng lặp: " . $conn->error;
            $_SESSION['message_type'] = "error";
        } else {
            $check_stmt->bind_param("si", $ten_mon, $id_khoa);
            $check_stmt->execute();
            $result = $check_stmt->get_result();

            if ($result->num_rows > 0) {
                $_SESSION['message'] = "Môn học **" . htmlspecialchars($ten_mon) . "** đã tồn tại trong khoa này.";
                $_SESSION['message_type'] = "error";
            } else {
                // Prepare INSERT statement for the `mon` table
                // Includes so_tin_chi and gia_tin_chi
                $stmt = $conn->prepare("INSERT INTO mon (ten_mon, id_khoa, so_tin_chi, gia_tin_chi) VALUES (?, ?, ?, ?)");
                if ($stmt === false) {
                    $_SESSION['message'] = "Lỗi chuẩn bị truy vấn thêm môn học: " . $conn->error;
                    $_SESSION['message_type'] = "error";
                } else {
                    // Bind parameters: ten_mon (s), id_khoa (i), so_tin_chi (i), gia_tin_chi (d for decimal)
                    $stmt->bind_param("siid", $ten_mon, $id_khoa, $so_tin_chi, $gia_tin_chi);

                    if ($stmt->execute()) {
                        $_SESSION['message'] = "Thêm môn học **" . htmlspecialchars($ten_mon) . "** thành công!";
                        $_SESSION['message_type'] = 'success';
                        
                        // Sau khi thêm thành công, chuyển hướng người dùng để tránh gửi lại form
                        // và hiển thị thông báo.
                        header("Location: mon.php"); // Chuyển hướng về trang danh sách môn học
                        exit(); // Rất quan trọng: Luôn thoát sau khi chuyển hướng
                    } else {
                        $_SESSION['message'] = "Lỗi khi thêm môn học: " . $stmt->error;
                        $_SESSION['message_type'] = "error";
                    }
                    $stmt->close();
                }
            }
            $check_stmt->close();
        }
    }
    // Nếu có lỗi, giữ lại các giá trị đã nhập (ngoại trừ trường hợp lỗi do không đúng định dạng số)
    // và không chuyển hướng, để thông báo lỗi được hiển thị.
}

// Retrieve message from session (if redirected from a successful submission or previous error)
$success = '';
$error = '';
if (isset($_SESSION['message'])) {
    if (isset($_SESSION['message_type']) && $_SESSION['message_type'] === 'success') {
        $success = $_SESSION['message'];
    } else {
        $error = $_SESSION['message'];
    }
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

$conn->close(); // Close the database connection
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thêm Môn học Mới</title>
    <link rel="stylesheet" href="css/mainadd.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
</head>
<body>
    <?php
    // Include header and menubar AFTER all PHP logic and header() calls
    include '../header.php'; // dkhp/header.php
    include 'menubar.php';   // dkhp/admin/menubar.php
    ?>
    <main>
        <div class="form-container">
            <br><br><br>
            <h2>Thêm Môn học Mới</h2>

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

            <form method="POST">
                <label for="ten_mon">Tên Môn học:</label>
                <input type="text" id="ten_mon" name="ten_mon" value="<?php echo htmlspecialchars($ten_mon); ?>" required>

                <label for="id_khoa">Khoa:</label>
                <select id="id_khoa" name="id_khoa" required>
                    <option value="">-- Chọn Khoa --</option>
                    <?php foreach ($khoa_options as $khoa) : ?>
                        <option value="<?php echo htmlspecialchars($khoa['ma_khoa']); ?>"
                            <?php echo ($id_khoa == $khoa['ma_khoa']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($khoa['ten_khoa']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="so_tin_chi">Số tín chỉ:</label>
                <input type="number" id="so_tin_chi" name="so_tin_chi" value="<?php echo htmlspecialchars($so_tin_chi); ?>" min="1" required>

                <label for="gia_tin_chi">Giá tín chỉ:</label>
                <input type="number" id="gia_tin_chi" name="gia_tin_chi" value="<?php echo htmlspecialchars($gia_tin_chi); ?>" min="0" step="0.01" required>

                <button type="submit"><i class="fas fa-plus"></i> Thêm Môn học</button>
                <a href="/dkhp/admin/mon.php" class="btn-back"><i class="fas fa-arrow-left"></i> Quay lại Danh sách</a>
            </form>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
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