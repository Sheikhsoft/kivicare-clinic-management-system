<?php
namespace App\Controllers;
use App\baseClasses\KCBase;
use App\baseClasses\KCRequest;


class KCWoocommerceController extends KCBase {

	private $request;

    public function __construct()
    {
        $this->request = new KCRequest();
    }

    public function bookAppointment () {

        $request_data = $this->request->getInputs();

        $this->setCustomerCookie();

		WC()->cart->empty_cart();

		WC()->cart->add_to_cart( $this->bookneticProduct(), 1, '', [], ['booknetic_appointment_id' => 213] );

        return [
			'status'  => true,
			'message' => esc_html__('Appointment Booked successfully','kc-lang'),
            'woocommerce_redirect' => wc_get_cart_url()
        ];

        wp_die();
    }

    private static function setCustomerCookie()
	{
		if ( WC()->session && WC()->session instanceof \WC_Session_Handler && WC()->session->get_session_cookie() === false )
		{
			WC()->session->set_customer_session_cookie( true );
		}

		return true;
    }

    public static function paymentMethodIsEnabled()
	{
		if( !self::woocommerceIsEnabled() )
		{
			return false;
		}

		// $methodIsActive = Helper::getOption('woocommerce_enabled', 'off');

        // return $methodIsActive == 'on';
        
        return true ;
    }
    
    public static function woocommerceIsEnabled()
	{
		return class_exists( 'WooCommerce', false );
	}
    
    public static function bookneticProduct()
	{
		if( !(new self())->paymentMethodIsEnabled() )
			return 0;

		$productId = 213;

		if ( $productId ) {
			$productInf = wc_get_product( $productId );
		}

		if( !$productId || !$productInf || !$productInf->exists() || $productInf->get_status() != 'publish' )
		{
			$productId = wp_insert_post([
				'post_title'    => 'Appointment Fees',
				'post_type'     => 'product',
				'post_status'   => 'publish'
			]);

			// Helper::setOption('woocommerce_product_id', 213);

			// set product is simple/variable/grouped

			wp_set_object_terms( $productId, 'simple', 'product_type' );

			update_post_meta( $productId, '_visibility', 'hidden' );
			update_post_meta( $productId, '_stock_status', 'instock');
			update_post_meta( $productId, 'total_sales', '0' );
			update_post_meta( $productId, '_downloadable', 'no' );
			update_post_meta( $productId, '_virtual', 'yes' );
			update_post_meta( $productId, '_regular_price', 520 );
			update_post_meta( $productId, '_sale_price', 420 );
			update_post_meta( $productId, '_purchase_note', '' );
			update_post_meta( $productId, '_featured', 'no' );
			update_post_meta( $productId, '_weight', '' );
			update_post_meta( $productId, '_length', '' );
			update_post_meta( $productId, '_width', '' );
			update_post_meta( $productId, '_height', '' );
			update_post_meta( $productId, '_sku', '' );
			update_post_meta( $productId, '_product_attributes', [] );
			update_post_meta( $productId, '_sale_price_dates_from', '' );
			update_post_meta( $productId, '_sale_price_dates_to', '' );
			update_post_meta( $productId, '_price', 422 );
			update_post_meta( $productId, '_sold_individually', 'yes' );
			update_post_meta( $productId, '_manage_stock', 'no' );
			update_post_meta( $productId, '_backorders', 'no' );
			wc_update_product_stock($productId, 0, 'set');
			update_post_meta( $productId, '_stock', '' );
		}

		return $productId;
	}



}