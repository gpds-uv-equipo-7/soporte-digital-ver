<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

include("conexion.php");

// Función estricta para validar texto sin caracteres especiales (solo letras, números, espacios y acentos)
function esTextoValido($txt) {
    return preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ0-9\s,.]+$/u', $txt);
}

$mensaje    = "";
$error      = "";
$modo_editar = false;
$id_editar  = 0;

// Inicializamos los nuevos campos para el formulario
$d = [
    'nombre' => '', 'telefono' => '', 'correo' => '',
    'calle' => '', 'num_casa' => '', 'cp' => '', 'colonia' => '', 'ciudad' => ''
];

/* ── GUARDAR ── */
if (isset($_POST['guardar'])) {
    $nombre    = trim($_POST['nombre']);
    $telefono  = trim($_POST['telefono']);
    $correo    = trim($_POST['correo']);
    
    // Nuevos campos de dirección
    $calle     = trim($_POST['calle']);
    $num_casa  = trim($_POST['num_casa']);
    $cp        = trim($_POST['cp']);
    $colonia   = trim($_POST['colonia']);
    $ciudad    = trim($_POST['ciudad']);

    // Guardar temporalmente en el array por si hay error
    $d = $_POST;

    if (empty($nombre) || empty($telefono) || empty($correo) || empty($calle) || empty($num_casa) || empty($cp) || empty($colonia) || empty($ciudad)) {
        $error = "Todos los campos son obligatorios.";
    } elseif (!preg_match('/^[a-záéíóúüñA-ZÁÉÍÓÚÜÑ\s]+$/u', $nombre)) {
        $error = "No se permiten caracteres especiales en el nombre.";
    } elseif (strlen($nombre) < 2) {
        $error = "El nombre debe tener al menos 2 caracteres.";
    } elseif (!preg_match('/^[0-9]{10}$/', $telefono)) {
        $error = "El teléfono debe tener exactamente 10 dígitos numéricos.";
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = "El correo electrónico no es válido.";
    } // VALIDACIONES DE DIRECCIÓN PEDIDAS POR EL PROFESOR
    elseif (!preg_match('/^[0-9]{1,4}$/', $num_casa)) {
        $error = "El número de casa debe ser solo números (máximo 4 dígitos).";
    } elseif (!preg_match('/^[0-9]{5}$/', $cp)) {
        $error = "El código postal debe tener exactamente 5 números.";
    } elseif (!esTextoValido($calle) || !esTextoValido($colonia) || !esTextoValido($ciudad)) {
        $error = "No se permiten caracteres especiales en los campos de texto de la dirección.";
    } else {
        // Concatenamos la dirección ordenada de manera legible
        $direccion_ordenada = "Calle: $calle, No. $num_casa, C.P. $cp, Col. $colonia, $ciudad";

        $chk = $conexion->prepare("SELECT id FROM clientes WHERE correo = ?");
        $chk->bind_param("s", $correo);
        $chk->execute();
        $chk->store_result();

        if ($chk->num_rows > 0) {
            $error = "Ese correo ya está registrado.";
        } else {
            $stmt = $conexion->prepare("INSERT INTO clientes (nombre, telefono, direccion, correo) VALUES (?,?,?,?)");
            $stmt->bind_param("ssss", $nombre, $telefono, $direccion_ordenada, $correo);
            $stmt->execute();
            $stmt->close();
            $mensaje = "Cliente registrado correctamente.";
            // Limpiar formulario
            $d = ['nombre'=>'','telefono'=>'','correo'=>'','calle'=>'','num_casa'=>'','cp'=>'','colonia'=>'','ciudad'=>''];
        }
        $chk->close();
    }
}

/* ── CARGAR PARA EDITAR ── */
if (isset($_GET['editar'])) {
    $id_editar   = (int) $_GET['editar'];
    $modo_editar = true;

    $stmt = $conexion->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt->bind_param("i", $id_editar);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($fila = $res->fetch_assoc()) {
        $d = $fila;
        // Intentar separar la dirección guardada para repoblar los campos individuales en la edición
        if (preg_match('/Calle: (.*), No. (.*), C.P. (.*), Col. (.*), (.*)/', $fila['direccion'], $coincidencias)) {
            $d['calle']    = $coincidencias[1];
            $d['num_casa'] = $coincidencias[2];
            $d['cp']       = $coincidencias[3];
            $d['colonia']  = $coincidencias[4];
            $d['ciudad']   = $coincidencias[5];
        } else {
            // Si el formato es viejo, ponemos todo en el campo calle
            $d['calle']    = $fila['direccion'];
            $d['num_casa'] = ''; $d['cp'] = ''; $d['colonia'] = ''; $d['ciudad'] = '';
        }
    }
    $stmt->close();
}

/* ── ACTUALIZAR ── */
if (isset($_POST['actualizar'])) {
    $id        = (int) $_POST['id'];
    $nombre    = trim($_POST['nombre']);
    $telefono  = trim($_POST['telefono']);
    $correo    = trim($_POST['correo']);
    
    $calle     = trim($_POST['calle']);
    $num_casa  = trim($_POST['num_casa']);
    $cp        = trim($_POST['cp']);
    $colonia   = trim($_POST['colonia']);
    $ciudad    = trim($_POST['ciudad']);

    if (empty($nombre) || empty($telefono) || empty($correo) || empty($calle) || empty($num_casa) || empty($cp) || empty($colonia) || empty($ciudad)) {
        $error = "Todos los campos son obligatorios.";
        $modo_editar = true; $id_editar = $id; $d = $_POST;
    } elseif (!preg_match('/^[a-záéíóúüñA-ZÁÉÍÓÚÜÑ\s]+$/u', $nombre)) {
        $error = "No se permiten caracteres especiales en el nombre.";
        $modo_editar = true; $id_editar = $id; $d = $_POST;
    } elseif (!preg_match('/^[0-9]{10}$/', $telefono)) {
        $error = "El teléfono debe tener exactamente 10 dígitos.";
        $modo_editar = true; $id_editar = $id; $d = $_POST;
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = "El correo no es válido.";
        $modo_editar = true; $id_editar = $id; $d = $_POST;
    } // VALIDACIONES DE DIRECCIÓN EN ACTUALIZAR
    elseif (!preg_match('/^[0-9]{1,4}$/', $num_casa)) {
        $error = "El número de casa debe ser solo números (máximo 4 dígitos).";
        $modo_editar = true; $id_editar = $id; $d = $_POST;
    } elseif (!preg_match('/^[0-9]{5}$/', $cp)) {
        $error = "El código postal debe tener exactamente 5 números.";
        $modo_editar = true; $id_editar = $id; $d = $_POST;
    } elseif (!esTextoValido($calle) || !esTextoValido($colonia) || !esTextoValido($ciudad)) {
        $error = "No se permiten caracteres especiales en la dirección.";
        $modo_editar = true; $id_editar = $id; $d = $_POST;
    } else {
        $direccion_ordenada = "Calle: $calle, No. $num_casa, C.P. $cp, Col. $colonia, $ciudad";

        $chk = $conexion->prepare("SELECT id FROM clientes WHERE correo = ? AND id != ?");
        $chk->bind_param("si", $correo, $id);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) {
            $error = "Ese correo ya está registrado en otro cliente.";
            $modo_editar = true; $id_editar = $id; $d = $_POST;
        } else {
            $stmt = $conexion->prepare("UPDATE clientes SET nombre=?, telefono=?, direccion=?, correo=? WHERE id=?");
            $stmt->bind_param("ssssi", $nombre, $telefono, $direccion_ordenada, $correo, $id);
            $stmt->execute();
            $stmt->close();
            header("Location: clientes.php?ok=1");
            exit();
        }
        $chk->close();
    }
}

/* ── ELIMINAR ── */
if (isset($_GET['eliminar'])) {
    $id = (int) $_GET['eliminar'];

    $chk = $conexion->prepare("SELECT COUNT(*) AS n FROM tickets WHERE cliente_id = ?");
    $chk->bind_param("i", $id);
    $chk->execute();
    $n = $chk->get_result()->fetch_assoc()['n'];
    $chk->close();

    if ($n > 0) {
        $error = "No se puede eliminar: el cliente tiene $n ticket(s) asociado(s).";
    } else {
        $stmt = $conexion->prepare("DELETE FROM clientes WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        header("Location: clientes.php?ok=2");
        exit();
    }
}

if (isset($_GET['ok'])) {
    $mensaje = $_GET['ok'] == 1 ? "Cliente actualizado." : "Cliente eliminado.";
}

$resultado = $conexion->query("SELECT * FROM clientes ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Clientes — Soporte Digital VER</title>
<link rel="stylesheet" href="css/estilos.css">
<script>
// Validaciones en tiempo real desde el navegador
function soloNumeros(input, maxLen) {
    input.value = input.value.replace(/[^0-9]/g, '');
    if(input.value.length > maxLen) {
        input.value = input.value.slice(0, maxLen);
    }
}

function textoSinEspeciales(input) {
    input.value = input.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ0-9\s]/g, '');
}
</script>
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

  <div class="page-title"><span>👥</span> Clientes</div>

  <div class="card">
    <h2><?= $modo_editar ? '✏️ Editar Cliente' : '➕ Registrar Cliente' ?></h2>

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
          <input type="text" name="nombre" placeholder="Ej. Juan Pérez"
            value="<?= htmlspecialchars($d['nombre']) ?>" required maxlength="120">
        </div>
        <div class="form-group">
          <label>Teléfono (10 dígitos)</label>
          <input type="text" name="telefono" placeholder="2291234567"
            value="<?= htmlspecialchars($d['telefono']) ?>" required maxlength="10" pattern="[0-9]{10}" oninput="soloNumeros(this, 10)">
        </div>
        <div class="form-group">
          <label>Correo electrónico</label>
          <input type="email" name="correo" placeholder="correo@ejemplo.com"
            value="<?= htmlspecialchars($d['correo']) ?>" required maxlength="150">
        </div>

        <div class="form-group">
          <label>Calle</label>
          <input type="text" name="calle" placeholder="Av. Ejército Mexicano"
            value="<?= htmlspecialchars($d['calle'] ?? '') ?>" required oninput="textoSinEspeciales(this)">
        </div>
        <div class="form-group">
          <label>Número de Casa (Máx 4 dígitos)</label>
          <input type="text" name="num_casa" placeholder="123"
            value="<?= htmlspecialchars($d['num_casa'] ?? '') ?>" required oninput="soloNumeros(this, 4)">
        </div>
        <div class="form-group">
          <label>Código Postal (5 dígitos)</label>
          <input type="text" name="cp" placeholder="91700"
            value="<?= htmlspecialchars($d['cp'] ?? '') ?>" required pattern="[0-9]{5}" oninput="soloNumeros(this, 5)">
        </div>
        <div class="form-group">
          <label>Colonia</label>
          <input type="text" name="colonia" placeholder="Centro"
            value="<?= htmlspecialchars($d['colonia'] ?? '') ?>" required oninput="textoSinEspeciales(this)">
        </div>
        <div class="form-group">
          <label>Ciudad</label>
          <input type="text" name="ciudad" placeholder="Veracruz"
            value="<?= htmlspecialchars($d['ciudad'] ?? '') ?>" required oninput="textoSinEspeciales(this)">
        </div>
      </div>

      <div class="form-actions">
        <?php if ($modo_editar): ?>
          <button type="submit" name="actualizar" class="btn btn-primary">💾 Actualizar</button>
          <a href="clientes.php" class="btn btn-secondary">✖ Cancelar</a>
        <?php else: ?>
          <button type="submit" name="guardar" class="btn btn-primary">➕ Guardar Cliente</button>
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
          <th>Dirección Estructurada</th>
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
          <td><strong><?= htmlspecialchars($fila['direccion']) ?></strong></td>
          <td><?= htmlspecialchars($fila['correo']) ?></td>
          <td style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="historial_cliente.php?id=<?= (int)$fila['id'] ?>" class="btn btn-secondary btn-sm">📋 Historial</a>
            <a href="clientes.php?editar=<?= (int)$fila['id'] ?>" class="btn btn-warning btn-sm">✏️ Editar</a>
            <a href="clientes.php?eliminar=<?= (int)$fila['id'] ?>"
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