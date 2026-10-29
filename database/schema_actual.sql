/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19-11.8.6-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: 127.0.0.1    Database: sidge
-- ------------------------------------------------------
-- Server version	11.8.6-MariaDB-0+deb13u1 from Debian

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

--
-- Table structure for table `anio_escolar`
--

DROP TABLE IF EXISTS `anio_escolar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `anio_escolar` (
  `id_anio_escolar` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_periodo` varchar(20) NOT NULL,
  `anio_inicio` date NOT NULL,
  `anio_cierre` date NOT NULL,
  `estado` enum('PLANIFICADO','ACTIVO','CERRADO') NOT NULL DEFAULT 'PLANIFICADO',
  `iniciado_en` datetime DEFAULT NULL,
  `iniciado_por` int(11) DEFAULT NULL,
  `cerrado_en` datetime DEFAULT NULL,
  `cerrado_por` int(11) DEFAULT NULL,
  `reapierto_en` datetime DEFAULT NULL,
  `reapierto_por` int(11) DEFAULT NULL,
  `motivo_reapertura` varchar(200) DEFAULT NULL,
  `activo_unico` tinyint(4) GENERATED ALWAYS AS (case when `estado` = 'ACTIVO' then 1 else NULL end) STORED,
  PRIMARY KEY (`id_anio_escolar`),
  UNIQUE KEY `nombre_periodo` (`nombre_periodo`),
  UNIQUE KEY `uk_anio_escolar_activo_unico` (`activo_unico`),
  KEY `idx_anio_escolar_fechas` (`anio_inicio`,`anio_cierre`),
  KEY `idx_anio_escolar_estado` (`estado`),
  KEY `idx_anio_escolar_iniciado_por` (`iniciado_por`),
  KEY `idx_anio_escolar_cerrado_por` (`cerrado_por`),
  KEY `fk_anio_escolar_reapierto_por` (`reapierto_por`),
  CONSTRAINT `fk_anio_escolar_cerrado_por` FOREIGN KEY (`cerrado_por`) REFERENCES `usuario` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_anio_escolar_iniciado_por` FOREIGN KEY (`iniciado_por`) REFERENCES `usuario` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_anio_escolar_reapierto_por` FOREIGN KEY (`reapierto_por`) REFERENCES `usuario` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=687 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `anio_escolar`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `anio_escolar` WRITE;
/*!40000 ALTER TABLE `anio_escolar` DISABLE KEYS */;
INSERT INTO `anio_escolar` VALUES
(175,'2025-2026','2025-08-15','2026-07-15','CERRADO','2026-10-05 11:27:43',5,'2026-10-05 13:18:01',5,'2026-10-05 11:27:43',5,'Cierre 2025-2026 revertido para resolver retiros pendientes del período',NULL),
(238,'2026-2027','2026-12-10','2027-07-15','ACTIVO','2026-10-05 13:19:11',5,'2026-10-05 11:24:32',5,'2026-10-05 13:19:11',5,'Reinicio del ciclo 2026-2027 para matricular el año en curso',1);
/*!40000 ALTER TABLE `anio_escolar` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_uca1400_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*//*!50003 TRIGGER trg_anio_uno_en_curso
BEFORE INSERT ON anio_escolar
FOR EACH ROW
BEGIN
    IF NEW.estado = 'ACTIVO' AND EXISTS (
        SELECT 1 FROM anio_escolar WHERE estado = 'ACTIVO'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Ya hay un año escolar en curso. Ciérralo antes de abrir otro.';
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_uca1400_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*//*!50003 TRIGGER trg_anio_uno_en_curso_upd
BEFORE UPDATE ON anio_escolar
FOR EACH ROW
BEGIN
    
    IF NEW.estado = 'ACTIVO' AND OLD.estado <> 'ACTIVO' AND EXISTS (
        SELECT 1 FROM anio_escolar
         WHERE estado = 'ACTIVO' AND id_anio_escolar <> NEW.id_anio_escolar
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Ya hay un año escolar en curso. Ciérralo antes de abrir otro.';
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `asignacion_docente`
--

DROP TABLE IF EXISTS `asignacion_docente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `asignacion_docente` (
  `id_asignacion` int(11) NOT NULL AUTO_INCREMENT,
  `id_docente` int(11) NOT NULL,
  `id_estructura_academica` int(11) NOT NULL,
  `id_tipo_asignacion` int(11) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,
  PRIMARY KEY (`id_asignacion`),
  KEY `idx_asignacion_estructura` (`id_estructura_academica`),
  KEY `idx_asignacion_tipo` (`id_tipo_asignacion`),
  KEY `idx_asignacion_docente_estructura` (`id_docente`,`id_estructura_academica`,`fecha_inicio`),
  CONSTRAINT `fk_asignacion_docente` FOREIGN KEY (`id_docente`) REFERENCES `docente` (`id_docente`) ON UPDATE CASCADE,
  CONSTRAINT `fk_asignacion_estructura` FOREIGN KEY (`id_estructura_academica`) REFERENCES `estructura_academica` (`id_estructura_academica`) ON UPDATE CASCADE,
  CONSTRAINT `fk_asignacion_tipo` FOREIGN KEY (`id_tipo_asignacion`) REFERENCES `tipo_asignacion` (`id_tipo_asignacion`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `asignacion_docente`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `asignacion_docente` WRITE;
/*!40000 ALTER TABLE `asignacion_docente` DISABLE KEYS */;
INSERT INTO `asignacion_docente` VALUES
(18,5,1537,10,'2026-12-10','2026-10-24'),
(49,5,1537,11,'2026-10-25','2026-10-24'),
(52,5,1537,11,'2026-10-24','2026-10-24'),
(53,5,1537,11,'2026-10-25','2026-10-24'),
(54,5,1537,11,'2026-10-25','2026-10-24'),
(55,5,1537,11,'2026-10-25','2026-10-24'),
(56,5,1537,11,'2026-10-25',NULL);
/*!40000 ALTER TABLE `asignacion_docente` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `bitacora`
--

DROP TABLE IF EXISTS `bitacora`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bitacora` (
  `id_bitacora` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `accion` varchar(100) NOT NULL,
  `modulo` varchar(50) NOT NULL,
  `direccion_ip` varchar(45) NOT NULL,
  PRIMARY KEY (`id_bitacora`),
  KEY `idx_bitacora_usuario` (`id_usuario`),
  KEY `idx_bitacora_fecha` (`fecha`),
  KEY `idx_bitacora_modulo` (`modulo`),
  KEY `idx_bitacora_accion` (`accion`),
  KEY `idx_bitacora_fecha_usuario` (`fecha`,`id_usuario`),
  CONSTRAINT `fk_bitacora_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4834 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bitacora`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `bitacora` WRITE;
/*!40000 ALTER TABLE `bitacora` DISABLE KEYS */;
INSERT INTO `bitacora` VALUES
(4067,2,'2026-10-20','08:37:02','LOGOUT','Autenticación','127.0.0.1'),
(4068,2,'2026-10-20','08:38:42','LOGIN','Autenticación','127.0.0.1'),
(4078,1,'2026-10-20','09:39:04','Generar reporte · Bitácora (desde: 2026-01-01, hasta: 2026-12-31, 2 registros)','Reportes','0.0.0.0'),
(4079,1,'2026-10-20','09:39:21','Generar reporte · Bitácora (desde: 2026-01-01, hasta: 2026-12-31, 3 registros)','Reportes','0.0.0.0'),
(4080,2,'2026-10-20','09:39:32','Generar reporte · Listado de usuarios (sin filtros adicionales, 21 registros)','Reportes','127.0.0.1'),
(4081,2,'2026-10-20','09:39:45','Generar reporte · Bitácora (sin filtros adicionales, 5 registros)','Reportes','127.0.0.1'),
(4082,2,'2026-10-20','09:41:08','Generar reporte · Listado de usuarios (sin filtros adicionales, 21 registros)','Reportes','127.0.0.1'),
(4083,2,'2026-10-20','09:53:19','Generar reporte · Listado de usuarios (sin filtros adicionales, 21 registros)','Reportes','127.0.0.1'),
(4084,2,'2026-10-20','10:05:35','Generar reporte · Listado de usuarios (sin filtros adicionales, 21 registros)','Reportes','127.0.0.1'),
(4085,2,'2026-10-20','10:17:28','Generar reporte · Bitácora (sin filtros adicionales, 9 registros)','Reportes','127.0.0.1'),
(4086,2,'2026-10-20','10:24:40','Generar reporte · Bitácora (sin filtros adicionales, 10 registros)','Reportes','127.0.0.1'),
(4087,2,'2026-10-20','10:32:03','Generar reporte · Bitácora (sin filtros adicionales, 11 registros)','Reportes','127.0.0.1'),
(4088,2,'2026-10-20','10:35:29','ACTUALIZAR CONFIGURACIÓN INSTITUCIONAL · Datos del plantel y ubicación territorial','Configuración','127.0.0.1'),
(4089,2,'2026-10-20','10:37:34','ACTUALIZAR CONFIGURACIÓN INSTITUCIONAL · Datos del plantel y ubicación territorial','Configuración','127.0.0.1'),
(4090,2,'2026-10-20','10:39:01','Generar reporte · Bitácora (sin filtros adicionales, 14 registros)','Reportes','127.0.0.1'),
(4091,2,'2026-10-20','10:39:10','Generar reporte · Bitácora (sin filtros adicionales, 15 registros)','Reportes','127.0.0.1'),
(4092,2,'2026-10-20','10:40:11','Generar reporte · Listado de usuarios (sin filtros adicionales, 21 registros)','Reportes','127.0.0.1'),
(4093,2,'2026-10-20','10:43:28','Generar reporte · Listado de usuarios (sin filtros adicionales, 21 registros)','Reportes','127.0.0.1'),
(4094,2,'2026-10-20','10:49:10','Generar reporte · Bitácora (sin filtros adicionales, 18 registros)','Reportes','127.0.0.1'),
(4095,2,'2026-10-20','10:49:17','Generar reporte · Bitácora (sin filtros adicionales, 19 registros)','Reportes','127.0.0.1'),
(4096,2,'2026-10-20','10:50:13','Generar reporte · Listado de usuarios (sin filtros adicionales, 21 registros)','Reportes','127.0.0.1'),
(4097,2,'2026-10-20','11:04:41','Generar reporte · Listado de usuarios (sin filtros adicionales, 21 registros)','Reportes','127.0.0.1'),
(4098,2,'2026-10-20','11:08:43','Generar reporte · Bitácora (sin filtros adicionales, 22 registros)','Reportes','127.0.0.1'),
(4099,2,'2026-10-20','11:10:56','Generar reporte · Bitácora (sin filtros adicionales, 23 registros)','Reportes','127.0.0.1'),
(4100,2,'2026-10-20','19:11:03','LOGIN','Autenticación','127.0.0.1'),
(4101,2,'2026-10-20','19:13:43','LOGOUT','Autenticación','127.0.0.1'),
(4102,8,'2026-10-20','19:14:09','LOGIN','Autenticación','127.0.0.1'),
(4103,8,'2026-10-20','19:14:21','CAMBIO_PASSWORD','Autenticación','127.0.0.1'),
(4104,8,'2026-10-20','19:14:39','LOGIN','Autenticación','127.0.0.1'),
(4105,8,'2026-10-20','19:14:56','LOGIN','Autenticación','127.0.0.1'),
(4106,8,'2026-10-20','19:19:29','LOGOUT','Autenticación','127.0.0.1'),
(4107,2,'2026-10-20','19:19:41','LOGIN','Autenticación','127.0.0.1'),
(4108,2,'2026-10-20','19:25:15','Generar reporte · Listado de usuarios (sin filtros adicionales, 21 registros)','Reportes','127.0.0.1'),
(4109,2,'2026-10-20','19:25:49','Generar reporte · Listado de usuarios (sin filtros adicionales, 21 registros)','Reportes','127.0.0.1'),
(4110,2,'2026-10-20','19:26:16','LOGOUT','Autenticación','127.0.0.1'),
(4111,17,'2026-10-20','19:26:28','LOGIN','Autenticación','127.0.0.1'),
(4112,17,'2026-10-21','11:31:04','LOGIN','Autenticación','127.0.0.1'),
(4113,17,'2026-10-21','11:34:34','EMISIÓN DE CONSTANCIA (RETIRO): Tarle Arias (V1212250928)','Constancias','127.0.0.1'),
(4114,17,'2026-10-21','13:00:47','LOGIN','Autenticación','127.0.0.1'),
(4115,17,'2026-10-21','13:04:51','LOGOUT','Autenticación','127.0.0.1'),
(4116,7,'2026-10-21','13:05:00','LOGIN','Autenticación','127.0.0.1'),
(4117,2,'2026-10-21','17:27:40','LOGIN','Autenticación','127.0.0.1'),
(4118,8,'2026-10-21','18:50:30','LOGIN','Autenticación','127.0.0.1'),
(4119,1,'2026-10-21','18:50:31','RATIFICACIÓN Estudiante A Prueba (TEST-001) · 3° Grado · A · Mañana → 4° Grado · A · Mañana','Ratificaciones','0.0.0.0'),
(4120,1,'2026-10-21','18:50:31','RATIFICACIÓN Estudiante B Prueba (TEST-002) · 3° Grado · A · Mañana → 4° Grado · A · Mañana','Ratificaciones','0.0.0.0'),
(4121,1,'2026-10-21','18:50:32','RATIFICACIÓN Estudiante G Prueba (TEST-007) · 3° Grado · A · Mañana → 4° Grado · A · Mañana','Ratificaciones','0.0.0.0'),
(4122,8,'2026-10-21','19:24:23','LOGOUT','Autenticación','127.0.0.1'),
(4123,17,'2026-10-21','19:24:37','LOGIN','Autenticación','127.0.0.1'),
(4124,17,'2026-10-21','19:24:48','LOGOUT','Autenticación','127.0.0.1'),
(4125,8,'2026-10-21','19:24:59','LOGIN','Autenticación','127.0.0.1'),
(4126,2,'2026-10-21','22:10:53','LOGIN','Autenticación','127.0.0.1'),
(4127,2,'2026-10-21','22:10:59','LOGOUT','Autenticación','127.0.0.1'),
(4128,7,'2026-10-21','22:11:35','LOGIN','Autenticación','127.0.0.1'),
(4129,7,'2026-10-21','22:15:03','LOGOUT','Autenticación','127.0.0.1'),
(4130,8,'2026-10-21','22:15:20','LOGIN','Autenticación','127.0.0.1'),
(4131,8,'2026-10-22','08:06:45','LOGIN','Autenticación','127.0.0.1'),
(4132,8,'2026-10-22','08:16:17','LOGOUT','Autenticación','127.0.0.1'),
(4133,17,'2026-10-22','08:16:35','LOGIN','Autenticación','127.0.0.1'),
(4134,17,'2026-10-22','08:16:58','EMISIÓN DE CONSTANCIA (RETIRO): Tarle Arias (V1212250928)','Constancias','127.0.0.1'),
(4135,17,'2026-10-22','08:17:10','EMISIÓN DE CONSTANCIA (PROSECUCION): Uyhui Hiuh (V1212586313)','Constancias','127.0.0.1'),
(4136,8,'2026-10-22','08:28:11','EMISIÓN MASIVA DE CERTIFICADOS DE CALIFICACIONES (Art. 108 RGLOE): Sección id 1537 (1 estudiantes)','Evaluación','0.0.0.0'),
(4137,8,'2026-10-22','08:28:22','EMISIÓN MASIVA DE CERTIFICADOS DE CALIFICACIONES (Art. 108 RGLOE): Sección id 1537 (1 estudiantes)','Evaluación','0.0.0.0'),
(4138,17,'2026-10-22','08:42:39','LOGOUT','Autenticación','127.0.0.1'),
(4139,8,'2026-10-22','08:42:48','LOGIN','Autenticación','127.0.0.1'),
(4142,8,'2026-10-22','10:04:36','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Uyhui Hiuh (V1212586313, Literal A)','Evaluación','127.0.0.1'),
(4143,8,'2026-10-22','10:15:33','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Uyhui Hiuh (V1212586313, Literal A)','Evaluación','127.0.0.1'),
(4144,8,'2026-10-22','10:22:52','LITERALES: 3 guardados (0 prom, 0 no prom, 3 sin lit) · 1 omitidos · estruct 1537','Resultado académico','127.0.0.1'),
(4145,8,'2026-10-22','10:23:57','EMISIÓN MASIVA DE CERTIFICADOS DE CALIFICACIONES (Art. 108 RGLOE): Sección id 1537 (1 estudiantes)','Evaluación','127.0.0.1'),
(4146,8,'2026-10-22','10:38:13','EMISIÓN MASIVA DE CERTIFICADOS DE CALIFICACIONES (Art. 108 RGLOE): Sección id 1537 (1 estudiantes)','Evaluación','127.0.0.1'),
(4147,8,'2026-10-22','11:40:10','LOGIN','Autenticación','127.0.0.1'),
(4148,8,'2026-10-22','11:40:30','EMISIÓN MASIVA DE CERTIFICADOS DE CALIFICACIONES (Art. 108 RGLOE): Sección id 1537 (1 estudiantes)','Evaluación','127.0.0.1'),
(4149,8,'2026-10-22','11:41:52','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Uyhui Hiuh (V1212586313, Literal A)','Evaluación','127.0.0.1'),
(4150,8,'2026-10-22','11:53:12','LITERALES: 3 guardados (0 prom, 1 no prom, 2 sin lit) · 1 omitidos · estruct 1537','Resultado académico','127.0.0.1'),
(4151,8,'2026-10-22','11:53:58','LITERALES: 3 guardados (1 prom, 1 no prom, 1 sin lit) · 1 omitidos · estruct 1537','Resultado académico','127.0.0.1'),
(4152,8,'2026-10-22','11:54:29','LITERALES: 3 guardados (1 prom, 1 no prom, 1 sin lit) · 1 omitidos · estruct 1537','Resultado académico','127.0.0.1'),
(4153,8,'2026-10-22','11:54:42','EMISIÓN MASIVA DE CERTIFICADOS DE CALIFICACIONES (Art. 108 RGLOE): Sección id 1537 (3 estudiantes)','Evaluación','127.0.0.1'),
(4154,8,'2026-10-22','11:54:51','LOGIN','Autenticación','127.0.0.1'),
(4155,7,'2026-10-22','11:54:52','LOGIN','Autenticación','127.0.0.1'),
(4156,8,'2026-10-22','11:54:55','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Huihi Jiojo (V5212250928, Literal E)','Evaluación','127.0.0.1'),
(4157,8,'2026-10-22','11:55:29','LOGIN','Autenticación','127.0.0.1'),
(4158,7,'2026-10-22','11:55:30','LOGIN','Autenticación','127.0.0.1'),
(4159,8,'2026-10-22','12:20:37','LITERALES: 3 guardados (0 prom, 0 no prom, 3 sin lit) · 1 omitidos · estruct 1537','Resultado académico','127.0.0.1'),
(4160,8,'2026-10-22','12:20:48','LITERALES: 3 guardados (0 prom, 0 no prom, 3 sin lit) · 1 omitidos · estruct 1537','Resultado académico','127.0.0.1'),
(4161,8,'2026-10-22','12:57:44','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Uyhui Hiuh (V1212586313, Literal A)','Evaluación','127.0.0.1'),
(4162,17,'2026-10-22','13:33:02','LOGIN','Autenticación','127.0.0.1'),
(4163,8,'2026-10-22','13:47:54','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Hiuh, Uyhui (V1212586313, Literal A)','Evaluación','127.0.0.1'),
(4164,8,'2026-10-22','13:51:47','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Hiuh, Uyhui (V1212586313, Literal A)','Evaluación','127.0.0.1'),
(4165,8,'2026-10-22','13:56:08','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Hiuh, Uyhui (V1212586313, Literal A)','Evaluación','127.0.0.1'),
(4166,8,'2026-10-22','13:56:51','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Hiuh, Uyhui (V1212586313, Literal A)','Evaluación','127.0.0.1'),
(4167,8,'2026-10-22','14:00:07','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Hiuh, Uyhui (V1212586313, Literal A)','Evaluación','127.0.0.1'),
(4168,8,'2026-10-22','14:01:59','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Hiuh, Uyhui (V1212586313, Literal A)','Evaluación','127.0.0.1'),
(4169,8,'2026-10-22','14:04:00','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Hiuh, Uyhui (V1212586313, Literal A)','Evaluación','127.0.0.1'),
(4170,8,'2026-10-22','14:05:49','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Hiuh, Uyhui (V1212586313, Literal A)','Evaluación','127.0.0.1'),
(4171,8,'2026-10-22','14:07:02','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Hiuh, Uyhui (V1212586313, Literal A)','Evaluación','127.0.0.1'),
(4172,8,'2026-10-22','14:12:40','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Hiuh, Uyhui (V1212586313, Literal A)','Evaluación','127.0.0.1'),
(4173,8,'2026-10-22','14:15:04','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Hiuh, Uyhui (V1212586313, Literal A)','Evaluación','127.0.0.1'),
(4174,8,'2026-10-22','14:18:30','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Hiuh, Uyhui (V1212586313, Literal A)','Evaluación','127.0.0.1'),
(4175,8,'2026-10-22','14:19:50','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Hiuh, Uyhui (V1212586313, Literal A)','Evaluación','127.0.0.1'),
(4176,8,'2026-10-22','14:22:19','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Hiuh, Uyhui (V1212586313, Literal A)','Evaluación','127.0.0.1'),
(4177,8,'2026-10-22','14:23:30','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Hiuh, Uyhui (V1212586313, Literal A)','Evaluación','127.0.0.1'),
(4178,8,'2026-10-22','14:24:03','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Hiuh, Uyhui (V1212586313, Literal A)','Evaluación','127.0.0.1'),
(4179,8,'2026-10-22','14:24:06','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Hiuh, Uyhui (V1212586313, Literal A)','Evaluación','127.0.0.1'),
(4180,8,'2026-10-22','14:25:05','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Hiuh, Uyhui (V1212586313, Literal A)','Evaluación','127.0.0.1'),
(4181,8,'2026-10-22','14:34:30','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Hiuh, Uyhui (V1212586313, Literal A)','Evaluación','127.0.0.1'),
(4182,8,'2026-10-22','14:35:16','EMISIÓN MASIVA DE CERTIFICADOS DE CALIFICACIONES (Art. 108 RGLOE): Sección id 1537 (1 estudiantes)','Evaluación','127.0.0.1'),
(4183,8,'2026-10-22','14:41:26','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Hiuh, Uyhui (V1212586313, Literal A)','Evaluación','127.0.0.1'),
(4184,8,'2026-10-22','14:42:34','LITERALES: 3 guardados (0 prom, 1 no prom, 2 sin lit) · 1 omitidos · estruct 1537','Resultado académico','127.0.0.1'),
(4185,8,'2026-10-22','14:42:44','LITERALES: 3 guardados (1 prom, 1 no prom, 1 sin lit) · 1 omitidos · estruct 1537','Resultado académico','127.0.0.1'),
(4186,8,'2026-10-22','14:42:50','EMISIÓN MASIVA DE CERTIFICADOS DE CALIFICACIONES (Art. 108 RGLOE): Sección id 1537 (3 estudiantes)','Evaluación','127.0.0.1'),
(4187,8,'2026-10-22','14:43:47','LOGOUT','Autenticación','127.0.0.1'),
(4188,8,'2026-10-22','14:44:24','LOGIN','Autenticación','127.0.0.1'),
(4189,8,'2026-10-22','14:44:39','LOGOUT','Autenticación','127.0.0.1'),
(4190,17,'2026-10-22','14:44:50','LOGIN','Autenticación','127.0.0.1'),
(4191,17,'2026-10-22','14:51:28','LOGOUT','Autenticación','127.0.0.1'),
(4192,8,'2026-10-22','14:51:42','LOGIN','Autenticación','127.0.0.1'),
(4193,8,'2026-10-22','14:53:59','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Jiojo, Huihi (V5212250928, Literal E)','Evaluación','127.0.0.1'),
(4194,8,'2026-10-22','14:56:28','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Hiuh, Uyhui (V1212586313, Literal A)','Evaluación','127.0.0.1'),
(4195,8,'2026-10-22','15:06:01','LOGOUT','Autenticación','127.0.0.1'),
(4196,17,'2026-10-22','15:06:14','LOGIN','Autenticación','127.0.0.1'),
(4197,17,'2026-10-22','15:11:23','ACTUALIZACIÓN DE RECAUDOS del estudiante Ana López (V1212586313) · pendiente: Autorización de retiro','Recaudos','127.0.0.1'),
(4198,17,'2026-10-22','15:11:28','LOGOUT','Autenticación','127.0.0.1'),
(4199,8,'2026-10-22','15:11:50','LOGIN','Autenticación','127.0.0.1'),
(4200,8,'2026-10-22','15:12:01','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): López Toledo, Ana Patricia (V1212586313, ','Evaluación','127.0.0.1'),
(4201,8,'2026-10-22','15:15:07','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Jiojo, Huihi (V5212250928, Literal E)','Evaluación','127.0.0.1'),
(4202,8,'2026-10-22','15:15:25','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): López Toledo, Ana Patricia (V1212586313, ','Evaluación','127.0.0.1'),
(4203,8,'2026-10-22','15:15:42','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Jiojo, Huihi (V5212250928, Literal E)','Evaluación','127.0.0.1'),
(4204,8,'2026-10-22','15:16:01','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Jiojo, Huihi (V5212250928, Literal E)','Evaluación','127.0.0.1'),
(4205,8,'2026-10-22','15:16:06','EMISIÓN MASIVA DE CERTIFICADOS DE CALIFICACIONES (Art. 108 RGLOE): Sección id 1537 (3 estudiantes)','Evaluación','127.0.0.1'),
(4206,8,'2026-10-22','15:17:02','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Jiojo, Huihi (V5212250928, Literal E)','Evaluación','127.0.0.1'),
(4207,8,'2026-10-22','15:17:57','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): López Toledo, Ana Patricia (V1212586313, ','Evaluación','127.0.0.1'),
(4208,8,'2026-10-22','15:18:51','LOGOUT','Autenticación','127.0.0.1'),
(4209,17,'2026-10-22','15:19:13','LOGIN','Autenticación','127.0.0.1'),
(4210,17,'2026-10-22','15:20:54','ACTUALIZACIÓN DE RECAUDOS del estudiante Huihi Jiojo (V5212250928) · pendiente: Autorización de reti','Recaudos','127.0.0.1'),
(4211,17,'2026-10-22','15:34:53','LOGOUT','Autenticación','127.0.0.1'),
(4212,8,'2026-10-22','15:35:13','LOGIN','Autenticación','127.0.0.1'),
(4213,8,'2026-10-22','15:35:28','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Pinto Garcia, Juan Torrealba (38524698, L','Evaluación','127.0.0.1'),
(4214,8,'2026-10-22','15:36:38','LOGOUT','Autenticación','127.0.0.1'),
(4215,17,'2026-10-22','15:36:59','LOGIN','Autenticación','127.0.0.1'),
(4216,17,'2026-10-22','15:37:08','EMISIÓN DE CONSTANCIA (ESTUDIO): Ana Patricia López Toledo (V1212586313)','Constancias','127.0.0.1'),
(4217,17,'2026-10-22','15:54:59','EMISIÓN DE CONSTANCIA (ESTUDIO): Ana Patricia López Toledo (V1212586313)','Constancias','127.0.0.1'),
(4218,17,'2026-10-22','15:55:24','EMISIÓN DE CONSTANCIA (INSCRIPCION): Ana Patricia López Toledo (V1212586313)','Constancias','127.0.0.1'),
(4219,17,'2026-10-22','16:05:29','EMISIÓN DE CONSTANCIA (INSCRIPCION): Ana Patricia López Toledo (V1212586313)','Constancias','127.0.0.1'),
(4220,17,'2026-10-22','16:07:10','EMISIÓN DE CONSTANCIA (ESTUDIO): Ana Patricia López Toledo (V1212586313)','Constancias','127.0.0.1'),
(4221,17,'2026-10-22','16:07:51','EMISIÓN DE CONSTANCIA (ESTUDIO): Ana Patricia López Toledo (V1212586313)','Constancias','127.0.0.1'),
(4222,17,'2026-10-22','16:08:03','LOGOUT','Autenticación','127.0.0.1'),
(4223,2,'2026-10-22','16:08:16','LOGIN','Autenticación','127.0.0.1'),
(4224,2,'2026-10-22','16:11:41','LOGOUT','Autenticación','127.0.0.1'),
(4225,8,'2026-10-22','16:11:53','LOGIN','Autenticación','127.0.0.1'),
(4226,8,'2026-10-22','16:12:01','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): López Toledo, Ana Patricia (V1212586313, ','Evaluación','127.0.0.1'),
(4227,8,'2026-10-22','16:12:08','LOGOUT','Autenticación','127.0.0.1'),
(4228,17,'2026-10-22','16:12:20','LOGIN','Autenticación','127.0.0.1'),
(4229,17,'2026-10-22','16:12:29','EMISIÓN DE CONSTANCIA (ESTUDIO): Ana Patricia López Toledo (V1212586313)','Constancias','127.0.0.1'),
(4230,17,'2026-10-22','16:13:18','EMISIÓN DE CONSTANCIA (INSCRIPCION): Ana Patricia López Toledo (V1212586313)','Constancias','127.0.0.1'),
(4231,17,'2026-10-22','16:15:19','EMISIÓN DE CONSTANCIA (INSCRIPCION): Ana Patricia López Toledo (V1212586313)','Constancias','127.0.0.1'),
(4232,17,'2026-10-22','16:15:47','EMISIÓN DE CONSTANCIA (ESTUDIO): Ana Patricia López Toledo (V1212586313)','Constancias','127.0.0.1'),
(4233,17,'2026-10-22','16:17:09','EMISIÓN DE CONSTANCIA (ESTUDIO): Ana Patricia López Toledo (V1212586313)','Constancias','127.0.0.1'),
(4234,17,'2026-10-22','16:19:20','EMISIÓN DE CONSTANCIA (RETIRO): Tarle Arias (V1212250928)','Constancias','127.0.0.1'),
(4235,17,'2026-10-22','16:27:33','EMISIÓN DE CONSTANCIA (RETIRO): Tarle Arias (V1212250928)','Constancias','127.0.0.1'),
(4236,17,'2026-10-22','16:56:51','EMISIÓN DE CONSTANCIA (RETIRO): Tarle Arias (V1212250928)','Constancias','127.0.0.1'),
(4237,17,'2026-10-22','16:57:02','EMISIÓN DE CONSTANCIA (PROSECUCION): Lhia Ana Perez Ruiz (V1201234567)','Constancias','127.0.0.1'),
(4238,17,'2026-10-22','16:59:47','EMISIÓN DE CONSTANCIA (PROSECUCION): Lhia Ana Perez Ruiz (V1201234567)','Constancias','127.0.0.1'),
(4239,17,'2026-10-22','17:53:15','EMISIÓN DE CONSTANCIA (RETIRO): Tarle Arias (V1212250928)','Constancias','127.0.0.1'),
(4240,17,'2026-10-22','17:55:50','EMISIÓN DE CONSTANCIA (PROSECUCION): Lhia Ana Perez Ruiz (V1201234567)','Constancias','127.0.0.1'),
(4241,2,'2026-10-22','18:49:19','LOGIN','Autenticación','127.0.0.1'),
(4242,17,'2026-10-22','19:50:57','LOGIN','Autenticación','127.0.0.1'),
(4243,17,'2026-10-22','20:15:12','EMISIÓN DE CONSTANCIA (ESTUDIO): Ana Patricia López Toledo (V1212586313)','Constancias','127.0.0.1'),
(4244,17,'2026-10-22','20:15:21','EMISIÓN DE CONSTANCIA (ESTUDIO): Ana Patricia López Toledo (V1212586313)','Constancias','127.0.0.1'),
(4245,17,'2026-10-22','20:16:03','EMISIÓN DE CONSTANCIA (INSCRIPCION): Erickmar Margarita Sanchez Lopez (V2202586313)','Constancias','127.0.0.1'),
(4246,17,'2026-10-22','20:16:48','EMISIÓN DE CONSTANCIA (INSCRIPCION): Ana Patricia López Toledo (V1212586313)','Constancias','127.0.0.1'),
(4247,17,'2026-10-22','20:17:19','EMISIÓN DE CONSTANCIA (PROSECUCION): Lhia Ana Perez Ruiz (V1201234567)','Constancias','127.0.0.1'),
(4248,17,'2026-10-22','20:18:13','EMISIÓN DE CONSTANCIA (RETIRO): Tarle Arias (V1212250928)','Constancias','127.0.0.1'),
(4249,17,'2026-10-22','20:18:40','LOGOUT','Autenticación','127.0.0.1'),
(4250,8,'2026-10-22','20:18:58','LOGIN','Autenticación','127.0.0.1'),
(4251,8,'2026-10-22','20:23:50','EMISIÓN MASIVA DE CERTIFICADOS DE CALIFICACIONES (Art. 108 RGLOE): Sección id 1537 (3 estudiantes)','Evaluación','127.0.0.1'),
(4252,8,'2026-10-22','20:48:25','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Ana López (V1212586313, Literal A)','Evaluación','127.0.0.1'),
(4253,8,'2026-10-22','20:48:53','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Ana López (V1212586313, Literal A)','Evaluación','127.0.0.1'),
(4254,8,'2026-10-22','21:07:08','EMISIÓN MASIVA DE CERTIFICADOS DE CALIFICACIONES (Art. 108 RGLOE): Sección id 1537 (3 estudiantes)','Evaluación','127.0.0.1'),
(4255,8,'2026-10-22','21:09:31','LITERALES: 4 guardados (3 prom, 1 no prom, 0 sin lit) · 0 omitidos · estruct 1537','Resultado académico','127.0.0.1'),
(4256,8,'2026-10-22','21:10:18','LOGOUT','Autenticación','127.0.0.1'),
(4257,17,'2026-10-22','21:10:44','LOGIN','Autenticación','127.0.0.1'),
(4258,17,'2026-10-22','21:17:08','EMISIÓN DE CONSTANCIA (ESTUDIO): Ana Patricia López Toledo (V1212586313)','Constancias','127.0.0.1'),
(4259,17,'2026-10-22','22:33:30','LOGIN','Autenticación','127.0.0.1'),
(4260,17,'2026-10-23','05:50:15','LOGIN','Autenticación','127.0.0.1'),
(4261,17,'2026-10-23','06:02:53','INSCRIPCIÓN Juan Ojeda (V6212250928) · 1° Grado · A · Mañana','Inscripciones','127.0.0.1'),
(4262,17,'2026-10-23','06:05:03','LOGOUT','Autenticación','127.0.0.1'),
(4263,7,'2026-10-23','06:05:15','LOGIN','Autenticación','127.0.0.1'),
(4264,7,'2026-10-23','07:01:41','LOGOUT','Autenticación','127.0.0.1'),
(4265,17,'2026-10-23','07:01:52','LOGIN','Autenticación','127.0.0.1'),
(4266,17,'2026-10-23','07:01:59','EMISIÓN DE CONSTANCIA (ESTUDIO): Ana Patricia López Toledo (V1212586313)','Constancias','127.0.0.1'),
(4267,17,'2026-10-23','07:02:50','EMISIÓN DE CONSTANCIA (INSCRIPCION): Ana Patricia López Toledo (V1212586313)','Constancias','127.0.0.1'),
(4268,17,'2026-10-23','07:03:06','EMISIÓN DE CONSTANCIA (INSCRIPCION): Ana Patricia López Toledo (V1212586313)','Constancias','127.0.0.1'),
(4269,17,'2026-10-23','07:03:23','EMISIÓN DE CONSTANCIA (INSCRIPCION): Juan Torrealba Pinto Garcia (38524698)','Constancias','127.0.0.1'),
(4270,17,'2026-10-23','07:03:54','EMISIÓN DE CONSTANCIA (INSCRIPCION): Leo  Pinto (V1162458963)','Constancias','127.0.0.1'),
(4271,17,'2026-10-23','07:04:04','EMISIÓN DE CONSTANCIA (INSCRIPCION): Tarle Arias (V1212250928)','Constancias','127.0.0.1'),
(4272,17,'2026-10-23','07:09:23','EMISIÓN DE CONSTANCIA (ESTUDIO): Ana Patricia López Toledo (V1212586313)','Constancias','127.0.0.1'),
(4273,17,'2026-10-23','07:09:32','EMISIÓN DE CONSTANCIA (ESTUDIO): Erickmar Margarita Sanchez Lopez (V2202586313)','Constancias','127.0.0.1'),
(4274,17,'2026-10-23','07:10:32','EMISIÓN DE CONSTANCIA (ESTUDIO): Maria Paredez (V4212250928)','Constancias','127.0.0.1'),
(4275,17,'2026-10-23','07:10:44','EMISIÓN DE CONSTANCIA (ESTUDIO): Lhia Ana Perez Ruiz (V1201234567)','Constancias','127.0.0.1'),
(4276,17,'2026-10-23','07:16:39','LOGOUT','Autenticación','127.0.0.1'),
(4277,7,'2026-10-23','07:16:50','LOGIN','Autenticación','127.0.0.1'),
(4278,2,'2026-10-23','08:39:11','LOGIN','Autenticación','127.0.0.1'),
(4279,2,'2026-10-23','08:39:45','LOGOUT','Autenticación','127.0.0.1'),
(4280,7,'2026-10-23','08:39:57','LOGIN','Autenticación','127.0.0.1'),
(4281,7,'2026-10-23','08:48:05','LOGOUT','Autenticación','127.0.0.1'),
(4282,7,'2026-10-23','08:48:18','LOGIN','Autenticación','127.0.0.1'),
(4283,7,'2026-10-23','11:07:29','LOGOUT','Autenticación','127.0.0.1'),
(4284,17,'2026-10-23','11:07:40','LOGIN','Autenticación','127.0.0.1'),
(4285,17,'2026-10-23','11:07:49','EMISIÓN DE CONSTANCIA (ESTUDIO): Ana Patricia López Toledo (V1212586313)','Constancias','127.0.0.1'),
(4286,17,'2026-10-23','11:08:30','LOGOUT','Autenticación','127.0.0.1'),
(4287,7,'2026-10-23','11:08:39','LOGIN','Autenticación','127.0.0.1'),
(4288,7,'2026-10-23','11:09:51','LOGOUT','Autenticación','127.0.0.1'),
(4289,17,'2026-10-23','11:10:06','LOGIN','Autenticación','127.0.0.1'),
(4290,17,'2026-10-23','11:12:33','LOGOUT','Autenticación','127.0.0.1'),
(4291,7,'2026-10-23','11:12:41','LOGIN','Autenticación','127.0.0.1'),
(4292,7,'2026-10-23','11:16:23','LOGOUT','Autenticación','127.0.0.1'),
(4293,17,'2026-10-23','11:16:34','LOGIN','Autenticación','127.0.0.1'),
(4294,17,'2026-10-23','11:17:01','LOGOUT','Autenticación','127.0.0.1'),
(4295,7,'2026-10-23','11:17:08','LOGIN','Autenticación','127.0.0.1'),
(4296,7,'2026-10-23','11:25:25','LOGOUT','Autenticación','127.0.0.1'),
(4297,17,'2026-10-23','11:25:38','LOGIN','Autenticación','127.0.0.1'),
(4298,7,'2026-10-23','13:00:02','LOGIN','Autenticación','127.0.0.1'),
(4299,7,'2026-10-23','13:00:21','LOGOUT','Autenticación','127.0.0.1'),
(4300,17,'2026-10-23','13:00:33','LOGIN','Autenticación','127.0.0.1'),
(4301,2,'2026-10-23','13:08:24','LOGIN','Autenticación','127.0.0.1'),
(4302,2,'2026-10-23','13:08:56','LOGOUT','Autenticación','127.0.0.1'),
(4303,17,'2026-10-23','13:14:26','LOGIN','Autenticación','127.0.0.1'),
(4304,17,'2026-10-23','15:15:10','LOGIN','Autenticación','127.0.0.1'),
(4305,17,'2026-10-23','15:21:40','ACTUALIZACIÓN DE RECAUDOS del estudiante Erickmar Sanchez (V2202586313) · pendiente: Autorización de','Recaudos','127.0.0.1'),
(4306,17,'2026-10-23','17:11:02','LOGOUT','Autenticación','127.0.0.1'),
(4307,7,'2026-10-23','17:11:17','LOGIN','Autenticación','127.0.0.1'),
(4308,7,'2026-10-23','18:41:47','LOGOUT','Autenticación','127.0.0.1'),
(4309,17,'2026-10-23','18:42:12','LOGIN','Autenticación','127.0.0.1'),
(4310,17,'2026-10-23','18:42:44','EMISIÓN DE CONSTANCIA (ESTUDIO): Ana Patricia López Toledo (V1212586313)','Constancias','127.0.0.1'),
(4311,17,'2026-10-23','18:42:59','EMISIÓN DE CONSTANCIA (ESTUDIO): Erickmar Margarita Sanchez Lopez (V2202586313)','Constancias','127.0.0.1'),
(4312,17,'2026-10-23','18:44:42','EMISIÓN DE CONSTANCIA (ESTUDIO): Juan Torrealba Pinto Garcia (38524698)','Constancias','127.0.0.1'),
(4313,17,'2026-10-23','18:45:15','LOGOUT','Autenticación','127.0.0.1'),
(4314,2,'2026-10-23','18:45:26','LOGIN','Autenticación','127.0.0.1'),
(4315,2,'2026-10-23','18:48:39','LOGOUT','Autenticación','127.0.0.1'),
(4316,17,'2026-10-23','18:49:25','LOGIN','Autenticación','127.0.0.1'),
(4317,17,'2026-10-23','18:50:20','LOGOUT','Autenticación','127.0.0.1'),
(4318,7,'2026-10-23','18:50:39','LOGIN','Autenticación','127.0.0.1'),
(4319,7,'2026-10-23','19:00:27','LOGOUT','Autenticación','127.0.0.1'),
(4320,17,'2026-10-23','19:00:43','LOGIN','Autenticación','127.0.0.1'),
(4321,17,'2026-10-23','19:52:49','LOGIN','Autenticación','127.0.0.1'),
(4322,17,'2026-10-23','20:16:08','LOGOUT','Autenticación','127.0.0.1'),
(4323,8,'2026-10-23','20:16:23','LOGIN','Autenticación','127.0.0.1'),
(4324,8,'2026-10-23','20:23:56','CIERRE SECCIÓN LITERALES: estruct 1537','Resultado académico','127.0.0.1'),
(4325,8,'2026-10-23','20:25:19','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Ana Patricia López Toledo (V1212586313, L','Evaluación','127.0.0.1'),
(4326,8,'2026-10-23','20:25:24','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Lhia Ana Perez Ruiz (V1201234567, Literal','Evaluación','127.0.0.1'),
(4327,8,'2026-10-23','20:33:06','LOGOUT','Autenticación','127.0.0.1'),
(4328,7,'2026-10-23','20:33:33','LOGIN','Autenticación','127.0.0.1'),
(4329,7,'2026-10-23','20:34:14','REAPERTURA SECCIÓN LITERALES: estruct 1537 · la docente se equivoco','Resultado académico','127.0.0.1'),
(4330,7,'2026-10-23','20:34:25','LOGOUT','Autenticación','127.0.0.1'),
(4331,17,'2026-10-23','20:34:39','LOGIN','Autenticación','127.0.0.1'),
(4332,17,'2026-10-23','20:34:51','LOGOUT','Autenticación','127.0.0.1'),
(4333,8,'2026-10-23','20:35:04','LOGIN','Autenticación','127.0.0.1'),
(4334,8,'2026-10-23','20:35:51','LITERALES: 5 guardados (4 prom, 1 no prom, 0 sin lit) · 0 omitidos · estruct 1537','Resultado académico','127.0.0.1'),
(4335,8,'2026-10-23','20:36:23','LITERALES: 5 guardados (4 prom, 1 no prom, 0 sin lit) · 0 omitidos · estruct 1537','Resultado académico','127.0.0.1'),
(4336,8,'2026-10-23','20:36:51','LOGOUT','Autenticación','127.0.0.1'),
(4337,7,'2026-10-24','08:00:16','LOGIN','Autenticación','127.0.0.1'),
(4338,7,'2026-10-24','08:21:14','LOGOUT','Autenticación','127.0.0.1'),
(4339,8,'2026-10-24','08:21:27','LOGIN','Autenticación','127.0.0.1'),
(4340,8,'2026-10-24','08:25:05','EMISIÓN MASIVA DE CERTIFICADOS DE CALIFICACIONES (Art. 108 RGLOE): Sección id 1537 (5 estudiantes)','Evaluación','127.0.0.1'),
(4341,8,'2026-10-24','08:55:20','LOGOUT','Autenticación','127.0.0.1'),
(4342,7,'2026-10-24','08:55:31','LOGIN','Autenticación','127.0.0.1'),
(4343,8,'2026-10-24','09:53:01','LOGIN','Autenticación','127.0.0.1'),
(4344,8,'2026-10-24','09:53:08','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Ana Patricia López Toledo (V1212586313, L','Evaluación','127.0.0.1'),
(4345,8,'2026-10-24','09:54:46','LOGOUT','Autenticación','127.0.0.1'),
(4346,17,'2026-10-24','09:55:08','LOGIN','Autenticación','127.0.0.1'),
(4347,17,'2026-10-24','09:55:17','EMISIÓN DE CONSTANCIA (PROSECUCION): Juan Fabricio Ojeda Suarez (V6212250928)','Constancias','127.0.0.1'),
(4348,2,'2026-10-24','12:01:29','LOGIN','Autenticación','127.0.0.1'),
(4349,2,'2026-10-24','12:02:07','LOGOUT','Autenticación','127.0.0.1'),
(4350,7,'2026-10-24','12:02:19','LOGIN','Autenticación','127.0.0.1'),
(4351,2,'2026-10-24','18:18:34','LOGIN','Autenticación','127.0.0.1'),
(4352,2,'2026-10-24','19:05:15','Generar reporte · Bitácora (sin filtros adicionales, 274 registros)','Reportes','127.0.0.1'),
(4353,2,'2026-10-24','19:05:47','Generar reporte · Bitácora (sin filtros adicionales, 275 registros)','Reportes','127.0.0.1'),
(4354,2,'2026-10-24','19:05:57','Generar reporte · Bitácora (sin filtros adicionales, 276 registros)','Reportes','127.0.0.1'),
(4355,2,'2026-10-24','19:07:01','Generar reporte · Listado de usuarios (sin filtros adicionales, 22 registros)','Reportes','127.0.0.1'),
(4356,2,'2026-10-24','19:08:01','Generar reporte · Bitácora (desde: 2026-10-23, hasta: 2026-10-24, 96 registros)','Reportes','127.0.0.1'),
(4357,2,'2026-10-24','19:11:09','Generar reporte · Bitácora (accion: password, 1 registros)','Reportes','127.0.0.1'),
(4358,2,'2026-10-24','19:21:02','Generar reporte · Listado de usuarios (sin filtros adicionales, 22 registros)','Reportes','127.0.0.1'),
(4359,2,'2026-10-24','19:31:40','LOGOUT','Autenticación','127.0.0.1'),
(4360,7,'2026-10-24','19:32:46','LOGIN','Autenticación','127.0.0.1'),
(4361,7,'2026-10-24','19:34:09','LOGOUT','Autenticación','127.0.0.1'),
(4362,17,'2026-10-24','19:34:38','LOGIN','Autenticación','127.0.0.1'),
(4363,7,'2026-10-25','06:13:20','LOGIN','Autenticación','::1'),
(4364,17,'2026-10-25','06:13:21','LOGIN','Autenticación','::1'),
(4365,8,'2026-10-25','06:13:22','LOGIN','Autenticación','::1'),
(4366,2,'2026-10-25','06:13:23','LOGIN','Autenticación','::1'),
(4367,8,'2026-10-25','06:13:34','LOGIN','Autenticación','::1'),
(4368,8,'2026-10-25','06:14:40','LOGIN','Autenticación','::1'),
(4369,8,'2026-10-25','06:15:10','LOGIN','Autenticación','::1'),
(4370,8,'2026-10-25','06:16:56','LOGIN','Autenticación','::1'),
(4371,8,'2026-10-25','06:17:08','LOGIN','Autenticación','::1'),
(4372,8,'2026-10-25','06:17:19','LOGIN','Autenticación','::1'),
(4373,8,'2026-10-25','06:17:37','LOGIN','Autenticación','::1'),
(4374,8,'2026-10-25','06:18:32','LOGIN','Autenticación','::1'),
(4375,17,'2026-10-25','06:31:21','LOGIN','Autenticación','::1'),
(4376,17,'2026-10-25','06:32:19','LOGIN','Autenticación','::1'),
(4377,17,'2026-10-25','06:33:40','LOGIN','Autenticación','::1'),
(4378,17,'2026-10-25','06:35:06','LOGIN','Autenticación','::1'),
(4379,17,'2026-10-25','06:35:08','EMISIÓN DE CONSTANCIA (ESTUDIO): Ana Patricia López Toledo (V1212586313)','Constancias','::1'),
(4380,8,'2026-10-25','06:38:36','LOGIN','Autenticación','::1'),
(4381,8,'2026-10-25','06:38:40','LOGIN','Autenticación','127.0.0.1'),
(4382,8,'2026-10-25','06:40:05','LOGIN','Autenticación','::1'),
(4383,8,'2026-10-25','06:40:50','LOGIN','Autenticación','::1'),
(4384,8,'2026-10-25','06:41:24','LOGIN','Autenticación','::1'),
(4385,8,'2026-10-25','06:42:43','LOGIN','Autenticación','::1'),
(4386,8,'2026-10-25','06:43:05','LOGIN','Autenticación','::1'),
(4387,8,'2026-10-25','06:43:46','LOGIN','Autenticación','::1'),
(4388,8,'2026-10-25','06:46:04','LOGIN','Autenticación','::1'),
(4389,8,'2026-10-25','06:46:31','LOGIN','Autenticación','::1'),
(4390,8,'2026-10-25','06:47:38','LOGIN','Autenticación','::1'),
(4391,8,'2026-10-25','06:48:08','LOGOUT','Autenticación','127.0.0.1'),
(4392,2,'2026-10-25','06:48:20','LOGIN','Autenticación','127.0.0.1'),
(4393,8,'2026-10-25','06:48:37','LOGIN','Autenticación','::1'),
(4394,8,'2026-10-25','06:48:43','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Ana Patricia López Toledo (V1212586313, L','Evaluación','::1'),
(4395,17,'2026-10-25','06:49:15','LOGIN','Autenticación','::1'),
(4396,17,'2026-10-25','06:50:07','LOGIN','Autenticación','::1'),
(4397,8,'2026-10-25','06:50:09','LOGIN','Autenticación','::1'),
(4398,8,'2026-10-25','06:50:58','LOGIN','Autenticación','::1'),
(4399,8,'2026-10-25','06:51:07','LOGOUT','Autenticación','::1'),
(4400,17,'2026-10-25','06:51:08','LOGIN','Autenticación','::1'),
(4401,2,'2026-10-25','06:51:16','Generar reporte · Bitácora (sin filtros adicionales, 323 registros)','Reportes','127.0.0.1'),
(4402,2,'2026-10-25','06:51:31','Generar reporte · Listado de usuarios (sin filtros adicionales, 22 registros)','Reportes','127.0.0.1'),
(4403,7,'2026-10-25','06:55:58','LOGIN','Autenticación','::1'),
(4404,7,'2026-10-25','06:56:08','LOGIN','Autenticación','::1'),
(4405,7,'2026-10-25','06:56:35','LOGIN','Autenticación','::1'),
(4406,7,'2026-10-25','06:56:49','LOGIN','Autenticación','::1'),
(4407,7,'2026-10-25','06:57:04','LOGIN','Autenticación','::1'),
(4408,7,'2026-10-25','06:57:57','LOGIN','Autenticación','::1'),
(4409,2,'2026-10-25','06:58:43','LOGOUT','Autenticación','127.0.0.1'),
(4410,8,'2026-10-25','06:59:07','LOGIN','Autenticación','127.0.0.1'),
(4411,8,'2026-10-25','06:59:42','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Ana Patricia López Toledo (V1212586313, L','Evaluación','127.0.0.1'),
(4412,7,'2026-10-25','07:00:19','LOGIN','Autenticación','::1'),
(4413,8,'2026-10-25','07:02:37','LOGOUT','Autenticación','127.0.0.1'),
(4414,2,'2026-10-25','07:02:48','LOGIN','Autenticación','127.0.0.1'),
(4415,7,'2026-10-25','07:03:26','LOGIN','Autenticación','::1'),
(4416,7,'2026-10-25','07:03:56','LOGIN','Autenticación','::1'),
(4417,7,'2026-10-25','07:05:31','LOGIN','Autenticación','::1'),
(4418,7,'2026-10-25','07:07:51','LOGIN','Autenticación','::1'),
(4419,7,'2026-10-25','07:08:11','LOGIN','Autenticación','::1'),
(4420,7,'2026-10-25','07:09:12','LOGIN','Autenticación','::1'),
(4421,17,'2026-10-25','07:10:44','LOGIN','Autenticación','::1'),
(4422,8,'2026-10-25','07:11:09','LOGIN','Autenticación','::1'),
(4423,7,'2026-10-25','07:12:43','LOGIN','Autenticación','::1'),
(4424,7,'2026-10-25','07:15:31','LOGIN','Autenticación','::1'),
(4425,7,'2026-10-25','07:16:22','LOGIN','Autenticación','::1'),
(4426,7,'2026-10-25','07:17:38','LOGIN','Autenticación','::1'),
(4427,7,'2026-10-25','07:18:56','LOGIN','Autenticación','::1'),
(4428,7,'2026-10-25','07:19:29','LOGIN','Autenticación','::1'),
(4429,7,'2026-10-25','07:20:22','LOGIN','Autenticación','::1'),
(4430,17,'2026-10-25','07:34:52','LOGIN','Autenticación','::1'),
(4431,17,'2026-10-25','07:36:31','LOGIN','Autenticación','::1'),
(4432,17,'2026-10-25','07:39:18','LOGIN','Autenticación','::1'),
(4433,17,'2026-10-25','07:40:28','LOGIN','Autenticación','::1'),
(4434,17,'2026-10-25','07:41:20','LOGIN','Autenticación','::1'),
(4435,17,'2026-10-25','07:42:26','LOGIN','Autenticación','::1'),
(4436,17,'2026-10-25','07:44:08','LOGIN','Autenticación','::1'),
(4437,17,'2026-10-25','07:44:10','LOGIN','Autenticación','::1'),
(4438,17,'2026-10-25','07:44:12','LOGIN','Autenticación','::1'),
(4439,17,'2026-10-25','07:44:16','LOGIN','Autenticación','::1'),
(4440,17,'2026-10-25','07:44:19','LOGIN','Autenticación','::1'),
(4441,17,'2026-10-25','07:44:22','LOGIN','Autenticación','::1'),
(4442,17,'2026-10-25','07:44:25','LOGIN','Autenticación','::1'),
(4443,17,'2026-10-25','07:45:05','LOGIN','Autenticación','::1'),
(4444,2,'2026-10-25','07:45:24','LOGIN','Autenticación','127.0.0.1'),
(4445,17,'2026-10-25','07:45:33','LOGIN','Autenticación','::1'),
(4446,17,'2026-10-25','07:46:29','LOGIN','Autenticación','::1'),
(4447,17,'2026-10-25','07:46:32','LOGIN','Autenticación','::1'),
(4448,17,'2026-10-25','07:46:35','LOGIN','Autenticación','::1'),
(4449,17,'2026-10-25','07:46:37','LOGIN','Autenticación','::1'),
(4450,17,'2026-10-25','07:46:40','LOGIN','Autenticación','::1'),
(4451,2,'2026-10-25','07:47:44','LOGOUT','Autenticación','127.0.0.1'),
(4452,8,'2026-10-25','07:47:58','LOGIN','Autenticación','127.0.0.1'),
(4453,8,'2026-10-25','07:48:34','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Ana Patricia López Toledo (V1212586313, L','Evaluación','127.0.0.1'),
(4454,7,'2026-10-25','07:49:59','LOGIN','Autenticación','::1'),
(4455,7,'2026-10-25','07:50:02','LOGIN','Autenticación','::1'),
(4456,7,'2026-10-25','07:50:05','LOGIN','Autenticación','::1'),
(4457,7,'2026-10-25','07:50:07','LOGIN','Autenticación','::1'),
(4458,7,'2026-10-25','07:50:55','LOGIN','Autenticación','::1'),
(4459,7,'2026-10-25','07:52:21','LOGIN','Autenticación','::1'),
(4460,7,'2026-10-25','07:53:10','LOGIN','Autenticación','::1'),
(4461,7,'2026-10-25','07:54:33','LOGIN','Autenticación','::1'),
(4462,8,'2026-10-25','07:54:35','LOGOUT','Autenticación','127.0.0.1'),
(4463,2,'2026-10-25','07:54:46','LOGIN','Autenticación','127.0.0.1'),
(4464,7,'2026-10-25','07:55:08','LOGIN','Autenticación','::1'),
(4465,7,'2026-10-25','07:55:46','LOGIN','Autenticación','::1'),
(4466,7,'2026-10-25','07:55:49','LOGIN','Autenticación','::1'),
(4467,7,'2026-10-25','07:55:52','LOGIN','Autenticación','::1'),
(4468,7,'2026-10-25','07:55:55','LOGIN','Autenticación','::1'),
(4469,7,'2026-10-25','07:55:58','LOGIN','Autenticación','::1'),
(4470,8,'2026-10-25','07:56:01','LOGIN','Autenticación','::1'),
(4471,8,'2026-10-25','07:56:03','LOGIN','Autenticación','::1'),
(4472,8,'2026-10-25','07:56:06','LOGIN','Autenticación','::1'),
(4473,8,'2026-10-25','07:56:09','LOGIN','Autenticación','::1'),
(4474,8,'2026-10-25','07:56:12','LOGIN','Autenticación','::1'),
(4475,8,'2026-10-25','07:56:50','LOGIN','Autenticación','::1'),
(4476,7,'2026-10-25','07:58:17','LOGIN','Autenticación','::1'),
(4477,7,'2026-10-25','07:58:20','LOGIN','Autenticación','::1'),
(4478,7,'2026-10-25','07:58:22','LOGIN','Autenticación','::1'),
(4479,7,'2026-10-25','07:58:25','LOGIN','Autenticación','::1'),
(4480,7,'2026-10-25','07:58:28','LOGIN','Autenticación','::1'),
(4481,8,'2026-10-25','07:58:31','LOGIN','Autenticación','::1'),
(4482,8,'2026-10-25','07:58:33','LOGIN','Autenticación','::1'),
(4483,8,'2026-10-25','07:58:36','LOGIN','Autenticación','::1'),
(4484,8,'2026-10-25','07:58:38','LOGIN','Autenticación','::1'),
(4485,8,'2026-10-25','07:58:41','LOGIN','Autenticación','::1'),
(4486,7,'2026-10-25','07:59:12','LOGIN','Autenticación','::1'),
(4487,7,'2026-10-25','07:59:37','LOGIN','Autenticación','::1'),
(4488,2,'2026-10-25','08:14:16','LOGIN','Autenticación','127.0.0.1'),
(4489,2,'2026-10-25','08:14:21','Generar reporte · Bitácora (sin filtros adicionales, 411 registros)','Bitácora','127.0.0.1'),
(4490,2,'2026-10-25','08:14:54','LOGIN','Autenticación','::1'),
(4491,2,'2026-10-25','08:14:55','Generar reporte · Bitácora (sin filtros adicionales, 413 registros)','Bitácora','::1'),
(4492,2,'2026-10-25','08:14:59','Generar reporte · Bitácora (sin filtros adicionales, 414 registros)','Bitácora','::1'),
(4493,2,'2026-10-25','08:15:04','Generar reporte · Bitácora (sin filtros adicionales, 415 registros)','Bitácora','::1'),
(4494,2,'2026-10-25','08:15:39','Generar reporte · Bitácora (sin filtros adicionales, 416 registros)','Bitácora','127.0.0.1'),
(4495,2,'2026-10-25','08:16:03','Generar reporte · Bitácora (sin filtros adicionales, 417 registros)','Bitácora','127.0.0.1'),
(4496,2,'2026-10-25','08:16:40','Generar reporte · Bitácora (sin filtros adicionales, 418 registros)','Bitácora','127.0.0.1'),
(4497,2,'2026-10-25','08:17:15','LOGIN','Autenticación','::1'),
(4498,2,'2026-10-25','08:17:43','LOGIN','Autenticación','::1'),
(4499,2,'2026-10-25','08:17:44','Generar reporte · Bitácora (sin filtros adicionales, 421 registros)','Bitácora','::1'),
(4500,2,'2026-10-25','08:17:49','Generar reporte · Bitácora (sin filtros adicionales, 422 registros)','Bitácora','::1'),
(4501,2,'2026-10-25','08:17:54','Generar reporte · Bitácora (sin filtros adicionales, 423 registros)','Bitácora','::1'),
(4502,2,'2026-10-25','08:18:37','LOGIN','Autenticación','::1'),
(4503,2,'2026-10-25','08:18:38','Generar reporte · Bitácora (accion: login, 261 registros)','Bitácora','::1'),
(4504,2,'2026-10-25','08:18:42','Generar reporte · Bitácora (sin filtros adicionales, 426 registros)','Bitácora','::1'),
(4505,2,'2026-10-25','08:34:25','LOGIN','Autenticación','::1'),
(4506,2,'2026-10-25','08:35:20','LOGIN','Autenticación','::1'),
(4507,2,'2026-10-25','08:35:21','Exportar PDF · Bitácora · 429 regs · 01/01/2020–25/10/2026','Bitácora','::1'),
(4508,2,'2026-10-25','08:35:37','LOGIN','Autenticación','::1'),
(4509,2,'2026-10-25','08:36:12','LOGIN','Autenticación','::1'),
(4510,2,'2026-10-25','08:36:14','Exportar PDF · Bitácora · 432 regs · 01/01/2020–25/10/2026','Bitácora','::1'),
(4511,2,'2026-10-25','08:36:21','Exportar PDF · Cuentas de Usuario · 22 regs · Rol: Todos · Estado: Todos','Cuentas de Usuario','::1'),
(4512,2,'2026-10-25','08:36:29','LOGIN','Autenticación','::1'),
(4513,2,'2026-10-25','08:36:30','Exportar PDF · Bitácora · 435 regs · 01/01/2020–25/10/2026','Bitácora','::1'),
(4514,2,'2026-10-25','08:36:38','Exportar PDF · Cuentas de Usuario · 22 regs · Rol: Todos · Estado: Todos','Cuentas de Usuario','::1'),
(4515,2,'2026-10-25','08:37:32','LOGIN','Autenticación','::1'),
(4516,2,'2026-10-25','08:37:33','Exportar PDF · Bitácora · 153 regs · 25/10/2026–25/10/2026','Bitácora','::1'),
(4517,2,'2026-10-25','08:37:34','Exportar PDF · Bitácora · 267 regs · 01/01/2020–25/10/2026 · Acción: Inicio de sesión','Bitácora','::1'),
(4518,2,'2026-10-25','08:37:37','Exportar PDF · Cuentas de Usuario · 6 regs · Rol: Todos · Estado: Inactivo','Cuentas de Usuario','::1'),
(4519,2,'2026-10-25','08:38:15','LOGIN','Autenticación','::1'),
(4520,2,'2026-10-25','08:38:16','Exportar PDF · Bitácora · 157 regs · 25/10/2026–25/10/2026','Bitácora','::1'),
(4521,2,'2026-10-25','08:38:18','Exportar PDF · Bitácora · 268 regs · 01/01/2020–25/10/2026 · Acción: Inicio de sesión','Bitácora','::1'),
(4522,2,'2026-10-25','08:38:20','Exportar PDF · Cuentas de Usuario · 6 regs · Rol: Todos · Estado: Inactivo','Cuentas de Usuario','::1'),
(4523,2,'2026-10-25','08:38:22','Exportar PDF · Cuentas de Usuario · 7 regs · Rol: Admin · Estado: Todos','Cuentas de Usuario','::1'),
(4524,2,'2026-10-25','08:40:19','LOGIN','Autenticación','::1'),
(4525,2,'2026-10-25','08:42:02','LOGIN','Autenticación','::1'),
(4526,2,'2026-10-25','08:42:39','LOGIN','Autenticación','::1'),
(4527,2,'2026-10-25','08:57:01','ELIMINACIÓN / RESTABLECIMIENTO DE IMAGEN · plantel_logo','Configuración','127.0.0.1'),
(4528,2,'2026-10-25','09:03:18','Exportar PDF · Bitácora · 165 regs · 25/10/2026–25/10/2026','Bitácora','127.0.0.1'),
(4529,2,'2026-10-25','09:22:08','LOGIN','Autenticación','::1'),
(4530,2,'2026-10-25','09:22:09','Exportar PDF · Bitácora · 135 regs · 25/10/2026–25/10/2026 · Acción: Inicio de sesión','Bitácora','::1'),
(4531,2,'2026-10-25','09:22:29','LOGIN','Autenticación','::1'),
(4532,2,'2026-10-25','09:22:30','Exportar PDF · Bitácora · 136 regs · 25/10/2026–25/10/2026 · Acción: Inicio de sesión','Bitácora','::1'),
(4533,2,'2026-10-25','09:22:32','Exportar PDF · Bitácora · 136 regs · 25/10/2026–25/10/2026 · Acción: Inicio de sesión','Bitácora','::1'),
(4534,2,'2026-10-25','09:22:33','Exportar PDF · Bitácora · 136 regs · 25/10/2026–25/10/2026 · Acción: Inicio de sesión','Bitácora','::1'),
(4535,2,'2026-10-25','09:22:51','LOGIN','Autenticación','::1'),
(4536,2,'2026-10-25','09:22:52','Exportar PDF · Bitácora · 137 regs · 25/10/2026–25/10/2026 · Acción: Inicio de sesión','Bitácora','::1'),
(4537,2,'2026-10-25','09:22:54','Exportar PDF · Bitácora · 137 regs · 25/10/2026–25/10/2026 · Acción: Inicio de sesión','Bitácora','::1'),
(4538,2,'2026-10-25','09:22:55','Exportar PDF · Bitácora · 137 regs · 25/10/2026–25/10/2026 · Acción: Inicio de sesión','Bitácora','::1'),
(4539,2,'2026-10-25','09:23:50','LOGIN','Autenticación','::1'),
(4540,2,'2026-10-25','09:23:51','Exportar PDF · Bitácora · 138 regs · 25/10/2026–25/10/2026 · Acción: Inicio de sesión','Bitácora','::1'),
(4541,2,'2026-10-25','09:24:29','LOGIN','Autenticación','::1'),
(4542,2,'2026-10-25','09:24:30','Exportar PDF · Bitácora · 27 regs · 25/10/2026–25/10/2026 · Acción: Inicio de sesión · q: admin','Bitácora','::1'),
(4543,2,'2026-10-25','09:24:30','Exportar PDF · Bitácora · 27 regs · 25/10/2026–25/10/2026 · Acción: Inicio de sesión · q: admin','Bitácora','::1'),
(4544,2,'2026-10-25','09:24:31','Exportar PDF · Bitácora · 27 regs · 25/10/2026–25/10/2026 · Acción: Inicio de sesión · q: admin','Bitácora','::1'),
(4545,2,'2026-10-25','09:25:00','LOGIN','Autenticación','::1'),
(4546,2,'2026-10-25','09:25:40','LOGIN','Autenticación','::1'),
(4547,2,'2026-10-25','09:25:41','Exportar PDF · Bitácora · 141 regs · 25/10/2026–25/10/2026 · Acción: Inicio de sesión','Bitácora','::1'),
(4548,2,'2026-10-25','09:26:45','LOGIN','Autenticación','::1'),
(4549,2,'2026-10-25','09:26:46','Exportar PDF · Bitácora · 30 regs · 25/10/2026–25/10/2026 · Acción: Inicio de sesión · q: admin','Bitácora','::1'),
(4550,2,'2026-10-25','09:27:16','LOGIN','Autenticación','::1'),
(4551,2,'2026-10-25','09:27:17','Exportar PDF · Bitácora · 31 regs · 25/10/2026–25/10/2026 · Acción: Inicio de sesión · q: admin','Bitácora','::1'),
(4552,2,'2026-10-25','09:27:18','Exportar PDF · Bitácora · 31 regs · 25/10/2026–25/10/2026 · Acción: Inicio de sesión · q: admin','Bitácora','::1'),
(4553,2,'2026-10-25','09:27:18','Exportar PDF · Bitácora · 31 regs · 25/10/2026–25/10/2026 · Acción: Inicio de sesión · q: admin','Bitácora','::1'),
(4554,2,'2026-10-25','09:28:53','LOGOUT','Autenticación','127.0.0.1'),
(4555,7,'2026-10-25','09:29:09','LOGIN','Autenticación','127.0.0.1'),
(4556,7,'2026-10-25','10:59:12','LOGOUT','Autenticación','127.0.0.1'),
(4557,17,'2026-10-25','10:59:23','LOGIN','Autenticación','127.0.0.1'),
(4558,7,'2026-10-25','11:00:37','LOGIN','Autenticación','::1'),
(4559,2,'2026-10-25','11:01:19','LOGIN','Autenticación','::1'),
(4560,2,'2026-10-25','11:01:27','LOGIN','Autenticación','::1'),
(4561,7,'2026-10-25','11:02:16','LOGIN','Autenticación','::1'),
(4562,7,'2026-10-25','11:02:17','DESASIGNACIÓN · Valentina Jiménez · 1° Grado·A·Mañana','Asignación Docente','::1'),
(4563,7,'2026-10-25','11:02:24','LOGIN','Autenticación','::1'),
(4564,7,'2026-10-25','11:02:43','LOGIN','Autenticación','::1'),
(4565,7,'2026-10-25','11:03:42','LOGIN','Autenticación','::1'),
(4566,7,'2026-10-25','11:04:03','LOGIN','Autenticación','::1'),
(4567,7,'2026-10-25','11:04:20','LOGIN','Autenticación','::1'),
(4568,7,'2026-10-25','11:04:22','DESASIGNACIÓN · Valentina Jiménez · 1° Grado·A·Mañana','Asignación Docente','::1'),
(4569,7,'2026-10-25','11:05:10','LOGIN','Autenticación','::1'),
(4570,7,'2026-10-25','11:05:54','LOGIN','Autenticación','::1'),
(4571,7,'2026-10-25','11:06:14','LOGIN','Autenticación','::1'),
(4572,7,'2026-10-25','11:07:27','LOGIN','Autenticación','::1'),
(4573,7,'2026-10-25','11:39:08','LOGIN','Autenticación','::1'),
(4574,7,'2026-10-25','11:39:09','DESASIGNACIÓN · Valentina Jiménez · 1° Grado·A·Mañana','Asignación Docente','::1'),
(4575,7,'2026-10-25','11:39:17','LOGIN','Autenticación','::1'),
(4576,7,'2026-10-25','11:39:23','LOGIN','Autenticación','::1'),
(4577,7,'2026-10-25','11:39:38','LOGIN','Autenticación','::1'),
(4578,7,'2026-10-25','11:39:39','DESASIGNACIÓN · Valentina Jiménez · 1° Grado·A·Mañana','Asignación Docente','::1'),
(4579,7,'2026-10-25','11:40:19','LOGIN','Autenticación','::1'),
(4580,7,'2026-10-25','11:40:33','LOGIN','Autenticación','::1'),
(4581,7,'2026-10-25','11:40:45','LOGIN','Autenticación','::1'),
(4582,7,'2026-10-25','11:40:47','DESASIGNACIÓN · Valentina Jiménez · 1° Grado·A·Mañana','Asignación Docente','::1'),
(4583,2,'2026-10-25','11:40:49','LOGIN','Autenticación','::1'),
(4584,7,'2026-10-25','11:40:55','LOGIN','Autenticación','::1'),
(4585,7,'2026-10-25','11:41:03','LOGIN','Autenticación','::1'),
(4586,7,'2026-10-25','11:41:04','DESASIGNACIÓN · Valentina Jiménez · 1° Grado·A·Mañana','Asignación Docente','::1'),
(4587,2,'2026-10-25','11:41:06','LOGIN','Autenticación','::1'),
(4588,2,'2026-10-25','11:41:33','LOGIN','Autenticación','::1'),
(4589,2,'2026-10-25','11:42:36','LOGIN','Autenticación','::1'),
(4590,2,'2026-10-25','11:43:53','LOGIN','Autenticación','::1'),
(4591,2,'2026-10-25','11:45:23','LOGIN','Autenticación','::1'),
(4592,1966,'2026-10-25','11:45:26','LOGIN','Autenticación','::1'),
(4593,1966,'2026-10-25','11:45:29','CAMBIO_PASSWORD','Autenticación','::1'),
(4594,1966,'2026-10-25','11:45:29','LOGIN','Autenticación','::1'),
(4595,17,'2026-10-25','12:35:55','EMISIÓN DE CONSTANCIA (ESTUDIO): Ana Patricia López Toledo (V1212586313)','Constancias','127.0.0.1'),
(4596,17,'2026-10-25','12:43:41','EMISIÓN DE CONSTANCIA (ESTUDIO): Ana Patricia López Toledo (V1212586313)','Constancias','127.0.0.1'),
(4597,17,'2026-10-25','12:44:36','EMISIÓN DE CONSTANCIA (ESTUDIO): Ana Patricia López Toledo (V1212586313)','Constancias','127.0.0.1'),
(4598,17,'2026-10-25','12:47:48','LOGOUT','Autenticación','127.0.0.1'),
(4599,17,'2026-10-25','12:48:05','LOGIN','Autenticación','127.0.0.1'),
(4600,17,'2026-10-25','12:48:27','EMISIÓN DE CONSTANCIA (ESTUDIO): Ana Patricia López Toledo (V1212586313)','Constancias','127.0.0.1'),
(4601,17,'2026-10-25','12:48:31','LOGOUT','Autenticación','127.0.0.1'),
(4602,8,'2026-10-25','12:48:41','LOGIN','Autenticación','127.0.0.1'),
(4603,2,'2026-10-25','15:16:03','LOGIN','Autenticación','127.0.0.1'),
(4604,2,'2026-10-25','15:50:59','LOGOUT','Autenticación','127.0.0.1'),
(4605,7,'2026-10-25','15:51:14','LOGIN','Autenticación','127.0.0.1'),
(4606,7,'2026-10-25','16:02:10','LOGOUT','Autenticación','127.0.0.1'),
(4607,2,'2026-10-25','16:02:25','LOGIN','Autenticación','127.0.0.1'),
(4608,2,'2026-10-25','16:10:23','LOGOUT','Autenticación','127.0.0.1'),
(4609,7,'2026-10-25','16:10:37','LOGIN','Autenticación','127.0.0.1'),
(4610,8,'2026-10-25','16:43:30','LOGIN','Autenticación','127.0.0.1'),
(4611,8,'2026-10-25','16:44:18','LOGOUT','Autenticación','127.0.0.1'),
(4612,7,'2026-10-25','16:44:29','LOGIN','Autenticación','127.0.0.1'),
(4613,7,'2026-10-25','17:01:08','LOGOUT','Autenticación','127.0.0.1'),
(4614,17,'2026-10-25','17:01:22','LOGIN','Autenticación','127.0.0.1'),
(4615,17,'2026-10-25','17:26:22','EMISIÓN DE CONSTANCIA (ESTUDIO): Ana Patricia López Toledo (V1212586313)','Constancias','127.0.0.1'),
(4616,17,'2026-10-25','17:29:44','LOGOUT','Autenticación','127.0.0.1'),
(4617,8,'2026-10-25','17:29:55','LOGIN','Autenticación','127.0.0.1'),
(4618,8,'2026-10-25','17:37:48','LOGOUT','Autenticación','127.0.0.1'),
(4619,2,'2026-10-25','17:38:20','LOGIN','Autenticación','127.0.0.1'),
(4620,2,'2026-10-26','05:35:17','LOGIN','Autenticación','127.0.0.1'),
(4621,2,'2026-10-26','05:57:01','Exportar PDF · Bitácora · 1 regs · 26/10/2026–26/10/2026','Bitácora','127.0.0.1'),
(4622,2,'2026-10-26','05:57:19','Exportar PDF · Cuentas de Usuario · 25 regs · Rol: Todos · Estado: Todos','Cuentas de Usuario','127.0.0.1'),
(4623,2,'2026-10-26','06:02:56','Exportar PDF · Bitácora · 3 regs · 26/10/2026–26/10/2026','Bitácora','127.0.0.1'),
(4624,2,'2026-10-26','06:08:49','LOGOUT','Autenticación','127.0.0.1'),
(4625,17,'2026-10-26','06:16:45','LOGIN','Autenticación','127.0.0.1'),
(4626,17,'2026-10-26','07:53:05','LOGIN','Autenticación','127.0.0.1'),
(4645,2,'2026-10-26','13:33:47','LOGIN','Autenticación','127.0.0.1'),
(4646,2,'2026-10-26','13:35:16','LOGOUT','Autenticación','127.0.0.1'),
(4647,2,'2026-10-26','13:35:27','LOGIN','Autenticación','127.0.0.1'),
(4650,2,'2026-10-26','13:38:06','LOGOUT','Autenticación','127.0.0.1'),
(4651,7,'2026-10-26','13:38:17','LOGIN','Autenticación','127.0.0.1'),
(4652,7,'2026-10-26','13:38:58','LOGIN','Autenticación','127.0.0.1'),
(4653,17,'2026-10-26','13:40:22','LOGIN','Autenticación','127.0.0.1'),
(4654,17,'2026-10-26','13:40:53','EMISIÓN DE CONSTANCIA (ESTUDIO): Ana Patricia López Toledo (V1212586313)','Constancias','127.0.0.1'),
(4655,17,'2026-10-26','13:41:16','LOGOUT','Autenticación','127.0.0.1'),
(4656,7,'2026-10-26','13:41:29','LOGIN','Autenticación','127.0.0.1'),
(4665,7,'2026-10-26','14:07:52','LOGOUT','Autenticación','127.0.0.1'),
(4666,7,'2026-10-26','14:09:05','LOGIN','Autenticación','127.0.0.1'),
(4667,7,'2026-10-26','14:10:37','LOGIN','Autenticación','127.0.0.1'),
(4668,7,'2026-10-26','14:18:34','LOGOUT','Autenticación','127.0.0.1'),
(4669,8,'2026-10-26','14:18:43','LOGIN','Autenticación','127.0.0.1'),
(4670,8,'2026-10-26','14:29:15','LOGIN','Autenticación','127.0.0.1'),
(4671,8,'2026-10-26','14:44:53','LOGIN','Autenticación','127.0.0.1'),
(4672,7,'2026-10-26','14:46:29','LOGIN','Autenticación','127.0.0.1'),
(4673,7,'2026-10-26','14:47:01','LOGOUT','Autenticación','127.0.0.1'),
(4674,17,'2026-10-26','14:47:10','LOGIN','Autenticación','127.0.0.1'),
(4675,17,'2026-10-26','14:47:31','EMISIÓN DE CONSTANCIA (RETIRO): Tarle Arias (V1212250928)','Constancias','127.0.0.1'),
(4676,17,'2026-10-26','14:51:37','LOGOUT','Autenticación','127.0.0.1'),
(4677,8,'2026-10-26','14:52:07','LOGIN','Autenticación','127.0.0.1'),
(4678,8,'2026-10-26','14:52:45','LITERALES: 5 guardados (4 prom, 1 no prom, 0 sin lit) · 0 omitidos · estruct 1537','Resultado académico','127.0.0.1'),
(4679,8,'2026-10-26','14:52:51','LOGOUT','Autenticación','127.0.0.1'),
(4680,2,'2026-10-26','14:53:01','LOGIN','Autenticación','127.0.0.1'),
(4681,2,'2026-10-26','15:06:51','LOGOUT','Autenticación','127.0.0.1'),
(4682,7,'2026-10-26','15:07:01','LOGIN','Autenticación','127.0.0.1'),
(4683,2,'2026-10-26','15:39:29','LOGIN','Autenticación','127.0.0.1'),
(4684,2,'2026-10-26','15:42:59','LOGOUT','Autenticación','127.0.0.1'),
(4685,7,'2026-10-26','15:43:08','LOGIN','Autenticación','127.0.0.1'),
(4686,7,'2026-10-26','15:47:16','LOGOUT','Autenticación','127.0.0.1'),
(4687,17,'2026-10-26','15:47:35','LOGIN','Autenticación','127.0.0.1'),
(4688,17,'2026-10-26','15:48:03','LOGOUT','Autenticación','127.0.0.1'),
(4689,8,'2026-10-26','15:48:31','LOGIN','Autenticación','127.0.0.1'),
(4690,8,'2026-10-26','15:48:44','LITERALES: 5 guardados (4 prom, 1 no prom, 0 sin lit) · 0 omitidos · estruct 1537','Resultado académico','127.0.0.1'),
(4691,8,'2026-10-26','15:49:14','EMISIÓN MASIVA DE CERTIFICADOS DE CALIFICACIONES (Art. 108 RGLOE): Sección id 1537 (5 estudiantes)','Evaluación','127.0.0.1'),
(4692,8,'2026-10-26','15:49:46','LOGOUT','Autenticación','127.0.0.1'),
(4693,7,'2026-10-26','15:49:56','LOGIN','Autenticación','127.0.0.1'),
(4694,7,'2026-10-26','16:17:55','CONFIRMACION: 0 confirmados (0 prom, 0 no prom) · 3 omitidos · estruct 1538','Resultado académico','127.0.0.1'),
(4695,2,'2026-10-26','17:44:06','LOGIN','Autenticación','127.0.0.1'),
(4696,2,'2026-10-26','17:44:37','LOGOUT','Autenticación','127.0.0.1'),
(4697,2,'2026-10-26','17:44:50','LOGIN','Autenticación','127.0.0.1'),
(4698,2,'2026-10-26','17:45:30','LOGOUT','Autenticación','127.0.0.1'),
(4699,7,'2026-10-26','17:45:49','LOGIN','Autenticación','127.0.0.1'),
(4700,7,'2026-10-26','17:57:40','LOGOUT','Autenticación','127.0.0.1'),
(4701,17,'2026-10-26','17:57:51','LOGIN','Autenticación','127.0.0.1'),
(4702,17,'2026-10-26','18:00:23','LOGOUT','Autenticación','127.0.0.1'),
(4703,7,'2026-10-26','18:00:32','LOGIN','Autenticación','127.0.0.1'),
(4704,7,'2026-10-26','18:06:17','LOGOUT','Autenticación','127.0.0.1'),
(4705,17,'2026-10-26','18:06:30','LOGIN','Autenticación','127.0.0.1'),
(4706,17,'2026-10-26','18:10:10','LOGOUT','Autenticación','127.0.0.1'),
(4707,7,'2026-10-26','18:10:24','LOGIN','Autenticación','127.0.0.1'),
(4708,7,'2026-10-26','18:22:48','LOGOUT','Autenticación','127.0.0.1'),
(4709,17,'2026-10-26','18:23:02','LOGIN','Autenticación','127.0.0.1'),
(4710,7,'2026-10-26','20:22:37','LOGIN','Autenticación','127.0.0.1'),
(4711,7,'2026-10-26','20:42:12','CAMBIO ESTADO María Eugenia González Pérez · ACTIVO → INACTIVO','Empleados','127.0.0.1'),
(4712,7,'2026-10-26','20:42:19','CAMBIO ESTADO María Eugenia González Pérez · INACTIVO → ACTIVO','Empleados','127.0.0.1'),
(4713,7,'2026-10-26','21:03:07','LOGOUT','Autenticación','127.0.0.1'),
(4714,17,'2026-10-26','21:04:21','LOGIN','Autenticación','127.0.0.1'),
(4715,7,'2026-10-27','08:59:37','LOGIN','Autenticación','127.0.0.1'),
(4716,7,'2026-10-27','09:10:20','LOGOUT','Autenticación','127.0.0.1'),
(4717,17,'2026-10-27','09:10:38','LOGIN','Autenticación','127.0.0.1'),
(4718,2,'2026-10-27','11:12:14','LOGIN','Autenticación','127.0.0.1'),
(4719,2,'2026-10-27','11:14:57','LOGOUT','Autenticación','127.0.0.1'),
(4720,17,'2026-10-27','11:15:14','LOGIN','Autenticación','127.0.0.1'),
(4721,17,'2026-10-27','11:16:33','EMISIÓN DE CONSTANCIA (ESTUDIO): Ana Patricia López Toledo (V1212586313)','Constancias','127.0.0.1'),
(4722,17,'2026-10-27','11:18:36','LOGOUT','Autenticación','127.0.0.1'),
(4723,7,'2026-10-27','11:19:34','LOGIN','Autenticación','127.0.0.1'),
(4724,7,'2026-10-27','13:37:02','LOGIN','Autenticación','127.0.0.1'),
(4725,7,'2026-10-27','14:40:54','LOGIN','Autenticación','127.0.0.1'),
(4726,7,'2026-10-27','14:55:01','LOGOUT','Autenticación','127.0.0.1'),
(4727,2,'2026-10-27','15:08:17','LOGIN','Autenticación','127.0.0.1'),
(4728,2,'2026-10-27','15:21:12','LOGOUT','Autenticación','127.0.0.1'),
(4729,7,'2026-10-27','15:21:25','LOGIN','Autenticación','127.0.0.1'),
(4730,7,'2026-10-27','15:26:28','LOGOUT','Autenticación','127.0.0.1'),
(4731,2,'2026-10-27','15:26:58','LOGIN','Autenticación','127.0.0.1'),
(4732,2,'2026-10-27','15:40:26','LOGOUT','Autenticación','127.0.0.1'),
(4733,7,'2026-10-27','15:40:37','LOGIN','Autenticación','127.0.0.1'),
(4734,7,'2026-10-27','15:41:21','LOGOUT','Autenticación','127.0.0.1'),
(4735,17,'2026-10-27','15:41:32','LOGIN','Autenticación','127.0.0.1'),
(4736,17,'2026-10-27','15:55:09','LOGOUT','Autenticación','127.0.0.1'),
(4737,7,'2026-10-27','15:55:21','LOGIN','Autenticación','127.0.0.1'),
(4738,7,'2026-10-27','15:57:38','LOGOUT','Autenticación','127.0.0.1'),
(4739,7,'2026-10-27','16:00:41','LOGIN','Autenticación','127.0.0.1'),
(4740,7,'2026-10-27','16:01:53','LOGOUT','Autenticación','127.0.0.1'),
(4741,17,'2026-10-27','16:02:04','LOGIN','Autenticación','127.0.0.1'),
(4742,17,'2026-10-27','16:02:10','EMISIÓN DE CONSTANCIA (ESTUDIO): Ana Patricia López Toledo (V1212586313)','Constancias','127.0.0.1'),
(4743,17,'2026-10-27','16:08:23','EMISIÓN DE CONSTANCIA (ESTUDIO): Ana Patricia López Toledo (V1212586313)','Constancias','127.0.0.1'),
(4744,17,'2026-10-27','16:08:26','LOGOUT','Autenticación','127.0.0.1'),
(4745,7,'2026-10-27','16:08:38','LOGIN','Autenticación','127.0.0.1'),
(4746,7,'2026-10-27','16:20:48','LOGOUT','Autenticación','127.0.0.1'),
(4747,7,'2026-10-27','16:22:01','LOGIN','Autenticación','127.0.0.1'),
(4748,7,'2026-10-27','16:22:17','LOGOUT','Autenticación','127.0.0.1'),
(4749,7,'2026-10-27','16:26:52','LOGIN','Autenticación','127.0.0.1'),
(4750,7,'2026-10-27','16:30:31','LOGOUT','Autenticación','127.0.0.1'),
(4751,2,'2026-10-27','16:55:30','LOGIN','Autenticación','127.0.0.1'),
(4752,2,'2026-10-27','16:56:46','CAMBIO_PASSWORD','Autenticación','127.0.0.1'),
(4753,2,'2026-10-27','16:56:59','LOGIN','Autenticación','127.0.0.1'),
(4754,2,'2026-10-27','17:21:58','LOGOUT','Autenticación','127.0.0.1'),
(4755,3,'2026-10-27','17:22:20','LOGIN','Autenticación','127.0.0.1'),
(4756,3,'2026-10-27','17:22:33','CAMBIO_PASSWORD','Autenticación','127.0.0.1'),
(4757,3,'2026-10-27','17:23:09','LOGIN','Autenticación','127.0.0.1'),
(4758,2,'2026-10-27','20:49:09','LOGIN','Autenticación','127.0.0.1'),
(4759,8,'2026-10-27','22:23:33','LOGIN','Autenticación','127.0.0.1'),
(4760,8,'2026-10-27','22:40:14','LOGOUT','Autenticación','127.0.0.1'),
(4761,17,'2026-10-27','22:41:15','LOGIN','Autenticación','127.0.0.1'),
(4762,17,'2026-10-27','22:41:30','LOGOUT','Autenticación','127.0.0.1'),
(4763,7,'2026-10-27','22:41:46','LOGIN','Autenticación','127.0.0.1'),
(4764,2,'2026-10-28','06:45:31','LOGIN','Autenticación','127.0.0.1'),
(4765,2,'2026-10-28','16:56:00','LOGIN','Autenticación','127.0.0.1'),
(4766,7,'2026-10-28','18:29:44','LOGIN','Autenticación','127.0.0.1'),
(4767,2,'2026-10-29','06:00:21','LOGIN','Autenticación','127.0.0.1'),
(4768,2,'2026-10-29','06:01:22','LOGOUT','Autenticación','127.0.0.1'),
(4769,7,'2026-10-29','06:01:59','LOGIN','Autenticación','127.0.0.1'),
(4770,7,'2026-10-29','06:22:30','LOGOUT','Autenticación','127.0.0.1'),
(4771,8,'2026-10-29','06:22:56','LOGIN','Autenticación','127.0.0.1'),
(4772,8,'2026-10-29','06:24:00','LOGOUT','Autenticación','127.0.0.1'),
(4773,8,'2026-10-29','06:33:09','LOGIN','Autenticación','127.0.0.1'),
(4774,8,'2026-10-29','06:37:49','LITERALES: 5 guardados (4 prom, 1 no prom, 0 sin lit) · 0 omitidos · estruct 1537','Resultado académico','127.0.0.1'),
(4775,8,'2026-10-29','06:40:47','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Ana Patricia López Toledo (V1212586313, L','Evaluación','127.0.0.1'),
(4776,8,'2026-10-29','06:41:29','LOGOUT','Autenticación','127.0.0.1'),
(4777,7,'2026-10-29','06:41:41','LOGIN','Autenticación','127.0.0.1'),
(4778,7,'2026-10-29','07:50:24','LOGIN','Autenticación','127.0.0.1'),
(4779,7,'2026-10-29','08:04:21','LOGOUT','Autenticación','127.0.0.1'),
(4780,8,'2026-10-29','08:04:32','LOGIN','Autenticación','127.0.0.1'),
(4781,8,'2026-10-29','08:22:50','LOGOUT','Autenticación','127.0.0.1'),
(4782,7,'2026-10-29','08:23:06','LOGIN','Autenticación','127.0.0.1'),
(4783,7,'2026-10-29','08:31:23','LOGOUT','Autenticación','127.0.0.1'),
(4784,7,'2026-10-29','12:03:53','LOGIN','Autenticación','127.0.0.1'),
(4785,7,'2026-10-29','12:19:17','LOGOUT','Autenticación','127.0.0.1'),
(4786,8,'2026-10-29','12:19:27','LOGIN','Autenticación','127.0.0.1'),
(4787,8,'2026-10-29','12:40:14','LITERALES: 5 guardados (4 prom, 1 no prom, 0 sin lit) · 0 omitidos · estruct 1537','Resultado académico','127.0.0.1'),
(4788,8,'2026-10-29','13:26:56','EMISIÓN MASIVA DE CERTIFICADOS DE CALIFICACIONES (Art. 108 RGLOE): Sección id 1537 (5 estudiantes)','Evaluación','127.0.0.1'),
(4789,8,'2026-10-29','13:32:26','LOGOUT','Autenticación','127.0.0.1'),
(4790,7,'2026-10-29','13:32:46','LOGIN','Autenticación','127.0.0.1'),
(4791,7,'2026-10-29','14:01:44','LOGOUT','Autenticación','127.0.0.1'),
(4792,2,'2026-10-29','14:02:00','LOGIN','Autenticación','127.0.0.1'),
(4793,2,'2026-10-29','14:02:20','LOGOUT','Autenticación','127.0.0.1'),
(4794,3,'2026-10-29','14:02:35','LOGIN','Autenticación','127.0.0.1'),
(4795,3,'2026-10-29','14:04:09','LOGOUT','Autenticación','127.0.0.1'),
(4796,3,'2026-10-29','14:09:24','LOGIN','Autenticación','127.0.0.1'),
(4797,3,'2026-10-29','14:17:04','LOGOUT','Autenticación','127.0.0.1'),
(4798,7,'2026-10-29','14:17:24','LOGIN','Autenticación','127.0.0.1'),
(4799,7,'2026-10-29','14:22:03','LOGOUT','Autenticación','127.0.0.1'),
(4800,2,'2026-10-29','14:22:16','LOGIN','Autenticación','127.0.0.1'),
(4801,2,'2026-10-29','14:42:01','LOGOUT','Autenticación','127.0.0.1'),
(4802,8,'2026-10-29','14:42:17','LOGIN','Autenticación','127.0.0.1'),
(4803,8,'2026-10-29','14:46:14','LOGOUT','Autenticación','127.0.0.1'),
(4804,17,'2026-10-29','14:46:34','LOGIN','Autenticación','127.0.0.1'),
(4805,17,'2026-10-29','14:49:10','LOGOUT','Autenticación','127.0.0.1'),
(4806,8,'2026-10-29','14:49:21','LOGIN','Autenticación','127.0.0.1'),
(4807,8,'2026-10-29','14:49:45','LITERALES: 5 guardados (1 prom, 1 no prom, 3 sin lit) · 0 omitidos · estruct 1537','Resultado académico','127.0.0.1'),
(4808,8,'2026-10-29','14:50:09','LITERALES: 5 guardados (0 prom, 0 no prom, 5 sin lit) · 0 omitidos · estruct 1537','Resultado académico','127.0.0.1'),
(4809,8,'2026-10-29','15:05:52','LITERALES: 5 guardados (3 prom, 2 no prom, 0 sin lit) · 0 omitidos · estruct 1537','Resultado académico','127.0.0.1'),
(4810,8,'2026-10-29','15:06:18','EMISIÓN MASIVA DE CERTIFICADOS DE CALIFICACIONES (Art. 108 RGLOE): Sección id 1537 (5 estudiantes)','Evaluación','127.0.0.1'),
(4811,8,'2026-10-29','15:09:46','LITERALES: 5 guardados (2 prom, 2 no prom, 1 sin lit) · 0 omitidos · estruct 1537','Resultado académico','127.0.0.1'),
(4812,8,'2026-10-29','15:10:13','LITERALES: 5 guardados (3 prom, 2 no prom, 0 sin lit) · 0 omitidos · estruct 1537','Resultado académico','127.0.0.1'),
(4813,8,'2026-10-29','15:13:23','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Ana Patricia López Toledo (V1212586313, L','Evaluación','127.0.0.1'),
(4814,8,'2026-10-29','15:13:26','EMISIÓN MASIVA DE CERTIFICADOS DE CALIFICACIONES (Art. 108 RGLOE): Sección id 1537 (5 estudiantes)','Evaluación','127.0.0.1'),
(4815,8,'2026-10-29','15:13:39','LITERALES: 5 guardados (2 prom, 2 no prom, 1 sin lit) · 0 omitidos · estruct 1537','Resultado académico','127.0.0.1'),
(4816,8,'2026-10-29','15:13:43','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Juan Fabricio Ojeda Suarez (V6212250928, ','Evaluación','127.0.0.1'),
(4817,8,'2026-10-29','15:14:00','EMISIÓN MASIVA DE CERTIFICADOS DE CALIFICACIONES (Art. 108 RGLOE): Sección id 1537 (4 estudiantes)','Evaluación','127.0.0.1'),
(4818,8,'2026-10-29','15:14:13','EMISIÓN MASIVA DE CERTIFICADOS DE CALIFICACIONES (Art. 108 RGLOE): Sección id 1537 (4 estudiantes)','Evaluación','127.0.0.1'),
(4819,8,'2026-10-29','15:14:34','EMISIÓN DE CERTIFICADO DE CALIFICACIONES (Art. 108 RGLOE): Juan Fabricio Ojeda Suarez (V6212250928, ','Evaluación','127.0.0.1'),
(4820,8,'2026-10-29','15:20:22','EMISIÓN MASIVA DE CERTIFICADOS DE CALIFICACIONES (Art. 108 RGLOE): Sección id 1537 (4 estudiantes)','Evaluación','127.0.0.1'),
(4821,8,'2026-10-29','15:27:42','EMISIÓN MASIVA DE CERTIFICADOS DE CALIFICACIONES (Art. 108 RGLOE): Sección id 1537 (4 estudiantes)','Evaluación','127.0.0.1'),
(4822,8,'2026-10-29','15:33:16','LOGOUT','Autenticación','127.0.0.1'),
(4823,2,'2026-10-29','15:33:34','LOGIN','Autenticación','127.0.0.1'),
(4824,2,'2026-10-29','15:51:01','LOGOUT','Autenticación','127.0.0.1'),
(4825,2,'2026-10-29','16:20:10','LOGIN','Autenticación','127.0.0.1'),
(4826,2,'2026-10-29','16:21:06','LOGOUT','Autenticación','127.0.0.1'),
(4827,7,'2026-10-29','16:21:34','LOGIN','Autenticación','127.0.0.1'),
(4828,7,'2026-10-29','18:26:30','LOGOUT','Autenticación','127.0.0.1'),
(4829,17,'2026-10-29','18:26:54','LOGIN','Autenticación','127.0.0.1'),
(4830,17,'2026-10-29','18:35:34','INSCRIPCIÓN Valerin Rojas (V11825863136) · 2° Grado · A · Mañana','Inscripciones','127.0.0.1'),
(4831,17,'2026-10-29','18:41:53','LOGOUT','Autenticación','127.0.0.1'),
(4832,8,'2026-10-29','18:48:05','LOGIN','Autenticación','127.0.0.1'),
(4833,8,'2026-10-29','18:48:24','EMISIÓN MASIVA DE CERTIFICADOS DE CALIFICACIONES (Art. 108 RGLOE): Sección id 1537 (4 estudiantes)','Evaluación','127.0.0.1');
/*!40000 ALTER TABLE `bitacora` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `cargo`
--

DROP TABLE IF EXISTS `cargo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cargo` (
  `id_cargo` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_cargo`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cargo`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `cargo` WRITE;
/*!40000 ALTER TABLE `cargo` DISABLE KEYS */;
INSERT INTO `cargo` VALUES
(1,'Director','ACTIVO'),
(2,'Subdirector','ACTIVO'),
(3,'Docente de Aula','ACTIVO'),
(4,'Coordinador Pedagógico','ACTIVO'),
(5,'Administrador del Sistema','ACTIVO'),
(6,'Psicopedagogo(a)','ACTIVO'),
(7,'Docente de Educación Especial','ACTIVO'),
(8,'Secretaria','ACTIVO');
/*!40000 ALTER TABLE `cargo` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `configuracion_institucional`
--

DROP TABLE IF EXISTS `configuracion_institucional`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `configuracion_institucional` (
  `clave` varchar(50) NOT NULL,
  `valor` text NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `fecha_actualizacion` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`clave`),
  KEY `fk_config_usuario` (`actualizado_por`),
  CONSTRAINT `fk_config_usuario` FOREIGN KEY (`actualizado_por`) REFERENCES `usuario` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `configuracion_institucional`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `configuracion_institucional` WRITE;
/*!40000 ALTER TABLE `configuracion_institucional` DISABLE KEYS */;
INSERT INTO `configuracion_institucional` VALUES
('cdce_estado','Carabobo','Estado del Centro de Desarrollo de la Calidad Educativa','2026-10-20 10:37:34',2),
('director_cargo','Director','Denominación exacta del cargo del directivo (ej. Director (E))','2026-10-18 14:52:29',2),
('director_cedula','V-8494162','Cédula de identidad del Director(a) con prefijo (V/E)','2026-10-18 14:52:29',2),
('director_firma','','Nombre de archivo de la firma en almacenamiento privado','2026-10-19 16:43:16',2),
('director_nombre','Tibisay Coromoto López Marín','Nombres y apellidos del Director(a) titular o encargado','2026-10-18 14:52:29',2),
('director_primer_apellido','López','Primer apellido del Director(a)','2026-10-18 14:52:29',2),
('director_primer_nombre','Tibisay','Primer nombre del Director(a)','2026-10-18 14:52:29',2),
('director_segundo_apellido','Marín','Segundo apellido del Director(a)','2026-10-18 14:52:29',2),
('director_segundo_nombre','Coromoto','Segundo nombre del Director(a)','2026-10-18 14:52:29',2),
('institucion_sello','','Nombre de archivo del sello húmedo en almacenamiento privado','2026-10-11 10:37:51',NULL),
('lugar_expedicion','Valencia','Lugar/Ciudad de expedición impreso en las constancias','2026-10-20 10:37:34',2),
('ministerio_linea_1','REPÚBLICA BOLIVARIANA DE VENEZUELA','Primera línea del membrete oficial del ministerio','2026-10-18 14:52:29',2),
('ministerio_linea_2','MINISTERIO DEL PODER POPULAR PARA LA EDUCACIÓN','Segunda línea del membrete oficial del ministerio','2026-10-18 14:52:29',2),
('ministerio_logo','/uploads/institucion/cef1a544eeb394157767ccfdc1b7728d.png','Ruta pública del logo oficial del ministerio','2026-10-12 17:27:51',2),
('nivel_codigo','21000','Código oficial del nivel educativo ante el MPPE','2026-10-20 10:37:34',2),
('nivel_educativo','Educación Primaria','Nivel educativo impartido por la institución','2026-10-20 10:37:34',2),
('plantel_cintillo','/uploads/institucion/95964f25d72be999d59af52d507a64e3.png','Ruta de la franja tricolor inferior para constancias','2026-10-19 04:38:40',2),
('plantel_codigo_dea','OD12345678','Código DEA oficial del plantel','2026-10-20 10:37:34',2),
('plantel_codigo_dependencia','0012345678','Código de dependencia oficial de la institución','2026-10-20 10:37:34',2),
('plantel_logo','/assets/img/logo.png','Ruta pública del escudo/logo del plantel','2026-10-25 08:57:01',2),
('plantel_municipio','Valencia','Municipio donde está ubicado el plantel','2026-10-20 10:37:34',2),
('plantel_nombre','ESCUELA BÁSICA \"ISIDRO RAMÍREZ\"','Nombre oficial completo de la institución','2026-10-22 14:16:05',2),
('plantel_nombre_corto','E.B. Isidro Ramírez','Nombre abreviado para menús y pies de página','2026-10-20 10:37:34',2),
('plantel_parroquia','Miguel Peña','Parroquia donde está ubicada la institución','2026-10-20 10:37:34',2);
/*!40000 ALTER TABLE `configuracion_institucional` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `contacto`
--

DROP TABLE IF EXISTS `contacto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contacto` (
  `id_contacto` int(11) NOT NULL AUTO_INCREMENT,
  `id_persona` int(11) NOT NULL,
  `codigo_telefono_principal` enum('0412','0414','0416','0422','0424','0426','0212') NOT NULL,
  `numero_telefono_principal` varchar(15) NOT NULL,
  `codigo_telefono_alternativo` enum('0412','0414','0416','0422','0424','0426','0212') DEFAULT NULL,
  `numero_telefono_alternativo` varchar(15) DEFAULT NULL,
  `correo_electronico` varchar(100) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_contacto`),
  UNIQUE KEY `id_persona` (`id_persona`),
  KEY `idx_contacto_telefono_principal` (`numero_telefono_principal`),
  KEY `idx_contacto_correo` (`correo_electronico`),
  CONSTRAINT `fk_contacto_persona` FOREIGN KEY (`id_persona`) REFERENCES `persona` (`id_persona`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2978 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contacto`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `contacto` WRITE;
/*!40000 ALTER TABLE `contacto` DISABLE KEYS */;
INSERT INTO `contacto` VALUES
(1,1,'0414','1234567',NULL,NULL,'mrodriguez.admin@sidge.edu.ve','2026-09-01 18:23:12','2026-09-01 18:23:12'),
(3,16,'0412','9915255',NULL,NULL,'derwinrojas351@gmail.com','2026-09-04 15:25:40','2026-09-04 15:25:40'),
(4,17,'0412','9915265',NULL,NULL,'maloa2022@gmail.com','2026-09-05 10:10:10','2026-09-05 10:10:10'),
(5,2,'0414','1234567',NULL,NULL,NULL,'2026-09-05 10:16:33','2026-10-03 20:00:28'),
(6,18,'0412','4169658',NULL,NULL,'maria@gmail.com','2026-09-05 10:25:56','2026-09-05 10:25:56'),
(7,19,'0412','4165856',NULL,NULL,'ivian@gmail.com','2026-09-05 11:29:49','2026-09-05 11:29:49'),
(26,42,'0412','4126325',NULL,NULL,'maitairiamar@gmail.com','2026-09-24 16:44:09','2026-09-24 16:44:09'),
(37,10,'0412','4126325',NULL,NULL,'maitairiamar@gmail.com','2026-09-24 17:29:40','2026-09-24 17:29:40'),
(54,80,'0412','4558888',NULL,NULL,NULL,'2026-09-25 18:12:14','2026-09-25 18:12:14'),
(709,1660,'0412','1234567',NULL,NULL,NULL,'2026-09-27 12:26:19','2026-09-27 12:26:19'),
(710,1662,'0412','1234567',NULL,NULL,NULL,'2026-09-27 12:26:19','2026-09-27 12:26:19'),
(1254,7804,'0412','4155522',NULL,NULL,'maitairiamar@gmail.com','2026-09-27 16:42:34','2026-09-27 16:42:34'),
(1618,11304,'0412','4158695',NULL,NULL,NULL,'2026-09-27 17:45:03','2026-09-27 17:45:03'),
(2849,7,'0412','4169888',NULL,NULL,NULL,'2026-10-02 13:50:15','2026-10-02 13:50:15'),
(2850,3,'0412','4444444',NULL,NULL,NULL,'2026-10-02 13:53:40','2026-10-02 13:53:40'),
(2903,24521,'0412','4165874',NULL,NULL,NULL,'2026-10-08 12:53:43','2026-10-08 12:53:43'),
(2960,24923,'0412','4164587',NULL,NULL,NULL,'2026-10-23 06:12:51','2026-10-23 06:12:51'),
(2967,24924,'0412','4157896',NULL,NULL,'jose@gmail.com','2026-10-24 08:07:04','2026-10-24 08:07:04');
/*!40000 ALTER TABLE `contacto` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `direccion`
--

DROP TABLE IF EXISTS `direccion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `direccion` (
  `id_direccion` int(11) NOT NULL AUTO_INCREMENT,
  `id_persona` int(11) NOT NULL,
  `sector_urbanizacion` varchar(100) NOT NULL,
  `calle_avenida` varchar(100) NOT NULL,
  `nro_casa_apto` varchar(20) NOT NULL,
  `punto_referencia` text DEFAULT NULL,
  `fecha_actualizacion` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `id_parroquia` int(11) NOT NULL,
  PRIMARY KEY (`id_direccion`),
  UNIQUE KEY `id_persona` (`id_persona`),
  KEY `idx_direccion_parroquia` (`id_parroquia`),
  CONSTRAINT `fk_direccion_parroquia` FOREIGN KEY (`id_parroquia`) REFERENCES `parroquia` (`id_parroquia`) ON UPDATE CASCADE,
  CONSTRAINT `fk_direccion_persona` FOREIGN KEY (`id_persona`) REFERENCES `persona` (`id_persona`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1211 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `direccion`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `direccion` WRITE;
/*!40000 ALTER TABLE `direccion` DISABLE KEYS */;
INSERT INTO `direccion` VALUES
(2,16,'Federacion','Fuerzas Armadas','519',NULL,'2026-09-28 05:16:07',4),
(3,17,'lkk','mmm','kk','jkjk','2026-09-05 10:10:10',4),
(4,18,'lkk','mmm','kk','jkjk','2026-09-05 10:25:56',4),
(5,19,'lll','mklmlk','mlm','','2026-09-05 11:29:49',4),
(28,42,'Federacion','Calle Fuerzas Armadas','519',NULL,'2026-09-30 23:15:21',4),
(31,10,'Federacion','Fuerzas Armadas','519',NULL,'2026-09-24 17:29:40',4),
(38,80,'Federacion','Av, las Ferias','458',NULL,'2026-09-30 22:49:12',4),
(489,1,'Federacion','Federacion','519',NULL,'2026-10-29 18:35:34',4),
(590,7804,'federacio','Fuerzas Armadas','333','federacio','2026-09-27 16:42:34',4),
(714,11304,'Federacion','Calle Fuerzas Armadas','1458',NULL,'2026-09-30 22:46:16',4),
(1129,7,'Nnn','Nnn','JJJJ',NULL,'2026-10-02 13:50:15',4),
(1130,3,'Ddd','Dddd','DDDD',NULL,'2026-10-02 13:53:40',4),
(1154,2,'Los Pinos','Principal','9',NULL,'2026-10-03 20:00:28',1),
(1180,24521,'Ugguybhu','Ijni','JIOJ',NULL,'2026-10-08 12:53:43',4),
(1193,24923,'Mmlmp','Knknl','NNLMLK','Kjnkno','2026-10-23 06:12:51',4),
(1200,24924,'Las Cajaras','Calle Fuerzas Armadas','CASA, 519',NULL,'2026-10-24 08:07:04',4);
/*!40000 ALTER TABLE `direccion` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `docente`
--

DROP TABLE IF EXISTS `docente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `docente` (
  `id_docente` int(11) NOT NULL AUTO_INCREMENT,
  `id_empleado` int(11) NOT NULL,
  PRIMARY KEY (`id_docente`),
  UNIQUE KEY `id_empleado` (`id_empleado`),
  CONSTRAINT `fk_docente_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `docente`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `docente` WRITE;
/*!40000 ALTER TABLE `docente` DISABLE KEYS */;
INSERT INTO `docente` VALUES
(2,4),
(3,5),
(4,8),
(9,10),
(5,11),
(10,78),
(15,91),
(16,92);
/*!40000 ALTER TABLE `docente` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `egreso`
--

DROP TABLE IF EXISTS `egreso`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `egreso` (
  `id_egreso` int(11) NOT NULL AUTO_INCREMENT,
  `id_matricula` int(11) NOT NULL,
  `fecha_egreso` datetime DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_egreso`),
  UNIQUE KEY `id_matricula` (`id_matricula`),
  KEY `fk_egreso_usuario` (`id_usuario`),
  CONSTRAINT `fk_egreso_matricula` FOREIGN KEY (`id_matricula`) REFERENCES `matricula` (`id_matricula`) ON UPDATE CASCADE,
  CONSTRAINT `fk_egreso_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=399 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `egreso`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `egreso` WRITE;
/*!40000 ALTER TABLE `egreso` DISABLE KEYS */;
INSERT INTO `egreso` VALUES
(391,21186,'2026-10-19 23:22:52',1);
/*!40000 ALTER TABLE `egreso` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `empleado`
--

DROP TABLE IF EXISTS `empleado`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `empleado` (
  `id_empleado` int(11) NOT NULL AUTO_INCREMENT,
  `id_persona` int(11) NOT NULL,
  `fecha_ingreso` date NOT NULL,
  `fecha_egreso` date DEFAULT NULL,
  `id_cargo` int(11) DEFAULT NULL,
  `id_turno` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_empleado`),
  UNIQUE KEY `id_persona` (`id_persona`),
  KEY `idx_empleado_fechas` (`fecha_ingreso`,`fecha_egreso`),
  KEY `fk_empleado_cargo` (`id_cargo`),
  KEY `idx_empleado_turno` (`id_turno`),
  CONSTRAINT `fk_empleado_cargo` FOREIGN KEY (`id_cargo`) REFERENCES `cargo` (`id_cargo`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_empleado_persona` FOREIGN KEY (`id_persona`) REFERENCES `persona` (`id_persona`) ON UPDATE CASCADE,
  CONSTRAINT `fk_empleado_turno` FOREIGN KEY (`id_turno`) REFERENCES `turno` (`id_turno`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=115 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `empleado`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `empleado` WRITE;
/*!40000 ALTER TABLE `empleado` DISABLE KEYS */;
INSERT INTO `empleado` VALUES
(1,1,'2024-01-15',NULL,5,NULL),
(2,2,'2026-01-15',NULL,1,NULL),
(3,3,'2024-01-15',NULL,2,2),
(4,4,'2024-02-01',NULL,3,NULL),
(5,5,'2024-02-01',NULL,3,NULL),
(6,6,'2024-02-10',NULL,4,NULL),
(7,7,'2024-03-01',NULL,8,2),
(8,8,'2024-03-01',NULL,3,NULL),
(9,9,'2024-03-15',NULL,6,NULL),
(10,10,'2024-04-01',NULL,3,NULL),
(11,11,'2024-04-01',NULL,7,2),
(13,16,'2020-06-16',NULL,1,NULL),
(14,17,'2020-09-16',NULL,5,NULL),
(15,18,'2020-06-16',NULL,5,NULL),
(16,19,'2020-06-16',NULL,4,NULL),
(35,42,'2020-06-16',NULL,4,NULL),
(42,80,'2026-06-16',NULL,1,NULL),
(43,11304,'2025-06-16',NULL,4,NULL),
(78,24521,'2025-06-16',NULL,3,2),
(83,24585,'2026-10-08',NULL,1,2),
(91,24923,'2026-06-16',NULL,3,1),
(92,24924,'2026-08-22',NULL,3,2);
/*!40000 ALTER TABLE `empleado` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `empleado_estado_historial`
--

DROP TABLE IF EXISTS `empleado_estado_historial`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `empleado_estado_historial` (
  `id_historial` int(11) NOT NULL AUTO_INCREMENT,
  `id_empleado` int(11) NOT NULL,
  `estado_anterior` enum('ACTIVO','INACTIVO') DEFAULT NULL,
  `estado_nuevo` enum('ACTIVO','INACTIVO') NOT NULL,
  `motivo` varchar(200) NOT NULL,
  `fecha_efectiva` date NOT NULL,
  `id_usuario` int(11) DEFAULT NULL COMMENT 'Usuario que hizo el cambio',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_historial`),
  KEY `idx_estado_historial_empleado` (`id_empleado`,`fecha_efectiva`),
  KEY `idx_estado_historial_usuario` (`id_usuario`),
  CONSTRAINT `fk_estado_historial_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_estado_historial_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `empleado_estado_historial`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `empleado_estado_historial` WRITE;
/*!40000 ALTER TABLE `empleado_estado_historial` DISABLE KEYS */;
INSERT INTO `empleado_estado_historial` VALUES
(8,3,'ACTIVO','INACTIVO','ll','2026-10-26',7,'2026-10-26 20:42:12'),
(9,3,'INACTIVO','ACTIVO','lml','2026-10-26',7,'2026-10-26 20:42:19');
/*!40000 ALTER TABLE `empleado_estado_historial` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `encuesta_ratificacion`
--

DROP TABLE IF EXISTS `encuesta_ratificacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `encuesta_ratificacion` (
  `id_matricula` int(11) NOT NULL,
  `tiene_canaima` tinyint(1) NOT NULL,
  `serial_canaima` varchar(50) DEFAULT NULL,
  `tiene_ingreso_sige` tinyint(1) NOT NULL,
  `fecha_ingreso_sige` date DEFAULT NULL,
  `motivo_sige` varchar(200) DEFAULT NULL,
  `repite_grado` tinyint(1) NOT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_matricula`),
  CONSTRAINT `fk_encuesta_matricula` FOREIGN KEY (`id_matricula`) REFERENCES `matricula` (`id_matricula`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ck_encuesta_canaima` CHECK (`tiene_canaima` in (0,1)),
  CONSTRAINT `ck_encuesta_sige` CHECK (`tiene_ingreso_sige` in (0,1)),
  CONSTRAINT `ck_encuesta_repite` CHECK (`repite_grado` in (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `encuesta_ratificacion`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `encuesta_ratificacion` WRITE;
/*!40000 ALTER TABLE `encuesta_ratificacion` DISABLE KEYS */;
/*!40000 ALTER TABLE `encuesta_ratificacion` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `estado`
--

DROP TABLE IF EXISTS `estado`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `estado` (
  `id_estado` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_estado`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estado`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `estado` WRITE;
/*!40000 ALTER TABLE `estado` DISABLE KEYS */;
INSERT INTO `estado` VALUES
(1,'Amazonas','ACTIVO'),
(2,'Anzoátegui','ACTIVO'),
(3,'Apure','ACTIVO'),
(4,'Aragua','ACTIVO'),
(5,'Barinas','ACTIVO'),
(6,'Bolívar','ACTIVO'),
(7,'Carabobo','ACTIVO'),
(8,'Cojedes','ACTIVO'),
(9,'Delta Amacuro','ACTIVO'),
(10,'Falcón','ACTIVO'),
(11,'Guárico','ACTIVO'),
(12,'Lara','ACTIVO'),
(13,'Mérida','ACTIVO'),
(14,'Miranda','ACTIVO'),
(15,'Monagas','ACTIVO'),
(16,'Nueva Esparta','ACTIVO'),
(17,'Portuguesa','ACTIVO'),
(18,'Sucre','ACTIVO'),
(19,'Táchira','ACTIVO'),
(20,'Trujillo','ACTIVO'),
(21,'La Guaira','ACTIVO'),
(22,'Yaracuy','ACTIVO'),
(23,'Zulia','ACTIVO'),
(24,'Distrito Capital','ACTIVO');
/*!40000 ALTER TABLE `estado` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `estructura_academica`
--

DROP TABLE IF EXISTS `estructura_academica`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `estructura_academica` (
  `id_estructura_academica` int(11) NOT NULL AUTO_INCREMENT,
  `id_anio_escolar` int(11) NOT NULL,
  `id_grado` int(11) NOT NULL,
  `id_seccion` int(11) NOT NULL,
  `id_turno` int(11) NOT NULL,
  `capacidad_maxima` int(11) NOT NULL,
  `enlace_whatsapp` varchar(120) DEFAULT NULL,
  PRIMARY KEY (`id_estructura_academica`),
  UNIQUE KEY `uk_estructura_academica` (`id_anio_escolar`,`id_grado`,`id_seccion`,`id_turno`),
  KEY `idx_estructura_anio` (`id_anio_escolar`),
  KEY `idx_estructura_grado` (`id_grado`),
  KEY `idx_estructura_seccion` (`id_seccion`),
  KEY `idx_estructura_turno` (`id_turno`),
  CONSTRAINT `fk_estructura_anio_escolar` FOREIGN KEY (`id_anio_escolar`) REFERENCES `anio_escolar` (`id_anio_escolar`) ON UPDATE CASCADE,
  CONSTRAINT `fk_estructura_grado` FOREIGN KEY (`id_grado`) REFERENCES `grado` (`id_grado`) ON UPDATE CASCADE,
  CONSTRAINT `fk_estructura_seccion` FOREIGN KEY (`id_seccion`) REFERENCES `seccion` (`id_seccion`) ON UPDATE CASCADE,
  CONSTRAINT `fk_estructura_turno` FOREIGN KEY (`id_turno`) REFERENCES `turno` (`id_turno`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1693 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estructura_academica`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `estructura_academica` WRITE;
/*!40000 ALTER TABLE `estructura_academica` DISABLE KEYS */;
INSERT INTO `estructura_academica` VALUES
(186,175,1,1,2,30,NULL),
(187,175,1,1,1,30,NULL),
(188,175,6,1,2,30,NULL),
(1536,238,1,1,1,30,NULL),
(1537,238,1,1,2,30,NULL),
(1538,238,6,1,2,30,NULL),
(1607,238,5,1,1,30,NULL);
/*!40000 ALTER TABLE `estructura_academica` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `estudiante`
--

DROP TABLE IF EXISTS `estudiante`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `estudiante` (
  `id_estudiante` int(11) NOT NULL AUTO_INCREMENT,
  `id_persona` int(11) NOT NULL,
  PRIMARY KEY (`id_estudiante`),
  UNIQUE KEY `id_persona` (`id_persona`),
  CONSTRAINT `fk_estudiante_persona` FOREIGN KEY (`id_persona`) REFERENCES `persona` (`id_persona`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20477 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estudiante`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `estudiante` WRITE;
/*!40000 ALTER TABLE `estudiante` DISABLE KEYS */;
INSERT INTO `estudiante` VALUES
(3064,4150),
(6118,7800),
(6120,7803),
(20113,24405),
(20114,24406),
(20118,24410),
(20124,24487),
(20125,24507),
(20126,24508),
(20127,24509),
(20128,24510),
(20131,24516),
(20132,24522),
(20394,24922),
(20476,25120);
/*!40000 ALTER TABLE `estudiante` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `estudiante_documento`
--

DROP TABLE IF EXISTS `estudiante_documento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `estudiante_documento` (
  `id_estudiante_documento` int(11) NOT NULL AUTO_INCREMENT,
  `id_estudiante` int(11) NOT NULL,
  `id_tipo_documento` int(11) NOT NULL,
  `observacion` text DEFAULT NULL,
  `consignado` tinyint(1) NOT NULL DEFAULT 0,
  `actualizado_en` datetime DEFAULT NULL,
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_estudiante_documento`),
  UNIQUE KEY `uk_estudiante_documento` (`id_estudiante`,`id_tipo_documento`),
  KEY `idx_estudiante_documento_tipo` (`id_tipo_documento`),
  KEY `idx_estudiante_documento_consignado` (`consignado`),
  KEY `idx_estudiante_documento_usuario` (`actualizado_por`),
  CONSTRAINT `fk_estudiante_documento_estudiante` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiante` (`id_estudiante`) ON UPDATE CASCADE,
  CONSTRAINT `fk_estudiante_documento_tipo` FOREIGN KEY (`id_tipo_documento`) REFERENCES `tipo_documento` (`id_tipo_documento`) ON UPDATE CASCADE,
  CONSTRAINT `fk_estudiante_documento_usuario` FOREIGN KEY (`actualizado_por`) REFERENCES `usuario` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=142 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estudiante_documento`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `estudiante_documento` WRITE;
/*!40000 ALTER TABLE `estudiante_documento` DISABLE KEYS */;
INSERT INTO `estudiante_documento` VALUES
(27,6120,3,'Sin copia ampliada',1,'2026-10-03 07:08:26',NULL),
(28,6120,5,'Sin copia ampliada',1,'2026-10-03 07:08:26',NULL),
(29,6120,2,NULL,0,'2026-10-03 05:58:09',17),
(30,6120,1,NULL,0,'2026-10-03 05:58:09',17),
(59,20114,3,NULL,0,'2026-10-03 07:05:34',17),
(60,20114,5,NULL,0,'2026-10-03 07:05:34',17),
(61,20114,2,NULL,1,'2026-10-03 07:05:34',17),
(62,20114,1,NULL,1,'2026-10-03 07:05:34',17),
(76,20124,3,NULL,1,NULL,NULL),
(77,20124,5,NULL,0,NULL,NULL),
(78,20124,2,NULL,0,NULL,NULL),
(79,20124,1,NULL,0,NULL,NULL),
(80,20125,3,NULL,1,NULL,NULL),
(81,20125,5,NULL,1,NULL,NULL),
(82,20125,2,NULL,0,NULL,NULL),
(83,20125,1,NULL,0,NULL,NULL),
(84,20126,3,NULL,0,NULL,NULL),
(85,20126,5,NULL,1,NULL,NULL),
(86,20126,2,NULL,0,NULL,NULL),
(87,20126,1,NULL,0,NULL,NULL),
(88,20127,3,NULL,1,NULL,NULL),
(89,20127,5,NULL,1,NULL,NULL),
(90,20127,2,NULL,0,NULL,NULL),
(91,20127,1,NULL,0,NULL,NULL),
(92,20128,3,NULL,1,NULL,NULL),
(93,20128,5,NULL,1,NULL,NULL),
(94,20128,2,NULL,0,NULL,NULL),
(95,20128,1,NULL,0,NULL,NULL),
(96,20131,3,NULL,1,NULL,NULL),
(97,20131,5,NULL,1,NULL,NULL),
(98,20131,2,NULL,0,NULL,NULL),
(99,20131,1,NULL,0,NULL,NULL),
(100,20132,3,NULL,0,NULL,NULL),
(101,20132,5,NULL,0,NULL,NULL),
(102,20132,2,NULL,0,NULL,NULL),
(103,20132,1,NULL,0,NULL,NULL),
(104,20132,10,NULL,0,'2026-10-22 15:11:23',17),
(105,20132,11,NULL,0,'2026-10-22 15:11:23',17),
(106,20132,8,NULL,0,'2026-10-22 15:11:23',17),
(107,20132,9,NULL,0,'2026-10-22 15:11:23',17),
(108,20132,7,NULL,0,'2026-10-22 15:11:23',17),
(109,20132,12,NULL,0,'2026-10-22 15:11:23',17),
(110,20131,10,NULL,0,'2026-10-22 15:20:54',17),
(111,20131,11,NULL,0,'2026-10-22 15:20:54',17),
(112,20131,8,NULL,0,'2026-10-22 15:20:54',17),
(113,20131,9,NULL,0,'2026-10-22 15:20:54',17),
(114,20131,7,NULL,0,'2026-10-22 15:20:54',17),
(115,20131,12,NULL,0,'2026-10-22 15:20:54',17),
(116,20394,10,NULL,1,NULL,NULL),
(117,20394,11,NULL,1,NULL,NULL),
(118,20394,8,NULL,1,NULL,NULL),
(119,20394,9,NULL,1,NULL,NULL),
(120,20394,3,NULL,0,NULL,NULL),
(121,20394,7,NULL,0,NULL,NULL),
(122,20394,2,NULL,0,NULL,NULL),
(123,20394,1,NULL,0,NULL,NULL),
(124,20394,12,NULL,0,NULL,NULL),
(125,20394,5,NULL,0,NULL,NULL),
(126,20114,10,NULL,0,'2026-10-23 15:21:40',17),
(127,20114,11,NULL,0,'2026-10-23 15:21:40',17),
(128,20114,8,NULL,0,'2026-10-23 15:21:40',17),
(129,20114,9,NULL,0,'2026-10-23 15:21:40',17),
(130,20114,7,NULL,0,'2026-10-23 15:21:40',17),
(131,20114,12,NULL,0,'2026-10-23 15:21:40',17),
(132,20476,10,NULL,0,NULL,NULL),
(133,20476,11,NULL,0,NULL,NULL),
(134,20476,8,NULL,0,NULL,NULL),
(135,20476,9,NULL,0,NULL,NULL),
(136,20476,3,NULL,0,NULL,NULL),
(137,20476,7,NULL,0,NULL,NULL),
(138,20476,2,NULL,0,NULL,NULL),
(139,20476,1,NULL,0,NULL,NULL),
(140,20476,12,NULL,1,NULL,NULL),
(141,20476,5,NULL,0,NULL,NULL);
/*!40000 ALTER TABLE `estudiante_documento` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `ficha_medica`
--

DROP TABLE IF EXISTS `ficha_medica`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ficha_medica` (
  `id_ficha_medica` int(11) NOT NULL AUTO_INCREMENT,
  `id_estudiante` int(11) NOT NULL,
  `condiciones_especiales` text DEFAULT NULL,
  `medicamentos` text DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `alergias` text DEFAULT NULL,
  `enfermedades` text DEFAULT NULL,
  `tipo_sangre` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') DEFAULT NULL,
  `fecha_actualizacion` date NOT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_ficha_medica`),
  UNIQUE KEY `id_estudiante` (`id_estudiante`),
  KEY `idx_ficha_medica_fecha` (`fecha_actualizacion`),
  CONSTRAINT `fk_ficha_medica_estudiante` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiante` (`id_estudiante`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=505 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ficha_medica`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `ficha_medica` WRITE;
/*!40000 ALTER TABLE `ficha_medica` DISABLE KEYS */;
INSERT INTO `ficha_medica` VALUES
(256,3064,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-03','2026-09-27 13:38:10'),
(290,6118,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-27','2026-09-27 16:26:07'),
(292,6120,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-01','2026-09-27 16:42:34'),
(464,20113,NULL,NULL,NULL,NULL,NULL,'AB+','2026-10-01','2026-10-01 08:47:36'),
(465,20114,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-23','2026-10-01 09:32:01'),
(466,20118,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-01','2026-10-01 12:15:05'),
(495,20132,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-23','2026-10-22 15:11:23'),
(496,20131,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-22','2026-10-22 15:20:54');
/*!40000 ALTER TABLE `ficha_medica` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `formacion_academica`
--

DROP TABLE IF EXISTS `formacion_academica`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `formacion_academica` (
  `id_formacion_academica` int(11) NOT NULL AUTO_INCREMENT,
  `id_empleado` int(11) NOT NULL,
  `id_titulo` int(11) NOT NULL,
  `id_grado_academico` int(11) NOT NULL,
  `id_institucion` int(11) NOT NULL,
  `fecha_obtencion` date NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_formacion_academica`),
  KEY `idx_formacion_academica_empleado` (`id_empleado`),
  KEY `idx_formacion_academica_titulo` (`id_titulo`),
  KEY `idx_formacion_academica_grado` (`id_grado_academico`),
  KEY `idx_formacion_academica_institucion` (`id_institucion`),
  KEY `idx_formacion_academica_estado` (`estado`),
  KEY `idx_formacion_academica_fecha` (`fecha_obtencion`),
  CONSTRAINT `fk_formacion_academica_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`) ON UPDATE CASCADE,
  CONSTRAINT `fk_formacion_academica_grado` FOREIGN KEY (`id_grado_academico`) REFERENCES `grado_academico` (`id_grado_academico`) ON UPDATE CASCADE,
  CONSTRAINT `fk_formacion_academica_institucion` FOREIGN KEY (`id_institucion`) REFERENCES `institucion` (`id_institucion`) ON UPDATE CASCADE,
  CONSTRAINT `fk_formacion_academica_titulo` FOREIGN KEY (`id_titulo`) REFERENCES `titulo` (`id_titulo`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `formacion_academica`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `formacion_academica` WRITE;
/*!40000 ALTER TABLE `formacion_academica` DISABLE KEYS */;
INSERT INTO `formacion_academica` VALUES
(2,13,2,2,4,'2010-06-16','ACTIVO','2026-09-04 15:25:40'),
(5,35,14,3,5,'2020-06-16','INACTIVO','2026-09-24 16:44:09'),
(6,35,14,3,5,'2020-06-16','INACTIVO','2026-09-25 08:25:57'),
(7,35,14,3,5,'2020-06-16','ACTIVO','2026-09-25 09:16:14'),
(8,42,4,3,9,'0001-06-16','ACTIVO','2026-09-25 18:12:14'),
(9,43,4,3,1,'2000-05-18','ACTIVO','2026-09-27 17:45:03'),
(20,10,4,3,4,'2025-06-16','ACTIVO','2026-10-03 20:09:52'),
(21,10,14,3,4,'2023-02-10','ACTIVO','2026-10-11 09:44:31'),
(22,91,4,3,4,'2025-02-05','ACTIVO','2026-10-23 06:12:51'),
(24,92,33,1,5,'2020-06-16','ACTIVO','2026-10-24 08:07:04');
/*!40000 ALTER TABLE `formacion_academica` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `grado`
--

DROP TABLE IF EXISTS `grado`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `grado` (
  `id_grado` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(30) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_grado`),
  UNIQUE KEY `nombre` (`nombre`),
  KEY `idx_grado_estado` (`estado`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `grado`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `grado` WRITE;
/*!40000 ALTER TABLE `grado` DISABLE KEYS */;
INSERT INTO `grado` VALUES
(1,'1° Grado','ACTIVO'),
(2,'6° Grado','ACTIVO'),
(3,'3° Grado','ACTIVO'),
(4,'4° Grado','ACTIVO'),
(5,'5° Grado','ACTIVO'),
(6,'2° Grado','ACTIVO');
/*!40000 ALTER TABLE `grado` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `grado_academico`
--

DROP TABLE IF EXISTS `grado_academico`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `grado_academico` (
  `id_grado_academico` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_grado_academico`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `grado_academico`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `grado_academico` WRITE;
/*!40000 ALTER TABLE `grado_academico` DISABLE KEYS */;
INSERT INTO `grado_academico` VALUES
(1,'Técnico Superior Universitario','ACTIVO'),
(2,'Licenciatura','ACTIVO'),
(3,'Ingeniería','ACTIVO'),
(4,'Especialización','INACTIVO'),
(5,'Maestría','INACTIVO'),
(6,'Doctorado','INACTIVO'),
(7,'Postdoctorado','ACTIVO');
/*!40000 ALTER TABLE `grado_academico` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `inscripcion`
--

DROP TABLE IF EXISTS `inscripcion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inscripcion` (
  `id_inscripcion` int(11) NOT NULL AUTO_INCREMENT,
  `id_matricula` int(11) NOT NULL,
  `id_estudiante` int(11) NOT NULL,
  `id_estructura_academica` int(11) NOT NULL,
  `id_anio_escolar` int(11) NOT NULL,
  `numero_inscripcion` int(11) NOT NULL,
  `ult_grado_cursado` varchar(20) DEFAULT NULL,
  `es_primer_ingreso` tinyint(1) NOT NULL DEFAULT 1,
  `motivo_egreso` varchar(100) DEFAULT NULL,
  `anio_escolar_pre` varchar(9) DEFAULT NULL,
  `id_institucion` int(11) NOT NULL,
  PRIMARY KEY (`id_inscripcion`),
  UNIQUE KEY `id_matricula` (`id_matricula`),
  UNIQUE KEY `uk_inscripcion_estudiante` (`id_estudiante`),
  UNIQUE KEY `uk_inscripcion_anio_folio` (`id_anio_escolar`,`numero_inscripcion`),
  KEY `idx_inscripcion_institucion` (`id_institucion`),
  KEY `idx_inscripcion_estructura` (`id_estructura_academica`),
  KEY `idx_inscripcion_estudiante` (`id_estudiante`),
  CONSTRAINT `fk_inscripcion_anio_escolar` FOREIGN KEY (`id_anio_escolar`) REFERENCES `anio_escolar` (`id_anio_escolar`) ON UPDATE CASCADE,
  CONSTRAINT `fk_inscripcion_estructura` FOREIGN KEY (`id_estructura_academica`) REFERENCES `estructura_academica` (`id_estructura_academica`) ON UPDATE CASCADE,
  CONSTRAINT `fk_inscripcion_estudiante` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiante` (`id_estudiante`) ON UPDATE CASCADE,
  CONSTRAINT `fk_inscripcion_institucion` FOREIGN KEY (`id_institucion`) REFERENCES `institucion` (`id_institucion`) ON UPDATE CASCADE,
  CONSTRAINT `fk_inscripcion_matricula` FOREIGN KEY (`id_matricula`) REFERENCES `matricula` (`id_matricula`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19184 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inscripcion`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `inscripcion` WRITE;
/*!40000 ALTER TABLE `inscripcion` DISABLE KEYS */;
INSERT INTO `inscripcion` VALUES
(2896,3010,3064,187,175,1,NULL,1,NULL,NULL,2),
(5836,6045,6118,187,175,2,NULL,1,NULL,NULL,2),
(6114,6047,6120,186,175,3,NULL,1,NULL,NULL,2),
(19160,20820,20113,186,175,4,'Educación Inicial',0,NULL,'2024-2025',764),
(19161,20821,20114,186,175,5,'Educación Inicial',0,NULL,'2024-2025',764),
(19164,20825,20118,187,175,6,NULL,1,NULL,NULL,2),
(19169,20841,20124,1537,238,1,NULL,1,NULL,NULL,2),
(19170,20842,20125,1538,238,2,NULL,1,NULL,NULL,2),
(19171,20843,20126,1538,238,3,NULL,1,NULL,NULL,2),
(19172,20846,20127,188,175,7,NULL,1,NULL,NULL,2),
(19173,20847,20128,1538,238,4,'1Er Grado',0,NULL,NULL,765),
(19176,20944,20131,1537,238,5,NULL,1,NULL,NULL,2),
(19177,20945,20132,1537,238,6,NULL,1,NULL,NULL,2),
(19182,21296,20394,1537,238,7,'Educación Inicial',0,NULL,'2024-2025',764),
(19183,21306,20476,1538,238,8,'2° Grado',0,NULL,'2024-2025',765);
/*!40000 ALTER TABLE `inscripcion` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `institucion`
--

DROP TABLE IF EXISTS `institucion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `institucion` (
  `id_institucion` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  `id_tipo_institucion` int(11) DEFAULT 8,
  `codigo_dea` varchar(20) DEFAULT NULL,
  `es_extranjera` tinyint(1) NOT NULL DEFAULT 0,
  `id_estado_geo` int(11) DEFAULT NULL,
  `id_municipio` int(11) DEFAULT NULL,
  `id_parroquia` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_institucion`),
  UNIQUE KEY `nombre` (`nombre`),
  UNIQUE KEY `ux_institucion_codigo_dea` (`codigo_dea`),
  KEY `fk_institucion_estado_geo` (`id_estado_geo`),
  KEY `fk_institucion_tipo_institucion` (`id_tipo_institucion`),
  KEY `fk_institucion_municipio` (`id_municipio`),
  KEY `fk_institucion_parroquia` (`id_parroquia`),
  CONSTRAINT `fk_institucion_estado_geo` FOREIGN KEY (`id_estado_geo`) REFERENCES `estado` (`id_estado`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_institucion_municipio` FOREIGN KEY (`id_municipio`) REFERENCES `municipio` (`id_municipio`),
  CONSTRAINT `fk_institucion_parroquia` FOREIGN KEY (`id_parroquia`) REFERENCES `parroquia` (`id_parroquia`),
  CONSTRAINT `fk_institucion_tipo_institucion` FOREIGN KEY (`id_tipo_institucion`) REFERENCES `tipo_institucion` (`id_tipo_institucion`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=775 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `institucion`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `institucion` WRITE;
/*!40000 ALTER TABLE `institucion` DISABLE KEYS */;
INSERT INTO `institucion` VALUES
(1,'Universidad Nacional Experimental Politécnica de la Fuerza Armada Nacional (UNEFA)','ACTIVO',6,NULL,0,NULL,NULL,NULL),
(2,'Universidad de Carabobo (UC)','ACTIVO',6,NULL,0,7,10,286),
(3,'Universidad Pedagógica Experimental Libertador (UPEL)','ACTIVO',6,NULL,0,NULL,NULL,NULL),
(4,'Universidad Politécnica Territorial de Valencia (UPTV)','ACTIVO',6,NULL,0,NULL,NULL,NULL),
(5,'Universidad Nacional Abierta (UNA)','ACTIVO',6,NULL,0,NULL,NULL,NULL),
(9,'Universidad Bolivariana de Venezuela (UBV)','ACTIVO',6,NULL,0,NULL,NULL,NULL),
(47,'Universidad José Antonio Páez (UJAP)','ACTIVO',6,NULL,0,7,NULL,NULL),
(83,'Universidad Arturo Michelena (UAM)','ACTIVO',6,NULL,0,7,NULL,NULL),
(684,'Universidad Bicentenaria de Aragua','ACTIVO',6,NULL,0,7,NULL,NULL),
(728,'Universidad Tecnológica del Centro (UNITEC)','ACTIVO',6,NULL,0,NULL,NULL,NULL),
(757,'Universidad Alejandro de Humboldt (UAH)','ACTIVO',6,NULL,0,NULL,NULL,NULL),
(764,'[PRUEBA] Preescolar SIGDE','ACTIVO',1,NULL,0,NULL,NULL,NULL),
(765,'[PRUEBA] Escuela SIGDE a','ACTIVO',8,'0052620M',0,7,14,4),
(766,'[PRUEBA] Escuela SIGDE B','ACTIVO',8,NULL,0,NULL,NULL,NULL);
/*!40000 ALTER TABLE `institucion` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `matricula`
--

DROP TABLE IF EXISTS `matricula`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `matricula` (
  `id_matricula` int(11) NOT NULL AUTO_INCREMENT,
  `id_estudiante` int(11) NOT NULL,
  `fecha_matricula` date NOT NULL,
  `id_anio_escolar` int(11) NOT NULL,
  `id_estructura_academica` int(11) NOT NULL,
  `tipo` enum('INSCRIPCION','RATIFICACION','RETIRO','EGRESO') NOT NULL,
  `motivo_anticipo` varchar(200) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_matricula`),
  UNIQUE KEY `uk_matricula_estudiante_anio_tipo` (`id_estudiante`,`id_anio_escolar`,`tipo`),
  KEY `idx_matricula_estudiante` (`id_estudiante`),
  KEY `idx_matricula_fecha` (`fecha_matricula`),
  KEY `idx_matricula_anio` (`id_anio_escolar`),
  KEY `idx_matricula_estructura` (`id_estructura_academica`),
  KEY `idx_matricula_tipo` (`tipo`),
  CONSTRAINT `fk_matricula_anio` FOREIGN KEY (`id_anio_escolar`) REFERENCES `anio_escolar` (`id_anio_escolar`) ON UPDATE CASCADE,
  CONSTRAINT `fk_matricula_estructura` FOREIGN KEY (`id_estructura_academica`) REFERENCES `estructura_academica` (`id_estructura_academica`) ON UPDATE CASCADE,
  CONSTRAINT `fk_matricula_estudiante` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiante` (`id_estudiante`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21307 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `matricula`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `matricula` WRITE;
/*!40000 ALTER TABLE `matricula` DISABLE KEYS */;
INSERT INTO `matricula` VALUES
(3010,3064,'2026-09-27',175,187,'INSCRIPCION',NULL,'2026-09-27 13:38:10'),
(6045,6118,'2026-09-27',175,187,'INSCRIPCION',NULL,'2026-09-27 16:26:07'),
(6047,6120,'2026-09-27',175,186,'INSCRIPCION',NULL,'2026-09-27 16:42:34'),
(20820,20113,'2026-10-01',175,186,'INSCRIPCION',NULL,'2026-10-01 08:47:36'),
(20821,20114,'2026-10-01',175,186,'INSCRIPCION',NULL,'2026-10-01 09:32:01'),
(20825,20118,'2026-10-01',175,187,'INSCRIPCION',NULL,'2026-10-01 12:15:05'),
(20831,20114,'2026-10-01',238,1538,'RATIFICACION',NULL,'2026-10-01 13:38:42'),
(20833,6120,'2026-10-01',238,1537,'RATIFICACION',NULL,'2026-10-01 13:57:12'),
(20836,20113,'2026-10-02',175,186,'RETIRO',NULL,'2026-10-02 20:34:26'),
(20841,20124,'2026-10-03',238,1537,'INSCRIPCION',NULL,'2026-10-03 10:37:57'),
(20842,20125,'2026-10-05',238,1538,'INSCRIPCION',NULL,'2026-10-05 09:32:56'),
(20843,20126,'2026-10-05',238,1538,'INSCRIPCION',NULL,'2026-10-05 09:34:51'),
(20844,20125,'2026-10-05',238,1538,'RETIRO',NULL,'2026-10-05 11:22:57'),
(20845,6118,'2026-10-05',175,187,'RETIRO',NULL,'2026-10-05 11:28:36'),
(20846,20127,'2026-10-05',175,188,'INSCRIPCION',NULL,'2026-10-05 12:34:28'),
(20847,20128,'2026-10-05',238,1538,'INSCRIPCION',NULL,'2026-10-05 14:25:18'),
(20944,20131,'2026-10-06',238,1537,'INSCRIPCION',NULL,'2026-10-06 12:23:08'),
(20945,20132,'2026-10-08',238,1537,'INSCRIPCION',NULL,'2026-10-08 13:10:32'),
(21186,3064,'2026-09-15',238,1607,'EGRESO',NULL,'2026-10-19 23:22:52'),
(21296,20394,'2026-10-23',238,1537,'INSCRIPCION',NULL,'2026-10-23 06:02:53'),
(21306,20476,'2026-10-29',238,1538,'INSCRIPCION',NULL,'2026-10-29 18:35:34');
/*!40000 ALTER TABLE `matricula` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_uca1400_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*//*!50003 TRIGGER `trg_matricula_transicion`
BEFORE INSERT ON `matricula`
FOR EACH ROW
BEGIN
    DECLARE v_previo      VARCHAR(20) DEFAULT NULL;
    DECLARE v_previo_anio INT         DEFAULT NULL;
    DECLARE v_ordinal     INT         DEFAULT NULL;

    
    SELECT m.tipo, m.id_anio_escolar INTO v_previo, v_previo_anio
      FROM matricula m
      INNER JOIN anio_escolar a ON a.id_anio_escolar = m.id_anio_escolar
     WHERE m.id_estudiante = NEW.id_estudiante
     ORDER BY a.anio_inicio DESC, m.id_matricula DESC
     LIMIT 1;

    reglas: BEGIN

    
    IF v_previo IS NULL THEN
        IF NEW.tipo <> 'INSCRIPCION' THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Un estudiante sin matriculas previas no se ratifica, no se retira y no egresa: se inscribe.';
        END IF;

        LEAVE reglas;
    END IF;

    
    IF v_previo IN ('RETIRO', 'EGRESO') THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'El ciclo de este estudiante ya se cerro, no admite mas matriculas.';
    END IF;

    
    IF NEW.tipo = 'INSCRIPCION' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'La inscripcion ocurre una sola vez en toda la vida escolar del alumno: los que ya estan inscriptos se ratifican.';
    END IF;

    
    IF NEW.tipo = 'RATIFICACION' AND (
        SELECT an2.anio_inicio FROM anio_escolar an2
         WHERE an2.id_anio_escolar = NEW.id_anio_escolar
    ) <= (
        SELECT an1.anio_inicio FROM anio_escolar an1
         WHERE an1.id_anio_escolar = v_previo_anio
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'La ratificacion debe ir en un anio escolar posterior al de la anterior.';
    END IF;

    
    IF NEW.tipo IN ('RETIRO', 'EGRESO') AND (
        SELECT an3.anio_inicio FROM anio_escolar an3
         WHERE an3.id_anio_escolar = NEW.id_anio_escolar
    ) < (
        SELECT an4.anio_inicio FROM anio_escolar an4
         WHERE an4.id_anio_escolar = v_previo_anio
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'El cierre no puede ir en un anio anterior al de la matricula del alumno.';
    END IF;

    
    IF NEW.tipo = 'EGRESO' THEN
        SELECT CAST(REGEXP_SUBSTR(g.nombre, '^[0-9]+') AS UNSIGNED) INTO v_ordinal
          FROM estructura_academica ea
          INNER JOIN grado g ON g.id_grado = ea.id_grado
         WHERE ea.id_estructura_academica = NEW.id_estructura_academica
         LIMIT 1;

        IF v_ordinal IS NOT NULL AND v_ordinal <> 6 THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'El egreso es solo para quien culmina 6to grado.';
        END IF;
    END IF;

    END reglas;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `motivo_retiro`
--

DROP TABLE IF EXISTS `motivo_retiro`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `motivo_retiro` (
  `id_motivo_retiro` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_motivo_retiro`),
  UNIQUE KEY `nombre` (`nombre`),
  KEY `idx_motivo_retiro_estado` (`estado`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `motivo_retiro`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `motivo_retiro` WRITE;
/*!40000 ALTER TABLE `motivo_retiro` DISABLE KEYS */;
INSERT INTO `motivo_retiro` VALUES
(7,'Cambio de residencia','ACTIVO');
/*!40000 ALTER TABLE `motivo_retiro` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `municipio`
--

DROP TABLE IF EXISTS `municipio`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `municipio` (
  `id_municipio` int(11) NOT NULL AUTO_INCREMENT,
  `id_estado` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_municipio`),
  UNIQUE KEY `uk_municipio_estado_nombre` (`id_estado`,`nombre`),
  KEY `idx_municipio_nombre` (`nombre`),
  CONSTRAINT `fk_municipio_estado` FOREIGN KEY (`id_estado`) REFERENCES `estado` (`id_estado`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=371 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `municipio`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `municipio` WRITE;
/*!40000 ALTER TABLE `municipio` DISABLE KEYS */;
INSERT INTO `municipio` VALUES
(1,7,'Bejuma','ACTIVO'),
(2,7,'Carlos Arvelo','ACTIVO'),
(3,7,'Diego Ibarra','ACTIVO'),
(4,7,'Guacara','ACTIVO'),
(5,7,'Juan José Mora','ACTIVO'),
(6,7,'Libertador','ACTIVO'),
(7,7,'Los Guayos','ACTIVO'),
(8,7,'Miranda','ACTIVO'),
(9,7,'Montalbán','ACTIVO'),
(10,7,'Naguanagua','ACTIVO'),
(11,7,'Puerto Cabello','ACTIVO'),
(12,7,'San Diego','ACTIVO'),
(13,7,'San Joaquín','ACTIVO'),
(14,7,'Valencia','ACTIVO'),
(15,1,'Alto Orinoco','ACTIVO'),
(16,1,'Atabapo','ACTIVO'),
(17,1,'Atures','ACTIVO'),
(18,1,'Autana','ACTIVO'),
(19,1,'Manapiare','ACTIVO'),
(20,1,'Maroa','ACTIVO'),
(21,1,'Río Negro','ACTIVO'),
(36,2,'Anaco','ACTIVO'),
(37,2,'Aragua','ACTIVO'),
(38,2,'Bolívar','ACTIVO'),
(39,2,'Bruzual','ACTIVO'),
(40,2,'Cajigal','ACTIVO'),
(41,2,'Carvajal','ACTIVO'),
(42,2,'Diego Bautista Urbaneja','ACTIVO'),
(43,2,'Freites','ACTIVO'),
(44,2,'Guanipa','ACTIVO'),
(45,2,'Guanta','ACTIVO'),
(46,2,'Independencia','ACTIVO'),
(47,2,'José Gregorio Monagas','ACTIVO'),
(48,2,'Juan Antonio Sotillo','ACTIVO'),
(49,2,'Juan Manuel Cajigal','ACTIVO'),
(50,2,'Libertad','ACTIVO'),
(51,2,'Manuel Ezequiel Bruzual','ACTIVO'),
(52,2,'Pedro María Freites','ACTIVO'),
(53,2,'Píritu','ACTIVO'),
(54,2,'San José de Guanipa','ACTIVO'),
(55,2,'Santa Ana','ACTIVO'),
(56,2,'Simón Rodríguez','ACTIVO'),
(57,3,'Achaguas','ACTIVO'),
(58,3,'Biruaca','ACTIVO'),
(59,3,'Muñoz','ACTIVO'),
(60,3,'Páez','ACTIVO'),
(61,3,'Pedro Camejo','ACTIVO'),
(62,3,'Rómulo Gallegos','ACTIVO'),
(63,3,'San Fernando','ACTIVO'),
(71,4,'Bolívar','ACTIVO'),
(72,4,'Camatagua','ACTIVO'),
(73,4,'Francisco Linares Alcántara','ACTIVO'),
(74,4,'Girardot','ACTIVO'),
(75,4,'José Ángel Lamas','ACTIVO'),
(76,4,'José Félix Ribas','ACTIVO'),
(77,4,'José Rafael Revenga','ACTIVO'),
(78,4,'Libertador','ACTIVO'),
(79,4,'Mario Briceño Iragorry','ACTIVO'),
(80,4,'Ocumare de la Costa de Oro','ACTIVO'),
(81,4,'San Casimiro','ACTIVO'),
(82,4,'San Sebastián','ACTIVO'),
(83,4,'Santiago Mariño','ACTIVO'),
(84,4,'Santos Michelena','ACTIVO'),
(85,4,'Sucre','ACTIVO'),
(86,4,'Tovar','ACTIVO'),
(87,4,'Urdaneta','ACTIVO'),
(88,4,'Zamora','ACTIVO'),
(89,5,'Alberto Arvelo Torrealba','ACTIVO'),
(90,5,'Andrés Eloy Blanco','ACTIVO'),
(91,5,'Antonio José de Sucre','ACTIVO'),
(92,5,'Arismendi','ACTIVO'),
(93,5,'Barinas','ACTIVO'),
(94,5,'Bolívar','ACTIVO'),
(95,5,'Cruz Paredes','ACTIVO'),
(96,5,'Ezequiel Zamora','ACTIVO'),
(97,5,'Obispos','ACTIVO'),
(98,5,'Páez','ACTIVO'),
(99,5,'Pedraza','ACTIVO'),
(100,5,'Rojas','ACTIVO'),
(101,5,'Sosa','ACTIVO'),
(102,6,'Caroní','ACTIVO'),
(103,6,'Cedeño','ACTIVO'),
(104,6,'El Callao','ACTIVO'),
(105,6,'Gran Sabana','ACTIVO'),
(106,6,'Heres','ACTIVO'),
(107,6,'Padre Pedro Chien','ACTIVO'),
(108,6,'Piar','ACTIVO'),
(109,6,'Raúl Leoni','ACTIVO'),
(110,6,'Roscio','ACTIVO'),
(111,6,'Sifontes','ACTIVO'),
(112,6,'Sucre','ACTIVO'),
(127,8,'Anzoátegui','ACTIVO'),
(128,8,'Falcón','ACTIVO'),
(129,8,'Girardot','ACTIVO'),
(130,8,'Lima Blanco','ACTIVO'),
(131,8,'Pao de San Juan Bautista','ACTIVO'),
(132,8,'Ricaurte','ACTIVO'),
(133,8,'Rómulo Gallegos','ACTIVO'),
(134,8,'San Carlos','ACTIVO'),
(135,8,'Tinaco','ACTIVO'),
(136,9,'Antonio Díaz','ACTIVO'),
(137,9,'Casacoima','ACTIVO'),
(138,9,'Pedernales','ACTIVO'),
(139,9,'Tucupita','ACTIVO'),
(140,10,'Acosta','ACTIVO'),
(141,10,'Bolívar','ACTIVO'),
(142,10,'Buchivacoa','ACTIVO'),
(143,10,'Cacique Manaure','ACTIVO'),
(144,10,'Carirubana','ACTIVO'),
(145,10,'Colina','ACTIVO'),
(146,10,'Dabajuro','ACTIVO'),
(147,10,'Democracia','ACTIVO'),
(148,10,'Falcón','ACTIVO'),
(149,10,'Federación','ACTIVO'),
(150,10,'Jacura','ACTIVO'),
(151,10,'José Laurencio Silva','ACTIVO'),
(152,10,'Los Taques','ACTIVO'),
(153,10,'Mauroa','ACTIVO'),
(154,10,'Miranda','ACTIVO'),
(155,10,'Monseñor Iturriza','ACTIVO'),
(156,10,'Palmasola','ACTIVO'),
(157,10,'Petit','ACTIVO'),
(158,10,'Píritu','ACTIVO'),
(159,10,'San Francisco','ACTIVO'),
(160,10,'Sucre','ACTIVO'),
(161,10,'Tocópero','ACTIVO'),
(162,10,'Unión','ACTIVO'),
(163,10,'Urumaco','ACTIVO'),
(164,10,'Zamora','ACTIVO'),
(165,11,'Camaguán','ACTIVO'),
(166,11,'Chaguaramas','ACTIVO'),
(167,11,'El Socorro','ACTIVO'),
(168,11,'Francisco de Miranda','ACTIVO'),
(169,11,'José Félix Ribas','ACTIVO'),
(170,11,'José Tadeo Monagas','ACTIVO'),
(171,11,'Juan Germán Roscio','ACTIVO'),
(172,11,'Juan José Rondón','ACTIVO'),
(173,11,'Julián Mellado','ACTIVO'),
(174,11,'Leonardo Infante','ACTIVO'),
(175,11,'Ortiz','ACTIVO'),
(176,11,'San Gerónimo de Guayabal','ACTIVO'),
(177,11,'San José de Guaribe','ACTIVO'),
(178,11,'Santa María de Ipire','ACTIVO'),
(179,11,'Zaraza','ACTIVO'),
(180,12,'Andrés Eloy Blanco','ACTIVO'),
(181,12,'Crespo','ACTIVO'),
(182,12,'Iribarren','ACTIVO'),
(183,12,'Jiménez','ACTIVO'),
(184,12,'Morán','ACTIVO'),
(185,12,'Palavecino','ACTIVO'),
(186,12,'Simón Planas','ACTIVO'),
(187,12,'Torres','ACTIVO'),
(188,12,'Urdaneta','ACTIVO'),
(189,13,'Alberto Adriani','ACTIVO'),
(190,13,'Andrés Bello','ACTIVO'),
(191,13,'Antonio Pinto Salinas','ACTIVO'),
(192,13,'Aricagua','ACTIVO'),
(193,13,'Arzobispo Chacón','ACTIVO'),
(194,13,'Campo Elías','ACTIVO'),
(195,13,'Caracciolo Parra Olmedo','ACTIVO'),
(196,13,'Cardenal Quintero','ACTIVO'),
(197,13,'Guaraque','ACTIVO'),
(198,13,'Julio César Salas','ACTIVO'),
(199,13,'Justo Briceño','ACTIVO'),
(200,13,'Libertador','ACTIVO'),
(201,13,'Miranda','ACTIVO'),
(202,13,'Obispo Ramos de Lora','ACTIVO'),
(203,13,'Padre Noguera','ACTIVO'),
(204,13,'Pueblo Llano','ACTIVO'),
(205,13,'Rangel','ACTIVO'),
(206,13,'Rivas Dávila','ACTIVO'),
(207,13,'Santos Marquina','ACTIVO'),
(208,13,'Sucre','ACTIVO'),
(209,13,'Tovar','ACTIVO'),
(210,13,'Tulio Febres Cordero','ACTIVO'),
(211,13,'Zea','ACTIVO'),
(212,14,'Acevedo','ACTIVO'),
(213,14,'Andrés Bello','ACTIVO'),
(214,14,'Baruta','ACTIVO'),
(215,14,'Brión','ACTIVO'),
(216,14,'Buroz','ACTIVO'),
(217,14,'Carrizal','ACTIVO'),
(218,14,'Chacao','ACTIVO'),
(219,14,'Cristóbal Rojas','ACTIVO'),
(220,14,'El Hatillo','ACTIVO'),
(221,14,'Guaicaipuro','ACTIVO'),
(222,14,'Independencia','ACTIVO'),
(223,14,'Lander','ACTIVO'),
(224,14,'Los Salias','ACTIVO'),
(225,14,'Páez','ACTIVO'),
(226,14,'Paz Castillo','ACTIVO'),
(227,14,'Plaza','ACTIVO'),
(228,14,'Simón Bolívar','ACTIVO'),
(229,14,'Sucre','ACTIVO'),
(230,14,'Urdaneta','ACTIVO'),
(231,14,'Zamora','ACTIVO'),
(232,15,'Acosta','ACTIVO'),
(233,15,'Aguasay','ACTIVO'),
(234,15,'Bolívar','ACTIVO'),
(235,15,'Caripe','ACTIVO'),
(236,15,'Cedeño','ACTIVO'),
(237,15,'Ezequiel Zamora','ACTIVO'),
(238,15,'Libertador','ACTIVO'),
(239,15,'Maturín','ACTIVO'),
(240,15,'Piar','ACTIVO'),
(241,15,'Punceres','ACTIVO'),
(242,15,'Santa Bárbara','ACTIVO'),
(243,15,'Sotillo','ACTIVO'),
(244,15,'Uracoa','ACTIVO'),
(245,16,'Antolín del Campo','ACTIVO'),
(246,16,'Arismendi','ACTIVO'),
(247,16,'Díaz','ACTIVO'),
(248,16,'García','ACTIVO'),
(249,16,'Gómez','ACTIVO'),
(250,16,'Maneiro','ACTIVO'),
(251,16,'Marcano','ACTIVO'),
(252,16,'Mariño','ACTIVO'),
(253,16,'Península de Macanao','ACTIVO'),
(254,16,'Tubores','ACTIVO'),
(255,16,'Villalba','ACTIVO'),
(256,17,'Agua Blanca','ACTIVO'),
(257,17,'Araure','ACTIVO'),
(258,17,'Esteller','ACTIVO'),
(259,17,'Guanare','ACTIVO'),
(260,17,'Guanarito','ACTIVO'),
(261,17,'Monseñor José Vicente de Unda','ACTIVO'),
(262,17,'Ospino','ACTIVO'),
(263,17,'Páez','ACTIVO'),
(264,17,'Papelón','ACTIVO'),
(265,17,'San Genaro de Boconoíto','ACTIVO'),
(266,17,'San Rafael de Onoto','ACTIVO'),
(267,17,'Santa Rosalía','ACTIVO'),
(268,17,'Sucre','ACTIVO'),
(269,17,'Turén','ACTIVO'),
(270,18,'Andrés Eloy Blanco','ACTIVO'),
(271,18,'Andrés Mata','ACTIVO'),
(272,18,'Arismendi','ACTIVO'),
(273,18,'Benítez','ACTIVO'),
(274,18,'Bermúdez','ACTIVO'),
(275,18,'Bolívar','ACTIVO'),
(276,18,'Cajigal','ACTIVO'),
(277,18,'Cruz Salmerón Acosta','ACTIVO'),
(278,18,'Libertador','ACTIVO'),
(279,18,'Mariño','ACTIVO'),
(280,18,'Mejía','ACTIVO'),
(281,18,'Montes','ACTIVO'),
(282,18,'Ribero','ACTIVO'),
(283,18,'Sucre','ACTIVO'),
(284,18,'Valdez','ACTIVO'),
(285,19,'Andrés Bello','ACTIVO'),
(286,19,'Antonio Rómulo Costa','ACTIVO'),
(287,19,'Ayacucho','ACTIVO'),
(288,19,'Bolívar','ACTIVO'),
(289,19,'Cárdenas','ACTIVO'),
(290,19,'Córdoba','ACTIVO'),
(291,19,'Fernández Feo','ACTIVO'),
(292,19,'Francisco de Miranda','ACTIVO'),
(293,19,'García de Hevia','ACTIVO'),
(294,19,'Guásimos','ACTIVO'),
(295,19,'Independencia','ACTIVO'),
(296,19,'Jáuregui','ACTIVO'),
(297,19,'José María Vargas','ACTIVO'),
(298,19,'Junín','ACTIVO'),
(299,19,'Libertad','ACTIVO'),
(300,19,'Libertador','ACTIVO'),
(301,19,'Lobatera','ACTIVO'),
(302,19,'Michelena','ACTIVO'),
(303,19,'Panamericano','ACTIVO'),
(304,19,'Pedro María Ureña','ACTIVO'),
(305,19,'Rafael Urdaneta','ACTIVO'),
(306,19,'Samuel Darío Maldonado','ACTIVO'),
(307,19,'San Cristóbal','ACTIVO'),
(308,19,'San Judas Tadeo','ACTIVO'),
(309,19,'Seboruco','ACTIVO'),
(310,19,'Simón Rodríguez','ACTIVO'),
(311,19,'Sucre','ACTIVO'),
(312,19,'Torbes','ACTIVO'),
(313,19,'Uribante','ACTIVO'),
(314,20,'Andrés Bello','ACTIVO'),
(315,20,'Boconó','ACTIVO'),
(316,20,'Bolívar','ACTIVO'),
(317,20,'Candelaria','ACTIVO'),
(318,20,'Carache','ACTIVO'),
(319,20,'Escuque','ACTIVO'),
(320,20,'José Felipe Márquez Cañizales','ACTIVO'),
(321,20,'Juan Vicente Campo Elías','ACTIVO'),
(322,20,'La Ceiba','ACTIVO'),
(323,20,'Miranda','ACTIVO'),
(324,20,'Monte Carmelo','ACTIVO'),
(325,20,'Motatán','ACTIVO'),
(326,20,'Pampán','ACTIVO'),
(327,20,'Pampanito','ACTIVO'),
(328,20,'Rafael Rangel','ACTIVO'),
(329,20,'San Rafael de Carvajal','ACTIVO'),
(330,20,'Sucre','ACTIVO'),
(331,20,'Trujillo','ACTIVO'),
(332,20,'Urdaneta','ACTIVO'),
(333,20,'Valera','ACTIVO'),
(334,21,'Vargas','ACTIVO'),
(335,22,'Arístides Bastidas','ACTIVO'),
(336,22,'Bolívar','ACTIVO'),
(337,22,'Bruzual','ACTIVO'),
(338,22,'Cocorote','ACTIVO'),
(339,22,'Independencia','ACTIVO'),
(340,22,'José Antonio Páez','ACTIVO'),
(341,22,'La Trinidad','ACTIVO'),
(342,22,'Manuel Monge','ACTIVO'),
(343,22,'Nirgua','ACTIVO'),
(344,22,'Peña','ACTIVO'),
(345,22,'San Felipe','ACTIVO'),
(346,22,'Sucre','ACTIVO'),
(347,22,'Urachiche','ACTIVO'),
(348,22,'Veroes','ACTIVO'),
(349,23,'Almirante Padilla','ACTIVO'),
(350,23,'Baralt','ACTIVO'),
(351,23,'Cabimas','ACTIVO'),
(352,23,'Catatumbo','ACTIVO'),
(353,23,'Colón','ACTIVO'),
(354,23,'Francisco Javier Pulgar','ACTIVO'),
(355,23,'Jesús Enrique Lossada','ACTIVO'),
(356,23,'Jesús María Semprún','ACTIVO'),
(357,23,'La Cañada de Urdaneta','ACTIVO'),
(358,23,'Lagunillas','ACTIVO'),
(359,23,'Machiques de Perijá','ACTIVO'),
(360,23,'Mara','ACTIVO'),
(361,23,'Maracaibo','ACTIVO'),
(362,23,'Miranda','ACTIVO'),
(363,23,'Páez','ACTIVO'),
(364,23,'Rosario de Perijá','ACTIVO'),
(365,23,'San Francisco','ACTIVO'),
(366,23,'Santa Rita','ACTIVO'),
(367,23,'Simón Bolívar','ACTIVO'),
(368,23,'Sucre','ACTIVO'),
(369,23,'Valmore Rodríguez','ACTIVO'),
(370,24,'Libertador','ACTIVO');
/*!40000 ALTER TABLE `municipio` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `nacionalidad`
--

DROP TABLE IF EXISTS `nacionalidad`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nacionalidad` (
  `id_nacionalidad` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_nacionalidad`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `nacionalidad`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `nacionalidad` WRITE;
/*!40000 ALTER TABLE `nacionalidad` DISABLE KEYS */;
INSERT INTO `nacionalidad` VALUES
(1,'Venezolano','ACTIVO'),
(2,'Colombiano','ACTIVO'),
(3,'Brasileño','ACTIVO'),
(4,'Peruano','ACTIVO'),
(5,'Ecuatoriano','ACTIVO'),
(6,'Boliviano','ACTIVO'),
(7,'Argentino','ACTIVO'),
(8,'Chileno','ACTIVO'),
(9,'Paraguayo','ACTIVO'),
(10,'Uruguayo','ACTIVO'),
(11,'Guyanesa','ACTIVO'),
(12,'Surinamense','ACTIVO'),
(13,'Panameño','ACTIVO'),
(14,'Costarricense','ACTIVO'),
(15,'Nicaragüense','ACTIVO'),
(16,'Hondureño','ACTIVO'),
(17,'Salvadoreño','ACTIVO'),
(18,'Guatemalteco','ACTIVO'),
(19,'Mexicano','ACTIVO'),
(20,'Estadounidense','ACTIVO'),
(21,'Español','ACTIVO'),
(22,'Portugués','ACTIVO'),
(23,'Italiano','ACTIVO'),
(24,'Dominicano','ACTIVO'),
(25,'Cubano','ACTIVO'),
(26,'Haitiano','ACTIVO');
/*!40000 ALTER TABLE `nacionalidad` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `notificacion`
--

DROP TABLE IF EXISTS `notificacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notificacion` (
  `id_notificacion` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario_destino` int(11) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `titulo` varchar(150) NOT NULL,
  `mensaje` text NOT NULL,
  `url_destino` varchar(255) NOT NULL,
  `id_referencia` int(11) DEFAULT NULL,
  `leida` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_lectura` datetime DEFAULT NULL,
  PRIMARY KEY (`id_notificacion`),
  KEY `idx_notificacion_usuario_leida` (`id_usuario_destino`,`leida`),
  KEY `idx_notificacion_fecha` (`fecha_creacion`),
  CONSTRAINT `fk_notificacion_usuario` FOREIGN KEY (`id_usuario_destino`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notificacion`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `notificacion` WRITE;
/*!40000 ALTER TABLE `notificacion` DISABLE KEYS */;
/*!40000 ALTER TABLE `notificacion` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `ocupacion`
--

DROP TABLE IF EXISTS `ocupacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ocupacion` (
  `id_ocupacion` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_ocupacion`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ocupacion`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `ocupacion` WRITE;
/*!40000 ALTER TABLE `ocupacion` DISABLE KEYS */;
INSERT INTO `ocupacion` VALUES
(1,'Médico','ACTIVO'),
(2,'Enfermero (externo)','ACTIVO'),
(3,'Odontólogo','ACTIVO'),
(4,'Abogado','ACTIVO'),
(5,'Ingeniero Civil','ACTIVO'),
(6,'Ingeniero de Sistemas','ACTIVO'),
(7,'Ingeniero Industrial','ACTIVO'),
(8,'Arquitecto','ACTIVO'),
(9,'Contador','ACTIVO'),
(10,'Administrador de Empresas','ACTIVO'),
(11,'Economista','ACTIVO'),
(12,'Psicólogo (externo)','ACTIVO'),
(13,'Trabajador Social (externo)','ACTIVO'),
(14,'Docente (en otro colegio)','ACTIVO'),
(15,'Comerciante / Dueño de negocio','ACTIVO'),
(16,'Vendedor','ACTIVO'),
(17,'Representante Comercial','ACTIVO'),
(18,'Agente de Seguros','ACTIVO'),
(19,'Empleado Administrativo','ACTIVO'),
(20,'Secretario (externo)','ACTIVO'),
(21,'Asistente Contable','ACTIVO'),
(22,'Cajero Bancario','ACTIVO'),
(23,'Chofer / Conductor','ACTIVO'),
(24,'Camionero','ACTIVO'),
(25,'Electricista','ACTIVO'),
(26,'Mecánico','ACTIVO'),
(27,'Albañil / Constructor','ACTIVO'),
(28,'Carpintero','ACTIVO'),
(29,'Plomero','ACTIVO'),
(30,'Operario de Fábrica','ACTIVO'),
(31,'Agricultor','ACTIVO'),
(32,'Ganadero','ACTIVO'),
(33,'Pescador','ACTIVO'),
(34,'Policía','ACTIVO'),
(35,'Militar','ACTIVO'),
(36,'Bombero','ACTIVO'),
(37,'Guardia de Seguridad (externo)','ACTIVO'),
(38,'Ama de Casa','ACTIVO'),
(39,'Jubilado / Pensionado','ACTIVO'),
(40,'Desempleado (en búsqueda activa)','ACTIVO'),
(41,'Estudiante','ACTIVO'),
(42,'Artista / Músico','ACTIVO'),
(43,'Diseñador Gráfico','ACTIVO'),
(44,'Chef / Cocinero (externo)','ACTIVO'),
(45,'Estilista / Peluquero','ACTIVO'),
(46,'Otro (especificar)','ACTIVO');
/*!40000 ALTER TABLE `ocupacion` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `pais`
--

DROP TABLE IF EXISTS `pais`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pais` (
  `id_pais` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(80) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_pais`),
  UNIQUE KEY `ux_pais_nombre` (`nombre`),
  KEY `idx_pais_estado` (`estado`)
) ENGINE=InnoDB AUTO_INCREMENT=178 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pais`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `pais` WRITE;
/*!40000 ALTER TABLE `pais` DISABLE KEYS */;
INSERT INTO `pais` VALUES
(1,'Venezuela','ACTIVO'),
(2,'Afganistán','ACTIVO'),
(3,'Albania','ACTIVO'),
(4,'Alemania','ACTIVO'),
(5,'Andorra','ACTIVO'),
(6,'Angola','ACTIVO'),
(7,'Argentina','ACTIVO'),
(8,'Armenia','ACTIVO'),
(9,'Australia','ACTIVO'),
(10,'Austria','ACTIVO'),
(11,'Azerbaiyán','ACTIVO'),
(12,'Bahamas','ACTIVO'),
(13,'Bangladés','ACTIVO'),
(14,'Barbados','ACTIVO'),
(15,'Bélgica','ACTIVO'),
(16,'Belice','ACTIVO'),
(17,'Benín','ACTIVO'),
(18,'Bielorrusia','ACTIVO'),
(19,'Birmania','ACTIVO'),
(20,'Bolivia','ACTIVO'),
(21,'Bosnia y Herzegovina','ACTIVO'),
(22,'Botsuana','ACTIVO'),
(23,'Brasil','ACTIVO'),
(24,'Brunei','ACTIVO'),
(25,'Bulgaria','ACTIVO'),
(26,'Burkina Faso','ACTIVO'),
(27,'Burundi','ACTIVO'),
(28,'Bután','ACTIVO'),
(29,'Camboya','ACTIVO'),
(30,'Camerún','ACTIVO'),
(31,'Cabo Verde','ACTIVO'),
(32,'Canadá','ACTIVO'),
(33,'Chad','ACTIVO'),
(34,'Catar','ACTIVO'),
(35,'Chile','ACTIVO'),
(36,'China','ACTIVO'),
(37,'Chipre','ACTIVO'),
(38,'Colombia','ACTIVO'),
(39,'Comoras','ACTIVO'),
(40,'Congo','ACTIVO'),
(41,'Corea del Norte','ACTIVO'),
(42,'Corea del Sur','ACTIVO'),
(43,'Costa de Marfil','ACTIVO'),
(44,'Costa Rica','ACTIVO'),
(45,'Croacia','ACTIVO'),
(46,'Cuba','ACTIVO'),
(47,'Dinamarca','ACTIVO'),
(48,'Djibouti','ACTIVO'),
(49,'Dominica','ACTIVO'),
(50,'Ecuador','ACTIVO'),
(51,'Egipto','ACTIVO'),
(52,'El Salvador','ACTIVO'),
(53,'Eslovaquia','ACTIVO'),
(54,'Eslovenia','ACTIVO'),
(55,'España','ACTIVO'),
(56,'Estados Unidos','ACTIVO'),
(57,'Etiopía','ACTIVO'),
(58,'Filipinas','ACTIVO'),
(59,'Finlandia','ACTIVO'),
(60,'Fiyi','ACTIVO'),
(61,'Francia','ACTIVO'),
(62,'Gabón','ACTIVO'),
(63,'Gambia','ACTIVO'),
(64,'Georgia','ACTIVO'),
(65,'Ghana','ACTIVO'),
(66,'Grecia','ACTIVO'),
(67,'Guatemala','ACTIVO'),
(68,'Guinea','ACTIVO'),
(69,'Guyana','ACTIVO'),
(70,'Haití','ACTIVO'),
(71,'Honduras','ACTIVO'),
(72,'Hungría','ACTIVO'),
(73,'India','ACTIVO'),
(74,'Indonesia','ACTIVO'),
(75,'Irak','ACTIVO'),
(76,'Irán','ACTIVO'),
(77,'Irlanda','ACTIVO'),
(78,'Islandia','ACTIVO'),
(79,'Israel','ACTIVO'),
(80,'Italia','ACTIVO'),
(81,'Jamaica','ACTIVO'),
(82,'Japón','ACTIVO'),
(83,'Jordania','ACTIVO'),
(84,'Kazajistán','ACTIVO'),
(85,'Kenia','ACTIVO'),
(86,'Kirguistán','ACTIVO'),
(87,'Kuwait','ACTIVO'),
(88,'Laos','ACTIVO'),
(89,'Lesoto','ACTIVO'),
(90,'Letonia','ACTIVO'),
(91,'Líbano','ACTIVO'),
(92,'Liberia','ACTIVO'),
(93,'Libia','ACTIVO'),
(94,'Liechtenstein','ACTIVO'),
(95,'Lituania','ACTIVO'),
(96,'Luxemburgo','ACTIVO'),
(97,'Macedonia del Norte','ACTIVO'),
(98,'Madagascar','ACTIVO'),
(99,'Malaui','ACTIVO'),
(100,'Malasia','ACTIVO'),
(101,'Maldivas','ACTIVO'),
(102,'Malí','ACTIVO'),
(103,'Malta','ACTIVO'),
(104,'Marruecos','ACTIVO'),
(105,'Mauricio','ACTIVO'),
(106,'México','ACTIVO'),
(107,'Moldavia','ACTIVO'),
(108,'Mónaco','ACTIVO'),
(109,'Mongolia','ACTIVO'),
(110,'Montenegro','ACTIVO'),
(111,'Mozambique','ACTIVO'),
(112,'Namibia','ACTIVO'),
(113,'Nauru','ACTIVO'),
(114,'Nepal','ACTIVO'),
(115,'Nicaragua','ACTIVO'),
(116,'Níger','ACTIVO'),
(117,'Nigeria','ACTIVO'),
(118,'Noruega','ACTIVO'),
(119,'Nueva Zelanda','ACTIVO'),
(120,'Omán','ACTIVO'),
(121,'Países Bajos','ACTIVO'),
(122,'Pakistán','ACTIVO'),
(123,'Palau','ACTIVO'),
(124,'Panamá','ACTIVO'),
(125,'Papúa Nueva Guinea','ACTIVO'),
(126,'Paraguay','ACTIVO'),
(127,'Perú','ACTIVO'),
(128,'Polonia','ACTIVO'),
(129,'Portugal','ACTIVO'),
(130,'Qatar','ACTIVO'),
(131,'Reino Unido','ACTIVO'),
(132,'República Centroafricana','ACTIVO'),
(133,'República Checa','ACTIVO'),
(134,'República de Moldavia','ACTIVO'),
(135,'República Dominicana','ACTIVO'),
(136,'Rumanía','ACTIVO'),
(137,'Rusia','ACTIVO'),
(138,'Ruanda','ACTIVO'),
(139,'San Cristóbal y Nieves','ACTIVO'),
(140,'San Marino','ACTIVO'),
(141,'San Vicente y las Granadinas','ACTIVO'),
(142,'Santa Lucía','ACTIVO'),
(143,'Santo Tomé y Príncipe','ACTIVO'),
(144,'Senegal','ACTIVO'),
(145,'Serbia','ACTIVO'),
(146,'Seychelles','ACTIVO'),
(147,'Sierra Leona','ACTIVO'),
(148,'Singapur','ACTIVO'),
(149,'Siria','ACTIVO'),
(150,'Somalia','ACTIVO'),
(151,'Sudán','ACTIVO'),
(152,'Sudán del Sur','ACTIVO'),
(153,'Suecia','ACTIVO'),
(154,'Suiza','ACTIVO'),
(155,'Tailandia','ACTIVO'),
(156,'Tanzania','ACTIVO'),
(157,'Tayikistán','ACTIVO'),
(158,'Togo','ACTIVO'),
(159,'Tonga','ACTIVO'),
(160,'Trinidad y Tobago','ACTIVO'),
(161,'Túnez','ACTIVO'),
(162,'Turkmenistán','ACTIVO'),
(163,'Turquía','ACTIVO'),
(164,'Tuvalu','ACTIVO'),
(165,'Ucrania','ACTIVO'),
(166,'Uganda','ACTIVO'),
(167,'Uruguay','ACTIVO'),
(168,'Uzbekistán','ACTIVO'),
(169,'Vanuatu','ACTIVO'),
(170,'Vaticano','ACTIVO'),
(171,'Vietnam','ACTIVO'),
(172,'Yemen','ACTIVO'),
(173,'Yibuti','ACTIVO'),
(174,'Zambia','ACTIVO'),
(175,'Zimbabue','ACTIVO'),
(176,'Puerto Rico','ACTIVO');
/*!40000 ALTER TABLE `pais` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `parentesco`
--

DROP TABLE IF EXISTS `parentesco`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `parentesco` (
  `id_parentesco` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `codigo` varchar(30) DEFAULT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_parentesco`),
  UNIQUE KEY `nombre` (`nombre`),
  UNIQUE KEY `uk_parentesco_codigo` (`codigo`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `parentesco`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `parentesco` WRITE;
/*!40000 ALTER TABLE `parentesco` DISABLE KEYS */;
INSERT INTO `parentesco` VALUES
(1,'Otro',NULL,'ACTIVO'),
(2,'Madre','madre','ACTIVO'),
(3,'Padre','padre','ACTIVO'),
(4,'Tía',NULL,'ACTIVO'),
(5,'Tío',NULL,'ACTIVO'),
(6,'Abuela',NULL,'ACTIVO'),
(7,'Abuelo',NULL,'ACTIVO'),
(8,'Hermana',NULL,'ACTIVO'),
(9,'Hermano',NULL,'ACTIVO'),
(10,'Tía abuela',NULL,'ACTIVO'),
(11,'Prima',NULL,'ACTIVO'),
(12,'Primo',NULL,'ACTIVO'),
(13,'Vecina',NULL,'ACTIVO'),
(14,'Vecino',NULL,'ACTIVO'),
(15,'Tutora',NULL,'ACTIVO'),
(16,'Tutor',NULL,'ACTIVO'),
(17,'Responsable',NULL,'ACTIVO');
/*!40000 ALTER TABLE `parentesco` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `parroquia`
--

DROP TABLE IF EXISTS `parroquia`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `parroquia` (
  `id_parroquia` int(11) NOT NULL AUTO_INCREMENT,
  `id_municipio` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_parroquia`),
  UNIQUE KEY `uk_parroquia_municipio_nombre` (`id_municipio`,`nombre`),
  KEY `idx_parroquia_nombre` (`nombre`),
  CONSTRAINT `fk_parroquia_municipio` FOREIGN KEY (`id_municipio`) REFERENCES `municipio` (`id_municipio`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1113 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `parroquia`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `parroquia` WRITE;
/*!40000 ALTER TABLE `parroquia` DISABLE KEYS */;
INSERT INTO `parroquia` VALUES
(1,14,'Candelaria','ACTIVO'),
(2,14,'Catedral','ACTIVO'),
(3,14,'El Socorro','ACTIVO'),
(4,14,'Miguel Peña','ACTIVO'),
(5,14,'Rafael Urdaneta','ACTIVO'),
(6,14,'San Blas','ACTIVO'),
(7,14,'San José','ACTIVO'),
(8,14,'Santa Rosa','ACTIVO'),
(9,14,'Negro Primero','ACTIVO'),
(10,7,'Güigüe','ACTIVO'),
(11,7,'Belén','ACTIVO'),
(12,7,'Tacarigua','ACTIVO'),
(13,7,'Mariara','ACTIVO'),
(14,7,'Aguas Calientes','ACTIVO'),
(15,7,'San Joaquín','ACTIVO'),
(16,7,'San Juan Pablo II','ACTIVO'),
(17,7,'Guacara','ACTIVO'),
(18,7,'Ciudad Alianza','ACTIVO'),
(19,7,'Yagua','ACTIVO'),
(20,7,'Morón','ACTIVO'),
(21,7,'Urama','ACTIVO'),
(22,7,'Tocuyito','ACTIVO'),
(23,7,'Independencia','ACTIVO'),
(24,7,'Los Guayos','ACTIVO'),
(25,7,'Agua Dulce','ACTIVO'),
(26,7,'Miranda','ACTIVO'),
(27,7,'Bejuma','ACTIVO'),
(28,7,'Canoabo','ACTIVO'),
(29,7,'Simón Bolívar','ACTIVO'),
(30,7,'San Diego','ACTIVO'),
(31,7,'Bartolomé Salom','ACTIVO'),
(32,7,'Democracia','ACTIVO'),
(33,7,'Fraternidad','ACTIVO'),
(34,7,'Goaigoaza','ACTIVO'),
(35,7,'Juan José Flores','ACTIVO'),
(36,7,'Unión','ACTIVO'),
(37,7,'Borburata','ACTIVO'),
(38,7,'Patanemo','ACTIVO'),
(39,7,'Naguanagua','ACTIVO'),
(40,7,'Bárbula','ACTIVO'),
(41,7,'Las Trincheras','ACTIVO'),
(42,7,'Montalbán','ACTIVO'),
(43,15,'Alto Orinoco','ACTIVO'),
(44,15,'Huachamacare Acanaña','ACTIVO'),
(45,15,'Marawaka Toky Shamanaña','ACTIVO'),
(46,15,'Mavaka Mavaka','ACTIVO'),
(47,15,'Sierra Parima Parimabé','ACTIVO'),
(48,16,'Ucata Laja Lisa','ACTIVO'),
(49,16,'Yapacana Macuruco','ACTIVO'),
(50,16,'Caname Guarinuma','ACTIVO'),
(51,17,'Fernando Girón Tovar','ACTIVO'),
(52,17,'Luis Alberto Gómez','ACTIVO'),
(53,17,'Pahueña Limón de Parhueña','ACTIVO'),
(54,17,'Platanillal Platanillal','ACTIVO'),
(55,18,'Samariapo','ACTIVO'),
(56,18,'Sipapo','ACTIVO'),
(57,18,'Munduapo','ACTIVO'),
(58,18,'Guayapo','ACTIVO'),
(59,19,'Alto Ventuari','ACTIVO'),
(60,19,'Medio Ventuari','ACTIVO'),
(61,19,'Bajo Ventuari','ACTIVO'),
(62,20,'Victorino','ACTIVO'),
(63,20,'Comunidad','ACTIVO'),
(64,21,'Casiquiare','ACTIVO'),
(65,21,'Cocuy','ACTIVO'),
(66,21,'San Carlos de Río Negro','ACTIVO'),
(67,21,'Solano','ACTIVO'),
(68,36,'Anaco','ACTIVO'),
(69,36,'San Joaquín','ACTIVO'),
(70,37,'Cachipo','ACTIVO'),
(71,37,'Aragua de Barcelona','ACTIVO'),
(72,51,'Clarines','ACTIVO'),
(73,51,'Guanape','ACTIVO'),
(74,51,'Sabana de Uchire','ACTIVO'),
(75,42,'Lechería','ACTIVO'),
(76,42,'El Morro','ACTIVO'),
(77,45,'Guanta','ACTIVO'),
(78,45,'Chorrerón','ACTIVO'),
(79,46,'Mamo','ACTIVO'),
(80,46,'Soledad','ACTIVO'),
(81,47,'Mapire','ACTIVO'),
(82,47,'Piar','ACTIVO'),
(83,47,'Santa Clara','ACTIVO'),
(84,47,'San Diego de Cabrutica','ACTIVO'),
(85,47,'Uverito','ACTIVO'),
(86,47,'Zuata','ACTIVO'),
(87,48,'Puerto La Cruz','ACTIVO'),
(88,48,'Pozuelos','ACTIVO'),
(89,49,'Onoto','ACTIVO'),
(90,49,'San Pablo','ACTIVO'),
(91,50,'San Mateo','ACTIVO'),
(92,50,'El Carito','ACTIVO'),
(93,50,'Santa Inés','ACTIVO'),
(94,50,'La Romereña','ACTIVO'),
(95,52,'Cantaura','ACTIVO'),
(96,52,'Libertador','ACTIVO'),
(97,52,'Santa Rosa','ACTIVO'),
(98,52,'Urica','ACTIVO'),
(99,53,'Píritu','ACTIVO'),
(100,53,'San Francisco','ACTIVO'),
(101,54,'San José de Guanipa','ACTIVO'),
(102,55,'Pueblo Nuevo','ACTIVO'),
(103,55,'Santa Ana','ACTIVO'),
(104,56,'Edmundo Barrios','ACTIVO'),
(105,56,'Miguel Otero Silva','ACTIVO'),
(106,57,'Achaguas','ACTIVO'),
(107,57,'Apurito','ACTIVO'),
(108,57,'El Yagual','ACTIVO'),
(109,57,'Guachara','ACTIVO'),
(110,57,'Mucuritas','ACTIVO'),
(111,57,'Queseras del medio','ACTIVO'),
(112,58,'Biruaca','ACTIVO'),
(113,59,'Bruzual','ACTIVO'),
(114,59,'Mantecal','ACTIVO'),
(115,59,'Quintero','ACTIVO'),
(116,59,'Rincón Hondo','ACTIVO'),
(117,59,'San Vicente','ACTIVO'),
(118,60,'Guasdualito','ACTIVO'),
(119,60,'Aramendi','ACTIVO'),
(120,60,'El Amparo','ACTIVO'),
(121,60,'San Camilo','ACTIVO'),
(122,60,'Urdaneta','ACTIVO'),
(123,61,'San Juan de Payara','ACTIVO'),
(124,61,'Codazzi','ACTIVO'),
(125,61,'Cunaviche','ACTIVO'),
(126,62,'Elorza','ACTIVO'),
(127,62,'La Trinidad','ACTIVO'),
(128,63,'San Fernando','ACTIVO'),
(129,63,'El Recreo','ACTIVO'),
(130,63,'Peñalver','ACTIVO'),
(131,63,'San Rafael de Atamaica','ACTIVO'),
(132,71,'Bolívar','ACTIVO'),
(133,72,'Camatagua','ACTIVO'),
(134,72,'Carmen de Cura','ACTIVO'),
(135,73,'Santa Rita','ACTIVO'),
(136,73,'Francisco de Miranda','ACTIVO'),
(137,73,'Moseñor Feliciano González','ACTIVO'),
(138,75,'Santa Cruz','ACTIVO'),
(139,76,'José Félix Ribas','ACTIVO'),
(140,76,'Castor Nieves Ríos','ACTIVO'),
(141,76,'Las Guacamayas','ACTIVO'),
(142,76,'Pao de Zárate','ACTIVO'),
(143,76,'Zuata','ACTIVO'),
(144,77,'José Rafael Revenga','ACTIVO'),
(145,78,'Palo Negro','ACTIVO'),
(146,78,'San Martín de Porres','ACTIVO'),
(147,79,'El Limón','ACTIVO'),
(148,79,'Caña de Azúcar','ACTIVO'),
(149,80,'Ocumare de la Costa','ACTIVO'),
(150,81,'San Casimiro','ACTIVO'),
(151,81,'Güiripa','ACTIVO'),
(152,81,'Ollas de Caramacate','ACTIVO'),
(153,81,'Valle Morín','ACTIVO'),
(154,82,'San Sebastían','ACTIVO'),
(155,83,'Turmero','ACTIVO'),
(156,83,'Arevalo Aponte','ACTIVO'),
(157,83,'Chuao','ACTIVO'),
(158,83,'Samán de Güere','ACTIVO'),
(159,83,'Alfredo Pacheco Miranda','ACTIVO'),
(160,84,'Santos Michelena','ACTIVO'),
(161,84,'Tiara','ACTIVO'),
(162,85,'Cagua','ACTIVO'),
(163,85,'Bella Vista','ACTIVO'),
(164,86,'Tovar','ACTIVO'),
(165,87,'Urdaneta','ACTIVO'),
(166,87,'Las Peñitas','ACTIVO'),
(167,87,'San Francisco de Cara','ACTIVO'),
(168,87,'Taguay','ACTIVO'),
(169,88,'Zamora','ACTIVO'),
(170,88,'Magdaleno','ACTIVO'),
(171,88,'San Francisco de Asís','ACTIVO'),
(172,88,'Valles de Tucutunemo','ACTIVO'),
(173,88,'Augusto Mijares','ACTIVO'),
(174,89,'Sabaneta','ACTIVO'),
(175,89,'Juan Antonio Rodríguez Domínguez','ACTIVO'),
(176,90,'El Cantón','ACTIVO'),
(177,90,'Santa Cruz de Guacas','ACTIVO'),
(178,90,'Puerto Vivas','ACTIVO'),
(179,91,'Ticoporo','ACTIVO'),
(180,91,'Nicolás Pulido','ACTIVO'),
(181,91,'Andrés Bello','ACTIVO'),
(182,92,'Arismendi','ACTIVO'),
(183,92,'Guadarrama','ACTIVO'),
(184,92,'La Unión','ACTIVO'),
(185,92,'San Antonio','ACTIVO'),
(186,93,'Barinas','ACTIVO'),
(187,93,'Alberto Arvelo Larriva','ACTIVO'),
(188,93,'San Silvestre','ACTIVO'),
(189,93,'Santa Inés','ACTIVO'),
(190,93,'Santa Lucía','ACTIVO'),
(191,93,'Torumos','ACTIVO'),
(192,93,'El Carmen','ACTIVO'),
(193,93,'Rómulo Betancourt','ACTIVO'),
(194,93,'Corazón de Jesús','ACTIVO'),
(195,93,'Ramón Ignacio Méndez','ACTIVO'),
(196,93,'Alto Barinas','ACTIVO'),
(197,93,'Manuel Palacio Fajardo','ACTIVO'),
(198,93,'Juan Antonio Rodríguez Domínguez','ACTIVO'),
(199,93,'Dominga Ortiz de Páez','ACTIVO'),
(200,94,'Barinitas','ACTIVO'),
(201,94,'Altamira de Cáceres','ACTIVO'),
(202,94,'Calderas','ACTIVO'),
(203,95,'Barrancas','ACTIVO'),
(204,95,'El Socorro','ACTIVO'),
(205,95,'Mazparrito','ACTIVO'),
(206,96,'Santa Bárbara','ACTIVO'),
(207,96,'Pedro Briceño Méndez','ACTIVO'),
(208,96,'Ramón Ignacio Méndez','ACTIVO'),
(209,96,'José Ignacio del Pumar','ACTIVO'),
(210,97,'Obispos','ACTIVO'),
(211,97,'Guasimitos','ACTIVO'),
(212,97,'El Real','ACTIVO'),
(213,97,'La Luz','ACTIVO'),
(214,99,'Ciudad Bolívia','ACTIVO'),
(215,99,'José Ignacio Briceño','ACTIVO'),
(216,99,'José Félix Ribas','ACTIVO'),
(217,99,'Páez','ACTIVO'),
(218,100,'Libertad','ACTIVO'),
(219,100,'Dolores','ACTIVO'),
(220,100,'Santa Rosa','ACTIVO'),
(221,100,'Palacio Fajardo','ACTIVO'),
(222,101,'Ciudad de Nutrias','ACTIVO'),
(223,101,'El Regalo','ACTIVO'),
(224,101,'Puerto Nutrias','ACTIVO'),
(225,101,'Santa Catalina','ACTIVO'),
(226,102,'Cachamay','ACTIVO'),
(227,102,'Chirica','ACTIVO'),
(228,102,'Dalla Costa','ACTIVO'),
(229,102,'Once de Abril','ACTIVO'),
(230,102,'Simón Bolívar','ACTIVO'),
(231,102,'Unare','ACTIVO'),
(232,102,'Universidad','ACTIVO'),
(233,102,'Vista al Sol','ACTIVO'),
(234,102,'Pozo Verde','ACTIVO'),
(235,102,'Yocoima','ACTIVO'),
(236,102,'5 de Julio','ACTIVO'),
(237,103,'Cedeño','ACTIVO'),
(238,103,'Altagracia','ACTIVO'),
(239,103,'Ascensión Farreras','ACTIVO'),
(240,103,'Guaniamo','ACTIVO'),
(241,103,'La Urbana','ACTIVO'),
(242,103,'Pijiguaos','ACTIVO'),
(243,104,'El Callao','ACTIVO'),
(244,105,'Gran Sabana','ACTIVO'),
(245,105,'Ikabarú','ACTIVO'),
(246,106,'Catedral','ACTIVO'),
(247,106,'Zea','ACTIVO'),
(248,106,'Orinoco','ACTIVO'),
(249,106,'José Antonio Páez','ACTIVO'),
(250,106,'Marhuanta','ACTIVO'),
(251,106,'Agua Salada','ACTIVO'),
(252,106,'Vista Hermosa','ACTIVO'),
(253,106,'La Sabanita','ACTIVO'),
(254,106,'Panapana','ACTIVO'),
(255,108,'Andrés Eloy Blanco','ACTIVO'),
(256,108,'Pedro Cova','ACTIVO'),
(257,110,'Roscio','ACTIVO'),
(258,110,'Salóm','ACTIVO'),
(259,111,'Sifontes','ACTIVO'),
(260,111,'Dalla Costa','ACTIVO'),
(261,111,'San Isidro','ACTIVO'),
(262,112,'Sucre','ACTIVO'),
(263,112,'Aripao','ACTIVO'),
(264,112,'Guarataro','ACTIVO'),
(265,112,'Las Majadas','ACTIVO'),
(266,112,'Moitaco','ACTIVO'),
(267,107,'Padre Pedro Chien','ACTIVO'),
(268,107,'Río Grande','ACTIVO'),
(269,1,'Bejuma','ACTIVO'),
(270,1,'Canoabo','ACTIVO'),
(271,1,'Simón Bolívar','ACTIVO'),
(272,2,'Güigüe','ACTIVO'),
(273,2,'Carabobo','ACTIVO'),
(274,2,'Tacarigua','ACTIVO'),
(275,3,'Mariara','ACTIVO'),
(276,3,'Aguas Calientes','ACTIVO'),
(277,4,'Ciudad Alianza','ACTIVO'),
(278,4,'Guacara','ACTIVO'),
(279,4,'Yagua','ACTIVO'),
(280,5,'Morón','ACTIVO'),
(281,5,'Yagua','ACTIVO'),
(282,6,'Tocuyito','ACTIVO'),
(283,6,'Independencia','ACTIVO'),
(284,8,'Miranda','ACTIVO'),
(285,9,'Montalbán','ACTIVO'),
(286,10,'Naguanagua','ACTIVO'),
(287,11,'Bartolomé Salóm','ACTIVO'),
(288,11,'Democracia','ACTIVO'),
(289,11,'Fraternidad','ACTIVO'),
(290,11,'Goaigoaza','ACTIVO'),
(291,11,'Juan José Flores','ACTIVO'),
(292,11,'Unión','ACTIVO'),
(293,11,'Borburata','ACTIVO'),
(294,11,'Patanemo','ACTIVO'),
(295,12,'San Diego','ACTIVO'),
(296,13,'San Joaquín','ACTIVO'),
(297,127,'Cojedes','ACTIVO'),
(298,127,'Juan de Mata Suárez','ACTIVO'),
(299,129,'El Baúl','ACTIVO'),
(300,129,'Sucre','ACTIVO'),
(301,130,'La Aguadita','ACTIVO'),
(302,130,'Macapo','ACTIVO'),
(303,131,'El Pao','ACTIVO'),
(304,132,'El Amparo','ACTIVO'),
(305,132,'Libertad de Cojedes','ACTIVO'),
(306,133,'Rómulo Gallegos','ACTIVO'),
(307,134,'San Carlos de Austria','ACTIVO'),
(308,134,'Juan Ángel Bravo','ACTIVO'),
(309,134,'Manuel Manrique','ACTIVO'),
(310,135,'General en Jefe José Laurencio Silva','ACTIVO'),
(311,136,'Curiapo','ACTIVO'),
(312,136,'Almirante Luis Brión','ACTIVO'),
(313,136,'Francisco Aniceto Lugo','ACTIVO'),
(314,136,'Manuel Renaud','ACTIVO'),
(315,136,'Padre Barral','ACTIVO'),
(316,136,'Santos de Abelgas','ACTIVO'),
(317,137,'Imataca','ACTIVO'),
(318,137,'Cinco de Julio','ACTIVO'),
(319,137,'Juan Bautista Arismendi','ACTIVO'),
(320,137,'Manuel Piar','ACTIVO'),
(321,137,'Rómulo Gallegos','ACTIVO'),
(322,138,'Pedernales','ACTIVO'),
(323,138,'Luis Beltrán Prieto Figueroa','ACTIVO'),
(324,139,'José Vidal Marcano','ACTIVO'),
(325,139,'Juan Millán','ACTIVO'),
(326,139,'Leonardo Ruíz Pineda','ACTIVO'),
(327,139,'Mariscal Antonio José de Sucre','ACTIVO'),
(328,139,'Monseñor Argimiro García','ACTIVO'),
(329,139,'Virgen del Valle','ACTIVO'),
(330,140,'Capadare','ACTIVO'),
(331,140,'La Pastora','ACTIVO'),
(332,140,'Libertador','ACTIVO'),
(333,140,'San Juan de los Cayos','ACTIVO'),
(334,141,'Aracua','ACTIVO'),
(335,141,'La Peña','ACTIVO'),
(336,141,'San Luis','ACTIVO'),
(337,142,'Bariro','ACTIVO'),
(338,142,'Borojó','ACTIVO'),
(339,142,'Capatárida','ACTIVO'),
(340,142,'Guajiro','ACTIVO'),
(341,142,'Seque','ACTIVO'),
(342,142,'Zazárida','ACTIVO'),
(343,142,'Valle de Eroa','ACTIVO'),
(344,143,'Cacique Manaure','ACTIVO'),
(345,144,'Norte','ACTIVO'),
(346,144,'Carirubana','ACTIVO'),
(347,144,'Santa Ana','ACTIVO'),
(348,144,'Urbana Punta Cardón','ACTIVO'),
(349,145,'La Vela de Coro','ACTIVO'),
(350,145,'Acurigua','ACTIVO'),
(351,145,'Guaibacoa','ACTIVO'),
(352,145,'Las Calderas','ACTIVO'),
(353,145,'Macoruca','ACTIVO'),
(354,146,'Dabajuro','ACTIVO'),
(355,147,'Agua Clara','ACTIVO'),
(356,147,'Avaria','ACTIVO'),
(357,147,'Pedregal','ACTIVO'),
(358,147,'Piedra Grande','ACTIVO'),
(359,147,'Purureche','ACTIVO'),
(360,148,'Adaure','ACTIVO'),
(361,148,'Adícora','ACTIVO'),
(362,148,'Baraived','ACTIVO'),
(363,148,'Buena Vista','ACTIVO'),
(364,148,'Jadacaquiva','ACTIVO'),
(365,148,'El Vínculo','ACTIVO'),
(366,148,'El Hato','ACTIVO'),
(367,148,'Moruy','ACTIVO'),
(368,148,'Pueblo Nuevo','ACTIVO'),
(369,149,'Agua Larga','ACTIVO'),
(370,149,'El Paují','ACTIVO'),
(371,149,'Independencia','ACTIVO'),
(372,149,'Mapararí','ACTIVO'),
(373,149,'Churuguara','ACTIVO'),
(374,150,'Agua Linda','ACTIVO'),
(375,150,'Araurima','ACTIVO'),
(376,150,'Jacura','ACTIVO'),
(377,151,'Tucacas','ACTIVO'),
(378,151,'Boca de Aroa','ACTIVO'),
(379,152,'Los Taques','ACTIVO'),
(380,152,'Judibana','ACTIVO'),
(381,153,'Mene de Mauroa','ACTIVO'),
(382,153,'San Félix','ACTIVO'),
(383,153,'Casigua','ACTIVO'),
(384,154,'Guzmán Guillermo','ACTIVO'),
(385,154,'Mitare','ACTIVO'),
(386,154,'Río Seco','ACTIVO'),
(387,154,'Sabaneta','ACTIVO'),
(388,154,'San Antonio','ACTIVO'),
(389,154,'San Gabriel','ACTIVO'),
(390,154,'Santa Ana','ACTIVO'),
(391,155,'Boca del Tocuyo','ACTIVO'),
(392,155,'Chichiriviche','ACTIVO'),
(393,155,'Tocuyo de la Costa','ACTIVO'),
(394,156,'Palmasola','ACTIVO'),
(395,157,'Cabure','ACTIVO'),
(396,157,'Colina','ACTIVO'),
(397,157,'Curimagua','ACTIVO'),
(398,158,'San José de la Costa','ACTIVO'),
(399,158,'Píritu','ACTIVO'),
(400,159,'San Francisco','ACTIVO'),
(401,160,'Sucre','ACTIVO'),
(402,160,'Pecaya','ACTIVO'),
(403,161,'Tocópero','ACTIVO'),
(404,162,'El Charal','ACTIVO'),
(405,162,'Las Vegas del Tuy','ACTIVO'),
(406,162,'Santa Cruz de Bucaral','ACTIVO'),
(407,163,'Bruzual','ACTIVO'),
(408,163,'Urumaco','ACTIVO'),
(409,164,'Puerto Cumarebo','ACTIVO'),
(410,164,'La Ciénaga','ACTIVO'),
(411,164,'La Soledad','ACTIVO'),
(412,164,'Pueblo Cumarebo','ACTIVO'),
(413,164,'Zazárida','ACTIVO'),
(414,165,'Camaguán','ACTIVO'),
(415,165,'Puerto Miranda','ACTIVO'),
(416,165,'Uverito','ACTIVO'),
(417,166,'Chaguaramas','ACTIVO'),
(418,167,'El Socorro','ACTIVO'),
(419,169,'Tucupido','ACTIVO'),
(420,169,'San Rafael de Laya','ACTIVO'),
(421,170,'Altagracia de Orituco','ACTIVO'),
(422,170,'San Rafael de Orituco','ACTIVO'),
(423,170,'San Francisco Javier de Lezama','ACTIVO'),
(424,170,'Paso Real de Macaira','ACTIVO'),
(425,170,'Carlos Soublette','ACTIVO'),
(426,170,'San Francisco de Macaira','ACTIVO'),
(427,170,'Libertad de Orituco','ACTIVO'),
(428,171,'Cantaclaro','ACTIVO'),
(429,171,'San Juan de los Morros','ACTIVO'),
(430,171,'Parapara','ACTIVO'),
(431,173,'El Sombrero','ACTIVO'),
(432,173,'Sosa','ACTIVO'),
(433,174,'Valle de la Pascua','ACTIVO'),
(434,174,'Espino','ACTIVO'),
(435,175,'San José de Tiznados','ACTIVO'),
(436,175,'San Francisco de Tiznados','ACTIVO'),
(437,175,'San Lorenzo de Tiznados','ACTIVO'),
(438,175,'Ortiz','ACTIVO'),
(439,176,'Guayabal','ACTIVO'),
(440,176,'Cazorla','ACTIVO'),
(441,177,'San José de Guaribe','ACTIVO'),
(442,177,'Uveral','ACTIVO'),
(443,178,'Santa María de Ipire','ACTIVO'),
(444,178,'Altamira','ACTIVO'),
(445,180,'Quebrada Honda de Guache','ACTIVO'),
(446,180,'Pío Tamayo','ACTIVO'),
(447,180,'Yacambú','ACTIVO'),
(448,181,'Fréitez','ACTIVO'),
(449,181,'José María Blanco','ACTIVO'),
(450,182,'Catedral','ACTIVO'),
(451,182,'Concepción','ACTIVO'),
(452,182,'El Cují','ACTIVO'),
(453,182,'Juan de Villegas','ACTIVO'),
(454,182,'Santa Rosa','ACTIVO'),
(455,182,'Tamaca','ACTIVO'),
(456,182,'Unión','ACTIVO'),
(457,182,'Aguedo Felipe Alvarado','ACTIVO'),
(458,182,'Buena Vista','ACTIVO'),
(459,182,'Juárez','ACTIVO'),
(460,183,'Juan Bautista Rodríguez','ACTIVO'),
(461,183,'Cuara','ACTIVO'),
(462,183,'Diego de Lozada','ACTIVO'),
(463,183,'Paraíso de San José','ACTIVO'),
(464,183,'San Miguel','ACTIVO'),
(465,183,'Tintorero','ACTIVO'),
(466,183,'José Bernardo Dorante','ACTIVO'),
(467,183,'Coronel Mariano Peraza ','ACTIVO'),
(468,184,'Bolívar','ACTIVO'),
(469,184,'Anzoátegui','ACTIVO'),
(470,184,'Guarico','ACTIVO'),
(471,184,'Hilario Luna y Luna','ACTIVO'),
(472,184,'Humocaro Alto','ACTIVO'),
(473,184,'Humocaro Bajo','ACTIVO'),
(474,184,'La Candelaria','ACTIVO'),
(475,184,'Morán','ACTIVO'),
(476,185,'Cabudare','ACTIVO'),
(477,185,'José Gregorio Bastidas','ACTIVO'),
(478,185,'Agua Viva','ACTIVO'),
(479,186,'Sarare','ACTIVO'),
(480,186,'Buría','ACTIVO'),
(481,186,'Gustavo Vegas León','ACTIVO'),
(482,187,'Trinidad Samuel','ACTIVO'),
(483,187,'Antonio Díaz','ACTIVO'),
(484,187,'Camacaro','ACTIVO'),
(485,187,'Castañeda','ACTIVO'),
(486,187,'Cecilio Zubillaga','ACTIVO'),
(487,187,'Chiquinquirá','ACTIVO'),
(488,187,'El Blanco','ACTIVO'),
(489,187,'Espinoza de los Monteros','ACTIVO'),
(490,187,'Lara','ACTIVO'),
(491,187,'Las Mercedes','ACTIVO'),
(492,187,'Manuel Morillo','ACTIVO'),
(493,187,'Montaña Verde','ACTIVO'),
(494,187,'Montes de Oca','ACTIVO'),
(495,187,'Torres','ACTIVO'),
(496,187,'Heriberto Arroyo','ACTIVO'),
(497,187,'Reyes Vargas','ACTIVO'),
(498,187,'Altagracia','ACTIVO'),
(499,188,'Siquisique','ACTIVO'),
(500,188,'Moroturo','ACTIVO'),
(501,188,'San Miguel','ACTIVO'),
(502,188,'Xaguas','ACTIVO'),
(503,189,'Presidente Betancourt','ACTIVO'),
(504,189,'Presidente Páez','ACTIVO'),
(505,189,'Presidente Rómulo Gallegos','ACTIVO'),
(506,189,'Gabriel Picón González','ACTIVO'),
(507,189,'Héctor Amable Mora','ACTIVO'),
(508,189,'José Nucete Sardi','ACTIVO'),
(509,189,'Pulido Méndez','ACTIVO'),
(510,190,'La Azulita','ACTIVO'),
(511,191,'Santa Cruz de Mora','ACTIVO'),
(512,191,'Mesa Bolívar','ACTIVO'),
(513,191,'Mesa de Las Palmas','ACTIVO'),
(514,192,'Aricagua','ACTIVO'),
(515,192,'San Antonio','ACTIVO'),
(516,193,'Canagua','ACTIVO'),
(517,193,'Capurí','ACTIVO'),
(518,193,'Chacantá','ACTIVO'),
(519,193,'El Molino','ACTIVO'),
(520,193,'Guaimaral','ACTIVO'),
(521,193,'Mucutuy','ACTIVO'),
(522,193,'Mucuchachí','ACTIVO'),
(523,194,'Fernández Peña','ACTIVO'),
(524,194,'Matriz','ACTIVO'),
(525,194,'Montalbán','ACTIVO'),
(526,194,'Acequias','ACTIVO'),
(527,194,'Jají','ACTIVO'),
(528,194,'La Mesa','ACTIVO'),
(529,194,'San José del Sur','ACTIVO'),
(530,195,'Tucaní','ACTIVO'),
(531,195,'Florencio Ramírez','ACTIVO'),
(532,196,'Santo Domingo','ACTIVO'),
(533,196,'Las Piedras','ACTIVO'),
(534,197,'Guaraque','ACTIVO'),
(535,197,'Mesa de Quintero','ACTIVO'),
(536,197,'Río Negro','ACTIVO'),
(537,198,'Arapuey','ACTIVO'),
(538,198,'Palmira','ACTIVO'),
(539,199,'San Cristóbal de Torondoy','ACTIVO'),
(540,199,'Torondoy','ACTIVO'),
(541,200,'Antonio Spinetti Dini','ACTIVO'),
(542,200,'Arias','ACTIVO'),
(543,200,'Caracciolo Parra Pérez','ACTIVO'),
(544,200,'Domingo Peña','ACTIVO'),
(545,200,'El Llano','ACTIVO'),
(546,200,'Gonzalo Picón Febres','ACTIVO'),
(547,200,'Jacinto Plaza','ACTIVO'),
(548,200,'Juan Rodríguez Suárez','ACTIVO'),
(549,200,'Lasso de la Vega','ACTIVO'),
(550,200,'Mariano Picón Salas','ACTIVO'),
(551,200,'Milla','ACTIVO'),
(552,200,'Osuna Rodríguez','ACTIVO'),
(553,200,'Sagrario','ACTIVO'),
(554,200,'El Morro','ACTIVO'),
(555,200,'Los Nevados','ACTIVO'),
(556,201,'Andrés Eloy Blanco','ACTIVO'),
(557,201,'La Venta','ACTIVO'),
(558,201,'Piñango','ACTIVO'),
(559,201,'Timotes','ACTIVO'),
(560,202,'Eloy Paredes','ACTIVO'),
(561,202,'San Rafael de Alcázar','ACTIVO'),
(562,202,'Santa Elena de Arenales','ACTIVO'),
(563,203,'Santa María de Caparo','ACTIVO'),
(564,204,'Pueblo Llano','ACTIVO'),
(565,205,'Cacute','ACTIVO'),
(566,205,'La Toma','ACTIVO'),
(567,205,'Mucuchíes','ACTIVO'),
(568,205,'Mucurubá','ACTIVO'),
(569,205,'San Rafael','ACTIVO'),
(570,206,'Gerónimo Maldonado','ACTIVO'),
(571,206,'Bailadores','ACTIVO'),
(572,207,'Tabay','ACTIVO'),
(573,208,'Chiguará','ACTIVO'),
(574,208,'Estánquez','ACTIVO'),
(575,208,'Lagunillas','ACTIVO'),
(576,208,'La Trampa','ACTIVO'),
(577,208,'Pueblo Nuevo del Sur','ACTIVO'),
(578,208,'San Juan','ACTIVO'),
(579,209,'El Amparo','ACTIVO'),
(580,209,'El Llano','ACTIVO'),
(581,209,'San Francisco','ACTIVO'),
(582,209,'Tovar','ACTIVO'),
(583,210,'Independencia','ACTIVO'),
(584,210,'María de la Concepción Palacios Blanco','ACTIVO'),
(585,210,'Nueva Bolivia','ACTIVO'),
(586,210,'Santa Apolonia','ACTIVO'),
(587,211,'Caño El Tigre','ACTIVO'),
(588,211,'Zea','ACTIVO'),
(589,212,'Aragüita','ACTIVO'),
(590,212,'Arévalo González','ACTIVO'),
(591,212,'Capaya','ACTIVO'),
(592,212,'Caucagua','ACTIVO'),
(593,212,'Panaquire','ACTIVO'),
(594,212,'Ribas','ACTIVO'),
(595,212,'El Café','ACTIVO'),
(596,212,'Marizapa','ACTIVO'),
(597,213,'Cumbo','ACTIVO'),
(598,213,'San José de Barlovento','ACTIVO'),
(599,214,'El Cafetal','ACTIVO'),
(600,214,'Las Minas','ACTIVO'),
(601,214,'Nuestra Señora del Rosario','ACTIVO'),
(602,215,'Higuerote','ACTIVO'),
(603,215,'Curiepe','ACTIVO'),
(604,215,'Tacarigua de Brión','ACTIVO'),
(605,216,'Mamporal','ACTIVO'),
(606,217,'Carrizal','ACTIVO'),
(607,218,'Chacao','ACTIVO'),
(608,219,'Charallave','ACTIVO'),
(609,219,'Las Brisas','ACTIVO'),
(610,220,'El Hatillo','ACTIVO'),
(611,221,'Altagracia de la Montaña','ACTIVO'),
(612,221,'Cecilio Acosta','ACTIVO'),
(613,221,'Los Teques','ACTIVO'),
(614,221,'El Jarillo','ACTIVO'),
(615,221,'San Pedro','ACTIVO'),
(616,221,'Tácata','ACTIVO'),
(617,221,'Paracotos','ACTIVO'),
(618,222,'Cartanal','ACTIVO'),
(619,222,'Santa Teresa del Tuy','ACTIVO'),
(620,223,'La Democracia','ACTIVO'),
(621,223,'Ocumare del Tuy','ACTIVO'),
(622,223,'Santa Bárbara','ACTIVO'),
(623,224,'San Antonio de los Altos','ACTIVO'),
(624,225,'Río Chico','ACTIVO'),
(625,225,'El Guapo','ACTIVO'),
(626,225,'Tacarigua de la Laguna','ACTIVO'),
(627,225,'Paparo','ACTIVO'),
(628,225,'San Fernando del Guapo','ACTIVO'),
(629,226,'Santa Lucía del Tuy','ACTIVO'),
(630,227,'Guarenas','ACTIVO'),
(631,228,'San Antonio de Yare','ACTIVO'),
(632,228,'San Francisco de Yare','ACTIVO'),
(633,229,'Leoncio Martínez','ACTIVO'),
(634,229,'Petare','ACTIVO'),
(635,229,'Caucagüita','ACTIVO'),
(636,229,'Filas de Mariche','ACTIVO'),
(637,229,'La Dolorita','ACTIVO'),
(638,230,'Cúa','ACTIVO'),
(639,230,'Nueva Cúa','ACTIVO'),
(640,231,'Guatire','ACTIVO'),
(641,231,'Bolívar','ACTIVO'),
(642,232,'San Antonio de Maturín','ACTIVO'),
(643,232,'San Francisco de Maturín','ACTIVO'),
(644,233,'Aguasay','ACTIVO'),
(645,234,'Caripito','ACTIVO'),
(646,235,'El Guácharo','ACTIVO'),
(647,235,'La Guanota','ACTIVO'),
(648,235,'Sabana de Piedra','ACTIVO'),
(649,235,'San Agustín','ACTIVO'),
(650,235,'Teresen','ACTIVO'),
(651,235,'Caripe','ACTIVO'),
(652,236,'Areo','ACTIVO'),
(653,236,'Capital Cedeño','ACTIVO'),
(654,236,'San Félix de Cantalicio','ACTIVO'),
(655,236,'Viento Fresco','ACTIVO'),
(656,237,'El Tejero','ACTIVO'),
(657,237,'Punta de Mata','ACTIVO'),
(658,238,'Chaguaramas','ACTIVO'),
(659,238,'Las Alhuacas','ACTIVO'),
(660,238,'Tabasca','ACTIVO'),
(661,238,'Temblador','ACTIVO'),
(662,239,'Alto de los Godos','ACTIVO'),
(663,239,'Boquerón','ACTIVO'),
(664,239,'Las Cocuizas','ACTIVO'),
(665,239,'La Cruz','ACTIVO'),
(666,239,'San Simón','ACTIVO'),
(667,239,'El Corozo','ACTIVO'),
(668,239,'El Furrial','ACTIVO'),
(669,239,'Jusepín','ACTIVO'),
(670,239,'La Pica','ACTIVO'),
(671,239,'San Vicente','ACTIVO'),
(672,240,'Aparicio','ACTIVO'),
(673,240,'Aragua de Maturín','ACTIVO'),
(674,240,'Chaguamal','ACTIVO'),
(675,240,'El Pinto','ACTIVO'),
(676,240,'Guanaguana','ACTIVO'),
(677,240,'La Toscana','ACTIVO'),
(678,240,'Taguaya','ACTIVO'),
(679,241,'Cachipo','ACTIVO'),
(680,241,'Quiriquire','ACTIVO'),
(681,242,'Santa Bárbara','ACTIVO'),
(682,243,'Barrancas','ACTIVO'),
(683,243,'Los Barrancos de Fajardo','ACTIVO'),
(684,244,'Uracoa','ACTIVO'),
(685,245,'Antolín del Campo','ACTIVO'),
(686,246,'Arismendi','ACTIVO'),
(687,248,'García','ACTIVO'),
(688,248,'Francisco Fajardo','ACTIVO'),
(689,249,'Bolívar','ACTIVO'),
(690,249,'Guevara','ACTIVO'),
(691,249,'Matasiete','ACTIVO'),
(692,249,'Santa Ana','ACTIVO'),
(693,249,'Sucre','ACTIVO'),
(694,250,'Aguirre','ACTIVO'),
(695,250,'Maneiro','ACTIVO'),
(696,251,'Adrián','ACTIVO'),
(697,251,'Juan Griego','ACTIVO'),
(698,251,'Yaguaraparo','ACTIVO'),
(699,252,'Porlamar','ACTIVO'),
(700,253,'San Francisco de Macanao','ACTIVO'),
(701,253,'Boca de Río','ACTIVO'),
(702,254,'Tubores','ACTIVO'),
(703,254,'Los Baleales','ACTIVO'),
(704,255,'Vicente Fuentes','ACTIVO'),
(705,255,'Villalba','ACTIVO'),
(706,247,'San Juan Bautista','ACTIVO'),
(707,247,'Zabala','ACTIVO'),
(708,257,'Capital Araure','ACTIVO'),
(709,257,'Río Acarigua','ACTIVO'),
(710,258,'Capital Esteller','ACTIVO'),
(711,258,'Uveral','ACTIVO'),
(712,259,'Guanare','ACTIVO'),
(713,259,'Córdoba','ACTIVO'),
(714,259,'San José de la Montaña','ACTIVO'),
(715,259,'San Juan de Guanaguanare','ACTIVO'),
(716,259,'Virgen de la Coromoto','ACTIVO'),
(717,260,'Guanarito','ACTIVO'),
(718,260,'Trinidad de la Capilla','ACTIVO'),
(719,260,'Divina Pastora','ACTIVO'),
(720,261,'Monseñor José Vicente de Unda','ACTIVO'),
(721,261,'Peña Blanca','ACTIVO'),
(722,262,'Capital Ospino','ACTIVO'),
(723,262,'Aparición','ACTIVO'),
(724,262,'La Estación','ACTIVO'),
(725,263,'Páez','ACTIVO'),
(726,263,'Payara','ACTIVO'),
(727,263,'Pimpinela','ACTIVO'),
(728,263,'Ramón Peraza','ACTIVO'),
(729,264,'Papelón','ACTIVO'),
(730,264,'Caño Delgadito','ACTIVO'),
(731,265,'San Genaro de Boconoito','ACTIVO'),
(732,265,'Antolín Tovar','ACTIVO'),
(733,266,'San Rafael de Onoto','ACTIVO'),
(734,266,'Santa Fe','ACTIVO'),
(735,266,'Thermo Morles','ACTIVO'),
(736,267,'Santa Rosalía','ACTIVO'),
(737,267,'Florida','ACTIVO'),
(738,268,'Sucre','ACTIVO'),
(739,268,'Concepción','ACTIVO'),
(740,268,'San Rafael de Palo Alzado','ACTIVO'),
(741,268,'Uvencio Antonio Velásquez','ACTIVO'),
(742,268,'San José de Saguaz','ACTIVO'),
(743,268,'Villa Rosa','ACTIVO'),
(744,269,'Turén','ACTIVO'),
(745,269,'Canelones','ACTIVO'),
(746,269,'Santa Cruz','ACTIVO'),
(747,269,'San Isidro Labrador','ACTIVO'),
(748,270,'Mariño','ACTIVO'),
(749,270,'Rómulo Gallegos','ACTIVO'),
(750,271,'San José de Aerocuar','ACTIVO'),
(751,271,'Tavera Acosta','ACTIVO'),
(752,272,'Río Caribe','ACTIVO'),
(753,272,'Antonio José de Sucre','ACTIVO'),
(754,272,'El Morro de Puerto Santo','ACTIVO'),
(755,272,'Puerto Santo','ACTIVO'),
(756,272,'San Juan de las Galdonas','ACTIVO'),
(757,273,'El Pilar','ACTIVO'),
(758,273,'El Rincón','ACTIVO'),
(759,273,'General Francisco Antonio Váquez','ACTIVO'),
(760,273,'Guaraúnos','ACTIVO'),
(761,273,'Tunapuicito','ACTIVO'),
(762,273,'Unión','ACTIVO'),
(763,274,'Santa Catalina','ACTIVO'),
(764,274,'Santa Rosa','ACTIVO'),
(765,274,'Santa Teresa','ACTIVO'),
(766,274,'Bolívar','ACTIVO'),
(767,274,'Maracapana','ACTIVO'),
(768,276,'Libertad','ACTIVO'),
(769,276,'El Paujil','ACTIVO'),
(770,276,'Yaguaraparo','ACTIVO'),
(771,277,'Cruz Salmerón Acosta','ACTIVO'),
(772,277,'Chacopata','ACTIVO'),
(773,277,'Manicuare','ACTIVO'),
(774,278,'Tunapuy','ACTIVO'),
(775,278,'Campo Elías','ACTIVO'),
(776,279,'Irapa','ACTIVO'),
(777,279,'Campo Claro','ACTIVO'),
(778,279,'Maraval','ACTIVO'),
(779,279,'San Antonio de Irapa','ACTIVO'),
(780,279,'Soro','ACTIVO'),
(781,280,'Mejía','ACTIVO'),
(782,281,'Cumanacoa','ACTIVO'),
(783,281,'Arenas','ACTIVO'),
(784,281,'Aricagua','ACTIVO'),
(785,281,'Cogollar','ACTIVO'),
(786,281,'San Fernando','ACTIVO'),
(787,281,'San Lorenzo','ACTIVO'),
(788,282,'Catuaro','ACTIVO'),
(789,282,'Rendón','ACTIVO'),
(790,282,'San Cruz','ACTIVO'),
(791,282,'Santa María','ACTIVO'),
(792,283,'Altagracia','ACTIVO'),
(793,283,'Santa Inés','ACTIVO'),
(794,283,'Valentín Valiente','ACTIVO'),
(795,283,'Ayacucho','ACTIVO'),
(796,283,'San Juan','ACTIVO'),
(797,283,'Raúl Leoni','ACTIVO'),
(798,283,'Gran Mariscal','ACTIVO'),
(799,284,'Cristóbal Colón','ACTIVO'),
(800,284,'Bideau','ACTIVO'),
(801,284,'Punta de Piedras','ACTIVO'),
(802,284,'Güiria','ACTIVO'),
(803,285,'Andrés Bello','ACTIVO'),
(804,286,'Antonio Rómulo Costa','ACTIVO'),
(805,287,'Ayacucho','ACTIVO'),
(806,287,'Rivas Berti','ACTIVO'),
(807,287,'San Pedro del Río','ACTIVO'),
(808,288,'Bolívar','ACTIVO'),
(809,288,'Palotal','ACTIVO'),
(810,288,'General Juan Vicente Gómez','ACTIVO'),
(811,288,'Isaías Medina Angarita','ACTIVO'),
(812,289,'Cárdenas','ACTIVO'),
(813,289,'Amenodoro Ángel Lamus','ACTIVO'),
(814,289,'La Florida','ACTIVO'),
(815,290,'Córdoba','ACTIVO'),
(816,291,'Fernández Feo','ACTIVO'),
(817,291,'Alberto Adriani','ACTIVO'),
(818,291,'Santo Domingo','ACTIVO'),
(819,292,'Francisco de Miranda','ACTIVO'),
(820,293,'García de Hevia','ACTIVO'),
(821,293,'Boca de Grita','ACTIVO'),
(822,293,'José Antonio Páez','ACTIVO'),
(823,294,'Guásimos','ACTIVO'),
(824,295,'Independencia','ACTIVO'),
(825,295,'Juan Germán Roscio','ACTIVO'),
(826,295,'Román Cárdenas','ACTIVO'),
(827,296,'Jáuregui','ACTIVO'),
(828,296,'Emilio Constantino Guerrero','ACTIVO'),
(829,296,'Monseñor Miguel Antonio Salas','ACTIVO'),
(830,297,'José María Vargas','ACTIVO'),
(831,298,'Junín','ACTIVO'),
(832,298,'La Petrólea','ACTIVO'),
(833,298,'Quinimarí','ACTIVO'),
(834,298,'Bramón','ACTIVO'),
(835,299,'Libertad','ACTIVO'),
(836,299,'Cipriano Castro','ACTIVO'),
(837,299,'Manuel Felipe Rugeles','ACTIVO'),
(838,300,'Libertador','ACTIVO'),
(839,300,'Doradas','ACTIVO'),
(840,300,'Emeterio Ochoa','ACTIVO'),
(841,300,'San Joaquín de Navay','ACTIVO'),
(842,301,'Lobatera','ACTIVO'),
(843,301,'Constitución','ACTIVO'),
(844,302,'Michelena','ACTIVO'),
(845,303,'Panamericano','ACTIVO'),
(846,303,'La Palmita','ACTIVO'),
(847,304,'Pedro María Ureña','ACTIVO'),
(848,304,'Nueva Arcadia','ACTIVO'),
(849,305,'Delicias','ACTIVO'),
(850,305,'Pecaya','ACTIVO'),
(851,306,'Samuel Darío Maldonado','ACTIVO'),
(852,306,'Boconó','ACTIVO'),
(853,306,'Hernández','ACTIVO'),
(854,307,'La Concordia','ACTIVO'),
(855,307,'San Juan Bautista','ACTIVO'),
(856,307,'Pedro María Morantes','ACTIVO'),
(857,307,'San Sebastián','ACTIVO'),
(858,307,'Dr. Francisco Romero Lobo','ACTIVO'),
(859,309,'Seboruco','ACTIVO'),
(860,310,'Simón Rodríguez','ACTIVO'),
(861,311,'Sucre','ACTIVO'),
(862,311,'Eleazar López Contreras','ACTIVO'),
(863,311,'San Pablo','ACTIVO'),
(864,312,'Torbes','ACTIVO'),
(865,313,'Uribante','ACTIVO'),
(866,313,'Cárdenas','ACTIVO'),
(867,313,'Juan Pablo Peñalosa','ACTIVO'),
(868,313,'Potosí','ACTIVO'),
(869,308,'San Judas Tadeo','ACTIVO'),
(870,314,'Araguaney','ACTIVO'),
(871,314,'El Jaguito','ACTIVO'),
(872,314,'La Esperanza','ACTIVO'),
(873,314,'Santa Isabel','ACTIVO'),
(874,315,'Boconó','ACTIVO'),
(875,315,'El Carmen','ACTIVO'),
(876,315,'Mosquey','ACTIVO'),
(877,315,'Ayacucho','ACTIVO'),
(878,315,'Burbusay','ACTIVO'),
(879,315,'General Ribas','ACTIVO'),
(880,315,'Guaramacal','ACTIVO'),
(881,315,'Vega de Guaramacal','ACTIVO'),
(882,315,'Monseñor Jáuregui','ACTIVO'),
(883,315,'Rafael Rangel','ACTIVO'),
(884,315,'San Miguel','ACTIVO'),
(885,315,'San José','ACTIVO'),
(886,316,'Sabana Grande','ACTIVO'),
(887,316,'Cheregüé','ACTIVO'),
(888,316,'Granados','ACTIVO'),
(889,317,'Arnoldo Gabaldón','ACTIVO'),
(890,317,'Bolivia','ACTIVO'),
(891,317,'Carrillo','ACTIVO'),
(892,317,'Cegarra','ACTIVO'),
(893,317,'Chejendé','ACTIVO'),
(894,317,'Manuel Salvador Ulloa','ACTIVO'),
(895,317,'San José','ACTIVO'),
(896,318,'Carache','ACTIVO'),
(897,318,'La Concepción','ACTIVO'),
(898,318,'Cuicas','ACTIVO'),
(899,318,'Panamericana','ACTIVO'),
(900,318,'Santa Cruz','ACTIVO'),
(901,319,'Escuque','ACTIVO'),
(902,319,'La Unión','ACTIVO'),
(903,319,'Santa Rita','ACTIVO'),
(904,319,'Sabana Libre','ACTIVO'),
(905,322,'Santa Apolonia','ACTIVO'),
(906,322,'El Progreso','ACTIVO'),
(907,322,'La Ceiba','ACTIVO'),
(908,322,'Tres de Febrero','ACTIVO'),
(909,323,'El Dividive','ACTIVO'),
(910,323,'Agua Santa','ACTIVO'),
(911,323,'Agua Caliente','ACTIVO'),
(912,323,'El Cenizo','ACTIVO'),
(913,323,'Valerita','ACTIVO'),
(914,324,'Monte Carmelo','ACTIVO'),
(915,324,'Buena Vista','ACTIVO'),
(916,324,'Santa María del Horcón','ACTIVO'),
(917,325,'Motatán','ACTIVO'),
(918,325,'El Baño','ACTIVO'),
(919,325,'Jalisco','ACTIVO'),
(920,326,'Pampán','ACTIVO'),
(921,326,'Flor de Patria','ACTIVO'),
(922,326,'La Paz','ACTIVO'),
(923,326,'Santa Ana','ACTIVO'),
(924,327,'Pampanito','ACTIVO'),
(925,327,'La Concepción','ACTIVO'),
(926,327,'Pampanito II','ACTIVO'),
(927,328,'Betijoque','ACTIVO'),
(928,328,'José Gregorio Hernández','ACTIVO'),
(929,328,'La Pueblita','ACTIVO'),
(930,328,'Los Cedros','ACTIVO'),
(931,329,'Carvajal','ACTIVO'),
(932,329,'Campo Alegre','ACTIVO'),
(933,329,'Antonio Nicolás Briceño','ACTIVO'),
(934,329,'José Leonardo Suárez','ACTIVO'),
(935,330,'Sabana de Mendoza','ACTIVO'),
(936,330,'Junín','ACTIVO'),
(937,330,'Valmore Rodríguez','ACTIVO'),
(938,330,'El Paraíso','ACTIVO'),
(939,331,'Andrés Linares','ACTIVO'),
(940,331,'Chiquinquirá','ACTIVO'),
(941,331,'Cristóbal Mendoza','ACTIVO'),
(942,331,'Cruz Carrillo','ACTIVO'),
(943,331,'Matriz','ACTIVO'),
(944,331,'Monseñor Carrillo','ACTIVO'),
(945,331,'Tres Esquinas','ACTIVO'),
(946,332,'Cabimbú','ACTIVO'),
(947,332,'Jajó','ACTIVO'),
(948,332,'La Mesa de Esnujaque','ACTIVO'),
(949,332,'Santiago','ACTIVO'),
(950,332,'Tuñame','ACTIVO'),
(951,332,'La Quebrada','ACTIVO'),
(952,333,'Juan Ignacio Montilla','ACTIVO'),
(953,333,'La Beatriz','ACTIVO'),
(954,333,'La Puerta','ACTIVO'),
(955,333,'Mendoza del Valle de Momboy','ACTIVO'),
(956,333,'Mercedes Díaz','ACTIVO'),
(957,333,'San Luis','ACTIVO'),
(958,334,'Caraballeda','ACTIVO'),
(959,334,'Carayaca','ACTIVO'),
(960,334,'Carlos Soublette','ACTIVO'),
(961,334,'Caruao Chuspa','ACTIVO'),
(962,334,'Catia La Mar','ACTIVO'),
(963,334,'El Junko','ACTIVO'),
(964,334,'La Guaira','ACTIVO'),
(965,334,'Macuto','ACTIVO'),
(966,334,'Maiquetía','ACTIVO'),
(967,334,'Naiguatá','ACTIVO'),
(968,334,'Urimare','ACTIVO'),
(969,335,'Arístides Bastidas','ACTIVO'),
(970,336,'Bolívar','ACTIVO'),
(971,337,'Chivacoa','ACTIVO'),
(972,337,'Campo Elías','ACTIVO'),
(973,338,'Cocorote','ACTIVO'),
(974,339,'Independencia','ACTIVO'),
(975,340,'José Antonio Páez','ACTIVO'),
(976,341,'La Trinidad','ACTIVO'),
(977,342,'Manuel Monge','ACTIVO'),
(978,343,'Salóm','ACTIVO'),
(979,343,'Temerla','ACTIVO'),
(980,343,'Nirgua','ACTIVO'),
(981,344,'San Andrés','ACTIVO'),
(982,344,'Yaritagua','ACTIVO'),
(983,345,'San Javier','ACTIVO'),
(984,345,'Albarico','ACTIVO'),
(985,345,'San Felipe','ACTIVO'),
(986,346,'Sucre','ACTIVO'),
(987,347,'Urachiche','ACTIVO'),
(988,349,'Isla de Toas','ACTIVO'),
(989,349,'Monagas','ACTIVO'),
(990,350,'San Timoteo','ACTIVO'),
(991,350,'General Urdaneta','ACTIVO'),
(992,350,'Libertador','ACTIVO'),
(993,350,'Marcelino Briceño','ACTIVO'),
(994,350,'Pueblo Nuevo','ACTIVO'),
(995,350,'Manuel Guanipa Matos','ACTIVO'),
(996,351,'Ambrosio','ACTIVO'),
(997,351,'Carmen Herrera','ACTIVO'),
(998,351,'La Rosa','ACTIVO'),
(999,351,'Germán Ríos Linares','ACTIVO'),
(1000,351,'San Benito','ACTIVO'),
(1001,351,'Rómulo Betancourt','ACTIVO'),
(1002,351,'Jorge Hernández','ACTIVO'),
(1003,351,'Punta Gorda','ACTIVO'),
(1004,351,'Arístides Calvani','ACTIVO'),
(1005,352,'Encontrados','ACTIVO'),
(1006,352,'Udón Pérez','ACTIVO'),
(1007,353,'Moralito','ACTIVO'),
(1008,353,'San Carlos del Zulia','ACTIVO'),
(1009,353,'Santa Cruz del Zulia','ACTIVO'),
(1010,353,'Santa Bárbara','ACTIVO'),
(1011,353,'Urribarrí','ACTIVO'),
(1012,354,'Carlos Quevedo','ACTIVO'),
(1013,354,'Francisco Javier Pulgar','ACTIVO'),
(1014,354,'Simón Rodríguez','ACTIVO'),
(1015,354,'Guamo-Gavilanes','ACTIVO'),
(1016,363,'Sinamaica','ACTIVO'),
(1017,363,'Alta Guajira','ACTIVO'),
(1018,363,'Elías Sánchez Rubio','ACTIVO'),
(1019,363,'Guajira','ACTIVO'),
(1020,356,'Jesús María Semprún','ACTIVO'),
(1021,356,'Barí','ACTIVO'),
(1022,357,'Concepción','ACTIVO'),
(1023,357,'Andrés Bello','ACTIVO'),
(1024,357,'Chiquinquirá','ACTIVO'),
(1025,357,'El Carmelo','ACTIVO'),
(1026,357,'Potreritos','ACTIVO'),
(1027,358,'Libertad','ACTIVO'),
(1028,358,'Alonso de Ojeda','ACTIVO'),
(1029,358,'Venezuela','ACTIVO'),
(1030,358,'Eleazar López Contreras','ACTIVO'),
(1031,358,'Campo Lara','ACTIVO'),
(1032,359,'Bartolomé de las Casas','ACTIVO'),
(1033,359,'Libertad','ACTIVO'),
(1034,359,'Río Negro','ACTIVO'),
(1035,359,'San José de Perijá','ACTIVO'),
(1036,360,'San Rafael','ACTIVO'),
(1037,360,'La Sierrita','ACTIVO'),
(1038,360,'Las Parcelas','ACTIVO'),
(1039,360,'Luis de Vicente','ACTIVO'),
(1040,360,'Monseñor Marcos Sergio Godoy','ACTIVO'),
(1041,360,'Ricaurte','ACTIVO'),
(1042,360,'Tamare','ACTIVO'),
(1043,361,'Antonio Borjas Romero','ACTIVO'),
(1044,361,'Bolívar','ACTIVO'),
(1045,361,'Cacique Mara','ACTIVO'),
(1046,361,'Carracciolo Parra Pérez','ACTIVO'),
(1047,361,'Cecilio Acosta','ACTIVO'),
(1048,361,'Cristo de Aranza','ACTIVO'),
(1049,361,'Coquivacoa','ACTIVO'),
(1050,361,'Chiquinquirá','ACTIVO'),
(1051,361,'Francisco Eugenio Bustamante','ACTIVO'),
(1052,361,'Idelfonzo Vásquez','ACTIVO'),
(1053,361,'Juana de Ávila','ACTIVO'),
(1054,361,'Luis Hurtado Higuera','ACTIVO'),
(1055,361,'Manuel Dagnino','ACTIVO'),
(1056,361,'Olegario Villalobos','ACTIVO'),
(1057,361,'Raúl Leoni','ACTIVO'),
(1058,361,'Santa Lucía','ACTIVO'),
(1059,361,'Venancio Pulgar','ACTIVO'),
(1060,361,'San Isidro','ACTIVO'),
(1061,362,'Altagracia','ACTIVO'),
(1062,362,'Faría','ACTIVO'),
(1063,362,'Ana María Campos','ACTIVO'),
(1064,362,'San Antonio','ACTIVO'),
(1065,362,'San José','ACTIVO'),
(1066,364,'Donaldo García','ACTIVO'),
(1067,364,'El Rosario','ACTIVO'),
(1068,364,'Sixto Zambrano','ACTIVO'),
(1069,365,'San Francisco','ACTIVO'),
(1070,365,'El Bajo','ACTIVO'),
(1071,365,'Domitila Flores','ACTIVO'),
(1072,365,'Francisco Ochoa','ACTIVO'),
(1073,365,'Los Cortijos','ACTIVO'),
(1074,365,'Marcial Hernández','ACTIVO'),
(1075,366,'Santa Rita','ACTIVO'),
(1076,366,'El Mene','ACTIVO'),
(1077,366,'Pedro Lucas Urribarrí','ACTIVO'),
(1078,366,'José Cenobio Urribarrí','ACTIVO'),
(1079,367,'Rafael Maria Baralt','ACTIVO'),
(1080,367,'Manuel Manrique','ACTIVO'),
(1081,367,'Rafael Urdaneta','ACTIVO'),
(1082,368,'Bobures','ACTIVO'),
(1083,368,'Gibraltar','ACTIVO'),
(1084,368,'Heras','ACTIVO'),
(1085,368,'Monseñor Arturo Álvarez','ACTIVO'),
(1086,368,'Rómulo Gallegos','ACTIVO'),
(1087,368,'El Batey','ACTIVO'),
(1088,369,'Rafael Urdaneta','ACTIVO'),
(1089,369,'La Victoria','ACTIVO'),
(1090,369,'Raúl Cuenca','ACTIVO'),
(1091,370,'Santa Rosalía','ACTIVO'),
(1092,370,'El Valle','ACTIVO'),
(1093,370,'Coche','ACTIVO'),
(1094,370,'Caricuao','ACTIVO'),
(1095,370,'Macarao','ACTIVO'),
(1096,370,'Antímano','ACTIVO'),
(1097,370,'La Vega','ACTIVO'),
(1098,370,'El Paraíso','ACTIVO'),
(1099,370,'El Junquito','ACTIVO'),
(1100,370,'Sucre','ACTIVO'),
(1101,370,'San Juan','ACTIVO'),
(1102,370,'Santa Teresa','ACTIVO'),
(1103,370,'23 de Enero','ACTIVO'),
(1104,370,'La Pastora','ACTIVO'),
(1105,370,'Altagracia','ACTIVO'),
(1106,370,'San José','ACTIVO'),
(1107,370,'San Bernardino','ACTIVO'),
(1108,370,'Catedral','ACTIVO'),
(1109,370,'La Candelaria','ACTIVO'),
(1110,370,'San Agustín','ACTIVO'),
(1111,370,'El Recreo','ACTIVO'),
(1112,370,'San Pedro','ACTIVO');
/*!40000 ALTER TABLE `parroquia` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `persona`
--

DROP TABLE IF EXISTS `persona`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `persona` (
  `id_persona` int(11) NOT NULL AUTO_INCREMENT,
  `foto` varchar(255) DEFAULT NULL,
  `tipo_documento` enum('V','E','CE','P') NOT NULL,
  `numero_documento` varchar(20) NOT NULL,
  `fecha_nacimiento` date NOT NULL,
  `primer_nombre` varchar(50) NOT NULL,
  `segundo_nombre` varchar(50) DEFAULT NULL,
  `primer_apellido` varchar(50) NOT NULL,
  `segundo_apellido` varchar(50) DEFAULT NULL,
  `sexo` enum('F','M') NOT NULL,
  `id_estado_nacimiento` int(11) DEFAULT NULL,
  `id_parroquia_nacimiento` int(11) DEFAULT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `id_nacionalidad` int(11) DEFAULT NULL,
  `es_fallecido` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_persona`),
  UNIQUE KEY `numero_documento` (`numero_documento`),
  KEY `idx_persona_nacimiento` (`id_estado_nacimiento`,`id_parroquia_nacimiento`),
  KEY `fk_persona_parroquia_nacimiento` (`id_parroquia_nacimiento`),
  KEY `fk_persona_nacionalidad` (`id_nacionalidad`),
  CONSTRAINT `fk_persona_estado_nacimiento` FOREIGN KEY (`id_estado_nacimiento`) REFERENCES `estado` (`id_estado`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_persona_nacionalidad` FOREIGN KEY (`id_nacionalidad`) REFERENCES `nacionalidad` (`id_nacionalidad`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_persona_parroquia_nacimiento` FOREIGN KEY (`id_parroquia_nacimiento`) REFERENCES `parroquia` (`id_parroquia`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25121 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `persona`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `persona` WRITE;
/*!40000 ALTER TABLE `persona` DISABLE KEYS */;
INSERT INTO `persona` VALUES
(1,NULL,'V','25863136','1988-03-22','María','Fernanda','Rodríguez','Salazar','F',7,NULL,'ACTIVO','2026-09-01 18:23:12','2026-10-23 15:21:40',1,0),
(2,NULL,'V','12345678','1990-02-11','Carlos',NULL,'Ramírez',NULL,'M',NULL,NULL,'ACTIVO','2026-09-01 20:33:12','2026-10-03 20:00:40',NULL,0),
(3,NULL,'V','15678901','1985-07-22','María','Eugenia','González','Pérez','F',NULL,NULL,'ACTIVO','2026-09-01 20:33:12','2026-10-26 20:42:19',NULL,0),
(4,NULL,'V','18234567','1990-11-08','Luis','Ernesto','Medina','Castillo','M',NULL,NULL,'INACTIVO','2026-09-01 20:33:12','2026-09-02 19:44:46',NULL,0),
(5,NULL,'V','20345678','1988-05-30','Ana','Isabel','Herrera','Blanco','F',NULL,NULL,'INACTIVO','2026-09-01 20:33:12','2026-09-03 20:14:19',NULL,0),
(6,NULL,'V','14567890','1975-09-12','José','Miguel','Rodríguez','Mora','M',NULL,NULL,'ACTIVO','2026-09-01 20:33:12','2026-09-21 16:24:53',NULL,0),
(7,NULL,'V','22456789','1992-01-25','Luisa','Fernanda','Martínez','Salazar','F',NULL,NULL,'ACTIVO','2026-09-01 20:33:12','2026-09-01 20:33:12',NULL,0),
(8,NULL,'V','16789012','1983-06-18','Pedro','Antonio','Flores','Díaz','M',NULL,NULL,'ACTIVO','2026-09-01 20:33:12','2026-10-03 04:30:34',NULL,0),
(9,NULL,'V','19012345','1995-04-03','Gabriela','Alejandra','Vargas','Mendoza','F',NULL,NULL,'ACTIVO','2026-09-01 20:33:12','2026-09-01 20:33:12',NULL,0),
(10,'/uploads/empleados/empleado_ca45b972e71ff81a.webp','V','11234567','1978-12-20','Roberto','Enrique','Castro','Fuentes','M',7,NULL,'ACTIVO','2026-09-01 20:33:12','2026-10-11 09:44:31',1,0),
(11,NULL,'V','23567890','1993-08-14','Valentina','Cristina','Jiménez','Ortega','F',NULL,NULL,'ACTIVO','2026-09-01 20:33:12','2026-09-01 20:33:12',NULL,0),
(16,'/uploads/empleados/empleado_6eaead8d42fb3d01.webp','V','22509285','1995-05-22','Derwin','Gabriel','Rojas','Quiroz','M',7,NULL,'ACTIVO','2026-09-04 15:25:40','2026-10-23 06:02:53',1,0),
(17,'/uploads/empleados/empleado_29a269233277afab.png','V','12523431','1979-07-13','Jenny','Maloa','Maita','Gutierrez','F',NULL,NULL,'ACTIVO','2026-09-05 10:10:10','2026-09-21 16:20:18',NULL,0),
(18,NULL,'V','22558602','0096-06-16','Ana','','Nuñes','Rodriguez','F',NULL,NULL,'ACTIVO','2026-09-05 10:25:56','2026-09-21 16:24:36',NULL,0),
(19,'/uploads/empleados/empleado_e533f7ad3a9c4eb4.avif','V','14526358','2000-06-16','Ivian','Valeria','Rojas','Maita','F',NULL,NULL,'ACTIVO','2026-09-05 11:29:49','2026-09-23 21:08:47',NULL,0),
(42,NULL,'V','12548963','2000-06-16','Maria',NULL,'Maita',NULL,'F',NULL,NULL,'ACTIVO','2026-09-24 16:44:09','2026-09-24 16:44:09',NULL,0),
(80,NULL,'V','15698566','2000-06-16','Anabell',NULL,'Rojas',NULL,'F',NULL,NULL,'ACTIVO','2026-09-25 18:12:14','2026-09-30 22:49:00',NULL,0),
(1660,NULL,'V','V51515201','1985-01-01','Padre',NULL,'Con','Parentesco','F',NULL,NULL,'ACTIVO','2026-09-27 12:26:19','2026-09-27 12:26:19',NULL,0),
(1662,NULL,'V','V51515202','1985-01-01','Madre',NULL,'Con','Parentesco','F',NULL,NULL,'ACTIVO','2026-09-27 12:26:19','2026-09-27 12:26:19',NULL,0),
(4150,NULL,'V','29863136','2015-05-12','Valerin','','Rojas','','F',7,4,'ACTIVO','2026-09-27 13:38:10','2026-10-11 15:34:48',NULL,0),
(5484,NULL,'V','96500001','1990-01-01','Prueba',NULL,'Js',NULL,'F',NULL,NULL,'ACTIVO','2026-09-27 14:12:54','2026-09-27 14:12:54',NULL,0),
(5485,NULL,'V','96500002','1990-01-01','Prueba',NULL,'Js',NULL,'F',NULL,NULL,'ACTIVO','2026-09-27 14:12:54','2026-09-27 14:12:54',NULL,0),
(7800,NULL,'CE','V2162586313','2016-06-05','Ivian','Valeria','Rojas','Maita','F',7,4,'INACTIVO','2026-09-27 16:26:07','2026-10-11 15:34:48',NULL,0),
(7803,NULL,'CE','V1162458963','2016-02-02','Leo','','Pinto','','M',7,4,'ACTIVO','2026-09-27 16:42:34','2026-10-11 15:34:48',NULL,0),
(7804,NULL,'V','24589635','1995-06-16','elena','Ivian Valeria','Ivian','Maita','F',NULL,NULL,'ACTIVO','2026-09-27 16:42:34','2026-09-27 16:42:34',NULL,0),
(11304,NULL,'V','12458958','1975-03-13','Jose',NULL,'Maita',NULL,'M',NULL,NULL,'ACTIVO','2026-09-27 17:45:03','2026-09-27 17:45:03',NULL,0),
(24405,NULL,'CE','V1202586313','2020-05-16','Ana',NULL,'Lucia',NULL,'F',7,4,'ACTIVO','2026-10-01 08:47:36','2026-10-11 15:34:48',NULL,0),
(24406,'/uploads/estudiantes/estudiante_87d2b38ccc17a09c.jpg','CE','V2202586313','2020-05-16','Erickmar','Margarita','Sanchez','Lopez','F',7,4,'ACTIVO','2026-10-01 09:32:00','2026-10-17 17:55:41',1,0),
(24410,NULL,'V','32586487','2020-09-16','Mery',NULL,'Aponte',NULL,'F',7,4,'ACTIVO','2026-10-01 12:15:05','2026-10-11 15:34:48',NULL,0),
(24487,NULL,'CE','V1201234567','2020-12-10','Lhia','Ana','Perez','Ruiz','F',7,4,'ACTIVO','2026-10-03 10:37:57','2026-10-11 15:34:48',NULL,0),
(24507,NULL,'CE','V1212250928','2021-06-16','Tarle',NULL,'Arias',NULL,'F',7,4,'INACTIVO','2026-10-05 09:32:56','2026-10-11 15:34:48',NULL,0),
(24508,NULL,'CE','V2212250928','2021-02-16','Patricia',NULL,'Ferrer',NULL,'F',7,4,'ACTIVO','2026-10-05 09:34:51','2026-10-11 15:34:48',NULL,0),
(24509,NULL,'CE','V3212250928','2021-06-16','Anthony',NULL,'Aponte',NULL,'F',7,4,'ACTIVO','2026-10-05 12:34:28','2026-10-11 15:34:48',NULL,0),
(24510,NULL,'CE','V4212250928','2021-06-16','Maria',NULL,'Paredez',NULL,'F',7,4,'ACTIVO','2026-10-05 14:25:18','2026-10-11 15:34:48',NULL,0),
(24516,NULL,'V','38524698','2021-06-16','Juan','Torrealba','Pinto','Garcia','M',7,4,'ACTIVO','2026-10-06 12:23:08','2026-10-22 15:28:25',1,0),
(24521,NULL,'V','23547896','1998-06-16','Hbuj',NULL,'Huh',NULL,'F',NULL,NULL,'ACTIVO','2026-10-08 12:53:43','2026-10-08 12:53:43',NULL,0),
(24522,NULL,'CE','V1212586313','2021-05-16','Ana','Patricia','López','Toledo','F',7,4,'ACTIVO','2026-10-08 13:10:32','2026-10-22 15:11:23',1,0),
(24582,NULL,'V','97600000','1990-01-01','Vista',NULL,'Prueba',NULL,'M',NULL,NULL,'ACTIVO','2026-10-08 16:25:34','2026-10-08 16:25:34',NULL,0),
(24583,NULL,'V','97600001','1990-01-01','Vista',NULL,'Prueba',NULL,'M',NULL,NULL,'ACTIVO','2026-10-08 16:25:35','2026-10-08 16:25:35',NULL,0),
(24584,NULL,'V','97600002','1990-01-01','Vista',NULL,'Prueba',NULL,'M',NULL,NULL,'ACTIVO','2026-10-08 16:25:35','2026-10-08 16:25:35',NULL,0),
(24585,NULL,'V','97600003','1990-01-01','Vista',NULL,'Prueba',NULL,'M',NULL,NULL,'ACTIVO','2026-10-08 16:25:35','2026-10-08 16:25:35',NULL,0),
(24586,NULL,'V','97600004','1990-01-01','Vista',NULL,'Prueba',NULL,'M',NULL,NULL,'ACTIVO','2026-10-08 16:25:36','2026-10-08 16:25:36',NULL,0),
(24922,NULL,'CE','V6212250928','2021-02-20','Juan','Fabricio','Ojeda','Suarez','M',7,4,'ACTIVO','2026-10-23 06:02:53','2026-10-23 06:02:53',1,0),
(24923,NULL,'V','14784589','1994-06-16','Juana','María','Castillo','Jiménez','F',7,NULL,'ACTIVO','2026-10-23 06:12:51','2026-10-23 06:12:51',1,0),
(24924,NULL,'V','14575474','1979-03-13','José','Rafael','Maita','Gutierrez','M',7,NULL,'ACTIVO','2026-10-24 08:07:04','2026-10-24 08:07:04',1,0),
(25120,NULL,'CE','V11825863136','2018-09-22','Valerin','Sophia','Rojas','Bocaney','F',7,4,'ACTIVO','2026-10-29 18:35:34','2026-10-29 18:35:34',1,0);
/*!40000 ALTER TABLE `persona` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `persona_estudiante`
--

DROP TABLE IF EXISTS `persona_estudiante`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `persona_estudiante` (
  `id_persona_estudiante` int(11) NOT NULL AUTO_INCREMENT,
  `id_persona` int(11) NOT NULL,
  `id_estudiante` int(11) NOT NULL,
  `es_representante_legal` tinyint(1) NOT NULL DEFAULT 0,
  `es_autorizado_retiro` tinyint(1) NOT NULL DEFAULT 0,
  `vive_con_estudiante` tinyint(1) NOT NULL DEFAULT 0,
  `estado_relacion` enum('ACTIVO','INACTIVO','SUSPENDIDO') NOT NULL DEFAULT 'ACTIVO',
  `id_parentesco` int(11) DEFAULT NULL,
  `id_profesion` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_persona_estudiante`),
  UNIQUE KEY `uk_relacion_persona_estudiante` (`id_persona`,`id_estudiante`),
  KEY `idx_relacion_estudiante` (`id_estudiante`),
  KEY `idx_relacion_estado_rel` (`estado_relacion`),
  KEY `idx_relacion_parentesco` (`id_parentesco`),
  KEY `idx_relacion_profesion` (`id_profesion`),
  CONSTRAINT `fk_relacion_estudiante` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiante` (`id_estudiante`) ON UPDATE CASCADE,
  CONSTRAINT `fk_relacion_parentesco` FOREIGN KEY (`id_parentesco`) REFERENCES `parentesco` (`id_parentesco`) ON UPDATE CASCADE,
  CONSTRAINT `fk_relacion_persona` FOREIGN KEY (`id_persona`) REFERENCES `persona` (`id_persona`) ON UPDATE CASCADE,
  CONSTRAINT `fk_relacion_profesion` FOREIGN KEY (`id_profesion`) REFERENCES `ocupacion` (`id_ocupacion`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2259 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `persona_estudiante`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `persona_estudiante` WRITE;
/*!40000 ALTER TABLE `persona_estudiante` DISABLE KEYS */;
INSERT INTO `persona_estudiante` VALUES
(684,1,3064,1,1,1,'ACTIVO',2,38),
(916,1,6118,1,1,1,'ACTIVO',2,22),
(918,7804,6120,1,1,1,'ACTIVO',2,38),
(2058,1,20113,1,1,1,'ACTIVO',2,28),
(2059,1,20114,1,1,1,'ACTIVO',2,43),
(2060,1,20118,1,1,1,'ACTIVO',15,44),
(2069,16,20114,0,0,0,'ACTIVO',1,NULL),
(2084,2,20124,1,1,1,'ACTIVO',12,21),
(2085,16,20125,1,1,1,'ACTIVO',3,4),
(2086,16,20126,1,1,1,'ACTIVO',3,4),
(2087,16,20127,1,1,1,'ACTIVO',5,4),
(2088,16,20128,1,1,1,'ACTIVO',3,4),
(2091,16,20131,1,1,1,'ACTIVO',3,4),
(2092,1,20132,1,1,1,'ACTIVO',3,44),
(2158,16,20394,1,1,1,'ACTIVO',3,4),
(2159,1,20394,0,1,0,'ACTIVO',1,NULL),
(2258,1,20476,1,1,1,'ACTIVO',5,44);
/*!40000 ALTER TABLE `persona_estudiante` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `recuperacion_acceso`
--

DROP TABLE IF EXISTS `recuperacion_acceso`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `recuperacion_acceso` (
  `id_recuperacion` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `fecha_solicitud` datetime NOT NULL DEFAULT current_timestamp(),
  `estado_recuperacion` enum('PENDIENTE','APROVADO','RECHAZADO') NOT NULL DEFAULT 'PENDIENTE',
  `origen` enum('SOLICITUD','ADMIN') NOT NULL DEFAULT 'SOLICITUD',
  `fecha_atencion` datetime DEFAULT NULL,
  `id_admin_atendio` int(11) DEFAULT NULL,
  `fecha_actualizacion` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_recuperacion`),
  KEY `idx_recuperacion_estado` (`estado_recuperacion`),
  KEY `idx_recuperacion_usuario` (`id_usuario`),
  KEY `idx_recuperacion_admin` (`id_admin_atendio`),
  KEY `idx_recuperacion_fechas` (`fecha_solicitud`,`fecha_atencion`),
  CONSTRAINT `fk_recuperacion_admin` FOREIGN KEY (`id_admin_atendio`) REFERENCES `usuario` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_recuperacion_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recuperacion_acceso`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `recuperacion_acceso` WRITE;
/*!40000 ALTER TABLE `recuperacion_acceso` DISABLE KEYS */;
INSERT INTO `recuperacion_acceso` VALUES
(6,9,'2026-09-11 11:10:59','RECHAZADO','SOLICITUD','2026-09-11 12:01:57',2,'2026-09-11 12:01:57'),
(7,1,'2026-09-11 11:11:07','APROVADO','SOLICITUD','2026-09-11 11:31:30',2,'2026-09-11 11:31:30'),
(8,1,'2026-09-11 12:27:01','RECHAZADO','SOLICITUD','2026-09-12 20:44:23',2,'2026-09-12 20:44:23'),
(9,3,'2026-09-13 09:03:17','APROVADO','ADMIN','2026-09-13 09:03:17',2,'2026-09-13 09:03:17'),
(10,9,'2026-09-13 09:04:32','APROVADO','SOLICITUD','2026-09-13 09:04:55',2,'2026-09-13 09:04:55'),
(11,7,'2026-09-13 09:06:01','APROVADO','ADMIN','2026-09-13 09:06:01',2,'2026-09-13 09:06:01'),
(12,6,'2026-09-13 19:45:15','APROVADO','ADMIN','2026-09-13 19:45:15',2,'2026-09-13 19:45:15'),
(13,7,'2026-09-14 08:57:32','APROVADO','ADMIN','2026-09-14 08:57:32',2,'2026-09-14 08:57:32'),
(14,15,'2026-09-14 09:02:01','APROVADO','ADMIN','2026-09-14 09:02:01',2,'2026-09-14 09:02:01'),
(15,8,'2026-09-14 10:11:38','APROVADO','ADMIN','2026-09-14 10:11:38',2,'2026-09-14 10:11:38'),
(16,6,'2026-09-14 11:57:24','APROVADO','ADMIN','2026-09-14 11:57:24',2,'2026-09-14 11:57:24'),
(17,1,'2026-09-16 09:08:57','APROVADO','SOLICITUD','2026-09-16 13:15:23',2,'2026-09-16 13:15:23'),
(18,9,'2026-09-16 13:17:07','APROVADO','SOLICITUD','2026-09-16 17:02:07',2,'2026-09-16 17:02:07'),
(19,8,'2026-09-16 13:17:29','APROVADO','ADMIN','2026-09-16 13:17:29',2,'2026-09-16 13:17:29'),
(20,1,'2026-09-16 13:17:52','APROVADO','ADMIN','2026-09-16 13:17:52',2,'2026-09-16 13:17:52'),
(21,9,'2026-09-16 17:01:55','APROVADO','ADMIN','2026-09-16 17:01:55',2,'2026-09-16 17:01:55'),
(22,10,'2026-09-16 22:14:59','RECHAZADO','SOLICITUD','2026-09-16 22:18:16',2,'2026-09-16 22:18:16'),
(23,9,'2026-09-16 22:27:08','RECHAZADO','SOLICITUD','2026-09-17 12:46:56',2,'2026-09-17 12:46:56'),
(24,1,'2026-09-17 08:57:07','APROVADO','SOLICITUD','2026-09-17 21:01:23',2,'2026-09-17 21:01:23'),
(25,7,'2026-09-17 12:45:21','APROVADO','ADMIN','2026-09-17 12:45:21',2,'2026-09-17 12:45:21'),
(26,7,'2026-09-19 05:59:11','APROVADO','ADMIN','2026-09-19 05:59:11',16,'2026-09-19 05:59:11'),
(27,7,'2026-09-20 11:45:13','APROVADO','ADMIN','2026-09-20 11:45:13',2,'2026-09-20 11:45:13'),
(28,7,'2026-09-21 12:05:46','APROVADO','ADMIN','2026-09-21 12:05:46',2,'2026-09-21 12:05:46'),
(29,7,'2026-09-21 15:57:50','APROVADO','ADMIN','2026-09-21 15:57:50',2,'2026-09-21 15:57:50'),
(30,9,'2026-09-24 09:27:59','APROVADO','SOLICITUD','2026-09-24 09:28:34',2,'2026-09-24 09:28:34'),
(32,17,'2026-09-25 14:23:59','APROVADO','SOLICITUD','2026-09-25 14:24:10',2,'2026-09-25 14:24:10'),
(33,9,'2026-09-25 14:28:36','APROVADO','ADMIN','2026-09-25 14:28:36',2,'2026-09-25 14:28:36'),
(34,17,'2026-09-30 07:29:54','APROVADO','ADMIN','2026-09-30 07:29:54',2,'2026-09-30 07:29:54'),
(35,17,'2026-09-30 07:32:05','APROVADO','ADMIN','2026-09-30 07:32:05',2,'2026-09-30 07:32:05'),
(36,17,'2026-09-30 07:32:46','APROVADO','ADMIN','2026-09-30 07:32:46',2,'2026-09-30 07:32:46'),
(37,17,'2026-09-30 07:34:21','APROVADO','ADMIN','2026-09-30 07:34:21',2,'2026-09-30 07:34:21'),
(38,17,'2026-09-30 07:34:39','APROVADO','ADMIN','2026-09-30 07:34:39',2,'2026-09-30 07:34:39'),
(39,17,'2026-09-30 07:39:03','APROVADO','ADMIN','2026-09-30 07:39:03',2,'2026-09-30 07:39:03'),
(40,17,'2026-09-30 19:39:56','APROVADO','ADMIN','2026-09-30 19:39:56',2,'2026-09-30 19:39:56'),
(41,463,'2026-10-03 04:58:31','APROVADO','ADMIN','2026-10-03 04:58:31',2,'2026-10-03 04:58:31'),
(42,9,'2026-10-12 14:50:40','APROVADO','ADMIN','2026-10-12 14:50:40',2,'2026-10-12 14:50:40'),
(43,1953,'2026-10-12 16:26:14','APROVADO','ADMIN','2026-10-12 16:26:14',2,'2026-10-12 16:26:14'),
(44,8,'2026-10-12 16:54:38','APROVADO','ADMIN','2026-10-12 16:54:38',2,'2026-10-12 16:54:38'),
(45,10,'2026-10-18 07:31:24','APROVADO','ADMIN','2026-10-18 07:31:24',2,'2026-10-18 07:31:24'),
(46,10,'2026-10-18 16:36:55','APROVADO','ADMIN','2026-10-18 16:36:55',2,'2026-10-18 16:36:55'),
(47,463,'2026-10-18 16:44:43','APROVADO','ADMIN','2026-10-18 16:44:43',2,'2026-10-18 16:44:43'),
(48,8,'2026-10-20 19:11:25','APROVADO','ADMIN','2026-10-20 19:11:25',2,'2026-10-20 19:11:25'),
(49,1,'2026-10-24 07:57:07','APROVADO','SOLICITUD','2026-10-26 05:44:05',2,'2026-10-26 05:44:05'),
(50,2,'2026-10-27 15:45:48','APROVADO','ADMIN','2026-10-27 15:45:48',1,'2026-10-27 15:45:48'),
(51,3,'2026-10-27 17:21:47','APROVADO','ADMIN','2026-10-27 17:21:47',2,'2026-10-27 17:21:47');
/*!40000 ALTER TABLE `recuperacion_acceso` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `reposo_empleado`
--

DROP TABLE IF EXISTS `reposo_empleado`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `reposo_empleado` (
  `id_reposo` int(11) NOT NULL AUTO_INCREMENT,
  `id_empleado` int(11) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,
  `motivo` varchar(200) DEFAULT NULL,
  `observacion` text DEFAULT NULL,
  `estado` enum('VIGENTE','CERRADO','ANULADO') NOT NULL DEFAULT 'VIGENTE',
  `id_usuario_registro` int(11) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_reposo`),
  KEY `idx_reposo_vigencia` (`id_empleado`,`fecha_inicio`,`fecha_fin`),
  KEY `idx_reposo_usuario_registro` (`id_usuario_registro`),
  CONSTRAINT `fk_reposo_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_reposo_usuario_registro` FOREIGN KEY (`id_usuario_registro`) REFERENCES `usuario` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `ck_reposo_rango` CHECK (`fecha_fin` is null or `fecha_fin` >= `fecha_inicio`)
) ENGINE=InnoDB AUTO_INCREMENT=80 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reposo_empleado`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `reposo_empleado` WRITE;
/*!40000 ALTER TABLE `reposo_empleado` DISABLE KEYS */;
INSERT INTO `reposo_empleado` VALUES
(73,10,'2026-10-11','2026-10-12',NULL,NULL,'CERRADO',NULL,'2026-10-11 09:42:39','2026-10-21 13:20:40');
/*!40000 ALTER TABLE `reposo_empleado` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `resultado_academico`
--

DROP TABLE IF EXISTS `resultado_academico`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `resultado_academico` (
  `id_resultado` int(11) NOT NULL AUTO_INCREMENT,
  `id_estudiante` int(11) NOT NULL,
  `id_anio_escolar` int(11) NOT NULL,
  `id_estructura_academica` int(11) NOT NULL,
  `id_matricula` int(11) NOT NULL,
  `literal` enum('A','B','C','D','E') DEFAULT NULL COMMENT 'Calificación cualitativa oficial de Educación Primaria',
  `estado_resultado` enum('PENDIENTE','PROMOVIDO','NO_PROMOVIDO') NOT NULL DEFAULT 'PENDIENTE' COMMENT 'Estado del resultado anual',
  `id_grado_destino` int(11) DEFAULT NULL COMMENT 'Grado sugerido para la siguiente matrícula (siguiente si promovido, mismo si no)',
  `observaciones` text DEFAULT NULL COMMENT 'Observaciones pedagógicas del docente o directivo',
  `propuesto_por` int(11) DEFAULT NULL COMMENT 'Usuario del docente que cargó la propuesta',
  `fecha_propuesta` datetime DEFAULT NULL,
  `confirmado_por` int(11) DEFAULT NULL COMMENT 'Usuario del directivo que validó y confirmó',
  `fecha_confirmacion` datetime DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_resultado`),
  UNIQUE KEY `uk_resultado_estudiante_anio` (`id_estudiante`,`id_anio_escolar`),
  KEY `idx_resultado_estructura` (`id_estructura_academica`),
  KEY `idx_resultado_matricula` (`id_matricula`),
  KEY `idx_resultado_estado` (`estado_resultado`),
  KEY `fk_res_acad_anio` (`id_anio_escolar`),
  KEY `fk_res_acad_grado_destino` (`id_grado_destino`),
  KEY `fk_res_acad_propuesto` (`propuesto_por`),
  KEY `fk_res_acad_confirmado` (`confirmado_por`),
  CONSTRAINT `fk_res_acad_anio` FOREIGN KEY (`id_anio_escolar`) REFERENCES `anio_escolar` (`id_anio_escolar`) ON UPDATE CASCADE,
  CONSTRAINT `fk_res_acad_confirmado` FOREIGN KEY (`confirmado_por`) REFERENCES `usuario` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_res_acad_estructura` FOREIGN KEY (`id_estructura_academica`) REFERENCES `estructura_academica` (`id_estructura_academica`) ON UPDATE CASCADE,
  CONSTRAINT `fk_res_acad_estudiante` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiante` (`id_estudiante`) ON UPDATE CASCADE,
  CONSTRAINT `fk_res_acad_grado_destino` FOREIGN KEY (`id_grado_destino`) REFERENCES `grado` (`id_grado`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_res_acad_matricula` FOREIGN KEY (`id_matricula`) REFERENCES `matricula` (`id_matricula`) ON UPDATE CASCADE,
  CONSTRAINT `fk_res_acad_propuesto` FOREIGN KEY (`propuesto_por`) REFERENCES `usuario` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=371 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resultado_academico`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `resultado_academico` WRITE;
/*!40000 ALTER TABLE `resultado_academico` DISABLE KEYS */;
INSERT INTO `resultado_academico` VALUES
(176,20132,238,1538,20945,NULL,'PENDIENTE',NULL,NULL,8,'2026-10-29 15:13:39',NULL,NULL,'2026-10-17 17:11:54','2026-10-29 15:13:39'),
(177,20131,238,1537,20944,'E','NO_PROMOVIDO',1,'no cumple faltas',8,'2026-10-29 15:13:39',NULL,NULL,'2026-10-17 17:11:54','2026-10-29 15:13:39'),
(178,20124,238,1537,20841,'B','PROMOVIDO',6,NULL,8,'2026-10-29 15:13:39',NULL,NULL,'2026-10-17 17:11:54','2026-10-29 15:13:39'),
(179,6120,238,1537,20833,'E','NO_PROMOVIDO',1,'desertor',8,'2026-10-29 15:13:39',NULL,NULL,'2026-10-17 17:11:54','2026-10-29 15:13:39'),
(200,3064,238,1607,21186,'A','PROMOVIDO',NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-19 23:22:52','2026-10-19 23:22:52'),
(312,20394,238,1537,21296,'A','PROMOVIDO',6,NULL,8,'2026-10-29 15:13:39',NULL,NULL,'2026-10-23 20:35:51','2026-10-29 15:13:39');
/*!40000 ALTER TABLE `resultado_academico` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `retiro`
--

DROP TABLE IF EXISTS `retiro`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `retiro` (
  `id_retiro` int(11) NOT NULL AUTO_INCREMENT,
  `id_matricula` int(11) NOT NULL,
  `id_motivo_retiro` int(11) NOT NULL,
  `id_persona_representante` int(11) DEFAULT NULL,
  `estado_solicitud` enum('PENDIENTE','APROBADO','RECHAZADO') NOT NULL DEFAULT 'PENDIENTE',
  `fecha_solicitud` datetime DEFAULT NULL,
  `fecha_retiro` date DEFAULT NULL,
  `fecha_resolucion` datetime DEFAULT NULL,
  `id_usuario_registro` int(11) DEFAULT NULL,
  `id_usuario_resolucion` int(11) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  PRIMARY KEY (`id_retiro`),
  UNIQUE KEY `id_matricula` (`id_matricula`),
  KEY `idx_retiro_motivo` (`id_motivo_retiro`),
  KEY `idx_retiro_estado` (`estado_solicitud`),
  KEY `fk_retiro_usuario_registro` (`id_usuario_registro`),
  KEY `fk_retiro_usuario_resolucion` (`id_usuario_resolucion`),
  KEY `idx_retiro_persona_rep` (`id_persona_representante`),
  CONSTRAINT `fk_retiro_matricula` FOREIGN KEY (`id_matricula`) REFERENCES `matricula` (`id_matricula`) ON UPDATE CASCADE,
  CONSTRAINT `fk_retiro_motivo` FOREIGN KEY (`id_motivo_retiro`) REFERENCES `motivo_retiro` (`id_motivo_retiro`) ON UPDATE CASCADE,
  CONSTRAINT `fk_retiro_persona_rep` FOREIGN KEY (`id_persona_representante`) REFERENCES `persona` (`id_persona`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_retiro_usuario_registro` FOREIGN KEY (`id_usuario_registro`) REFERENCES `usuario` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_retiro_usuario_resolucion` FOREIGN KEY (`id_usuario_resolucion`) REFERENCES `usuario` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=598 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `retiro`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `retiro` WRITE;
/*!40000 ALTER TABLE `retiro` DISABLE KEYS */;
INSERT INTO `retiro` VALUES
(504,20845,7,1,'APROBADO',NULL,'2026-03-15','2026-10-05 11:28:36',NULL,5,NULL),
(572,20836,7,NULL,'APROBADO',NULL,NULL,'2026-10-05 11:28:54',NULL,5,NULL),
(573,20844,7,NULL,'APROBADO','2026-10-05 11:02:58',NULL,'2026-10-05 11:22:57',17,5,NULL);
/*!40000 ALTER TABLE `retiro` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `rol`
--

DROP TABLE IF EXISTS `rol`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rol` (
  `id_rol` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_rol`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=100 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rol`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `rol` WRITE;
/*!40000 ALTER TABLE `rol` DISABLE KEYS */;
INSERT INTO `rol` VALUES
(1,'Admin','ACTIVO'),
(2,'Coordinador','ACTIVO'),
(3,'Docente','ACTIVO'),
(4,'Directivo','ACTIVO'),
(5,'Secretaria','ACTIVO');
/*!40000 ALTER TABLE `rol` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `seccion`
--

DROP TABLE IF EXISTS `seccion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `seccion` (
  `id_seccion` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(10) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_seccion`),
  UNIQUE KEY `nombre` (`nombre`),
  KEY `idx_seccion_estado` (`estado`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `seccion`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `seccion` WRITE;
/*!40000 ALTER TABLE `seccion` DISABLE KEYS */;
INSERT INTO `seccion` VALUES
(1,'A','ACTIVO'),
(2,'B','ACTIVO'),
(3,'C','ACTIVO'),
(4,'D','INACTIVO');
/*!40000 ALTER TABLE `seccion` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `talla_uniforme`
--

DROP TABLE IF EXISTS `talla_uniforme`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `talla_uniforme` (
  `id_talla_uniforme` int(11) NOT NULL AUTO_INCREMENT,
  `id_estudiante` int(11) NOT NULL,
  `talla_camisa` varchar(10) NOT NULL,
  `talla_pantalon` varchar(10) NOT NULL,
  `talla_zapato` varchar(10) NOT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_actualizacion` date NOT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `peso_kg` decimal(5,2) DEFAULT NULL,
  `estatura_cm` smallint(5) unsigned DEFAULT NULL,
  PRIMARY KEY (`id_talla_uniforme`),
  UNIQUE KEY `id_estudiante` (`id_estudiante`),
  KEY `idx_talla_uniforme_fecha` (`fecha_actualizacion`),
  CONSTRAINT `fk_talla_uniforme_estudiante` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiante` (`id_estudiante`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `talla_uniforme`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `talla_uniforme` WRITE;
/*!40000 ALTER TABLE `talla_uniforme` DISABLE KEYS */;
INSERT INTO `talla_uniforme` VALUES
(30,20113,'4','4','28',NULL,'2026-10-01','2026-10-01 08:47:36',NULL,NULL),
(31,20114,'4','4','28','','2026-10-23','2026-10-01 09:32:01',NULL,NULL),
(34,6120,'M','M','38','','2026-10-01','2026-10-01 14:42:10',NULL,NULL),
(38,3064,'M','M','38','','2026-10-11','2026-10-01 15:01:11',NULL,NULL),
(71,20132,'','','','','2026-10-23','2026-10-22 15:11:23',NULL,NULL),
(72,20131,'','','','','2026-10-22','2026-10-22 15:20:54',NULL,NULL);
/*!40000 ALTER TABLE `talla_uniforme` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `tipo_asignacion`
--

DROP TABLE IF EXISTS `tipo_asignacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tipo_asignacion` (
  `id_tipo_asignacion` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_tipo_asignacion`),
  UNIQUE KEY `nombre` (`nombre`),
  KEY `idx_tipo_asignacion_estado` (`estado`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tipo_asignacion`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `tipo_asignacion` WRITE;
/*!40000 ALTER TABLE `tipo_asignacion` DISABLE KEYS */;
INSERT INTO `tipo_asignacion` VALUES
(10,'Titular','ACTIVO'),
(11,'Suplente','ACTIVO');
/*!40000 ALTER TABLE `tipo_asignacion` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `tipo_documento`
--

DROP TABLE IF EXISTS `tipo_documento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tipo_documento` (
  `id_tipo_documento` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `codigo` varchar(40) DEFAULT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_tipo_documento`),
  UNIQUE KEY `nombre` (`nombre`),
  UNIQUE KEY `uk_tipo_documento_codigo` (`codigo`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tipo_documento`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `tipo_documento` WRITE;
/*!40000 ALTER TABLE `tipo_documento` DISABLE KEYS */;
INSERT INTO `tipo_documento` VALUES
(1,'Partida de nacimiento del alumno','partida_nacimiento','ACTIVO'),
(2,'Fotos del alumno','foto_alumno','ACTIVO'),
(3,'Constancia de vacunas','constancia_vacuna','ACTIVO'),
(5,'Representante legal','foto_representante','ACTIVO'),
(7,'Constancia prosecución estudios','constancia_prosecucion','ACTIVO'),
(8,'Cédula del alumno','cedula_alumno','ACTIVO'),
(9,'Cédula del representante legal','cedula_representante','ACTIVO'),
(10,'Autorización de retiro','autorizacion_retiro','ACTIVO'),
(11,'Cédula de la persona autorizada','cedula_autorizada','ACTIVO'),
(12,'Permiso de la LOPNNA','permiso_lopnna','ACTIVO');
/*!40000 ALTER TABLE `tipo_documento` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `tipo_institucion`
--

DROP TABLE IF EXISTS `tipo_institucion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tipo_institucion` (
  `id_tipo_institucion` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_tipo_institucion`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tipo_institucion`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `tipo_institucion` WRITE;
/*!40000 ALTER TABLE `tipo_institucion` DISABLE KEYS */;
INSERT INTO `tipo_institucion` VALUES
(1,'Preescolar','ACTIVO'),
(6,'Universidad','ACTIVO'),
(8,'Escuela','ACTIVO');
/*!40000 ALTER TABLE `tipo_institucion` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `titulo`
--

DROP TABLE IF EXISTS `titulo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `titulo` (
  `id_titulo` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `id_grado_academico` int(11) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_titulo`),
  UNIQUE KEY `uk_titulo_nombre_grado` (`nombre`,`id_grado_academico`),
  KEY `fk_titulo_grado_academico` (`id_grado_academico`),
  CONSTRAINT `fk_titulo_grado_academico` FOREIGN KEY (`id_grado_academico`) REFERENCES `grado_academico` (`id_grado_academico`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `titulo`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `titulo` WRITE;
/*!40000 ALTER TABLE `titulo` DISABLE KEYS */;
INSERT INTO `titulo` VALUES
(1,'Licenciado en Computación',2,'ACTIVO'),
(2,'Arquitecto',2,'ACTIVO'),
(3,'Licenciado',2,'ACTIVO'),
(4,'Ingeniero en Produccion Industrial',3,'ACTIVO'),
(5,'Docente',2,'ACTIVO'),
(6,'sistema',2,'ACTIVO'),
(7,'civil',3,'ACTIVO'),
(11,'sistema',3,'ACTIVO'),
(13,'Comercio',2,'ACTIVO'),
(14,'Robotica',3,'ACTIVO'),
(26,'Agropecuario',3,'ACTIVO'),
(32,'Agro',2,'ACTIVO'),
(33,'Sistema',1,'ACTIVO');
/*!40000 ALTER TABLE `titulo` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `turno`
--

DROP TABLE IF EXISTS `turno`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `turno` (
  `id_turno` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(30) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_turno`),
  UNIQUE KEY `nombre` (`nombre`),
  KEY `idx_turno_estado` (`estado`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `turno`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `turno` WRITE;
/*!40000 ALTER TABLE `turno` DISABLE KEYS */;
INSERT INTO `turno` VALUES
(1,'Tarde','ACTIVO'),
(2,'Mañana','ACTIVO');
/*!40000 ALTER TABLE `turno` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `usuario`
--

DROP TABLE IF EXISTS `usuario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuario` (
  `id_usuario` int(11) NOT NULL AUTO_INCREMENT,
  `id_persona` int(11) NOT NULL,
  `id_rol` int(11) NOT NULL,
  `nombre_usuario` varchar(50) NOT NULL,
  `password_provisional` tinyint(1) NOT NULL DEFAULT 1,
  `password_hash` varchar(255) NOT NULL,
  `bloqueado_hasta` datetime DEFAULT NULL,
  `intentos_fallidos` smallint(6) NOT NULL DEFAULT 0,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `id_persona` (`id_persona`),
  UNIQUE KEY `nombre_usuario` (`nombre_usuario`),
  KEY `fk_usuario_rol` (`id_rol`),
  CONSTRAINT `fk_usuario_persona` FOREIGN KEY (`id_persona`) REFERENCES `persona` (`id_persona`) ON UPDATE CASCADE,
  CONSTRAINT `fk_usuario_rol` FOREIGN KEY (`id_rol`) REFERENCES `rol` (`id_rol`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1992 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuario`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `usuario` WRITE;
/*!40000 ALTER TABLE `usuario` DISABLE KEYS */;
INSERT INTO `usuario` VALUES
(1,1,1,'admin1',1,'$2y$12$WLRi4whBhjIlOjwvT9UyMumHczcQwYpgTOwLKX0LU2ak.aqpSALgS',NULL,1,'2026-09-01 18:23:12','2026-10-26 05:44:05','ACTIVO'),
(2,5,1,'admin',0,'$2y$12$4P5nxGimp3rTselFFnmnu.fR/RUR4vCHlVaRy0WGG4eH9.ltUglc.',NULL,0,'2026-09-02 00:34:42','2026-10-28 16:56:00','ACTIVO'),
(3,4,2,'coordinado',0,'$2y$12$2tV4gLEWm/qJrcyQTLbvDubz2ivwePpWjeoJ.hzsrnLaZkHPMKfjC',NULL,0,'2026-09-01 21:14:48','2026-10-27 17:23:09','ACTIVO'),
(4,6,5,'jose',1,'$2y$12$e4hPefcUHLLRKx4YtuYHbuUWzI/YZo9G2AbqxQIGUeYVEMq/NVHQK',NULL,0,'2026-09-01 21:19:14','2026-09-06 16:44:51','INACTIVO'),
(5,2,4,'maria',1,'$2y$12$/keOIaCSRu/Xiuvg4Mnz2eZc/RCr7XETJ7CqFnaBE/KtZFdSVnHfu',NULL,0,'2026-09-01 21:21:14','2026-09-06 15:59:05','INACTIVO'),
(6,9,2,'gaby',0,'$2y$12$QDFylkLW2bmS1CjCyd3yuuYfRwH1LzXbrUJYjkExx7YXlLDAd1KGu',NULL,0,'2026-09-01 21:24:05','2026-09-18 08:49:52','ACTIVO'),
(7,8,4,'director',0,'$2y$12$eOkSn6z/.slz6PeYw5ALA.SqwHvrFG53pQyHsVrpdxk1wji4FiqGm',NULL,0,'2026-09-02 17:53:06','2026-10-29 14:17:24','ACTIVO'),
(8,11,3,'docente',0,'$2y$12$HJwVJxsAADRESqQTeI9Dr.nPxWnRfQoboJC9AUfJ5vOSTiVJdHxYq',NULL,0,'2026-09-06 16:41:31','2026-10-22 11:55:30','ACTIVO'),
(9,16,3,'derwin',0,'$2y$12$HpNuFCMj2gkpW1MAsKv.5uqhmmhv4LcbxSgcVG2G4sBFk0ZQJCcIK',NULL,0,'2026-09-06 19:54:25','2026-10-12 14:51:26','ACTIVO'),
(10,17,1,'jenny',1,'$2y$12$IGU9./nI2g9ga.fQliwbyez083tNYJueEulWu7PRXyoft/K7oi8q6',NULL,1,'2026-09-06 19:59:24','2026-10-18 21:28:54','ACTIVO'),
(11,7,2,'luisa',1,'$2y$12$lKw8Q8vHsITRwnBkc5IU1OJ69B.NzjmzHCt76Hu9RSYB5SCBl00Fy',NULL,1,'2026-09-06 20:26:33','2026-10-25 11:01:36','ACTIVO'),
(13,19,1,'ivian',1,'$2y$12$dYvcboBYHgSYXoNQgq8zTe2QgWc36oZBcECVcHocWxfkvhR5gh5B.',NULL,0,'2026-09-06 20:30:24','2026-09-28 13:50:34','ACTIVO'),
(15,10,5,'miriam',0,'$2y$12$jd1yrEHIvjI9LG5hRryIv.UjdbWe5smC2N9qQoeDvUUZYI8lZ2Ksy',NULL,0,'2026-09-10 10:48:34','2026-10-22 13:32:26','ACTIVO'),
(16,18,1,'iriamarmaita',0,'$2y$12$ug8FjHtOA/jcUtCJZKcxienrOllhBwzjbG4L6ifTVFfVrwlG4tOZS',NULL,0,'2026-09-18 08:48:04','2026-09-28 13:50:30','ACTIVO'),
(17,3,5,'maria.09',0,'$2y$12$qe.SatK8mbIu0jALJjnQhOQRaeaW9bHiuBLVrMfjyu7sepXZHvaEq',NULL,0,'2026-09-18 09:24:35','2026-10-27 09:10:38','ACTIVO'),
(463,5485,4,'imaria',1,'$2y$12$6iZw2TUz..MSifOIqeeHwObJxL/pchfpX4QYCCzgJHZK7VFV5VvzS',NULL,0,'2026-09-27 14:12:55','2026-10-18 16:44:43','ACTIVO'),
(1949,24582,1,'admin_QaEnum.2026',0,'$2y$12$aq2V8kLtPBYle/dkRIKryev60HgYKe1gtsxlRlLLjz8IhIaSTmm6G',NULL,0,'2026-10-08 16:25:35','2026-10-17 19:53:10','INACTIVO'),
(1950,24583,4,'directivo_QaEnum.2026',0,'$2y$12$ZjjhgWMQg0yvv9glzgH1q.tSavjVxsjgOojWcrnf6pW.3YSD3w/hO',NULL,0,'2026-10-08 16:25:35','2026-10-17 19:53:10','INACTIVO'),
(1951,24584,2,'coordinador_QaEnum.2026',0,'$2y$12$C1ryoE10Wdbf3bwo0zjFaOCWmhe1iqKpA9LfpifkhvzowGf6TAatu',NULL,0,'2026-10-08 16:25:35','2026-10-17 19:53:10','INACTIVO'),
(1952,24585,5,'secretaria_QaEnum.2026',0,'$2y$12$PoSVlJlIbg2VddCtH/zWcuaa4xJYwXowr4b4p4cXOiTg5vhD5Cw7q',NULL,0,'2026-10-08 16:25:36','2026-10-17 19:53:10','INACTIVO'),
(1953,24586,3,'docente1',0,'$2y$12$iTq3oRZliw/G/eVYn9i3yuL6jTFvgRqr3/7mMdcpkLaGclnUzoK6a',NULL,0,'2026-10-08 16:25:36','2026-10-12 16:27:19','ACTIVO'),
(1963,80,1,'hiss',1,'$2y$12$gg1OFM5uW/Ao7HuJnDKZTemVMuEITnMpWeTL04t97V743ojd6n24.',NULL,0,'2026-10-24 18:22:19','2026-10-24 18:22:19','ACTIVO'),
(1964,42,2,'qa.asig.1792942955093',1,'$2y$12$WO03u3xkDpbnyKwiknk/C.n8wIiPB9w8Mr19yELkbwvKrkIwK1ZVe',NULL,0,'2026-10-25 11:42:38','2026-10-25 11:43:11','INACTIVO'),
(1965,11304,2,'qa.asig.1792943031710',1,'$2y$12$/MW7JYASchreCbWc/w7YFu4h6RZ3qAf42IXTOIOUKm6nTHKJyHDWi',NULL,0,'2026-10-25 11:43:54','2026-10-25 11:43:59','INACTIVO'),
(1966,24521,2,'qa.asig.122036',0,'$2y$12$yzrFqi8nTSsiR6dxQxORtOMLuXwCpdQrqwyMZ1CqcRsC66EK0GY3W',NULL,0,'2026-10-25 11:45:25','2026-10-25 11:45:31','INACTIVO'),
(1981,24924,1,'ll.',1,'$2y$12$zjKv5z.2anVVxIN5JKD/g.VA8N3yxHLQUtnVcCM5xpiEXOigBE42i',NULL,0,'2026-10-26 13:36:38','2026-10-26 13:37:27','ACTIVO');
/*!40000 ALTER TABLE `usuario` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Dumping routines for database 'sidge'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2026-10-29 18:56:56
