<?php
namespace App\Repositories;

use PDO;
use App\Contracts\Repositories\ScheduleRepositoryInterface;

class ScheduleRepository extends BaseRepository implements ScheduleRepositoryInterface
{
    public function create(array $data): bool
    {
        $stmt = $this->conn->prepare("
            INSERT INTO schedules
                (unit_id, event_id, event_type_id, vacancies, scheduled_at, duration_minutes, status)
            VALUES
                (:unit, :event, :type, :vacancies, :scheduled_at, :duration, 'available')
        ");
        return $stmt->execute([
            ':unit'         => $data['unit_id'],
            ':event'        => $data['event_id'],
            ':type'         => $data['event_type_id'],
            ':vacancies'    => $data['vacancies'],
            ':scheduled_at' => $data['scheduled_at'],
            ':duration'     => $data['duration_minutes'],
        ]);
    }

    public function getAllAdmin(): array
    {
        return $this->conn->query("
            SELECT
                s.id AS schedule_id,
                e.name AS event_name, e.price AS event_price, e.slug AS event_slug,
                et.name AS type_name, u.name AS unit_name,
                s.scheduled_at, s.duration_minutes, s.vacancies, s.status
            FROM schedules s
            JOIN units       u  ON s.unit_id       = u.id
            JOIN events      e  ON s.event_id      = e.id
            JOIN event_types et ON s.event_type_id = et.id
            ORDER BY s.scheduled_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAvailable(?string $eventSlug, ?string $typeSlug): array
    {
        $sql = "
            SELECT
                s.id AS schedule_id,
                e.name AS event_name, e.price AS event_price, e.slug AS event_slug,
                et.name AS type_name, et.slug AS type_slug,
                u.name AS unit_name,
                s.scheduled_at, s.duration_minutes, s.vacancies
            FROM schedules s
            JOIN events      e  ON s.event_id      = e.id
            JOIN units       u  ON s.unit_id       = u.id
            JOIN event_types et ON s.event_type_id = et.id
            WHERE s.status = 'available' AND s.vacancies > 0 AND s.scheduled_at > NOW()
        ";

        if ($eventSlug) $sql .= " AND LOWER(e.slug)  = :eventSlug";
        if ($typeSlug)  $sql .= " AND LOWER(et.slug) = :typeSlug";
        $sql .= " ORDER BY s.scheduled_at ASC";

        $stmt = $this->conn->prepare($sql);
        if ($eventSlug) $stmt->bindValue(':eventSlug', strtolower($eventSlug));
        if ($typeSlug)  $stmt->bindValue(':typeSlug',  strtolower($typeSlug));
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function closeExpired(): void
    {
        $this->conn->prepare("
            UPDATE schedules SET status = 'unavailable'
            WHERE scheduled_at <= NOW() AND status = 'available'
        ")->execute();

        $this->conn->prepare("
            UPDATE schedules SET status = 'available'
            WHERE scheduled_at > NOW() AND status = 'unavailable'
        ")->execute();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->conn->prepare("DELETE FROM schedules WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
