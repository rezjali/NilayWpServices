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
}

class Elementor_NSM_Services_Grid_Widget extends NSM_Elementor_Widget_Base {
    public function get_name() { return 'nsm_services_grid'; }
    public function get_title() { return __( 'گرید خدمات نیلای', 'nilay-services' ); }
    public function get_icon() { return 'eicon-gallery-grid'; }

    protected function register_controls() {
        // Content Tab
        $this->start_controls_section('content_section', ['label' => 'محتوا و کوئری']);
        $this->add_control('count', ['label' => 'تعداد', 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 6]);
        $this->add_responsive_control('columns', ['label' => 'تعداد ستون‌ها', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 3, 'options' => [1=>1, 2=>2, 3=>3, 4=>4]]);
        $this->end_controls_section();

        // Style Tab
        $this->start_controls_section('style_card_section', ['label' => 'کارت', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
        $this->add_control('card_bg_color', ['label' => 'رنگ پس‌زمینه', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .nsm-service-card' => 'background-color: {{VALUE}};']]);
        $this->add_group_control(\Elementor\Group_Control_Border::get_type(), ['name' => 'card_border', 'selector' => '{{WRAPPER}} .nsm-service-card']);
        $this->add_control('card_border_radius', ['label' => 'انحنای کادر', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 50]], 'selectors' => ['{{WRAPPER}} .nsm-service-card' => 'border-radius: {{SIZE}}{{UNIT}};']]);
        $this->add_group_control(\Elementor\Group_Control_Box_Shadow::get_type(), ['name' => 'card_box_shadow', 'selector' => '{{WRAPPER}} .nsm-service-card']);
        $this->end_controls_section();

        $this->start_controls_section('style_title_section', ['label' => 'عنوان', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
        $this->add_control('title_color', ['label' => 'رنگ عنوان', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .nsm-service-card-title a' => 'color: {{VALUE}};']]);
        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'title_typography', 'selector' => '{{WRAPPER}} .nsm-service-card-title']);
        $this->end_controls_section();
        
        $this->start_controls_section('style_button_section', ['label' => 'دکمه', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
        $this->add_control('button_bg_color', ['label' => 'رنگ پس‌زمینه', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .nsm-service-card-button' => 'background-color: {{VALUE}};']]);
        $this->add_control('button_text_color', ['label' => 'رنگ متن', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .nsm-service-card-button' => 'color: {{VALUE}};']]);
        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'button_typography', 'selector' => '{{WRAPPER}} .nsm-service-card-button']);
        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        echo NSM_Shortcodes::render_services_grid([
            'count' => $settings['count'],
            'columns' => $settings['columns'],
        ]);
    }
}

class Elementor_NSM_Services_Carousel_Widget extends NSM_Elementor_Widget_Base {
    public function get_name() { return 'nsm_services_carousel'; }
    public function get_title() { return __( 'کاروسل خدمات نیلای', 'nilay-services' ); }
    public function get_icon() { return 'eicon-media-carousel'; }

    protected function register_controls() {
        $this->start_controls_section('content_section', ['label' => 'محتوا']);
        $this->add_control('count', ['label' => 'تعداد', 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 5]);
        $this->add_responsive_control('columns', ['label' => 'تعداد ستون‌ها', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 3, 'options' => [1=>1, 2=>2, 3=>3, 4=>4]]);
        $this->add_control('autoplay', ['label' => 'پخش خودکار', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->end_controls_section();
        
        // Style Tab (similar to Grid)
        $this->start_controls_section('style_card_section_carousel', ['label' => 'کارت', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
        $this->add_control('card_bg_color_carousel', ['label' => 'رنگ پس‌زمینه', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .nsm-service-card' => 'background-color: {{VALUE}};']]);
        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        echo NSM_Shortcodes::render_services_carousel([
            'count' => $settings['count'],
            'columns' => $settings['columns'],
            'autoplay' => ($settings['autoplay'] === 'yes') ? 'true' : 'false',
        ]);
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
        $this->start_controls_tabs('filter_buttons_style_tabs');
        // Normal State
        $this->start_controls_tab('filter_button_normal', ['label' => 'عادی']);
        $this->add_control('filter_button_bg_color', ['label' => 'رنگ پس‌زمینه', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .nsm-filter-buttons button' => 'background-color: {{VALUE}};']]);
        $this->add_control('filter_button_text_color', ['label' => 'رنگ متن', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .nsm-filter-buttons button' => 'color: {{VALUE}};']]);
        $this->end_controls_tab();
        // Active State
        $this->start_controls_tab('filter_button_active', ['label' => 'فعال']);
        $this->add_control('filter_button_active_bg_color', ['label' => 'رنگ پس‌زمینه', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .nsm-filter-buttons button.active' => 'background-color: {{VALUE}};']]);
        $this->add_control('filter_button_active_text_color', ['label' => 'رنگ متن', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .nsm-filter-buttons button.active' => 'color: {{VALUE}};']]);
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
    public function get_title() { return __( 'نمایش تکی خدمت', 'nilay-services' ); }
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
        
        // Style Tab (same as Grid)
        $this->start_controls_section('style_card_section_single', ['label' => 'کارت', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
        $this->add_control('card_bg_color_single', ['label' => 'رنگ پس‌زمینه', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .nsm-service-card' => 'background-color: {{VALUE}};']]);
        $this->add_group_control(\Elementor\Group_Control_Border::get_type(), ['name' => 'card_border_single', 'selector' => '{{WRAPPER}} .nsm-service-card']);
        $this->end_controls_section();
    }

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
