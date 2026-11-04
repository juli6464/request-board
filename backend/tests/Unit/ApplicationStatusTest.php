<?php

namespace Tests\Unit;

use App\Enums\ApplicationStatus as S;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ApplicationStatusTest extends TestCase
{
    public static function matrix(): array
    {
        $allowed = [
            'created>in_review', 'in_review>in_progress', 'in_progress>done',
            'in_review>paused', 'in_progress>paused',
            'paused>in_review', 'paused>in_progress',
        ];

        $cases = [];
        foreach (S::cases() as $from) {
            foreach (S::cases() as $to) {
                $key = "{$from->value}>{$to->value}";
                $cases[$key] = [$from, $to, in_array($key, $allowed, true)];
            }
        }

        return $cases;
    }

    #[DataProvider('matrix')]
    public function test_transition_matrix(S $from, S $to, bool $expected): void
    {
        $this->assertSame($expected, $from->canTransitionTo($to));
    }

    public function test_done_is_the_only_final_state(): void
    {
        $finals = array_filter(S::cases(), fn (S $s) => $s->isFinal());

        $this->assertSame([S::Done], array_values($finals));
    }
}
