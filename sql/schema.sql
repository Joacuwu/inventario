-- Instalación en MySQL 5.6+ / MariaDB. Después crear un usuario con sql/crear_usuario.php.
CREATE TABLE IF NOT EXISTS usuarios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario VARCHAR(50) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  rol ENUM('admin', 'operador') NOT NULL DEFAULT 'operador',
  PRIMARY KEY (id),
  UNIQUE KEY uk_usuarios_usuario (usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categorias (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(60) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_categorias_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS productos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(20) NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  categoria_id INT UNSIGNED NOT NULL,
  precio DECIMAL(12,2) NOT NULL,
  stock INT UNSIGNED NOT NULL DEFAULT 0,
  stock_minimo INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uk_productos_codigo (codigo),
  KEY idx_productos_nombre (nombre),
  CONSTRAINT fk_productos_categoria FOREIGN KEY (categoria_id) REFERENCES categorias (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cada cambio de stock queda registrado con quién lo hizo y cómo quedó el stock.
CREATE TABLE IF NOT EXISTS movimientos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  producto_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  tipo ENUM('entrada', 'salida') NOT NULL,
  cantidad INT UNSIGNED NOT NULL,
  stock_resultante INT UNSIGNED NOT NULL,
  motivo VARCHAR(150) NOT NULL,
  fecha DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_movimientos_fecha (fecha),
  KEY idx_movimientos_producto (producto_id),
  CONSTRAINT fk_movimientos_producto FOREIGN KEY (producto_id) REFERENCES productos (id),
  CONSTRAINT fk_movimientos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO categorias (nombre) VALUES ('Electricidad'), ('Herramientas'), ('Pinturería'), ('Plomería'), ('Tornillería');
