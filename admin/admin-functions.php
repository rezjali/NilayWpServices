<?php
/**
 * فایل توابع و کلاس‌های مربوط به بخش مدیریت (Admin)
 *
 * @package Nilay_Service_Manager/Admin
 * @version 4.5.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * کلاس نصب‌کننده افزونه
 */
class NSM_Install {
    public static function activate() {
        NSM_Post_Types::register_post_types();
        NSM_Post_Types::register_taxonomies();
        flush_rewrite_rules();
        
        // Add default terminology options on activation
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
            <?php if ($active_tab == 'tools') { do_settings_sections( 'nsm_settings_tools' ); } ?>
             <?php if (isset($_GET['cache-cleared']) && $_GET['cache-cleared'] == 'true') : ?>
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
            'main_settings' => 'تنظیمات اصلی', 'active_payment_gateway' => 'درگاه پرداخت فعال', 'active_sms_provider' => 'سامانه پیامکی فعال',
            'gateway_inactive' => 'غیرفعال', 'gateway_zarinpal' => 'زرین پال', 'gateway_zibal' => 'زیبال',
            'sms_inactive' => 'غیرفعال', 'sms_kavenegar' => 'کاوه نگار', 'sms_farazsms' => 'فراز اس ام اس',
            // Payment Settings
            'zarinpal_settings' => 'تنظیمات درگاه پرداخت زرین‌پال', 'merchant_code' => 'کد مرچنت', 'zarinpal_desc' => 'کد مرچنت ۳۶ کاراکتری خود را از پنل زرین‌پال دریافت و در این قسمت وارد کنید.',
            'zibal_settings' => 'تنظیمات درگاه پرداخت زیبال', 'zibal_desc' => 'کد مرچنت خود را از پنل زیبال دریافت و در این قسمت وارد کنید.',
            'test_connection' => 'تست اتصال', 'test_button' => 'ارسال درخواست تست', 'save_before_test' => 'برای تست، ابتدا تنظیمات را ذخیره کنید.',
            // SMS Settings
            'kavenegar_settings' => 'تنظیمات سامانه پیامکی کاوه‌نگار', 'api_key' => 'کلید API', 'kavenegar_desc' => 'کلید API خود را از پنل کاوه‌نگار دریافت و در این قسمت وارد کنید.',
            'test_mobile_number' => 'شماره موبایل تست', 'test_mobile_desc' => 'یک شماره موبایل معتبر برای ارسال پیامک تستی وارد کنید.', 'test_send' => 'تست ارسال',
            'farazsms_settings' => 'تنظیمات سامانه پیامکی فراز اس‌ام‌اس', 'farazsms_desc' => 'کلید API خود را از پنل فراز اس‌ام‌اس دریافت و در این قسمت وارد کنید.',
            // Notifications Settings
            'sms_patterns' => 'الگوهای اطلاع‌رسانی پیامکی', 'admin_free_reg' => 'ثبت‌نام رایگان (برای مدیر)', 'user_free_reg' => 'ثبت‌نام رایگان (برای کاربر)',
            'admin_paid_reg' => 'خرید موفق (برای مدیر)', 'user_paid_reg' => 'خرید موفق (برای کاربر)', 'patterns_help_title' => 'راهنمای الگوها',
            'patterns_help_desc' => 'می‌توانید از الگوهای زیر در متن پیامک‌ها استفاده کنید:',
            // Tools
            'utility_tools' => 'ابزارهای کاربردی', 'clear_cache' => 'پاک کردن کش', 'clear_cache_desc' => 'اگر تغییرات در سایت اعمال نمی‌شوند، از این دکمه برای پاک کردن کش داخلی وردپرس استفاده کنید.',
            'clear_cache_button' => 'پاک کردن کش افزونه', 'cache_cleared_success' => 'کش افزونه و وردپرس با موفقیت پاک شد.',
            // Meta Box
            'service_details' => 'جزئیات خدمت', 'select_service_type' => 'نوع خدمت را انتخاب کنید:', 'choose' => 'انتخاب کنید...', 'premium_content_label' => 'محتوای ویژه',
            'premium_content_desc' => 'با فعال کردن این گزینه، این فیلد فقط به کاربرانی که خدمت را خریداری کرده‌اند نمایش داده می‌شود.', 'add_new_item' => 'افزودن آیتم جدید',
            // Frontend
            'key_info' => 'اطلاعات کلیدی', 'service_type' => 'نوع خدمت', 'status' => 'وضعیت', 'payment_model' => 'مدل فروش', 'featured' => 'ویژه',
            'start_date' => 'تاریخ برگزاری/شروع', 'start_time' => 'ساعت', 'status_available' => 'در دسترس', 'status_full' => 'تکمیل ظرفیت', 'status_ended' => 'پایان یافته',
            'payment_free' => 'رایگان', 'payment_paid' => 'پولی', 'is_featured_yes' => 'بله', 'is_featured_no' => 'خیر',
            'registration_fee' => 'هزینه ثبت نام', 'toman' => 'تومان', 'buy_now' => 'خرید و مشاهده', 'payment_and_reg' => 'پرداخت و ثبت نام',
            'fullname' => 'نام و نام خانوادگی', 'mobile_number' => 'شماره موبایل', 'free_reg_button' => 'ثبت نام رایگان', 'reg_success' => 'ثبت‌نام شما با موفقیت انجام شد.',
            'more_details' => 'توضیحات تکمیلی', 'gallery' => 'گالری تصاویر', 'no_service_in_cat' => 'هیچ خدمتی در این دسته یافت نشد.',
            'all_cats' => 'همه', 'view_details_btn' => 'مشاهده جزئیات',
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
        add_settings_field( 'kavenegar_test_mobile', nsm_get_string('test_mobile_number', 'شماره موبایل تست'), [self::class, 'render_text_field'], 'nsm_settings_kavenegar', 'nsm_kavenegar_section', ['id' => 'kavenegar_test_mobile', 'group' => 'nsm_sms_options', 'desc' => nsm_get_string('test_mobile_desc', 'یک شماره موبایل معتبر برای ارسال پیامک تستی وارد کنید.')] );
        add_settings_field( 'kavenegar_test', nsm_get_string('test_send', 'تست ارسال'), [self::class, 'render_test_button'], 'nsm_settings_kavenegar', 'nsm_kavenegar_section', ['gateway' => 'kavenegar'] );

        add_settings_section( 'nsm_farazsms_section', nsm_get_string('farazsms_settings', 'تنظیمات سامانه پیامکی فراز اس‌ام‌اس'), '__return_false', 'nsm_settings_farazsms' );
        add_settings_field( 'farazsms_api_key', nsm_get_string('api_key', 'کلید API'), [self::class, 'render_text_field'], 'nsm_settings_farazsms', 'nsm_farazsms_section', ['id' => 'farazsms_api_key', 'group' => 'nsm_sms_options', 'desc' => nsm_get_string('farazsms_desc', 'کلید API خود را از پنل فراز اس‌ام‌اس دریافت و در این قسمت وارد کنید.')] );
        add_settings_field( 'farazsms_test_mobile', nsm_get_string('test_mobile_number', 'شماره موبایل تست'), [self::class, 'render_text_field'], 'nsm_settings_farazsms', 'nsm_farazsms_section', ['id' => 'farazsms_test_mobile', 'group' => 'nsm_sms_options', 'desc' => nsm_get_string('test_mobile_desc', 'یک شماره موبایل معتبر برای ارسال پیامک تستی وارد کنید.')] );
        add_settings_field( 'farazsms_test', nsm_get_string('test_send', 'تست ارسال'), [self::class, 'render_test_button'], 'nsm_settings_farazsms', 'nsm_farazsms_section', ['gateway' => 'farazsms'] );
        
        // Notifications Settings
        register_setting( 'nsm_settings_group_notifications', 'nsm_notification_options', [self::class, 'sanitize_textarea_fields'] );
        add_settings_section( 'nsm_notifications_section', nsm_get_string('sms_patterns', 'الگوهای اطلاع‌رسانی پیامکی'), '__return_false', 'nsm_settings_notifications' );
        add_settings_field( 'admin_free_reg', nsm_get_string('admin_free_reg', 'ثبت‌نام رایگان (برای مدیر)'), [self::class, 'render_textarea_field'], 'nsm_settings_notifications', 'nsm_notifications_section', ['id' => 'admin_free_reg', 'group' => 'nsm_notification_options'] );
        add_settings_field( 'user_free_reg', nsm_get_string('user_free_reg', 'ثبت‌نام رایگان (برای کاربر)'), [self::class, 'render_textarea_field'], 'nsm_settings_notifications', 'nsm_notifications_section', ['id' => 'user_free_reg', 'group' => 'nsm_notification_options'] );
        add_settings_field( 'admin_paid_reg', nsm_get_string('admin_paid_reg', 'خرید موفق (برای مدیر)'), [self::class, 'render_textarea_field'], 'nsm_settings_notifications', 'nsm_notifications_section', ['id' => 'admin_paid_reg', 'group' => 'nsm_notification_options'] );
        add_settings_field( 'user_paid_reg', nsm_get_string('user_paid_reg', 'خرید موفق (برای کاربر)'), [self::class, 'render_textarea_field'], 'nsm_settings_notifications', 'nsm_notifications_section', ['id' => 'user_paid_reg', 'group' => 'nsm_notification_options'] );
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
    }

    // Sanitize callbacks
    public static function sanitize_general_options($input) {
        $output = [];
        $output['active_payment_gateway'] = isset($input['active_payment_gateway']) ? sanitize_key($input['active_payment_gateway']) : 'none';
        $output['active_sms_provider'] = isset($input['active_sms_provider']) ? sanitize_key($input['active_sms_provider']) : 'none';
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
        echo '<p class="description">' . nsm_get_string('patterns_help_desc', 'می‌توانید از الگوهای زیر در متن پیامک‌ها استفاده کنید:') . '</p>';
        echo '<code>[service_name]</code>, <code>[user_name]</code>, <code>[user_mobile]</code>, <code>[price]</code>, <code>[date]</code>, <code>[time]</code>, <code>[transaction_id]</code>';
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

            <h2>استفاده از شورت‌کدها</h2>
            <p>می‌توانید از شورت‌کدهای زیر برای نمایش خدمات در هر بخشی از سایت (مانند ویرایشگر کلاسیک، ابزارک‌ها یا سایر صفحه‌سازها) استفاده کنید.</p>

            <h3>۱. شورت‌کد گرید خدمات: <code>[services_grid]</code></h3>
            <p>این شورت‌کد خدمات را به صورت یک گرید (شبکه) نمایش می‌دهد.</p>
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

            <h3>۲. شورت‌کد کاروسل خدمات: <code>[services_carousel]</code></h3>
            <p>این شورت‌کد خدمات را به صورت یک اسلایدر (کاروسل) نمایش می‌دهد و تمام پارامترهای <code>[services_grid]</code> را نیز می‌پذیرد.</p>
            <table class="shortcode-table">
                 <thead><tr><th>پارامتر</th><th>توضیح</th><th>مقادیر مجاز</th><th>مثال</th></tr></thead>
                <tbody>
                    <tr><td><code>autoplay</code></td><td>پخش خودکار اسلایدر</td><td><code>true</code>, <code>false</code> (پیش‌فرض: false)</td><td><code>[services_carousel autoplay="true"]</code></td></tr>
                    <tr><td><code>speed</code></td><td>سرعت پخش خودکار به میلی‌ثانیه</td><td>یک عدد (پیش‌فرض: 3000)</td><td><code>[services_carousel speed="5000"]</code></td></tr>
                </tbody>
            </table>

            <h3>۳. شورت‌کد فیلتر و گرید خدمات: <code>[service_filter_grid]</code></h3>
            <p>یک ابزارک کامل برای فیلتر ایجکس خدمات بر اساس دسته‌بندی‌ها ایجاد می‌کند. این شورت‌کد تمام پارامترهای <code>[services_grid]</code> را برای نمایش اولیه می‌پذیرد.</p>
            <p>مثال: <code>[service_filter_grid count="12" columns="4"]</code></p>

            <h3>۴. شورت‌کد کارت تکی خدمت: <code>[single_service]</code></h3>
            <p>این شورت‌کد فقط یک خدمت خاص را به صورت کارت نمایش می‌دهد و تمام پارامترهای سفارشی‌سازی کارت (مانند <code>show_image</code>) را می‌پذیرد.</p>
             <table class="shortcode-table">
                 <thead><tr><th>پارامتر</th><th>توضیح</th><th>مثال</th></tr></thead>
                <tbody>
                    <tr><td><code>id</code></td><td><strong>(الزامی)</strong> شناسه‌ی (ID) خدمتی که می‌خواهید نمایش دهید.</td><td><code>[single_service id="123"]</code></td></tr>
                </tbody>
            </table>
            
            <h3>۵. شورت‌کد نمایش یک فیلد خاص: <code>[service_meta]</code></h3>
            <p>برای نمایش مقدار یک فیلد خاص از یک خدمت در هر کجای سایت.</p>
             <table class="shortcode-table">
                 <thead><tr><th>پارامتر</th><th>توضیح</th><th>مثال</th></tr></thead>
                <tbody>
                    <tr><td><code>id</code></td><td><strong>(الزامی)</strong> شناسه‌ی (ID) خدمت.</td><td rowspan="2"><code>[service_meta id="123" meta_key="_nsm_price"]</code></td></tr>
                    <tr><td><code>meta_key</code></td><td><strong>(الزامی)</strong> کلید متای فیلد مورد نظر.</td></tr>
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
    public static function add_meta_boxes() { add_meta_box('nsm_service_details', nsm_get_string('service_details', 'جزئیات خدمت'), [self::class, 'render_meta_box'], 'service', 'normal', 'high'); }
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
    
    private static function render_field($post_id, $field) {
        $value = get_post_meta($post_id, $field['id'], true);
        $extra_class = $field['extra_class'] ?? '';
        echo '<div class="nsm-meta-box-field ' . esc_attr($extra_class) . '">';
        
        echo '<label for="' . esc_attr($field['id']) . '">' . esc_html($field['label']) . '</label>';
        
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
            $value = isset($item_values[$sub_field['id']]) ? esc_attr($item_values[$sub_field['id']]) : '';
            $html .= '<div class="nsm-meta-box-field">';
            $html .= '<label>' . esc_html($sub_field['label']) . '</label>';
            
            $sub_field_type = $sub_field['type'] ?? 'text';

            switch ($sub_field_type) {
                case 'media':
                    $html .= '<input type="text" name="' . $name . '" value="' . $value . '" style="width: 70%;" readonly>';
                    $html .= ' <button type="button" class="button nsm-upload-button">' . nsm_get_string('media_modal_button', 'انتخاب') . '</button>';
                    $html .= '<div class="nsm-image-preview">' . ($value ? '<img src="'.esc_url($value).'" style="max-width:100px; margin-top: 5px;"/>' : '') . '</div>';
                    break;
                case 'textarea':
                    $html .= '<textarea name="' . $name . '" rows="3">' . esc_textarea($value) . '</textarea>';
                    break;
                case 'text':
                default:
                    $html .= '<input type="text" name="' . $name . '" value="' . $value . '">';
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
        
        foreach (self::$meta_fields as $group) {
            foreach ($group as $field) {
                if (isset($_POST[$field['id']])) {
                    $value = $_POST[$field['id']];
                    if ($field['type'] === 'repeater' && is_array($value)) {
                        $sanitized_repeater = [];
                        foreach ($value as $item) {
                            $sanitized_item = [];
                            if (is_array($item)) {
                                foreach($item as $key => $sub_value) {
                                    $sanitized_item[sanitize_key($key)] = wp_kses_post($sub_value); // Allow some HTML
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
                    'layout-4' => 'قالب ۴: تیره و شیک', 'layout-5' => 'قالب ۵: فرود و اقدام',
                    'layout-6' => 'قالب ۶: تیره و مینیمال', 'layout-7' => 'قالب ۷: تیره و گرادیانت',
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
