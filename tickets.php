<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

include("conexion.php");
function contieneEspeciales($txt){ return preg_match('/[?¿¡!@#$%^&*()+=\[\]{};:"\\|<>\/]/u',$txt); }

$error       = "";
$mensaje     = "";
$modo_editar = false;
$id_editar   = 0;
$d           = ['cliente_id'=>'','tecnico_id'=>'','fecha'=>'','descripcion'=>'','estado'=>'Abierto','reporte_tecnico'=>''];

/* ── GUARDAR ── */
if (isset($_POST['guardar'])) {
    $cliente_id      = (int) $_POST['cliente_id'];
    $tecnico_id      = (int) $_POST['tecnico_id'];
    $fecha           = $_POST['fecha'];
    $descripcion     = trim($_POST['descripcion']);
    $estado          = $_POST['estado'];
    $reporte_tecnico = trim($_POST['reporte_tecnico'] ?? '');

    $estados_validos = ['Abierto','En Proceso','Cerrado'];

    if (!$cliente_id || !$tecnico_id || empty($fecha) || empty($descripcion)) {
        $error = "Todos los campos obligatorios deben llenarse.";
    } elseif (!in_array($estado, $estados_validos)) {
        $error = "Estado no válido.";
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !checkdate((int)substr($fecha,5,2),(int)substr($fecha,8,2),(int)substr($fecha,0,4))) {
        $error = "La fecha no es válida.";
    } elseif ((int)substr($fecha,0,4) < 2000 || (int)substr($fecha,0,4) > 2100) {
        $error = "La fecha debe estar entre el año 2000 y 2100.";
    } elseif (contieneEspeciales($descripcion) || contieneEspeciales($reporte_tecnico)) {
        $error = "No se permiten caracteres especiales en este campo.";
    } else {
        $stmt = $conexion->prepare(
            "INSERT INTO tickets (cliente_id, tecnico_id, fecha, descripcion, estado, reporte_tecnico)
             VALUES (?,?,?,?,?,?)"
        );
        $stmt->bind_param("iissss", $cliente_id, $tecnico_id, $fecha, $descripcion, $estado, $reporte_tecnico);
        $stmt->execute();
        $nuevo_id = $stmt->insert_id;
        $stmt->close();

        // Notificación simulada al crear
        _registrar_notificacion($conexion, $nuevo_id, $cliente_id, $estado);

        header("Location: tickets.php?ok=1");
        exit();
    }
}

/* ── CARGAR PARA EDITAR ── */
if (isset($_GET['editar'])) {
    $id_editar   = (int) $_GET['editar'];
    $modo_editar = true;

    $stmt = $conexion->prepare("SELECT * FROM tickets WHERE id = ?");
    $stmt->bind_param("i", $id_editar);
    $stmt->execute();
    if ($fila = $stmt->get_result()->fetch_assoc()) {
        $d = $fila;
    }
    $stmt->close();
}

/* ── ACTUALIZAR ── */
if (isset($_POST['actualizar'])) {
    $id              = (int) $_POST['id'];
    $cliente_id      = (int) $_POST['cliente_id'];
    $tecnico_id      = (int) $_POST['tecnico_id'];
    $fecha           = $_POST['fecha'];
    $descripcion     = trim($_POST['descripcion']);
    $estado          = $_POST['estado'];
    $reporte_tecnico = trim($_POST['reporte_tecnico'] ?? '');

    $estados_validos = ['Abierto','En Proceso','Cerrado'];

    if (!$cliente_id || !$tecnico_id || empty($fecha) || empty($descripcion)) {
        $error = "Todos los campos obligatorios deben llenarse.";
        $modo_editar = true; $id_editar = $id; $d = $_POST;
    } elseif (!in_array($estado, $estados_validos)) {
        $error = "Estado no válido.";
        $modo_editar = true; $id_editar = $id; $d = $_POST;
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !checkdate((int)substr($fecha,5,2),(int)substr($fecha,8,2),(int)substr($fecha,0,4))) {
        $error = "La fecha no es válida.";
        $modo_editar = true; $id_editar = $id; $d = $_POST;
    } elseif ((int)substr($fecha,0,4) < 2000 || (int)substr($fecha,0,4) > 2100) {
        $error = "La fecha debe estar entre el año 2000 y 2100.";
        $modo_editar = true; $id_editar = $id; $d = $_POST;
    } else {
        // Verificar si cambió el estado para notificación
        $prev = $conexion->prepare("SELECT estado, cliente_id FROM tickets WHERE id=?");
        $prev->bind_param("i", $id);
        $prev->execute();
        $prev_data = $prev->get_result()->fetch_assoc();
        $prev->close();

        $stmt = $conexion->prepare(
            "UPDATE tickets SET cliente_id=?, tecnico_id=?, fecha=?, descripcion=?, estado=?, reporte_tecnico=?
             WHERE id=?"
        );
        $stmt->bind_param("iissssi", $cliente_id, $tecnico_id, $fecha, $descripcion, $estado, $reporte_tecnico, $id);
        $stmt->execute();
        $stmt->close();

        // Notificación si cambió el estado
        if ($prev_data && $prev_data['estado'] !== $estado) {
            _registrar_notificacion($conexion, $id, $cliente_id, $estado);
        }

        header("Location: tickets.php?ok=2");
        exit();
    }
}

/* ── ELIMINAR ── */
if (isset($_GET['eliminar'])) {
    $id = (int) $_GET['eliminar'];

    // Solo se puede eliminar si el ticket está Cerrado
    $chk = $conexion->prepare("SELECT estado FROM tickets WHERE id = ?");
    $chk->bind_param("i", $id);
    $chk->execute();
    $fila_chk = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$fila_chk) {
        $error = "El ticket no existe.";
    } elseif ($fila_chk['estado'] !== 'Cerrado') {
        $error = "No se puede eliminar el ticket porque está en estado \"{$fila_chk['estado']}\". Primero debe cerrarse.";
    } else {
        $stmt = $conexion->prepare("DELETE FROM tickets WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        header("Location: tickets.php?ok=3");
        exit();
    }
    exit();
}

if (isset($_GET['ok'])) {
    $msgs = ['1'=>'Ticket creado.','2'=>'Ticket actualizado.','3'=>'Ticket eliminado.'];
    $mensaje = $msgs[$_GET['ok']] ?? '';
}

/* ── FUNCIÓN: Registrar notificación simulada ── */
function _registrar_notificacion($conexion, $ticket_id, $cliente_id, $estado_nuevo) {
    $msg = "Tu solicitud (Ticket #$ticket_id) ha cambiado de estado a: $estado_nuevo.";
    $stmt = $conexion->prepare(
        "INSERT INTO notificaciones (ticket_id, cliente_id, mensaje, estado_nuevo)
         VALUES (?,?,?,?)"
    );
    $stmt->bind_param("iiss", $ticket_id, $cliente_id, $msg, $estado_nuevo);
    $stmt->execute();
    $stmt->close();
}

/* ── LISTAS ── */
$clientes = $conexion->query("SELECT * FROM clientes ORDER BY nombre");
$tecnicos = $conexion->query("SELECT * FROM tecnicos ORDER BY nombre");

$tickets = $conexion->query(
    "SELECT t.id, c.nombre AS cliente, c.id AS cliente_id_val, te.nombre AS tecnico,
            t.fecha, t.descripcion, t.estado, t.reporte_tecnico
     FROM tickets t
     INNER JOIN clientes c  ON t.cliente_id = c.id
     INNER JOIN tecnicos te ON t.tecnico_id = te.id
     ORDER BY t.id DESC"
);

// Notificaciones recientes (últimas 5) para mostrar en página
$notif_recientes = $conexion->query(
    "SELECT n.id, n.mensaje, n.estado_nuevo, n.fecha_envio, c.nombre AS cliente
     FROM notificaciones n
     INNER JOIN clientes c ON n.cliente_id = c.id
     ORDER BY n.id DESC LIMIT 5"
);

function badgeEstado($estado) {
    $map = ['Abierto'=>'badge-open','En Proceso'=>'badge-process','Cerrado'=>'badge-closed'];
    $cls = $map[$estado] ?? 'badge-closed';
    return "<span class='badge $cls'>" . htmlspecialchars($estado) . "</span>";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tickets — Soporte Digital VER</title>
<link rel="stylesheet" href="css/estilos.css">
<style>
  .notif-panel {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 18px 22px;
    margin-bottom: 28px;
  }
  .notif-panel h3 { font-size: 15px; font-weight: 700; margin-bottom: 12px; color: var(--text); }
  .notif-item {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
  }
  .notif-item:last-child { border-bottom: none; }
  .notif-icon { font-size: 18px; flex-shrink: 0; }
  .notif-body { flex: 1; }
  .notif-msg  { font-size: 13px; color: var(--text); }
  .notif-meta { font-size: 11px; color: var(--text-muted); margin-top: 3px; }
  .reporte-section {
    margin-top: 14px;
    border-top: 1px solid var(--border);
    padding-top: 14px;
  }
</style>
</head>
<body>

<header>
  <a href="dashboard.php" class="brand">🖥️ Soporte<span>Digital</span>&nbsp;VER</a>
  <nav class="nav-links">
    <a href="clientes.php">👥 Clientes</a>
    <a href="tecnicos.php">🛠️ Técnicos</a>
    <a href="tickets.php" class="active">🎫 Tickets</a>
    <a href="agenda.php">📅 Agenda</a>
    <a href="usuarios.php">👤 Usuarios</a>
  </nav>
  <div class="user-info">
    👤 <strong><?= htmlspecialchars($_SESSION['usuario']) ?></strong>
    <a href="logout.php" class="btn-logout">🚪 Salir</a>
  </div>
</header>

<div class="container">

  <div class="page-title"><span>🎫</span> Tickets</div>

  <!-- Panel de notificaciones recientes (RF-08) -->
  <?php
  $notif_arr = [];
  while ($n = $notif_recientes->fetch_assoc()) $notif_arr[] = $n;
  if (!empty($notif_arr)): ?>
  <div class="notif-panel">
    <h3>🔔 Notificaciones recientes (simuladas)</h3>
    <?php foreach ($notif_arr as $n): ?>
      <div class="notif-item">
        <span class="notif-icon">📨</span>
        <div class="notif-body">
          <div class="notif-msg"><?= htmlspecialchars($n['mensaje']) ?></div>
          <div class="notif-meta">
            Cliente: <strong><?= htmlspecialchars($n['cliente']) ?></strong>
            — <?= htmlspecialchars($n['fecha_envio']) ?>
            — <span style="color:var(--success)">✅ Notificación enviada</span>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Formulario -->
  <div class="card">
    <h2><?= $modo_editar ? '✏️ Editar Ticket' : '➕ Registrar Ticket' ?></h2>

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
          <label>Cliente</label>
          <select name="cliente_id" required>
            <option value="">— Seleccione un cliente —</option>
            <?php while ($c = $clientes->fetch_assoc()): ?>
              <option value="<?= (int)$c['id'] ?>"
                <?= (string)$d['cliente_id'] === (string)$c['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['nombre']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Técnico asignado</label>
          <select name="tecnico_id" required>
            <option value="">— Seleccione un técnico —</option>
            <?php while ($t = $tecnicos->fetch_assoc()): ?>
              <option value="<?= (int)$t['id'] ?>"
                <?= (string)$d['tecnico_id'] === (string)$t['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($t['nombre']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Fecha</label>
          <input type="date" name="fecha"
            value="<?= htmlspecialchars($d['fecha']) ?>" required>
        </div>
        <div class="form-group">
          <label>Estado</label>
          <select name="estado" required>
            <?php foreach (['Abierto','En Proceso','Cerrado'] as $est): ?>
              <option value="<?= $est ?>" <?= $d['estado'] === $est ? 'selected' : '' ?>>
                <?= $est ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-group" style="margin-top:14px;">
        <label>Descripción del problema</label>
        <textarea name="descripcion" rows="3" placeholder="Describe el problema o solicitud..."
          required maxlength="1000"><?= htmlspecialchars($d['descripcion']) ?></textarea>
      </div>

      <!-- RF-03: Reporte Técnico (visible siempre, obligatorio al cerrar) -->
      <div class="reporte-section">
        <div class="form-group">
          <label>
            📝 Reporte Técnico
            <span style="font-size:12px;color:var(--text-muted);font-weight:400;">
              — Acciones realizadas (requerido al cerrar el ticket)
            </span>
          </label>
          <textarea name="reporte_tecnico" rows="3"
            placeholder="Ej: Se eliminó malware con Malwarebytes. Se actualizó Windows 11. Se instaló antivirus ESET. Se realizó respaldo en disco externo."
            maxlength="2000"><?= htmlspecialchars($d['reporte_tecnico'] ?? '') ?></textarea>
        </div>
      </div>

      <div class="form-actions">
        <?php if ($modo_editar): ?>
          <button type="submit" name="actualizar" class="btn btn-primary">💾 Actualizar Ticket</button>
          <a href="tickets.php" class="btn btn-secondary">✖ Cancelar</a>
        <?php else: ?>
          <button type="submit" name="guardar" class="btn btn-primary">➕ Guardar Ticket</button>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Tabla de tickets -->
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Cliente</th>
          <th>Técnico</th>
          <th>Fecha</th>
          <th>Descripción</th>
          <th>Reporte</th>
          <th>Estado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($ticket = $tickets->fetch_assoc()): ?>
        <tr>
          <td><?= (int)$ticket['id'] ?></td>
          <td>
            <a href="historial_cliente.php?id=<?= (int)$ticket['cliente_id_val'] ?>"
               style="color:var(--accent);text-decoration:none;"
               title="Ver historial del cliente">
              <?= htmlspecialchars($ticket['cliente']) ?>
            </a>
          </td>
          <td><?= htmlspecialchars($ticket['tecnico']) ?></td>
          <td><?= htmlspecialchars($ticket['fecha']) ?></td>
          <td style="max-width:180px;white-space:normal;"><?= htmlspecialchars($ticket['descripcion']) ?></td>
          <td style="max-width:180px;">
            <?php if (!empty($ticket['reporte_tecnico'])): ?>
              <span style="font-size:12px;color:var(--text);">
                <?= htmlspecialchars(mb_strimwidth($ticket['reporte_tecnico'], 0, 60, '…')) ?>
              </span>
            <?php else: ?>
              <span style="font-size:12px;color:var(--text-muted);font-style:italic;">Sin reporte</span>
            <?php endif; ?>
          </td>
          <td><?= badgeEstado($ticket['estado']) ?></td>
          <td style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="tickets.php?editar=<?= (int)$ticket['id'] ?>" class="btn btn-warning btn-sm">✏️ Editar</a>
            <?php if ($ticket['estado'] === 'Cerrado'): ?>
              <a href="tickets.php?eliminar=<?= (int)$ticket['id'] ?>"
                 class="btn btn-danger btn-sm"
                 onclick="return confirm('¿Eliminar ticket #<?= (int)$ticket['id'] ?>? Esta acción no se puede deshacer.')">
                 🗑️ Eliminar
              </a>
            <?php else: ?>
              <span class="btn btn-sm" style="background:#444;color:#888;cursor:not-allowed;opacity:.6;"
                title="Solo se pueden eliminar tickets Cerrados">🔒 Eliminar</span>
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
