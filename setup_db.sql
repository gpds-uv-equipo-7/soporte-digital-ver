-- =====================================================
--  SOPORTE DIGITAL VER — Script de actualización BD
--  Ejecutar en phpMyAdmin sobre: soporte_digital_ver
-- =====================================================

-- 1) Agregar columna "correo" a usuarios (arregla el error fatal)
ALTER TABLE usuarios
  ADD COLUMN IF NOT EXISTS correo VARCHAR(150) NOT NULL DEFAULT '' AFTER contraseña;

-- 2) Agregar columna "rol" a usuarios (si aún no existe)
ALTER TABLE usuarios
  ADD COLUMN IF NOT EXISTS rol VARCHAR(50) NOT NULL DEFAULT 'tecnico' AFTER correo;

-- 3) Agregar columna "reporte_tecnico" a tickets (RF-03)
ALTER TABLE tickets
  ADD COLUMN IF NOT EXISTS reporte_tecnico TEXT NULL DEFAULT NULL AFTER descripcion;

-- 4) Tabla de agenda / servicios programados (RF-04)
CREATE TABLE IF NOT EXISTS agenda (
  id            INT(11)      NOT NULL AUTO_INCREMENT,
  cliente_id    INT(11)      NOT NULL,
  tecnico_id    INT(11)      NOT NULL,
  fecha_hora    DATETIME     NOT NULL,
  tipo_servicio VARCHAR(100) NOT NULL DEFAULT 'Visita a domicilio',
  notas         TEXT         NULL,
  estado        VARCHAR(30)  NOT NULL DEFAULT 'Programado',
  PRIMARY KEY (id),
  KEY fk_agenda_cliente (cliente_id),
  KEY fk_agenda_tecnico (tecnico_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5) Tabla de notificaciones simuladas (RF-08)
CREATE TABLE IF NOT EXISTS notificaciones (
  id          INT(11)     NOT NULL AUTO_INCREMENT,
  ticket_id   INT(11)     NOT NULL,
  cliente_id  INT(11)     NOT NULL,
  mensaje     TEXT        NOT NULL,
  estado_nuevo VARCHAR(50) NOT NULL,
  fecha_envio DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY fk_notif_ticket  (ticket_id),
  KEY fk_notif_cliente (cliente_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
