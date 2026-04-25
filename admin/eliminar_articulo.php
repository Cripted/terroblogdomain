<?php
/**
 * ajax/eliminar_articulo.php
 * Elimina un artículo via AJAX (POST).
 * Requiere sesión activa con rol editor o superior.
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../../config/auth.php';

if (!$auth->isLoggedIn() || !$auth->hasRole('editor')) {
    echo json_encode(['success' => false, 'message' => 'Sin permisos']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'Solicitud inválida']);
    exit;
}

$id = intval($_POST['id']);

try {
    $db = getDB();

    // Obtener imagen para borrarla del disco
    $stmt = $db->prepare("SELECT imagen_destacada FROM articulos WHERE id = ?");
    $stmt->execute([$id]);
    $art = $stmt->fetch();

    if (!$art) {
        echo json_encode(['success' => false, 'message' => 'Artículo no encontrado']);
        exit;
    }

    // Borrar relaciones de tags
    $db->prepare("DELETE FROM articulo_tags WHERE articulo_id = ?")->execute([$id]);

    // Borrar artículo
    $db->prepare("DELETE FROM articulos WHERE id = ?")->execute([$id]);

    // Borrar imagen del servidor si existe
    if ($art['imagen_destacada']) {
        $path = UPLOAD_DIR . $art['imagen_destacada'];
        if (file_exists($path)) @unlink($path);
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}