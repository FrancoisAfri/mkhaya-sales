<?php

namespace App\Modules\Sales\Support;

use RuntimeException;

/**
 * A tiny, safe arithmetic evaluator for formula targets: numbers, + - * /, brackets and the named
 * sales measures — nothing else is accepted, so a formula can never run code or touch the database.
 * Division by zero gives 0.
 */
final class Formula
{
    public const VARIABLES = ['net_value', 'gross_value', 'quantity', 'discount', 'active_customers', 'transactions'];

    /** @return string[] the variables a formula uses (validates it on the way) */
    public static function variables(string $expr): array
    {
        $tokens = self::tokens($expr);
        self::toRpn($tokens);

        return array_values(array_unique(array_filter($tokens, fn ($t) => in_array($t, self::VARIABLES, true))));
    }

    public static function evaluate(string $expr, array $values): float
    {
        $stack = [];
        foreach (self::toRpn(self::tokens($expr)) as $t) {
            if (is_numeric($t)) {
                $stack[] = (float) $t;
            } elseif (in_array($t, self::VARIABLES, true)) {
                $stack[] = (float) ($values[$t] ?? 0);
            } elseif ($t === 'neg') {
                $stack[] = -array_pop($stack);
            } else {
                $b = array_pop($stack);
                $a = array_pop($stack);
                $stack[] = match ($t) { '+' => $a + $b, '-' => $a - $b, '*' => $a * $b, '/' => $b == 0.0 ? 0.0 : $a / $b };
            }
        }
        if (count($stack) !== 1) {
            throw new RuntimeException('The formula is incomplete.');
        }

        return (float) $stack[0];
    }

    private static function tokens(string $expr): array
    {
        preg_match_all('/\s*(\d+(?:\.\d+)?|[a-z_]+|[-+*\/()])\s*/', strtolower($expr), $m);
        if (preg_replace('/\s+/', '', implode('', $m[1])) !== preg_replace('/\s+/', '', strtolower($expr)) || ! $m[1]) {
            throw new RuntimeException('Use only numbers, + - * / ( ) and: ' . implode(', ', self::VARIABLES) . '.');
        }
        foreach ($m[1] as $t) {
            if (preg_match('/^[a-z_]+$/', $t) && ! in_array($t, self::VARIABLES, true)) {
                throw new RuntimeException("Unknown name \"{$t}\". Allowed: " . implode(', ', self::VARIABLES) . '.');
            }
        }

        return $m[1];
    }

    private static function toRpn(array $tokens): array
    {
        $out = [];
        $ops = [];
        $prec = ['+' => 1, '-' => 1, '*' => 2, '/' => 2, 'neg' => 3];
        $prev = null;
        foreach ($tokens as $t) {
            if (is_numeric($t) || in_array($t, self::VARIABLES, true)) {
                $out[] = $t;
            } elseif ($t === '(') {
                $ops[] = $t;
            } elseif ($t === ')') {
                while ($ops && end($ops) !== '(') {
                    $out[] = array_pop($ops);
                }
                if (! $ops) {
                    throw new RuntimeException('Brackets do not match.');
                }
                array_pop($ops);
            } else {
                if ($t === '-' && ($prev === null || in_array($prev, ['+', '-', '*', '/', '('], true))) {
                    $t = 'neg';
                }
                while ($ops && end($ops) !== '(' && $prec[end($ops)] >= $prec[$t] && $t !== 'neg') {
                    $out[] = array_pop($ops);
                }
                $ops[] = $t;
            }
            $prev = $t;
        }
        while ($ops) {
            $op = array_pop($ops);
            if ($op === '(') {
                throw new RuntimeException('Brackets do not match.');
            }
            $out[] = $op;
        }

        return $out;
    }
}
