<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Mismos datos de prueba que schema.sql, para quien prefiera `migrate --seed`.
 */
class ApplicationSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['Alta de proveedor de mensajería', 'Registrar al nuevo proveedor de envíos y validar documentación fiscal.', 'created', '2026-10-28 09:15:00'],
            ['Acceso VPN para equipo de soporte', 'Crear perfiles VPN para los 4 agentes que entran el lunes.', 'created', '2026-11-03 14:02:00'],
            ['Compra de licencias de diseño', 'Renovar 6 licencias anuales antes del vencimiento del día 15.', 'in_review', '2026-10-25 11:30:00'],
            ['Migración del CRM a la nube', 'Mover el CRM on-premise al proveedor cloud elegido en el comité.', 'in_progress', '2026-10-10 08:00:00'],
            ['Rediseño del onboarding', 'Actualizar el flujo de bienvenida de clientes con el nuevo branding.', 'in_progress', '2026-10-18 16:45:00'],
            ['Auditoría de permisos en ERP', 'Revisar roles con privilegios elevados. Bloqueado esperando a seguridad.', 'paused', '2026-10-05 10:20:00'],
            ['Integración con pasarela de pagos', 'Pendiente de credenciales de producción del proveedor.', 'paused', '2026-10-12 13:10:00'],
            ['Reporte mensual de ventas', 'Automatizar el envío del reporte a gerencia el primer día hábil.', 'done', '2026-09-30 09:00:00'],
        ];

        foreach ($rows as [$title, $description, $status, $createdAt]) {
            DB::table('applications')->insert([
                'title' => $title,
                'description' => $description,
                'status' => $status,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }
}
