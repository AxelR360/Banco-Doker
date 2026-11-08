-- MySQL dump 10.13  Distrib 8.4.13, for Linux (x86_64)
--
-- Host: localhost    Database: banco_digital
-- ------------------------------------------------------
-- Server version	8.4.13

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `clientes`
--

DROP TABLE IF EXISTS `clientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clientes` (
  `id_cliente` int unsigned NOT NULL AUTO_INCREMENT,
  `identificacion` varchar(13) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombres` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellidos` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `correo` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telefono` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` enum('ACTIVO','INACTIVO','BLOQUEADO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVO',
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_cliente`),
  UNIQUE KEY `uq_clientes_identificacion` (`identificacion`),
  UNIQUE KEY `uq_clientes_correo` (`correo`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clientes`
--

LOCK TABLES `clientes` WRITE;
/*!40000 ALTER TABLE `clientes` DISABLE KEYS */;
INSERT INTO `clientes` VALUES (1,'0801000001','Axel','Rosero','axel.rosero@ejemplo.test','0990000001',NULL,'ACTIVO','2026-11-04 19:34:50','2026-11-04 19:34:50'),(2,'0801000002','Melany','Zambrano','melany.zambrano@ejemplo.test','0990000002',NULL,'ACTIVO','2026-11-04 19:34:50','2026-11-04 19:34:50'),(3,'0801000003','Jordan','Solis','jordan.solis@ejemplo.test','0990000003',NULL,'ACTIVO','2026-11-04 19:34:50','2026-11-04 19:34:50');
/*!40000 ALTER TABLE `clientes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cuentas`
--

DROP TABLE IF EXISTS `cuentas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cuentas` (
  `id_cuenta` int unsigned NOT NULL AUTO_INCREMENT,
  `numero_cuenta` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_cliente` int unsigned NOT NULL,
  `tipo` enum('AHORROS','CORRIENTE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'AHORROS',
  `moneda` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `saldo` decimal(15,2) NOT NULL DEFAULT '0.00',
  `estado` enum('ACTIVA','BLOQUEADA','CERRADA') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVA',
  `abierta_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_cuenta`),
  UNIQUE KEY `uq_cuentas_numero` (`numero_cuenta`),
  KEY `idx_cuentas_cliente` (`id_cliente`),
  CONSTRAINT `fk_cuentas_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `ck_cuentas_saldo` CHECK ((`saldo` >= 0))
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cuentas`
--

LOCK TABLES `cuentas` WRITE;
/*!40000 ALTER TABLE `cuentas` DISABLE KEYS */;
INSERT INTO `cuentas` VALUES (1,'2200000001',1,'AHORROS','USD',450.00,'ACTIVA','2026-11-04 19:34:50'),(2,'2200000002',2,'AHORROS','USD',300.00,'ACTIVA','2026-11-04 19:34:50'),(3,'2200000003',3,'CORRIENTE','USD',1000.00,'ACTIVA','2026-11-04 19:34:50');
/*!40000 ALTER TABLE `cuentas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `movimientos`
--

DROP TABLE IF EXISTS `movimientos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `movimientos` (
  `id_movimiento` bigint unsigned NOT NULL AUTO_INCREMENT,
  `referencia` char(36) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'UUID Ãºnico: evita duplicados (RN-07)',
  `tipo` enum('DEPOSITO','RETIRO','TRANSFERENCIA') COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_cuenta_origen` int unsigned DEFAULT NULL,
  `id_cuenta_destino` int unsigned DEFAULT NULL,
  `monto` decimal(15,2) NOT NULL,
  `moneda` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `descripcion` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` enum('PENDIENTE','COMPLETADO','RECHAZADO','REVERSADO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE',
  `id_usuario` int unsigned DEFAULT NULL COMMENT 'Usuario que ejecutÃ³ la operaciÃ³n',
  `fecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_movimiento`),
  UNIQUE KEY `uq_movimientos_referencia` (`referencia`),
  KEY `fk_mov_usuario` (`id_usuario`),
  KEY `idx_mov_origen_fecha` (`id_cuenta_origen`,`fecha`),
  KEY `idx_mov_destino_fecha` (`id_cuenta_destino`,`fecha`),
  CONSTRAINT `fk_mov_destino` FOREIGN KEY (`id_cuenta_destino`) REFERENCES `cuentas` (`id_cuenta`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_mov_origen` FOREIGN KEY (`id_cuenta_origen`) REFERENCES `cuentas` (`id_cuenta`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_mov_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `ck_movimientos_cuentas` CHECK ((((`tipo` = _latin1'DEPOSITO') and (`id_cuenta_destino` is not null) and (`id_cuenta_origen` is null)) or ((`tipo` = _latin1'RETIRO') and (`id_cuenta_origen` is not null) and (`id_cuenta_destino` is null)) or ((`tipo` = _latin1'TRANSFERENCIA') and (`id_cuenta_origen` is not null) and (`id_cuenta_destino` is not null)))),
  CONSTRAINT `ck_movimientos_distintas` CHECK (((`id_cuenta_origen` is null) or (`id_cuenta_destino` is null) or (`id_cuenta_origen` <> `id_cuenta_destino`))),
  CONSTRAINT `ck_movimientos_monto` CHECK ((`monto` > 0))
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `movimientos`
--

LOCK TABLES `movimientos` WRITE;
/*!40000 ALTER TABLE `movimientos` DISABLE KEYS */;
INSERT INTO `movimientos` VALUES (1,'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa','TRANSFERENCIA',1,2,50.00,'USD','Transferencia de prueba','COMPLETADO',3,'2026-11-04 19:34:50');
/*!40000 ALTER TABLE `movimientos` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_movimientos_no_delete` BEFORE DELETE ON `movimientos` FOR EACH ROW BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Los movimientos no se eliminan; use estado REVERSADO';
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `prestamos`
--

DROP TABLE IF EXISTS `prestamos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prestamos` (
  `id_prestamo` int unsigned NOT NULL AUTO_INCREMENT,
  `id_cliente` int unsigned NOT NULL,
  `monto` decimal(15,2) NOT NULL,
  `plazo_meses` smallint unsigned NOT NULL,
  `tasa_anual` decimal(5,2) DEFAULT NULL,
  `estado` enum('SOLICITADO','EN_REVISION','APROBADO','RECHAZADO','CANCELADO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SOLICITADO',
  `id_evaluador` int unsigned DEFAULT NULL COMMENT 'Asesor/admin que evalÃºa',
  `observacion` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `solicitado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resuelto_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id_prestamo`),
  KEY `fk_prestamos_evaluador` (`id_evaluador`),
  KEY `idx_prestamos_cliente` (`id_cliente`),
  CONSTRAINT `fk_prestamos_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_prestamos_evaluador` FOREIGN KEY (`id_evaluador`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `ck_prestamos_monto` CHECK ((`monto` > 0)),
  CONSTRAINT `ck_prestamos_plazo` CHECK ((`plazo_meses` between 1 and 120))
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prestamos`
--

LOCK TABLES `prestamos` WRITE;
/*!40000 ALTER TABLE `prestamos` DISABLE KEYS */;
INSERT INTO `prestamos` VALUES (1,2,1500.00,12,NULL,'SOLICITADO',NULL,NULL,'2026-11-04 19:34:50',NULL);
/*!40000 ALTER TABLE `prestamos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tarjetas`
--

DROP TABLE IF EXISTS `tarjetas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tarjetas` (
  `id_tarjeta` int unsigned NOT NULL AUTO_INCREMENT,
  `id_cuenta` int unsigned NOT NULL,
  `token` char(36) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Identificador ficticio de la tarjeta',
  `ultimos4` char(4) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('DEBITO','CREDITO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DEBITO',
  `titular` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vencimiento` date NOT NULL,
  `estado` enum('ACTIVA','BLOQUEADA','VENCIDA') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVA',
  `creada_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_tarjeta`),
  UNIQUE KEY `uq_tarjetas_token` (`token`),
  KEY `idx_tarjetas_cuenta` (`id_cuenta`),
  CONSTRAINT `fk_tarjetas_cuenta` FOREIGN KEY (`id_cuenta`) REFERENCES `cuentas` (`id_cuenta`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tarjetas`
--

LOCK TABLES `tarjetas` WRITE;
/*!40000 ALTER TABLE `tarjetas` DISABLE KEYS */;
INSERT INTO `tarjetas` VALUES (1,1,'11111111-1111-1111-1111-111111111111','4242','DEBITO','AXEL ROSERO','2029-12-31','ACTIVA','2026-11-04 19:34:50');
/*!40000 ALTER TABLE `tarjetas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios` (
  `id_usuario` int unsigned NOT NULL AUTO_INCREMENT,
  `keycloak_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'sub (UUID) del usuario en Keycloak',
  `username` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rol` enum('ADMIN','ASESOR','CLIENTE') COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_cliente` int unsigned DEFAULT NULL,
  `estado` enum('ACTIVO','INACTIVO','BLOQUEADO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVO',
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `uq_usuarios_keycloak` (`keycloak_id`),
  UNIQUE KEY `uq_usuarios_username` (`username`),
  UNIQUE KEY `uq_usuarios_cliente` (`id_cliente`),
  CONSTRAINT `fk_usuarios_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,'00000000-0000-0000-0000-000000000001','admin','ADMIN',NULL,'ACTIVO','2026-11-04 19:34:50'),(2,'00000000-0000-0000-0000-000000000002','asesor1','ASESOR',NULL,'ACTIVO','2026-11-04 19:34:50'),(3,'00000000-0000-0000-0000-000000000003','axel.rosero','CLIENTE',1,'ACTIVO','2026-11-04 19:34:50'),(4,'00000000-0000-0000-0000-000000000004','melany.zambrano','CLIENTE',2,'ACTIVO','2026-11-04 19:34:50');
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary view structure for view `v_historial`
--

DROP TABLE IF EXISTS `v_historial`;
/*!50001 DROP VIEW IF EXISTS `v_historial`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_historial` AS SELECT 
 1 AS `id_movimiento`,
 1 AS `referencia`,
 1 AS `tipo`,
 1 AS `cuenta`,
 1 AS `monto`,
 1 AS `moneda`,
 1 AS `estado`,
 1 AS `fecha`*/;
SET character_set_client = @saved_cs_client;

--
-- Dumping routines for database 'banco_digital'
--
/*!50003 DROP PROCEDURE IF EXISTS `sp_transferir` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = latin1 */ ;
/*!50003 SET character_set_results = latin1 */ ;
/*!50003 SET collation_connection  = latin1_swedish_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_transferir`(
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
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Final view structure for view `v_historial`
--

/*!50001 DROP VIEW IF EXISTS `v_historial`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = latin1 */;
/*!50001 SET character_set_results     = latin1 */;
/*!50001 SET collation_connection      = latin1_swedish_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_historial` AS select `m`.`id_movimiento` AS `id_movimiento`,`m`.`referencia` AS `referencia`,`m`.`tipo` AS `tipo`,`c`.`numero_cuenta` AS `cuenta`,`m`.`monto` AS `monto`,`m`.`moneda` AS `moneda`,`m`.`estado` AS `estado`,`m`.`fecha` AS `fecha` from (`movimientos` `m` join `cuentas` `c` on((`c`.`id_cuenta` in (`m`.`id_cuenta_origen`,`m`.`id_cuenta_destino`)))) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-04 19:46:46
