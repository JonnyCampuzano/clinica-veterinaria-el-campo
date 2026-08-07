USE clinica_veterinaria;

CREATE TABLE IF NOT EXISTS historias_clinicas (
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
    INDEX idx_consultas_proxima_cita (proxima_cita),
    INDEX idx_consultas_mascota (mascota_id),
    INDEX idx_consultas_usuario (usuario_id),

    CONSTRAINT fk_consultas_mascotas
        FOREIGN KEY (mascota_id)
        REFERENCES mascotas(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_consultas_usuarios
        FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
