<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../conn.php';
include '../function.php';
check_login();   // Đảm bảo người dùng đã đăng nhập
check_admin('/dkhp/'); // Passed base_url to check_admin

$base_url = '/dkhp/';

$message = '';
$message_type = '';
$search_query = ''; // To store the search keyword for ten_hocki
$filter_nam_hoc = ''; // To store the selected academic year for filtering

// --- Process Delete Học Kỳ ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_hocki_to_delete = intval($_GET['id']);

    $check_related_stmt = $conn->prepare("SELECT COUNT(*) FROM lop_hocphan WHERE id_hocki = ?");
    if ($check_related_stmt === false) {
        $_SESSION['message'] = "Lỗi chuẩn bị truy vấn kiểm tra liên kết: " . $conn->error;
        $_SESSION['message_type'] = "error";
    } else {
        $check_related_stmt->bind_param("i", $id_hocki_to_delete);
        $check_related_stmt->execute();
        $check_related_stmt->bind_result($count_related);
        $check_related_stmt->fetch();
        $check_related_stmt->close();

        if ($count_related > 0) {
            $_SESSION['message'] = "Không thể xóa học kỳ này vì có lớp học phần đang thuộc học kỳ này. Vui lòng di chuyển hoặc xóa các lớp học phần liên quan trước.";
            $_SESSION['message_type'] = "error";
        } else {
            $stmt_delete = $conn->prepare("DELETE FROM hocki WHERE id_hocki = ?");
            if ($stmt_delete === false) {
                $_SESSION['message'] = "Lỗi chuẩn bị truy vấn xóa: " . $conn->error;
                $_SESSION['message_type'] = "error";
            } else {
                $stmt_delete->bind_param("i", $id_hocki_to_delete);
                if ($stmt_delete->execute()) {
                    $_SESSION['message'] = "Học kỳ có ID: **" . htmlspecialchars($id_hocki_to_delete) . "** đã được xóa thành công!";
                    $_SESSION['message_type'] = "success";
                } else {
                    $_SESSION['message'] = "Lỗi khi xóa học kỳ: " . $stmt_delete->error;
                    $_SESSION['message_type'] = "error";
                }
                $stmt_delete->close();
            }
        }
    }
    header("Location: hocki.php");
    exit();
}

// --- Get message from session (if any) ---
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'] ?? 'success';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// --- Process Search and Filter ---
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_query = $_GET['search'];
}
if (isset($_GET['nam_hoc']) && !empty($_GET['nam_hoc'])) {
    $filter_nam_hoc = $_GET['nam_hoc'];
}

// --- Pagination Logic ---
$records_per_page = 10;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// Build dynamic SQL query based on search and filter
$sql_data = "SELECT id_hocki, ten_hocki, nam_hoc, trangthai FROM hocki";
$sql_count = "SELECT COUNT(id_hocki) FROM hocki";
$where_clauses = [];
$bind_types = '';
$bind_values = [];

if (!empty($search_query)) {
    $where_clauses[] = "ten_hocki LIKE ?";
    $bind_types .= "s";
    $bind_values[] = '%' . $search_query . '%';
}
if (!empty($filter_nam_hoc)) {
    $where_clauses[] = "nam_hoc = ?";
    $bind_types .= "s"; // Assuming nam_hoc is stored as VARCHAR/string
    $bind_values[] = $filter_nam_hoc;
}

if (!empty($where_clauses)) {
    $where_clause_str = " WHERE " . implode(" AND ", $where_clauses);
    $sql_data .= $where_clause_str;
    $sql_count .= $where_clause_str;
}

$sql_data .= " ORDER BY nam_hoc DESC, ten_hocki ASC LIMIT ?, ?";

// Get distinct years for the filter dropdown
$distinct_years = [];
$stmt_years = $conn->prepare("SELECT DISTINCT nam_hoc FROM hocki ORDER BY nam_hoc DESC");
if ($stmt_years) {
    $stmt_years->execute();
    $result_years = $stmt_years->get_result();
    while ($row = $result_years->fetch_assoc()) {
        $distinct_years[] = $row['nam_hoc'];
    }
    $stmt_years->close();
}


// Count total records (considering search and filter)
$stmt_count = $conn->prepare($sql_count);
if ($stmt_count === false) {
    die("Lỗi chuẩn bị truy vấn đếm: " . $conn->error . " SQL: " . $sql_count);
}

if (!empty($bind_values)) {
    $refs = [];
    foreach ($bind_values as $key => $value) {
        $refs[$key] = &$bind_values[$key];
    }
    array_unshift($refs, $bind_types);
    call_user_func_array([$stmt_count, 'bind_param'], $refs);
}

$stmt_count->execute();
$stmt_count->bind_result($total_records);
$stmt_count->fetch();
$stmt_count->close();

$total_pages = ceil($total_records / $records_per_page);

// Prepare and execute main query (considering search, filter, and pagination)
$stmt_select = $conn->prepare($sql_data);

if ($stmt_select === false) {
    die("Lỗi chuẩn bị truy vấn dữ liệu học kỳ: " . $conn->error . " SQL: " . $sql_data);
}

// Combine all bind values for the main query
$final_bind_values = array_merge($bind_values, [$offset, $records_per_page]);
$final_bind_types = $bind_types . "ii"; // Add 'ii' for offset and records_per_page

$refs = [];
foreach ($final_bind_values as $key => $value) {
    $refs[$key] = &$final_bind_values[$key];
}
array_unshift($refs, $final_bind_types);

call_user_func_array([$stmt_select, 'bind_param'], $refs);

$stmt_select->execute();
$result = $stmt_select->get_result();
$hockis = $result->fetch_all(MYSQLI_ASSOC);
$stmt_select->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Học kỳ - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="<?php echo $base_url; ?>style.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>admin/css/menubar.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>admin/css/mainlist.css">
    <style>
        /* General styles for filter and search */
        .filter-search-bar {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            align-items: center;
            flex-wrap: wrap; /* Allow wrapping on smaller screens */
        }

        .filter-search-bar form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-search-bar input[type="text"],
        .filter-search-bar select {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 1em;
            min-width: 150px; /* Ensure inputs/selects are not too small */
        }

        .filter-search-bar button {
            padding: 8px 15px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .filter-search-bar button:hover {
            background-color: #0056b3;
        }

        .btn-clear-filters {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: #dc3545;
            font-size: 0.9em;
            white-space: nowrap; /* Prevent wrapping */
        }
        .btn-clear-filters i {
            margin-right: 5px;
        }

        /* Adjust action-bar to integrate filter/search and add button */
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        /* CSS for status column */
        .status-active, .status-inactive {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 0.9em;
            text-shadow: 0 1px 0 rgba(0,0,0,0.1);
        }

        .status-active { /* When trangthai = 0 (HIỆN) */
            background-color: #e6f7ed; /* Light green */
            color: #52c41a; /* Green */
            border: 1px solid #b7eb8f;
        }

        .status-inactive { /* When trangthai = 1 (ẨN) */
            background-color: #fff1f0; /* Light red */
            color: #f5222d; /* Red */
            border: 1px solid #ffa39e;
        }

        .status-active .fas, .status-inactive .fas {
            margin-right: 5px;
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
            <h2>Danh sách Học kỳ</h2>

            <?php if (!empty($message)) : ?>
                <div class="alert <?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="action-bar">
                <div class="filter-search-bar">
                    <form action="hocki.php" method="GET">
                        <input type="text" name="search" placeholder="Tìm theo tên học kỳ..."
                               value="<?php echo htmlspecialchars($search_query); ?>">
                        
                        <select name="nam_hoc">
                            <option value="">-- Chọn Năm học --</option>
                            <?php foreach ($distinct_years as $year) : ?>
                                <option value="<?php echo htmlspecialchars($year); ?>"
                                        <?php echo ($year == $filter_nam_hoc) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($year); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit"><i class="fas fa-filter"></i> Lọc</button>
                        <?php if (!empty($search_query) || !empty($filter_nam_hoc)) : ?>
                            <a href="hocki.php" class="btn-clear-filters">
                                <i class="fas fa-times-circle"></i> Xóa bộ lọc
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
                <a href="themhocki.php" class="btn-add"><i class="fas fa-plus"></i> Thêm Học kỳ mới</a>
            </div>

            <div class="scroll-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID Học kỳ</th>
                            <th>Tên Học kỳ</th>
                            <th>Năm học</th>
                            <th>Trạng thái</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($hockis)) : ?>
                        <?php foreach ($hockis as $hocki) : ?>
                            <tr>
                                <td><?php echo htmlspecialchars($hocki['id_hocki']); ?></td>
                                <td><?php echo htmlspecialchars($hocki['ten_hocki']); ?></td>
                                <td><?php echo htmlspecialchars($hocki['nam_hoc']); ?></td>
                                <td>
                                    <?php if ($hocki['trangthai'] == 0) : // 0 means visible/active ?>
                                        <span class="status-active"><i class="fas fa-eye"></i> Hiện</span>
                                    <?php else : // 1 means hidden/inactive ?>
                                        <span class="status-inactive"><i class="fas fa-eye-slash"></i> Ẩn</span>
                                    <?php endif; ?>
                                </td>
                                <td class="data-action-buttons">
                                    <a class="btn-edit" href="suahocki.php?id=<?php echo htmlspecialchars($hocki['id_hocki']); ?>"><i class="fas fa-edit"></i> Sửa</a>
                                    <a class="btn-delete" href="hocki.php?action=delete&id=<?php echo htmlspecialchars($hocki['id_hocki']); ?>" onclick="return confirm('Bạn có chắc chắn muốn xóa học kỳ <?php echo htmlspecialchars($hocki['ten_hocki']); ?> năm <?php echo htmlspecialchars($hocki['nam_hoc']); ?> không? Thao tác này có thể ảnh hưởng đến các lớp học phần liên quan!');"><i class="fas fa-trash-alt"></i> Xóa</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="5" style="text-align:center;">Không tìm thấy học kỳ nào
                                <?php 
                                    if (!empty($search_query) && !empty($filter_nam_hoc)) {
                                        echo "với tên '" . htmlspecialchars($search_query) . "' và năm học '" . htmlspecialchars($filter_nam_hoc) . "'";
                                    } elseif (!empty($search_query)) {
                                        echo "với tên '" . htmlspecialchars($search_query) . "'";
                                    } elseif (!empty($filter_nam_hoc)) {
                                        echo "trong năm học '" . htmlspecialchars($filter_nam_hoc) . "'";
                                    }
                                ?>.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                <?php if ($total_pages > 1) : ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                        <a href="hocki.php?page=<?php echo $i; ?>
                            <?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>
                            <?php echo !empty($filter_nam_hoc) ? '&nam_hoc=' . urlencode($filter_nam_hoc) : ''; ?>"
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