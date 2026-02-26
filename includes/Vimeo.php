<?php
if (!defined('ABSPATH')) exit;

class PRESS_LMS_Vimeo
{
    const OPT_TOKEN = 'press_lms_vimeo_token';

    public static function init()
    {
        // nada por enquanto
    }

    public static function get_token()
    {
        // Novo padrão: settings array
        if (class_exists('PRESS_LMS_Settings')) {
            $t = PRESS_LMS_Settings::get('vimeo_token', '');
            $t = is_string($t) ? trim($t) : '';
            if ($t !== '') return $t;
        }

        // Fallback: option antiga (caso já tenha salvo assim)
        $token = get_option(self::OPT_TOKEN, '');
        return is_string($token) ? trim($token) : '';
    }


    public static function has_token()
    {
        return self::get_token() !== '';
    }

    public static function parse_video_id($url)
    {
        $url = trim((string)$url);
        if ($url === '') return null;

        // exemplos:
        // https://vimeo.com/123456789
        // https://player.vimeo.com/video/123456789
        // https://vimeo.com/manage/videos/123456789
        // https://vimeo.com/123456789/abcdef (link privado)
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

    public static function get_video_data($video_id)
    {
        $video_id = (int)$video_id;
        if (!$video_id) return new WP_Error('press_vimeo_invalid_id', 'Video ID inválido.');

        // GET /videos/{video_id}
        return self::api_get('/videos/' . $video_id);
    }

    public static function get_embed_html($video_id, $width = 960)
    {
        // Player padrão do Vimeo (funciona para public/unlisted e private embeddable)
        $video_id = (int)$video_id;
        if (!$video_id) return '';

        $src = 'https://player.vimeo.com/video/' . $video_id;
        $w = (int)$width;

        return '<div class="press-vimeo-embed" style="position:relative;border-radius:12px;overflow:hidden;">
            <iframe src="' . esc_url($src) . '" style="position:absolute;top:0;left:0;width:100%;height:100%;" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>
        </div>';
    }
}
