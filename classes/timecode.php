<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Timecode helpers.
 *
 * @package mod_videodiscussion
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videodiscussion;

/**
 * Class timecode.
 */
class timecode {
    /**
     * Converts seconds to H:MM:SS or MM:SS.
     *
     * @param float $seconds
     * @return string
     */
    public static function format(float $seconds): string {
        $seconds = max(0, (int)round($seconds));
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;
        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        }
        return sprintf('%02d:%02d', $minutes, $secs);
    }

    /**
     * Parses seconds, MM:SS or HH:MM:SS.
     *
     * @param string $value
     * @return float|null
     */
    public static function parse(string $value): ?float {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return max(0, (float)$value);
        }
        $parts = array_map('trim', explode(':', $value));
        if (count($parts) < 2 || count($parts) > 3) {
            return null;
        }
        foreach ($parts as $part) {
            if ($part === '' || !is_numeric($part)) {
                return null;
            }
        }
        if (count($parts) === 2) {
            return max(0, ((float)$parts[0] * 60) + (float)$parts[1]);
        }
        return max(0, ((float)$parts[0] * 3600) + ((float)$parts[1] * 60) + (float)$parts[2]);
    }
}
