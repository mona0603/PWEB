--  		STORED PROCEDURES			--

-- Se actualizaron los SP para que sean compatibles con longblob, no borre los de antes por si acaso no manches que miedo.

--  					1.1 Registrar usuarios con BLOB 					--
DELIMITER $$
CREATE PROCEDURE `RegisterUser`(
    IN r_Username VARCHAR(100), 
    IN r_DisplayName VARCHAR(100), 
    IN r_Email VARCHAR(100),
    IN r_Birthdate DATE,
    IN r_Password VARCHAR(255),
    IN r_Recovery VARCHAR(255),
    IN r_Avatar LONGBLOB,
    IN r_Banner LONGBLOB,
    IN r_UserType INT,
    IN r_Deactivated TINYINT
)
BEGIN
    INSERT INTO User (
        Username, DisplayName, Email, Birthdate, Password, Recovery, Avatar, Banner, UserType, Deactivated
    ) VALUES (
        r_Username, r_DisplayName, r_Email, r_Birthdate, r_Password, r_Recovery, r_Avatar, r_Banner, r_UserType, r_Deactivated
    );
END$$
DELIMITER ;

--  					2.1 Actualizar usuarios con BLOB 					--
DELIMITER $$
CREATE PROCEDURE `UpdateUser`(
    IN p_ID INT,
    IN p_Username VARCHAR(100),
    IN p_DisplayName VARCHAR(100),
    IN p_Email VARCHAR(100),
    IN p_Gender VARCHAR(20),
    IN p_Birthdate DATE,
    IN p_Avatar LONGBLOB,
    IN p_Banner LONGBLOB,
    IN p_Bio VARCHAR(255)
)
BEGIN
    UPDATE User
    SET Username = p_Username,
        DisplayName = p_DisplayName,
        Email = p_Email,
        Gender = p_Gender,
        Birthdate = p_Birthdate,
        Avatar = COALESCE(p_Avatar, Avatar),
        Banner = COALESCE(p_Banner, Banner),
        Bio = p_Bio
    WHERE ID = p_ID;
END$$
DELIMITER ;

--  					3. Crear infografias 					--
DELIMITER $$
CREATE PROCEDURE `CreatePedia`(
    IN cp_Title VARCHAR(50),
    IN cp_Content LONGTEXT,
    IN cp_Logo LONGBLOB,
    IN cp_Media LONGBLOB,
    IN cp_AuthorID INT,
    IN cp_HID INT
)
BEGIN
    INSERT INTO MyPedia (MP_Title, MP_Content, MP_Logo, MP_Media, MP_AuthorID, MP_HID)
    VALUES (cp_Title, cp_Content, cp_Logo, cp_Media, cp_AuthorID, cp_HID);
    
    SELECT LAST_INSERT_ID() AS NewID;
END$$
DELIMITER ;