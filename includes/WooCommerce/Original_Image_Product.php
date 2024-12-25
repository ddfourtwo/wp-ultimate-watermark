<?php

namespace Ultimate_Watermark\WooCommerce;

class Original_Image_Product extends \WC_Product_Simple {
    
    public function __construct($product = 0) {
        $this->product_type = 'original-image';
        parent::__construct($product);
    }

    public function get_type() {
        return 'original-image';
    }

    public function is_virtual() {
        return true;
    }

    public function is_downloadable() {
        return true;
    }
}
