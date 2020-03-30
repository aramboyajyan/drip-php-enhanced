# Drip API Wrapper - PHP

This is a fork of the PHP library found [here](https://github.com/DripEmail/drip-php).

An object-oriented PHP wrapper for Drip's REST API v2.0

## Installation

Run `composer require dripemail/drip-php`

## Authentication

For private integrations, you may use your personal API Token (found
[here](https://www.getdrip.com/user/edit)) via the `api_key` setting:

```php
$client = new \Drip\Client("YOUR_API_KEY", "YOUR_ACCOUNT_ID");
```

For public integrations, pass in the user's OAuth token via the `access_token`
setting:

```php
$client = new \Drip\Client("YOUR_ACCESS_TOKEN", "YOUR_ACCOUNT_ID");
```

Your account ID can be found [here](https://www.getdrip.com/settings/site).
Most API actions require an account ID, with the exception of methods like
the "list accounts" endpoint.


## PHP version support

We attempt to support versions of PHP that are supported upstream: http://php.net/supported-versions.php
