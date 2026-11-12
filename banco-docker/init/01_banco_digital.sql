-- =====================================================================
-- Sistema de Gestión Bancaria Digital (simulado) - Grupo #4
-- Universidad Técnica Luis Vargas Torres de Esmeraldas | FACI | TIC
-- Asignatura: Programación V | Docente: Fabián Caicedo
-- Base de datos MySQL 8.0 (InnoDB) - según SRS IEEE 830 (sección 3.4, BD-01 a BD-08)
-- Datos ficticios: no se almacenan contraseñas, PIN, CVV ni números completos de tarjeta.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS banco_digital CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE banco_digital;

-- ---------------------------------------------------------------------
-- CLIENTES (RF-01, RF-02, RF-03)
-- ---------------------------------------------------------------------
CREATE TABLE clientes (
  id_cliente      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  identificacion  VARCHAR(13)  NOT NULL,
  nombres         VARCHAR(80)  NOT NULL,
  apellidos       VARCHAR(80)  NOT NULL,
  correo          VARCHAR(120) NOT NULL,
  telefono        VARCHAR(15)  NULL,
  direccion       VARCHAR(150) NULL,
  estado          ENUM('ACTIVO','INACTIVO','BLOQUEADO') NOT NULL DEFAULT 'ACTIVO',
  creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT uq_clientes_identificacion UNIQUE (identificacion),
  CONSTRAINT uq_clientes_correo UNIQUE (correo)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- USUARIOS (perfil local; credenciales e identidad viven en Keycloak) (RF-04, BD-06)
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
  id_usuario     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  keycloak_id    CHAR(36)    NOT NULL COMMENT 'sub (UUID) del usuario en Keycloak',
  username       VARCHAR(60) NOT NULL,
  rol            ENUM('ADMIN','ASESOR','CLIENTE') NOT NULL,
  id_cliente     INT UNSIGNED NULL,
  estado         ENUM('ACTIVO','INACTIVO','BLOQUEADO') NOT NULL DEFAULT 'ACTIVO',
  creado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_usuarios_keycloak UNIQUE (keycloak_id),
  CONSTRAINT uq_usuarios_username UNIQUE (username),
  CONSTRAINT uq_usuarios_cliente  UNIQUE (id_cliente),          -- CLIENTES 1:0..1 USUARIOS
  CONSTRAINT fk_usuarios_cliente FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- CUENTAS (RF-07, RF-08)
-- ---------------------------------------------------------------------
CREATE TABLE cuentas (
  id_cuenta      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  numero_cuenta  VARCHAR(20) NOT NULL,
  id_cliente     INT UNSIGNED NOT NULL,
  tipo           ENUM('AHORROS','CORRIENTE') NOT NULL DEFAULT 'AHORROS',
  moneda         CHAR(3) NOT NULL DEFAULT 'USD',
  saldo          DECIMAL(15,2) NOT NULL DEFAULT 0.00,           -- BD-03
  estado         ENUM('ACTIVA','BLOQUEADA','CERRADA') NOT NULL DEFAULT 'ACTIVA',
  abierta_en     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_cuentas_numero UNIQUE (numero_cuenta),
  CONSTRAINT ck_cuentas_saldo CHECK (saldo >= 0),
  CONSTRAINT fk_cuentas_cliente FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_cuentas_cliente (id_cliente)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- MOVIMIENTOS (RF-09 a RF-12; RN-01 a RN-07; BD-08)
-- ---------------------------------------------------------------------
CREATE TABLE movimientos (
  id_movimiento     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  referencia        CHAR(36) NOT NULL COMMENT 'UUID único: evita duplicados (RN-07)',
  tipo              ENUM('DEPOSITO','RETIRO','TRANSFERENCIA') NOT NULL,
  id_cuenta_origen  INT UNSIGNED NULL,
  id_cuenta_destino INT UNSIGNED NULL,
  monto             DECIMAL(15,2) NOT NULL,
  moneda            CHAR(3) NOT NULL DEFAULT 'USD',
  descripcion       VARCHAR(150) NULL,
  estado            ENUM('PENDIENTE','COMPLETADO','RECHAZADO','REVERSADO') NOT NULL DEFAULT 'PENDIENTE',
  id_usuario        INT UNSIGNED NULL COMMENT 'Usuario que ejecutó la operación',
  fecha             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_movimientos_referencia UNIQUE (referencia),
  CONSTRAINT ck_movimientos_monto CHECK (monto > 0),                                  -- RN-02
  CONSTRAINT ck_movimientos_distintas CHECK (id_cuenta_origen IS NULL OR id_cuenta_destino IS NULL
                                              OR id_cuenta_origen <> id_cuenta_destino), -- RN-05
  CONSTRAINT ck_movimientos_cuentas CHECK (
        (tipo = 'DEPOSITO'      AND id_cuenta_destino IS NOT NULL AND id_cuenta_origen IS NULL)
     OR (tipo = 'RETIRO'        AND id_cuenta_origen  IS NOT NULL AND id_cuenta_destino IS NULL)
     OR (tipo = 'TRANSFERENCIA' AND id_cuenta_origen  IS NOT NULL AND id_cuenta_destino IS NOT NULL)),
  CONSTRAINT fk_mov_origen  FOREIGN KEY (id_cuenta_origen)  REFERENCES cuentas(id_cuenta) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_mov_destino FOREIGN KEY (id_cuenta_destino) REFERENCES cuentas(id_cuenta) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_mov_usuario FOREIGN KEY (id_usuario)        REFERENCES usuarios(id_usuario) ON UPDATE CASCADE ON DELETE SET NULL,
  INDEX idx_mov_origen_fecha  (id_cuenta_origen,  fecha),
  INDEX idx_mov_destino_fecha (id_cuenta_destino, fecha)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- TARJETAS ficticias (RF-13; BD-07, RN-09): solo últimos 4 dígitos, sin CVV ni PIN
-- ---------------------------------------------------------------------
CREATE TABLE tarjetas (
  id_tarjeta     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_cuenta      INT UNSIGNED NOT NULL,
  token          CHAR(36) NOT NULL COMMENT 'Identificador ficticio de la tarjeta',
  ultimos4       CHAR(4)  NOT NULL,
  tipo           ENUM('DEBITO','CREDITO') NOT NULL DEFAULT 'DEBITO',
  titular        VARCHAR(100) NOT NULL,
  vencimiento    DATE NOT NULL,
  estado         ENUM('ACTIVA','BLOQUEADA','VENCIDA') NOT NULL DEFAULT 'ACTIVA',
  creada_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_tarjetas_token UNIQUE (token),
  CONSTRAINT fk_tarjetas_cuenta FOREIGN KEY (id_cuenta) REFERENCES cuentas(id_cuenta)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_tarjetas_cuenta (id_cuenta)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- PRÉSTAMOS simulados (RF-14, RF-15; RN-10)
-- ---------------------------------------------------------------------
CREATE TABLE prestamos (
  id_prestamo     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_cliente      INT UNSIGNED NOT NULL,
  monto           DECIMAL(15,2) NOT NULL,
  plazo_meses     SMALLINT UNSIGNED NOT NULL,
  tasa_anual      DECIMAL(5,2) NULL,
  estado          ENUM('SOLICITADO','EN_REVISION','APROBADO','RECHAZADO','CANCELADO') NOT NULL DEFAULT 'SOLICITADO',
  id_evaluador    INT UNSIGNED NULL COMMENT 'Asesor/admin que evalúa',
  observacion     VARCHAR(200) NULL,
  solicitado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resuelto_en     DATETIME NULL,
  CONSTRAINT ck_prestamos_monto CHECK (monto > 0),
  CONSTRAINT ck_prestamos_plazo CHECK (plazo_meses BETWEEN 1 AND 120),
  CONSTRAINT fk_prestamos_cliente   FOREIGN KEY (id_cliente)   REFERENCES clientes(id_cliente) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_prestamos_evaluador FOREIGN KEY (id_evaluador) REFERENCES usuarios(id_usuario) ON UPDATE CASCADE ON DELETE SET NULL,
  INDEX idx_prestamos_cliente (id_cliente)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- BD-08: los movimientos completados no se pueden eliminar físicamente
-- ---------------------------------------------------------------------
DELIMITER $$
CREATE TRIGGER trg_movimientos_no_delete
BEFORE DELETE ON movimientos FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Los movimientos no se eliminan; use estado REVERSADO';
END$$

-- ---------------------------------------------------------------------
-- Transferencia atómica (RF-11, RN-01, RN-03, RN-04, RN-06): una sola transacción
-- ---------------------------------------------------------------------
CREATE PROCEDURE sp_transferir(
  IN p_origen INT UNSIGNED, IN p_destino INT UNSIGNED, IN p_monto DECIMAL(15,2),
  IN p_referencia CHAR(36), IN p_usuario INT UNSIGNED, IN p_desc VARCHAR(150))
BEGIN
  DECLARE v_saldo DECIMAL(15,2); DECLARE v_est_o VARCHAR(10); DECLARE v_est_d VARCHAR(10);
  DECLARE v_mon_o CHAR(3); DECLARE v_mon_d CHAR(3);
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

  IF p_origen = p_destino THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se puede transferir a la misma cuenta'; END IF;
  IF p_monto <= 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El monto debe ser mayor que cero'; END IF;

  START TRANSACTION;
  -- Bloqueo en orden de id para evitar deadlocks
  SELECT saldo, estado, moneda INTO v_saldo, v_est_o, v_mon_o FROM cuentas
    WHERE id_cuenta = p_origen FOR UPDATE;
  SELECT estado, moneda INTO v_est_d, v_mon_d FROM cuentas
    WHERE id_cuenta = p_destino FOR UPDATE;

  IF v_est_o <> 'ACTIVA' OR v_est_d <> 'ACTIVA' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cuenta bloqueada o cerrada'; END IF;
  IF v_mon_o <> v_mon_d THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Monedas distintas: sin conversión automática'; END IF;
  IF v_saldo < p_monto  THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Saldo insuficiente'; END IF;

  UPDATE cuentas SET saldo = saldo - p_monto WHERE id_cuenta = p_origen;
  UPDATE cuentas SET saldo = saldo + p_monto WHERE id_cuenta = p_destino;
  INSERT INTO movimientos (referencia,tipo,id_cuenta_origen,id_cuenta_destino,monto,moneda,descripcion,estado,id_usuario)
    VALUES (p_referencia,'TRANSFERENCIA',p_origen,p_destino,p_monto,v_mon_o,p_desc,'COMPLETADO',p_usuario);
  COMMIT;
END$$
DELIMITER ;

-- ---------------------------------------------------------------------
-- DATOS FICTICIOS DE PRUEBA
-- ---------------------------------------------------------------------
INSERT INTO clientes (identificacion,nombres,apellidos,correo,telefono) VALUES
 ('0801000001','Axel','Rosero','axel.rosero@ejemplo.test','0990000001'),
 ('0801000002','Melany','Zambrano','melany.zambrano@ejemplo.test','0990000002'),
 ('0801000003','Jordan','Solis','jordan.solis@ejemplo.test','0990000003');

INSERT INTO usuarios (keycloak_id,username,rol,id_cliente) VALUES
 ('00000000-0000-0000-0000-000000000001','admin','ADMIN',NULL),
 ('00000000-0000-0000-0000-000000000002','asesor1','ASESOR',NULL),
 ('00000000-0000-0000-0000-000000000003','axel.rosero','CLIENTE',1),
 ('00000000-0000-0000-0000-000000000004','melany.zambrano','CLIENTE',2);

INSERT INTO cuentas (numero_cuenta,id_cliente,tipo,saldo) VALUES
 ('2200000001',1,'AHORROS',500.00),
 ('2200000002',2,'AHORROS',250.00),
 ('2200000003',3,'CORRIENTE',1000.00);

INSERT INTO tarjetas (id_cuenta,token,ultimos4,titular,vencimiento) VALUES
 (1,'11111111-1111-1111-1111-111111111111','4242','AXEL ROSERO','2029-12-31');

INSERT INTO prestamos (id_cliente,monto,plazo_meses) VALUES (2,1500.00,12);

CALL sp_transferir(1,2,50.00,'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',3,'Transferencia de prueba');

-- Vista útil para el historial por cuenta (RF-12)
CREATE VIEW v_historial AS
SELECT m.id_movimiento, m.referencia, m.tipo, c.numero_cuenta AS cuenta,
       m.monto, m.moneda, m.estado, m.fecha
FROM movimientos m
JOIN cuentas c ON c.id_cuenta IN (m.id_cuenta_origen, m.id_cuenta_destino);
