//Massiel Sequeira

<?php
//Añadimos nuestra propia clase extendiendo de WC_Payment_Gateway
class EpayG_Payment_Gateway extends WC_Payment_Gateway {

  // Configuracion del id, descripcion y otros valores de la pasarela de pago
  function __construct() {
    //ID global de nuestra pasarela de pago
    $this->id = "epayg_payment_gateway";

    //Titulo de la pasarela que aparecera con las demas pasarelas de pago activas
    $this->method_title = __( "EpayG", 'epayg-payment-gateway' );

    //Breve descripcion de nuestra pasarela de pago
    $this->method_description = __( "Metodo de pago rapido atravez de tarjeta de credito o debito", 'epayg-payment-gateway' );

    //Titulo que usaremos dentro de las configuraciones de nuestra pasarela
    $this->title = __( "EpayG", 'epayg-payment-gateway' );

    //Icono de nuestra pasarela
    $this->icon = apply_filters( 'woocommerce_gateway_icon', plugins_url('\images\icon.png', __FILE__) );

    //integramos los campos de pago
    $this->has_fields = true;

    // Añadimos un soporte de woocommerce para añadir el forms de la tarjeta de pago
    $this->supports = array( 'default_credit_card_form' );

    // Definimos nuestra configuracion y la cargamos en un metodo llamado init_form_fields()
    $this->init_form_fields();

    //llamamos a init settings para cargarlas en variables
    $this->init_settings();

   //Convertimos estas  configuraciones en variables para poder usarlas
    foreach ( $this->settings as $setting_key => $value ) {
      $this->$setting_key = $value;
    }

    //añadimos una accion para verificar el certificado SSL
    add_action( 'admin_notices', array( $this,  'do_ssl_check' ) );

    // Guardamos las configuraciones verificamos si es el administrador de la pagina y actualizamos la configurcion
    if ( is_admin() ) {
      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    }
  } // Fin del constructor


  // Construimos los campos de administracion del plugin para el administrador
  public function init_form_fields() {
    $this->form_fields = array(
      'enabled' => array(
        //funcion de activar o desactivar el plugin
        'title'   => __( 'Activar / Desactivar', 'epayg-payment-gateway' ),
        'label'   => __( 'Activar este metodo de pago', 'epayg-payment-gateway' ),
        'type'    => 'checkbox',
        'default' => 'no',
      ),
      'title' => array(
        //titulo para el proceso de pago
        'title'   => __( 'Título', 'epayg-payment-gateway' ),
        'type'    => 'text',
        'desc_tip'  => __( 'Título de pago que el cliente verá durante el proceso de pago.', 'epayg-payment-gateway' ),
        'default' => __( 'EpayG', 'epayg-payment-gateway' ),
      ),
      'description' => array(
        //breve descripcion
        'title'   => __( 'Descripción', 'epayg-payment-gateway' ),
        'type'    => 'textarea',
        'desc_tip'  => __( 'Descripción de pago que el cliente verá durante el proceso de pago.', 'epayg-payment-gateway' ),
        'default' => __( 'Pague con seguridad usando su tarjeta de crédito.', 'epayg-payment-gateway' ),
        'css'   => 'max-width:350px;'
      ),
      // INIT------> Prueba de authorize.net <------
      'api_login' => array(
        'title'    => __( 'API Login', 'epayg-payment-gateway' ),
        'type'    => 'text',
        'desc_tip'  => __( 'Esta llave la provee Authorize.net al registrarse', 'epayg-payment-gateway' ),
      ),
      'trans_key' => array(
        'title'    => __( 'Llave de transaccion', 'epayg-payment-gateway' ),
        'type'    => 'password',
        'desc_tip'  => __( 'Esta llave la provee Authorize.net al registrarse', 'epayg-payment-gateway' ),
      ),
      'environment' => array(
        'title'    => __( 'Modo de prueba por authorize.net', 'epayg-payment-gateway' ),
        'label'    => __( 'Habilitar el modo de prueba', 'epayg-payment-gateway' ),
        'type'    => 'checkbox',
        'description' => __( 'Utiliza este modo para testear el plugin de pago EpayG con Authorize.net', 'epayg-payment-gateway' ),
        'default'  => 'no',
      ),
      // END-------> Prueba de authorize.net <------
      /*
      //key id para el hash de pago
      'key_id' => array(
        'title'   => __( 'Key id', 'epayg_payment' ),
        'type'    => 'text',
        'desc_tip'  => __( 'ID de clave de seguridad del panel de control del comerciante.', 'epayg_payment' ),
        'default' => '',
      ),
      //api key para el hash de pago
      'api_key' => array(
        'title'   => __( 'Api key', 'epayg_payment' ),
        'type'    => 'text',
        'desc_tip'  => __( 'ID de clave de api del panel de control del comerciante.', 'epayg_payment' ),
        'default' => '',
      ),
      */
    );
  }

  //Empezamos el proceso de pago
  public function process_payment( $order_id ) {
    global $woocommerce;

    //obtenemos la informacion de esta orden para saber a quien y cuanto se va a cobrar
    $customer_order = new WC_Order( $order_id );

    // INIT------> Prueba de authorize.net <------
    // chequeamos la trancision
    $environment = ( $this->environment == "yes" ) ? 'TRUE' : 'FALSE';
    // Decidimos en que URL vamos a publicar
    $environment_url = ( "FALSE" == $environment )
                           ? 'https://secure.authorize.net/gateway/transact.dll'
           : 'https://test.authorize.net/gateway/transact.dll';
    // END------> Prueba de authorize.net <------

    //URl de la API propia
    //$environment_url = 'https://EpayG/payment_Gateway.com/';

    //$time = time();

    //$key_id = $this->key_id;

    // INIT------> Prueba de authorize.net <------
    $payload = array(
     // Credenciales de la API
     "x_tran_key"             => $this->trans_key,
     "x_login"                => $this->api_login,
     "x_version"              => "3.1",

     // Orden total
     "x_amount"               => $customer_order->order_total,

     // Informacion de la tarjeta de credito
     "x_card_num"             => str_replace( array(' ', '-' ), '', $_POST['epayg_payment_gateway-card-number'] ),
     "x_card_code"            => ( isset( $_POST['epayg_payment_gateway-card-cvc'] ) ) ? $_POST['epayg_payment_gateway-card-cvc'] : '',
     "x_exp_date"             => str_replace( array( '/', ' '), '', $_POST['epayg_payment_gateway-card-expiry'] ),

     "x_type"                 => 'AUTH_CAPTURE',
     "x_invoice_num"          => str_replace( "#", "", $customer_order->get_order_number() ),
     "x_test_request"         => $environment,
     "x_delim_char"           => '|',
     "x_encap_char"           => '',
     "x_delim_data"           => "TRUE",
     "x_relay_response"       => "FALSE",
     "x_method"               => "CC",

     // Billing Information
     "x_first_name"           => $customer_order->billing_first_name,
     "x_last_name"            => $customer_order->billing_last_name,
     "x_address"              => $customer_order->billing_address_1,
     "x_city"                => $customer_order->billing_city,
     "x_state"                => $customer_order->billing_state,
     "x_zip"                  => $customer_order->billing_postcode,
     "x_country"              => $customer_order->billing_country,
     "x_phone"                => $customer_order->billing_phone,
     "x_email"                => $customer_order->billing_email,

     // Shipping Information
     "x_ship_to_first_name"   => $customer_order->shipping_first_name,
     "x_ship_to_last_name"    => $customer_order->shipping_last_name,
     "x_ship_to_company"      => $customer_order->shipping_company,
     "x_ship_to_address"      => $customer_order->shipping_address_1,
     "x_ship_to_city"         => $customer_order->shipping_city,
     "x_ship_to_country"      => $customer_order->shipping_country,
     "x_ship_to_state"        => $customer_order->shipping_state,
     "x_ship_to_zip"          => $customer_order->shipping_postcode,

     // information customer
     "x_cust_id"              => $customer_order->user_id,
     "x_customer_ip"          => $_SERVER['REMOTE_ADDR'],

   );
     // END------> Prueba de authorize.net <------

    /*
    $orderid = str_replace( "#", "", $customer_order->get_order_number() );
    //construccion del hash de entrada
    $hash = md5($orderid."|".$customer_order->order_total."|".$time."|".$this->api_key);
    // Preparamos la informacion a enviar
    $payload = array(
      "key_id"  => $key_id,
      "hash" => $hash,
      "time" => $time,
      "amount" => $customer_order->order_total,
      "ccnumber" => str_replace( array(' ', '-' ), '', $_POST['bac_payment-card-number'] ),
      "ccexp" => str_replace( array( '/', ' '), '', $_POST['bac_payment-card-expiry'] ),
      "orderid" => $orderid,
      "cvv" => ( isset( $_POST['bac_payment-card-cvc'] ) ) ? $_POST['bac_payment-card-cvc'] : '',
      "type" => "auth",
     );
     */

    // Enviamos esta autorizacion para el procesamiento
    $response = wp_remote_post( $environment_url, array(
      'method'    => 'POST',
      //http buils query lo utilizamos para crear una consulta de tipo http de modo que usara el hash de entrada y contruira una url respectiva
      //Ejemplo de construccion: foo=bar&x=1&...n campos del hash
      'body'      => http_build_query( $payload ),
      'timeout'   => 90,
      'sslverify' => false,
    ) );

    //verificamos la respuesta obtenida si tiene algun error
    if ( is_wp_error( $response ) )
      throw new Exception( __( 'Ups! Tenemos un pequeño inconveniente con este pago, sentimos las molestias.', 'epayg-payment-gateway' ) );

    if ( empty( $response['body'] ) )
      throw new Exception( __( 'La respuesta esta vacia.', 'epayg-payment-gateway' ) );

    // Si no se encontro ningun error recuperamos la respuesta
    $response_body = wp_remote_retrieve_body( $response );

    foreach ( preg_split( "/\r?\n/", $response_body ) as $line ) {
      $resp = explode( "|", $line );
    }
    // valores obtenidos
    $r['response_code']             = $resp[0];
    $r['response_sub_code']         = $resp[1];
    $r['response_reason_code']      = $resp[2];
    $r['response_reason_text']      = $resp[3];

    /*
    // Analizamos la respuesta para poder leerla
    $resp_e = explode( "&", $response_body ); //Convertimos el cuerpo de la respuesta en strings quitando el delimitador &
    $resp = array();
    foreach($resp_e as $r) {
      $v = explode('=', $r);//separamos los string de cada iteracion del delimitador =
      $resp[$v[0]] = $v[1];//Almacenamos los datos separados en el arreglo resp
    }*/

    //Evaluamos la respuesta del codigo enviado para verificar si fue exitoso o no
    if ( ($r['response_code'] == 1 ) || ( $r['response_code'] == 4 ) ) {
      // El pago se completo con exito
      $customer_order->add_order_note( __( 'EpayG: Pago completado con exito!.', 'epayg-payment-gateway' ) );

      // Marcamos el pedido como pagado
      $customer_order->payment_complete();

      // Vaciamos el carrito
      $woocommerce->cart->empty_cart();

      // Redirigimos a la pagina de agradecimiento
      return array(
        'result'   => 'success',
        'redirect' => $this->get_return_url( $customer_order ),
      );
    } else {
      // Si la transaccion no fue exitosa agregamos una notificacion al carrito
      wc_add_notice( $r['response_reason_text'], 'error' );
      // agregamos una nota al pedido referenciado
      $customer_order->add_order_note( 'Error: '. $r['response_reason_text'] );
    }

  } //fin del proceso de pago

  //funcion para validar los campos
  public function validate_fields() {
    return true; //retornamos verdadero para activar la validacion
  }

  //chequeo del certificado ssl
  public function do_ssl_check() {
    if( $this->enabled == "yes" ) {
      if( get_option( 'woocommerce_force_ssl_checkout' ) == "no" ) {
        echo "<div class=\"error\"><p>". sprintf( __( "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>" ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) ."</p></div>";
      }
    }
  }
}
?>