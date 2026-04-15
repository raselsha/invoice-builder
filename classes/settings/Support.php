<?php
/**
 * @author shahadat hossain <raselsha@gmail.com>
 * @version 1.0.0
 * @since 1.1.0
 */

namespace PDFEV;

defined('ABSPATH') || exit;

class INVB_Support_Settings{
    public function __construct() {
        add_action('pdfev_settings_tabs',array($this,'tabs'),30,0);
        add_action('pdfev_settings_tabs_content',array($this,'tabs_content'));
    }
    public function tabs(){
    ?>
        <button class="nav-tab" data-tab-target="pdfev_emd_vwr_admin_tabs_support"> <?php echo esc_html__('Support','pdf-embed-viewer'); ?> </button>
    <?php
    }
    public function tabs_content() {
    // Handle form submission
    if ( isset($_POST['pev_feature_request_submit']) && check_admin_referer('pev_feature_request_action') ) {
        $name    = isset($_POST['feature_name']) ? sanitize_text_field($_POST['feature_name']) : '';
        $email   = isset($_POST['feature_email']) ? sanitize_email($_POST['feature_email']) : '';
        $message = isset($_POST['feature_message']) ? sanitize_textarea_field($_POST['feature_message']) : '';

        $subject = 'PDF Embed Viewer - Feature Request';
        $body    = "Name: $name\nEmail: $email\nMessage:\n$message";
        $to      = 'raselsha@gmail.com';

        $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];

        if ( wp_mail( $to, $subject, $body, $headers ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Thank you! Your feature request has been sent.', 'pdf-embed-viewer' ) . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Please configure your email then try again.', 'pdf-embed-viewer' ) . '</p></div>';
        }
    }
    ?>

    <div class="pdfev-tab-content" data-tab="pdfev_emd_vwr_admin_tabs_support">
        <div class="pdf-embed-viewer-support" style="margin-bottom: 30px;">
            <h2 style="margin-top: 0;"><?php esc_html_e( 'Need Help or Want to Share Your Experience?', 'pdf-embed-viewer' ); ?></h2>
            <ul style="list-style: disc; padding-left: 20px;">
                <li>
                    <?php esc_html_e( 'Give a review for your feedback', 'pdf-embed-viewer' ); ?> –
                    <a target="_blank" href="<?php echo esc_url( 'https://wordpress.org/support/plugin/pdf-embed-viewer/reviews/#new-post' ); ?>">
                        <?php esc_html_e( 'Add Review', 'pdf-embed-viewer' ); ?>
                    </a>
                </li>
                <li>
                    <?php esc_html_e( 'Create a support ticket', 'pdf-embed-viewer' ); ?> –
                    <a target="_blank" href="<?php echo esc_url( 'https://wordpress.org/support/plugin/pdf-embed-viewer/' ); ?>">
                        <?php esc_html_e( 'Support', 'pdf-embed-viewer' ); ?>
                    </a>
                </li>
            </ul>
            <div style="max-width: 500px;">
                <h2><?php esc_html_e( 'Feature Request Form', 'pdf-embed-viewer' ); ?></h2>
                <form method="post">
                    <?php wp_nonce_field( 'pev_feature_request_action' ); ?>

                    <p>
                        <label for="feature_name"><?php esc_html_e( 'Your Name', 'pdf-embed-viewer' ); ?></label><br>
                        <input type="text" id="feature_name" name="feature_name" required style="width: 100%;">
                    </p>

                    <p>
                        <label for="feature_email"><?php esc_html_e( 'Your Email', 'pdf-embed-viewer' ); ?></label><br>
                        <input type="email" id="feature_email" name="feature_email" required style="width: 100%;">
                    </p>

                    <p>
                        <label for="feature_message"><?php esc_html_e( 'Feature Description', 'pdf-embed-viewer' ); ?></label><br>
                        <textarea id="feature_message" name="feature_message" rows="5" required style="width: 100%;"></textarea>
                    </p>

                    <p>
                        <button type="submit" name="pev_feature_request_submit" class="button button-primary">
                            <?php esc_html_e( 'Send Request', 'pdf-embed-viewer' ); ?>
                        </button>
                    </p>
                </form>
            </div>
        </div>
    </div>
    <?php
    }
}

new \PDFEV\INVB_Support_Settings();
