<?php
/*
Plugin Name: Big, Middle, and Little Endians
Plugin URI: http://wordpress.org/extend/plugins/big-middle-little-endian/
Author: Scott Taylor
Author URI: http://scotty-t.com
Version: 0.1
Description: Fixes rewrites for non-Big Endian date permastructs
*/

/**
 * - - - - - - 
 * CONFIRMED 
 * - - - - - - 
 *  !!! = Doesn't work without this plugin
 * ----------------
 * Big Endian in both directions
 * 
 * 2012/09/12 yyyy/mm/dd
 * 2012/09 yyyy/mm
 * 2012 yyyy
 * 12 dd !!!
 * 09/12 mm/dd !!!
 * ----------------
 * Reverse of Middle Endian in both directions
 * 
 * 2012/12/09 yyyy/dd/mm
 * 2012/09 yyyy/mm !!! ~ matches day
 * 2012 yyyyy
 * 09 mm !!! 
 * 12/09 dd/mm !!!
 * ----------------
 * Middle Endian in both directions
 * 
 * 09/12/2012 mm/dd/yyyy
 * 09/12 mm/dd
 * 09 mm
 * 2012 yyyy !!!
 * 09/2012 mm/yyyy !!!
 * ----------------
 * Little Endian in both directions
 * 
 * 12/09/2012 dd/mm/yyyy
 * 12/09 dd/yy
 * 12 dd
 * 2012 yyyy !!!
 * 09/2012 mm/yyyy !!!
 * ----------------
 * 
 * Translated:
 * 
 * Year and Month archives don't work when you date format is Middle or Little Endian.
 * Year archives work in Middle when reversed (reversed, why not), but months do not.
 * 
 * This plugin makes all date formats work regardless of endianness.
 * 
 */

register_activation_hook( __FILE__, 'flush_rewrite_rules' );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

class BigMiddleLittleEndian {
	/**
	 * Stores reference to Big Endian permastruct (%year%/%monthnum%/%day%)
	 * 
	 * @var string 
	 */
	var $big_endian;
	/**
	 * Actual matching date permastruct
	 * 
	 * @var string
	 */
	var $rewrite_endian;
	/**
	 * All available date endians
	 * 
	 * @var array
	 */
	var $endians;
	/**
	 * Re-generated date rewrite rules
	 * 
	 * @var array
	 */
	var $rules;
	/**
	 * Plugin instance
	 * 
	 * @var BigMiddleLittleEndian
	 */
	static $instance;
	/**
	 * Singleton accessor
	 * 
	 * @return BigMiddleLittleEndian
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) )
			self::$instance = new BigMiddleLittleEndian;
		
		return self::$instance;
	}
	
	/**
	 * Filters and properties
	 * 
	 */
	private function __construct() {
		$this->endians = array(
			'big'		=> '%year%/%monthnum%/%day%',
			'middle'	=> '%monthnum%/%day%/%year%',
			'revmiddle'	=> '%year%/%day%/%monthnum%',
			'little'	=> '%day%/%monthnum%/%year%'
		);
		$this->big_endian = $this->endians['big'];
		
		add_action( 'init', array( $this, 'tags' ) );
		add_filter( 'date_rewrite_rules', array( $this, 'rewrite_rules' ) );
		add_filter( 'rewrite_rules_array', array( $this, 'remove_overrides' ) );
	}
	
	/**
	 * Eventually, support 1-365 Julian date format, and week number (1-52) as a date format
	 * 
	 */
	function tags() {
		// TODO: this doesn't work yet
		add_rewrite_tag( '%week%', '([0-9]{1,2})', 'week' );
		add_rewrite_tag( '%julian%', '([0-9]{1,3})', 'julian' );		
	}
	/**
	 * Determine endianness of date permastruct
	 * 
	 * @global WP_Rewrite $wp_rewrite
	 * @return string
	 */
	function get_date_endian() {
		global $wp_rewrite;
		
		if ( isset( $this->rewrite_endian ) )
			return $this->rewrite_endian;
		
		foreach ( $this->endians as $struct ) {
			if ( false !== strpos( $wp_rewrite->permalink_structure, $struct ) ) {
				$this->rewrite_endian = $struct;
				return $this->rewrite_endian;
			}
		}
		
		return $this->big_endian;
	}	
	/**
	 * return date permastruct from Rewrite's permastruct
	 * 
	 * @global WP_Rewrite $wp_rewrite
	 * @return string
	 */
	function get_date_permastruct() {
		global $wp_rewrite;

		if ( empty( $wp_rewrite->permalink_structure ) ) {
			$wp_rewrite->date_structure = '';
			return false;
		}

		// Do not allow the date tags and %post_id% to overlap in the permalink
		// structure. If they do, move the date tags to $front/date/.
		$front = $wp_rewrite->front;
		preg_match_all( '/%.+?%/', $wp_rewrite->permalink_structure, $tokens );
		$tok_index = 1;
		foreach ( (array) $tokens[0] as $token ) {
			if ( '%post_id%' == $token && ( $tok_index <= 3 ) ) {
				$front = $front . 'date/';
				break;
			}
			$tok_index++;
		}

		$wp_rewrite->date_structure = $front . $this->get_date_endian();

		return $wp_rewrite->date_structure;
	}		
	/**
	 * Post rewrites will wipe out date rewrites (they are generated before, but loaded after)
	 * Override them with our fixed rewrites
	 * 
	 * @param array $rules
	 * @return array
	 */
	function remove_overrides( $rules ) {
		foreach ( $this->rules as $match => $query )
			$rules[$match] = $query;
		
		return $rules;
	}
	
	/**
	 * We're gonna fix core... move along, nothing to see here...
	 * 
	 * Strip all of the unnecessary code out of WP_Rewrite::generate_rewrite_rules()
	 * and focus it on our custom date endian permastructs
	 * 
	 * @global WP_Rewrite $wp_rewrite
	 * @return array
	 */
	function rewrite_rules() {
		global $wp_rewrite;
		
		$permalink_structure = $this->get_date_permastruct();
		
		//build a regex to match the feed section of URLs, something like (feed|atom|rss|rss2)/?
		$feedregex2 = '';
		foreach ( (array) $wp_rewrite->feeds as $feed_name )
			$feedregex2 .= $feed_name . '|';
		$feedregex2 = '(' . trim( $feedregex2, '|' ) . ')/?$';

		//$feedregex is identical but with /feed/ added on as well, so URLs like <permalink>/feed/atom
		//and <permalink>/atom are both possible
		$feedregex = $wp_rewrite->feed_base . '/' . $feedregex2;
		//build a regex to match the trackback and page/xx parts of URLs
		$pageregex = $wp_rewrite->pagination_base . '/?([0-9]{1,})/?$';

		//build up an array of endpoint regexes to append => queries to append
		$ep_query_append = array();
		foreach ( (array) $wp_rewrite->endpoints as $endpoint ) {
			//match everything after the endpoint name, but allow for nothing to appear there
			$epmatch = $endpoint[1] . '(/(.*))?/?$';
			//this will be appended on to the rest of the query for each dir
			$epquery = '&' . $endpoint[1] . '=';
			$ep_query_append[$epmatch] = array( $endpoint[0], $epquery );
		}

		//get everything up to the first rewrite tag
		$front = substr( $permalink_structure, 0, strpos( $permalink_structure, '%' ) );
		//build an array of the tags (note that said array ends up being in $tokens[0])
		preg_match_all( '/%.?%/', $permalink_structure, $tokens );

		$index = $wp_rewrite->index; //probably 'index.php'
		$feedindex = $index;
				
		foreach ( $this->endians as $date_struct ) {
			if ( $this->get_date_endian() === $date_struct ) {
				$sets = array();
				$tags = explode( '/', $this->get_date_endian() );
				// match the first tag
				$sets[] = array( reset( $tags ) ); // y or m or d
				
				// match practical combinations
				if ( '%year%' === reset( $tags ) ) {
					if ( $this->rewrite_endian === $this->big_endian )
						$sets[] = array( '%day%' );
					else
						$sets[] = array( '%monthnum%' );
					$sets[] = array_diff( $tags, array( '%day%' ) ); // y and m 
					$sets[] = array_diff( $tags, array( '%year%' ) ); // m and d		
				} elseif ( '%monthnum%' === reset( $tags ) ) {
					$sets[] = array( '%year%' );
					$sets[] = array_diff( $tags, array( '%year%' ) ); // m and d
					$sets[] = array_diff( $tags, array( '%day%' ) ); // m and y
				} elseif ( '%day%' === reset( $tags ) ) {
					$sets[] = array( '%year%' );
					$sets[] = array_diff( $tags, array( '%year%' ) ); // d and m	
					$sets[] = array_diff( $tags, array( '%day%' ) ); // d and y
				}
				
				// match all 3 tags
				$sets[] = $tags;
				break;
			}
		}
		
		$sets = array_map( 'array_values', $sets );
		
		$dirs = array();
		foreach ( $sets as $tokens ) {
			if ( '%year%' === reset( $tokens ) && in_array( '%day%', $tokens ) && ! in_array( '%monthnum%', $tokens ) ) {
				// if year is the first token (Big Endian, Reverse Middle Endian), we need to ensure we have a
				// month archive, not a day archive, the RegEx won't work for manth AND day
				$tokens = array( '%year%', '%monthnum%' );
			}
			
			$dirs[] = join( '/', $tokens );
			
			$parts = array();
			foreach ( $tokens as $i => $token ) {
				$parts[] = str_replace( $wp_rewrite->rewritecode, $wp_rewrite->queryreplace, $token ) . $wp_rewrite->preg_index( $i + 1 );
			}

			$queries[] = join( '&', $parts );
		}	
		
		//get the structure, minus any cruft (stuff that isn't tags) at the front
		$structure = $permalink_structure;
		if ( $front != '/' )
			$structure = str_replace( $front, '', $structure );

		//create a list of dirs to walk over, making rewrite rules for each level
		//so for example, a $structure of /%year%/%monthnum%/%postname% would create
		//rewrite rules for /%year%/, /%year%/%monthnum%/ and /%year%/%monthnum%/%postname%
		$structure = trim( $structure, '/' );
		$num_dirs = count( $dirs );		
		
		//strip slashes from the front of $front
		$front = preg_replace( '|^/|', '', $front );

		//the main workhorse loop
		$post_rewrite = array();
		$struct = $front;
		for ( $j = 0; $j < $num_dirs; ++$j ) {
			//get the struct for this dir, and trim slashes off the front
			$struct = $front . $dirs[$j] . '/';
			$struct = ltrim( $struct, '/' );

			//replace tags with regexes
			$match = str_replace( $wp_rewrite->rewritecode, $wp_rewrite->rewritereplace, $struct );

			//make a list of tags, and store how many there are in $num_toks
			$num_toks = preg_match_all( '#%[^/]+%#', $struct, $toks );
			
			//get the 'tagname=$matches[i]'
			$query = $queries[$j];
			
			//set up $ep_mask_specific which is used to match more specific URL types
			switch ( $dirs[$j] ) {
				case '%year%':
					$ep_mask_specific = EP_YEAR;
					break;
				case '%monthnum%':
					$ep_mask_specific = EP_MONTH;
					break;
				case '%day%':
					$ep_mask_specific = EP_DAY;
					break;
				default:
					$ep_mask_specific = EP_NONE;
			}

			//create query for /page/xx
			$pagematch = $match . $pageregex;
			$pagequery = $index . '?' . $query . '&paged=' . $wp_rewrite->preg_index( $num_toks + 1 );

			//create query for /feed/(feed|atom|rss|rss2|rdf)
			$feedmatch = $match . $feedregex;
			$feedquery = $feedindex . '?' . $query . '&feed=' . $wp_rewrite->preg_index( $num_toks + 1 );

			//create query for /(feed|atom|rss|rss2|rdf) (see comment near creation of $feedregex)
			$feedmatch2 = $match . $feedregex2;

			//start creating the array of rewrites for this dir
			$rewrite = array( 
				$feedmatch => $feedquery, 
				$feedmatch2 => $feedquery, 
				$pagematch => $pagequery 
			);

			//do endpoints
			foreach ( (array) $ep_query_append as $regex => $ep ) {
				//add the endpoints on if the mask fits
				if ( $ep[0] & EP_DATE || $ep[0] & $ep_mask_specific )
					$rewrite[$match . $regex] = $index . '?' . $query . $ep[1] . $wp_rewrite->preg_index( $num_toks + 2 );
			}

			//if we've got some tags in this dir
			if ( $num_toks ) {
				$post = false;
				$page = false;

				if ( ! $post ) {
					// For custom post types, we need to add on endpoints as well.
					foreach ( get_post_types( array( '_builtin' => false ) ) as $ptype ) {
						if ( strpos( $struct, "%$ptype%") !== false ) {
							$post = true;
							$page = is_post_type_hierarchical( $ptype ); // This is for page style attachment url's
							break;
						}
					}
				}

				//not matching a permalink so this is a lot simpler
				//close the match and finalise the query
				$match .= '?$';
				$query = $index . '?' . $query;		

				//create the final array for this dir by joining the $rewrite array (which currently
				//only contains rules/queries for trackback, pages etc) to the main regex/query for
				//this dir
				$rewrite = array_merge( $rewrite, array( $match => $query ) );
			} //if( $num_toks)
			//add the rules for this dir to the accumulating $post_rewrite
			$post_rewrite = array_merge( $rewrite, $post_rewrite );
		} //foreach ( $dir)
		
		$this->rules = $post_rewrite;
		return $post_rewrite; //the finished rules. phew!
	}	
}
BigMiddleLittleEndian::get_instance();