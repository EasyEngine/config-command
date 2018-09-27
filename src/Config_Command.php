<?php

use Mustangostang\Spyc;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Manges global EE configuration.
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

		EE::log( $config[ $args[0] ] );
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
}
