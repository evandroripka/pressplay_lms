<?php
if (!defined('ABSPATH')) exit;

class PRESS_LMS_Deactivator {
    public static function deactivate() {
        flush_rewrite_rules();
    }
}
