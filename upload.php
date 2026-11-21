<?php
ob_start();

$uploadDir = __DIR__ . '/uploads/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$maxSize = 5 * 1024 * 1024; // 5 МБ
$allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'txt', 'docx', 'xlsx', 'zip'];

function back(string $param, string $text): void {
    $url = 'index.php?' . $param . '=' . urlencode($text);
    if (!headers_sent()) {
        header('Location: ' . $url);
    } else {
        echo '<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8">'
           . '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url) . '">'
           . '</head><body><a href="' . htmlspecialchars($url) . '">Вернуться к списку файлов</a></body></html>';
    }
    exit;
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

function addLog(string $line): void {
    file_put_contents(
        __DIR__ . '/log.txt',
        date('d.m.Y H:i:s') . ' | ' . $line . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    back('error', 'Загрузка возможна только через форму на главной странице');
}

if (empty($_FILES) && ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $text = 'Запрос больше post_max_size (' . ini_get('post_max_size')
          . '): сервер отклонил данные, $_POST и $_FILES пусты';
    addLog('ОТКАЗ | превышен post_max_size (' . ini_get('post_max_size')
         . '), размер запроса ' . formatSize((int)$_SERVER['CONTENT_LENGTH']));
    back('error', $text);
}

$incoming = [];
if (isset($_FILES['userfile']) && is_array($_FILES['userfile'])) {
    $incoming[] = $_FILES['userfile'];
}
if (isset($_FILES['files']) && is_array($_FILES['files']['name'] ?? null)) {
    foreach ($_FILES['files']['name'] as $i => $n) {
        $incoming[] = [
            'name'     => $_FILES['files']['name'][$i],
            'type'     => $_FILES['files']['type'][$i],
            'tmp_name' => $_FILES['files']['tmp_name'][$i],
            'error'    => $_FILES['files']['error'][$i],
            'size'     => $_FILES['files']['size'][$i],
        ];
    }
}
$real = array_filter($incoming, fn($f) => $f['error'] !== UPLOAD_ERR_NO_FILE);
if (!empty($real)) {
    $incoming = $real;
}

if (empty($incoming)) {
    back('error', 'Файл не выбран (код ошибки 4, UPLOAD_ERR_NO_FILE)');
}

$okList  = [];
$errList = [];

foreach ($incoming as $file) {
    $origName = (string)$file['name'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $text = match ($file['error']) {
            UPLOAD_ERR_INI_SIZE   => 'Файл «' . $origName . '» больше upload_max_filesize ('
                                     . ini_get('upload_max_filesize') . ') — код ошибки 1',
            UPLOAD_ERR_FORM_SIZE  => 'Файл «' . $origName . '» больше MAX_FILE_SIZE из HTML-формы — код ошибки 2',
            UPLOAD_ERR_PARTIAL    => 'Файл «' . $origName . '» загружен частично — код ошибки 3, повторите попытку',
            UPLOAD_ERR_NO_FILE    => 'Файл не выбран — код ошибки 4',
            UPLOAD_ERR_NO_TMP_DIR => 'На сервере нет временной папки — код ошибки 6',
            UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл на диск — код ошибки 7',
            UPLOAD_ERR_EXTENSION  => 'Загрузку остановило расширение PHP — код ошибки 8',
            default               => 'Неизвестная ошибка загрузки — код ' . $file['error'],
        };
        $errList[] = $text;
        addLog('ОТКАЗ | ' . $origName . ' | ' . $text);
        continue;
    }

    if ($file['size'] > $maxSize) {
        $text = 'Файл «' . $origName . '» больше 5 МБ (' . formatSize((int)$file['size'])
              . ') — ограничение скрипта';
        $errList[] = $text;
        addLog('ОТКАЗ | ' . $origName . ' | ' . $text);
        continue;
    }

    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        $text = 'Недопустимый тип файла: .' . $ext . ' (разрешены: ' . implode(', ', $allowed) . ')';
        $errList[] = $text;
        addLog('ОТКАЗ | ' . $origName . ' | ' . $text);
        continue;
    }

    $name = basename($origName);
    $name = preg_replace('/[^\w.\-]/u', '_', $name);
    if ($name === '' || $name === '.' || $name === '..' || $name[0] === '.') {
        $errList[] = 'Некорректное имя файла: «' . $origName . '»';
        continue;
    }

    if (file_exists($uploadDir . $name)) {
        $base = time() . '_' . $name;
        $try  = $base;
        $k    = 1;
        while (file_exists($uploadDir . $try)) {
            $try = $base . '_' . $k;
            $k++;
        }
        $name = $try;
    }

    if (move_uploaded_file($file['tmp_name'], $uploadDir . $name)) {
        $okList[] = $name;
        addLog('ЗАГРУЗКА | ' . $name . ' | ' . formatSize((int)$file['size']));
    } else {
        $text = 'Не удалось сохранить файл «' . $origName . '»';
        $errList[] = $text;
        addLog('ОТКАЗ | ' . $origName . ' | ' . $text);
    }
}

$params = [];
if ($okList) {
    $params['msg'] = count($okList) === 1
        ? 'Файл загружен: ' . $okList[0]
        : 'Загружено файлов: ' . count($okList) . ' (' . implode(', ', $okList) . ')';
}
if ($errList) {
    $params['error'] = implode(' | ', $errList);
}
header('Location: index.php?' . http_build_query($params));
exit;
