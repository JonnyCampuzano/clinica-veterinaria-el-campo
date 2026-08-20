USE clinica_veterinaria;

CREATE TABLE IF NOT EXISTS reservas_citas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NULL,
    nombre_cliente VARCHAR(512) NULL,
    correo_cliente VARCHAR(512) NULL,
    nombre_mascota VARCHAR(120) NOT NULL,
    especie VARCHAR(80) NOT NULL,
    fecha DATE NOT NULL,
    hora TIME NOT NULL,
    motivo TEXT NOT NULL,
    estado ENUM('Pendiente','Confirmada','Cancelada')
        NOT NULL DEFAULT 'Pendiente',
    fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_reservas_fecha (fecha),
    INDEX idx_reservas_estado (estado),
    INDEX idx_reservas_usuario (usuario_id)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
