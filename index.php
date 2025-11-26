<?php

class Task {
    // Properti Class (OOP 1: Property)
    public $id;
    public $title;
    public $deadline;
    public $priority;
    public $status;

    // Method Constructor (OOP 1: Method Khusus)
    public function __construct($data) {
        $this->id = $data['id']; // (Modul 1: Variabel & Tipe Data)
        $this->title = $data['title'];
        $this->deadline = $data['deadline'];
        $this->priority = $data['priority'];
        $this->status = $data['status'] ?? 'pending';
    }

    // Method untuk Menandai Selesai (OOP 1: Method)
    public function markDone(&$stats) {
        if ($this->status !== 'done') { // (Modul 2: Pengkondisian)
            $this->status = 'done';
            $stats["tasks_done"]++; // (Modul 1: Array)
        }
    }

    // Method untuk Mendapatkan Data dalam bentuk Array
    public function toArray() {
        return [
            "id" => $this->id,
            "title" => $this->title,
            "deadline" => $this->deadline,
            "priority" => $this->priority,
            "status" => $this->status
        ];
    }
}

// INISIALISASI DAN PENGATURAN FILE DATA

// (Modul 1: Variabel & Tipe Data)
$tasksFile = 'tasks.json';
$statsFile = 'stats.json';
$logFile = 'study_log.json';

// Inisialisasi file jika belum ada (Modul 2: Pengkondisian | Modul 4: Function)
if (!file_exists($tasksFile)) file_put_contents($tasksFile, json_encode([]));
if (!file_exists($statsFile)) {
    file_put_contents($statsFile, json_encode([
        "total_minutes" => 0,
        "tasks_done" => 0,
        "last_study_date" => "",
        "streak" => 0
    ]));
}
if (!file_exists($logFile)) file_put_contents($logFile, json_encode([]));

// Membaca data dari file (Modul 4: Function | Modul 1: Array)
$rawTasks = json_decode(file_get_contents($tasksFile), true);
$stats = json_decode(file_get_contents($statsFile), true);
$studyLog = json_decode(file_get_contents($logFile), true);

// Mengubah array mentah menjadi array of Task Objects (OOP 1: Object)
$tasks = [];
foreach ($rawTasks as $data) { // (Modul 3: Perulangan)
    $tasks[] = new Task($data);
}

// ------ SORT BY DEADLINE ------
// (Modul 4: Function)
usort($tasks, fn($a,$b) => strcmp($a->deadline, $b->deadline));

// LOGIC BACKEND (TAMBAH, SELESAI, HAPUS, BELAJAR)

// ------ TAMBAH TUGAS ------
if (isset($_POST['add'])) { // (Modul 2: Pengkondisian)
    // Membuat objek Task baru (OOP 1: Object Instantiation)
    $newTask = new Task([
        "id" => time(), // (Modul 4: Function)
        "title" => htmlspecialchars($_POST["title"]),
        "deadline" => $_POST["deadline"],
        "priority" => $_POST["priority"]
    ]);
    
    $tasks[] = $newTask;

    // Mengubah objek kembali ke array untuk disimpan (Modul 4: Function)
    $tasksToSave = array_map(fn($t) => $t->toArray(), $tasks);
    file_put_contents($tasksFile, json_encode($tasksToSave, JSON_PRETTY_PRINT));
    
    header("Location: index.php");
    exit;
}

// ------ SELESAIKAN TUGAS ------
if (isset($_GET["done"])) {
    foreach ($tasks as $t) { // (Modul 3: Perulangan)
        if ($t->id == $_GET["done"]) { // (Modul 2: Pengkondisian)
            // Memanggil method dari Object Task (OOP 1: Method Call)
            $t->markDone($stats);
        }
    }
    
    $tasksToSave = array_map(fn($t) => $t->toArray(), $tasks);
    file_put_contents($tasksFile, json_encode($tasksToSave, JSON_PRETTY_PRINT));
    file_put_contents($statsFile, json_encode($stats, JSON_PRETTY_PRINT));
    
    header("Location: index.php");
    exit;
}

// ------ HAPUS TUGAS ------
if (isset($_GET["delete"])) {
    // (Modul 4: Function | Modul 1: Array)
    $tasks = array_filter($tasks, fn($t)=>$t->id != $_GET["delete"]);
    
    $tasksToSave = array_map(fn($t) => $t->toArray(), $tasks);
    file_put_contents($tasksFile, json_encode($tasksToSave, JSON_PRETTY_PRINT));
    
    header("Location: index.php");
    exit;
}

// ------ MODE BELAJAR ------
if (isset($_POST["study"])) {
    $minutes = intval($_POST["minutes"]);
    $subject = htmlspecialchars($_POST["subject"]);

    // Update Statistik (Modul 1: Variabel & Tipe Data)
    $stats["total_minutes"] += $minutes;

    // Log Belajar Baru
    $studyLog[] = [
        "timestamp" => time(),
        "date" => date("Y-m-d"),
        "minutes" => $minutes,
        "subject" => $subject
    ];

    // Streak Logic (Modul 2: Pengkondisian | Modul 4: Function)
    $today = date("Y-m-d");
    if ($stats["last_study_date"] == $today) {
        // same day
    } elseif ($stats["last_study_date"] == date("Y-m-d", strtotime("-1 day"))) {
        $stats["streak"]++;
    } else {
        $stats["streak"] = 1;
    }

    $stats["last_study_date"] = $today;

    file_put_contents($statsFile, json_encode($stats, JSON_PRETTY_PRINT));
    file_put_contents($logFile, json_encode($studyLog, JSON_PRETTY_PRINT));
    header("Location: index.php");
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
    <div class="left-panel">
        <div class="card">
            <h2>Tambah Tugas</h2>
            <form method="POST">
                <label>Judul</label>
                <input type="text" name="title" required>
                <label>Deadline</label>
                <input type="text" name="deadline" placeholder="format: YYYY-MM-DD (contoh: 2025-11-26)">
                <label>Prioritas</label>
                <select name="priority">
                    <option value="tinggi">Tinggi</option>
                    <option value="sedang">Sedang</option>
                    <option value="rendah">Rendah</option>
                </select>
                <button name="add" class="btn-add">Tambah</button>
            </form>
        </div>

        <div class="card pretty-form">
        <h2>Mode Belajar</h2>
        <form method="POST">
            <label>Mata Kuliah</label>
            <input type="text" name="subject" placeholder="Contoh: Pemrograman Web" required>
            <label>Durasi belajar (menit)</label>
            <input type="number" name="minutes" placeholder="Misal: 25" required>
            <button name="study" class="btn-add">Tambah</button>
        </form>
        </div>

        <div class="card">
            <h2>Daftar Tugas</h2>
            <table>
                <tr>
                    <th>Judul</th>
                    <th>Deadline</th>
                    <th>Prioritas</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
                <?php if (empty($tasks)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: #aaa;">Tidak ada tugas</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($tasks as $t): // (Modul 3: Perulangan) ?>
                    <tr class="<?= $t->status == 'done' ? 'task-done' : '' ?>">
                        <td><?= htmlspecialchars($t->title) ?></td>
                        <td><?= $t->deadline ?: "-" ?></td>
                        <td><?= ucfirst($t->priority) ?></td>
                        <td><?= ucfirst($t->status) ?></td>
                        <td>
                            <?php if ($t->status == "pending"): // (Modul 2: Pengkondisian) ?>
                                <a class="btn green" href="?done=<?= $t->id ?>">Selesai</a>
                            <?php endif; ?>
                            <a class="btn red" href="?delete=<?= $t->id ?>">Hapus</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>


    <div class="right-panel">
        <div class="card colored stat-pink">
            <h2>📊 Statistik Belajar</h2>
            <p><strong>Total durasi belajar:</strong> <?= $stats["total_minutes"] ?> menit</p>
            <p><strong>Tugas selesai:</strong> <?= $stats["tasks_done"] ?></p>
            <p><strong>Streak belajar:</strong> <?= $stats["streak"] ?> hari</p>
        </div>

        <div class="card colored stat-blue">
            <h2>📅 Info Mingguan</h2>
            <p>Total jam minggu ini: <strong><?= round($stats["total_minutes"]/60, 1) ?></strong> jam</p>
        </div>

        <div class="card colored stat-green">
            <h2>⏱ Progress Harian</h2>
            <p>Terakhir belajar: <strong><?= $stats["last_study_date"] ?: "-" ?></strong></p>
        </div>
        
        <div class="card">
            <h2>📚 Riwayat Belajar Terbaru</h2>
            <?php
            // Ambil 5 log terbaru dan balikkan urutannya (Modul 1: Array | Modul 4: Function)
            $recentLog = array_reverse(array_slice($studyLog, -5));
            if (!empty($recentLog)):
            ?>
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
    </div>
</div>
</body>
</html>
