<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$files = [];

foreach (['src', 'tests'] as $directory) {
    $path = $projectRoot . DIRECTORY_SEPARATOR . $directory;
    if (!is_dir($path)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            $files[] = $file->getPathname();
        }
    }
}

sort($files);
$invalidFiles = [];

foreach ($files as $file) {
    $process = proc_open(
        [PHP_BINARY, '-l', $file],
        [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes
    );

    if (!is_resource($process)) {
        $invalidFiles[$file] = 'Nao foi possivel iniciar o PHP lint.';
        continue;
    }

    $output = stream_get_contents($pipes[1]);
    $errorOutput = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    if (proc_close($process) !== 0) {
        $invalidFiles[$file] = trim($output . PHP_EOL . $errorOutput);
    }
}

if ($invalidFiles === []) {
    echo sprintf("Lint aprovado em %d arquivo(s).%s", count($files), PHP_EOL);
    exit(0);
}

foreach ($invalidFiles as $file => $error) {
    fwrite(STDERR, sprintf("%s:%s%s%s", $file, PHP_EOL, $error, PHP_EOL));
}

exit(1);
