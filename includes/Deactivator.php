<?php
if (!defined('ABSPATH')) exit;

class MLB_LMS_Deactivator {
    public static function deactivate() {
        flush_rewrite_rules();
    }
}
