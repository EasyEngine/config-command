Feature: Config file location (EE_CONFIG_PATH) and bootstrap defaults
  Every ee run first makes sure locale and ee_installer_version exist in the config file (Runner::ensure_present_in_config).

  Scenario: A missing config file and its directories are created with the defaults
    Given a temporary ee config path that does not exist yet
    When I run 'bin/ee config list --format=json'
    Then the exit code should be 0
    And STDOUT should be JSON equal to:
      """
      [
        {"key": "locale", "value": "en_US"},
        {"key": "ee_installer_version", "value": "stable"}
      ]
      """
    And the file '{CONFIG_PATH}' should contain exactly:
      """
      ---
      locale: en_US
      ee_installer_version: stable
      """
    And the real ee config file should be unchanged

  Scenario: An empty config file gets the defaults
    Given an empty temporary ee config file
    When I run 'bin/ee config get ee_installer_version'
    Then the exit code should be 0
    And STDOUT should be:
      """
      stable
      """
    And the YAML file '{CONFIG_PATH}' should contain exactly these entries:
      | key                  | value    |
      | locale               | "en_US"  |
      | ee_installer_version | "stable" |

  Scenario: Missing defaults are appended and existing values are kept
    Given a temporary ee config file with:
      """
      le-mail: admin@bh-config-command.test
      locale: de_DE
      """
    When I run 'bin/ee config get le-mail'
    Then the exit code should be 0
    And the YAML file '{CONFIG_PATH}' should contain exactly these entries:
      | key                  | value                          |
      | le-mail              | "admin@bh-config-command.test" |
      | locale               | "de_DE"                        |
      | ee_installer_version | "stable"                       |

  Scenario: A config file that already has the defaults is not rewritten by read-only commands
    Given a temporary ee config file with:
      """
      # hand-written
      locale: en_US
      ee_installer_version: nightly
      """
    When I run 'bin/ee config list'
    Then the exit code should be 0
    And the file '{CONFIG_PATH}' should contain exactly:
      """
      # hand-written
      locale: en_US
      ee_installer_version: nightly
      """

  Scenario: EE_CONFIG_PATH switches files per invocation
    Given a temporary ee config file with:
      """
      locale: en_US
      ee_installer_version: stable
      le-mail: first@bh-config-command.test
      """
    When I run 'EE_CONFIG_PATH={CONFIG_DIR}/second.yml bin/ee config set le-mail second@bh-config-command.test'
    Then the exit code should be 0
    When I run 'bin/ee config get le-mail'
    Then STDOUT should be:
      """
      first@bh-config-command.test
      """
    When I run 'EE_CONFIG_PATH={CONFIG_DIR}/second.yml bin/ee config get le-mail'
    Then STDOUT should be:
      """
      second@bh-config-command.test
      """
    And the real ee config file should be unchanged

  Scenario: A hand-written disabled_commands list disables a config subcommand
    Given a temporary ee config file with:
      """
      locale: en_US
      ee_installer_version: stable
      disabled_commands:
        - config set
      """
    When I run 'bin/ee config set bh-key v'
    Then the exit code should be 1
    And STDERR should contain:
      """
      The 'config set' command has been disabled from the config file.
      """
    And the YAML file '{CONFIG_PATH}' should not have key 'bh-key'
    When I run 'bin/ee config get locale'
    Then the exit code should be 0
    And STDOUT should be:
      """
      en_US
      """

  # quiet (config key or --quiet) never takes effect, because core picks the logger before reading the config.
  # If that gets fixed, get must print its value in a way quiet doesn't swallow, or this scenario fails.
  Scenario: quiet in the config file does not hide the value config get prints
    Given a temporary ee config file with:
      """
      locale: en_US
      ee_installer_version: stable
      """
    When I run 'bin/ee config set quiet true'
    Then the exit code should be 0
    And the YAML file '{CONFIG_PATH}' should have key 'quiet' with value 'true'
    When I run 'bin/ee config get locale'
    Then the exit code should be 0
    And STDOUT should be:
      """
      en_US
      """

  # set stores the string 'false' (truthy), which only stays harmless while quiet is ignored.
  Scenario: Setting quiet to false stores a string and keeps output visible
    Given a temporary ee config file with:
      """
      locale: en_US
      ee_installer_version: stable
      """
    When I run 'bin/ee config set quiet false'
    Then the exit code should be 0
    And the YAML file '{CONFIG_PATH}' should have key 'quiet' with value 'false'
    When I run 'bin/ee config get locale'
    Then the exit code should be 0
    And STDOUT should be:
      """
      en_US
      """
