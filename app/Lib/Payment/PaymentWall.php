<?php
require_once ROOT. DS . 'vendors' . DS . 'PaymentWall' . DS . 'lib' . DS . 'paymentwall.php';
class PaymentWall {
    
    private $access_key ;
	private $secret ;

    private $user_token;
    private $game_app;
    private $order_id;

    private $note = ' ';

    function __construct($access_key, $secret , $user_token, $game_app)
    {
        $this->access_key   = $access_key;
        $this->secret       = $secret;
        $this->user_token   = $user_token;
        $this->game_app     = $game_app;
    }

    public function getUserToken() {
        return $this->user_token;
    }

    public function setUserToken($user_token) {
        $this->user_token = $user_token;
    }

    public function getGameApp() {
        return $this->game_app;
    }

    public function setGameApp($game_app) {
        $this->game_app = $game_app;
    }

    public function getOrderId() {
        return $this->order_id;
    }

    public function setOrderId($order_id) {
        $this->order_id = $order_id;
    }

    public function getNote() {
        return $this->note;
    }

    public function setNote($note) {
        $this->note = $note;
    }

    # $product : dữ liệu từ bảng product
    # type: array
    public function create( $product ){
        Paymentwall_Base::setApiType(Paymentwall_Base::API_GOODS);
        Paymentwall_Base::setAppKey($this->access_key);
        Paymentwall_Base::setSecretKey($this->secret);

        $widget = new Paymentwall_Widget(
            $this->getOrderId(),   // id of the end-user who's making the payment ( orderId)
            'pw',          // widget code, e.g. pw; can be picked inside of your merchant account
            array(         // product details for Flexible Widget Call. To let users select the product on Paymentwall's end, leave this array empty
                new Paymentwall_Product(
                    $this->getOrderId(),   // id of the product in your system
                    $product['price'],
                    'USD',      // currency code
                    $product['title']      // product name
                )
            ),
            array('app' => $this->getGameApp(), 'qtoken' => $this->getUserToken())
        );
        return $widget->getUrl();
    }

    public function close(){
        Paymentwall_Base::setApiType(Paymentwall_Base::API_GOODS);
        Paymentwall_Base::setAppKey($this->access_key);
        Paymentwall_Base::setSecretKey($this->secret);

        $pingback = new Paymentwall_Pingback($_GET, $_SERVER['REMOTE_ADDR']);
        if ($pingback->validate()) {
            $productId = $pingback->getProduct()->getId();
            if ($pingback->isDeliverable()) {
                // deliver the product
            } else if ($pingback->isCancelable()) {
                // withdraw the product
            } else if ($pingback->isUnderReview()) {
                // set "pending" status to order
            }
            echo 'OK'; // Paymentwall expects response to be OK, otherwise the pingback will be resent
        } else {
            echo $pingback->getErrorSummary();
        }
    }
}