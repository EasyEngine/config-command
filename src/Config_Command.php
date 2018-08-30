<?php

use EE\Model\Site;
use Mustangostang\Spyc;
use \Symfony\Component\Filesystem\Filesystem;

/**
 * Manges global EE configuration.
 *
 * ## EXAMPLES
 *
 *     # Save value in config
 *     $ ee config set le-mail='abc@example.com' admin-email=abcd@example1.com
 *
 *     # Get value from config
 *     $ ee config get le-mail
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
	 */
	public function get( $args, $assoc_args ) {
		$config_file_path = getenv( 'EE_CONFIG_PATH' ) ? getenv( 'EE_CONFIG_PATH' ) : EE_CONF_ROOT . '/config.yml';
		$config = Spyc::YAMLLoad( $config_file_path );

		if ( ! isset( $config[$args[0]] ) ) {
			EE::error("No config value with key '$args[0]' set");
		}

		EE::log( $config[$args[0]] );
	}

	/**
	 * Get a config value
	 *
	 * ## OPTIONS
	 *
	 * <config-key-value>...
	 * : Key value pair of config to set
	 */
	public function set( $args, $assoc_args ) {
		$config_file_path = getenv( 'EE_CONFIG_PATH' ) ? getenv( 'EE_CONFIG_PATH' ) : EE_CONF_ROOT . '/config.yml';
		$config = Spyc::YAMLLoad( $config_file_path );

		foreach ( $args as $arg ) {
			$key_val = explode( '=', $arg, 2 );

			if ( count( $key_val ) < 2 ) {
				EE::warning( "Cannot add $arg in config as it has no corrosponding value" );
				continue;
			}

			list( $key, $val ) = $key_val;
			$config[$key] = $value;
		}

		$this->fs->dumpFile( $config_file_path, Spyc::YAMLDump( $config, false, false, true ) );
	}
}
