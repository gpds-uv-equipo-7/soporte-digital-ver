<?php
session_start();

if (isset($_SESSION['usuario'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";

if (isset($_POST['login'])) {

    include("conexion.php");

    $usuario  = trim($_POST['usuario']);
    $password = $_POST['password'];

    // ── Prepared statement para evitar SQL Injection ──
    $stmt = $conexion->prepare(
        "SELECT id, usuario, contraseña FROM usuarios WHERE usuario = ?"
    );
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $fila = $resultado->fetch_assoc();
        // Comparación directa (sin hash, compatible con BD actual)
        if ($password === $fila['contraseña']) {
            $_SESSION['usuario']    = $fila['usuario'];
            $_SESSION['usuario_id'] = $fila['id'];
            header("Location: dashboard.php");
            exit();
        }
    }

    $error = "Usuario o contraseña incorrectos.";
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Soporte Digital VER — Login</title>
<link rel="stylesheet" href="css/estilos.css">
</head>
<body>

<div class="login-wrap">
<div class="login-box">

  <div class="login-logo">
    <span class="logo-icon">🖥️</span>
    <h1>Soporte<span>Digital</span></h1>
    <p>Sistema de Gestión de Soporte Técnico — Veracruz</p>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" autocomplete="off">

    <div class="form-group">
      <label>Usuario</label>
      <input
        type="text"
        name="usuario"
        placeholder="Ingresa tu usuario"
        required
        maxlength="80"
        value="<?= isset($_POST['usuario']) ? htmlspecialchars($_POST['usuario']) : '' ?>">
    </div>

    <div class="form-group">
      <label>Contraseña</label>
      <input
        type="password"
        name="password"
        placeholder="••••••••"
        required
        maxlength="100">
    </div>

    <button type="submit" name="login" class="btn btn-primary btn-login">
      → Ingresar
    </button>

  </form>

</div>
</div>

</body>
</html>
