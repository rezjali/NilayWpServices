<?php
/**
 * Plugin Name:       نیلای - افزونه مدیریت خدمات
 * Plugin URI:        https://example.com/
 * Description:       افزونه‌ای جامع برای مدیریت و نمایش انواع خدمات، رویدادها و محصولات با قابلیت اتصال به درگاه پرداخت، پنل پیامک و یکپارچگی کامل با المنتور.
 * Version:           1.6.0
 * Author:            Gemini
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
 * کلاس اصلی افزونه مدیریت خدمات نیلای
 */
final class Nilay_Service_Manager {

    const VERSION = '1.6.0';
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
        
        add_action('wp_ajax_nsm_test_gateway', array('NSM_API_Handler', 'ajax_test_gateway'));
        
        add_filter('the_content', array('NSM_Frontend', 'display_single_service_meta'));
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
            .nsm-paid-options, .nsm-paid-toggle { display: none; }
            .nsm-paid-toggle { display: inline-block; margin-right: 10px; }
        ";
        wp_add_inline_style( 'wp-admin', $css );

        $js = "
            jQuery(document).ready(function($){
                function togglePaymentFields() {
                    if ($('#_nsm_payment_model').val() === 'paid') {
                        $('.nsm-paid-options, .nsm-paid-toggle').show();
                    } else {
                        $('.nsm-paid-options, .nsm-paid-toggle').hide();
                    }
                }
                togglePaymentFields();
                $('#_nsm_payment_model').on('change', togglePaymentFields);

                function toggleServiceTypeFields() {
                    var selectedType = $('#_nsm_service_type').val();
                    $('.nsm-fields-group').removeClass('active').hide();
                    if (selectedType) {
                        $('#nsm-group-' + selectedType).addClass('active').show();
                    }
                }
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
            .nsm-services-grid { display: grid; grid-gap: 20px; }
            .nsm-service-card { border: 1px solid #eee; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); transition: all 0.3s ease; display: flex; flex-direction: column; }
            .nsm-service-card:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
            .nsm-service-card-thumb img { width: 100%; height: 200px; object-fit: cover; }
            .nsm-service-card-content { padding: 15px; flex-grow: 1; display: flex; flex-direction: column; }
            .nsm-service-card-title { font-size: 1.2em; font-weight: bold; margin-top: 0; margin-bottom: 10px; }
            .nsm-service-card-title a { text-decoration: none; color: #333; }
            .nsm-service-card-meta { font-size: 0.9em; color: #777; margin-bottom: 15px; }
            .nsm-service-card-meta span { margin-left: 10px; }
            .nsm-service-card-excerpt { color: #555; flex-grow: 1; }
            .nsm-service-card-button { display: inline-block; background-color: #0073aa; color: #fff; padding: 8px 15px; border-radius: 4px; text-decoration: none; margin-top: 15px; text-align: center; }
            .nsm-carousel-container { position: relative; }
            .swiper-button-next, .swiper-button-prev { color: #0073aa; }
            .nsm-filter-buttons { margin-bottom: 20px; text-align: center; }
            .nsm-filter-buttons button { background: #f0f0f0; border: 1px solid #ddd; padding: 8px 15px; cursor: pointer; border-radius: 4px; margin: 0 5px 5px 5px; }
            .nsm-filter-buttons button.active { background: #0073aa; color: #fff; }
            .nsm-single-service-meta { background: #f9f9f9; border: 1px solid #eee; padding: 20px; margin-top: 30px; border-radius: 8px; }
            .nsm-single-service-meta h3 { margin-top: 0; border-bottom: 2px solid #0073aa; padding-bottom: 10px; margin-bottom: 15px; font-size: 1.4em; }
            .nsm-single-service-meta .meta-item { margin-bottom: 12px; display: flex; align-items: flex-start; }
            .nsm-single-service-meta .meta-item strong { min-width: 140px; display: inline-block; color: #333; }
            .nsm-single-service-meta .meta-item span { color: #555; }
            .nsm-meta-repeater-item { border-bottom: 1px dashed #ddd; padding-bottom: 10px; margin-bottom: 10px; }
            .nsm-meta-repeater-item:last-child { border-bottom: 0; }
            .nsm-meta-sponsors img { max-width: 100px; margin-left: 10px; border: 1px solid #eee; padding: 5px; border-radius: 4px; }
            .nsm-paid-content-block { border: 2px dashed #f0ad4e; padding: 20px; text-align: center; background: #fcf8e3; border-radius: 8px; margin-top: 20px; }
            .nsm-paid-content-block .button { background: #5cb85c; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; }
        ";
        wp_add_inline_style( 'wp-block-library', $css );

        $js = "
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('.nsm-services-carousel').forEach(function(carousel){
                    var settings = JSON.parse(carousel.dataset.settings);
                    new Swiper(carousel, { slidesPerView: 1, spaceBetween: 30, loop: true, autoplay: settings.autoplay ? { delay: settings.speed } : false, navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' }, breakpoints: { 640: { slidesPerView: 1 }, 768: { slidesPerView: 2 }, 1024: { slidesPerView: settings.columns || 3 } } });
                });

                var filterContainer = document.querySelector('.nsm-filter-container');
                if(filterContainer){
                    var filterButtons = filterContainer.querySelectorAll('.nsm-filter-button');
                    var grid = filterContainer.querySelector('.nsm-services-grid');
                    var initialSettings = JSON.parse(grid.dataset.settings);
                    filterButtons.forEach(function(button){
                        button.addEventListener('click', function(){
                            filterButtons.forEach(btn => btn.classList.remove('active'));
                            this.classList.add('active');
                            var term = this.dataset.term;
                            grid.style.opacity = '0.5';
                            var data = new URLSearchParams();
                            data.append('action', 'nsm_filter_services');
                            data.append('term', term);
                            data.append('settings', JSON.stringify(initialSettings));
                            fetch('". admin_url('admin-ajax.php') ."', { method: 'POST', body: data })
                            .then(response => response.text())
                            .then(html => { grid.innerHTML = html; grid.style.opacity = '1'; });
                        });
                    });
                }
            });
        ";
        wp_add_inline_script( 'jquery', $js );
    }

    public function register_elementor_widgets( $widgets_manager ) {
        require_once NSM_PLUGIN_DIR . 'elementor-widgets.php';
        
        $widgets_manager->register( new \Elementor_NSM_Services_Grid_Widget() );
        $widgets_manager->register( new \Elementor_NSM_Services_Carousel_Widget() );
        $widgets_manager->register( new \Elementor_NSM_Services_Filter_Widget() );
        $widgets_manager->register( new \Elementor_NSM_Single_Service_Widget() );
    }

    public function add_elementor_widget_category( $elements_manager ) {
        $elements_manager->add_category(
            'nilay-services',
            [ 'title' => __( 'خدمات نیلای', 'nilay-services' ), 'icon' => 'fa fa-cubes' ]
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
        $labels = [ 'name' => 'خدمات', 'singular_name' => 'خدمت', 'menu_name' => 'خدمات', 'name_admin_bar' => 'خدمت', 'add_new' => 'افزودن خدمت', 'add_new_item' => 'افزودن خدمت جدید', 'new_item' => 'خدمت جدید', 'edit_item' => 'ویرایش خدمت', 'view_item' => 'مشاهده خدمت', 'all_items' => 'همه خدمات', 'search_items' => 'جستجوی خدمات', 'not_found' => 'هیچ خدمتی یافت نشد.', ];
        $args = [ 'labels' => $labels, 'public' => true, 'publicly_queryable' => true, 'show_ui' => true, 'show_in_menu' => true, 'query_var' => true, 'rewrite' => [ 'slug' => 'service' ], 'capability_type' => 'post', 'has_archive' => true, 'hierarchical' => false, 'menu_position' => 20, 'menu_icon' => 'dashicons-clipboard', 'supports' => [ 'title', 'thumbnail', 'excerpt' ], 'show_in_rest' => true, ];
        register_post_type( 'service', $args );
    }

    public static function register_taxonomies() {
        $cat_labels = [ 'name' => 'دسته‌بندی‌های خدمات', 'singular_name' => 'دسته‌بندی', 'menu_name' => 'دسته‌بندی‌ها' ];
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
            <?php $active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'payment'; ?>
            <h2 class="nav-tab-wrapper">
                <a href="?post_type=service&page=nsm-settings&tab=payment" class="nav-tab <?php echo $active_tab == 'payment' ? 'nav-tab-active' : ''; ?>">درگاه‌های پرداخت</a>
                <a href="?post_type=service&page=nsm-settings&tab=sms" class="nav-tab <?php echo $active_tab == 'sms' ? 'nav-tab-active' : ''; ?>">سامانه‌های پیامکی</a>
                <a href="?post_type=service&page=nsm-settings&tab=notifications" class="nav-tab <?php echo $active_tab == 'notifications' ? 'nav-tab-active' : ''; ?>">اطلاع‌رسانی‌ها</a>
            </h2>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'nsm_settings_group' );
                if ( $active_tab == 'payment' ) {
                    do_settings_sections( 'nsm_settings_zarinpal' );
                    echo '<hr>';
                    do_settings_sections( 'nsm_settings_zibal' );
                } elseif ($active_tab == 'sms') {
                    do_settings_sections( 'nsm_settings_kavenegar' );
                    echo '<hr>';
                    do_settings_sections( 'nsm_settings_farazsms' );
                } else {
                    do_settings_sections( 'nsm_settings_notifications' );
                }
                submit_button( 'ذخیره تنظیمات' );
                ?>
            </form>
        </div>
        <?php
    }
}

/**
 * کلاس مدیریت تنظیمات
 */
class NSM_Settings {
    public static function register_settings() {
        register_setting( 'nsm_settings_group', 'nsm_options', ['sanitize_callback' => 'nsm_sanitize_options'] );
        
        add_settings_section( 'nsm_zarinpal_section', 'تنظیمات درگاه پرداخت زرین‌پال', '__return_false', 'nsm_settings_zarinpal' );
        add_settings_field( 'zarinpal_merchant_id', 'کد مرچنت', [self::class, 'render_text_field'], 'nsm_settings_zarinpal', 'nsm_zarinpal_section', ['id' => 'zarinpal_merchant_id'] );
        add_settings_field( 'zarinpal_test', 'تست اتصال', [self::class, 'render_test_button'], 'nsm_settings_zarinpal', 'nsm_zarinpal_section', ['gateway' => 'zarinpal'] );

        add_settings_section( 'nsm_zibal_section', 'تنظیمات درگاه پرداخت زیبال', '__return_false', 'nsm_settings_zibal' );
        add_settings_field( 'zibal_merchant_id', 'کد مرچنت', [self::class, 'render_text_field'], 'nsm_settings_zibal', 'nsm_zibal_section', ['id' => 'zibal_merchant_id'] );
        add_settings_field( 'zibal_test', 'تست اتصال', [self::class, 'render_test_button'], 'nsm_settings_zibal', 'nsm_zibal_section', ['gateway' => 'zibal'] );

        add_settings_section( 'nsm_kavenegar_section', 'تنظیمات سامانه پیامکی کاوه‌نگار', '__return_false', 'nsm_settings_kavenegar' );
        add_settings_field( 'kavenegar_api_key', 'کلید API', [self::class, 'render_text_field'], 'nsm_settings_kavenegar', 'nsm_kavenegar_section', ['id' => 'kavenegar_api_key'] );
        add_settings_field( 'kavenegar_test_mobile', 'شماره موبایل تست', [self::class, 'render_text_field'], 'nsm_settings_kavenegar', 'nsm_kavenegar_section', ['id' => 'kavenegar_test_mobile'] );
        add_settings_field( 'kavenegar_test', 'تست ارسال', [self::class, 'render_test_button'], 'nsm_settings_kavenegar', 'nsm_kavenegar_section', ['gateway' => 'kavenegar'] );

        add_settings_section( 'nsm_farazsms_section', 'تنظیمات سامانه پیامکی فراز اس‌ام‌اس', '__return_false', 'nsm_settings_farazsms' );
        add_settings_field( 'farazsms_api_key', 'کلید API', [self::class, 'render_text_field'], 'nsm_settings_farazsms', 'nsm_farazsms_section', ['id' => 'farazsms_api_key'] );
        add_settings_field( 'farazsms_test_mobile', 'شماره موبایل تست', [self::class, 'render_text_field'], 'nsm_settings_farazsms', 'nsm_farazsms_section', ['id' => 'farazsms_test_mobile'] );
        add_settings_field( 'farazsms_test', 'تست ارسال', [self::class, 'render_test_button'], 'nsm_settings_farazsms', 'nsm_farazsms_section', ['gateway' => 'farazsms'] );
        
        add_settings_section( 'nsm_notifications_section', 'الگوهای اطلاع‌رسانی پیامکی', '__return_false', 'nsm_settings_notifications' );
        add_settings_field( 'admin_free_reg', 'ثبت‌نام رایگان (برای مدیر)', [self::class, 'render_textarea_field'], 'nsm_settings_notifications', 'nsm_notifications_section', ['id' => 'admin_free_reg'] );
        add_settings_field( 'user_free_reg', 'ثبت‌نام رایگان (برای کاربر)', [self::class, 'render_textarea_field'], 'nsm_settings_notifications', 'nsm_notifications_section', ['id' => 'user_free_reg'] );
        add_settings_field( 'admin_paid_reg', 'خرید موفق (برای مدیر)', [self::class, 'render_textarea_field'], 'nsm_settings_notifications', 'nsm_notifications_section', ['id' => 'admin_paid_reg'] );
        add_settings_field( 'user_paid_reg', 'خرید موفق (برای کاربر)', [self::class, 'render_textarea_field'], 'nsm_settings_notifications', 'nsm_notifications_section', ['id' => 'user_paid_reg'] );
        add_settings_field( 'patterns_help', 'راهنمای الگوها', [self::class, 'render_patterns_help'], 'nsm_settings_notifications', 'nsm_notifications_section' );
    }

    public static function render_text_field($args) {
        $options = get_option('nsm_options');
        $value = isset($options[$args['id']]) ? $options[$args['id']] : '';
        echo '<input type="text" name="nsm_options[' . esc_attr($args['id']) . ']" value="' . esc_attr($value) . '" class="regular-text">';
    }
    public static function render_textarea_field($args) {
        $options = get_option('nsm_options');
        $value = isset($options[$args['id']]) ? $options[$args['id']] : '';
        echo '<textarea name="nsm_options[' . esc_attr($args['id']) . ']" rows="5" class="large-text">' . esc_textarea($value) . '</textarea>';
    }
    public static function render_test_button($args) {
        $gateway = $args['gateway'];
        echo '<button type="button" class="button nsm-test-button" data-gateway="'.esc_attr($gateway).'">ارسال درخواست تست</button>';
        echo '<p class="description">برای تست، ابتدا تنظیمات را ذخیره کنید.</p>';
        echo '<div id="nsm-test-result-' . esc_attr($gateway) . '" class="nsm-test-result"></div>';
    }
    public static function render_patterns_help() {
        echo '<p class="description">می‌توانید از الگوهای زیر در متن پیامک‌ها استفاده کنید:</p>';
        echo '<code>[service_name]</code>, <code>[user_name]</code>, <code>[price]</code>, <code>[date]</code>';
    }
}
function nsm_sanitize_options($input) {
    $sanitized_input = [];
    if (empty($input) || !is_array($input)) return $sanitized_input;
    foreach ($input as $key => $value) {
        if (strpos($key, '_reg') !== false) {
            $sanitized_input[$key] = sanitize_textarea_field($value);
        } else {
            $sanitized_input[$key] = sanitize_text_field($value);
        }
    }
    return $sanitized_input;
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

            <h2>کدهای کوتاه (Shortcodes)</h2>
            <p>برای نمایش خدمات در هر بخش از سایت خود می‌توانید از کدهای کوتاه زیر استفاده کنید.</p>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 20%;">کد کوتاه</th>
                        <th>توضیحات</th>
                        <th>پارامترها</th>
                        <th>مثال</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>[services_grid]</code></td>
                        <td>نمایش خدمات به صورت شبکه‌ای (گرید).</td>
                        <td><code>category</code>, <code>keyword</code>, <code>count</code>, <code>columns</code>, <code>orderby</code>, <code>order</code></td>
                        <td><code>[services_grid category="amoozeshi" count="6" columns="3"]</code></td>
                    </tr>
                    <tr>
                        <td><code>[services_carousel]</code></td>
                        <td>نمایش خدمات به صورت اسلایدر (کاروسل).</td>
                        <td><code>category</code>, <code>keyword</code>, <code>count</code>, <code>columns</code>, <code>autoplay</code>, <code>speed</code></td>
                        <td><code>[services_carousel count="5" columns="3" autoplay="true"]</code></td>
                    </tr>
                    <tr>
                        <td><code>[services_filter]</code></td>
                        <td>ایجاد یک گالری خدمات با قابلیت فیلتر ایجکس.</td>
                        <td><code>count</code>, <code>columns</code></td>
                        <td><code>[services_filter count="9" columns="3"]</code></td>
                    </tr>
                     <tr>
                        <td><code>[single_service]</code></td>
                        <td>نمایش اطلاعات یک خدمت خاص.</td>
                        <td><code>id</code></td>
                        <td><code>[single_service id="123"]</code></td>
                    </tr>
                </tbody>
            </table>

            <h2>یکپارچگی با المنتور</h2>
            <p>این افزونه سه ویجت اختصاصی به صفحه‌ساز المنتور اضافه می‌کند:</p>
            <ul>
                <li><strong>گرید خدمات:</strong> برای نمایش خدمات به صورت شبکه‌ای با تنظیمات کامل ظاهری و محتوایی.</li>
                <li><strong>کاروسل خدمات:</strong> برای نمایش خدمات به صورت اسلایدر.</li>
                <li><strong>لیست فیلتر خدمات:</strong> برای ایجاد گالری خدمات با قابلیت فیلتر زنده.</li>
            </ul>
            <p>این ویجت‌ها را می‌توانید در دسته‌بندی «خدمات نیلای» در پنل ویجت‌های المنتور پیدا کنید.</p>
        </div>
        <?php
    }
}

/**
 * کلاس مدیریت متاباکس‌ها و فیلدهای سفارشی
 */
class NSM_Meta_Boxes {
    private static $meta_fields = [];
    public static function add_meta_boxes() { add_meta_box('nsm_service_details', 'جزئیات خدمت', [self::class, 'render_meta_box'], 'service', 'normal', 'high'); }
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
        echo '<div id="nsm-group-general" class="nsm-fields-group active">';
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
        
        $is_paid_value = get_post_meta($post_id, $field['id'] . '_is_paid', true);
        $paid_toggle_html = '<label class="nsm-paid-toggle"><input type="checkbox" name="' . esc_attr($field['id']) . '_is_paid" value="1" ' . checked($is_paid_value, '1', false) . '> نیاز به پرداخت دارد؟</label>';

        echo '<label for="' . esc_attr($field['id']) . '">' . esc_html($field['label']) . '</label>';
        if (isset($field['is_premium']) && $field['is_premium']) {
            echo $paid_toggle_html;
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
                                    $sanitized_item[$key] = sanitize_text_field($sub_value);
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
                ['id' => '_nsm_payment_model', 'label' => 'مدل فروش', 'type' => 'select', 'options' => ['free' => 'رایگان', 'paid' => 'پولی']],
                ['id' => '_nsm_price', 'label' => 'هزینه/مبلغ', 'type' => 'number', 'desc' => 'مبلغ را به تومان وارد کنید.', 'extra_class' => 'nsm-paid-options'],
                ['id' => '_nsm_reg_link', 'label' => 'لینک ثبت‌نام/خرید', 'type' => 'url', 'desc' => 'اگر خالی باشد، از درگاه پرداخت استفاده می‌شود.', 'extra_class' => 'nsm-paid-options'],
                ['id' => '_nsm_status', 'label' => 'وضعیت خدمت', 'type' => 'select', 'options' => ['available' => 'در دسترس', 'full' => 'تکمیل ظرفیت', 'ended' => 'پایان یافته']],
                ['id' => '_nsm_date', 'label' => 'تاریخ برگزاری', 'type' => 'date', 'is_premium' => true],
                ['id' => '_nsm_time', 'label' => 'ساعت برگزاری', 'type' => 'time', 'is_premium' => true],
                ['id' => '_nsm_main_description', 'label' => 'توضیحات عمومی (برای همه قابل مشاهده)', 'type' => 'wp_editor'],
                ['id' => '_nsm_paid_content', 'label' => 'محتوای ویژه کاربران خریدار', 'type' => 'wp_editor', 'extra_class' => 'nsm-paid-options'],
            ],
            'educational' => [
                ['id' => '_nsm_edu_instructor', 'label' => 'نام مدرس', 'type' => 'text'],
                ['id' => '_nsm_edu_instructor_bio', 'label' => 'بیوگرافی کوتاه مدرس', 'type' => 'textarea', 'is_premium' => true],
                ['id' => '_nsm_edu_level', 'label' => 'سطح دوره', 'type' => 'select', 'options' => ['beginner' => 'مقدماتی', 'intermediate' => 'متوسط', 'advanced' => 'پیشرفته']],
                ['id' => '_nsm_edu_sessions', 'label' => 'تعداد جلسات', 'type' => 'number'],
                ['id' => '_nsm_edu_duration', 'label' => 'مدت زمان کل دوره (ساعت)', 'type' => 'number'],
                ['id' => '_nsm_edu_prerequisites', 'label' => 'پیش‌نیازها', 'type' => 'textarea'],
                ['id' => '_nsm_edu_audience', 'label' => 'مخاطبین دوره', 'type' => 'textarea'],
                ['id' => '_nsm_edu_syllabus', 'label' => 'سرفصل‌های دوره', 'type' => 'wp_editor', 'is_premium' => true],
                ['id' => '_nsm_edu_downloads', 'label' => 'فایل‌های ضمیمه', 'type' => 'repeater', 'fields' => [['id' => 'title', 'label' => 'عنوان فایل'], ['id' => 'url', 'label' => 'لینک دانلود']], 'is_premium' => true],
                ['id' => '_nsm_edu_certificate', 'label' => 'ارائه گواهینامه', 'type' => 'select', 'options' => ['no' => 'خیر', 'yes' => 'بله']],
            ],
            'events' => [
                ['id' => '_nsm_evt_organizer', 'label' => 'نام برگزارکننده', 'type' => 'text'],
                ['id' => '_nsm_evt_location', 'label' => 'محل برگزاری', 'type' => 'text', 'desc' => 'آدرس دقیق را وارد کنید.', 'is_premium' => true],
                ['id' => '_nsm_evt_map', 'label' => 'کد Embed نقشه گوگل', 'type' => 'textarea', 'is_premium' => true],
                ['id' => '_nsm_evt_speakers', 'label' => 'سخنرانان / اجرا کنندگان', 'type' => 'repeater', 'fields' => [['id' => 'name', 'label' => 'نام'], ['id' => 'title', 'label' => 'عنوان/سمت'], ['id' => 'photo', 'label' => 'عکس', 'type' => 'media']]],
                ['id' => '_nsm_evt_agenda', 'label' => 'برنامه زمان‌بندی رویداد', 'type' => 'repeater', 'fields' => [['id' => 'time', 'label' => 'زمان (مثلا 09:00)'], ['id' => 'title', 'label' => 'عنوان برنامه'], ['id' => 'description', 'label' => 'توضیحات']], 'is_premium' => true],
                ['id' => '_nsm_evt_tickets', 'label' => 'انواع بلیت', 'type' => 'repeater', 'fields' => [['id' => 'type', 'label' => 'نوع بلیت (VIP)'], ['id' => 'price', 'label' => 'قیمت'], ['id' => 'features', 'label' => 'ویژگی‌ها']]],
                ['id' => '_nsm_evt_sponsors', 'label' => 'اسپانسرها', 'type' => 'repeater', 'fields' => [['id' => 'name', 'label' => 'نام اسپانسر'], ['id' => 'logo', 'label' => 'لوگو', 'type' => 'media']]],
            ],
            'art' => [
                ['id' => '_nsm_art_artist', 'label' => 'نام هنرمند/گروه', 'type' => 'text'],
                ['id' => '_nsm_art_type', 'label' => 'نوع رویداد هنری', 'type' => 'text', 'desc' => 'مثال: کنسرت پاپ، نمایشگاه نقاشی'],
                ['id' => '_nsm_art_venue', 'label' => 'نام سالن / گالری', 'type' => 'text'],
                ['id' => '_nsm_art_age_limit', 'label' => 'محدودیت سنی', 'type' => 'text'],
                ['id' => '_nsm_art_gallery', 'label' => 'گالری تصاویر', 'type' => 'repeater', 'fields' => [['id' => 'image', 'label' => 'تصویر', 'type' => 'media']], 'is_premium' => true],
            ],
            'consulting' => [
                ['id' => '_nsm_con_consultant', 'label' => 'نام مشاور/کوچ', 'type' => 'text'],
                ['id' => '_nsm_con_photo', 'label' => 'عکس مشاور', 'type' => 'media'],
                ['id' => '_nsm_con_credentials', 'label' => 'تحصیلات و گواهینامه‌ها', 'type' => 'textarea'],
                ['id' => '_nsm_con_duration', 'label' => 'مدت زمان هر جلسه (دقیقه)', 'type' => 'number'],
                ['id' => '_nsm_con_type', 'label' => 'نوع جلسه', 'type' => 'select', 'options' => ['phone' => 'تلفنی', 'online' => 'آنلاین تصویری', 'in_person' => 'حضوری']],
                ['id' => '_nsm_con_packages', 'label' => 'پکیج‌های مشاوره', 'type' => 'repeater', 'fields' => [['id' => 'title', 'label' => 'عنوان پکیج'], ['id' => 'sessions', 'label' => 'تعداد جلسات'], ['id' => 'price', 'label' => 'قیمت']]],
                ['id' => '_nsm_con_booking_shortcode', 'label' => 'شورت‌کد سیستم رزرو', 'type' => 'text', 'is_premium' => true],
            ],
            'digital' => [
                 ['id' => '_nsm_dig_delivery_time', 'label' => 'زمان تحویل', 'type' => 'text', 'desc' => 'مثال: 5 روز کاری'],
                 ['id' => '_nsm_dig_revisions', 'label' => 'تعداد بازبینی', 'type' => 'number'],
                 ['id' => '_nsm_dig_portfolio', 'label' => 'لینک نمونه کار', 'type' => 'url'],
                 ['id' => '_nsm_dig_tech_stack', 'label' => 'تکنولوژی‌های مورد استفاده', 'type' => 'textarea'],
                 ['id' => '_nsm_dig_process', 'label' => 'فرآیند انجام کار', 'type' => 'wp_editor', 'is_premium' => true],
            ],
            'technical' => [
                ['id' => '_nsm_tech_area', 'label' => 'محدوده خدمات‌دهی', 'type' => 'text', 'desc' => 'مثال: شهر تهران'],
                ['id' => '_nsm_tech_warranty', 'label' => 'گارانتی خدمات', 'type' => 'text', 'desc' => 'مثال: 6 ماه ضمانت'],
                ['id' => '_nsm_tech_sla', 'label' => 'تعهدنامه سطح خدمات (SLA)', 'type' => 'textarea', 'is_premium' => true],
            ],
            'health' => [
                ['id' => '_nsm_hlt_specialist', 'label' => 'نام متخصص/مربی', 'type' => 'text'],
                ['id' => '_nsm_hlt_center', 'label' => 'نام مرکز/باشگاه', 'type' => 'text'],
                ['id' => '_nsm_hlt_schedule', 'label' => 'برنامه هفتگی کلاس‌ها/جلسات', 'type' => 'wp_editor', 'is_premium' => true],
                ['id' => '_nsm_hlt_insurance', 'label' => 'بیمه‌های طرف قرارداد', 'type' => 'textarea'],
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
            'ceremonial' => [
                ['id' => '_nsm_cer_capacity', 'label' => 'ظرفیت مهمانان', 'type' => 'number'],
                ['id' => '_nsm_cer_venue_type', 'label' => 'نوع محل برگزاری', 'type' => 'text', 'desc' => 'مثال: باغ، تالار، سالن همایش'],
                ['id' => '_nsm_cer_packages', 'label' => 'پکیج‌های پذیرایی', 'type' => 'repeater', 'fields' => [['id' => 'title', 'label' => 'عنوان پکیج'], ['id' => 'price_per_person', 'label' => 'قیمت هر نفر'], ['id' => 'menu', 'label' => 'منو']], 'is_premium' => true],
                ['id' => '_nsm_cer_equipment', 'label' => 'تجهیزات قابل ارائه', 'type' => 'textarea'],
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
        $options = get_option('nsm_options');

        switch ($gateway) {
            case 'zarinpal':
                $merchant_id = $options['zarinpal_merchant_id'] ?? '';
                if (empty($merchant_id)) wp_send_json_error('کد مرچنت زرین پال وارد نشده است.');
                if (strlen($merchant_id) === 36) wp_send_json_success('کد مرچنت معتبر به نظر می‌رسد. (تست شبیه‌سازی شده)');
                else wp_send_json_error('کد مرچنت زرین پال باید 36 کاراکتر باشد.');
                break;
            case 'zibal':
                $merchant_id = $options['zibal_merchant_id'] ?? '';
                if (empty($merchant_id)) wp_send_json_error('کد مرچنت زیبال وارد نشده است.');
                wp_send_json_success('اتصال با زیبال موفقیت آمیز بود (تست شبیه‌سازی شده).');
                break;
            case 'kavenegar':
                $api_key = $options['kavenegar_api_key'] ?? '';
                $mobile = $options['kavenegar_test_mobile'] ?? '';
                if (empty($api_key)) wp_send_json_error('کلید API کاوه نگار وارد نشده است.');
                if (empty($mobile) || !preg_match('/^09[0-9]{9}$/', $mobile)) wp_send_json_error('شماره موبایل تست، معتبر نیست.');
                wp_send_json_success("پیامک تست به شماره {$mobile} ارسال شد (تست شبیه‌سازی شده).");
                break;
            case 'farazsms':
                $api_key = $options['farazsms_api_key'] ?? '';
                $mobile = $options['farazsms_test_mobile'] ?? '';
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
 * کلاس مدیریت نمایش در فرانت‌اند
 */
class NSM_Frontend {
    public static function display_single_service_meta($content) {
        if (is_singular('service') && in_the_loop() && is_main_query()) {
            $post_id = get_the_ID();
            $meta_html = self::get_service_meta_html($post_id);
            return $content . $meta_html;
        }
        return $content;
    }
    private static function get_service_meta_html($post_id) {
        NSM_Meta_Boxes::define_fields();
        $service_type = get_post_meta($post_id, '_nsm_service_type', true);
        if (!$service_type) return '';

        $all_fields = NSM_Meta_Boxes::$meta_fields;
        $fields_to_display = array_merge($all_fields['general'], $all_fields[$service_type] ?? []);
        
        $payment_model = get_post_meta($post_id, '_nsm_payment_model', true);
        $user_has_purchased = false; // This should be determined dynamically
        $premium_content_exists = false;

        ob_start();
        ?>
        <div class="nsm-single-service-meta">
            <?php
            foreach ($fields_to_display as $field) {
                $is_paid_field = get_post_meta($post_id, $field['id'] . '_is_paid', true);

                if ($payment_model === 'paid' && $is_paid_field && !$user_has_purchased) {
                    $premium_content_exists = true;
                    continue; // Skip paid fields for non-purchased users
                }
                
                $value = get_post_meta($post_id, $field['id'], true);
                if (empty($value)) continue;
                ?>
                <div class="meta-item">
                    <strong><?php echo esc_html($field['label']); ?>:</strong>
                    <span>
                        <?php
                        switch ($field['type']) {
                            case 'select': echo esc_html($field['options'][$value] ?? $value); break;
                            case 'wp_editor': case 'textarea': echo wpautop(wp_kses_post($value)); break;
                            case 'repeater':
                                if (is_array($value)) {
                                    echo '<div class="nsm-meta-repeater-container">';
                                    foreach ($value as $item) {
                                        echo '<div class="nsm-meta-repeater-item">';
                                        foreach ($field['fields'] as $sub_field) {
                                            if (!empty($item[$sub_field['id']])) {
                                                echo '<strong>' . esc_html($sub_field['label']) . ':</strong> ';
                                                if ($sub_field['type'] === 'media') {
                                                    echo '<img src="'.esc_url($item[$sub_field['id']]).'" style="max-width: 80px; vertical-align: middle;"/>';
                                                } else {
                                                    echo esc_html($item[$sub_field['id']]);
                                                }
                                                echo '<br>';
                                            }
                                        }
                                        echo '</div>';
                                    }
                                    echo '</div>';
                                }
                                break;
                            default: echo esc_html($value); break;
                        }
                        ?>
                    </span>
                </div>
                <?php
            }

            if ($premium_content_exists) {
                $reg_link = get_post_meta($post_id, '_nsm_reg_link', true);
                 if (!empty($reg_link)) {
                    echo '<div class="nsm-paid-content-block">';
                    echo '<h4>بخشی از اطلاعات این خدمت ویژه اعضای خریدار است</h4>';
                    echo '<p>برای دسترسی به محتوای کامل، لطفا این خدمت را خریداری نمایید.</p>';
                    echo '<a href="'.esc_url($reg_link).'" class="button">خرید و مشاهده</a>';
                    echo '</div>';
                }
            }
            ?>
        </div>
        <?php
        return ob_get_clean();
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

    private static function render_service_card($post_id) {
        $price = get_post_meta($post_id, '_nsm_price', true);
        $payment_model = get_post_meta($post_id, '_nsm_payment_model', true);
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
                    <?php if ($payment_model === 'paid' && $price): ?><span> | قیمت: <?php echo esc_html($price); ?></span>
                    <?php elseif ($payment_model === 'free'): ?><span> | رایگان</span><?php endif; ?>
                </div>
                <div class="nsm-service-card-excerpt"><?php echo get_the_excerpt($post_id); ?></div>
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
