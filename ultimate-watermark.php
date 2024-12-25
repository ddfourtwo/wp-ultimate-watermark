<?php
/*
Plugin Name: Ultimate Watermark
Description: Image Watermark plugin for WordPress media.
Version: 1.0.11
Author: MantraBrain
Author URI: https://mantrabrain.com/
License: MIT License
License URI: http://opensource.org/licenses/MIT
Text Domain: ultimate-watermark
Domain Path: /languages
Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

// exit if accessed directly
if (!defined('ABSPATH'))
    exit;

define('ULTIMATE_WATERMARK_FILE', __FILE__);
define('ULTIMATE_WATERMARK_VERSION', '1.0.11');
define('ULTIMATE_WATERMARK_URI', plugins_url('', ULTIMATE_WATERMARK_FILE));
define('ULTIMATE_WATERMARK_DIR', plugin_dir_path(ULTIMATE_WATERMARK_FILE));
define('ULTIMATE_WATERMARK_PLUGIN_PATH', ULTIMATE_WATERMARK_DIR);

include_once plugin_dir_path(ULTIMATE_WATERMARK_FILE) . 'vendor/autoload.php';

/**
 * Get instance of main class.
 *
 * @return object Instance
 */

use Ultimate_Watermark\Init;

function ultimate_watermark()
{
    static $instance;

    // first call to instance() initializes the plugin
    if ($instance === null || !($instance instanceof Init))
        $instance = Init::instance();

    return $instance;
}

ultimate_watermark();

// Initialize WooCommerce integration
require_once ULTIMATE_WATERMARK_PLUGIN_PATH . 'includes/WooCommerce/Product_Integration.php';
require_once ULTIMATE_WATERMARK_PLUGIN_PATH . 'includes/WooCommerce/Original_Image_Product.php';
require_once ULTIMATE_WATERMARK_PLUGIN_PATH . 'includes/WooCommerce/Download_Handler.php';

// Check if WooCommerce is active
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    new Ultimate_Watermark\WooCommerce\Product_Integration();
    new Ultimate_Watermark\WooCommerce\Download_Handler();
}

// Enqueue frontend scripts
add_action('wp_enqueue_scripts', function() {
    if (!is_admin()) {
        wp_enqueue_style(
            'ulwm-frontend-image-purchase',
            plugins_url('assets/css/frontend-image-purchase.css', __FILE__),
            array(),
            ULTIMATE_WATERMARK_VERSION
        );

        wp_enqueue_script(
            'ulwm-frontend-image-purchase',
            plugins_url('assets/js/frontend-image-purchase.js', __FILE__),
            array('jquery'),
            ULTIMATE_WATERMARK_VERSION,
            true
        );

        wp_localize_script('ulwm-frontend-image-purchase', 'ulwm_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ulwm_ajax_nonce')
        ));
    }
});