<?php


namespace App\DTOs;

final readonly class NotificationSummaryData
{
    public function __construct(
        public int $total,
        public int $processed,
        public int $failed,
        public int $pending,
        public int $processing,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            total:      (int) ($data['total']      ?? 0),
            processed:  (int) ($data['processed']  ?? 0),
            failed:     (int) ($data['failed']      ?? 0),
            pending:    (int) ($data['pending']     ?? 0),
            processing: (int) ($data['processing']  ?? 0),
        );
    }

    public function toArray(): array
    {
        return [
            'total'      => $this->total,
            'processed'  => $this->processed,
            'failed'     => $this->failed,
            'pending'    => $this->pending,
            'processing' => $this->processing,
        ];
    }
}
