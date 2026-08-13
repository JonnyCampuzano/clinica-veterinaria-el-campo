<?php
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

/*
|--------------------------------------------------------------------------
| SESIÓN Y ENLACES
|--------------------------------------------------------------------------
*/

$sesionActiva = !empty($_SESSION['usuario_id']);

$enlaceSistema = $sesionActiva
    ? url('panel.php')
    : url('login.php');

$textoSistema = $sesionActiva
    ? 'Ir al panel'
    : 'Iniciar sesión';

$enlaceReserva = url('reservar.php');
?>
<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="description"
        content="Clínica Veterinaria El Campo en Petrillo_Nobol, Guayas. Atención veterinaria, consultas, vacunación, prevención y citas para el bienestar de tus mascotas."
    >

    <title>
        Clínica Veterinaria El Campo | Nobol, Guayas
    </title>

    <link
        rel="stylesheet"
        href="<?= e(url('assets/css/publica.css')) ?>"
    >

</head>

<body>


<!-- =========================================================
     ENCABEZADO
========================================================= -->

<header class="public-header">

    <div class="container header-top">

        <!-- MARCA -->
        <a
            class="public-brand"
            href="#inicio"
            aria-label="Ir al inicio"
        >

            <span class="public-logo">
                🐾
            </span>

            <span class="public-brand-text">

                <strong>
                    CLÍNICA VETERINARIA
                    <em>EL CAMPO</em>
                </strong>

                <small>
                    Salud y bienestar para tus mascotas
                </small>

            </span>

        </a>


        <!-- INFORMACIÓN SUPERIOR -->
        <div class="header-right">

            <div class="contact-summary">

                <div class="contact-item">
                    <span class="contact-icon">📍</span>
                    <span>Petrillo_Nobol, Guayas – Ecuador</span>
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
                    <span>
                        Lun – Vie: 08h00 – 17h00
                    </span>
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


    <!-- NAVEGACIÓN -->
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

                <a
                    class="active"
                    href="#inicio"
                >
                    🏠 Inicio
                </a>

                <a href="#nosotros">
                    🐕 Nosotros
                </a>

                <a href="#servicios">
                    🩺 Servicios
                </a>

                <a href="#horario">
                    📅 Horario
                </a>

                <a href="#noticias">
                    📰 Noticias
                </a>

                <a href="#contacto">
                    📞 Contacto
                </a>

                <a
                    class="nav-reserva"
                    href="<?= e($enlaceReserva) ?>"
                >
                    📅 Agendar cita
                </a>

            </div>

        </div>

    </nav>

</header>



<main>


<!-- =========================================================
     PORTADA
========================================================= -->

<section
    class="hero"
    id="inicio"
>

    <div class="container hero-grid">


        <!-- TEXTO -->
        <div class="hero-copy">

            <span class="hero-eyebrow">
                🐾 CUIDAMOS A QUIENES MÁS QUIERES
            </span>

            <h1>

                Bienvenidos a la

                <br>

                <strong>
                    Clínica Veterinaria El Campo
                </strong>

            </h1>


            <p>

                Cuidamos la salud de tus mascotas con atención
                veterinaria profesional, cercana y responsable.

                Agenda una cita y permítenos acompañarte
                en cada etapa de su bienestar.

            </p>


            <div class="hero-actions">

                <a
                    class="primary-action"
                    href="<?= e($enlaceReserva) ?>"
                >
                    📅 Agendar una cita
                </a>


                <a
                    class="secondary-action"
                    href="#servicios"
                >
                    🩺 Conocer servicios
                </a>

            </div>


            <div class="hero-benefits">

                <span>
                    ✓ Atención personalizada
                </span>

                <span>
                    ✓ Profesionales capacitados
                </span>

                <span>
                    ✓ Seguimiento veterinario
                </span>

            </div>

        </div>



        <!-- IMAGEN -->
        <div class="hero-image-card">

            <img
                src="<?= e(url('assets/img/doctor.png')) ?>"
                alt="Veterinario atendiendo a una mascota"
            >

            <div class="hero-image-badge">

                <strong>
                    🩺 Atención responsable
                </strong>

                <span>
                    Profesionales comprometidos
                    con el bienestar de tu mascota
                </span>

            </div>

        </div>

    </div>

</section>



<!-- =========================================================
     SERVICIOS
========================================================= -->

<section
    class="service-strip"
    id="servicios"
>

    <div class="container">


        <div class="section-heading services-heading">

            <div>

                <span class="section-label">
                    NUESTROS SERVICIOS
                </span>

                <h2>
                    Atención veterinaria para cada etapa
                    de la vida de tu mascota
                </h2>

            </div>

            <p>
                Prevención, diagnóstico y seguimiento
                con atención profesional y responsable.
            </p>

        </div>



        <div class="service-grid">


            <!-- CONSULTA -->
            <article class="service-item">

                <span class="service-icon">
                    🩺
                </span>

                <h3>
                    Consulta Veterinaria
                </h3>

                <p>
                    Evaluación médica general para
                    cuidar la salud de tu mascota.
                </p>

                <small class="service-action">
                    Atención profesional
                </small>

            </article>



            <!-- VACUNACIÓN -->
            <article class="service-item">

                <span class="service-icon">
                    💉
                </span>

                <h3>
                    Vacunación
                </h3>

                <p>
                    Planes de vacunación de acuerdo
                    con la edad y especie de tu mascota.
                </p>

                <small class="service-action">
                    Prevención y protección
                </small>

            </article>



            <!-- CONTROL -->
            <article class="service-item">

                <span class="service-icon">
                    ❤️
                </span>

                <h3>
                    Control Preventivo
                </h3>

                <p>
                    Revisiones periódicas para prevenir
                    enfermedades y mantener su bienestar.
                </p>

                <small class="service-action">
                    Cuidado continuo
                </small>

            </article>



            <!-- TRATAMIENTOS -->
            <article class="service-item">

                <span class="service-icon">
                    💊
                </span>

                <h3>
                    Tratamientos
                </h3>

                <p>
                    Seguimiento médico y tratamientos
                    según las necesidades de cada paciente.
                </p>

                <small class="service-action">
                    Seguimiento veterinario
                </small>

            </article>



            <!-- MASCOTAS -->
            <article class="service-item">

                <span class="service-icon">
                    🐾
                </span>

                <h3>
                    Bienestar Animal
                </h3>

                <p>
                    Orientación para alimentación,
                    higiene y cuidado responsable.
                </p>

                <small class="service-action">
                    Cuidamos su bienestar
                </small>

            </article>



            <!-- CITA -->
            <a
                class="service-item service-item-link service-featured"
                href="<?= e($enlaceReserva) ?>"
                aria-label="Agendar una cita veterinaria"
            >

                <span class="service-icon">
                    📅
                </span>

                <h3>
                    Citas Veterinarias
                </h3>

                <p>
                    Reserva un horario para que tu mascota
                    reciba atención veterinaria.
                </p>

                <small class="service-action">
                    Agendar cita →
                </small>

            </a>

        </div>

    </div>

</section>



<!-- =========================================================
     NOSOTROS Y HORARIO
========================================================= -->

<section
    class="information-section"
    id="nosotros"
>

    <div class="container information-grid">


        <!-- NOSOTROS -->
        <article class="info-card about-card">

            <span class="section-label">
                NOSOTROS
            </span>

            <h2>
                Comprometidos con el bienestar animal
            </h2>

            <p>

                En Clínica Veterinaria El Campo brindamos
                atención cercana, segura y responsable.

                Nuestro objetivo es acompañar a cada familia
                en el cuidado preventivo y médico de sus mascotas.

            </p>


            <div class="feature-list">

                <span>
                    ✓ Atención personalizada
                </span>

                <span>
                    ✓ Seguimiento clínico
                </span>

                <span>
                    ✓ Cuidado preventivo
                </span>

                <span>
                    ✓ Atención profesional
                </span>

            </div>

        </article>



        <!-- HORARIO -->
        <article
            class="info-card schedule-card"
            id="horario"
        >

            <span class="section-label">
                HORARIO
            </span>

            <h2>
                Horario de atención
            </h2>


            <div class="schedule-row">

                <span>
                    Lunes a viernes
                </span>

                <strong>
                    08h00 – 17h00
                </strong>

            </div>


            <div class="schedule-row">

                <span>
                    Sábados
                </span>

                <strong>
                    08h00 – 13h00
                </strong>

            </div>


            <div class="schedule-row">

                <span>
                    Domingos
                </span>

                <strong>
                    Emergencias
                </strong>

            </div>


            <a
                class="schedule-action"
                href="<?= e($enlaceReserva) ?>"
            >
                📅 Solicitar una cita
            </a>

        </article>

    </div>

</section>



<!-- =========================================================
     INFORMACIÓN / NOTICIAS
========================================================= -->

<section
    class="updates-section"
    id="noticias"
>

    <div class="container">


        <div class="section-heading">

            <div>

                <span class="section-label">
                    INFORMACIÓN
                </span>

                <h2>
                    Consejos, campañas y atención veterinaria
                </h2>

            </div>


            <p>
                Información importante para mantener
                saludable y protegida a tu mascota.
            </p>

        </div>



        <div class="updates-grid">


            <!-- VACUNACIÓN -->
            <a
                href="#contacto"
                class="update-card update-card-link dark-blue"
                aria-label="Solicitar información sobre vacunación"
            >

                <span class="update-category">
                    💉 PREVENCIÓN
                </span>

                <h3>
                    Campaña de vacunación preventiva
                </h3>

                <p>
                    Consulta el plan de vacunación recomendado
                    según la edad y especie de tu mascota.
                </p>

                <span class="update-action">
                    Más información →
                </span>

            </a>



            <!-- CUIDADO RESPONSABLE -->
            <a
                href="#contacto"
                class="update-card update-card-link bright-blue"
                aria-label="Información sobre cuidado responsable"
            >

                <span class="update-category">
                    🐾 BIENESTAR
                </span>

                <h3>
                    Cuidado responsable de mascotas
                </h3>

                <p>
                    Conoce recomendaciones sobre alimentación,
                    higiene, vacunación y prevención.
                </p>

                <span class="update-action">
                    Conocer más →
                </span>

            </a>



            <!-- CITA -->
            <a
                href="<?= e($enlaceReserva) ?>"
                class="update-card update-card-link teal"
                aria-label="Agendar una cita veterinaria"
            >

                <span class="update-category">
                    ⚡ ATENCIÓN RÁPIDA
                </span>

                <h3>
                    Agenda una cita veterinaria
                </h3>

                <p>
                    Reserva el horario más conveniente
                    para que tu mascota reciba atención.
                </p>

                <span class="update-action">
                    Agendar cita →
                </span>

            </a>

        </div>

    </div>

</section>



<!-- =========================================================
     CONTACTO
========================================================= -->

<section
    class="contact-section"
    id="contacto"
>

    <div class="container contact-grid">


        <div>

            <span class="section-label">
                CONTACTO
            </span>

            <h2>
                Estamos para cuidar a tu mascota
            </h2>

            <p>

                Visítanos en Nobol, Guayas,
                o comunícate con nosotros mediante
                nuestros canales de atención.

            </p>

        </div>



        <div class="contact-details">

            <span>
                📍 Petrillo_Nobol, Guayas – Ecuador
            </span>


            <a href="mailto:elcampo@veterinaria.ec">
                ✉️ elcampo@veterinaria.ec
            </a>


            <a href="tel:+593990681268">
                ☎️ 099 068 1268
            </a>


            <span>
                🕐 Lun – Vie: 08h00 – 17h00
            </span>


            <div class="contact-actions">

                <a
                    class="primary-action"
                    href="<?= e($enlaceReserva) ?>"
                >
                    📅 Agendar cita
                </a>


                <a
                    class="secondary-action"
                    href="<?= e($enlaceSistema) ?>"
                >
                    👤 <?= e($textoSistema) ?>
                </a>

            </div>

        </div>

    </div>

</section>


</main>



<!-- =========================================================
     PIE DE PÁGINA
========================================================= -->

<footer class="public-footer">

    <div class="container footer-inner">


        <div>

            <strong>
                🐾 Clínica Veterinaria El Campo
            </strong>

            <span>
                Salud y bienestar para tus mascotas.
            </span>

        </div>


        <div class="footer-contact">

            <span>
                Petrillo_Nobol, Guayas – Ecuador
            </span>

            <span>
                © <?= date('Y') ?>
                Todos los derechos reservados.
            </span>

        </div>

    </div>

</footer>


<script
    src="<?= e(url('assets/js/publica.js')) ?>"
></script>

</body>
</html>