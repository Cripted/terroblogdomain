<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../config/database.php';

$action    = $_GET['action']    ?? 'list';
$id        = isset($_GET['id'])        ? (int)$_GET['id']        : null;
$slug      = isset($_GET['slug'])      ? sanitize($_GET['slug'])  : null;
$categoria = isset($_GET['categoria']) ? sanitize($_GET['categoria']) : null;
$limit     = isset($_GET['limit'])     ? max(1,min(50,(int)$_GET['limit'])) : 10;

switch ($action) {

    case 'get':
        if (!$id && !$slug) { echo json_encode(['error'=>'ID o slug requerido']); exit; }
        $sql = "SELECT a.*, c.nombre AS categoria, c.icono AS categoria_icono,
                       j.nombre AS juego, u.nombre_completo AS autor,
                       GROUP_CONCAT(t.nombre SEPARATOR ',') AS tags
                FROM articulos a
                LEFT JOIN categorias c  ON a.categoria_id = c.id
                LEFT JOIN juegos j      ON a.juego_id     = j.id
                LEFT JOIN usuarios u    ON a.autor_id     = u.id
                LEFT JOIN articulo_tags atags ON a.id     = atags.articulo_id
                LEFT JOIN tags t        ON atags.tag_id   = t.id
                WHERE ".($id ? "a.id=?" : "a.slug=?")." AND a.publicado=1
                GROUP BY a.id";
        $stmt = $conn->prepare($sql);
        $id ? $stmt->bind_param("i",$id) : $stmt->bind_param("s",$slug);
        $stmt->execute();
        $art = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($art) {
            $u=$conn->prepare("UPDATE articulos SET vistas=vistas+1 WHERE id=?");
            $u->bind_param("i",$art['id']); $u->execute(); $u->close();
            $art['tags'] = $art['tags'] ? explode(',', $art['tags']) : [];
            echo json_encode($art, JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['error'=>'Artículo no encontrado']);
        }
        break;

    case 'list':
        $where=[]; $types=''; $params=[];
        $where[] = 'a.publicado=1';
        if ($categoria) { $where[]='c.slug=?'; $types.='s'; $params[]=$categoria; }
        $types.='i'; $params[]=$limit;
        $stmt = $conn->prepare(
            "SELECT a.id,a.titulo,a.slug,a.extracto,a.imagen_destacada,a.vistas,
                    a.calificacion,a.destacado,a.fecha_publicacion,
                    c.nombre AS categoria,c.icono AS categoria_icono,
                    j.nombre AS juego, u.nombre_completo AS autor
             FROM articulos a
             LEFT JOIN categorias c ON a.categoria_id=c.id
             LEFT JOIN juegos j     ON a.juego_id=j.id
             LEFT JOIN usuarios u   ON a.autor_id=u.id
             WHERE ".implode(' AND ',$where)."
             ORDER BY a.fecha_publicacion DESC LIMIT ?"
        );
        $stmt->bind_param($types,...$params);
        $stmt->execute();
        echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC), JSON_UNESCAPED_UNICODE);
        $stmt->close();
        break;

    case 'destacados':
        $stmt = $conn->prepare(
            "SELECT a.id,a.titulo,a.slug,a.extracto,a.imagen_destacada,a.vistas,
                    a.calificacion,a.fecha_publicacion,
                    c.nombre AS categoria,c.icono AS categoria_icono,
                    u.nombre_completo AS autor
             FROM articulos a
             LEFT JOIN categorias c ON a.categoria_id=c.id
             LEFT JOIN usuarios u   ON a.autor_id=u.id
             WHERE a.publicado=1 AND a.destacado=1
             ORDER BY a.fecha_publicacion DESC LIMIT ?"
        );
        $stmt->bind_param("i",$limit); $stmt->execute();
        echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC), JSON_UNESCAPED_UNICODE);
        $stmt->close();
        break;

    case 'relacionados':
        $aid = isset($_GET['articulo_id']) ? (int)$_GET['articulo_id'] : null;
        if (!$aid) { echo json_encode(['error'=>'articulo_id requerido']); exit; }
        $stmt = $conn->prepare(
            "SELECT a2.id,a2.titulo,a2.slug,a2.extracto,a2.imagen_destacada,
                    a2.fecha_publicacion,c.nombre AS categoria,c.icono AS categoria_icono
             FROM articulos a1
             JOIN articulos a2 ON (a1.categoria_id=a2.categoria_id OR a1.juego_id=a2.juego_id)
             LEFT JOIN categorias c ON a2.categoria_id=c.id
             WHERE a1.id=? AND a2.id!=? AND a2.publicado=1
             GROUP BY a2.id ORDER BY a2.fecha_publicacion DESC LIMIT 3"
        );
        $stmt->bind_param("ii",$aid,$aid); $stmt->execute();
        echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC), JSON_UNESCAPED_UNICODE);
        $stmt->close();
        break;

    default:
        echo json_encode(['error'=>'Acción no válida']);
}
$conn->close();