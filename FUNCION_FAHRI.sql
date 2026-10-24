-- File Function Stored Procedure oleh Fahri
-- =========================================================
-- 7. DATABASE FUNCION 
-- =========================================================

DELIMITER //

CREATE FUNCTION fn_get_stok_terakhir(p_idbarang INT) 
RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE s_terakhir INT;
    
    SELECT IFNULL(stock, 0) INTO s_terakhir
    FROM kartu_stok
    WHERE idbarang = p_idbarang
    ORDER BY idkartu_stok DESC
    LIMIT 1;
    
    RETURN s_terakhir;
END //

DELIMITER ;
SELECT fn_get_stok_terakhir(1);
