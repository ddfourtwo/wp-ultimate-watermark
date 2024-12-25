<?php

class YPRM_Child_Popup extends YPRM_Popup {
    public function __construct() {
        parent::__construct();
        
        // Add filter for popup buttons
        add_filter('yprm_popup_buttons', array($this, 'add_purchase_button'), 10, 2);
    }

    public function add_purchase_button($buttons_html, $attachment_id) {
        if (!$attachment_id) {
            return $buttons_html;
        }

        // Find the closing div of the buttons section
        $pos = strrpos($buttons_html, '</div>');
        if ($pos !== false) {
            // Insert our purchase button before the closing div
            $purchase_button = '<div class="purchase popup-icon-shopping-cart" data-attachment-id="' . esc_attr($attachment_id) . '" style="opacity: 1;"></div>';
            $buttons_html = substr_replace($buttons_html, $purchase_button, $pos, 0);
        }

        return $buttons_html;
    }
}

// Add custom CSS to match theme styling
add_action('wp_head', function() {
    ?>
    <style>
        .popup-icon-shopping-cart:before {
            content: "\e908"; /* Using a cart icon from the theme's icon font */
        }
        
        .yprm-popup .purchase {
            cursor: pointer;
            width: 40px;
            height: 40px;
            line-height: 40px;
            text-align: center;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            margin: 15px 0;
            color: #fff;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .yprm-popup .purchase:hover {
            background: rgba(255, 255, 255, 0.4);
        }
    </style>
    <?php
});
