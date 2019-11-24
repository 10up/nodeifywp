# NodeifyWP 

> NodeifyWP let's you create isomorphic JavaScript applications with WordPress and PHP. With NodeifyWP, you can manage your content using WordPress and output the content directly on the front-end isomorphically without anything like Express. NodeifyWP yields all the benefits of WordPress and powerful isomorphic Node.js technologies.

[![Support Level](https://img.shields.io/badge/support-stable-blue.svg)](#support-level) [![Build Status](https://travis-ci.org/10up/nodeifywp.svg?branch=master)](https://travis-ci.org/10up/nodeifywp) [![Release Version](https://img.shields.io/github/v/tag/10up/nodeifywp)](https://github.com/10up/nodeifywp/releases/latest) ![WordPress tested up to version](https://img.shields.io/badge/WordPress-v4.8%20tested-success.svg)

## Background

Isomorphic web applications (running the same code on the server and client) are all the rage because they provide the flexibility, extensibility, and consistency needed to build large and powerful "app-like" experiences on the web. JavaScript and Node.js are used to create isomorphic applications since JS runs natively in the web browser.

As 8 million different isomorphic web frameworks and strategies have popped up around JavaScript, we, in the WordPress community, have been stuck in PHP world where the same isomorphism isn't really possible. We believe WordPress is an incredibly relevant and useful fully-fledged CMS with the best overall editorial experience available for publishing content. Therefore, we don't want to abandon WordPress for the newest "hot" web technology.

To create JavaScript powered "app-like" experiences in WordPress, we currently have a few options:

1. Create a PHP theme with a client side layer that refreshes the DOM using something like Underscore templates and the [REST API](http://v2.wp-api.org/). This strategy allows us to achieve the desired effect but is a bit forced in that we have to create templates in PHP and separate ones for JavaScript. This structure is tough to maintain from a development perspective.

2. With the release of the REST API, we can discard WordPress's front-end completely. We can use Node.js and something like Express to serve our front-end communicating with WordPress using the REST API. This is great but presents some serious difficulties. For one, we have to make an external request to get simple things like theme options, menus, and sidebars. Customizer functionality is essentially useless. Previews and comments are very hard to implement. The admin bar is gone. Front-end authentication becomes an extremely hard problem to solve. Plugins can't interact with the front-end.

3. Some hybrid of the first two options.

The options currently available lead us to build [NodeifyWP](https://github.com/10up/nodeifywp/).

*NodeifyWP uses PHP to execute Node.js code on the server.* This is made possible by [V8Js](https://github.com/phpv8/v8js) which is a PHP extension for [Google's V8 engine](https://developers.google.com/v8/). NodeifyWP exposes WordPress hooks, nav menus, sidebars, posts, and more within server-side Javascript. A simple API for registering PHP "tags" within JavaScript is made available. It also includes a REST API for retrieving route information, updated tags, sidebars, menus, etc. as the state of your application changes. With NodeifyWP, we can serve a __true isomorphic application__ from within PHP. We get all the benefits of WordPress and all the benefits of powerful isomorphic Node.js technologies. No separate Node.js/Express server is necessary.

## Requirements

* [PHP V8Js](https://pecl.php.net/package/v8js). If you want to use V8Js with PHP7, you will have to do some tinkering. Our [Twenty Sixteen React](https://github.com/10up/twentysixteenreact) theme has a development environment built in with Dockerfiles for creating everything.
* [Google V8](https://developers.google.com/v8/)
* PHP 5.6+
* WordPress 4.7+

__We've created a [Dockerized NodeifyWP environment](https://github.com/10up/nodeifywp-environment) that sets up all this for you.__

## Install

1. Install and start up the [NodeifyWP environment](https://github.com/10up/nodeifywp-environment). Since V8Js and V8 can be difficult to setup, we've created this packaged environment. We highly recommend using it.
2. Install the plugin. You can install it from [WordPress.org](https://wordpress.org/plugins/nodeifywp) or as a [Composer dependency](https://packagist.org/packages/10up/nodeifywp).
3. Activate the plugin.
4. Remember, NodeifyWP is a framework. Build or [use a theme](https://github.com/10up/twentysixteenreact) that implements NodeifyWP.

## Themes

The following themes have been built with NodeifyWP:

[Twenty Sixteen React](https://github.com/10up/twentysixteenreact) is a NodeifyWP WordPress theme rebuilt using the following technologies:
* [Node.js](https://nodejs.org/)
* [React.js](https://facebook.github.io/react/)
* [Redux](http://redux.js.org/docs/introduction/)
* [NodeifyWP](https://github.com/10up/nodeifywp/)

## Usage

After making sure NodeifyWP is properly installed (either as a plugin or Composer dependency), add the following to `functions.php` in your theme:

```php
\NodeifyWP\App::setup( $server_js_path, $client_js_url = null, $includes_js_path, $includes_js_url = null );
```

`$server_js_path` should be an absolute path to your server JS entrypoint. `$client_js_url` should be a url to your client.js entrypoint.

You can supply optional third and fourth paramaters, `$includes_js_path` and `$includes_js_url`, to the `setup` method.  `$includes_js_path` should point to a server side JavaScript file that holds your application includes, `$includes_js_url` to the same includes located client side. Storing your includes here will let NodeifyWP cache your includes using [V8 heap snapshots](http://v8project.blogspot.com/2015/09/custom-startup-snapshots.html).

Once setup, NodeifyWP will automatically take over your theme by executing server JavaScript and exiting. Nothing in index.php, header.php, archive.php, etc. will even be parsed in PHP.

NodeifyWP transfers WordPress settings, sidebars, posts, etc. to JavaScript using a globalized variable, `PHP.context` using V8Js. This context object let's you render your theme isomorphically. The context object is built as follows:

`PHP.context.$route` - Contains information about the current page being shown.

__Example:__
```javascript
PHP.context.$route = {
  type: 'home', // Type of route being shown i.e. home or single
  object_type: null, // Type of object being viewed i.e. category if viewing a category archive
  object_id: null // ID of object if viewing a single
};
```

`PHP.context.$nav_menus` - An object with menu names as keys containing each registered theme menu.

__Example:__
```javascript
PHP.context.$nav_menus = {
  primary: [
      {
        title: 'Link title',
        url: 'http://site.com',
        children: [ ... ]
      }
  ]
};
```

`PHP.context.$posts` - An array of the posts for the current route. For a page, there would just be one post in the array.

__Example:__
```javascript
PHP.context.$posts = [
  {
    ID: 1,
    post_title: '',
    post_content: '',
    the_title: '', // Filtered title
    the_content: '', // Filtered content
    post_class: '', // Post classes for current post
    permalink: '',
    ...
  }
];
```

`PHP.context.$sidebars` - An object containing sidebar HTML where the key is the sidebar name.

__Example:__
```javascript
PHP.context.$sidebars = {
  'sidebar-1': 'Raw sidebar HTML'
};
```

`PHP.context.$template_tags` - Contains registered template tags. See API section below for registering template tags

__Example:__
```javascript
PHP.context.$template_tags = {
  wp_head: 'Raw wp_head HTML'
};
```

`PHP.context.$user` - Contains current logged in user information.

__Example:__
```javascript
PHP.context.$user = {
  user_login: '',
  user_nicename: '',
  ID: '',
  display_name: '',
  rest_nonce: ''
};
```

`PHP.client_js_url` - URL to client side JavaScript file.

In your server side JavaScript, you could print or inspect one of these objects like so:
```javascript
print(PHP.client_js_url);

print(require('util').inspect(PHP.context.$sidebars));
```

## API

NodeifyWP has a few useful API methods available:

```php
\NodeifyWP\App::instance()->register_template_tag( $tag_name, $tag_function, $constant = true, $on_action = 'nodeifywp_render' );
```

Registered template tags "localize" content for use within JavaScript. By default, NodeifyWP includes a number of common template tags such as `wp_head` (see `standard-tags.php`). Template tags are made available in PHP as 

* (string) `$tag_name`: Name of tag. Will be available as `PHP.context.$template_tags.$tag_name` in JS.
* (callable) `$tag_function`: This function will be executed to determine the contents of our tag
* (boolean) `$constant`: Constant tags will not be re-calculated on client side navigation (in `get_route` API calls).
* (string) `$on_action`: You can choose where the template tag should be rendered

```php
\NodeifyWP\App::instance()->register_post_tag( $tag_name, $tag_function );
```

Registered post tags "localize" content for use within JavaScript on individual post objects.

* (string) `$tag_name`: Name of tag. Will be available as `PHP.context.$posts[...][{$tag_name}]` in JS.
* (callable) `$tag_function`: This function will be executed to determine the contents of our tag. A `WP_Post` object will be passed to the function and setup as the global post.

For example, to register post meta for use within each post in JavaScript:

```php
\NodeifyWP\App::instance()->register_post_tag( 'my_meta', function( $post ) {
  $meta = get_post_meta( $post->ID, 'my_meta', true );
  echo $meta;
} );
```

The post tag would then be available in JavaScript as `PHP.context.$posts[...].my_meta`.

## V8Js "Gotchas"

* `console` does not exist. Use `print()` instead.
* `setTimeout` does not exist.

## Benchmarks

NodeifyWP (and it's [supported themes](https://github.com/10up/nodeifywp#themes)) is not a performance bottleneck and will scale with any WordPress website. We've compiled [benchmarks for using Twenty Sixteen React, a NodeifyWP theme, in comparison with the standard Twenty Sixteen theme](https://docs.google.com/spreadsheets/d/1iAa5cjmAIWhz_yYkiuW2Hyn-GvbxSsAcUq2sKnFp5IM/edit#gid=158310691). Takeaways from our benchmarks:
* With no caching set up, Twenty Sixteen React's average response time is about 200ms longer than Twenty Sixteen with the same configuration.
* Using NodeifyWP includes (heap snapshots) and object caching, Twenty Sixteen React's average response time is about 150ms longer than Twenty Sixteen with the same configuration.
* Using object caching with a page cache (Batcache), Twenty Sixteen React's average response time is 40ms slower than Twenty Sixteen with the same configuration.

Since NodeifyWP and Twenty Sixteen React rely on V8, it's inescapeable that there will be some overhead. However, by optimizing V8 and V8Js our benchmarks show we can reduce overhead enough that the effect on perceived page load time is nearly nothing. Furthermore, the user experience gains of running an SPA style website make NodeifyWP an even more appealing, production-ready framework.

## Contributing

We are excited to see how the community receives the project. We'd love to receive links to open-sourced themes using NodeifyWP.

## License

This is free software; you can redistribute it and/or modify it under the terms of the [GNU General Public License](http://www.gnu.org/licenses/gpl-2.0.html) as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

## Support Level

**Stable:** 10up is not planning to develop any new features for this, but will still respond to bug reports and security concerns. We welcome PRs, but any that include new features should be small and easy to integrate and should not include breaking changes. We otherwise intend to keep this tested up to the most recent version of WordPress.

## Like what you see?

<p align="center">
<a href="http://10up.com/contact/"><img src="https://10updotcom-wpengine.s3.amazonaws.com/uploads/2016/10/10up-Github-Banner.png" width="850"></a>
</p>
