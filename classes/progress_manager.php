<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <https://www.gnu.org/licenses/>.

/**
 * Video progress storage.
 *
 * @package    mod_videodiscussion
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videodiscussion;

/**
 * Class progress_manager.
 */
class progress_manager {
    /** Maximum accepted video duration in seconds. */
    private const MAX_DURATION = 86400;

    /** Maximum number of intervals accepted in one request. */
    private const MAX_SEGMENTS = 5000;

    /**
     * Stores progress by merging watched intervals.
     *
     * @param int $activityid
     * @param int $userid
     * @param float $duration
     * @param float $lastposition
     * @param array $segments
     * @return \stdClass
     */
    public function save(int $activityid, int $userid, float $duration, float $lastposition, array $segments): \stdClass {
        global $DB;

        $now = time();
        $duration = min(self::MAX_DURATION, max(0, $duration));
        $lastposition = max(0, $duration > 0 ? min($lastposition, $duration) : $lastposition);
        $segments = array_slice($segments, -self::MAX_SEGMENTS);

        $record = $DB->get_record('videodiscussion_progress', [
            'videodiscussionid' => $activityid,
            'userid' => $userid,
        ]);

        $existing = $record ? (json_decode((string)$record->watchedsegments, true) ?: []) : [];
        $existing = $this->merge($existing, $duration);
        $candidate = $this->merge(array_merge($existing, $segments), $duration);

        $existingunique = $this->total_duration($existing);
        $candidateunique = $this->total_duration($candidate);
        $elapsed = $record ? max(1, $now - (int)$record->timemodified) : 10;
        $allowednew = min(120.0, max(20.0, ($elapsed * 1.5) + 5.0));

        if (($candidateunique - $existingunique) > $allowednew) {
            $merged = $existing;
            $unique = $existingunique;
        } else {
            $merged = $candidate;
            $unique = $candidateunique;
        }

        $percent = $duration > 0 ? min(100, ($unique / $duration) * 100) : 0;

        if (!$record) {
            $record = (object)[
                'videodiscussionid' => $activityid,
                'userid' => $userid,
                'timecreated' => $now,
            ];
        }
        $record->duration = $duration;
        $record->lastposition = $lastposition;
        $record->uniquewatched = $unique;
        $record->percent = $percent;
        $record->watchedsegments = json_encode($merged);
        $record->timemodified = $now;

        if (empty($record->id)) {
            $record->id = $DB->insert_record('videodiscussion_progress', $record);
        } else {
            $DB->update_record('videodiscussion_progress', $record);
        }

        return $record;
    }

    /**
     * Calculates the total duration represented by merged intervals.
     *
     * @param array $segments
     * @return float
     */
    private function total_duration(array $segments): float {
        $total = 0.0;
        foreach ($segments as $segment) {
            $total += max(0, (float)$segment[1] - (float)$segment[0]);
        }
        return $total;
    }

    /**
     * Merges and normalises intervals.
     *
     * @param array $segments
     * @param float $duration
     * @return array
     */
    private function merge(array $segments, float $duration): array {
        $clean = [];
        foreach ($segments as $segment) {
            if (!is_array($segment) || count($segment) < 2 || !is_numeric($segment[0]) || !is_numeric($segment[1])) {
                continue;
            }
            $start = max(0, (float)$segment[0]);
            $end = max($start, (float)$segment[1]);
            if ($duration > 0) {
                $start = min($start, $duration);
                $end = min($end, $duration);
            }
            if (($end - $start) > 0 && ($end - $start) <= 30) {
                $clean[] = [$start, $end];
            }
        }

        usort($clean, static fn($a, $b) => $a[0] <=> $b[0]);
        $merged = [];
        foreach ($clean as $segment) {
            if (!$merged || $segment[0] > $merged[count($merged) - 1][1] + 0.75) {
                $merged[] = $segment;
                continue;
            }
            $merged[count($merged) - 1][1] = max($merged[count($merged) - 1][1], $segment[1]);
        }

        return array_values($merged);
    }
}
