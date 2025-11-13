-- Segun yo si te mando todo para que funcione, pero si te salta error dime, porque si se me pudo olvidar algo
USE PCI;
-- DROP DATABASE PCI; La borre por los cambios XD
SHOW VARIABLES LIKE 'max_allowed_packet'; -- si ejecutas verás q aqui solo deja guardar fotos/videos de 1MB
-- SET GLOBAL max_allowed_packet = 100*1024*1024;  -- abria que ejecutar esto para q deje hasta de 100MB pero ya no le moví a eso, solo use imagenes de menos de 1MB

--  		TABLES			--
-- 					1. User 					--
CREATE TABLE User (
   ID INT AUTO_INCREMENT PRIMARY KEY,
   Username varchar(100),
   DisplayName varchar(100),
   Bio varchar(100),
   Email varchar(100) NOT NULL,
   Password varchar(100) NOT NULL,
   Recovery varchar(50) NOT NULL,
   Avatar varchar(255),
   Banner varchar(255),
   Birthdate DATE NOT NULL,
   Gender ENUM('Male', 'Female', 'Other'),
   Register TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
   UserType INT NOT NULL -- Si es 0: USUARIO COMÚN, si es 1: ADMINISTRADOR
);

Select * from user;

ALTER TABLE User
MODIFY COLUMN Deactivated TINYINT(1) DEFAULT 0;


ALTER TABLE User 
MODIFY COLUMN Recovery VARCHAR(255) DEFAULT '';


-- Se modifico banner y avatar de varchar a LONGBLOB

ALTER TABLE User 
MODIFY Avatar LONGBLOB,
MODIFY Banner LONGBLOB;
 
ALTER TABLE User 
ADD GoogleID VARCHAR(255) UNIQUE;
-- Agregar para "borrar" al usuario

UPDATE User
SET Deactivated = 0
WHERE Deactivated IS NULL;

ALTER TABLE User 
ADD Deactivated TINYINT; -- 0 Activo, 1 Desactivado

select*FROM USER;

SELECT ID, Username, LENGTH(Avatar) AS size_bytes FROM User;


DELETE FROM USER
WHERE ID=3;

-- UPDATE User
-- SET Deactivated = 0 WHERE ID = 3;
-- SELECT Email, Deactivated FROM User WHERE Email = 'wa@gmail.com';

UPDATE User
SET UserType = 1 WHERE ID = 2; -- NOTA: habrá que cambiarle manualmente a un usuario que es admin xd

-- Lo mismo con el birthdate, viene de la mano con lo de google asi que aun no hay problema si no se hace nada.
ALTER TABLE User 
MODIFY Birthdate DATE NULL;
 
-- 					2. Follower 					--
CREATE TABLE Follower (
    FID INT AUTO_INCREMENT PRIMARY KEY,
    Follower_ID INT NOT NULL,
    Following_ID INT NOT NULL,
    F_Date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (Follower_ID) REFERENCES User(ID) ON DELETE CASCADE,
    FOREIGN KEY (Following_ID) REFERENCES User(ID) ON DELETE CASCADE,
    UNIQUE (Follower_ID, Following_ID)
);
-- TRUNCATE TABLE Follower;
-- SELECT * FROM Follower;

-- 					3. Posts 					--
CREATE TABLE post (
    PostID INT AUTO_INCREMENT PRIMARY KEY,
    User_ID INT NOT NULL,
    Content TEXT,
	Media LONGBLOB,
    MediaType VARCHAR(50),
    Likes INT DEFAULT 0,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    Comments INT DEFAULT 0,
    Edited TINYINT(1) DEFAULT 0,
    
 FOREIGN KEY (User_ID) REFERENCES User(ID) ON DELETE CASCADE
);

ALTER TABLE post ADD COLUMN Views INT DEFAULT 0; -- para el trigger
ALTER TABLE Post ADD COLUMN PostType ENUM('Post', 'News') DEFAULT 'Post'; -- para las noticias
ALTER TABLE post ADD COLUMN Title TEXT; -- añadí un campo para un titulo en la publicacion
ALTER TABLE Post
MODIFY COLUMN Title VARCHAR(100) NOT NULL; -- lo modifcamos para q no sea nulo XD y sea varchar mejor


SELECT *FROM post;

ALTER TABLE post ADD COLUMN Status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending';

-- 					3.1 Vistas de los posts 					--
CREATE TABLE PostViews (
    vp_ID INT AUTO_INCREMENT PRIMARY KEY,
    post_ID INT NOT NULL, -- ID de la infografía vista
    ViewerID INT NULL,  -- ID del usuario que la vio (si está logueado)
    ViewedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_ID) REFERENCES post(PostID) ON DELETE CASCADE,
    FOREIGN KEY (ViewerID) REFERENCES User(ID) ON DELETE SET NULL
);
SELECT * FROM PostViews;

-- 					4. Likes 					--
CREATE TABLE PostLike (
    LikeID INT AUTO_INCREMENT PRIMARY KEY,
    Post_ID INT NOT NULL,
    User_ID INT NOT NULL,
    LikedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (Post_ID) REFERENCES Post(PostID) ON DELETE CASCADE,
    FOREIGN KEY (User_ID) REFERENCES User(ID) ON DELETE CASCADE,
    UNIQUE (Post_ID, User_ID) -- evita que un usuario dé like más de una vez al mismo post
);

-- 					5. Comment 					--
CREATE TABLE comment (
    CommentID INT AUTO_INCREMENT PRIMARY KEY,
    Post_ID INT NOT NULL,
    User_ID INT NOT NULL,
    Content TEXT,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ParentCommentID INT,
    Edited TINYINT(1) DEFAULT 0,
    
    FOREIGN KEY (Post_ID) REFERENCES Post(PostID) ON DELETE CASCADE,
    FOREIGN KEY (User_ID) REFERENCES User(ID) ON DELETE CASCADE,
	FOREIGN KEY (ParentCommentID) REFERENCES Comment(CommentID) ON DELETE CASCADE
);


-- 					6. Topics 					--
CREATE TABLE Topic (
    TopicID INT AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(50) NOT NULL UNIQUE
);
-- SELECT * FROM Topic;
-- SELECT * FROM PostTopic;

-- YucaNota: Como aun no hay funciones de admin al momento que envio esto, para las pruebas use el INSERT de abajo para que funcione.
INSERT INTO Topic (Name) VALUES ('Controversies'); -- Esto carga el topic para crear el post por ahora (puedes poner otro, no tiene que ser players)
-- NOTA: se añadió Players, World Rankings, Tournaments, Qualifiers, Controversies


-- TAMBIEN SE INCLUYE ESTA DE TOPICS / Esta tabla es la intermediaria para que se junte con los post
CREATE TABLE PostTopic ( 
    Post_ID INT NOT NULL,
    Topic_ID INT NOT NULL,
    
    FOREIGN KEY (Post_ID) REFERENCES Post(PostID) ON DELETE CASCADE,
    FOREIGN KEY (Topic_ID) REFERENCES Topic(TopicID) ON DELETE CASCADE,
    
    PRIMARY KEY (Post_ID, Topic_ID)  -- evita duplicados
);

-- 					7. Notification 					--
-- YucaNota: Las notificaciones solo funcionan de momento con follows, ya despues integro likes y comentarios
-- Si quieres otro tipo de notificacion ya me dices.

CREATE TABLE Notification (
    NID INT AUTO_INCREMENT PRIMARY KEY,
    User_ID INT NOT NULL,           -- el que recibe la notificación
    Actor_ID INT NOT NULL,          -- el que hizo la acción
    Type ENUM('follow','like','comment') NOT NULL,
    Message VARCHAR(255),
    IsRead BOOLEAN DEFAULT FALSE,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (User_ID) REFERENCES User(ID) ON DELETE CASCADE,
    FOREIGN KEY (Actor_ID) REFERENCES User(ID) ON DELETE CASCADE
);


ALTER TABLE Notification ADD COLUMN Post_ID INT NULL AFTER Actor_ID;
ALTER TABLE Notification
ADD FOREIGN KEY (Post_ID) REFERENCES Post(PostID) ON DELETE CASCADE;

ALTER TABLE Notification 
MODIFY COLUMN Type ENUM('follow', 'like', 'comment', 'review') NOT NULL;

SELECT *FROM Notification;

-- 					8. Infografias? 					--
CREATE TABLE MyPedia (
    ID_MP INT AUTO_INCREMENT PRIMARY KEY, -- ID
    MP_Title VARCHAR(50) NOT NULL UNIQUE,
    MP_Content LONGTEXT NOT NULL, -- contenido de la infografia
    MP_Logo LONGBLOB NOT NULL,
    MP_Media LONGBLOB,
    MP_CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    MP_AuthorID INT NOT NULL, -- autor de la infog
    MP_HID INT NOT NULL, -- id del tema de la infog
    FOREIGN KEY (MP_AuthorID) REFERENCES User(ID) ON DELETE CASCADE,
    FOREIGN KEY (MP_HID) REFERENCES History(H_ID) ON DELETE CASCADE -- Para saber que tema de la infografia es, de la tabla 9
);
ALTER TABLE MyPedia ADD COLUMN MP_Views INT DEFAULT 0; -- para el TRIGGER y ver cuantas views tiene la infografia

SELECT * FROM MyPedia;

-- esto era una prueba
-- UPDATE MyPedia
--  MP_Content = "Lorem ipsum dolor sit amet consectetur adipiscing elit fusce, donec consequat ullamcorper sem habitant purus commodo tempor fermentum, praesent mi mauris quam integer accumsan urna. Donec penatibus faucibus magnis posuere venenatis risus facilisi odio, vivamus quisque litora curabitur lacinia enim id egestas, metus orci nostra euismod nibh sem mi. Parturient curae faucibus fusce rutrum quisque sagittis eleifend inceptos, rhoncus per cursus mollis habitasse pulvinar.
-- At porttitor ridiculus curabitur tempus semper tincidunt diam facilisis nec, ultrices tempor porta leo natoque sagittis montes dictum aenean, nulla proin arcu dictumst elementum quam sed torquent. Facilisis vestibulum congue mi tincidunt ultricies laoreet augue fames torquent est, ridiculus sodales vivamus malesuada mollis cursus tempus imperdiet litora ullamcorper hac, himenaeos cubilia scelerisque aptent suscipit risus habitant eu non. Dui natoque vestibulum volutpat dignissim egestas, dictum consequat convallis elementum torquent hendrerit, neque enim senectus malesuada.
-- Lorem ipsum dolor sit amet consectetur adipiscing elit fusce, donec consequat ullamcorper sem habitant purus commodo tempor fermentum, praesent mi mauris quam integer accumsan urna. Donec penatibus faucibus magnis posuere venenatis risus facilisi odio, vivamus quisque litora curabitur lacinia enim id egestas, metus orci nostra euismod nibh sem mi. Parturient curae faucibus fusce rutrum quisque sagittis eleifend inceptos, rhoncus per cursus mollis habitasse pulvinar.
-- At porttitor ridiculus curabitur tempus semper tincidunt diam facilisis nec, ultrices tempor porta leo natoque sagittis montes dictum aenean, nulla proin arcu dictumst elementum quam sed torquent. Facilisis vestibulum congue mi tincidunt ultricies laoreet augue fames torquent est, ridiculus sodales vivamus malesuada mollis cursus tempus imperdiet litora ullamcorper hac, himenaeos cubilia scelerisque aptent suscipit risus habitant eu non. Dui natoque vestibulum volutpat dignissim egestas, dictum consequat convallis elementum torquent hendrerit, neque enim senectus malesuada."
-- WHERE ID_MP = 5;

-- 					8.1 Vistas de las infografias 					--
CREATE TABLE MyPediaViews (
    ViewID INT AUTO_INCREMENT PRIMARY KEY,
    ID_MP INT NOT NULL, -- ID de la infografía vista
    ViewerID INT NULL,  -- ID del usuario que la vio (si está logueado)
    ViewedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ID_MP) REFERENCES MyPedia(ID_MP) ON DELETE CASCADE,
    FOREIGN KEY (ViewerID) REFERENCES User(ID) ON DELETE SET NULL
);
SELECT * FROM MyPediaViews;

-- 					9. History topics 					--
CREATE TABLE History (
    H_ID INT AUTO_INCREMENT PRIMARY KEY,
    H_Name VARCHAR(50) NOT NULL UNIQUE
);

-- 		!	TEMAS (se agregaron los que estan en la seccion de HISTORY y EVENTS de la página, solo copias y pegas):
-- Top Leagues, Origins, Legends, World Cups, Eurocup, Champions, America's Cup (para el de americas cup solo pones doble apostrofe al insertar)

-- 					10. Contenido de los tags 					--
-- EJ: Sede: Mexico, Fecha de Inicio: Octubre 2025
CREATE TABLE MyPediaContent (
    ID_Extra INT AUTO_INCREMENT PRIMARY KEY,
    ID_MP INT NOT NULL,
    Field_Name VARCHAR(100) NOT NULL,
    Field_Value TEXT NOT NULL,
    FOREIGN KEY (ID_MP) REFERENCES MyPedia(ID_MP) ON DELETE CASCADE
);
SELECT * FROM MyPediaContent;


select * from History;
INSERT INTO History (H_Name) VALUES ('America''s Cup'); 

select * from History;
INSERT INTO History (H_Name) VALUES ('Origins'); 