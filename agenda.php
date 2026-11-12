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
$d           = ['cliente_id'=>'','tecnico_id'=>'','fecha_hora'=>'','tipo_servicio'=>'Visita a domicilio','notas'=>'','estado'=>'Programado'];

/* ── GUARDAR ── */
if (isset($_POST['guardar'])) {
    $cliente_id    = (int) $_POST['cliente_id'];
    $tecnico_id    = (int) $_POST['tecnico_id'];
    $fecha_hora    = trim($_POST['fecha_hora']);
    $tipo_servicio = trim($_POST['tipo_servicio']);
    $notas         = trim($_POST['notas']);
    $estado        = in_array($_POST['estado'], ['Programado','En Curso','Completado','Cancelado'])
                     ? $_POST['estado'] : 'Programado';

    if (!$cliente_id || !$tecnico_id || empty($fecha_hora) || empty($tipo_servicio)) {
        $error = "Cliente, técnico, fecha/hora y tipo de servicio son obligatorios.";
    } elseif ((int)substr($fecha_hora,0,4) < 2026 || (int)substr($fecha_hora,0,4) > 2027) {
        $error = "Solo se permiten citas entre 2026 y 2027.";
    } elseif (preg_match('/[?¡!@#$%^&*()+=\[\]{};:\"\\|<>\/]/u',$notas)) {
        $error = "No se permiten caracteres especiales en este campo.";
    } else {
        $stmt = $conexion->prepare(
            "INSERT INTO agenda (cliente_id, tecnico_id, fecha_hora, tipo_servicio, notas, estado)
             VALUES (?,?,?,?,?,?)"
        );
        $stmt->bind_param("iissss", $cliente_id, $tecnico_id, $fecha_hora, $tipo_servicio, $notas, $estado);
        $stmt->execute();
        $stmt->close();
        header("Location: agenda.php?ok=1");
        exit();
    }
}

/* ── CARGAR PARA EDITAR ── */
if (isset($_GET['editar'])) {
    $id_editar   = (int) $_GET['editar'];
    $modo_editar = true;

    $stmt = $conexion->prepare("SELECT * FROM agenda WHERE id = ?");
    $stmt->bind_param("i", $id_editar);
    $stmt->execute();
    if ($fila = $stmt->get_result()->fetch_assoc()) {
        $d = $fila;
        // Formato compatible con datetime-local input
        $d['fecha_hora'] = str_replace(' ', 'T', substr($d['fecha_hora'], 0, 16));
    }
    $stmt->close();
}

/* ── ACTUALIZAR ── */
if (isset($_POST['actualizar'])) {
    $id            = (int) $_POST['id'];
    $cliente_id    = (int) $_POST['cliente_id'];
    $tecnico_id    = (int) $_POST['tecnico_id'];
    $fecha_hora    = trim($_POST['fecha_hora']);
    $tipo_servicio = trim($_POST['tipo_servicio']);
    $notas         = trim($_POST['notas']);
    $estado        = in_array($_POST['estado'], ['Programado','En Curso','Completado','Cancelado'])
                     ? $_POST['estado'] : 'Programado';

    if (!$cliente_id || !$tecnico_id || empty($fecha_hora) || empty($tipo_servicio)) {
        $error = "Cliente, técnico, fecha/hora y tipo de servicio son obligatorios.";
        $modo_editar = true; $id_editar = $id; $d = $_POST;
    } elseif ((int)substr($fecha_hora,0,4) < 2026 || (int)substr($fecha_hora,0,4) > 2027) {
        $error = "Solo se permiten citas entre 2026 y 2027.";
    } elseif (preg_match('/[?¡!@#$%^&*()+=\[\]{};:\"\\|<>\/]/u',$notas)) {
        $error = "No se permiten caracteres especiales en este campo.";
        $modo_editar = true; $id_editar = $id; $d = $_POST;
    } else {
        $stmt = $conexion->prepare(
            "UPDATE agenda SET cliente_id=?, tecnico_id=?, fecha_hora=?, tipo_servicio=?, notas=?, estado=?
             WHERE id=?"
        );
        $stmt->bind_param("iissssi", $cliente_id, $tecnico_id, $fecha_hora, $tipo_servicio, $notas, $estado, $id);
        $stmt->execute();
        $stmt->close();
        header("Location: agenda.php?ok=2");
        exit();
    }
}

/* ── ELIMINAR ── */
if (isset($_GET['eliminar'])) {
    $id   = (int) $_GET['eliminar'];
    $stmt = $conexion->prepare("DELETE FROM agenda WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: agenda.php?ok=3");
    exit();
}

if (isset($_GET['ok'])) {
    $msgs = ['1'=>'Servicio agendado correctamente.','2'=>'Servicio actualizado.','3'=>'Servicio eliminado.'];
    $mensaje = $msgs[$_GET['ok']] ?? '';
}

/* ── LISTAS ── */
$clientes = $conexion->query("SELECT id, nombre FROM clientes ORDER BY nombre");
$tecnicos = $conexion->query("SELECT id, nombre FROM tecnicos ORDER BY nombre");

// Mes a mostrar
$mes_actual = $_GET['mes'] ?? date('Y-m');
[$anio, $mes] = explode('-', $mes_actual);
$primer_dia   = mktime(0,0,0, $mes, 1, $anio);
$dias_mes     = (int) date('t', $primer_dia);
$dia_semana   = (int) date('w', $primer_dia); // 0=dom

// Servicios del mes
$inicio = "$anio-$mes-01 00:00:00";
$fin    = "$anio-$mes-$dias_mes 23:59:59";
$stmt   = $conexion->prepare(
    "SELECT a.id, a.fecha_hora, a.tipo_servicio, a.estado,
            c.nombre AS cliente, te.nombre AS tecnico
     FROM agenda a
     INNER JOIN clientes c  ON a.cliente_id  = c.id
     INNER JOIN tecnicos te ON a.tecnico_id  = te.id
     WHERE a.fecha_hora BETWEEN ? AND ?
     ORDER BY a.fecha_hora"
);
$stmt->bind_param("ss", $inicio, $fin);
$stmt->execute();
$servicios_mes = $stmt->get_result();
$stmt->close();

// Indexar por día
$por_dia = [];
while ($srv = $servicios_mes->fetch_assoc()) {
    $d_num = (int) date('j', strtotime($srv['fecha_hora']));
    $por_dia[$d_num][] = $srv;
}

// Lista completa para la tabla
$lista = $conexion->query(
    "SELECT a.id, a.fecha_hora, a.tipo_servicio, a.notas, a.estado,
            c.nombre AS cliente, te.nombre AS tecnico
     FROM agenda a
     INNER JOIN clientes c  ON a.cliente_id  = c.id
     INNER JOIN tecnicos te ON a.tecnico_id  = te.id
     ORDER BY a.fecha_hora DESC
     LIMIT 50"
);

$prev_mes = date('Y-m', strtotime("$anio-$mes-01 -1 month"));
$next_mes = date('Y-m', strtotime("$anio-$mes-01 +1 month"));

$estado_color = ['Programado'=>'badge-process','En Curso'=>'badge-open','Completado'=>'badge-closed','Cancelado'=>'badge-danger'];

function badgeAgenda($estado) {
    $map = ['Programado'=>'badge-process','En Curso'=>'badge-open','Completado'=>'badge-closed','Cancelado'=>'badge-danger'];
    $cls = $map[$estado] ?? 'badge-closed';
    return "<span class='badge $cls'>" . htmlspecialchars($estado) . "</span>";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Agenda — Soporte Digital VER</title>
<link rel="stylesheet" href="css/estilos.css">
<style>
  /* ── Calendario ── */
  .cal-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 16px;
  }
  .cal-header h3 { font-size: 17px; font-weight: 700; color: var(--text); }
  .cal-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
  }
  .cal-day-name {
    text-align: center;
    font-size: 11px; font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    padding: 6px 0;
  }
  .cal-cell {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 8px;
    min-height: 72px;
    padding: 6px 8px;
    font-size: 12px;
    position: relative;
  }
  .cal-cell.empty { background: transparent; border-color: transparent; }
  .cal-cell.today { border-color: var(--accent); }
  .cal-num {
    font-size: 13px; font-weight: 700; color: var(--text-muted);
    margin-bottom: 4px;
    display: block;
  }
  .cal-cell.today .cal-num { color: var(--accent); }
  .cal-event {
    background: rgba(0,212,255,0.1);
    border-left: 2px solid var(--accent);
    border-radius: 3px;
    padding: 2px 5px;
    margin-bottom: 2px;
    font-size: 11px;
    color: var(--text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .cal-event.Completado { border-color: var(--text-muted); opacity: .6; }
  .cal-event.Cancelado  { border-color: var(--danger); opacity: .5; }
  .cal-event.En\ Curso  { border-color: var(--success); }
  .badge-danger {
    background: rgba(255,77,109,0.15);
    color: var(--danger);
    border: 1px solid rgba(255,77,109,0.3);
  }
</style>
</head>
<body>

<header>
  <a href="dashboard.php" class="brand">🖥️ Soporte<span>Digital</span>&nbsp;VER</a>
  <nav class="nav-links">
    <a href="clientes.php">👥 Clientes</a>
    <a href="tecnicos.php">🛠️ Técnicos</a>
    <a href="tickets.php">🎫 Tickets</a>
    <a href="agenda.php" class="active">📅 Agenda</a>
    <a href="usuarios.php">👤 Usuarios</a>
  </nav>
  <div class="user-info">
    👤 <strong><?= htmlspecialchars($_SESSION['usuario']) ?></strong>
    <a href="logout.php" class="btn-logout">🚪 Salir</a>
  </div>
</header>

<div class="container">

  <div class="page-title"><span>📅</span> Agenda de Servicios</div>

  <!-- FORMULARIO -->
  <div class="card">
    <h2><?= $modo_editar ? '✏️ Editar Servicio' : '➕ Programar Servicio' ?></h2>

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
            <option value="">— Seleccione —</option>
            <?php $clientes->data_seek(0); while ($c = $clientes->fetch_assoc()): ?>
              <option value="<?= (int)$c['id'] ?>"
                <?= (string)($d['cliente_id'] ?? '') === (string)$c['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['nombre']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Técnico</label>
          <select name="tecnico_id" required>
            <option value="">— Seleccione —</option>
            <?php $tecnicos->data_seek(0); while ($t = $tecnicos->fetch_assoc()): ?>
              <option value="<?= (int)$t['id'] ?>"
                <?= (string)($d['tecnico_id'] ?? '') === (string)$t['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($t['nombre']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Fecha y hora</label>
          <input type="datetime-local" min="2026-01-01T00:00" max="2027-12-31T23:59" min="2026-01-01T00:00" max="2027-12-31T23:59" name="fecha_hora"
            value="<?= htmlspecialchars($d['fecha_hora']) ?>" required>
        </div>
        <div class="form-group">
          <label>Tipo de servicio</label>
          <select name="tipo_servicio">
            <?php foreach (['Visita a domicilio','Soporte remoto','Entrega de equipo','Revisión preventiva'] as $tipo): ?>
              <option value="<?= $tipo ?>" <?= ($d['tipo_servicio'] ?? '') === $tipo ? 'selected' : '' ?>>
                <?= $tipo ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Estado</label>
          <select name="estado">
            <?php foreach (['Programado','En Curso','Completado','Cancelado'] as $est): ?>
              <option value="<?= $est ?>" <?= ($d['estado'] ?? 'Programado') === $est ? 'selected' : '' ?>>
                <?= $est ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-group" style="margin-top:14px;">
        <label>Notas adicionales</label>
        <textarea name="notas" rows="2" placeholder="Indicaciones de acceso, equipo a revisar, etc."
          maxlength="500"><?= htmlspecialchars($d['notas'] ?? '') ?></textarea>
      </div>

      <div class="form-actions">
        <?php if ($modo_editar): ?>
          <button type="submit" name="actualizar" class="btn btn-primary">💾 Actualizar</button>
          <a href="agenda.php" class="btn btn-secondary">✖ Cancelar</a>
        <?php else: ?>
          <button type="submit" name="guardar" class="btn btn-primary">➕ Agendar</button>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- CALENDARIO -->
  <div class="card">
    <div class="cal-header">
      <a href="agenda.php?mes=<?= $prev_mes ?>" class="btn btn-secondary btn-sm">← Anterior</a>
      <h3>📅 <?= ucfirst(strftime('%B %Y', $primer_dia)) ?></h3>
      <a href="agenda.php?mes=<?= $next_mes ?>" class="btn btn-secondary btn-sm">Siguiente →</a>
    </div>

    <div class="cal-grid">
      <?php foreach (['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'] as $dn): ?>
        <div class="cal-day-name"><?= $dn ?></div>
      <?php endforeach; ?>

      <?php
        // Celdas vacías antes del día 1
        for ($i = 0; $i < $dia_semana; $i++): ?>
          <div class="cal-cell empty"></div>
      <?php endfor;

        $hoy = (int) date('j');
        $mes_hoy  = date('m');
        $anio_hoy = date('Y');

        for ($dia = 1; $dia <= $dias_mes; $dia++):
          $es_hoy = ($dia === $hoy && (int)$mes === (int)$mes_hoy && (int)$anio === (int)$anio_hoy);
      ?>
          <div class="cal-cell <?= $es_hoy ? 'today' : '' ?>">
            <span class="cal-num"><?= $dia ?></span>
            <?php if (!empty($por_dia[$dia])): ?>
              <?php foreach ($por_dia[$dia] as $ev): ?>
                <div class="cal-event <?= htmlspecialchars($ev['estado']) ?>"
                     title="<?= htmlspecialchars($ev['cliente'] . ' — ' . $ev['tecnico']) ?>">
                  <?= date('H:i', strtotime($ev['fecha_hora'])) ?>
                  <?= htmlspecialchars(mb_strimwidth($ev['cliente'], 0, 12, '…')) ?>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
      <?php endfor; ?>
    </div>
  </div>

  <!-- LISTA DE SERVICIOS -->
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Fecha / Hora</th>
          <th>Cliente</th>
          <th>Técnico</th>
          <th>Tipo</th>
          <th>Notas</th>
          <th>Estado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($srv = $lista->fetch_assoc()): ?>
        <tr>
          <td><?= (int)$srv['id'] ?></td>
          <td><?= htmlspecialchars(str_replace('T',' ', $srv['fecha_hora'])) ?></td>
          <td><?= htmlspecialchars($srv['cliente']) ?></td>
          <td><?= htmlspecialchars($srv['tecnico']) ?></td>
          <td><?= htmlspecialchars($srv['tipo_servicio']) ?></td>
          <td style="max-width:180px;white-space:normal;font-size:13px;color:var(--text-muted);">
            <?= htmlspecialchars($srv['notas'] ?? '—') ?>
          </td>
          <td><?= badgeAgenda($srv['estado']) ?></td>
          <td style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="agenda.php?editar=<?= (int)$srv['id'] ?>" class="btn btn-warning btn-sm">✏️</a>
            <a href="agenda.php?eliminar=<?= (int)$srv['id'] ?>"
               class="btn btn-danger btn-sm"
               onclick="return confirm('¿Eliminar este servicio agendado?')">🗑️</a>
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
