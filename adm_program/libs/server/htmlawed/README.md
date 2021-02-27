htmLawed
========

[![Build Status](https://img.shields.io/travis/vanilla/htmlawed.svg?style=flat)](https://travis-ci.org/vanilla/htmlawed)
[![Coverage](https://img.shields.io/scrutinizer/coverage/g/vanilla/htmlawed.svg?style=flat)](https://scrutinizer-ci.com/g/vanilla/htmlawed/)
[![Packagist Version](https://img.shields.io/packagist/v/vanilla/htmlawed.svg?style=flat)](https://packagist.org/packages/vanilla/htmlawed)
![LGPL-3.0](https://img.shields.io/packagist/l/vanilla/htmlawed.svg?style=flat)

A composer wrapper for the [htmLawed](http://www.bioinformatics.org/phplabware/internal_utilities/htmLawed/) library to purify &amp; filter HTML.
Tested with [PHPUnit](http://phpunit.de/) and [PhantomJS](http://phantomjs.org/).

Why use htmLawed?
-----------------

If your website has any user-generated content then you need to worry about [cross-site scripting (XSS)](http://en.wikipedia.org/wiki/Cross-site_scripting).
htmLawed will take a piece of potentially malicious html and remove the malicious code, leaving the rest of html behind.

Beyond the base htmLawed library, this package makes htmLawed a composer package and wraps it in an object so that it can be autoloaded.

Installation
------------

*htmLawed requres PHP 5.4 or higher*

htmLawed is [PSR-4](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md) compliant and can be installed using [composer](//getcomposer.org). Just add `vanilla/htmlawed` to your composer.json.

```json
"require": {
    "vanilla/htmlawed": "~1.0"
}
```

Example
-------

```php
echo Htmlawed::filter('<h1>Hello world!');
// Outputs: '<h1>Hello world!</h1>'.

echo Htmlawed::filter('<i>nothing to see</i><script>alert("xss")</script>')
// Outputs: '<i>nothing to see</i>alert("xss")'
```

Configs and Specs
-----------------

The htmLawed filter takes two optional parameters: `$config` and `$spec`. This library provides sensible defaults to these parameters, but you can override them in `Htmlawed::filter()`.

```php
$xss = "<i>nothing to see <script>alert('xss')</script>";

// Pass an empty config and spec for no filtering of malicious code.
echo Htmlawed::filter($xss, [], []);
// Outputs: '<i>nothing to see <script type="text/javascript">alert("xss")</script></i>'

// Pass safe=1 to turn on all the safe options.
echo Htmlawed::filter($xss, ['safe' => 1]);
// Outputs: '<i>nothing to see alert("xss")</i>'

// We provide a convenience method that strips all tags that aren't supposed to be in rss feeds.
echo Htmlawed::filterRSS('<html><body><h1>Hello world!</h1></body></html>');
// Outputs: '<h1>Hello world!</h1>'
```

See the [htmLawed documentation](http://www.bioinformatics.org/phplabware/internal_utilities/htmLawed/htmLawed_README.htm#s2.2) for the full list of options.

Differences in Vanilla's version of Htmlawed
--------------------------------------------

We try and use the most recent version of htmLawed with as few changes as possible so that bug fixes and security
releases can be merged from the main project. However, We've made a few changes in the source code.

* Balance tags (hl_bal) before validating tags (hl_tag). We found some cases where an unbalanced script tag would not
  get removed and this addresses that issue.
* Don't add an extra `<div>` inside of `<blockquote>` tags.
* Remove naked `<span>`.
* Change indentation from 1 space to 4 spaces.

*If the original author of htmLawed wants to make any of these changes upstream please get in contact with support@vanillaforums.com.*