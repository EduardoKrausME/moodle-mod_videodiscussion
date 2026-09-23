// This file is part of Moodle - http://moodle.org/
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
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * main.js
 *
 * @package   mod_videodiscussion
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/notification'], function(Ajax, Notification) {

    const loadScript = (src, test) => new Promise((resolve, reject) => {
        if (test()) {
            resolve();
            return;
        }

        let script = document.querySelector(`script[src="${src}"]`);
        if (!script) {
            script = document.createElement('script');
            script.src = src;
            script.async = true;
            document.head.appendChild(script);
        }

        const started = Date.now();
        const timer = window.setInterval(() => {
            if (test()) {
                window.clearInterval(timer);
                resolve();
            } else if (Date.now() - started > 15000) {
                window.clearInterval(timer);
                reject(new Error(`Unable to load ${src}`));
            }
        }, 100);
    });

    class Html5Adapter {
        constructor(video) {
            this.video = video;
        }

        async ready() {
            if (this.video.readyState >= 1) {
                return;
            }

            await new Promise(resolve => {
                this.video.addEventListener('loadedmetadata', resolve, {once: true});
            });
        }

        async currentTime() {
            return this.video.currentTime || 0;
        }

        async duration() {
            return Number.isFinite(this.video.duration) ? this.video.duration : 0;
        }

        async seek(seconds) {
            this.video.currentTime = Math.max(0, seconds);
        }

        async playing() {
            return !this.video.paused && !this.video.ended;
        }
    }

    class YoutubeAdapter {
        constructor(iframe) {
            this.iframe = iframe;
            this.player = null;
        }

        async ready() {
            await loadScript(
                'https://www.youtube.com/iframe_api',
                () => window.YT && window.YT.Player
            );

            this.player = await new Promise(resolve => {
                const player = new window.YT.Player(this.iframe, {
                    events: {
                        onReady: () => resolve(player)
                    }
                });
            });
        }

        async currentTime() {
            return this.player ? this.player.getCurrentTime() || 0 : 0;
        }

        async duration() {
            return this.player ? this.player.getDuration() || 0 : 0;
        }

        async seek(seconds) {
            if (this.player) {
                this.player.seekTo(Math.max(0, seconds), true);
            }
        }

        async playing() {
            return this.player
                ? this.player.getPlayerState() === window.YT.PlayerState.PLAYING
                : false;
        }
    }

    class VimeoAdapter {
        constructor(iframe) {
            this.iframe = iframe;
            this.player = null;
            this.isPlaying = false;
        }

        async ready() {
            await loadScript(
                'https://player.vimeo.com/api/player.js',
                () => window.Vimeo && window.Vimeo.Player
            );

            this.player = new window.Vimeo.Player(this.iframe);

            this.player.on('play', () => {
                this.isPlaying = true;
            });

            this.player.on('pause', () => {
                this.isPlaying = false;
            });

            this.player.on('ended', () => {
                this.isPlaying = false;
            });

            await this.player.ready();
        }

        async currentTime() {
            return this.player ? this.player.getCurrentTime() : 0;
        }

        async duration() {
            return this.player ? this.player.getDuration() : 0;
        }

        async seek(seconds) {
            if (this.player) {
                await this.player.setCurrentTime(Math.max(0, seconds));
            }
        }

        async playing() {
            return this.isPlaying;
        }
    }

    const mergeSegments = segments => {
        const clean = segments
            .filter(segment => (
                Array.isArray(segment) &&
                segment.length >= 2 &&
                segment[1] > segment[0]
            ))
            .map(segment => [
                Number(segment[0]),
                Number(segment[1])
            ])
            .sort((a, b) => a[0] - b[0]);

        const merged = [];

        clean.forEach(segment => {
            if (
                !merged.length ||
                segment[0] > merged[merged.length - 1][1] + 0.75
            ) {
                merged.push(segment);
            } else {
                merged[merged.length - 1][1] = Math.max(
                    merged[merged.length - 1][1],
                    segment[1]
                );
            }
        });

        return merged;
    };

    const formatTime = seconds => {
        const total = Math.max(0, Math.round(seconds));
        const hours = Math.floor(total / 3600);
        const minutes = Math.floor((total % 3600) / 60);
        const secs = total % 60;

        if (hours > 0) {
            return `${hours}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }

        return `${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    };

    const makeAdapter = root => {
        const html5 = root.querySelector('[data-region="html5-player"]');
        if (html5) {
            return new Html5Adapter(html5);
        }

        const youtube = root.querySelector('[data-region="youtube-player"]');
        if (youtube) {
            return new YoutubeAdapter(youtube);
        }

        const vimeo = root.querySelector('[data-region="vimeo-player"]');
        if (vimeo) {
            return new VimeoAdapter(vimeo);
        }

        return null;
    };

    const positionMarkers = async (root, adapter) => {
        const duration = await adapter.duration();

        if (!duration) {
            return;
        }

        root.querySelectorAll('[data-vd-marker]').forEach(marker => {
            const time = Number(marker.dataset.time || 0);

            marker.style.left =
                `${Math.min(100, Math.max(0, (time / duration) * 100))}%`;
        });
    };

    const init = async () => {
        const root = document.querySelector(
            '[data-region="videodiscussion"]'
        );

        if (!root) {
            return;
        }

        let config = {};

        try {
            config = JSON.parse(root.dataset.config || '{}');
        } catch (error) {
            Notification.exception(error);
            return;
        }

        const adapter = makeAdapter(root);

        if (!adapter) {
            return;
        }

        try {
            await adapter.ready();
        } catch (error) {
            Notification.exception(error);
            return;
        }

        if (Number(config.lastposition) > 0) {
            await adapter.seek(Number(config.lastposition));
        }

        await positionMarkers(root, adapter);

        root.querySelectorAll(
            '[data-vd-seek], [data-vd-marker]'
        ).forEach(element => {
            element.addEventListener('click', async event => {
                event.preventDefault();

                await adapter.seek(
                    Number(element.dataset.time || 0)
                );

                const thread = element.closest('.vd-thread');

                if (thread) {
                    thread.scrollIntoView({
                        behavior: 'smooth',
                        block: 'nearest'
                    });
                }
            });
        });

        const commentButton = root.querySelector(
            '[data-action="comment-now"]'
        );

        if (commentButton) {
            commentButton.addEventListener('click', async () => {
                const time = await adapter.currentTime();

                const form = root.querySelector(
                    '[data-region="new-discussion-form"]'
                );

                const timeInput = root.querySelector(
                    '[data-region="new-timepoint"]'
                );

                const timeLabel = root.querySelector(
                    '[data-region="new-timecode"]'
                );

                if (form && timeInput && timeLabel) {
                    timeInput.value = String(time);
                    timeLabel.textContent = formatTime(time);
                    form.classList.remove('d-none');

                    const subject = form.querySelector(
                        'input[name="subject"]'
                    );

                    if (subject) {
                        subject.focus();
                    }
                }
            });
        }

        let segments = Array.isArray(config.segments)
            ? config.segments
            : [];

        let lastSample = null;
        let dirty = false;
        let saving = false;

        const saveProgress = async () => {
            if (!dirty || saving) {
                return;
            }

            saving = true;

            try {
                const duration = await adapter.duration();
                const position = await adapter.currentTime();

                const result = await Ajax.call([{
                    methodname: 'mod_videodiscussion_save_progress',
                    args: {
                        cmid: Number(config.cmid),
                        duration: duration,
                        lastposition: position,
                        segmentsjson: JSON.stringify(segments)
                    }
                }])[0];

                const label = root.querySelector(
                    '[data-region="progress-label"]'
                );

                const bar = root.querySelector(
                    '[data-region="progress-bar"]'
                );

                if (label) {
                    label.textContent =
                        `${Number(result.percent).toFixed(2)}%`;
                }

                if (bar) {
                    bar.style.width =
                        `${Math.min(
                            100,
                            Math.max(0, Number(result.percent))
                        )}%`;
                }

                dirty = false;
            } catch (error) {
                Notification.exception(error);
            } finally {
                saving = false;
            }
        };

        window.setInterval(async () => {
            try {
                const position = await adapter.currentTime();
                const playing = await adapter.playing();

                if (playing && lastSample !== null) {
                    const delta = position - lastSample;

                    if (delta > 0.1 && delta <= 2.5) {
                        segments.push([
                            lastSample,
                            position
                        ]);

                        segments = mergeSegments(segments);
                        dirty = true;
                    }
                }

                lastSample = position;
            } catch (error) {
                lastSample = null;
            }
        }, 1000);

        window.setInterval(saveProgress, 10000);

        window.addEventListener('pagehide', () => {
            saveProgress();
        });
    };

    return {
        init: init
    };
});
