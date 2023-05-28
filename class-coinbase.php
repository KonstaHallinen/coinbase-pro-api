<?php
/**
 * PHP client for Coinbase Exchange REST API
 *
 * @author      Konsta Hallinen
 * @license     MIT (see LICENSE)
 * @link        https://github.com/KonstaHallinen/coinbase-pro-api
 * 
 * @deprecated  Coinbase has announced (https://www.coinbase.com/blog/hello-advanced-trade-goodbye-coinbase-pro)
 *              that they will close the Coinbase Pro and the Exchange REST API.
 *              You should migrate to Advanced Trade API instead: https://docs.cloud.coinbase.com/advanced-trade-api/docs/rest-api-overview 
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
     *
     * @param   string  $key        API key
     * @param   string  $secret     API secret
     * @param   string  $passphrase API passphrase
     * @param   bool    $sandbox    Use the sandbox API. Requires test credentials: https://public.sandbox.pro.coinbase.com/profile/api
     */
    public function __construct($key, $secret, $passphrase, $sandbox = false) {
        $this->key = $key;
        $this->secret = $secret;
        $this->passphrase = $passphrase;
        $this->base_url = $sandbox ? 'https://api-public.sandbox.exchange.coinbase.com/' : 'https://api.exchange.coinbase.com/';
    }


    /**
     * Create the Coinbase signature required in every private API call.
     *
     * @param   string  $endpoint   The API endpoint without the leading slash
     * @param   string  $method     HTTP request method
     * @param   array   $body       Request body
     * @param   string  $timestamp  Epoch timestamp
     *
     * @return  string  Base 64 encoded signature string for CB-ACCESS-SIGN header.
     */
    private function signature($endpoint, $method, $body = '', $timestamp = false) {
        $body = is_array($body) ? json_encode($body) : $body;
        $timestamp = $timestamp ?: time();

        $what = $timestamp . $method . $endpoint . $body;

        return base64_encode(hash_hmac("sha256", $what, base64_decode($this->secret), true));
    }
    

    /**
     * Send a request to the Coinbase API.
     * 
     * @param   string  $endpoint       API endpoint without leading slash (and optional query params if using get)
     * @param   string  $method         GET|POST|DELETE
     * @param   array   $body           Request body
     * @param   string  $timestamp      Timestamp in ISO 8601 format with microseconds
     *
     * @return  array   The response
     */
    private function send_request($endpoint, $public = true, $method = 'get', $body = array(), $timestamp = false) {
        $curl = curl_init();

        $headers = array(
            'Accept: application/json',
            'Content-Type:application/json'
        );
        $method = strtoupper($method);

        // Add signature to private API calls
        if(!$public) {
            $timestamp = $timestamp ?: time(); // TODO make sure if epoch is always good for POST requests
            
            $headers[] = 'CB-ACCESS-KEY:' . $this->key;
            $headers[] = 'CB-ACCESS-TIMESTAMP:' . $timestamp;
            $headers[] = 'CB-ACCESS-PASSPHRASE:' . $this->passphrase;

            if($method == 'GET' || $method == 'DELETE') {
                $headers[] = 'CB-ACCESS-SIGN:' . $this->signature('/' . $endpoint, $method);
            }
            else {
                $headers[] = 'CB-ACCESS-SIGN:' . $this->signature('/' . $endpoint, $method, $body, $timestamp);
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));
            }
        }

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->base_url . $endpoint,
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
        
        // Returned value is not JSON (for example the order deletion returns a plain string that is not in JSON format)
        if(!is_array($response)) {
            return array($result);
        }
        
        // API error
        if(array_key_exists('message', $response)) {
            return array('error' => 'API error: ' . $response['message']);
        }
        
        // Successful
        return $response;
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
     * @return  array
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
     * @return  array
     */
    public function get_account($account_id) {
        $result = $this->send_request('accounts/' . $account_id, false);
        return $result;
    }


    /**
     * List the holds of an account that belong to the same profile as the API key.
     *
     * @link    https://docs.cloud.coinbase.com/exchange/reference/exchangerestapi_getaccountholds
     *
     * @param   string  $account_id Account id
     * @param   array   $get_parameters Get parameters for the query
     *
     * @return  array
     */
    public function get_account_holds($account_id, $get_parameters = array()) {
        $params = $this->format_parameters($get_parameters);
        
        $result = $this->send_request('accounts/' . $account_id . '/holds' . $params, false);
        return $result;
    }


    /**
     * List ledger activity for an account.
     *
     * @link    https://docs.cloud.coinbase.com/exchange/reference/exchangerestapi_getaccountledger
     *
     * @param   string  $account_id Account id
     * @param   array   $get_parameters Get parameters for the query
     *
     * @return  array
     */
    public function get_account_ledger($account_id, $get_parameters = array()) {
        $params = $this->format_parameters($get_parameters);
        
        $result = $this->send_request('accounts/' . $account_id . '/ledger' . $params, false);
        return $result;
    }


    /**
     * Lists past withdrawals and deposits for an account.
     *
     * https://docs.cloud.coinbase.com/exchange/reference/exchangerestapi_getaccounttransfers
     *
     * @param   string  $account_id Account id
     * @param   array   $get_parameters Get parameters for the query
     *
     * @return  array
     */
    public function get_account_transfers($account_id, $get_parameters = array()) {
        $params = $this->format_parameters($get_parameters);
        
        $result = $this->send_request('accounts/' . $account_id . '/transfers' . $params, false);
        return $result;
    }

    
    
    //======================================================================
    // COINBASE ACCOUNTS
    //======================================================================

    /**
     * Get all the user's available Coinbase wallets (These are the wallets/accounts that are used for buying and selling on www.coinbase.com)
     *
     * @link    https://docs.cloud.coinbase.com/exchange/reference/exchangerestapi_getcoinbaseaccounts
     *
     * @return  array
     */
    public function get_coinbase_accounts() {
        $result = $this->send_request('coinbase-accounts', false);
        return $result;
    }


    /**
     * Generate a one-time crypto address for depositing crypto.
     * 
     * @link    https://docs.cloud.coinbase.com/exchange/reference/exchangerestapi_postcoinbaseaccountaddresses
     * @see     get_coinbase_accounts()
     *
     * @param   string  $account_id Coinabse account id
     *
     * @return  array
     */
    public function generate_crypto_address($account_id) {
        $result = $this->send_request('coinbase-accounts/' . $account_id . '/addresses', false, 'post');
        return $result;
    }

    

    //======================================================================
    // FEES
    //======================================================================

    /**
     * Gets a list of all of the current user's profiles.
     * 
     * @link    https://docs.cloud.coinbase.com/exchange/reference/exchangerestapi_getfees
     *
     * @return  array
     */
    public function get_fees() {
        $result = $this->send_request('fees');
        return $result;
    }



    //======================================================================
    // ORDERS
    //======================================================================

    /**
     * Get a list of fills. A fill is a partial or complete match on a specific order.
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
     * Cancel all open orders
     * 
     * @link    https://docs.cloud.coinbase.com/exchange/reference/exchangerestapi_deleteorders
     *
     * @param   array   $get_parameters Get parameters for the query
     *
     * @return  array
     */
    public function cancel_orders($get_parameters = array()) {
        $params = $this->format_parameters($get_parameters);
        $result = $this->send_request('orders' . $params, false, 'delete');
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
     * Create an order.
     * 
     * @link    https://docs.cloud.coinbase.com/exchange/reference/exchangerestapi_postorders
     *
     * @param   array   $body   Body for the query
     *
     * @return  array
     */
    public function create_order($body) {
        $result = $this->send_request('orders', false, 'post', $body);
        return $result;
    }

    
    /**
     * Get a single order by id.
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

    
    /**
     * Cancel a single open order by order id.
     * 
     * @link    https://docs.cloud.coinbase.com/exchange/reference/exchangerestapi_deleteorder
     *
     * @param   string  $order_id   The order ID
     *
     * @return  array
     */
    public function cancel_order($order_id) {
        $result = $this->send_request('orders/' . $order_id, false, 'delete');
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
        $start = !empty($get_parameters['start']) ? strtotime($get_parameters['start']) : strtotime('-4 week');
        $end = !empty($get_parameters['end']) ? strtotime($get_parameters['end']) : strtotime('now');

        // Split large selections into multiple requests. If selection results in more than 300 data points, the request will be rejected.
        /*if(($end - $start) / $get_parameters['granularity'] > 300) {
            while() {
                // TODO
            }
        }*/

        $get_parameters['start'] = $this->format_timestamp(gmdate('Y-m-d H:i:s', $start));
        $get_parameters['end'] = $this->format_timestamp(gmdate('Y-m-d H:i:s', $end));
        $params = $this->format_parameters($get_parameters);
        
        $result = $this->send_request('products/' . $product_id . '/candles' . $params);
        return $result;
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
    // PROFILES
    //======================================================================

    /**
     * Gets a list of all of the current user's profiles.
     * 
     * @link    https://docs.cloud.coinbase.com/exchange/reference/exchangerestapi_getprofiles
     *
     * @param   array   $get_parameters Get parameters for the query
     *
     * @return  array
     */
    public function get_profiles($get_parameters = array()) {
        $params = $this->format_parameters($get_parameters);

        $result = $this->send_request('profiles' . $params, false);
        return $result;
    }

    /**
     * Create a new profile.
     * 
     * @link    https://docs.cloud.coinbase.com/exchange/reference/exchangerestapi_postprofile
     *
     * @param   array|string    $body   Profile name
     *
     * @return  array
     */
    public function create_profile($body) {
        if(is_string($body)) {
            $body = array(
                'name' => $body
            );
        }
        
        $result = $this->send_request('profiles', false, 'post', $body);
        return $result;
    }

    
    /**
     * Gets a list of all of the current user's profiles.
     * 
     * @link    https://docs.cloud.coinbase.com/exchange/reference/exchangerestapi_getprofiles
     *
     * @param   array   $profile_id     Profile / portfolio ID
     * @param   array   $get_parameters Get parameters for the query
     *
     * @return  array
     */
    public function get_profile($profile_id, $get_parameters = array()) {
        $params = $this->format_parameters($get_parameters);

        $result = $this->send_request('profiles/' . $profile_id . $params, false);
        return $result;
    }

    

    //======================================================================
    // USERS
    //======================================================================

    /**
     * Gets exchange limits information for a single user.
     * 
     * @link    https://docs.cloud.coinbase.com/exchange/reference/exchangerestapi_getprofiles
     *
     * @param   array   $user_id    User ID
     *
     * @return  array
     */
    public function get_user_exchange_limits($user_id) {
        $result = $this->send_request('users/' . $user_id . '/exchange-limits', false);
        return $result;
    }
}

?>
