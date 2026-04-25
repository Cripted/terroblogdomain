<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../config/database.php';

$action = $_GET['action'] ?? 'list';

switch ($action) {

    case 'list':
        $r = $conn->query("SELECT id,nombre,slug,descripcion,desarrollador,anio_lanzamiento,imagen_portada,calificacion FROM juegos WHERE activo=1 ORDER BY nombre");
        echo json_encode($r->fetch_all(MYSQLI_ASSOC), JSON_UNESCAPED_UNICODE);
        break;

    case 'estadisticas':
        $stats = [
            'miembros_activos'     => (int)$conn->query("SELECT COUNT(*) FROM usuarios WHERE activo=1")->fetch_row()[0],
            'articulos_publicados' => (int)$conn->query("SELECT COUNT(*) FROM articulos WHERE publicado=1")->fetch_row()[0],
            'discusiones_activas'  => (int)$conn->query("SELECT COUNT(*) FROM discusiones WHERE aprobado=1")->fetch_row()[0],
            'comentarios_totales'  => (int)$conn->query("SELECT COUNT(*) FROM comentarios_discusion WHERE aprobado=1")->fetch_row()[0],
        ];
        echo json_encode($stats, JSON_UNESCAPED_UNICODE);
        break;

    case 'top_contribuidores':
        $r = $conn->query("SELECT autor_nombre AS nombre, COUNT(*) AS total_discusiones, COALESCE(SUM(likes),0) AS total_likes FROM discusiones WHERE aprobado=1 GROUP BY autor_nombre ORDER BY total_discusiones DESC, total_likes DESC LIMIT 5");
        $lista = $r->fetch_all(MYSQLI_ASSOC);
        $ejemplos = [
            ['nombre'=>'HorrorMaster88','total_discusiones'=>45,'total_likes'=>156],
            ['nombre'=>'SilentFan_2026','total_discusiones'=>38,'total_likes'=>132],
            ['nombre'=>'PyramidHead_Forever','total_discusiones'=>29,'total_likes'=>118],
            ['nombre'=>'REVillageExplorer','total_discusiones'=>22,'total_likes'=>95],
            ['nombre'=>'GhostHunter_Pro','total_discusiones'=>19,'total_likes'=>87],
        ];
        $nombres = array_column($lista,'nombre');
        foreach ($ejemplos as $e) if (!in_array($e['nombre'],$nombres)) $lista[]=$e;
        echo json_encode(array_slice($lista,0,5), JSON_UNESCAPED_UNICODE);
        break;

    default:
        echo json_encode(['error'=>'Acción no válida']);
}
$conn->close();