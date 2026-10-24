
-- =========================================================
-- 9. TRIGGER (trg_after_insert_penjualan / Kartu Stok)
-- =========================================================

DELIMITER //

CREATE TRIGGER trg_after_insert_detail_penjualan
AFTER INSERT ON detail_penjualan
FOR EACH ROW
BEGIN
    DECLARE v_stok_sebelumnya INT;
    DECLARE v_stok_terbaru INT;

    -- Ambil stok terakhir barang tersebut
    SELECT fn_get_stok_terakhir(NEW.idbarang) INTO v_stok_sebelumnya;
    
    -- Hitung stok baru (stok lama - jumlah keluar)
    SET v_stok_terbaru = v_stok_sebelumnya - NEW.jumlah;

    -- Insert ke kartu_stok
    INSERT INTO kartu_stok (jenis_transaksi, masuk, keluar, stock, idtransaksi, idbarang)
    VALUES ('K', 0, NEW.jumlah, v_stok_terbaru, NEW.penjualan_idpenjualan, NEW.idbarang);
END //

DELIMITER ;
