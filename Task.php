<?php

class Task {
    private $id;
    private $title;
    private $deadline;
    private $priority;
    private $status;
    private $completion_date; 
    
    // Konstanta untuk menghindari 'magic strings'
    public const STATUS_PENDING = 'pending';
    public const STATUS_DONE = 'done';

    public function __construct(array $data) {
        $this->id = $data['id'];
        $this->title = $data['title'];
        $this->deadline = $data['deadline'] ?? null;
        $this->priority = $data['priority'];
        $this->status = $data['status'] ?? self::STATUS_PENDING;
        $this->completion_date = $data['completion_date'] ?? null;
    }

    // Metode Getter
    public function getId(): int { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function getDeadline(): ?string { return $this->deadline; }
    public function getPriority(): string { return $this->priority; }
    public function getStatus(): string { return $this->status; }
    public function getCompletionDate(): ?string { return $this->completion_date; }

    // Metode Aksi
    public function markDone(): void {
        if ($this->status !== self::STATUS_DONE) {
            $this->status = self::STATUS_DONE;
            $this->completion_date = date("Y-m-d H:i:s");
        }
    }

    // Metode untuk penyimpanan (tetap penting untuk serialisasi)
    public function toArray(): array {
        return [
            "id" => $this->id, 
            "title" => $this->title, 
            "deadline" => $this->deadline, 
            "priority" => $this->priority, 
            "status" => $this->status,
            "completion_date" => $this->completion_date 
        ];
    }
}
