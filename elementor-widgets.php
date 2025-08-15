<?php
// جلوگیری از دسترسی مستقیم به فایل
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * این فایل شامل کلاس‌های ویجت المنتور است و فقط زمانی فراخوانی می‌شود که المنتور فعال باشد.
 */

abstract class NSM_Elementor_Widget_Base extends \Elementor\Widget_Base {
    public function get_categories() { return [ 'nilay-services' ]; }

    protected function get_services_list() {
        $services = get_posts(['post_type' => 'service', 'numberposts' => -1]);
        $options = [];
        if ($services) {
            foreach ($services as $service) {
                $options[$service->ID] = $service->post_title;
            }
        }
        return $options;
    }
    
    protected function get_taxonomies_list($taxonomy) {
        $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
        $options = [];
        if (!is_wp_error($terms) && !empty($terms)) {
            foreach ($terms as $term) {
                $options[$term->slug] = $term->name;
            }
        }
        return $options;
    }

    protected function get_all_meta_keys() {
        NSM_Meta_Boxes::define_fields();
        $fields = NSM_Meta_Boxes::$meta_fields;
        $options = [
            'post_title' => 'عنوان خدمت', 
            '_nsm_service_summary' => 'چکیده خدمت', 
            '_nsm_service_details' => 'توضیحات کامل',
            'post_date' => 'تاریخ انتشار'
        ];
        foreach($fields as $group_key => $group) {
            foreach($group as $field) {
                $options[$field['id']] = $field['label'] . " ($group_key)";
            }
        }
        return $options;
    }
}

class Elementor_NSM_Services_Grid_Widget extends NSM_Elementor_Widget_Base {
    public function get_name() { return 'nsm_services_grid'; }
    public function get_title() { return __( 'گرید خدمات نیلای', 'nilay-services' ); }
    public function get_icon() { return 'eicon-gallery-grid'; }

    protected function register_controls() {
        // --- Query Section ---
        $this->start_controls_section('query_section', ['label' => 'کوئری']);
        
        $this->add_control('source', [
            'label' => 'منبع', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'latest',
            'options' => [
                'latest' => 'آخرین خدمات',
                'featured' => 'خدمات ویژه',
                'by_id' => 'انتخاب دستی',
                'related' => 'مرتبط (بر اساس دسته‌بندی)',
            ]
        ]);
        
        $this->add_control('post_ids', [
            'label' => 'جستجو و انتخاب خدمات', 'type' => \Elementor\Controls_Manager::SELECT2,
            'options' => $this->get_services_list(), 'multiple' => true, 'label_block' => true,
            'condition' => ['source' => 'by_id']
        ]);
        
        $this->add_control('category_ids', [
            'label' => 'دسته‌بندی‌ها', 'type' => \Elementor\Controls_Manager::SELECT2,
            'options' => $this->get_taxonomies_list('service_category'), 'multiple' => true, 'label_block' => true,
            'condition' => ['source' => 'latest']
        ]);

        $this->add_control('orderby', ['label' => 'مرتب سازی بر اساس', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'date', 'options' => ['date' => 'تاریخ', 'title' => 'عنوان', 'rand' => 'تصادفی'], 'condition' => ['source!' => 'by_id']]);
        $this->add_control('order', ['label' => 'ترتیب', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'DESC', 'options' => ['DESC' => 'نزولی', 'ASC' => 'صعودی'], 'condition' => ['source!' => 'by_id']]);
        
        $this->end_controls_section();
        
        // --- Layout Section ---
        $this->start_controls_section('layout_section', ['label' => 'چیدمان']);
        $this->add_responsive_control('columns', ['label' => 'تعداد ستون‌ها', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 3, 'options' => [1=>1, 2=>2, 3=>3, 4=>4, 6=>6]]);
        $this->add_control('rows', ['label' => 'تعداد ردیف‌ها', 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 2]);
        $this->end_controls_section();

        // --- Style Tab ---
        $this->start_controls_section('style_card_section', ['label' => 'کارت', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
        $this->start_controls_tabs('card_style_tabs');
        $this->start_controls_tab('card_normal', ['label' => 'عادی']);
        $this->add_control('card_bg_color', ['label' => 'رنگ پس‌زمینه', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .nsm-service-card' => 'background-color: {{VALUE}};']]);
        $this->add_group_control(\Elementor\Group_Control_Box_Shadow::get_type(), ['name' => 'card_box_shadow', 'selector' => '{{WRAPPER}} .nsm-service-card']);
        $this->end_controls_tab();
        $this->start_controls_tab('card_hover', ['label' => 'هاور']);
        $this->add_control('card_bg_color_hover', ['label' => 'رنگ پس‌زمینه هاور', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .nsm-service-card:hover' => 'background-color: {{VALUE}};']]);
        $this->add_group_control(\Elementor\Group_Control_Box_Shadow::get_type(), ['name' => 'card_box_shadow_hover', 'selector' => '{{WRAPPER}} .nsm-service-card:hover']);
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->add_group_control(\Elementor\Group_Control_Border::get_type(), ['name' => 'card_border', 'selector' => '{{WRAPPER}} .nsm-service-card', 'separator' => 'before']);
        $this->add_responsive_control('card_border_radius', ['label' => 'انحنای کادر', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => ['px', '%'], 'selectors' => ['{{WRAPPER}} .nsm-service-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_responsive_control('card_padding', ['label' => 'فاصله داخلی', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => ['px', 'em', '%'], 'selectors' => ['{{WRAPPER}} .nsm-service-card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_responsive_control('card_margin', ['label' => 'فاصله خارجی', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => ['px', 'em', '%'], 'selectors' => ['{{WRAPPER}} .nsm-service-card' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->end_controls_section();

        $this->start_controls_section('style_image_section', ['label' => 'تصویر', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
        $this->add_responsive_control('image_height', ['label' => 'ارتفاع تصویر', 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => ['px', 'vh'], 'range' => ['px' => ['min' => 50, 'max' => 500]], 'selectors' => ['{{WRAPPER}} .nsm-service-card-thumb img' => 'height: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('image_border_radius', ['label' => 'انحنای کادر تصویر', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => ['px', '%'], 'selectors' => ['{{WRAPPER}} .nsm-service-card-thumb img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->end_controls_section();
        
        $this->start_controls_section('style_content_section', ['label' => 'محتوا', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
        $this->add_responsive_control('content_padding', ['label' => 'پدینگ داخلی', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => ['px', 'em', '%'], 'selectors' => ['{{WRAPPER}} .nsm-service-card-content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_control('title_heading', ['label' => 'عنوان', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before']);
        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'title_typography', 'selector' => '{{WRAPPER}} .nsm-service-card-title']);
        $this->start_controls_tabs('title_style_tabs');
        $this->start_controls_tab('title_normal', ['label' => 'عادی']);
        $this->add_control('title_color', ['label' => 'رنگ', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .nsm-service-card-title a' => 'color: {{VALUE}};']]);
        $this->end_controls_tab();
        $this->start_controls_tab('title_hover', ['label' => 'هاور']);
        $this->add_control('title_color_hover', ['label' => 'رنگ', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .nsm-service-card-title a:hover' => 'color: {{VALUE}};']]);
        $this->end_controls_tab();
        $this->end_controls_tabs();
        
        $this->add_control('meta_heading', ['label' => 'متا (تاریخ و قیمت)', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before']);
        $this->add_control('meta_color', ['label' => 'رنگ متا', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .nsm-service-card-meta' => 'color: {{VALUE}};']]);
        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'meta_typography', 'selector' => '{{WRAPPER}} .nsm-service-card-meta']);
        
        $this->add_control('excerpt_heading', ['label' => 'چکیده', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before']);
        $this->add_control('excerpt_color', ['label' => 'رنگ چکیده', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .nsm-service-card-excerpt' => 'color: {{VALUE}};']]);
        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'excerpt_typography', 'selector' => '{{WRAPPER}} .nsm-service-card-excerpt']);
        $this->end_controls_section();
        
        $this->start_controls_section('style_button_section', ['label' => 'دکمه', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'button_typography', 'selector' => '{{WRAPPER}} .nsm-service-card-button']);
        $this->start_controls_tabs('button_style_tabs');
        $this->start_controls_tab('button_normal', ['label' => 'عادی']);
        $this->add_control('button_bg_color', ['label' => 'رنگ پس‌زمینه', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .nsm-service-card-button' => 'background-color: {{VALUE}};']]);
        $this->add_control('button_text_color', ['label' => 'رنگ متن', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .nsm-service-card-button' => 'color: {{VALUE}};']]);
        $this->add_group_control(\Elementor\Group_Control_Border::get_type(), ['name' => 'button_border', 'selector' => '{{WRAPPER}} .nsm-service-card-button']);
        $this->end_controls_tab();
        $this->start_controls_tab('button_hover', ['label' => 'هاور']);
        $this->add_control('button_bg_color_hover', ['label' => 'رنگ پس‌زمینه', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .nsm-service-card-button:hover' => 'background-color: {{VALUE}};']]);
        $this->add_control('button_text_color_hover', ['label' => 'رنگ متن', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .nsm-service-card-button:hover' => 'color: {{VALUE}};']]);
        $this->add_control('button_border_color_hover', ['label' => 'رنگ کادر', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .nsm-service-card-button:hover' => 'border-color: {{VALUE}};']]);
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->add_responsive_control('button_border_radius', ['label' => 'انحنای کادر', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => ['px', '%'], 'selectors' => ['{{WRAPPER}} .nsm-service-card-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_responsive_control('button_padding', ['label' => 'پدینگ دکمه', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => ['px', 'em'], 'selectors' => ['{{WRAPPER}} .nsm-service-card-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $total_posts = (int)$settings['columns'] * (int)$settings['rows'];

        $args = [
            'post_type' => 'service',
            'posts_per_page' => $total_posts,
            'orderby' => $settings['orderby'],
            'order' => $settings['order'],
        ];

        if ($settings['source'] === 'by_id' && !empty($settings['post_ids'])) {
            $args['post__in'] = $settings['post_ids'];
            $args['posts_per_page'] = -1;
        } elseif ($settings['source'] === 'featured') {
            $args['meta_key'] = '_nsm_is_featured';
            $args['meta_value'] = 'yes';
        } elseif ($settings['source'] === 'related') {
             $terms = get_the_terms(get_the_ID(), 'service_category');
             if (!empty($terms) && !is_wp_error($terms)) {
                $term_ids = wp_list_pluck($terms, 'term_id');
                $args['tax_query'] = [['taxonomy' => 'service_category', 'field' => 'term_id', 'terms' => $term_ids]];
                $args['post__not_in'] = [get_the_ID()];
             }
        } elseif (!empty($settings['category_ids'])) {
             $args['tax_query'] = [['taxonomy' => 'service_category', 'field' => 'slug', 'terms' => $settings['category_ids']]];
        }

        $services_query = new WP_Query($args);
        if (!$services_query->have_posts()) {
            echo 'هیچ خدمتی یافت نشد.';
            return;
        }
        
        echo '<div class="nsm-services-grid" style="grid-template-columns: repeat(' . esc_attr($settings['columns']) . ', 1fr);">';
        while ($services_query->have_posts()) { 
            $services_query->the_post(); 
            echo NSM_Shortcodes::render_service_card(get_the_ID()); 
        }
        echo '</div>';
        wp_reset_postdata();
    }
}

class Elementor_NSM_Services_Carousel_Widget extends Elementor_NSM_Services_Grid_Widget {
    public function get_name() { return 'nsm_services_carousel'; }
    public function get_title() { return __( 'کاروسل خدمات نیلای', 'nilay-services' ); }
    public function get_icon() { return 'eicon-media-carousel'; }

    protected function register_controls() {
        parent::register_controls(); // Inherit all controls from Grid

        $this->start_controls_section('carousel_section', ['label' => 'تنظیمات کاروسل']);
        $this->add_control('autoplay', ['label' => 'پخش خودکار', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->add_control('speed', ['label' => 'سرعت پخش خودکار (میلی ثانیه)', 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 3000, 'condition' => ['autoplay' => 'yes']]);
        $this->add_control('navigation', ['label' => 'نمایش دکمه‌های ناوبری', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->end_controls_section();

        $this->start_controls_section('style_navigation_section', ['label' => 'ناوبری (دکمه‌های قبل/بعد)', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
        $this->add_control('nav_color', ['label' => 'رنگ دکمه‌ها', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .swiper-button-next, {{WRAPPER}} .swiper-button-prev' => 'color: {{VALUE}};']]);
        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $total_posts = (int)$settings['columns'] * (int)$settings['rows'];

        $args = [
            'post_type' => 'service',
            'posts_per_page' => $total_posts,
            'orderby' => $settings['orderby'],
            'order' => $settings['order'],
        ];

        if ($settings['source'] === 'by_id' && !empty($settings['post_ids'])) {
            $args['post__in'] = $settings['post_ids'];
        } elseif ($settings['source'] === 'featured') {
            $args['meta_key'] = '_nsm_is_featured';
            $args['meta_value'] = 'yes';
        } elseif (!empty($settings['category_ids'])) {
             $args['tax_query'] = [['taxonomy' => 'service_category', 'field' => 'slug', 'terms' => $settings['category_ids']]];
        }

        $services_query = new WP_Query($args);
        if (!$services_query->have_posts()) {
            echo 'هیچ خدمتی یافت نشد.';
            return;
        }

        $swiper_settings = [
            'autoplay' => ($settings['autoplay'] === 'yes') ? ['delay' => $settings['speed']] : false,
            'loop' => true,
            'navigation' => ($settings['navigation'] === 'yes') ? ['nextEl' => '.swiper-button-next', 'prevEl' => '.swiper-button-prev'] : false,
            'slidesPerView' => 1,
            'spaceBetween' => 30,
            'breakpoints' => [
                '640' => ['slidesPerView' => 1],
                '768' => ['slidesPerView' => 2],
                '1024' => ['slidesPerView' => $settings['columns'] ?: 3],
            ]
        ];
        ?>
        <div class="nsm-carousel-container">
            <div class="swiper nsm-services-carousel" data-settings='<?php echo esc_attr(json_encode($swiper_settings)); ?>'>
                <div class="swiper-wrapper">
                    <?php while ($services_query->have_posts()) { $services_query->the_post(); echo '<div class="swiper-slide">' . NSM_Shortcodes::render_service_card(get_the_ID()) . '</div>'; } ?>
                </div>
                <?php if ($settings['navigation'] === 'yes'): ?>
                    <div class="swiper-button-next"></div>
                    <div class="swiper-button-prev"></div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        wp_reset_postdata();
    }
}

class Elementor_NSM_Services_Filter_Widget extends NSM_Elementor_Widget_Base {
    public function get_name() { return 'nsm_services_filter'; }
    public function get_title() { return __( 'فیلتر خدمات نیلای', 'nilay-services' ); }
    public function get_icon() { return 'eicon-filter'; }

    protected function register_controls() {
        $this->start_controls_section('content_section', ['label' => 'محتوا']);
        $this->add_control('count', ['label' => 'تعداد اولیه', 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 9]);
        $this->add_responsive_control('columns', ['label' => 'تعداد ستون‌ها', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 3, 'options' => [1=>1, 2=>2, 3=>3, 4=>4]]);
        $this->end_controls_section();

        $this->start_controls_section('style_filter_buttons_section', ['label' => 'دکمه‌های فیلتر', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'filter_button_typography', 'selector' => '{{WRAPPER}} .nsm-filter-buttons button']);
        $this->add_responsive_control('filter_buttons_padding', ['label' => 'پدینگ دکمه‌ها', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => ['px', 'em'], 'selectors' => ['{{WRAPPER}} .nsm-filter-buttons button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->start_controls_tabs('filter_buttons_style_tabs');
        // Normal State
        $this->start_controls_tab('filter_button_normal', ['label' => 'عادی']);
        $this->add_control('filter_button_bg_color', ['label' => 'رنگ پس‌زمینه', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .nsm-filter-buttons button' => 'background-color: {{VALUE}};']]);
        $this->add_control('filter_button_text_color', ['label' => 'رنگ متن', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .nsm-filter-buttons button' => 'color: {{VALUE}};']]);
        $this->add_group_control(\Elementor\Group_Control_Border::get_type(), ['name' => 'filter_button_border', 'selector' => '{{WRAPPER}} .nsm-filter-buttons button']);
        $this->end_controls_tab();
        // Active State
        $this->start_controls_tab('filter_button_active', ['label' => 'فعال']);
        $this->add_control('filter_button_active_bg_color', ['label' => 'رنگ پس‌زمینه', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .nsm-filter-buttons button.active' => 'background-color: {{VALUE}};']]);
        $this->add_control('filter_button_active_text_color', ['label' => 'رنگ متن', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .nsm-filter-buttons button.active' => 'color: {{VALUE}};']]);
        $this->add_control('filter_button_active_border_color', ['label' => 'رنگ کادر', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .nsm-filter-buttons button.active' => 'border-color: {{VALUE}};']]);
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        echo NSM_Shortcodes::render_services_filter([
            'count' => $settings['count'],
            'columns' => $settings['columns'],
        ]);
    }
}

class Elementor_NSM_Single_Service_Widget extends NSM_Elementor_Widget_Base {
    public function get_name() { return 'nsm_single_service'; }
    public function get_title() { return __( 'کارت تکی خدمت', 'nilay-services' ); }
    public function get_icon() { return 'eicon-post'; }

    protected function register_controls() {
        $this->start_controls_section('content_section', ['label' => 'انتخاب خدمت']);
        $this->add_control('service_id', [
            'label' => 'خدمت را انتخاب کنید',
            'type' => \Elementor\Controls_Manager::SELECT2,
            'options' => $this->get_services_list(),
            'label_block' => true,
        ]);
        $this->end_controls_section();
        
        // Inherit style controls from Grid Widget for consistency
        $this->start_controls_section('style_card_section_single', ['label' => 'کارت', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
        $this->add_control('card_bg_color_single', ['label' => 'رنگ پس‌زمینه', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .nsm-service-card' => 'background-color: {{VALUE}};']]);
        $this->add_group_control(\Elementor\Group_Control_Border::get_type(), ['name' => 'card_border_single', 'selector' => '{{WRAPPER}} .nsm-service-card']);
        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $service_id = $settings['service_id'];
        if (empty($service_id)) {
            echo 'لطفا یک خدمت را از تنظیمات ویجت انتخاب کنید.';
            return;
        }
        echo NSM_Shortcodes::render_service_card($service_id);
    }
}

class Elementor_NSM_Services_List_Widget extends NSM_Elementor_Widget_Base {
    public function get_name() { return 'nsm_services_list'; }
    public function get_title() { return __( 'لیست خدمات نیلای', 'nilay-services' ); }
    public function get_icon() { return 'eicon-bullet-list'; }

    protected function register_controls() {
        // --- Query Section ---
        $this->start_controls_section('query_section', ['label' => 'کوئری']);
        $this->add_control('count', ['label' => 'تعداد', 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 5]);
        $this->end_controls_section();

        // Style Tab
        $this->start_controls_section('style_list_section', ['label' => 'لیست', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
        $this->add_control('title_color', ['label' => 'رنگ عنوان', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .nsm-service-list-item a' => 'color: {{VALUE}};']]);
        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'title_typography', 'selector' => '{{WRAPPER}} .nsm-service-list-item a']);
        $this->add_control('meta_color', ['label' => 'رنگ متا', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .nsm-service-list-meta' => 'color: {{VALUE}};']]);
        $this->add_responsive_control('item_padding', ['label' => 'پدینگ آیتم', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => ['px', 'em'], 'selectors' => ['{{WRAPPER}} .nsm-service-list-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $args = ['post_type' => 'service', 'posts_per_page' => $settings['count']];
        $services_query = new WP_Query($args);

        if ($services_query->have_posts()) {
            echo '<ul class="nsm-service-list">';
            while ($services_query->have_posts()) {
                $services_query->the_post();
                echo '<li class="nsm-service-list-item">';
                echo '<a href="' . get_permalink() . '">' . get_the_title() . '</a>';
                echo '<div class="nsm-service-list-meta">' . get_the_date() . '</div>';
                echo '</li>';
            }
            echo '</ul>';
            wp_reset_postdata();
        }
    }
}

class Elementor_NSM_Service_Details_Widget extends NSM_Elementor_Widget_Base {
    public function get_name() { return 'nsm_service_details'; }
    public function get_title() { return __( 'جزئیات خدمت نیلای', 'nilay-services' ); }
    public function get_icon() { return 'eicon-document-file'; }

    protected function register_controls() {
        $this->start_controls_section('content_section', ['label' => 'محتوا']);
        $this->add_control('data_source', [
            'label' => 'فیلد مورد نظر را انتخاب کنید',
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => $this->get_all_meta_keys(),
            'label_block' => true,
        ]);
        $this->add_control('skin', [
            'label' => 'پوسته نمایش', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'simple',
            'options' => [
                'simple' => 'ساده (متن)',
                'profile' => 'پروفایل (برای مدرس/مشاور)',
                'timeline' => 'تایم لاین (برای برنامه زمانبندی)',
                'pricing_table' => 'جدول قیمت (برای بلیت/پکیج)',
                'list' => 'لیست (برای خدمات تور)',
            ]
        ]);
        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $post_id = get_the_ID();
        if (!$post_id || get_post_type($post_id) !== 'service') {
            echo 'این ویجت فقط در صفحات تکی خدمت کار می‌کند.';
            return;
        }

        $key = $settings['data_source'];
        $value = get_post_meta($post_id, $key, true);
        if (empty($value)) return;

        switch ($settings['skin']) {
            case 'profile':
                // Logic to display instructor/consultant profile
                break;
            case 'timeline':
                // Logic to display agenda
                break;
            // etc.
            default:
                if(is_array($value)) {
                    // Handle repeater fields gracefully
                    echo '<ul>';
                    foreach($value as $item) {
                        echo '<li>' . implode(', ', $item) . '</li>';
                    }
                    echo '</ul>';
                } else {
                    echo wpautop(wp_kses_post($value));
                }
        }
    }
}
