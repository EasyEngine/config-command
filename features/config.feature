Feature: ee config command group, help and usage
  All scenarios use a throwaway EE_CONFIG_PATH; the real /opt/easyengine/config/config.yml is never touched.

  Background:
    Given a temporary ee config file with:
      """
      locale: en_US
      ee_installer_version: stable
      """

  Scenario: Running ee config without a subcommand prints the usage of every subcommand
    When I run 'bin/ee config'
    Then the exit code should be 0
    And STDOUT should be:
      """
      usage: ee config get <config-key>
         or: ee config list [--format=<format>]
         or: ee config set <key> <value>
         or: ee config unset <key>

      See 'ee help config <command>' for more information on a specific command.
      """
    And STDERR should be empty

  Scenario: ee help config lists all subcommands
    When I run 'bin/ee help config'
    Then the exit code should be 0
    And STDOUT should contain:
      """
      ee config <command>
      """
    And STDOUT should contain:
      """
      get
      """
    And STDOUT should contain:
      """
      list       Lists the config values.
      """
    And STDOUT should contain:
      """
      set        Set a config value
      """
    And STDOUT should contain:
      """
      unset      Unset a config value
      """

  Scenario Outline: ee help config <subcommand> shows its synopsis
    When I run 'bin/ee help config <subcommand>'
    Then the exit code should be 0
    And STDOUT should contain:
      """
      ee config <synopsis>
      """
    And STDERR should be empty

    Examples:
      | subcommand | synopsis                  |
      | get        | get <config-key>          |
      | set        | set <key> <value>         |
      | unset      | unset <key>               |
      | list       | list [--format=<format>]  |

  Scenario: ee help config list documents every supported format
    When I run 'bin/ee help config list'
    Then the exit code should be 0
    And STDOUT should contain:
      """
          default: table
          options:
            - table
            - csv
            - yaml
            - json
            - text
      """
    And STDOUT should not contain:
      """
      - count
      """

  Scenario: An unknown config subcommand is rejected with a suggestion
    When I run 'bin/ee config gett locale'
    Then the exit code should be 1
    And STDERR should contain:
      """
      Error: 'gett' is not a registered subcommand of 'config'. See 'ee help config' for available subcommands.
      """
    And STDERR should contain:
      """
      Did you mean 'get'?
      """
    And STDOUT should be empty

  Scenario: An unknown config subcommand without a close match gets no suggestion
    When I run 'bin/ee config remove locale'
    Then the exit code should be 1
    And STDERR should contain:
      """
      Error: 'remove' is not a registered subcommand of 'config'.
      """
    And STDERR should not contain:
      """
      Did you mean
      """

  Scenario: config commands skip the Docker requirement check
    Given a directory in PATH where docker and docker-compose always fail
    When I run 'PATH={FAILING_DOCKER_DIR}:$PATH bin/ee config get locale'
    Then the exit code should be 0
    And STDOUT should be:
      """
      en_US
      """
    And STDERR should be empty
    And the file '{FAILING_DOCKER_DIR}/calls' should not exist
