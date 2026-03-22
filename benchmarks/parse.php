<?php

declare(strict_types=1);

/**
 * Performance benchmark for EzPhp\Env\Parser.
 *
 * Generates a representative .env file with four variable types
 * (plain, double-quoted, single-quoted, interpolated) and measures
 * parsing throughput over multiple iterations.
 *
 * Exits with code 1 if the per-parse time exceeds the defined threshold,
 * allowing CI to detect performance regressions automatically.
 *
 * Usage:
 *   php benchmarks/parse.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use EzPhp\Env\Parser;

const ITERATIONS = 500;
const VARIABLE_GROUPS = 100;
const THRESHOLD_MS = 10.0; // per-parse upper bound in milliseconds

$lines = [];

for ($i = 0; $i < VARIABLE_GROUPS; $i++) {
    $lines[] = 'PLAIN_' . $i . '=value_' . $i;
    $lines[] = 'DOUBLE_' . $i . '="quoted value ' . $i . ' with \\n newline"';
    $lines[] = 'SINGLE_' . $i . "='literal \$dollar and \\n backslash {$i}'";
    $lines[] = 'INTERP_' . $i . '="${PLAIN_0}_suffix_' . $i . '"';
}

$content = implode("\n", $lines);
$variableCount = VARIABLE_GROUPS * 4;

$parser = new Parser();

// Warm-up: one pass to allow opcode caching before measuring
$parser->parse($content);

$start = hrtime(true);

for ($i = 0; $i < ITERATIONS; $i++) {
    $parser->parse($content);
}

$end = hrtime(true);

$totalMs = ($end - $start) / 1_000_000;
$perParseMs = $totalMs / ITERATIONS;

echo sprintf(
    "Dotenv Parser Benchmark\n" .
    "  Variables per parse : %d (%d groups × 4 types)\n" .
    "  Iterations          : %d\n" .
    "  Total time          : %.2f ms\n" .
    "  Per parse           : %.3f ms\n" .
    "  Threshold           : %.1f ms\n",
    $variableCount,
    VARIABLE_GROUPS,
    ITERATIONS,
    $totalMs,
    $perParseMs,
    THRESHOLD_MS,
);

if ($perParseMs > THRESHOLD_MS) {
    echo sprintf(
        "FAIL: %.3f ms exceeds threshold of %.1f ms\n",
        $perParseMs,
        THRESHOLD_MS,
    );
    exit(1);
}

echo "PASS\n";
exit(0);
