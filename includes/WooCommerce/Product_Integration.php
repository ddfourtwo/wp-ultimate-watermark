<?php

namespace Ultimate_Watermark\WooCommerce;

class Product_Integration {
    public function __construct() {
        // Register AJAX handlers
        add_action('wp_ajax_ulwm_add_to_cart', array($this, 'add_image_to_cart'));
        add_action('wp_ajax_nopriv_ulwm_add_to_cart', array($this, 'add_image_to_cart'));
        add_action('wp_ajax_ulwm_get_attachment_id', array($this, 'get_attachment_id_from_url'));
        add_action('wp_ajax_nopriv_ulwm_get_attachment_id', array($this, 'get_attachment_id_from_url'));
        
        // Other actions
        add_action('woocommerce_order_status_completed', array($this, 'handle_completed_order'));
        add_action('woocommerce_thankyou', array($this, 'add_download_links'), 10, 1);
        add_action('before_delete_post', array($this, 'cleanup_symlink'));
        
        // Debug log on init to verify class is loaded
        add_action('init', function() {
            error_log('Ultimate_Watermark\WooCommerce\Product_Integration initialized');
            error_log('AJAX actions registered: ulwm_add_to_cart, ulwm_get_attachment_id');
        });
        
        add_action('init', array($this, 'register_image_product_type'));
        add_filter('woocommerce_data_stores', array($this, 'register_data_store'));
    }

    public function register_image_product_type() {
        require_once plugin_dir_path(__FILE__) . 'Original_Image_Product.php';
    }

    public function register_data_store($stores) {
        $stores['product-original-image'] = 'WC_Product_Data_Store_CPT';
        return $stores;
    }

    public function add_image_to_cart() {
        // Verify nonce first
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ulwm_ajax_nonce')) {
            error_log('Nonce verification failed for add_image_to_cart');
            wp_send_json_error('Invalid security token');
            return;
        }

        // Get and validate attachment ID
        $attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;
        error_log('Processing add to cart for attachment ID: ' . $attachment_id);
        
        if (!$attachment_id) {
            error_log('Invalid attachment ID received');
            wp_send_json_error('Invalid attachment ID');
            return;
        }

        try {
            $product_id = $this->get_or_create_product_for_image($attachment_id);
            error_log('Got product ID: ' . $product_id);
            
            // Add to cart
            if (WC()->cart->add_to_cart($product_id, 1)) {
                error_log('Added product to cart successfully');
                wp_send_json_success(array(
                    'cart_url' => wc_get_cart_url(),
                    'message' => __('Image added to cart successfully', 'ultimate-watermark')
                ));
            } else {
                error_log('Failed to add product to cart');
                wp_send_json_error(__('Failed to add image to cart', 'ultimate-watermark'));
            }
            
        } catch (\Exception $e) {
            error_log('Error in add_image_to_cart: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }

    private function get_or_create_product_for_image($attachment_id) {
        global $wpdb;
        
        // Check if product already exists for this attachment
        $product_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
            WHERE meta_key = '_original_image_attachment_id' 
            AND meta_value = %d 
            LIMIT 1",
            $attachment_id
        ));

        if ($product_id) {
            return $product_id;
        }

        // Create new product
        $attachment = get_post($attachment_id);
        if (!$attachment) {
            throw new \Exception('Invalid attachment ID');
        }

        $product = new \WC_Product_Simple();
        $product->set_name(sprintf(__('Original Image: %s', 'ultimate-watermark'), $attachment->post_title));
        $product->set_status('publish');
        $product->set_catalog_visibility('hidden');
        $product->set_price('10.00');
        $product->set_regular_price('10.00');
        $product->set_virtual(true);
        $product->set_downloadable(true);
        
        // Set product image to the attachment
        $product->set_image_id($attachment_id);
        
        // Get the original file path
        $attached_file = get_attached_file($attachment_id);
        if (!$attached_file) {
            throw new \Exception('Could not get attached file path');
        }
        error_log('Original file path: ' . $attached_file);

        // Look for backup file with new naming convention
        $pathinfo = pathinfo($attached_file);
        $backup_path = $pathinfo['dirname'] . DIRECTORY_SEPARATOR . 
                      $pathinfo['filename'] . '.backup.' . $pathinfo['extension'];
        
        error_log('Looking for backup at: ' . $backup_path);
        
        if (!file_exists($backup_path) || !is_readable($backup_path)) {
            error_log('Backup file not found or not readable at: ' . $backup_path);
            throw new \Exception('Original image file not found. Please ensure the image has been watermarked.');
        }

        // Get upload directory info
        $upload_dir = wp_upload_dir();
        if (is_wp_error($upload_dir)) {
            throw new \Exception('Failed to get upload directory: ' . $upload_dir->get_error_message());
        }
        
        // Create WooCommerce downloads directory
        $wc_download_dir = trailingslashit($upload_dir['basedir']) . 'wc-downloads';
        if (!file_exists($wc_download_dir)) {
            if (!wp_mkdir_p($wc_download_dir)) {
                throw new \Exception('Failed to create downloads directory');
            }
        }

        // Create a unique filename for the download
        $ext = pathinfo($backup_path, PATHINFO_EXTENSION);
        $unique_filename = md5('original_' . $attachment_id . time()) . '.' . $ext;
        $symlink_path = trailingslashit($wc_download_dir) . $unique_filename;
        
        // Try to create symlink first, fall back to copy
        if (!@symlink($backup_path, $symlink_path)) {
            error_log('Symlink creation failed, trying to copy file');
            if (!@copy($backup_path, $symlink_path)) {
                throw new \Exception('Failed to create download file');
            }
            error_log('Successfully copied file to: ' . $symlink_path);
        } else {
            error_log('Successfully created symlink at: ' . $symlink_path);
        }

        // Get the URL for the symlink
        $symlink_url = str_replace(
            $upload_dir['basedir'],
            $upload_dir['baseurl'],
            $symlink_path
        );
        
        // Set download file
        $product->set_downloads(array(
            array(
                'name' => $attachment->post_title,
                'file' => $symlink_url,
                'download_id' => md5($symlink_url)
            )
        ));
        
        $product->save();
        
        // Store attachment relationship and file paths
        update_post_meta($product->get_id(), '_original_image_attachment_id', $attachment_id);
        update_post_meta($product->get_id(), '_original_image_symlink', $symlink_path);
        update_post_meta($product->get_id(), '_original_image_source', $backup_path);
        
        return $product->get_id();
    }

    public function get_attachment_id_from_url() {
        // Verify nonce first
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ulwm_ajax_nonce')) {
            error_log('Nonce verification failed for get_attachment_id_from_url');
            wp_send_json_error('Invalid security token');
            return;
        }

        // Get and sanitize the image URL
        $image_url = isset($_POST['image_url']) ? esc_url_raw($_POST['image_url']) : '';
        error_log('Received image URL: ' . $image_url);
        
        if (empty($image_url)) {
            error_log('Empty image URL received');
            wp_send_json_error('Invalid image URL');
            return;
        }

        // Get the upload directory info
        $upload_dir = wp_upload_dir();
        error_log('Upload directory base URL: ' . $upload_dir['baseurl']);
        
        // Extract the relative path from the URL
        $file_path = preg_replace('|^' . preg_quote($upload_dir['baseurl']) . '/|', '', $image_url);
        error_log('Extracted relative path: ' . $file_path);
        
        // First try to find by guid
        global $wpdb;
        $attachment = $wpdb->get_row($wpdb->prepare(
            "SELECT ID 
            FROM $wpdb->posts 
            WHERE post_type = 'attachment' 
            AND guid = %s",
            $image_url
        ));

        if ($attachment) {
            error_log('Found attachment by guid: ' . $attachment->ID);
            wp_send_json_success(array('attachment_id' => $attachment->ID));
            return;
        }

        // Try by _wp_attached_file meta
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id 
            FROM $wpdb->postmeta 
            WHERE meta_key = '_wp_attached_file' 
            AND meta_value = %s",
            $file_path
        ));

        if ($attachment_id) {
            error_log('Found attachment by meta: ' . $attachment_id);
            wp_send_json_success(array('attachment_id' => $attachment_id));
            return;
        }

        // Try by filename only
        $filename = basename($file_path);
        error_log('Trying filename only: ' . $filename);
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id 
            FROM $wpdb->postmeta 
            WHERE meta_key = '_wp_attached_file' 
            AND meta_value LIKE %s",
            '%' . $wpdb->esc_like($filename)
        ));

        if ($attachment_id) {
            error_log('Found attachment by filename: ' . $attachment_id);
            wp_send_json_success(array('attachment_id' => $attachment_id));
            return;
        }

        error_log('No attachment found for URL: ' . $image_url);
        error_log('Tried paths: ' . implode(', ', array(
            'guid: ' . $image_url,
            'relative path: ' . $file_path,
            'filename: ' . $filename
        )));
        wp_send_json_error('Attachment not found');
    }

    public function handle_completed_order($order_id) {
        $order = wc_get_order($order_id);
        
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $attachment_id = get_post_meta($product->get_id(), '_original_image_attachment_id', true);
            
            if ($attachment_id) {
                // Add download permission
                wc_downloadable_product_permissions($order_id, $item->get_id());
            }
        }
    }

    public function add_download_links($order_id) {
        $order = wc_get_order($order_id);
        
        if ($order && $order->has_downloadable_item() && $order->is_download_permitted()) {
            wc_get_template('order/order-downloads.php', array(
                'downloads'  => $order->get_downloadable_items(),
                'order'     => $order,
            ));
        }
    }

    public function modify_download_columns($columns) {
        // Customize download columns if needed
        return $columns;
    }

    public function cleanup_symlink($post_id) {
        if (get_post_type($post_id) === 'product') {
            $symlink_path = get_post_meta($post_id, '_original_image_symlink', true);
            if ($symlink_path && file_exists($symlink_path)) {
                unlink($symlink_path);
            }
        }
    }
}
