<?php
namespace App\Config;

use DI\ContainerBuilder;
use PDO;
use function DI\create;
use function DI\get;

class Container
{
    public static function build(): \DI\Container
    {
        $builder = new ContainerBuilder();

        $builder->addDefinitions([

            // PDO singleton
            PDO::class => function () {
                $dsn = sprintf(
                    'mysql:host=%s;dbname=%s;port=%s;charset=utf8mb4',
                    $_ENV['DB_HOST'],
                    $_ENV['DB_NAME'],
                    $_ENV['DB_PORT']
                );
                return new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_PERSISTENT         => true,
                ]);
            },

            // Repositories
            \App\Contracts\Repositories\PersonRepositoryInterface::class =>
                create(\App\Repositories\PersonRepository::class)->constructor(get(PDO::class)),

            \App\Contracts\Repositories\TransactionRepositoryInterface::class =>
                create(\App\Repositories\TransactionRepository::class)->constructor(get(PDO::class)),

            \App\Contracts\Repositories\ScheduleRepositoryInterface::class =>
                create(\App\Repositories\ScheduleRepository::class)->constructor(get(PDO::class)),

            \App\Contracts\Repositories\EventRepositoryInterface::class =>
                create(\App\Repositories\EventRepository::class)->constructor(get(PDO::class)),

            \App\Contracts\Repositories\UnitRepositoryInterface::class =>
                create(\App\Repositories\UnitRepository::class)->constructor(get(PDO::class)),

            \App\Contracts\Repositories\EventTypeRepositoryInterface::class =>
                create(\App\Repositories\EventTypeRepository::class)->constructor(get(PDO::class)),

            // Services
            \App\Contracts\Services\EmailServiceInterface::class => function () {
                return new \App\Services\EmailService(
                    $_ENV['MAIL_HOST']     ?? 'smtp.hostinger.com',
                    $_ENV['MAIL_USERNAME'] ?? 'contato@misturadeluz.com',
                    $_ENV['MAIL_PASSWORD'] ?? '',
                    (int) ($_ENV['MAIL_PORT'] ?? 465),
                    $_ENV['MAIL_FROM_NAME'] ?? 'Mistura de Luz'
                );
            },

            \App\Contracts\Services\PaymentGatewayInterface::class => function () {
                return new \App\Services\MercadoPagoGateway($_ENV['MP_ACCESS_TOKEN']);
            },

            \App\Contracts\Services\AuthServiceInterface::class =>
                create(\App\Services\AuthService::class)->constructor(
                    get(\App\Contracts\Repositories\PersonRepositoryInterface::class),
                    get(\App\Contracts\Services\EmailServiceInterface::class)
                ),

            \App\Services\PaymentService::class => function ($c) {
                return new \App\Services\PaymentService(
                    $c->get(\App\Contracts\Repositories\TransactionRepositoryInterface::class),
                    $c->get(\App\Contracts\Services\PaymentGatewayInterface::class),
                    $_ENV['APP_URL'] ?? 'http://localhost:8080'
                );
            },

            \App\Contracts\Services\RegistrationServiceInterface::class =>
                create(\App\Services\RegistrationService::class)->constructor(
                    get(\App\Contracts\Repositories\PersonRepositoryInterface::class),
                    get(\App\Contracts\Repositories\TransactionRepositoryInterface::class),
                    get(PDO::class)
                ),

        ]);

        return $builder->build();
    }
}
