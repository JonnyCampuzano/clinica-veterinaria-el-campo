CREATE DATABASE IF NOT EXISTS clinica_veterinaria
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE clinica_veterinaria;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS consultas;
DROP TABLE IF EXISTS citas;
DROP TABLE IF EXISTS inventario;
DROP TABLE IF EXISTS mascotas;
DROP TABLE IF EXISTS clientes;
DROP TABLE IF EXISTS usuarios;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol ENUM('Administrador', 'Veterinario', 'Recepción') NOT NULL DEFAULT 'Recepción',
    estado ENUM('Activo', 'Inactivo') NOT NULL DEFAULT 'Activo',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE clientes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    cedula VARCHAR(13) NULL UNIQUE,
    telefono VARCHAR(30) NOT NULL,
    email VARCHAR(150) NULL UNIQUE,
    direccion VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE mascotas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    especie VARCHAR(60) NOT NULL,
    raza VARCHAR(100) NULL,
    sexo ENUM('Macho', 'Hembra') NOT NULL,
    fecha_nacimiento DATE NULL,
    peso DECIMAL(6,2) NULL,
    color VARCHAR(80) NULL,
    alergias TEXT NULL,
    observaciones TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_mascotas_clientes
        FOREIGN KEY (cliente_id) REFERENCES clientes(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE citas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mascota_id INT UNSIGNED NOT NULL,
    fecha DATE NOT NULL,
    hora TIME NOT NULL,
    motivo TEXT NOT NULL,
    estado ENUM('Pendiente', 'Confirmada', 'Atendida', 'Cancelada') NOT NULL DEFAULT 'Pendiente',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_citas_fecha (fecha),
    CONSTRAINT fk_citas_mascotas
        FOREIGN KEY (mascota_id) REFERENCES mascotas(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE consultas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mascota_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    fecha DATE NOT NULL,
    motivo TEXT NOT NULL,
    diagnostico TEXT NOT NULL,
    tratamiento TEXT NOT NULL,
    peso DECIMAL(6,2) NULL,
    temperatura DECIMAL(4,1) NULL,
    proxima_cita DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_consultas_fecha (fecha),
    CONSTRAINT fk_consultas_mascotas
        FOREIGN KEY (mascota_id) REFERENCES mascotas(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_consultas_usuarios
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE inventario (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    categoria VARCHAR(80) NOT NULL,
    stock INT UNSIGNED NOT NULL DEFAULT 0,
    stock_minimo INT UNSIGNED NOT NULL DEFAULT 5,
    precio DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0,
    fecha_vencimiento DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO usuarios (nombre, email, password, rol, estado) VALUES
('Administrador El Campo', 'admin@elcampo.ec', '$2y$12$dyoY1kybg5davSBlmq0wlu44sjvIHpY5NYfY5JZl.45vWDchpwfPC', 'Administrador', 'Activo');

INSERT INTO clientes (nombres, apellidos, cedula, telefono, email, direccion) VALUES
('María', 'Gómez', '0912345678', '0987654321', 'maria@example.com', 'Daule, Guayas'),
('Carlos', 'Mendoza', '0923456789', '0991112233', 'carlos@example.com', 'Nobol, Guayas'),
('Ana', 'Vera', '0934567890', '0965554433', 'ana@example.com', 'Petrillo, Nobol');

INSERT INTO mascotas (cliente_id, nombre, especie, raza, sexo, fecha_nacimiento, peso, color, alergias, observaciones) VALUES
(1, 'Max', 'Perro', 'Labrador', 'Macho', '2022-03-15', 28.50, 'Dorado', NULL, 'Mascota sociable'),
(2, 'Luna', 'Gato', 'Mestizo', 'Hembra', '2023-06-10', 4.20, 'Blanco y negro', 'Penicilina', 'Mantener en transportadora'),
(3, 'Rocky', 'Perro', 'Mestizo', 'Macho', '2021-11-02', 18.70, 'Café', NULL, NULL);

INSERT INTO citas (mascota_id, fecha, hora, motivo, estado) VALUES
(1, CURDATE(), '09:00:00', 'Vacunación anual', 'Confirmada'),
(2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '10:30:00', 'Control general', 'Pendiente'),
(3, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '15:00:00', 'Revisión de piel', 'Pendiente');

INSERT INTO consultas
(mascota_id, usuario_id, fecha, motivo, diagnostico, tratamiento, peso, temperatura, proxima_cita)
VALUES
(1, 1, DATE_SUB(CURDATE(), INTERVAL 10 DAY), 'Decaimiento y falta de apetito',
 'Gastritis leve', 'Dieta blanda durante tres días y medicación según indicación.', 28.50, 38.6,
 DATE_ADD(CURDATE(), INTERVAL 5 DAY));

INSERT INTO inventario (nombre, categoria, stock, stock_minimo, precio, fecha_vencimiento) VALUES
('Vacuna séxtuple canina', 'Vacuna', 12, 5, 18.50, DATE_ADD(CURDATE(), INTERVAL 8 MONTH)),
('Desparasitante oral', 'Medicamento', 4, 6, 7.25, DATE_ADD(CURDATE(), INTERVAL 5 MONTH)),
('Shampoo medicado', 'Higiene', 9, 3, 12.00, NULL),
('Alimento premium 2 kg', 'Alimento', 3, 5, 19.90, DATE_ADD(CURDATE(), INTERVAL 10 MONTH));
