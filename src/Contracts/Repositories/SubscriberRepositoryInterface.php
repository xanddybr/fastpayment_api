<?php
namespace App\Contracts\Repositories;

interface SubscriberRepositoryInterface
{
    public function saveCompleteRegistration(array $data): int;
    public function createAnamnesis(int $subscribedId, array $data): void;
    public function getAllSubscribers(): array;
}
