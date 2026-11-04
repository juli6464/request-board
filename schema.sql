-- =============================================================================
--  Tablero de solicitudes con flujo de estados — esquema + datos de prueba
--  Motor: MySQL 8.x (compatible con MariaDB 10.4+ de XAMPP)
--  Importar:  mysql -u root -p < schema.sql      (o phpMyAdmin > Importar)
-- =============================================================================

CREATE DATABASE IF NOT EXISTS request_board
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE request_board;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS application_status_logs;
DROP TABLE IF EXISTS applications;
DROP TABLE IF EXISTS statuses;
SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------------------------
-- Catálogo de estados.
-- La PK es el código string (clave natural): la API y las FKs usan el mismo
-- identificador estable ('in_review'), nunca un ID autoincremental que
-- cambiaría entre entornos.
-- -----------------------------------------------------------------------------
CREATE TABLE statuses (
  code        VARCHAR(50)      NOT NULL,
  name        VARCHAR(100)     NOT NULL,
  sort_order  TINYINT UNSIGNED NOT NULL,
  is_final    TINYINT(1)       NOT NULL DEFAULT 0,
  PRIMARY KEY (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO statuses (code, name, sort_order, is_final) VALUES
  ('created',     'Creada',      1, 0),
  ('in_review',   'En revisión', 2, 0),
  ('in_progress', 'En progreso', 3, 0),
  ('paused',      'Pausada',     4, 0),
  ('done',        'Terminada',   5, 1);

-- -----------------------------------------------------------------------------
-- Solicitudes.
-- `status` es FK al catálogo: la BD rechaza cualquier código inexistente.
-- Las reglas de transición (qué estado sigue a cuál) viven en el dominio
-- (App\Enums\ApplicationStatus), no en triggers.
-- -----------------------------------------------------------------------------
CREATE TABLE applications (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title       VARCHAR(255)    NOT NULL,
  description TEXT            NULL,
  status      VARCHAR(50)     NOT NULL DEFAULT 'created',
  created_at  TIMESTAMP       NULL DEFAULT NULL,
  updated_at  TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY applications_status_created_at_index (status, created_at),
  CONSTRAINT applications_status_foreign
    FOREIGN KEY (status) REFERENCES statuses (code)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Historial de transiciones (auditoría, solo inserción).
-- -----------------------------------------------------------------------------
CREATE TABLE application_status_logs (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  application_id BIGINT UNSIGNED NOT NULL,
  from_status    VARCHAR(50)     NOT NULL,
  to_status      VARCHAR(50)     NOT NULL,
  created_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY application_status_logs_application_id_foreign (application_id),
  CONSTRAINT application_status_logs_application_id_foreign
    FOREIGN KEY (application_id) REFERENCES applications (id) ON DELETE CASCADE,
  CONSTRAINT application_status_logs_from_status_foreign
    FOREIGN KEY (from_status) REFERENCES statuses (code) ON UPDATE CASCADE,
  CONSTRAINT application_status_logs_to_status_foreign
    FOREIGN KEY (to_status) REFERENCES statuses (code) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Datos de prueba: al menos una solicitud en cada estado.
-- -----------------------------------------------------------------------------
INSERT INTO applications (id, title, description, status, created_at, updated_at) VALUES
  (1, 'Alta de proveedor de mensajería',   'Registrar al nuevo proveedor de envíos y validar documentación fiscal.',     'created',     '2026-10-28 09:15:00', '2026-10-28 09:15:00'),
  (2, 'Acceso VPN para equipo de soporte', 'Crear perfiles VPN para los 4 agentes que entran el lunes.',                 'created',     '2026-11-03 14:02:00', '2026-11-03 14:02:00'),
  (3, 'Compra de licencias de diseño',     'Renovar 6 licencias anuales antes del vencimiento del día 15.',              'in_review',   '2026-10-25 11:30:00', '2026-10-27 10:00:00'),
  (4, 'Migración del CRM a la nube',       'Mover el CRM on-premise al proveedor cloud elegido en el comité.',           'in_progress', '2026-10-10 08:00:00', '2026-10-20 09:30:00'),
  (5, 'Rediseño del onboarding',           'Actualizar el flujo de bienvenida de clientes con el nuevo branding.',       'in_progress', '2026-10-18 16:45:00', '2026-10-24 12:00:00'),
  (6, 'Auditoría de permisos en ERP',      'Revisar roles con privilegios elevados. Bloqueado esperando a seguridad.',   'paused',      '2026-10-05 10:20:00', '2026-10-15 17:40:00'),
  (7, 'Integración con pasarela de pagos', 'Pendiente de credenciales de producción del proveedor.',                     'paused',      '2026-10-12 13:10:00', '2026-10-22 11:05:00'),
  (8, 'Reporte mensual de ventas',         'Automatizar el envío del reporte a gerencia el primer día hábil.',           'done',        '2026-09-30 09:00:00', '2026-10-08 15:20:00');

-- Historial coherente con el estado actual de cada solicitud.
INSERT INTO application_status_logs (application_id, from_status, to_status, created_at) VALUES
  (3, 'created',     'in_review',   '2026-10-27 10:00:00'),
  (4, 'created',     'in_review',   '2026-10-13 09:00:00'),
  (4, 'in_review',   'in_progress', '2026-10-20 09:30:00'),
  (5, 'created',     'in_review',   '2026-10-21 08:15:00'),
  (5, 'in_review',   'in_progress', '2026-10-24 12:00:00'),
  (6, 'created',     'in_review',   '2026-10-07 11:00:00'),
  (6, 'in_review',   'paused',      '2026-10-15 17:40:00'),
  (7, 'created',     'in_review',   '2026-10-14 10:00:00'),
  (7, 'in_review',   'in_progress', '2026-10-17 16:30:00'),
  (7, 'in_progress', 'paused',      '2026-10-22 11:05:00'),
  (8, 'created',     'in_review',   '2026-10-01 09:00:00'),
  (8, 'in_review',   'in_progress', '2026-10-02 10:00:00'),
  (8, 'in_progress', 'paused',      '2026-10-03 18:00:00'),
  (8, 'paused',      'in_progress', '2026-10-06 08:30:00'),
  (8, 'in_progress', 'done',        '2026-10-08 15:20:00');
