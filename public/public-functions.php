<?php
/**
 * فایل توابع و کلاس‌های مربوط به بخش عمومی (Public/Frontend)
 *
 * @package Nilay_Service_Manager/Public
 * @version 4.5.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
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
            wp_die(nsm_get_string('error_invalid_info', 'اطلاعات وارد شده نامعتبر است. لطفا به صفحه قبل بازگردید.'));
        }

        $price = get_post_meta($service_id, '_nsm_price', true);
        $general_options = get_option('nsm_general_options');
        $active_gateway = $general_options['active_payment_gateway'] ?? 'none';

        if ($active_gateway === 'none' || !$price) {
            wp_die(nsm_get_string('error_payment_unavailable', 'امکان پرداخت برای این خدمت در حال حاضر وجود ندارد.'));
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
        
        // Here you would add the actual SMS sending logic based on the provider
        // For example: if ($sms_provider === 'kavenegar') { ... }
        
        return true;
    }

    private static function replace_patterns($template, $service_id) {
        $service = get_post($service_id);
        $price = get_post_meta($service_id, '_nsm_price', true);
        $date = get_post_meta($service_id, '_nsm_date', true);
        $time = get_post_meta($service_id, '_nsm_time', true);
        
        $user_name = isset($_POST['user_name']) ? sanitize_text_field($_POST['user_name']) : 'کاربر تست'; 
        $user_mobile = isset($_POST['user_mobile']) ? sanitize_text_field($_POST['user_mobile']) : '09123456789';
        $transaction_id = 'XYZ' . rand(1000, 9999);

        $patterns = [
            '[service_name]' => $service->post_title,
            '[user_name]' => $user_name,
            '[user_mobile]' => $user_mobile,
            '[price]' => number_format($price) . ' ' . nsm_get_string('toman', 'تومان'),
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
            
            $service_content = '';
            switch ($template) {
                case 'layout-2': $service_content = self::render_template_two($post_id); break;
                case 'layout-3': $service_content = self::render_template_three($post_id); break;
                case 'layout-4': $service_content = self::render_template_four($post_id); break;
                case 'layout-5': $service_content = self::render_template_five($post_id); break;
                case 'layout-6': $service_content = self::render_template_six($post_id); break;
                case 'layout-7': $service_content = self::render_template_seven($post_id); break;
                case 'layout-1': default: $service_content = self::render_template_one($post_id); break;
            }
            return $service_content;
        }
        return $content;
    }

    // --- TEMPLATE 1: MODERN PROFESSIONAL ---
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
    private static function render_template_two($post_id) {
        $service_type = get_post_meta($post_id, '_nsm_service_type', true);
        $gallery = get_post_meta($post_id, '_nsm_gallery_images', true);
        $thumb_url = get_the_post_thumbnail_url($post_id, 'full');
        ob_start();
        ?>
        <div class="nsm-single-service-wrapper nsm-layout-2">
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
    
    // --- TEMPLATE 4: SLEEK DARK MODE ---
    private static function render_template_four($post_id) {
        $service_type = get_post_meta($post_id, '_nsm_service_type', true);
        $thumb_url = get_the_post_thumbnail_url($post_id, 'full') ?: 'https://placehold.co/1200x800/374151/d1d5db?text=Nilay+Service';
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

    // --- NEW TEMPLATE 6: DARK MINIMALIST ---
    private static function render_template_six($post_id) {
        $service_type = get_post_meta($post_id, '_nsm_service_type', true);
        $thumb_url = get_the_post_thumbnail_url($post_id, 'full') ?: 'https://placehold.co/1600x800/1f2937/9ca3af?text=Nilay+Service';
        ob_start();
        ?>
        <div class="nsm-single-service-wrapper nsm-layout-6">
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

    // --- NEW TEMPLATE 7: DARK GLASSMORPHISM ---
    private static function render_template_seven($post_id) {
        $service_type = get_post_meta($post_id, '_nsm_service_type', true);
        ob_start();
        ?>
        <div class="nsm-single-service-wrapper nsm-layout-7">
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
        $user_has_purchased = (isset($_GET['status']) && $_GET['status'] === 'success');
        if ($paid_content && $user_has_purchased) {
             self::render_content_section(nsm_get_string('premium_content_label', 'محتوای ویژه'), $paid_content, true);
        }
    }
    
    private static function render_repeater_list($post_id, $meta_key, $title, $sub_fields, $is_premium = false) {
        if ($is_premium && !(isset($_GET['status']) && $_GET['status'] === 'success')) return;

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
        self::render_repeater_list($post_id, '_nsm_legal_specialty', 'حوزه‌های تخصصی', ['item' => '']);
    }

    private static function render_transport_content($post_id) { 
        self::render_content_section('محدوده سرویس‌دهی', get_post_meta($post_id, '_nsm_trans_service_area', true), true);
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
        self::render_content_section('سوابق مربی/پرستار', get_post_meta($post_id, '_nsm_child_tutor_credentials', true), true);
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
}
