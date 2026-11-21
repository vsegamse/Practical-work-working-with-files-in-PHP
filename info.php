<?php
$keys = [
    'file_uploads'        => 'Разрешена ли загрузка файлов (1 = да)',
    'upload_max_filesize' => 'Максимальный размер ОДНОГО файла',
    'post_max_size'       => 'Максимальный размер ВСЕГО POST-запроса',
    'max_file_uploads'    => 'Сколько файлов можно загрузить за один запрос',
    'memory_limit'        => 'Память для одного скрипта',
    'max_execution_time'  => 'Время выполнения скрипта, секунды',
    'max_input_time'      => 'Время приёма данных запроса, секунды',
];

$iniFile = php_ini_loaded_file();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Настройки загрузки — Файловый менеджер</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">

    <header>
        <h1>Настройки загрузки</h1>
        <p class="subtitle">Текущие значения php.ini · <a href="index.php">← назад к файловому менеджеру</a></p>
    </header>

    <section class="card">
        <h2>Директивы PHP</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Директива</th><th>Значение</th><th>За что отвечает</th></tr>
                </thead>
                <tbody>
                <?php foreach ($keys as $k => $desc): ?>
                    <tr>
                        <td><b><?= htmlspecialchars($k) ?></b></td>
                        <td><?= htmlspecialchars((string)ini_get($k)) ?></td>
                        <td><?= htmlspecialchars($desc) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="limits" style="margin-top:16px">
            Файл настроек: <b><?= htmlspecialchars($iniFile !== false ? $iniFile : '(none) — используются значения по умолчанию') ?></b><br>
            Версия PHP: <b><?= htmlspecialchars(PHP_VERSION) ?></b>
        </div>

        <p class="hint">
            Правило соотношения: memory_limit ≥ post_max_size &gt; upload_max_filesize.
            После изменения php.ini перезапустите сервер (Ctrl+C и снова php -S ...),
            иначе новые значения не применятся. Полный список настроек — функция phpinfo().
        </p>
    </section>

    <footer>Практическая работа: работа с файлами в PHP · задание 6</footer>

</div>
</body>
</html>
