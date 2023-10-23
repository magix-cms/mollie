<?php
class plugins_mollie_db
{
    /**
     * @param $config
     * @param bool $params
     * @return mixed|null
     * @throws Exception
     */
    /**
     * @var debug_logger $logger
     */
    protected debug_logger $logger;

    /**
     * @param array $config
     * @param array $params
     * @return array|bool
     */
    public function fetchData(array $config, array $params = []) {
        if ($config['context'] === 'all') {
            switch ($config['type']) {
                case 'data':
                    $query = 'SELECT mo.* FROM mc_mollie AS mo';
                    break;
                default:
                    return false;
            }

            try {
                return component_routing_db::layer()->fetchAll($query, $params);
            }
            catch (Exception $e) {
                if(!isset($this->logger)) $this->logger = new debug_logger(MP_LOG_DIR);
                $this->logger->log('statement','db',$e->getMessage(),$this->logger::LOG_MONTH);
            }
        }
        elseif ($config['context'] === 'one') {
            switch ($config['type']) {
                case 'root':
                    $query = 'SELECT * FROM mc_mollie ORDER BY id_mollie DESC LIMIT 0,1';
                    break;
                case 'history':
                    $query = 'SELECT * FROM mc_mollie_history WHERE order_h = :order_h';
                    break;
                case 'lastHistory':
                    $query = 'SELECT * FROM mc_mollie_history ORDER BY id_mollie_h DESC LIMIT 0,1';
                    break;
                default:
                    return false;
            }

            try {
                return component_routing_db::layer()->fetch($query, $params);
            }
            catch (Exception $e) {
                if(!isset($this->logger)) $this->logger = new debug_logger(MP_LOG_DIR);
                $this->logger->log('statement','db',$e->getMessage(),$this->logger::LOG_MONTH);
            }
        }
        return false;
    }
    /**
     * @param string $type
     * @param array $params
     * @return bool
     */
    public function insert(string $type, array $params = []): bool {
        switch ($type) {
            case 'config':

                $query = 'INSERT INTO mc_mollie (apikey)
                VALUE(:apikey)';

                break;
            case 'history':

                $query = 'INSERT INTO mc_mollie_history (order_h,status_h)
                VALUE(:order_h,:status_h)';

                break;
            default:
                return false;
        }

        try {
            component_routing_db::layer()->insert($query,$params);
            return true;
        }
        catch (Exception $e) {
            if(!isset($this->logger)) $this->logger = new debug_logger(MP_LOG_DIR);
            $this->logger->log('statement','db',$e->getMessage(),$this->logger::LOG_MONTH);
            return false;
        }

    }

    /**
     * @param string $type
     * @param array $params
     * @return bool
     */
    public function update(string $type, array $params = []): bool {
        switch ($type) {
            case 'config':
                $query = 'UPDATE mc_mollie
                    SET apikey=:apikey
                    WHERE id_mollie=:id';
                break;
            default:
                return false;
        }

        try {
            component_routing_db::layer()->update($query,$params);
            return true;
        }
        catch (Exception $e) {
            if(!isset($this->logger)) $this->logger = new debug_logger(MP_LOG_DIR);
            $this->logger->log('statement','db',$e->getMessage(),$this->logger::LOG_MONTH);
            return false;
        }
    }
}
?>