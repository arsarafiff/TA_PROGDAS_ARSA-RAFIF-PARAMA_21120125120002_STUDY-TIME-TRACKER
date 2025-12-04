<?php
// Membutuhkan kelas Task.php
require_once 'Task.php'; 

class DataManager {
    private const DATA_FILE = 'app_data.json';
    private $data;

    public function __construct() {
        $this->loadData();
    }

    // --- Private Helper Method ---
    private function loadData(): void {
        if (!file_exists(self::DATA_FILE)) {
            // Konten default yang disatukan
            $default = [
                "tasks" => [], // Tugas PENDING
                "done_tasks" => [], // Riwayat tugas selesai
                "stats" => ["total_minutes" => 0, "tasks_done" => 0, "last_study_date" => "", "streak" => 0],
                "study_log" => []
            ];
            file_put_contents(self::DATA_FILE, json_encode($default, JSON_PRETTY_PRINT));
            $this->data = $default;
        } else {
            $this->data = json_decode(file_get_contents(self::DATA_FILE), true);
        }
    }
    
    // --- Public I/O Methods ---
    public function saveData(): void {
        file_put_contents(self::DATA_FILE, json_encode($this->data, JSON_PRETTY_PRINT));
    }
    
    // --- Public Getter Methods ---
    public function getTasksData(): array { return $this->data['tasks']; }
    public function getDoneTasks(): array { return $this->data['done_tasks']; }
    public function getStats(): array { return $this->data['stats']; }
    public function getStudyLog(): array { return $this->data['study_log']; }

    // --- Public Setter/Updater Methods ---
    // Dipanggil sebelum saveData()
    public function setTasksData(array $tasksArray): void { $this->data['tasks'] = $tasksArray; }
    public function setStats(array $stats): void { $this->data['stats'] = $stats; }
    public function setDoneTasks(array $doneTasks): void { $this->data['done_tasks'] = $doneTasks; }
    public function addStudyLog(array $logEntry): void { $this->data['study_log'][] = $logEntry; }
}
