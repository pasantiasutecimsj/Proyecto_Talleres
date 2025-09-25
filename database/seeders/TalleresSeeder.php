<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Carbon\Carbon;

class TalleresSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // 1) Pool de CIs desde tu lista de personas (solo numéricos de 8 dígitos)
            //    Si querés, pegá acá la lista completa; dejé ~85 para cubrir todas las inscripciones.
            $cis = array_unique(array_map('strval', [
                15862522,16050065,16331166,17144798,17750311,
                18339007,18389682,19478406,19590725,25054331,
                25115959,25117781,25145340,25256242,25256270,
                25366134,25366184,25390606,25406871,25430666,
                25496743,25561354,25636078,25644948,25730662,
                25788277,26014192,26015431,26035794,26173295,
                26289727,26438302,26470932,26480082,26500773,
                26512570,26732382,26855398,26970465,27083388,
                27084586,27113345,27126920,27168572,27230686,
                27262360,27302916,27336347,27397957,27430527,
                27451000,27451157,27500332,27769891,27874119,
                27933911,27978587,28180947,28355469,28365622,
                28431817,28449395,28522155,28549892,28569757,
                28858057,28974899,29081467,29135608,29205479,
                29205485,29311406,29419870,29446651,29471791,
                29567493,29614119,29635078,29678022,29768526,
                29906706,29923960,29983372,30024517,30098564,
                30100705,30167820,30336586,30358148,30411930,
                30414136,30616865,30762640,30872996,30975514,
                30999873,31023962,31054783,31182601,31214688,
                31235199,31304386,31403215,31576583,31600665,
                31715949,31771026,32020701,32045105,32052441,
                32150069,32219601,32267250,
            ]));

            $cis = array_values(array_filter($cis, fn($ci) => preg_match('/^\d{8}$/', $ci)));

            // Split roles
            $cisDocentes      = array_slice($cis, 0, 5);
            $cisOrganizadores = array_slice($cis, 5, 3);
            $cisAsistentes    = array_slice($cis, 8);

            // 2) Insert base por CI (PK en cada tabla)
            DB::table('docentes')->insertOrIgnore(array_map(fn($ci) => ['ci' => $ci], $cisDocentes));
            DB::table('organizadores')->insertOrIgnore(array_map(fn($ci) => ['ci' => $ci], $cisOrganizadores));
            DB::table('asistentes')->insertOrIgnore(array_map(fn($ci) => ['ci' => $ci], $cisAsistentes));

            // 3) Talleres (3)
            $now = Carbon::now('America/Montevideo');
            $tallerIds = [];

            $talleres = [
                ['nombre' => 'Yoga para Todos',   'descripcion' => 'Movilidad y respiración', 'id_ciudad' => 1, 'calle' => 'Av. Artigas', 'numero' => '1234'],
                ['nombre' => 'Linux Inicial',     'descripcion' => 'Comandos y shell',        'id_ciudad' => 2, 'calle' => '18 de Julio', 'numero' => '450'],
                ['nombre' => 'Cocina Saludable',  'descripcion' => 'Menú semanal',             'id_ciudad' => 3, 'calle' => 'San Martín',  'numero' => '99'],
            ];

            foreach ($talleres as $i => $t) {
                $id = DB::table('talleres')->insertGetId([
                    'nombre'      => $t['nombre'],
                    'descripcion' => $t['descripcion'],
                    'id_ciudad'   => $t['id_ciudad'],
                    'calle'       => $t['calle'],
                    'numero'      => $t['numero'],
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
                $tallerIds[] = $id;

                // relación talleres_organizadores (uno por taller)
                $orgCi = $cisOrganizadores[$i % count($cisOrganizadores)];
                DB::table('talleres_organizadores')->insertOrIgnore([
                    'taller_id'      => $id,
                    'ci_organizador' => $orgCi,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
            }

            // 4) Clases (2 por taller) + clases extra para docente 15862522
            $claseIds = [];
            $docIdx = 0;

            foreach ($tallerIds as $ti => $tallerId) {
                for ($k = 0; $k < 2; $k++) {
                    $fecha = $now->copy()->next('wednesday')->addWeeks($ti * 2 + $k)->setTime(18, 0, 0);
                    $ciDoc = $cisDocentes[$docIdx % count($cisDocentes)];
                    $docIdx++;

                    $claseId = DB::table('clases')->insertGetId([
                        'fecha_hora'         => $fecha,
                        'asistentes_maximos' => 20,
                        'ci_docente'         => $ciDoc,
                        'taller_id'          => $tallerId,
                        'created_at'         => $now,
                        'updated_at'         => $now,
                    ]);
                    $claseIds[] = $claseId;
                }
            }

            // --- Clases extra para docente 15862522 ---
            $doc15862522 = '15862522';
            $fechasExtra = [
                $now->copy()->subDays(3)->setTime(10, 0),   // pasado
                $now->copy()->subDays(1)->setTime(14, 0),   // pasado
                $now->copy()->setTime(9, 0),                // hoy
                $now->copy()->setTime(11, 0),               // hoy (otra clase hoy)
                $now->copy()->addDays(1)->setTime(16, 0),   // futuro
                $now->copy()->addDays(2)->setTime(18, 0),   // futuro
                $now->copy()->addDays(4)->setTime(10, 0),   // futuro
                $now->copy()->addDays(4)->setTime(14, 0),   // futuro (misma fecha)
                $now->copy()->addDays(4)->setTime(11, 0),   // futuro
                $now->copy()->addDays(4)->setTime(12, 0),   // futuro (misma fecha)                $now->copy()->addDays(4)->setTime(10, 0),   // futuro
                $now->copy()->addDays(4)->setTime(13, 0),   // futuro (misma fecha)
                $now->copy()->addDays(7)->setTime(12, 0),   // futuro
            ];

            foreach ($fechasExtra as $f) {
                DB::table('clases')->insert([
                    'fecha_hora'         => $f,
                    'asistentes_maximos' => 20,
                    'ci_docente'         => $doc15862522,
                    'taller_id'          => $tallerIds[array_rand($tallerIds)],
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ]);
            }

            // 5) Inscripciones a clase (10 por clase, únicas por clase)
            $pickFrom = $cisAsistentes;
            $cursor = 0;

            foreach ($claseIds as $claseId) {
                $tomar = 10;
                $elegidos = [];
                for ($i = 0; $i < $tomar && count($pickFrom) > 0; $i++) {
                    $ci = $pickFrom[$cursor % count($pickFrom)];
                    $cursor++;
                    if (in_array($ci, $elegidos, true)) {
                        $i--;
                        continue;
                    }
                    $elegidos[] = $ci;
                }

                $rows = array_map(fn($ci) => [
                    'clase_id'     => $claseId,
                    'ci_asistente' => $ci,          // FK -> asistentes.ci
                    'asistio'      => (bool) random_int(0, 1),
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ], $elegidos);

                DB::table('clase_asistentes')->insertOrIgnore($rows);
            }
        });

        $this->command?->info('TalleresSeeder: datos de ejemplo creados ✅');
    }
}
