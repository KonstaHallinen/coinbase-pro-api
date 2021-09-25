<?php
/**
 * Coinbase API handler
 */
class CoinbaseExchange {
    
    // Time constants
    const MINUTE_IN_SECONDS = 60;
    const HOUR_IN_SECONDS   = self::MINUTE_IN_SECONDS * 60;
    const DAY_IN_SECONDS    = self::HOUR_IN_SECONDS * 24;
    const WEEK_IN_SECONDS   = self::DAY_IN_SECONDS * 7;
    const MONTH_IN_SECONDS  = self::DAY_IN_SECONDS * 30;
    const YEAR_IN_SECONDS   = self::DAY_IN_SECONDS * 365;
    
    

    //======================================================================
    // COMMON FUNCTIONS
    //======================================================================

    /**
     * Constructor for CoinbaseExchance
     */
    public function __construct($key, $secret, $passphrase) {
        $this->key = $key;
        $this->secret = $secret;
        $this->passphrase = $passphrase;
        $this->base_url = 'https://api.pro.coinbase.com/';
    }


    /**
     * Create the Coinbase signature required in every API call.
     *
     * @return  string  Base 64 encoded signature string for CB-ACCESS-SIGN header.
     */
    private function signature($endpoint = '', $method = 'GET', $body = '', $timestamp = false) {
        $body = is_array($body) ? json_encode($body) : $body;
        $timestamp = $timestamp ? $timestamp : time();

        $what = $timestamp . $method . $endpoint . $body;

        return base64_encode(hash_hmac("sha256", $what, base64_decode($this->secret), true));
    }


    /**
     * Format query parameter string
     *
     * @param   array   $params GET parameters
     *
     * @return  string  Formatted string for request url
     */
    private function format_parameters($params) {
        $validated_params = array();

        foreach($params as $param => $value) {
            if($value !== null) {
                $validated_params[] = $param . '=' . $value;
            }
        }

        $query = !empty($validated_params) ? '?' . implode('&', $validated_params) : '';
        return $query;
    }


    /**
     * Format timestamp to ISO8601, ie. 2014-11-06T10:34:47.123456Z
     *
     * @param   string  $date   Date as a string, Y-m-d H:i:s
     *
     * @return  string  ISO8601 timestamp
     */
    private function format_timestamp($date = '') {
        $datetime = new DateTime($date);
        return $datetime->format('Y-m-d\TH:i:s.u');
    }


    /**
     * Send a request to the Coinbase API.
     * 
     * @param   string          $endpoint       API endpoint without leading slash (and optional query params if using get)
     * @param   string          $method         GET / POST / DELETE
     * @param   string          $query_params   Query parameters
     * @param   string|array    $body           Request body
     * @param   string          $timestamp      Timestamp in ISO 8601 format with microseconds
     *
     * @return  string  All account balances in JSON format.
     */
    public function send_request($endpoint, $public = true, $method = 'get', $query_params = '', $body = false, $timestamp = false) {
        $ch = curl_init();

        $headers = array('Content-Type:application/json', 'limit:1');
        $method = strtoupper($method);

        if(!$public) {
            $headers[] = 'CB-ACCESS-KEY:' . $this->key;
            $headers[] = 'CB-ACCESS-TIMESTAMP:' . time();
            $headers[] = 'CB-ACCESS-PASSPHRASE:' . $this->passphrase;

            if($method == 'GET') {
                $headers[] = 'CB-ACCESS-SIGN:' . $this->signature('/' . $endpoint);
            }
            else {
                $headers[] = 'CB-ACCESS-SIGN:' . $this->signature('/' . $endpoint, $method, $body, $timestamp);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

                if($method == 'POST') {
                    curl_setopt($ch, CURLOPT_POST, true);
                }
                else if($method == 'DELETE') {
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                }
            }
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_USERAGENT, 'fake');
        curl_setopt($ch, CURLOPT_URL, $this->base_url . $endpoint . $query_params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  
        $result = curl_exec($ch);
        curl_close($ch);

        return json_decode($result, true);
    }



    //======================================================================
    // ACCOUNTS
    //======================================================================

    /**
     * Get a list of trading accounts (and balances) from the profile of the API key.
     *
     * @link    https://docs.pro.coinbase.com/#list-accounts
     *
     * @return  array  All account balances
     */
    public function list_accounts() {
        $result = $this->send_request('accounts', false);
        return $result;
    }



    //======================================================================
    // PRODUCTS
    //======================================================================

    /**
     * Get a list of available currency pairs for trading.
     * 
     * @link    https://docs.pro.coinbase.com/#get-products
     *
     * @return  array
     */
    public function get_products() {
        $result = $this->send_request('products');
        return $result;
    }


    /**
     * Get a list of open orders for a product.
     * 
     * @link    https://docs.pro.coinbase.com/#get-product-order-book
     *
     * @param   string  $product_id     Trade pair ID, like BTC-USD
     * @param   array   $get_parameters Get parameters for the query
     *
     * @return  array
     */
    public function get_product_order_book($product_id, $get_parameters = array()) {
        $params = $this->format_parameters($get_parameters);

        $result = $this->send_request('products/' . $product_id . '/book' . $params);
        return $result;
    }


    /**
     * Historic rates for a product. Rates are returned in grouped buckets based on requested granularity.
     * 
     * @link    https://docs.pro.coinbase.com/#get-historic-rates
     *
     * @param   string  $product_id Trade pair ID, like BTC-USD
     * @param   array   $get_parameters Get parameters for the query
     *
     * @return  array   [[time, low, high, open, close, volume], [1415398768, 0.32, 4.2, 0.35, 4.2, 12.3], ... ]
     */
    public function get_historic_rates($product_id, $get_parameters = array('start' => false, 'end' => false, 'granularity' => self::DAY_IN_SECONDS)) {
        $params = $this->format_parameters($get_parameters);
        $result = array();
        $start = $get_parameters['start'] ?: strtotime('-4 week');
        $end = $get_parameters['end'] ?: strtotime('now');

        // TODO
        // Split large selections into multiple requests. If selection results in more than 300 data points, the request will be rejected.
        /*if(array_key_exists('start', $get_parameters)) {
            $start = $get_parameters['start']
        }*/
        
        
        // $result = $this->send_request('products/' . $product_id . '/candles' . $params);
        return array($params, $start, $end);
    }



    /**
     * Get a list of the latest trades for a product.
     * 
     * @link    https://docs.pro.coinbase.com/#get-trades
     *
     * @param   string  $product_id Trade pair ID, like BTC-USD
     * @param   array   $get_parameters Get parameters for the query
     *
     * @return  array
     */
    public function get_trades($product_id, $get_parameters = array()) {
        $params = $this->format_parameters($get_parameters);

        $result = $this->send_request('products/' . $product_id . '/trades' . $params);
        return $result;
    }


    /**
     * Get 24 hr stats for the product. Volume is in base currency units. Open, high, low are in quote currency units.
     * 
     * @link    https://docs.pro.coinbase.com/#get-24hr-stats
     *
     * @param   string  $product_id Trade pair ID, like BTC-USD
     *
     * @return  array
     */
    public function get_24hr_stats($product_id) {
        $result = $this->send_request('products/' . $product_id . '/stats');
        return $result;
    }



    //======================================================================
    // ORDERS
    //======================================================================

    /**
     * Get a list of open and un-settled orders.
     * 
     * @link    https://docs.pro.coinbase.com/#get-open-orders
     *
     * @param   string  $product_id Trade pair ID, like BTC-USD
     * @param   string  $limit      Amount of orders, default 1000
     *
     * @return  array
     */
    public function list_orders($product_id = false, $limit = false) {
        $params = $this->$this->format_parameters(array(
            'limit' => $limit
        ));

        $result = $this->send_request('orders/' . $params, false);
        return $result;
    }
}

?>