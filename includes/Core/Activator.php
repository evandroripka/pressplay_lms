<?php
if (!defined('ABSPATH')) exit;

class PRESS_LMS_Activator
{
    public static function activate()
    {
        // Create or update custom database tables.
        PRESS_LMS_Database::migrate();

        // Register the student role without changing the site's default role.
        PRESS_LMS_Roles::add_roles();

        // Register post types before flushing rewrite rules.
        if (class_exists('PRESS_LMS_CPT')) {
            PRESS_LMS_CPT::register_course();
            PRESS_LMS_CPT::register_lesson();
        }

        // Register custom rewrite rules used by the LMS frontend.
        if (class_exists('PRESS_LMS_Rewrite')) {
            PRESS_LMS_Rewrite::add_rules();
        }

        if (class_exists('PRESS_LMS_Woo') && method_exists('PRESS_LMS_Woo', 'register_account_endpoint')) {
            PRESS_LMS_Woo::register_account_endpoint();
        }

        // Account requirements are scoped to course carts by PRESS_LMS_Woo.
        // The LMS registration form assigns its own role; no global setting is needed.

        // Refresh rewrite rules once activation is complete.
        flush_rewrite_rules();

        if (class_exists('PRESS_LMS_Rewrite') && method_exists('PRESS_LMS_Rewrite', 'get_schema_version')) {
            update_option('press_lms_rewrite_schema_version', PRESS_LMS_Rewrite::get_schema_version(), false);
        }
    }
}
