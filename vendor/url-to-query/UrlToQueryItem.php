<?php namespace GM;

class UrlToQueryItem {

    /**
     * UrlToQuery instance.
     * @var \GM\UrlToQuery
     */
    private $resolver;

    /**
     * Relative path of the url to resolve.
     * @var string
     */
    private $request;

    /**
     * Resolved query arguments.
     * @var array
     */
    private $query_vars = [ ];

    /**
     * Query string variables in the url to be resolved.
     * @var array
     */
    private $query_string = [ ];

    /**
     * Query variabes set in the rewrite rule that match against the url to be resolved.
     * @var array
     */
    private $perma_q_vars = [ ];

    /**
     * Matched rule.
     * @var string|void
     */
    private $matched_rule = NULL;

    /**
     * Matched Query
     * @var string
     */
    private $matched_query = '';

    /**
     * Query variables (slug) for all the publicly queriable registered CPTs.
     * @var array
     */
    private $post_type_query_vars = [ ];

    /**
     * An id for the error
     * @var string|void
     */
    private $error = NULL;

    /**
     * Is set to true after the url has been resolved.
     * @var bool
     */
    private $done = FALSE;

    /**
     * Constructor
     * @param \GM\UrlToQuery $resolver
     */
    function __construct( UrlToQuery $resolver ) {
        $this->resolver = $resolver;
    }

    /**
     * Resolve an url to an array of WP_Query arguments for main query.
     *
     * @param string $url               Url to resolve
     * @param type $query_string_vars   Query variables to be added to the url
     * @return array|\WP_Error          Resolved query or WP_Error is something goes wrong
     */
    function resolve( $url = '', Array $query_string_vars = [ ] ) {
        if ( $this->done && empty( $this->error ) ) {
            return $this->query_vars;
        }
        $this->parseUrl( $url, $query_string_vars );
        $rewrite = (array) $this->resolver->getRewrite();
        if ( ! empty( $rewrite ) ) {
            list( $matches, $query ) = $this->parseRewriteRules( $rewrite );
            $this->setMatchedQuery( $matches, $query );
            $this->maybeAdmin();
        }
        return $this->resolveVars();
    }

    /**
     * Get the query vars after having resolved the url.
     *
     * @return array|\WP_Error Resolved query or WP_Error is something goes wrong
     */
    function getQueryVars() {
        if ( ! $this->done ) {
            $this->error = 'not-resolved';
            return $this->getError();
        }
        return $this->query_vars;
    }

    /**
     * Get the matched rule after having resolved the url.
     *
     * @return string|\WP_Error Matched rule or WP_Error is something goes wrong
     */
    function getMatchedRule() {
        if ( ! $this->done ) {
            $this->error = 'not-resolved';
            return $this->getError();
        }
        return $this->matched_rule;
    }

    /**
     * Get the matched rewrite rule query after having resolved the url.
     *
     * @return array|\WP_Error Matched rewrite rule query or WP_Error is something goes wrong
     */
    function getMatchedQuery() {
        if ( ! $this->done ) {
            $this->error = 'not-resolved';
            return $this->getError();
        }
        return $this->matched_query;
    }

    /**
     * Get the matched rewrite rule query vars after having resolved the url.
     *
     * @return array|\WP_Error Matched rewrite rule query vars or WP_Error is something goes wrong
     */
    function getPermalinkVars() {
        if ( ! $this->done ) {
            $this->error = 'not-resolved';
            return $this->getError();
        }
        return $this->perma_q_vars;
    }

    /**
     * Return a WP_Error object is something gone wrong, otherwise FALSE.
     *
     * @return bool|\WP_Error
     */
    function getError() {
        return ! empty( $this->error ) ? new \WP_Error( 'url-to-query-' . $this->error ) : FALSE;
    }

    /**
     * Parse the url to be resolved taking only relative part and stripping out query vars.
     *
     * @param type string
     * @param array $query_string_vars
     */
    private function parseUrl( $url = '', Array $query_string_vars = [ ] ) {
        parse_str( parse_url( $url, PHP_URL_QUERY ), $this->query_string );
        $request_uri = trim( parse_url( $url, PHP_URL_PATH ), '/' );
        $this->request = trim( preg_replace( '#^/*index\.php#', '', $request_uri ), '/' );
        if ( ! empty( $query_string_vars ) ) {
            $this->query_string = array_merge( $this->query_string, $query_string_vars );
        }
    }

    /**
     * Loop throught registered rewrite rule and check them against the url to resolve.
     *
     * @param array $rewrite
     * @return array
     * @uses \GM\UrlToQueryItem::parseRewriteRule()
     */
    private function parseRewriteRules( Array $rewrite ) {
        $this->error = '404';
        $request_match = $this->request;
        if ( empty( $request_match ) && isset( $rewrite['$'] ) ) {
            $this->matched_rule = '$';
            $matches = [ '' ];
            $query = $rewrite['$'];
        } else {
            foreach ( (array) $rewrite as $match => $query ) {
                $matches = $this->parseRewriteRule( $match, $query );
                if ( ! is_null( $this->matched_rule ) ) {
                    return [ $matches, $query ];
                }
            }
        }
        return [ $matches, $query ];
    }

    /**
     * Take the two part of a rewriote rule (the url and the query) and compare against the url to
     * be resolved to find a match.
     *
     * @param string $match The url part of the rewrite rule
     * @param string $query The query part of the rewrite rule
     * @return array
     */
    private function parseRewriteRule( $match, $query ) {
        $matches = [ ];
        $request_match = $this->request;
        if ( ! empty( $this->request ) && strpos( $match, $this->request ) === 0 ) {
            $request_match = $this->request . '/' . $this->request;
        }
        if (
            preg_match( "#^{$match}#", $request_match, $matches )
            || preg_match( "#^{$match}#", urldecode( $request_match ), $matches )
        ) {
            $varmatch = NULL;
            if ( $this->resolver->isVerbose() && preg_match( '/pagename=\$matches\[([0-9]+)\]/', $query, $varmatch ) ) {
                $page = get_page_by_path( $matches[ $varmatch[1] ] );
                if ( ! $page ) {
                    return;
                }
            }
            $this->matched_rule = $match;
        }
        return $matches;
    }

    /**
     * When a rute matches, save matched query string and matched query array.
     *
     * @param array $matches    Matches coming from regex compare matched rule to url
     * @param string $query     Query part of the matched rule
     */
    private function setMatchedQuery( $matches = [ ], $query = '' ) {
        if ( ! is_null( $this->matched_rule ) ) {
            $mathed = \WP_MatchesMapRegex::apply( preg_replace( "!^.+\?!", '', $query ), $matches );
            $this->matched_query = addslashes( $mathed );
            parse_str( $this->matched_query, $this->perma_q_vars );
            if ( '404' === $this->error ) $this->error = NULL;
        }
    }

    /**
     * Check if the url is for admin, in that case unset all the frontend query variables
     */
    private function maybeAdmin() {
        if ( empty( $this->request ) || strpos( $this->request, 'wp-admin/' ) !== FALSE ) {
            $this->error = NULL;
            if (
                ! is_null( $this->perma_q_vars )
                && strpos( $this->request, 'wp-admin/' ) !== FALSE
            ) {
                $this->perma_q_vars = NULL;
            }
        }
    }

    /**
     * Setup the query variables if a rewrite rule matched or if some variables are passed as query
     * string. Strips out not registered query variables and perform the 'request' filter
     * before saving and return found query vars.
     *
     * @return array
     * @uses \GM\UrlToQueryItem::parseQueryVars()
     * @uses \GM\UrlToQueryItem::parseTaxQueryVars()
     * @uses \GM\UrlToQueryItem::parseCptQueryVars()
     * @uses \GM\UrlToQueryItem::parsePrivateQueryVars()
     */
    private function resolveVars() {
        $this->setCptQueryVars();
        $wp = $this->resolver->getWp();
        $public_query_vars = (array) apply_filters( 'query_vars', $wp->public_query_vars );
        $extra_query_vars = (array) $this->resolver->getExtraQueryVars();
        $this->parseQueryVars( $public_query_vars, $extra_query_vars );
        $this->parseTaxQueryVars();
        $this->parseCptQueryVars();
        $this->parsePrivateQueryVars( $extra_query_vars, $wp->private_query_vars );
        if ( ! is_null( $this->error ) ) {
            return $this->getError();
        }
        $this->query_vars = apply_filters( 'request', $this->query_vars );
        $this->done = TRUE;
        return $this->query_vars;
    }

    /**
     * Store all the query rewrite slugs for all registered post types
     */
    private function setCptQueryVars() {
        foreach ( get_post_types( [ ], 'objects' ) as $post_type => $t ) {
            if ( $t->query_var ) $this->post_type_query_vars[$t->query_var] = $post_type;
        }
    }

    /**
     * Store query variables to be returned, merging ones coming from matched rule (if any) and ones
     * coming from query string or configs
     *
     * @param array $public_vars
     * @param array $extra
     * @uses \GM\UrlToQueryItem::parseQueryVar()
     */
    private function parseQueryVars( Array $public_vars = [ ], Array $extra = [ ] ) {
        foreach ( $public_vars as $wpvar ) {
            if ( isset( $extra[$wpvar] ) ) {
                $this->query_vars[$wpvar] = $extra[$wpvar];
            } elseif ( isset( $this->query_string[$wpvar] ) ) {
                $this->query_vars[$wpvar] = $this->query_string[$wpvar];
            } elseif ( isset( $this->perma_q_vars[$wpvar] ) ) {
                $this->query_vars[$wpvar] = $this->perma_q_vars[$wpvar];
            }
            if ( ! empty( $this->query_vars[$wpvar] ) ) {
                $this->parseQueryVar( $wpvar );
            }
        }
    }

    /**
     * Parse a query variable, "flattening" it if is an array or an object, also set 'post_type'
     * and 'name' query var, if a slug of a registered post type is present among query vars
     *
     * @param string  $wpvar
     */
    private function parseQueryVar( $wpvar = '' ) {
        if ( ! is_array( $this->query_vars[$wpvar] ) ) {
            $this->query_vars[$wpvar] = (string) $this->query_vars[$wpvar];
        } else {
            foreach ( $this->query_vars[$wpvar] as $vkey => $v ) {
                if ( ! is_object( $v ) ) {
                    $this->query_vars[$wpvar][$vkey] = (string) $v;
                }
            }
        }
        if ( isset( $this->post_type_query_vars[$wpvar] ) ) {
            $this->query_vars['post_type'] = $this->post_type_query_vars[$wpvar];
            $this->query_vars['name'] = $this->query_vars[$wpvar];
        }
    }

    /**
     * Convert spacet to '+' in the query variables for custom taxonomies
     */
    private function parseTaxQueryVars() {
        foreach ( get_taxonomies( [ ], 'objects' ) as $t ) {
            if ( $t->query_var && isset( $this->query_vars[$t->query_var] ) ) {
                $encoded = str_replace( ' ', '+', $this->query_vars[$t->query_var] );
                $this->query_vars[$t->query_var] = $encoded;
            }
        }
    }

    /**
     * Remove from query variables any non publicly queriable post type rewrite slug
     */
    private function parseCptQueryVars() {
        if ( isset( $this->query_vars['post_type'] ) ) {
            $queryable = get_post_types( [ 'publicly_queryable' => TRUE ] );
            if (
                ! is_array( $this->query_vars['post_type'] )
                && ! in_array( $this->query_vars['post_type'], $queryable, TRUE )
            ) {
                unset( $this->query_vars['post_type'] );
            } elseif ( is_array( $this->query_vars['post_type'] ) ) {
                $allowed = array_intersect( $this->query_vars['post_type'], $queryable );
                $this->query_vars['post_type'] = $allowed;
            }
        }
    }

    /**
     * Look in extra query variables passed to resolver and compare to WP object private variables
     * if some variables are found they are added to query variables to be returned
     * 
     * @param array $extra
     * @param array $private
     */
    private function parsePrivateQueryVars( Array $extra = [ ], Array $private = [ ] ) {
        if ( ! empty( $extra ) ) {
            foreach ( $private as $var ) {
                if ( isset( $extra[$var] ) ) {
                    $this->query_vars[$var] = $extra[$var];
                }
            }
        }
    }

}
