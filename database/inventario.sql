USE clinica_veterinaria;

CREATE TABLE IF NOT EXISTS inventario (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL UNIQUE,
    nombre VARCHAR(150) NOT NULL,
    categoria VARCHAR(100) NOT NULL,
    descripcion TEXT NULL,
    stock INT NOT NULL DEFAULT 0,
    stock_minimo INT NOT NULL DEFAULT 5,
    precio_compra DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    precio_venta DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    fecha_vencimiento DATE NULL,
    estado ENUM(
        'disponible',
        'agotado',
        'inactivo'
    ) NOT NULL DEFAULT 'disponible',
    fecha_registro TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_inventario_nombre (nombre),
    INDEX idx_inventario_categoria (categoria),
    INDEX idx_inventario_stock (stock)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS movimientos_inventario (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    producto_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NULL,
    tipo ENUM(
        'entrada',
        'salida',
        'ajuste'
    ) NOT NULL,
    cantidad INT NOT NULL,
    stock_anterior INT NOT NULL,
    stock_nuevo INT NOT NULL,
    motivo VARCHAR(255) NULL,
    fecha_movimiento TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_movimientos_producto
        FOREIGN KEY (producto_id)
        REFERENCES inventario(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_movimientos_usuario
        FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    INDEX idx_movimientos_producto (producto_id),
    INDEX idx_movimientos_fecha (fecha_movimiento)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
