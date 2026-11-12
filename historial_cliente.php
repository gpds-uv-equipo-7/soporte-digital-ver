<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

include("conexion.php");

$cliente_id = (int) ($_GET['id'] ?? 0);

if (!$cliente_id) {
    header("Location: clientes.php");
    exit();
}

// Datos del cliente
$stmt = $conexion->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->bind_param("i", $cliente_id);
$stmt->execute();
$cliente = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$cliente) {
    header("Location: clientes.php");
    exit();
}

// Historial de tickets del cliente
$stmt = $conexion->prepare(
    "SELECT t.id, t.fecha, t.descripcion, t.estado, t.reporte_tecnico,
            te.nombre AS tecnico
     FROM tickets t
     INNER JOIN tecnicos te ON t.tecnico_id = te.id
     WHERE t.cliente_id = ?
     ORDER BY t.id DESC"
);
$stmt->bind_param("i", $cliente_id);
$stmt->execute();
$tickets = $stmt->get_result();
$stmt->close();

// Conteos rápidos
$stats = $conexion->prepare(
    "SELECT
       COUNT(*) AS total,
       SUM(estado='Abierto') AS abiertos,
       SUM(estado='En Proceso') AS en_proceso,
       SUM(estado='Cerrado') AS cerrados
     FROM tickets WHERE cliente_id = ?"
);
$stats->bind_param("i", $cliente_id);
$stats->execute();
$s = $stats->get_result()->fetch_assoc();
$stats->close();

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
<title>Historial de <?= htmlspecialchars($cliente['nombre']) ?> — Soporte Digital VER</title>
<link rel="stylesheet" href="css/estilos.css">
<style>
  .historial-header {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 24px 28px;
    margin-bottom: 24px;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
  }
  .cliente-info h3 { font-size: 20px; color: var(--text); margin-bottom: 8px; }
  .cliente-info p  { font-size: 13px; color: var(--text-muted); margin: 3px 0; }
  .mini-stats { display: flex; gap: 12px; flex-wrap: wrap; }
  .mini-stat {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 12px 18px;
    text-align: center;
    min-width: 80px;
  }
  .mini-stat .n { font-size: 22px; font-weight: 800; }
  .mini-stat .l { font-size: 11px; color: var(--text-muted); margin-top: 3px; }
  .reporte-box {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 13px;
    color: var(--text);
    max-width: 300px;
    white-space: pre-wrap;
    word-break: break-word;
  }
  .no-reporte { color: var(--text-muted); font-style: italic; font-size: 13px; }
  .empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-muted);
  }
  .empty-state .icon { font-size: 48px; margin-bottom: 12px; }
</style>
</head>
<body>

<header>
  <a href="dashboard.php" class="brand">🖥️ Soporte<span>Digital</span>&nbsp;VER</a>
  <nav class="nav-links">
    <a href="clientes.php" class="active">👥 Clientes</a>
    <a href="tecnicos.php">🛠️ Técnicos</a>
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

  <div class="page-title"><span>📋</span> Historial del Cliente</div>

  <!-- Tarjeta del cliente + estadísticas -->
  <div class="historial-header">
    <div class="cliente-info">
      <h3>👤 <?= htmlspecialchars($cliente['nombre']) ?></h3>
      <p>📞 <?= htmlspecialchars($cliente['telefono']) ?></p>
      <p>📧 <?= htmlspecialchars($cliente['correo']) ?></p>
      <p>📍 <?= htmlspecialchars($cliente['direccion']) ?></p>
    </div>
    <div class="mini-stats">
      <div class="mini-stat">
        <div class="n"><?= (int)$s['total'] ?></div>
        <div class="l">Total</div>
      </div>
      <div class="mini-stat">
        <div class="n" style="color:var(--success)"><?= (int)$s['abiertos'] ?></div>
        <div class="l">Abiertos</div>
      </div>
      <div class="mini-stat">
        <div class="n" style="color:var(--warning)"><?= (int)$s['en_proceso'] ?></div>
        <div class="l">En Proceso</div>
      </div>
      <div class="mini-stat">
        <div class="n" style="color:var(--text-muted)"><?= (int)$s['cerrados'] ?></div>
        <div class="l">Cerrados</div>
      </div>
    </div>
  </div>

  <!-- Tabla de tickets -->
  <?php if ($s['total'] == 0): ?>
    <div class="empty-state">
      <div class="icon">🎫</div>
      <p>Este cliente no tiene tickets registrados todavía.</p>
    </div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Fecha</th>
          <th>Técnico</th>
          <th>Descripción</th>
          <th>Reporte Técnico</th>
          <th>Estado</th>
          <th>Acción</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($t = $tickets->fetch_assoc()): ?>
        <tr>
          <td><?= (int)$t['id'] ?></td>
          <td><?= htmlspecialchars($t['fecha']) ?></td>
          <td><?= htmlspecialchars($t['tecnico']) ?></td>
          <td style="max-width:200px;white-space:normal;"><?= htmlspecialchars($t['descripcion']) ?></td>
          <td>
            <?php if (!empty($t['reporte_tecnico'])): ?>
              <div class="reporte-box"><?= htmlspecialchars($t['reporte_tecnico']) ?></div>
            <?php else: ?>
              <span class="no-reporte">Sin reporte</span>
            <?php endif; ?>
          </td>
          <td><?= badgeEstado($t['estado']) ?></td>
          <td>
            <a href="tickets.php?editar=<?= (int)$t['id'] ?>" class="btn btn-warning btn-sm">✏️ Ver/Editar</a>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <div style="margin-top:20px;display:flex;gap:10px;">
    <a href="clientes.php" class="btn btn-secondary">← Regresar a Clientes</a>
    <a href="tickets.php" class="btn btn-primary">🎫 Nuevo Ticket</a>
  </div>

</div>
</body>
</html>
