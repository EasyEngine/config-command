<?php

use Mustangostang\Spyc;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Manges global EasyEngine configuration.
 *
 * @package ee-cli
 */
class Config_Command extends EE_Command {

	/**
	 * @var Filesystem $fs Symfony Filesystem object.
	 */
	private $fs;

	public function __construct() {

		$this->fs = new Filesystem();
	}

	/**
	 * Set a config value
	 *
	 * ## OPTIONS
	 *
	 * <config-key>
	 * : Name of config value to get
	 *
	 * ## EXAMPLES
	 *
	 *     # Get value from config
	 *     $ ee config get le-mail
	 *
	 */
	public function get( $args, $assoc_args ) {
		$config_file_path = getenv( 'EE_CONFIG_PATH' ) ? getenv( 'EE_CONFIG_PATH' ) : EE_ROOT_DIR . '/config/config.yml';
		$config = Spyc::YAMLLoad( $config_file_path );

		if ( ! isset( $config[ $args[0] ] ) ) {
			EE::error( "No config value with key '$args[0]' set" );
		}

		// Printed for the user only: config values can be secrets and must not reach the log file.
		EE::line( $config[ $args[0] ] );
	}

	/**
	 * Set a config value
	 *
	 * ## OPTIONS
	 *
	 * <key>
	 * : Key of config to set
	 *
	 * <value>
	 * : Value of config to set
	 *
	 * ## EXAMPLES
	 *
	 *     # Save value in config
	 *     $ ee config set le-mail abc@example.com
	 *
	 */
	public function set( $args, $assoc_args ) {
		$config_file_path = getenv( 'EE_CONFIG_PATH' ) ? getenv( 'EE_CONFIG_PATH' ) : EE_ROOT_DIR . '/config/config.yml';
		$config = Spyc::YAMLLoad( $config_file_path );
		$key   = $args[0];
		$value = $args[1];

		$config[ $key ] = $value;

		$this->fs->dumpFile( $config_file_path, Spyc::YAMLDump( $config, false, false, true ) );
	}

	/**
	 * Unset a config value
	 *
	 * ## OPTIONS
	 *
	 * <key>
	 * : Key of config to unset
	 *
	 * ## EXAMPLES
	 *
	 *     # Remove value from config
	 *     $ ee config unset cloudflare-api-key
	 *
	 */
	public function unset( $args, $assoc_args ) {
		$config_file_path = getenv( 'EE_CONFIG_PATH' ) ? getenv( 'EE_CONFIG_PATH' ) : EE_ROOT_DIR . '/config/config.yml';
		$config           = Spyc::YAMLLoad( $config_file_path );
		$key              = $args[0];

		if ( ! isset( $config[ $key ] ) ) {
			EE::error( "No config value with key '$key' set" );
		}

		unset( $config[ $key ] );

		$this->fs->dumpFile( $config_file_path, Spyc::YAMLDump( $config, false, false, true ) );
	}

	/**
	 * Lists the config values.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - yaml
	 *   - json
	 *   - text
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # List all config values
	 *     $ ee config list
	 *
	 *     # List all config values in JSON
	 *     $ ee config list --format=json
	 *
	 * @subcommand list
	 */
	public function _list( $args, $assoc_args ) {
		$config_file_path = getenv( 'EE_CONFIG_PATH' ) ? getenv( 'EE_CONFIG_PATH' ) : EE_ROOT_DIR . '/config/config.yml';
		$config           = Spyc::YAMLLoad( $config_file_path );
		$format           = \EE\Utils\get_flag_value( $assoc_args, 'format' );

		if ( empty( $config ) ) {
			\EE::error( 'No config values found!' );
		}

		if ( 'text' === $format ) {
			foreach ( $config as $key => $value ) {
				\EE::line( $key . ': ' . $value );
			}
		} else {
			$result = array_map(
				function ( $key, $value ) {
					return [
						'key'   => $key,
						'value' => $value,
					];
				},
				array_keys( $config ),
				$config
			);

			$formatter = new \EE\Formatter( $assoc_args, [ 'key', 'value' ] );
			$formatter->display_items( $result );
		}
	}
}
