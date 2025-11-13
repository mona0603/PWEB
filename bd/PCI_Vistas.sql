--  		VIEWS			--

--  					1. Informacion del usuario basica 					--
CREATE OR REPLACE VIEW ViewProfile AS
SELECT 
    ID, 
    Username, 
    DisplayName, 
    Bio, 
    Avatar, 
    Banner, 
    Deactivated
FROM User
WHERE Deactivated = 0 OR Deactivated IS NULL;

SELECT * FROM ViewProfile WHERE ID = 2;

-- ! NOTA: Las VIEWS de la 2 a la 4 fueron modificadas agregando WHERE u.Deactivated = 0; para que no muestre usuarios que estan desactivados en los seguidos o seguidores
--  					2. Mostrar cuantos seguidores y seguidos tiene el usuario 					--
CREATE OR REPLACE VIEW CountFollow AS
SELECT 
    u.ID AS User_ID,
    (
        SELECT COUNT(*)
        FROM Follower f
        JOIN User uf ON uf.ID = f.Following_ID
        WHERE f.Follower_ID = u.ID AND COALESCE(uf.Deactivated, 0) = 0
    ) AS total_following,
    (
        SELECT COUNT(*)
        FROM Follower f
        JOIN User uf ON uf.ID = f.Follower_ID
        WHERE f.Following_ID = u.ID AND COALESCE(uf.Deactivated, 0) = 0
    ) AS total_followers
FROM User u
WHERE COALESCE(u.Deactivated, 0) = 0;


--  					3. ViewFollowing (todas las relaciones) 					--
CREATE OR REPLACE VIEW ViewFollowing AS
SELECT 
    f.Follower_ID,
    u.ID AS Following_ID,
    u.DisplayName,
    u.Username,
    u.Avatar,
    u.Banner,
    u.Bio,
    cf.total_following,
    cf.total_followers
FROM Follower f
JOIN User u ON u.ID = f.Following_ID
LEFT JOIN CountFollow cf ON cf.User_ID = u.ID
WHERE 
    COALESCE(u.Deactivated, 0) = 0;



--  					4. ViewFollowers (todas las relaciones) 					--
CREATE OR REPLACE VIEW ViewFollowers AS
SELECT 
    f.Following_ID,
    u.ID AS Follower_ID,
    u.DisplayName,
    u.Username,
    u.Avatar,
    u.Banner,
    u.Bio,
    cf.total_following,
    cf.total_followers
FROM Follower f
JOIN User u ON u.ID = f.Follower_ID
LEFT JOIN CountFollow cf ON cf.User_ID = u.ID
WHERE 
    COALESCE(u.Deactivated, 0) = 0;


--  					6. Notificaciones 					--
CREATE OR REPLACE VIEW ViewNotification AS
SELECT 
    n.NID,
    n.User_ID,
    n.Actor_ID,
    n.Type,
    n.Message,
    n.IsRead,
    n.CreatedAt,
    u.DisplayName AS ActorName,
    u.Username AS ActorUsername,
    u.Avatar AS ActorAvatar
FROM Notification n
JOIN User u ON u.ID = n.Actor_ID
WHERE COALESCE(u.Deactivated, 0) = 0;


--  					7. Enciclopedia completa 					--
CREATE OR REPLACE VIEW ViewMyPedia AS
SELECT
    m.ID_MP AS ID,
    m.MP_HID AS H_ID,
    m.MP_Logo AS Logo,
    m.MP_Title AS Title,
    m.MP_Content AS Content,
    m.MP_Media AS Media,
    m.MP_CreatedAt AS CreatedAt,
    u.DisplayName AS AuthorName,
    m.MP_Views AS Views
FROM
    MyPedia m
INNER JOIN
    User u ON m.MP_AuthorID = u.ID
WHERE
    COALESCE(u.Deactivated, 0) = 0;


SELECT * FROM ViewMyPedia;

--  					8. Posts con informacion completa 					--
CREATE OR REPLACE VIEW ViewPosts AS
SELECT
    p.PostID,
    p.Content,
    p.Title,
    p.Media,
    p.MediaType,
    p.CreatedAt,
    p.Edited,
    p.Views,
    p.Status,
    p.User_ID AS UserID,
    u.Username,
    u.Avatar,
    t.Name AS TopicName,
    COUNT(DISTINCT pl.LikeID) AS Likes,
    COUNT(DISTINCT c.CommentID) AS Comments
FROM Post p
LEFT JOIN User u ON u.ID = p.User_ID AND (u.Deactivated = 0 OR u.Deactivated IS NULL)
LEFT JOIN PostTopic pt ON pt.Post_ID = p.PostID
LEFT JOIN Topic t ON t.TopicID = pt.Topic_ID
LEFT JOIN PostLike pl ON pl.Post_ID = p.PostID
LEFT JOIN User pl_user ON pl_user.ID = pl.User_ID AND (pl_user.Deactivated = 0 OR pl_user.Deactivated IS NULL)
LEFT JOIN Comment c ON c.Post_ID = p.PostID
LEFT JOIN User c_user ON c_user.ID = c.User_ID AND (c_user.Deactivated = 0 OR c_user.Deactivated IS NULL)
WHERE p.PostType = 'Post'
GROUP BY p.PostID;


SELECT * FROM ViewPosts;