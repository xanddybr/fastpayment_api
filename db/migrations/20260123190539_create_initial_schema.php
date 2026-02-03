<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateInitialSchema extends AbstractMigration
{
    public function change(): void
    {
        // 1. Tipos de Pessoas (signed => false para permitir FK)
        $this->table('types_person', ['signed' => false])
            ->addColumn('name', 'string', ['limit' => 50])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true])
            ->create();

        // 2. Pessoas
        $this->table('persons', ['signed' => false])
            ->addColumn('full_name', 'string', ['limit' => 100])
            ->addColumn('email', 'string', ['limit' => 100])
            ->addColumn('password', 'string', ['limit' => 255])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'active'])
            ->addColumn('type_person_id', 'integer', ['signed' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true])
            ->addIndex(['email'], ['unique' => true])
            ->addForeignKey('type_person_id', 'types_person', 'id', ['delete' => 'NO_ACTION', 'update' => 'NO_ACTION'])
            ->create();

        // 3. Detalhes Adicionais da Pessoa
        $this->table('person_details', ['signed' => false])
            ->addColumn('person_id', 'integer', ['signed' => false])
            ->addColumn('activity_professional', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('phone', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('street', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('number', 'integer')
            ->addColumn('neighborhood', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('city', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('obs_motived', 'text', ['null' => true])
            ->addColumn('first_time', 'boolean', ['default' => 0])
            ->addForeignKey('person_id', 'persons', 'id', ['delete' => 'CASCADE'])
            ->create();

        // 4. Unidades de Atendimento
        $this->table('units', ['signed' => false])
            ->addColumn('name', 'string', ['limit' => 100])
            ->create();

        // 5. Tipos de Eventos
        $this->table('event_types', ['signed' => false])
            ->addColumn('name', 'string', ['limit' => 100])
            ->create();

        // 6. Eventos
        $this->table('events', ['signed' => false])
            ->addColumn('name', 'string', ['limit' => 100])
            ->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2])
            ->addColumn('slug', 'string', ['limit' => 100, 'null' => true])
            ->addIndex(['slug'], ['unique' => true])
            ->create();

        // 7. Agendamentos (Schedules)
        $this->table('schedules', ['signed' => false])
            ->addColumn('event_id', 'integer', ['signed' => false])
            ->addColumn('event_type_id', 'integer', ['signed' => false])
            ->addColumn('unit_id', 'integer', ['signed' => false])
            ->addColumn('vacancies', 'integer', ['default' => 0])
            ->addColumn('scheduled_at', 'datetime')
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'available'])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true])
            ->addForeignKey('event_id', 'events', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('event_type_id', 'event_types', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('unit_id', 'units', 'id', ['delete' => 'CASCADE'])
            ->create();

        // 8. Pagamentos (Correção: null => false para a Primary Key)
        $this->table('payments', ['id' => false, 'primary_key' => 'id'])
            ->addColumn('id', 'biginteger', ['signed' => false, 'null' => false]) 
            ->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2])
            ->addColumn('status', 'string', ['limit' => 50])
            ->addColumn('payer_email', 'string', ['limit' => 100])
            ->addColumn('external_reference', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('approved_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true])
            ->create();

        // 9. Inscrições (Garantir que os tipos batem para a FK)
        $this->table('registrations', ['signed' => false])
            ->addColumn('person_id', 'integer', ['signed' => false])
            ->addColumn('schedule_id', 'integer', ['signed' => false])
            ->addColumn('payment_id', 'biginteger', ['null' => true, 'signed' => false])
            ->addColumn('status', 'string', ['limit' => 50, 'default' => 'pending'])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true])
            ->addForeignKey('person_id', 'persons', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('schedule_id', 'schedules', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('payment_id', 'payments', 'id', ['delete' => 'SET_NULL'])
            ->create();
            
        // 10. Transações Financeiras
        $this->table('transactions', ['signed' => false])
            ->addColumn('schedule_id', 'integer', ['signed' => false])
            ->addColumn('person_id', 'integer', ['null' => true, 'signed' => false])
            ->addColumn('payer_email', 'string', ['limit' => 255])
            ->addColumn('payment_status', 'string', ['limit' => 50, 'default' => 'pending'])
            ->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'null' => true])
            ->addForeignKey('schedule_id', 'schedules', 'id', ['delete' => 'CASCADE'])
            ->create();

        // 11. Logs de Histórico
        $this->table('history_logs', ['signed' => false])
            ->addColumn('transaction_id', 'integer', ['null' => true, 'signed' => false])
            ->addColumn('action', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('details', 'text', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true])
            ->create();

        // 12. Códigos de Verificação (OTP)
        $this->table('registered_codes', ['signed' => false])
            ->addColumn('email', 'string', ['limit' => 255])
            ->addColumn('code', 'string', ['limit' => 6])
            ->addColumn('expires_at', 'datetime')
            ->addColumn('status', 'enum', ['values' => ['pendente', 'validado', 'expirado'], 'default' => 'pendente'])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true])
            ->create();
    }
}