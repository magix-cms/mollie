<?php
include_once ('db.php');
class plugins_mollie_admin extends plugins_mollie_db
{
    /**
     * @var backend_model_template $template
     * @var backend_model_data $data
     * @var component_core_message $message
     * @var backend_controller_plugins $plugins
     * @var backend_model_language $modelLanguage
     * @var component_collections_language $collectionLanguage
     */
    protected backend_model_template $template;
    protected backend_model_data $data;
    protected component_core_message $message;
    protected backend_controller_plugins $plugins;
    protected backend_model_language $modelLanguage;
    protected component_collections_language $collectionLanguage;

    /**
     * @var string $action
     * @var string $tabs
     * @var string $type
     */
    public string
        $action,
        $controller;
    /**
     * constructeur
     */
    public function __construct(backend_model_template $t = null) {
        $this->template = $t instanceof backend_model_template ? $t : new backend_model_template();
        $this->plugins = new backend_controller_plugins();
        $formClean = new form_inputEscape();
        $this->message = new component_core_message($this->template);
        $this->data = new backend_model_data($this);

        // Global
        if (http_request::isGet('action')) {
            $this->action = $formClean->simpleClean($_GET['action']);
        } elseif (http_request::isPost('action')) {
            $this->action = $formClean->simpleClean($_POST['action']);
        }

        // POST
        if (http_request::isPost('apikey')) {
            $this->apikey = $formClean->simpleClean($_POST['apikey']);
        }

    }

    /**
     * Method to override the name of the plugin in the admin menu
     * @return string
     */
    public function getExtensionName()
    {
        return $this->template->getConfigVars('mollie_plugin');
    }
    // --- Database actions
    /**
     * Assign data to the defined variable or return the data
     * @param string $type
     * @param string|int|null $id
     * @param ?string $context
     * @param string|bool $assign
     * @return mixed
     */
    private function getItems(string $type, $id = null, ?string $context = null, $assign = true) {
        return $this->data->getItems($type, $id, $context, $assign);
    }

    /**
     * Update data
     * @param string $type
     * @param array $params
     */
    private function upd(string $type, array $params) {
        switch ($type) {
            case 'config':
                parent::update($type, $params);
                break;
        }
    }

    /**
     * Insert data
     * @param string $type
     * @param array $params
     */
    private function add(string $type, array $params) {
        switch ($type) {
            case 'config':
                parent::insert($type, $params);
                break;
        }
    }


    private function save(){
        $setData = $this->getItems('root',NULL,'one',false);
        if($setData['id_mollie']){

            $this->upd(
            'config',
               [
                   'apikey'      =>  $this->apikey,
                    'id'            =>  $setData['id_mollie']
               ]
            );
        }else{
            $this->add(
                'config',
                ['apikey'      =>  $this->apikey]
            );
        }
        $this->message->json_post_response(true, 'update');
    }
    /**
     * Execute plugin
     */
    public function run()
    {
        if (http_request::isRequest('action')) $this->action = form_inputEscape::simpleClean($_REQUEST['action']);
        if(http_request::isGet('controller')) $this->controller = form_inputEscape::simpleClean($_GET['controller']);
        if(http_request::isMethod('POST') && isset($this->action)) {
            switch ($this->action) {
                case 'edit':
                    $this->save();
                    break;
            }
        }else{
            $data = $this->getItems('root',NULL,'one',false);
            $this->template->assign('mollie', $data);
            $this->template->display('index.tpl');
        }
    }
}