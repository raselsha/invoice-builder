<?php
/**
 * Plugin Name: Invoice Builder
 * Plugin URI: https://wordpress.org/plugins/invoice-builder
 * Description: The Invoice Builder plugin helps users create and manage professional invoices quickly. It features automated calculations, client management, Pdf download.
 * Version: 1.0.0
 * Author: Shahadat Hossain
 * Author URI: https://shahadat.com.bd
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: invoice-builder
 * Domain Path: /languages
 */

 if( ! defined('ABSPATH') ) { die( "Don't access directly" ); }

 if( ! class_exists( 'INVB_Invoice_Builder' ) ){
    class INVB_Invoice_Builder{

        public function __construct() {
            add_action( 'plugins_loaded', [$this,'load_textdomain'] );     
            $this->define_contstants();
            $this->include_plugin_files();     
        }

        public function load_textdomain() {
            load_plugin_textdomain( 'invoice-builder', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
        }

        public function define_contstants() {
            define( 'INVB_Invoice_Builder_Path', plugin_dir_path(__FILE__) );
            define( 'INVB_Invoice_Builder_URL', plugin_dir_url(__FILE__) );
            define( 'INVB_Invoice_Builder_ACTIVE', true );
            define( 'INVB_Invoice_Builder_VERSION', '1.0.0' );
        }

        public function include_plugin_files() {
            
            require_once INVB_Invoice_Builder_Path . 'inc/cpt-register.php';
            require_once INVB_Invoice_Builder_Path . 'inc/metabox-register.php';
            require_once INVB_Invoice_Builder_Path . 'inc/enque-style-script.php';
            require_once INVB_Invoice_Builder_Path . 'inc/metabox/Interface.php';
            
        }

        public static function activate() {
            update_option('rewrite_rules','');
        }

        public static function deactivate() {
            flush_rewrite_rules();
        }

        public static function uninstall() {

        }
    }
 }

 if( class_exists( 'INVB_Invoice_Builder' ) ){
    register_activation_hook( __FILE__, array( 'INVB_Invoice_Builder','activate' ) );
    // register_deactivation_hook( __FILE__, array( 'INVB_Invoice_Builder','deactivate' ) );
    // register_uninstall_hook( __FILE__, array( 'INVB_Invoice_Builder','uninstall' ) );
    $INVB_Invoice_Builder = new INVB_Invoice_Builder();
 }