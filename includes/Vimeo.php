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
        if (preg_match('~vimeo\.com/(?:video/|manage/videos/)?(\d+)~i', $url, $m)) {
            return (int)$m[1];
        }

        if (preg_match('~player\.vimeo\.com/video/(\d+)~i', $url, $m)) {
            return (int)$m[1];
        }
        if (preg_match('~vimeo\.com/(?:video/|manage/videos/|ondemand/[^/]+/)?(\d+)~i', $url, $m)) {
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

        // Request the standard Vimeo video payload.
        return self::api_get('/videos/' . $video_id);
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
    public static function get_embed_html($video_id, $width = 960)
    {
        // Use the standard Vimeo player for public, unlisted, or embeddable private videos.
        $video_id = (int)$video_id;
        if (!$video_id) return '';

        $src = 'https://player.vimeo.com/video/' . $video_id;
        $w = (int)$width;

        return '<div class="press-vimeo-embed" style="position:relative;border-radius:12px;overflow:hidden;">
            <iframe src="' . esc_url($src) . '" style="position:absolute;top:0;left:0;width:100%;height:100%;" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>
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
