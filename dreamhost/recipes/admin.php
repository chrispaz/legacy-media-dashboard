<?php
require_once __DIR__ . '/config.php';

session_name('lmd_admin');
session_start();

$error = '';
$msg   = '';

// ── Logout ──────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// ── Login ────────────────────────────────────────────────────────────────────
if (!isset($_SESSION['auth'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pass'])) {
        if (trim($_POST['user'] ?? '') === ADMIN_USER && ($_POST['pass'] ?? '') === ADMIN_PASS) {
            $_SESSION['auth'] = true;
            header('Location: admin.php');
            exit;
        }
        $error = 'Usuario o contraseña incorrectos.';
    }
    showLogin($error);
    exit;
}

// ── DB ───────────────────────────────────────────────────────────────────────
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    die('Error de conexión: ' . htmlspecialchars($e->getMessage()));
}

$action = $_GET['action'] ?? 'list';
$id     = isset($_GET['id']) ? intval($_GET['id']) : 0;

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'delete') {
        $delId = intval($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM recipes WHERE id = ?')->execute([$delId]);
        header('Location: admin.php?msg=deleted');
        exit;
    }

    if ($postAction === 'save') {
        $name     = trim($_POST['name']     ?? '');
        $comments = trim($_POST['comments'] ?? '');
        $saveId   = intval($_POST['id']     ?? 0);

        $ingredients = [];
        $iqtys  = $_POST['iqty']  ?? [];
        $inames = $_POST['iname'] ?? [];
        for ($i = 0; $i < count($inames); $i++) {
            $n = trim($inames[$i] ?? '');
            if ($n !== '') {
                $ingredients[] = ['qty' => trim($iqtys[$i] ?? ''), 'name' => $n, 'checked' => false];
            }
        }

        $steps = [];
        foreach ($_POST['step'] ?? [] as $st) {
            $st = trim($st);
            if ($st !== '') { $steps[] = ['text' => $st, 'checked' => false]; }
        }

        if ($saveId) {
            $pdo->prepare('UPDATE recipes SET name=?, ingredients=?, steps=?, comments=? WHERE id=?')
                ->execute([$name, json_encode($ingredients), json_encode($steps), $comments, $saveId]);
        } else {
            $pdo->prepare('INSERT INTO recipes (name, ingredients, steps, comments) VALUES (?, ?, ?, ?)')
                ->execute([$name, json_encode($ingredients), json_encode($steps), $comments]);
        }
        header('Location: admin.php?msg=saved');
        exit;
    }
}

if (isset($_GET['msg'])) {
    $msg = ($_GET['msg'] === 'saved') ? 'Receta guardada correctamente.' : 'Receta eliminada.';
}

// ── GET handlers ──────────────────────────────────────────────────────────────
if ($action === 'list') {
    $recipes = $pdo->query('SELECT id, name, updated_at FROM recipes ORDER BY name')->fetchAll();
    showList($recipes, $msg);

} elseif ($action === 'new') {
    showForm(null);

} elseif ($action === 'edit' && $id) {
    $stmt = $pdo->prepare('SELECT * FROM recipes WHERE id = ?');
    $stmt->execute([$id]);
    $recipe = $stmt->fetch();
    if (!$recipe) { header('Location: admin.php'); exit; }
    $recipe['ingredients'] = json_decode($recipe['ingredients'], true) ?: [];
    $recipe['steps']       = json_decode($recipe['steps'],       true) ?: [];
    showForm($recipe);
}

// =============================================================================
// TEMPLATE FUNCTIONS
// =============================================================================

function pageHeader($title) { ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($title) ?> — LMD Recetas</title>
<style>
/* ── Reset ───────────────────────────────────────────── */
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  background: #f4f4f5;
  color: #111827;
  font-size: 15px;
  line-height: 1.6;
}
a { color: #1d4ed8; text-decoration: none; }
a:hover { text-decoration: underline; }

/* ── Topbar ──────────────────────────────────────────── */
.topbar {
  background: #111827;
  color: #fff;
  padding: 0 28px;
  height: 54px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 3px solid #2563eb;
}
.topbar h1 { font-size: 0.95em; font-weight: 700; letter-spacing: .5px; }
.topbar-right { display: flex; align-items: center; gap: 20px; }
.topbar-user { font-size: 0.82em; color: #9ca3af; }
.btn-logout {
  background: transparent; color: #d1d5db;
  border: 1px solid #374151;
  padding: 5px 14px; border-radius: 5px;
  cursor: pointer; font-size: 0.82em; font-family: inherit;
}
.btn-logout:hover { background: #1f2937; color: #fff; border-color: #6b7280; }

/* ── Layout ──────────────────────────────────────────── */
.container { max-width: 920px; margin: 28px auto; padding: 0 20px 60px; }

/* ── Card ────────────────────────────────────────────── */
.card {
  background: #fff;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  box-shadow: 0 1px 3px rgba(0,0,0,.06);
  overflow: hidden;
}
.card-header {
  padding: 16px 24px;
  border-bottom: 1px solid #e5e7eb;
  display: flex; align-items: center; justify-content: space-between;
  background: #fff;
}
.card-header h2 { font-size: 1em; font-weight: 700; color: #111827; }
.card-body { padding: 28px; }

/* ── Table ───────────────────────────────────────────── */
table { width: 100%; border-collapse: collapse; }
th {
  text-align: left;
  font-size: 0.72em; font-weight: 700;
  text-transform: uppercase; letter-spacing: .8px;
  color: #374151;
  padding: 11px 20px;
  border-bottom: 2px solid #111827;
  background: #f9fafb;
}
td {
  padding: 14px 20px;
  border-bottom: 1px solid #e5e7eb;
  vertical-align: middle;
  color: #111827;
}
tr:last-child td { border-bottom: none; }
tr:hover td { background: #f9fafb; }
.col-name { font-weight: 600; font-size: 0.97em; }
.col-date { font-size: 0.83em; color: #6b7280; white-space: nowrap; }
.col-acts { text-align: right; white-space: nowrap; }

/* ── Buttons ─────────────────────────────────────────── */
.btn {
  display: inline-flex; align-items: center;
  padding: 8px 18px; border-radius: 6px;
  font-size: 0.88em; font-weight: 600;
  cursor: pointer; border: none;
  font-family: inherit; text-decoration: none; line-height: 1;
}
.btn:hover { text-decoration: none; }
.btn-primary { background: #2563eb; color: #fff; }
.btn-primary:hover { background: #1d4ed8; color: #fff; }
.btn-outline {
  background: #fff; border: 1px solid #d1d5db;
  color: #374151; font-weight: 500;
}
.btn-outline:hover { background: #f9fafb; border-color: #6b7280; color: #111827; }
.btn-danger { background: #dc2626; color: #fff; }
.btn-danger:hover { background: #b91c1c; color: #fff; }
.btn-sm { padding: 5px 12px; font-size: 0.8em; }

/* ── Forms ───────────────────────────────────────────── */
.form-group { margin-bottom: 22px; }
label {
  display: block; font-size: 0.88em; font-weight: 700;
  color: #111827; margin-bottom: 7px;
}
.sublabel {
  font-size: 0.8em; color: #6b7280;
  font-weight: 400; margin-left: 6px;
}
input[type=text], input[type=password], textarea {
  width: 100%;
  border: 1.5px solid #d1d5db; border-radius: 6px;
  padding: 10px 13px; font-size: 0.95em;
  font-family: inherit; color: #111827;
  background: #fff;
}
input[type=text]:focus, input[type=password]:focus, textarea:focus {
  outline: none;
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37,99,235,.15);
}
textarea { resize: vertical; min-height: 110px; line-height: 1.7; }

.section-head {
  font-size: 0.75em; font-weight: 800;
  text-transform: uppercase; letter-spacing: 1px;
  color: #374151; margin-bottom: 10px;
}

/* ── Ingredient / Step rows ──────────────────────────── */
.rows-wrap {
  border: 1.5px solid #d1d5db;
  border-radius: 7px; overflow: hidden; margin-bottom: 10px;
}
.row-item {
  display: flex; align-items: center; gap: 8px;
  padding: 8px 12px; border-bottom: 1px solid #e5e7eb;
  background: #fff;
}
.row-item:last-child { border-bottom: none; }
.row-item input[type=text] { margin: 0; border: 1.5px solid #e5e7eb; }
.row-item input[type=text]:focus { border-color: #2563eb; }
.input-qty  { width: 115px; flex-shrink: 0; }
.input-item { flex: 1; }
.row-num {
  color: #374151; font-size: 0.82em; font-weight: 700;
  min-width: 22px; text-align: right; flex-shrink: 0;
}
.btn-del {
  background: none; border: none; color: #dc2626;
  cursor: pointer; font-size: 1.2em; padding: 3px 5px; line-height: 1;
}
.btn-del:hover { color: #991b1b; }
.btn-add-row {
  background: #f9fafb; border: 1.5px dashed #d1d5db;
  border-radius: 6px; padding: 9px 14px;
  font-size: 0.87em; font-weight: 500; color: #374151;
  cursor: pointer; font-family: inherit; width: 100%; text-align: left;
}
.btn-add-row:hover { background: #f4f4f5; border-color: #6b7280; color: #111827; }

/* ── Alerts ──────────────────────────────────────────── */
.alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 0.9em; font-weight: 500; }
.alert-ok  { background: #f0fdf4; color: #15803d; border: 1.5px solid #86efac; }
.alert-err { background: #fef2f2; color: #b91c1c; border: 1.5px solid #fca5a5; }

/* ── Misc ────────────────────────────────────────────── */
.form-actions {
  display: flex; gap: 10px;
  padding-top: 20px; border-top: 1px solid #e5e7eb; margin-top: 20px;
}
.breadcrumb { font-size: 0.87em; color: #374151; margin-bottom: 20px; font-weight: 500; }
.breadcrumb a { color: #374151; }
.breadcrumb a:hover { color: #1d4ed8; }

.empty-state { padding: 60px 24px; text-align: center; color: #6b7280; }
.empty-state p { margin-bottom: 16px; font-size: 1.05em; }

/* ── Login ───────────────────────────────────────────── */
.login-wrap {
  min-height: 100vh; display: flex;
  align-items: center; justify-content: center;
  background: #f4f4f5;
}
.login-card {
  background: #fff; border-radius: 10px;
  padding: 44px; width: 380px;
  border: 1px solid #e5e7eb;
  box-shadow: 0 4px 20px rgba(0,0,0,.08);
}
.login-logo {
  font-size: 1.5em; font-weight: 800;
  color: #111827; margin-bottom: 4px;
  letter-spacing: -0.5px;
}
.login-sub { color: #6b7280; font-size: 0.9em; margin-bottom: 28px; }
</style>
</head>
<body>
<?php }

// ── Login page ────────────────────────────────────────────────────────────────
function showLogin($error) {
    pageHeader('Acceso');
    ?>
<div class="login-wrap">
  <div class="login-card">
    <div class="login-logo">LMD</div>
    <div class="login-sub">Administrador de recetas</div>
    <?php if ($error): ?>
    <div class="alert alert-err"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST">
      <div class="form-group">
        <label>Usuario</label>
        <input type="text" name="user" autocomplete="username" required autofocus>
      </div>
      <div class="form-group" style="margin-bottom:24px">
        <label>Contraseña</label>
        <input type="password" name="pass" autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:10px">
        Entrar
      </button>
    </form>
  </div>
</div>
</body></html>
<?php }

// ── Recipe list ───────────────────────────────────────────────────────────────
function showList($recipes, $msg) {
    pageHeader('Recetas');
    $count = count($recipes);
    ?>
<div class="topbar">
  <h1>LMD — Administrador de Recetas</h1>
  <div class="topbar-right">
    <span class="topbar-user">admin</span>
    <form method="POST" style="margin:0">
      <button type="submit" name="logout" class="btn-logout">Cerrar sesión</button>
    </form>
  </div>
</div>
<div class="container">
  <?php if ($msg): ?>
  <div class="alert alert-ok"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>
  <div class="card">
    <div class="card-header">
      <h2><?= $count ?> receta<?= $count !== 1 ? 's' : '' ?></h2>
      <a href="?action=new" class="btn btn-primary btn-sm">+ Nueva receta</a>
    </div>
    <?php if (!$recipes): ?>
    <div class="empty-state">
      <p>Aún no hay recetas guardadas.</p>
      <a href="?action=new" class="btn btn-primary">Crear la primera</a>
    </div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Nombre</th>
          <th>Última modificación</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recipes as $r): ?>
        <tr>
          <td class="col-name"><?= htmlspecialchars($r['name']) ?></td>
          <td class="col-date"><?= date('d/m/Y  H:i', strtotime($r['updated_at'])) ?></td>
          <td class="col-acts">
            <a href="?action=edit&id=<?= $r['id'] ?>" class="btn btn-outline btn-sm">Editar</a>
            &nbsp;
            <form method="POST" style="display:inline"
                  onsubmit="return confirm('¿Eliminar «<?= htmlspecialchars(addslashes($r['name'])) ?>»? Esta acción no se puede deshacer.')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id"     value="<?= $r['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>
</body></html>
<?php }

// ── Recipe form (create / edit) ───────────────────────────────────────────────
function showForm($recipe) {
    $isNew = !$recipe;
    $title = $isNew ? 'Nueva receta' : 'Editar receta';
    pageHeader($title);
    $ings    = $isNew ? [] : ($recipe['ingredients'] ?? []);
    $steps   = $isNew ? [] : ($recipe['steps']       ?? []);
    $comments = $isNew ? '' : ($recipe['comments'] ?? '');
    ?>
<div class="topbar">
  <h1>LMD — Administrador de Recetas</h1>
  <div class="topbar-right">
    <span class="topbar-user">admin</span>
    <form method="POST" style="margin:0">
      <button type="submit" name="logout" class="btn-logout">Cerrar sesión</button>
    </form>
  </div>
</div>
<div class="container">
  <div class="breadcrumb">
    <a href="admin.php">← Volver a la lista</a>
  </div>
  <div class="card">
    <div class="card-header">
      <h2><?= htmlspecialchars($title) ?><?php if (!$isNew): ?>: <em><?= htmlspecialchars($recipe['name']) ?></em><?php endif; ?></h2>
    </div>
    <div class="card-body">
      <form method="POST" id="recipe-form">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id"     value="<?= $isNew ? '' : $recipe['id'] ?>">

        <!-- Nombre -->
        <div class="form-group">
          <label>Nombre de la receta</label>
          <input type="text" name="name" required placeholder="Ej: Paella Valenciana"
                 value="<?= htmlspecialchars($isNew ? '' : $recipe['name']) ?>">
        </div>

        <!-- Ingredientes -->
        <div class="form-group">
          <div class="section-head">Ingredientes</div>
          <div id="ing-rows" class="rows-wrap">
            <?php foreach ($ings as $ing): ?>
            <div class="row-item">
              <input type="text" class="input-qty"  name="iqty[]"
                     placeholder="Cantidad" value="<?= htmlspecialchars($ing['qty'] ?? '') ?>">
              <input type="text" class="input-item" name="iname[]"
                     placeholder="Ingrediente" value="<?= htmlspecialchars($ing['name'] ?? '') ?>">
              <button type="button" class="btn-del" onclick="delRow(this)" title="Eliminar">×</button>
            </div>
            <?php endforeach; ?>
          </div>
          <button type="button" class="btn-add-row" onclick="addIng()">+ Agregar ingrediente</button>
        </div>

        <!-- Pasos -->
        <div class="form-group">
          <div class="section-head">Pasos <span class="sublabel">en orden de preparación</span></div>
          <div id="step-rows" class="rows-wrap">
            <?php foreach ($steps as $i => $step): ?>
            <div class="row-item">
              <span class="row-num"><?= $i + 1 ?>.</span>
              <input type="text" class="input-item" name="step[]"
                     placeholder="Descripción del paso" value="<?= htmlspecialchars($step['text'] ?? '') ?>">
              <button type="button" class="btn-del" onclick="delRow(this); renumber()" title="Eliminar">×</button>
            </div>
            <?php endforeach; ?>
          </div>
          <button type="button" class="btn-add-row" onclick="addStep()">+ Agregar paso</button>
        </div>

        <!-- Notas -->
        <div class="form-group">
          <label>Notas y comentarios <span class="sublabel">sustituciones, variaciones, tips</span></label>
          <textarea name="comments" placeholder="Ej: Es mejor utilizar aceite de oliva en lugar de canola. El arroz debe quedar completamente seco."><?= htmlspecialchars($comments) ?></textarea>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary">Guardar receta</button>
          <a href="admin.php" class="btn btn-outline">Cancelar</a>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function delRow(btn) {
    btn.closest('.row-item').remove();
}
function addIng() {
    var wrap = document.getElementById('ing-rows');
    var div  = document.createElement('div');
    div.className = 'row-item';
    div.innerHTML =
        '<input type="text" class="input-qty"  name="iqty[]"  placeholder="Cantidad">' +
        '<input type="text" class="input-item" name="iname[]" placeholder="Ingrediente">' +
        '<button type="button" class="btn-del" onclick="delRow(this)" title="Eliminar">\u00d7</button>';
    wrap.appendChild(div);
    div.querySelector('input').focus();
}
function addStep() {
    var wrap = document.getElementById('step-rows');
    var num  = wrap.querySelectorAll('.row-item').length + 1;
    var div  = document.createElement('div');
    div.className = 'row-item';
    div.innerHTML =
        '<span class="row-num">' + num + '.</span>' +
        '<input type="text" class="input-item" name="step[]" placeholder="Descripci\u00f3n del paso">' +
        '<button type="button" class="btn-del" onclick="delRow(this); renumber()" title="Eliminar">\u00d7</button>';
    wrap.appendChild(div);
    div.querySelector('input').focus();
}
function renumber() {
    var rows = document.querySelectorAll('#step-rows .row-item');
    for (var i = 0; i < rows.length; i++) {
        var num = rows[i].querySelector('.row-num');
        if (num) num.textContent = (i + 1) + '.';
    }
}
</script>
</body></html>
<?php }
