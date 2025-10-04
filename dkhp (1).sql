-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th6 04, 2025 lúc 07:08 PM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `dkhp`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `dangky_hocphan`
--

CREATE TABLE `dangky_hocphan` (
  `id_dkhp` int(11) NOT NULL,
  `id_taikhoan` int(11) NOT NULL,
  `id_lop_hp` int(11) NOT NULL,
  `ngay_dang_ky` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `dangky_hocphan`
--

INSERT INTO `dangky_hocphan` (`id_dkhp`, `id_taikhoan`, `id_lop_hp`, `ngay_dang_ky`) VALUES
(2, 6, 7, '2025-06-03 18:51:36'),
(3, 6, 5, '2025-06-03 18:53:36'),
(4, 6, 6, '2025-06-03 19:03:51'),
(5, 6, 2, '2025-06-03 19:03:53');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `hocki`
--

CREATE TABLE `hocki` (
  `id_hocki` int(11) NOT NULL,
  `ten_hocki` varchar(100) NOT NULL,
  `nam_hoc` varchar(100) NOT NULL,
  `trangthai` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `hocki`
--

INSERT INTO `hocki` (`id_hocki`, `ten_hocki`, `nam_hoc`, `trangthai`) VALUES
(1, 'Học kì I', '2025-2026', 0);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `khoa`
--

CREATE TABLE `khoa` (
  `ma_khoa` int(11) NOT NULL,
  `ten_khoa` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `khoa`
--

INSERT INTO `khoa` (`ma_khoa`, `ten_khoa`) VALUES
(2, 'Công nghệ Ô Tô'),
(1, 'Công Nghệ Thông Tin 2020');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `lich_hoc`
--

CREATE TABLE `lich_hoc` (
  `id_lich_hoc` int(11) NOT NULL,
  `id_lop_hp` int(11) NOT NULL COMMENT 'Khóa ngoại đến lop_hocphan',
  `ngay_trong_tuan` int(11) NOT NULL COMMENT 'Thứ trong tuần (1=Thứ 2, 2=Thứ 3,..., 6=Thứ 7, 7=Chủ nhật)',
  `tiet_bat_dau` int(11) NOT NULL COMMENT 'Tiết học bắt đầu',
  `tiet_ket_thuc` int(11) NOT NULL COMMENT 'Tiết học kết thúc',
  `ghi_chu` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `lich_hoc`
--

INSERT INTO `lich_hoc` (`id_lich_hoc`, `id_lop_hp`, `ngay_trong_tuan`, `tiet_bat_dau`, `tiet_ket_thuc`, `ghi_chu`) VALUES
(1, 3, 1, 1, 3, ''),
(2, 3, 3, 1, 2, ''),
(3, 4, 1, 1, 2, ''),
(4, 4, 3, 3, 4, ''),
(5, 4, 5, 1, 4, ''),
(6, 7, 1, 1, 4, ''),
(7, 7, 3, 6, 9, '');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `lop_hocphan`
--

CREATE TABLE `lop_hocphan` (
  `id_lop_hp` int(11) NOT NULL,
  `ten_lop_hocphan` varchar(255) NOT NULL,
  `id_mon` int(11) NOT NULL,
  `id_taikhoan` int(11) NOT NULL COMMENT 'Ma_tk của giang vien phu trach lop hoc phan (vaitro = 2)',
  `id_hocki` int(11) NOT NULL,
  `si_so_toi_da` int(11) DEFAULT NULL,
  `si_so_hien_tai` int(11) NOT NULL DEFAULT 0,
  `id_phong` int(11) NOT NULL,
  `trangthai` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `lop_hocphan`
--

INSERT INTO `lop_hocphan` (`id_lop_hp`, `ten_lop_hocphan`, `id_mon`, `id_taikhoan`, `id_hocki`, `si_so_toi_da`, `si_so_hien_tai`, `id_phong`, `trangthai`) VALUES
(1, 'LTNOT25_1', 4, 5, 1, 50, 0, 1, 0),
(2, 'LTW25_1', 1, 5, 1, 50, 1, 1, 2),
(3, '4124', 4, 5, 1, 12, 0, 1, 0),
(4, '12312', 4, 5, 1, 12, 0, 1, 2),
(5, '12421', 1, 5, 1, 12, 1, 1, 0),
(6, 'ttst', 4, 5, 1, 123, 1, 1, 0),
(7, '12412', 4, 5, 1, 12, 1, 1, 0),
(8, '1421', 4, 5, 1, 12, 0, 1, 0);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `mon`
--

CREATE TABLE `mon` (
  `id_mon` int(11) NOT NULL,
  `ten_mon` varchar(100) NOT NULL,
  `id_khoa` int(11) NOT NULL,
  `so_tin_chi` int(11) NOT NULL DEFAULT 3 COMMENT 'Số tín chỉ của môn học',
  `gia_tin_chi` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Giá tiền trên một tín chỉ'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `mon`
--

INSERT INTO `mon` (`id_mon`, `ten_mon`, `id_khoa`, `so_tin_chi`, `gia_tin_chi`) VALUES
(1, 'Lập trình Web', 1, 3, 0.00),
(4, 'Lập trình nhúng OTO', 2, 3, 0.00),
(5, 'senso và ứng dụng', 1, 4, 35000.00);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `phong_hoc`
--

CREATE TABLE `phong_hoc` (
  `id_phong` int(11) NOT NULL,
  `ten_phong` varchar(50) NOT NULL,
  `suc_chua` int(11) DEFAULT NULL,
  `loai_phong` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `phong_hoc`
--

INSERT INTO `phong_hoc` (`id_phong`, `ten_phong`, `suc_chua`, `loai_phong`) VALUES
(1, 'A206', 20, 'Thí nghiệm'),
(2, 'A207', 50, 'Lý thuyết');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `taikhoan`
--

CREATE TABLE `taikhoan` (
  `ma_tk` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `matkhau` varchar(32) NOT NULL COMMENT 'Mật khẩu đã mã hóa MD5',
  `ten_taikhoan` varchar(100) NOT NULL,
  `gioitinh` varchar(5) DEFAULT NULL,
  `ngaysinh` date DEFAULT NULL,
  `sdt` varchar(11) DEFAULT NULL,
  `vaitro` int(11) DEFAULT 1 COMMENT '0 Admin, 1 Sinhvien, 2 giangvien',
  `ma_khoa` int(11) DEFAULT NULL,
  `img` varchar(255) DEFAULT 'uploads/default_avatar.png'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `taikhoan`
--

INSERT INTO `taikhoan` (`ma_tk`, `email`, `matkhau`, `ten_taikhoan`, `gioitinh`, `ngaysinh`, `sdt`, `vaitro`, `ma_khoa`, `img`) VALUES
(1, '20004181@st.vlute.edu.vn', '1c1ba718636a544caad80f9117f675a7', 'thach', 'Nam', '0000-00-00', '', 0, NULL, 'uploads/1_1748959378.jpg'),
(4, 'testsv@gmail.com', '6195d4465bd2251f2a7f4ad1fcc87085', 'testsv@gmail.com', '', '0000-00-00', '', 1, 2, 'uploads/default_avatar.png'),
(5, 'trankimngan@gmail.com', '1c1ba718636a544caad80f9117f675a7', 'Trần kim Ngân', 'Nữ', '0000-00-00', '', 2, 1, 'uploads/default_avatar.png'),
(6, 'testsv2@gmail.com', '5ad2c5eee1dad910564546be3a1d5343', 'testsv2@gmail.com', '', '0000-00-00', '', 1, 1, 'uploads/default_avatar.png');

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `dangky_hocphan`
--
ALTER TABLE `dangky_hocphan`
  ADD PRIMARY KEY (`id_dkhp`),
  ADD KEY `id_taikhoan` (`id_taikhoan`),
  ADD KEY `id_lop_hp` (`id_lop_hp`);

--
-- Chỉ mục cho bảng `hocki`
--
ALTER TABLE `hocki`
  ADD PRIMARY KEY (`id_hocki`),
  ADD UNIQUE KEY `ten_hocki` (`ten_hocki`);

--
-- Chỉ mục cho bảng `khoa`
--
ALTER TABLE `khoa`
  ADD PRIMARY KEY (`ma_khoa`),
  ADD UNIQUE KEY `ten_khoa` (`ten_khoa`);

--
-- Chỉ mục cho bảng `lich_hoc`
--
ALTER TABLE `lich_hoc`
  ADD PRIMARY KEY (`id_lich_hoc`),
  ADD KEY `id_lop_hp` (`id_lop_hp`);

--
-- Chỉ mục cho bảng `lop_hocphan`
--
ALTER TABLE `lop_hocphan`
  ADD PRIMARY KEY (`id_lop_hp`),
  ADD KEY `id_mon` (`id_mon`),
  ADD KEY `id_taikhoan` (`id_taikhoan`),
  ADD KEY `id_hocki` (`id_hocki`),
  ADD KEY `id_phong` (`id_phong`);

--
-- Chỉ mục cho bảng `mon`
--
ALTER TABLE `mon`
  ADD PRIMARY KEY (`id_mon`),
  ADD UNIQUE KEY `ten_mon` (`ten_mon`),
  ADD KEY `id_khoa` (`id_khoa`);

--
-- Chỉ mục cho bảng `phong_hoc`
--
ALTER TABLE `phong_hoc`
  ADD PRIMARY KEY (`id_phong`),
  ADD UNIQUE KEY `ten_phong` (`ten_phong`);

--
-- Chỉ mục cho bảng `taikhoan`
--
ALTER TABLE `taikhoan`
  ADD PRIMARY KEY (`ma_tk`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `ma_khoa` (`ma_khoa`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `dangky_hocphan`
--
ALTER TABLE `dangky_hocphan`
  MODIFY `id_dkhp` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT cho bảng `hocki`
--
ALTER TABLE `hocki`
  MODIFY `id_hocki` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT cho bảng `khoa`
--
ALTER TABLE `khoa`
  MODIFY `ma_khoa` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT cho bảng `lich_hoc`
--
ALTER TABLE `lich_hoc`
  MODIFY `id_lich_hoc` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT cho bảng `lop_hocphan`
--
ALTER TABLE `lop_hocphan`
  MODIFY `id_lop_hp` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT cho bảng `mon`
--
ALTER TABLE `mon`
  MODIFY `id_mon` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT cho bảng `phong_hoc`
--
ALTER TABLE `phong_hoc`
  MODIFY `id_phong` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT cho bảng `taikhoan`
--
ALTER TABLE `taikhoan`
  MODIFY `ma_tk` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `dangky_hocphan`
--
ALTER TABLE `dangky_hocphan`
  ADD CONSTRAINT `dangky_hocphan_ibfk_1` FOREIGN KEY (`id_taikhoan`) REFERENCES `taikhoan` (`ma_tk`),
  ADD CONSTRAINT `dangky_hocphan_ibfk_2` FOREIGN KEY (`id_lop_hp`) REFERENCES `lop_hocphan` (`id_lop_hp`);

--
-- Các ràng buộc cho bảng `lich_hoc`
--
ALTER TABLE `lich_hoc`
  ADD CONSTRAINT `lich_hoc_ibfk_1` FOREIGN KEY (`id_lop_hp`) REFERENCES `lop_hocphan` (`id_lop_hp`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `lop_hocphan`
--
ALTER TABLE `lop_hocphan`
  ADD CONSTRAINT `lop_hocphan_ibfk_1` FOREIGN KEY (`id_mon`) REFERENCES `mon` (`id_mon`),
  ADD CONSTRAINT `lop_hocphan_ibfk_2` FOREIGN KEY (`id_taikhoan`) REFERENCES `taikhoan` (`ma_tk`),
  ADD CONSTRAINT `lop_hocphan_ibfk_3` FOREIGN KEY (`id_hocki`) REFERENCES `hocki` (`id_hocki`),
  ADD CONSTRAINT `lop_hocphan_ibfk_4` FOREIGN KEY (`id_phong`) REFERENCES `phong_hoc` (`id_phong`);

--
-- Các ràng buộc cho bảng `mon`
--
ALTER TABLE `mon`
  ADD CONSTRAINT `mon_ibfk_1` FOREIGN KEY (`id_khoa`) REFERENCES `khoa` (`ma_khoa`);

--
-- Các ràng buộc cho bảng `taikhoan`
--
ALTER TABLE `taikhoan`
  ADD CONSTRAINT `taikhoan_ibfk_1` FOREIGN KEY (`ma_khoa`) REFERENCES `khoa` (`ma_khoa`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
