
-- =========================================================
-- 8. STORED PROCEDURE (sp_tambah_penjualan)
-- =========================================================

DELIMITER //

CREATE PROCEDURE sp_tambah_penjualan(
    IN p_iduser INT,
    IN p_idmargin INT,
    IN p_subtotal INT,
    IN p_ppn INT,
    IN p_total INT,
    OUT p_idpenjualan INT
)
BEGIN
    INSERT INTO penjualan (subtotal_nilai, ppn, total_nilai, iduser, idmargin_penjualan)
    VALUES (p_subtotal, p_ppn, p_total, p_iduser, p_idmargin);
    
    -- Mengambil ID penjualan yang baru saja digenerate
    SET p_idpenjualan = LAST_INSERT_ID();
END //

DELIMITER ;
CALL sp_tambah_penjualan(2, 2, 50000, 5000, 55000, @id_baru);
SELECT @id_baru;
