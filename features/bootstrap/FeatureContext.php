<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;

class FeatureContext implements Context, SnippetAcceptingContext
{
    private $command_output;

    /**
     * @When I run "ee config set test_key test_value"
     */
    public function iRunSetTestKeyCommand()
    {
        $this->command_output = shell_exec('ee config set test_key test_value');
    }

    /**
     * @Then STDOUT should not return anything
     */
    public function stdoutShouldNotReturnAnything()
    {
        if (trim($this->command_output) !== '') {
            throw new Exception("Expected no output, but got '$this->command_output'");
        }
    }

    /**
     * @When I run "ee config get test_key"
     */
    public function iRunGetTestKeyCommand()
    {
        $this->command_output = shell_exec('ee config get test_key');
    }

    /**
     * @Then STDOUT should return "test_value"
     */
    public function stdoutShouldReturnTestValue()
    {
        if (trim($this->command_output) !== 'test_value') {
            throw new Exception("Expected 'test_value', but got '$this->command_output'");
        }
    }

    /**
     * @Then the configuration file should contain "test_key: test_value"
     */
    public function theConfigurationFileShouldContainTestKeyTestValue()
    {
        $config_file_path = '/opt/easyengine/config/config.yml';
        if (!file_exists($config_file_path)) {
            throw new Exception("Configuration file does not exist at $config_file_path");
        }

        $config_file_content = file_get_contents($config_file_path);
        if (strpos($config_file_content, 'test_key: test_value') === false) {
            throw new Exception("Configuration file does not contain 'test_key: test_value'");
        }
    }
}