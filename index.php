<?php

class Task {
    // Properti Class (OOP 1: Property)
    public $id;
    public $title;
    public $deadline;
    public $priority;
    public $status;
    public $completion_date; 

    // Method Constructor (OOP 1: Method Khusus)
    public function __construct($data) {
        $this->id = $data['id']; // (Modul 1: Variabel & Tipe Data)
        $this->title = $data['title'];
        $this->deadline = $data['deadline'];
        $this->priority = $data['priority'];
        $this->status = $data['status'] ?? 'pending';
        // Ambil completion_date jika ada
        $this->completion_date = $data['completion_date'] ?? null;
    }

    // Method untuk Menandai Selesai (OOP 1: Method)
    public function markDone(&$stats) { // Parameter $stats dilewatkan dengan referensi (&)
        if ($this->status !== 'done') { // (Modul 2: Pengkondisian)
            $this->status = 'done';
            // Mencatat tanggal dan waktu penyelesaian
            $this->completion_date = date("Y-m-d H:i:s"); // (Modul 4: Function Date/Time)
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
            "status" => $this->status,
            "completion_date" => $this->completion_date 
        ];
    }
}

// Mendefinisikan nama file JSON. Fungsi utilitas untuk membaca/membuat file JSON jika belum ada. Memuat data tasks, stats, dan study log.
$tasksFile = 'tasks.json'; // (Modul 1: Variabel & Tipe Data)
$statsFile = 'stats.json';
$logFile = 'study_log.json';

// Function untuk memastikan file JSON ada dan memuat isinya
function get_or_create_json($filename, $default_content = []) { // (Modul 4: Function)
    if (!file_exists($filename)) { // (Modul 2: Pengkondisian)
        file_put_contents($filename, json_encode($default_content, JSON_PRETTY_PRINT));
        return $default_content;
    }
    return json_decode(file_get_contents($filename), true);
}

$rawTasks = get_or_create_json($tasksFile, []);
$stats = get_or_create_json($statsFile, ["total_minutes" => 0, "tasks_done" => 0, "last_study_date" => "", "streak" => 0]);
$studyLog = get_or_create_json($logFile, []);

// Konversi data mentah ke array of Task Objects (OOP 1: Object Instantiation)
$tasks = array_map(fn($data) => new Task($data), $rawTasks); // (Modul 4: Function Array)


// Mengurutkan array $tasks (yang belum selesai) berdasarkan Deadline dan Prioritas sebelum ditampilkan.
usort($tasks, function($a, $b) { // (Modul 4: Function Array)
    $priorityOrder = ['tinggi' => 3, 'sedang' => 2, 'rendah' => 1];
    $deadline_cmp = strcmp($a->deadline, $b->deadline);
    if ($deadline_cmp !== 0) return $deadline_cmp;
    return ($priorityOrder[$b->priority] ?? 0) - ($priorityOrder[$a->priority] ?? 0);
});

$mode = $_GET['mode'] ?? 'tugas'; // Menentukan mode tampilan awal

// Menangani semua permintaan POST/GET (Tambah, Selesaikan, Hapus Tugas, dan Log Belajar). Memperbarui data di memori ($tasks, $stats, $studyLog).
$redirect_mode = $mode;

if (isset($_POST['add'])) { // Tambah Tugas (Modul 2: Pengkondisian)
    $tasks[] = new Task(["id" => time(), "title" => htmlspecialchars($_POST["title"]), "deadline" => $_POST["deadline"], "priority" => $_POST["priority"]]);
    $redirect_mode = 'tugas';
} elseif (isset($_GET["done"])) { // Selesaikan Tugas
    foreach ($tasks as $t) if ($t->id == $_GET["done"]) $t->markDone($stats); // Memanggil Method Class (OOP 1: Method Call)
    file_put_contents($statsFile, json_encode($stats, JSON_PRETTY_PRINT));
    $redirect_mode = 'tugas';
} elseif (isset($_GET["delete"])) { // Hapus Tugas
    $tasks = array_filter($tasks, fn($t)=>$t->id != $_GET["delete"]); // (Modul 4: Function Array)
    $redirect_mode = 'tugas';
} elseif (isset($_POST["study"])) { // Log Belajar & Streak
    $minutes = intval($_POST["minutes"]);
    $stats["total_minutes"] += $minutes; // (Modul 1: Array)
    $studyLog[] = ["timestamp" => time(), "date" => date("Y-m-d"), "minutes" => $minutes, "subject" => htmlspecialchars($_POST["subject"])];

    // Logika Streak
    $today = date("Y-m-d");
    if ($stats["last_study_date"] !== $today) {
        $stats["streak"] = ($stats["last_study_date"] == date("Y-m-d", strtotime("-1 day"))) ? $stats["streak"] + 1 : 1;
        $stats["last_study_date"] = $today;
    }
    file_put_contents($statsFile, json_encode($stats, JSON_PRETTY_PRINT));
    file_put_contents($logFile, json_encode($studyLog, JSON_PRETTY_PRINT));
    $redirect_mode = 'belajar';
}

// Menyimpan semua perubahan data dari memori ke file JSON yang relevan
if (isset($_POST['add']) || isset($_GET["done"]) || isset($_GET["delete"]) || isset($_POST["study"])) {
    $tasksToSave = array_map(fn($t) => $t->toArray(), $tasks);
    file_put_contents($tasksFile, json_encode($tasksToSave, JSON_PRETTY_PRINT));
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

                    <?php foreach ($tasks as $t): ?> // (Modul 3: Perulangan)
                        <tr class="<?= $t->status == 'done' ? 'task-done' : '' ?>"> // (Modul 2: Pengkondisian)
                            <td><?= htmlspecialchars($t->title) ?></td>
                            <td><?= $t->deadline ?: "-" ?></td>
                            <td><?= ucfirst($t->priority) ?></td>
                            <td><?= ucfirst($t->status) ?></td>
                            <td>
                                <?php if ($t->status == "pending"): ?>
                                    <a class="btn green" href="?done=<?= $t->id ?>&mode=tugas">Selesai</a>
                                <?php endif; ?>
                                <a class="btn red" href="?delete=<?= $t->id ?>&mode=tugas">Hapus</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <div class="card colored stat-success"> 
                <h2>✅ Riwayat Tugas Selesai</h2>
                <?php
                // Filter, Sort, dan Ambil 5 terbaru (Modul 4: Function Array)
                $doneTasks = array_filter($tasks, fn($t) => $t->status == 'done' && $t->completion_date != null);
                usort($doneTasks, fn($a, $b) => strcmp($b->completion_date, $a->completion_date));
                $recentDoneTasks = array_slice($doneTasks, 0, 5);

                if (!empty($recentDoneTasks)):
                ?>
                <ul>
                    <?php foreach ($recentDoneTasks as $t): ?>
                        <li>
                            <strong><?= htmlspecialchars($t->title) ?></strong> diselesaikan pada 
                            <?= date("Y-m-d", strtotime($t->completion_date)) ?> // (Modul 4: Date Formatting)
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
                <p><strong>Tugas Pending:</strong> <?= count(array_filter($tasks, fn($t) => $t->status == 'pending')) ?></p>
            </div>
            
            <?php elseif ($mode == 'belajar'): ?>
            
            <div class="card">
                <h2>📚 Riwayat Belajar Terbaru</h2>
                <?php
                $recentLog = array_reverse(array_slice($studyLog, -5)); // (Modul 4: Function Array)
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
