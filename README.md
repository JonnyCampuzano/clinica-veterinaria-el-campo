# Clínica Veterinaria El Campo 🐾

Sistema web desarrollado con **PHP, MySQL, HTML, CSS y JavaScript**, organizado por módulos y listo para ejecutarse con XAMPP.

## Módulos incluidos

- Página pública institucional con diseño azul y blanco.
- Inicio de sesión con roles.
- Panel principal con indicadores.
- Gestión de clientes.
- Gestión de mascotas.
- Gestión de citas.
- Historia clínica y consultas veterinarias.
- Inventario con alerta de stock bajo.
- Gestión de usuarios para administradores.
- Protección de formularios mediante token CSRF.
- Diseño adaptable a computadora, tablet y celular.

## Requisitos

- XAMPP con Apache, PHP y MySQL.
- Visual Studio Code.
- Navegador web.

## Instalación rápida

1. Descomprime la carpeta `clinica_veterinaria_el_campo`.
2. Copia la carpeta dentro de:

   `C:\xampp\htdocs\`

3. Abre XAMPP y enciende **Apache** y **MySQL**.
4. Abre en el navegador:

   `http://localhost/clinica_veterinaria_el_campo/instalar.php`

5. Conserva estos valores si usas XAMPP sin modificaciones:

   - Servidor: `localhost`
   - Puerto: `3306`
   - Usuario: `root`
   - Contraseña: vacía

6. Pulsa **Crear base de datos**.
7. Abre la página pública:

   `http://localhost/clinica_veterinaria_el_campo/`

8. Para ingresar al panel administrativo usa el botón **Iniciar sesión** o abre:

   `http://localhost/clinica_veterinaria_el_campo/login.php`

## Usuario inicial

- Correo: `admin@elcampo.ec`
- Contraseña: `Admin123*`

Después de entrar, puedes crear usuarios con rol Administrador, Veterinario o Recepción.

## Abrir en Visual Studio Code

1. Abre Visual Studio Code.
2. Selecciona **Archivo → Abrir carpeta**.
3. Escoge:

   `C:\xampp\htdocs\clinica_veterinaria_el_campo`

## Instalación manual con phpMyAdmin

Si no deseas usar `instalar.php`:

1. Abre `http://localhost/phpmyadmin`.
2. Entra en la pestaña **Importar**.
3. Selecciona `database/clinica_veterinaria.sql`.
4. Pulsa **Continuar**.
5. Verifica que `config/database.php` tenga las credenciales correctas.

## Estructura principal

```text
clinica_veterinaria_el_campo/
├── assets/
│   ├── css/style.css
│   └── js/app.js
├── citas/
├── clientes/
├── config/
├── consultas/
├── database/
├── includes/
├── inventario/
├── mascotas/
├── usuarios/
├── index.php              # Página pública
├── panel.php              # Panel administrativo
├── instalar.php
├── login.php
└── logout.php
```

## Importante

- No abras los archivos PHP con doble clic.
- Ejecuta el sistema siempre desde `http://localhost/...`.
- Si cambias el nombre de la carpeta, el sistema detectará automáticamente la nueva ruta.
- El archivo `config/database.local.php` se crea automáticamente al usar el instalador.
