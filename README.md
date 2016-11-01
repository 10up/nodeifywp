# ReactifyWP

ReactifyWP let's you create isomorphic JavaScript applications with PHP. With ReactifyWP, you can manage your content using WordPress and output the content directly on the front-end isomorphically without anything like Express. Pretty crazy, huh?

The magic is made possible by the [PHP v8js PECL package](https://pecl.php.net/package/v8js). The easiest way to understand how this works is by looking at our [Twenty Sixteen React](https://github.com/10up/twentysixteenreact) theme.

## Requirements

* [PHP v8js](https://pecl.php.net/package/v8js). If you want to use v8js with PHP7, you will have to do some tinkering. Our [Twenty Sixteen React](https://github.com/10up/twentysixteenreact) theme has a development environment built in with Dockerfiles for creating everything.
* PHP 5.6
* WordPress 4.7+

## Install

Install is easy via composer: `composer require 10up/reactifywp --save`. The package comes with an easy autoloader. Once you've loaded the autoloader, add the following to `functions.php` in your theme:

```php
\ReactifyWP\App::setup( __DIR__ . '/js/server.js', get_stylesheet_directory_uri() . '/js/client.js' );
```

...
