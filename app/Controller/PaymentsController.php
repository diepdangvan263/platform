<?php

App::uses('AppController', 'Controller');
App::uses('PaymentLib', 'Payment');

class PaymentsController extends AppController {
	public function beforeFilter()
	{
		parent::beforeFilter();
        $this->Auth->allow(array(
            'api_pay', 'api_charge'
        ));
	}

    public $components = array(
        'Search.Prg'
    );

    public function api_pay(){
        $data = $this->pay(true);

        $result = array(
            'status' => 1,
            'message' => 'error'
        );
        if( !empty($data) ){
            $result = array(
                'status' => 0,
                'message' => 'success',
                'data' => $data
            );
        }

        $this->set('result', $result);
        $this->set('_serialize', 'result');
    }

	public function pay($return = false)
	{
//		 echo 'Hệ thống thanh toán đang được bảo trì, và sẽ online trong thời gian sớm nhất. Chúng tôi xin lỗi vì sự bất tiện này.';
//		 die();

        $result_api = false;

        $this->layout = 'payment';

		# load for view
		$this->loadModel('Payment');
		
		$game = $this->Common->currentGame();
		if( empty($game) || !$this->Auth->loggedIn() ){
            CakeLog::error('Vui lòng login', 'payment');
            if($return) return false;
			throw new NotFoundException('Vui lòng login');
		}
		$user = $this->Auth->user();

		$paymentLib = new PaymentLib();
		# check to see if there is unresolved payment

        if ($this->request->is('post')) {
            $chanel = Payment::CHANEL_HANOIPAY; // default
            if( in_array($game['app'], array('cbf45aa058807a173cccd5a6ac74c9a3', '1f5d898444a64fbbbdd10a38e5363c7d', //p17
                '2f9a1f92822b9f96fe6a20a68598023e', 'a8f01e2bec804367aad3ed5190ac595d', // t17
                '368134ee5abfbce34ffab0ed6f5d2ee3', '339a82e78b8a6c61096bcd01a435c664', // p20
                '443fe0ec835beae4ad944ee6da22729a', 'f26e77b1b71803f57ee7324b7355484d', // p21
                '07079b885d5c848486d90cd69bdd99ef', '33a1963c804930fea2293b03e38e96aa' // p22
            ))){
                $chanel = Payment::CHANEL_VIPPAY;
            }
            #chuyển về hanoipay
//            App::import('Lib', 'RedisCake');
//            $Redis = new RedisCake('action_count');
//            $Redis->incr('action_count_payment_all_game');
//            $Redis->expire('action_count_payment_all_game', 60*60); // set 1h
//            $count_redis = $Redis->get('action_count_payment_all_game');
//            if( is_numeric($count_redis) && $count_redis%2 ) $chanel = Payment::CHANEL_HANOIPAY;

            $data = $this->request->data;
            $data = array_merge($data, array(
                'user_id' => $user['id'],
                'game_id' => $game['id'],
                'chanel' => $chanel,
                'status' => WaitingPayment::STATUS_WAIT,
                'time' => time(),
                'order_id' => microtime(true) * 10000
            ));

            $this->loadModel('Payment');
            $this->loadModel('WaitingPayment');
            try {
                $unresolvedPayment = $this->WaitingPayment->save($data);

                $dataSource = $this->Payment->getDataSource();
                $dataSource->begin();

                $test_type = 0;
                if(!empty($game['data']['payment']['testallowed'])){
                    $testList = $game['data']['payment']['testallowed'];
                    if( in_array($user['email'], array_map('trim', explode("\n", $testList))) ){
                        $test_type = 1;
                    }
                }
                if($test_type){
                    $price_test = array(10000, 20000, 30000, 50000, 100000, 200000, 300000, 500000);
                    if( $data['card_serial'] == '123456789' && in_array($data['card_code'], $price_test) )
                    $result = array(
                        'status'    => 0,
                        'messsage'  => 'success',
                        'data'      => array(
                            'time'  => $data['time'],
                            'type'  => $data['type'],
                            'chanel'    => $data['chanel'],

                            'order_id'  => $data['order_id'],
                            'user_id'   => $data['user_id'],
                            'game_id'   => $data['game_id'],

                            'card_code' => $data['card_code'],
                            'price'     => $data['card_code'],
                            'card_serial'   => $data['card_serial']
                        )
                    );
                }else{
                    # gọi đến api cổng thanh toán và check thẻ
                    $result = $paymentLib->callPayApi($data);
                }

                if( isset($result['status']) && $result['status'] == 0 && $data['order_id'] == $result['data']['order_id']){
                    # trạng thái thành công, lưu dữ liệu payment
                    $data_payment = array(
                        'waiting_id'	=> $unresolvedPayment['WaitingPayment']['id'],

                        'time'  => $data['time'],
                        'type'  => $data['type'],
                        'test'	=> $test_type,
                        'chanel'    => $data['chanel'],

                        'order_id'  => $result['data']['order_id'],
                        'user_id' 	=> $user['id'],
                        'account_id'=> $this->Common->getAccount(),
                        'game_id' 	=> $game['id'],

                        'card_code' => $result['data']['card_code'],
                        'price'     => $result['data']['price'],
                        'card_serial'   => $result['data']['card_serial']
                    );
                    $paymentLib->setResolvedPayment($unresolvedPayment['WaitingPayment']['id'], WaitingPayment::STATUS_COMPLETED);
                    $paymentLib->add($data_payment);
                    $result_api = $data_payment;
                    if(!$return) $this->render('/Payments/result');
                }elseif (!empty($result['status']) && $result['status'] == 1){
                    # trạng thái lỗi, thẻ đã sử dụng, hoặc thẻ không đúng
                    CakeLog::info('trạng thái lỗi, thẻ đã sử dụng, hoặc thẻ không đúng', 'payment');
                    $paymentLib->setResolvedPayment($unresolvedPayment['WaitingPayment']['id'], WaitingPayment::STATUS_ERROR);
                    if(!$return) $this->render('/Payments/error');
                }else{
                    # chờ hệ thống cổng thanh toán
                    CakeLog::info('chờ hệ thống cổng thanh toán', 'payment');
                    $paymentLib->setResolvedPayment($unresolvedPayment['WaitingPayment']['id'], WaitingPayment::STATUS_QUEUEING);
                    if(!$return) $this->render('/Payments/order');
                }
                $dataSource->commit();
            } catch (Exception $e) {
                CakeLog::error($e->getMessage());
                $dataSource->rollback();
            }
        }

        if($return) return $result_api;
	}

	public function api_charge(){
	    $result = array(
	        'status'    => 1,
            'mesage'    => 'empty'
        );

        $app = 'app';
        $token  = 'token';

        if( $this->request->header($app) ){
            $appKey = $this->request->header($app);
        }

        if ( $this->request->query('app_key') ) {
            $appKey = $this->request->query('app_key');
        } elseif ( $this->request->query('appkey') ) {
            $appKey = $this->request->query('appkey');
        } elseif ( $this->request->query('app') ) {
            $appKey = $this->request->query('app');
        }

        if( $this->request->header($token) ){
            $accessToken = $this->request->header($token);
        }

        if ( $this->request->query('access_token') ) {
            $accessToken = $this->request->query('access_token');
        }elseif ( $this->request->query('token') ){
            $accessToken = $this->request->query('token');
        }

        if (!isset($appKey, $accessToken)) {
            $result = array(
                'status'    => 2,
                'mesage'    => 'empty token or appkey'
            );
            goto end;
        }

        $game = $this->Common->currentGame();
        if( empty($game) || !$this->Auth->loggedIn() ){
            $result = array(
                'status'    => 3,
                'mesage'    => 'Invalid token or appkey'
            );
            goto end;
        }
        $user = $this->Auth->user();

        $price = $sign_input = false;
        if( !empty($this->request->data('price')) ){
            $price = $this->request->data('price');
        }elseif ( !empty($this->request->query('price')) ){
            $price = $this->request->query('price');
        }

        if( !is_numeric($price) || $price <= 0 ){
            $result = array(
                'status'    => 7,
                'mesage'    => 'Invalid price'
            );
            goto end;
        }

        if( !empty($this->request->data('sign')) ){
            $sign_input = $this->request->data('sign');
        }elseif ( !empty($this->request->query('sign')) ){
            $sign_input = $this->request->query('sign');
        }

        if( empty($price) || empty($sign_input) ){
            $result = array(
                'status' => 4,
                'message' => 'Necessary data is missing'
            );
            goto end;
        }

        $paymentLib = new PaymentLib();
        # update payment user khi ingame trả về
        # dữ liệu truyền sang `price`, `sign`
        $data = array(
            'user_id'   => $user['id'],
            'game_id'   => $game['id'],
            'time'      => time(),
            'order_id'  => microtime(true) * 10000,
            'price'     => $price,
            'sign'      => $sign_input
        );

        $sign = md5( $game['app'] . $game['secret_key'] . $accessToken . $data['price'] );
        if( empty($data['sign']) || $sign != $data['sign'] ){
            CakeLog::error('sign api charge:'. $sign, 'payment');
            $result = array(
                'status'    => 5,
                'message'   => 'The sign is incorrect'
            );
            goto end;
        }

        if( $paymentLib->sub($data) ){
            $result = array(
                'status'    => 0,
                'mesage'    => 'success'
            );
            goto end;
        }else{
            $result = array(
                'status'    => 6,
                'mesage'    => 'error'
            );
            goto end;
        }

        end:
        $this->set('result', $result);
        $this->set('_serialize', 'result');
    }

    public function admin_index(){
        $this->layout = 'default_bootstrap';

        $this->Prg->commonProcess();
        $this->request->data['Payment'] = $this->passedArgs;

        $parsedConditions = array();
        if(!empty($this->passedArgs)) {
            $parsedConditions = $this->Payment->parseCriteria($this->passedArgs);
        }

        if( !empty($this->passedArgs) && empty($parsedConditions)
        ){
            if (	(count($this->passedArgs) == 1 && empty($this->passedArgs['page']))
                ||	count($this->passedArgs) > 1
            ) {
                $this->Session->setFlash("Can not find anyone match this conditions", "error");
            }
        }

        $parsedConditions = array_merge(array(
            'Payment.game_id' => $this->Session->read('Auth.User.permission_game_default')
        ), $parsedConditions);

        $games = $this->Payment->Game->find('list', array(
            'fields' => array('id', 'title_os'),
            'conditions' => array(
                'Game.id' => $this->Session->read('Auth.User.permission_game_default'),
            )
        ));

        $this->paginate = array(
            'Payment' => array(
                'fields' => array('Payment.*', 'User.username', 'User.id', 'Game.title', 'Game.os'),
                'conditions' => $parsedConditions,
                'contain' => array(
                    'Game', 'User'
                ),
                'order' => array('Payment.id' => 'DESC'),
                'recursive' => -1,
                'limit' => 20
            )
        );

        $payments = $this->paginate();

        $chanels = array(
            Payment::CHANEL_VIPPAY      => 'Vippay',
            Payment::CHANEL_HANOIPAY    => 'Hanoipay',
            Payment::CHANEL_PAYPAL      => 'Paypal',
            Payment::CHANEL_MOLIN       => 'Molin',
            Payment::CHANEL_ONEPAY      => '1Pay',
            Payment::CHANEL_PAYMENTWALL => 'PaymentWall',
        );

        $types = array(
            Payment::TYPE_NETWORK_VIETTEL   => 'Viettel',
            Payment::TYPE_NETWORK_VINAPHONE => 'Vinaphone',
            Payment::TYPE_NETWORK_MOBIFONE  => 'Mobifone',
            Payment::TYPE_NETWORK_GATE      => 'Gate',
            Payment::TYPE_NETWORK_BANKING   => 'Visa'
        );

        $this->set(compact('payments', 'games', 'chanels', 'types'));
    }

    public function pay_list(){
        $this->layout = 'payment';

        $game = $this->Common->currentGame();
        if( empty($game) || !$this->Auth->loggedIn() ){
            CakeLog::error('Vui lòng login', 'payment');
            throw new NotFoundException('Vui lòng login');
        }

        $token = $this->request->header('token');
        $this->set(compact('token', 'game'));
    }

    public function pay_paypal_index(){
        $game = $this->Common->currentGame();
        if( empty($game) || !$this->Auth->loggedIn() ){
            CakeLog::error('Vui lòng login', 'payment');
            throw new NotFoundException('Vui lòng login');
        }

        $token = $this->request->header('token');

        //get currency
        $currency = $this->request->query('currency');
        if (!$currency) {
            $currency = 'USD';
        } else {
            $currency = strtolower($currency);
        }

        $gameData = $game['data'];
        if (!isset($gameData['vcurrency']['type']) || empty($gameData['vcurrency']['type']))
            $vcurrencyType = "diamond";
        else
            $vcurrencyType = $gameData['vcurrency']['type'];

        //get list price
        $this->loadModel('Product');
        $products = $this->Product->find('all', array(
            'conditions' => array(
                'Product.game_id' => $game['id'],
            ),
            'order'     => array('Product.platform_price' => 'asc' ),
            'recursive' => -1
        ));

        $this->set(compact('products', 'vcurrencyType', 'currency', 'game', 'token'));
        $this->layout = 'payment';
        $this->loadModel('Payment');
    }

    public function pay_paypal_order(){
        $game = $this->Common->currentGame();
        if( empty($game) || !$this->Auth->loggedIn() ){
            CakeLog::error('Vui lòng login', 'payment');
            throw new NotFoundException('Vui lòng login');
        }

        $token = $this->request->header('token');

        //get currency
        $currency = $this->request->query('currency');
        if (!$currency) {
            $currency = 'USD';
        } else {
            $currency = strtolower($currency);
        }

        $productId = $this->request->query('productId');
        if( empty($this->request->query('productId')) ){
            CakeLog::error('Chưa chọn gói xu - paypal', 'payment');
            throw new NotFoundException('Chưa chọn gói xu');
        }

        $this->loadModel('Product');
        $this->Product->recursive = -1;
        $product = $this->Product->findById($productId);

        if( empty($product) ){
            CakeLog::error('Không có gói xu phù hợp - paypal', 'payment');
            throw new NotFoundException('Không có gói xu phù hợp');
        }

        # xử lý mua hàng qua paypal
        App::uses('Paypal', 'Payment');
        $paypal = new Paypal($game['app'], $token);
        $linkPaypal = $paypal->buy($product['Product']['title'], $product['Product']['price'], $currency);
        if( empty($linkPaypal) ){
            CakeLog::error('Lỗi tạo giao dịch - paypal', 'payment');
            throw new NotFoundException('Lỗi tạo giao dịch, vui lòng thử lại');
        }
        $this->redirect($linkPaypal);
    }

    public function pay_paypal_response(){
        $game = $this->Common->currentGame();
        if( empty($game) || !$this->Auth->loggedIn() ){
            CakeLog::error('Vui lòng login', 'payment');
            throw new NotFoundException('Vui lòng login');
        }
        $user = $this->Auth->user();

        $paypal_id = $this->request->query('paymentId');
        if( empty($paypal_id) ){
            CakeLog::error('Lỗi giao dịch - paypal response', 'payment');
            throw new NotFoundException('Lỗi giao dịch');
        }

        $clientId = Configure::read('Paypal.clientId');
        $secret = Configure::read('Paypal.secret');

        $paypal_token_url = Configure::read('Paypal.TokenUrl');
        $paypal_payment_url = Configure::read('Paypal.PaymentUrl');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $paypal_token_url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $clientId.":".$secret);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");

        $result = curl_exec($ch);
        curl_close($ch);

        if(!empty($result)) {
            $json = json_decode($result);
            $accessToken = $json->access_token;

            $ch1 = curl_init();
            curl_setopt($ch1, CURLOPT_URL, $paypal_payment_url . $paypal_id);
            curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch1, CURLOPT_HTTPHEADER, array(
                'Authorization: Bearer ' . $accessToken,
                'Accept: application/json',
                'Content-Type: application/json'
            ));

            $result = curl_exec($ch1);
            curl_close($ch1);

            echo "<pre>"; print_r(json_decode($result));die;
        }
    }
}
