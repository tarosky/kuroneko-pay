# Kuroneko Web Collect

Contributors: yamatofinancial  
Tags: woocommerce, payment, japan, yamato  
Requires at least: 4.7  
Requires PHP: 5.4  
Tested up to: 4.9.4  
Stable tag: 1.2.1  
License: GPL v3 or later  
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Add Kuroneko Web Collect gateway to WooCommerce.

## Description

This plugin adds [Kuroneko Web Collect](https://www.yamatofinancial.jp/wc/)'s payment gateway to your [WooCommerce](https://woocommerce.com) site.

Available payment methods are below:

- Credit Card
- Convenience Store（Japanese Drug Store）

### Requirements

- [Kuroneko Web Collect](https://www.yamatofinancial.jp/wc/) account.
- WooCommerce 3.0 and over.
- PHP 5.4 and over.
- [Subscription Extension](http://www.woothemes.com/products/woocommerce-subscriptions/) for recurring payment.
- Having or belonging to a legal organization in Japan.

### About Kuroneko Pay

Kuroneko Pay is official plugin by [YAMATO FINANCIAL Co.,Ltd.](https://www.yamatofinancial.jp/) of
a traditional logistic group [YAMATO HOLDINGS CO., LTD.](http://www.yamato-hd.co.jp/).

Kuroneko Pay can **ship internationally**.

You can start from free plan which charges no initial cost.
Please [submit registration](https://www.yamatofinancial.jp/form/order1_input.php)
or [contact us](https://www.yamatofinancial.jp/form/inquiry_input.php).

## Installation

Installation from admin screen is recommended.
Go to "Plugins > Add New" and search with "kuroneko pay".

For manual installation:

- Download zip and unpack it.
- Upload unpacked folder in `wp-content/plugins` directory as `kuroneko-pay`
- Go to admin screen "Plugins" and activate it.

### Setting

After activation, go to WooCommerce settings screen "WooCommerce > Setting".
Click "Checkout" tab there, and you will find 3 payment methods added.

- Credit card(Japan)
- Credit card(International)
- Convenience Store

For each of them, you have to enter `Shop Code` which you will be given from [Kuroneko Web Collect](https://www.yamatofinancial.jp/wc/).

If you need optional service like recurring payment or repeater card feature,
please enter **option key**.

For convenience store gateway, you have to specify available store brands.

After setup, customers will see the payment gateway on checkout page.

## Frequently Asked Questions

Feel free to contact us at support forum.

## Changelog

### 1.2.0

* Update minimum requirement for WooCommerce to 3.0.

### 1.0.0

- Initial release.
