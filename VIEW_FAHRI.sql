
-- View 1: Role
CREATE OR REPLACE VIEW v_master_role AS
SELECT 
    idrole,
    nama_role
FROM role;

-- View 2: User
CREATE OR REPLACE VIEW v_master_user AS
SELECT 
    u.iduser,
    u.username,
    r.nama_role
FROM user u
JOIN role r ON u.idrole = r.idrole;

-- View 3: Margin Penjualan
CREATE OR REPLACE VIEW v_master_margin AS
SELECT  
    m.idmargin_penjualan, 
    m.persen, 
    IF(m.status = 1, 'Aktif', 'Non-Aktif') AS status_margin, 
    u.username AS diubah_oleh, 
    m.updated_at
FROM margin_penjualan m
JOIN user u ON m.iduser = u.iduser;

-- View 4: Satuan
CREATE OR REPLACE VIEW v_master_satuan AS
SELECT 
    idsatuan,
    nama_satuan,
    IF(status = 1, 'Aktif', 'Non-Aktif') AS status_satuan
FROM satuan;

-- View 5: Vendor (Menampilkan nilai asli seperti 'Y', 'N', dan 'A')
CREATE OR REPLACE VIEW v_master_vendor AS
SELECT 
    idvendor,
    nama_vendor,
    badan_hukum,
    status AS status_vendor
FROM vendor;

-- View 6: Barang
CREATE OR REPLACE VIEW v_master_barang AS
SELECT 
    b.idbarang,
    b.jenis,
    b.nama AS nama_barang,
    s.nama_satuan,
    b.harga,
    IF(b.status = 1, 'Aktif', 'Non-Aktif') AS status_barang
FROM barang b
JOIN satuan s ON b.idsatuan = s.idsatuan;

SELECT * FROM v_master_role;
SELECT * FROM v_master_user;
SELECT * FROM v_master_margin;
SELECT * FROM v_master_satuan;
SELECT * FROM v_master_vendor;
SELECT * FROM v_master_barang;
