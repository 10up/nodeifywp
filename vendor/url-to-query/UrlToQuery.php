<?php namespace GM;

class UrlToQuery {

    /**
     * Rewrite rules array
     * @var array
     */
    private $rewrite;

    /**
     * $wp_rewrite->use_verbose_page_rules
     * @var bool
     */
    private $use_verbose;

    /**
     * Global WP object
     * @var \WP
     */
    private $wp;

    /**
     * Extra variables to be added to any query resolved
     * @var array
     */
    private $extra_query_vars = [ ];

    /**
     * Array of the instantiated \GM\UrlToQueryItem objects
     * @var array
     */
    private $items = [ ];

    /**
     * Constructor
     * @param array $extra_query_vars Extra variables to be added to any query resolved
     */
    function __construct( $extra_query_vars = [ ] ) {
        if ( is_array( $extra_query_vars ) ) {
            $this->extra_query_vars = $extra_query_vars;
        } elseif ( is_string( $extra_query_vars ) && ! empty( $extra_query_vars ) ) {
            parse_str( $extra_query_vars, $this->extra_query_vars );
        }
        $this->rewrite = $GLOBALS['wp_rewrite']->wp_rewrite_rules();
        $this->wp = $GLOBALS['wp'];
        $this->use_verbose = (bool) $GLOBALS['wp_rewrite']->use_verbose_page_rules;
    }

    /**
     * Resolve an url to an array of WP_Query arguments for main query
     *
     * @param string $url               Url to resolve
     * @param type $query_string_vars   Query variables to be added to the url
     * @return array|\WP_Error          Resolved query or WP_Error is something goes wrong
     */
    function resolve( $url = '', $query_string_vars = [ ] ) {
        $url = filter_var( $url, FILTER_SANITIZE_URL );
        if ( ! empty( $url ) ) {
            $id = md5( $url . serialize( $query_string_vars ) );
            if ( ! isset( $this->items[$id] ) || ! $this->items[$id] instanceof UrlToQueryItem ) {
                $this->items[$id] = new UrlToQueryItem( $this );
            }
            $result = $this->items[$id]->resolve( $url, $query_string_vars );
        } else {
            $result = new \WP_Error( 'url-to-query-bad-url' );
        }
        return $result;
    }

    /**
     * Get the rewrite rules array
     *
     * @return array
     */
    function getRewrite() {
        return $this->rewrite;
    }

    /**
     * Get the global WP object
     *
     * @return \WP
     */
    function getWp() {
        return $this->wp;
    }

    /**
     * Get the array of extra variables to be added to resolved queries
     *
     * @return array
     */
    function getExtraQueryVars() {
        return $this->extra_query_vars;
    }

    /**
     * Is true when $wp_rewrite->use_verbose_page_rules is true
     *
     * @return bool
     */
    function isVerbose() {
        return (bool) $this->use_verbose;
    }

}