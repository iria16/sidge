<?php
foreach (['/mnt/c/Users/iriamar/OneDrive/Documentos/sidgev1', '/var/www/html/sidgev1'] as $repo) {
    echo "Checking repo: $repo\n";
    $output = shell_exec("cd $repo && git fsck --lost-found 2>/dev/null");
    preg_match_all('/dangling blob ([a-f0-9]{40})/', $output, $matches);
    foreach ($matches[1] as $hash) {
        $content = shell_exec("cd $repo && git cat-file -p $hash 2>/dev/null");
        if (stripos($content, 'encuesta') !== false || stripos($content, 'canaima') !== false || stripos($content, 'repite_grado') !== false) {
            echo "  Found in $hash:\n";
            $lines = explode("\n", $content);
            foreach ($lines as $i => $line) {
                if (stripos($line, 'encuesta') !== false || stripos($line, 'canaima') !== false || stripos($line, 'repite') !== false) {
                    echo "    Line " . ($i + 1) . ": " . trim($line) . "\n";
                }
            }
            file_put_contents("/tmp/survey_{$hash}.php", $content);
        }
    }
}
