<?php

// OOP 1 class
class Task {
    public $id;
    public $title;
    public $deadline;
    public $priority;
    public $status;
    public $completion_date; 

    public function __construct($data) {    // OOP 1 constructor
        $this->id = $data['id'];    // variabel, tipe data, array
        $this->title = $data['title'];
        $this->deadline = $data['deadline'];
        $this->priority = $data['priority'];
        $this->status = $data['status'] ?? 'pending';  
        $this->completion_date = $data['completion_date'] ?? null;
    }

    // Method untuk menandai selesai
    public function markDone(&$stats) {   
        if ($this->status !== 'done') { // pengkondisian
            $this->status = 'done';
            $this->completion_date = date("Y-m-d H:i:s");
            $stats["tasks_done"]++; 
        }
    }

    // method mendapatkan data dalam bentuk array
    public function toArray() {
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

$dataFile = 'data.json'; // Menyimpan tasks (pending) dan stats
$logFile = 'log.json';   // Menyimpan done_tasks dan study_log

function get_or_create_json($filename, $default_content = []) {
    if (!file_exists($filename)) {
        file_put_contents($filename, json_encode($default_content, JSON_PRETTY_PRINT));
        return $default_content;
    }
    return json_decode(file_get_contents($filename), true);
}

// Muat Data Aktif (Tasks PENDING & Stats)
$data = get_or_create_json($dataFile, [
    "tasks" => [], 
    "stats" => ["total_minutes" => 0, "tasks_done" => 0, "last_study_date" => "", "streak" => 0]
]);

// Muat Data Log (Riwayat Tugas Selesai & Riwayat Belajar)
$log = get_or_create_json($logFile, [
    "done_tasks" => [], 
    "study_log" => []
]);

// OOP 1 instansiasi objek
$tasks = array_map(fn($data) => new Task($data), $data['tasks']); // $tasks kini mencatat tugas PENDING
$stats = &$data['stats'];
$doneLog = &$log['done_tasks'];
$studyLog = &$log['study_log'];


// Mengurutkan array $tasks
usort($tasks, function($a, $b) {
    $priorityOrder = ['tinggi' => 3, 'sedang' => 2, 'rendah' => 1];
    $deadline_cmp = strcmp($a->deadline, $b->deadline);
    if ($deadline_cmp !== 0) return $deadline_cmp;
    return ($priorityOrder[$b->priority] ?? 0) - ($priorityOrder[$a->priority] ?? 0);
});

$mode = $_GET['mode'] ?? 'tugas';
$redirect_mode = $mode;

if (isset($_POST['add'])) { // Tambah Tugas
    $tasks[] = new Task(["id" => time(), "title" => htmlspecialchars($_POST["title"]), "deadline" => $_POST["deadline"], "priority" => $_POST["priority"]]);
    $redirect_mode = 'tugas';
    
} elseif (isset($_GET["done"])) { // Selesaikan Tugas
    $taskId = $_GET["done"];
    $taskIndex = -1;
    
    foreach ($tasks as $index => $t) {
        if ($t->id == $taskId) {
            $t->markDone($stats);
            $taskIndex = $index;
            
            // Catat entri ke Riwayat Tugas Selesai ($doneLog)
            $doneLog[] = [
                "title" => $t->title,
                "completion_date" => $t->completion_date
            ];
            break; 
        }
    }
    
    // Hapus tugas yang selesai dari Daftar Tugas Aktif ($tasks)
    if ($taskIndex !== -1) {
        array_splice($tasks, $taskIndex, 1);
    }

    $redirect_mode = 'tugas';

} elseif (isset($_GET["delete"])) { // Hapus Tugas
    // Tugas yang pending dihapus dari $tasks.
    $tasks = array_filter($tasks, fn($t)=>$t->id != $_GET["delete"]);
    $redirect_mode = 'tugas';

} elseif (isset($_POST["study"])) { // Log Belajar & Streak
    $minutes = intval($_POST["minutes"]);
    $stats["total_minutes"] += $minutes;
    $studyLog[] = ["timestamp" => time(), "date" => date("Y-m-d"), "minutes" => $minutes, "subject" => htmlspecialchars($_POST["subject"])];

    // Logika Streak
    $today = date("Y-m-d");
    if ($stats["last_study_date"] !== $today) {
        $stats["streak"] = ($stats["last_study_date"] == date("Y-m-d", strtotime("-1 day"))) ? $stats["streak"] + 1 : 1;
        $stats["last_study_date"] = $today;
    }

    $redirect_mode = 'belajar';
}

if (isset($_POST['add']) || isset($_GET["done"]) || isset($_GET["delete"]) || isset($_POST["study"])) {
    // Simpan Data Aktif (Tasks PENDING & Stats)
    $data['tasks'] = array_map(fn($t) => $t->toArray(), $tasks); 
    file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT));

    // Simpan Data Log (Done Tasks & Study Log)
    $log['done_tasks'] = $doneLog;
    $log['study_log'] = $studyLog;
    file_put_contents($logFile, json_encode($log, JSON_PRETTY_PRINT));
    
    header("Location: index.php?mode=" . $redirect_mode);
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Study Tracker</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap">
</head>

<body>
<div class="wrapper">
    <div class="top-nav">
        <a href="?mode=tugas" class="nav-item <?= $mode == 'tugas' ? 'active' : '' ?>">Menu Tugas</a>
        <a href="?mode=belajar" class="nav-item <?= $mode == 'belajar' ? 'active' : '' ?>">Menu Belajar</a>
    </div>

    <div class="content-wrapper">
        <div class="left-panel">
            <?php if ($mode == 'tugas'): ?>
            <div class="card">
                <h2>Tambah Tugas</h2>
                <form method="POST">
                    <label>Judul</label><input type="text" name="title" required>
                    <label>Deadline</label><input type="text" name="deadline" placeholder="format: YYYY-MM-DD (contoh: 2025-11-26)">
                    <label>Prioritas</label>
                    <select name="priority">
                        <option value="tinggi">Tinggi</option><option value="sedang">Sedang</option><option value="rendah">Rendah</option>
                    </select>
                    <button name="add" class="btn-add">Tambah</button>
                </form>
            </div>
            
            <?php elseif ($mode == 'belajar'): ?>
            <div class="card pretty-form">
                <h2>Mode Belajar</h2>
                <form method="POST">
                    <label>Mata Kuliah</label><input type="text" name="subject" placeholder="Contoh: Pemrograman Web" required>
                    <label>Durasi belajar (menit)</label><input type="number" name="minutes" placeholder="Misal: 25" required>
                    <button name="study" class="btn-add">Tambah</button>
                </form>
            </div>
            <?php endif; ?>
        </div>


        <div class="right-panel">
            <?php if ($mode == 'tugas'): ?>
            
            <div class="card">
                <h2>Daftar Tugas</h2>
                <table>
                    <tr><th>Judul</th><th>Deadline</th><th>Prioritas</th><th>Status</th><th>Aksi</th></tr>
                    <?php if (empty($tasks)): ?>
                        <tr><td colspan="5" style="text-align: center; color: #aaa;">Tidak ada tugas</td></tr>
                    <?php endif; ?>

                    <?php foreach ($tasks as $t): ?>
                        <tr>
                            <td><?= htmlspecialchars($t->title) ?></td>
                            <td><?= $t->deadline ?: "-" ?></td>
                            <td><?= ucfirst($t->priority) ?></td>
                            <td><?= ucfirst($t->status) ?></td>
                            <td>
                                <a class="btn green" href="?done=<?= $t->id ?>&mode=tugas">Selesai</a>
                                <a class="btn red" href="?delete=<?= $t->id ?>&mode=tugas">Hapus</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <div class="card"> 
                <h2>✅ Riwayat Tugas Selesai </h2>
                <?php
                // Mengambil 5 data terbaru dari $doneLog yang berasal dari log.json
                $recentDoneTasks = array_reverse(array_slice($doneLog, -5));

                if (!empty($recentDoneTasks)):
                ?>
                <ul>
                    <?php foreach ($recentDoneTasks as $t): ?>
                        <li>
                            <strong><?= htmlspecialchars($t['title']) ?></strong> diselesaikan pada 
                            <?= date("Y-m-d", strtotime($t['completion_date'])) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                    <p style="color: #444;">Belum ada tugas yang diselesaikan.</p>
                <?php endif; ?>
            </div>

            <div class="card colored stat-pink">
                <h2>📊 Statistik Tugas</h2>
                <p><strong>Tugas Selesai:</strong> <?= $stats["tasks_done"] ?></p>
                <p><strong>Tugas Pending:</strong> <?= count($tasks) ?></p> 
            </div>
            
            <?php elseif ($mode == 'belajar'): ?>
            
            <div class="card">
                <h2>📚 Riwayat Belajar Terbaru</h2>
                <?php
                // Mengambil 5 data terbaru dari $studyLog yang berasal dari log.json
                $recentLog = array_reverse(array_slice($studyLog, -5));
                if (!empty($recentLog)): ?>
                <ul>
                    <?php foreach ($recentLog as $log): ?>
                        <li>
                            <strong><?= htmlspecialchars($log['subject']) ?></strong> (<?= $log['minutes'] ?> menit) pada <?= $log['date'] ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                    <p style="color: #aaa;">Belum ada sesi belajar yang dicatat.</p>
                <?php endif; ?>
            </div>

            <div class="card colored stat-blue">
                <h2>📅 Info Mingguan</h2>
                <p>Total jam minggu ini: <strong><?= round($stats["total_minutes"]/60, 1) ?></strong> jam</p>
            </div>

            <div class="card colored stat-green">
                <h2>⏱ Progress Harian</h2>
                <p>Terakhir belajar: <strong><?= $stats["last_study_date"] ?: "-" ?></strong></p>
                <p>Streak belajar: <strong><?= $stats["streak"] ?></strong> hari</p>
            </div>
            
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
