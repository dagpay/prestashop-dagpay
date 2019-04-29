# Dagpay for PrestaShop

Accept dagcoin payments on your PrestaShop store

Dagpay helps you to accept lightning fast dagcoin payments directly from your eCommerce store. Start accepting Dagpay payments for your business today and say goodbye to the slow transactions times, fraudulent chargebacks and to the enormous transaction fees.

### Key features of Dagpay
* Checkout with Dagpay and accept dagcoin payments on your PrestaShop store;
* Prices in your local currency, let customers pay with dagcoin;
* Wallet to wallet transactions - Dagpay does not have access to your dagcoins and/or your private keys. Your funds move safely directly to your provided DagWallet address;
* Overview of all your dagcoin payments in the Dagpay merchant dashboard at https://dagpay.io/

## Installation

1. Download the [PrestaShop module .zip file](https://github.com/dagpay/prestashop-dagpay/releases/download/v1.0.0/prestashop-dagpay.zip);
2. Open **Installed modules** tab;
3. Click **Upload module** and select the downloaded extension .zip file.

## Setup & Configuration

After installing and activating the Dagpay extension in your PrestaShop Admin Panel, complete the setup according to the following instructions:

1. Log in to your Dagpay account and head over to **Merchant Tools** > **Integrations** and click **ADD INTEGRATION**.
2. Add your environment Name, Description and choose your Wallet for receiving payments.
3. Add the status URL for server-to-server communication and redirect URLs.
* The status URL for PrestaShop is https://`store_base_path`?fc=module&module=dagpay&controller=callback ( change `store_base_path` with your store domain address, for example https://myprestashopstore.com?fc=module&module=dagpay&controller=callback;
Redirect URLs to redirect back to your store from the payment view depending on the final outcome of the transaction (can be set the same for all states). For example https://myprestashopstore.com/success/
Save the environment and copy the generated environment ID, user ID and secret keys and in your PrestaShop Admin panel navigate to the **Installed modules** section, enable the Dagpay payment gateway and enter the keys to the corresponding fields.
Save the changes and Dagpay should be working on your PrestaShop store.
