<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'itk_wc_free_gift_product_id' );
delete_option( 'itk_wc_free_gift_min_amount' );
