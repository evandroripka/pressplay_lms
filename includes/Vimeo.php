<?php
if (!defined('ABSPATH')) exit;

/**
 * Vimeo integration helpers for token resolution, metadata lookups, and embeds.
 */
class PRESS_LMS_Vimeo
{
    const OPT_TOKEN = 'press_lms_vimeo_token';

    /**
     * Reserved for future Vimeo-specific hooks.
     */
    public static function init()
    {
    }

    /**
     * Resolve the configured Vimeo token while preserving legacy option support.
     */
    public static function get_token()
    {
        // Read the token from the settings array first.
        if (class_exists('PRESS_LMS_Settings')) {
            $t = PRESS_LMS_Settings::get('vimeo_token', '');
            $t = is_string($t) ? trim($t) : '';
            if ($t !== '') return $t;
        }

        // Keep backward compatibility with the legacy standalone option.
        $token = get_option(self::OPT_TOKEN, '');
        return is_string($token) ? trim($token) : '';
    }


    public static function has_token()
    {
        return self::get_token() !== '';
    }

    /**
     * Extract the Vimeo video identifier from supported public and manager URLs.
     */
    public static function parse_video_id($url)
    {
        $url = trim((string)$url);
        if ($url === '') return null;

        // Supported examples:
        // https://vimeo.com/123456789
        // https://player.vimeo.com/video/123456789
        // https://vimeo.com/manage/videos/123456789
        // https://vimeo.com/123456789/abcdef
        $parts = wp_parse_url($url);
        if (!in_array(strtolower((string) ($parts['host'] ?? '')), ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true)) {
            return null;
        }
        if (preg_match('~^/(?:video/|manage/videos/|ondemand/[^/]+/)?(\d+)(?:/|$)~', (string) ($parts['path'] ?? ''), $m)) {
            return (int)$m[1];
        }

        return null;
    }

    /**
     * Execute a Vimeo API request against the authenticated API base URL.
     */
    public static function api_get($path)
    {
        $token = self::get_token();
        if (!$token) return new WP_Error('press_vimeo_no_token', 'Vimeo token não configurado.');

        $url = 'https://api.vimeo.com' . $path;

        $res = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/vnd.vimeo.*+json;version=3.4',
            ],
        ]);

        if (is_wp_error($res)) return $res;

        $code = (int) wp_remote_retrieve_response_code($res);
        $body = wp_remote_retrieve_body($res);
        $json = json_decode($body, true);

        if ($code >= 400) {
            $msg = is_array($json) && !empty($json['error']) ? $json['error'] : ('Erro Vimeo API: HTTP ' . $code);
            return new WP_Error('press_vimeo_api_error', $msg, ['code' => $code, 'body' => $body]);
        }

        return $json;
    }

    /**
     * Fetch the standard Vimeo metadata payload for a single video.
     */
    public static function get_video_data($video_id)
    {
        $video_id = (int)$video_id;
        if (!$video_id) return new WP_Error('press_vimeo_invalid_id', 'Video ID inválido.');

        // Scope the cache to credentials so replacing a token retries immediately.
        $key = 'presslms_vimeo_api_' . md5($video_id . ':' . self::get_token());
        $cached = get_transient($key);
        if ($cached !== false) return $cached;
        $data = self::api_get('/videos/' . $video_id);
        set_transient($key, $data, is_wp_error($data) ? 120 : HOUR_IN_SECONDS);
        return $data;
    }

    /** Resolve metadata even when an API token cannot access an embeddable video. */
    public static function get_video_metadata(string $url)
    {
        $id = self::parse_video_id($url);
        if (!$id) return new WP_Error('press_vimeo_invalid_id', 'URL Vimeo invalida.');
        $data = self::get_video_data($id);
        if (is_array($data) && (int) ($data['duration'] ?? 0) > 0) return $data;

        $key = 'presslms_vimeo_oembed_' . md5($url . home_url('/'));
        $cached = get_transient($key);
        if ($cached !== false) return $cached;

        // Only Vimeo's fixed endpoint receives the configured URL; never fetch a user-supplied host.
        $response = wp_safe_remote_get(add_query_arg(['url' => $url, 'format' => 'json'], 'https://vimeo.com/api/oembed.json'), [
            'timeout' => 10,
            'headers' => ['Referer' => home_url('/')],
        ]);
        $payload = is_wp_error($response) ? null : json_decode(wp_remote_retrieve_body($response), true);
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200 &&
            is_array($payload) && (int) ($payload['video_id'] ?? 0) === $id && (int) ($payload['duration'] ?? 0) > 0) {
            $data = [
                'duration' => (int) $payload['duration'],
                'name' => (string) ($payload['title'] ?? ''),
                'pictures' => ['sizes' => [['width' => (int) ($payload['thumbnail_width'] ?? 0), 'link' => (string) ($payload['thumbnail_url'] ?? '')]]],
            ];
        } else {
            $data = new WP_Error('press_vimeo_metadata_unavailable', 'Nao foi possivel consultar a duracao. Verifique a privacidade e o dominio de incorporacao no Vimeo.');
        }
        set_transient($key, $data, is_wp_error($data) ? 120 : HOUR_IN_SECONDS);
        return $data;
    }

    /**
     * Pick the first thumbnail that satisfies the target width, with fallback.
     */
    public static function extract_thumbnail_url($data, int $target_width = 640): string
    {
        if (!is_array($data)) {
            return '';
        }

        $sizes = $data['pictures']['sizes'] ?? [];
        if (!is_array($sizes) || empty($sizes)) {
            return '';
        }

        $fallback = '';

        foreach ($sizes as $size) {
            if (!is_array($size)) {
                continue;
            }

            $link = isset($size['link']) ? trim((string) $size['link']) : '';
            if ($link === '') {
                continue;
            }

            $fallback = $link;

            $width = isset($size['width']) ? (int) $size['width'] : 0;
            if ($width >= $target_width) {
                return $link;
            }
        }

        return $fallback;
    }

    /**
     * Return a thumbnail URL for the requested Vimeo video.
     */
    public static function get_video_thumbnail_url(int $video_id, int $target_width = 640): string
    {
        $data = self::get_video_data($video_id);
        if (is_wp_error($data)) {
            return '';
        }

        return self::extract_thumbnail_url($data, $target_width);
    }

    /**
     * Render the standard Vimeo player iframe wrapper.
     */
    public static function get_embed_html($video_id, $width = 960, string $original_url = '', bool $trailer = false)
    {
        // Use the standard Vimeo player for public, unlisted, or embeddable private videos.
        $video_id = (int)$video_id;
        if (!$video_id) return '';

        $src = 'https://player.vimeo.com/video/' . $video_id;
        $parts = wp_parse_url($original_url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (in_array($host, ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true)) {
            parse_str((string) ($parts['query'] ?? ''), $query);
            $hash = is_string($query['h'] ?? null) ? $query['h'] : '';
            if ($hash === '' && preg_match('~/' . $video_id . '/([a-zA-Z0-9]+)/*$~', (string) ($parts['path'] ?? ''), $matches)) {
                $hash = $matches[1];
            }
            if ($hash !== '' && ctype_alnum($hash)) {
                $src = add_query_arg('h', $hash, $src);
            }
        }

        $src = add_query_arg('fullscreen', '1', $src);
        if ($trailer) {
            // Vimeo honors branding parameters only on eligible owner plans.
            $src = add_query_arg('vimeo_logo', '0', $src);
        }
        return '<div class="press-vimeo-embed" style="position:relative;aspect-ratio:16/9;border-radius:12px;overflow:hidden;">
            <iframe title="' . ($trailer ? 'Trailer do curso' : 'Video da aula') . '" src="' . esc_url($src) . '" style="position:absolute;top:0;left:0;width:100%;height:100%;" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen webkitallowfullscreen mozallowfullscreen></iframe>
        </div>';
    }

    /**
     * Return the video duration in seconds from the Vimeo payload.
     */
    public static function get_video_duration_seconds(int $video_id): int
    {
        $data = self::get_video_data($video_id);
        if (is_wp_error($data)) return 0;

        $duration = isset($data['duration']) ? (int) $data['duration'] : 0; // Seconds.
        return max(0, $duration);
    }

    /**
     * Return the remote modification timestamp so duration and thumbnail caches can be refreshed.
     */
    public static function get_video_modified_time(int $video_id): string
    {
        $data = self::get_video_data($video_id);
        if (is_wp_error($data)) return '';

        $t = isset($data['modified_time']) ? (string) $data['modified_time'] : '';
        return trim($t);
    }
}
