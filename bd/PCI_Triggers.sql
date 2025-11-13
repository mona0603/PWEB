--  		TRIGGERS			--

--  					1. Actualizar cuantas vistas tiene la infografia clickeada
DELIMITER //
CREATE TRIGGER TPediaView
AFTER INSERT ON MyPediaViews
FOR EACH ROW
BEGIN
    UPDATE MyPedia
    SET MP_Views = MP_Views + 1
    WHERE ID_MP = NEW.ID_MP;
END;
//
DELIMITER ;

--  					2. Actualizar cuantas vistas tiene las publicaciones de usuarios
DELIMITER //

CREATE TRIGGER TPostView
AFTER INSERT ON PostViews
FOR EACH ROW
BEGIN
    UPDATE post
    SET Views = Views + 1
    WHERE PostID = NEW.post_ID;
END;
//

DELIMITER ;