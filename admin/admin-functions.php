<?php
/**
 * فایل توابع و کلاس‌های مربوط به بخش مدیریت (Admin)
 *
 * @package Nilay_Service_Manager/Admin
 * @version 4.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * کلاس مدیریت درخواست‌های API (ایجکس) و فرم‌ها
 */
class NSM_Admin_Handler {
    public static function ajax_test_gateway() {
        check_ajax_referer('nsm_test_nonce', '_ajax_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => nsm_get_string('error_permission_denied', 'دسترسی غیرمجاز.')]);
        }

        $gateway = isset($_POST['gateway']) ? sanitize_key($_POST['gateway']) : '';
        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
        
        if (empty($gateway) || empty($api_key)) {
            wp_send_json_error(['message' => 'اطلاعات ضروری (نوع درگاه یا کلید API) ارسال نشده است.']);
        }

        $result = ['success' => false, 'message' => 'درگاه یا سرویس‌دهنده انتخاب شده معتبر نیست.'];

        switch ($gateway) {
            case 'zarinpal':
                $result = NSM_Gateways::verify_zarinpal_credentials($api_key);
                break;
            case 'zibal':
                $result = NSM_Gateways::verify_zibal_credentials($api_key);
                break;
            case 'kavenegar':
                $result = NSM_Gateways::verify_kavenegar_credentials($api_key);
                break;
            case 'farazsms':
                $result = NSM_Gateways::verify_farazsms_credentials($api_key);
                break;
        }

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    public static function handle_export_single_service() {
        if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'nsm_export_single_nonce')) {
            wp_die(nsm_get_string('error_permission_denied', 'دسترسی غیرمجاز.'));
        }
        if (!current_user_can('edit_posts')) {
            wp_die(nsm_get_string('error_permission_denied', 'دسترسی غیرمجاز.'));
        }
        $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
        if (!$post_id || get_post_type($post_id) !== 'service') {
            wp_die('Post ID is invalid.');
        }

        $exporter = new NSM_Import_Export_Handler();
        $exporter->export_single($post_id);
    }

    public static function handle_import_single_service() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nsm_import_single_nonce')) {
            wp_die(nsm_get_string('error_permission_denied', 'دسترسی غیرمجاز.'));
        }
        if (!current_user_can('edit_posts')) {
            wp_die(nsm_get_string('error_permission_denied', 'دسترسی غیرمجاز.'));
        }
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!$post_id || get_post_type($post_id) !== 'service') {
            wp_die('Post ID is invalid.');
        }
        if (empty($_FILES['import_file']['tmp_name'])) {
            wp_redirect(add_query_arg('nsm_import_status', 'no_file', get_edit_post_link($post_id, 'raw')));
            exit;
        }

        $importer = new NSM_Import_Export_Handler();
        $result = $importer->import_single($post_id, $_FILES['import_file']);

        wp_redirect(add_query_arg('nsm_import_status', $result ? 'success' : 'error', get_edit_post_link($post_id, 'raw')));
        exit;
    }
    
    public static function handle_global_export() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nsm_global_export_nonce')) {
            wp_die(nsm_get_string('error_permission_denied', 'دسترسی غیرمجاز.'));
        }
        if (!current_user_can('manage_options')) {
            wp_die(nsm_get_string('error_permission_denied', 'دسترسی غیرمجاز.'));
        }
        $exporter = new NSM_Import_Export_Handler();
        $exporter->export_all();
    }

    public static function handle_global_import() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nsm_global_import_nonce')) {
            wp_die(nsm_get_string('error_permission_denied', 'دسترسی غیرمجاز.'));
        }
        if (!current_user_can('manage_options')) {
            wp_die(nsm_get_string('error_permission_denied', 'دسترسی غیرمجاز.'));
        }
        if (empty($_FILES['import_file']['tmp_name'])) {
            wp_redirect(add_query_arg(['page' => 'nsm-settings', 'tab' => 'tools', 'nsm_import_status' => 'no_file'], admin_url('edit.php?post_type=service')));
            exit;
        }

        $importer = new NSM_Import_Export_Handler();
        $result = $importer->import_all($_FILES['import_file']);
        
        $status = 'error';
        if (is_array($result)) {
            $status = 'success&created=' . $result['created'] . '&updated=' . $result['updated'];
        }

        wp_redirect(add_query_arg(['page' => 'nsm-settings', 'tab' => 'tools', 'nsm_import_status' => $status], admin_url('edit.php?post_type=service')));
        exit;
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
        
        if ( ! get_option('nsm_terminology_options') ) {
            add_option('nsm_terminology_options', NSM_Settings::get_default_terminology());
        }
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
        $labels = [ 
            'name' => nsm_get_string('services', 'خدمات'), 
            'singular_name' => nsm_get_string('service', 'خدمت'), 
            'menu_name' => nsm_get_string('services', 'خدمات'), 
            'name_admin_bar' => nsm_get_string('service', 'خدمت'), 
            'add_new' => nsm_get_string('add_new_service', 'افزودن خدمت'), 
            'add_new_item' => nsm_get_string('add_new_service_item', 'افزودن خدمت جدید'), 
            'new_item' => nsm_get_string('new_service', 'خدمت جدید'), 
            'edit_item' => nsm_get_string('edit_service', 'ویرایش خدمت'), 
            'view_item' => nsm_get_string('view_service', 'مشاهده خدمت'), 
            'all_items' => nsm_get_string('all_services', 'همه خدمات'), 
            'search_items' => nsm_get_string('search_services', 'جستجوی خدمات'), 
            'not_found' => nsm_get_string('no_service_found', 'هیچ خدمتی یافت نشد.'), 
        ];
        $args = [ 'labels' => $labels, 'public' => true, 'publicly_queryable' => true, 'show_ui' => true, 'show_in_menu' => true, 'query_var' => true, 'rewrite' => [ 'slug' => 'service' ], 'capability_type' => 'post', 'has_archive' => true, 'hierarchical' => false, 'menu_position' => 20, 'menu_icon' => 'dashicons-clipboard', 'supports' => [ 'title', 'thumbnail' ], 'show_in_rest' => true, ];
        register_post_type( 'service', $args );
    }

    public static function register_taxonomies() {
        $cat_labels = [ 
            'name' => nsm_get_string('service_category', 'دسته‌بندی‌های خدمات'), 
            'singular_name' => nsm_get_string('category', 'دسته‌بندی'), 
            'menu_name' => nsm_get_string('categories', 'دسته‌بندی‌ها') 
        ];
        $cat_args = [ 'hierarchical' => true, 'labels' => $cat_labels, 'show_ui' => true, 'show_admin_column' => true, 'rewrite' => [ 'slug' => 'service-category' ], 'show_in_rest' => true, ];
        register_taxonomy( 'service_category', [ 'service' ], $cat_args );

        $key_labels = [ 
            'name' => nsm_get_string('service_keywords', 'کلیدواژه‌های خدمات'), 
            'singular_name' => nsm_get_string('keyword', 'کلیدواژه'), 
            'menu_name' => nsm_get_string('keywords', 'کلیدواژه‌ها') 
        ];
        $key_args = [ 'hierarchical' => false, 'labels' => $key_labels, 'show_ui' => true, 'show_admin_column' => true, 'rewrite' => [ 'slug' => 'service-keyword' ], 'show_in_rest' => true, ];
        register_taxonomy( 'service_keyword', [ 'service' ], $key_args );
    }
}


/**
 * کلاس مدیریت منوهای مدیریت
 */
class NSM_Admin_Menu {
    public static function register_menus() {
        add_submenu_page('edit.php?post_type=service', nsm_get_string('settings_page_title', 'تنظیمات خدمات نیلای'), nsm_get_string('settings_menu_title', 'تنظیمات'), 'manage_options', 'nsm-settings', ['NSM_Settings_Page', 'render_page']);
        add_submenu_page('edit.php?post_type=service', nsm_get_string('help_page_title', 'راهنمای افزونه'), nsm_get_string('help_menu_title', 'راهنما'), 'manage_options', 'nsm-help', ['NSM_Help_Page', 'render_page']);
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
                <a href="?post_type=service&page=nsm-settings&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>"><?php echo nsm_get_string('tab_general', 'عمومی'); ?></a>
                <a href="?post_type=service&page=nsm-settings&tab=payment" class="nav-tab <?php echo $active_tab == 'payment' ? 'nav-tab-active' : ''; ?>"><?php echo nsm_get_string('tab_payment', 'درگاه‌های پرداخت'); ?></a>
                <a href="?post_type=service&page=nsm-settings&tab=sms" class="nav-tab <?php echo $active_tab == 'sms' ? 'nav-tab-active' : ''; ?>"><?php echo nsm_get_string('tab_sms', 'سامانه‌های پیامکی'); ?></a>
                <a href="?post_type=service&page=nsm-settings&tab=notifications" class="nav-tab <?php echo $active_tab == 'notifications' ? 'nav-tab-active' : ''; ?>"><?php echo nsm_get_string('tab_notifications', 'اطلاع‌رسانی‌ها'); ?></a>
                <a href="?post_type=service&page=nsm-settings&tab=terminology" class="nav-tab <?php echo $active_tab == 'terminology' ? 'nav-tab-active' : ''; ?>"><?php echo nsm_get_string('tab_terminology', 'مدیریت اصطلاحات'); ?></a>
                <a href="?post_type=service&page=nsm-settings&tab=tools" class="nav-tab <?php echo $active_tab == 'tools' ? 'nav-tab-active' : ''; ?>"><?php echo nsm_get_string('tab_tools', 'ابزارها'); ?></a>
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
                } 
                
                if ($active_tab !== 'tools') {
                    submit_button( nsm_get_string('save_settings', 'ذخیره تنظیمات') );
                }
                ?>
            </form>
            <?php 
            if ($active_tab == 'tools') { 
                do_settings_sections( 'nsm_settings_tools' ); 
            } 
            // Display import/export notices
            if (isset($_GET['nsm_import_status'])) {
                $status = $_GET['nsm_import_status'];
                if ($status === 'success') {
                    $created = isset($_GET['created']) ? intval($_GET['created']) : 0;
                    $updated = isset($_GET['updated']) ? intval($_GET['updated']) : 0;
                    $message = sprintf(nsm_get_string('import_success_message', 'درون‌ریزی با موفقیت انجام شد. %d خدمت جدید ایجاد و %d خدمت به‌روزرسانی شد.'), $created, $updated);
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
                } elseif ($status === 'error') {
                    echo '<div class="notice notice-error is-dismissible"><p>' . nsm_get_string('import_error_message', 'خطا در پردازش فایل. لطفاً از یک فایل برون‌بری معتبر استفاده کنید.') . '</p></div>';
                } elseif ($status === 'no_file') {
                     echo '<div class="notice notice-warning is-dismissible"><p>' . nsm_get_string('import_no_file_message', 'هیچ فایلی برای درون‌ریزی انتخاب نشده است.') . '</p></div>';
                }
            }
            if (isset($_GET['cache-cleared']) && $_GET['cache-cleared'] == 'true') : ?>
                <div id="message" class="updated notice is-dismissible">
                    <p><?php echo nsm_get_string('cache_cleared_success', 'کش افزونه و وردپرس با موفقیت پاک شد.'); ?></p>
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

    /**
     * آرایه کامل اصطلاحات قابل ترجمه
     */
    public static function get_default_terminology() {
        return [
            // CPT & Taxonomy
            'service' => 'خدمت', 'services' => 'خدمات', 'service_category' => 'دسته‌بندی خدمات', 'add_new_service' => 'افزودن خدمت',
            'add_new_service_item' => 'افزودن خدمت جدید', 'new_service' => 'خدمت جدید', 'edit_service' => 'ویرایش خدمت',
            'view_service' => 'مشاهده خدمت', 'all_services' => 'همه خدمات', 'search_services' => 'جستجوی خدمات',
            'no_service_found' => 'هیچ خدمتی یافت نشد.', 'category' => 'دسته‌بندی', 'categories' => 'دسته‌بندی‌ها',
            'service_keywords' => 'کلیدواژه‌های خدمات', 'keyword' => 'کلیدواژه', 'keywords' => 'کلیدواژه‌ها',
            // Admin Menu
            'settings_page_title' => 'تنظیمات خدمات نیلای', 'settings_menu_title' => 'تنظیمات', 'help_page_title' => 'راهنمای افزونه', 'help_menu_title' => 'راهنما',
            // Settings Tabs
            'tab_general' => 'عمومی', 'tab_payment' => 'درگاه‌های پرداخت', 'tab_sms' => 'سامانه‌های پیامکی', 'tab_notifications' => 'اطلاع‌رسانی‌ها', 'tab_terminology' => 'مدیریت اصطلاحات', 'tab_tools' => 'ابزارها',
            // General Settings
            'main_settings' => 'تنظیمات اصلی', 'active_payment_gateway' => 'درگاه پرداخت فعال', 'active_sms_provider' => 'سامانه پیامکی فعال', 'admin_mobile_number' => 'شماره موبایل مدیر',
            'gateway_inactive' => 'غیرفعال', 'gateway_zarinpal' => 'زرین پال', 'gateway_zibal' => 'زیبال',
            'sms_inactive' => 'غیرفعال', 'sms_kavenegar' => 'کاوه نگار', 'sms_farazsms' => 'فراز اس ام اس',
            // Payment Settings
            'zarinpal_settings' => 'تنظیمات درگاه پرداخت زرین‌پال', 'merchant_code' => 'کد مرچنت', 'zarinpal_desc' => 'کد مرچنت ۳۶ کاراکتری خود را از پنل زرین‌پال دریافت و در این قسمت وارد کنید.',
            'zibal_settings' => 'تنظیمات درگاه پرداخت زیبال', 'zibal_desc' => 'کد مرچنت خود را از پنل زیبال دریافت و در این قسمت وارد کنید.',
            'test_connection' => 'تست اتصال', 'test_button' => 'ارسال درخواست تست', 'save_before_test' => 'برای تست، ابتدا تنظیمات را ذخیره کنید.',
            // SMS Settings
            'kavenegar_settings' => 'تنظیمات سامانه پیامکی کاوه‌نگار', 'api_key' => 'کلید API', 'kavenegar_desc' => 'کلید API خود را از پنل کاوه‌نگار دریافت و در این قسمت وارد کنید.',
            'farazsms_settings' => 'تنظیمات سامانه پیامکی فراز اس‌ام‌اس', 'farazsms_desc' => 'کلید API خود را از پنل فراز اس‌ام‌اس دریافت و در این قسمت وارد کنید.', 'farazsms_pattern_code' => 'کد پترن', 'farazsms_sender_number' => 'شماره خط فرستنده',
            // Notifications Settings
            'sms_patterns' => 'الگوهای اطلاع‌رسانی پیامکی', 'admin_free_reg' => 'ثبت‌نام رایگان (برای مدیر)', 'user_free_reg' => 'ثبت‌نام رایگان (برای کاربر)',
            'admin_paid_reg' => 'خرید موفق (برای مدیر)', 'user_paid_reg' => 'خرید موفق (برای کاربر)', 'patterns_help_title' => 'راهنمای الگوها',
            'patterns_help_desc' => 'برای کاوه‌نگار نام قالب و برای فراز اس‌ام‌اس کد پترن را وارد کنید. از متغیرهای زیر می‌توانید در متن الگو/پترن خود استفاده کنید:',
            // Tools
            'utility_tools' => 'ابزارهای کاربردی', 'clear_cache' => 'پاک کردن کش', 'clear_cache_desc' => 'اگر تغییرات در سایت اعمال نمی‌شوند، از این دکمه برای پاک کردن کش داخلی وردپرس استفاده کنید.',
            'clear_cache_button' => 'پاک کردن کش افزونه', 'cache_cleared_success' => 'کش افزونه و وردپرس با موفقیت پاک شد.',
            'import_export_services' => 'درون‌ریزی / برون‌بری خدمات', 'export_all_services' => 'برون‌بری تمام خدمات', 'export_all_desc' => 'از تمام خدمات و اطلاعات آن‌ها یک فایل پشتیبان با فرمت JSON تهیه کنید.',
            'export_button' => 'دریافت فایل برون‌بری', 'import_services' => 'درون‌ریزی خدمات از فایل', 'import_desc' => 'یک فایل JSON معتبر (که قبلاً از همین افزونه برون‌بری شده) را برای ایجاد یا به‌روزرسانی خدمات انتخاب کنید.',
            'import_button' => 'شروع درون‌ریزی', 'import_success_message' => 'درون‌ریزی با موفقیت انجام شد. %d خدمت جدید ایجاد و %d خدمت به‌روزرسانی شد.',
            'import_error_message' => 'خطا در پردازش فایل. لطفاً از یک فایل برون‌بری معتبر استفاده کنید.', 'import_no_file_message' => 'هیچ فایلی برای درون‌ریزی انتخاب نشده است.',
            // Meta Box
            'service_details' => 'جزئیات خدمت', 'select_service_type' => 'نوع خدمت را انتخاب کنید:', 'choose' => 'انتخاب کنید...', 'premium_content_label' => 'محتوای ویژه',
            'premium_content_desc' => 'با فعال کردن این گزینه، این فیلد فقط به کاربرانی که خدمت را خریداری کرده‌اند نمایش داده می‌شود.', 'add_new_item' => 'افزودن آیتم جدید',
            'custom_fields_title' => 'فیلدهای سفارشی', 'import_export_single_title' => 'درون‌ریزی / برون‌بری این خدمت', 'export_this_service' => 'برون‌بری این خدمت',
            'import_to_this_service' => 'درون‌ریزی برای این خدمت', 'import_single_desc' => 'فایل JSON را انتخاب کنید. تمام اطلاعات این خدمت با محتوای فایل جایگزین خواهد شد.',
            'import_single_success' => 'اطلاعات با موفقیت درون‌ریزی و ذخیره شد.', 'import_single_error' => 'درون‌ریزی ناموفق بود.', 'import_single_no_file' => 'فایلی انتخاب نشده است.',
            // Frontend
            'key_info' => 'اطلاعات کلیدی', 'service_type' => 'نوع خدمت', 'status' => 'وضعیت', 'payment_model' => 'مدل فروش', 'featured' => 'ویژه',
            'start_date' => 'تاریخ برگزاری/شروع', 'start_time' => 'ساعت', 'status_available' => 'در دسترس', 'status_full' => 'تکمیل ظرفیت', 'status_ended' => 'پایان یافته',
            'payment_free' => 'رایگان', 'payment_paid' => 'پولی', 'is_featured_yes' => 'بله', 'is_featured_no' => 'خیر',
            'registration_fee' => 'هزینه ثبت نام', 'toman' => 'تومان', 'buy_now' => 'خرید و مشاهده', 'payment_and_reg' => 'پرداخت و ثبت نام',
            'fullname' => 'نام و نام خانوادگی', 'mobile_number' => 'شماره موبایل', 'free_reg_button' => 'ثبت نام رایگان', 'reg_success' => 'ثبت‌نام شما با موفقیت انجام شد.',
            'more_details' => 'توضیحات تکمیلی', 'gallery' => 'گالری تصاویر', 'no_service_in_cat' => 'هیچ خدمتی در این دسته یافت نشد.',
            'all_cats' => 'همه', 'view_details_btn' => 'مشاهده جزئیات', 'custom_fields_front_title' => 'سایر مشخصات',
            // JS
            'confirm_delete_item' => 'آیا از حذف این آیتم مطمئن هستید؟', 'media_modal_title' => 'انتخاب یا آپلود', 'media_modal_button' => 'انتخاب',
            'testing_request' => 'در حال ارسال درخواست...', 'test_success' => 'موفق', 'test_error' => 'خطا',
            // Errors
            'error_permission_denied' => 'دسترسی غیرمجاز.', 'error_invalid_info' => 'اطلاعات وارد شده نامعتبر است. لطفا به صفحه قبل بازگردید.',
            'error_payment_unavailable' => 'امکان پرداخت برای این خدمت در حال حاضر وجود ندارد.', 'error_invalid_gateway' => 'درگاه نامعتبر است.',
        ];
    }

    public static function register_settings() {
        // General Settings
        register_setting( 'nsm_settings_group_general', 'nsm_general_options', [self::class, 'sanitize_general_options'] );
        add_settings_section( 'nsm_general_section', nsm_get_string('main_settings', 'تنظیمات اصلی'), '__return_false', 'nsm_settings_general' );
        add_settings_field( 'active_payment_gateway', nsm_get_string('active_payment_gateway', 'درگاه پرداخت فعال'), [self::class, 'render_select_field'], 'nsm_settings_general', 'nsm_general_section', ['id' => 'active_payment_gateway', 'group' => 'nsm_general_options', 'options' => ['none' => nsm_get_string('gateway_inactive', 'غیرفعال'), 'zarinpal' => nsm_get_string('gateway_zarinpal', 'زرین پال'), 'zibal' => nsm_get_string('gateway_zibal', 'زیبال')]] );
        add_settings_field( 'active_sms_provider', nsm_get_string('active_sms_provider', 'سامانه پیامکی فعال'), [self::class, 'render_select_field'], 'nsm_settings_general', 'nsm_general_section', ['id' => 'active_sms_provider', 'group' => 'nsm_general_options', 'options' => ['none' => nsm_get_string('sms_inactive', 'غیرفعال'), 'kavenegar' => nsm_get_string('sms_kavenegar', 'کاوه نگار'), 'farazsms' => nsm_get_string('sms_farazsms', 'فراز اس ام اس')]] );
        add_settings_field( 'admin_mobile_number', nsm_get_string('admin_mobile_number', 'شماره موبایل مدیر'), [self::class, 'render_text_field'], 'nsm_settings_general', 'nsm_general_section', ['id' => 'admin_mobile_number', 'group' => 'nsm_general_options', 'desc' => 'این شماره برای دریافت پیامک‌های اطلاع‌رسانی مدیر استفاده می‌شود.'] );

        // Payment Settings
        register_setting( 'nsm_settings_group_payment', 'nsm_payment_options', [self::class, 'sanitize_text_fields'] );
        add_settings_section( 'nsm_zarinpal_section', nsm_get_string('zarinpal_settings', 'تنظیمات درگاه پرداخت زرین‌پال'), '__return_false', 'nsm_settings_zarinpal' );
        add_settings_field( 'zarinpal_merchant_id', nsm_get_string('merchant_code', 'کد مرچنت'), [self::class, 'render_text_field'], 'nsm_settings_zarinpal', 'nsm_zarinpal_section', ['id' => 'zarinpal_merchant_id', 'group' => 'nsm_payment_options', 'desc' => nsm_get_string('zarinpal_desc', 'کد مرچنت ۳۶ کاراکتری خود را از پنل زرین‌پال دریافت و در این قسمت وارد کنید.')] );
        add_settings_field( 'zarinpal_test', nsm_get_string('test_connection', 'تست اتصال'), [self::class, 'render_test_button'], 'nsm_settings_zarinpal', 'nsm_zarinpal_section', ['gateway' => 'zarinpal'] );

        add_settings_section( 'nsm_zibal_section', nsm_get_string('zibal_settings', 'تنظیمات درگاه پرداخت زیبال'), '__return_false', 'nsm_settings_zibal' );
        add_settings_field( 'zibal_merchant_id', nsm_get_string('merchant_code', 'کد مرچنت'), [self::class, 'render_text_field'], 'nsm_settings_zibal', 'nsm_zibal_section', ['id' => 'zibal_merchant_id', 'group' => 'nsm_payment_options', 'desc' => nsm_get_string('zibal_desc', 'کد مرچنت خود را از پنل زیبال دریافت و در این قسمت وارد کنید.')] );
        add_settings_field( 'zibal_test', nsm_get_string('test_connection', 'تست اتصال'), [self::class, 'render_test_button'], 'nsm_settings_zibal', 'nsm_zibal_section', ['gateway' => 'zibal'] );

        // SMS Settings
        register_setting( 'nsm_settings_group_sms', 'nsm_sms_options', [self::class, 'sanitize_text_fields'] );
        add_settings_section( 'nsm_kavenegar_section', nsm_get_string('kavenegar_settings', 'تنظیمات سامانه پیامکی کاوه‌نگار'), '__return_false', 'nsm_settings_kavenegar' );
        add_settings_field( 'kavenegar_api_key', nsm_get_string('api_key', 'کلید API'), [self::class, 'render_text_field'], 'nsm_settings_kavenegar', 'nsm_kavenegar_section', ['id' => 'kavenegar_api_key', 'group' => 'nsm_sms_options', 'desc' => nsm_get_string('kavenegar_desc', 'کلید API خود را از پنل کاوه‌نگار دریافت و در این قسمت وارد کنید.')] );
        add_settings_field( 'kavenegar_test', nsm_get_string('test_connection', 'تست اتصال'), [self::class, 'render_test_button'], 'nsm_settings_kavenegar', 'nsm_kavenegar_section', ['gateway' => 'kavenegar'] );

        add_settings_section( 'nsm_farazsms_section', nsm_get_string('farazsms_settings', 'تنظیمات سامانه پیامکی فراز اس‌ام‌اس'), '__return_false', 'nsm_settings_farazsms' );
        add_settings_field( 'farazsms_api_key', nsm_get_string('api_key', 'کلید API'), [self::class, 'render_text_field'], 'nsm_settings_farazsms', 'nsm_farazsms_section', ['id' => 'farazsms_api_key', 'group' => 'nsm_sms_options', 'desc' => nsm_get_string('farazsms_desc', 'کلید API خود را از پنل فراز اس‌ام‌اس دریافت و در این قسمت وارد کنید.')] );
        add_settings_field( 'farazsms_sender_number', nsm_get_string('farazsms_sender_number', 'شماره خط فرستنده'), [self::class, 'render_text_field'], 'nsm_settings_farazsms', 'nsm_farazsms_section', ['id' => 'farazsms_sender_number', 'group' => 'nsm_sms_options', 'desc' => 'شماره خط اختصاصی شما برای ارسال پیامک پترن.'] );
        add_settings_field( 'farazsms_test', nsm_get_string('test_connection', 'تست اتصال'), [self::class, 'render_test_button'], 'nsm_settings_farazsms', 'nsm_farazsms_section', ['gateway' => 'farazsms'] );
        
        // Notifications Settings
        register_setting( 'nsm_settings_group_notifications', 'nsm_notification_options', [self::class, 'sanitize_text_fields'] );
        add_settings_section( 'nsm_notifications_section', nsm_get_string('sms_patterns', 'الگوهای اطلاع‌رسانی پیامکی'), '__return_false', 'nsm_settings_notifications' );
        add_settings_field( 'admin_free_reg', nsm_get_string('admin_free_reg', 'ثبت‌نام رایگان (برای مدیر)'), [self::class, 'render_text_field'], 'nsm_settings_notifications', 'nsm_notifications_section', ['id' => 'admin_free_reg', 'group' => 'nsm_notification_options', 'desc' => 'نام قالب (کاوه‌نگار) یا کد پترن (فراز اس‌ام‌اس) را وارد کنید.'] );
        add_settings_field( 'user_free_reg', nsm_get_string('user_free_reg', 'ثبت‌نام رایگان (برای کاربر)'), [self::class, 'render_text_field'], 'nsm_settings_notifications', 'nsm_notifications_section', ['id' => 'user_free_reg', 'group' => 'nsm_notification_options', 'desc' => 'نام قالب (کاوه‌نگار) یا کد پترن (فراز اس‌ام‌اس) را وارد کنید.'] );
        add_settings_field( 'admin_paid_reg', nsm_get_string('admin_paid_reg', 'خرید موفق (برای مدیر)'), [self::class, 'render_text_field'], 'nsm_settings_notifications', 'nsm_notifications_section', ['id' => 'admin_paid_reg', 'group' => 'nsm_notification_options', 'desc' => 'نام قالب (کاوه‌نگار) یا کد پترن (فراز اس‌ام‌اس) را وارد کنید.'] );
        add_settings_field( 'user_paid_reg', nsm_get_string('user_paid_reg', 'خرید موفق (برای کاربر)'), [self::class, 'render_text_field'], 'nsm_settings_notifications', 'nsm_notifications_section', ['id' => 'user_paid_reg', 'group' => 'nsm_notification_options', 'desc' => 'نام قالب (کاوه‌نگار) یا کد پترن (فراز اس‌ام‌اس) را وارد کنید.'] );
        add_settings_field( 'patterns_help', nsm_get_string('patterns_help_title', 'راهنمای الگوها'), [self::class, 'render_patterns_help'], 'nsm_settings_notifications', 'nsm_notifications_section' );
        
        // Terminology Settings
        register_setting( 'nsm_settings_group_terminology', 'nsm_terminology_options', [self::class, 'sanitize_text_fields'] );
        add_settings_section( 'nsm_terminology_section', nsm_get_string('tab_terminology', 'مدیریت اصطلاحات'), '__return_false', 'nsm_settings_terminology' );
        $all_terms = self::get_default_terminology();
        foreach ($all_terms as $key => $label) {
            add_settings_field($key, $label, [self::class, 'render_text_field'], 'nsm_settings_terminology', 'nsm_terminology_section', ['id' => $key, 'group' => 'nsm_terminology_options']);
        }
        
        // Tools Settings
        add_settings_section( 'nsm_tools_section', nsm_get_string('utility_tools', 'ابزارهای کاربردی'), '__return_false', 'nsm_settings_tools' );
        add_settings_field( 'clear_cache', nsm_get_string('clear_cache', 'پاک کردن کش'), [self::class, 'render_clear_cache_button'], 'nsm_settings_tools', 'nsm_tools_section' );
        add_settings_section( 'nsm_import_export_section', nsm_get_string('import_export_services', 'درون‌ریزی / برون‌بری خدمات'), '__return_false', 'nsm_settings_tools' );
        add_settings_field( 'global_export', nsm_get_string('export_all_services', 'برون‌بری تمام خدمات'), [self::class, 'render_global_export_button'], 'nsm_settings_tools', 'nsm_import_export_section' );
        add_settings_field( 'global_import', nsm_get_string('import_services', 'درون‌ریزی خدمات از فایل'), [self::class, 'render_global_import_form'], 'nsm_settings_tools', 'nsm_import_export_section' );
    }

    // Sanitize callbacks
    public static function sanitize_general_options($input) {
        $output = [];
        $output['active_payment_gateway'] = isset($input['active_payment_gateway']) ? sanitize_key($input['active_payment_gateway']) : 'none';
        $output['active_sms_provider'] = isset($input['active_sms_provider']) ? sanitize_key($input['active_sms_provider']) : 'none';
        $output['admin_mobile_number'] = isset($input['admin_mobile_number']) ? sanitize_text_field($input['admin_mobile_number']) : '';
        return $output;
    }
    public static function sanitize_text_fields($input) {
        $output = [];
        if(is_array($input)){
            foreach ($input as $key => $value) {
                $output[sanitize_key($key)] = sanitize_text_field($value);
            }
        }
        return $output;
    }
    public static function sanitize_textarea_fields($input) {
        $output = [];
        if(is_array($input)){
            foreach ($input as $key => $value) {
                $output[sanitize_key($key)] = sanitize_textarea_field($value);
            }
        }
        return $output;
    }

    public static function render_text_field($args) {
        $options = get_option($args['group']);
        $value = isset($options[$args['id']]) ? $options[$args['id']] : '';
        echo '<input type="text" name="' . esc_attr($args['group']) . '[' . esc_attr($args['id']) . ']" value="' . esc_attr($value) . '" class="regular-text">';
        if (!empty($args['desc'])) {
            echo '<p class="description">' . esc_html($args['desc']) . '</p>';
        }
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
        echo '<button type="button" class="button nsm-test-button" data-gateway="'.esc_attr($gateway).'">' . nsm_get_string('test_button', 'ارسال درخواست تست') . '</button>';
        echo '<p class="description">' . nsm_get_string('save_before_test', 'برای تست، ابتدا تنظیمات را ذخیره کنید.') . '</p>';
        echo '<div id="nsm-test-result-' . esc_attr($gateway) . '" class="nsm-test-result"></div>';
    }
    public static function render_patterns_help() {
        echo '<p class="description">' . nsm_get_string('patterns_help_desc', 'برای کاوه‌نگار نام قالب و برای فراز اس‌ام‌اس کد پترن را وارد کنید. از متغیرهای زیر می‌توانید در متن الگو/پترن خود استفاده کنید:') . '</p>';
        echo '<ul>';
        echo '<li><strong>کاوه نگار:</strong> توکن‌ها به ترتیب: <code>%token</code> (نام کاربر), <code>%token2</code> (نام خدمت), <code>%token3</code> (کد رهگیری)</li>';
        echo '<li><strong>فراز اس ام اس:</strong> متغیرها به ترتیب: <code>%user_name%</code>, <code>%service_name%</code>, <code>%transaction_id%</code></li>';
        echo '</ul>';
    }
    public static function render_clear_cache_button() {
        $nonce_url = wp_nonce_url(
            admin_url('admin-post.php?action=nsm_clear_cache'),
            'nsm_clear_cache_nonce'
        );
        ?>
        <p class="description"><?php echo nsm_get_string('clear_cache_desc', 'اگر تغییرات در سایت اعمال نمی‌شوند، از این دکمه برای پاک کردن کش داخلی وردپرس استفاده کنید.'); ?></p>
        <p><a href="<?php echo esc_url($nonce_url); ?>" class="button button-primary"><?php echo nsm_get_string('clear_cache_button', 'پاک کردن کش افزونه'); ?></a></p>
        <?php
    }
    public static function render_global_export_button() {
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="nsm_global_export">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('nsm_global_export_nonce'); ?>">
            <p class="description"><?php echo nsm_get_string('export_all_desc', 'از تمام خدمات و اطلاعات آن‌ها یک فایل پشتیبان با فرمت JSON تهیه کنید.'); ?></p>
            <?php submit_button(nsm_get_string('export_button', 'دریافت فایل برون‌بری'), 'primary', 'submit', false); ?>
        </form>
        <?php
    }
    public static function render_global_import_form() {
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
            <input type="hidden" name="action" value="nsm_global_import">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('nsm_global_import_nonce'); ?>">
            <p class="description"><?php echo nsm_get_string('import_desc', 'یک فایل JSON معتبر (که قبلاً از همین افزونه برون‌بری شده) را برای ایجاد یا به‌روزرسانی خدمات انتخاب کنید.'); ?></p>
            <p><input type="file" name="import_file" accept=".json"></p>
            <?php submit_button(nsm_get_string('import_button', 'شروع درون‌ریزی'), 'secondary', 'submit', false); ?>
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
        <div class="wrap nsm-help-page">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <p>به صفحه راهنمای افزونه مدیریت خدمات نیلای خوش آمدید. در این بخش با قابلیت‌های کلیدی و نحوه استفاده از افزونه آشنا می‌شوید.</p>

            <h2>۱. افزودن و مدیریت خدمات</h2>
            <p>برای افزودن یک خدمت جدید، از منوی "خدمات" در پیشخوان وردپرس، روی "افزودن خدمت" کلیک کنید.</p>
            <ul>
                <li><strong>انتخاب نوع خدمت:</strong> اولین و مهم‌ترین قدم، انتخاب "نوع خدمت" است. با انتخاب هر نوع، فیلدهای مرتبط با آن به صورت خودکار نمایش داده می‌شوند.</li>
                <li><strong>جزئیات عمومی:</strong> فیلدهایی مانند قالب نمایش، حالت رنگی (روشن/تیره)، مدل فروش (رایگان/پولی)، قیمت و... در این بخش قرار دارند.</li>
                <li><strong>فیلدهای تخصصی:</strong> بر اساس نوع خدمت انتخابی، فیلدهای تخصصی (مانند سرفصل‌ها برای دوره آموزشی یا برنامه سفر برای تور) ظاهر می‌شوند.</li>
                <li><strong>فیلدهای سفارشی:</strong> در انتهای صفحه، بخشی برای افزودن فیلدهای دلخواه شما وجود دارد. می‌توانید هر تعداد فیلد با عنوان، مقدار، آیکون و نوع نمایش دلخواه اضافه کنید. این فیلدها در انتهای صفحه خدمت نمایش داده می‌شوند.</li>
            </ul>

            <h2>۲. درون‌ریزی و برون‌بری</h2>
            <p>این افزونه دو روش برای تهیه پشتیبان و انتقال اطلاعات خدمات ارائه می‌دهد:</p>
            <ul>
                <li><strong>روش تکی (در صفحه ویرایش خدمت):</strong> در ستون کناری صفحه ویرایش هر خدمت، متاباکسی با عنوان "درون‌ریزی / برون‌بری این خدمت" وجود دارد. با کلیک روی دکمه "برون‌بری"، یک فایل JSON از تمام اطلاعات همان خدمت دریافت می‌کنید. سپس می‌توانید این فایل را در یک خدمت دیگر (در همین سایت یا سایت دیگر) درون‌ریزی کنید تا تمام اطلاعات جایگزین شوند.</li>
                <li><strong>روش کلی (در تنظیمات افزونه):</strong> در صفحه "تنظیمات > ابزارها"، می‌توانید از تمام خدمات سایت به صورت یکجا یک فایل JSON خروجی بگیرید یا یک فایل را برای درون‌ریزی خدمات انتخاب کنید. هنگام درون‌ریزی کلی، افزونه به صورت هوشمند خدمات موجود را به‌روزرسانی و خدمات جدید را ایجاد می‌کند.</li>
            </ul>

            <h2>۳. استفاده از شورت‌کدها</h2>
            <p>می‌توانید از شورت‌کدهای زیر برای نمایش خدمات در هر بخشی از سایت (مانند ویرایشگر کلاسیک، ابزارک‌ها یا سایر صفحه‌سازها) استفاده کنید.</p>

            <h3>شورت‌کد اصلی: <code>[services_grid]</code></h3>
            <p>این شورت‌کد خدمات را به صورت یک گرید (شبکه) نمایش می‌دهد و پرکاربردترین شورت‌کد افزونه است.</p>
            <table class="shortcode-table">
                <thead><tr><th>پارامتر</th><th>توضیح</th><th>مقادیر مجاز</th><th>مثال</th></tr></thead>
                <tbody>
                    <tr><td><code>count</code></td><td>تعداد کل خدمات برای نمایش</td><td>یک عدد (پیش‌فرض: 9)</td><td><code>[services_grid count="6"]</code></td></tr>
                    <tr><td><code>columns</code></td><td>تعداد ستون‌های گرید</td><td>1, 2, 3, 4, 6 (پیش‌فرض: 3)</td><td><code>[services_grid columns="4"]</code></td></tr>
                    <tr><td><code>category</code></td><td>نمایش خدمات از یک دسته‌بندی خاص (از نامک دسته‌بندی استفاده کنید)</td><td>نامک دسته‌بندی</td><td><code>[services_grid category="webinar"]</code></td></tr>
                    <tr><td><code>keyword</code></td><td>نمایش خدمات از یک کلیدواژه خاص (از نامک کلیدواژه استفاده کنید)</td><td>نامک کلیدواژه</td><td><code>[services_grid keyword="online-course"]</code></td></tr>
                    <tr><td><code>ids</code></td><td>نمایش خدمات خاص بر اساس شناسه‌شان (با کاما جدا کنید)</td><td>12,34,56</td><td><code>[services_grid ids="12,34"]</code></td></tr>
                    <tr><td><code>orderby</code></td><td>مرتب‌سازی بر اساس</td><td><code>date</code>, <code>title</code>, <code>rand</code>, <code>ID</code></td><td><code>[services_grid orderby="title"]</code></td></tr>
                    <tr><td><code>order</code></td><td>ترتیب مرتب‌سازی</td><td><code>ASC</code>, <code>DESC</code></td><td><code>[services_grid order="ASC"]</code></td></tr>
                    <tr><td><code>show_image</code></td><td>نمایش یا عدم نمایش تصویر کارت</td><td><code>yes</code>, <code>no</code> (پیش‌فرض: yes)</td><td><code>[services_grid show_image="no"]</code></td></tr>
                    <tr><td><code>show_excerpt</code></td><td>نمایش یا عدم نمایش خلاصه</td><td><code>yes</code>, <code>no</code> (پیش‌فرض: yes)</td><td><code>[services_grid show_excerpt="no"]</code></td></tr>
                    <tr><td><code>excerpt_length</code></td><td>تعداد کلمات خلاصه</td><td>یک عدد (پیش‌فرض: 15)</td><td><code>[services_grid excerpt_length="10"]</code></td></tr>
                    <tr><td><code>button_text</code></td><td>متن دکمه کارت</td><td>متن دلخواه</td><td><code>[services_grid button_text="بیشتر بخوانید"]</code></td></tr>
                </tbody>
            </table>

            <h3>سایر شورت‌کدها</h3>
             <table class="shortcode-table">
                 <thead><tr><th>شورت‌کد</th><th>توضیح</th><th>مثال</th></tr></thead>
                <tbody>
                    <tr><td><code>[services_carousel]</code></td><td>خدمات را به صورت اسلایدر نمایش می‌دهد و تمام پارامترهای <code>[services_grid]</code> را به همراه دو پارامتر اضافی <code>autoplay="true"</code> و <code>speed="5000"</code> می‌پذیرد.</td><td><code>[services_carousel count="8" autoplay="true"]</code></td></tr>
                    <tr><td><code>[service_filter_grid]</code></td><td>یک گرید کامل از خدمات همراه با دکمه‌های فیلتر ایجکس بر اساس دسته‌بندی‌ها ایجاد می‌کند. این شورت‌کد تمام پارامترهای <code>[services_grid]</code> را برای نمایش اولیه می‌پذیرد.</td><td><code>[service_filter_grid count="12" columns="4"]</code></td></tr>
                    <tr><td><code>[single_service]</code></td><td>فقط یک خدمت خاص را با استفاده از ID آن به صورت کارت نمایش می‌دهد.</td><td><code>[single_service id="123"]</code></td></tr>
                     <tr><td><code>[service_meta]</code></td><td>مقدار یک فیلد خاص از یک خدمت را نمایش می‌دهد.</td><td><code>[service_meta id="123" meta_key="_nsm_price"]</code></td></tr>
                </tbody>
            </table>
        </div>
        <?php
    }
}

/**
 * کلاس مدیریت متاباکس‌ها و فیلدهای سفارشی
 */
class NSM_Meta_Boxes {
    public static $meta_fields = [];
    public static function add_meta_boxes() { 
        add_meta_box('nsm_service_details', nsm_get_string('service_details', 'جزئیات خدمت'), [self::class, 'render_meta_box'], 'service', 'normal', 'high'); 
        add_meta_box('nsm_custom_fields', nsm_get_string('custom_fields_title', 'فیلدهای سفارشی'), [self::class, 'render_custom_fields_meta_box'], 'service', 'normal', 'default');
        add_meta_box('nsm_service_import_export', nsm_get_string('import_export_single_title', 'درون‌ریزی / برون‌بری این خدمت'), [self::class, 'render_import_export_meta_box'], 'service', 'side', 'low');
    }

    public static function render_meta_box( $post ) {
        wp_nonce_field( 'nsm_save_meta_box_data', 'nsm_meta_box_nonce' );
        self::define_fields();
        $service_type = get_post_meta( $post->ID, '_nsm_service_type', true );
        ?>
        <div class="nsm-meta-box-field">
            <label for="_nsm_service_type"><strong><?php echo nsm_get_string('select_service_type', 'نوع خدمت را انتخاب کنید:'); ?></strong></label>
            <select name="_nsm_service_type" id="_nsm_service_type">
                <option value=""><?php echo nsm_get_string('choose', 'انتخاب کنید...'); ?></option>
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
    
    public static function render_custom_fields_meta_box($post) {
        // Nonce is already in the main meta box
        self::render_field($post->ID, [
            'id' => '_nsm_custom_fields',
            'label' => '',
            'type' => 'repeater',
            'fields' => [
                ['id' => 'field_label', 'label' => 'عنوان فیلد'],
                ['id' => 'field_value', 'label' => 'مقدار فیلد', 'type' => 'textarea'],
                ['id' => 'field_type', 'label' => 'نوع نمایش', 'type' => 'select', 'options' => [
                    'text' => 'متن ساده', 
                    'html' => 'HTML (بدون پاراگراف خودکار)', 
                    'wpautop' => 'محتوای کامل (با پاراگراف خودکار)'
                ]],
                ['id' => 'field_icon', 'label' => 'آیکون (اختیاری)', 'type' => 'media']
            ]
        ]);
    }

    public static function render_import_export_meta_box($post) {
        if (isset($_GET['nsm_import_status'])) {
            $status = $_GET['nsm_import_status'];
            if ($status === 'success') {
                echo '<div class="notice notice-success is-dismissible"><p>' . nsm_get_string('import_single_success', 'اطلاعات با موفقیت درون‌ریزی و ذخیره شد.') . '</p></div>';
            } elseif ($status === 'error') {
                 echo '<div class="notice notice-error is-dismissible"><p>' . nsm_get_string('import_single_error', 'درون‌ریزی ناموفق بود.') . '</p></div>';
            } elseif ($status === 'no_file') {
                 echo '<div class="notice notice-warning is-dismissible"><p>' . nsm_get_string('import_single_no_file', 'فایلی انتخاب نشده است.') . '</p></div>';
            }
        }
        ?>
        <h4><?php echo nsm_get_string('export_this_service', 'برون‌بری این خدمت'); ?></h4>
        <a href="<?php echo esc_url(admin_url('admin-post.php?action=nsm_export_single&post_id=' . $post->ID . '&nonce=' . wp_create_nonce('nsm_export_single_nonce'))); ?>" class="button button-secondary"><?php echo nsm_get_string('export_button', 'دریافت فایل برون‌بری'); ?></a>
        <hr>
        <h4><?php echo nsm_get_string('import_to_this_service', 'درون‌ریزی برای این خدمت'); ?></h4>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
            <input type="hidden" name="action" value="nsm_import_single">
            <input type="hidden" name="post_id" value="<?php echo $post->ID; ?>">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('nsm_import_single_nonce'); ?>">
            <p class="description"><?php echo nsm_get_string('import_single_desc', 'فایل JSON را انتخاب کنید. تمام اطلاعات این خدمت با محتوای فایل جایگزین خواهد شد.'); ?></p>
            <p><input type="file" name="import_file" accept=".json" style="width: 100%;"></p>
            <?php submit_button(nsm_get_string('import_button', 'شروع درون‌ریزی'), 'primary', 'submit', false); ?>
        </form>
        <?php
    }
    
    private static function render_field($post_id, $field) {
        $value = get_post_meta($post_id, $field['id'], true);
        $extra_class = $field['extra_class'] ?? '';
        echo '<div class="nsm-meta-box-field ' . esc_attr($extra_class) . '">';
        
        if (!empty($field['label'])) {
            echo '<label for="' . esc_attr($field['id']) . '">' . esc_html($field['label']) . '</label>';
        }
        
        if (isset($field['is_premium']) && $field['is_premium']) {
            $is_paid_value = get_post_meta($post_id, $field['id'] . '_is_paid', true);
            echo '<div class="nsm-paid-toggle-container"><label><input type="checkbox" name="' . esc_attr($field['id']) . '_is_paid" value="1" ' . checked($is_paid_value, '1', false) . '> <strong>' . nsm_get_string('premium_content_label', 'محتوای ویژه') . ':</strong> ' . nsm_get_string('premium_content_desc', 'با فعال کردن این گزینه، این فیلد فقط به کاربرانی که خدمت را خریداری کرده‌اند نمایش داده می‌شود.') . '</label></div>';
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
                 echo ' <button type="button" class="button nsm-upload-button">' . nsm_get_string('media_modal_button', 'انتخاب') . '</button>';
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
                echo '<button type="button" class="button add-repeater-field">' . nsm_get_string('add_new_item', 'افزودن آیتم جدید') . '</button>';
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
            $value = isset($item_values[$sub_field['id']]) ? $item_values[$sub_field['id']] : '';
            $html .= '<div class="nsm-meta-box-field">';
            $html .= '<label>' . esc_html($sub_field['label']) . '</label>';
            
            $sub_field_type = $sub_field['type'] ?? 'text';

            switch ($sub_field_type) {
                case 'media':
                    $html .= '<input type="text" name="' . $name . '" value="' . esc_attr($value) . '" style="width: 70%;" readonly>';
                    $html .= ' <button type="button" class="button nsm-upload-button">' . nsm_get_string('media_modal_button', 'انتخاب') . '</button>';
                    $html .= '<div class="nsm-image-preview">' . ($value ? '<img src="'.esc_url($value).'" style="max-width:100px; margin-top: 5px;"/>' : '') . '</div>';
                    break;
                case 'textarea':
                    $html .= '<textarea name="' . $name . '" rows="3">' . esc_textarea($value) . '</textarea>';
                    break;
                case 'select':
                     $html .= '<select name="' . $name . '">';
                     foreach($sub_field['options'] as $key => $label) {
                         $selected = ($value == $key) ? ' selected="selected"' : '';
                         $html .= '<option value="' . esc_attr($key) . '"' . $selected . '>' . esc_html($label) . '</option>';
                     }
                     $html .= '</select>';
                    break;
                case 'text':
                default:
                    $html .= '<input type="text" name="' . $name . '" value="' . esc_attr($value) . '">';
                    break;
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
        
        $all_fields = array_merge(...array_values(self::$meta_fields));
        $all_fields[] = [
            'id' => '_nsm_custom_fields',
            'type' => 'repeater',
        ];

        foreach ($all_fields as $field) {
            if (isset($_POST[$field['id']])) {
                $value = $_POST[$field['id']];
                if ($field['type'] === 'repeater' && is_array($value)) {
                    $sanitized_repeater = [];
                    foreach ($value as $item) {
                        $sanitized_item = [];
                        if (is_array($item)) {
                            foreach($item as $key => $sub_value) {
                                $sanitized_item[sanitize_key($key)] = wp_kses_post($sub_value);
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
    
    public static function get_service_types() {
        return [
            'educational' => '۱. آموزشی و فرهنگی',
            'events' => '۲. رویدادها و همایش‌ها',
            'consulting' => '۳. مشاوره و کوچینگ',
            'tourism' => '۴. گردشگری و اقامتی',
            'sports' => '۵. ورزشی و سلامتی',
            'beauty' => '۶. زیبایی و آرایشی',
            'technical' => '۷. فنی و نصب',
            'rentals' => '۸. اجاره',
            'restaurant' => '۹. رستوران و کافه',
            'legal' => '۱۰. حقوقی و مالی',
            'transport' => '۱۱. حمل و نقل',
            'pets' => '۱۲. حیوانات خانگی',
            'home' => '۱۳. خدمات منزل',
            'creative' => '۱۴. خلاقانه و هنری',
            'childcare' => '۱۵. آموزشی و مراقبتی کودک',
            'ceremonial' => '۱۶. تشریفات و تدارکات',
        ];
    }

    public static function define_fields() {
        self::$meta_fields = [
            'general' => [
                ['id' => '_nsm_service_summary', 'label' => 'چکیده خدمت', 'type' => 'textarea'],
                ['id' => '_nsm_service_details', 'label' => 'توضیحات کامل خدمت', 'type' => 'wp_editor'],
                ['id' => '_nsm_gallery_images', 'label' => 'گالری تصاویر', 'type' => 'repeater', 'fields' => [['id' => 'image', 'label' => 'تصویر', 'type' => 'media']]],
                ['id' => '_nsm_service_template', 'label' => 'قالب نمایش صفحه', 'type' => 'select', 'options' => [
                    'layout-1' => 'قالب ۱: حرفه‌ای مدرن', 'layout-2' => 'قالب ۲: گالری فراگیر', 'layout-3' => 'قالب ۳: تمرکز بر هیرو',
                    'layout-4' => 'قالب ۴: شیک', 'layout-5' => 'قالب ۵: فرود و اقدام',
                    'layout-6' => 'قالب ۶: مینیمال', 'layout-7' => 'قالب ۷: گرادیانت',
                ]],
                ['id' => '_nsm_template_mode', 'label' => 'حالت رنگی قالب', 'type' => 'select', 'options' => [
                    'light' => 'روشن (پیش‌فرض)', 'dark' => 'تیره',
                ]],
                ['id' => '_nsm_payment_model', 'label' => 'مدل فروش', 'type' => 'select', 'options' => ['free' => 'رایگان', 'paid' => 'پولی']],
                ['id' => '_nsm_price', 'label' => 'هزینه/مبلغ', 'type' => 'number', 'extra_class' => 'nsm-paid-options'],
                ['id' => '_nsm_reg_link', 'label' => 'لینک ثبت‌نام/خرید خارجی', 'type' => 'url', 'extra_class' => 'nsm-paid-options'],
                ['id' => '_nsm_is_featured', 'label' => 'خدمت ویژه', 'type' => 'select', 'options' => ['no' => 'خیر', 'yes' => 'بله']],
                ['id' => '_nsm_status', 'label' => 'وضعیت خدمت', 'type' => 'select', 'options' => ['available' => 'در دسترس', 'full' => 'تکمیل ظرفیت', 'ended' => 'پایان یافته']],
                ['id' => '_nsm_date', 'label' => 'تاریخ برگزاری/شروع', 'type' => 'date'],
                ['id' => '_nsm_time', 'label' => 'ساعت برگزاری/شروع', 'type' => 'time'],
                ['id' => '_nsm_paid_content', 'label' => 'محتوای ویژه خریداران', 'type' => 'wp_editor', 'extra_class' => 'nsm-paid-options', 'is_premium' => true],
            ],
            'educational' => [
                ['id' => '_nsm_edu_format', 'label' => 'شیوه برگزاری', 'type' => 'select', 'options' => ['online' => 'آنلاین', 'in_person' => 'حضوری', 'hybrid' => 'ترکیبی']],
                ['id' => '_nsm_edu_level', 'label' => 'سطح دوره', 'type' => 'select', 'options' => ['beginner' => 'مقدماتی', 'intermediate' => 'متوسط', 'advanced' => 'پیشرفته']],
                ['id' => '_nsm_edu_sessions', 'label' => 'تعداد جلسات', 'type' => 'number'],
                ['id' => '_nsm_edu_duration_total', 'label' => 'کل مدت زمان دوره (ساعت)', 'type' => 'number'],
                ['id' => '_nsm_edu_instructor_name', 'label' => 'نام مدرس', 'type' => 'text'],
                ['id' => '_nsm_edu_instructor_photo', 'label' => 'تصویر مدرس', 'type' => 'media'],
                ['id' => '_nsm_edu_instructor_bio', 'label' => 'بیوگرافی مدرس', 'type' => 'textarea'],
                ['id' => '_nsm_edu_prerequisites', 'label' => 'پیش‌نیازهای دوره', 'type' => 'repeater', 'fields' => [['id' => 'item', 'label' => 'آیتم پیش‌نیاز']]],
                ['id' => '_nsm_edu_syllabus', 'label' => 'سرفصل‌های کامل دوره', 'type' => 'repeater', 'fields' => [['id' => 'section_title', 'label' => 'عنوان سرفصل'], ['id' => 'section_topics', 'label' => 'توضیحات سرفصل', 'type' => 'textarea']]],
                ['id' => '_nsm_edu_downloads', 'label' => 'فایل‌های ضمیمه', 'type' => 'repeater', 'fields' => [['id' => 'file_title', 'label' => 'عنوان فایل'], ['id' => 'file_url', 'label' => 'لینک فایل', 'type' => 'media']], 'is_premium' => true],
                ['id' => '_nsm_edu_certificate', 'label' => 'ارائه گواهینامه', 'type' => 'select', 'options' => ['no' => 'خیر', 'yes' => 'بله']],
                ['id' => '_nsm_edu_support_type', 'label' => 'نحوه پشتیبانی', 'type' => 'text'],
            ],
            'events' => [
                ['id' => '_nsm_evt_type', 'label' => 'نوع رویداد', 'type' => 'select', 'options' => ['conference' => 'کنفرانس', 'webinar' => 'وبینار', 'workshop' => 'کارگاه', 'seminar' => 'سمینار']],
                ['id' => '_nsm_evt_organizer', 'label' => 'نام برگزارکننده', 'type' => 'text'],
                ['id' => '_nsm_evt_capacity', 'label' => 'ظرفیت رویداد (نفر)', 'type' => 'number'],
                ['id' => '_nsm_evt_location_name', 'label' => 'محل برگزاری', 'type' => 'text'],
                ['id' => '_nsm_evt_location_address', 'label' => 'آدرس دقیق', 'type' => 'textarea'],
                ['id' => '_nsm_evt_location_map', 'label' => 'کد Embed نقشه', 'type' => 'textarea'],
                ['id' => '_nsm_evt_speakers', 'label' => 'سخنرانان', 'type' => 'repeater', 'fields' => [['id' => 'speaker_name', 'label' => 'نام'], ['id' => 'speaker_title', 'label' => 'عنوان/سمت'], ['id' => 'speaker_photo', 'label' => 'عکس', 'type' => 'media']]],
                ['id' => '_nsm_evt_agenda', 'label' => 'برنامه زمان‌بندی', 'type' => 'repeater', 'fields' => [['id' => 'agenda_time', 'label' => 'زمان'], ['id' => 'agenda_title', 'label' => 'عنوان برنامه']]],
                ['id' => '_nsm_evt_tickets', 'label' => 'انواع بلیت', 'type' => 'repeater', 'fields' => [['id' => 'ticket_type', 'label' => 'نوع بلیت (VIP)'], ['id' => 'ticket_price', 'label' => 'قیمت'], ['id' => 'ticket_features', 'label' => 'ویژگی‌ها']]],
            ],
            'consulting' => [
                ['id' => '_nsm_con_consultant_name', 'label' => 'نام مشاور/کوچ', 'type' => 'text'],
                ['id' => '_nsm_con_consultant_photo', 'label' => 'تصویر مشاور', 'type' => 'media'],
                ['id' => '_nsm_con_experience_years', 'label' => 'سال‌های تجربه', 'type' => 'number'],
                ['id' => '_nsm_con_specializations', 'label' => 'حوزه‌های تخصصی', 'type' => 'repeater', 'fields' => [['id' => 'item', 'label' => 'تخصص']]],
                ['id' => '_nsm_con_credentials', 'label' => 'تحصیلات و گواهینامه‌ها', 'type' => 'repeater', 'fields' => [['id' => 'item', 'label' => 'مدرک/گواهینامه']]],
                ['id' => '_nsm_con_session_duration', 'label' => 'مدت زمان هر جلسه (دقیقه)', 'type' => 'number'],
                ['id' => '_nsm_con_session_type', 'label' => 'نوع جلسه', 'type' => 'select', 'options' => ['phone' => 'تلفنی', 'online' => 'آنلاین', 'in_person' => 'حضوری']],
                ['id' => '_nsm_con_packages', 'label' => 'پکیج‌های مشاوره', 'type' => 'repeater', 'fields' => [['id' => 'package_title', 'label' => 'عنوان'], ['id' => 'package_sessions_count', 'label' => 'تعداد جلسات'], ['id' => 'package_price', 'label' => 'قیمت']]],
            ],
            'tourism' => [
                ['id' => '_nsm_trs_destination', 'label' => 'مقصد', 'type' => 'text'],
                ['id' => '_nsm_trs_duration_text', 'label' => 'مدت زمان تور', 'type' => 'text'],
                ['id' => '_nsm_trs_vehicle', 'label' => 'وسیله نقلیه', 'type' => 'text'],
                ['id' => '_nsm_trs_accommodation_type', 'label' => 'نوع اقامتگاه', 'type' => 'text'],
                ['id' => '_nsm_trs_includes', 'label' => 'خدمات شامل می‌شود', 'type' => 'repeater', 'fields' => [['id' => 'item', 'label' => 'مورد']]],
                ['id' => '_nsm_trs_excludes', 'label' => 'خدمات شامل نمی‌شود', 'type' => 'repeater', 'fields' => [['id' => 'item', 'label' => 'مورد']]],
                ['id' => '_nsm_trs_itinerary', 'label' => 'برنامه سفر روز به روز', 'type' => 'repeater', 'fields' => [['id' => 'day_title', 'label' => 'عنوان روز'], ['id' => 'day_plan', 'label' => 'برنامه', 'type' => 'textarea']]],
                ['id' => '_nsm_trs_required_items', 'label' => 'لوازم ضروری سفر', 'type' => 'repeater', 'fields' => [['id' => 'item', 'label' => 'مورد']]],
            ],
            'sports' => [
                ['id' => '_nsm_sport_coach_name', 'label' => 'نام مربی/متخصص', 'type' => 'text'],
                ['id' => '_nsm_sport_coach_photo', 'label' => 'تصویر مربی', 'type' => 'media'],
                ['id' => '_nsm_sport_class_level', 'label' => 'سطح کلاس', 'type' => 'select', 'options' => ['beginner' => 'مبتدی', 'intermediate' => 'متوسط', 'advanced' => 'حرفه‌ای']],
                ['id' => '_nsm_sport_duration', 'label' => 'مدت زمان هر جلسه (دقیقه)', 'type' => 'number'],
                ['id' => '_nsm_sport_equipment', 'label' => 'تجهیزات مورد نیاز', 'type' => 'repeater', 'fields' => [['id' => 'item', 'label' => 'تجهیزات']]],
                ['id' => '_nsm_sport_schedule', 'label' => 'برنامه هفتگی', 'type' => 'repeater', 'fields' => [['id' => 'day', 'label' => 'روز'], ['id' => 'time', 'label' => 'ساعت']]],
                ['id' => '_nsm_sport_focus_areas', 'label' => 'تمرکز اصلی', 'type' => 'text'],
            ],
            'beauty' => [
                ['id' => '_nsm_beauty_specialist_name', 'label' => 'نام متخصص/آرایشگر', 'type' => 'text'],
                ['id' => '_nsm_beauty_duration', 'label' => 'مدت زمان تقریبی (دقیقه)', 'type' => 'number'],
                ['id' => '_nsm_beauty_used_products', 'label' => 'محصولات مورد استفاده', 'type' => 'repeater', 'fields' => [['id' => 'item', 'label' => 'محصول']]],
                ['id' => '_nsm_beauty_pre_care', 'label' => 'مراقبت‌های قبل', 'type' => 'repeater', 'fields' => [['id' => 'item', 'label' => 'مورد']]],
                ['id' => '_nsm_beauty_post_care', 'label' => 'مراقبت‌های بعد', 'type' => 'repeater', 'fields' => [['id' => 'item', 'label' => 'مورد']]],
            ],
            'technical' => [
                ['id' => '_nsm_tech_service_area', 'label' => 'محدوده ارائه خدمات', 'type' => 'textarea'],
                ['id' => '_nsm_tech_supported_brands', 'label' => 'برندهای تحت پوشش', 'type' => 'repeater', 'fields' => [['id' => 'item', 'label' => 'برند']]],
                ['id' => '_nsm_tech_transport_fee', 'label' => 'هزینه ایاب و ذهاب', 'type' => 'number'],
                ['id' => '_nsm_tech_warranty', 'label' => 'مدت زمان گارانتی', 'type' => 'text'],
                ['id' => '_nsm_tech_working_hours', 'label' => 'ساعات کاری', 'type' => 'text'],
            ],
            'rentals' => [
                ['id' => '_nsm_rental_daily_rate', 'label' => 'هزینه اجاره روزانه', 'type' => 'number'],
                ['id' => '_nsm_rental_weekly_rate', 'label' => 'هزینه اجاره هفتگی', 'type' => 'number'],
                ['id' => '_nsm_rental_deposit', 'label' => 'مبلغ ودیعه', 'type' => 'number'],
                ['id' => '_nsm_rental_features', 'label' => 'ویژگی‌ها و امکانات', 'type' => 'repeater', 'fields' => [['id' => 'feature_icon', 'label' => 'آیکون', 'type' => 'media'], ['id' => 'feature_text', 'label' => 'متن ویژگی']]],
                ['id' => '_nsm_rental_terms', 'label' => 'شرایط و قوانین اجاره', 'type' => 'wp_editor'],
            ],
            'restaurant' => [
                ['id' => '_nsm_resto_booking_type', 'label' => 'نوع رزرو', 'type' => 'select', 'options' => ['table' => 'میز', 'event' => 'رویداد ویژه']],
                ['id' => '_nsm_resto_capacity_per_table', 'label' => 'ظرفیت هر میز (نفر)', 'type' => 'number'],
                ['id' => '_nsm_resto_menu_items', 'label' => 'آیتم‌های منوی ویژه', 'type' => 'repeater', 'fields' => [['id' => 'item_name', 'label' => 'نام'], ['id' => 'item_description', 'label' => 'توضیحات'], ['id' => 'item_price', 'label' => 'قیمت'], ['id' => 'item_image', 'label' => 'تصویر', 'type' => 'media']]],
                ['id' => '_nsm_resto_chef_name', 'label' => 'نام سرآشپز', 'type' => 'text'],
                ['id' => '_nsm_resto_music_type', 'label' => 'نوع موسیقی', 'type' => 'text'],
            ],
            'legal' => [
                ['id' => '_nsm_legal_lawyer_name', 'label' => 'نام وکیل/مشاور', 'type' => 'text'],
                ['id' => '_nsm_legal_license_number', 'label' => 'شماره پروانه/مجوز', 'type' => 'text'],
                ['id' => '_nsm_legal_specialty', 'label' => 'حوزه‌های تخصصی', 'type' => 'repeater', 'fields' => [['id' => 'item', 'label' => 'تخصص']]],
                ['id' => '_nsm_legal_consult_type', 'label' => 'نوع مشاوره', 'type' => 'select', 'options' => ['in_person' => 'حضوری', 'phone' => 'تلفنی', 'online' => 'آنلاین']],
                ['id' => '_nsm_legal_office_address', 'label' => 'آدرس دفتر', 'type' => 'textarea'],
            ],
            'transport' => [
                ['id' => '_nsm_trans_vehicle_type', 'label' => 'نوع وسیله نقلیه', 'type' => 'text'],
                ['id' => '_nsm_trans_has_insurance', 'label' => 'شامل بیمه بار', 'type' => 'select', 'options' => ['no' => 'خیر', 'yes' => 'بله']],
                ['id' => '_nsm_trans_worker_count', 'label' => 'تعداد کارگر همراه', 'type' => 'number'],
                ['id' => '_nsm_trans_pricing_model', 'label' => 'مدل قیمت‌گذاری', 'type' => 'text'],
                ['id' => '_nsm_trans_service_area', 'label' => 'محدوده سرویس‌دهی', 'type' => 'textarea'],
            ],
            'pets' => [
                ['id' => '_nsm_pets_animal_type', 'label' => 'نوع حیوان', 'type' => 'text'],
                ['id' => '_nsm_pets_has_boarding', 'label' => 'امکان نگهداری شبانه‌روزی', 'type' => 'select', 'options' => ['no' => 'خیر', 'yes' => 'بله']],
                ['id' => '_nsm_pets_vet_present', 'label' => 'دامپزشک مستقر', 'type' => 'select', 'options' => ['no' => 'خیر', 'yes' => 'بله']],
                ['id' => '_nsm_pets_required_vaccines', 'label' => 'واکسن‌های الزامی', 'type' => 'repeater', 'fields' => [['id' => 'item', 'label' => 'واکسن']]],
                ['id' => '_nsm_pets_grooming_packages', 'label' => 'پکیج‌های آرایشی', 'type' => 'repeater', 'fields' => [['id' => 'package_name', 'label' => 'نام پکیج'], ['id' => 'package_price', 'label' => 'قیمت']]],
            ],
            'home' => [
                ['id' => '_nsm_home_service_type', 'label' => 'نوع خدمات', 'type' => 'select', 'options' => ['cleaning' => 'نظافت', 'gardening' => 'باغبانی', 'repairs' => 'تعمیرات']],
                ['id' => '_nsm_home_pricing_model', 'label' => 'مدل قیمت‌گذاری', 'type' => 'text'],
                ['id' => '_nsm_home_staff_count', 'label' => 'تعداد نیروی اعزامی', 'type' => 'number'],
                ['id' => '_nsm_home_cleaning_materials', 'label' => 'تامین مواد شوینده', 'type' => 'select', 'options' => ['client' => 'با مشتری', 'company' => 'با ما']],
                ['id' => '_nsm_home_is_insured', 'label' => 'تحت پوشش بیمه', 'type' => 'select', 'options' => ['no' => 'خیر', 'yes' => 'بله']],
            ],
            'creative' => [
                ['id' => '_nsm_creative_portfolio_url', 'label' => 'لینک نمونه کارها', 'type' => 'url'],
                ['id' => '_nsm_creative_delivery_format', 'label' => 'فرمت فایل تحویلی', 'type' => 'text'],
                ['id' => '_nsm_creative_revision_rounds', 'label' => 'تعداد دفعات بازبینی', 'type' => 'number'],
                ['id' => '_nsm_creative_software_used', 'label' => 'نرم‌افزارهای مورد استفاده', 'type' => 'repeater', 'fields' => [['id' => 'item', 'label' => 'نرم‌افزار']]],
                ['id' => '_nsm_creative_packages', 'label' => 'پکیج‌های خدمات', 'type' => 'repeater', 'fields' => [['id' => 'package_title', 'label' => 'عنوان'], ['id' => 'package_includes', 'label' => 'شامل', 'type' => 'textarea'], ['id' => 'package_price', 'label' => 'قیمت']]],
            ],
            'childcare' => [
                ['id' => '_nsm_child_age_group', 'label' => 'گروه سنی مناسب', 'type' => 'text'],
                ['id' => '_nsm_child_tutor_credentials', 'label' => 'سوابق مربی/پرستار', 'type' => 'textarea'],
                ['id' => '_nsm_child_has_meal', 'label' => 'شامل وعده غذایی', 'type' => 'select', 'options' => ['no' => 'خیر', 'yes' => 'بله']],
                ['id' => '_nsm_child_pickup_service', 'label' => 'دارای سرویس رفت و برگشت', 'type' => 'select', 'options' => ['no' => 'خیر', 'yes' => 'بله']],
                ['id' => '_nsm_child_curriculum', 'label' => 'برنامه آموزشی و فعالیت‌ها', 'type' => 'repeater', 'fields' => [['id' => 'activity_title', 'label' => 'عنوان فعالیت'], ['id' => 'activity_desc', 'label' => 'توضیحات', 'type' => 'textarea']]],
            ],
            'ceremonial' => [
                ['id' => '_nsm_cer_event_type', 'label' => 'نوع مراسم قابل پوشش', 'type' => 'text'],
                ['id' => '_nsm_cer_min_guests', 'label' => 'حداقل تعداد مهمانان', 'type' => 'number'],
                ['id' => '_nsm_cer_max_guests', 'label' => 'حداکثر تعداد مهمانان', 'type' => 'number'],
                ['id' => '_nsm_cer_menu_packages', 'label' => 'پکیج‌های منو', 'type' => 'repeater', 'fields' => [['id' => 'menu_title', 'label' => 'عنوان منو'], ['id' => 'menu_items', 'label' => 'آیتم‌ها', 'type' => 'textarea'], ['id' => 'price_per_person', 'label' => 'قیمت هر نفر']]],
                ['id' => '_nsm_cer_extra_services', 'label' => 'خدمات جانبی', 'type' => 'repeater', 'fields' => [['id' => 'item', 'label' => 'خدمت']]],
            ],
        ];
    }
}


/**
 * کلاس مدیریت درون‌ریزی و برون‌بری
 */
class NSM_Import_Export_Handler {
    
    public function export_single($post_id) {
        $data = $this->get_service_data($post_id);
        $filename = 'service-' . $post_id . '-' . date('Y-m-d') . '.json';
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename=' . $filename);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public function import_single($post_id, $file) {
        $content = file_get_contents($file['tmp_name']);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || empty($data)) {
            return false;
        }
        
        $this->set_service_data($post_id, $data);
        return true;
    }

    public function export_all() {
        $services = [];
        $query = new WP_Query(['post_type' => 'service', 'posts_per_page' => -1, 'post_status' => 'any']);
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $services[] = $this->get_service_data(get_the_ID());
            }
        }
        wp_reset_postdata();

        $filename = 'all-services-' . date('Y-m-d') . '.json';
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename=' . $filename);
        echo json_encode($services, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public function import_all($file) {
        $content = file_get_contents($file['tmp_name']);
        $services = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($services)) {
            return false;
        }
        
        $created_count = 0;
        $updated_count = 0;

        foreach ($services as $service_data) {
            $existing_post = get_page_by_path($service_data['post']['post_name'], OBJECT, 'service');
            
            $post_id = 0;
            if ($existing_post) {
                // Update existing
                $post_id = $existing_post->ID;
                $updated_count++;
            } else {
                // Create new
                $post_id = wp_insert_post([
                    'post_title' => $service_data['post']['post_title'],
                    'post_name' => $service_data['post']['post_name'],
                    'post_type' => 'service',
                    'post_status' => 'publish',
                ]);
                $created_count++;
            }
            
            if ($post_id && !is_wp_error($post_id)) {
                $this->set_service_data($post_id, $service_data);
            }
        }
        return ['created' => $created_count, 'updated' => $updated_count];
    }

    private function get_service_data($post_id) {
        $post = get_post($post_id);
        $all_meta = get_post_meta($post_id);
        $service_meta = [];
        foreach ($all_meta as $key => $value) {
            if (strpos($key, '_nsm_') === 0) {
                $service_meta[$key] = maybe_unserialize($value[0]);
            }
        }

        return [
            'post' => [
                'post_title' => $post->post_title,
                'post_name' => $post->post_name,
            ],
            'meta' => $service_meta,
            'taxonomies' => [
                'service_category' => wp_get_object_terms($post_id, 'service_category', ['fields' => 'slugs']),
                'service_keyword' => wp_get_object_terms($post_id, 'service_keyword', ['fields' => 'slugs']),
            ],
            'thumbnail_url' => get_the_post_thumbnail_url($post_id, 'full'),
        ];
    }

    private function set_service_data($post_id, $data) {
        // Update post meta
        if (isset($data['meta']) && is_array($data['meta'])) {
            foreach ($data['meta'] as $key => $value) {
                update_post_meta($post_id, $key, $value);
            }
        }

        // Set taxonomies
        if (isset($data['taxonomies']) && is_array($data['taxonomies'])) {
            foreach ($data['taxonomies'] as $tax => $terms) {
                wp_set_object_terms($post_id, $terms, $tax, false);
            }
        }
        
        // Set thumbnail
        if (!empty($data['thumbnail_url'])) {
            $this->set_thumbnail_from_url($post_id, $data['thumbnail_url']);
        }
    }

    private function set_thumbnail_from_url($post_id, $image_url) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $tmp = download_url($image_url);
        if (is_wp_error($tmp)) {
            return false;
        }
        
        $file_array = [];
        preg_match('/[^\?]+\.(jpg|jpe|jpeg|gif|png)/i', $image_url, $matches);
        $file_array['name'] = basename($matches[0]);
        $file_array['tmp_name'] = $tmp;
        
        $attach_id = media_handle_sideload($file_array, $post_id);

        if (is_wp_error($attach_id)) {
            @unlink($file_array['tmp_name']);
            return false;
        }
        
        set_post_thumbnail($post_id, $attach_id);
    }
}
