<?php
require __DIR__  . '/mollie-api-php/vendor/autoload.php';


include_once ('db.php');
class plugins_mollie_public extends plugins_mollie_db
{
    protected $template, $header, $data, $getlang, $moreinfo, $sanitize, $mail, $origin, $modelDomain, $config, $settings,$bridge,$mollie;
    public $msg, $type, $purchase,$id_account,$ws;

    /**
     * frontend_controller_home constructor.
     */
    public function __construct()
    {
        $this->template = new frontend_model_template();
        $formClean = new form_inputEscape();
        $this->sanitize = new filter_sanitize();
        //$this->header = new component_httpUtils_header($this->template);
        $this->header = new http_header();
        $this->data = new frontend_model_data($this);
        $this->getlang = $this->template->currentLanguage();
        $this->mail = new mail_swift('mail');
        $this->modelDomain = new frontend_model_domain($this->template);
        $this->config = $this->getItems('config', null, 'one', false);
        $this->settings = new frontend_model_setting();
        $this->bridge = new plugins_bridge_public();
        $this->ws = new frontend_model_webservice();
        $this->mollie = new \Mollie\Api\MollieApiClient();


        if (http_request::isPost('msg')) {
            $this->msg = $formClean->arrayClean($_POST['msg']);
        }

        /*if (http_request::isPost('type')) {
            $this->type = $formClean->simpleClean($_POST['type']);
        }*/
        //id_account
        if(http_request::isSession('id_account')){
            $this->id_account = $_SESSION['id_account'];
        }

        if (http_request::isPost('purchase')) {
            $this->purchase = $formClean->arrayClean($_POST['purchase']);
        }
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
     * @param $setConfig
     * @return array
     */
    private function setUrl($setConfig){
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
                "value"     => $config['price']
            ],
            "description" => $config['setName'],
            "redirectUrl" => $setUrl['redirectUrl'].'='.$config['order'],
            "webhookUrl"  => $setUrl['webhookUrl'],
            "metadata" => [
                'order'     =>  $config['order'],
                'account'   =>  $_SESSION['id_account'],
                'credit'    =>  $this->purchase['credit'],
                'promocode' =>  $this->purchase['promocode']
            ]
        ]);


        try {

            /*print '<pre>';
            print_r($payment);
            print '<pre>';*/
            // REDIRECT USER TO getCheckoutUrl
            header("Location: " . $payment->getCheckoutUrl(), true, 303);
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
            if ($payment->isPaid() && !$payment->hasRefunds() && !$payment->hasChargebacks()) {
                /*
                 * The payment is paid and isn't refunded or charged back.
                 * At this point you'd probably want to start the process of delivering the product to the customer.
                 */
                $getPayment = array(
                    'account' => $payment->metadata->account,
                    'promocode' => $payment->metadata->promocode,
                    'credit' => $payment->metadata->credit,
                    'amount' => $payment->amount->value,
                    'currency' => $payment->amount->currency,
                    'method' => 'mollie'
                );

                parent::insert(
                    array(
                        'type' => 'history'
                    ), array(
                        'order_h' => $payment->metadata->order,
                        'status_h' => 'paid'
                    )
                );

                if (is_array($getPayment)) {
                    /*$this->add(array(
                            'type' => 'transaction',
                            'data' => $getPayment
                        )
                    );*/
                }


            } //elseif ($payment->isOpen()) {
            /*
             * The payment is open.
             */
            //}
            elseif ($payment->isPending()) {
                /*
                 * The payment is pending.
                 */
                parent::insert(
                    array(
                        'type' => 'history'
                    ), array(
                        'order_h' => $payment->metadata->order,
                        'status_h' => 'pending'
                    )
                );
            } elseif ($payment->isFailed()) {
                /*
                 * The payment has failed.
                 */
                parent::insert(
                    array(
                        'type' => 'history'
                    ), array(
                        'order_h' => $payment->metadata->order,
                        'status_h' => 'failed'
                    )
                );
            } elseif ($payment->isExpired()) {
                /*
                 * The payment is expired.
                 */
                parent::insert(
                    array(
                        'type' => 'history'
                    ), array(
                        'order_h' => $payment->metadata->order,
                        'status_h' => 'expired'
                    )
                );
            } elseif ($payment->isCanceled()) {
                /*
                 * The payment has been canceled.
                 */
                parent::insert(
                    array(
                        'type' => 'history'
                    ), array(
                        'order_h' => $payment->metadata->order,
                        'status_h' => 'canceled'
                    )
                );
            }
        }catch(Exception $e) {
            $logger = new debug_logger(MP_LOG_DIR);
            $logger->log('php', 'error', 'An error has occured : '.$e->getMessage(), debug_logger::LOG_MONTH);
        }

    }

    /**
     *
     */
    public function run(){

        if(isset($_GET['order'])){

            $data = $this->setItemsAccount();

            $mollie = $this->getItems('history',array('order_h'=>$_GET['order']),'one',false);

            $this->template->assign('mollie',$mollie);
            $this->template->display('mollie/index.tpl');

        }elseif(isset($_GET['webhook'])){

            $this->captureOrder(
                array(
                    'debug'=>false
                )
            );

        }else{
            if(isset($this->purchase)){

                $account = new plugins_account_public();
                $account->securePage();

                // config data for payment
                $config = array(
                    'plugin'    =>  'mollie',
                    'setName'   =>  $this->purchase['name'],
                    'price'     =>  $this->purchase['price'],
                    'currency'  =>  $this->purchase['currency'],
                    'order'     =>  filter_rsa::tokenID(),
                    'quantity'  =>  1,
                    'debug'     =>  false//pre,none,printer
                );

                $this->createPayment($config);
            }

        }
    }
}
?>