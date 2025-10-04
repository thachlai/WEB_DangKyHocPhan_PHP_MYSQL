<?php
// Bắt đầu session nếu chưa được bắt đầu. Rất quan trọng phải gọi hàm này ở ĐẦU MỖI TRANG PHP
// nơi bạn muốn sử dụng session (như kiểm tra đăng nhập, vai trò người dùng).
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Bao gồm file kết nối cơ sở dữ liệu.
// Sử dụng __DIR__ để đảm bảo đường dẫn tuyệt đối đến conn.php,
// hoạt động đúng bất kể function.php được include từ đâu.
// Đảm bảo file conn.php nằm cùng cấp với function.php trong thư mục 'dkhp'.
require_once __DIR__ . '/conn.php'; 

// ========================================================================
// CẤU HÌNH QUAN TRỌNG: ĐỊNH NGHĨA ĐƯỜNG DẪN GỐC CỦA ỨNG DỤNG
// ========================================================================
/**
 * Hàm lấy đường dẫn URL cơ sở của ứng dụng.
 * Đây là đường dẫn đến thư mục 'dkhp' trên web server (ví dụ: http://localhost/dkhp/).
 *
 * @return string Đường dẫn URL đầy đủ đến thư mục gốc của dự án.
 */
function get_dkhp_base_url() {
    $host = $_SERVER['HTTP_HOST'];
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    
    // Đường dẫn thư mục gốc của dự án trên URL.
    // Nếu thư mục 'dkhp' nằm TRỰC TIẾP trong thư mục gốc của web server (htdocs, www),
    // ví dụ: http://localhost/dkhp/, thì $projectBase = '/dkhp'.
    // Nếu thư mục 'dkhp' LÀ thư mục gốc của web server (ví dụ: http://localhost/ ),
    // thì $projectBase = ''.
    $projectBase = '/dkhp'; // <<< CẦN ĐIỀU CHỈNH GIÁ TRỊ NÀY CHO ĐÚNG MÔI TRƯỜNG CỦA BẠN!
    
    // Đảm bảo có dấu '/' ở cuối
    return "{$protocol}://{$host}{$projectBase}/";
}

/**
 * Chuyển hướng người dùng đến một trang cụ thể trong thư mục gốc của dự án DKHP.
 *
 * @param string $path Đường dẫn tương đối đến file từ thư mục gốc của dự án DKHP (ví dụ: 'dangnhap.php' hoặc 'admin/index.php').
 */
function redirect_to_dkhp_root($path = 'dangnhap.php') {
    $fullRedirectPath = get_dkhp_base_url() . $path;
    header("Location: " . $fullRedirectPath);
    exit(); // Luôn thoát sau khi chuyển hướng để ngăn chặn mã tiếp tục thực thi
}

/**
 * Hàm hiển thị thông báo (nếu có) và sau đó xóa khỏi session.
 * Sử dụng cho các thông báo lỗi, thành công, cảnh báo, v.v.
 */
function display_message() {
    if (isset($_SESSION['message'])) {
        $message = htmlspecialchars($_SESSION['message']);
        $type = htmlspecialchars($_SESSION['message_type'] ?? 'info'); // 'error', 'success', 'info', 'warning'

        $style = "";
        switch ($type) {
            case 'error':
                $style = "background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;";
                break;
            case 'success':
                $style = "background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;";
                break;
            case 'warning':
                $style = "background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba;";
                break;
            default: // info
                $style = "background-color: #cce5ff; color: #004085; border: 1px solid #b8daff;";
                break;
        }

        echo "<div style='padding: 10px; margin-bottom: 20px; border-radius: 5px; text-align: center; {$style}'>";
        echo $message;
        echo "</div>";
        unset($_SESSION['message']); // Xóa thông báo sau khi hiển thị
        unset($_SESSION['message_type']); // Xóa loại thông báo
    }
}


// --- Các hàm kiểm tra quyền truy cập dựa trên vai trò ---
// Quy ước vai trò: 0 = Admin, 1 = Sinh viên, 2 = Giảng viên

/**
 * Chuyển hướng người dùng đến trang thông báo truy cập bất hợp pháp.
 * Đây là hàm trung gian để dễ dàng thay đổi đường dẫn trang thông báo sau này.
 */
function redirect_to_unauthorized_page() {
    // Đảm bảo rằng trang 'thongbao_truycap.php' đã được tạo trong thư mục gốc của dự án
    redirect_to_dkhp_root('thongbao_truycap.php'); 
}

/**
 * Kiểm tra xem người dùng đã đăng nhập hay chưa.
 * Nếu chưa, chuyển hướng về trang đăng nhập và thoát.
 */
function check_login() {
    if (!isset($_SESSION['ma_tk'])) {
        $_SESSION['message'] = "Bạn cần đăng nhập để truy cập trang này.";
        $_SESSION['message_type'] = "error";
        redirect_to_dkhp_root('dangnhap.php'); // Vẫn về trang đăng nhập, vì chưa đăng nhập thì không có vai trò để chuyển hướng tới trang thông báo vai trò sai.
    }
}

/**
 * Chuyển hướng người dùng về trang chủ tương ứng với vai trò của họ.
 * Hàm này được sử dụng khi người dùng không có quyền truy cập vào một khu vực.
 * (Hàm này có thể được dùng cho trang đăng nhập thành công, hoặc nếu bạn muốn một vai trò
 * bị chuyển hướng về trang chủ của chính họ khi cố truy cập trang của vai trò khác.)
 */
function redirect_to_role_home() {
    $current_role = $_SESSION['vaitro'] ?? null; // Sử dụng null coalescing operator cho an toàn
    $redirect_path = 'index.php'; // Mặc định là trang chủ chung

    if ($current_role == 0) { // Admin
        $redirect_path = 'admin/index.php';
    } elseif ($current_role == 1) { // Sinh viên
        $redirect_path = 'sinhvien/index.php';
    } elseif ($current_role == 2) { // Giảng viên
        $redirect_path = 'giangvien/index.php';
    }
    redirect_to_dkhp_root($redirect_path);
}

/**
 * Kiểm tra quyền Admin (vaitro = 0).
 * Nếu người dùng không phải Admin, chuyển hướng về trang thông báo lỗi.
 */
function check_admin() {
    check_login(); // Đảm bảo đã đăng nhập trước
    if ($_SESSION['vaitro'] != 0) {
        $_SESSION['message'] = "Bạn không có quyền truy cập trang quản trị.";
        $_SESSION['message_type'] = "error";
        redirect_to_unauthorized_page(); // Chuyển hướng về trang thông báo lỗi
    }
}

/**
 * Kiểm tra quyền Sinh viên (vaitro = 1).
 * Nếu người dùng không phải Sinh viên, chuyển hướng về trang thông báo lỗi.
 */
function check_student() {
    check_login(); // Đảm bảo đã đăng nhập trước
    if ($_SESSION['vaitro'] != 1) {
        $_SESSION['message'] = "Bạn cần đăng nhập với tài khoản sinh viên để truy cập trang này.";
        $_SESSION['message_type'] = "error";
        redirect_to_unauthorized_page(); // Chuyển hướng về trang thông báo lỗi
    }
}

/**
 * Kiểm tra quyền Giảng viên (vaitro = 2).
 * Nếu người dùng không phải Giảng viên, chuyển hướng về trang thông báo lỗi.
 */
function check_giangvien() {
    check_login(); // Đảm bảo đã đăng nhập trước
    if ($_SESSION['vaitro'] != 2) {
        $_SESSION['message'] = "Bạn không có quyền truy cập trang dành cho giảng viên.";
        $_SESSION['message_type'] = "error";
        redirect_to_unauthorized_page(); // Chuyển hướng về trang thông báo lỗi
    }
}


// --- Các hàm kiểm tra trạng thái hiển thị (yêu cầu kết nối CSDL) ---

/**
 * Kiểm tra trạng thái của Tài khoản (ví dụ: bị khóa, không hoạt động).
 * Giả định status = 0 là hoạt động, 1 là khóa/không hoạt động.
 *
 * @param int $ma_tk Mã tài khoản
 * @param mysqli $conn Đối tượng kết nối CSDL (cần được truyền vào khi gọi hàm)
 */
function check_taikhoan_status($ma_tk, $conn) {
    // Để hàm này hoạt động, bảng 'taikhoan' cần có cột 'status' (hoặc 'trangthai')
    // Nếu chưa có, bạn có thể thêm cột này vào CSDL
    
    // HÀM NÀY NÊN ĐƯỢC GỌI NGAY SAU KHI ĐĂNG NHẬP THÀNH CÔNG VÀ KHI CẦN KIỂM TRA LẠI TRẠNG THÁI TK
    // NẾU GỌI TRÊN MỖI TRANG, CÓ THỂ GÂY TẢI KHÔNG CẦN THIẾT HOẶC VẤN ĐỀ VỀ LOGIC CHUYỂN HƯỚNG
    // ĐỀ XUẤT NÊN CHỈ KIỂM TRA SAU KHI ĐĂNG NHẬP VÀ LƯU TRẠNG THÁI VÀO SESSION (NẾU CẦN)
    
    // Ví dụ về cách gọi: check_taikhoan_status($_SESSION['ma_tk'], $conn);
    
    $sql = "SELECT trangthai FROM taikhoan WHERE ma_tk = ?"; // Giả sử cột là 'trangthai'
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $ma_tk);
    $stmt->execute();
    $stmt->bind_result($trangthai);
    if ($stmt->fetch()) { // Nếu tìm thấy tài khoản
        $stmt->close();
        if ($trangthai == 1) { // Nếu trangthai = 1 (khóa/không hoạt động)
            // Hủy session của tài khoản bị khóa và chuyển hướng về trang đăng nhập
            session_destroy(); 
            $_SESSION['message'] = "Tài khoản của bạn đã bị khóa hoặc không hoạt động. Vui lòng liên hệ quản trị viên.";
            $_SESSION['message_type'] = "error";
            redirect_to_dkhp_root('dangnhap.php'); // Tài khoản bị khóa thì vẫn về trang đăng nhập
        }
    } else {
        // Tài khoản không tồn tại, có thể hủy session và chuyển hướng đăng nhập
        session_destroy(); 
        $_SESSION['message'] = "Tài khoản không tồn tại.";
        $_SESSION['message_type'] = "error";
        redirect_to_dkhp_root('dangnhap.php'); // Tài khoản không tồn tại thì cũng về trang đăng nhập
    }
    $stmt->close();
}

/**
 * Kiểm tra Môn học có đang bị ẩn/khóa không.
 * Giả định trangthai = 0 là hiển thị, 1 là ẩn/khóa.
 *
 * @param int $id_mon Mã môn học
 * @param mysqli $conn Đối tượng kết nối CSDL (cần được truyền vào khi gọi hàm)
 */
function check_mon_status($id_mon, $conn) {
    // Để hàm này hoạt động, bảng 'mon' cần có cột 'trangthai'
    // và `conn.php` phải được include trước khi hàm này được gọi.
    $sql = "SELECT trangthai FROM mon WHERE id_mon = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_mon);
    $stmt->execute();
    $stmt->bind_result($trangthai);
    if ($stmt->fetch() && $trangthai == 1) { // Nếu trangthai = 1 (ẩn/khóa)
        $stmt->close();
        $_SESSION['message'] = "Môn học này hiện không khả dụng.";
        $_SESSION['message_type'] = "error";
        redirect_to_unauthorized_page(); // Chuyển hướng về trang thông báo lỗi
    }
    $stmt->close();
}

/**
 * Kiểm tra Lớp Học phần có đang bị ẩn/khóa không.
 * Giả định trangthai = 0 là hiển thị, 1 là ẩn/khóa.
 *
 * @param int $id_lop_hp Mã lớp học phần
 * @param mysqli $conn Đối tượng kết nối CSDL (cần được truyền vào khi gọi hàm)
 */
function check_lophocphan_status($id_lop_hp, $conn) {
    // Đảm bảo bảng `lop_hocphan` có cột `trangthai`.
    $sql = "SELECT trangthai FROM lop_hocphan WHERE id_lop_hp = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_lop_hp);
    $stmt->execute();
    $stmt->bind_result($trangthai);
    if ($stmt->fetch() && $trangthai == 1) { // Nếu trangthai = 1 (ẩn/khóa)
        $stmt->close();
        $_SESSION['message'] = "Lớp học phần này hiện không khả dụng.";
        $_SESSION['message_type'] = "error";
        redirect_to_unauthorized_page(); // Chuyển hướng về trang thông báo lỗi
    }
    $stmt->close();
}

/**
 * Kiểm tra Phòng học có đang bị ẩn/khóa không.
 *
 * @param int $id_phong Mã phòng học
 * @param mysqli $conn Đối tượng kết nối CSDL (cần được truyền vào khi gọi hàm)
 */
function check_phonghoc_status($id_phong, $conn) {
    // Để hàm này hoạt động, bảng 'phong_hoc' cần có cột 'trangthai'
    $sql = "SELECT trangthai FROM phong_hoc WHERE id_phong = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_phong);
    $stmt->execute();
    $stmt->bind_result($trangthai);
    if ($stmt->fetch() && $trangthai == 1) { // Nếu trangthai = 1 (ẩn/khóa)
        $stmt->close();
        $_SESSION['message'] = "Phòng học này hiện không khả dụng.";
        $_SESSION['message_type'] = "error";
        redirect_to_unauthorized_page(); // Chuyển hướng về trang thông báo lỗi
    }
    $stmt->close();
}

/**
 * Kiểm tra Học kỳ có đang bị ẩn/khóa không.
 * Giả định trangthai = 0 là hiển thị, 1 là ẩn/khóa.
 *
 * @param int $id_hocki Mã học kỳ
 * @param mysqli $conn Đối tượng kết nối CSDL (cần được truyền vào khi gọi hàm)
 */
function check_hocky_status($id_hocki, $conn) {
    // Để hàm này hoạt động, bảng 'hocki' cần có cột 'trangthai'
    $sql = "SELECT trangthai FROM hocki WHERE id_hocki = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_hocki);
    $stmt->execute();
    $stmt->bind_result($trangthai);
    if ($stmt->fetch() && $trangthai == 1) { // Nếu trangthai = 1 (ẩn/khóa)
        $stmt->close();
        $_SESSION['message'] = "Học kỳ này hiện không khả dụng.";
        $_SESSION['message_type'] = "error";
        redirect_to_unauthorized_page(); // Chuyển hướng về trang thông báo lỗi
    }
    $stmt->close();
}

// Hàm tiện ích để định dạng tiền tệ
function format_currency($amount) {
    return number_format($amount, 0, ',', '.') . 'đ';
}

?>