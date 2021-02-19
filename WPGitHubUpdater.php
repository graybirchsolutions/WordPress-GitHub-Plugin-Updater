<?php

// Prevent loading this file directly and/or if the class is already defined
if ( ! defined( 'ABSPATH' ) || class_exists( 'WPGitHubUpdater' ) || class_exists( 'WP_GitHub_Updater' ) )
	return;

/**
 *
 *
 * @version 1.7
 * @author Joachim Kudish <info@jkudish.com>
 * @link http://jkudish.com
 * @package WPGitHubUpdater
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @copyright Copyright (c) 2011-2013, Joachim Kudish
 *
 * GNU General Public License, Free Software Foundation
 * <http://creativecommons.org/licenses/GPL/2.0/>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */
// Class name changed to eliminate underscore to comply with PSR-4/PSR-0 autoload
class WPGitHubUpdater {

	/**
	 * GitHub Updater version
	 */
	const VERSION = 1.7;

	/**
	 * @var $config the config for the updater
	 * @access public
	 */
	var $config;

	/**
	 * @var $missing_config any config that is missing from the initialization of this instance
	 * @access public
	 */
	var $missing_config;

	/**
	 * @var $github_data temporiraly store the data fetched from GitHub, allows us to only load the data once per class instance
	 * @access private
	 */
	private $github_data;

	/**
	 * @var $github_latest_release temporiraly store the data fetched from GitHub, allows us to only load the data once per class instance
	 * @access private
	 */
	private $github_latest_release;

	/**
	 * Class Constructor
	 *
	 * @since 1.0
	 * @param array $config the configuration required for the updater to work
	 * @see has_minimum_config()
	 * @return void
	 */
	public function __construct( $config = array() ) {

		$defaults = array(
			'slug' => plugin_basename( __FILE__ ),
			'proper_folder_name' => dirname( plugin_basename( __FILE__ ) ),
			'sslverify' => true,
			'access_token' => '',
		);

		$this->config = wp_parse_args( $config, $defaults );

		// if the minimum config isn't set, issue a warning and bail
		if ( ! $this->has_minimum_config() ) {
			$message = 'The GitHub Updater was initialized without the minimum required configuration, please check the config in your plugin. The following params are missing: ';
			$message .= implode( ',', $this->missing_config );
			_doing_it_wrong( __CLASS__, $message , self::VERSION );
			return;
		}

		$this->set_defaults();

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'api_check' ) );

		// Hook into the plugin details screen
		add_filter( 'plugins_api', array( $this, 'get_plugin_info' ), 10, 3 );
		add_filter( 'upgrader_post_install', array( $this, 'upgrader_post_install' ), 10, 3 );

		// set timeout
		add_filter( 'http_request_timeout', array( $this, 'http_request_timeout' ) );

		// set sslverify for zip download
		add_filter( 'http_request_args', array( $this, 'http_request_sslverify' ), 10, 2 );
	}

	public function has_minimum_config() {

		$this->missing_config = array();

		$required_config_params = array(
			'api_url',
			'raw_url',
			'github_url',
			'zip_url',
			'requires',
			'tested',
			'readme',
		);

		foreach ( $required_config_params as $required_param ) {
			if ( empty( $this->config[$required_param] ) )
				$this->missing_config[] = $required_param;
		}

		return ( empty( $this->missing_config ) );
	}


	/**
	 * Check wether or not the transients need to be overruled and API needs to be called for every single page load
	 *
	 * @return bool overrule or not
	 */
	public function overrule_transients() {
		return ( defined( 'WP_GITHUB_FORCE_UPDATE' ) && WP_GITHUB_FORCE_UPDATE );
	}


	/**
	 * Set defaults
	 *
	 * @since 1.2
	 * @return void
	 */
	public function set_defaults() {
		if ( !empty( $this->config['access_token'] ) ) {

			// See Downloading a zipball (private repo) https://help.github.com/articles/downloading-files-from-the-command-line
			extract( parse_url( $this->config['zip_url'] ) ); // $scheme, $host, $path

			$zip_url = $scheme . '://api.github.com/repos' . $path;
			$zip_url = add_query_arg( array( 'access_token' => $this->config['access_token'] ), $zip_url );

			$this->config['zip_url'] = $zip_url;
		}


		if ( ! isset( $this->config['new_version'] ) )
			$this->config['new_version'] = $this->get_new_version();

		if ( ! isset( $this->config['last_updated'] ) )
			$this->config['last_updated'] = $this->get_date();

		if ( ! isset( $this->config['description'] ) )
			$this->config['description'] = $this->get_description();

		$plugin_data = $this->get_plugin_data();
		if ( ! isset( $this->config['plugin_name'] ) )
			$this->config['plugin_name'] = $plugin_data['Name'];

		if ( ! isset( $this->config['version'] ) )
			$this->config['version'] = $plugin_data['Version'];

		if ( ! isset( $this->config['author'] ) )
			$this->config['author'] = $plugin_data['Author'];

		if ( ! isset( $this->config['homepage'] ) )
			$this->config['homepage'] = $plugin_data['PluginURI'];

		if ( ! isset( $this->config['readme'] ) )
			$this->config['readme'] = 'README.md';

	}


	/**
	 * Callback fn for the http_request_timeout filter
	 *
	 * @since 1.0
	 * @return int timeout value
	 */
	public function http_request_timeout() {
		return 2;
	}

	/**
	 * Callback fn for the http_request_args filter
	 *
	 * @param unknown $args
	 * @param unknown $url
	 *
	 * @return mixed
	 */
	public function http_request_sslverify( $args, $url ) {
		if ( $this->config[ 'zip_url' ] == $url )
			$args[ 'sslverify' ] = $this->config[ 'sslverify' ];

		return $args;
	}


	/**
	 * Get New Version from GitHub
	 *
	 * @since 1.0
	 * @return int $version the version number
	 */
	public function get_new_version() {
		$version = get_site_transient( md5($this->config['slug']).'_new_version' );

		if ( $this->overrule_transients() || ( !isset( $version ) || !$version || '' == $version ) ) {

			$version = false;

			// Added for 1.7
			// Fetch version tag from releases/latest as first choice

			$_latest = $this->get_latest_release();

			if ( !empty( $_latest->tag_name )) {
				// Extract version number string from the tag_name
				// Presumes tag may include some text prior to the version number (e.g. 'Release 1.2.3' or 'v1.2.4')
				// or text after the version string separated by whitespace.
				// tag_name = 'v2.0.0beta2' returns version string 2.0.0beta2
				// Requires at least 2-digit (major.minor) version string
				preg_match ( '/(?:[0-9]+\.)+[0-9A-Za-z-.]+/', $_latest->tag_name, $matches );

				if ( empty( $matches[0] ) )
					$version = false;
				else
					$version = $matches[0];
			}

			// If no release tag, try version string in plugin data block
			if ( false === $version ) {
				$raw_response = $this->remote_get( trailingslashit( $this->config['raw_url'] ) . basename( $this->config['slug'] ) );

				if ( is_wp_error( $raw_response ) )
					$version = false;

				if (is_array($raw_response)) {
					if (!empty($raw_response['body']))
						preg_match( '/.*Version\:\s*(.*)$/mi', $raw_response['body'], $matches );
				}

				if ( empty( $matches[1] ) )
					$version = false;
				else
					$version = $matches[1];
				error_log('Version selected from plugin data block: ' . $version);
			}

			// back compat for older readme version handling
			// only done when there is no version found in file name
			if ( false === $version ) {
				$raw_response = $this->remote_get( trailingslashit( $this->config['raw_url'] ) . $this->config['readme'] );

				if ( is_wp_error( $raw_response ) )
					return $version;

				preg_match( '#^\s*`*~Current Version\:\s*([^~]*)~#im', $raw_response['body'], $__version );

				if ( isset( $__version[1] ) ) {
					$version_readme = $__version[1];
					if ( -1 == version_compare( $version, $version_readme ) )
						$version = $version_readme;
				}
			}

			// refresh every 6 hours
			if ( false !== $version )
				set_site_transient( md5($this->config['slug']).'_new_version', $version, 60*60*6 );
		}

		return $version;
	}


	/**
	 * Interact with GitHub
	 *
	 * @param string $query
	 *
	 * @since 1.6
	 * @return mixed
	 */
	public function remote_get( $query ) {
		if ( ! empty( $this->config['access_token'] ) )
			$query = add_query_arg( array( 'access_token' => $this->config['access_token'] ), $query );

		$raw_response = wp_remote_get( $query, array(
			'sslverify' => $this->config['sslverify']
		) );

		return $raw_response;
	}


	/**
	 * Get GitHub Data from the specified repository
	 *
	 * @since 1.0
	 * @return array $github_data the data
	 */
	public function get_github_data() {
		if ( isset( $this->github_data ) && ! empty( $this->github_data ) ) {
			$github_data = $this->github_data;
		} else {
			$github_data = get_site_transient( md5($this->config['slug']).'_github_data' );

			if ( $this->overrule_transients() || ( ! isset( $github_data ) || ! $github_data || '' == $github_data ) ) {
				$github_data = $this->remote_get( $this->config['api_url'] );

				if ( is_wp_error( $github_data ) )
					return false;

				$github_data = json_decode( $github_data['body'] );

				// refresh every 6 hours
				set_site_transient( md5($this->config['slug']).'_github_data', $github_data, 60*60*6 );
			}

			// Store the data in this class instance for future calls
			$this->github_data = $github_data;
		}

		return $github_data;
	}

	/**
	 * Get GitHub Latest Release data from the specified repository
	 *
	 * @since 1.7
	 * @return array $github_latest_release the data
	 */
	public function get_latest_release() {
		if ( isset( $this->github_latest_release ) && ! empty( $this->github_latest_release ) ) {
			$github_latest_release = $this->github_latest_release;
		} else {
			$github_latest_release = get_site_transient( md5($this->config['slug']).'_github_latest_release' );

			if ( $this->overrule_transients() || ( ! isset( $github_latest_release ) || ! $github_latest_release || '' == $github_latest_release ) ) {
				$github_latest_release = $this->remote_get( trailingslashit( $this->config['api_url'] ) . 'releases/latest'  );

				if ( is_wp_error( $github_latest_release ) )
					return false;

				$github_latest_release = json_decode( $github_latest_release['body'] );

				// refresh every 6 hours
				set_site_transient( md5($this->config['slug']).'_github_latest_release', $github_latest_release, 60*60*6 );
			}

			// Store the data in this class instance for future calls
			$this->github_latest_release = $github_latest_release;

			// Overwrite the zip_url with (1) the first zip asset or (2) the zipball_url from the release data info
			$no_zip_asset = true;
			if ( !empty( $github_latest_release->assets ) ) {
				foreach ($github_latest_release->assets as $asset) {
					if ( $asset->content_type === 'application/zip' ) {
						$this->config['zip_url'] = $asset->browser_download_url;
						$no_zip_asset = false;
						break;
					}
				}
			}
			if (!empty( $github_latest_release->zipball_url && $no_zip_asset ))
				$this->config['zip_url'] = $github_latest_release->zipball_url;
		}

		return $github_latest_release;
	}


	/**
	 * Get update date
	 *
	 * @since 1.0
	 * @return string $date the date
	 */
	public function get_date() {
		// New in 1.7 - Prefer latest release publication date on GitHub
		$_date = $this->get_latest_release();
		if ( !empty( $_date->published_at ))
			return date( 'Y-m-d', strtotime( $_date->published_at ) );
		$_date = $this->get_github_data();
		return ( !empty( $_date->updated_at ) ) ? date( 'Y-m-d', strtotime( $_date->updated_at ) ) : false;
	}


	/**
	 * Get plugin description
	 * 
	 * Updated for 1.7
	 * 
	 * get_description() now returns an array with descriptive text for different sections of the plugin information display in Wordpress.
	 * 
	 * Instead of the simple 'description' block from the repo metadata, this routine will retrieve the README file (presumed to be written in
	 * Markdown), convert it to HTML and insert it into the $description array as the 'description' section.
	 * 
	 * Working with Releases (new for 1.7), get_description() will fetch the body text / release description (again in Markdown) stored in the
	 * release metadata, convert it to HTML and insert it into the $description array as the 'changelog' section.
	 * 
	 * As an alternative to using the release description, the config can include a reference for a 'changelog' file in the repository.
	 * The changelog file has precedence over the release description. If ommitted from the config array, release description is used.
	 *
	 * @since 1.0
	 * @return array $description An associative array of strings describing different sections of the Wordpress plugin information sections.
	 */
	public function get_description() {

		$pd = new Parsedown; // Instantiate the Parsedown parser for Markdown

		// Fetch plugin description (1) from README or (2) from the repo metadata
		if ( !empty( $this->config['readme'] ) ) {
			$raw_response = $this->remote_get( trailingslashit( $this->config['raw_url'] ) . $this->config['readme'] );
			if ( is_wp_error( $raw_response ) ) {
				$description['description'] = 'README file not found in GitHub repo.';
			} else {
				$description['description'] = $pd->text($raw_response['body']);
			}
		} else {
			$gdata = $this->get_github_data();
			if ( !empty( $gdata->description ) ) {
				$description['description'] = $gdata->description;
			}
			else {
				$description['description'] = 'No description available.';
			}
		}

		// Fetch release changelog (1) from CHANGELOG file or (2) from the release description
		if ( !empty( $this->config['changelog'] ) ) {
			$raw_response = $this->remote_get( trailingslashit( $this->config['raw_url'] ) . $this->config['changelog'] );
			if ( is_wp_error( $raw_response ) ) {
				$description['changelog'] = 'Changelog not found.';
			} else {
				$description['changelog'] = $pd->text($raw_response['body']);
			}
		} else {
			$rdata = $this->get_latest_release();
			$description['changelog'] = $pd->text( $rdata->body );
		}

		return ( !empty( $description ) ) ? $description : false;
	}


	/**
	 * Get Plugin data
	 *
	 * @since 1.0
	 * @return object $data the data
	 */
	public function get_plugin_data() {
		include_once ABSPATH.'/wp-admin/includes/plugin.php';
		$data = get_plugin_data( WP_PLUGIN_DIR.'/'.$this->config['slug'] );
		return $data;
	}


	/**
	 * Hook into the plugin update check and connect to GitHub
	 *
	 * @since 1.0
	 * @param object  $transient the plugin data transient
	 * @return object $transient updated plugin data transient
	 */
	public function api_check( $transient ) {

		// Check if the transient contains the 'checked' information
		// If not, just return its value without hacking it
		if ( empty( $transient->checked ) )
			return $transient;

		// check the version and decide if it's new
		$update = version_compare( $this->config['new_version'], $this->config['version'] );

		if ( 1 === $update ) {
			$response = new stdClass;
			$response->new_version = $this->config['new_version'];
			$response->slug = $this->config['proper_folder_name'];
			$response->url = add_query_arg( array( 'access_token' => $this->config['access_token'] ), $this->config['github_url'] );
			$response->package = $this->config['zip_url'];

			// If response is false, don't alter the transient
			if ( false !== $response )
				$transient->response[ $this->config['slug'] ] = $response;
		}

		return $transient;
	}


	/**
	 * Get Plugin info
	 *
	 * @since 1.0
	 * @param bool    $false  always false
	 * @param string  $action the API function being performed
	 * @param object  $args   plugin arguments
	 * @return object $response the plugin info
	 */
	public function get_plugin_info( $false, $action, $response ) {

		// Check if this call API is for the right plugin
		if ( !isset( $response->slug ) || !($response->slug == $this->config['slug'] || $response->slug == $this->config['proper_folder_name']) )
			return false;

		$response->slug = $this->config['slug'];
		$response->plugin_name  = $this->config['plugin_name'];
		$response->version = $this->config['new_version'];
		$response->author = $this->config['author'];
		$response->homepage = $this->config['homepage'];
		$response->requires = $this->config['requires'];
		$response->tested = $this->config['tested'];
		$response->downloaded   = 0;
		$response->last_updated = $this->config['last_updated'];
		$response->sections = $this->config['description'];
		$response->download_link = $this->config['zip_url'];

		return $response;
	}


	/**
	 * Upgrader/Updater
	 * Move & activate the plugin, echo the update message
	 *
	 * @since 1.0
	 * @param boolean $true       always true
	 * @param mixed   $hook_extra not used
	 * @param array   $result     the result of the move
	 * @return array $result the result of the move
	 */
	public function upgrader_post_install( $true, $hook_extra, $result ) {

		global $wp_filesystem;

		// Move & Activate
		$proper_destination = WP_PLUGIN_DIR.'/'.$this->config['proper_folder_name'];
		$wp_filesystem->move( $result['destination'], $proper_destination );
		$result['destination'] = $proper_destination;
		$activate = activate_plugin( WP_PLUGIN_DIR.'/'.$this->config['slug'] );

		// Output the update message
		$fail  = __( 'The plugin has been updated, but could not be reactivated. Please reactivate it manually.', 'github_plugin_updater' );
		$success = __( 'Plugin reactivated successfully.', 'github_plugin_updater' );
		echo is_wp_error( $activate ) ? $fail : $success;
		return $result;

	}
}
