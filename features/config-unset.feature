Feature: ee config unset
  Removes one top-level key from the config file at EE_CONFIG_PATH (a throwaway file here).

  Background:
    Given a temporary ee config file with:
      """
      locale: en_US
      ee_installer_version: stable
      le-mail: admin@bh-config-command.test
      cloudflare-api-key: bh-dummy-key
      """

  Scenario: Unset an existing key removes only that key and prints nothing
    When I run 'bin/ee config unset cloudflare-api-key'
    Then the exit code should be 0
    And STDOUT should be empty
    And STDERR should be empty
    And the file '{CONFIG_PATH}' should contain exactly:
      """
      locale: en_US
      ee_installer_version: stable
      le-mail: admin@bh-config-command.test
      """
    And the real ee config file should be unchanged

  Scenario: An unset key can no longer be read
    When I run 'bin/ee config unset le-mail'
    And I run 'bin/ee config get le-mail'
    Then the exit code should be 1
    And STDERR should be:
      """
      Error: No config value with key 'le-mail' set
      """

  Scenario: Unsetting the same key twice fails the second time
    When I run 'bin/ee config unset le-mail'
    Then the exit code should be 0
    When I run 'bin/ee config unset le-mail'
    Then the exit code should be 1
    And STDERR should be:
      """
      Error: No config value with key 'le-mail' set
      """

  Scenario: Unsetting a missing key fails and leaves the file byte-identical
    When I run 'bin/ee config unset bh-missing-key'
    Then the exit code should be 1
    And STDERR should be:
      """
      Error: No config value with key 'bh-missing-key' set
      """
    And STDOUT should be empty
    And the file '{CONFIG_PATH}' should contain exactly:
      """
      locale: en_US
      ee_installer_version: stable
      le-mail: admin@bh-config-command.test
      cloudflare-api-key: bh-dummy-key
      """

  Scenario: Unset keys are case-sensitive
    When I run 'bin/ee config unset LE-MAIL'
    Then the exit code should be 1
    And the YAML file '{CONFIG_PATH}' should have key 'le-mail' with value 'admin@bh-config-command.test'

  Scenario: Unset removes a nested map as a whole
    Given a temporary ee config file with:
      """
      locale: en_US
      ee_installer_version: stable
      parent:
        child: x
      """
    When I run 'bin/ee config unset parent'
    Then the exit code should be 0
    And the YAML file '{CONFIG_PATH}' should not have key 'parent'

  Scenario: Unset of a key holding an empty string works
    Given a temporary ee config file with:
      """
      locale: en_US
      ee_installer_version: stable
      bh-empty: ""
      """
    When I run 'bin/ee config unset bh-empty'
    Then the exit code should be 0
    And the YAML file '{CONFIG_PATH}' should not have key 'bh-empty'

  Scenario: Unset without a key prints the usage
    When I run 'bin/ee config unset'
    Then the exit code should be 1
    And STDOUT should be:
      """
      usage: ee config unset <key>
      """

  Scenario: Unset with two keys is rejected and removes nothing
    When I run 'bin/ee config unset le-mail cloudflare-api-key'
    Then the exit code should be 1
    And STDERR should be:
      """
      Error: Too many positional arguments: cloudflare-api-key
      """
    And the YAML file '{CONFIG_PATH}' should have key 'le-mail'
    And the YAML file '{CONFIG_PATH}' should have key 'cloudflare-api-key'

  Scenario: Unset rejects unknown flags
    When I run 'bin/ee config unset le-mail --yes'
    Then the exit code should be 1
    And STDERR should contain:
      """
      unknown --yes parameter
      """
    And the YAML file '{CONFIG_PATH}' should have key 'le-mail'

  Scenario: Unset keeps the file mode
    Given the file '{CONFIG_PATH}' has mode '0640'
    When I run 'bin/ee config unset cloudflare-api-key'
    Then the exit code should be 0
    And the file '{CONFIG_PATH}' should have mode '0640'

  Scenario: Unsetting a bootstrap default is undone by the next ee invocation
    # Every ee run re-adds locale/ee_installer_version (Runner::ensure_present_in_config).
    When I run 'bin/ee config unset locale'
    Then the exit code should be 0
    And the YAML file '{CONFIG_PATH}' should not have key 'locale'
    When I run 'bin/ee config get locale'
    Then the exit code should be 0
    And STDOUT should be:
      """
      en_US
      """
    And the YAML file '{CONFIG_PATH}' should have key 'locale' with value 'en_US'
