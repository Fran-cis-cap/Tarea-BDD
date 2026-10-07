-- ============================================================
-- SaludUSM - Tarea 2 INF-239 (MySQL)
-- Script completo: tablas, function, procedimientos, triggers,
-- views y datos de prueba.
-- ============================================================

CREATE DATABASE IF NOT EXISTS saludusm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE saludusm;

-- ============================================================
-- 1. LIMPIEZA TABLAS EXISTENTES
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP VIEW IF EXISTS vista_citas_detalle;
DROP VIEW IF EXISTS vista_historial_clinico;
DROP VIEW IF EXISTS vista_panel_centros;
DROP VIEW IF EXISTS vista_top_diagnosticos;
 
DROP PROCEDURE IF EXISTS actualizar_citas_vencidas;
DROP PROCEDURE IF EXISTS sp_agendar_cita;
DROP PROCEDURE IF EXISTS sp_reprogramar_cita;
 
DROP FUNCTION IF EXISTS porcentaje_inasistencia;
DROP FUNCTION IF EXISTS bloque_disponible;

DROP TABLE IF EXISTS receta;
DROP TABLE IF EXISTS atencion_diagnostico;
DROP TABLE IF EXISTS diagnostico;
DROP TABLE IF EXISTS atencion;
DROP TABLE IF EXISTS cita;
DROP TABLE IF EXISTS medico_centro;
DROP TABLE IF EXISTS medico_especialidad;
DROP TABLE IF EXISTS medico;
DROP TABLE IF EXISTS paciente;
DROP TABLE IF EXISTS centro_medico;
DROP TABLE IF EXISTS especialidad;
DROP TABLE IF EXISTS estado_cita;
DROP TABLE IF EXISTS prevision;
DROP TABLE IF EXISTS administrador;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- 2. TABLAS
-- ============================================================

CREATE TABLE prevision (
    id_prevision INT AUTO_INCREMENT PRIMARY KEY,
    nombre_prevision VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE estado_cita (
    id_estado INT AUTO_INCREMENT PRIMARY KEY,
    nombre_estado VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE especialidad (
    id_especialidad INT AUTO_INCREMENT PRIMARY KEY,
    nombre_especialidad VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE centro_medico (
    id_centro INT AUTO_INCREMENT PRIMARY KEY,
    codigo_interno VARCHAR(20) NOT NULL UNIQUE,
    nombre_centro VARCHAR(100) NOT NULL,
    comuna VARCHAR(50) NOT NULL,
    region VARCHAR(50) NOT NULL
) ENGINE=InnoDB;


CREATE TABLE paciente (
    id_paciente INT AUTO_INCREMENT PRIMARY KEY,
    rut VARCHAR(10) NOT NULL UNIQUE,
    nombre_completo VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    fecha_nacimiento DATE NOT NULL,
    sexo VARCHAR(5) NOT NULL,
    telefono_contacto VARCHAR(15) NULL,
    comuna_residencia VARCHAR(50) NOT NULL,
    contrasena VARCHAR(255) NOT NULL,
    id_prevision INT NOT NULL,
    FOREIGN KEY (id_prevision) REFERENCES prevision (id_prevision) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE medico (
    id_medico INT AUTO_INCREMENT PRIMARY KEY,
    rut VARCHAR(10) NOT NULL UNIQUE,
    nombre_completo VARCHAR(255) NOT NULL,
    email_institucional VARCHAR(100) NOT NULL UNIQUE,
    contrasena VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE administrador (
    id_admin INT AUTO_INCREMENT PRIMARY KEY,
    rut VARCHAR(10) NOT NULL UNIQUE,
    nombre_completo VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    contrasena VARCHAR(255) NOT NULL
) ENGINE=InnoDB;


CREATE TABLE medico_especialidad (
    id_medico INT NOT NULL,
    id_especialidad INT NOT NULL,
    PRIMARY KEY (id_medico, id_especialidad),
    FOREIGN KEY (id_medico) REFERENCES medico (id_medico) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (id_especialidad) REFERENCES especialidad (id_especialidad) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE medico_centro (
    id_medico INT NOT NULL,
    id_centro INT NOT NULL,
    PRIMARY KEY (id_medico, id_centro),
    FOREIGN KEY (id_medico) REFERENCES medico (id_medico) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (id_centro) REFERENCES centro_medico (id_centro) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE cita (
    id_cita INT AUTO_INCREMENT PRIMARY KEY,
    fecha_hora DATETIME NOT NULL,
    id_paciente INT NOT NULL,
    id_medico INT NOT NULL,
    id_centro INT NOT NULL,
    id_estado INT NOT NULL,
    id_especialidad INT NOT NULL,
    FOREIGN KEY (id_paciente) REFERENCES paciente (id_paciente) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (id_medico) REFERENCES medico (id_medico) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (id_centro) REFERENCES centro_medico (id_centro) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (id_estado) REFERENCES estado_cita (id_estado) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (id_especialidad) REFERENCES especialidad (id_especialidad) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE atencion (
    id_atencion INT AUTO_INCREMENT PRIMARY KEY,
    fecha_atencion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    motivo_consulta VARCHAR(255) NOT NULL,
    observaciones_clinicas VARCHAR(255) NOT NULL,
    id_cita INT NOT NULL UNIQUE,
    FOREIGN KEY (id_cita) REFERENCES cita (id_cita) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE diagnostico (
    id_diagnostico INT AUTO_INCREMENT PRIMARY KEY,
    codigo_cie10 VARCHAR(10) NOT NULL UNIQUE,
    descripcion VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE atencion_diagnostico (
    id_atencion INT NOT NULL,
    id_diagnostico INT NOT NULL,
    PRIMARY KEY (id_atencion, id_diagnostico),
    FOREIGN KEY (id_atencion) REFERENCES atencion (id_atencion) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (id_diagnostico) REFERENCES diagnostico (id_diagnostico) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE receta (
    id_receta INT AUTO_INCREMENT PRIMARY KEY,
    id_atencion INT NOT NULL,
    medicamento VARCHAR(100) NOT NULL,
    dosis VARCHAR(50) NOT NULL,
    dias_tratamiento INT NOT NULL,
    FOREIGN KEY (id_atencion) REFERENCES atencion (id_atencion)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 3. CATÁLOGOS (el orden fija los ids que usan los procedimientos:
--    1 Reservada, 2 Confirmada, 3 Atendida, 4 No Asistió, 5 Cancelada)
-- ============================================================

INSERT INTO prevision (nombre_prevision) VALUES 
('Fonasa'),
('Isapre'),
('Particular');

INSERT INTO estado_cita (nombre_estado) VALUES 
('Reservada'),
('Confirmada'),
('Atendida'),
('No Asistió'),
('Cancelada');

INSERT INTO especialidad (nombre_especialidad) VALUES 
('Cardiología'),
('Pediatría'),
('Traumatología'),
('Medicina General'),
('Dermatología'),
('Neurología'),
('Kinesiología'),
('Oftalmología');

DROP PROCEDURE IF EXISTS actualizar_citas_vencidas;

-- ============================================================
-- 4. FUNCTIONS
-- ============================================================

DELIMITER //

-- Porcentaje de citas "No Asistió" de un centro (Panel de gestión, 3.4.5)
CREATE FUNCTION porcentaje_inasistencia(p_id_centro INT)
RETURNS DECIMAL(5,2)
READS SQL DATA
BEGIN
    DECLARE v_total INT DEFAULT 0;
    DECLARE v_faltas INT DEFAULT 0;
 
    SELECT COUNT(*), COALESCE(SUM(id_estado = 4), 0)
      INTO v_total, v_faltas
      FROM cita
     WHERE id_centro = p_id_centro;
 
    RETURN IF(v_total = 0, 0, ROUND(v_faltas * 100 / v_total, 2));
END //
 
-- Devuelve 1 si el médico no tiene una cita activa en ese horario (Agendar hora, 3.4.4)
CREATE FUNCTION bloque_disponible(p_id_medico INT, p_fecha_hora DATETIME)
RETURNS TINYINT(1)
READS SQL DATA
BEGIN
    RETURN NOT EXISTS (
        SELECT 1 FROM cita
         WHERE id_medico = p_id_medico
           AND fecha_hora = p_fecha_hora
           AND id_estado <> 5
    );
END //

DELIMITER ;

-- ============================================================
-- 5. PROCEDIMIENTOS ALMACENADOS
-- ============================================================

DELIMITER //

-- Citas vencidas (Reservada/Confirmada con fecha pasada) pasan a Cancelada (3.6)
CREATE PROCEDURE actualizar_citas_vencidas()
BEGIN
    UPDATE cita
    SET id_estado = 5
    WHERE id_estado IN (1, 2) AND fecha_hora < NOW();
END //

DROP TRIGGER IF EXISTS validar_sobreagendamiento;

-- Agendar una cita nueva en estado Reservada (3.4.4 / 3.6).
-- El trigger valida especialidad, centro y sobre-agendamiento.
CREATE PROCEDURE sp_agendar_cita(
    IN p_id_paciente INT,
    IN p_id_medico INT,
    IN p_id_centro INT,
    IN p_id_especialidad INT,
    IN p_fecha_hora DATETIME
)
BEGIN
    IF p_fecha_hora <= NOW() THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Error: no se puede agendar una cita en una fecha u hora pasada.';
    END IF;
 
    INSERT INTO cita (fecha_hora, id_paciente, id_medico, id_centro, id_estado, id_especialidad)
    VALUES (p_fecha_hora, p_id_paciente, p_id_medico, p_id_centro, 1, p_id_especialidad);
 
    SELECT LAST_INSERT_ID() AS id_cita;
END //
 
-- Reprogramar una cita propia (3.6): solo Reservada/Confirmada y no vencida
CREATE PROCEDURE sp_reprogramar_cita(
    IN p_id_cita INT,
    IN p_id_paciente INT,
    IN p_nueva_fecha_hora DATETIME
)
BEGIN
    DECLARE v_estado INT DEFAULT NULL;
    DECLARE v_fecha DATETIME DEFAULT NULL;
 
    SELECT id_estado, fecha_hora INTO v_estado, v_fecha
      FROM cita
     WHERE id_cita = p_id_cita AND id_paciente = p_id_paciente;
 
    IF v_estado IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Error: la cita no existe o no pertenece al paciente.';
    END IF;
 
    IF v_estado NOT IN (1, 2) OR v_fecha < NOW() THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Error: solo se pueden reprogramar citas Reservadas o Confirmadas cuya fecha no haya pasado.';
    END IF;
 
    IF p_nueva_fecha_hora <= NOW() THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Error: la nueva fecha y hora debe ser futura.';
    END IF;
 
    UPDATE cita SET fecha_hora = p_nueva_fecha_hora WHERE id_cita = p_id_cita;
END //
 
DELIMITER ;

-- ============================================================
-- 6. TRIGGERS
-- ============================================================

DELIMITER //

CREATE TRIGGER validar_sobreagendamiento 
BEFORE INSERT ON cita
FOR EACH ROW
BEGIN
    DECLARE cantidad_citas_previa_medico INT;
    DECLARE cantidad_citas_previa_paciente INT;

    -- Se valida si el médico ya tiene cita en ese horario, es decir, que no este 'Cancelada'
    SELECT COUNT(*) INTO cantidad_citas_previa_medico 
    FROM cita 
    WHERE id_medico = NEW.id_medico AND fecha_hora = NEW.fecha_hora AND id_estado !=5;

    IF cantidad_citas_previa_medico > 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Error: El médico ya tiene una cita agendada en la fecha y hora seleccionada.';
    END IF;

    -- Se valida si el paciente ya tiene cita en ese horario, es decir, no 'Cancelada'
    SELECT COUNT(*) INTO cantidad_citas_previa_paciente 
    FROM cita 
    WHERE id_paciente = NEW.id_paciente AND fecha_hora = NEW.fecha_hora AND id_estado != 5;

    IF cantidad_citas_previa_paciente > 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Error: El paciente ya tiene otra cita registrada en ese mismo bloque horario.';
    END IF;
END //

DELIMITER //

CREATE TRIGGER validar_sobreagendamiento_upd
BEFORE UPDATE ON cita
FOR EACH ROW
BEGIN
    IF NEW.id_estado <> 5 THEN
        IF (SELECT COUNT(*) FROM cita
            WHERE id_medico = NEW.id_medico AND fecha_hora = NEW.fecha_hora
              AND id_estado <> 5 AND id_cita <> NEW.id_cita) > 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Error: El médico ya tiene una cita en esa fecha y hora.';
        END IF;
        IF (SELECT COUNT(*) FROM cita
            WHERE id_paciente = NEW.id_paciente AND fecha_hora = NEW.fecha_hora
              AND id_estado <> 5 AND id_cita <> NEW.id_cita) > 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Error: El paciente ya tiene otra cita en ese horario.';
        END IF;
    END IF;
END //

DELIMITER ;

-- ============================================================
-- 7. VIEWS
-- ============================================================

DROP VIEW IF EXISTS historial_clinico_completo;

CREATE VIEW historial_clinico_completo AS 
SELECT 
    a.id_atencion,
    c.id_cita,
    c.fecha_hora AS fecha_cita,
    p.rut AS rut_paciente,
    p.nombre_completo AS nombre_paciente,
    p.fecha_nacimiento,
    m.rut AS rut_medico,
    m.nombre_completo AS nombre_medico,
    e.nombre_especialidad,
    cm.nombre_centro,
    a.motivo_consulta,
    a.observaciones_clinicas,
    d.codigo_cie10,
    d.descripcion AS diagnostico_descripcion,
    r.medicamento,
    r.dosis,
    r.dias_tratamiento
FROM atencion a
JOIN cita c ON a.id_cita = c.id_cita
JOIN paciente p ON c.id_paciente = p.id_paciente
JOIN medico m ON c.id_medico = m.id_medico
JOIN especialidad e ON c.id_especialidad = e.id_especialidad
JOIN centro_medico cm ON c.id_centro = cm.id_centro
LEFT JOIN atencion_diagnostico ad ON a.id_atencion = ad.id_atencion
LEFT JOIN diagnostico d ON ad.id_diagnostico = d.id_diagnostico
LEFT JOIN receta r ON a.id_atencion = r.id_atencion;

-- Panel de gestión: total de citas y % de inasistencia por centro (usa la function)
CREATE VIEW vista_panel_centros AS
SELECT
    cm.id_centro,
    cm.nombre_centro,
    cm.comuna,
    cm.region,
    COUNT(c.id_cita) AS total_citas,
    COALESCE(SUM(c.id_estado = 4), 0) AS total_no_asistio,
    porcentaje_inasistencia(cm.id_centro) AS porcentaje_inasistencia
FROM centro_medico cm
LEFT JOIN cita c ON c.id_centro = cm.id_centro
GROUP BY cm.id_centro, cm.nombre_centro, cm.comuna, cm.region;
 
-- Panel de gestión: los 5 diagnósticos más frecuentes de la red
CREATE VIEW vista_top_diagnosticos AS
SELECT
    d.codigo_cie10,
    d.descripcion,
    COUNT(*) AS total
FROM atencion_diagnostico ad
JOIN diagnostico d ON ad.id_diagnostico = d.id_diagnostico
GROUP BY d.id_diagnostico, d.codigo_cie10, d.descripcion
ORDER BY total DESC, d.codigo_cie10
LIMIT 5;

DELIMITER ;

-- ============================================================
-- SCRIPT DE POBLAMIENTO DE DATOS DE PRUEBA (DML - SALUDUSM)
-- ============================================================

-- 1. CENTROS MÉDICOS
INSERT INTO centro_medico (codigo_interno, nombre_centro, comuna, region) VALUES 
('CENTRO-01', 'SaludUSM Casa Central', 'Valparaíso', 'Valparaíso'),
('CENTRO-02', 'SaludUSM San Joaquín', 'San Joaquín', 'Metropolitana'),
('CENTRO-03', 'SaludUSM Concepción', 'Concepción', 'Biobío'),
('CENTRO-04', 'SaludUSM Viña del Mar', 'Viña del Mar', 'Valparaíso');

-- 2. DIAGNÓSTICOS (CIE-10) - Más de 5 para probar Top 5 en Panel de Gestión
INSERT INTO diagnostico (codigo_cie10, descripcion) VALUES 
('J00', 'Rinitis aguda (Resfriado común)'),
('I10', 'Hipertensión esencial (primaria)'),
('E11', 'Diabetes mellitus tipo 2'),
('K21', 'Enfermedad por reflujo gastroesofágico'),
('M54', 'Lumbago no especificado'),
('J45', 'Asma bronquial'),
('N39', 'Infección del tracto urinario'),
('G43', 'Migraña y otros síndromes de cefalea');

-- 3. USUARIOS: ADMINISTRADORES (Rol 3)
INSERT INTO administrador (rut, nombre_completo, email, contrasena) VALUES 
('11111111-1', 'Admin General SaludUSM', 'admin@saludusm.cl', 'admin123'),
('22222222-2', 'Admin Secundario', 'admin2@saludusm.cl', 'admin123');

-- 4. USUARIOS: MÉDICOS (Rol 2)
INSERT INTO medico (rut, nombre_completo, email_institucional, contrasena) VALUES 
('12345678-9', 'Dr. Roberto Gómez', 'roberto.gomez@saludusm.cl', 'medico123'),
('98765432-1', 'Dra. María Elena', 'maria.elena@saludusm.cl', 'medico123'),
('15555444-3', 'Dr. Carlos Mendoza', 'carlos.mendoza@saludusm.cl', 'medico123'),
('16666777-8', 'Dra. Ana Torres', 'ana.torres@saludusm.cl', 'medico123');

-- Asignación de Especialidades a Médicos (1 a 3 especialidades)
INSERT INTO medico_especialidad (id_medico, id_especialidad) VALUES 
(1, 1), (1, 4),        -- Dr. Roberto: Cardiología y Medicina General
(2, 2),                -- Dra. María: Pediatría
(3, 3), (3, 7),        -- Dr. Carlos: Traumatología y Kinesiología
(4, 5);                -- Dra. Ana: Dermatología

-- Asignación de Centros a Médicos (1 o más centros)
INSERT INTO medico_centro (id_medico, id_centro) VALUES 
(1, 1), (1, 2),        -- Dr. Roberto atiende en Casa Central y San Joaquín
(2, 1), (2, 4),        -- Dra. María atiende en Casa Central y Viña del Mar
(3, 2), (3, 3),        -- Dr. Carlos atiende en San Joaquín y Concepción
(4, 1);                -- Dra. Ana atiende en Casa Central

-- 5. USUARIOS: PACIENTES (Rol 1)
INSERT INTO paciente (rut, nombre_completo, fecha_nacimiento, sexo, telefono_contacto, comuna_residencia, contrasena, id_prevision) VALUES 
('19876543-2', 'Juan Pérez Cotapos', '1995-04-12', 'M', '+56912345678', 'Valparaíso', 'paciente123', 1),
('20123456-7', 'Ana María Silva', '2000-08-25', 'F', '+56987654321', 'Viña del Mar', 'paciente123', 2),
('18234567-8', 'Pedro Morales Soto', '1988-11-03', 'M', '+56911223344', 'Santiago', 'paciente123', 3),
('17345678-9', 'Luisa Fernández Rivas', '1982-01-15', 'F', '+56955667788', 'Concepción', 'paciente123', 1),
('21456789-0', 'Diego Araya Castro', '2002-06-30', 'M', '+56999887766', 'San Joaquín', 'paciente123', 2);

-- 6. CITAS MÉDICAS (Pasadas y Futuras en diversos Estados)
INSERT INTO cita (fecha_hora, id_paciente, id_medico, id_centro, id_estado, id_especialidad) VALUES 
-- Citas Pasadas (Atendidas)
('2026-09-10 09:00:00', 1, 1, 1, 3, 4), -- Paciente 1, Dr. Roberto (Med. General), Atendida
('2026-09-12 11:30:00', 2, 2, 1, 3, 2), -- Paciente 2, Dra. María (Pediatría), Atendida
('2026-09-15 15:00:00', 3, 3, 2, 3, 3), -- Paciente 3, Dr. Carlos (Traumatología), Atendida
('2026-09-20 10:00:00', 4, 1, 1, 3, 1), -- Paciente 4, Dr. Roberto (Cardiología), Atendida
('2026-09-22 16:00:00', 5, 4, 1, 3, 5), -- Paciente 5, Dra. Ana (Dermatología), Atendida

-- Citas Pasadas (No Asistió y Cancelada)
('2026-09-18 10:00:00', 1, 2, 1, 4, 2), -- No Asistió
('2026-09-25 14:00:00', 2, 1, 2, 5, 4), -- Cancelada
('2026-09-28 09:30:00', 3, 3, 3, 4, 7), -- No Asistió

-- Citas Vencidas (Para probar el Stored Procedure sp_actualizar_citas_vencidas)
('2026-09-01 08:30:00', 4, 1, 1, 1, 4), -- Pasada en 'Reservada' -> Debe pasar a 'Cancelada'

-- Citas Futuras (Próximas)
('2026-10-20 10:00:00', 1, 1, 1, 1, 4), -- Reservada
('2026-10-22 11:00:00', 2, 2, 4, 2, 2), -- Confirmada
('2026-10-25 16:30:00', 3, 3, 2, 1, 3); -- Reservada

-- 7. ATENCIONES MÉDICAS (Asociadas a las Citas Atendidas: id_cita 1, 2, 3, 4, 5)
INSERT INTO atencion (motivo_consulta, observaciones_clinicas, id_cita) VALUES 
('Control de rutina y resfriado', 'Paciente presenta congestión nasal leve y fiebre de 37.5°C.', 1),
('Fiebre alta y tos', 'Se evalúa paciente pediátrico, pulmones limpios.', 2),
('Dolor en rodilla derecha', 'Contusión por actividad deportiva. Se sugiere reposo y kinesiterapia.', 3),
('Chequeo cardiovascular', 'Presión arterial elevada (140/90). Se inicia control de hipertensión.', 4),
('Aparición de manchas en la piel', 'Dermatitis de contacto ligera.', 5);

-- 8. DIAGNÓSTICOS POR ATENCIÓN (N:M entre Atención y Diagnóstico)
INSERT INTO atencion_diagnostico (id_atencion, id_diagnostico) VALUES 
(1, 1), -- Atención 1 -> Resfriado común (J00)
(2, 1), (2, 6), -- Atención 2 -> Resfriado (J00) y Asma (J45)
(3, 5), -- Atención 3 -> Lumbago/Dolor (M54)
(4, 2), (4, 3), -- Atención 4 -> Hipertensión (I10) y Diabetes (E11)
(5, 1); -- Atención 5 -> Resfriado / Afección cutánea

-- 9. RECETAS MÉRICA (Asociadas a las Atenciones)
INSERT INTO receta (id_receta, id_atencion, medicamento, dosis, dias_tratamiento) VALUES 
(1, 1, 'Paracetamol 500mg', '1 tableta cada 8 horas', 5),
(2, 1, 'Ibuprofeno 400mg', '1 tableta cada 12 horas con alimentos', 3),
(1, 2, 'Amoxicilina 250mg/5ml', '5ml cada 8 horas', 7),
(1, 3, 'Ketoprofeno 100mg', '1 comprimido cada 12 horas', 5),
(1, 4, 'Enalapril 10mg', '1 comprimido cada mañana', 30),
(2, 4, 'Metformina 850mg', '1 comprimido con el almuerzo', 30),
(1, 5, 'Loratadina 10mg', '1 comprimido en la noche', 10);