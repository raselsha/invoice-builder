<?php
/**
 * Register a custom post type
 * 
 * this class generate a custom post type into wordpress.
 * 
 * @author Shahadat Hossain <raselsha@gmail.com>
 * @version 1.0.0
 * @since 1.0.0
 */

 if( ! defined('ABSPATH') ) { die( "Don't access directly" ); }

if( ! class_exists('INVB_Invoice_Builder_Metabox')){
        class INVB_Invoice_Builder_Metabox{
            public function __construct(){
                add_action('add_meta_boxes',[$this,'metabox']);
            }

            public function metabox(){

                add_meta_box('invb-invoice-builder-metabox',__('Invoice Builder', 'invoice-builder' ),[$this,'meta_box_layout'],'invb_invoice_builder','normal','high');
            }

            public function meta_box_layout($post){
                $post_id = $post->ID;
            ?>
                <main class="invb-invoice-builder" id="invb-invoice-builder">
                    <div class="content">
                        <?php do_action('invb_invoice_builder_interface',$post_id); ?>
                    </div>
                    <aside>
                        <ul>
                            <?php do_action('invb_invoice_builder_tools',$post_id); ?>
                        </ul>
                    </aside>
                    
                </main>
            <?php
            }           
        }
        new INVB_Invoice_Builder_Metabox();
    }