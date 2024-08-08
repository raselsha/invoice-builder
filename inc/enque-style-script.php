<?php
/**
 * Register stylesheet and scripts
 * 
 * this class add the css and scripts to the wordpress.
 * 
 * @author Shahadat Hossain <raselsha@gmail.com>
 * @version 1.0.0
 * @since 1.0.0
 */
if( ! defined('ABSPATH') ) { die( "Don't access directly" ); }

if( ! class_exists('INVB_Invoice_Builder_Enque') ){
    class INVB_Invoice_Builder_Enque{
        public function __construct()
        {
            add_action('admin_enqueue_scripts',[$this,'backend_style']);
            // add_action('wp_enqueue_scripts',[$this,'frontend_style']);
        }

        public function backend_style(){
            wp_enqueue_style('main',INVB_Invoice_Builder_URL.'assets/css/admin.css',['wp-color-picker'],INVB_Invoice_Builder_VERSION,'all');
            wp_enqueue_media();       
            wp_enqueue_script( 'main', INVB_Invoice_Builder_URL.'assets/js/admin.js', ['jquery','wp-color-picker','jquery-ui-core'], INVB_Invoice_Builder_VERSION, true );
        }
    }
    
    new INVB_Invoice_Builder_Enque();
}