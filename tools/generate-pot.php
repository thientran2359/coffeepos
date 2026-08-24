<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$output = $root . '/languages/coffeepos.pot';
$files = [];

foreach (['coffeepos.php', 'uninstall.php', 'includes', 'templates', 'assets/js'] as $relative) {
    $path = $root . '/' . $relative;
    if (is_file($path)) {
        $files[] = $path;
        continue;
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile() && in_array(strtolower($file->getExtension()), ['php', 'js'], true)) {
            $files[] = $file->getPathname();
        }
    }
}

$messages = [];
$plurals = [];
$functions = '(?:__|_e|_x|_n|esc_html__|esc_html_e|esc_attr__|esc_attr_e)';
$patterns = [
    '/' . $functions . '\(\s*\'((?:\\\\.|[^\'])*)\'\s*,\s*\'coffeepos\'/s',
    '/' . $functions . '\(\s*"((?:\\\\.|[^"])*)"\s*,\s*\'coffeepos\'/s',
];

foreach ($files as $file) {
    $contents = (string) file_get_contents($file);
    foreach ($patterns as $pattern) {
        if (! preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE)) {
            continue;
        }

        foreach ($matches[1] as [$message, $offset]) {
            $message = stripcslashes($message);
            if ($message === '') {
                continue;
            }
            $line = substr_count(substr($contents, 0, (int) $offset), "\n") + 1;
            $relative = str_replace('\\', '/', substr($file, strlen($root) + 1));
            $messages[$message][] = $relative . ':' . $line;
        }
    }

    $pluralPatterns = [
        '/_n\(\s*\'((?:\\\\.|[^\'])*)\'\s*,\s*\'((?:\\\\.|[^\'])*)\'\s*,.*?,\s*\'coffeepos\'\s*\)/s',
        '/_n\(\s*"((?:\\\\.|[^"])*)"\s*,\s*"((?:\\\\.|[^"])*)"\s*,.*?,\s*\'coffeepos\'\s*\)/s',
    ];
    foreach ($pluralPatterns as $pattern) {
        if (! preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE)) {
            continue;
        }
        foreach ($matches[1] as $index => [$singular, $offset]) {
            $singular = stripcslashes($singular);
            $plural = stripcslashes($matches[2][$index][0]);
            $line = substr_count(substr($contents, 0, (int) $offset), "\n") + 1;
            $relative = str_replace('\\', '/', substr($file, strlen($root) + 1));
            $messages[$singular][] = $relative . ':' . $line;
            $plurals[$singular] = $plural;
        }
    }
}

ksort($messages, SORT_NATURAL | SORT_FLAG_CASE);

$pot = <<<'POT'
msgid ""
msgstr ""
"Project-Id-Version: CoffeePOS 1.0.0\n"
"Report-Msgid-Bugs-To: https://wordpress.org/support/plugin/coffeepos/\n"
"POT-Creation-Date: 2026-08-24 00:00+0000\n"
"MIME-Version: 1.0\n"
"Content-Type: text/plain; charset=UTF-8\n"
"Content-Transfer-Encoding: 8bit\n"
"X-Domain: coffeepos\n"

POT;

foreach ($messages as $message => $references) {
    $escaped = addcslashes($message, "\\\"\n\r\t");
    $pot .= '#: ' . implode(' ', array_unique($references)) . "\n";
    $pot .= 'msgid "' . $escaped . '"' . "\n";
    if (isset($plurals[$message])) {
        $escapedPlural = addcslashes($plurals[$message], "\\\"\n\r\t");
        $pot .= 'msgid_plural "' . $escapedPlural . '"' . "\n";
        $pot .= "msgstr[0] \"\"\nmsgstr[1] \"\"\n\n";
    } else {
        $pot .= "msgstr \"\"\n\n";
    }
}

if (file_put_contents($output, $pot) === false) {
    fwrite(STDERR, "Could not write {$output}.\n");
    exit(1);
}

fwrite(STDOUT, sprintf("Generated %s with %d messages.\n", $output, count($messages)));
