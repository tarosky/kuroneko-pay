# Kuroneko Web Collect

Contributors: yamatofinancial  
Tags: woocommerce, payment, japan, yamato  
Requires at least: 4.7  
Requires PHP: 5.4  
Tested up to: 4.9.6  
Stable tag: 1.2.4  
License: GPL v3 or later  
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Add Kuroneko Web Collect gateway to WooCommerce.

## Description

This plugin adds [Kuroneko Web Collect](https://www.yamatofinancial.jp/wc/)'s payment gateway to your [WooCommerce](https://woocommerce.com) site.

Available payment methods are below:

- Credit Card
- Convenience Store Online

### Requirements

- [Kuroneko Web Collect](https://www.yamatofinancial.jp/wc/) account.
- WooCommerce 3.0 and over.
- PHP 5.4 and over.
- [Subscription Extension](http://www.woothemes.com/products/woocommerce-subscriptions/) for recurring payment.
- Having or belonging to a legal organization in Japan.

### About Kuroneko Web Collect

Kuroneko Web Collect is an online payment gateway service by [YAMATO FINANCIAL Co.,Ltd.](https://www.yamatofinancial.jp/), which enables credit card payment and payment at convenience store.

Please [send an account request](https://www.yamatofinancial.jp/form/order1_input.php) and getting started.

* After submission, our account manager will contact you.
* Kuroneko Web collect requires an inspection of your business by YAMATO FINANCIAL. As a result, you might not have an account.


## Installation

Installation from admin screen is recommended.
Go to "Plugins > Add New" and search with "kuroneko Web Collect".

For manual installation:

- Download zip and unpack it.
- Upload unpacked folder in `wp-content/plugins` directory as `kuroneko-pay`
- Go to admin screen "Plugins" and activate it.

### Setting

After activation, go to WooCommerce setting screen "WooCommerce > Setting".
Click "Checkout" tab there, and you will find 3 payment methods added.

- Credit card(Japan)
- Credit card(International) **NOTICE:** Kuroneko Web Collect International shippping service is required.
- Convenience Store

For each of them, you have to enter `Shop Code` and `Option Key` which you will be given from [Kuroneko Web Collect](https://www.yamatofinancial.jp/wc/).

* More detailed information for Option Key, refer [this page](https://na-ab24.marketo.com/rs/250-BBD-746/images/accesskey.pdf).
* For convenience store gateway, you have to specify available store brands.

After setup, customers would be able to see the payment gateway on checkout page.

## Frequently Asked Questions

Feel free to contact us at support forum.

## Changelog

### 1.2.4

* Fix JS error on clicking checkout button.


### 1.2.3

* Add new otion "Hide option service". Default is "no".

### 1.2.2

* Fix token service bug.

### 1.2.0

* Update minimum requirement for WooCommerce to 3.0.

### 1.0.0

- Initial release.
