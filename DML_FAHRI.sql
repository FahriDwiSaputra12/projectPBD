
-- Data Master: role
INSERT INTO role (nama_role) VALUES 
('Admin'),
('Kasir'),
('Manager');

-- Data Master: user
INSERT INTO user (username, password, idrole) VALUES 
('admin1', 'pass123', 1),
('kasir1', 'kasir123', 2),
('manager1', 'man123', 3);

-- Data Master: margin_penjualan
INSERT INTO margin_penjualan (persen, status, iduser) VALUES 
(10.0, 0, 1),
(20.0, 1, 1),
(30.0, 0, 1),
(40.0, 0, 1),
(50.0, 0, 1);

-- Data Master: satuan
INSERT INTO satuan (nama_satuan, status) VALUES 
('Pcs', 1),
('Dus', 1),
('Kg', 1);

-- Data Master: vendor
INSERT INTO vendor (nama_vendor, badan_hukum, status) VALUES 
('PT Sumber Makmur', 'Y', 'A'),
('CV Maju Bersama', 'Y', 'A'),
('UD Sejahtera', 'N', 'A');

-- Data Master: barang
INSERT INTO barang (jenis, nama, idsatuan, status, harga) VALUES 
('A', 'Buku Tulis', 1, 1, 5000),
('A', 'Pulpen Gel', 1, 1, 3000),
('B', 'Kertas A4', 2, 1, 45000);
