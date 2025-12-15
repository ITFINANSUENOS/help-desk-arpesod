<?php
class DateHelper
{

    /**
     * Calcula la fecha límite sumando solo días hábiles a una fecha de inicio.
     *
     * @param string $fecha_inicio_str La fecha de inicio (ej: '2025-07-24 10:00:00')
     * @param int $dias_habiles El número de días hábiles a sumar.
     * @return DateTime La fecha y hora límite.
     */
    public static function calcularFechaLimiteHabil($fecha_inicio_str, $dias_habiles)
    {
        // Lista de festivos en Colombia para 2025
        $festivos = [
            '2025-01-01',
            '2025-01-06',
            '2025-03-24',
            '2025-04-17',
            '2025-04-18',
            '2025-05-01',
            '2025-06-02',
            '2025-06-23',
            '2025-06-30',
            '2025-07-20',
            '2025-08-07',
            '2025-08-18',
            '2025-10-13',
            '2025-11-03',
            '2025-11-17',
            '2025-12-08',
            '2025-12-25'
        ];

        $fecha_actual = new DateTime($fecha_inicio_str);

        // --- NUEVA LÓGICA: Normalizar fecha de inicio ---
        // Si el ticket se abre en fin de semana o festivo, la cuenta empieza
        // desde el siguiente día hábil a la misma hora.
        while (true) {
            $dia_semana = $fecha_actual->format('N');
            $fecha_simple = $fecha_actual->format('Y-m-d');

            // Si es fin de semana (6-7) o festivo
            if ($dia_semana >= 6 || in_array($fecha_simple, $festivos)) {
                $fecha_actual->add(new DateInterval('P1D'));
            } else {
                // Es un día hábil, salimos del bucle
                break;
            }
        }
        // ------------------------------------------------

        $dias_sumados = 0;

        while ($dias_sumados < $dias_habiles) {
            $fecha_actual->add(new DateInterval('P1D')); // Sumamos un día
            $dia_semana = $fecha_actual->format('N'); // 1 (Lunes) a 7 (Domingo)
            $fecha_simple = $fecha_actual->format('Y-m-d');

            // Si es un día de semana (no sábado ni domingo) Y no es festivo
            if ($dia_semana < 6 && !in_array($fecha_simple, $festivos)) {
                $dias_sumados++;
            }
        }
        return $fecha_actual;
    }

    public static function calcular_horas_habiles($fecha_inicio_str, $fecha_fin_str, $horario_laboral = ['inicio' => '09:00:00', 'fin' => '18:00:00'])
    {
        $fecha_inicio = new DateTime($fecha_inicio_str);
        $fecha_fin = new DateTime($fecha_fin_str);

        if ($fecha_inicio >= $fecha_fin) {
            return 0;
        }

        $festivos = [
            '2025-01-01',
            '2025-01-06',
            '2025-03-24',
            '2025-04-17',
            '2025-04-18',
            '2025-05-01',
            '2025-06-02',
            '2025-06-23',
            '2025-06-30',
            '2025-07-20',
            '2025-08-07',
            '2025-08-18',
            '2025-10-13',
            '2025-11-03',
            '2025-11-17',
            '2025-12-08',
            '2025-12-25'
        ];

        $horas_habiles_totales = 0;
        $fecha_actual = clone $fecha_inicio;

        while ($fecha_actual < $fecha_fin) {
            $dia_semana = $fecha_actual->format('N');
            $fecha_simple = $fecha_actual->format('Y-m-d');

            if ($dia_semana < 6 && !in_array($fecha_simple, $festivos)) {
                $inicio_jornada = new DateTime($fecha_actual->format('Y-m-d') . ' ' . $horario_laboral['inicio']);
                $fin_jornada = new DateTime($fecha_actual->format('Y-m-d') . ' ' . $horario_laboral['fin']);

                $inicio_calculo = max($fecha_actual, $inicio_jornada);
                $fin_calculo = min($fecha_fin, $fin_jornada);

                if ($inicio_calculo < $fin_calculo) {
                    $diferencia_segundos = $fin_calculo->getTimestamp() - $inicio_calculo->getTimestamp();
                    $horas_habiles_totales += $diferencia_segundos / 3600;
                }
            }
            // Avanzamos al día siguiente
            $fecha_actual->modify('+1 day')->setTime(0, 0, 0);
        }

        return $horas_habiles_totales;
    }
}
