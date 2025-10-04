<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../conn.php';
include '../function.php';

check_student();
check_login();

$sinh_vien_id = $_SESSION['ma_tk'];
$id_dkhp = isset($_GET['id_dkhp']) ? intval($_GET['id_dkhp']) : 0;
$hocki_id_redirect = isset($_GET['hocki_id']) ? intval($_GET['hocki_id']) : 0;

if ($id_dkhp === 0) {
    $_SESSION['message'] = "Yêu cầu hủy không hợp lệ.";
    $_SESSION['message_type'] = "error";
    header("Location: dangkyhocphan.php?hocki_id=" . $hocki_id_redirect);
    exit();
}

// Bắt đầu transaction để đảm bảo tính toàn vẹn dữ liệu
$conn->begin_transaction();

try {
    // 1. Lấy id_lop_hp, id_taikhoan và quan trọng nhất là trangthai của lop_hocphan
    $stmt_get_dkhp_info = $conn->prepare("SELECT dkhp.id_lop_hp, dkhp.id_taikhoan, lhp.trangthai AS lop_hp_trangthai
                                         FROM dangky_hocphan dkhp
                                         JOIN lop_hocphan lhp ON dkhp.id_lop_hp = lhp.id_lop_hp
                                         WHERE dkhp.id_dkhp = ? FOR UPDATE"); // FOR UPDATE để khóa hàng, tránh race condition
    if ($stmt_get_dkhp_info === false) {
        throw new Exception("Lỗi chuẩn bị truy vấn thông tin đăng ký: " . $conn->error);
    }
    $stmt_get_dkhp_info->bind_param("i", $id_dkhp);
    $stmt_get_dkhp_info->execute();
    $result_get_dkhp_info = $stmt_get_dkhp_info->get_result();

    if ($result_get_dkhp_info->num_rows === 0) {
        throw new Exception("Đăng ký học phần không tồn tại.");
    }
    $dkhp_info = $result_get_dkhp_info->fetch_assoc();
    $stmt_get_dkhp_info->close();

    // Kiểm tra quyền sở hữu
    if ($dkhp_info['id_taikhoan'] != $sinh_vien_id) {
        throw new Exception("Bạn không có quyền hủy đăng ký này.");
    }

    // *** Thêm logic kiểm tra trạng thái lớp học phần tại đây ***
    // Nếu trạng thái của lớp học phần là 2 (chốt) thì không cho phép hủy
    if ($dkhp_info['lop_hp_trangthai'] == 2) {
        throw new Exception("Không thể hủy đăng ký lớp học phần này vì lớp đã chốt.");
    }

    $id_lop_hp_to_update = $dkhp_info['id_lop_hp'];

    // 2. Xóa đăng ký học phần
    $stmt_delete_reg = $conn->prepare("DELETE FROM dangky_hocphan WHERE id_dkhp = ?");
    if ($stmt_delete_reg === false) {
        throw new Exception("Lỗi chuẩn bị truy vấn xóa đăng ký: " . $conn->error);
    }
    $stmt_delete_reg->bind_param("i", $id_dkhp);
    if (!$stmt_delete_reg->execute()) {
        throw new Exception("Lỗi khi xóa đăng ký: " . $stmt_delete_reg->error);
    }
    $stmt_delete_reg->close();

    // 3. Giảm sĩ số hiện tại của lớp học phần
    $stmt_update_siso = $conn->prepare("UPDATE lop_hocphan SET si_so_hien_tai = si_so_hien_tai - 1 WHERE id_lop_hp = ? AND si_so_hien_tai > 0");
    if ($stmt_update_siso === false) {
        throw new Exception("Lỗi chuẩn bị truy vấn cập nhật sĩ số: " . $conn->error);
    }
    $stmt_update_siso->bind_param("i", $id_lop_hp_to_update);
    if (!$stmt_update_siso->execute()) {
        throw new Exception("Lỗi khi cập nhật sĩ số: " . $stmt_update_siso->error);
    }
    $stmt_update_siso->close();

    // Commit transaction nếu mọi thứ thành công
    $conn->commit();
    $_SESSION['message'] = "Hủy đăng ký học phần thành công!";
    $_SESSION['message_type'] = "success";

} catch (Exception $e) {
    // Rollback transaction nếu có lỗi
    $conn->rollback();
    $_SESSION['message'] = "Lỗi hủy đăng ký: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
}

$conn->close();
header("Location: dangkyhocphan.php?hocki_id=" . $hocki_id_redirect);
exit();
?>