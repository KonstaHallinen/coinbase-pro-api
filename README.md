A PHP class for communicating with the [Coinbase Pro API](https://docs.pro.coinbase.com/).
## Disclaimer
**Please note that the script doesn't yet have a function for every Coinbase API method**. This is a work in progress and will be updated as my own project develops.
## Usage
Refer to the official API documentation for more detailed information about the possible query parameters and returned values. Each function is named after the documentation URL, so for example [Get product trades](https://docs.cloud.coinbase.com/exchange/reference/exchangerestapi_getproducttrades) would use a function called get_product_trades and so on.
Here is a crude example:

    // Include the file
    require_once('class-coinbase.php');
	
	// Initialise the class. You can leave the variables empty if you only need to use public methods.
    $coinbase = new CoinbaseExchange('Your API key', 'Your API secret', 'Your API passphrase');

	// Call a function. GET parameters can be added to the API call by using an array.
    $trades = $coinbase->get_product_trades('USDC-EUR', array('limit' => '2'));
    
    /* Returned JSON is automatically converted to an array so var_dump($trades); would print something like this:
    array(2) {
      [0]=>
      array(5) {
        ["time"]=>
        string(27) "2021-09-27T17:58:59.424327Z"
        ["trade_id"]=>
        int(3852088)
        ["price"]=>
        string(10) "0.85500000"
        ["size"]=>
        string(11) "56.27000000"
        ["side"]=>
        string(4) "sell"
      }
      [1]=>
      array(5) {
        ["time"]=>
        string(27) "2021-09-27T17:58:54.818301Z"
        ["trade_id"]=>
        int(3852087)
        ["price"]=>
        string(10) "0.85500000"
        ["size"]=>
        string(13) "1498.71000000"
        ["side"]=>
        string(4) "sell"
      }
    } */
