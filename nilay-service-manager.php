<?php
/**
 * Plugin Name:       نیلای - افزونه مدیریت خدمات
 * Plugin URI:        https://nilayteam.ir/
 * Description:       افزونه‌ای جامع برای مدیریت و نمایش انواع خدمات، رویدادها و محصولات با قابلیت اتصال به درگاه پرداخت و پنل پیامک.
 * Version:           4.6.1
 * Author:            Gemini & NilayTeam
 * Author URI:        https://gemini.google.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       nilay-services
 * Domain Path:       /languages
 */

// جلوگیری از دسترسی مستقیم به فایل
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * تابع کمکی برای مدیریت اصطلاحات
 * تمامی متون افزونه از این تابع فراخوانی می‌شوند
 */
function nsm_get_string($key, $default = '') {
    $options = get_option('nsm_terminology_options');
    return !empty($options[$key]) ? esc_html($options[$key]) : esc_html($default);
}

/**
 * کلاس اصلی افزونه مدیریت خدمات نیلای
 */
final class Nilay_Service_Manager {

    const VERSION = '4.6.1';
    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->define_constants();
        $this->includes();
        $this->init_hooks();
    }

    private function define_constants() {
        define( 'NSM_VERSION', self::VERSION );
        define( 'NSM_PLUGIN_FILE', __FILE__ );
        define( 'NSM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
        define( 'NSM_INCLUDES_DIR', NSM_PLUGIN_DIR . 'includes/' );
    }

    /**
     * بارگذاری فایل‌های مورد نیاز افزونه
     */
    private function includes() {
        require_once NSM_PLUGIN_DIR . 'admin/admin-functions.php';
        require_once NSM_PLUGIN_DIR . 'public/public-functions.php';
        require_once NSM_INCLUDES_DIR . 'class-nsm-gateways.php'; // فایل جدید درگاه‌ها
    }

    private function init_hooks() {
        // فعال‌سازی و غیرفعال‌سازی
        register_activation_hook( NSM_PLUGIN_FILE, array( 'NSM_Install', 'activate' ) );
        register_deactivation_hook( NSM_PLUGIN_FILE, array( 'NSM_Install', 'deactivate' ) );

        // اجرای کلاس درگاه‌ها برای فعال شدن هوک اعتبارسنجی
        new NSM_Gateways();

        // هوک‌های مربوط به بخش مدیریت (Admin)
        add_action( 'init', array( 'NSM_Post_Types', 'register_post_types' ) );
        add_action( 'init', array( 'NSM_Post_Types', 'register_taxonomies' ) );
        add_action( 'admin_menu', array( 'NSM_Admin_Menu', 'register_menus' ) );
        add_action( 'add_meta_boxes', array( 'NSM_Meta_Boxes', 'add_meta_boxes' ) );
        add_action( 'save_post_service', array( 'NSM_Meta_Boxes', 'save_meta_data' ) );
        add_action( 'admin_init', array('NSM_Settings', 'register_settings'));
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_assets' ) );
        add_action('wp_ajax_nsm_test_gateway', array('NSM_Admin_Handler', 'ajax_test_gateway'));
        add_action('admin_post_nsm_clear_cache', array($this, 'handle_clear_cache'));
        add_action('admin_post_nsm_export_single', array('NSM_Admin_Handler', 'handle_export_single_service'));
        add_action('admin_post_nsm_import_single', array('NSM_Admin_Handler', 'handle_import_single_service'));
        add_action('admin_post_nsm_global_export', array('NSM_Admin_Handler', 'handle_global_export'));
        add_action('admin_post_nsm_global_import', array('NSM_Admin_Handler', 'handle_global_import'));

        // هوک‌های مربوط به بخش عمومی (Public)
        add_action( 'wp_enqueue_scripts', array( $this, 'frontend_enqueue_assets' ) );
        add_action('init', array('NSM_Shortcodes', 'register_shortcodes'));
        add_action('init', array('NSM_Payment_Handler', 'handle_payment_request')); // این هوک برای دریافت اطلاعات فرم پرداخت است
        add_filter('the_content', array('NSM_Frontend', 'display_single_service_page'));
        add_action('wp_ajax_nsm_filter_services', array('NSM_Shortcodes', 'filter_services_ajax_handler'));
        add_action('wp_ajax_nopriv_nsm_filter_services', array('NSM_Shortcodes', 'filter_services_ajax_handler'));
    }
    
    public function handle_clear_cache() {
        check_admin_referer('nsm_clear_cache_nonce');

        if ( ! current_user_can('manage_options') ) {
            wp_die(nsm_get_string('error_permission_denied', 'دسترسی غیرمجاز.'));
        }
        
        wp_cache_flush();
        
        wp_redirect(admin_url('edit.php?post_type=service&page=nsm-settings&tab=tools&cache-cleared=true'));
        exit;
    }

    public function admin_enqueue_assets( $hook ) {
        global $post_type;
        $screen = get_current_screen();
        if ( ('post.php' == $hook || 'post-new.php' == $hook && 'service' === $post_type) || ($screen && strpos($screen->id, 'nsm-') !== false) ) {
            wp_enqueue_media();
            $this->add_inline_admin_assets();
        }
    }

    public function frontend_enqueue_assets() {
        wp_enqueue_style( 'swiper-css', 'https://unpkg.com/swiper/swiper-bundle.min.css', array(), '8.0.0' );
        wp_enqueue_script( 'swiper-js', 'https://unpkg.com/swiper/swiper-bundle.min.js', array(), '8.0.0', true );
        wp_enqueue_style( 'nsm-google-fonts', 'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700&display=swap', array(), null );
        $this->add_inline_frontend_assets();
    }
    
    private function add_inline_admin_assets() {
        $css = "
            .nsm-meta-box-field { margin-bottom: 15px; }
            .nsm-meta-box-field label { font-weight: bold; display: block; margin-bottom: 5px; }
            .nsm-meta-box-field input[type='text'], .nsm-meta-box-field input[type='number'], .nsm-meta-box-field input[type='url'], .nsm-meta-box-field select, .nsm-meta-box-field textarea { width: 100%; max-width: 600px; }
            .nsm-meta-box-field .description { font-style: italic; color: #666; }
            .nsm-fields-group { display: none; padding: 15px; border: 1px solid #ccd0d4; margin-top: 15px; background: #fdfdfd; border-radius: 4px; }
            .nsm-fields-group.active { display: block; }
            .nsm-repeater-field { border: 1px solid #ddd; padding: 10px; margin-bottom: 10px; border-radius: 4px; position: relative; background: #fafafa; padding-top: 25px; }
            .nsm-repeater-field .remove-repeater-field { color: #a00; text-decoration: none; position: absolute; top: 5px; left: 5px; font-size: 18px; }
            .nsm-test-result { margin-top: 10px; padding: 10px; border-radius: 4px; display: none; }
            .nsm-test-success { border: 1px solid #4CAF50; background: #DFF0D8; color: #3C763D; display: block !important; }
            .nsm-test-error { border: 1px solid #F44336; background: #F2DEDE; color: #A94442; display: block !important; }
            .nsm-paid-options, .nsm-paid-toggle-container { display: none; }
            .nsm-paid-toggle-container { margin: 10px 0; background: #fffbe6; border: 1px solid #ffe58f; padding: 8px 12px; border-radius: 3px; }
            .nsm-help-page code { background: #eee; padding: 2px 5px; border-radius: 3px; font-size: 0.9em; }
            .nsm-help-page .shortcode-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
            .nsm-help-page .shortcode-table th, .nsm-help-page .shortcode-table td { border: 1px solid #ddd; padding: 8px; text-align: right; }
            .nsm-help-page .shortcode-table th { background-color: #f7f7f7; }
        ";
        wp_add_inline_style( 'wp-admin', $css );

        $js = "
            jQuery(document).ready(function($){
                function togglePaymentFields() {
                    if ($('#_nsm_payment_model').val() === 'paid') {
                        $('.nsm-paid-options, .nsm-paid-toggle-container').show();
                    } else {
                        $('.nsm-paid-options, .nsm-paid-toggle-container').hide();
                    }
                }
                togglePaymentFields();
                $('#_nsm_payment_model').on('change', togglePaymentFields);

                function toggleServiceTypeFields() {
                    var selectedType = $('#_nsm_service_type').val();
                    $('.nsm-fields-group[id!=\"nsm-group-general\"]').removeClass('active').hide();
                    if (selectedType) {
                        $('#nsm-group-' + selectedType).addClass('active').show();
                    }
                }
                $('#nsm-group-general').addClass('active').show();
                
                toggleServiceTypeFields();
                $('#_nsm_service_type').on('change', toggleServiceTypeFields);
                
                $(document).on('click', '.add-repeater-field', function(e) {
                    e.preventDefault();
                    var container = $(this).prev('.repeater-fields-container');
                    var template = container.data('template');
                    var newIndex = new Date().getTime();
                    var newField = template.replace(/__INDEX__/g, newIndex);
                    container.append(newField);
                });

                $(document).on('click', '.remove-repeater-field', function(e){
                    e.preventDefault();
                    if(confirm('" . nsm_get_string('confirm_delete_item', 'آیا از حذف این آیتم مطمئن هستید؟') . "')) {
                        $(this).closest('.nsm-repeater-field').remove();
                    }
                });

                $(document).on('click', '.nsm-upload-button', function(e) {
                    e.preventDefault();
                    var button = $(this);
                    var frame = wp.media({ title: '" . nsm_get_string('media_modal_title', 'انتخاب یا آپلود') . "', button: { text: '" . nsm_get_string('media_modal_button', 'انتخاب') . "' }, multiple: false });
                    frame.on('select', function() {
                        var attachment = frame.state().get('selection').first().toJSON();
                        button.prev('input').val(attachment.url);
                        button.siblings('.nsm-image-preview').html('<img src=\"' + attachment.url + '\" style=\"max-width:150px; margin-top: 5px;\"/>');
                    });
                    frame.open();
                });
                
                $('.nsm-test-button').on('click', function(){
                    var button = $(this);
                    var gateway = button.data('gateway');
                    var resultDiv = $('#nsm-test-result-' + gateway);
                    var apiKey = '';
                    
                    // Find the relevant API key field based on the gateway
                    if(gateway === 'zarinpal') apiKey = $('input[name=\"nsm_payment_options[zarinpal_merchant_id]\"]').val();
                    else if(gateway === 'zibal') apiKey = $('input[name=\"nsm_payment_options[zibal_merchant_id]\"]').val();
                    else if(gateway === 'kavenegar') apiKey = $('input[name=\"nsm_sms_options[kavenegar_api_key]\"]').val();
                    else if(gateway === 'farazsms') apiKey = $('input[name=\"nsm_sms_options[farazsms_api_key]\"]').val();

                    button.prop('disabled', true);
                    resultDiv.html('" . nsm_get_string('testing_request', 'در حال ارسال درخواست...') . "').removeClass('nsm-test-success nsm-test-error').show();
                    
                    var data = {
                        'action': 'nsm_test_gateway',
                        'gateway': gateway,
                        'api_key': apiKey,
                        '_ajax_nonce': '". wp_create_nonce('nsm_test_nonce') ."'
                    };

                    $.post(ajaxurl, data, function(response) {
                        if(response.success) {
                            resultDiv.html('<strong>" . nsm_get_string('test_success', 'موفق') . ":</strong> ' + response.data.message).addClass('nsm-test-success');
                        } else {
                            resultDiv.html('<strong>" . nsm_get_string('test_error', 'خطا') . ":</strong> ' + response.data.message).addClass('nsm-test-error');
                        }
                        button.prop('disabled', false);
                    });
                });
            });
        ";
        wp_add_inline_script( 'jquery-core', $js );
    }
    
    private function add_inline_frontend_assets() {
        $css = "
            :root {
                --nsm-primary-color: #4f46e5; --nsm-secondary-color: #7c3aed; --nsm-accent-color: #db2777;
                --nsm-shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
                --nsm-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
                --nsm-shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
            }
            .nsm-mode-light {
                --nsm-text-color: #1f2937; --nsm-text-light: #6b7280; --nsm-bg-color: #ffffff;
                --nsm-bg-alt: #f9fafb; --nsm-border-color: #e5e7eb; --nsm-card-bg: #ffffff;
                --nsm-success-color: #10b981; --nsm-error-color: #ef4444; --nsm-warning-color: #f59e0b;
            }
            .nsm-mode-dark {
                --nsm-text-color: #d1d5db; --nsm-text-light: #9ca3af; --nsm-bg-color: #111827;
                --nsm-bg-alt: #1f2937; --nsm-border-color: #374151; --nsm-card-bg: #1f2937;
                --nsm-success-color: #10b981; --nsm-error-color: #ef4444; --nsm-warning-color: #f59e0b;
            }
            .nsm-payment-notice { padding: 15px; margin-bottom: 20px; border-radius: 8px; border-width: 1px; border-style: solid; }
            .nsm-payment-notice.success { background-color: #f0fdf4; border-color: #bbf7d0; color: #166534; }
            .nsm-payment-notice.error { background-color: #fef2f2; border-color: #fecaca; color: #991b1b; }

            .nsm-services-grid { display: grid; gap: 30px; }
            .nsm-grid-desktop-1 { grid-template-columns: repeat(1, 1fr); }
            .nsm-grid-desktop-2 { grid-template-columns: repeat(2, 1fr); }
            .nsm-grid-desktop-3 { grid-template-columns: repeat(3, 1fr); }
            .nsm-grid-desktop-4 { grid-template-columns: repeat(4, 1fr); }
            .nsm-grid-desktop-6 { grid-template-columns: repeat(6, 1fr); }
            @media (max-width: 1024px) {
                .nsm-grid-tablet-1 { grid-template-columns: repeat(1, 1fr); }
                .nsm-grid-tablet-2 { grid-template-columns: repeat(2, 1fr); }
                .nsm-grid-tablet-3 { grid-template-columns: repeat(3, 1fr); }
            }
            @media (max-width: 767px) {
                .nsm-grid-mobile-1 { grid-template-columns: repeat(1, 1fr); }
                .nsm-grid-mobile-2 { grid-template-columns: repeat(2, 1fr); }
            }

            .nsm-service-card { background: var(--nsm-card-bg); border-radius: 15px; box-shadow: var(--nsm-shadow); transition: all 0.3s ease; overflow: hidden; display: flex; flex-direction: column; }
            .nsm-service-card:hover { transform: translateY(-5px); box-shadow: var(--nsm-shadow-lg); }
            .nsm-service-card-thumb { position: relative; overflow: hidden; }
            .nsm-service-card-thumb a { display: block; }
            .nsm-service-card-thumb img { width: 100%; height: 200px; object-fit: cover; display: block; transition: transform 0.4s ease; }
            .nsm-service-card:hover .nsm-service-card-thumb img { transform: scale(1.05); }
            .nsm-service-card-thumb .nsm-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.2); opacity: 0; transition: opacity 0.3s ease; }
            .nsm-service-card:hover .nsm-service-card-thumb .nsm-overlay { opacity: 1; }
            .nsm-service-card-badge { position: absolute; top: 15px; right: 15px; background-color: var(--nsm-primary-color); color: #fff; padding: 5px 12px; border-radius: 20px; font-size: 0.8em; font-weight: bold; }
            .nsm-service-card-content { padding: 25px; display: flex; flex-direction: column; flex-grow: 1; }
            .nsm-service-card-title { font-size: 1.25em; margin: 0 0 10px; }
            .nsm-service-card-title a { color: var(--nsm-text-color); text-decoration: none; transition: color 0.3s ease; }
            .nsm-service-card-title a:hover { color: var(--nsm-primary-color); }
            .nsm-service-card-meta { color: var(--nsm-text-light); font-size: 0.85em; margin-bottom: 15px; }
            .nsm-service-card-excerpt { color: var(--nsm-text-light); flex-grow: 1; margin-bottom: 20px; }
            .nsm-service-card-button { background-color: var(--nsm-primary-color); color: #fff; text-align: center; padding: 12px 20px; border-radius: 8px; text-decoration: none; font-weight: 500; transition: background-color 0.3s ease; align-self: flex-start; }
            .nsm-service-card-button:hover { background-color: var(--nsm-secondary-color); }
            
            /* Filter Widget Styles */
            .nsm-filter-buttons { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; margin-bottom: 30px; }
            .nsm-filter-buttons button { background: #f1f5f9; border: none; padding: 10px 20px; border-radius: 20px; cursor: pointer; transition: all 0.3s ease; }
            .nsm-filter-buttons button.active { background: var(--nsm-primary-color); color: #fff; }
            .nsm-no-results { text-align: center; padding: 20px; }

            /* Single Service Page Styles */
            .nsm-single-service-wrapper { font-family: 'Vazirmatn', sans-serif; background-color: var(--nsm-bg-color); color: var(--nsm-text-color); padding-bottom: 50px; }
            .nsm-container { max-width: 1200px; margin: 0 auto; padding: 0 15px; }
            .nsm-info-card { background: var(--nsm-card-bg); padding: 30px; border-radius: 15px; box-shadow: var(--nsm-shadow); margin-bottom: 30px; border: 1px solid var(--nsm-border-color); }
            .nsm-info-card h2 { font-size: 1.5em; font-weight: 700; margin-top: 0; margin-bottom: 25px; border-bottom: 1px solid var(--nsm-border-color); padding-bottom: 15px; }
            .nsm-info-card ul { list-style: disc; padding-right: 20px; }
            .nsm-cta-button { display: block; width: 100%; background-image: linear-gradient(to right, var(--nsm-secondary-color), var(--nsm-primary-color)); color: #fff; text-align: center; padding: 15px; border-radius: 10px; text-decoration: none; font-weight: bold; font-size: 1.1em; transition: all 0.3s ease; box-shadow: var(--nsm-shadow-sm); border: none; cursor: pointer; }
            .nsm-cta-button:hover { transform: translateY(-2px); box-shadow: var(--nsm-shadow); }
            .nsm-payment-form input { width: 100%; padding: 12px; margin-bottom: 10px; border: 1px solid var(--nsm-border-color); border-radius: 8px; background-color: var(--nsm-bg-alt); color: var(--nsm-text-color); }
            .nsm-instructor-profile { display: flex; gap: 20px; align-items: center; background: var(--nsm-bg-alt); padding: 20px; border-radius: 10px; margin-bottom: 20px; }
            .nsm-instructor-profile img { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; }
            .nsm-instructor-profile .instructor-name { font-size: 1.2em; font-weight: bold; margin: 0; }
            .nsm-instructor-profile .instructor-bio { font-size: 0.9em; color: var(--nsm-text-light); }
            .nsm-speakers-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 20px; }
            .nsm-speaker-card { text-align: center; }
            .nsm-speaker-card img { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; margin-bottom: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .nsm-agenda-list { list-style: none; padding: 0; position: relative; }
            .nsm-agenda-list::before { content: ''; position: absolute; right: 5px; top: 0; bottom: 0; width: 2px; background: var(--nsm-border-color); }
            .nsm-agenda-item { position: relative; padding: 10px 25px 10px 0; }
            .nsm-agenda-item::before { content: ''; position: absolute; right: 0; top: 15px; width: 12px; height: 12px; background: var(--nsm-secondary-color); border-radius: 50%; border: 2px solid var(--nsm-card-bg); box-shadow: 0 0 0 2px var(--nsm-secondary-color); }
            .nsm-includes-excludes { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
            .nsm-includes-excludes h3 { margin-top: 0; font-size: 1.1em; }
            .nsm-includes-excludes ul { margin: 0; padding-right: 20px; }
            .nsm-includes ul li { color: var(--nsm-success-color); }
            .nsm-excludes ul li { color: #F44336; }
            .nsm-pricing-table { width: 100%; border-collapse: collapse; }
            .nsm-pricing-table th, .nsm-pricing-table td { padding: 15px; text-align: right; border-bottom: 1px solid var(--nsm-border-color); }
            .nsm-pricing-table th { background-color: var(--nsm-bg-alt); }
            
            /* --- Sidebar Meta & Custom Fields Info --- */
            .nsm-meta-info-list { list-style: none; padding: 0; margin: 0; }
            .nsm-meta-info-item { display: flex; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--nsm-border-color); }
            .nsm-meta-info-item:last-child { border-bottom: none; }
            .nsm-meta-info-item svg, .nsm-meta-info-item img { width: 20px; height: 20px; margin-left: 15px; flex-shrink: 0; color: var(--nsm-primary-color); }
            .nsm-meta-info-item img { border-radius: 3px; }
            .nsm-meta-info-item .meta-label { font-weight: 500; color: var(--nsm-text-light); margin-left: 5px; }
            .nsm-meta-info-item .meta-value { font-weight: bold; color: var(--nsm-text-color); }
            .nsm-meta-info-item .meta-value a { color: inherit; text-decoration: none; }
            .nsm-meta-info-item .meta-value a:hover { color: var(--nsm-primary-color); }
            .nsm-sidebar-box .nsm-meta-info-list { margin: -12px 0; }

            /* --- Template 1: Modern Professional --- */
            .nsm-layout-1 { padding-top: 50px; }
            .nsm-mode-light .nsm-layout-1 { background-color: var(--nsm-bg-alt); }
            .nsm-layout-1 .nsm-hero-section { display: grid; grid-template-columns: 1fr 1fr; align-items: center; gap: 50px; margin-bottom: 50px; }
            @media (max-width: 767px) { .nsm-layout-1 .nsm-hero-section { grid-template-columns: 1fr; } }
            .nsm-layout-1 .nsm-hero-content h1 { font-size: 3.5em; font-weight: 700; margin-bottom: 20px; color: var(--nsm-text-color); }
            .nsm-mode-dark .nsm-layout-1 .nsm-hero-content h1 { color: #fff; }
            .nsm-layout-1 .nsm-hero-content .service-summary { font-size: 1.2em; color: var(--nsm-text-light); }
            .nsm-layout-1 .nsm-hero-image img { border-radius: 20px; box-shadow: var(--nsm-shadow-lg); }
            .nsm-layout-1 .nsm-main-layout { display: grid; grid-template-columns: 1fr 380px; gap: 40px; }
            @media (max-width: 1024px) { .nsm-layout-1 .nsm-main-layout { grid-template-columns: 1fr; } }
            .nsm-layout-1 .nsm-sidebar { position: sticky; top: 30px; }
            .nsm-layout-1 .nsm-sidebar-box { background: var(--nsm-card-bg); padding: 30px; border-radius: 15px; box-shadow: var(--nsm-shadow); margin-bottom: 25px; }
            .nsm-layout-1 .nsm-price-box .price-value { font-size: 2.8em; font-weight: 700; color: var(--nsm-primary-color); }

            /* --- Template 2: Immersive Gallery --- */
            .nsm-layout-2 { padding-top: 0; }
            .nsm-layout-2 .nsm-hero-gallery { height: 60vh; min-height: 450px; }
            .nsm-layout-2 .nsm-hero-gallery .swiper-slide { background-size: cover; background-position: center; }
            .nsm-layout-2 .nsm-hero-gallery .swiper-pagination-bullet-active { background-color: #fff; }
            .nsm-layout-2 .nsm-main-layout { margin-top: -120px; position: relative; z-index: 2; display: grid; grid-template-columns: 380px 1fr; gap: 40px; align-items: start;}
            @media (max-width: 1024px) { .nsm-layout-2 .nsm-main-layout { grid-template-columns: 1fr; } }
            .nsm-layout-2 .nsm-title-card { background: var(--nsm-card-bg); text-align:center; padding: 40px; border-radius: 15px; box-shadow: var(--nsm-shadow-lg); margin-bottom: 30px; }
            .nsm-layout-2 .nsm-title-card h1 { font-size: 3em; margin: 0; color: var(--nsm-text-color); }
            .nsm-layout-2 .nsm-sidebar { position: sticky; top: 30px; }
            .nsm-layout-2 .nsm-sidebar-box { background: var(--nsm-card-bg); padding: 30px; border-radius: 15px; box-shadow: var(--nsm-shadow); margin-bottom: 25px; }

            /* --- Template 3: Hero Focus --- */
            .nsm-layout-3 { padding-top: 0; }
            .nsm-mode-light .nsm-layout-3 { background-color: var(--nsm-bg-alt); }
            .nsm-layout-3 .nsm-hero-section { height: 65vh; min-height: 450px; position: relative; display: flex; align-items: center; justify-content: center; text-align: center; color: #fff; border-radius: 0 0 40px 40px; overflow: hidden; }
            .nsm-layout-3 .nsm-hero-section .nsm-hero-bg { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-size: cover; background-position: center; transform: scale(1.05); transition: transform 0.5s ease; }
            .nsm-layout-3:hover .nsm-hero-section .nsm-hero-bg { transform: scale(1); }
            .nsm-layout-3 .nsm-hero-section::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(to top, rgba(0,0,0,0.7), rgba(0,0,0,0.2)); }
            .nsm-layout-3 .nsm-hero-content { position: relative; z-index: 2; }
            .nsm-layout-3 .nsm-hero-content h1 { font-size: 4em; text-shadow: 0 2px 10px rgba(0,0,0,0.5); }
            .nsm-layout-3 .nsm-main-layout { margin-top: 40px; display: grid; grid-template-columns: 1fr 380px; gap: 40px; }
            @media (max-width: 1024px) { .nsm-layout-3 .nsm-main-layout { grid-template-columns: 1fr; } }
            .nsm-layout-3 .nsm-sidebar { position: sticky; top: 30px; }
            .nsm-layout-3 .nsm-sidebar-box, .nsm-layout-3 .nsm-info-card { background: var(--nsm-card-bg); border-radius: 15px; box-shadow: var(--nsm-shadow); }
            .nsm-layout-3 .nsm-sidebar-box { padding: 30px; margin-bottom: 25px; }

            /* --- Template 4: Sleek --- */
            .nsm-layout-4 { padding-top: 50px; }
            .nsm-layout-4 .nsm-hero-section { display: grid; grid-template-columns: 1fr 1fr; align-items: center; gap: 40px; margin-bottom: 50px; }
            @media (max-width: 767px) { .nsm-layout-4 .nsm-hero-section { grid-template-columns: 1fr; } }
            .nsm-layout-4 .nsm-hero-content h1 { font-size: 3.5em; color: var(--nsm-text-color); }
            .nsm-mode-dark .nsm-layout-4 .nsm-hero-content h1 { color: #fff; }
            .nsm-layout-4 .nsm-hero-image img { border-radius: 20px; border: 2px solid var(--nsm-border-color); }
            .nsm-layout-4 .nsm-main-layout { display: grid; grid-template-columns: 1fr 380px; gap: 40px; }
            @media (max-width: 1024px) { .nsm-layout-4 .nsm-main-layout { grid-template-columns: 1fr; } }
            .nsm-layout-4 .nsm-sidebar { position: sticky; top: 30px; }
            .nsm-mode-dark .nsm-layout-4 .nsm-meta-info-item svg { color: var(--nsm-secondary-color); }

            /* --- Template 5: Action-Oriented Landing --- */
            .nsm-layout-5 { padding-top: 50px; }
            .nsm-layout-5 .nsm-main-layout { display: grid; grid-template-columns: 1fr 400px; gap: 50px; align-items: start; }
            @media (max-width: 1024px) { .nsm-layout-5 .nsm-main-layout { grid-template-columns: 1fr; } }
            .nsm-layout-5 .nsm-main-content { padding-top: 20px; }
            .nsm-layout-5 .nsm-main-content h1 { font-size: 3.5em; margin-top: 0; color: var(--nsm-text-color); }
            .nsm-layout-5 .nsm-main-content .service-summary { font-size: 1.2em; color: var(--nsm-text-light); margin-bottom: 40px; }
            .nsm-layout-5 .nsm-sidebar { position: sticky; top: 30px; }
            .nsm-layout-5 .nsm-sidebar-box { border: 1px solid var(--nsm-border-color); margin-bottom: 25px; padding: 30px; }
            .nsm-layout-5 .nsm-featured-image { border-radius: 15px; overflow: hidden; box-shadow: var(--nsm-shadow-lg); margin-bottom: 30px; }
            .nsm-layout-5 .nsm-featured-image img { width: 100%; display: block; }
            
            /* --- Template 6: Minimalist --- */
            .nsm-layout-6 { padding-top: 50px; }
            .nsm-layout-6 .nsm-hero-image { height: 50vh; min-height: 350px; border-radius: 20px; background-size: cover; background-position: center; margin-bottom: 40px; }
            .nsm-layout-6 .nsm-main-layout { display: grid; grid-template-columns: 1fr 380px; gap: 40px; }
            @media (max-width: 1024px) { .nsm-layout-6 .nsm-main-layout { grid-template-columns: 1fr; } }
            .nsm-layout-6 .nsm-main-content h1 { font-size: 3.5em; color: var(--nsm-text-color); }
            .nsm-mode-dark .nsm-layout-6 .nsm-main-content h1 { color: #fff; }
            .nsm-layout-6 .nsm-info-card, .nsm-layout-6 .nsm-sidebar-box { background-color: transparent; box-shadow: none; border: 1px solid var(--nsm-border-color); }
            .nsm-layout-6 .nsm-sidebar { position: sticky; top: 30px; }
            .nsm-mode-dark .nsm-layout-6 .nsm-meta-info-item svg { color: var(--nsm-secondary-color); }

            /* --- Template 7: Gradient --- */
            .nsm-layout-7 { padding-top: 50px; }
            .nsm-mode-dark.nsm-layout-7 { background: #0F2027; background: -webkit-linear-gradient(to right, #2C5364, #203A43, #0F2027); background: linear-gradient(to right, #2C5364, #203A43, #0F2027); }
            .nsm-mode-light.nsm-layout-7 { background: #eef2f3; background: -webkit-linear-gradient(to right, #eef2f3, #8e9eab); background: linear-gradient(to right, #eef2f3, #8e9eab); }
            .nsm-layout-7 .nsm-hero-content { text-align: center; padding: 50px 0; }
            .nsm-layout-7 .nsm-hero-content h1 { font-size: 4em; text-shadow: 0 2px 15px rgba(0,0,0,0.3); color: var(--nsm-text-color); }
            .nsm-mode-dark .nsm-layout-7 .nsm-hero-content h1 { color: #fff; }
            .nsm-layout-7 .nsm-main-layout { display: grid; grid-template-columns: 1fr 380px; gap: 40px; }
            @media (max-width: 1024px) { .nsm-layout-7 .nsm-main-layout { grid-template-columns: 1fr; } }
            .nsm-mode-dark .nsm-layout-7 .nsm-info-card, .nsm-mode-dark .nsm-layout-7 .nsm-sidebar-box { background: rgba(255, 255, 255, 0.05); -webkit-backdrop-filter: blur(10px); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.1); box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37); }
            .nsm-mode-light .nsm-layout-7 .nsm-info-card, .nsm-mode-light .nsm-layout-7 .nsm-sidebar-box { background: rgba(255, 255, 255, 0.5); border: 1px solid rgba(0, 0, 0, 0.1); -webkit-backdrop-filter: blur(10px); backdrop-filter: blur(10px); }
            .nsm-layout-7 .nsm-sidebar { position: sticky; top: 30px; }
            .nsm-mode-dark .nsm-layout-7 .nsm-meta-info-item svg { color: #fff; }
        ";
        wp_add_inline_style( 'wp-block-library', $css );
    }
}

// این خط افزونه را اجرا می‌کند
Nilay_Service_Manager::instance();
