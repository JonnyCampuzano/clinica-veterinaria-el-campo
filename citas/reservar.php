<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| ZONA HORARIA
|--------------------------------------------------------------------------
*/

date_default_timezone_set('America/Guayaquil');


/*
|--------------------------------------------------------------------------
| SESIÓN
|--------------------------------------------------------------------------
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| CONEXIÓN A LA BASE DE DATOS
|--------------------------------------------------------------------------
*/

require_once dirname(__DIR__) . '/config/conexion.php';


/*
|--------------------------------------------------------------------------
| COMPROBAR CONEXIÓN
|--------------------------------------------------------------------------
*/

if (!isset($pdo) || !($pdo instanceof PDO)) {
    exit('No fue posible conectar con la base de datos.');
}


/*
|--------------------------------------------------------------------------
| COMPROBAR QUE EL USUARIO INICIÓ SESIÓN
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['usuario_id']) ||
    !isset($_SESSION['rol'])
) {

    header('Location: ../login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| NORMALIZAR ROL
|--------------------------------------------------------------------------
*/

$rol = strtolower(
    trim(
        (string) $_SESSION['rol']
    )
);

$rol = strtr(
    $rol,
    [
        'á' => 'a',
        'é' => 'e',
        'í' => 'i',
        'ó' => 'o',
        'ú' => 'u',
        'ñ' => 'n'
    ]
);


/*
|--------------------------------------------------------------------------
| SOLO CLIENTE
|--------------------------------------------------------------------------
*/

if ($rol !== 'cliente') {

    header('Location: ../panel.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| DATOS DEL USUARIO
|--------------------------------------------------------------------------
*/

$usuarioId = (int) (
    $_SESSION['usuario_id']
    ?? $_SESSION['id_usuario']
    ?? 0
);

$nombreUsuario = trim(
    (string) (
        $_SESSION['nombre']
        ?? $_SESSION['usuario']
        ?? 'Cliente'
    )
);

$correoUsuario = trim(
    (string) (
        $_SESSION['correo']
        ?? ''
    )
);


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$error = '';
$exito = '';

$nombreMascota = '';
$especie = '';
$fecha = '';
$hora = '';
$motivo = '';


/*
|--------------------------------------------------------------------------
| CREAR TABLA DE RESERVAS SI NO EXISTE
|--------------------------------------------------------------------------
*/

try {

    $sqlTabla = "
        CREATE TABLE IF NOT EXISTS reservas_citas
        (
            id INT AUTO_INCREMENT PRIMARY KEY,

            usuario_id INT NOT NULL,

            nombre_cliente VARCHAR(150) NOT NULL,

            correo_cliente VARCHAR(150) NULL,

            nombre_mascota VARCHAR(100) NOT NULL,

            especie VARCHAR(50) NOT NULL,

            fecha DATE NOT NULL,

            hora TIME NOT NULL,

            motivo VARCHAR(500) NOT NULL,

            estado VARCHAR(30) NOT NULL DEFAULT 'Pendiente',

            fecha_registro DATETIME NOT NULL
                DEFAULT CURRENT_TIMESTAMP
        )
        ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
    ";

    $pdo->exec($sqlTabla);

} catch (PDOException $e) {

    error_log(
        'Error creando reservas_citas: ' .
        $e->getMessage()
    );

    $error =
        'No fue posible preparar el módulo de reservas.';
}


/*
|--------------------------------------------------------------------------
| PROCESAR RESERVA
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    $error === ''
) {

    /*
    |--------------------------------------------------------------------------
    | RECIBIR DATOS
    |--------------------------------------------------------------------------
    */

    $nombreMascota = trim(
        (string) (
            $_POST['nombre_mascota']
            ?? ''
        )
    );

    $especie = trim(
        (string) (
            $_POST['especie']
            ?? ''
        )
    );

    $fecha = trim(
        (string) (
            $_POST['fecha']
            ?? ''
        )
    );

    $hora = trim(
        (string) (
            $_POST['hora']
            ?? ''
        )
    );

    $motivo = trim(
        (string) (
            $_POST['motivo']
            ?? ''
        )
    );


    /*
    |--------------------------------------------------------------------------
    | VALIDAR CAMPOS
    |--------------------------------------------------------------------------
    */

    if (
        $nombreMascota === '' ||
        $especie === '' ||
        $fecha === '' ||
        $hora === '' ||
        $motivo === ''
    ) {

        $error =
            'Por favor completa todos los campos.';

    }


    /*
    |--------------------------------------------------------------------------
    | VALIDAR ESPECIE
    |--------------------------------------------------------------------------
    */

    $especiesPermitidas = [
        'Perro',
        'Gato',
        'Ave',
        'Conejo',
        'Otro'
    ];


    if (
        $error === '' &&
        !in_array(
            $especie,
            $especiesPermitidas,
            true
        )
    ) {

        $error =
            'La especie seleccionada no es válida.';
    }


    /*
    |--------------------------------------------------------------------------
    | VALIDAR FECHA
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        $fechaObjeto =
            DateTime::createFromFormat(
                'Y-m-d',
                $fecha
            );

        $fechaValida =
            $fechaObjeto !== false &&
            $fechaObjeto->format('Y-m-d') === $fecha;


        if (!$fechaValida) {

            $error =
                'La fecha seleccionada no es válida.';

        } else {

            $hoy = date('Y-m-d');


            if ($fecha < $hoy) {

                $error =
                    'No puedes reservar una cita en una fecha anterior.';
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | NO PERMITIR SÁBADOS NI DOMINGOS
    |--------------------------------------------------------------------------
    */

    if (
        $error === '' &&
        isset($fechaObjeto) &&
        $fechaObjeto instanceof DateTime
    ) {

        $numeroDia =
            (int) $fechaObjeto->format('N');


        if ($numeroDia >= 6) {

            $error =
                'La clínica atiende de lunes a viernes. '
                . 'Selecciona otro día.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | VALIDAR HORA
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        $horaSinSegundos =
            substr(
                $hora,
                0,
                5
            );


        if (
            $horaSinSegundos < '08:00' ||
            $horaSinSegundos > '17:00'
        ) {

            $error =
                'El horario de atención es de 08:00 a 17:00.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | VALIDAR QUE LA FECHA/HORA NO HAYA PASADO
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        try {

            $fechaHoraReserva =
                new DateTime(
                    $fecha . ' ' . $hora
                );

            $ahora =
                new DateTime();


            if ($fechaHoraReserva <= $ahora) {

                $error =
                    'Debes seleccionar una fecha y hora futura.';
            }

        } catch (Throwable $e) {

            $error =
                'La fecha u hora seleccionada no es válida.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | COMPROBAR HORARIO OCUPADO
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        try {

            $sqlHorario = "
                SELECT id
                FROM reservas_citas

                WHERE fecha = :fecha

                AND TIME_FORMAT(
                    hora,
                    '%H:%i'
                ) = :hora

                AND estado <> 'Cancelada'

                LIMIT 1
            ";


            $stmtHorario =
                $pdo->prepare(
                    $sqlHorario
                );


            $stmtHorario->execute([

                ':fecha' =>
                    $fecha,

                ':hora' =>
                    substr(
                        $hora,
                        0,
                        5
                    )

            ]);


            $horarioOcupado =
                $stmtHorario->fetch(
                    PDO::FETCH_ASSOC
                );


            if ($horarioOcupado) {

                $error =
                    'Ese horario ya fue reservado. '
                    . 'Por favor selecciona otra hora.';
            }

        } catch (PDOException $e) {

            error_log(
                'Error verificando horario: ' .
                $e->getMessage()
            );

            $error =
                'No fue posible comprobar el horario.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | GUARDAR RESERVA
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        try {

            $sqlGuardar = "
                INSERT INTO reservas_citas
                (
                    usuario_id,
                    nombre_cliente,
                    correo_cliente,
                    nombre_mascota,
                    especie,
                    fecha,
                    hora,
                    motivo,
                    estado
                )
                VALUES
                (
                    :usuario_id,
                    :nombre_cliente,
                    :correo_cliente,
                    :nombre_mascota,
                    :especie,
                    :fecha,
                    :hora,
                    :motivo,
                    'Pendiente'
                )
            ";


            $stmtGuardar =
                $pdo->prepare(
                    $sqlGuardar
                );


            $stmtGuardar->execute([

                ':usuario_id' =>
                    $usuarioId,

                ':nombre_cliente' =>
                    $nombreUsuario,

                ':correo_cliente' =>
                    $correoUsuario,

                ':nombre_mascota' =>
                    $nombreMascota,

                ':especie' =>
                    $especie,

                ':fecha' =>
                    $fecha,

                ':hora' =>
                    $hora,

                ':motivo' =>
                    $motivo

            ]);


            /*
            |--------------------------------------------------------------------------
            | MENSAJE DE ÉXITO
            |--------------------------------------------------------------------------
            */

            $exito =
                'Tu cita fue registrada correctamente. '
                . 'La clínica deberá confirmarla.';


            /*
            |--------------------------------------------------------------------------
            | LIMPIAR CAMPOS
            |--------------------------------------------------------------------------
            */

            $nombreMascota = '';
            $especie = '';
            $fecha = '';
            $hora = '';
            $motivo = '';


        } catch (PDOException $e) {

            error_log(
                'Error guardando reserva: ' .
                $e->getMessage()
            );


            $error =
                'No fue posible registrar la cita. '
                . 'Inténtalo nuevamente.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| FECHA MÍNIMA
|--------------------------------------------------------------------------
*/

$fechaMinima =
    date('Y-m-d');

?>

<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Reservar cita | Clínica Veterinaria El Campo
</title>


<style>

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }


    body {

        min-height: 100vh;

        font-family:
            "Segoe UI",
            Arial,
            Helvetica,
            sans-serif;

        background:
            linear-gradient(
                135deg,
                #f1f7ff,
                #f8fbff
            );

        color: #1e293b;
    }


    /*
    |--------------------------------------------------------------------------
    | HEADER
    |--------------------------------------------------------------------------
    */

    .header {

        min-height: 84px;

        background: #ffffff;

        border-bottom:
            1px solid #dce6f3;

        padding:
            15px 5%;

        display: flex;

        justify-content: space-between;

        align-items: center;

        gap: 20px;

        box-shadow:
            0 3px 15px
            rgba(34, 75, 130, 0.07);
    }


    .marca {

        display: flex;

        align-items: center;

        gap: 14px;
    }


    .logo {

        width: 52px;

        height: 52px;

        border-radius: 14px;

        background: #edf8f1;

        display: flex;

        justify-content: center;

        align-items: center;

        font-size: 27px;
    }


    .marca-texto h2 {

        color: #174c9b;

        font-size: 21px;

        margin-bottom: 3px;
    }


    .marca-texto p {

        color: #718096;

        font-size: 13px;
    }


    .usuario {

        display: flex;

        align-items: center;

        gap: 15px;
    }


    .usuario-datos {

        text-align: right;
    }


    .usuario-datos strong {

        display: block;

        color: #1e3a5f;

        font-size: 14px;
    }


    .usuario-datos span {

        color: #718096;

        font-size: 12px;
    }


    .btn-salir {

        display: inline-flex;

        justify-content: center;

        align-items: center;

        min-height: 43px;

        padding:
            0 18px;

        border-radius: 9px;

        background: #dc3545;

        color: #ffffff;

        text-decoration: none;

        font-weight: 700;

        font-size: 14px;

        transition:
            background .2s,
            transform .2s;
    }


    .btn-salir:hover {

        background: #bd2635;

        transform:
            translateY(-1px);
    }


    /*
    |--------------------------------------------------------------------------
    | CONTENEDOR
    |--------------------------------------------------------------------------
    */

    .contenedor {

        width: 92%;

        max-width: 1000px;

        margin:
            45px auto;

        padding-bottom: 50px;
    }


    /*
    |--------------------------------------------------------------------------
    | BIENVENIDA
    |--------------------------------------------------------------------------
    */

    .bienvenida {

        margin-bottom: 25px;
    }


    .bienvenida h1 {

        color: #163d72;

        font-size: 31px;

        margin-bottom: 8px;
    }


    .bienvenida p {

        color: #68788f;

        font-size: 16px;

        line-height: 1.6;
    }


    /*
    |--------------------------------------------------------------------------
    | MENSAJES
    |--------------------------------------------------------------------------
    */

    .alerta {

        padding:
            16px 18px;

        margin-bottom: 22px;

        border-radius: 11px;

        font-size: 15px;

        line-height: 1.5;
    }


    .alerta-error {

        background: #fff0f0;

        border:
            1px solid #f3b2b2;

        color: #a82323;
    }


    .alerta-exito {

        background: #eaf8ef;

        border:
            1px solid #a5d7b7;

        color: #176b37;
    }


    /*
    |--------------------------------------------------------------------------
    | CARD
    |--------------------------------------------------------------------------
    */

    .card {

        background: #ffffff;

        border:
            1px solid #d7e3f3;

        border-radius: 20px;

        padding:
            35px 38px;

        box-shadow:
            0 18px 45px
            rgba(38, 74, 120, 0.10);
    }


    .card-titulo {

        display: flex;

        align-items: center;

        gap: 12px;

        margin-bottom: 8px;

        color: #174c9b;

        font-size: 24px;
    }


    .card-descripcion {

        margin-bottom: 30px;

        color: #718096;

        font-size: 15px;

        line-height: 1.5;
    }


    /*
    |--------------------------------------------------------------------------
    | GRID
    |--------------------------------------------------------------------------
    */

    .grid {

        display: grid;

        grid-template-columns:
            repeat(2, minmax(0, 1fr));

        gap:
            22px 24px;
    }


    .grupo {

        min-width: 0;
    }


    .grupo-completo {

        grid-column:
            1 / -1;
    }


    /*
    |--------------------------------------------------------------------------
    | CAMPOS
    |--------------------------------------------------------------------------
    */

    label {

        display: block;

        margin-bottom: 8px;

        color: #26384f;

        font-size: 15px;

        font-weight: 700;
    }


    .campo {

        width: 100%;

        min-height: 54px;

        padding:
            0 15px;

        border:
            1px solid #bad0ee;

        border-radius: 10px;

        background: #ffffff;

        color: #1f2937;

        font-family: inherit;

        font-size: 15px;

        outline: none;

        transition:
            border-color .2s,
            box-shadow .2s;
    }


    .campo:focus {

        border-color: #2f7d4a;

        box-shadow:
            0 0 0 3px
            rgba(47, 125, 74, 0.11);
    }


    select.campo {

        cursor: pointer;
    }


    textarea.campo {

        min-height: 125px;

        padding:
            14px 15px;

        resize: vertical;
    }


    /*
    |--------------------------------------------------------------------------
    | INFORMACIÓN
    |--------------------------------------------------------------------------
    */

    .info {

        margin-top: 25px;

        padding:
            15px 17px;

        background: #f0f7ff;

        border:
            1px solid #cae0fa;

        border-radius: 10px;

        color: #31577d;

        font-size: 14px;

        line-height: 1.6;
    }


    /*
    |--------------------------------------------------------------------------
    | BOTÓN
    |--------------------------------------------------------------------------
    */

    .acciones {

        margin-top: 28px;
    }


    .btn-reservar {

        width: 100%;

        min-height: 58px;

        border: none;

        border-radius: 11px;

        background: #2f7d4a;

        color: #ffffff;

        font-family: inherit;

        font-size: 17px;

        font-weight: 800;

        cursor: pointer;

        transition:
            background .2s,
            transform .2s,
            box-shadow .2s;
    }


    .btn-reservar:hover {

        background: #27693e;

        transform:
            translateY(-1px);

        box-shadow:
            0 7px 18px
            rgba(47, 125, 74, 0.20);
    }


    /*
    |--------------------------------------------------------------------------
    | PIE
    |--------------------------------------------------------------------------
    */

    .pie {

        margin-top: 20px;

        text-align: center;

        color: #758399;

        font-size: 13px;
    }


    /*
    |--------------------------------------------------------------------------
    | RESPONSIVE
    |--------------------------------------------------------------------------
    */

    @media (max-width: 700px) {

        .header {

            flex-direction: column;

            align-items: flex-start;
        }


        .usuario {

            width: 100%;

            justify-content: space-between;
        }


        .usuario-datos {

            text-align: left;
        }


        .contenedor {

            margin-top: 30px;
        }


        .bienvenida h1 {

            font-size: 25px;
        }


        .card {

            padding:
                28px 22px;
        }


        .grid {

            grid-template-columns: 1fr;
        }


        .grupo-completo {

            grid-column: auto;
        }
    }

</style>

</head>


<body>


<!-- ================================================================
     HEADER
================================================================ -->

<header class="header">


    <div class="marca">

        <div class="logo">
            🐾
        </div>


        <div class="marca-texto">

            <h2>
                Clínica Veterinaria El Campo
            </h2>

            <p>
                Reserva de citas
            </p>

        </div>

    </div>


    <div class="usuario">


        <div class="usuario-datos">

            <strong>
                <?= htmlspecialchars(
                    $nombreUsuario,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </strong>

            <span>
                Cliente
            </span>

        </div>


        <a
            href="../logout.php"
            class="btn-salir"
        >
            Cerrar sesión
        </a>


    </div>


</header>


<!-- ================================================================
     CONTENIDO
================================================================ -->

<main class="contenedor">


    <!-- BIENVENIDA -->

    <section class="bienvenida">

        <h1>
            Hola,
            <?= htmlspecialchars(
                $nombreUsuario,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
            👋
        </h1>


        <p>
            Solicita una cita para tu mascota.
            Completa la información y la clínica
            podrá revisar y confirmar tu solicitud.
        </p>

    </section>


    <!-- ERROR -->

    <?php if ($error !== ''): ?>

        <div class="
            alerta
            alerta-error
        ">

            ⚠️

            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>


    <!-- ÉXITO -->

    <?php if ($exito !== ''): ?>

        <div class="
            alerta
            alerta-exito
        ">

            ✅

            <?= htmlspecialchars(
                $exito,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>


    <!-- CARD -->

    <section class="card">


        <h2 class="card-titulo">
            📅 Reservar una cita
        </h2>


        <p class="card-descripcion">
            Ingresa los datos de tu mascota y
            selecciona la fecha y hora que deseas.
        </p>


        <form
            method="POST"
            action=""
        >


            <div class="grid">


                <!-- MASCOTA -->

                <div class="grupo">

                    <label for="nombre_mascota">
                        Nombre de la mascota
                    </label>


                    <input
                        type="text"
                        id="nombre_mascota"
                        name="nombre_mascota"
                        class="campo"
                        placeholder="Ejemplo: Max"
                        value="<?= htmlspecialchars(
                            $nombreMascota,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        maxlength="100"
                        required
                    >

                </div>


                <!-- ESPECIE -->

                <div class="grupo">

                    <label for="especie">
                        Especie
                    </label>


                    <select
                        id="especie"
                        name="especie"
                        class="campo"
                        required
                    >


                        <option value="">
                            Seleccione una especie
                        </option>


                        <option
                            value="Perro"
                            <?= $especie === 'Perro'
                                ? 'selected'
                                : '' ?>
                        >
                            🐶 Perro
                        </option>


                        <option
                            value="Gato"
                            <?= $especie === 'Gato'
                                ? 'selected'
                                : '' ?>
                        >
                            🐱 Gato
                        </option>


                        <option
                            value="Ave"
                            <?= $especie === 'Ave'
                                ? 'selected'
                                : '' ?>
                        >
                            🐦 Ave
                        </option>


                        <option
                            value="Conejo"
                            <?= $especie === 'Conejo'
                                ? 'selected'
                                : '' ?>
                        >
                            🐰 Conejo
                        </option>


                        <option
                            value="Otro"
                            <?= $especie === 'Otro'
                                ? 'selected'
                                : '' ?>
                        >
                            🐾 Otro
                        </option>


                    </select>

                </div>


                <!-- FECHA -->

                <div class="grupo">

                    <label for="fecha">
                        Fecha de la cita
                    </label>


                    <input
                        type="date"
                        id="fecha"
                        name="fecha"
                        class="campo"
                        min="<?= htmlspecialchars(
                            $fechaMinima,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        value="<?= htmlspecialchars(
                            $fecha,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        required
                    >

                </div>


                <!-- HORA -->

                <div class="grupo">

                    <label for="hora">
                        Hora
                    </label>


                    <input
                        type="time"
                        id="hora"
                        name="hora"
                        class="campo"
                        min="08:00"
                        max="17:00"
                        step="1800"
                        value="<?= htmlspecialchars(
                            $hora,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        required
                    >

                </div>


                <!-- MOTIVO -->

                <div class="
                    grupo
                    grupo-completo
                ">

                    <label for="motivo">
                        Motivo de la cita
                    </label>


                    <textarea
                        id="motivo"
                        name="motivo"
                        class="campo"
                        maxlength="500"
                        placeholder="Ejemplo: consulta general, vacunación, control, revisión..."
                        required
                    ><?= htmlspecialchars(
                        $motivo,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?></textarea>

                </div>


            </div>


            <!-- INFORMACIÓN -->

            <div class="info">

                ℹ️ <strong>Importante:</strong>

                la cita será registrada con estado

                <strong>Pendiente</strong>.

                La Clínica Veterinaria El Campo
                deberá confirmar posteriormente la cita.

                <br>

                Horario de atención:
                <strong>
                    lunes a viernes de 08:00 a 17:00.
                </strong>

            </div>


            <!-- BOTÓN -->

            <div class="acciones">

                <button
                    type="submit"
                    class="btn-reservar"
                >
                    📅 Solicitar cita
                </button>

            </div>


        </form>


    </section>


    <div class="pie">

        🐾 Clínica Veterinaria El Campo ·
        Nobol, Guayas – Ecuador

    </div>


</main>


</body>

</html>