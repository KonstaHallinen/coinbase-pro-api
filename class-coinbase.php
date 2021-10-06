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
    // COMMON
    //======================================================================

    /**
     * Constructor for CoinbaseExchance
     */
    public function __construct($key, $secret, $passphrase) {
        $this->key = $key;
        $this->secret = $secret;
        $this->passphrase = $passphrase;
        $this->base_url = 'https://api.exchange.coinbase.com/';
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
    private function send_request($endpoint, $public = true, $method = 'get', $query_params = '', $body = false, $timestamp = false) {
        $curl = curl_init();

        $headers = array(
            'Accept: application/json',
            'Content-Type:application/json'
        );
        $method = strtoupper($method);

        // Add signature to private API calls
        if(!$public) {
            $headers[] = 'CB-ACCESS-KEY:' . $this->key;
            $headers[] = 'CB-ACCESS-TIMESTAMP:' . time();
            $headers[] = 'CB-ACCESS-PASSPHRASE:' . $this->passphrase;

            if($method == 'GET') {
                $headers[] = 'CB-ACCESS-SIGN:' . $this->signature('/' . $endpoint);
            }
            else {
                $headers[] = 'CB-ACCESS-SIGN:' . $this->signature('/' . $endpoint, $method, $body, $timestamp);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
            }
        }

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->base_url . $endpoint . $query_params,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_USERAGENT => 'API Explorer',
            CURLOPT_CUSTOMREQUEST => $method,
        ]);
        
        $result = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        
        // cURL error
        if($err) {
            return array('error' => 'cURL error: ' . $err);
        }
        
        $response = json_decode($result, true);
        
        // API error
        if(array_key_exists('message', $response)) {
            return array('error' => 'API error: ' . $response['message']);
        }
        
        // Successful
        return json_decode($result, true);
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
                if(is_array($value)) {
                    foreach($value as $str) {
                        $validated_params[] = $param . '=' . $str;
                    }
                }
                else {
                    $validated_params[] = $param . '=' . $value;
                }
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

    
    
    //======================================================================
    // ACCOUNTS
    //======================================================================

    /**
     * Get a list of trading accounts (and balances) from the profile of the API key.
     *
     * @link    https://docs.cloud.coinbase.com/exchange/reference/exchangerestapi_getaccounts
     *
     * @return  array  All account balances
     */
    public function get_accounts() {
        $result = $this->send_request('accounts', false);
        return $result;
    }


    /**
     * Get information for a single account.
     *
     * @link    https://docs.cloud.coinbase.com/exchange/reference/exchangerestapi_getaccount
     *
     * @param   string  $account_id Account id
     *
     * @return  array   All account balances
     */
    public function get_account($account_id) {
        $result = $this->send_request('accounts/' . $account_id, false);
        return $result;
    }



    //======================================================================
    // PRODUCTS
    //======================================================================

    /**
     * Get a list of available currency pairs for trading.
     * 
     * @link    https://docs.cloud.coinbase.com/exchange/reference/exchangerestapi_getproducts
     *
     * @return  array
     */
    public function get_products() {
        $result = $this->send_request('products');
        return $result;
    }
    
    
    /**
     * Get a list of available currency pairs for trading.
     * 
     * @link    https://docs.cloud.coinbase.com/exchange/reference/exchangerestapi_getproduct
     *
     * @param   string  $product_id     Trade pair ID, like BTC-USD
     *
     * @return  array
     */
    public function get_product($product_id) {
        $result = $this->send_request('products/' . $product_id);
        return $result;
    }

    /**
     * Get a list of open orders for a product.
     * 
     * @link    https://docs.cloud.coinbase.com/exchange/reference/exchangerestapi_getproductbook
     *
     * @param   string  $product_id     Trade pair ID, like BTC-USD
     * @param   array   $get_parameters Get parameters for the query
     *
     * @return  array
     */
    public function get_product_book($product_id, $get_parameters = array()) {
        $params = $this->format_parameters($get_parameters);

        $result = $this->send_request('products/' . $product_id . '/book' . $params);
        return $result;
    }


    /**
     * Historic rates for a product. Rates are returned in grouped buckets based on requested granularity.
     * 
     * @link    https://docs.cloud.coinbase.com/exchange/reference/exchangerestapi_getproductcandles
     *
     * @param   string  $product_id Trade pair ID, like BTC-USD
     * @param   array   $get_parameters Get parameters for the query
     *
     * @return  array   [[time, low, high, open, close, volume], [1415398768, 0.32, 4.2, 0.35, 4.2, 12.3], ... ]
     */
    public function get_product_candles($product_id, $get_parameters = array('start' => false, 'end' => false, 'granularity' => self::DAY_IN_SECONDS)) {
        $result = array();
        $start = $get_parameters['start'] ? strtotime($get_parameters['start']) : strtotime('-4 week');
        $end = $get_parameters['end'] ?: strtotime('now');

        // Split large selections into multiple requests. If selection results in more than 300 data points, the request will be rejected.
        /*if(($end - $start) / $get_parameters['granularity'] > 300) {
            while() {
                // TODO
            }
        }*/

        $get_parameters['start'] = $this->format_timestamp(date('Y-m-d H:i:s', $start));
        $get_parameters['end'] = $this->format_timestamp(date('Y-m-d H:i:s', $end));
        $params = $this->format_parameters($get_parameters);
        $result = $this->send_request('products/' . $product_id . '/candles' . $params);
        return array($result);
    }


    /**
     * Gets 30 day and 24 hour stats for a product. Volume is in base currency units. Open, high, low are in quote currency units.
     * 
     * @link    https://docs.cloud.coinbase.com/exchange/reference/exchangerestapi_getproductstats
     *
     * @param   string  $product_id Trade pair ID, like BTC-USD
     *
     * @return  array
     */
    public function get_product_stats($product_id) {
        $result = $this->send_request('products/' . $product_id . '/stats');
        return $result;
    }


    /**
     * Gets snapshot information about the last trade (tick), best bid/ask and 24h volume.
     * 
     * @link    https://docs.cloud.coinbase.com/exchange/reference/exchangerestapi_getproductticker
     *
     * @param   string  $product_id Trade pair ID, like BTC-USD
     *
     * @return  array
     */
    public function get_product_ticker($product_id) {
        $result = $this->send_request('products/' . $product_id . '/ticker');
        return $result;
    }


    /**
     * Get a list of the latest trades for a product.
     * 
     * @link    https://docs.cloud.coinbase.com/exchange/reference/exchangerestapi_getproducttrades
     *
     * @param   string  $product_id Trade pair ID, like BTC-USD
     * @param   array   $get_parameters Get parameters for the query
     *
     * @return  array
     */
    public function get_product_trades($product_id, $get_parameters = array()) {
        $params = $this->format_parameters($get_parameters);

        $result = $this->send_request('products/' . $product_id . '/trades' . $params);
        return $result;
    }


    //======================================================================
    // ORDERS
    //======================================================================

    /**
     * Get a list of open and un-settled orders.
     * 
     * @link    https://docs.cloud.coinbase.com/exchange/reference/exchangerestapi_getfills
     *
     * @param   array   $get_parameters Get parameters for the query
     *
     * @return  array
     */
    public function get_fills($get_parameters = array()) {
        $params = $this->format_parameters($get_parameters);
        $result = $this->send_request('fills' . $params, false);
        return $result;
    }

    
    /**
     * Get a list of open and un-settled orders.
     * 
     * @link    https://docs.cloud.coinbase.com/exchange/reference/exchangerestapi_getorders
     *
     * @param   array   $get_parameters Get parameters for the query
     *
     * @return  array
     */
    public function get_orders($get_parameters = array()) {
        if(!array_key_exists('limit', $get_parameters)) {
            $get_parameters['limit'] = 100;
        }
        if(!array_key_exists('status', $get_parameters)) {
            $get_parameters['status'] = array('all');
        }

        $params = $this->format_parameters($get_parameters);
        $result = $this->send_request('orders' . $params, false);
        return $result;
    }

    
    /**
     * https://docs.cloud.coinbase.com/exchange/reference/exchangerestapi_getorder
     * 
     * @link    https://docs.cloud.coinbase.com/exchange/reference/exchangerestapi_getorder
     *
     * @param   string  $order_id   The order ID
     * @param   array   $get_parameters Get parameters for the query
     *
     * @return  array
     */
    public function get_order($order_id, $get_parameters = array()) {
        $params = $this->format_parameters($get_parameters);
        $result = $this->send_request('orders/' . $order_id . $params, false);
        return $result;
    }
}

?>