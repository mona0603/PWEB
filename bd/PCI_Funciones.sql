--  		FUNCTION			--

--  					1. Funcion para saber si el usuario logeado sigue a ciertos usuarios
DELIMITER $$
CREATE FUNCTION AlreadyFollows(ActualUser INT, AnotherUser INT)
RETURNS BOOLEAN
DETERMINISTIC
BEGIN
    DECLARE Results BOOLEAN;
    SELECT EXISTS (
        SELECT 1 FROM Follower 
        WHERE Follower_ID = ActualUser AND Following_ID = AnotherUser
    ) INTO Results;
    RETURN Results;
END$$
DELIMITER ;

-- DROP FUNCTION AlreadyFollows;
-- de parametros es el usuario actual / usuario a comparar
SELECT AlreadyFollows(4, 2) AS isFollowing;

--                          2.Funcion para aprobar o rechazar publicaciones -- 

DELIMITER $$

CREATE FUNCTION fn_update_post_status(p_postId INT, p_status ENUM('Approved', 'Rejected'))
RETURNS VARCHAR(100)
DETERMINISTIC
BEGIN
    DECLARE msg VARCHAR(100);

    UPDATE Post
    SET Status = p_status
    WHERE PostID = p_postId;

    IF ROW_COUNT() > 0 THEN
        SET msg = CONCAT('Publicación ', p_status, ' correctamente.');
    ELSE
        SET msg = 'No se encontró la publicación o no se actualizó.';
    END IF;

    RETURN msg;
END$$

DELIMITER ;

