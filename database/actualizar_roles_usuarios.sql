USE clinica_veterinaria;

-- 1. Permitir temporalmente el rol antiguo y el nuevo.
ALTER TABLE usuarios
MODIFY COLUMN rol ENUM(
    'Administrador',
    'Veterinario',
    'Recepción',
    'Recepcionista'
) NOT NULL DEFAULT 'Recepcionista';

-- 2. Convertir registros antiguos.
UPDATE usuarios
SET rol = 'Recepcionista'
WHERE rol = 'Recepción';

-- 3. Dejar únicamente los tres roles definitivos.
ALTER TABLE usuarios
MODIFY COLUMN rol ENUM(
    'Administrador',
    'Recepcionista',
    'Veterinario'
) NOT NULL DEFAULT 'Recepcionista';
