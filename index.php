<?php
$uploadDir = __DIR__ . '/uploads/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$maxFile = ini_get('upload_max_filesize');
$maxPost = ini_get('post_max_size');

$msg   = $_GET['msg']   ?? '';
$error = $_GET['error'] ?? '';

$files = [];
foreach (array_diff(scandir($uploadDir), ['.', '..']) as $f) {
    if ($f[0] === '.') {
        continue;
    }
    $path = $uploadDir . $f;
    if (is_file($path)) {
        $files[] = [
            'name'  => $f,
            'size'  => filesize($path),
            'mtime' => filemtime($path),
        ];
    }
}

$sort = $_GET['sort'] ?? 'name';
if (!in_array($sort, ['name', 'size', 'date'], true)) {
    $sort = 'name';
}
$dir = ($_GET['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

usort($files, function (array $a, array $b) use ($sort, $dir): int {
    $cmp = match ($sort) {
        'size'  => $a['size']  <=> $b['size'],
        'date'  => $a['mtime'] <=> $b['mtime'],
        default => strcasecmp($a['name'], $b['name']),
    };
    return $dir === 'desc' ? -$cmp : $cmp;
});

$totalSize = array_sum(array_column($files, 'size'));

function formatSize(int $bytes): string {
    $units = ['Б', 'КБ', 'МБ', 'ГБ'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 1) . ' ' . $units[$i];
}

function sortLink(string $key, string $title, string $sort, string $dir): string {
    $next  = ($sort === $key && $dir === 'asc') ? 'desc' : 'asc';
    $arrow = $sort === $key ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
    return '<a href="?sort=' . $key . '&amp;dir=' . $next . '">' . $title . $arrow . '</a>';
}

$logFile  = __DIR__ . '/log.txt';
$logLines = is_file($logFile)
    ? file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
    : [];
$logLines = array_reverse(array_slice($logLines, -10));

$imageExt = ['jpg', 'jpeg', 'png', 'gif'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Файловый менеджер</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">

    <header>
        <h1>Файловый менеджер</h1>
        <p class="subtitle">Практическая работа: работа с файлами в PHP</p>
    </header>

    <?php if ($msg): ?>
        <div class="alert success"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <section class="card">
        <h2>Загрузка файла</h2>

        <div class="limits">
            Ограничения сервера: один файл — до <b><?= htmlspecialchars($maxFile) ?></b>,
            весь запрос — до <b><?= htmlspecialchars($maxPost) ?></b>
            · <a href="info.php">все настройки php.ini</a>
        </div>

        <form action="upload.php" method="post" enctype="multipart/form-data" class="upload-form">
            <input type="hidden" name="MAX_FILE_SIZE" value="5242880">
            <input type="file" name="userfile" required>
            <button type="submit">Загрузить</button>
        </form>

        <p class="hint">Разрешены: jpg, jpeg, png, gif, pdf, txt, docx, xlsx, zip. Не более 5 МБ.</p>

        <h3 class="sub">Множественная загрузка</h3>
        <form action="upload.php" method="post" enctype="multipart/form-data" class="upload-form">
            <input type="hidden" name="MAX_FILE_SIZE" value="5242880">
            <input type="file" name="files[]" multiple required>
            <button type="submit">Загрузить все</button>
        </form>
        <p class="hint">Можно выбрать сразу несколько файлов (Ctrl+клик). Каждый проверяется отдельно.</p>
    </section>

    <section class="card">
        <h2>Загруженные файлы</h2>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th><?= sortLink('name', 'Имя файла', $sort, $dir) ?></th>
                        <th><?= sortLink('size', 'Размер', $sort, $dir) ?></th>
                        <th><?= sortLink('date', 'Дата загрузки', $sort, $dir) ?></th>
                        <th>Действие</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($files)): ?>
                    <tr><td colspan="5" class="empty">Файлов пока нет</td></tr>
                <?php else: ?>
                    <?php foreach ($files as $i => $f): ?>
                    <?php $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)); ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td>
                            <div class="name-cell">
                            <?php if (in_array($ext, $imageExt, true)): ?>
                                <img class="thumb" src="uploads/<?= rawurlencode($f['name']) ?>"
                                     alt="<?= htmlspecialchars($f['name']) ?>" width="50" height="50">
                            <?php endif; ?>
                            <a href="uploads/<?= rawurlencode($f['name']) ?>" download>
                                <?= htmlspecialchars($f['name']) ?>
                            </a>
                            </div>
                        </td>
                        <td><?= formatSize($f['size']) ?></td>
                        <td><?= date('d.m.Y H:i', $f['mtime']) ?></td>
                        <td>
                            <a class="btn-delete"
                               href="delete.php?file=<?= urlencode($f['name']) ?>"
                               onclick="return confirm('Удалить этот файл?')">Удалить</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <p class="total">
            Всего файлов: <?= count($files) ?>, общий размер: <?= formatSize((int)$totalSize) ?>
        </p>
    </section>

    <section class="card">
        <h2>Журнал операций <span class="hint-inline">(последние 10 записей, новые сверху)</span></h2>
        <?php if (empty($logLines)): ?>
            <p class="empty">Журнал пуст — загрузите или удалите файл</p>
        <?php else: ?>
            <div class="log">
                <?php foreach ($logLines as $line): ?>
                    <div class="log-line"><?= htmlspecialchars($line) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <footer>Выполнил: Танчук Вячеслав, Группа: ИС-381</footer>

</div>
</body>
</html>
