
# Coinbase API PHP class
A single class PHP client made for communicating with the [Coinbase Pro API](https://docs.cloud.coinbase.com/exchange/reference/).

## Disclaimer
**This is an unofficial project and made without any cooperation with the Coinbase staff.**

Please note that the script doesn't yet have a function for every Coinbase API method. This is a work in progress and will be updated as my own project develops.
## Usage
Refer to the official API documentation for more detailed information about the possible query parameters and returned values. Almost every* function is named after the documentation URL, so for example [Get product trades (getproducttrades)](https://docs.cloud.coinbase.com/exchange/reference/exchangerestapi_getproducttrades) would use a function called get_product_trades and so on.

*Exceptions:
| Function | Doc URL | Description |
|--|--|--|
| `create_order` | postorders | Create a new order |
| `cancel_order` | deleteorder | Cancel a single open order |
| `cancel_orders` | deleteorders | Cancel all open orders |


### Here is a basic code example:

    // Include the file
    require_once('class-coinbase.php');
	
	// Initialise the class. You can leave the variables empty if you only need to use public methods.
    $coinbase = new CoinbaseExchange('Your API key', 'Your API secret', 'Your API passphrase');

	// Call a function. GET parameters can be added to the API call by using an array.
    $trades = $coinbase->get_product_trades('USDC-EUR', array('limit' => '2'));
    
    /* Returned JSON is automatically converted in to an array so var_dump($trades); would print something like this:
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


Errors will also be returned as arrays. There are basically two types of errors that could occur:

	// API error. Caused most likely by wrong credentials or missing a required parameter. Example:
	array(1) {
	  ["error"]=>
	  string(24) "API error: Unauthorized."
	}
	
    // cURL error. Caused most likely by connection issues. Example:
    array(1) {
	  ["error"]=>
	  string(61) "cURL error: Could not resolve host: api.exchange.coinbase.com"
	}
    
## Methods
List of supported methods. Gives a rough idea of project completeness.

Accounts
- [x] Get all accounts for a profile
- [x] Get a single account by id
- [x] Get a single account's holds
- [x] Get a single account's ledger
- [x] Get a single account's transfers

Coinbase accounts
- [x] Get all Coinbase wallets
- [x] Generate crypto address

Conversions
- [ ] Convert currency
- [ ] Get a conversion

Currencies
- [ ] Get all known currencies
- [ ] Get a currency

Transfers
- [ ] Deposit from Coinbase account
- [ ] Deposit from payment method
- [ ] Get all payment methods
- [ ] Get all transfers
- [ ] Get a single transfer
- [ ] Withdraw to Coinbase account
- [ ] Withdraw to crypto address
- [ ] Get fee estimate for crypto withdrawal
- [ ] Withdraw to payment method

Fees
- [x] Get fees

Orders
- [x] Get all fills
- [x] Get all orders
- [x] Cancel all orders
- [x] Create a new order
- [x] Get single order
- [x] Cancel an order

Coinbase price oracle
- [ ] Get signed prices

Products
- [x] Get all known trading pairs
- [x] Get single product
- [x] Get product book
- [x] Get product candles
- [x] Get product stats
- [x] Get product ticker
- [x] Get product trades

Profiles
- [x] Get profiles
- [x] Create a profile
- [ ] Transfer funds between profiles
- [x] Get profile by id
- [ ] Rename a profile
- [ ] Delete a profile

Reports
- [ ] Get all reports
- [ ] Create a report
- [ ] Get a report

Users
- [x] Get user exchange limits