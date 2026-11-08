-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Servidor: mysql
-- Tiempo de generación: 05-11-2026 a las 00:54:12
-- Versión del servidor: 8.4.13
-- Versión de PHP: 8.3.36

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `banco_digital`
--

DELIMITER $$
--
-- Procedimientos
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_transferir` (IN `p_origen` INT UNSIGNED, IN `p_destino` INT UNSIGNED, IN `p_monto` DECIMAL(15,2), IN `p_referencia` CHAR(36), IN `p_usuario` INT UNSIGNED, IN `p_desc` VARCHAR(150))   BEGIN
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
  IF v_mon_o <> v_mon_d THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Monedas distintas: sin conversiÃ³n automÃ¡tica'; END IF;
  IF v_saldo < p_monto  THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Saldo insuficiente'; END IF;

  UPDATE cuentas SET saldo = saldo - p_monto WHERE id_cuenta = p_origen;
  UPDATE cuentas SET saldo = saldo + p_monto WHERE id_cuenta = p_destino;
  INSERT INTO movimientos (referencia,tipo,id_cuenta_origen,id_cuenta_destino,monto,moneda,descripcion,estado,id_usuario)
    VALUES (p_referencia,'TRANSFERENCIA',p_origen,p_destino,p_monto,v_mon_o,p_desc,'COMPLETADO',p_usuario);
  COMMIT;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `id_cliente` int UNSIGNED NOT NULL,
  `identificacion` varchar(13) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombres` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellidos` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `correo` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telefono` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` enum('ACTIVO','INACTIVO','BLOQUEADO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVO',
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `clientes`
--

INSERT INTO `clientes` (`id_cliente`, `identificacion`, `nombres`, `apellidos`, `correo`, `telefono`, `direccion`, `estado`, `creado_en`, `actualizado_en`) VALUES
(1, '0801000001', 'Axel', 'Rosero', 'axel.rosero@ejemplo.test', '0990000001', NULL, 'ACTIVO', '2026-11-04 19:34:50', '2026-11-04 19:34:50'),
(2, '0801000002', 'Melany', 'Zambrano', 'melany.zambrano@ejemplo.test', '0990000002', NULL, 'ACTIVO', '2026-11-04 19:34:50', '2026-11-04 19:34:50'),
(3, '0801000003', 'Jordan', 'Solis', 'jordan.solis@ejemplo.test', '0990000003', NULL, 'ACTIVO', '2026-11-04 19:34:50', '2026-11-04 19:34:50');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cuentas`
--

CREATE TABLE `cuentas` (
  `id_cuenta` int UNSIGNED NOT NULL,
  `numero_cuenta` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_cliente` int UNSIGNED NOT NULL,
  `tipo` enum('AHORROS','CORRIENTE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'AHORROS',
  `moneda` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `saldo` decimal(15,2) NOT NULL DEFAULT '0.00',
  `estado` enum('ACTIVA','BLOQUEADA','CERRADA') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVA',
  `abierta_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ;

--
-- Volcado de datos para la tabla `cuentas`
--

INSERT INTO `cuentas` (`id_cuenta`, `numero_cuenta`, `id_cliente`, `tipo`, `moneda`, `saldo`, `estado`, `abierta_en`) VALUES
(1, '2200000001', 1, 'AHORROS', 'USD', 450.00, 'ACTIVA', '2026-11-04 19:34:50'),
(2, '2200000002', 2, 'AHORROS', 'USD', 300.00, 'ACTIVA', '2026-11-04 19:34:50'),
(3, '2200000003', 3, 'CORRIENTE', 'USD', 1000.00, 'ACTIVA', '2026-11-04 19:34:50');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `movimientos`
--

CREATE TABLE `movimientos` (
  `id_movimiento` bigint UNSIGNED NOT NULL,
  `referencia` char(36) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'UUID Ãºnico: evita duplicados (RN-07)',
  `tipo` enum('DEPOSITO','RETIRO','TRANSFERENCIA') COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_cuenta_origen` int UNSIGNED DEFAULT NULL,
  `id_cuenta_destino` int UNSIGNED DEFAULT NULL,
  `monto` decimal(15,2) NOT NULL,
  `moneda` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `descripcion` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` enum('PENDIENTE','COMPLETADO','RECHAZADO','REVERSADO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE',
  `id_usuario` int UNSIGNED DEFAULT NULL COMMENT 'Usuario que ejecutÃ³ la operaciÃ³n',
  `fecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ;

--
-- Volcado de datos para la tabla `movimientos`
--

INSERT INTO `movimientos` (`id_movimiento`, `referencia`, `tipo`, `id_cuenta_origen`, `id_cuenta_destino`, `monto`, `moneda`, `descripcion`, `estado`, `id_usuario`, `fecha`) VALUES
(1, 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 'TRANSFERENCIA', 1, 2, 50.00, 'USD', 'Transferencia de prueba', 'COMPLETADO', 3, '2026-11-04 19:34:50');

--
-- Disparadores `movimientos`
--
DELIMITER $$
CREATE TRIGGER `trg_movimientos_no_delete` BEFORE DELETE ON `movimientos` FOR EACH ROW BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Los movimientos no se eliminan; use estado REVERSADO';
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `prestamos`
--

CREATE TABLE `prestamos` (
  `id_prestamo` int UNSIGNED NOT NULL,
  `id_cliente` int UNSIGNED NOT NULL,
  `monto` decimal(15,2) NOT NULL,
  `plazo_meses` smallint UNSIGNED NOT NULL,
  `tasa_anual` decimal(5,2) DEFAULT NULL,
  `estado` enum('SOLICITADO','EN_REVISION','APROBADO','RECHAZADO','CANCELADO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SOLICITADO',
  `id_evaluador` int UNSIGNED DEFAULT NULL COMMENT 'Asesor/admin que evalÃºa',
  `observacion` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `solicitado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resuelto_en` datetime DEFAULT NULL
) ;

--
-- Volcado de datos para la tabla `prestamos`
--

INSERT INTO `prestamos` (`id_prestamo`, `id_cliente`, `monto`, `plazo_meses`, `tasa_anual`, `estado`, `id_evaluador`, `observacion`, `solicitado_en`, `resuelto_en`) VALUES
(1, 2, 1500.00, 12, NULL, 'SOLICITADO', NULL, NULL, '2026-11-04 19:34:50', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tarjetas`
--

CREATE TABLE `tarjetas` (
  `id_tarjeta` int UNSIGNED NOT NULL,
  `id_cuenta` int UNSIGNED NOT NULL,
  `token` char(36) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Identificador ficticio de la tarjeta',
  `ultimos4` char(4) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('DEBITO','CREDITO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DEBITO',
  `titular` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vencimiento` date NOT NULL,
  `estado` enum('ACTIVA','BLOQUEADA','VENCIDA') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVA',
  `creada_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `tarjetas`
--

INSERT INTO `tarjetas` (`id_tarjeta`, `id_cuenta`, `token`, `ultimos4`, `tipo`, `titular`, `vencimiento`, `estado`, `creada_en`) VALUES
(1, 1, '11111111-1111-1111-1111-111111111111', '4242', 'DEBITO', 'AXEL ROSERO', '2029-12-31', 'ACTIVA', '2026-11-04 19:34:50');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int UNSIGNED NOT NULL,
  `keycloak_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'sub (UUID) del usuario en Keycloak',
  `username` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rol` enum('ADMIN','ASESOR','CLIENTE') COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_cliente` int UNSIGNED DEFAULT NULL,
  `estado` enum('ACTIVO','INACTIVO','BLOQUEADO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVO',
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `keycloak_id`, `username`, `rol`, `id_cliente`, `estado`, `creado_en`) VALUES
(1, '00000000-0000-0000-0000-000000000001', 'admin', 'ADMIN', NULL, 'ACTIVO', '2026-11-04 19:34:50'),
(2, '00000000-0000-0000-0000-000000000002', 'asesor1', 'ASESOR', NULL, 'ACTIVO', '2026-11-04 19:34:50'),
(3, '00000000-0000-0000-0000-000000000003', 'axel.rosero', 'CLIENTE', 1, 'ACTIVO', '2026-11-04 19:34:50'),
(4, '00000000-0000-0000-0000-000000000004', 'melany.zambrano', 'CLIENTE', 2, 'ACTIVO', '2026-11-04 19:34:50');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_historial`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_historial` (
`id_movimiento` bigint unsigned
,`referencia` char(36)
,`tipo` enum('DEPOSITO','RETIRO','TRANSFERENCIA')
,`cuenta` varchar(20)
,`monto` decimal(15,2)
,`moneda` char(3)
,`estado` enum('PENDIENTE','COMPLETADO','RECHAZADO','REVERSADO')
,`fecha` datetime
);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id_cliente`),
  ADD UNIQUE KEY `uq_clientes_identificacion` (`identificacion`),
  ADD UNIQUE KEY `uq_clientes_correo` (`correo`);

--
-- Indices de la tabla `cuentas`
--
ALTER TABLE `cuentas`
  ADD PRIMARY KEY (`id_cuenta`),
  ADD UNIQUE KEY `uq_cuentas_numero` (`numero_cuenta`),
  ADD KEY `idx_cuentas_cliente` (`id_cliente`);

--
-- Indices de la tabla `movimientos`
--
ALTER TABLE `movimientos`
  ADD PRIMARY KEY (`id_movimiento`),
  ADD UNIQUE KEY `uq_movimientos_referencia` (`referencia`),
  ADD KEY `fk_mov_usuario` (`id_usuario`),
  ADD KEY `idx_mov_origen_fecha` (`id_cuenta_origen`,`fecha`),
  ADD KEY `idx_mov_destino_fecha` (`id_cuenta_destino`,`fecha`);

--
-- Indices de la tabla `prestamos`
--
ALTER TABLE `prestamos`
  ADD PRIMARY KEY (`id_prestamo`),
  ADD KEY `fk_prestamos_evaluador` (`id_evaluador`),
  ADD KEY `idx_prestamos_cliente` (`id_cliente`);

--
-- Indices de la tabla `tarjetas`
--
ALTER TABLE `tarjetas`
  ADD PRIMARY KEY (`id_tarjeta`),
  ADD UNIQUE KEY `uq_tarjetas_token` (`token`),
  ADD KEY `idx_tarjetas_cuenta` (`id_cuenta`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `uq_usuarios_keycloak` (`keycloak_id`),
  ADD UNIQUE KEY `uq_usuarios_username` (`username`),
  ADD UNIQUE KEY `uq_usuarios_cliente` (`id_cliente`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id_cliente` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `cuentas`
--
ALTER TABLE `cuentas`
  MODIFY `id_cuenta` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `movimientos`
--
ALTER TABLE `movimientos`
  MODIFY `id_movimiento` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `prestamos`
--
ALTER TABLE `prestamos`
  MODIFY `id_prestamo` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tarjetas`
--
ALTER TABLE `tarjetas`
  MODIFY `id_tarjeta` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_historial`
--
DROP TABLE IF EXISTS `v_historial`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_historial`  AS SELECT `m`.`id_movimiento` AS `id_movimiento`, `m`.`referencia` AS `referencia`, `m`.`tipo` AS `tipo`, `c`.`numero_cuenta` AS `cuenta`, `m`.`monto` AS `monto`, `m`.`moneda` AS `moneda`, `m`.`estado` AS `estado`, `m`.`fecha` AS `fecha` FROM (`movimientos` `m` join `cuentas` `c` on((`c`.`id_cuenta` in (`m`.`id_cuenta_origen`,`m`.`id_cuenta_destino`)))) ;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `cuentas`
--
ALTER TABLE `cuentas`
  ADD CONSTRAINT `fk_cuentas_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Filtros para la tabla `movimientos`
--
ALTER TABLE `movimientos`
  ADD CONSTRAINT `fk_mov_destino` FOREIGN KEY (`id_cuenta_destino`) REFERENCES `cuentas` (`id_cuenta`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `fk_mov_origen` FOREIGN KEY (`id_cuenta_origen`) REFERENCES `cuentas` (`id_cuenta`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `fk_mov_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `prestamos`
--
ALTER TABLE `prestamos`
  ADD CONSTRAINT `fk_prestamos_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_prestamos_evaluador` FOREIGN KEY (`id_evaluador`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `tarjetas`
--
ALTER TABLE `tarjetas`
  ADD CONSTRAINT `fk_tarjetas_cuenta` FOREIGN KEY (`id_cuenta`) REFERENCES `cuentas` (`id_cuenta`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuarios_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`) ON DELETE RESTRICT ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
