<?php

namespace Ultimate_Watermark\Admin;

use Ultimate_Watermark\Admin\Pages\Settings_Page;
use Ultimate_Watermark\Admin\Settings\Settings_Main;

class Menu
{
    public function __construct()
    {
        add_action('admin_menu', array($this, 'license_menu'), 55);

    }

    public function license_menu()
    {
        $settings_page = add_menu_page(
            __('Ultimate Watermark', 'ultimate-watermark'),
            __('Watermark', 'ultimate-watermark'),
            'administrator',
            'ultimate-watermark',
            array($this, 'watermark_page'),
            'dashicons-images-alt',
            3

        );

        add_action('load-' . $settings_page, array($this, 'settings_page_init'));


    }

    public function watermark_page()
    {
        Settings_Main::output();
    }

    public function settings_page_init()
    {
        global $current_tab, $current_section;

        // Include settings pages.
        Settings_Main::get_settings_pages();

        // Get current tab/section.
        $current_tab = empty($_GET['tab']) ? 'general' : sanitize_title(wp_unslash($_GET['tab'])); // WPCS: input var okay, CSRF ok.
        $current_section = empty($_REQUEST['section']) ? '' : sanitize_title(wp_unslash($_REQUEST['section'])); // WPCS: input var okay, CSRF ok.

        // Save settings if data has been posted.
        if ('' !== $current_section && apply_filters("ultimate_watermark_save_settings_{$current_tab}_{$current_section}", !empty($_POST['save']))) { // WPCS: input var okay, CSRF ok.
            Settings_Main::save();
        } elseif ('' === $current_section && apply_filters("ultimate_watermark_save_settings_{$current_tab}", !empty($_POST['save']))) { // WPCS: input var okay, CSRF ok.
            Settings_Main::save();
        }

        // Add any posted messages.
        if (!empty($_GET['ultimate_watermark_error'])) { // WPCS: input var okay, CSRF ok.
            Settings_Main::add_error(wp_kses_post(wp_unslash($_GET['ultimate_watermark_error']))); // WPCS: input var okay, CSRF ok.
        }

        if (!empty($_GET['ultimate_watermark_message'])) { // WPCS: input var okay, CSRF ok.
            Settings_Main::add_message(wp_kses_post(wp_unslash($_GET['ultimate_watermark_message']))); // WPCS: input var okay, CSRF ok.
        }

        do_action('ultimate_watermark_settings_page_init');


    }
}