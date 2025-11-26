<?php
// ------ FILE DATA ------
$tasksFile = 'tasks.json';
$statsFile = 'stats.json';

if (!file_exists($tasksFile)) file_put_contents($tasksFile, json_encode([]));
if (!file_exists($statsFile)) {
    file_put_contents($statsFile, json_encode([
        "total_minutes" => 0,
        "tasks_done" => 0,
        "last_study_date" => "",
        "streak" => 0
    ]));
}

$tasks = json_decode(file_get_contents($tasksFile), true);
$stats = json_decode(file_get_contents($statsFile), true);

// ------ SORT BY DEADLINE ------
usort($tasks, fn($a,$b) => strcmp($a['deadline'], $b['deadline']));

// ------ TAMBAH TUGAS ------
if (isset($_POST['add'])) {
    $tasks[] = [
        "id" => time(),
        "title" => $_POST["title"],
        "deadline" => $_POST["deadline"],
        "priority" => $_POST["priority"],
        "status" => "pending"
    ];
    file_put_contents($tasksFile, json_encode($tasks, JSON_PRETTY_PRINT));
    header("Location: index.php");
    exit;
}

// ------ SELESAIKAN TUGAS ------
if (isset($_GET["done"])) {
    foreach ($tasks as &$t) {
        if ($t["id"] == $_GET["done"]) {
            $t["status"] = "done";
            $stats["tasks_done"]++;
        }
    }
    file_put_contents($tasksFile, json_encode($tasks, JSON_PRETTY_PRINT));
    file_put_contents($statsFile, json_encode($stats, JSON_PRETTY_PRINT));
    header("Location: index.php");
    exit;
}

// ------ HAPUS TUGAS ------
if (isset($_GET["delete"])) {
    $tasks = array_filter($tasks, fn($t)=>$t["id"] != $_GET["delete"]);
    file_put_contents($tasksFile, json_encode($tasks, JSON_PRETTY_PRINT));
    header("Location: index.php");
    exit;
}

// ------ MODE BELAJAR ------
if (isset($_POST["study"])) {
    $minutes = intval($_POST["minutes"]);
    $stats["total_minutes"] += $minutes;

    // Streak
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
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Study Tracker</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>

<div class="wrapper">

    <!-- ================= LEFT PANEL ================= -->
    <div class="left-panel">

        <!-- Tambah Tugas -->
        <div class="card">
            <h2>Tambah Tugas</h2>
            <form method="POST">
                <label>Judul</label>
                <input type="text" name="title" required>

                <label>Deadline</label>
                <input type="date" name="deadline">

                <label>Prioritas</label>
                <select name="priority">
                    <option value="tinggi">Tinggi</option>
                    <option value="sedang">Sedang</option>
                    <option value="rendah">Rendah</option>
                </select>

                <button name="add">Tambah</button>
            </form>
        </div>

        <!-- Mode Belajar -->
        <div class="card pretty-form">
        <h2>Mode Belajar</h2>

        <form method="POST">

            <label>Durasi belajar (menit)</label>
            <input type="number" name="minutes" class="nice-input" placeholder="Misal: 25" required>

            <button name="study" class="btn-add">Tambah</button>
        </form>
        </div>



        <!-- Daftar Tugas -->
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

                <?php foreach ($tasks as $t): ?>
                    <tr>
                        <td><?= htmlspecialchars($t["title"]) ?></td>
                        <td><?= $t["deadline"] ?: "-" ?></td>
                        <td><?= ucfirst($t["priority"]) ?></td>
                        <td><?= ucfirst($t["status"]) ?></td>
                        <td>
                            <?php if ($t["status"] == "pending"): ?>
                                <a class="btn green" href="?done=<?= $t['id'] ?>">Selesai</a>
                            <?php endif; ?>
                            <a class="btn red" href="?delete=<?= $t['id'] ?>">Hapus</a>
                        </td>
                    </tr>
                <?php endforeach; ?>

            </table>
        </div>

    </div>


    <!-- ================= RIGHT PANEL ================= -->
    <div class="right-panel">

        <!-- Statistik -->
        <div class="card colored stat-pink">
            <h2>📊 Statistik Belajar</h2>
            <p><strong>Total durasi belajar:</strong> <?= $stats["total_minutes"] ?> menit</p>
            <p><strong>Tugas selesai:</strong> <?= $stats["tasks_done"] ?></p>
            <p><strong>Streak belajar:</strong> <?= $stats["streak"] ?> hari</p>
        </div>

        <!-- Statistik Tambahan -->
        <div class="card colored stat-blue">
            <h2>📅 Info Mingguan</h2>
            <p>Total jam minggu ini: <strong><?= round($stats["total_minutes"]/60,1) ?></strong> jam</p>
        </div>

        <div class="card colored stat-green">
            <h2>⏱ Progress Harian</h2>
            <p>Terakhir belajar: <strong><?= $stats["last_study_date"] ?: "-" ?></strong></p>
        </div>

    </div>

</div>

</body>
</html>
