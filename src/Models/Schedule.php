<?php
namespace App\Models;

class Schedule extends BaseModel
{
    public function __construct(
        public readonly int     $id,
        public readonly int     $eventId,
        public readonly int     $eventTypeId,
        public readonly int     $unitId,
        public readonly int     $vacancies,
        public readonly int     $durationMinutes,
        public readonly string  $scheduledAt,
        public readonly string  $status,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id:              (int) $data['id'],
            eventId:         (int) $data['event_id'],
            eventTypeId:     (int) $data['event_type_id'],
            unitId:          (int) $data['unit_id'],
            vacancies:       (int) $data['vacancies'],
            durationMinutes: (int) $data['duration_minutes'],
            scheduledAt:           $data['scheduled_at'],
            status:                $data['status'],
        );
    }
}
