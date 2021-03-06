<?php
App::import('Model', 'WaitingPayment');
App::import('Model', 'Payment');
App::import('Lib', 'Vippay');

class PaymentLib {
    public function checkUnsolvedPayment( $user_id, $game_id ){
        $this->WaitingPayment = ClassRegistry::init('WaitingPayment');
        $waiting = $this->WaitingPayment->find('first', array(
            'conditions' => array(
                'game_id'   => $game_id,
                'user_id'   => $user_id,
                'status'    => WaitingPayment::STATUS_WAIT
            )
        ));

        if (!empty($waiting['WaitingPayment'])) {
            return $waiting;
        } else {
            return false;
        }
    }

    # default status is error
    public function setResolvedPayment($id, $status = 3) {
        $this->WaitingPayment = ClassRegistry::init('WaitingPayment');
        $newData = array(
            'id'        => $id,
            'status'    => $status,
            'time'      => time()
        );
        $this->WaitingPayment->save($newData);
    }

    /*
     * data :   card_code, card_serial, type,
     *          order_id, user_id, game_id, chanel
     */
    public function callPayApi($data){
        ClassRegistry::init('Payment');

        $result = false;
        if( !empty($data['chanel']) ){
            switch ($data['chanel']){
                case Payment::CHANEL_VIPPAY:
                    $vippay = new Vippay();
                    $result = $vippay->call($data);
                    break;
                case Payment::CHANEL_HANOIPAY:
                    App::import('Lib', 'Hanoipay');
                    $hanoipay = new Hanoipay();
                    $result = $hanoipay->call($data);
                    break;
            }
        }

        return $result;
    }

    /*
     * data :   order_id, user_id, game_id, card_code, card_serial, price,
     *          time, type, chanel,
     *          test = 0 // default
     *          note = '' // default
     *          waiting_id
     */
    public function add($data){
        $this->__getPriceEnd($data);
        CakeLog::info('data add :' .print_r($data,true));
        try {
            $this->Payment = ClassRegistry::init('Payment');
            
            $dataSource = $this->Payment->getDataSource();
            $dataSource->begin();

            $this->Payment->save($data);

            App::import('Lib', 'Transaction');
            $this->Transaction = ClassRegistry::init('Transaction');
            $data['type'] = Transaction::TYPE_PAY;
            $this->Transaction->save($data);

            $this->Payment->User->recursive = -1;
            $user = $this->Payment->User->findById($data['user_id']);
            $updatePay = $user['User']['payment'] + $data['price'];
            $this->Payment->User->id = $data['user_id'];
            $this->Payment->User->saveField('payment', $updatePay, array('callbacks' => false));
            
            $dataSource->commit();
            return true;
        }catch (Exception $e){
            CakeLog::error('error save payment - ' . $e->getMessage(), 'payment');
            $dataSource->rollback();
        }
        
        return false;
    }

    /*
     * data :   order_id, user_id, game_id, price,
     *          time, note,
     *          test = 0 // default
     */
    public function sub($data){
        CakeLog::info('log charge data:' . print_r($data,true), 'payment');
        try {
            $this->Charge = ClassRegistry::init('Charge');

            $dataSource = $this->Charge->getDataSource();
            $dataSource->begin();

            $this->Charge->save($data);

            App::import('Lib', 'Transaction');
            $this->Transaction = ClassRegistry::init('Transaction');
            $data['type'] = Transaction::TYPE_SPEND;
            $this->Transaction->save($data);

            $this->Charge->User->recursive = -1;
            $user = $this->Charge->User->findById($data['user_id']);
            $updatePay = $user['User']['payment'] - $data['price'];

            CakeLog::info('log charge user_id:' . $data['user_id']
                . '- before update pay:' . $user['User']['payment']
                . ' - update pay:' . $updatePay, 'payment');

            if($updatePay >= 0) {
                $this->Charge->User->id = $data['user_id'];
                $this->Charge->User->saveField('payment', $updatePay);
                $dataSource->commit();
                return true;
            }else{
                CakeLog::error('Not enough', 'payment');
            }
        }catch (Exception $e){
            CakeLog::error('error save charge', 'payment');
        }
        $dataSource->rollback();
        return false;
    }

    private function __getPriceEnd(&$data){
        if( !empty($data) ){
            ClassRegistry::init('Payment');
            $price_end = 0;

            if($data['chanel'] == Payment::CHANEL_VIPPAY){
                switch ( $data['type'] ){
                    case Payment::TYPE_NETWORK_VIETTEL:
                    case Payment::TYPE_NETWORK_MOBIFONE:
                    case Payment::TYPE_NETWORK_VINAPHONE:
                        $price_end = 0.79 * $data['price'];
                        break;
                    case Payment::TYPE_NETWORK_GATE:
                        $price_end = 0.83 * $data['price'];
                        break;
                }
            }elseif ( $data['chanel'] == Payment::CHANEL_HANOIPAY ){
                switch ( $data['type'] ){
                    case Payment::TYPE_NETWORK_VIETTEL:
                        $price_end = 0.8 * $data['price'];
                        break;
                    case Payment::TYPE_NETWORK_MOBIFONE:
                        $price_end = 0.815 * $data['price'];
                        break;
                    case Payment::TYPE_NETWORK_VINAPHONE:
                        $price_end = 0.81 * $data['price'];
                        break;
                    case Payment::TYPE_NETWORK_GATE:
                        $price_end = 0.82 * $data['price'];
                        break;
                }

            }elseif ( $data['chanel'] == Payment::CHANEL_ONEPAY ){
                $price_end = $data['price'] * 0.967 - 3300;
            }elseif ( $data['chanel'] == Payment::CHANEL_PAYMENTWALL ){
                return ;
            }

            $data['price_end'] = $price_end;
        }
    }
}