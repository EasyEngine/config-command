@destructive
Feature: Default config location (/opt/easyengine/config/config.yml)
  These scenarios run without EE_CONFIG_PATH, so they read or write the real global config.
  The context snapshots the real file before every scenario and restores it byte-for-byte afterwards.

  Background:
    Given the real ee config file exists

  Scenario: Without EE_CONFIG_PATH, list reads the real global config
    When I run 'bin/ee config list --format=json'
    Then the exit code should be 0
    And STDOUT should be JSON listing the entries of the YAML file '{REAL_CONFIG_PATH}'

  Scenario: An empty EE_CONFIG_PATH falls back to the real global config
    When I run 'EE_CONFIG_PATH= bin/ee config list --format=json'
    Then the exit code should be 0
    And STDOUT should be JSON listing the entries of the YAML file '{REAL_CONFIG_PATH}'

  Scenario: Without EE_CONFIG_PATH, set, get and unset operate on the real global config
    When I run 'bin/ee config set bh-config-command-key bh-value'
    Then the exit code should be 0
    And the YAML file '{REAL_CONFIG_PATH}' should have key 'bh-config-command-key' with value 'bh-value'
    When I run 'bin/ee config get bh-config-command-key'
    Then the exit code should be 0
    And STDOUT should be:
      """
      bh-value
      """
    When I run 'bin/ee config unset bh-config-command-key'
    Then the exit code should be 0
    And the YAML file '{REAL_CONFIG_PATH}' should not have key 'bh-config-command-key'
    And the real ee config file should have the same values as before

  Scenario: Set and unset keep the mode of the real global config
    When I run 'bin/ee config set bh-config-command-key bh-value'
    Then the exit code should be 0
    And the real ee config file should have the same mode as before
    When I run 'bin/ee config unset bh-config-command-key'
    Then the exit code should be 0
    And the real ee config file should have the same mode as before
