Feature: ee config list
  Lists every top-level key of the config file at EE_CONFIG_PATH (a throwaway file here).

  Background:
    Given a temporary ee config file with:
      """
      locale: en_US
      ee_installer_version: stable
      le-mail: admin@bh-config-command.test
      no-of-backups: "3"
      """

  Scenario: The default format is a table of key and value
    When I run 'bin/ee config list'
    Then the exit code should be 0
    And STDOUT should be a table with rows:
      | key                  | value                        |
      | locale               | en_US                        |
      | ee_installer_version | stable                       |
      | le-mail              | admin@bh-config-command.test |
      | no-of-backups        | 3                            |
    And STDERR should be empty

  Scenario: --format=table matches the default
    When I run 'bin/ee config list --format=table'
    Then the exit code should be 0
    And STDOUT should be a table with rows:
      | key                  | value                        |
      | locale               | en_US                        |
      | ee_installer_version | stable                       |
      | le-mail              | admin@bh-config-command.test |
      | no-of-backups        | 3                            |

  Scenario: --format=json keeps file order and value types
    When I run 'bin/ee config list --format=json'
    Then the exit code should be 0
    And STDOUT should be JSON equal to:
      """
      [
        {"key": "locale", "value": "en_US"},
        {"key": "ee_installer_version", "value": "stable"},
        {"key": "le-mail", "value": "admin@bh-config-command.test"},
        {"key": "no-of-backups", "value": "3"}
      ]
      """
    And STDOUT should be JSON listing the entries of the YAML file '{CONFIG_PATH}'
    And STDERR should be empty

  Scenario: --format=csv prints a header and one row per key
    When I run 'bin/ee config list --format=csv'
    Then the exit code should be 0
    And STDOUT should be CSV with rows:
      | key                  | value                        |
      | locale               | en_US                        |
      | ee_installer_version | stable                       |
      | le-mail              | admin@bh-config-command.test |
      | no-of-backups        | 3                            |
    And STDERR should be empty

  Scenario: --format=yaml prints a list of key/value maps
    When I run 'bin/ee config list --format=yaml'
    Then the exit code should be 0
    And STDOUT should be YAML equal to:
      """
      ---
      - key: locale
        value: en_US
      - key: ee_installer_version
        value: stable
      - key: le-mail
        value: admin@bh-config-command.test
      - key: no-of-backups
        value: "3"
      """

  Scenario: --format=text prints key: value lines
    When I run 'bin/ee config list --format=text'
    Then the exit code should be 0
    And STDOUT should be:
      """
      locale: en_US
      ee_installer_version: stable
      le-mail: admin@bh-config-command.test
      no-of-backups: 3
      """
    And STDERR should be empty

  Scenario: List does not modify the config file
    When I run 'bin/ee config list --format=json'
    Then the file '{CONFIG_PATH}' should contain exactly:
      """
      locale: en_US
      ee_installer_version: stable
      le-mail: admin@bh-config-command.test
      no-of-backups: "3"
      """
    And the real ee config file should be unchanged

  Scenario: List reflects set and unset
    When I run 'bin/ee config set bh-added v1'
    And I run 'bin/ee config unset le-mail'
    And I run 'bin/ee config list --format=json'
    Then the exit code should be 0
    And STDOUT should be JSON equal to:
      """
      [
        {"key": "locale", "value": "en_US"},
        {"key": "ee_installer_version", "value": "stable"},
        {"key": "no-of-backups", "value": "3"},
        {"key": "bh-added", "value": "v1"}
      ]
      """

  Scenario: An empty config file still lists the bootstrap defaults
    # "No config values found!" is unreachable: the bootstrap always writes locale/ee_installer_version first.
    Given an empty temporary ee config file
    When I run 'bin/ee config list --format=json'
    Then the exit code should be 0
    And STDOUT should be JSON equal to:
      """
      [
        {"key": "locale", "value": "en_US"},
        {"key": "ee_installer_version", "value": "stable"}
      ]
      """
    And STDERR should be empty

  Scenario: List shows booleans, integers, empty strings and nested maps with their JSON types
    Given a temporary ee config file with:
      """
      locale: en_US
      ee_installer_version: stable
      flag: true
      port: 8080
      empty-value: ""
      parent:
        child: x
        sibling: z
      """
    When I run 'bin/ee config list --format=json'
    Then the exit code should be 0
    And STDOUT should be JSON equal to:
      """
      [
        {"key": "locale", "value": "en_US"},
        {"key": "ee_installer_version", "value": "stable"},
        {"key": "flag", "value": true},
        {"key": "port", "value": 8080},
        {"key": "empty-value", "value": ""},
        {"key": "parent", "value": {"child": "x", "sibling": "z"}}
      ]
      """

  Scenario: Nested maps are JSON-encoded in table and CSV output
    Given a temporary ee config file with:
      """
      locale: en_US
      ee_installer_version: stable
      parent:
        child: x
        sibling: z
      """
    When I run 'bin/ee config list --format=table'
    Then the exit code should be 0
    And STDOUT should be a table with rows:
      | key                  | value                       |
      | locale               | en_US                       |
      | ee_installer_version | stable                      |
      | parent               | {"child":"x","sibling":"z"} |
    When I run 'bin/ee config list --format=csv'
    Then the exit code should be 0
    And STDOUT should be CSV with rows:
      | key                  | value                       |
      | locale               | en_US                       |
      | ee_installer_version | stable                      |
      | parent               | {"child":"x","sibling":"z"} |

  Scenario: The removed count format is rejected
    When I run 'bin/ee config list --format=count'
    Then the exit code should be 1
    And STDERR should contain:
      """
      Error: Parameter errors:
      """
    And STDERR should contain:
      """
      Invalid value specified for 'format'
      """
    And STDOUT should be empty

  Scenario: An unknown format is rejected
    When I run 'bin/ee config list --format=xml'
    Then the exit code should be 1
    And STDERR should contain:
      """
      Invalid value specified for 'format'
      """

  Scenario: A misspelt flag gets a suggestion
    When I run 'bin/ee config list --formt=json'
    Then the exit code should be 1
    And STDERR should contain:
      """
      unknown --formt parameter
      """
    And STDERR should contain:
      """
      Did you mean '--format'?
      """

  Scenario: --fields is not supported
    When I run 'bin/ee config list --fields=key'
    Then the exit code should be 1
    And STDERR should contain:
      """
      unknown --fields parameter
      """

  Scenario: List takes no positional arguments
    When I run 'bin/ee config list le-mail'
    Then the exit code should be 1
    And STDERR should be:
      """
      Error: Too many positional arguments: le-mail
      """

  Scenario: Machine-readable formats still print with --quiet
    When I run 'bin/ee config list --format=json --quiet'
    Then the exit code should be 0
    And STDOUT should be JSON listing the entries of the YAML file '{CONFIG_PATH}'

  # --quiet is ignored today (core sets up the logger before reading the config). Table and text output go through the logger, so fixing quiet must not swallow them.
  Scenario: Human-readable formats still print with --quiet
    When I run 'bin/ee config list --quiet'
    Then the exit code should be 0
    And STDOUT should be a table with rows:
      | key                  | value                        |
      | locale               | en_US                        |
      | ee_installer_version | stable                       |
      | le-mail              | admin@bh-config-command.test |
      | no-of-backups        | 3                            |
    When I run 'bin/ee config list --format=text --quiet'
    Then the exit code should be 0
    And STDOUT should contain:
      """
      le-mail: admin@bh-config-command.test
      """

  # Fixed in v4.13.2 (EasyEngine/config-command#14), regression check: --format=text prints through EE::log, which also writes every value (secrets included) to ee.log.
  Scenario: List --format=text does not copy values into ee.log
    Given a temporary ee config file with:
      """
      locale: en_US
      ee_installer_version: stable
      cloudflare-api-key: bh-config-command-secret-{RUN_ID}
      """
    And I remember the size of the file '{EE_LOG}'
    When I run 'bin/ee config list --format=text'
    Then the exit code should be 0
    And STDOUT should contain:
      """
      cloudflare-api-key: bh-config-command-secret-{RUN_ID}
      """
    And the text appended to the file '{EE_LOG}' should not contain 'bh-config-command-secret-{RUN_ID}'
