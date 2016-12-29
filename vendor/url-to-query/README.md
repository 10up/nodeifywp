Url To Query
============

A WordPress plugin that allow resolving any kind of WordPress url (even from custom rewrite rules) to related main query arguments.

#Requirements

- PHP 5.4+
- WordPress 3.9+
- [Composer](https://getcomposer.org/) to install

#Installation

The plugin is a Composer package and can be installed in plugin directory via:

``` bash
composer create-project gmazzap/url-to-query --no-dev
```

## What & Why

WP comes with a set of tools to convert custom urls into specific query variables:
is possible to change the [permalink structure](http://codex.wordpress.org/Using_Permalinks#Choosing_your_permalink_structure), and there is also an [API](http://codex.wordpress.org/Rewrite_API/add_rewrite_rule) to add completely custom rules, however, there is **not** a way to *reverse* the process: i.e. to know to which query arguments an arbitrary url is connected to.

The url *resolving* is done in core by [`parse_request`](https://github.com/WordPress/WordPress/blob/71eb75a1599be8b456b2040f7ac2235c0e6b217e/wp-includes/class-wp.php#L120) method of `WP` class saved in the global `$wp` variable.

Using that method for the purpose explained above is hard/discouraged because:
 * it directly accesses to `$_SERVER`, `$_POST` and `$_GET` variables, making hard to parse arbitrary urls not related with current HTTP request
 * it triggers some action hooks strictly related to current HTTP request parsing, that makes no sense to trigger for arbitrary urls
 * it accesses and modifies properties of global `$wp` variable that should not be changed after request is parsed or very likely *things* will break

This is the reason why I wrote this simple plugin, it adds a template tag **`url_to_query`** that accepts an url and returns related main query arguments.

##How to use##

``` php
$args = url_to_query( 'http://example.com/sample-page' );
// $args = array(  'pagename' => 'sample-page' );

$args = url_to_query( 'http://example.com/category/uncategorized/' )
// $args = array(  'category_name' => 'uncategorized' );
```

It is also possible to pass a relative url:

``` php
$args = url_to_query( '/sample-page' );
// $args = array(  'pagename' => 'sample-page' );
```

###Using query string###

When pretty permalinks are not used, (sometimes even in that case) WordPress can make use of query string in the
urls to set query arguments. The plugin works perfectly with them:

``` php
$args = url_to_query( '/?attachment_id=880' );
$args = array(  'attachment_id' => '880' );
```

To simplify this task, `url_to_query` accepts a second argument: an array of query vars to be considered
in the same way core considers `$_REQUEST` variables when an url is parsed:

``` php
$args = url_to_query( '/', array( 'attachment_id' => '880' ) );
// $args = array(  'attachment_id' => '880' );
```

Note that the array passed as second argument is not straight merged to the query vars, only valid query vars will be used, just like core does when parse urls:

``` php
$args = url_to_query( '/', array( 'attachment_id' => '880', 'foo' => 'bar' ) );
// $args = array(  'attachment_id' => '880' );
```

###Custom rewrite rules###

Plugin works with no problems with custom rewrite rules, just few things to consider:

* `url_to_query` have to be called *after* query rules are added, or it can't recognize them
* just like for core, rewrite rules have to be flushed before `url_to_query` can recognize a newly added rule
* just like core, if the rule contains custom query variables, they have to be *allowed*, maybe using [`add_rewrite_tag`](http://codex.wordpress.org/Rewrite_API/add_rewrite_tag)
or using `'query_vars'` filter (see [Codex example](http://codex.wordpress.org/Custom_Queries#Custom_Archives))

Example:

``` php
add_action( 'init', 'my_rew_rules' );

function my_rew_rules() {
  add_rewrite_tag('%food%', '([^&]+)');
  add_rewrite_tag('%variety%', '([^&]+)');
  add_rewrite_rule(
    '^nutrition/([^/]*)/([^/]*)/?',
    'index.php?page_id=12&food=$matches[1]&variety=$matches[2]',
    'top'
  );
}

add_action( 'footer' function() {
  $args = url_to_query( '/nutrition/cake/cherry/' )
  // $args = array( 'page_id' => '12', 'food' => 'cake', 'variety' => 'cherry' );
} );
```

###Plugin classes###

Even if plugin provides the `'url_to_query'` template tag, it internally uses two classes to resolve urls and
it is possible directly use them, instead of the template tag. Indeed, only one of them should be used, the second is used internally.

``` php
$resolver = new GM\UrlToQuery();
$args = $resolver->resolve( 'http://example.com/sample-page', array( 'page' => '2' ) );
// $args = array( 'pagename' => 'sample-page', 'page' => '2' );
```

So `resolve()` method of `GM\UrlToQuery` works exactly in the same way `url_to_query` function does.

The same instance of `GM\UrlToQuery` can be used to resolve different urls:

``` php
$resolver = new GM\UrlToQuery();

$args1 = $resolver->resolve( 'http://example.com/sample-page', array( 'page' => '2' ) );
// $args1 = array( 'pagename' => 'sample-page', 'page' => '2' );

$args2 = $resolver->resolve( '/?attachment_id=880' );
// $args2 = array( 'attachment_id' => '880' );

$args3 = $resolver->resolve( 'http://example.com/category/uncategorized/' );
// $args3 = array( 'category_name' => 'uncategorized' );
```


================

# License

Url_To_Query is released under MIT.







