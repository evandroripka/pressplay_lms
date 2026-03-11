<?php
if (!defined('ABSPATH')) exit;

/**
 * Normalize lesson materials, infer file types, and resolve SVG icons.
 */
class PRESS_LMS_Materials
{
    /**
     * Reserved for future hooks.
     */
    public static function init()
    {
        // No runtime hooks are required for this helper today.
    }

    /**
     * Map material kinds to SVG files in assets/svg.
     */
    private static function icon_map()
    {
        return [
            'pdf'         => 'pdf.svg',
            'excel'       => 'excel.svg',
            'word'        => 'word.svg',
            'powerpoint'  => 'power point.svg',
            'img'         => 'img.svg',
            'video'       => 'video.svg',
            'music'       => 'music.svg',
            'zipado'      => 'zipado.svg',
            '3d'          => '3d.svg',
            'txt'         => 'txt.svg',

            'www'         => 'www.svg',

            'others'      => 'others.svg',
        ];
    }

    /**
     * Group known file extensions by material kind.
     */
    private static function ext_map()
    {
        return [
            'pdf' => ['pdf'],

            'excel' => ['xls', 'xlsx', 'csv', 'ods'],
            'word' => ['doc', 'docx', 'odt', 'rtf'],
            'powerpoint' => ['ppt', 'pptx', 'odp', 'key'],

            'img' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'tiff', 'tif', 'heic'],
            'video' => ['mp4', 'mov', 'm4v', 'webm', 'mkv', 'avi'],
            'music' => ['mp3', 'wav', 'aac', 'ogg', 'flac', 'm4a'],

            'zipado' => ['zip', 'rar', '7z', 'tar', 'gz', 'tgz'],

            '3d' => ['fbx', 'obj', 'glb', 'gltf', 'blend', 'stl', 'dae'],

            'txt' => ['txt', 'md', 'log', 'ini', 'json', 'xml', 'yml', 'yaml'],
        ];
    }

    /**
     * Return the SVG icon URL for a given material kind.
     */
    public static function get_icon_url($kind)
    {
        $kind = self::sanitize_kind($kind);
        $map = self::icon_map();
        $file = $map[$kind] ?? $map['others'];

        if (!defined('PRESS_LMS_URL')) return '';
        return trailingslashit(PRESS_LMS_URL) . 'assets/svg/' . rawurlencode($file);
    }

    /**
     * Return an admin-safe SVG <img> tag instead of inline markup.
     */
    public static function get_icon_img_html($kind, $size = 22)
    {
        $src = self::get_icon_url($kind);
        if (!$src) return '';

        $size = (int)$size;
        if ($size <= 0) $size = 22;

        return '<img src="' . esc_url($src) . '" alt="" width="' . esc_attr($size) . '" height="' . esc_attr($size) . '" style="display:block;" />';
    }

    /**
     * Detect the material kind from the attachment first, then from the URL.
     */
    public static function detect_kind($url = '', $attachment_id = 0)
    {
        $attachment_id = (int) $attachment_id;
        $url = trim((string)$url);

        // Resolve the file type from the attachment when available.
        if ($attachment_id > 0) {
            $file = get_attached_file($attachment_id);
            if ($file) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                $kind = self::kind_from_extension($ext);
                if ($kind) return $kind;
            }

            $mime = (string) get_post_mime_type($attachment_id);
            $kind = self::kind_from_mime($mime);
            if ($kind) return $kind;

            $att_url = (string) wp_get_attachment_url($attachment_id);
            if ($att_url) {
                $ext = self::extract_extension_from_url($att_url);
                $kind = self::kind_from_extension($ext);
                if ($kind) return $kind;
            }
        }

        // Fall back to the provided URL.
        if ($url !== '') {
            $ext = self::extract_extension_from_url($url);
            $kind = self::kind_from_extension($ext);
            if ($kind) return $kind;

            // Treat generic HTTP(S) links as web resources.
            if (preg_match('~^https?://~i', $url)) {
                return 'www';
            }
        }

        return 'others';
    }

    /**
     * Normalize and validate the lesson materials array.
     */
    public static function normalize_items($items)
    {
        if (!is_array($items)) return [];

        $out = [];

        foreach ($items as $item) {
            if (!is_array($item)) continue;

            $type = isset($item['type']) ? sanitize_key($item['type']) : '';
            $type = in_array($type, ['file', 'link'], true) ? $type : '';

            $name = sanitize_text_field($item['name'] ?? '');
            $url  = isset($item['url']) ? esc_url_raw((string)$item['url']) : '';
            $attachment_id = isset($item['attachment_id']) ? (int)$item['attachment_id'] : 0;

            $id = sanitize_text_field($item['id'] ?? '');
            if ($id === '') $id = '';

            if ($type === 'file') {
                if ($attachment_id <= 0 && $url === '') continue;

                if ($attachment_id > 0 && $url === '') {
                    $att_url = (string) wp_get_attachment_url($attachment_id);
                    if ($att_url) $url = esc_url_raw($att_url);
                }
            } elseif ($type === 'link') {
                if ($url === '') continue;
            } else {
                continue;
            }

            $kind = self::detect_kind($url, $attachment_id);

            $out[] = [
                'id'            => $id,
                'type'          => $type,
                'name'          => $name,
                'url'           => $url,
                'attachment_id' => $attachment_id,
                'kind'          => $kind,
            ];
        }

        return array_values($out);
    }

    // Internal helpers.

    private static function sanitize_kind($kind)
    {
        $kind = sanitize_key((string)$kind);
        $allowed = array_keys(self::icon_map());
        return in_array($kind, $allowed, true) ? $kind : 'others';
    }

    private static function extract_extension_from_url($url)
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        return strtolower(pathinfo($path, PATHINFO_EXTENSION));
    }

    private static function kind_from_extension($ext)
    {
        $ext = strtolower((string)$ext);
        if ($ext === '') return null;

        $map = self::ext_map();

        // Keep the extension lookup order deterministic.
        foreach (['pdf','excel','word','powerpoint','img','video','music','zipado','3d','txt'] as $kind) {
            if (!empty($map[$kind]) && in_array($ext, $map[$kind], true)) {
                return $kind;
            }
        }

        return null;
    }

    private static function kind_from_mime($mime)
    {
        $mime = strtolower(trim((string)$mime));
        if ($mime === '') return null;

        if (str_contains($mime, 'pdf')) return 'pdf';
        if (str_contains($mime, 'spreadsheet') || str_contains($mime, 'excel') || $mime === 'text/csv') return 'excel';
        if (str_contains($mime, 'word')) return 'word';
        if (str_contains($mime, 'presentation') || str_contains($mime, 'powerpoint')) return 'powerpoint';
        if (str_starts_with($mime, 'image/')) return 'img';
        if (str_starts_with($mime, 'video/')) return 'video';
        if (str_starts_with($mime, 'audio/')) return 'music';
        if (str_contains($mime, 'zip') || str_contains($mime, 'rar') || str_contains($mime, '7z')) return 'zipado';

        // Treat HTML resources as generic web links.
        if (str_contains($mime, 'text/html')) return 'www';

        if (str_contains($mime, 'json') || str_contains($mime, 'xml') || str_contains($mime, 'text/plain')) return 'txt';

        return null;
    }
}
