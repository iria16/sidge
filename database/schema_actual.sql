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

INSERT INTO `cargo` (`id_cargo`, `nombre`, `estado`) VALUES
(1,'Director','ACTIVO'),
(2,'Subdirector','ACTIVO'),
(3,'Docente de Aula','ACTIVO'),
(4,'Coordinador Pedagógico','ACTIVO'),
(5,'Administrador del Sistema','ACTIVO'),
(6,'Psicopedagogo(a)','ACTIVO'),
(7,'Docente de Educación Especial','ACTIVO'),
(8,'Secretaria','ACTIVO');

--
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
--
-- Table structure for table `estudiante_documento`
--

DROP TABLE IF EXISTS `estudiante_documento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `estudiante_documento` (
  `id_estudiante_documento` int(11) NOT NULL AUTO_INCREMENT,
  `id_estudiante` int(11) NOT NULL,
  `id_persona_estudiante` int(11) DEFAULT NULL,
  `id_tipo_documento` int(11) NOT NULL,
  `observacion` text DEFAULT NULL,
  `consignado` tinyint(1) NOT NULL DEFAULT 0,
  `requerido` tinyint(1) DEFAULT NULL,
  `actualizado_en` datetime DEFAULT NULL,
  `actualizado_por` int(11) DEFAULT NULL,
  `id_persona_estudiante_unico` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_estudiante_documento`),
  UNIQUE KEY `uk_estudiante_documento_instancia` (`id_estudiante`,`id_tipo_documento`,`id_persona_estudiante_unico`),
  KEY `idx_estudiante_documento_tipo` (`id_tipo_documento`),
  KEY `idx_estudiante_documento_consignado` (`consignado`),
  KEY `idx_estudiante_documento_usuario` (`actualizado_por`),
  KEY `idx_estudiante_documento_persona` (`id_persona_estudiante`),
  CONSTRAINT `fk_estudiante_documento_estudiante` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiante` (`id_estudiante`) ON UPDATE CASCADE,
  CONSTRAINT `fk_estudiante_documento_tipo` FOREIGN KEY (`id_tipo_documento`) REFERENCES `tipo_documento` (`id_tipo_documento`) ON UPDATE CASCADE,
  CONSTRAINT `fk_estudiante_documento_persona_estudiante` FOREIGN KEY (`id_persona_estudiante`) REFERENCES `persona_estudiante` (`id_persona_estudiante`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_estudiante_documento_usuario` FOREIGN KEY (`actualizado_por`) REFERENCES `usuario` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=142 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
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
  `documentos_evaluados_en` datetime DEFAULT NULL,
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
  `talla_camisa` enum('','2','4','6','8','10','12','14','16','S','M','L','XL') NOT NULL DEFAULT '',
  `talla_pantalon` enum('','2','4','6','8','10','12','14','16','26','28','30','32','34','36') NOT NULL DEFAULT '',
  `talla_zapato` enum('','24','25','26','27','28','29','30','31','32','33','34','35','36','37','38','39','40') NOT NULL DEFAULT '',
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
(2,'Mañana','ACTIVO'),
(3,'Tiempo Completo','ACTIVO');
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
