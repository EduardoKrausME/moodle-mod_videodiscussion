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
 * Video player configuration helper.
 *
 * @package mod_videodiscussion
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videodiscussion;

use context_module;
use moodle_url;

/**
 * Class player.
 */
class player {
    /**
     * Builds the player configuration.
     *
     * @param \stdClass $activity
     * @param context_module $context
     * @return array
     */
    public static function config(\stdClass $activity, context_module $context): array {
        $source = $activity->videosource;
        $config = [
            'type' => $source,
            'url' => '',
            'html5' => false,
            'youtube' => false,
            'vimeo' => false,
        ];

        if ($source === 'upload') {
            $fs = get_file_storage();
            $files = $fs->get_area_files($context->id, 'mod_videodiscussion', 'video', 0, 'id', false);
            $file = reset($files);
            if ($file) {
                $config['url'] = moodle_url::make_pluginfile_url(
                    $context->id,
                    'mod_videodiscussion',
                    'video',
                    0,
                    $file->get_filepath(),
                    $file->get_filename()
                )->out(false);
            }
            $config['html5'] = true;
            return $config;
        }

        if ($source === 'youtube') {
            $id = self::youtube_id((string)$activity->videourl);
            if ($id !== '') {
                $config['url'] = 'https://www.youtube.com/embed/' . rawurlencode($id) . '?enablejsapi=1&rel=0';
            }
            $config['youtube'] = true;
            return $config;
        }

        if ($source === 'vimeo') {
            $id = self::vimeo_id((string)$activity->videourl);
            if ($id !== '') {
                $config['url'] = 'https://player.vimeo.com/video/' . rawurlencode($id) . '?api=1';
            }
            $config['vimeo'] = true;
            return $config;
        }

        $config['url'] = (string)$activity->videourl;
        $config['html5'] = true;
        return $config;
    }

    /**
     * youtube_id
     *
     * @param string $url
     * @return string
     */
    private static function youtube_id(string $url): string {
        if (preg_match('~(?:youtu\\.be/|youtube\\.com/(?:watch\\?v=|embed/|shorts/))([A-Za-z0-9_-]{6,})~', $url, $m)) {
            return $m[1];
        }
        return preg_match('/^[A-Za-z0-9_-]{6,}$/', $url) ? $url : '';
    }

    /**
     * vimeo_id
     *
     * @param string $url
     * @return string
     */
    private static function vimeo_id(string $url): string {
        if (preg_match('~vimeo\\.com/(?:video/)?([0-9]+)~', $url, $m)) {
            return $m[1];
        }
        return ctype_digit($url) ? $url : '';
    }
}
