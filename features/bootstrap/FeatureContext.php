<?php
/**
 * Behat tests.
 *
 * @package ee-cli
 */

/* Start: Loading required files to enable EE::launch() in tests. */
define( 'EE_ROOT', __DIR__ . '/../..' );

include_once( EE_ROOT . '/php/class-ee.php' );
include_once( EE_ROOT . '/php/EE/Runner.php' );
include_once( EE_ROOT . '/php/utils.php' );

define( 'EE', true );
define( 'EE_VERSION', trim( file_get_contents( EE_ROOT . '/VERSION' ) ) );

$root_alias = '/opt';
if ( 'Darwin' === php_uname( 's' ) ) {
	$sys_name   = posix_getpwuid(posix_geteuid())['name'];
	if ( ! empty( $sys_name ) ) {
		$root_alias = '/Users/' . $sys_name;
	}
}
// A package autoload file may already define it.
if ( ! defined( 'EE_ROOT_DIR' ) ) {
	define( 'EE_ROOT_DIR', $root_alias . '/easyengine' );
}

require_once EE_ROOT . '/php/bootstrap.php';

if ( ! class_exists( 'EE\Runner' ) ) {
	require_once EE_ROOT . '/php/EE/Runner.php';
}

if ( ! class_exists( 'EE\Configurator' ) ) {
	require_once EE_ROOT . '/php/EE/Configurator.php';
}

$logger_dir = EE_ROOT . '/php/EE/Loggers';
$iterator   = new \DirectoryIterator( $logger_dir );

// Make sure the base class is declared first.
include_once "$logger_dir/Base.php";

foreach ( $iterator as $filename ) {
	if ( '.php' !== pathinfo( $filename, PATHINFO_EXTENSION ) ) {
		continue;
	}

	include_once "$logger_dir/$filename";
}
$runner = \EE::get_runner();
$runner->init_logger();
$runner->init_config();
/* End. Loading required files to enable EE::launch() in tests. */

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\AfterFeatureScope;
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;

use Behat\Gherkin\Node\PyStringNode,
	Behat\Gherkin\Node\TableNode;

define( 'EE_SITE_ROOT', EE_ROOT_DIR . '/sites' );

class FeatureContext implements Context
{
	public $command;
	public $webroot_path;
	public $ee_path;

	/**
	 * Every site created by this suite starts with this prefix; cleanup only touches these.
	 */
	const SITE_PREFIX = 'bh-config-command-';

	public $commands = [];

	/**
	 * Initializes context.
	 */
	public function __construct()
	{
		$this->commands = [];
		$this->ee_path = getcwd();
	}

	/**
	 * @Given ee phar is generated
	 */
	public function eePharIsPresent()
	{
		self::$pharGenerated = true;
		// Checks if phar already exists, replaces it
		if(file_exists('ee-old.phar')) {
			// Below exec call is required as currenly `ee cli update` is ran with root
			// which updates ee.phar with root privileges.
			exec("sudo rm ee.phar");
			copy('ee-old.phar','ee.phar');
			return 0;
		}
		exec("php -dphar.readonly=0 utils/make-phar.php ee.phar", $output, $return_status);
		if (0 !== $return_status) {
			throw new Exception("Unable to generate phar" . $return_status);
		}

		// Cache generaed phar as it is expensive to generate one
		copy('ee.phar','ee-old.phar');
	}

	/**
	 * @Given :command is installed
	 */
	public function isInstalled($command)
	{
		exec("type " . $command, $output, $return_status);
		if (0 !== $return_status) {
			throw new Exception($command . " is not installed! Exit code is:" . $return_status);
		}
	}

	/**
	 * @When /I run '(.*)'|"(.*)"/
	 */
	public function iRun($command)
	{
		$command = $this->replaceVariables( $command );

		// sudo resets the environment, so the command would silently use the real config.
		if ( $this->tempConfigPath && preg_match( '/\b(sudo|su)\b|\benv\s+-/', $command ) ) {
			throw new Exception( "Refusing to run '$command': sudo, su and env options can drop EE_CONFIG_PATH. Drop them (the suite runs as root)." );
		}

		// Not EE::launch(): it copies the command's STDOUT/STDERR into ee.log, which would pollute log assertions.
		$this->commands[] = \EE\Process::create( $command, null, null )->run();
	}

	/**
	 * @Then return value should be 0
	 */
	public function returnValueShouldBe0()
	{
		if ( 0 !== $this->commands[0]->return_code ) {
			throw new Exception("Actual return code is not zero: \n" . $this->command);
		}
	}

	/**
	 * @Then return value of command :index should be 0
	 */
	public function returnValueOfCommandShouldBe0($index)
	{
		if ( 0 !== $this->commands[ $index - 1 ]->return_code ) {
			throw new Exception("Actual return code is not zero: \n" . $this->command);
		}
	}

	/**
	 * @Then After delay of :time seconds
	 */
	public function afterDelayOfSeconds( $time )
	{
		sleep( $time );
	}

	/**
	 * @Then /(STDOUT|STDERR) should return exactly/
	 */
	public function stdoutShouldReturnExactly($output_stream, PyStringNode $expected_output)
	{
		$command_output = $output_stream === "STDOUT" ? $this->commands[0]->stdout : $this->commands[0]->stderr;

		$command_output = str_replace(["\033[1;31m","\033[0m"],'',$command_output);

		if ( $expected_output->getRaw() !== trim($command_output)) {
			throw new Exception("Actual output is:\n" . $command_output);
		}
	}

	/**
	 * @Then /(STDOUT|STDERR) of command :index should return exactly/
	 */
	public function stdoutOfCommandShouldReturnExactly($output_stream, $index, PyStringNode $expected_output)
	{
		$command_output = $output_stream === "STDOUT" ? $this->commands[ $index - 1 ]->stdout : $this->commands[ $index - 1 ]->stderr;

		$command_output = str_replace(["\033[1;31m","\033[0m"],'',$command_output);

		if ( $expected_output->getRaw() !== trim($command_output)) {
			throw new Exception("Actual output is:\n" . $command_output);
		}
	}

	/**
	 * @Then /(STDOUT|STDERR) should return something like/
	 */
	public function stdoutShouldReturnSomethingLike($output_stream, PyStringNode $expected_output)
	{
		$command_output = $output_stream === "STDOUT" ? $this->commands[0]->stdout : $this->commands[0]->stderr;

		if (strpos($command_output, $expected_output->getRaw()) === false) {
			throw new Exception("Actual output is:\n" . $command_output);
		}
	}


	/**
	 * @Then /(STDOUT|STDERR) of command :index should return something like/
	 */
	public function stdoutOfCommandShouldReturnSomethingLike($output_stream, $index, PyStringNode $expected_output)
	{
		$command_output = $output_stream === "STDOUT" ? $this->commands[ $index - 1 ]->stdout : $this->commands[ $index - 1 ]->stderr;

		if (strpos($command_output, $expected_output->getRaw()) === false) {
			throw new Exception("Actual output is:\n" . $command_output);
		}
	}

	/**
	 * @Then The site :site should be :type multisite
	 */
	public function theSiteShouldBeMultisite( $site, $type )
	{
		$result = EE::launch( "cd " . EE_SITE_ROOT . "/$site && docker-compose exec --user='www-data' php sh -c 'wp config get SUBDOMAIN_INSTALL'", false, true );

		if( $result->stderr ) {
			throw new Exception("Found error while executing command: $result->stderr");
		}

		if($type === 'subdomain' && trim($result->stdout) !== '1') {
			throw new Exception("Expecting SUBDOMAIN_INSTALL to be 1. Got: $result->stdout");
		}
		else if($type === 'subdir' && trim($result->stdout) !== '') {
			throw new Exception("Expecting SUBDOMAIN_INSTALL to be empty. Got: $result->stdout");
		}
		else if($type !== 'subdomain' && $type !== 'subdir') {
			throw new Exception("Found unknown site type: $type");
		}
	}

	/**
	 * @Then The :site db entry should be removed
	 */
	public function theDbEntryShouldBeRemoved($site)
	{
		$out = shell_exec("sudo bin/ee site list");
		if (strpos($out, $site) !== false) {
			throw new Exception("$site db entry not been removed!");
		}

	}

	/**
	 * @Then The :site webroot should be removed
	 */
	public function theWebrootShouldBeRemoved($site)
	{
		if ( file_exists( EE_SITE_ROOT . '/' . $site ) ) {
			throw new Exception("Webroot has not been removed!");
		}
	}

	/**
	 * @Then Following containers of site :site should be removed:
	 */
	public function followingContainersOfSiteShouldBeRemoved($site, TableNode $table)
	{
		$containers = $table->getHash();
		$site_name = implode(explode('.', $site));

		foreach ($containers as $container) {

			$sevice = $container['container'];
			$container_name = $site_name . '_' . $sevice . '_1';

			exec("docker inspect -f '{{.State.Running}}' $container_name > /dev/null 2>&1", $exec_out, $return);
			if (!$return) {
				throw new Exception("$container_name has not been removed!");
			}
		}
	}

	/**
	 * @Then The site :site should have webroot
	 */
	public function theSiteShouldHaveWebroot($site)
	{
		if ( ! file_exists( EE_SITE_ROOT . '/' . $site ) ) {
			throw new Exception("Webroot has not been created!");
		}
	}

	/**
	 * @Then The site :site should have WordPress
	 */
	public function theSiteShouldHaveWordpress($site)
	{
		if ( ! file_exists( EE_SITE_ROOT . '/' . $site . '/app/wp-config.php' ) ) {
			throw new Exception("WordPress data not found!");
		}
	}

	/**
	 * @Then Request on :site should contain following headers:
	 */
	public function requestOnShouldContainFollowingHeaders($site, TableNode $table)
	{
		$url = 'http://' . $site;

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_NOBODY, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		$headers = curl_exec($ch);

		curl_close($ch);

		$rows = $table->getHash();

		foreach ($rows as $row) {
			if (strpos($headers, $row['header']) === false) {
				throw new Exception("Unable to find " . $row['header'] . "\nActual output is : " . $headers);
			}
		}
	}

	/**
	 * @Then Request on :host with header :header should contain following headers:
	 */
	public function requestOnWithHeaderShouldContainFollowingHeaders($host, $header, TableNode $table)
	{

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $host);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [ $header ]);
		curl_setopt($ch, CURLOPT_NOBODY, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		$headers = curl_exec($ch);

		curl_close($ch);

		$rows = $table->getHash();

		foreach ($rows as $row) {
			if (strpos($headers, $row['header']) === false) {
				throw new Exception("Unable to find " . $row['header'] . "\nActual output is : " . $headers);
			}
		}
	}

	/**
	 * @Then Request on :host with resolve option :resolve should contain following headers:
	 */
	public function requestOnWithResolveOptionShouldContainFollowingHeaders($host, $resolve, TableNode $table)
	{

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $host);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_NOBODY, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RESOLVE, [ $resolve ]);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		$headers = curl_exec($ch);
		curl_close($ch);

		$rows = $table->getHash();

		foreach ($rows as $row) {
			if (strpos($headers, $row['header']) === false) {
				throw new Exception("Unable to find " . $row['header'] . "\nActual output is : " . $headers);
			}
		}
	}

	/**
	 * @AfterScenario
	 */
	public function cleanupScenario(AfterScenarioScope $scope)
	{
		$this->commands = [];
		chdir($this->ee_path);
	}

	/**
	 * @Then There should be :expected_running_containers containers with labels
	 */
	public function thereShouldBeContainersWithLabel($expected_running_containers, PyStringNode $pyStringNode)
	{
		$labels = $pyStringNode->getStrings();
		$label_string = implode(' -f label=',$labels);

		$result = EE::launch( "docker ps -qf label=$label_string | wc -l", false, true );
		$running_containers = (int) trim($result->stdout);

		if ( (int) $expected_running_containers !== $running_containers ) {
			throw new Exception("Expected $expected_running_containers running containers. Found: $running_containers");
		}
	}

	/**
	 * @AfterFeature
	 */
	public static function cleanup(AfterFeatureScope $scope)
	{
		// Only phars built by "ee phar is generated", never a build of the checkout's owner.
		if ( self::$pharGenerated ) {
			foreach ( [ 'ee.phar', 'ee-old.phar' ] as $phar ) {
				if ( file_exists( $phar ) ) {
					unlink( $phar );
				}
			}
		}
	}

	/*
	 * ---------------------------------------------------------------------
	 * Shared steps (added for config-command). Generic enough to be reused.
	 *
	 * Placeholders usable in commands and paths: {CONFIG_PATH}, {CONFIG_DIR},
	 * {REAL_CONFIG_PATH}, {EE_LOG}, {RUN_ID}, {FAILING_DOCKER_DIR}.
	 * ---------------------------------------------------------------------
	 */

	/** @var string|null EE_CONFIG_PATH exported for this scenario. */
	private $tempConfigPath = null;

	/** @var string|null Per-scenario scratch dir (holds the temp config). */
	private $scratchDir = null;

	/** @var array Remembered file sizes, keyed by path. */
	private $rememberedSizes = [];

	/** @var array Extra placeholder values set by steps. */
	private $variables = [];

	/** @var array Environment variables changed by this scenario => previous value (false = was unset). */
	private $savedEnv = [];

	/** @var resource|null Lock held for the whole run, so two runs never snapshot each other's changes. */
	private static $runLock = null;

	/** @var bool Whether "ee phar is generated" ran, so the cleanup may remove ee.phar and ee-old.phar. */
	private static $pharGenerated = false;

	/**
	 * On-disk copy of the real config, written before every scenario and removed once the file is verified intact.
	 * It survives kill -9, so an interrupted run can be recovered (see recoverInterruptedRun()).
	 */
	const REAL_CONFIG_BACKUP = '/var/tmp/bh-config-command-real-config.backup';

	/** Key the @destructive scenarios write to the real config; marks leftovers of an interrupted run. */
	const REAL_CONFIG_MARKER = 'bh-config-command-key:';

	/** @var array|null State of the real config at scenario start; null outside a scenario. Static so the shutdown handler sees it. */
	private static $realConfigState = null;

	/** @var bool */
	private static $shutdownHandlerRegistered = false;

	private static function realConfigPath() {
		return EE_ROOT_DIR . '/config/config.yml';
	}

	/**
	 * Content, mode, owner and symlink target of the real config.
	 */
	private static function captureRealConfig() {
		$path = self::realConfigPath();
		clearstatcache();
		$state = [
			'link'   => is_link( $path ) ? readlink( $path ) : null,
			'exists' => is_file( $path ),
		];
		if ( null !== $state['link'] ) {
			$target = '/' === substr( $state['link'], 0, 1 ) ? $state['link'] : dirname( $path ) . '/' . $state['link'];
			// The restore rewrites the first link's target, which would replace a further link with a file.
			if ( is_link( $target ) ) {
				throw new Exception( "$path is a chain of symlinks; only a single symlink to the real file is supported." );
			}
		}
		if ( $state['exists'] ) {
			$content = file_get_contents( $path );
			$stat    = stat( $path );
			if ( false === $content || false === $stat ) {
				throw new Exception( "Unable to read $path" );
			}
			$state['content'] = $content;
			$state['mode']    = $stat['mode'] & 07777;
			$state['uid']     = $stat['uid'];
			$state['gid']     = $stat['gid'];
		}

		return $state;
	}

	/**
	 * Puts the real config back as captured: same bytes (written atomically), mode, owner and symlink.
	 */
	private static function restoreRealConfig( array $state ) {
		$path = self::realConfigPath();
		clearstatcache();

		if ( null !== $state['link'] ) {
			if ( ! is_link( $path ) || readlink( $path ) !== $state['link'] ) {
				if ( ( file_exists( $path ) || is_link( $path ) ) && ! unlink( $path ) ) {
					throw new Exception( "Unable to remove $path to restore its symlink" );
				}
				if ( ! symlink( $state['link'], $path ) ) {
					throw new Exception( "Unable to restore the symlink $path -> {$state['link']}" );
				}
			}
			$dest = '/' === substr( $state['link'], 0, 1 ) ? $state['link'] : dirname( $path ) . '/' . $state['link'];
		} else {
			if ( is_link( $path ) && ! unlink( $path ) ) {
				throw new Exception( "Unable to remove the symlink $path" );
			}
			$dest = $path;
		}

		if ( ! $state['exists'] ) {
			// For a dangling symlink $dest is its target, which a scenario may have created.
			if ( is_file( $dest ) && ! is_link( $dest ) && ! unlink( $dest ) ) {
				throw new Exception( "Unable to remove $dest, which did not exist before the scenario" );
			}
		} else {
			// Temp file + rename, so concurrent ee runs never read a half-written config.
			$tmp = $dest . '.bh-restore-' . getmypid();
			// Private from the first byte: the config can hold secrets.
			$old = umask( 077 );
			$written = file_put_contents( $tmp, $state['content'] );
			umask( $old );
			if ( strlen( $state['content'] ) !== $written
				|| ! chown( $tmp, $state['uid'] )
				|| ! chgrp( $tmp, $state['gid'] )
				|| ! chmod( $tmp, $state['mode'] )
				|| ! rename( $tmp, $dest ) ) {
				@unlink( $tmp );
				throw new Exception( "Unable to restore $dest" );
			}
		}

		if ( ! self::sameState( self::captureRealConfig(), $state ) ) {
			throw new Exception( "Restoring $path did not reproduce the original file; the original is saved in " . self::REAL_CONFIG_BACKUP );
		}
	}

	private static function writeRealConfigBackup( array $state ) {
		$tmp = self::REAL_CONFIG_BACKUP . '.tmp';
		// /var/tmp is shared: never reuse a file (or symlink) someone else put at the temp path.
		if ( ( file_exists( $tmp ) || is_link( $tmp ) ) && ! unlink( $tmp ) ) {
			throw new Exception( "Unable to remove the stale $tmp" );
		}
		$old = umask( 077 );
		// The config can hold secrets (cloudflare-api-key), hence 0600.
		$meta = $state;
		unset( $meta['content'] );
		$json = json_encode( [ 'path' => self::realConfigPath(), 'state' => $meta, 'content_b64' => isset( $state['content'] ) ? base64_encode( $state['content'] ) : null ] );
		$fh   = fopen( $tmp, 'x' );
		umask( $old );
		// A short write (disk full) must not publish a truncated backup.
		$ok = false;
		if ( $fh ) {
			$ok = false !== $json && strlen( $json ) === fwrite( $fh, $json );
			$ok = fclose( $fh ) && $ok;
		}
		if ( ! $ok && file_exists( $tmp ) ) {
			unlink( $tmp );
		}
		if ( ! $ok || ! chmod( $tmp, 0600 ) || ! rename( $tmp, self::REAL_CONFIG_BACKUP ) ) {
			throw new Exception( 'Unable to write ' . self::REAL_CONFIG_BACKUP . '; refusing to run without a backup of the real config.' );
		}
	}

	/**
	 * A leftover in shared /var/tmp is only trusted when it's a regular file of ours that nobody else can write.
	 */
	private static function assertTrustedFile( $path ) {
		clearstatcache();
		$stat = lstat( $path );
		if ( is_link( $path ) || ! is_file( $path ) || false === $stat || posix_geteuid() !== $stat['uid'] || ( $stat['mode'] & 022 ) ) {
			throw new Exception( "$path is not a regular file owned by this user and writable only by it; refusing to use it. Inspect it and delete it by hand." );
		}
	}

	private static function readRealConfigBackup() {
		self::assertTrustedFile( self::REAL_CONFIG_BACKUP );
		$data = json_decode( (string) file_get_contents( self::REAL_CONFIG_BACKUP ), true );
		if ( ! is_array( $data ) || ! isset( $data['state'] ) || self::realConfigPath() !== $data['path'] ) {
			throw new Exception( self::REAL_CONFIG_BACKUP . ' is unreadable or belongs to another config path; inspect it and delete it by hand.' );
		}
		$state = $data['state'];
		if ( $state['exists'] ) {
			$state['content'] = base64_decode( $data['content_b64'] );
		}

		return $state;
	}

	private static function sameState( array $a, array $b ) {
		ksort( $a );
		ksort( $b );

		return $a === $b;
	}

	private function replaceVariables( $string ) {
		$vars = array_merge(
			[
				'{REAL_CONFIG_PATH}' => self::realConfigPath(),
				'{EE_LOG}'           => EE_ROOT_DIR . '/logs/ee.log',
				'{RUN_ID}'           => $this->runId(),
			],
			$this->variables
		);
		if ( $this->tempConfigPath ) {
			$vars['{CONFIG_PATH}'] = $this->tempConfigPath;
			$vars['{CONFIG_DIR}']  = dirname( $this->tempConfigPath );
		}

		$string = strtr( $string, $vars );
		// A leftover placeholder would silently become a relative path (or the real config) in commands.
		if ( preg_match( '/\{[A-Z][A-Z_]*\}/', $string, $m ) ) {
			throw new Exception( "Placeholder {$m[0]} is not defined at this point of the scenario." );
		}

		return $string;
	}

	private function runId() {
		static $id = null;
		if ( null === $id ) {
			$id = getmypid() . '-' . substr( md5( uniqid( '', true ) ), 0, 8 );
		}

		return $id;
	}

	private function scratchDir() {
		if ( ! $this->scratchDir ) {
			$this->scratchDir = sys_get_temp_dir() . '/' . self::SITE_PREFIX . $this->runId() . '-' . substr( md5( uniqid( '', true ) ), 0, 6 );
			if ( ! mkdir( $this->scratchDir, 0700, true ) ) {
				throw new Exception( "Unable to create scratch dir {$this->scratchDir}" );
			}
		}

		return $this->scratchDir;
	}

	private function useTempConfig( $path ) {
		$this->tempConfigPath = $path;
		putenv( 'EE_CONFIG_PATH=' . $path );
		$_ENV['EE_CONFIG_PATH'] = $path;
	}

	private function lastCommand() {
		if ( empty( $this->commands ) ) {
			throw new Exception( 'No command has been run in this scenario.' );
		}

		return end( $this->commands );
	}

	private static function stripAnsi( $text ) {
		return preg_replace( '/\e\[[0-9;]*[A-Za-z]/', '', $text );
	}

	private function lastOutput( $stream ) {
		$result = $this->lastCommand();

		return self::stripAnsi( 'STDOUT' === $stream ? $result->stdout : $result->stderr );
	}

	private function describeLastCommand() {
		$result = $this->lastCommand();
		// Without a temporary config the command read the real one, whose values can be credentials.
		if ( null === $this->tempConfigPath ) {
			return sprintf( "Command: %s\nExit code: %d\n(output omitted: the command used the real config)", $result->command, $result->return_code );
		}

		return sprintf(
			"Command: %s\nExit code: %d\nSTDOUT:\n%s\nSTDERR:\n%s",
			$result->command,
			$result->return_code,
			self::stripAnsi( $result->stdout ),
			self::stripAnsi( $result->stderr )
		);
	}

	private static function loadYamlFile( $path ) {
		if ( ! is_file( $path ) ) {
			throw new Exception( "YAML file $path does not exist." );
		}

		// Same parser EE uses, so assertions see what EE sees.
		return \Mustangostang\Spyc::YAMLLoad( $path );
	}

	private static function normalizeForCompare( $value ) {
		if ( is_array( $value ) ) {
			$is_list = array_keys( $value ) === range( 0, count( $value ) - 1 );
			if ( ! $is_list ) {
				ksort( $value );
			}
			foreach ( $value as $k => $v ) {
				$value[ $k ] = self::normalizeForCompare( $v );
			}
		}

		return $value;
	}

	/**
	 * Decoded JSON (objects as stdClass) with object keys sorted; objects are wrapped so they never equal a list.
	 */
	private static function normalizeJson( $value ) {
		if ( $value instanceof \stdClass ) {
			$value = (array) $value;
			ksort( $value );

			return [ '{}' => array_map( [ __CLASS__, 'normalizeJson' ], $value ) ];
		}

		return is_array( $value ) ? array_map( [ __CLASS__, 'normalizeJson' ], $value ) : $value;
	}

	private function setEnv( $name, $value ) {
		if ( ! array_key_exists( $name, $this->savedEnv ) ) {
			$this->savedEnv[ $name ] = getenv( $name );
		}
		putenv( false === $value ? $name : "$name=$value" );
	}

	private function restoreEnv() {
		foreach ( $this->savedEnv as $name => $value ) {
			putenv( false === $value ? $name : "$name=$value" );
		}
		$this->savedEnv = [];
	}

	/** Changed top-level keys only: failure messages must not print the real config (it can hold secrets). */
	private static function describeRealConfigChange( array $before, array $after ) {
		$load = function ( array $state ) {
			return $state['exists'] ? (array) \Mustangostang\Spyc::YAMLLoadString( $state['content'] ) : [];
		};
		$a    = $load( $before );
		$b    = $load( $after );
		$keys = [];
		foreach ( array_unique( array_merge( array_keys( $a ), array_keys( $b ) ) ) as $key ) {
			if ( ! array_key_exists( $key, $a ) || ! array_key_exists( $key, $b ) || $a[ $key ] !== $b[ $key ] ) {
				$keys[] = $key;
			}
		}
		$meta = [];
		foreach ( [ 'exists', 'link', 'mode', 'uid', 'gid' ] as $field ) {
			$x = isset( $before[ $field ] ) ? $before[ $field ] : null;
			$y = isset( $after[ $field ] ) ? $after[ $field ] : null;
			if ( $x !== $y ) {
				$meta[] = "$field: " . var_export( $x, true ) . ' -> ' . var_export( $y, true );
			}
		}

		return 'Changed keys: ' . ( $keys ? implode( ', ', $keys ) : '(none; formatting or comments only)' ) . ( $meta ? '; ' . implode( '; ', $meta ) : '' );
	}

	/**
	 * Recovers the real config after a run that died mid-scenario (kill -9, power loss).
	 *
	 * @BeforeSuite
	 */
	public static function recoverInterruptedRun() {
		$lock = self::REAL_CONFIG_BACKUP . '.lock';
		if ( file_exists( $lock ) || is_link( $lock ) ) {
			self::assertTrustedFile( $lock );
		}
		$old = umask( 077 );
		self::$runLock = fopen( $lock, 'c' );
		umask( $old );
		if ( ! self::$runLock || ! flock( self::$runLock, LOCK_EX | LOCK_NB ) ) {
			throw new Exception( 'Another run of this suite holds ' . self::REAL_CONFIG_BACKUP . '.lock; runs must not overlap.' );
		}
		if ( ! file_exists( self::REAL_CONFIG_BACKUP ) ) {
			return;
		}
		$state   = self::readRealConfigBackup();
		$current = self::captureRealConfig();
		if ( self::sameState( $state, $current ) ) {
			unlink( self::REAL_CONFIG_BACKUP );
			return;
		}
		// Only undo what is recognisably ours; anything else may be a legitimate change made since.
		if ( isset( $current['content'] ) && false !== strpos( $current['content'], self::REAL_CONFIG_MARKER ) ) {
			self::restoreRealConfig( $state );
			unlink( self::REAL_CONFIG_BACKUP );
			fwrite( STDERR, 'Restored ' . self::realConfigPath() . ' from ' . self::REAL_CONFIG_BACKUP . ", left by an interrupted run.\n" );
			return;
		}
		throw new Exception( self::REAL_CONFIG_BACKUP . ' was left by an interrupted run and ' . self::realConfigPath() . ' differs from it (' . self::describeRealConfigChange( $state, $current ) . '). Restore the config from the backup by hand if the change is ours, then delete the backup.' );
	}

	/**
	 * @BeforeScenario
	 */
	public function snapshotRealConfig( BeforeScenarioScope $scope ) {
		// An EE_CONFIG_PATH inherited from the caller's shell must not leak into scenarios.
		putenv( 'EE_CONFIG_PATH' );
		unset( $_ENV['EE_CONFIG_PATH'] );
		// Help output goes through $PAGER (less -r by default), and host php.ini decides where PHP notices go.
		$this->setEnv( 'PAGER', 'cat' );
		if ( false === getenv( 'EE_PHP_ARGS' ) ) {
			$this->setEnv( 'EE_PHP_ARGS', '-d display_errors=0 -d log_errors=1 -d error_log= -d error_reporting=' . ( E_ALL & ~E_DEPRECATED ) );
		}

		if ( file_exists( self::REAL_CONFIG_BACKUP ) ) {
			throw new Exception( self::REAL_CONFIG_BACKUP . ' still exists: restoring ' . self::realConfigPath() . ' failed earlier in this run. Fix it by hand before running more scenarios.' );
		}
		$state = self::captureRealConfig();
		self::writeRealConfigBackup( $state );
		self::$realConfigState = $state;
		self::registerShutdownHandler();
	}

	private static function registerShutdownHandler() {
		if ( self::$shutdownHandlerRegistered ) {
			return;
		}
		self::$shutdownHandlerRegistered = true;
		// Behat turns SIGINT into exit() without AfterScenario, and an Error in a step is fatal; both still run shutdown functions.
		register_shutdown_function( [ __CLASS__, 'restoreRealConfigOnShutdown' ] );
		if ( function_exists( 'pcntl_signal' ) && function_exists( 'pcntl_async_signals' ) ) {
			pcntl_async_signals( true );
			foreach ( [ 'SIGTERM', 'SIGHUP' ] as $signal ) {
				if ( defined( $signal ) ) {
					pcntl_signal(
						constant( $signal ),
						function ( $signo ) {
							// Stop the whole command tree (sh -> bin/ee -> php) first, so nothing writes the config after the restore.
							$tree = self::descendants( getmypid() );
							if ( $tree && function_exists( 'posix_kill' ) ) {
								foreach ( $tree as $pid ) {
									posix_kill( $pid, $signo );
								}
								$deadline = microtime( true ) + 10;
								while ( true ) {
									while ( pcntl_waitpid( -1, $status, WNOHANG ) > 0 ) {
										continue;
									}
									$alive = array_filter( $tree, [ __CLASS__, 'isAlive' ] );
									if ( ! $alive || microtime( true ) >= $deadline ) {
										break;
									}
									usleep( 100000 );
								}
								foreach ( $alive as $pid ) {
									posix_kill( $pid, SIGKILL );
								}
								while ( pcntl_waitpid( -1, $status ) > 0 ) {
									continue;
								}
							}
							exit( 128 + $signo );
						}
					);
				}
			}
		}
	}

	/**
	 * Every descendant process of $pid, parents before children.
	 */
	private static function descendants( $pid ) {
		$tree = [];
		foreach ( glob( "/proc/$pid/task/*/children" ) ?: [] as $file ) {
			foreach ( preg_split( '/\s+/', trim( (string) @file_get_contents( $file ) ) ) as $child ) {
				if ( '' !== $child ) {
					$tree[] = (int) $child;
					$tree   = array_merge( $tree, self::descendants( (int) $child ) );
				}
			}
		}

		return $tree;
	}

	private static function isAlive( $pid ) {
		$stat = @file_get_contents( "/proc/$pid/stat" );

		return false !== $stat && ! preg_match( '/\) Z /', $stat );
	}

	public static function restoreRealConfigOnShutdown() {
		$state = self::$realConfigState;
		if ( null === $state ) {
			return;
		}
		try {
			if ( ! self::sameState( $state, self::captureRealConfig() ) ) {
				self::restoreRealConfig( $state );
				fwrite( STDERR, 'Restored ' . self::realConfigPath() . " after an interrupted scenario.\n" );
			}
			self::$realConfigState = null;
			unlink( self::REAL_CONFIG_BACKUP );
		} catch ( \Throwable $e ) {
			fwrite( STDERR, 'Could not restore ' . self::realConfigPath() . ': ' . $e->getMessage() . "\nThe original is saved in " . self::REAL_CONFIG_BACKUP . "\n" );
		}
	}

	/**
	 * Restores the real config and fails any non-@destructive scenario that changed it.
	 *
	 * @AfterScenario
	 */
	public function restoreConfigState( AfterScenarioScope $scope ) {
		// The real config first: nothing below may prevent its restore.
		$state = self::$realConfigState;
		if ( null !== $state ) {
			$current = self::captureRealConfig();
			$changed = ! self::sameState( $state, $current );
			if ( $changed ) {
				// Throws (keeping the backup and the state for the shutdown handler) if the restore can't be verified.
				self::restoreRealConfig( $state );
			}
			self::$realConfigState = null;
			if ( ! unlink( self::REAL_CONFIG_BACKUP ) ) {
				throw new Exception( 'Unable to remove ' . self::REAL_CONFIG_BACKUP );
			}
		}

		putenv( 'EE_CONFIG_PATH' );
		unset( $_ENV['EE_CONFIG_PATH'] );
		$this->restoreEnv();
		$this->tempConfigPath  = null;
		$this->variables       = [];
		$this->rememberedSizes = [];

		if ( $this->scratchDir && 0 === strpos( $this->scratchDir, sys_get_temp_dir() . '/' . self::SITE_PREFIX ) ) {
			exec( 'rm -rf ' . escapeshellarg( $this->scratchDir ) );
		}
		$this->scratchDir = null;

		if ( null !== $state && $changed ) {
			$tags = array_merge( $scope->getFeature()->getTags(), $scope->getScenario()->getTags() );
			if ( ! in_array( 'destructive', $tags, true ) ) {
				throw new Exception( 'Scenario modified the real ' . self::realConfigPath() . ' without the @destructive tag; it has been restored (if another process changed it meanwhile, re-apply that change). ' . self::describeRealConfigChange( $state, $current ) );
			}
		}
	}

	/**
	 * @Given a temporary ee config file with:
	 */
	public function aTemporaryEeConfigFileWith( PyStringNode $content ) {
		$path = $this->scratchDir() . '/config.yml';
		file_put_contents( $path, $this->replaceVariables( $content->getRaw() ) . "\n" );
		$this->useTempConfig( $path );
	}

	/**
	 * @Given an empty temporary ee config file
	 */
	public function anEmptyTemporaryEeConfigFile() {
		$path = $this->scratchDir() . '/config.yml';
		file_put_contents( $path, '' );
		$this->useTempConfig( $path );
	}

	/**
	 * @Given a temporary ee config path that does not exist yet
	 */
	public function aTemporaryEeConfigPathThatDoesNotExistYet() {
		$this->useTempConfig( $this->scratchDir() . '/missing/nested/config.yml' );
	}

	/**
	 * @Given the temporary ee config file is a symlink
	 */
	public function theTemporaryEeConfigFileIsASymlink() {
		$path   = $this->requireTempConfig();
		$target = dirname( $path ) . '/real-target.yml';
		rename( $path, $target );
		symlink( $target, $path );
		$this->variables['{CONFIG_TARGET}'] = $target;
	}

	/**
	 * @Then the temporary ee config file should be a symlink
	 */
	public function theTemporaryEeConfigFileShouldBeASymlink() {
		$path = $this->requireTempConfig();
		if ( ! is_link( $path ) ) {
			throw new Exception( "$path is no longer a symlink; it was replaced by a regular file with:\n" . file_get_contents( $path ) );
		}
	}

	private function requireTempConfig() {
		if ( ! $this->tempConfigPath ) {
			throw new Exception( 'No temporary ee config file is set up for this scenario.' );
		}

		return $this->tempConfigPath;
	}

	/**
	 * @Then the real ee config file should be unchanged
	 */
	public function theRealEeConfigFileShouldBeUnchanged() {
		$current = self::captureRealConfig();
		if ( ! self::sameState( self::requireRealConfigState(), $current ) ) {
			throw new Exception( 'The real ' . self::realConfigPath() . ' changed. ' . self::describeRealConfigChange( self::requireRealConfigState(), $current ) );
		}
	}

	private static function requireRealConfigState() {
		if ( null === self::$realConfigState ) {
			throw new Exception( 'The real ee config was not snapshotted for this scenario.' );
		}

		return self::$realConfigState;
	}

	/**
	 * The global-config scenarios compare against the real file, so it must exist (any ee command on an initialised install creates it).
	 *
	 * @Given the real ee config file exists
	 */
	public function theRealEeConfigFileExists() {
		if ( ! self::requireRealConfigState()['exists'] ) {
			throw new Exception( self::realConfigPath() . ' does not exist; these scenarios need an initialised EasyEngine.' );
		}
	}

	/**
	 * @Then the real ee config file should have the same values as before
	 */
	public function theRealEeConfigFileShouldHaveTheSameValuesAsBefore() {
		$state  = self::requireRealConfigState();
		$before = $state['exists'] ? \Mustangostang\Spyc::YAMLLoadString( $state['content'] ) : [];
		$now    = self::loadYamlFile( self::realConfigPath() );
		if ( $before !== $now ) {
			throw new Exception( 'Real config values differ. ' . self::describeRealConfigChange( $state, self::captureRealConfig() ) );
		}
	}

	/**
	 * Lets a command use a value without it appearing in the command line (which the runner logs).
	 *
	 * @Given the environment variable :name is set to :value
	 */
	public function theEnvironmentVariableIsSetTo( $name, $value ) {
		if ( 'EE_CONFIG_PATH' === $name ) {
			throw new Exception( 'Use a temporary ee config step to set EE_CONFIG_PATH.' );
		}
		$this->setEnv( $name, $this->replaceVariables( $value ) );
	}

	/**
	 * @Then the real ee config file should have the same mode as before
	 */
	public function theRealEeConfigFileShouldHaveTheSameModeAsBefore() {
		clearstatcache();
		$path   = self::realConfigPath();
		$state    = self::requireRealConfigState();
		$expected = $state['exists'] ? $state['mode'] : null;
		$actual   = is_file( $path ) ? ( fileperms( $path ) & 07777 ) : null;
		if ( $actual !== $expected ) {
			throw new Exception( sprintf( 'Mode of %s changed from %s to %s', $path, null === $expected ? 'missing' : sprintf( '%04o', $expected ), null === $actual ? 'missing' : sprintf( '%04o', $actual ) ) );
		}
	}

	/**
	 * @Given a directory in PATH where docker and docker-compose always fail
	 */
	public function aDirectoryInPathWhereDockerAndDockerComposeAlwaysFail() {
		$dir = $this->scratchDir() . '/failing-docker-bin';
		mkdir( $dir, 0700, true );
		foreach ( [ 'docker', 'docker-compose' ] as $bin ) {
			// Each call is recorded, so a scenario can prove the shims were never run.
			file_put_contents( "$dir/$bin", "#!/bin/sh\necho \"$bin \$*\" >> '$dir/calls'\necho 'fake $bin: unavailable' >&2\nexit 1\n" );
			chmod( "$dir/$bin", 0755 );
		}
		$this->variables['{FAILING_DOCKER_DIR}'] = $dir;
	}

	/**
	 * @Given the file :path has mode :mode
	 */
	public function theFileHasMode( $path, $mode ) {
		$path = $this->replaceVariables( $path );
		if ( ! chmod( $path, octdec( $mode ) ) ) {
			throw new Exception( "Unable to chmod $path to $mode" );
		}
	}

	/**
	 * @Then the file :path should have mode :mode
	 */
	public function theFileShouldHaveMode( $path, $mode ) {
		$path = $this->replaceVariables( $path );
		clearstatcache();
		$actual = sprintf( '%04o', fileperms( $path ) & 0777 );
		if ( sprintf( '%04o', octdec( $mode ) ) !== $actual ) {
			throw new Exception( "Expected $path to have mode $mode, got $actual" );
		}
	}

	/**
	 * @Then the file :path should exist
	 */
	public function theFileShouldExist( $path ) {
		$path = $this->replaceVariables( $path );
		if ( ! file_exists( $path ) ) {
			throw new Exception( "$path does not exist." );
		}
	}

	/**
	 * @Then the file :path should not exist
	 */
	public function theFileShouldNotExist( $path ) {
		$path = $this->replaceVariables( $path );
		if ( file_exists( $path ) ) {
			throw new Exception( "$path exists, with:\n" . ( is_file( $path ) ? file_get_contents( $path ) : '(not a regular file)' ) );
		}
	}

	/**
	 * Exact match, ignoring trailing newlines.
	 *
	 * @Then the file :path should contain exactly:
	 */
	public function theFileShouldContainExactly( $path, PyStringNode $expected ) {
		$path = $this->replaceVariables( $path );
		if ( ! is_file( $path ) ) {
			throw new Exception( "$path does not exist." );
		}
		$actual   = file_get_contents( $path );
		$expected = $this->replaceVariables( $expected->getRaw() );
		if ( rtrim( $expected, "\n" ) !== rtrim( $actual, "\n" ) ) {
			throw new Exception( "Content of $path differs.\nExpected:\n" . $expected . "\nActual:\n" . $actual );
		}
	}

	/**
	 * @Then the file :path should contain:
	 */
	public function theFileShouldContain( $path, PyStringNode $expected ) {
		$path = $this->replaceVariables( $path );
		if ( ! is_file( $path ) ) {
			throw new Exception( "$path does not exist." );
		}
		$actual = file_get_contents( $path );
		if ( false === strpos( $actual, $this->replaceVariables( $expected->getRaw() ) ) ) {
			throw new Exception( "$path does not contain:\n" . $expected->getRaw() . "\nActual:\n" . $actual );
		}
	}

	/**
	 * @Then the file :path should not contain:
	 */
	public function theFileShouldNotContain( $path, PyStringNode $unexpected ) {
		$path = $this->replaceVariables( $path );
		if ( ! is_file( $path ) ) {
			throw new Exception( "$path does not exist." );
		}
		$actual = file_get_contents( $path );
		if ( false !== strpos( $actual, $this->replaceVariables( $unexpected->getRaw() ) ) ) {
			throw new Exception( "$path unexpectedly contains:\n" . $unexpected->getRaw() . "\nActual:\n" . $actual );
		}
	}

	/**
	 * @Given I remember the size of the file :path
	 */
	public function iRememberTheSizeOfTheFile( $path ) {
		$path = $this->replaceVariables( $path );
		clearstatcache();
		$this->rememberedSizes[ $path ] = is_file( $path ) ? filesize( $path ) : 0;
	}

	/**
	 * @Then the text appended to the file :path should not contain :text
	 */
	public function theTextAppendedToTheFileShouldNotContain( $path, $text ) {
		$path = $this->replaceVariables( $path );
		$text = $this->replaceVariables( $text );
		if ( ! isset( $this->rememberedSizes[ $path ] ) ) {
			throw new Exception( "Size of $path was not remembered earlier in the scenario." );
		}
		clearstatcache();
		if ( ! is_file( $path ) ) {
			throw new Exception( "$path does not exist, so nothing can be said about what was logged." );
		}
		$content = file_get_contents( $path );
		// A rotated (shrunk) file is checked as a whole.
		$appended = strlen( $content ) >= $this->rememberedSizes[ $path ] ? substr( $content, $this->rememberedSizes[ $path ] ) : $content;
		if ( '' === $appended ) {
			throw new Exception( "Nothing was appended to $path, so the command didn't log there and the check would be vacuous." );
		}
		$lines    = array_filter(
			explode( "\n", $appended ),
			function ( $line ) use ( $text ) {
				return false !== strpos( $line, $text );
			}
		);
		if ( $lines ) {
			throw new Exception( "$path gained lines containing '$text':\n" . implode( "\n", $lines ) );
		}
	}

	/**
	 * @Then the YAML file :path should have key :key with value :value
	 */
	public function theYamlFileShouldHaveKeyWithValue( $path, $key, $value ) {
		$path   = $this->replaceVariables( $path );
		$value  = $this->replaceVariables( $value );
		$config = self::loadYamlFile( $path );
		if ( ! array_key_exists( $key, $config ) ) {
			throw new Exception( "Key '$key' not found in $path. Keys: " . implode( ', ', array_keys( $config ) ) );
		}
		if ( $config[ $key ] !== $value ) {
			throw new Exception( "Key '$key' in $path is " . var_export( $config[ $key ], true ) . ', expected ' . var_export( $value, true ) );
		}
	}

	/**
	 * @Then the YAML file :path should have key :key
	 */
	public function theYamlFileShouldHaveKey( $path, $key ) {
		$path   = $this->replaceVariables( $path );
		$config = self::loadYamlFile( $path );
		if ( ! array_key_exists( $key, $config ) ) {
			throw new Exception( "Key '$key' not found in $path. Keys: " . implode( ', ', array_keys( $config ) ) );
		}
	}

	/**
	 * @Then the YAML file :path should not have key :key
	 */
	public function theYamlFileShouldNotHaveKey( $path, $key ) {
		$path   = $this->replaceVariables( $path );
		$config = self::loadYamlFile( $path );
		if ( array_key_exists( $key, $config ) ) {
			throw new Exception( "Key '$key' unexpectedly present in $path with value " . var_export( $config[ $key ], true ) );
		}
	}

	/**
	 * Values in the table are JSON literals, so types are checked too ("3" vs 3).
	 *
	 * @Then the YAML file :path should contain exactly these entries:
	 */
	public function theYamlFileShouldContainExactlyTheseEntries( $path, TableNode $table ) {
		$path     = $this->replaceVariables( $path );
		$config   = self::loadYamlFile( $path );
		$expected = [];
		foreach ( $table->getHash() as $row ) {
			$decoded = json_decode( $row['value'], true );
			if ( null === $decoded && 'null' !== trim( $row['value'] ) ) {
				throw new Exception( "Invalid JSON literal in table: {$row['value']}" );
			}
			$expected[ $row['key'] ] = $decoded;
		}
		if ( $expected !== $config ) {
			throw new Exception( "Entries of $path differ (order and types matter).\nExpected: " . json_encode( $expected ) . "\nActual:   " . json_encode( $config ) );
		}
	}

	/**
	 * @Then /^(STDOUT|STDERR) should be:$/
	 */
	public function outputShouldBe( $stream, PyStringNode $expected ) {
		$expected = $this->replaceVariables( $expected->getRaw() );
		if ( trim( $expected ) !== trim( $this->lastOutput( $stream ) ) ) {
			throw new Exception( "$stream differs from:\n$expected\n\n" . $this->describeLastCommand() );
		}
	}

	/**
	 * @Then /^(STDOUT|STDERR) should (contain|not contain):$/
	 */
	public function outputShouldContain( $stream, $action, PyStringNode $expected ) {
		$expected = $this->replaceVariables( $expected->getRaw() );
		$found    = false !== strpos( $this->lastOutput( $stream ), $expected );
		if ( ( 'contain' === $action ) !== $found ) {
			throw new Exception( "$stream should $action:\n$expected\n\n" . $this->describeLastCommand() );
		}
	}

	/**
	 * @Then /^(STDOUT|STDERR) should be empty$/
	 */
	public function outputShouldBeEmpty( $stream ) {
		if ( '' !== trim( $this->lastOutput( $stream ) ) ) {
			throw new Exception( "$stream is not empty.\n\n" . $this->describeLastCommand() );
		}
	}

	/**
	 * @Then /^the exit code should be (\d+)$/
	 */
	public function theExitCodeShouldBe( $code ) {
		if ( (int) $code !== $this->lastCommand()->return_code ) {
			throw new Exception( "Expected exit code $code.\n\n" . $this->describeLastCommand() );
		}
	}

	/**
	 * Strict structural comparison; object key order is ignored, list order is not.
	 *
	 * @Then STDOUT should be JSON equal to:
	 */
	public function stdoutShouldBeJsonEqualTo( PyStringNode $expected ) {
		$actual = json_decode( $this->lastOutput( 'STDOUT' ) );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			throw new Exception( 'STDOUT is not valid JSON: ' . json_last_error_msg() . "\n\n" . $this->describeLastCommand() );
		}
		$wanted = json_decode( $this->replaceVariables( $expected->getRaw() ) );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			throw new Exception( 'Expected value is not valid JSON: ' . json_last_error_msg() );
		}
		if ( self::normalizeJson( $wanted ) !== self::normalizeJson( $actual ) ) {
			throw new Exception( 'JSON differs from: ' . json_encode( $wanted ) . "\n\n" . $this->describeLastCommand() );
		}
	}

	/**
	 * @Then STDOUT should be YAML equal to:
	 */
	public function stdoutShouldBeYamlEqualTo( PyStringNode $expected ) {
		$actual = \Mustangostang\Spyc::YAMLLoadString( $this->lastOutput( 'STDOUT' ) );
		$wanted = \Mustangostang\Spyc::YAMLLoadString( $this->replaceVariables( $expected->getRaw() ) );
		if ( self::normalizeForCompare( $wanted ) !== self::normalizeForCompare( $actual ) ) {
			throw new Exception( 'YAML differs from: ' . json_encode( $wanted ) . "\nParsed actual: " . json_encode( $actual ) . "\n\n" . $this->describeLastCommand() );
		}
	}

	/**
	 * Accepts both the ASCII renderer (TTY) and the tab-separated one (pipe); rows must match exactly and in order.
	 *
	 * @Then STDOUT should be a table with rows:
	 */
	public function stdoutShouldBeATableWithRows( TableNode $expected ) {
		$rows = [];
		foreach ( explode( "\n", $this->lastOutput( 'STDOUT' ) ) as $line ) {
			$line = rtrim( $line, "\r" );
			if ( '' === trim( $line ) || preg_match( '/^\+[-+]+\+$/', trim( $line ) ) ) {
				continue;
			}
			if ( '|' === substr( $line, 0, 1 ) ) {
				$rows[] = array_map( 'trim', explode( '|', trim( trim( $line ), '|' ) ) );
			} else {
				$rows[] = explode( "\t", $line );
			}
		}
		$wanted = array_map(
			function ( $row ) {
				return array_map( [ $this, 'replaceVariables' ], $row );
			},
			$expected->getRows()
		);
		if ( $wanted !== $rows ) {
			throw new Exception( 'Table differs. Expected rows: ' . json_encode( $wanted ) . "\nParsed rows: " . json_encode( $rows ) . "\n\n" . $this->describeLastCommand() );
		}
	}

	/**
	 * @Then STDOUT should be CSV with rows:
	 */
	public function stdoutShouldBeCsvWithRows( TableNode $expected ) {
		$rows = [];
		foreach ( explode( "\n", rtrim( $this->lastOutput( 'STDOUT' ), "\n" ) ) as $line ) {
			$rows[] = str_getcsv( $line, ',', '"', '\\' );
		}
		$wanted = array_map(
			function ( $row ) {
				return array_map( [ $this, 'replaceVariables' ], $row );
			},
			$expected->getRows()
		);
		if ( $wanted !== $rows ) {
			throw new Exception( 'CSV differs. Expected rows: ' . json_encode( $wanted ) . "\nParsed rows: " . json_encode( $rows ) . "\n\n" . $this->describeLastCommand() );
		}
	}

	/**
	 * For `ee config list --format=json`-style output: [{"key":..,"value":..}] in file order.
	 *
	 * @Then STDOUT should be JSON listing the entries of the YAML file :path
	 */
	public function stdoutShouldBeJsonListingTheEntriesOfTheYamlFile( $path ) {
		$file     = $this->replaceVariables( $path );
		$config   = self::loadYamlFile( $file );
		$expected = [];
		foreach ( $config as $key => $value ) {
			$expected[] = [
				'key'   => $key,
				'value' => $value,
			];
		}
		$actual = json_decode( $this->lastOutput( 'STDOUT' ) );
		// Encoded the way ee encodes it, so maps become objects and lists stay lists.
		if ( self::normalizeJson( json_decode( json_encode( $expected ) ) ) !== self::normalizeJson( $actual ) ) {
			// The real config can hold credentials (cloudflare-api-key), so no values or output then.
			if ( false !== realpath( $file ) && realpath( $file ) === realpath( self::realConfigPath() ) ) {
				throw new Exception( 'STDOUT does not list the entries of the real config (values omitted).' );
			}
			throw new Exception( 'Expected: ' . json_encode( $expected ) . "\n\n" . $this->describeLastCommand() );
		}
	}
}
