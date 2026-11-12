<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

include("conexion.php");
function contieneEspeciales($txt){ return preg_match('/[?¿¡!@#$%^&*()+=\[\]{};:"\\|<>\/]/u',$txt); }

$mensaje     = "";
$error       = "";
$modo_editar = false;
$id_editar   = 0;
$d           = ['usuario'=>'','correo'=>'','rol'=>'tecnico'];

/* ── GUARDAR ── */
if (isset($_POST['guardar'])) {
    $usuario  = trim($_POST['usuario']);
    $password = $_POST['password'];
    $correo   = trim($_POST['correo']);
    $rol      = in_array($_POST['rol'], ['admin','tecnico']) ? $_POST['rol'] : 'tecnico';

    if (empty($usuario) || empty($password) || empty($correo)) {
        $error = "Todos los campos son obligatorios.";
    } elseif (strlen($usuario) < 3) {
        $error = "El usuario debe tener al menos 3 caracteres.";
    } elseif (strlen($password) < 4) {
        $error = "La contraseña debe tener al menos 4 caracteres.";
    } elseif (contieneEspeciales($usuario) || contieneEspeciales($correo)) {
        $error = "No se permiten caracteres especiales en este campo.";
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = "El correo no es válido.";
    } else {
        $chk = $conexion->prepare("SELECT id FROM usuarios WHERE usuario = ?");
        $chk->bind_param("s", $usuario);
        $chk->execute();
        $chk->store_result();

        if ($chk->num_rows > 0) {
            $error = "Ese nombre de usuario ya existe.";
        } else {
            $stmt = $conexion->prepare(
                "INSERT INTO usuarios (usuario, contraseña, correo, rol) VALUES (?,?,?,?)"
            );
            $stmt->bind_param("ssss", $usuario, $password, $correo, $rol);
            $stmt->execute();
            $stmt->close();
            $mensaje = "Usuario creado correctamente.";
        }
        $chk->close();
    }
}

/* ── CARGAR PARA EDITAR ── */
if (isset($_GET['editar'])) {
    $id_editar   = (int) $_GET['editar'];
    $modo_editar = true;

    $stmt = $conexion->prepare("SELECT id, usuario, correo, rol FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $id_editar);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($fila = $res->fetch_assoc()) {
        $d = $fila;
    }
    $stmt->close();
}

/* ── ACTUALIZAR ── */
if (isset($_POST['actualizar'])) {
    $id      = (int) $_POST['id'];
    $usuario = trim($_POST['usuario']);
    $correo  = trim($_POST['correo']);
    $pass    = $_POST['password'];
    $rol     = in_array($_POST['rol'], ['admin','tecnico']) ? $_POST['rol'] : 'tecnico';

    if (empty($usuario) || empty($correo)) {
        $error = "Usuario y correo son obligatorios.";
        $modo_editar = true; $id_editar = $id; $d = $_POST;
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = "El correo no es válido.";
        $modo_editar = true; $id_editar = $id; $d = $_POST;
    } else {
        if (!empty($pass)) {
            $stmt = $conexion->prepare(
                "UPDATE usuarios SET usuario=?, correo=?, contraseña=?, rol=? WHERE id=?"
            );
            $stmt->bind_param("ssssi", $usuario, $correo, $pass, $rol, $id);
        } else {
            $stmt = $conexion->prepare(
                "UPDATE usuarios SET usuario=?, correo=?, rol=? WHERE id=?"
            );
            $stmt->bind_param("sssi", $usuario, $correo, $rol, $id);
        }
        $stmt->execute();
        $stmt->close();
        header("Location: usuarios.php?ok=1");
        exit();
    }
}

/* ── ELIMINAR ── */
if (isset($_GET['eliminar'])) {
    $id = (int) $_GET['eliminar'];

    $stmt = $conexion->prepare("SELECT usuario FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($res && $res['usuario'] === $_SESSION['usuario']) {
        $error = "No puedes eliminar tu propio usuario mientras estás activo.";
    } else {
        $stmt = $conexion->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        header("Location: usuarios.php?ok=2");
        exit();
    }
}

if (isset($_GET['ok'])) {
    $msgs = ['1'=>'Usuario actualizado.','2'=>'Usuario eliminado.'];
    $mensaje = $msgs[$_GET['ok']] ?? '';
}

/* ── LISTAR ── */
$resultado = $conexion->query("SELECT id, usuario, correo, rol FROM usuarios ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Usuarios — Soporte Digital VER</title>
<link rel="stylesheet" href="css/estilos.css">
</head>
<body>

<header>
  <a href="dashboard.php" class="brand">🖥️ Soporte<span>Digital</span>&nbsp;VER</a>
  <nav class="nav-links">
    <a href="clientes.php">👥 Clientes</a>
    <a href="tecnicos.php">🛠️ Técnicos</a>
    <a href="tickets.php">🎫 Tickets</a>
    <a href="agenda.php">📅 Agenda</a>
    <a href="usuarios.php" class="active">👤 Usuarios</a>
  </nav>
  <div class="user-info">
    👤 <strong><?= htmlspecialchars($_SESSION['usuario']) ?></strong>
    <a href="logout.php" class="btn-logout">🚪 Salir</a>
  </div>
</header>

<div class="container">

  <div class="page-title"><span>👤</span> Usuarios del Sistema</div>

  <div class="card">
    <h2><?= $modo_editar ? '✏️ Editar Usuario' : '➕ Crear Usuario' ?></h2>

    <?php if ($error): ?>
      <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($mensaje): ?>
      <div class="alert alert-success">✅ <?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <form method="POST">
      <?php if ($modo_editar): ?>
        <input type="hidden" name="id" value="<?= (int)$id_editar ?>">
      <?php endif; ?>

      <div class="form-grid">
        <div class="form-group">
          <label>Nombre de usuario</label>
          <input type="text" name="usuario" placeholder="usuario123"
            value="<?= htmlspecialchars($d['usuario']) ?>" required maxlength="60" minlength="3">
        </div>
        <div class="form-group">
          <label>Correo electrónico</label>
          <input type="email" name="correo" placeholder="correo@ejemplo.com"
            value="<?= htmlspecialchars($d['correo'] ?? '') ?>" required maxlength="150">
        </div>
        <div class="form-group">
          <label>Contraseña <?= $modo_editar ? '(dejar vacío para no cambiar)' : '' ?></label>
          <input type="password" name="password" placeholder="••••••••"
            <?= $modo_editar ? '' : 'required' ?> maxlength="100" minlength="4">
        </div>
        <div class="form-group">
          <label>Rol</label>
          <select name="rol">
            <option value="admin"   <?= ($d['rol'] ?? '') === 'admin'   ? 'selected' : '' ?>>Administrador</option>
            <option value="tecnico" <?= ($d['rol'] ?? 'tecnico') === 'tecnico' ? 'selected' : '' ?>>Técnico</option>
          </select>
        </div>
      </div>

      <div class="form-actions">
        <?php if ($modo_editar): ?>
          <button type="submit" name="actualizar" class="btn btn-primary">💾 Actualizar</button>
          <a href="usuarios.php" class="btn btn-secondary">✖ Cancelar</a>
        <?php else: ?>
          <button type="submit" name="guardar" class="btn btn-primary">➕ Crear Usuario</button>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Usuario</th>
          <th>Correo</th>
          <th>Rol</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($fila = $resultado->fetch_assoc()): ?>
        <tr>
          <td><?= (int)$fila['id'] ?></td>
          <td>
            <?= htmlspecialchars($fila['usuario']) ?>
            <?php if ($fila['usuario'] === $_SESSION['usuario']): ?>
              <span class="badge badge-open" style="margin-left:6px;font-size:11px;">Tú</span>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($fila['correo'] ?? '—') ?></td>
          <td>
            <span class="badge <?= ($fila['rol'] ?? '') === 'admin' ? 'badge-process' : 'badge-closed' ?>">
              <?= ($fila['rol'] ?? 'tecnico') === 'admin' ? '⚙️ Admin' : '🛠️ Técnico' ?>
            </span>
          </td>
          <td style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="usuarios.php?editar=<?= (int)$fila['id'] ?>" class="btn btn-warning btn-sm">✏️ Editar</a>
            <?php if ($fila['usuario'] !== $_SESSION['usuario']): ?>
            <a href="usuarios.php?eliminar=<?= (int)$fila['id'] ?>"
               class="btn btn-danger btn-sm"
               onclick="return confirm('¿Eliminar usuario <?= htmlspecialchars(addslashes($fila['usuario'])) ?>?')">
               🗑️ Eliminar
            </a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <div style="margin-top:20px;">
    <a href="dashboard.php" class="btn btn-secondary">← Regresar al Dashboard</a>
  </div>

</div>
</body>
</html>
