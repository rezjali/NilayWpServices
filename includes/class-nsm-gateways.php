<?php
/**
 * کلاس مدیریت متمرکز درگاه‌های پرداخت و پنل‌های پیامکی
 *
 * @package Nilay_Service_Manager/Includes
 * @version 4.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class NSM_Gateways {

    public function __construct() {
        // این هوک منتظر بازگشت کاربر از درگاه پرداخت است
        add_action( 'init', [ $this, 'handle_payment_verification' ] );
    }

    /**
     * شروع فرآیند پرداخت
     *
     * @param int $service_id شناسه خدمت
     * @param array $user_data داده‌های کاربر شامل نام و موبایل
     */
    public static function process_payment( $service_id, $user_data ) {
        $price = get_post_meta( $service_id, '_nsm_price', true );
        $general_options = get_option( 'nsm_general_options' );
        $active_gateway = $general_options['active_payment_gateway'] ?? 'none';

        if ( $active_gateway === 'none' || ! $price || $price <= 0 ) {
            wp_die( nsm_get_string( 'error_payment_unavailable', 'امکان پرداخت برای این خدمت در حال حاضر وجود ندارد.' ) );
        }

        $order_id = time() . rand( 100, 999 );

        $transient_data = [
            'service_id'  => $service_id,
            'user_name'   => $user_data['user_name'],
            'user_mobile' => $user_data['user_mobile'],
            'price'       => $price,
        ];
        set_transient( 'nsm_payment_' . $order_id, $transient_data, HOUR_IN_SECONDS );

        $callback_url = add_query_arg( [
            'nsm_action' => 'verify_payment',
            'order_id'   => $order_id,
        ], site_url( '/' ) );

        $description = nsm_get_string( 'service', 'خدمت' ) . ': ' . get_the_title( $service_id );

        if ( $active_gateway === 'zarinpal' ) {
            self::zarinpal_request( $price, $callback_url, $description, $user_data['user_mobile'] );
        } elseif ( $active_gateway === 'zibal' ) {
            self::zibal_request( $price, $callback_url, $description, $order_id );
        } else {
            self::redirect_with_error( get_permalink( $service_id ), 'error_invalid_gateway' );
        }
    }

    private static function zarinpal_request( $amount, $callback_url, $description, $mobile ) {
        $payment_options = get_option( 'nsm_payment_options' );
        $merchant_id = $payment_options['zarinpal_merchant_id'] ?? '';
        if ( empty( $merchant_id ) ) {
            self::redirect_with_error( get_permalink( $_POST['service_id'] ), 'gateway_not_configured' );
        }

        $data = [
            'merchant_id'  => $merchant_id,
            'amount'       => $amount * 10, // تبدیل تومان به ریال
            'callback_url' => add_query_arg( 'gateway', 'zarinpal', $callback_url ),
            'description'  => $description,
            'metadata'     => [ 'mobile' => $mobile ],
        ];

        $response = wp_remote_post( 'https://api.zarinpal.com/pg/v4/payment/request.json', [ 'body' => json_encode( $data ), 'headers' => [ 'Content-Type' => 'application/json' ] ] );

        if ( is_wp_error( $response ) ) {
            self::redirect_with_error( get_permalink( $_POST['service_id'] ), 'connection_failed' );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['data']['code'] ) && $body['data']['code'] == 100 ) {
            wp_redirect( 'https://www.zarinpal.com/pg/StartPay/' . $body['data']['authority'] );
            exit;
        } else {
            $error_message = $body['errors']['message'] ?? 'خطای نامشخص از درگاه';
            self::redirect_with_error( get_permalink( $_POST['service_id'] ), urlencode( $error_message ) );
        }
    }

    private static function zibal_request( $amount, $callback_url, $description, $order_id ) {
        $payment_options = get_option( 'nsm_payment_options' );
        $merchant_id = $payment_options['zibal_merchant_id'] ?? '';
        if ( empty( $merchant_id ) ) {
            self::redirect_with_error( get_permalink( $_POST['service_id'] ), 'gateway_not_configured' );
        }

        $data = [
            'merchant'     => $merchant_id,
            'amount'       => $amount * 10, // تبدیل تومان به ریال
            'callbackUrl'  => add_query_arg( 'gateway', 'zibal', $callback_url ),
            'description'  => $description,
            'orderId'      => $order_id,
        ];

        $response = wp_remote_post( 'https://gateway.zibal.ir/v1/request', [ 'body' => json_encode( $data ), 'headers' => [ 'Content-Type' => 'application/json' ] ] );

        if ( is_wp_error( $response ) ) {
            self::redirect_with_error( get_permalink( $_POST['service_id'] ), 'connection_failed' );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['result'] ) && $body['result'] == 100 ) {
            wp_redirect( 'https://gateway.zibal.ir/start/' . $body['trackId'] );
            exit;
        } else {
            $error_message = $body['message'] ?? 'خطای نامشخص از درگاه زیبال';
            self::redirect_with_error( get_permalink( $_POST['service_id'] ), urlencode( $error_message ) );
        }
    }

    public function handle_payment_verification() {
        if ( ! isset( $_GET['nsm_action'] ) || $_GET['nsm_action'] !== 'verify_payment' ) {
            return;
        }

        $gateway = sanitize_key( $_GET['gateway'] ?? '' );
        $order_id = sanitize_text_field( $_GET['order_id'] ?? '' );
        $transient_data = get_transient( 'nsm_payment_' . $order_id );

        if ( ! $order_id || ! $transient_data ) {
            wp_die( 'اطلاعات تراکنش یافت نشد یا منقضی شده است.' );
        }
        
        $service_id = $transient_data['service_id'];

        if ( $gateway === 'zarinpal' ) {
            $this->zarinpal_verify( $order_id, $transient_data );
        } elseif ( $gateway === 'zibal' ) {
            $this->zibal_verify( $order_id, $transient_data );
        } else {
            self::redirect_with_error( get_permalink( $service_id ), 'invalid_gateway' );
        }
    }

    private function zarinpal_verify( $order_id, $transient_data ) {
        $service_id = $transient_data['service_id'];
        $authority = sanitize_text_field( $_GET['Authority'] ?? '' );

        if ( empty( $_GET['Status'] ) || $_GET['Status'] !== 'OK' ) {
            delete_transient( 'nsm_payment_' . $order_id );
            self::redirect_with_error( get_permalink( $service_id ), 'payment_cancelled' );
        }

        $payment_options = get_option( 'nsm_payment_options' );
        $merchant_id = $payment_options['zarinpal_merchant_id'] ?? '';
        $amount = $transient_data['price'] * 10; // ریال

        $data = [ 'merchant_id' => $merchant_id, 'amount' => $amount, 'authority' => $authority ];
        $response = wp_remote_post( 'https://api.zarinpal.com/pg/v4/payment/verify.json', [ 'body' => json_encode( $data ), 'headers' => [ 'Content-Type' => 'application/json' ] ] );

        if ( is_wp_error( $response ) ) {
            self::redirect_with_error( get_permalink( $service_id ), 'verify_failed' );
        }
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['data']['code'] ) && ( $body['data']['code'] == 100 || $body['data']['code'] == 101 ) ) {
            $ref_id = $body['data']['ref_id'];
            self::complete_registration( $transient_data, $ref_id );
            delete_transient( 'nsm_payment_' . $order_id );
            self::redirect_with_success( get_permalink( $service_id ), $ref_id );
        } else {
            delete_transient( 'nsm_payment_' . $order_id );
            $error_message = $body['errors']['message'] ?? 'خطای نامشخص در تایید تراکنش';
            self::redirect_with_error( get_permalink( $service_id ), urlencode( $error_message ) );
        }
    }

    private function zibal_verify( $order_id, $transient_data ) {
        $service_id = $transient_data['service_id'];

        if ( empty( $_GET['success'] ) || $_GET['success'] != 1 ) {
            delete_transient( 'nsm_payment_' . $order_id );
            self::redirect_with_error( get_permalink( $service_id ), 'payment_cancelled' );
        }

        $payment_options = get_option( 'nsm_payment_options' );
        $merchant_id = $payment_options['zibal_merchant_id'] ?? '';
        $trackId = intval( $_GET['trackId'] );

        $data = [ 'merchant' => $merchant_id, 'trackId' => $trackId ];
        $response = wp_remote_post( 'https://gateway.zibal.ir/v1/verify', [ 'body' => json_encode( $data ), 'headers' => [ 'Content-Type' => 'application/json' ] ] );

        if ( is_wp_error( $response ) ) {
            self::redirect_with_error( get_permalink( $service_id ), 'verify_failed' );
        }
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['result'] ) && $body['result'] == 100 ) {
            $ref_id = $body['refNumber'];
            self::complete_registration( $transient_data, $ref_id );
            delete_transient( 'nsm_payment_' . $order_id );
            self::redirect_with_success( get_permalink( $service_id ), $ref_id );
        } else {
            delete_transient( 'nsm_payment_' . $order_id );
            $error_message = $body['message'] ?? 'خطای نامشخص در تایید تراکنش';
            self::redirect_with_error( get_permalink( $service_id ), urlencode( $error_message ) );
        }
    }

    /**
     * تکمیل فرآیند ثبت نام پس از پرداخت موفق یا ثبت نام رایگان
     */
    public static function complete_registration( $data, $transaction_id = '' ) {
        $is_paid = ! empty( $transaction_id );
        $user_template_key = $is_paid ? 'user_paid_reg' : 'user_free_reg';
        $admin_template_key = $is_paid ? 'admin_paid_reg' : 'admin_free_reg';

        // ارسال پیامک به کاربر
        self::send_sms( $user_template_key, $data['user_mobile'], $data, $transaction_id );
        
        // ارسال پیامک به مدیر
        $general_options = get_option('nsm_general_options');
        $admin_mobile = $general_options['admin_mobile_number'] ?? '';
        if ( ! empty( $admin_mobile ) ) {
            self::send_sms( $admin_template_key, $admin_mobile, $data, $transaction_id );
        }
    }

    /**
     * ارسال پیامک بر اساس تنظیمات
     */
    public static function send_sms( $template_key, $recipient, $data, $transaction_id = '' ) {
        $general_options = get_option( 'nsm_general_options' );
        $sms_provider = $general_options['active_sms_provider'] ?? 'none';
        if ( $sms_provider === 'none' || empty($recipient) ) {
            return false;
        }

        $notification_options = get_option( 'nsm_notification_options' );
        $template_name_or_code = $notification_options[ $template_key ] ?? '';
        if ( empty( $template_name_or_code ) ) {
            return false;
        }

        $service_name = get_the_title($data['service_id']);
        $user_name = $data['user_name'];

        if ( $sms_provider === 'kavenegar' ) {
            self::kavenegar_send_lookup( $recipient, $template_name_or_code, $user_name, $service_name, $transaction_id );
        } elseif ( $sms_provider === 'farazsms' ) {
            $params = [
                'user_name'      => $user_name,
                'service_name'   => $service_name,
                'transaction_id' => $transaction_id,
            ];
            self::farazsms_send_pattern( $recipient, $template_name_or_code, $params );
        }

        return true;
    }

    private static function kavenegar_send_lookup( $receptor, $template, $token, $token2 = '', $token3 = '' ) {
        $sms_options = get_option( 'nsm_sms_options' );
        $api_key = $sms_options['kavenegar_api_key'] ?? '';
        if ( empty( $api_key ) ) return;

        $url = sprintf(
            'https://api.kavenegar.com/v1/%s/verify/lookup.json?receptor=%s&template=%s&token=%s&token2=%s&token3=%s',
            $api_key,
            urlencode( $receptor ),
            urlencode( $template ),
            urlencode( $token ),
            urlencode( $token2 ),
            urlencode( $token3 )
        );
        wp_remote_get( $url, [ 'timeout' => 20 ] );
    }
    
    private static function farazsms_send_pattern( $receptor, $pattern_code, $params ) {
        $sms_options = get_option( 'nsm_sms_options' );
        $api_key = $sms_options['farazsms_api_key'] ?? '';
        $sender = $sms_options['farazsms_sender_number'] ?? '';
        if ( empty( $api_key ) || empty($sender) ) return;

        $url = "https://ippanel.com/patterns/api/v1/send";
        $body = [
            'pattern_code' => $pattern_code,
            'originator'   => $sender,
            'recipient'    => $receptor,
            'values'       => $params,
        ];

        wp_remote_post($url, [
            'method'  => 'POST',
            'timeout' => 20,
            'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'AccessKey ' . $api_key],
            'body'    => json_encode($body)
        ]);
    }

    // --- توابع تست اتصال ---

    public static function verify_zarinpal_credentials( $api_key ) {
        $response = wp_remote_post( 'https://api.zarinpal.com/pg/v4/payment/request.json', [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => json_encode( [
                'merchant_id'  => $api_key,
                'amount'       => 10000,
                'callback_url' => home_url(),
                'description'  => 'تست اتصال افزونه مدیریت خدمات نیلای',
            ] ),
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'message' => 'خطا در ارتباط با زرین‌پال: ' . $response->get_error_message() ];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $code = $body['errors']['code'] ?? ( $body['data']['code'] ?? null );

        if ( $code == 100 ) {
            return [ 'success' => true, 'message' => 'اتصال با موفقیت برقرار شد.' ];
        } elseif ( $code == -9 ) {
            return [ 'success' => false, 'message' => 'مرچنت کد نامعتبر است. (کد خطا: ' . $code . ')' ];
        } else {
            return [ 'success' => false, 'message' => 'خطا: ' . ( $body['errors']['message'] ?? 'ناشناخته' ) ];
        }
    }

    public static function verify_zibal_credentials( $api_key ) {
        $response = wp_remote_post( 'https://gateway.zibal.ir/v1/request', [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => json_encode( [
                'merchant'     => $api_key,
                'amount'       => 10000,
                'callbackUrl'  => home_url(),
                'description'  => 'تست اتصال افزونه مدیریت خدمات نیلای',
            ] ),
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'message' => 'خطا در ارتباط با زیبال: ' . $response->get_error_message() ];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['result'] ) && $body['result'] == 100 ) {
            return [ 'success' => true, 'message' => 'اتصال با موفقیت برقرار شد.' ];
        } else {
            return [ 'success' => false, 'message' => 'خطا: ' . ( $body['message'] ?? 'مرچنت کد نامعتبر است.' ) ];
        }
    }
    
    public static function verify_kavenegar_credentials( $api_key ) {
        $url = 'https://api.kavenegar.com/v1/' . $api_key . '/account/info.json';
        $response = wp_remote_get( $url );

        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'message' => 'خطا در ارتباط با کاوه نگار: ' . $response->get_error_message() ];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $return_status = $body['return']['status'] ?? 0;

        if ( $return_status == 200 ) {
            $credit = $body['entries']['remaincredit'] ?? 'نامشخص';
            return [ 'success' => true, 'message' => 'اتصال موفق. اعتبار: ' . number_format( $credit ) ];
        } else {
            return [ 'success' => false, 'message' => 'خطا: ' . ( $body['return']['message'] ?? 'کلید API نامعتبر است.' ) ];
        }
    }

    public static function verify_farazsms_credentials( $api_key ) {
         $url = 'http://ippanel.com/api/v1/user/credit';
         $response = wp_remote_get( $url, [
            'headers' => [ 'Authorization' => 'AccessKey ' . $api_key ]
         ] );

         if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'message' => 'خطا در ارتباط با فراز پیامک: ' . $response->get_error_message() ];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $status_code = $body['status']['code'] ?? null;

        if ( $status_code == '200' ) {
             $credit = $body['data']['credit'] ?? 'نامشخص';
             return [ 'success' => true, 'message' => 'اتصال موفق. اعتبار: ' . number_format( $credit ) ];
        } else {
             return [ 'success' => false, 'message' => 'خطا: ' . ( $body['status']['message'] ?? 'کلید API نامعتبر است.' ) ];
        }
    }

    // --- توابع کمکی ---

    private static function redirect_with_error( $url, $error_code ) {
        wp_redirect( add_query_arg( ['nsm_payment_status' => 'error', 'nsm_error' => $error_code], $url ) );
        exit;
    }

    private static function redirect_with_success( $url, $ref_id ) {
        wp_redirect( add_query_arg( ['nsm_payment_status' => 'success', 'track_id' => $ref_id], $url ) );
        exit;
    }
}
