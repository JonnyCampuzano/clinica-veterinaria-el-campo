<?php
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

$sesionActiva = !empty($_SESSION['usuario_id']);

$enlaceSistema = $sesionActiva
    ? url('panel.php')
    : url('login.php');

$textoSistema = $sesionActiva
    ? 'Ir al panel'
    : 'Iniciar sesión';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta
        name="description"
        content="Clínica Veterinaria El Campo: atención médica, citas, mascotas, historias clínicas e inventario."
    >

    <title>Clínica Veterinaria El Campo</title>

    <link
        rel="stylesheet"
        href="<?= e(url('assets/css/publica.css')) ?>"
    >
</head>

<body>

<header class="public-header">

    <div class="container header-top">

        <!-- Logo y nombre -->
        <a
            class="public-brand"
            href="#inicio"
            aria-label="Ir al inicio"
        >
            <span class="public-logo">🐾</span>

            <span class="public-brand-text">
                <strong>
                    CLÍNICA VETERINARIA <em>EL CAMPO</em>
                </strong>

                <small>
                    Salud y bienestar para tus mascotas
                </small>
            </span>
        </a>

        <!-- Información y botón juntos a la derecha -->
        <div class="header-right">

            <div class="contact-summary">

                <div class="contact-item">
                    <span class="contact-icon">📍</span>
                    <span>Nobol, Guayas – Ecuador</span>
                </div>

                <a
                    class="contact-item"
                    href="mailto:elcampo@veterinaria.ec"
                >
                    <span class="contact-icon">✉️</span>
                    <span>elcampo@veterinaria.ec</span>
                </a>

                <div class="contact-item">
                    <span class="contact-icon">🕐</span>
                    <span>Lun – Vie: 08h00 – 17h00</span>
                </div>

            </div>

            <a
                class="login-button"
                href="<?= e($enlaceSistema) ?>"
            >
                👤 <?= e($textoSistema) ?>
            </a>

        </div>

    </div>

    <nav
        class="main-navbar"
        aria-label="Navegación principal"
    >
        <div class="container navbar-inner">

            <button
                class="mobile-nav-button"
                id="mobileNavButton"
                type="button"
                aria-controls="publicNavLinks"
                aria-expanded="false"
            >
                ☰ Menú
            </button>

            <div
                class="nav-links"
                id="publicNavLinks"
            >
                <a class="active" href="#inicio">🏠 Inicio</a>
                <a href="#nosotros">🐕 Nosotros</a>
                <a href="#servicios">💊 Servicios</a>
                <a href="#horario">📅 Horario</a>
                <a href="#noticias">📰 Noticias</a>
                <a href="#contacto">📞 Contacto</a>
                <a href="<?= e($enlaceSistema) ?>">🔐 Sistema</a>
            </div>

        </div>
    </nav>

</header>

<main>

    <!-- PORTADA -->
    <section class="hero" id="inicio">
        <div class="container hero-grid">

            <div class="hero-copy">

                <h1>
                    Bienvenidos a la
                    <br>
                    <strong>Clínica Veterinaria El Campo</strong>
                </h1>

                <p>
                    Un espacio donde tus mascotas reciben atención médica
                    de calidad. Administra citas, clientes, mascotas,
                    historias clínicas e inventario desde nuestro sistema
                    veterinario.
                </p>

                <div class="hero-actions">

                    <a
                        class="primary-action"
                        href="#servicios"
                    >
                        🐾 Nuestros servicios
                    </a>

                    <a
                        class="secondary-action"
                        href="#horario"
                    >
                        📅 Ver horario
                    </a>

                </div>

            </div>

            <div class="hero-image-card">

                <img
                    src="<?= e(url('assets/img/doctor.png')) ?>"
                    alt="Veterinario atendiendo a una mascota"
                >

                <div class="hero-image-badge">
                    <strong>Atención responsable</strong>

                    <span>
                        Profesionales al cuidado de tu mascota
                    </span>
                </div>

            </div>

        </div>
    </section>

    <!-- SERVICIOS -->
    <section class="service-strip" id="servicios">
        <div class="container service-grid">

            <a
                class="service-item service-item-link"
                href="<?= e($enlaceSistema) ?>"
                aria-label="Acceder al módulo de citas médicas"
            >
                <span class="service-icon">📅</span>
                <h2>Citas Médicas</h2>
                <p>Agenda y administra las citas veterinarias</p>
                <small class="service-action">Gestionar citas →</small>
            </a>

            <a
                class="service-item service-item-link"
                href="<?= e($enlaceSistema) ?>"
                aria-label="Acceder al módulo de clientes"
            >
                <span class="service-icon">👥</span>
                <h2>Clientes</h2>
                <p>Registro y administración de propietarios</p>
                <small class="service-action">Gestionar clientes →</small>
            </a>

            <a
                class="service-item service-item-link"
                href="<?= e($enlaceSistema) ?>"
                aria-label="Acceder al módulo de inventario"
            >
                <span class="service-icon">📦</span>
                <h2>Inventario</h2>
                <p>Control de medicamentos, productos y existencias</p>
                <small class="service-action">Ver inventario →</small>
            </a>

            <a
                class="service-item service-item-link"
                href="<?= e($enlaceSistema) ?>"
                aria-label="Acceder al módulo de mascotas"
            >
                <span class="service-icon">🐾</span>
                <h2>Mascotas</h2>
                <p>Registro y seguimiento de las mascotas</p>
                <small class="service-action">Gestionar mascotas →</small>
            </a>

            <a
                class="service-item service-item-link"
                href="<?= e($enlaceSistema) ?>"
                aria-label="Acceder al módulo de historia clínica"
            >
                <span class="service-icon">📋</span>
                <h2>Historia Clínica</h2>
                <p>Consultas, diagnósticos y tratamientos médicos</p>
                <small class="service-action">Ver historial →</small>
            </a>

        </div>
    </section>

    <!-- NOSOTROS Y HORARIO -->
    <section class="information-section" id="nosotros">
        <div class="container information-grid">

            <article class="info-card about-card">

                <span class="section-label">NOSOTROS</span>

                <h2>
                    Comprometidos con el bienestar animal
                </h2>

                <p>
                    Brindamos atención veterinaria cercana, segura y
                    responsable. Nuestro objetivo es acompañar a cada
                    familia en el cuidado preventivo y médico de sus
                    mascotas.
                </p>

                <div class="feature-list">
                    <span>✓ Atención personalizada</span>
                    <span>✓ Seguimiento clínico</span>
                    <span>✓ Equipamiento y procedimientos seguros</span>
                </div>

            </article>

            <article
                class="info-card schedule-card"
                id="horario"
            >

                <span class="section-label">HORARIO</span>

                <h2>Horario de atención</h2>

                <div class="schedule-row">
                    <span>Lunes a viernes</span>
                    <strong>08h00 – 17h00</strong>
                </div>

                <div class="schedule-row">
                    <span>Sábados</span>
                    <strong>08h00 – 13h00</strong>
                </div>

                <div class="schedule-row">
                    <span>Domingos</span>
                    <strong>Emergencias</strong>
                </div>

                <a href="#contacto">
                    Solicitar información
                </a>

            </article>

        </div>
    </section>

    <!-- NOTICIAS -->
    <section class="updates-section" id="noticias">
        <div class="container">

            <div class="section-heading">

                <div>
                    <span class="section-label">INFORMACIÓN</span>

                    <h2>
                        Noticias, eventos y atención rápida
                    </h2>
                </div>

                <p>
                    Conoce nuestras campañas y servicios disponibles.
                </p>

            </div>

            <div class="updates-grid">

                <a
                    href="#contacto"
                    class="update-card update-card-link dark-blue"
                    aria-label="Solicitar información sobre vacunación"
                >
                    <span class="update-category">
                        💉 Noticias y novedades
                    </span>

                    <h3>Campaña de vacunación preventiva</h3>

                    <p>
                        Consulta el plan recomendado según la edad
                        y especie de tu mascota.
                    </p>

                    <span class="update-action">
                        Más información →
                    </span>
                </a>

                <a
                    href="#horario"
                    class="update-card update-card-link bright-blue"
                    aria-label="Consultar fecha de la jornada"
                >
                    <span class="update-category">
                        📅 Próximos eventos
                    </span>

                    <h3>Jornada de cuidado responsable</h3>

                    <p>
                        Charlas sobre alimentación, higiene,
                        vacunación y prevención.
                    </p>

                    <span class="update-action">
                        Consultar fecha →
                    </span>
                </a>

                <a
                    href="<?= e($enlaceSistema) ?>"
                    class="update-card update-card-link teal"
                    aria-label="Agendar una cita veterinaria"
                >
                    <span class="update-category">
                        ⚡ Atención rápida
                    </span>

                    <h3>Agenda una cita veterinaria</h3>

                    <p>
                        Accede al sistema y reserva el horario más
                        conveniente para tu mascota.
                    </p>

                    <span class="update-action">
                        Agendar cita →
                    </span>
                </a>

            </div>

        </div>
    </section>

    <!-- CONTACTO -->
    <section class="contact-section" id="contacto">
        <div class="container contact-grid">

            <div>

                <span class="section-label">CONTACTO</span>

                <h2>
                    Estamos para cuidar a tu mascota
                </h2>

                <p>
                    Visítanos en Nobol, Guayas, o comunícate mediante
                    nuestros canales de atención.
                </p>

            </div>

            <div class="contact-details">

                <span>📍 Nobol, Guayas – Ecuador</span>

                <a href="mailto:elcampo@veterinaria.ec">
                    ✉️ elcampo@veterinaria.ec
                </a>

                <a href="tel:+593990681268">
                    ☎️ 099 068 1268
                </a>

                <a
                    class="primary-action"
                    href="<?= e($enlaceSistema) ?>"
                >
                    🔐 <?= e($textoSistema) ?>
                </a>

            </div>

        </div>
    </section>

</main>

<footer class="public-footer">

    <div class="container footer-inner">

        <div>
            <strong>Clínica Veterinaria El Campo</strong>
            <span>Salud y bienestar para tus mascotas.</span>
        </div>

        <span>
            © <?= date('Y') ?> Todos los derechos reservados.
        </span>

    </div>

</footer>

<script src="<?= e(url('assets/js/publica.js')) ?>"></script>

</body>
</html>