<?php
// api.php — REST API de recetas
// Endpoints:
//   GET    api.php          → lista [{id, name, updated_at}]
//   GET    api.php?id=X     → receta completa con ingredients y steps como arrays
//   POST   api.php          → crear   body: {name, ingredients, steps, comments}
//   PUT    api.php?id=X     → editar  body: {name, ingredients, steps, comments}
//   DELETE api.php?id=X     → eliminar

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection error']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? intval($_GET['id']) : 0;

switch ($method) {

    case 'GET':
        if ($id) {
            $stmt = $pdo->prepare('SELECT * FROM recipes WHERE id = ?');
            $stmt->execute([$id]);
            $r = $stmt->fetch();
            if (!$r) {
                http_response_code(404);
                echo json_encode(['error' => 'Not found']);
                exit;
            }
            $r['ingredients'] = json_decode($r['ingredients'], true) ?: [];
            $r['steps']       = json_decode($r['steps'],       true) ?: [];
            echo json_encode($r);
        } else {
            $rows = $pdo->query('SELECT id, name, updated_at FROM recipes ORDER BY name')->fetchAll();
            echo json_encode($rows);
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || empty(trim($data['name'] ?? ''))) {
            http_response_code(400);
            echo json_encode(['error' => 'Name required']);
            exit;
        }
        $stmt = $pdo->prepare(
            'INSERT INTO recipes (name, ingredients, steps, comments) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            trim($data['name']),
            json_encode($data['ingredients'] ?? []),
            json_encode($data['steps']       ?? []),
            $data['comments'] ?? ''
        ]);
        echo json_encode(['id' => (int)$pdo->lastInsertId()]);
        break;

    case 'PUT':
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID required']);
            exit;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare(
            'UPDATE recipes SET name=?, ingredients=?, steps=?, comments=? WHERE id=?'
        );
        $stmt->execute([
            trim($data['name']    ?? ''),
            json_encode($data['ingredients'] ?? []),
            json_encode($data['steps']       ?? []),
            $data['comments'] ?? '',
            $id
        ]);
        echo json_encode(['ok' => true]);
        break;

    case 'DELETE':
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID required']);
            exit;
        }
        $stmt = $pdo->prepare('DELETE FROM recipes WHERE id = ?');
        $stmt->execute([$id]);
        echo json_encode(['ok' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}
