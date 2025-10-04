<?php
include '../conn.php'; // Include your database connection file

header('Content-Type: application/json');

$id_lop_hp = isset($_GET['id_lop_hp']) ? intval($_GET['id_lop_hp']) : 0;

$lich_hocs = [];

if ($id_lop_hp > 0) {
    $stmt = $conn->prepare("SELECT id_lich_hoc, ngay_trong_tuan, tiet_bat_dau, tiet_ket_thuc, ghi_chu FROM lich_hoc WHERE id_lop_hp = ? ORDER BY ngay_trong_tuan, tiet_bat_dau");
    if ($stmt) {
        $stmt->bind_param("i", $id_lop_hp);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $lich_hocs[] = $row;
        }
        $stmt->close();
    }
}

$conn->close();
echo json_encode($lich_hocs);
?>