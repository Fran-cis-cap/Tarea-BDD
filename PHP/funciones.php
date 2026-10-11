<?php
// ============================================================
// FUNCIONES COMUNES
// Bloques de atención (supuesto del README): 30 minutos, de 08:00 a 18:00
// (el último bloque parte a las 17:30).
// ============================================================
const BLOQUE_MINUTOS = 30;
const HORA_INICIO    = 8;
const HORA_FIN       = 18;

// Escapa texto para mostrarlo en HTML (evita XSS)
function h($texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

// Mensaje de una sola vez que sobrevive a una redirección
function flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash'] = ['tipo' => $tipo, 'msg' => $mensaje];
}

function mostrar_flash(): void
{
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        echo '<div class="alert alert-' . h($f['tipo']) . '">' . h($f['msg']) . '</div>';
    }
}

// Mensaje claro a partir de una excepción de MySQL.
// Los triggers y procedimientos lanzan mensajes en español (SQLSTATE 45000).
function msg_error(PDOException $e): string
{
    $info = $e->errorInfo ?? [];
    $codigo = (int) ($info[1] ?? 0);

    if ($codigo === 1062) {
        return 'Ya existe un registro igual (dato repetido).';
    }
    if ($codigo === 1451) {
        return 'No se puede eliminar porque tiene registros asociados (por ejemplo, citas).';
    }
    if ($codigo === 1452) {
        return 'El dato seleccionado no existe.';
    }
    if (($info[0] ?? '') === '45000') {
        return $info[2];
    }
    return 'No se pudo completar la operación: ' . ($info[2] ?? $e->getMessage());
}

// Requisitos de contraseña (README): mínimo 6 caracteres y al menos 2 números.
// Devuelve el mensaje de error, o null si la contraseña es válida.
function validar_contrasena(string $c): ?string
{
    if (strlen($c) < 6) {
        return 'La contraseña debe tener al menos 6 caracteres.';
    }
    if (strlen($c) > 72) {
        return 'La contraseña no puede superar los 72 caracteres.';
    }
    if (preg_match_all('/[0-9]/', $c) < 2) {
        return 'La contraseña debe contener al menos 2 números.';
    }
    return null;
}

// Formato de RUT: 7 u 8 dígitos, guion y dígito verificador (número o K), sin puntos.
function rut_formato_valido(string $rut): bool
{
    return (bool) preg_match('/^[0-9]{7,8}-[0-9kK]$/', $rut);
}

// Genera los bloques de un médico para una fecha (YYYY-MM-DD).
// Usa la function SQL bloque_disponible y descarta los bloques que ya pasaron.
function generar_bloques(PDO $pdo, int $id_medico, string $fecha): array
{
    $bloques = [];
    $ahora = new DateTime();
    $st = $pdo->prepare('SELECT bloque_disponible(?, ?)');

    for ($m = HORA_INICIO * 60; $m < HORA_FIN * 60; $m += BLOQUE_MINUTOS) {
        $hora = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
        $dt = $fecha . ' ' . $hora . ':00';

        $st->execute([$id_medico, $dt]);
        $libre = (bool) $st->fetchColumn();
        $st->closeCursor();

        $bloques[] = [
            'hora'       => $hora,
            'datetime'   => $dt,
            'disponible' => $libre && (new DateTime($dt) > $ahora),
        ];
    }
    return $bloques;
}

// Color de la etiqueta según el estado de la cita
function badge_estado(string $estado): string
{
    $colores = [
        'Reservada'  => 'warning text-dark',
        'Confirmada' => 'info text-dark',
        'Atendida'   => 'success',
        'No Asistió' => 'danger',
        'Cancelada'  => 'secondary',
    ];
    $clase = $colores[$estado] ?? 'light text-dark';
    return '<span class="badge bg-' . $clase . '">' . h($estado) . '</span>';
}

function fecha_hora_legible(string $fecha_hora): string
{
    return date('d-m-Y H:i', strtotime($fecha_hora));
}

// Valida una fecha YYYY-MM-DD
function fecha_valida(string $f): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $f);
    return $d && $d->format('Y-m-d') === $f;
}