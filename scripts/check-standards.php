<?php

declare(strict_types=1);

/**
 * Gate de padrao de codigo: proibe else/elseif e switch no codigo de producao.
 * Espelha o `check:standards` do SDK Node, que nao tinha equivalente em PHP.
 */

$root = dirname(__DIR__);
$scanDirs = ['src'];

$rules = [
    'else'   => '/}\s*else\b|^\s*else\b/m',
    'elseif' => '/\belseif\b|}\s*else\s+if\b/m',
    'switch' => '/^\s*switch\s*\(/m',
];

$violations = [];

foreach ($scanDirs as $dir) {
    $path = $root . DIRECTORY_SEPARATOR . $dir;
    if (!is_dir($path)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());
        if ($contents === false) {
            continue;
        }

        foreach ($rules as $name => $pattern) {
            if (preg_match_all($pattern, $contents, $matches) > 0) {
                $relative = str_replace($root . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $violations[] = sprintf('%s: %d x %s', $relative, count($matches[0]), $name);
            }
        }
    }
}

if ($violations === []) {
    echo "[check-standards] sem else/elseif/switch no codigo de producao." . PHP_EOL;
    exit(0);
}

foreach ($violations as $violation) {
    fwrite(STDERR, '[check-standards] VIOLACAO ' . $violation . PHP_EOL);
}
fwrite(STDERR, '[check-standards] use early return ou match() no lugar.' . PHP_EOL);
exit(1);
