<?php
/**
 * Plugin Name: Url To Query
 * Plugin URI: https://github.com/Giuseppe-Mazzapica/Url_To_Query
 * Description: Convert any kind of WP url (even custom rewrite rules) to related main query arguments.
 * Author: Giuseppe Mazzapica
 * Author URI: http://gm.zoomlab.it
 * Requires at least: 3.9
 * Tested up to: 3.9.1
 */
if ( file_exists( plugin_dir_path( __FILE__ ) . 'vendor/autoload.php' ) ) {
    require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
}

if ( ! function_exists( 'url_to_query' ) ) {

    /**
     * The only template tag for the plugin, allow to easily resolve an url to an array of WP_Query
     * arguments for main query, without having to instanciate plugin classes.
     *
     * @param string $url       Url to resolve
     * @param type $query_vars  Query variables to be added to the url
     * @return array|\WP_Error  Resolved query or WP_Error is something goes wrong
     * @staticvar \GM\UrlToQuery $resolver
     */
    function url_to_query( $url = '', Array $query_vars = [ ] ) {
        static $resolver = NULL;
        if ( is_null( $resolver ) ) {
            $resolver = new GM\UrlToQuery( );
        }
        return $resolver->resolve( $url, $query_vars );
    }

}