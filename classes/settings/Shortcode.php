<?php
/**
 * @author shahadat hossain <raselsha@gmail.com>
 * @version 1.0.0
 * @since 1.1.0
 */

namespace PDFEV;

defined('ABSPATH') || exit;
class INVB_Shortcode_Generator {
    public function __construct() {
        add_action('pdfev_settings_tabs',array($this,'tabs'),20,0);
        add_action('pdfev_settings_tabs_content',array($this,'tabs_content'));
        add_action('wp_ajax_pdfev_shortcode_generate', array($this,'shortcode_generate')); 
    }
    public function tabs(){
        ?>
        <button class="nav-tab" data-tab-target="pdfev_emd_vwr_admin_tabs_shortcode"> <?php echo esc_html__('Shortcode','pdf-embed-viewer'); ?> </button>
        <?php
    }
    public function tabs_content(){
        $categories = get_terms( array(
            'taxonomy'   => 'pdfev_category',
            'hide_empty' => false, // false রাখলে empty category গুলোও পাবে
        ) );

        ?>
        <div class="pdfev-tab-content" data-tab="pdfev_emd_vwr_admin_tabs_shortcode">
            <div class="pdfev-shortcode-generaor">
                <div class="shortcode-sidebar">
                    <h2><?php echo esc_html__('Shortcode Generator','pdf-embed-viewer') ?></h2>

                    <div class="form-group">
                        <label for="template"><?php echo esc_html__('Template','pdf-embed-viewer'); ?></label>
                        <select id="template">
                            <option value="list"><?php echo esc_html__('list','pdf-embed-viewer'); ?></option>
                            <option value="grid"><?php echo esc_html__('grid','pdf-embed-viewer'); ?></option>
                            <option value="ebook"><?php echo esc_html__('ebook','pdf-embed-viewer'); ?></option>
                            <option value="newsletter"><?php echo esc_html__('newsletter','pdf-embed-viewer'); ?></option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="category"><?php echo esc_html__('Category','pdf-embed-viewer'); ?></label>
                        
                        <select id="category">
                            <option value=""><?php echo esc_html__('Select Category','pdf-embed-viewer'); ?></option>
                            <?php foreach ( $categories as $category ) : ?>
                            <option value="<?php echo esc_html( $category->slug ); ?>"><?php echo esc_html( $category->name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        
                    </div>

                    <div class="form-group">
                        <label for="limit"><?php echo esc_html__('Limit','pdf-embed-viewer'); ?></label>
                        <input type="number" id="limit" value="10" min="1">
                    </div>

                    <div class="form-group">
                        <label for="order"><?php echo esc_html__('Order','pdf-embed-viewer'); ?></label>
                        <select id="order">
                            <option value="dsc"><?php echo esc_html__('dsc','pdf-embed-viewer'); ?></option>
                            <option value="asc"><?php echo esc_html__('asc','pdf-embed-viewer'); ?></option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="read"><?php echo esc_html__('Read Button','pdf-embed-viewer'); ?></label>
                        <select id="read">
                            <option value="yes"><?php echo esc_html__('yes','pdf-embed-viewer'); ?></option>
                            <option value="no"><?php echo esc_html__('no','pdf-embed-viewer'); ?></option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="download"><?php echo esc_html__('Download Button','pdf-embed-viewer'); ?></label>
                        <select id="download">
                            <option value="yes"><?php echo esc_html__('yes','pdf-embed-viewer'); ?></option>
                            <option value="no"><?php echo esc_html__('no','pdf-embed-viewer'); ?></option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="reading_count"><?php echo esc_html__('Reading Count','pdf-embed-viewer'); ?></label>
                        <select id="reading_count">
                            <option value="yes"><?php echo esc_html__('yes','pdf-embed-viewer'); ?></option>
                            <option value="no"><?php echo esc_html__('no','pdf-embed-viewer'); ?></option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="downloading_count"><?php echo esc_html__('Downloading Count','pdf-embed-viewer'); ?></label>
                        <select id="downloading_count">
                            <option value="yes"><?php echo esc_html__('yes','pdf-embed-viewer'); ?></option>
                            <option value="no"><?php echo esc_html__('no','pdf-embed-viewer'); ?></option>
                        </select>
                    </div>

                    <button id="pdfev-shortcode-generate"><?php echo esc_html__('Generate Shortcode','pdf-embed-viewer'); ?></button>
                    <button id="pdfev-shortcode-reset" type="button"><?php echo esc_html__('Reset','pdf-embed-viewer'); ?></button>
                    
                </div>
                <div class="shortcode-previewer">
                    <h2><?php echo esc_html__('ShortCode','pdf-embed-viewer'); ?></h2>
                    <div id="pdfev-shortcode" class="pdfev-shortcode">[pdfev_viewer template="list" category="" limit="10" order="dsc" read="yes" download="yes" reading_count="yes" downloading_count="yes"]</div>
                    <!-- <div id="shortcode-previewer"></div> -->
                    <button id="pdfev-copy-shortcode"><?php echo esc_html__('Copy','pdf-embed-viewer'); ?></button>
                </div>
            </div>
        </div> 
        <?php
    }
    public function shortcode_generate(){
        check_ajax_referer('pdf_ajax_nonce', 'ajaxnonce');
        $template = isset($_POST['template']) ? sanitize_text_field($_POST['template']) : '';
        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;
        $order = isset($_POST['order']) ? sanitize_text_field($_POST['order']) : '';
        $read = isset($_POST['read']) ? sanitize_text_field($_POST['read']) : '';
        $download = isset($_POST['download']) ? sanitize_text_field($_POST['download']) : '';
        $reading_count = isset($_POST['reading_count']) ? sanitize_text_field($_POST['reading_count']) : '';
        $downloading_count = isset($_POST['downloading_count']) ? sanitize_text_field($_POST['downloading_count']) : '';

        $shortcode = sprintf(
            '[pdfev_viewer template="%s" category="%s" limit="%d" order="%s" read="%s" download="%s" reading_count="%s" downloading_count="%s"]',
            $template, $category, $limit, $order, $read, $download, $reading_count, $downloading_count
        );
        
        
        ob_start();
        // echo $shortcode;
        $html = ob_get_clean();
        
        wp_send_json_success([
            'html' => $html,
            'shortcode' => $shortcode,
        ]);
        wp_die();
    }
}

new \PDFEV\INVB_Shortcode_Generator();
