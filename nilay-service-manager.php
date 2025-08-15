<?php
/**
 * Plugin Name:       نیلای - افزونه مدیریت خدمات
 * Plugin URI:        https://nilayteam.ir/
 * Description:       افزونه‌ای جامع برای مدیریت و نمایش انواع خدمات، رویدادها و محصولات با قابلیت اتصال به درگاه پرداخت، پنل پیامک و یکپارچگی کامل با المنتور.
 * Version:           2.6.0
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
 */
function nsm_get_string($key, $default = '') {
    $options = get_option('nsm_terminology_options');
    return !empty($options[$key]) ? esc_html($options[$key]) : esc_html($default);
}

/**
 * کلاس اصلی افزونه مدیریت خدمات نیلای
 */
final class Nilay_Service_Manager {

    const VERSION = '2.6.0';
    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->define_constants();
        $this->init_hooks();
    }

    private function define_constants() {
        define( 'NSM_VERSION', self::VERSION );
        define( 'NSM_PLUGIN_FILE', __FILE__ );
        define( 'NSM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
    }

    private function init_hooks() {
        register_activation_hook( NSM_PLUGIN_FILE, array( 'NSM_Install', 'activate' ) );
        register_deactivation_hook( NSM_PLUGIN_FILE, array( 'NSM_Install', 'deactivate' ) );

        add_action( 'init', array( 'NSM_Post_Types', 'register_post_types' ) );
        add_action( 'init', array( 'NSM_Post_Types', 'register_taxonomies' ) );
        add_action( 'admin_menu', array( 'NSM_Admin_Menu', 'register_menus' ) );
        add_action( 'add_meta_boxes', array( 'NSM_Meta_Boxes', 'add_meta_boxes' ) );
        add_action( 'save_post_service', array( 'NSM_Meta_Boxes', 'save_meta_data' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_assets' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'frontend_enqueue_assets' ) );
        add_action( 'admin_init', array('NSM_Settings', 'register_settings'));
        
        add_action( 'plugins_loaded', array($this, 'init_elementor_integration') );

        add_action('init', array('NSM_Shortcodes', 'register_shortcodes'));
        add_action('init', array('NSM_Payment_Handler', 'handle_payment_request'));
        
        add_action('wp_ajax_nsm_test_gateway', array('NSM_API_Handler', 'ajax_test_gateway'));
        add_action('admin_post_nsm_clear_cache', array($this, 'handle_clear_cache'));
        
        add_filter('the_content', array('NSM_Frontend', 'display_single_service_page'));
    }
    
    public function handle_clear_cache() {
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'nsm_clear_cache_nonce' ) ) {
            wp_die('Invalid nonce.');
        }
        if ( ! current_user_can('manage_options') ) {
            wp_die('Permission denied.');
        }

        if ( did_action( 'elementor/loaded' ) ) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }
        
        wp_redirect(admin_url('edit.php?post_type=service&page=nsm-settings&tab=tools&cache-cleared=true'));
        exit;
    }

    public function init_elementor_integration() {
        if ( did_action( 'elementor/loaded' ) ) {
            add_action( 'elementor/widgets/register', array( $this, 'register_elementor_widgets' ) );
            add_action( 'elementor/elements/categories_registered', array( $this, 'add_elementor_widget_category' ) );
        }
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
                
                $('.add-repeater-field').on('click', function(e){
                    e.preventDefault();
                    var container = $(this).prev('.repeater-fields-container');
                    var template = container.data('template');
                    var newIndex = new Date().getTime();
                    var newField = template.replace(/__INDEX__/g, newIndex);
                    container.append(newField);
                });

                $(document).on('click', '.remove-repeater-field', function(e){
                    e.preventDefault();
                    if(confirm('آیا از حذف این آیتم مطمئن هستید؟')) {
                        $(this).closest('.nsm-repeater-field').remove();
                    }
                });

                $(document).on('click', '.nsm-upload-button', function(e) {
                    e.preventDefault();
                    var button = $(this);
                    var frame = wp.media({ title: 'انتخاب یا آپلود', button: { text: 'انتخاب' }, multiple: false });
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
                    
                    button.prop('disabled', true);
                    resultDiv.html('در حال ارسال درخواست...').removeClass('nsm-test-success nsm-test-error').show();
                    
                    var data = {
                        'action': 'nsm_test_gateway',
                        'gateway': gateway,
                        '_ajax_nonce': '". wp_create_nonce('nsm_test_nonce') ."'
                    };

                    $.post(ajaxurl, data, function(response) {
                        if(response.success) {
                            resultDiv.html('<strong>موفق:</strong> ' + response.data).addClass('nsm-test-success');
                        } else {
                            resultDiv.html('<strong>خطا:</strong> ' + response.data).addClass('nsm-test-error');
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
                --nsm-text-color: #1f2937; --nsm-text-light: #6b7280; --nsm-bg-light: #f9fafb; --nsm-border-color: #e5e7eb;
                --nsm-success-color: #10b981; --nsm-warning-color: #f59e0b;
                --nsm-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
                --nsm-shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
                --nsm-shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            }
            .nsm-single-service-wrapper { font-family: 'Vazirmatn', sans-serif; }
            .nsm-container { max-width: 1200px; margin: 0 auto; padding: 0 15px; }
            
            /* General Helper Classes */
            .nsm-info-card { background: #fff; padding: 30px; border-radius: 15px; box-shadow: var(--nsm-shadow); margin-bottom: 30px; }
            .nsm-info-card h2 { font-size: 1.5em; font-weight: 700; margin-top: 0; margin-bottom: 25px; border-bottom: 1px solid var(--nsm-border-color); padding-bottom: 15px; }
            .nsm-cta-button { display: block; width: 100%; background-image: linear-gradient(to right, var(--nsm-secondary-color), var(--nsm-primary-color)); color: #fff; text-align: center; padding: 15px; border-radius: 10px; text-decoration: none; font-weight: bold; font-size: 1.1em; transition: all 0.3s ease; box-shadow: var(--nsm-shadow-sm); border: none; cursor: pointer; }
            .nsm-cta-button:hover { transform: translateY(-2px); box-shadow: var(--nsm-shadow); }
            .nsm-payment-form input { width: 100%; padding: 12px; margin-bottom: 10px; border: 1px solid var(--nsm-border-color); border-radius: 8px; }
            .nsm-instructor-profile { display: flex; gap: 20px; align-items: center; background: var(--nsm-bg-light); padding: 20px; border-radius: 10px; margin-bottom: 20px; }
            .nsm-instructor-profile img { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; }
            .nsm-instructor-profile .instructor-name { font-size: 1.2em; font-weight: bold; margin: 0; }
            .nsm-instructor-profile .instructor-bio { font-size: 0.9em; color: #666; }
            .nsm-speakers-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 20px; }
            .nsm-speaker-card { text-align: center; }
            .nsm-speaker-card img { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; margin-bottom: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .nsm-agenda-list { list-style: none; padding: 0; position: relative; }
            .nsm-agenda-list::before { content: ''; position: absolute; right: 5px; top: 0; bottom: 0; width: 2px; background: var(--nsm-border-color); }
            .nsm-agenda-item { position: relative; padding: 10px 25px 10px 0; }
            .nsm-agenda-item::before { content: ''; position: absolute; right: 0; top: 15px; width: 12px; height: 12px; background: var(--nsm-secondary-color); border-radius: 50%; border: 2px solid #fff; box-shadow: 0 0 0 2px var(--nsm-secondary-color); }
            .nsm-includes-excludes { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
            .nsm-includes-excludes h3 { margin-top: 0; font-size: 1.1em; }
            .nsm-includes-excludes ul { margin: 0; padding-right: 20px; }
            .nsm-includes ul li { color: var(--nsm-success-color); }
            .nsm-excludes ul li { color: #F44336; }
            .nsm-pricing-table { width: 100%; border-collapse: collapse; }
            .nsm-pricing-table th, .nsm-pricing-table td { padding: 15px; text-align: right; border-bottom: 1px solid var(--nsm-border-color); }
            .nsm-pricing-table th { background-color: var(--nsm-bg-light); }

            /* --- Template 1: Classic Corporate --- */
            .nsm-layout-1 { background-color: var(--nsm-bg-light); padding: 40px 0; }
            .nsm-layout-1 .nsm-hero-section { display: grid; grid-template-columns: 1fr 1fr; align-items: center; gap: 40px; margin-bottom: 50px; }
            .nsm-layout-1 .nsm-hero-content h1 { font-size: 3em; }
            .nsm-layout-1 .nsm-hero-image img { border-radius: 20px; box-shadow: var(--nsm-shadow); }
            .nsm-layout-1 .nsm-main-layout { display: grid; grid-template-columns: 1fr 350px; gap: 40px; }
            .nsm-layout-1 .nsm-sidebar { position: sticky; top: 30px; }
            .nsm-layout-1 .nsm-sidebar-box { background: #fff; padding: 25px; border-radius: 15px; box-shadow: var(--nsm-shadow); margin-bottom: 25px; }
            .nsm-layout-1 .nsm-meta-pills { display: flex; flex-wrap: wrap; gap: 10px; }
            .nsm-layout-1 .nsm-meta-pill { background: var(--nsm-bg-light); border: 1px solid var(--nsm-border-color); padding: 8px 15px; border-radius: 99px; font-size: 0.9em; }
            .nsm-layout-1 .nsm-price-box .price-value { font-size: 2.5em; font-weight: 700; color: var(--nsm-primary-color); }

            /* --- Template 2: Modern Gallery --- */
            .nsm-layout-2 { background-color: var(--nsm-bg-light); padding-bottom: 40px; }
            .nsm-layout-2 .nsm-hero-gallery { background-color: var(--nsm-text-color); padding: 50px 0; }
            .nsm-layout-2 .nsm-hero-gallery img { width: 100%; height: 500px; object-fit: cover; border-radius: 20px; box-shadow: var(--nsm-shadow-lg); }
            .nsm-layout-2 .nsm-main-layout { margin-top: -100px; position: relative; z-index: 2; }
            .nsm-layout-2 .nsm-title-card { background: #fff; text-align:center; padding: 30px; border-radius: 15px; box-shadow: var(--nsm-shadow); margin-bottom: 30px; }
            .nsm-layout-2 .nsm-title-card h1 { font-size: 3em; margin: 0; }
            .nsm-layout-2 .nsm-meta-pills { justify-content: center; margin-top: 20px; }
            .nsm-layout-2 .nsm-sidebar { position: sticky; top: 30px; }

            /* --- Template 3: Dynamic Visual --- */
            .nsm-layout-3 { background-color: var(--nsm-bg-light); padding-bottom: 50px; }
            .nsm-layout-3 .nsm-hero-section { height: 50vh; min-height: 400px; position: relative; display: flex; align-items: center; justify-content: center; text-align: center; color: #fff; }
            .nsm-layout-3 .nsm-hero-section .nsm-hero-bg { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-size: cover; background-position: center; }
            .nsm-layout-3 .nsm-hero-section::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
            .nsm-layout-3 .nsm-hero-content { position: relative; z-index: 2; }
            .nsm-layout-3 .nsm-hero-content h1 { font-size: 4em; }
            .nsm-layout-3 .nsm-main-layout { margin-top: -80px; position: relative; z-index: 3; }

            /* --- Template 4: Dark Techy --- */
            .nsm-layout-4 { background-color: #111827; color: #d1d5db; padding: 50px 0; }
            .nsm-layout-4 .nsm-hero-content h1 { color: #fff; }
            .nsm-layout-4 .nsm-info-card, .nsm-layout-4 .nsm-sidebar-box { background-color: #1f2937; border: 1px solid #374151; }
            .nsm-layout-4 h2 { color: #fff; border-right-color: var(--nsm-secondary-color); }
            .nsm-layout-4 .nsm-meta-pill { background-color: #374151; border-color: #4b5563; }
            .nsm-layout-4 .nsm-meta-pill .meta-label { color: #9ca3af; }
            .nsm-layout-4 .nsm-meta-pill .meta-value { color: #d1d5db; }
            .nsm-layout-4 .nsm-payment-form input { background: #374151; border-color: #4b5563; color: #fff; }

            /* --- Template 5: Focus & Action --- */
            .nsm-layout-5 { background-color: #fff; padding: 50px 0; }
            .nsm-layout-5 .nsm-main-layout { display: grid; grid-template-columns: 1fr 400px; gap: 50px; align-items: start; }
            .nsm-layout-5 .nsm-main-content { padding-top: 20px; }
            .nsm-layout-5 .nsm-main-content h1 { font-size: 3.5em; margin-top: 0; }
            .nsm-layout-5 .nsm-main-content .service-summary { font-size: 1.2em; color: var(--nsm-text-light); margin-bottom: 40px; }
            .nsm-layout-5 .nsm-sidebar { position: sticky; top: 30px; }
            .nsm-layout-5 .nsm-sidebar-box { background: #fff; border: 1px solid var(--nsm-border-color); }
            .nsm-layout-5 .nsm-featured-image { border-radius: 15px; overflow: hidden; box-shadow: var(--nsm-shadow-lg); margin-bottom: 30px; }
            .nsm-layout-5 .nsm-featured-image img { width: 100%; display: block; }
        ";
        wp_add_inline_style( 'wp-block-library', $css );
    }

    public function register_elementor_widgets( $widgets_manager ) {
        require_once NSM_PLUGIN_DIR . 'elementor-widgets.php';
        
        $widgets_manager->register( new \Elementor_NSM_Services_Grid_Widget() );
        $widgets_manager->register( new \Elementor_NSM_Services_Carousel_Widget() );
        $widgets_manager->register( new \Elementor_NSM_Services_Filter_Widget() );
        $widgets_manager->register( new \Elementor_NSM_Single_Service_Widget() );
        $widgets_manager->register( new \Elementor_NSM_Services_List_Widget() );
        $widgets_manager->register( new \Elementor_NSM_Service_Details_Widget() );
    }

    public function add_elementor_widget_category( $elements_manager ) {
        $elements_manager->add_category(
            'nilay-services',
            [ 'title' => nsm_get_string('elementor_cat_title', 'خدمات نیلای'), 'icon' => 'fa fa-cubes' ]
        );
    }
}

/**
 * کلاس نصب‌کننده افزونه
 */
class NSM_Install {
    public static function activate() {
        NSM_Post_Types::register_post_types();
        NSM_Post_Types::register_taxonomies();
        flush_rewrite_rules();
        
        $default_terminology = [
            'service' => 'خدمت', 'services' => 'خدمات', 'service_category' => 'دسته‌بندی خدمات',
            'payment' => 'پرداخت', 'buy_now' => 'خرید و مشاهده',
            'elementor_cat_title' => 'خدمات نیلای'
        ];
        add_option('nsm_terminology_options', $default_terminology);
    }
    public static function deactivate() {
        flush_rewrite_rules();
    }
}

/**
 * کلاس مدیریت انواع پست و تکسونومی‌ها
 */
class NSM_Post_Types {
    public static function register_post_types() {
        $labels = [ 'name' => nsm_get_string('services', 'خدمات'), 'singular_name' => nsm_get_string('service', 'خدمت'), 'menu_name' => nsm_get_string('services', 'خدمات'), 'name_admin_bar' => nsm_get_string('service', 'خدمت'), 'add_new' => 'افزودن ' . nsm_get_string('service', 'خدمت'), 'add_new_item' => 'افزودن ' . nsm_get_string('service', 'خدمت') . ' جدید', 'new_item' => nsm_get_string('service', 'خدمت') . ' جدید', 'edit_item' => 'ویرایش ' . nsm_get_string('service', 'خدمت'), 'view_item' => 'مشاهده ' . nsm_get_string('service', 'خدمت'), 'all_items' => 'همه ' . nsm_get_string('services', 'خدمات'), 'search_items' => 'جستجوی ' . nsm_get_string('services', 'خدمات'), 'not_found' => 'هیچ ' . nsm_get_string('service', 'خدمت') . 'ی یافت نشد.', ];
        $args = [ 'labels' => $labels, 'public' => true, 'publicly_queryable' => true, 'show_ui' => true, 'show_in_menu' => true, 'query_var' => true, 'rewrite' => [ 'slug' => 'service' ], 'capability_type' => 'post', 'has_archive' => true, 'hierarchical' => false, 'menu_position' => 20, 'menu_icon' => 'dashicons-clipboard', 'supports' => [ 'title', 'thumbnail' ], 'show_in_rest' => true, ];
        register_post_type( 'service', $args );
    }

    public static function register_taxonomies() {
        $cat_labels = [ 'name' => nsm_get_string('service_category', 'دسته‌بندی‌های خدمات'), 'singular_name' => 'دسته‌بندی', 'menu_name' => 'دسته‌بندی‌ها' ];
        $cat_args = [ 'hierarchical' => true, 'labels' => $cat_labels, 'show_ui' => true, 'show_admin_column' => true, 'rewrite' => [ 'slug' => 'service-category' ], 'show_in_rest' => true, ];
        register_taxonomy( 'service_category', [ 'service' ], $cat_args );

        $key_labels = [ 'name' => 'کلیدواژه‌های خدمات', 'singular_name' => 'کلیدواژه', 'menu_name' => 'کلیدواژه‌ها' ];
        $key_args = [ 'hierarchical' => false, 'labels' => $key_labels, 'show_ui' => true, 'show_admin_column' => true, 'rewrite' => [ 'slug' => 'service-keyword' ], 'show_in_rest' => true, ];
        register_taxonomy( 'service_keyword', [ 'service' ], $key_args );
    }
}

/**
 * کلاس مدیریت منوهای مدیریت
 */
class NSM_Admin_Menu {
    public static function register_menus() {
        add_submenu_page('edit.php?post_type=service', 'تنظیمات خدمات نیلای', 'تنظیمات', 'manage_options', 'nsm-settings', ['NSM_Settings_Page', 'render_page']);
        add_submenu_page('edit.php?post_type=service', 'راهنمای افزونه', 'راهنما', 'manage_options', 'nsm-help', ['NSM_Help_Page', 'render_page']);
    }
}

/**
 * کلاس رندر صفحه تنظیمات
 */
class NSM_Settings_Page {
    public static function render_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <?php $active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'general'; ?>
            <h2 class="nav-tab-wrapper">
                <a href="?post_type=service&page=nsm-settings&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">عمومی</a>
                <a href="?post_type=service&page=nsm-settings&tab=payment" class="nav-tab <?php echo $active_tab == 'payment' ? 'nav-tab-active' : ''; ?>">درگاه‌های پرداخت</a>
                <a href="?post_type=service&page=nsm-settings&tab=sms" class="nav-tab <?php echo $active_tab == 'sms' ? 'nav-tab-active' : ''; ?>">سامانه‌های پیامکی</a>
                <a href="?post_type=service&page=nsm-settings&tab=notifications" class="nav-tab <?php echo $active_tab == 'notifications' ? 'nav-tab-active' : ''; ?>">اطلاع‌رسانی‌ها</a>
                <a href="?post_type=service&page=nsm-settings&tab=terminology" class="nav-tab <?php echo $active_tab == 'terminology' ? 'nav-tab-active' : ''; ?>">مدیریت اصطلاحات</a>
                <a href="?post_type=service&page=nsm-settings&tab=tools" class="nav-tab <?php echo $active_tab == 'tools' ? 'nav-tab-active' : ''; ?>">ابزارها</a>
            </h2>
            <form action="options.php" method="post">
                <?php
                if ( $active_tab == 'general' ) {
                    settings_fields( 'nsm_settings_group_general' );
                    do_settings_sections( 'nsm_settings_general' );
                } elseif ( $active_tab == 'payment' ) {
                    settings_fields( 'nsm_settings_group_payment' );
                    do_settings_sections( 'nsm_settings_zarinpal' );
                    echo '<hr>';
                    do_settings_sections( 'nsm_settings_zibal' );
                } elseif ($active_tab == 'sms') {
                    settings_fields( 'nsm_settings_group_sms' );
                    do_settings_sections( 'nsm_settings_kavenegar' );
                    echo '<hr>';
                    do_settings_sections( 'nsm_settings_farazsms' );
                } elseif ($active_tab == 'notifications') {
                    settings_fields( 'nsm_settings_group_notifications' );
                    do_settings_sections( 'nsm_settings_notifications' );
                } elseif ($active_tab == 'terminology') {
                    settings_fields( 'nsm_settings_group_terminology' );
                    do_settings_sections( 'nsm_settings_terminology' );
                } elseif ($active_tab == 'tools') {
                    settings_fields( 'nsm_settings_group_tools' );
                    do_settings_sections( 'nsm_settings_tools' );
                }
                // Hide submit button on tools tab
                if ($active_tab !== 'tools') {
                    submit_button( 'ذخیره تنظیمات' );
                }
                ?>
            </form>
             <?php if (isset($_GET['cache-cleared']) && $_GET['cache-cleared'] == 'true') : ?>
                <div id="message" class="updated notice is-dismissible">
                    <p>کش افزونه و المنتور با موفقیت پاک شد.</p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}

/**
 * کلاس مدیریت تنظیمات
 */
class NSM_Settings {
    public static function register_settings() {
        // General Settings
        register_setting( 'nsm_settings_group_general', 'nsm_general_options' );
        add_settings_section( 'nsm_general_section', 'تنظیمات اصلی', '__return_false', 'nsm_settings_general' );
        add_settings_field( 'active_payment_gateway', 'درگاه پرداخت فعال', [self::class, 'render_select_field'], 'nsm_settings_general', 'nsm_general_section', ['id' => 'active_payment_gateway', 'group' => 'nsm_general_options', 'options' => ['none' => 'غیرفعال', 'zarinpal' => 'زرین پال', 'zibal' => 'زیبال']] );
        add_settings_field( 'active_sms_provider', 'سامانه پیامکی فعال', [self::class, 'render_select_field'], 'nsm_settings_general', 'nsm_general_section', ['id' => 'active_sms_provider', 'group' => 'nsm_general_options', 'options' => ['none' => 'غیرفعال', 'kavenegar' => 'کاوه نگار', 'farazsms' => 'فراز اس ام اس']] );

        // Payment Settings
        register_setting( 'nsm_settings_group_payment', 'nsm_payment_options' );
        add_settings_section( 'nsm_zarinpal_section', 'تنظیمات درگاه پرداخت زرین‌پال', '__return_false', 'nsm_settings_zarinpal' );
        add_settings_field( 'zarinpal_merchant_id', 'کد مرچنت', [self::class, 'render_text_field'], 'nsm_settings_zarinpal', 'nsm_zarinpal_section', ['id' => 'zarinpal_merchant_id', 'group' => 'nsm_payment_options'] );
        add_settings_field( 'zarinpal_test', 'تست اتصال', [self::class, 'render_test_button'], 'nsm_settings_zarinpal', 'nsm_zarinpal_section', ['gateway' => 'zarinpal'] );

        add_settings_section( 'nsm_zibal_section', 'تنظیمات درگاه پرداخت زیبال', '__return_false', 'nsm_settings_zibal' );
        add_settings_field( 'zibal_merchant_id', 'کد مرچنت', [self::class, 'render_text_field'], 'nsm_settings_zibal', 'nsm_zibal_section', ['id' => 'zibal_merchant_id', 'group' => 'nsm_payment_options'] );
        add_settings_field( 'zibal_test', 'تست اتصال', [self::class, 'render_test_button'], 'nsm_settings_zibal', 'nsm_zibal_section', ['gateway' => 'zibal'] );

        // SMS Settings
        register_setting( 'nsm_settings_group_sms', 'nsm_sms_options' );
        add_settings_section( 'nsm_kavenegar_section', 'تنظیمات سامانه پیامکی کاوه‌نگار', '__return_false', 'nsm_settings_kavenegar' );
        add_settings_field( 'kavenegar_api_key', 'کلید API', [self::class, 'render_text_field'], 'nsm_settings_kavenegar', 'nsm_kavenegar_section', ['id' => 'kavenegar_api_key', 'group' => 'nsm_sms_options'] );
        add_settings_field( 'kavenegar_test_mobile', 'شماره موبایل تست', [self::class, 'render_text_field'], 'nsm_settings_kavenegar', 'nsm_kavenegar_section', ['id' => 'kavenegar_test_mobile', 'group' => 'nsm_sms_options'] );
        add_settings_field( 'kavenegar_test', 'تست ارسال', [self::class, 'render_test_button'], 'nsm_settings_kavenegar', 'nsm_kavenegar_section', ['gateway' => 'kavenegar'] );

        add_settings_section( 'nsm_farazsms_section', 'تنظیمات سامانه پیامکی فراز اس‌ام‌اس', '__return_false', 'nsm_settings_farazsms' );
        add_settings_field( 'farazsms_api_key', 'کلید API', [self::class, 'render_text_field'], 'nsm_settings_farazsms', 'nsm_farazsms_section', ['id' => 'farazsms_api_key', 'group' => 'nsm_sms_options'] );
        add_settings_field( 'farazsms_test_mobile', 'شماره موبایل تست', [self::class, 'render_text_field'], 'nsm_settings_farazsms', 'nsm_farazsms_section', ['id' => 'farazsms_test_mobile', 'group' => 'nsm_sms_options'] );
        add_settings_field( 'farazsms_test', 'تست ارسال', [self::class, 'render_test_button'], 'nsm_settings_farazsms', 'nsm_farazsms_section', ['gateway' => 'farazsms'] );
        
        // Notifications Settings
        register_setting( 'nsm_settings_group_notifications', 'nsm_notification_options' );
        add_settings_section( 'nsm_notifications_section', 'الگوهای اطلاع‌رسانی پیامکی', '__return_false', 'nsm_settings_notifications' );
        add_settings_field( 'admin_free_reg', 'ثبت‌نام رایگان (برای مدیر)', [self::class, 'render_textarea_field'], 'nsm_settings_notifications', 'nsm_notifications_section', ['id' => 'admin_free_reg', 'group' => 'nsm_notification_options'] );
        add_settings_field( 'user_free_reg', 'ثبت‌نام رایگان (برای کاربر)', [self::class, 'render_textarea_field'], 'nsm_settings_notifications', 'nsm_notifications_section', ['id' => 'user_free_reg', 'group' => 'nsm_notification_options'] );
        add_settings_field( 'admin_paid_reg', 'خرید موفق (برای مدیر)', [self::class, 'render_textarea_field'], 'nsm_settings_notifications', 'nsm_notifications_section', ['id' => 'admin_paid_reg', 'group' => 'nsm_notification_options'] );
        add_settings_field( 'user_paid_reg', 'خرید موفق (برای کاربر)', [self::class, 'render_textarea_field'], 'nsm_settings_notifications', 'nsm_notifications_section', ['id' => 'user_paid_reg', 'group' => 'nsm_notification_options'] );
        add_settings_field( 'patterns_help', 'راهنمای الگوها', [self::class, 'render_patterns_help'], 'nsm_settings_notifications', 'nsm_notifications_section' );
        
        // Terminology Settings
        register_setting( 'nsm_settings_group_terminology', 'nsm_terminology_options' );
        add_settings_section( 'nsm_terminology_section', 'مدیریت اصطلاحات و عناوین', '__return_false', 'nsm_settings_terminology' );
        $terms = [
            'service' => 'خدمت', 'services' => 'خدمات', 'service_category' => 'دسته‌بندی خدمات',
            'payment' => 'پرداخت', 'buy_now' => 'خرید و مشاهده',
            'elementor_cat_title' => 'عنوان دسته ویجت المنتور',
            'widget_grid_title' => 'عنوان ویجت گرید خدمات',
            'widget_carousel_title' => 'عنوان ویجت کاروسل خدمات',
            'widget_filter_title' => 'عنوان ویجت فیلتر خدمات',
            'widget_list_title' => 'عنوان ویجت لیست خدمات',
            'widget_table_title' => 'عنوان ویجت جدول خدمات',
            'widget_single_title' => 'عنوان ویجت کارت تکی',
            'widget_details_title' => 'عنوان ویجت جزئیات خدمت',
        ];
        foreach ($terms as $key => $label) {
            add_settings_field($key, $label, [self::class, 'render_text_field'], 'nsm_settings_terminology', 'nsm_terminology_section', ['id' => $key, 'group' => 'nsm_terminology_options']);
        }
        
        // Tools Settings
        register_setting( 'nsm_settings_group_tools', 'nsm_tools_options' );
        add_settings_section( 'nsm_tools_section', 'ابزارهای کاربردی', '__return_false', 'nsm_settings_tools' );
        add_settings_field( 'clear_cache', 'پاک کردن کش', [self::class, 'render_clear_cache_button'], 'nsm_settings_tools', 'nsm_tools_section' );
    }

    public static function render_text_field($args) {
        $options = get_option($args['group']);
        $value = isset($options[$args['id']]) ? $options[$args['id']] : '';
        echo '<input type="text" name="' . esc_attr($args['group']) . '[' . esc_attr($args['id']) . ']" value="' . esc_attr($value) . '" class="regular-text">';
    }
    public static function render_textarea_field($args) {
        $options = get_option($args['group']);
        $value = isset($options[$args['id']]) ? $options[$args['id']] : '';
        echo '<textarea name="' . esc_attr($args['group']) . '[' . esc_attr($args['id']) . ']" rows="5" class="large-text">' . esc_textarea($value) . '</textarea>';
    }
    public static function render_select_field($args) {
        $options = get_option($args['group']);
        $value = isset($options[$args['id']]) ? $options[$args['id']] : '';
        echo '<select name="' . esc_attr($args['group']) . '[' . esc_attr($args['id']) . ']">';
        foreach($args['options'] as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($value, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }
    public static function render_test_button($args) {
        $gateway = $args['gateway'];
        echo '<button type="button" class="button nsm-test-button" data-gateway="'.esc_attr($gateway).'">ارسال درخواست تست</button>';
        echo '<p class="description">برای تست، ابتدا تنظیمات را ذخیره کنید.</p>';
        echo '<div id="nsm-test-result-' . esc_attr($gateway) . '" class="nsm-test-result"></div>';
    }
    public static function render_patterns_help() {
        echo '<p class="description">می‌توانید از الگوهای زیر در متن پیامک‌ها استفاده کنید:</p>';
        echo '<code>[service_name]</code>, <code>[user_name]</code>, <code>[user_mobile]</code>, <code>[price]</code>, <code>[date]</code>, <code>[time]</code>, <code>[transaction_id]</code>';
    }
    public static function render_clear_cache_button() {
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="nsm_clear_cache">
            <?php wp_nonce_field( 'nsm_clear_cache_nonce' ); ?>
            <p class="description">اگر تغییرات در ویجت‌های المنتور اعمال نمی‌شوند، از این دکمه برای پاک کردن کش داخلی المنتور استفاده کنید.</p>
            <p><button type="submit" class="button button-primary">پاک کردن کش افزونه و المنتور</button></p>
        </form>
        <?php
    }
}

/**
 * کلاس رندر صفحه راهنما
 */
class NSM_Help_Page {
    public static function render_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <p>به صفحه راهنمای افزونه مدیریت خدمات نیلای خوش آمدید. در این بخش می‌توانید با قابلیت‌های افزونه آشنا شوید.</p>

            <h2>ویجت‌های المنتور</h2>
            <p>این افزونه ویجت‌های اختصاصی زیر را به صفحه‌ساز المنتور اضافه می‌کند:</p>
            <ul>
                <li><strong><?php echo nsm_get_string('widget_grid_title', 'گرید خدمات'); ?>:</strong> برای نمایش خدمات به صورت شبکه‌ای با تنظیمات کامل کوئری، چیدمان و استایل.</li>
                <li><strong><?php echo nsm_get_string('widget_carousel_title', 'کاروسل خدمات'); ?>:</strong> برای نمایش خدمات به صورت اسلایدر با قابلیت پخش خودکار.</li>
                <li><strong><?php echo nsm_get_string('widget_filter_title', 'فیلتر خدمات'); ?>:</strong> برای ایجاد گالری خدمات با قابلیت فیلتر زنده بر اساس دسته‌بندی.</li>
                <li><strong><?php echo nsm_get_string('widget_list_title', 'لیست خدمات'); ?>:</strong> نمایش خدمات به صورت یک لیست متنی ساده، مناسب برای سایدبار.</li>
                <li><strong><?php echo nsm_get_string('widget_table_title', 'جدول خدمات'); ?>:</strong> نمایش خدمات و فیلدهای منتخب در یک جدول قابل سفارشی‌سازی.</li>
                <li><strong><?php echo nsm_get_string('widget_single_title', 'کارت تکی خدمت'); ?>:</strong> برای نمایش یک کارت از یک خدمت خاص در هر صفحه‌ای.</li>
                <li><strong><?php echo nsm_get_string('widget_details_title', 'جزئیات خدمت'); ?>:</strong> ابزاری قدرتمند برای نمایش یک فیلد خاص از خدمت فعلی در صفحات تکی (نیازمند المنتور پرو).</li>
            </ul>
            <p>این ویجت‌ها را می‌توانید در دسته‌بندی «<?php echo nsm_get_string('elementor_cat_title', 'خدمات نیلای'); ?>» در پنل ویجت‌های المنتور پیدا کنید.</p>
        </div>
        <?php
    }
}

/**
 * کلاس مدیریت متاباکس‌ها و فیلدهای سفارشی
 */
class NSM_Meta_Boxes {
    public static $meta_fields = [];
    public static function add_meta_boxes() { add_meta_box('nsm_service_details', 'جزئیات ' . nsm_get_string('service', 'خدمت'), [self::class, 'render_meta_box'], 'service', 'normal', 'high'); }
    public static function render_meta_box( $post ) {
        wp_nonce_field( 'nsm_save_meta_box_data', 'nsm_meta_box_nonce' );
        self::define_fields();
        $service_type = get_post_meta( $post->ID, '_nsm_service_type', true );
        ?>
        <div class="nsm-meta-box-field">
            <label for="_nsm_service_type"><strong>نوع خدمت را انتخاب کنید:</strong></label>
            <select name="_nsm_service_type" id="_nsm_service_type">
                <option value="">انتخاب کنید...</option>
                <?php foreach ( self::get_service_types() as $key => $label ) echo '<option value="' . esc_attr( $key ) . '" ' . selected( $service_type, $key, false ) . '>' . esc_html( $label ) . '</option>'; ?>
            </select>
        </div><hr>
        <?php
        echo '<div id="nsm-group-general" class="nsm-fields-group">';
        foreach (self::$meta_fields['general'] as $field) self::render_field($post->ID, $field);
        echo '</div>';
        foreach (self::$meta_fields as $group_key => $fields) {
            if ($group_key === 'general') continue;
            echo '<div id="nsm-group-' . esc_attr($group_key) . '" class="nsm-fields-group">';
            echo '<h3>' . esc_html(self::get_service_types()[$group_key]) . '</h3>';
            foreach ($fields as $field) self::render_field($post->ID, $field);
            echo '</div>';
        }
    }
    
    private static function render_field($post_id, $field) {
        $value = get_post_meta($post_id, $field['id'], true);
        $extra_class = $field['extra_class'] ?? '';
        echo '<div class="nsm-meta-box-field ' . esc_attr($extra_class) . '">';
        
        echo '<label for="' . esc_attr($field['id']) . '">' . esc_html($field['label']) . '</label>';
        
        if (isset($field['is_premium']) && $field['is_premium']) {
            $is_paid_value = get_post_meta($post_id, $field['id'] . '_is_paid', true);
            echo '<div class="nsm-paid-toggle-container"><label><input type="checkbox" name="' . esc_attr($field['id']) . '_is_paid" value="1" ' . checked($is_paid_value, '1', false) . '> <strong>محتوای ویژه:</strong> با فعال کردن این گزینه، این فیلد فقط به کاربرانی که خدمت را خریداری کرده‌اند نمایش داده می‌شود.</label></div>';
        }

        switch ($field['type']) {
            case 'text': case 'number': case 'url': case 'date': case 'time':
                echo '<input type="' . esc_attr($field['type']) . '" id="' . esc_attr($field['id']) . '" name="' . esc_attr($field['id']) . '" value="' . esc_attr($value) . '">';
                break;
            case 'select':
                echo '<select id="' . esc_attr($field['id']) . '" name="' . esc_attr($field['id']) . '">';
                foreach ($field['options'] as $key => $label) {
                    echo '<option value="' . esc_attr($key) . '" ' . selected($value, $key, false) . '>' . esc_html($label) . '</option>';
                }
                echo '</select>';
                break;
            case 'textarea':
                echo '<textarea id="' . esc_attr($field['id']) . '" name="' . esc_attr($field['id']) . '" rows="5">' . esc_textarea($value) . '</textarea>';
                break;
            case 'wp_editor':
                wp_editor($value, $field['id'], ['textarea_name' => $field['id']]);
                break;
            case 'media':
                 echo '<input type="text" id="' . esc_attr($field['id']) . '" name="' . esc_attr($field['id']) . '" value="' . esc_attr($value) . '" style="width: 70%;" readonly>';
                 echo ' <button type="button" class="button nsm-upload-button">انتخاب</button>';
                 echo '<div class="nsm-image-preview">' . ($value ? '<img src="'.esc_url($value).'" style="max-width:150px; margin-top: 5px;"/>' : '') . '</div>';
                 break;
            case 'repeater':
                echo '<div class="repeater-fields-container" data-template="' . esc_attr(self::get_repeater_template($field)) . '">';
                if (!empty($value) && is_array($value)) {
                    foreach ($value as $index => $item) {
                        echo self::get_repeater_template($field, $index, $item);
                    }
                }
                echo '</div>';
                echo '<button type="button" class="button add-repeater-field">افزودن آیتم جدید</button>';
                break;
        }

        if (!empty($field['desc'])) {
            echo '<p class="description">' . esc_html($field['desc']) . '</p>';
        }
        echo '</div>';
    }

    private static function get_repeater_template($field, $index = '__INDEX__', $item_values = []) {
        $html = '<div class="nsm-repeater-field">';
        $html .= '<a href="#" class="remove-repeater-field dashicons dashicons-no-alt"></a>';
        foreach ($field['fields'] as $sub_field) {
            $name = esc_attr($field['id'] . '[' . $index . '][' . $sub_field['id'] . ']');
            $value = isset($item_values[$sub_field['id']]) ? esc_attr($item_values[$sub_field['id']]) : '';
            $html .= '<div class="nsm-meta-box-field">';
            $html .= '<label>' . esc_html($sub_field['label']) . '</label>';
            if($sub_field['type'] === 'media') {
                 $html .= '<input type="text" name="' . $name . '" value="' . $value . '" style="width: 70%;" readonly>';
                 $html .= ' <button type="button" class="button nsm-upload-button">انتخاب</button>';
                 $html .= '<div class="nsm-image-preview">' . ($value ? '<img src="'.esc_url($value).'" style="max-width:100px; margin-top: 5px;"/>' : '') . '</div>';
            } else {
                $html .= '<input type="text" name="' . $name . '" value="' . $value . '">';
            }
            $html .= '</div>';
        }
        $html .= '</div>';
        return $html;
    }

    public static function save_meta_data( $post_id ) {
        if ( ! isset( $_POST['nsm_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['nsm_meta_box_nonce'], 'nsm_save_meta_box_data' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        self::define_fields();
        
        if (isset($_POST['_nsm_service_type'])) {
            update_post_meta($post_id, '_nsm_service_type', sanitize_text_field($_POST['_nsm_service_type']));
        }
        
        foreach (self::$meta_fields as $group) {
            foreach ($group as $field) {
                // Save main field value
                if (isset($_POST[$field['id']])) {
                    $value = $_POST[$field['id']];
                    if ($field['type'] === 'repeater' && is_array($value)) {
                        $sanitized_repeater = [];
                        foreach ($value as $item) {
                            $sanitized_item = [];
                            if (is_array($item)) {
                                foreach($item as $key => $sub_value) {
                                    $sanitized_item[sanitize_key($key)] = sanitize_text_field($sub_value);
                                }
                            }
                            $sanitized_repeater[] = $sanitized_item;
                        }
                        update_post_meta($post_id, $field['id'], $sanitized_repeater);
                    } elseif ($field['type'] === 'wp_editor' || $field['type'] === 'textarea') {
                        update_post_meta($post_id, $field['id'], wp_kses_post($value));
                    } else {
                        update_post_meta($post_id, $field['id'], sanitize_text_field($value));
                    }
                } else {
                    delete_post_meta($post_id, $field['id']);
                }

                // Save 'is_paid' checkbox value
                if (isset($field['is_premium']) && $field['is_premium']) {
                    $is_paid_key = $field['id'] . '_is_paid';
                    if (isset($_POST[$is_paid_key])) {
                        update_post_meta($post_id, $is_paid_key, '1');
                    } else {
                        delete_post_meta($post_id, $is_paid_key);
                    }
                }
            }
        }
    }
    
    private static function get_service_types() {
        return [ 'educational' => 'آموزشی و فرهنگی', 'events' => 'رویدادها و همایش‌ها', 'art' => 'هنری و سرگرمی', 'consulting' => 'مشاوره و کوچینگ', 'digital' => 'طراحی و خدمات دیجیتال', 'technical' => 'فنی و مهندسی', 'health' => 'سلامتی و زیبایی', 'tourism' => 'گردشگری و اقامتی', 'ceremonial' => 'تشریفات و تدارکات', ];
    }

    public static function define_fields() {
        self::$meta_fields = [
            'general' => [
                ['id' => '_nsm_service_template', 'label' => 'قالب نمایش صفحه', 'type' => 'select', 'options' => [
                    'layout-1' => 'قالب ۱: کلاسیک و شرکتی',
                    'layout-2' => 'قالب ۲: گالری مدرن',
                    'layout-3' => 'قالب ۳: پویا و بصری',
                    'layout-4' => 'قالب ۴: تیره و تکنولوژی محور',
                    'layout-5' => 'قالب ۵: فوکوس و اقدام',
                ], 'desc' => 'قالب نمایش صفحه تکی این خدمت را انتخاب کنید.'],
                ['id' => '_nsm_payment_model', 'label' => 'مدل فروش', 'type' => 'select', 'options' => ['free' => 'رایگان', 'paid' => 'پولی']],
                ['id' => '_nsm_price', 'label' => 'هزینه/مبلغ', 'type' => 'number', 'desc' => 'مبلغ را به تومان وارد کنید.', 'extra_class' => 'nsm-paid-options'],
                ['id' => '_nsm_reg_link', 'label' => 'لینک ثبت‌نام/خرید خارجی', 'type' => 'url', 'desc' => 'اگر این فیلد پر شود، کاربر به این لینک هدایت میشود. در غیر این صورت از درگاه پرداخت افزونه استفاده خواهد شد.', 'extra_class' => 'nsm-paid-options'],
                ['id' => '_nsm_service_summary', 'label' => 'چکیده خدمت', 'type' => 'textarea', 'desc' => 'یک توضیح کوتاه برای نمایش در لیست خدمات (جایگزین چکیده پیش‌فرض وردپرس).'],
                ['id' => '_nsm_service_details', 'label' => 'توضیحات کامل خدمت', 'type' => 'wp_editor', 'desc' => 'این محتوا در بخش اصلی صفحه خدمت نمایش داده می‌شود.'],
                ['id' => '_nsm_is_featured', 'label' => 'خدمت ویژه', 'type' => 'select', 'options' => ['no' => 'خیر', 'yes' => 'بله'], 'desc' => 'خدمات ویژه را میتوانید در ویجت ها جداگانه نمایش دهید.'],
                ['id' => '_nsm_status', 'label' => 'وضعیت خدمت', 'type' => 'select', 'options' => ['available' => 'در دسترس', 'full' => 'تکمیل ظرفیت', 'ended' => 'پایان یافته']],
                ['id' => '_nsm_date', 'label' => 'تاریخ برگزاری/شروع', 'type' => 'date'],
                ['id' => '_nsm_time', 'label' => 'ساعت برگزاری/شروع', 'type' => 'time'],
                ['id' => '_nsm_paid_content', 'label' => 'محتوای ویژه کاربران خریدار', 'type' => 'wp_editor', 'extra_class' => 'nsm-paid-options', 'is_premium' => true],
            ],
            'educational' => [
                ['id' => '_nsm_edu_instructor', 'label' => 'نام مدرس', 'type' => 'text'],
                ['id' => '_nsm_edu_instructor_photo', 'label' => 'عکس مدرس', 'type' => 'media'],
                ['id' => '_nsm_edu_instructor_bio', 'label' => 'بیوگرافی کوتاه مدرس', 'type' => 'textarea', 'is_premium' => false],
                ['id' => '_nsm_edu_format', 'label' => 'شیوه برگزاری', 'type' => 'select', 'options' => ['online' => 'آنلاین', 'in_person' => 'حضوری', 'hybrid' => 'ترکیبی']],
                ['id' => '_nsm_edu_level', 'label' => 'سطح دوره', 'type' => 'select', 'options' => ['beginner' => 'مقدماتی', 'intermediate' => 'متوسط', 'advanced' => 'پیشرفته']],
                ['id' => '_nsm_edu_sessions', 'label' => 'تعداد جلسات', 'type' => 'number'],
                ['id' => '_nsm_edu_duration', 'label' => 'مدت زمان کل دوره (ساعت)', 'type' => 'number'],
                ['id' => '_nsm_edu_prerequisites', 'label' => 'پیش‌نیازها', 'type' => 'textarea'],
                ['id' => '_nsm_edu_audience', 'label' => 'مخاطبین دوره', 'type' => 'textarea'],
                ['id' => '_nsm_edu_syllabus', 'label' => 'سرفصل‌های دوره', 'type' => 'wp_editor', 'is_premium' => true],
                ['id' => '_nsm_edu_downloads', 'label' => 'فایل‌های ضمیمه', 'type' => 'repeater', 'fields' => [['id' => 'title', 'label' => 'عنوان فایل'], ['id' => 'url', 'label' => 'لینک دانلود']], 'is_premium' => true],
                ['id' => '_nsm_edu_certificate', 'label' => 'ارائه گواهینامه', 'type' => 'select', 'options' => ['no' => 'خیر', 'yes' => 'بله']],
                ['id' => '_nsm_edu_certificate_details', 'label' => 'جزئیات گواهینامه', 'type' => 'text', 'desc' => 'مثلا: گواهی معتبر از دانشگاه تهران', 'is_premium' => true],
            ],
            'events' => [
                ['id' => '_nsm_evt_type', 'label' => 'نوع رویداد', 'type' => 'select', 'options' => ['conference' => 'کنفرانس', 'webinar' => 'وبینار', 'workshop' => 'کارگاه آموزشی', 'seminar' => 'سمینار']],
                ['id' => '_nsm_evt_organizer', 'label' => 'نام برگزارکننده', 'type' => 'text'],
                ['id' => '_nsm_evt_capacity', 'label' => 'ظرفیت رویداد (نفر)', 'type' => 'number'],
                ['id' => '_nsm_evt_location', 'label' => 'محل برگزاری', 'type' => 'text', 'desc' => 'آدرس دقیق یا پلتفرم آنلاین را وارد کنید.', 'is_premium' => true],
                ['id' => '_nsm_evt_map', 'label' => 'کد Embed نقشه گوگل', 'type' => 'textarea', 'is_premium' => true],
                ['id' => '_nsm_evt_speakers', 'label' => 'سخنرانان / اجرا کنندگان', 'type' => 'repeater', 'fields' => [['id' => 'name', 'label' => 'نام'], ['id' => 'title', 'label' => 'عنوان/سمت'], ['id' => 'photo', 'label' => 'عکس', 'type' => 'media']]],
                ['id' => '_nsm_evt_agenda', 'label' => 'برنامه زمان‌بندی رویداد', 'type' => 'repeater', 'fields' => [['id' => 'time', 'label' => 'زمان (مثلا 09:00)'], ['id' => 'title', 'label' => 'عنوان برنامه'], ['id' => 'description', 'label' => 'توضیحات']], 'is_premium' => true],
                ['id' => '_nsm_evt_tickets', 'label' => 'انواع بلیت', 'type' => 'repeater', 'fields' => [['id' => 'type', 'label' => 'نوع بلیت (VIP)'], ['id' => 'price', 'label' => 'قیمت'], ['id' => 'features', 'label' => 'ویژگی‌ها']]],
                ['id' => '_nsm_evt_sponsors', 'label' => 'اسپانسرها', 'type' => 'repeater', 'fields' => [['id' => 'name', 'label' => 'نام اسپانسر'], ['id' => 'logo', 'label' => 'لوگو', 'type' => 'media']]],
            ],
            'consulting' => [
                ['id' => '_nsm_con_consultant', 'label' => 'نام مشاور/کوچ', 'type' => 'text'],
                ['id' => '_nsm_con_photo', 'label' => 'عکس مشاور', 'type' => 'media'],
                ['id' => '_nsm_con_experience', 'label' => 'سال‌های تجربه', 'type' => 'number'],
                ['id' => '_nsm_con_specializations', 'label' => 'حوزه‌های تخصصی', 'type' => 'textarea', 'desc' => 'هر تخصص را با کاما (,) جدا کنید.'],
                ['id' => '_nsm_con_credentials', 'label' => 'تحصیلات و گواهینامه‌ها', 'type' => 'textarea'],
                ['id' => '_nsm_con_duration', 'label' => 'مدت زمان هر جلسه (دقیقه)', 'type' => 'number'],
                ['id' => '_nsm_con_type', 'label' => 'نوع جلسه', 'type' => 'select', 'options' => ['phone' => 'تلفنی', 'online' => 'آنلاین تصویری', 'in_person' => 'حضوری']],
                ['id' => '_nsm_con_packages', 'label' => 'پکیج‌های مشاوره', 'type' => 'repeater', 'fields' => [['id' => 'title', 'label' => 'عنوان پکیج'], ['id' => 'sessions', 'label' => 'تعداد جلسات'], ['id' => 'price', 'label' => 'قیمت']]],
                ['id' => '_nsm_con_booking_shortcode', 'label' => 'شورت‌کد سیستم رزرو', 'type' => 'text', 'is_premium' => true],
            ],
             'tourism' => [
                ['id' => '_nsm_trs_destination', 'label' => 'مقصد', 'type' => 'text'],
                ['id' => '_nsm_trs_duration', 'label' => 'مدت زمان تور', 'type' => 'text', 'desc' => 'مثال: 3 روز و 2 شب'],
                ['id' => '_nsm_trs_vehicle', 'label' => 'وسیله نقلیه', 'type' => 'text'],
                ['id' => '_nsm_trs_accommodation', 'label' => 'محل اقامت', 'type' => 'text'],
                ['id' => '_nsm_trs_includes', 'label' => 'خدمات تور شامل', 'type' => 'textarea', 'desc' => 'هر مورد را در یک خط بنویسید.'],
                ['id' => '_nsm_trs_excludes', 'label' => 'خدمات تور شامل نمی‌شود', 'type' => 'textarea', 'desc' => 'هر مورد را در یک خط بنویسید.'],
                ['id' => '_nsm_trs_itinerary', 'label' => 'برنامه سفر روزانه', 'type' => 'wp_editor', 'is_premium' => true],
            ],
        ];
    }
}

/**
 * کلاس مدیریت API ها
 */
class NSM_API_Handler {
    public static function ajax_test_gateway() {
        check_ajax_referer('nsm_test_nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('دسترسی غیرمجاز.');
        
        $gateway = sanitize_text_field($_POST['gateway']);
        $payment_options = get_option('nsm_payment_options');
        $sms_options = get_option('nsm_sms_options');

        switch ($gateway) {
            case 'zarinpal':
                $merchant_id = $payment_options['zarinpal_merchant_id'] ?? '';
                if (empty($merchant_id)) wp_send_json_error('کد مرچنت زرین پال وارد نشده است.');
                if (strlen($merchant_id) === 36) wp_send_json_success('کد مرچنت معتبر به نظر می‌رسد. (تست شبیه‌سازی شده)');
                else wp_send_json_error('کد مرچنت زرین پال باید 36 کاراکتر باشد.');
                break;
            case 'zibal':
                $merchant_id = $payment_options['zibal_merchant_id'] ?? '';
                if (empty($merchant_id)) wp_send_json_error('کد مرچنت زیبال وارد نشده است.');
                wp_send_json_success('اتصال با زیبال موفقیت آمیز بود (تست شبیه‌سازی شده).');
                break;
            case 'kavenegar':
                $api_key = $sms_options['kavenegar_api_key'] ?? '';
                $mobile = $sms_options['kavenegar_test_mobile'] ?? '';
                if (empty($api_key)) wp_send_json_error('کلید API کاوه نگار وارد نشده است.');
                if (empty($mobile) || !preg_match('/^09[0-9]{9}$/', $mobile)) wp_send_json_error('شماره موبایل تست، معتبر نیست.');
                wp_send_json_success("پیامک تست به شماره {$mobile} ارسال شد (تست شبیه‌سازی شده).");
                break;
            case 'farazsms':
                $api_key = $sms_options['farazsms_api_key'] ?? '';
                $mobile = $sms_options['farazsms_test_mobile'] ?? '';
                if (empty($api_key)) wp_send_json_error('کلید API فراز اس ام اس وارد نشده است.');
                if (empty($mobile) || !preg_match('/^09[0-9]{9}$/', $mobile)) wp_send_json_error('شماره موبایل تست، معتبر نیست.');
                wp_send_json_success("پیامک تست به شماره {$mobile} ارسال شد (تست شبیه‌سازی شده).");
                break;
            default:
                wp_send_json_error('درگاه نامعتبر است.');
        }
    }
}

/**
 * کلاس مدیریت پرداخت
 */
class NSM_Payment_Handler {
    public static function handle_payment_request() {
        if ( !isset($_POST['nsm_payment_nonce']) || !wp_verify_nonce($_POST['nsm_payment_nonce'], 'nsm_submit_payment') ) return;
        
        $service_id = intval($_POST['service_id']);
        $user_name = sanitize_text_field($_POST['user_name']);
        $user_mobile = sanitize_text_field($_POST['user_mobile']);
        // Basic validation
        if (!$service_id || !$user_name || !preg_match('/^09[0-9]{9}$/', $user_mobile)) {
            wp_die('اطلاعات وارد شده نامعتبر است. لطفا به صفحه قبل بازگردید.');
        }

        $price = get_post_meta($service_id, '_nsm_price', true);
        $general_options = get_option('nsm_general_options');
        $active_gateway = $general_options['active_payment_gateway'] ?? 'none';

        if ($active_gateway === 'none' || !$price) {
            wp_die('امکان پرداخت برای این خدمت در حال حاضر وجود ندارد.');
        }
        
        // This is a simulation
        $callback_url = add_query_arg(['service_id' => $service_id, 'status' => 'success', 'track_id' => rand(1000,9999)], get_permalink($service_id));
        
        NSM_Notification_Handler::send_sms('user_paid_reg', $user_mobile, $service_id);
        NSM_Notification_Handler::send_sms('admin_paid_reg', get_option('admin_email'), $service_id);

        wp_redirect($callback_url);
        exit;
    }
}

/**
 * کلاس مدیریت اطلاع رسانی ها
 */
class NSM_Notification_Handler {
    public static function send_sms($template_key, $recipient, $service_id) {
        $general_options = get_option('nsm_general_options');
        $sms_provider = $general_options['active_sms_provider'] ?? 'none';
        if ($sms_provider === 'none') return false;

        $notification_options = get_option('nsm_notification_options');
        $template = $notification_options[$template_key] ?? '';
        if (empty($template)) return false;

        $message = self::replace_patterns($template, $service_id);
        
        return true;
    }

    private static function replace_patterns($template, $service_id) {
        $service = get_post($service_id);
        $price = get_post_meta($service_id, '_nsm_price', true);
        $date = get_post_meta($service_id, '_nsm_date', true);
        $time = get_post_meta($service_id, '_nsm_time', true);
        
        $user_name = 'کاربر تست'; 
        $user_mobile = '09123456789';
        $transaction_id = 'XYZ123';

        $patterns = [
            '[service_name]' => $service->post_title,
            '[user_name]' => $user_name,
            '[user_mobile]' => $user_mobile,
            '[price]' => number_format($price) . ' تومان',
            '[date]' => $date,
            '[time]' => $time,
            '[transaction_id]' => $transaction_id,
        ];

        return str_replace(array_keys($patterns), array_values($patterns), $template);
    }
}


/**
 * کلاس مدیریت نمایش در فرانت‌اند
 */
class NSM_Frontend {
    public static function display_single_service_page($content) {
        if (is_singular('service') && in_the_loop() && is_main_query()) {
            $post_id = get_the_ID();
            $template = get_post_meta($post_id, '_nsm_service_template', true) ?: 'layout-1';
            
            switch ($template) {
                case 'layout-2': return self::render_template_two($post_id);
                case 'layout-3': return self::render_template_three($post_id);
                case 'layout-4': return self::render_template_four($post_id);
                case 'layout-5': return self::render_template_five($post_id);
                case 'layout-1': default: return self::render_template_one($post_id);
            }
        }
        return $content;
    }

    // --- TEMPLATE 1: CLASSIC CORPORATE ---
    private static function render_template_one($post_id) {
        $service_type = get_post_meta($post_id, '_nsm_service_type', true);
        $thumb_url = get_the_post_thumbnail_url($post_id, 'full') ?: 'https://placehold.co/1200x800/e0e7ff/4f46e5?text=Nilay+Service';
        ob_start();
        ?>
        <div class="nsm-single-service-wrapper nsm-layout-1">
            <div class="nsm-container">
                <section class="nsm-hero-section">
                    <div class="nsm-hero-content">
                        <h1><?php the_title(); ?></h1>
                        <p class="service-summary"><?php echo esc_html(get_post_meta($post_id, '_nsm_service_summary', true)); ?></p>
                    </div>
                    <div class="nsm-hero-image">
                        <img src="<?php echo esc_url($thumb_url); ?>" alt="<?php the_title_attribute(); ?>">
                    </div>
                </section>
                <div class="nsm-main-layout">
                    <main class="nsm-main-content">
                        <?php self::render_main_content_by_type($post_id, $service_type); ?>
                    </main>
                    <aside class="nsm-sidebar">
                        <div class="nsm-sidebar-box">
                            <?php self::render_payment_box($post_id); ?>
                        </div>
                        <div class="nsm-sidebar-box">
                            <?php self::render_sidebar_meta($post_id, $service_type); ?>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    // --- TEMPLATE 2: MODERN GALLERY ---
    private static function render_template_two($post_id) {
        $service_type = get_post_meta($post_id, '_nsm_service_type', true);
        $thumb_url = get_the_post_thumbnail_url($post_id, 'full') ?: 'https://placehold.co/1200x800/e0e7ff/4f46e5?text=Nilay+Service';
        ob_start();
        ?>
        <div class="nsm-single-service-wrapper nsm-layout-2">
            <div class="nsm-hero-gallery">
                <div class="nsm-container">
                    <img src="<?php echo esc_url($thumb_url); ?>" alt="<?php the_title_attribute(); ?>">
                </div>
            </div>
            <div class="nsm-container">
                <div class="nsm-main-layout">
                    <main class="nsm-main-content">
                        <div class="nsm-title-card">
                             <h1><?php the_title(); ?></h1>
                             <div class="nsm-meta-pills">
                                <?php self::render_sidebar_meta($post_id, $service_type, false); ?>
                            </div>
                        </div>
                        <?php self::render_main_content_by_type($post_id, $service_type); ?>
                    </main>
                    <aside class="nsm-sidebar">
                        <div class="nsm-sidebar-box">
                            <?php self::render_payment_box($post_id); ?>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    // --- TEMPLATE 3: DYNAMIC VISUAL ---
    private static function render_template_three($post_id) {
        $service_type = get_post_meta($post_id, '_nsm_service_type', true);
        $thumb_url = get_the_post_thumbnail_url($post_id, 'full') ?: 'https://placehold.co/1200x800/e0e7ff/4f46e5?text=Nilay+Service';
        ob_start();
        ?>
        <div class="nsm-single-service-wrapper nsm-layout-3">
            <section class="nsm-hero-section">
                <div class="nsm-hero-bg" style="background-image: url('<?php echo esc_url($thumb_url); ?>');"></div>
                <div class="nsm-hero-content">
                    <h1><?php the_title(); ?></h1>
                </div>
            </section>
            <div class="nsm-container">
                <div class="nsm-main-layout">
                    <main class="nsm-main-content">
                        <?php self::render_main_content_by_type($post_id, $service_type); ?>
                    </main>
                    <aside class="nsm-sidebar">
                        <div class="nsm-sidebar-box">
                            <?php self::render_payment_box($post_id); ?>
                        </div>
                        <div class="nsm-sidebar-box">
                            <?php self::render_sidebar_meta($post_id, $service_type); ?>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    // --- TEMPLATE 4: DARK TECHY ---
    private static function render_template_four($post_id) {
        $service_type = get_post_meta($post_id, '_nsm_service_type', true);
        $thumb_url = get_the_post_thumbnail_url($post_id, 'full') ?: 'https://placehold.co/1200x800/e0e7ff/4f46e5?text=Nilay+Service';
        ob_start();
        ?>
        <div class="nsm-single-service-wrapper nsm-layout-4">
            <div class="nsm-container">
                <section class="nsm-hero-section">
                     <div class="nsm-hero-content">
                        <h1><?php the_title(); ?></h1>
                        <p class="service-summary"><?php echo esc_html(get_post_meta($post_id, '_nsm_service_summary', true)); ?></p>
                    </div>
                    <div class="nsm-hero-image">
                        <img src="<?php echo esc_url($thumb_url); ?>" alt="<?php the_title_attribute(); ?>">
                    </div>
                </section>
                <div class="nsm-main-layout">
                    <main class="nsm-main-content">
                        <?php self::render_main_content_by_type($post_id, $service_type); ?>
                    </main>
                    <aside class="nsm-sidebar">
                        <div class="nsm-sidebar-box">
                            <?php self::render_payment_box($post_id); ?>
                        </div>
                        <div class="nsm-sidebar-box">
                            <?php self::render_sidebar_meta($post_id, $service_type); ?>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    // --- TEMPLATE 5: FOCUS & ACTION ---
    private static function render_template_five($post_id) {
        $service_type = get_post_meta($post_id, '_nsm_service_type', true);
        $thumb_url = get_the_post_thumbnail_url($post_id, 'full');
        ob_start();
        ?>
        <div class="nsm-single-service-wrapper nsm-layout-5">
            <div class="nsm-container">
                <div class="nsm-main-layout">
                    <main class="nsm-main-content">
                        <h1><?php the_title(); ?></h1>
                        <p class="service-summary"><?php echo esc_html(get_post_meta($post_id, '_nsm_service_summary', true)); ?></p>
                        <?php if ($thumb_url): ?>
                        <div class="nsm-featured-image">
                            <img src="<?php echo esc_url($thumb_url); ?>" alt="<?php the_title_attribute(); ?>">
                        </div>
                        <?php endif; ?>
                        <?php self::render_main_content_by_type($post_id, $service_type); ?>
                    </main>
                    <aside class="nsm-sidebar">
                        <div class="nsm-sidebar-box">
                            <?php self::render_payment_box($post_id); ?>
                        </div>
                         <div class="nsm-sidebar-box">
                             <?php self::render_sidebar_meta($post_id, $service_type); ?>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // --- HELPER FUNCTIONS ---
    private static function render_main_content_by_type($post_id, $service_type) {
        $details = get_post_meta($post_id, '_nsm_service_details', true);
        self::render_content_section('توضیحات کامل', $details);

        switch ($service_type) {
            case 'educational': self::render_educational_content($post_id); break;
            case 'events': self::render_events_content($post_id); break;
            case 'consulting': self::render_consulting_content($post_id); break;
            case 'tourism': self::render_tourism_content($post_id); break;
            case 'digital': self::render_digital_content($post_id); break;
        }
        
        $paid_content = get_post_meta($post_id, '_nsm_paid_content', true);
        $user_has_purchased = (isset($_GET['status']) && $_GET['status'] === 'success');
        if ($paid_content && $user_has_purchased) {
             self::render_content_section('محتوای ویژه', $paid_content, true);
        }
    }

    private static function render_educational_content($post_id) {
        $instructor_photo = get_post_meta($post_id, '_nsm_edu_instructor_photo', true);
        $instructor_name = get_post_meta($post_id, '_nsm_edu_instructor', true);
        $instructor_bio = get_post_meta($post_id, '_nsm_edu_instructor_bio', true);
        if ($instructor_name) {
            echo '<div class="nsm-info-card"><h2>درباره مدرس</h2><div class="nsm-instructor-profile">';
            if($instructor_photo) echo '<img src="'.esc_url($instructor_photo).'" alt="'.esc_attr($instructor_name).'"/>';
            echo '<div><h3 class="instructor-name">'.esc_html($instructor_name).'</h3><p class="instructor-bio">'.esc_html($instructor_bio).'</p></div>';
            echo '</div></div>';
        }
        $syllabus = get_post_meta($post_id, '_nsm_edu_syllabus', true);
        self::render_content_section('سرفصل‌ها', $syllabus, true);
        
        $prerequisites = get_post_meta($post_id, '_nsm_edu_prerequisites', true);
        self::render_content_section('پیش‌نیازها', $prerequisites, true);
        
        $audience = get_post_meta($post_id, '_nsm_edu_audience', true);
        self::render_content_section('مخاطبین دوره', $audience, true);
    }

    private static function render_events_content($post_id) {
        $speakers = get_post_meta($post_id, '_nsm_evt_speakers', true);
        if ($speakers && is_array($speakers)) {
            echo '<div class="nsm-info-card"><h2>سخنرانان</h2><div class="nsm-speakers-grid">';
            foreach($speakers as $speaker) {
                echo '<div class="nsm-speaker-card">';
                if(!empty($speaker['photo'])) echo '<img src="'.esc_url($speaker['photo']).'" alt="'.esc_attr($speaker['name']).'"/>';
                echo '<div class="speaker-name">'.esc_html($speaker['name']).'</div>';
                echo '<div class="speaker-title">'.esc_html($speaker['title']).'</div>';
                echo '</div>';
            }
            echo '</div></div>';
        }
        $agenda = get_post_meta($post_id, '_nsm_evt_agenda', true);
        if ($agenda && is_array($agenda)) {
            echo '<div class="nsm-info-card"><h2>برنامه زمان‌بندی</h2><ul class="nsm-agenda-list">';
            foreach($agenda as $item) {
                echo '<li class="nsm-agenda-item"><div class="nsm-agenda-time">'.esc_html($item['time']).'</div><h4 class="nsm-agenda-title">'.esc_html($item['title']).'</h4><p class="nsm-agenda-desc">'.esc_html($item['description']).'</p></li>';
            }
            echo '</ul></div>';
        }
        $tickets = get_post_meta($post_id, '_nsm_evt_tickets', true);
        if ($tickets && is_array($tickets)) {
             echo '<div class="nsm-info-card"><h2>انواع بلیت</h2><table class="nsm-pricing-table"><thead><tr><th>نوع بلیت</th><th>ویژگی‌ها</th><th>قیمت</th></tr></thead><tbody>';
            foreach($tickets as $ticket) {
                echo '<tr><td>'.esc_html($ticket['type']).'</td><td>'.esc_html($ticket['features']).'</td><td>'.esc_html($ticket['price']).'</td></tr>';
            }
            echo '</tbody></table></div>';
        }
        $map = get_post_meta($post_id, '_nsm_evt_map', true);
        self::render_content_section('نقشه', $map, true);
    }

    private static function render_consulting_content($post_id) {
        $consultant_photo = get_post_meta($post_id, '_nsm_con_photo', true);
        $consultant_name = get_post_meta($post_id, '_nsm_con_consultant', true);
        $specializations = get_post_meta($post_id, '_nsm_con_specializations', true);
        if ($consultant_name) {
            echo '<div class="nsm-info-card"><h2>درباره مشاور</h2><div class="nsm-instructor-profile">';
            if($consultant_photo) echo '<img src="'.esc_url($consultant_photo).'" alt="'.esc_attr($consultant_name).'"/>';
            echo '<div><h3 class="instructor-name">'.esc_html($consultant_name).'</h3><p class="instructor-bio">'.esc_html($specializations).'</p></div>';
            echo '</div></div>';
        }
        $credentials = get_post_meta($post_id, '_nsm_con_credentials', true);
        self::render_content_section('تحصیلات و گواهینامه‌ها', $credentials, true);
        
        $packages = get_post_meta($post_id, '_nsm_con_packages', true);
        if ($packages && is_array($packages)) {
             echo '<div class="nsm-info-card"><h2>پکیج‌های مشاوره</h2><table class="nsm-pricing-table"><thead><tr><th>عنوان پکیج</th><th>تعداد جلسات</th><th>قیمت</th></tr></thead><tbody>';
            foreach($packages as $package) {
                echo '<tr><td>'.esc_html($package['title']).'</td><td>'.esc_html($package['sessions']).'</td><td>'.esc_html($package['price']).'</td></tr>';
            }
            echo '</tbody></table></div>';
        }
    }

    private static function render_tourism_content($post_id) {
        $includes = get_post_meta($post_id, '_nsm_trs_includes', true);
        $excludes = get_post_meta($post_id, '_nsm_trs_excludes', true);
        $itinerary = get_post_meta($post_id, '_nsm_trs_itinerary', true);

        if ($includes || $excludes) {
            echo '<div class="nsm-info-card"><div class="nsm-includes-excludes">';
            if ($includes) {
                echo '<div class="nsm-includes"><h3>خدمات تور شامل</h3><ul>';
                $include_items = explode("\n", $includes);
                foreach ($include_items as $item) { if(trim($item)) echo '<li>' . esc_html(trim($item)) . '</li>'; }
                echo '</ul></div>';
            }
            if ($excludes) {
                echo '<div class="nsm-excludes"><h3>خدمات تور شامل نمی‌شود</h3><ul>';
                $exclude_items = explode("\n", $excludes);
                foreach ($exclude_items as $item) { if(trim($item)) echo '<li>' . esc_html(trim($item)) . '</li>'; }
                echo '</ul></div>';
            }
            echo '</div></div>';
        }
        self::render_content_section('برنامه سفر روزانه', $itinerary, true);
    }
    
    private static function render_digital_content($post_id) {
        $portfolio = get_post_meta($post_id, '_nsm_dig_portfolio', true);
        $process = get_post_meta($post_id, '_nsm_dig_process', true);
        if($portfolio) {
            echo '<a href="'.esc_url($portfolio).'" target="_blank" class="nsm-cta-button" style="margin-bottom:20px; background-image: none; background-color: var(--nsm-accent-color);">مشاهده نمونه کارها</a>';
        }
        self::render_content_section('فرآیند انجام کار', $process, true);
    }
    
    private static function render_sidebar_meta($post_id, $service_type, $with_box = true) {
        if($with_box) echo '<h3>اطلاعات کلیدی</h3>';
        echo '<div class="nsm-meta-pills">';
        $status_map = ['available' => 'در دسترس', 'full' => 'تکمیل ظرفیت', 'ended' => 'پایان یافته'];
        $status_val = get_post_meta($post_id, '_nsm_status', true);
        self::render_meta_pill('وضعیت', $status_map[$status_val] ?? $status_val);
        
        switch($service_type) {
            case 'educational':
                $level_map = ['beginner' => 'مقدماتی', 'intermediate' => 'متوسط', 'advanced' => 'پیشرفته'];
                $level_val = get_post_meta($post_id, '_nsm_edu_level', true);
                self::render_meta_pill('سطح دوره', $level_map[$level_val] ?? $level_val);
                self::render_meta_pill('تعداد جلسات', get_post_meta($post_id, '_nsm_edu_sessions', true));
                $duration = get_post_meta($post_id, '_nsm_edu_duration', true);
                if($duration) self::render_meta_pill('مدت دوره', $duration . ' ساعت');
                break;
            case 'events':
                $capacity = get_post_meta($post_id, '_nsm_evt_capacity', true);
                if($capacity) self::render_meta_pill('ظرفیت', $capacity . ' نفر');
                self::render_meta_pill('محل برگزاری', get_post_meta($post_id, '_nsm_evt_location', true));
                break;
            case 'consulting':
                $duration = get_post_meta($post_id, '_nsm_con_duration', true);
                if($duration) self::render_meta_pill('مدت جلسه', $duration . ' دقیقه');
                $type_map = ['phone' => 'تلفنی', 'online' => 'آنلاین تصویری', 'in_person' => 'حضوری'];
                $type_val = get_post_meta($post_id, '_nsm_con_type', true);
                self::render_meta_pill('نوع جلسه', $type_map[$type_val] ?? $type_val);
                break;
            case 'tourism':
                self::render_meta_pill('مقصد', get_post_meta($post_id, '_nsm_trs_destination', true));
                self::render_meta_pill('مدت تور', get_post_meta($post_id, '_nsm_trs_duration', true));
                break;
            case 'digital':
                self::render_meta_pill('زمان تحویل', get_post_meta($post_id, '_nsm_dig_delivery_time', true));
                self::render_meta_pill('تعداد بازبینی', get_post_meta($post_id, '_nsm_dig_revisions', true));
                break;
        }
        echo '</div>';
    }

    private static function render_payment_box($post_id) {
        $payment_model = get_post_meta($post_id, '_nsm_payment_model', true);
        $price = get_post_meta($post_id, '_nsm_price', true);
        $reg_link = get_post_meta($post_id, '_nsm_reg_link', true);
        $user_has_purchased = (isset($_GET['status']) && $_GET['status'] === 'success');

        if ($user_has_purchased) {
            echo '<div class="nsm-paid-content-block" style="border-color:var(--nsm-success-color); background:#dff0d8;"><p>ثبت‌نام شما با موفقیت انجام شد.</p></div>';
            return;
        }

        if ($payment_model === 'paid') {
            echo '<div class="nsm-price-box"><div class="price-label">هزینه ثبت نام</div><div class="price-value">'.number_format($price).' <span style="font-size:0.4em">تومان</span></div></div>';
            if (!empty($reg_link)) {
                echo '<a href="'.esc_url($reg_link).'" class="nsm-cta-button">'.nsm_get_string('buy_now', 'ثبت نام و خرید').'</a>';
            } else {
                ?>
                <form action="" method="post" class="nsm-payment-form">
                    <input type="hidden" name="service_id" value="<?php echo esc_attr($post_id); ?>">
                    <?php wp_nonce_field('nsm_submit_payment', 'nsm_payment_nonce'); ?>
                    <p><input type="text" name="user_name" placeholder="نام و نام خانوادگی" required></p>
                    <p><input type="text" name="user_mobile" placeholder="شماره موبایل" required></p>
                    <p><button type="submit" class="nsm-cta-button">پرداخت و ثبت نام</button></p>
                </form>
                <?php
            }
        } else {
            echo '<h3>ثبت نام رایگان</h3>';
            echo '<a href="#" class="nsm-cta-button">ثبت نام در این رویداد</a>'; // Placeholder for free registration form
        }
    }
    
    private static function render_content_section($title, $content, $in_card = false) {
        if (empty(trim($content))) return;
        $html = '<h2>' . esc_html($title) . '</h2>' . wpautop(wp_kses_post($content));
        if ($in_card) {
            echo '<div class="nsm-info-card">' . $html . '</div>';
        } else {
            echo $html;
        }
    }

    private static function render_meta_pill($label, $value) {
        if (empty(trim($value))) return;
        echo '<div class="nsm-meta-pill"><span class="meta-label">' . esc_html($label) . ':</span><span class="meta-value">' . esc_html($value) . '</span></div>';
    }
}

/**
 * کلاس مدیریت کدهای کوتاه
 */
class NSM_Shortcodes {
    public static function register_shortcodes() {
        add_shortcode('services_grid', [self::class, 'render_services_grid']);
        add_shortcode('services_carousel', [self::class, 'render_services_carousel']);
        add_shortcode('services_filter', [self::class, 'render_services_filter']);
        add_shortcode('single_service', [self::class, 'render_single_service']);
        
        add_action('wp_ajax_nsm_filter_services', [self::class, 'filter_services_ajax_handler']);
        add_action('wp_ajax_nopriv_nsm_filter_services', [self::class, 'filter_services_ajax_handler']);
    }

    private static function get_query_args($atts) {
        $args = [
            'post_type' => 'service',
            'posts_per_page' => isset($atts['count']) ? intval($atts['count']) : 9,
            'orderby' => isset($atts['orderby']) ? sanitize_key($atts['orderby']) : 'date',
            'order' => isset($atts['order']) ? sanitize_key($atts['order']) : 'DESC',
        ];
        $tax_query = [];
        if (!empty($atts['category']) && $atts['category'] !== 'all') {
            $tax_query[] = ['taxonomy' => 'service_category', 'field' => 'slug', 'terms' => sanitize_text_field($atts['category'])];
        }
        if (!empty($atts['keyword'])) {
            $tax_query[] = ['taxonomy' => 'service_keyword', 'field' => 'slug', 'terms' => sanitize_text_field($atts['keyword'])];
        }
        if (!empty($tax_query)) $args['tax_query'] = $tax_query;
        return $args;
    }

    public static function render_service_card($post_id) {
        $price = get_post_meta($post_id, '_nsm_price', true);
        $payment_model = get_post_meta($post_id, '_nsm_payment_model', true);
        $summary = get_post_meta($post_id, '_nsm_service_summary', true);
        ob_start();
        ?>
        <div class="nsm-service-card">
            <div class="nsm-service-card-thumb">
                <a href="<?php echo get_permalink($post_id); ?>">
                    <?php if (has_post_thumbnail($post_id)) : echo get_the_post_thumbnail($post_id, 'medium_large'); else: ?>
                        <img src="https://placehold.co/600x400/eee/ccc?text=Service" alt="<?php echo get_the_title($post_id); ?>">
                    <?php endif; ?>
                </a>
            </div>
            <div class="nsm-service-card-content">
                <h3 class="nsm-service-card-title"><a href="<?php echo get_permalink($post_id); ?>"><?php echo get_the_title($post_id); ?></a></h3>
                <div class="nsm-service-card-meta">
                    <span><?php echo get_the_date('j F Y', $post_id); ?></span>
                    <?php if ($payment_model === 'paid' && $price): ?><span> | قیمت: <?php echo number_format($price); ?> تومان</span>
                    <?php elseif ($payment_model === 'free'): ?><span> | رایگان</span><?php endif; ?>
                </div>
                <div class="nsm-service-card-excerpt"><?php echo esc_html($summary); ?></div>
                <a href="<?php echo get_permalink($post_id); ?>" class="nsm-service-card-button">مشاهده جزئیات</a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public static function render_services_grid($atts) {
        $atts = shortcode_atts(['category' => '', 'keyword' => '', 'count' => 9, 'columns' => 3, 'orderby' => 'date', 'order' => 'DESC'], $atts, 'services_grid');
        $query_args = self::get_query_args($atts);
        $services_query = new WP_Query($query_args);
        if (!$services_query->have_posts()) return '<p>هیچ خدمتی یافت نشد.</p>';
        ob_start();
        echo '<div class="nsm-services-grid" style="grid-template-columns: repeat(' . intval($atts['columns']) . ', 1fr);">';
        while ($services_query->have_posts()) { $services_query->the_post(); echo self::render_service_card(get_the_ID()); }
        echo '</div>';
        wp_reset_postdata();
        return ob_get_clean();
    }

    public static function render_services_carousel($atts) {
        $atts = shortcode_atts(['category' => '', 'keyword' => '', 'count' => 5, 'columns' => 3, 'autoplay' => 'false', 'speed' => 3000], $atts, 'services_carousel');
        $query_args = self::get_query_args($atts);
        $services_query = new WP_Query($query_args);
        if (!$services_query->have_posts()) return '<p>هیچ خدمتی یافت نشد.</p>';
        $settings = json_encode(['autoplay' => ($atts['autoplay'] === 'true'), 'speed' => intval($atts['speed']), 'columns' => intval($atts['columns'])]);
        ob_start();
        ?>
        <div class="nsm-carousel-container">
            <div class="swiper nsm-services-carousel" data-settings='<?php echo esc_attr($settings); ?>'>
                <div class="swiper-wrapper">
                    <?php while ($services_query->have_posts()) { $services_query->the_post(); echo '<div class="swiper-slide">' . self::render_service_card(get_the_ID()) . '</div>'; } ?>
                </div>
                <div class="swiper-button-next"></div>
                <div class="swiper-button-prev"></div>
            </div>
        </div>
        <?php
        wp_reset_postdata();
        return ob_get_clean();
    }
    
    public static function render_services_filter($atts) {
        $atts = shortcode_atts(['count' => 9, 'columns' => 3], $atts, 'services_filter');
        $categories = get_terms(['taxonomy' => 'service_category']);
        ob_start();
        ?>
        <div class="nsm-filter-container">
            <?php if (!is_wp_error($categories) && !empty($categories)): ?>
            <div class="nsm-filter-buttons">
                <button class="nsm-filter-button active" data-term="all">همه</button>
                <?php foreach ($categories as $category): ?>
                    <button class="nsm-filter-button" data-term="<?php echo esc_attr($category->slug); ?>"><?php echo esc_html($category->name); ?></button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="nsm-services-grid" style="grid-template-columns: repeat(<?php echo intval($atts['columns']); ?>, 1fr);" data-settings='<?php echo esc_attr(json_encode($atts)); ?>'>
                <?php
                $initial_query = new WP_Query(self::get_query_args($atts));
                if ($initial_query->have_posts()) {
                    while ($initial_query->have_posts()) { $initial_query->the_post(); echo self::render_service_card(get_the_ID()); }
                } else { echo '<p>هیچ خدمتی یافت نشد.</p>'; }
                wp_reset_postdata();
                ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function filter_services_ajax_handler() {
        $term = sanitize_text_field($_POST['term']);
        $settings = json_decode(stripslashes($_POST['settings']), true);
        $atts = $settings;
        $atts['category'] = $term;
        $query_args = self::get_query_args($atts);
        $services_query = new WP_Query($query_args);
        if ($services_query->have_posts()) {
            while ($services_query->have_posts()) { $services_query->the_post(); echo self::render_service_card(get_the_ID()); }
        } else { echo '<p>هیچ خدمتی در این دسته یافت نشد.</p>'; }
        wp_reset_postdata();
        wp_die();
    }

    public static function render_single_service($atts) {
        $atts = shortcode_atts(['id' => 0], $atts, 'single_service');
        $post_id = intval($atts['id']);
        if (!$post_id || get_post_type($post_id) !== 'service') return '';
        return self::render_service_card($post_id);
    }
}

/**
 * اجرای افزونه
 */
function nsm_run_plugin() {
    return Nilay_Service_Manager::instance();
}
nsm_run_plugin();
