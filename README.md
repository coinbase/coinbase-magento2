# coinbase-magento2

Accept Bitcoin on your Magento2-powered website with Coinbase.

## Installation

If you don't have a Coinbase account, sign up at https://www.coinbase.com/merchants. Coinbase offers daily payouts for merchants in the United States. For more infomation on setting up payouts, see https://www.coinbase.com/docs/merchant_tools/payouts.

### Magento Connect

The plugin has been submitted to the Magento Connect Marketplace and is pending approval.

### Manual installation

1. Sign onto your Magento 2 server as the Magento file system owner.
1. Navigate to the root of your Magento2 installation.
1. Add the following entry to ./composer.json under "repositories"

```json
{
    "type": "vcs",
    "url": "https://github.com/coinbase/coinbase-magento2"
}
```

1. Add the following line to ./composer.json under "require"

```json
"coinbase/module-coinbase-magento2-gateway": "~1.0",
```

1. Run the following command:

```bash
composer update &&\
  composer install &&\
  bin/magento setup:upgrade &&\
  bin/magento setup:static-content:deploy
```

## Set-up

1. Create a Coinbase API Key (https://www.coinbase.com/settings/api) with the `wallet:checkouts:create` permission.
1. Open Magento Admin and navigate to Stores -> Configuration -> Sales -> Payment Methods
1. Scroll down to 'Coinbase'. If you can't find 'Coinbase', try clearing your Magento cache.
1. Set "Enabled" to "Yes" and enter your generated API Key and API Secret.
1. Click "Save Config" at the upper right part of the screen.

## License
[Open Source License](LICENSE)
