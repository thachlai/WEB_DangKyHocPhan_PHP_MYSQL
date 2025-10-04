<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../conn.php';
include '../function.php';

check_student();
check_login();

$sinh_vien_id = $_SESSION['ma_tk'];
$id_lop_hp = isset($_GET['id_lop_hp']) ? intval($_GET['id_lop_hp']) : 0;
$hocki_id_redirect = isset($_GET['hocki_id']) ? intval($_GET['hocki_id']) : 0;

if ($id_lop_hp === 0) {
    $_SESSION['message'] = "Lớp học phần không hợp lệ.";
    $_SESSION['message_type'] = "error";
    header("Location: dangkyhocphan.php?hocki_id=" . $hocki_id_redirect);
    exit();
}

// Bắt đầu transaction để đảm bảo tính toàn vẹn dữ liệu
$conn->begin_transaction();

try {
    // 1. Kiểm tra thông tin lớp học phần (trạng thái, sĩ số)
    $stmt_check_lhp = $conn->prepare("SELECT si_so_toi_da, si_so_hien_tai, trangthai, id_hocki FROM lop_hocphan WHERE id_lop_hp = ? FOR UPDATE"); // FOR UPDATE để khóa hàng, tránh race condition
    if ($stmt_check_lhp === false) {
        throw new Exception("Lỗi chuẩn bị truy vấn kiểm tra lớp học phần: " . $conn->error);
    }
    $stmt_check_lhp->bind_param("i", $id_lop_hp);
    $stmt_check_lhp->execute();
    $result_check_lhp = $stmt_check_lhp->get_result();
    if ($result_check_lhp->num_rows === 0) {
        throw new Exception("Lớp học phần không tồn tại.");
    }
    $lop_hp_info = $result_check_lhp->fetch_assoc();
    $stmt_check_lhp->close();

    if ($lop_hp_info['trangthai'] == 1) { // 1 là Ẩn
        throw new Exception("Lớp học phần này đã bị ẩn và không thể đăng ký.");
    }
    if ($lop_hp_info['si_so_hien_tai'] >= $lop_hp_info['si_so_toi_da']) {
        throw new Exception("Lớp học phần này đã đủ sĩ số. Vui lòng chọn lớp khác.");
    }

    // 2. Kiểm tra xem sinh viên đã đăng ký lớp này chưa
    $stmt_check_reg = $conn->prepare("SELECT id_dkhp FROM dangky_hocphan WHERE id_taikhoan = ? AND id_lop_hp = ?");
    if ($stmt_check_reg === false) {
        throw new Exception("Lỗi chuẩn bị truy vấn kiểm tra đăng ký: " . $conn->error);
    }
    $stmt_check_reg->bind_param("ii", $sinh_vien_id, $id_lop_hp);
    $stmt_check_reg->execute();
    $result_check_reg = $stmt_check_reg->get_result();
    if ($result_check_reg->num_rows > 0) {
        throw new Exception("Bạn đã đăng ký lớp học phần này rồi.");
    }
    $stmt_check_reg->close();

    // 3. Kiểm tra trùng lịch học (Phức tạp hơn, sẽ thêm sau nếu cần)
    // Để làm phần này, chúng ta cần lấy lịch học của lớp mới và so sánh với tất cả các lớp mà sinh viên đã đăng ký trong cùng học kỳ.
    // Ví dụ:
    /*
    $current_schedule = []; // Lấy lịch các lớp sinh viên đã đăng ký
    $new_class_schedule = []; // Lấy lịch của lớp đang muốn đăng ký
    // ... logic so sánh lịch ...
    if (has_schedule_conflict($current_schedule, $new_class_schedule)) {
        throw new Exception("Lớp học phần này bị trùng lịch với lớp bạn đã đăng ký.");
    }
    */

    // 4. Thực hiện đăng ký
    $stmt_insert_reg = $conn->prepare("INSERT INTO dangky_hocphan (id_taikhoan, id_lop_hp) VALUES (?, ?)");
    if ($stmt_insert_reg === false) {
        throw new Exception("Lỗi chuẩn bị truy vấn đăng ký: " . $conn->error);
    }
    $stmt_insert_reg->bind_param("ii", $sinh_vien_id, $id_lop_hp);
    if (!$stmt_insert_reg->execute()) {
        throw new Exception("Lỗi khi thêm đăng ký: " . $stmt_insert_reg->error);
    }
    $stmt_insert_reg->close();

    // 5. Cập nhật sĩ số hiện tại của lớp học phần
    $stmt_update_siso = $conn->prepare("UPDATE lop_hocphan SET si_so_hien_tai = si_so_hien_tai + 1 WHERE id_lop_hp = ?");
    if ($stmt_update_siso === false) {
        throw new Exception("Lỗi chuẩn bị truy vấn cập nhật sĩ số: " . $conn->error);
    }
    $stmt_update_siso->bind_param("i", $id_lop_hp);
    if (!$stmt_update_siso->execute()) {
        throw new Exception("Lỗi khi cập nhật sĩ số: " . $stmt_update_siso->error);
    }
    $stmt_update_siso->close();

    // Commit transaction nếu mọi thứ thành công
    $conn->commit();
    $_SESSION['message'] = "Đăng ký học phần thành công!";
    $_SESSION['message_type'] = "success";

} catch (Exception $e) {
    // Rollback transaction nếu có lỗi
    $conn->rollback();
    $_SESSION['message'] = "Lỗi đăng ký: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
}

$conn->close();
header("Location: dangkyhocphan.php?hocki_id=" . $hocki_id_redirect);
exit();
?>