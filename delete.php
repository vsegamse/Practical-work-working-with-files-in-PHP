<?php
function addLog(string $line): void {
    file_put_contents(
        __DIR__ . '/log.txt',
        date('d.m.Y H:i:s') . ' | ' . $line . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

function formatSize(int $bytes): string {
    $units = ['Б', 'КБ', 'МБ', 'ГБ'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 1) . ' ' . $units[$i];
}

$name = basename($_GET['file'] ?? '');
$path = __DIR__ . '/uploads/' . $name;

if ($name !== '' && $name[0] !== '.' && is_file($path)) {
    $size = filesize($path);
    if (unlink($path)) {
        addLog('УДАЛЕНИЕ | ' . $name . ' | ' . formatSize((int)$size));
        header('Location: index.php?msg=' . urlencode('Файл удалён: ' . $name));
        exit;
    }
    header('Location: index.php?error=' . urlencode('Не удалось удалить файл: ' . $name));
    exit;
}

header('Location: index.php?error=' . urlencode('Файл не найден: ' . ($name === '' ? '(имя не передано)' : $name)));
exit;
