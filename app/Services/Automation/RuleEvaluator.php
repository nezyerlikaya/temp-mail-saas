<?php

namespace App\Services\Automation;

use App\Services\Service;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class RuleEvaluator extends Service
{
    public function matches(?array $conditionGroup, array $payload): bool
    {
        if ($conditionGroup === null || $conditionGroup === []) {
            return true;
        }

        if (isset($conditionGroup['all']) && is_array($conditionGroup['all'])) {
            return collect($conditionGroup['all'])
                ->every(fn (mixed $condition): bool => $this->evaluateCondition($condition, $payload));
        }

        if (isset($conditionGroup['any']) && is_array($conditionGroup['any'])) {
            return collect($conditionGroup['any'])
                ->contains(fn (mixed $condition): bool => $this->evaluateCondition($condition, $payload));
        }

        return $this->evaluateCondition($conditionGroup, $payload);
    }

    public function evaluateCondition(mixed $condition, array $payload): bool
    {
        if (! is_array($condition)) {
            return false;
        }

        $field = $condition['field'] ?? null;
        $operator = Str::lower((string) ($condition['operator'] ?? 'equals'));

        if (! is_string($field) || ! preg_match('/^[A-Za-z0-9_.-]+$/', $field)) {
            return false;
        }

        $actual = Arr::get($payload, $field);
        $expected = $condition['value'] ?? null;

        return match ($operator) {
            'equals', '=' => $actual == $expected,
            'not_equals', '!=' => $actual != $expected,
            'greater_than', '>' => is_numeric($actual) && is_numeric($expected) && $actual > $expected,
            'greater_than_or_equal', '>=' => is_numeric($actual) && is_numeric($expected) && $actual >= $expected,
            'less_than', '<' => is_numeric($actual) && is_numeric($expected) && $actual < $expected,
            'less_than_or_equal', '<=' => is_numeric($actual) && is_numeric($expected) && $actual <= $expected,
            'contains' => is_string($actual) && is_scalar($expected) && str_contains($actual, (string) $expected),
            'in' => is_array($expected) && in_array($actual, $expected, true),
            'exists' => Arr::has($payload, $field),
            default => false,
        };
    }
}
