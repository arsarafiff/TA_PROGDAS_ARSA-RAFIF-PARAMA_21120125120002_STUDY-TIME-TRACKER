<?php
require_once 'Task.php';
require_once 'DataManager.php';

// 1. Inisialisasi Data Manager & Muat Data
$dm = new DataManager();
$stats = $dm->getStats();
$doneLog = $dm->getDoneTasks();
$studyLog = $dm->getStudyLog();

// 2. Instansiasi Objek Task (Mapping dari array data JSON)
$tasks = array_map(fn($data) => new Task($data), $dm->getTasksData());

// 3. Logika Pengurutan
usort($tasks, function(Task $a, Task $b) {
    $priorityOrder = ['tinggi' => 3, 'sedang' => 2, 'rendah' => 1];
    $deadline_cmp = strcmp($a->getDeadline() ?? '9999-12-31', $b->getDeadline() ?? '9999-12-31');
    if ($deadline_cmp !== 0) return $deadline_cmp;
    return ($priorityOrder[$b->getPriority()] ?? 0) - ($priorityOrder[$a->getPriority()] ?? 0);
});

$mode = $_GET['mode'] ?? 'tugas';
$redirect_mode = $mode;
$action_performed = false;
$edit_task = null;
$edit_task_id = $_GET['edit'] ?? null;

// Jika ada parameter edit, cari task yang akan diedit
if ($edit_task_id) {
    foreach ($tasks as $t) {
        if ($t->getId() === (int)$edit_task_id) {
            $edit_task = $t;
            break;
        }
    }
}

// 4. Logika Controller (Pemrosesan POST/GET)
if (isset($_POST['add'])) { // Tambah Tugas
    $tasks[] = new Task([
        "id" => time(), 
        "title" => htmlspecialchars($_POST["title"]), 
        "deadline" => $_POST["deadline"], 
        "priority" => $_POST["priority"]
    ]);
    $action_performed = true;
    $redirect_mode = 'tugas';
    
} elseif (isset($_POST['edit'])) { // Edit Tugas
    $taskId = (int)$_POST["task_id"];
    foreach ($tasks as $t) {
        if ($t->getId() === $taskId) {
            $t->setTitle(htmlspecialchars($_POST["title"]));
            $t->setDeadline($_POST["deadline"]);
            $t->setPriority($_POST["priority"]);
            break;
        }
    }
    $action_performed = true;
    $redirect_mode = 'tugas';
    
} elseif (isset($_GET["done"])) { // Selesaikan Tugas
    $taskId = (int)$_GET["done"];
    
    // Menggunakan array_filter dan array_values untuk membuat array tugas baru (filter tugas yang selesai)
    $tasks_pending = [];
    $task_moved = null;

    foreach ($tasks as $t) {
        if ($t->getId() === $taskId) {
            $t->markDone(); 
            $task_moved = $t;
        } else {
            $tasks_pending[] = $t;
        }
    }
    
    if ($task_moved) {
        // Update Stats & Log
        $stats["tasks_done"]++;
        $doneLog[] = [
            "title" => $task_moved->getTitle(),
            "completion_date" => $task_moved->getCompletionDate()
        ];
        $dm->setDoneTasks($doneLog); // Simpan log baru ke DataManager
        $dm->setStats($stats);       // Simpan stats baru ke DataManager
    }

    $tasks = $tasks_pending; // Update daftar tugas
    $action_performed = true;
    $redirect_mode = 'tugas';

} elseif (isset($_GET["delete"])) { // Hapus Tugas
    $taskId = (int)$_GET["delete"];
    // Hapus tugas yang pending
    $tasks = array_values(array_filter($tasks, fn(Task $t) => $t->getId() !== $taskId));
    $action_performed = true;
    $redirect_mode = 'tugas';

} elseif (isset($_POST["study"])) { // Log Belajar & Streak
    $minutes = intval($_POST["minutes"]);
    $subject = htmlspecialchars($_POST["subject"]);
    
    if ($minutes > 0) {
        $stats["total_minutes"] += $minutes;
        
        $dm->addStudyLog(["timestamp" => time(), "date" => date("Y-m-d"), "minutes" => $minutes, "subject" => $subject]);
        
        // Logika Streak
        $today = date("Y-m-d");
        if ($stats["last_study_date"] !== $today) {
            $yesterday = date("Y-m-d", strtotime("-1 day"));
            $stats["streak"] = ($stats["last_study_date"] === $yesterday) ? $stats["streak"] + 1 : 1;
            $stats["last_study_date"] = $today;
        }
        $dm->setStats($stats); // Simpan stats baru
    }

    $action_performed = true;
    $redirect_mode = 'belajar';
}

// 5. Penyimpanan Data dan Redirect
if ($action_performed) {
    // Simpan semua tugas aktif (dalam format array) ke DataManager
    $dm->setTasksData(array_map(fn(Task $t) => $t->toArray(), $tasks)); 
    $dm->saveData();
    
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
                <h2><?= $edit_task ? 'Edit Tugas' : 'Tambah Tugas' ?></h2>
                <form method="POST">
                    <?php if ($edit_task): ?>
                        <input type="hidden" name="task_id" value="<?= $edit_task->getId() ?>">
                    <?php endif; ?>
                    <label>Judul</label><input type="text" name="title" value="<?= $edit_task ? htmlspecialchars($edit_task->getTitle()) : '' ?>" required>
                    <label>Deadline</label><input type="text" name="deadline" placeholder="format: YYYY-MM-DD (contoh: 2025-11-26)" value="<?= $edit_task ? $edit_task->getDeadline() : '' ?>">
                    <label>Prioritas</label>
                    <select name="priority">
                        <option value="tinggi" <?= $edit_task && $edit_task->getPriority() === 'tinggi' ? 'selected' : '' ?>>Tinggi</option>
                        <option value="sedang" <?= $edit_task && $edit_task->getPriority() === 'sedang' ? 'selected' : '' ?>>Sedang</option>
                        <option value="rendah" <?= $edit_task && $edit_task->getPriority() === 'rendah' ? 'selected' : '' ?>>Rendah</option>
                    </select>
                    <button name="<?= $edit_task ? 'edit' : 'add' ?>" class="btn-add"><?= $edit_task ? 'Simpan Perubahan' : 'Tambah' ?></button>
                    <?php if ($edit_task): ?>
                        <a href="?mode=tugas" class="btn" style="background-color: #aaa; color: white; text-decoration: none; display: inline-block; padding: 8px 12px; border-radius: 4px; margin-top: 8px;">Batal</a>
                    <?php endif; ?>
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
                            <td><?= htmlspecialchars($t->getTitle()) ?></td> 
                            <td><?= $t->getDeadline() ?: "-" ?></td> 
                            <td><?= ucfirst($t->getPriority()) ?></td> 
                            <td><?= ucfirst($t->getStatus()) ?></td> 
                            <td>
                                <a class="btn blue" href="?edit=<?= $t->getId() ?>&mode=tugas">Edit</a>
                                <a class="btn green" href="?done=<?= $t->getId() ?>&mode=tugas">Selesai</a> 
                                <a class="btn red" href="?delete=<?= $t->getId() ?>&mode=tugas">Hapus</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <div class="card"> 
                <h2>✅ Riwayat Tugas Selesai </h2>
                <?php
                // Mengambil 5 data terbaru dari $doneLog
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
                // Mengambil 5 data terbaru dari $studyLog
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
