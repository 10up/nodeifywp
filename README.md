# ReactifyWP

ReactifyWP let's you create isomorphic JavaScript applications with PHP. With ReactifyWP, you can manage your content using WordPress and output the content directly on the front-end isomorphically without anything like Express. Pretty crazy, huh?

The magic is made possible by the [PHP v8js PECL package](https://pecl.php.net/package/v8js). The easiest way to understand how this works is by looking at our [Twenty Sixteen React](https://github.com/10up/twentysixteenreact) theme.

## Requirements

* [PHP v8js](https://pecl.php.net/package/v8js). If you want to use v8js with PHP7, you will have to do some tinkering. Our [Twenty Sixteen React](https://github.com/10up/twentysixteenreact) theme has a development environment built in with Dockerfiles for creating everything.
* PHP 5.6
* WordPress 4.7+

## Usage

Install is easy via composer: `composer require 10up/reactifywp --save`. The package comes with an easy autoloader. Once you've loaded the autoloader, add the following to `functions.php` in your theme:

```php
\ReactifyWP\App::setup( $server_js_path, $client_js_url );
```

`$server_js_path` should be an absolute path to your server JS entrypoint. `$client_js_url` should be a url to your client.js entrypoint.

Once setup, ReactifyWP will automatically take over your theme by executing server JavaScript and exiting. Nothing in index.php, header.php, archive.php, etc. will even be parsed in PHP.

ReactifyWP transfers WordPress settings, sidebars, posts, etc. to JavaScript using a globalized variable, `PHP.context` using v8js. This context object let's you render your theme isomorphically. The context object is built as follows:

`PHP.context.$route` - Contains information about the current page being shown.

Example:
```javascript
PHP.context.$route = {
  type: 'home', // Type of route being shown i.e. home or single
  object_type: null, // Type of object being viewed i.e. category if viewing a category archive
  object_id: null // ID of object if viewing a single
};
```

`PHP.context.$nav_menus` - An object with menu names as keys containing each registered theme menu.

Example:
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

Example:
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

Example:
```javascript
PHP.context.$sidebars = {
  'sidebar-1': 'Raw sidebar HTML'
};
```

`PHP.context.$template_tags` - Contains registered template tags. See API section below for registering template tags

Example:
```javascript
PHP.context.$template_tags = {
  wp_head: 'Raw wp_head HTML'
};
```

`PHP.context.$user` - Contains current logged in user information.

Example:
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

ReactifyWP has a few useful API methods available:

* `\ReactifyWP\App::instance()->register_template_tag( $tag_name, $tag_function, $constant = true, $on_action = 'reactifywp_render' );`

  Registered template tags "localize" content for use within JavaScript. By default, ReactifyWP includes a number of common template tags such as `wp_head` (see `standard-tags.php`). Template tags are made available in PHP as 
  
  * (string) `$tag_name`: Name of tag. Will be available as `PHP.context.$template_tags.$tag_name` in JS.
  * (callable) `$tag_function`: This function will be executed to determine the contents of our tag
  * (boolean) `$constant`: Constant tags will not be re-calculated on client side navigation (in `get_route` API calls).
  * (string) `$on_action`: You can choose where the template tag should be rendered

* `\ReactifyWP\App::instance()->register_post_tag( $tag_name, $tag_function );`

  Registered post tags "localize" content for use within JavaScript on individual post objects.
  
  * (string) `$tag_name`: Name of tag. Will be available as `PHP.context.$posts[...][{$tag_name}]`` in JS.
  * (callable) `$tag_function`: This function will be executed to determine the contents of our tag. A `WP_Post` object will be passed to the function and setup as the global post.

  For example, to register post meta for use within each post in JavaScript:

  ```php
  \ReactifyWP\App::instance()->register_post_tag( 'my_meta', function( $post ) {
    $meta = get_post_meta( $post->ID, 'my_meta', true );
    echo $meta;
  } );
  ```

  The post tag would then be available in JavaScript as `PHP.context.$posts[...].my_meta`.

## v8js "Gotchas"

* `console` does not exist. Use `print()` and `require('util').inspect` instead.
* `setTimeout` does not exist.


