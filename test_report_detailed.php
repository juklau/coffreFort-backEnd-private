#!/usr/bin/env php
<?php

/**
 * Rapport visuel des tests PHPUnit
 * Usage : php test-report.php
 * À placer à la racine du projet (même niveau que vendor/)
 */

class C {
    const RESET  = "\033[0m";
    const RED    = "\033[31m";
    const GREEN  = "\033[32m";
    const YELLOW = "\033[33m";
    const BLUE   = "\033[34m";
    const CYAN   = "\033[36m";
    const BOLD   = "\033[1m";
}

//ligne de séparation
function line(string $char = '─', int $len = 80): void {
    echo str_repeat($char, $len) . "\n";
}

// ── Résoudre le chemin absolu vers PHPUnit ────────────────────────────────────
// On se place toujours dans le répertoire du script, peu importe d'où on l'appelle
// __DIR__ => chemin absolu du dossier où se trouve le fichier
chdir(__DIR__);
// chdir() changer directory =>change le répertoire de travail courant du processus PHP.

$phpunit = __DIR__ . '/vendor/bin/phpunit';

if (!file_exists($phpunit)) {
    echo C::RED . "Erreur : PHPUnit introuvable dans vendor/bin/phpunit\n" . C::RESET;
    echo "Vérifie que tu lances le script depuis la racine du projet.\n";
    exit(1);
}

// ── Exécution des tests ───────────────────────────────────────────────────────
echo C::BOLD . "\n🧪 Exécution des tests PHPUnit...\n" . C::RESET;
line('═');

// Utiliser proc_open au lieu de exec pour une capture plus fiable
$descriptors = [
    0 => ['pipe', 'r'],  //stdin => entrée -> pas servi
    1 => ['pipe', 'w'],  //stdout => sortie normale
    2 => ['pipe', 'w'],  //stderr => sortie d'erreur
];

$process = proc_open(
    PHP_BINARY . ' ' . $phpunit . ' --no-coverage',
    $descriptors,
    $pipes
);

if (!is_resource($process)) {
    echo C::RED . "Erreur : impossible de lancer PHPUnit\n" . C::RESET;
    exit(1);
}

fclose($pipes[0]); //ferme
$stdout   = stream_get_contents($pipes[1]); //lit
$stderr   = stream_get_contents($pipes[2]); //lit
fclose($pipes[1]); //ferme
fclose($pipes[2]); //ferme
$exitCode = proc_close($process); //fermer processus et récup son code de sortie
//test passed => exitcode = 0

// Combiner stdout + stderr (PHPUnit écrit sur stderr selon la version)
$output = $stdout . "\n" . $stderr;

// ── Parsing : résumé global ───────────────────────────────────────────────────
$total    = 0;
$passed   = 0;
$errors   = 0;
$failures = 0;
$warnings = 0;

// "49 / 49 (100%)" ou "47 / 49 (95%)"
// \s* => 0 ou plusieurs espaces
// \d+ => un ou plusieurs chiffres
if (preg_match('/(\d+)\s*\/\s*(\d+)\s*\(/', $output, $m)) {
    $passed = (int)$m[1];
    $total  = (int)$m[2];
}

// "Tests: 49, Assertions: 120, Failures: 2, Errors: 1, Warnings: 3"
if (preg_match('/Tests:\s*(\d+),\s*Assertions:\s*(\d+)(.*)/', $output, $m)) {
    $total = (int)$m[1];
    $tail  = $m[3];
    if (preg_match('/Failures:\s*(\d+)/', $tail, $fm))  $failures = (int)$fm[1];
    if (preg_match('/Errors:\s*(\d+)/',   $tail, $em))  $errors   = (int)$em[1];
    if (preg_match('/Warnings:\s*(\d+)/', $tail, $wm))  $warnings = (int)$wm[1];
    $passed = $total - $failures - $errors;
}

// ── Parsing : blocs d'erreurs et failures ────────────────────────────────────

function extractBlocks(string $section): array {
    $blocks = [];
    $parts = preg_split('/\n(?=\d+\) )/', $section);
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') continue;
        if (preg_match('/^(\d+)\) (.+)/s', $part, $m)) {
            $blocks[(int)$m[1]] = trim($m[2]);
        }
    }
    return $blocks;
}

//extraire le nom de classe et de la méthode depuis le bloc
function parseTestName(string $block): array {
    if (preg_match('/Tests\\\\Controller\\\\(\w+)::(\w+)/', $block, $m)) {
        return ['class' => $m[1], 'method' => $m[2]];
    }
    return ['class' => '?', 'method' => '?'];
}

$errorList   = [];
$failureList = [];

// Erreurs
if ($errors > 0 && preg_match('/There were \d+ errors?:\n-+\n(.+?)(?=\nThere were|\nTime:|\nOK|$)/s', $output, $m)) {
    foreach (extractBlocks($m[1]) as $n => $block) {
        $test   = parseTestName($block);
        $exType = 'Error';
        $exMsg  = '';
        if (preg_match('/^(\w+(?:Exception|Error)[^:]*): (.+?)(?:\n|$)/s', $block, $em)) {
            $exType = $em[1];
            $exMsg  = trim(explode("\n", $em[2])[0]);
        } elseif (preg_match('/^(.+?)\n/', $block, $em)) {
            $exMsg = trim($em[1]);
        }
        $errorList[] = [
            'n'      => $n,
            'class'  => $test['class'],
            'method' => $test['method'],
            'type'   => $exType,
            'msg'    => $exMsg,
        ];
    }
}

// Failures
if ($failures > 0 && preg_match('/There were \d+ failures?:\n-+\n(.+?)(?=\nThere were|\nTime:|\nOK|$)/s', $output, $m)) {
    foreach (extractBlocks($m[1]) as $n => $block) {
        $test      = parseTestName($block);
        $actual    = null;
        $expected  = null;
        $assertion = '';

        //exemple: "Failed asserting that 401 matches expected 200."
        if (preg_match('/Failed asserting that (\d+) matches expected (\d+)/', $block, $fm)) {
            $actual    = (int)$fm[1]; // 401 => ce qui le contrôleur a retourné
            $expected  = (int)$fm[2]; // 200 => ce que le test attendait
            $assertion = "Code HTTP reçu : $actual — attendu : $expected";
        } elseif (preg_match("/Failed asserting that '(.*)' contains \"(.+)\"/", $block, $fm)) { //'' contains "inférieure"
            $assertion = "Chaîne introuvable dans la réponse : \"{$fm[2]}\"";
        } elseif (preg_match('/Failed asserting that (.+?)\./', $block, $fm)) { // tous ce qui commence par ça...
            $assertion = trim($fm[1]);
        }

        $failureList[] = [
            'n'         => $n,
            'class'     => $test['class'],
            'method'    => $test['method'],
            'actual'    => $actual,
            'expected'  => $expected,
            'assertion' => $assertion,
        ];
    }
}

// ── Affichage ─────────────────────────────────────────────────────────────────
echo "\n";
line('═');
echo C::BOLD . "  📊  RAPPORT DE TESTS\n" . C::RESET;
line('═');

echo "\n";
$statusLabel = ($exitCode === 0)
    ? C::GREEN . C::BOLD . "✅  TOUS LES TESTS PASSENT" . C::RESET
    : C::RED   . C::BOLD . "❌  DES TESTS ONT ÉCHOUÉ"  . C::RESET;
echo "  $statusLabel\n\n";

//sprintf => formattage du texte
//%-20s => chaîne alignée à gauche sur 20 caractères
echo sprintf("  %-20s %s\n", "Total",     C::BOLD   . $total    . C::RESET);
echo sprintf("  %-20s %s\n", "Réussis",   C::GREEN  . $passed   . C::RESET);
echo sprintf("  %-20s %s\n", "Failures",  ($failures > 0 ? C::YELLOW : C::GREEN) . $failures . C::RESET);
echo sprintf("  %-20s %s\n", "Erreurs",   ($errors   > 0 ? C::RED    : C::GREEN) . $errors   . C::RESET);
if ($warnings > 0) {
    echo sprintf("  %-20s %s\n", "Warnings", C::CYAN . $warnings . C::RESET);
}

// Par contrôleur
echo "\n";
line();
echo C::BOLD . "  📁  PAR CONTRÔLEUR\n" . C::RESET;
line();

$controllers = ['UserController', 'FileController', 'ShareController', 'AdminController'];
$issues = [];
foreach ($errorList   as $e) { 
    $issues[$e['class']][] = 'error'; 
}
foreach ($failureList as $f) {
    $issues[$f['class']][] = 'failure'; 
}

foreach ($controllers as $ctrl) {
    $count = count($issues[$ctrl] ?? []);
    if ($count === 0) {
        echo "  " . C::GREEN . "✅  $ctrl" . C::RESET . "\n";
    } else {
        echo "  " . C::RED . "❌  $ctrl" . C::RESET . " — $count problème(s)\n";
    }
}

// Détail erreurs
if (!empty($errorList)) {
    echo "\n";
    line();
    echo C::RED . C::BOLD . "  ❌  ERREURS (" . count($errorList) . ")\n" . C::RESET;
    line();
    foreach ($errorList as $e) {
        echo "\n  " . C::RED . "#{$e['n']}  {$e['class']}::{$e['method']}" . C::RESET . "\n";
        echo "       Type    : " . C::BOLD . $e['type'] . C::RESET . "\n";
        echo "       Message : " . $e['msg'] . "\n";
    }
}

// Détail failures
if (!empty($failureList)) {
    echo "\n";
    line();
    echo C::YELLOW . C::BOLD . "  ⚠️   FAILURES (" . count($failureList) . ")\n" . C::RESET;
    line();
    foreach ($failureList as $f) {
        echo "\n  " . C::YELLOW . "#{$f['n']}  {$f['class']}::{$f['method']}" . C::RESET . "\n";
        if ($f['actual'] !== null) {
            echo "       Reçu     : " . C::RED   . $f['actual']   . C::RESET . "\n";
            echo "       Attendu  : " . C::GREEN . $f['expected'] . C::RESET . "\n";
        }
        if ($f['assertion']) {
            echo "       Détail   : " . $f['assertion'] . "\n";
        }
    }
}

// Pied de page
echo "\n";
line('═');
echo C::BOLD . "  Statut final : " . ($exitCode === 0
    ? C::GREEN . "✅  RÉUSSI"
    : C::RED   . "❌  ÉCHOUÉ"
) . C::RESET . "\n";
line('═');
echo "\n";

// Export JSON
$report = [
    'status'   => $exitCode === 0 ? 'success' : 'failed',
    'summary'  => compact('total', 'passed', 'failures', 'errors', 'warnings'),
    'errors'   => $errorList,
    'failures' => $failureList,
];
file_put_contents(__DIR__ . '/test_report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "  Rapport JSON sauvegardé : test_report.json\n\n";

exit($exitCode);