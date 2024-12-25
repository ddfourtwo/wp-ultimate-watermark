<?php

namespace Ultimate_Watermark\WooCommerce;

class Download_Handler {
    public function __construct() {
        add_action('init', array($this, 'handle_download'));
    }

    public function handle_download() {
        if (!isset($_GET['ulwm_download']) || $_GET['ulwm_download'] !== 'original') {
            return;
        }

        $attachment_id = isset($_GET['attachment_id']) ? absint($_GET['attachment_id']) : 0;
        if (!$attachment_id || !wp_verify_nonce($_GET['nonce'], 'download_original_' . $attachment_id)) {
            wp_die(__('Invalid download request', 'ultimate-watermark'));
        }

        // Get the backup file path
        $attached_file = get_attached_file($attachment_id);
        $backup_path = ultimate_watermark()->utils->get_image_backup_filepath($attached_file);

        if (!file_exists($backup_path)) {
            wp_die(__('Original file not found', 'ultimate-watermark'));
        }

        // Get file info
        $file_name = basename($backup_path);
        $file_size = filesize($backup_path);
        $file_ext = strtolower(pathinfo($backup_path, PATHINFO_EXTENSION));

        // Set headers for download
        nocache_headers();
        header('Content-Type: ' . $this->get_mime_type($file_ext));
        header('Content-Disposition: attachment; filename="' . $file_name . '"');
        header('Content-Length: ' . $file_size);
        header('Content-Transfer-Encoding: binary');

        // Clean output buffer
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Read file in chunks to handle large files
        if ($file_size > 0) {
            $handle = @fopen($backup_path, 'rb');
            if ($handle === false) {
                wp_die(__('Error opening file', 'ultimate-watermark'));
            }

            while (!feof($handle)) {
                echo @fread($handle, 8192);
                if (connection_status() != 0) {
                    @fclose($handle);
                    exit;
                }
            }
            @fclose($handle);
        } else {
            readfile($backup_path);
        }
        exit;
    }

    private function get_mime_type($ext) {
        $mime_types = array(
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp'
        );
        return isset($mime_types[$ext]) ? $mime_types[$ext] : 'application/octet-stream';
    }

    private function user_can_download($attachment_id) {
        if (!is_user_logged_in()) {
            return false;
        }

        $current_user_id = get_current_user_id();
        
        // Get all orders for the current user
        $orders = wc_get_orders(array(
            'customer_id' => $current_user_id,
            'status' => 'completed',
        ));

        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $product_id = $item->get_product_id();
                $linked_attachment_id = get_post_meta($product_id, '_original_image_attachment_id', true);
                
                if ($linked_attachment_id == $attachment_id) {
                    return true;
                }
            }
        }

        return false;
    }
}
