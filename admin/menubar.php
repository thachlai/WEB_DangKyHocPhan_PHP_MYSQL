<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Đường dẫn gốc của trang web (đảm bảo đã được định nghĩa ở header.php hoặc ở đây)
// Nếu bạn include menubar.php trước header.php, bạn cần định nghĩa $base_url ở đây.
// Nếu menubar.php luôn được include sau header.php, thì $base_url đã có sẵn.
$base_url = '/dkhp/'; 

// Lấy thông tin người dùng từ session
$user_name = $_SESSION['ten_taikhoan'] ?? 'Người dùng'; // Thay 'Admin' bằng 'Người dùng' mặc định cho tổng quát hơn

// Lấy đường dẫn ảnh từ session
// Kiểm tra xem $_SESSION['img'] có tồn tại và không rỗng không
if (isset($_SESSION['img']) && !empty($_SESSION['img'])) {
    $user_img = $base_url . $_SESSION['img'];
    // Thêm kiểm tra sự tồn tại của file vật lý để phòng trường hợp ảnh đã bị xóa thủ công
    $file_path_on_server = $_SERVER['DOCUMENT_ROOT'] . $user_img;
    if (!file_exists($file_path_on_server)) {
        $user_img = $base_url . 'uploads/default_avatar.png'; // Dùng ảnh mặc định nếu file không tồn tại
    }
} else {
    $user_img = $base_url . 'uploads/default_avatar.png'; // Dùng ảnh mặc định nếu không có trong session
}

// Kiểm tra quyền Admin (có thể gọi lại để đảm bảo, nhưng thường đã có trong trang chính)
// include_once '../function.php'; // Đảm bảo function.php được include nếu check_admin() được gọi ở đây
// check_admin(); // Nếu bạn muốn kiểm tra quyền ngay trong menubar
?>

<link rel="stylesheet" href="<?php echo $base_url; ?>admin/css/menubar.css">

<button id="menu-toggle" class="menu-toggle-btn">&#9776;</button>

<aside class="admin-sidebar" id="sidebar">
    <nav class="admin-nav">
        <ul>
            
            <li class="accordion">
                <br><br><br>
                <a href="#">Quản lý Khoa <i class="fas fa-chevron-down toggle-icon"></i></a>
                <ul class="sub-menu">
                    <li><a href="<?php echo $base_url; ?>admin/khoa.php">Danh sách khoa</a></li>
                    <li><a href="<?php echo $base_url; ?>admin/themkhoa.php">Thêm Khoa</a></li>
                </ul>
            </li>
            <li class="accordion">
                <a href="#">Quản lý Tài khoản <i class="fas fa-chevron-down toggle-icon"></i></a>
                <ul class="sub-menu">
                    <li><a href="<?php echo $base_url; ?>admin/taikhoan.php">Danh sách Tài khoản</a></li>
                    <li><a href="<?php echo $base_url; ?>admin/themtaikhoan.php">Thêm Tài khoản</a></li>
                </ul>
            </li>
            <li class="accordion">
                <a href="#">Quản lý Phòng học <i class="fas fa-chevron-down toggle-icon"></i></a>
                <ul class="sub-menu">
                    <li><a href="<?php echo $base_url; ?>admin/phong.php">Danh sách phòng học</a></li>
                    <li><a href="<?php echo $base_url; ?>admin/themphong.php">Thêm Phòng học</a></li>
                </ul>
            </li>
            <li class="accordion">
                <a href="#">Quản lý Môn học <i class="fas fa-chevron-down toggle-icon"></i></a>
                <ul class="sub-menu">
                    <li><a href="<?php echo $base_url; ?>admin/mon.php">Danh sách Môn học</a></li>
                    <li><a href="<?php echo $base_url; ?>admin/themmon.php">Thêm Môn học</a></li>
                </ul>
            </li>

            <li class="accordion">
                <a href="#">Quản lý Học Kì <i class="fas fa-chevron-down toggle-icon"></i></a>
                <ul class="sub-menu">
                    <li><a href="<?php echo $base_url; ?>admin/hocki.php">Danh sách Học kì</a></li>
                    <li><a href="<?php echo $base_url; ?>admin/themhocki.php">Thêm Học kì</a></li>
                </ul>
            </li>

            <li class="accordion">
                <a href="#">Quản lý Lớp Học phần <i class="fas fa-chevron-down toggle-icon"></i></a>
                <ul class="sub-menu">
                    <li><a href="<?php echo $base_url; ?>admin/lophocphan.php">Danh sách Lớp HP</a></li>
                    <li><a href="<?php echo $base_url; ?>admin/themlophocphan.php">Thêm Lớp HP</a></li>
                </ul>
            </li>

            </ul>
    </nav>

    <div class="user-profile">
        <div class="profile-info">
            <img src="<?php echo $user_img; ?>" alt="Admin Avatar" class="profile-avatar">
            <p>Xin chào, <strong><?php echo htmlspecialchars($user_name); ?></strong></p>
        </div>
        <div class="profile-actions">
            <a href="<?php echo $base_url; ?>hoso.php">Hồ sơ</a>
            <a href="<?php echo $base_url; ?>dangxuat.php">Đăng xuất</a>
        </div>
    </div>
</aside>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        const sidebar = document.getElementById('sidebar');
        const toggleBtn = document.getElementById('menu-toggle');
        const body = document.body;

        if (toggleBtn && sidebar) {
            toggleBtn.addEventListener('click', () => {
                sidebar.classList.toggle('show');
                body.classList.toggle('sidebar-open');
            });
        }

        // Xử lý Accordion (dropdown của menu)
        document.querySelectorAll('.admin-nav .accordion > a').forEach(link => {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                const parentLi = this.parentElement;
                
                // Đóng tất cả các sub-menu khác trước khi mở cái này
                document.querySelectorAll('.admin-nav .accordion.active').forEach(activeAccordion => {
                    if (activeAccordion !== parentLi) {
                        activeAccordion.classList.remove('active');
                        activeAccordion.querySelector('.toggle-icon').classList.remove('fa-chevron-up');
                        activeAccordion.querySelector('.toggle-icon').classList.add('fa-chevron-down');
                    }
                });

                // Mở/đóng sub-menu hiện tại
                parentLi.classList.toggle('active');
                const toggleIcon = this.querySelector('.toggle-icon');
                if (toggleIcon) {
                    toggleIcon.classList.toggle('fa-chevron-up', parentLi.classList.contains('active'));
                    toggleIcon.classList.toggle('fa-chevron-down', !parentLi.classList.contains('active'));
                }
            });
        });
    });
</script>