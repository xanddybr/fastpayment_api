<?php

use Phinx\Seed\AbstractSeed;

class InitialDataSeeder extends AbstractSeed
{
    public function run(): void
    {
        // 1. Tipos de Pessoa
        $typesPerson = $this->table('types_person');
        $typesPerson->insert([
            ['id' => 1, 'name' => 'Admin'],
            ['id' => 2, 'name' => 'Cliente']
        ])->saveData();

        // 2. Unidades
        $units = $this->table('units');
        $units->insert([
            ['id' => 1, 'name' => 'Sede Principal'],
            ['id' => 2, 'name' => 'Unidade Digital']
        ])->saveData();

        // 3. Tipos de Eventos
        $eventTypes = $this->table('event_types');
        $eventTypes->insert([
            ['id' => 1, 'name' => 'Curso'],
            ['id' => 2, 'name' => 'Atendimento Individual'],
            ['id' => 3, 'name' => 'Workshop']
        ])->saveData();

        // 4. Eventos
        $events = $this->table('events');
        $events->insert([
            ['id' => 1, 'name' => 'Reiki Módulo 1', 'price' => 250.00, 'slug' => 'reiki-1'],
            ['id' => 2, 'name' => 'Yoga para Iniciantes', 'price' => 80.00, 'slug' => 'yoga'],
            ['id' => 3, 'name' => 'Meditação Guiada', 'price' => 0.00, 'slug' => 'meditacao']
        ])->saveData();

        // 5. Pessoas (Admin e Cliente)
        $persons = $this->table('persons');
        $persons->insert([
            [
                'id' => 1,
                'full_name' => 'Administrador do Sistema',
                'email' => 'admin@teste.com',
                'password' => password_hash('admin123', PASSWORD_DEFAULT),
                'status' => 'active',
                'type_person_id' => 1
            ],
            [
                'id' => 2,
                'full_name' => 'João Cliente Teste',
                'email' => 'cliente@teste.com',
                'password' => password_hash('cliente123', PASSWORD_DEFAULT),
                'status' => 'active',
                'type_person_id' => 2
            ]
        ])->saveData();

        // 6. Detalhes do Cliente
        $details = $this->table('person_details');
        $details->insert([
            [
                'person_id' => 2,
                'activity_professional' => 'Desenvolvedor',
                'phone' => '11999999999',
                'street' => 'Rua de Teste',
                'number' => 123,
                'neighborhood' => 'Centro',
                'city' => 'São Paulo',
                'first_time' => 1
            ]
        ])->saveData();

        // 7. Agendamentos (Schedules)
        $schedules = $this->table('schedules');
        $schedules->insert([
            [
                'id' => 1,
                'event_id' => 1,
                'event_type_id' => 1,
                'unit_id' => 1,
                'vacancies' => 10,
                'scheduled_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
                'status' => 'available'
            ],
            [
                'id' => 2,
                'event_id' => 2,
                'event_type_id' => 2,
                'unit_id' => 2,
                'vacancies' => 5,
                'scheduled_at' => date('Y-m-d H:i:s', strtotime('+10 days')),
                'status' => 'available'
            ]
        ])->saveData();

        // 8. Pagamentos (ID do Mercado Pago fake)
        $payments = $this->table('payments');
        $paymentId = 1234567890;
        $payments->insert([
            [
                'id' => $paymentId,
                'amount' => 250.00,
                'status' => 'approved',
                'payer_email' => 'cliente@teste.com',
                'external_reference' => 'REG-001',
                'approved_at' => date('Y-m-d H:i:s')
            ]
        ])->saveData();

        // 9. Inscrição (Registration)
        $registrations = $this->table('registrations');
        $registrations->insert([
            [
                'person_id' => 2,
                'schedule_id' => 1,
                'payment_id' => $paymentId,
                'status' => 'confirmed'
            ]
        ])->saveData();

        // 10. Transação
        $transactions = $this->table('transactions');
        $transactions->insert([
            [
                'id' => 1,
                'schedule_id' => 1,
                'person_id' => 2,
                'payer_email' => 'cliente@teste.com',
                'payment_status' => 'approved',
                'amount' => 250.00
            ]
        ])->saveData();

        // 11. Log
        $logs = $this->table('history_logs');
        $logs->insert([
            [
                'transaction_id' => 1,
                'action' => 'Pagamento Confirmado',
                'details' => 'O sistema recebeu a confirmação do Mercado Pago.'
            ]
        ])->saveData();

        // 12. Código OTP (Último código enviado)
        $codes = $this->table('registered_codes');
        $codes->insert([
            [
                'email' => 'cliente@teste.com',
                'code' => '123456',
                'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
                'status' => 'pendente'
            ]
        ])->saveData();
    }
}