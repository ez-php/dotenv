<?php

declare(strict_types=1);

namespace EzPhp\Env;

/**
 * Class Parser
 *
 * Parses the content of a .env file into a key-value map.
 *
 * Supported syntax:
 *   KEY=value               unquoted, trailing inline comment stripped
 *   KEY="value"             double-quoted: escape sequences + variable interpolation
 *   KEY='value'             single-quoted: literal, no escapes, no interpolation
 *   KEY=                    empty value
 *   export KEY=value        "export" prefix is stripped
 *   # comment               line is ignored
 *
 * @package EzPhp\Env
 */
final class Parser
{
    /**
     * @param string $content Raw .env file content.
     *
     * @return array<string, string>
     */
    public function parse(string $content): array
    {
        $vars = [];

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = ltrim(substr($line, 7));
            }

            $eqPos = strpos($line, '=');

            if ($eqPos === false) {
                continue;
            }

            $key = rtrim(substr($line, 0, $eqPos));
            $raw = substr($line, $eqPos + 1);

            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
                continue;
            }

            $vars[$key] = $this->parseValue($raw, $vars);
        }

        return $vars;
    }

    /**
     * @param string               $raw
     * @param array<string, string> $loaded Variables already parsed in this file.
     *
     * @return string
     */
    private function parseValue(string $raw, array $loaded): string
    {
        $raw = ltrim($raw);

        if ($raw === '' || $raw === '""' || $raw === "''") {
            return '';
        }

        if (str_starts_with($raw, '"')) {
            return $this->parseDoubleQuoted($raw, $loaded);
        }

        if (str_starts_with($raw, "'")) {
            return $this->parseSingleQuoted($raw);
        }

        return $this->parseUnquoted($raw);
    }

    /**
     * @param array<string, string> $loaded
     */
    private function parseDoubleQuoted(string $raw, array $loaded): string
    {
        $result = '';
        $len = strlen($raw);
        $i = 1; // skip opening "

        while ($i < $len) {
            $char = $raw[$i];

            if ($char === '"') {
                break; // closing quote
            }

            if ($char === '\\' && $i + 1 < $len) {
                $next = $raw[$i + 1];
                $result .= match ($next) {
                    'n' => "\n",
                    't' => "\t",
                    'r' => "\r",
                    '"' => '"',
                    '\\' => '\\',
                    '$' => '$',
                    default => '\\' . $next,
                };
                $i += 2;
                continue;
            }

            if ($char === '$') {
                [$expanded, $consumed] = $this->expandVariable($raw, $i, $loaded);
                $result .= $expanded;
                $i += $consumed;
                continue;
            }

            $result .= $char;
            $i++;
        }

        return $result;
    }

    /**
     * @param string $raw
     *
     * @return string
     */
    private function parseSingleQuoted(string $raw): string
    {
        $end = strpos($raw, "'", 1);

        return $end === false ? substr($raw, 1) : substr($raw, 1, $end - 1);
    }

    /**
     * @param string $raw
     *
     * @return string
     */
    private function parseUnquoted(string $raw): string
    {
        return rtrim((string) preg_replace('/\s+#.*$/', '', $raw));
    }

    /**
     * Expand a $VAR or ${VAR} reference starting at $pos (the $ character).
     *
     * @param array<string, string> $loaded
     *
     * @return array{string, int} [expanded value, characters consumed (including $)]
     */
    private function expandVariable(string $str, int $pos, array $loaded): array
    {
        $next = $pos + 1 < strlen($str) ? $str[$pos + 1] : '';

        if ($next === '{') {
            $end = strpos($str, '}', $pos + 2);

            if ($end === false) {
                return ['$', 1];
            }

            $name = substr($str, $pos + 2, $end - $pos - 2);
            $value = $this->resolveVar($name, $loaded);

            return [$value, $end - $pos + 1];
        }

        // $VAR_NAME — consume valid identifier characters
        $name = '';
        $i = $pos + 1;

        while ($i < strlen($str) && preg_match('/[A-Za-z0-9_]/', $str[$i])) {
            $name .= $str[$i];
            $i++;
        }

        if ($name === '') {
            return ['$', 1];
        }

        return [$this->resolveVar($name, $loaded), strlen($name) + 1];
    }

    /**
     * @param array<string, string> $loaded
     */
    private function resolveVar(string $name, array $loaded): string
    {
        if (isset($loaded[$name])) {
            return $loaded[$name];
        }

        $env = getenv($name);

        return $env !== false ? $env : '';
    }
}
