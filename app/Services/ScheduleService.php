<?php

namespace App\Services;

use App\Repositories\ScheduleRepository;
use Carbon\CarbonImmutable;

final class ScheduleService
{
    public function __construct(private readonly ScheduleRepository $scheduleRepository)
    {
    }

    public function isStudentEnrolledInSemester(string $studentId, string $semesterId): bool
    {
        return $this->scheduleRepository->isStudentEnrolledInSemester($studentId, $semesterId);
    }

    public function sessions(string $studentId, ?string $semesterId): array
    {
        return array_map(static function (array $session): array {
            $url = $session['meeting_url'] ?? null;
            $parts = is_string($url) ? parse_url($url) : false;
            if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
                $session['meeting_url'] = null;
            }
            return $session;
        }, $this->scheduleRepository->weeklySessions($studentId, $semesterId));
    }

    /** @param list<array<string, mixed>> $sessions @return array{weekStart: string, weekEnd: string, sessions: list<array<string, mixed>>} */
    public function attachDates(array $sessions, string $weekOf): array
    {
        $timezone = (string) config('services.campus.timezone', 'Asia/Manila');
        $start = CarbonImmutable::parse($weekOf, $timezone)->startOfWeek(CarbonImmutable::MONDAY);
        $end = $start->addDays(6);
        $datedSessions = array_map(static function (array $session) use ($start): array {
            $weekday = (int) $session['day_of_week'];
            $offset = $weekday === 0 ? 6 : $weekday - 1;
            $session['scheduled_date'] = $start->addDays($offset)->toDateString();
            return $session;
        }, $sessions);

        return ['weekStart' => $start->toDateString(), 'weekEnd' => $end->toDateString(), 'sessions' => $datedSessions];
    }
}
