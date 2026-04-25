<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../config/database.php';

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

switch ($action) {

    case 'list':
        $slug = isset($_GET['juego']) ? sanitize($_GET['juego']) : null;
        if (!$slug) { echo json_encode(['error'=>'Juego requerido']); exit; }
        $stmt = $conn->prepare(
            "SELECT d.id,d.titulo,d.contenido,d.autor_nombre,d.vistas,d.likes,d.fecha_creacion,
                    (SELECT COUNT(*) FROM comentarios_discusion cd WHERE cd.discusion_id=d.id AND cd.aprobado=1) AS total_comentarios
             FROM discusiones d JOIN juegos j ON d.juego_id=j.id
             WHERE j.slug=? AND d.aprobado=1 ORDER BY d.fecha_creacion DESC"
        );
        $stmt->bind_param("s",$slug); $stmt->execute();
        echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC), JSON_UNESCAPED_UNICODE);
        $stmt->close();
        break;

    case 'get':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        if (!$id) { echo json_encode(['error'=>'ID requerido']); exit; }
        $stmt = $conn->prepare("SELECT d.*,j.nombre AS juego FROM discusiones d JOIN juegos j ON d.juego_id=j.id WHERE d.id=? AND d.aprobado=1");
        $stmt->bind_param("i",$id); $stmt->execute();
        $d = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if ($d) {
            $u=$conn->prepare("UPDATE discusiones SET vistas=vistas+1 WHERE id=?");
            $u->bind_param("i",$id); $u->execute(); $u->close();
            echo json_encode($d, JSON_UNESCAPED_UNICODE);
        } else echo json_encode(['error'=>'No encontrada']);
        break;

    case 'comentarios':
        $did = isset($_GET['discusion_id']) ? (int)$_GET['discusion_id'] : null;
        if (!$did) { echo json_encode(['error'=>'discusion_id requerido']); exit; }
        $stmt = $conn->prepare("SELECT id,autor_nombre,contenido,fecha_creacion FROM comentarios_discusion WHERE discusion_id=? AND aprobado=1 ORDER BY fecha_creacion ASC");
        $stmt->bind_param("i",$did); $stmt->execute();
        echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC), JSON_UNESCAPED_UNICODE);
        $stmt->close();
        break;

    case 'nueva':
        if ($_SERVER['REQUEST_METHOD']!=='POST') { echo json_encode(['error'=>'POST requerido']); exit; }
        $titulo  = sanitize($_POST['titulo']       ?? '');
        $cont    = sanitize($_POST['contenido']    ?? '');
        $autor   = sanitize($_POST['autor_nombre'] ?? '');
        $jslug   = sanitize($_POST['juego_slug']   ?? '');
        if (!$titulo||!$cont||!$autor||!$jslug) { echo json_encode(['success'=>false,'message'=>'Todos los campos son requeridos']); exit; }
        $js=$conn->prepare("SELECT id FROM juegos WHERE slug=? AND activo=1");
        $js->bind_param("s",$jslug); $js->execute();
        $j=$js->get_result()->fetch_assoc(); $js->close();
        if (!$j) { echo json_encode(['success'=>false,'message'=>'Juego no encontrado']); exit; }
        $s=$conn->prepare("INSERT INTO discusiones (titulo,contenido,autor_nombre,juego_id,aprobado) VALUES (?,?,?,?,1)");
        $s->bind_param("sssi",$titulo,$cont,$autor,$j['id']);
        $ok=$s->execute(); $nid=$conn->insert_id; $s->close();
        echo json_encode($ok ? ['success'=>true,'id'=>$nid] : ['success'=>false,'message'=>'Error al crear']);
        break;

    case 'comentar':
        if ($_SERVER['REQUEST_METHOD']!=='POST') { echo json_encode(['error'=>'POST requerido']); exit; }
        $did  = isset($_POST['discusion_id']) ? (int)$_POST['discusion_id'] : null;
        $autor= sanitize($_POST['autor_nombre'] ?? '');
        $cont = sanitize($_POST['contenido']    ?? '');
        if (!$did||!$autor||!$cont) { echo json_encode(['success'=>false,'message'=>'Campos requeridos']); exit; }
        $s=$conn->prepare("INSERT INTO comentarios_discusion (discusion_id,autor_nombre,contenido,aprobado) VALUES (?,?,?,1)");
        $s->bind_param("iss",$did,$autor,$cont);
        $ok=$s->execute(); $nid=$conn->insert_id; $s->close();
        echo json_encode($ok ? ['success'=>true,'id'=>$nid] : ['success'=>false,'message'=>'Error']);
        break;

    case 'like':
        if ($_SERVER['REQUEST_METHOD']!=='POST') { echo json_encode(['error'=>'POST requerido']); exit; }
        $did = isset($_POST['discusion_id']) ? (int)$_POST['discusion_id'] : null;
        if (!$did) { echo json_encode(['success'=>false,'message'=>'ID requerido']); exit; }
        $u=$conn->prepare("UPDATE discusiones SET likes=likes+1 WHERE id=?");
        $u->bind_param("i",$did); $u->execute(); $u->close();
        $s=$conn->prepare("SELECT likes FROM discusiones WHERE id=?");
        $s->bind_param("i",$did); $s->execute();
        $row=$s->get_result()->fetch_assoc(); $s->close();
        echo json_encode(['success'=>true,'likes'=>$row['likes']]);
        break;

    default:
        echo json_encode(['error'=>'Acción no válida']);
}
$conn->close();