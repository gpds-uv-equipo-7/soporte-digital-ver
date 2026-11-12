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
$d           = ['nombre'=>'','telefono'=>'','especialidad'=>'','correo'=>''];

/* ── GUARDAR ── */
if (isset($_POST['guardar'])) {

    $nombre       = trim($_POST['nombre']);
    $telefono     = trim($_POST['telefono']);
    $especialidad = trim($_POST['especialidad']);
    $correo       = trim($_POST['correo']);

    if (empty($nombre) || empty($telefono) || empty($especialidad) || empty($correo)) {
        $error = "Todos los campos son obligatorios.";
    } elseif (!preg_match('/^[a-záéíóúüñA-ZÁÉÍÓÚÜÑ\s]+$/u', $nombre)) {
        $error = "No se permiten caracteres especiales en este campo.";
    } elseif (strlen($nombre) < 2) {
        $error = "El nombre debe tener al menos 2 caracteres.";
    } elseif (!preg_match('/^[0-9]{10}$/', $telefono)) {
        $error = "El teléfono debe tener exactamente 10 dígitos numéricos.";
    } elseif (contieneEspeciales($especialidad) || contieneEspeciales($correo)) {
        $error = "No se permiten caracteres especiales en este campo.";
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = "El correo electrónico no es válido.";
    } else {
        $chk = $conexion->prepare("SELECT id FROM tecnicos WHERE correo = ?");
        $chk->bind_param("s", $correo);
        $chk->execute();
        $chk->store_result();

        if ($chk->num_rows > 0) {
            $error = "Ese correo ya está registrado.";
        } else {
            $stmt = $conexion->prepare(
                "INSERT INTO tecnicos (nombre, telefono, especialidad, correo) VALUES (?,?,?,?)"
            );
            $stmt->bind_param("ssss", $nombre, $telefono, $especialidad, $correo);
            $stmt->execute();
            $stmt->close();
            $mensaje = "Técnico registrado correctamente.";
        }
        $chk->close();
    }
}

/* ── CARGAR PARA EDITAR ── */
if (isset($_GET['editar'])) {
    $id_editar   = (int) $_GET['editar'];
    $modo_editar = true;

    $stmt = $conexion->prepare("SELECT * FROM tecnicos WHERE id = ?");
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
    $id           = (int) $_POST['id'];
    $nombre       = trim($_POST['nombre']);
    $telefono     = trim($_POST['telefono']);
    $especialidad = trim($_POST['especialidad']);
    $correo       = trim($_POST['correo']);

    if (empty($nombre) || empty($telefono) || empty($especialidad) || empty($correo)) {
        $error = "Todos los campos son obligatorios.";
        $modo_editar = true; $id_editar = $id; $d = $_POST;
    } elseif (!preg_match('/^[a-záéíóúüñA-ZÁÉÍÓÚÜÑ\s]+$/u', $nombre)) {
        $error = "No se permiten caracteres especiales en este campo.";
        $modo_editar = true; $id_editar = $id; $d = $_POST;
    } elseif (strlen($nombre) < 2) {
        $error = "El nombre debe tener al menos 2 caracteres.";
        $modo_editar = true; $id_editar = $id; $d = $_POST;
    } elseif (!preg_match('/^[0-9]{10}$/', $telefono)) {
        $error = "El teléfono debe tener exactamente 10 dígitos.";
        $modo_editar = true; $id_editar = $id; $d = $_POST;
    } elseif (contieneEspeciales($especialidad) || contieneEspeciales($correo)) {
        $error = "No se permiten caracteres especiales en este campo.";
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = "El correo no es válido.";
        $modo_editar = true; $id_editar = $id; $d = $_POST;
    } else {
        // Verificar correo duplicado en otro técnico
        $chk = $conexion->prepare("SELECT id FROM tecnicos WHERE correo = ? AND id != ?");
        $chk->bind_param("si", $correo, $id);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) {
            $error = "Ese correo ya está registrado en otro técnico.";
            $modo_editar = true; $id_editar = $id; $d = $_POST;
        } else {
            $stmt = $conexion->prepare(
                "UPDATE tecnicos SET nombre=?, telefono=?, especialidad=?, correo=? WHERE id=?"
            );
            $stmt->bind_param("ssssi", $nombre, $telefono, $especialidad, $correo, $id);
            $stmt->execute();
            $stmt->close();
            header("Location: tecnicos.php?ok=1");
            exit();
        }
        $chk->close();
    }
}

/* ── ELIMINAR ── */
if (isset($_GET['eliminar'])) {
    $id = (int) $_GET['eliminar'];

    $chk = $conexion->prepare("SELECT COUNT(*) AS n FROM tickets WHERE tecnico_id = ?");
    $chk->bind_param("i", $id);
    $chk->execute();
    $n = $chk->get_result()->fetch_assoc()['n'];
    $chk->close();

    if ($n > 0) {
        $error = "No se puede eliminar: el técnico tiene $n ticket(s) asociado(s).";
    } else {
        $stmt = $conexion->prepare("DELETE FROM tecnicos WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        header("Location: tecnicos.php?ok=2");
        exit();
    }
}

if (isset($_GET['ok'])) {
    $mensaje = $_GET['ok'] == 1 ? "Técnico actualizado correctamente." : "Técnico eliminado.";
}

/* ── LISTAR ── */
$resultado = $conexion->query("SELECT * FROM tecnicos ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Técnicos — Soporte Digital VER</title>
<link rel="stylesheet" href="css/estilos.css">
</head>
<body>

<header>
  <a href="dashboard.php" class="brand">🖥️ Soporte<span>Digital</span>&nbsp;VER</a>
  <nav class="nav-links">
    <a href="clientes.php">👥 Clientes</a>
    <a href="tecnicos.php" class="active">🛠️ Técnicos</a>
    <a href="tickets.php">🎫 Tickets</a>
    <a href="agenda.php">📅 Agenda</a>
    <a href="usuarios.php">👤 Usuarios</a>
  </nav>
  <div class="user-info">
    👤 <strong><?= htmlspecialchars($_SESSION['usuario']) ?></strong>
    <a href="logout.php" class="btn-logout">🚪 Salir</a>
  </div>
</header>

<div class="container">

  <div class="page-title"><span>🛠️</span> Técnicos</div>

  <div class="card">
    <h2><?= $modo_editar ? '✏️ Editar Técnico' : '➕ Registrar Técnico' ?></h2>

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
          <label>Nombre completo</label>
          <input type="text" name="nombre" placeholder="Ej. Carlos López"
            value="<?= htmlspecialchars($d['nombre']) ?>" required maxlength="120">
        </div>
        <div class="form-group">
          <label>Teléfono (10 dígitos)</label>
          <input type="text" name="telefono" placeholder="2291234567"
            value="<?= htmlspecialchars($d['telefono']) ?>" required maxlength="10" pattern="[0-9]{10}">
        </div>
        <div class="form-group">
          <label>Especialidad</label>
          <input type="text" name="especialidad" placeholder="Ej. Redes, Hardware, Software"
            value="<?= htmlspecialchars($d['especialidad']) ?>" required maxlength="120">
        </div>
        <div class="form-group">
          <label>Correo electrónico</label>
          <input type="email" name="correo" placeholder="correo@ejemplo.com"
            value="<?= htmlspecialchars($d['correo']) ?>" required maxlength="150">
        </div>
      </div>

      <div class="form-actions">
        <?php if ($modo_editar): ?>
          <button type="submit" name="actualizar" class="btn btn-primary">💾 Actualizar</button>
          <a href="tecnicos.php" class="btn btn-secondary">✖ Cancelar</a>
        <?php else: ?>
          <button type="submit" name="guardar" class="btn btn-primary">➕ Guardar Técnico</button>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Nombre</th>
          <th>Teléfono</th>
          <th>Especialidad</th>
          <th>Correo</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($fila = $resultado->fetch_assoc()): ?>
        <tr>
          <td><?= (int)$fila['id'] ?></td>
          <td><?= htmlspecialchars($fila['nombre']) ?></td>
          <td><?= htmlspecialchars($fila['telefono']) ?></td>
          <td><?= htmlspecialchars($fila['especialidad']) ?></td>
          <td><?= htmlspecialchars($fila['correo']) ?></td>
          <td style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="tecnicos.php?editar=<?= (int)$fila['id'] ?>" class="btn btn-warning btn-sm">✏️ Editar</a>
            <a href="tecnicos.php?eliminar=<?= (int)$fila['id'] ?>"
               class="btn btn-danger btn-sm"
               onclick="return confirm('¿Eliminar a <?= htmlspecialchars(addslashes($fila['nombre'])) ?>?')">
               🗑️ Eliminar
            </a>
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
