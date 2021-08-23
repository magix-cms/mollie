<?php
require __DIR__  . '/mollie-api-php/vendor/autoload.php';
include_once ('db.php');
class plugins_mollie_public extends plugins_mollie_db
{
    protected $template,
        $mail,
        $header,
        $data,
        $getlang,
        $modelDomain,
        $config,
        $settings,
        $about,
        $mollie,
        $message,
        $sanitize;

    public $purchase,
        $custom,
        $urlStatus,
        $payment_plugin = true,
        $callback,
        $order,
        $redirect;

    /**
     * plugins_hipay_public constructor.
     * @param null $t
     */
    public function __construct($t = null)
    {
        $this->template = $t instanceof frontend_model_template ? $t : new frontend_model_template();
        $this->header = new http_header();
        $this->data = new frontend_model_data($this,$this->template);
        $this->getlang = $this->template->lang;
        $formClean = new form_inputEscape();
        $this->sanitize = new filter_sanitize();
        //$this->header = new component_httpUtils_header($this->template);
        $this->message = new component_core_message($this->template);
        $this->mollie = new \Mollie\Api\MollieApiClient();
        $this->modelDomain = new frontend_model_domain($this->template);
        $this->about = new frontend_model_about($this->template);
        $formClean = new form_inputEscape();

        if (http_request::isPost('purchase')) {
            $this->purchase = $formClean->arrayClean($_POST['purchase']);
        }
        // ------ custom utilisé pour metadata
        /*if (http_request::isPost('custom')) {
            $this->custom = $formClean->arrayClean($_POST['custom']);
        }*/
        if (http_request::isGet('urlStatus')) {
            $this->urlStatus = $formClean->simpleClean($_GET['urlStatus']);
        }
        if (http_request::isGet('redirect')) {
            $this->redirect = $formClean->simpleClean($_GET['redirect']);
        }elseif (http_request::isPost('redirect')) {
            $this->redirect = $formClean->simpleClean($_POST['redirect']);
        }
        if (http_request::isPost('callback')) {
            $this->callback = $formClean->simpleClean($_POST['callback']);
        }
        /*if (http_request::isPost('order')) {
            $this->order = $formClean->simpleClean($_POST['order']);
        }*/
        $this->order = filter_rsa::tokenID();
        if (http_request::isPost('custom')) {
            $array = $_POST['custom'];
            $array['order'] = $formClean->simpleClean($this->order);
            $this->custom = $array;
        }
        //@ToDo switch to this declaration when deployed online
        $this->mail = new frontend_model_mail($this->template, 'mollie');
    }
    /**
     * Assign data to the defined variable or return the data
     * @param string $type
     * @param string|int|null $id
     * @param string $context
     * @param boolean $assign
     * @return mixed
     */
    private function getItems($type, $id = null, $context = null, $assign = true) {
        return $this->data->getItems($type, $id, $context, $assign);
    }

    /**
     * @return mixed
     */
    private function setItemsAccount(){
        return $this->getItems('root',NULL,'one',false);
    }
    /**
     * Update data
     * @param $data
     * @throws Exception
     */
    private function add($data)
    {
        switch ($data['type']) {
            case 'history':
                parent::insert(
                    array(
                        'context' => $data['context'],
                        'type' => $data['type']
                    ),
                    $data['data']
                );
                break;
        }
    }
    /**
     * @param $setConfig
     * @return array
     */
    /*private function setUrl($setConfig){
        $baseUrl = http_url::getUrl();
        $lang = $this->template->currentLanguage();
        $setConfig['plugin'] = isset($setConfig['plugin']) ? $setConfig['plugin'] : false;
        if($setConfig['plugin']) {
            $url = $baseUrl . '/'. $lang . '/' . $setConfig['plugin'] . '/';
            return array(
                'redirectUrl' => $url . '?order',
                'webhookUrl' => $url . '?webhook'
            );
        }
    }*/

    /**
     * @param $setConfig
     * @return array
     */
    private function setUrl($setConfig){
        $baseUrl = http_url::getUrl();
        $lang = $this->getlang;
        $setConfig['plugin'] = isset($setConfig['plugin']) ? $setConfig['plugin'] : false;

        if($setConfig['plugin']) {
            $url = $baseUrl . '/'. $lang . '/' . $setConfig['plugin'] . '/';
            if(isset($this->callback)){
                $callback = $baseUrl . '/'. $lang . '/' . $this->callback . '/';
            }else{
                $callback = $url;
            }

            if(isset($this->redirect)){
                $redirect = '&redirect='.$this->redirect;
            }else{
                $redirect = '';
            }
            isset($this->redirect) ? '&redirect='.$this->redirect : '';
            // ----- todo voir pour redirectUrl
            return array(
                'webhookUrl' => $callback . '?webhook=true',
                'redirectUrl' => $url . '?order='.$this->order.$redirect
            );
        }
    }

    /**
     * @param $config
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function createPayment($config){

        //https://github.com/mollie/mollie-api-php
        $data = $this->setItemsAccount();
        $this->mollie->setApiKey($data['apikey']);

        // Set redirect urls
        $setUrl = $this->setUrl($config);

        ### Creating a new payment.
        $payment = $this->mollie->payments->create([
            "amount" => [
                "currency"  => $config['currency'],
                "value"     => $config['amount']
            ],
            "description" => $config['setName'],
            "redirectUrl" => $setUrl['redirectUrl'],
            "webhookUrl"  => $setUrl['webhookUrl'],
            // method payment is optionnal
            //"method"      => $this->purchase['method_pay'],
            "metadata" => /*[
                'order'     =>  $config['order'],
                'account'   =>  $_SESSION['id_account'],
                'credit'    =>  $this->purchase['credit'],
                'promocode' =>  $this->purchase['promocode']
            ]*/
            $this->custom
        ]);


        try {
            if(isset($config['debug']) && $config['debug'] == 'pre'){
                print '<pre>';
                print_r($payment);
                print '<pre>';
            }else{
                // REDIRECT USER TO getCheckoutUrl
                header("Location: " . $payment->getCheckoutUrl(), true, 303);
            }
        } catch(Exception $e) {
            $logger = new debug_logger(MP_LOG_DIR);
            $logger->log('php', 'error', 'An error has occured : '.$e->getMessage(), debug_logger::LOG_MONTH);
        }
    }

    /**
     * @param $config
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function captureOrder($config){

        $data = $this->setItemsAccount();
        $this->mollie->setApiKey($data['apikey']);
        /*
            * Retrieve the payment's current state.
            */
        $payment = $this->mollie->payments->get($_POST["id"]);

        try {
            $getPayment = array();
            if ($payment->isPaid() && !$payment->hasRefunds() && !$payment->hasChargebacks()) {
                /*
                 * The payment is paid and isn't refunded or charged back.
                 * At this point you'd probably want to start the process of delivering the product to the customer.
                 */
                $getPayment = [
                    'amount' => $payment->amount,
                    'method' => $payment->method,
                    'metadata' => $payment->metadata,
                    'status' => 'paid'
                ];

                $this->add(array(
                    'type' => 'history',
                    'data' => array(
                        'order_h' => $payment->metadata->order,
                        'status_h' => 'paid'
                    )
                ));

            } //elseif ($payment->isOpen()) {
            /*
             * The payment is open.
             */
            //}
            elseif ($payment->isPending()) {
                /*
                 * The payment is pending.
                 */
                $this->add(array(
                    'type' => 'history',
                    'data' => array(
                        'order_h' => $payment->metadata->order,
                        'status_h' => 'pending'
                    )
                ));
                $getPayment = [
                    'status' => 'pending'
                ];
            } elseif ($payment->isFailed()) {
                /*
                 * The payment has failed.
                 */
                $this->add(array(
                    'type' => 'history',
                    'data' => array(
                        'order_h' => $payment->metadata->order,
                        'status_h' => 'failed'
                    )
                ));
                $getPayment = [
                    'status' => 'failed'
                ];
            } elseif ($payment->isExpired()) {
                /*
                 * The payment is expired.
                 */
                $this->add(array(
                    'type' => 'history',
                    'data' => array(
                        'order_h' => $payment->metadata->order,
                        'status_h' => 'expired'
                    )
                ));
                $getPayment = [
                    'status' => 'expired'
                ];
            } elseif ($payment->isCanceled()) {
                /*
                 * The payment has been canceled.
                 */
                $this->add(array(
                    'type' => 'history',
                    'data' => array(
                        'order_h' => $payment->metadata->order,
                        'status_h' => 'canceled'
                    )
                ));
                $getPayment = [
                    'status' => 'canceled'
                ];
            }
            if(isset($config['debug']) && $config['debug'] == 'printer'){
                $log = new debug_logger(MP_LOG_DIR);
                $log->tracelog('start payment');
                $log->tracelog(json_encode($getPayment));
                $log->tracelog('sleep');
            }else{
                return $getPayment;
            }

        }catch(Exception $e) {
            $logger = new debug_logger(MP_LOG_DIR);
            $logger->log('php', 'error', 'An error has occured : '.$e->getMessage(), debug_logger::LOG_MONTH);
        }

    }

    /**
     * @return array
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function getMethod(){

        $data = $this->setItemsAccount();
        $this->mollie->setApiKey($data['apikey']);
        $methods = $this->mollie->methods->allActive();

        $newData = array();
        foreach ($methods as $key => $value){
            $newData[$key]['id'] = $value->id;
            $newData[$key]['description'] = $value->description;
            $newData[$key]['img']['1x'] = $value->image->size1x;
            $newData[$key]['img']['2x'] = $value->image->size2x;
        }
        return $newData;
    }
    /**
     * Send a mail
     * @param $email
     * @param $tpl
     * @return bool
     */
    protected function send_email($email, $tpl, $data, $file = false) {
        if($email) {
            $this->template->configLoad();
            if(!$this->sanitize->mail($email)) {
                $this->message->json_post_response(false,'error_mail');
            }
            else {
                if($this->getlang) {
                    $contact = new plugins_contact_public();
                    $sender = $contact->getSender();

                    if(!empty($sender) && !empty($email)) {
                        $allowed_hosts = array_map(function($dom) { return $dom['url_domain']; },$this->modelDomain->getValidDomains());
                        if (!isset($_SERVER['HTTP_HOST']) || !in_array($_SERVER['HTTP_HOST'], $allowed_hosts)) {
                            header($_SERVER['SERVER_PROTOCOL'].' 400 Bad Request');
                            exit;
                        }
                        $noreply = 'noreply@'.str_replace('www.','',$_SERVER['HTTP_HOST']);

                        return $this->mail->send_email($email,$tpl,$data,'',$noreply,$sender['mail_sender'],$file);
                    }
                    else {
                        $this->message->json_post_response(false,'error_plugin');
                        return false;
                    }
                }
            }
        }
    }
    public function getPaymentStatus(){
        $mollie = $this->getItems('lastHistory',NULL,'one',false);
        return $mollie['status_h'];
    }
    /**
     *
     */
    public function run(){

        if(isset($_GET['order'])){
            if(isset($_COOKIE['mc_cart'])) {
                $mollie = $this->getItems('history',array('order_h'=>$_GET['order']),'one',false);

                $status = 'pending';
                switch ($mollie['status_h']) {
                    case 'paid':
                        $status = 'success';
                        break;
                    case 'failed':
                        $status = 'error';
                        break;
                    case 'canceled':
                    case 'expired':
                        $status = 'canceled';
                        break;
                }

                header("location:/$this->getlang/cartpay/order/?step=done_step&status=$status");
            }else{
                $mollie = $this->getItems('history',array('order_h'=>$_GET['order']),'one',false);
                $this->template->assign('mollie',$mollie);

                if(isset($this->redirect)){
                    $baseUrl = http_url::getUrl();
                    header( "Refresh: 3;URL=$baseUrl/$this->getlang/$this->redirect/" );
                }
                $this->template->display('mollie/index.tpl');
            }

        }elseif(isset($_GET['webhook'])){

            $getPayment = $this->captureOrder(
                array(
                    'debug'=>false
                )
            );

            if(isset($getPayment['status']) && $getPayment['status'] == 'paid') {
                $result = [
                    'amount'    =>  $getPayment['amount']->value,
                    'currency'  =>  $getPayment['amount']->currency
                ];
                foreach ($getPayment['metadata'] as $key => $value){
                    $result[$key] = $value;
                }
                /*$log = new debug_logger(MP_LOG_DIR);
                $log->tracelog('start payment');
                $log->tracelog(json_encode($result));
                $log->tracelog('sleep');*/

                if(isset($result['email'])){
                    //$log->tracelog('email true');
                    $about = new frontend_model_about($this->template);
                    $collection = $about->getCompanyData();
                    //$collection['contact']['mail']
                    $this->send_email($result['email'], 'admin', $result);
                    if(isset($collection['contact']['mail']) && !empty($collection['contact']['mail'])){
                        $this->send_email($collection['contact']['mail'], 'admin', $result);
                    }
                }else{
                    //$log->tracelog('email false');
                }
            }

        }elseif(isset($_GET['listmethod'])){

            $data = $this->setItemsAccount();
            $this->mollie->setApiKey($data['apikey']);
            $methods = $this->mollie->methods->allActive();

            /*print '<pre>';
            print_r($methods);
            print '</pre>';*/
            /*$this->template->assign('listMethod',$methods);
            $this->template->display('mollie/index.tpl');*/
            $newData = array();
            foreach ($methods as $key => $value){
                $newData[$key]['id'] = $value->id;
                $newData[$key]['description'] = $value->description;
                $newData[$key]['img']['1x'] = $value->image->size1x;
                $newData[$key]['img']['2x'] = $value->image->size2x;
            }
            /*print '<pre>';
            print_r($newData);
            print '</pre>';*/
            $this->header->set_json_headers();
            $this->message->json_post_response(true, null, $newData);

        }else{
            if(isset($this->purchase)) {

                $this->template->addConfigFile(
                    array(component_core_system::basePath() . '/plugins/mollie/i18n/'),
                    array('public_local_'),
                    false
                );
                $this->template->configLoad();

                $collection = $this->about->getCompanyData();

                // config data for payment
                $config = array(
                    'plugin' => 'mollie',
                    'setName' => $this->template->getConfigVars('order_on') . ' ' . $collection['name'],
                    'amount' => $this->purchase['amount'],
                    'currency' => 'EUR',//$this->purchase['currency'],
                    //'order' => $this->order,
                    'quantity' => isset($this->custom['quantity']) ? $this->custom['quantity'] : 1,
                    'debug' => false//pre,none,printer
                );
                //print_r($config);
                $this->createPayment($config);
            }
        }
    }
}
?>