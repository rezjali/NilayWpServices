<?php
/**
 * فایل توابع و کلاس‌های مربوط به بخش عمومی (Public/Frontend)
 *
 * @package Nilay_Service_Manager/Public
 * @version 4.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * کلاس مدیریت شورت‌کدها
 */
class NSM_Shortcodes {

    public static function register_shortcodes() {
        add_shortcode('services_grid', [self::class, 'render_services_grid']);
        add_shortcode('services_carousel', [self::class, 'render_services_grid']); // Uses the same render function
        add_shortcode('service_filter_grid', [self::class, 'render_service_filter_grid']);
        add_shortcode('single_service', [self::class, 'render_single_service']);
        add_shortcode('service_meta', [self::class, 'render_service_meta']);
    }

    public static function render_services_grid($atts, $content = null, $tag = 'services_grid') {
        $atts = shortcode_atts([
            'count' => 9,
            'columns' => 3,
            'category' => '',
            'keyword' => '',
            'ids' => '',
            'orderby' => 'date',
            'order' => 'DESC',
            'show_image' => 'yes',
            'show_excerpt' => 'yes',
            'excerpt_length' => 15,
            'button_text' => nsm_get_string('view_details_btn', 'مشاهده جزئیات'),
            // Carousel specific
            'autoplay' => 'false',
            'speed' => 3000,
        ], $atts, $tag);

        $args = [
            'post_type' => 'service',
            'posts_per_page' => intval($atts['count']),
            'orderby' => sanitize_key($atts['orderby']),
            'order' => sanitize_key($atts['order']),
            'post_status' => 'publish',
        ];

        if (!empty($atts['ids'])) {
            $args['post__in'] = array_map('intval', explode(',', $atts['ids']));
        }

        $tax_query = [];
        if (!empty($atts['category'])) {
            $tax_query[] = ['taxonomy' => 'service_category', 'field' => 'slug', 'terms' => sanitize_text_field($atts['category'])];
        }
        if (!empty($atts['keyword'])) {
            $tax_query[] = ['taxonomy' => 'service_keyword', 'field' => 'slug', 'terms' => sanitize_text_field($atts['keyword'])];
        }
        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }

        $query = new WP_Query($args);
        ob_start();

        if ($query->have_posts()) {
            $is_carousel = ($tag === 'services_carousel');
            $container_classes = $is_carousel ? 'swiper nsm-services-carousel' : 'nsm-services-grid nsm-grid-desktop-'.esc_attr($atts['columns']).' nsm-grid-tablet-2 nsm-grid-mobile-1';
            
            echo '<div class="' . $container_classes . '" ' . ($is_carousel ? 'data-autoplay="'.esc_attr($atts['autoplay']).'" data-speed="'.esc_attr($atts['speed']).'"' : '') . '>';
            echo $is_carousel ? '<div class="swiper-wrapper">' : '';

            while ($query->have_posts()) {
                $query->the_post();
                echo $is_carousel ? '<div class="swiper-slide">' : '';
                self::render_service_card($atts);
                echo $is_carousel ? '</div>' : '';
            }

            echo $is_carousel ? '</div><div class="swiper-pagination"></div></div>' : '</div>';
            
            if ($is_carousel) {
                wp_add_inline_script('swiper-js', "
                    document.addEventListener('DOMContentLoaded', function () {
                        document.querySelectorAll('.nsm-services-carousel').forEach(function(carousel) {
                            new Swiper(carousel, {
                                slidesPerView: 1,
                                spaceBetween: 30,
                                pagination: { el: '.swiper-pagination', clickable: true },
                                autoplay: carousel.dataset.autoplay === 'true' ? { delay: parseInt(carousel.dataset.speed) } : false,
                                breakpoints: { 640: { slidesPerView: 2 }, 1024: { slidesPerView: " . esc_js($atts['columns']) . " } }
                            });
                        });
                    });
                ");
            }

        } else {
            echo '<p>' . nsm_get_string('no_service_found', 'هیچ خدمتی یافت نشد.') . '</p>';
        }

        wp_reset_postdata();
        return ob_get_clean();
    }

    public static function render_service_filter_grid($atts) {
        $categories = get_terms(['taxonomy' => 'service_category', 'hide_empty' => true]);
        ob_start();
        ?>
        <div class="nsm-filter-grid-wrapper">
            <?php if (!is_wp_error($categories) && !empty($categories)): ?>
            <div class="nsm-filter-buttons">
                <button class="active" data-category="all"><?php echo nsm_get_string('all_cats', 'همه'); ?></button>
                <?php foreach ($categories as $category): ?>
                    <button data-category="<?php echo esc_attr($category->slug); ?>"><?php echo esc_html($category->name); ?></button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="nsm-filter-results">
                <?php echo self::render_services_grid($atts); ?>
            </div>
        </div>
        <?php
        wp_add_inline_script('jquery-core', "
            jQuery(document).ready(function($){
                $('.nsm-filter-buttons button').on('click', function(){
                    var button = $(this);
                    var category = button.data('category');
                    button.addClass('active').siblings().removeClass('active');
                    
                    $.ajax({
                        url: '" . admin_url('admin-ajax.php') . "',
                        type: 'POST',
                        data: {
                            action: 'nsm_filter_services',
                            category: category,
                            atts: " . json_encode($atts) . "
                        },
                        beforeSend: function(){
                            $('.nsm-filter-results').css('opacity', 0.5);
                        },
                        success: function(response){
                             $('.nsm-filter-results').html(response).css('opacity', 1);
                        }
                    });
                });
            });
        ");
        return ob_get_clean();
    }

    public static function filter_services_ajax_handler() {
        $category = sanitize_text_field($_POST['category']);
        $atts = $_POST['atts'];
        if ($category !== 'all') {
            $atts['category'] = $category;
        } else {
            unset($atts['category']);
        }
        echo self::render_services_grid($atts);
        wp_die();
    }
    
    public static function render_single_service($atts) {
        $atts = shortcode_atts(['id' => 0], $atts, 'single_service');
        $service_id = intval($atts['id']);
        if (!$service_id || get_post_type($service_id) !== 'service') return '';
        
        global $post;
        $original_post = $post;
        $post = get_post($service_id);
        setup_postdata($post);
        
        ob_start();
        self::render_service_card($atts);
        $output = ob_get_clean();
        
        wp_reset_postdata();
        $post = $original_post;
        if($post) setup_postdata($post);
        
        return $output;
    }

    public static function render_service_meta($atts) {
        $atts = shortcode_atts(['id' => 0, 'meta_key' => ''], $atts, 'service_meta');
        $service_id = intval($atts['id']);
        $meta_key = sanitize_text_field($atts['meta_key']);
        if (!$service_id || !$meta_key) return '';
        return esc_html(get_post_meta($service_id, $meta_key, true));
    }

    private static function render_service_card($atts) {
        $post_id = get_the_ID();
        $thumb_url = get_the_post_thumbnail_url($post_id, 'medium_large') ?: 'https://placehold.co/600x400/e0e7ff/4f46e5?text=Nilay';
        $price = get_post_meta($post_id, '_nsm_price', true);
        $payment_model = get_post_meta($post_id, '_nsm_payment_model', true);
        ?>
        <div class="nsm-service-card">
            <?php if ($atts['show_image'] === 'yes'): ?>
            <div class="nsm-service-card-thumb">
                <a href="<?php the_permalink(); ?>">
                    <img src="<?php echo esc_url($thumb_url); ?>" alt="<?php the_title_attribute(); ?>">
                    <div class="nsm-overlay"></div>
                </a>
                <?php if ($payment_model === 'paid' && $price): ?>
                    <span class="nsm-service-card-badge"><?php echo number_format($price) . ' ' . nsm_get_string('toman', 'تومان'); ?></span>
                <?php else: ?>
                     <span class="nsm-service-card-badge"><?php echo nsm_get_string('payment_free', 'رایگان'); ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="nsm-service-card-content">
                <h3 class="nsm-service-card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                <?php if ($atts['show_excerpt'] === 'yes'): ?>
                <div class="nsm-service-card-excerpt">
                    <?php echo wp_trim_words(get_post_meta($post_id, '_nsm_service_summary', true), intval($atts['excerpt_length']), '...'); ?>
                </div>
                <?php endif; ?>
                <a href="<?php the_permalink(); ?>" class="nsm-service-card-button"><?php echo esc_html($atts['button_text']); ?></a>
            </div>
        </div>
        <?php
    }
}


/**
 * کلاس مدیریت ارسال فرم ثبت نام
 */
class NSM_Payment_Handler {
    public static function handle_payment_request() {
        if ( !isset($_POST['nsm_payment_nonce']) || !wp_verify_nonce($_POST['nsm_payment_nonce'], 'nsm_submit_payment') ) {
            return;
        }
        
        $service_id = intval($_POST['service_id']);
        $user_name = sanitize_text_field($_POST['user_name']);
        $user_mobile = sanitize_text_field($_POST['user_mobile']);

        // Basic validation
        if (!$service_id || !$user_name || !preg_match('/^09[0-9]{9}$/', $user_mobile)) {
            wp_die(nsm_get_string('error_invalid_info', 'اطلاعات وارد شده نامعتبر است. لطفا به صفحه قبل بازگردید.'));
        }

        $user_data = [
            'user_name' => $user_name,
            'user_mobile' => $user_mobile,
        ];
        
        $payment_model = get_post_meta($service_id, '_nsm_payment_model', true);

        if ($payment_model === 'paid') {
            NSM_Gateways::process_payment($service_id, $user_data);
        } else {
            // Handle free registration
            $transient_data = [
                'service_id'  => $service_id,
                'user_name'   => $user_name,
                'user_mobile' => $user_mobile,
            ];
            NSM_Gateways::complete_registration($transient_data);
            wp_redirect(add_query_arg('nsm_reg_status', 'success', get_permalink($service_id)));
            exit;
        }
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
            $template_mode = get_post_meta($post_id, '_nsm_template_mode', true) ?: 'light';
            
            ob_start();

            // نمایش پیام‌های بازگشتی از درگاه پرداخت یا ثبت نام رایگان
            if (isset($_GET['nsm_payment_status'])) {
                if ($_GET['nsm_payment_status'] === 'success') {
                    $track_id = sanitize_text_field($_GET['track_id'] ?? '');
                    echo '<div class="nsm-payment-notice success">پرداخت شما با موفقیت انجام شد. کد رهگیری: ' . esc_html($track_id) . '</div>';
                } else {
                    $error_msg = urldecode(sanitize_text_field($_GET['nsm_error'] ?? 'خطای نامشخص'));
                    echo '<div class="nsm-payment-notice error">پرداخت ناموفق بود. خطا: ' . esc_html($error_msg) . '</div>';
                }
            } elseif (isset($_GET['nsm_reg_status']) && $_GET['nsm_reg_status'] === 'success') {
                echo '<div class="nsm-payment-notice success">' . nsm_get_string('reg_success', 'ثبت‌نام شما با موفقیت انجام شد.') . '</div>';
            }


            // رندر کردن قالب صفحه
            switch ($template) {
                case 'layout-2': echo self::render_template_two($post_id, $template_mode); break;
                case 'layout-3': echo self::render_template_three($post_id, $template_mode); break;
                case 'layout-4': echo self::render_template_four($post_id, $template_mode); break;
                case 'layout-5': echo self::render_template_five($post_id, $template_mode); break;
                case 'layout-6': echo self::render_template_six($post_id, $template_mode); break;
                case 'layout-7': echo self::render_template_seven($post_id, $template_mode); break;
                case 'layout-1': default: echo self::render_template_one($post_id, $template_mode); break;
            }

            return ob_get_clean();
        }
        return $content;
    }

    private static function get_wrapper_class($layout_class, $template_mode) {
        $mode_class = ($template_mode === 'dark') ? 'nsm-mode-dark' : 'nsm-mode-light';
        return "nsm-single-service-wrapper {$layout_class} {$mode_class}";
    }

    // --- TEMPLATE 1: MODERN PROFESSIONAL ---
    private static function render_template_one($post_id, $template_mode) {
        $service_type = get_post_meta($post_id, '_nsm_service_type', true);
        $thumb_url = get_the_post_thumbnail_url($post_id, 'full') ?: 'https://placehold.co/1200x800/e0e7ff/4f46e5?text=Nilay+Service';
        ob_start();
        ?>
        <div class="<?php echo self::get_wrapper_class('nsm-layout-1', $template_mode); ?>">
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
                            <?php self::render_payment_box($post_id, $service_type); ?>
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
    
    // --- TEMPLATE 2: IMMERSIVE GALLERY ---
    private static function render_template_two($post_id, $template_mode) {
        $service_type = get_post_meta($post_id, '_nsm_service_type', true);
        $gallery = get_post_meta($post_id, '_nsm_gallery_images', true);
        $thumb_url = get_the_post_thumbnail_url($post_id, 'full');
        ob_start();
        ?>
        <div class="<?php echo self::get_wrapper_class('nsm-layout-2', $template_mode); ?>">
            <div class="nsm-hero-gallery swiper">
                <div class="swiper-wrapper">
                    <?php if ($thumb_url) echo '<div class="swiper-slide" style="background-image: url('.esc_url($thumb_url).');"></div>'; ?>
                    <?php if ($gallery && is_array($gallery)): foreach ($gallery as $item): if(!empty($item['image'])): ?>
                        <div class="swiper-slide" style="background-image: url('<?php echo esc_url($item['image']); ?>');"></div>
                    <?php endif; endforeach; endif; ?>
                </div>
                 <div class="swiper-pagination"></div>
            </div>
            <div class="nsm-container">
                <div class="nsm-main-layout">
                     <aside class="nsm-sidebar">
                        <div class="nsm-sidebar-box">
                            <?php self::render_payment_box($post_id, $service_type); ?>
                        </div>
                    </aside>
                    <main class="nsm-main-content">
                        <div class="nsm-title-card">
                             <h1><?php the_title(); ?></h1>
                             <?php self::render_sidebar_meta($post_id, $service_type, false); ?>
                        </div>
                        <?php self::render_main_content_by_type($post_id, $service_type); ?>
                    </main>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    // --- TEMPLATE 3: HERO FOCUS ---
    private static function render_template_three($post_id, $template_mode) {
        $service_type = get_post_meta($post_id, '_nsm_service_type', true);
        $thumb_url = get_the_post_thumbnail_url($post_id, 'full') ?: 'https://placehold.co/1200x800/e0e7ff/4f46e5?text=Nilay+Service';
        ob_start();
        ?>
        <div class="<?php echo self::get_wrapper_class('nsm-layout-3', $template_mode); ?>">
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
                            <?php self::render_payment_box($post_id, $service_type); ?>
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
    
    // --- TEMPLATE 4: SLEEK ---
    private static function render_template_four($post_id, $template_mode) {
        $service_type = get_post_meta($post_id, '_nsm_service_type', true);
        $thumb_url = get_the_post_thumbnail_url($post_id, 'full') ?: 'https://placehold.co/1200x800/374151/d1d5db?text=Nilay+Service';
        ob_start();
        ?>
        <div class="<?php echo self::get_wrapper_class('nsm-layout-4', $template_mode); ?>">
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
                            <?php self::render_payment_box($post_id, $service_type); ?>
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
    
    // --- TEMPLATE 5: ACTION-ORIENTED LANDING ---
    private static function render_template_five($post_id, $template_mode) {
        $service_type = get_post_meta($post_id, '_nsm_service_type', true);
        $thumb_url = get_the_post_thumbnail_url($post_id, 'full');
        ob_start();
        ?>
        <div class="<?php echo self::get_wrapper_class('nsm-layout-5', $template_mode); ?>">
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
                            <?php self::render_payment_box($post_id, $service_type); ?>
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

    // --- TEMPLATE 6: MINIMALIST ---
    private static function render_template_six($post_id, $template_mode) {
        $service_type = get_post_meta($post_id, '_nsm_service_type', true);
        $thumb_url = get_the_post_thumbnail_url($post_id, 'full') ?: 'https://placehold.co/1600x800/1f2937/9ca3af?text=Nilay+Service';
        ob_start();
        ?>
        <div class="<?php echo self::get_wrapper_class('nsm-layout-6', $template_mode); ?>">
            <div class="nsm-container">
                <div class="nsm-hero-image" style="background-image: url('<?php echo esc_url($thumb_url); ?>');"></div>
                <div class="nsm-main-layout">
                    <main class="nsm-main-content">
                        <h1><?php the_title(); ?></h1>
                        <p class="service-summary"><?php echo esc_html(get_post_meta($post_id, '_nsm_service_summary', true)); ?></p>
                        <?php self::render_main_content_by_type($post_id, $service_type); ?>
                    </main>
                    <aside class="nsm-sidebar">
                        <div class="nsm-sidebar-box">
                            <?php self::render_payment_box($post_id, $service_type); ?>
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

    // --- TEMPLATE 7: GRADIENT ---
    private static function render_template_seven($post_id, $template_mode) {
        $service_type = get_post_meta($post_id, '_nsm_service_type', true);
        ob_start();
        ?>
        <div class="<?php echo self::get_wrapper_class('nsm-layout-7', $template_mode); ?>">
            <div class="nsm-container">
                <section class="nsm-hero-content">
                    <h1><?php the_title(); ?></h1>
                    <p class="service-summary"><?php echo esc_html(get_post_meta($post_id, '_nsm_service_summary', true)); ?></p>
                </section>
                <div class="nsm-main-layout">
                    <main class="nsm-main-content">
                        <?php self::render_main_content_by_type($post_id, $service_type); ?>
                    </main>
                    <aside class="nsm-sidebar">
                        <div class="nsm-sidebar-box">
                            <?php self::render_payment_box($post_id, $service_type); ?>
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
        self::render_content_section(nsm_get_string('more_details', 'توضیحات تکمیلی'), $details);

        switch ($service_type) {
            case 'educational': self::render_educational_content($post_id); break;
            case 'events': self::render_events_content($post_id); break;
            case 'consulting': self::render_consulting_content($post_id); break;
            case 'tourism': self::render_tourism_content($post_id); break;
            case 'sports': self::render_sports_content($post_id); break;
            case 'beauty': self::render_beauty_content($post_id); break;
            case 'technical': self::render_technical_content($post_id); break;
            case 'rentals': self::render_rentals_content($post_id); break;
            case 'restaurant': self::render_restaurant_content($post_id); break;
            case 'legal': self::render_legal_content($post_id); break;
            case 'transport': self::render_transport_content($post_id); break;
            case 'pets': self::render_pets_content($post_id); break;
            case 'home': self::render_home_content($post_id); break;
            case 'creative': self::render_creative_content($post_id); break;
            case 'childcare': self::render_childcare_content($post_id); break;
            case 'ceremonial': self::render_ceremonial_content($post_id); break;
        }
        
        // RENDER CUSTOM FIELDS
        self::render_custom_fields_section($post_id);

        $gallery = get_post_meta($post_id, '_nsm_gallery_images', true);
        if ($gallery && is_array($gallery)) {
            echo '<div class="nsm-info-card"><h2>' . nsm_get_string('gallery', 'گالری تصاویر') . '</h2><div class="nsm-gallery-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px;">';
            foreach ($gallery as $item) {
                if (!empty($item['image'])) {
                    echo '<a href="'.esc_url($item['image']).'" target="_blank"><img src="'.esc_url($item['image']).'" style="width:100%; height: 120px; object-fit: cover; border-radius: 8px;"/></a>';
                }
            }
            echo '</div></div>';
        }
        
        $paid_content = get_post_meta($post_id, '_nsm_paid_content', true);
        $user_has_purchased = (isset($_GET['nsm_payment_status']) && $_GET['nsm_payment_status'] === 'success');
        if ($paid_content && $user_has_purchased) {
             self::render_content_section(nsm_get_string('premium_content_label', 'محتوای ویژه'), $paid_content, true);
        }
    }
    
    private static function render_repeater_list($post_id, $meta_key, $title, $sub_fields, $is_premium = false) {
        if ($is_premium && !(isset($_GET['nsm_payment_status']) && $_GET['nsm_payment_status'] === 'success')) return;

        $items = get_post_meta($post_id, $meta_key, true);
        if (!empty($items) && is_array($items)) {
            echo '<div class="nsm-info-card"><h2>' . esc_html($title) . '</h2><ul>';
            foreach ($items as $item) {
                echo '<li>';
                $line = '';
                foreach ($sub_fields as $key => $label) {
                    if (!empty($item[$key])) {
                        $line .= '<strong>' . esc_html($label) . ':</strong> ' . wp_kses_post($item[$key]) . ' ';
                    }
                }
                echo trim($line) . '</li>';
            }
            echo '</ul></div>';
        }
    }
    
    private static function render_custom_fields_section($post_id) {
        $custom_fields = get_post_meta($post_id, '_nsm_custom_fields', true);
        if (empty($custom_fields) || !is_array($custom_fields)) {
            return;
        }

        ob_start();
        ?>
        <div class="nsm-info-card">
            <h2><?php echo nsm_get_string('custom_fields_front_title', 'سایر مشخصات'); ?></h2>
            <ul class="nsm-meta-info-list">
                <?php foreach ($custom_fields as $field): 
                    if (empty($field['field_label']) && empty($field['field_value'])) continue;
                    
                    $icon_html = '';
                    if (!empty($field['field_icon'])) {
                        $icon_html = '<img src="' . esc_url($field['field_icon']) . '" alt="' . esc_attr($field['field_label']) . '">';
                    } else {
                        $icon_html = self::get_svg_icon('chevron-left');
                    }

                    $value_html = '';
                    $field_type = $field['field_type'] ?? 'text';
                    if ($field_type === 'wpautop') {
                        $value_html = wpautop(wp_kses_post($field['field_value']));
                    } elseif ($field_type === 'html') {
                        $value_html = wp_kses_post($field['field_value']);
                    } else {
                        $value_html = esc_html($field['field_value']);
                    }
                ?>
                <li class="nsm-meta-info-item">
                    <?php echo $icon_html; ?>
                    <div>
                        <span class="meta-label"><?php echo esc_html($field['field_label']); ?>:</span>
                        <span class="meta-value"><?php echo $value_html; ?></span>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
        echo ob_get_clean();
    }

    private static function render_educational_content($post_id) {
        $instructor_photo = get_post_meta($post_id, '_nsm_edu_instructor_photo', true);
        $instructor_name = get_post_meta($post_id, '_nsm_edu_instructor_name', true);
        $instructor_bio = get_post_meta($post_id, '_nsm_edu_instructor_bio', true);
        if ($instructor_name) {
            echo '<div class="nsm-info-card"><h2>درباره مدرس</h2><div class="nsm-instructor-profile">';
            if($instructor_photo) echo '<img src="'.esc_url($instructor_photo).'" alt="'.esc_attr($instructor_name).'"/>';
            echo '<div><h3 class="instructor-name">'.esc_html($instructor_name).'</h3><p class="instructor-bio">'.esc_html($instructor_bio).'</p></div>';
            echo '</div></div>';
        }
        self::render_repeater_list($post_id, '_nsm_edu_prerequisites', 'پیش‌نیازهای دوره', ['item' => '']);
        self::render_repeater_list($post_id, '_nsm_edu_syllabus', 'سرفصل‌های دوره', ['section_title' => 'سرفصل', 'section_topics' => 'توضیحات']);
        
        $is_downloads_premium = get_post_meta($post_id, '_nsm_edu_downloads_is_paid', true) === '1';
        self::render_repeater_list($post_id, '_nsm_edu_downloads', 'فایل‌های ضمیمه', ['file_title' => 'فایل', 'file_url' => 'لینک'], $is_downloads_premium);
    }

    private static function render_events_content($post_id) {
        $speakers = get_post_meta($post_id, '_nsm_evt_speakers', true);
        if ($speakers && is_array($speakers)) {
            echo '<div class="nsm-info-card"><h2>سخنرانان</h2><div class="nsm-speakers-grid">';
            foreach($speakers as $speaker) {
                echo '<div class="nsm-speaker-card">';
                if(!empty($speaker['speaker_photo'])) echo '<img src="'.esc_url($speaker['speaker_photo']).'" alt="'.esc_attr($speaker['speaker_name']).'"/>';
                echo '<div class="speaker-name">'.esc_html($speaker['speaker_name']).'</div>';
                echo '<div class="speaker-title">'.esc_html($speaker['speaker_title']).'</div>';
                echo '</div>';
            }
            echo '</div></div>';
        }
        $agenda = get_post_meta($post_id, '_nsm_evt_agenda', true);
        if ($agenda && is_array($agenda)) {
            echo '<div class="nsm-info-card"><h2>برنامه زمان‌بندی</h2><ul class="nsm-agenda-list">';
            foreach($agenda as $item) {
                echo '<li class="nsm-agenda-item"><div class="nsm-agenda-time">'.esc_html($item['agenda_time']).'</div><h4 class="nsm-agenda-title">'.esc_html($item['agenda_title']).'</h4></li>';
            }
            echo '</ul></div>';
        }
        $tickets = get_post_meta($post_id, '_nsm_evt_tickets', true);
        if ($tickets && is_array($tickets)) {
             echo '<div class="nsm-info-card"><h2>انواع بلیت</h2><table class="nsm-pricing-table"><thead><tr><th>نوع بلیت</th><th>ویژگی‌ها</th><th>قیمت</th></tr></thead><tbody>';
            foreach($tickets as $ticket) {
                echo '<tr><td>'.esc_html($ticket['ticket_type']).'</td><td>'.esc_html($ticket['ticket_features']).'</td><td>'.esc_html($ticket['ticket_price']).'</td></tr>';
            }
            echo '</tbody></table></div>';
        }
        $map = get_post_meta($post_id, '_nsm_evt_location_map', true);
        self::render_content_section('نقشه و محل برگزاری', $map, true);
    }

    private static function render_consulting_content($post_id) {
        $consultant_photo = get_post_meta($post_id, '_nsm_con_consultant_photo', true);
        $consultant_name = get_post_meta($post_id, '_nsm_con_consultant_name', true);
        if ($consultant_name) {
            echo '<div class="nsm-info-card"><h2>درباره مشاور</h2><div class="nsm-instructor-profile">';
            if($consultant_photo) echo '<img src="'.esc_url($consultant_photo).'" alt="'.esc_attr($consultant_name).'"/>';
            echo '<div><h3 class="instructor-name">'.esc_html($consultant_name).'</h3></div>';
            echo '</div></div>';
        }
        self::render_repeater_list($post_id, '_nsm_con_specializations', 'حوزه‌های تخصصی', ['item' => '']);
        self::render_repeater_list($post_id, '_nsm_con_credentials', 'تحصیلات و گواهینامه‌ها', ['item' => '']);
        
        $packages = get_post_meta($post_id, '_nsm_con_packages', true);
        if ($packages && is_array($packages)) {
             echo '<div class="nsm-info-card"><h2>پکیج‌های مشاوره</h2><table class="nsm-pricing-table"><thead><tr><th>عنوان پکیج</th><th>تعداد جلسات</th><th>قیمت</th></tr></thead><tbody>';
            foreach($packages as $package) {
                echo '<tr><td>'.esc_html($package['package_title']).'</td><td>'.esc_html($package['package_sessions_count']).'</td><td>'.esc_html($package['package_price']).'</td></tr>';
            }
            echo '</tbody></table></div>';
        }
    }

    private static function render_tourism_content($post_id) {
        $includes = get_post_meta($post_id, '_nsm_trs_includes', true);
        $excludes = get_post_meta($post_id, '_nsm_trs_excludes', true);
        
        if ($includes || $excludes) {
            echo '<div class="nsm-info-card"><div class="nsm-includes-excludes">';
            if ($includes && is_array($includes)) {
                echo '<div class="nsm-includes"><h3>خدمات تور شامل</h3><ul>';
                foreach($includes as $item) echo '<li>' . esc_html($item['item']) . '</li>';
                echo '</ul></div>';
            }
            if ($excludes && is_array($excludes)) {
                echo '<div class="nsm-excludes"><h3>خدمات تور شامل نمی‌شود</h3><ul>';
                foreach($excludes as $item) echo '<li>' . esc_html($item['item']) . '</li>';
                echo '</ul></div>';
            }
            echo '</div></div>';
        }
        self::render_repeater_list($post_id, '_nsm_trs_itinerary', 'برنامه سفر روزانه', ['day_title' => 'روز', 'day_plan' => 'برنامه']);
        self::render_repeater_list($post_id, '_nsm_trs_required_items', 'لوازم ضروری سفر', ['item' => '']);
    }

    private static function render_sports_content($post_id) {
        $coach_photo = get_post_meta($post_id, '_nsm_sport_coach_photo', true);
        $coach_name = get_post_meta($post_id, '_nsm_sport_coach_name', true);
        if ($coach_name) {
            echo '<div class="nsm-info-card"><h2>درباره مربی</h2><div class="nsm-instructor-profile">';
            if($coach_photo) echo '<img src="'.esc_url($coach_photo).'" alt="'.esc_attr($coach_name).'"/>';
            echo '<div><h3 class="instructor-name">'.esc_html($coach_name).'</h3></div>';
            echo '</div></div>';
        }
        self::render_repeater_list($post_id, '_nsm_sport_equipment', 'تجهیزات مورد نیاز', ['item' => '']);
        $schedule = get_post_meta($post_id, '_nsm_sport_schedule', true);
        if ($schedule && is_array($schedule)) {
            echo '<div class="nsm-info-card"><h2>برنامه هفتگی</h2><ul class="nsm-agenda-list">';
            foreach($schedule as $item) {
                echo '<li class="nsm-agenda-item"><div class="nsm-agenda-time">'.esc_html($item['day']).'</div><h4 class="nsm-agenda-title">ساعت '.esc_html($item['time']).'</h4></li>';
            }
            echo '</ul></div>';
        }
    }

    private static function render_beauty_content($post_id) {
        self::render_repeater_list($post_id, '_nsm_beauty_pre_care', 'مراقبت‌های قبل', ['item' => '']);
        self::render_repeater_list($post_id, '_nsm_beauty_post_care', 'مراقبت‌های بعد', ['item' => '']);
        self::render_repeater_list($post_id, '_nsm_beauty_used_products', 'محصولات مورد استفاده', ['item' => '']);
    }

    private static function render_technical_content($post_id) {
        self::render_key_value_list($post_id, 'جزئیات فنی', [
            '_nsm_tech_warranty' => 'مدت زمان گارانتی',
            '_nsm_tech_working_hours' => 'ساعات کاری',
            '_nsm_tech_transport_fee' => 'هزینه ایاب و ذهاب',
        ]);
        self::render_content_section('محدوده ارائه خدمات', get_post_meta($post_id, '_nsm_tech_service_area', true));
        self::render_repeater_list($post_id, '_nsm_tech_supported_brands', 'برندهای تحت پوشش', ['item' => '']);
    }

    private static function render_rentals_content($post_id) {
        $features = get_post_meta($post_id, '_nsm_rental_features', true);
        if ($features && is_array($features)) {
            echo '<div class="nsm-info-card"><h2>ویژگی‌ها و امکانات</h2><div class="nsm-features-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px;">';
            foreach($features as $feature) {
                echo '<div class="nsm-feature-item" style="text-align:center;">';
                if(!empty($feature['feature_icon'])) echo '<img src="'.esc_url($feature['feature_icon']).'" alt="'.esc_attr($feature['feature_text']).'" style="width: 40px; height: 40px; margin-bottom: 10px;"/>';
                echo '<div>'.esc_html($feature['feature_text']).'</div>';
                echo '</div>';
            }
            echo '</div></div>';
        }
        self::render_content_section('شرایط و قوانین اجاره', get_post_meta($post_id, '_nsm_rental_terms', true), true);
    }

    private static function render_restaurant_content($post_id) {
        $menu_items = get_post_meta($post_id, '_nsm_resto_menu_items', true);
        if ($menu_items && is_array($menu_items)) {
            echo '<div class="nsm-info-card"><h2>منوی ویژه</h2><div class="nsm-menu-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px;">';
            foreach($menu_items as $item) {
                echo '<div class="nsm-menu-item" style="display:flex; gap: 15px; align-items:center;">';
                if(!empty($item['item_image'])) echo '<img src="'.esc_url($item['item_image']).'" alt="'.esc_attr($item['item_name']).'" style="width: 80px; height: 80px; border-radius: 8px; object-fit: cover;"/>';
                echo '<div><h4>'.esc_html($item['item_name']).'</h4><p>'.esc_html($item['item_description']).'</p><strong>'.number_format($item['item_price']).' تومان</strong></div>';
                echo '</div>';
            }
            echo '</div></div>';
        }
    }
    
    private static function render_legal_content($post_id) {
        self::render_content_section('آدرس دفتر', get_post_meta($post_id, '_nsm_legal_office_address', true));
        self::render_repeater_list($post_id, '_nsm_legal_specialty', 'حوزه‌های تخصصی', ['item' => '']);
    }

    private static function render_transport_content($post_id) { 
        self::render_content_section('محدوده سرویس‌دهی', get_post_meta($post_id, '_nsm_trans_service_area', true));
    }

    private static function render_pets_content($post_id) {
        self::render_repeater_list($post_id, '_nsm_pets_required_vaccines', 'واکسن‌های الزامی', ['item' => '']);
        $packages = get_post_meta($post_id, '_nsm_pets_grooming_packages', true);
        if ($packages && is_array($packages)) {
             echo '<div class="nsm-info-card"><h2>پکیج‌های آرایشی</h2><table class="nsm-pricing-table"><thead><tr><th>عنوان پکیج</th><th>قیمت</th></tr></thead><tbody>';
            foreach($packages as $package) {
                echo '<tr><td>'.esc_html($package['package_name']).'</td><td>'.number_format($package['package_price']).' تومان</td></tr>';
            }
            echo '</tbody></table></div>';
        }
    }

    private static function render_home_content($post_id) { }

    private static function render_creative_content($post_id) {
        self::render_repeater_list($post_id, '_nsm_creative_software_used', 'نرم‌افزارهای مورد استفاده', ['item' => '']);
        $packages = get_post_meta($post_id, '_nsm_creative_packages', true);
        if ($packages && is_array($packages)) {
            echo '<div class="nsm-info-card"><h2>پکیج‌های خدمات</h2>';
            foreach($packages as $package) {
                echo '<h4>'.esc_html($package['package_title']).' - '.number_format($package['package_price']).' تومان</h4>';
                echo '<div>'.wpautop(esc_html($package['package_includes'])).'</div><hr>';
            }
            echo '</div>';
        }
    }

    private static function render_childcare_content($post_id) { 
        self::render_repeater_list($post_id, '_nsm_child_curriculum', 'برنامه آموزشی و فعالیت‌ها', ['activity_title' => 'فعالیت', 'activity_desc' => 'توضیحات']);
        self::render_content_section('سوابق مربی/پرستار', get_post_meta($post_id, '_nsm_child_tutor_credentials', true));
    }

    private static function render_ceremonial_content($post_id) {
        $menu_packages = get_post_meta($post_id, '_nsm_cer_menu_packages', true);
        if ($menu_packages && is_array($menu_packages)) {
            echo '<div class="nsm-info-card"><h2>پکیج‌های منو</h2><table class="nsm-pricing-table"><thead><tr><th>عنوان منو</th><th>آیتم‌ها</th><th>قیمت هر نفر</th></tr></thead><tbody>';
            foreach($menu_packages as $package) {
                echo '<tr><td>'.esc_html($package['menu_title']).'</td><td>'.wpautop(esc_html($package['menu_items'])).'</td><td>'.number_format($package['price_per_person']).' تومان</td></tr>';
            }
            echo '</tbody></table></div>';
        }
        self::render_repeater_list($post_id, '_nsm_cer_extra_services', 'خدمات جانبی', ['item' => '']);
    }
    
    // --- OTHERS ---
    private static function render_content_section($title, $content, $apply_wpautop = true) {
        if (!empty($content)) {
            $sanitized_content = wp_kses_post($content);
            echo '<div class="nsm-info-card"><h2>' . esc_html($title) . '</h2>' . ($apply_wpautop ? wpautop($sanitized_content) : $sanitized_content) . '</div>';
        }
    }
    
    private static function render_key_value_list($post_id, $title, $fields_map) {
        $has_content = false;
        ob_start();
        echo '<ul>';
        foreach ($fields_map as $meta_key => $label) {
            $value = get_post_meta($post_id, $meta_key, true);
            if (!empty($value)) {
                $has_content = true;
                echo '<li><strong>' . esc_html($label) . ':</strong> ' . esc_html($value) . '</li>';
            }
        }
        echo '</ul>';
        $output = ob_get_clean();

        if ($has_content) {
            self::render_content_section($title, $output, false);
        }
    }

    private static function render_payment_box($post_id) {
        $payment_model = get_post_meta($post_id, '_nsm_payment_model', true);
        $price = get_post_meta($post_id, '_nsm_price', true);
        $reg_link = get_post_meta($post_id, '_nsm_reg_link', true);
        $status = get_post_meta($post_id, '_nsm_status', true);

        if ($status === 'full' || $status === 'ended') {
            echo '<strong>' . ($status === 'full' ? nsm_get_string('status_full', 'تکمیل ظرفیت') : nsm_get_string('status_ended', 'پایان یافته')) . '</strong>';
            return;
        }
        
        echo '<form method="POST" class="nsm-payment-form">';
        wp_nonce_field('nsm_submit_payment', 'nsm_payment_nonce');
        echo '<input type="hidden" name="service_id" value="' . esc_attr($post_id) . '">';
        echo '<input type="text" name="user_name" placeholder="' . nsm_get_string('fullname', 'نام و نام خانوادگی') . '" required>';
        echo '<input type="tel" name="user_mobile" placeholder="' . nsm_get_string('mobile_number', 'شماره موبایل') . '" pattern="09[0-9]{9}" title="شماره موبایل باید با 09 شروع شود و 11 رقم باشد." required>';

        if ($payment_model === 'paid') {
            if ($reg_link) {
                echo '<a href="' . esc_url($reg_link) . '" class="nsm-cta-button" target="_blank">' . nsm_get_string('buy_now', 'خرید و مشاهده') . '</a>';
            } else {
                echo '<div class="nsm-price-box" style="margin-bottom: 15px; text-align: center;"><span class="price-value">' . number_format($price) . '</span> <small>' . nsm_get_string('toman', 'تومان') . '</small></div>';
                echo '<button type="submit" class="nsm-cta-button">' . nsm_get_string('payment_and_reg', 'پرداخت و ثبت نام') . '</button>';
            }
        } else {
            echo '<button type="submit" class="nsm-cta-button">' . nsm_get_string('free_reg_button', 'ثبت نام رایگان') . '</button>';
        }
        echo '</form>';
    }
    
    private static function render_sidebar_meta($post_id, $service_type, $show_title = true) {
        $fields = self::get_meta_fields_for_sidebar($post_id, $service_type);
        if (empty($fields)) return;
        
        if ($show_title) echo '<h2>' . nsm_get_string('key_info', 'اطلاعات کلیدی') . '</h2>';
        echo '<ul class="nsm-meta-info-list">';
        foreach ($fields as $field) {
            echo '<li class="nsm-meta-info-item">' . $field['icon'] . '<div><span class="meta-label">' . $field['label'] . ':</span> <span class="meta-value">' . $field['value'] . '</span></div></li>';
        }
        echo '</ul>';
    }
    
    private static function get_meta_fields_for_sidebar($post_id, $service_type) {
        $fields = [];
        $all_service_types = NSM_Meta_Boxes::get_service_types();
        $service_type_label = $all_service_types[$service_type] ?? '';
        $service_type_label = preg_replace('/^[\d\x{06F0}-\x{06F9}]+\.\s*/u', '', $service_type_label);

        $fields[] = ['label' => nsm_get_string('service_type', 'نوع خدمت'), 'value' => esc_html($service_type_label), 'icon' => self::get_svg_icon('tag')];
        
        $categories = get_the_term_list($post_id, 'service_category', '', ', ');
        if ($categories && !is_wp_error($categories)) {
            $fields[] = ['label' => nsm_get_string('category', 'دسته‌بندی'), 'value' => $categories, 'icon' => self::get_svg_icon('folder')];
        }
        $keywords = get_the_term_list($post_id, 'service_keyword', '', ', ');
        if ($keywords && !is_wp_error($keywords)) {
            $fields[] = ['label' => nsm_get_string('keywords', 'کلیدواژه‌ها'), 'value' => $keywords, 'icon' => self::get_svg_icon('hash')];
        }

        if($val = get_post_meta($post_id, '_nsm_status', true)) $fields[] = ['label' => nsm_get_string('status', 'وضعیت'), 'value' => self::get_status_label($val), 'icon' => self::get_svg_icon('check-circle')];
        if($val = get_post_meta($post_id, '_nsm_payment_model', true)) $fields[] = ['label' => nsm_get_string('payment_model', 'مدل فروش'), 'value' => ($val === 'paid' ? nsm_get_string('payment_paid', 'پولی') : nsm_get_string('payment_free', 'رایگان')), 'icon' => self::get_svg_icon('credit-card')];
        if($val = get_post_meta($post_id, '_nsm_is_featured', true)) $fields[] = ['label' => nsm_get_string('featured', 'ویژه'), 'value' => ($val === 'yes' ? nsm_get_string('is_featured_yes', 'بله') : nsm_get_string('is_featured_no', 'خیر')), 'icon' => self::get_svg_icon('star')];
        if($val = get_post_meta($post_id, '_nsm_date', true)) $fields[] = ['label' => nsm_get_string('start_date', 'تاریخ برگزاری/شروع'), 'value' => esc_html($val), 'icon' => self::get_svg_icon('calendar')];
        if($val = get_post_meta($post_id, '_nsm_time', true)) $fields[] = ['label' => nsm_get_string('start_time', 'ساعت'), 'value' => esc_html($val), 'icon' => self::get_svg_icon('clock')];

        // Service-specific fields
        switch($service_type) {
            case 'educational':
                if($val = get_post_meta($post_id, '_nsm_edu_instructor_name', true)) $fields[] = ['label' => 'مدرس', 'value' => esc_html($val), 'icon' => self::get_svg_icon('user')];
                if($val = get_post_meta($post_id, '_nsm_edu_sessions', true)) $fields[] = ['label' => 'تعداد جلسات', 'value' => esc_html($val), 'icon' => self::get_svg_icon('list')];
                if($val = get_post_meta($post_id, '_nsm_edu_duration_total', true)) $fields[] = ['label' => 'کل مدت زمان', 'value' => esc_html($val) . ' ساعت', 'icon' => self::get_svg_icon('clock')];
                if($val = get_post_meta($post_id, '_nsm_edu_certificate', true)) $fields[] = ['label' => 'ارائه گواهینامه', 'value' => ($val === 'yes' ? 'بله' : 'خیر'), 'icon' => self::get_svg_icon('award')];
                if($val = get_post_meta($post_id, '_nsm_edu_support_type', true)) $fields[] = ['label' => 'نحوه پشتیبانی', 'value' => esc_html($val), 'icon' => self::get_svg_icon('message-square')];
                break;
            case 'events':
                if($val = get_post_meta($post_id, '_nsm_evt_organizer', true)) $fields[] = ['label' => 'برگزارکننده', 'value' => esc_html($val), 'icon' => self::get_svg_icon('user')];
                if($val = get_post_meta($post_id, '_nsm_evt_location_name', true)) $fields[] = ['label' => 'محل برگزاری', 'value' => esc_html($val), 'icon' => self::get_svg_icon('map-pin')];
                if($val = get_post_meta($post_id, '_nsm_evt_capacity', true)) $fields[] = ['label' => 'ظرفیت', 'value' => esc_html($val) . ' نفر', 'icon' => self::get_svg_icon('users')];
                break;
            case 'consulting':
                 if($val = get_post_meta($post_id, '_nsm_con_consultant_name', true)) $fields[] = ['label' => 'مشاور', 'value' => esc_html($val), 'icon' => self::get_svg_icon('user')];
                 if($val = get_post_meta($post_id, '_nsm_con_experience_years', true)) $fields[] = ['label' => 'سال تجربه', 'value' => esc_html($val), 'icon' => self::get_svg_icon('award')];
                 if($val = get_post_meta($post_id, '_nsm_con_session_duration', true)) $fields[] = ['label' => 'مدت جلسه', 'value' => esc_html($val) . ' دقیقه', 'icon' => self::get_svg_icon('clock')];
                break;
            case 'tourism':
                if($val = get_post_meta($post_id, '_nsm_trs_destination', true)) $fields[] = ['label' => 'مقصد', 'value' => esc_html($val), 'icon' => self::get_svg_icon('map-pin')];
                if($val = get_post_meta($post_id, '_nsm_trs_duration_text', true)) $fields[] = ['label' => 'مدت زمان', 'value' => esc_html($val), 'icon' => self::get_svg_icon('clock')];
                if($val = get_post_meta($post_id, '_nsm_trs_vehicle', true)) $fields[] = ['label' => 'وسیله نقلیه', 'value' => esc_html($val), 'icon' => self::get_svg_icon('truck')];
                if($val = get_post_meta($post_id, '_nsm_trs_accommodation_type', true)) $fields[] = ['label' => 'نوع اقامتگاه', 'value' => esc_html($val), 'icon' => self::get_svg_icon('home')];
                break;
            case 'sports':
                if($val = get_post_meta($post_id, '_nsm_sport_coach_name', true)) $fields[] = ['label' => 'مربی', 'value' => esc_html($val), 'icon' => self::get_svg_icon('user')];
                if($val = get_post_meta($post_id, '_nsm_sport_duration', true)) $fields[] = ['label' => 'مدت جلسه', 'value' => esc_html($val) . ' دقیقه', 'icon' => self::get_svg_icon('clock')];
                if($val = get_post_meta($post_id, '_nsm_sport_focus_areas', true)) $fields[] = ['label' => 'تمرکز اصلی', 'value' => esc_html($val), 'icon' => self::get_svg_icon('activity')];
                break;
            case 'beauty':
                if($val = get_post_meta($post_id, '_nsm_beauty_specialist_name', true)) $fields[] = ['label' => 'متخصص', 'value' => esc_html($val), 'icon' => self::get_svg_icon('user')];
                if($val = get_post_meta($post_id, '_nsm_beauty_duration', true)) $fields[] = ['label' => 'مدت زمان', 'value' => esc_html($val) . ' دقیقه', 'icon' => self::get_svg_icon('clock')];
                break;
            case 'rentals':
                if($val = get_post_meta($post_id, '_nsm_rental_daily_rate', true)) $fields[] = ['label' => 'اجاره روزانه', 'value' => number_format($val) . ' تومان', 'icon' => self::get_svg_icon('dollar-sign')];
                if($val = get_post_meta($post_id, '_nsm_rental_weekly_rate', true)) $fields[] = ['label' => 'اجاره هفتگی', 'value' => number_format($val) . ' تومان', 'icon' => self::get_svg_icon('dollar-sign')];
                if($val = get_post_meta($post_id, '_nsm_rental_deposit', true)) $fields[] = ['label' => 'مبلغ ودیعه', 'value' => number_format($val) . ' تومان', 'icon' => self::get_svg_icon('dollar-sign')];
                break;
            case 'restaurant':
                if($val = get_post_meta($post_id, '_nsm_resto_chef_name', true)) $fields[] = ['label' => 'سرآشپز', 'value' => esc_html($val), 'icon' => self::get_svg_icon('user')];
                if($val = get_post_meta($post_id, '_nsm_resto_music_type', true)) $fields[] = ['label' => 'نوع موسیقی', 'value' => esc_html($val), 'icon' => self::get_svg_icon('music')];
                break;
            case 'legal':
                if($val = get_post_meta($post_id, '_nsm_legal_lawyer_name', true)) $fields[] = ['label' => 'وکیل/مشاور', 'value' => esc_html($val), 'icon' => self::get_svg_icon('user')];
                if($val = get_post_meta($post_id, '_nsm_legal_license_number', true)) $fields[] = ['label' => 'شماره پروانه', 'value' => esc_html($val), 'icon' => self::get_svg_icon('award')];
                break;
            case 'transport':
                if($val = get_post_meta($post_id, '_nsm_trans_vehicle_type', true)) $fields[] = ['label' => 'نوع وسیله نقلیه', 'value' => esc_html($val), 'icon' => self::get_svg_icon('truck')];
                if($val = get_post_meta($post_id, '_nsm_trans_worker_count', true)) $fields[] = ['label' => 'تعداد کارگر', 'value' => esc_html($val), 'icon' => self::get_svg_icon('users')];
                break;
            case 'pets':
                if($val = get_post_meta($post_id, '_nsm_pets_animal_type', true)) $fields[] = ['label' => 'نوع حیوان', 'value' => esc_html($val), 'icon' => self::get_svg_icon('github')]; // Placeholder icon
                if($val = get_post_meta($post_id, '_nsm_pets_has_boarding', true)) $fields[] = ['label' => 'نگهداری شبانه‌روزی', 'value' => ($val === 'yes' ? 'بله' : 'خیر'), 'icon' => self::get_svg_icon('home')];
                if($val = get_post_meta($post_id, '_nsm_pets_vet_present', true)) $fields[] = ['label' => 'دامپزشک مستقر', 'value' => ($val === 'yes' ? 'بله' : 'خیر'), 'icon' => self::get_svg_icon('activity')];
                break;
            case 'home':
                if($val = get_post_meta($post_id, '_nsm_home_staff_count', true)) $fields[] = ['label' => 'تعداد نیروی اعزامی', 'value' => esc_html($val), 'icon' => self::get_svg_icon('users')];
                if($val = get_post_meta($post_id, '_nsm_home_is_insured', true)) $fields[] = ['label' => 'تحت پوشش بیمه', 'value' => ($val === 'yes' ? 'بله' : 'خیر'), 'icon' => self::get_svg_icon('shield')];
                break;
            case 'creative':
                if($val = get_post_meta($post_id, '_nsm_creative_portfolio_url', true)) $fields[] = ['label' => 'نمونه کارها', 'value' => '<a href="'.esc_url($val).'" target="_blank">مشاهده</a>', 'icon' => self::get_svg_icon('link')];
                if($val = get_post_meta($post_id, '_nsm_creative_revision_rounds', true)) $fields[] = ['label' => 'دفعات بازبینی', 'value' => esc_html($val), 'icon' => self::get_svg_icon('refresh-cw')];
                break;
            case 'childcare':
                if($val = get_post_meta($post_id, '_nsm_child_age_group', true)) $fields[] = ['label' => 'گروه سنی', 'value' => esc_html($val), 'icon' => self::get_svg_icon('users')];
                if($val = get_post_meta($post_id, '_nsm_child_has_meal', true)) $fields[] = ['label' => 'شامل وعده غذایی', 'value' => ($val === 'yes' ? 'بله' : 'خیر'), 'icon' => self::get_svg_icon('coffee')];
                if($val = get_post_meta($post_id, '_nsm_child_pickup_service', true)) $fields[] = ['label' => 'سرویس رفت و برگشت', 'value' => ($val === 'yes' ? 'بله' : 'خیر'), 'icon' => self::get_svg_icon('truck')];
                break;
            case 'ceremonial':
                if($val = get_post_meta($post_id, '_nsm_cer_min_guests', true)) $fields[] = ['label' => 'حداقل مهمانان', 'value' => esc_html($val), 'icon' => self::get_svg_icon('users')];
                if($val = get_post_meta($post_id, '_nsm_cer_max_guests', true)) $fields[] = ['label' => 'حداکثر مهمانان', 'value' => esc_html($val), 'icon' => self::get_svg_icon('users')];
                break;
        }
        return $fields;
    }
    
    private static function get_status_label($status) {
        $labels = ['available' => nsm_get_string('status_available', 'در دسترس'), 'full' => nsm_get_string('status_full', 'تکمیل ظرفیت'), 'ended' => nsm_get_string('status_ended', 'پایان یافته')];
        return $labels[$status] ?? '';
    }
    
    private static function get_svg_icon($name) {
        // Feather Icons SVG set
        $icons = [
            'activity' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>',
            'award' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg>',
            'calendar' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>',
            'check-circle' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>',
            'chevron-left' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>',
            'clock' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>',
            'coffee' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a4 4 0 0 1 0 8h-1"></path><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path><line x1="6" y1="1" x2="6" y2="4"></line><line x1="10" y1="1" x2="10" y2="4"></line><line x1="14" y1="1" x2="14" y2="4"></line></svg>',
            'credit-card' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>',
            'dollar-sign' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>',
            'folder' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>',
            'hash' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="9" x2="20" y2="9"></line><line x1="4" y1="15" x2="20" y2="15"></line><line x1="10" y1="3" x2="8" y2="21"></line><line x1="16" y1="3" x2="14" y2="21"></line></svg>',
            'home' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>',
            'link' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.72"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.72-1.72"></path></svg>',
            'list' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>',
            'map-pin' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>',
            'message-square' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>',
            'music' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"></path><circle cx="6" cy="18" r="3"></circle><circle cx="18" cy="16" r="3"></circle></svg>',
            'refresh-cw' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>',
            'shield' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>',
            'star' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>',
            'tag' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>',
            'truck' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>',
            'user' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>',
            'users' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
            'github' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"></path></svg>',
        ];
        return $icons[$name] ?? '';
    }
}
