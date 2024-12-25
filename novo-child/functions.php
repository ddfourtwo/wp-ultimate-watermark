<?php 

/**
 * Enqueue child scripts and styles.
 */
function novo_child_scripts() {
	wp_enqueue_style( 'novo-child-style', get_stylesheet_uri() );
	wp_enqueue_script( 'novo-child-script', get_stylesheet_directory_uri() . '/script.js', array('jquery'), '', true );
    
    // Enqueue popup purchase script with debug info
    error_log('Enqueuing popup purchase script');
    wp_enqueue_script(
        'novo-popup-purchase',
        get_stylesheet_directory_uri() . '/js/popup-purchase.js',
        array('jquery'),
        '1.0.0',
        true
    );

    // Localize the script with necessary data
    $nonce = wp_create_nonce('ulwm_ajax_nonce');
    error_log('Generated nonce for AJAX: ' . $nonce);
    wp_localize_script('novo-popup-purchase', 'ulwm_vars', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => $nonce
    ));

    // Add custom styles for the purchase button
    wp_add_inline_style('novo-child-style', '
        .yprm-popup-block .buttons .purchase {
            padding: 0 20px;
            height: 40px;
            line-height: 40px;
            text-align: center;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            margin: 15px 0;
            color: #fff;
            font-size: 14px;
            transition: all 0.3s ease;
            cursor: pointer;
            white-space: nowrap;
        }

        .yprm-popup-block .buttons .purchase:hover {
            background: rgba(255, 255, 255, 0.4);
        }

        .popup-icon-shopping-cart:before {
            content: "\e908";
            font-family: "basic-ui" !important;
        }
    ');
}
add_action( 'wp_enqueue_scripts', 'novo_child_scripts', 202 );

// Load custom popup class
require_once get_stylesheet_directory() . '/includes/class-popup.php';

// Replace default popup with our custom one
add_action('init', function() {
    if (class_exists('YPRM_Popup')) {
        remove_action('init', array('YPRM_Popup', 'init'));
        new YPRM_Child_Popup();
    }
});

// Add custom styles for purchase button in popup
add_action('wp_enqueue_scripts', function() {
    wp_add_inline_style('novo-child-style', '
        .yprm-popup .yprm-purchase-button-wrap {
            margin-top: 15px;
            text-align: center;
        }
        
        .yprm-popup .ulwm-purchase-btn {
            background: #c48f56;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
            display: inline-block;
        }
        
        .yprm-popup .ulwm-purchase-btn:hover {
            background: #a87842;
        }
        
        .yprm-popup .ulwm-purchase-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
    ');
});