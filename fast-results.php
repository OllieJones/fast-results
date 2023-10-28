<?php
/**
 * Fast Results
 *
 * @package       fast-results
 * @author        Ollie Jones
 * @license       gplv2
 *
 * @wordpress-plugin
 * Plugin Name:   Fast Results
 * Plugin URI:    https://www.plumislandmedia.net/fast-results/
 * Description:   Speed up the generation of large site result pages.
 * Version:       0.1.1
 * Author:        Ollie Jones
 * Author URI:    https://github.com/OllieJones
 * Text Domain:   fast-results
 * Domain Path:   /languages
 * License:       GPLv2
 * License URI:   https://www.gnu.org/licenses/gpl-2.0.html
 *
 * You should have received a copy of the GNU General Public License
 * along with Post from Email. If not, see <https://www.gnu.org/licenses/gpl-2.0.html/>.
 */

namespace Fast_Results;

use WP_Query;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

class FastResults {
  private static $instance = null;

  private $initialized = false;
  private $stubs_to_intervene = array();

  public static function init() {
//    error_log('init');

    $instance = self::getInstance();
  }

  public static function getInstance() {
    if ( self::$instance == null ) {
      self::$instance = new FastResults();
    }

    return self::$instance;
  }

  private function __construct() {
    add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ), 10, 1 );
  }

  /**
   * Action. Fires after the query variable object is created, but before the actual query is run.
   *
   * Note: If using conditional tags, use the method versions within the passed instance
   * (e.g. $this->is_main_query() instead of is_main_query()). This is because the functions
   * like is_main_query() test against the global $wp_query instance, not the passed one.
   *
   * @param WP_Query $query The WP_Query instance (passed by reference).
   *
   * @since 2.0.0
   *
   */
  public function pre_get_posts( $query ) {
    if ( $this->initialized ) {
      return;
    }
    if ( ! isset( $query->query_vars['no_found_rows'] ) || ! $query->query_vars['no_found_rows'] ) {
      /* Avoid intercepting queries unless we're attempting pagination. */
      $this->initialized = true;
      add_filter( 'posts_request', array( $this, 'posts_request' ), 10, 2 );
      add_filter( 'found_posts_query', array( $this, 'found_posts_query' ), 10, 2 );
      add_filter( 'found_posts', array( $this, 'found_posts' ), 10, 2 );
    }
  }

  /**
   * Filters the completed SQL query before sending.
   *
   * We strip the SQL_CALC_FOUND_ROWS item if we have a cached found-rows value.
   *
   * @param string $request The complete SQL query.
   * @param WP_Query $query The WP_Query instance (passed by reference).
   *
   * @since 2.0.0
   *
   */
  public function posts_request( $request, $query ) {
    if ( ! isset( $query->query_vars['no_found_rows'] ) || ! $query->query_vars['no_found_rows'] ) {
      /* We're attempting pagination. */
      $stub       = $this->get_stub( $request );
      $key        = $this->generate_cache_key( $query->query_vars, $stub );
      $found_rows = wp_cache_get( $key, 'fast-results-found-rows' );

      $query->query_vars['fast-results-key'] = $key;
      if ( is_numeric( $found_rows ) ) {
        $query->query_vars['fast-results-value'] = $found_rows;
        if ( 0 === count( $this->stubs_to_intervene ) ) {
          add_filter( 'query', array( $this, 'query' ), 1, 1 );
        }
        $this->stubs_to_intervene[ $stub ] = true;
      } else {
        unset ( $query->query_vars['fast-results-value'] );
      }
    }

    return $request;
  }

  /**
   * Filters the query to run for retrieving the found posts.
   *
   * @param string $request The query to run to find the found posts.
   * @param WP_Query $query The WP_Query instance (passed by reference).
   *
   * @since 2.1.0
   *
   */
  public function found_posts_query( $request, $query ) {
    if ( isset( $query->query_vars['fast-results-key'] ) ) {
      if ( isset( $query->query_vars['fast-results-value'] ) ) {
        $request = str_replace( 'FOUND_ROWS()', $query->query_vars['fast-results-value'], $request );
      }
    }

    return $request;
  }

  /**
   * Filters the number of found posts for the query.
   *
   * @param int $found_posts The number of posts found.
   * @param WP_Query $query The WP_Query instance (passed by reference).
   *
   * @since 2.1.0
   *
   */
  public function found_posts( $found_posts, $query ) {
    if ( isset( $query->query_vars['fast-results-key'] ) ) {
      if ( ! isset( $query->query_vars['fast-results-value'] ) ) {
        $query->query_vars['fast-results-value'] = $found_posts;
        wp_cache_set( $query->query_vars['fast-results-key'], $found_posts, 'fast-results-found-rows', WEEK_IN_SECONDS );
      }
    }

    return $found_posts;
  }

  /**
   * Filters the database query.
   *
   * Some queries are made before the plugins have been loaded,
   * and thus cannot be filtered with this method.
   *
   * @param string $query Database query.
   *
   * @since 2.1.0
   *
   */
  public function query( $query ) {
    if ( str_contains( $query, 'SQL_CALC_FOUND_ROWS' ) ) {
      $stub = $this->get_stub( $query );
      if ( isset( $this->stubs_to_intervene[ $stub ] ) ) {
        $query = str_replace( 'SQL_CALC_FOUND_ROWS', '/*SQL_CALC_FOUND_ROWS*/', $query );
        unset ( $this->stubs_to_intervene[ $stub ] );
        if ( 0 === count( $this->stubs_to_intervene ) ) {
          remove_filter( 'query', array( $this, 'query' ), 1 );
        }
      }
    }

    return $query;
  }

  /**
   * Generates cache key. Adapted from class-wp-query.php
   *
   * @param array $args Query arguments.
   * @param string $sql SQL statement.
   *
   * @return string Cache key.
   * @global wpdb $wpdb WordPress database abstraction object.
   *
   */
  protected function generate_cache_key( array $args, $sql ) {
    global $wpdb;

    /* Generate the same key for queries for different pages of a query */
    unset(
      $args['cache_results'],
      $args['fields'],
      $args['lazy_load_term_meta'],
      $args['update_post_meta_cache'],
      $args['update_post_term_cache'],
      $args['update_menu_item_cache'],
      $args['suppress_filters'],
      $args['paged'],
      $args['page'],
      $args['offset'],
      $args['no_found_rows']
    );

    $placeholder = $wpdb->placeholder_escape();
    array_walk_recursive(
      $args,
      /*
       * Replace wpdb placeholders with the string used in the database
       * query to avoid unreachable cache keys. This is necessary because
       * the placeholder is randomly generated in each request.
       *
       * $value is passed by reference to allow it to be modified.
       * array_walk_recursive() does not return an array.
       */
      static function ( &$value ) use ( $wpdb, $placeholder ) {
        if ( is_string( $value ) && str_contains( $value, $placeholder ) ) {
          $value = $wpdb->remove_placeholder_escape( $value );
        }
      }
    );

    /* Replace wpdb placeholder in the SQL statement used by the cache key. */
    $sql = $wpdb->remove_placeholder_escape( $sql );

    /*
     * Notice that $last_changed is refreshed on every page view
     * in the absence of a persistent object cache. So these
     * cache keys are useless across page views.
     */
    $last_changed = wp_cache_get_last_changed( 'posts' );
    if ( ! empty( $this->tax_query->queries ) ) {
      $last_changed .= wp_cache_get_last_changed( 'terms' );
    }

    $key = md5( serialize( $args ) . $sql . $last_changed );


    return 'fast_results:' . $key;
  }

  /**
   * Remove the SELECT and LIMIT clauses from a SQL query.
   *
   * @param string $request
   *
   * @return string
   */
  private function get_stub( $request ) {

    $request = preg_replace( '/\s+/', ' ', $request );

    return preg_replace( '/.+?\sFROM\s(.+)\sLIMIT\s\d+.*$/', '$1', $request );
  }

}

/**
 * HELPER COMMENT START
 *
 * This file contains the main information about the plugin.
 * It is used to register all components necessary to run the plugin.
 *
 * The comment above contains all information about the plugin
 * that are used by WordPress to differentiate the plugin and register it properly.
 * It also contains further PHPDocs parameter for a better documentation
 *
 * HELPER COMMENT END
 */

// Plugin name
const FAST_RESULTS_NAME = 'Fast Results';

// Plugin version
const FAST_RESULTS_VERSION = '0.1.1';

// Plugin Root File
const FAST_RESULTS_PLUGIN_FILE = __FILE__;

// Plugin base
define( 'FAST_RESULTS_PLUGIN_BASE', plugin_basename( FAST_RESULTS_PLUGIN_FILE ) );

// Plugin slug
define( 'FAST_RESULTS_SLUG', explode( DIRECTORY_SEPARATOR, FAST_RESULTS_PLUGIN_BASE )[0] );

// Plugin Folder Path
define( 'FAST_RESULTS_PLUGIN_DIR', plugin_dir_path( FAST_RESULTS_PLUGIN_FILE ) );

// Plugin Folder URL
define( 'FAST_RESULTS_PLUGIN_URL', plugin_dir_url( FAST_RESULTS_PLUGIN_FILE ) );


register_activation_hook( __FILE__, 'Fast_Results\activate' );
register_deactivation_hook( __FILE__, 'Fast_Results\deactivate' );

add_action( 'init', array( 'Fast_Results\FastResults', 'init' ) );


function activate() {
//    error_log('activate');
  register_uninstall_hook( __FILE__, 'Fast_Results\uninstall' );
}

function deactivate() {
//    error_log('deactivate');

}

function uninstall() {
//    error_log('uninstall');
}
