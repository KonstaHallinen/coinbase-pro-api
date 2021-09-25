<?php
/**
 * Coinbase API handler
 */
class CoinbaseExchange {
    
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
     * Send a request to the Coinbase API.
     * 
     * @param   string          $endpoint       API endpoint without leading slash (and optional query params if using get)
     * @param   string          $method         GET / POST / DELETE
     * @param   string          $query_params   Query parameters
     * @param   string|array    $body           Request body
     * @param   string          $timestamp      Timestamp in ISO 8601 format with microseconds, ie. 2014-11-06T10:34:47.123456Z
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
     * @param   array   $get_parameters Get parameters for the query, like array(parameter_name => parameter_value)
     *
     * @return  array
     */
    public function get_product_order_book($product_id, $get_parameters = array()) {
        $params = $this->format_parameters($get_parameters);
        
        $result = $this->send_request('products/' . $product_id . '/book' . $params);
        return $result;
    }

    
    /**
     * Get a list of the latest trades for a product.
     * 
     * @link    https://docs.pro.coinbase.com/#get-trades
     *
     * @param   string  $product_id Trade pair ID, like BTC-USD
     * @param   array   $get_parameters Get parameters for the query, like array(parameter_name => parameter_value)
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