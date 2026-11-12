<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

include("conexion.php");

$clientes = (int) $conexion->query("SELECT COUNT(*) AS n FROM clientes")->fetch_assoc()['n'];
$tecnicos = (int) $conexion->query("SELECT COUNT(*) AS n FROM tecnicos")->fetch_assoc()['n'];
$tickets  = (int) $conexion->query("SELECT COUNT(*) AS n FROM tickets")->fetch_assoc()['n'];
$usuarios = (int) $conexion->query("SELECT COUNT(*) AS n FROM usuarios")->fetch_assoc()['n'];

$abiertos  = (int) $conexion->query("SELECT COUNT(*) AS n FROM tickets WHERE estado='Abierto'")->fetch_assoc()['n'];
$enproceso = (int) $conexion->query("SELECT COUNT(*) AS n FROM tickets WHERE estado='En Proceso'")->fetch_assoc()['n'];
$cerrados  = (int) $conexion->query("SELECT COUNT(*) AS n FROM tickets WHERE estado='Cerrado'")->fetch_assoc()['n'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — Soporte Digital VER</title>
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
    <a href="usuarios.php">👤 Usuarios</a>
  </nav>

  <div class="user-info">
    👤 <strong><?= htmlspecialchars($_SESSION['usuario']) ?></strong>
    <a href="logout.php" class="btn-logout">🚪 Salir</a>
  </div>
</header>

<div class="container">

  <div class="page-title">
    <span>📊</span> Dashboard
  </div>

  <!-- Stats principales -->
  <div class="stats">
    <div class="stat-card">
      <div class="stat-num"><?= $clientes ?></div>
      <div class="stat-label">Clientes</div>
      <div class="stat-icon">👥</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= $tecnicos ?></div>
      <div class="stat-label">Técnicos</div>
      <div class="stat-icon">🛠️</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= $tickets ?></div>
      <div class="stat-label">Tickets totales</div>
      <div class="stat-icon">🎫</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= $usuarios ?></div>
      <div class="stat-label">Usuarios del sistema</div>
      <div class="stat-icon">👤</div>
    </div>
  </div>

  <!-- Estado de tickets -->
  <div class="stats" style="grid-template-columns: repeat(3,1fr); margin-bottom:36px;">
    <div class="stat-card">
      <div class="stat-num" style="color:var(--success)"><?= $abiertos ?></div>
      <div class="stat-label">Tickets Abiertos</div>
    </div>
    <div class="stat-card">
      <div class="stat-num" style="color:var(--warning)"><?= $enproceso ?></div>
      <div class="stat-label">En Proceso</div>
    </div>
    <div class="stat-card">
      <div class="stat-num" style="color:var(--text-muted)"><?= $cerrados ?></div>
      <div class="stat-label">Cerrados</div>
    </div>
  </div>

  <!-- Menú de navegación -->
  <div class="menu">
    <a href="clientes.php" class="menu-card">
      <div class="menu-icon">👥</div>
      <div class="menu-label">Clientes</div>
      <div class="menu-desc">Registrar, editar y eliminar clientes</div>
    </a>
    <a href="tecnicos.php" class="menu-card">
      <div class="menu-icon">🛠️</div>
      <div class="menu-label">Técnicos</div>
      <div class="menu-desc">Gestionar personal técnico</div>
    </a>
    <a href="tickets.php" class="menu-card">
      <div class="menu-icon">🎫</div>
      <div class="menu-label">Tickets</div>
      <div class="menu-desc">Crear y dar seguimiento a tickets</div>
    </a>
    <a href="agenda.php" class="menu-card">
      <div class="menu-icon">📅</div>
      <div class="menu-label">Agenda</div>
      <div class="menu-desc">Programar visitas y servicios</div>
    </a>
    <a href="usuarios.php" class="menu-card">
      <div class="menu-icon">👤</div>
      <div class="menu-label">Usuarios</div>
      <div class="menu-desc">Administrar accesos al sistema</div>
    </a>
  </div>

</div>

</body>
</html>
