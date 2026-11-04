Feature: ee config get
  Reads one top-level key from the config file at EE_CONFIG_PATH (a throwaway file here).

  Background:
    Given a temporary ee config file with:
      """
      locale: en_US
      ee_installer_version: stable
      le-mail: admin@bh-config-command.test
      no-of-backups: "3"
      rclone-path: remote:bh-config-command/backups
      empty-value: ""
      flag: true
      port: 8080
      parent:
        child: x
        sibling: z
      nullkey: ~
      """

  Scenario: Get an existing string value
    When I run 'bin/ee config get le-mail'
    Then the exit code should be 0
    And STDOUT should be:
      """
      admin@bh-config-command.test
      """
    And STDERR should be empty

  Scenario: Get a value containing a colon
    When I run 'bin/ee config get rclone-path'
    Then the exit code should be 0
    And STDOUT should be:
      """
      remote:bh-config-command/backups
      """

  Scenario: Get a quoted numeric value
    When I run 'bin/ee config get no-of-backups'
    Then the exit code should be 0
    And STDOUT should be:
      """
      3
      """

  Scenario: Get an unquoted integer value
    When I run 'bin/ee config get port'
    Then the exit code should be 0
    And STDOUT should be:
      """
      8080
      """

  Scenario: Get a boolean value prints PHP's string form of true
    When I run 'bin/ee config get flag'
    Then the exit code should be 0
    And STDOUT should be:
      """
      1
      """

  Scenario: Get an empty value succeeds with empty output
    When I run 'bin/ee config get empty-value'
    Then the exit code should be 0
    And STDOUT should be empty
    And STDERR should be empty

  Scenario: Get ee_installer_version
    When I run 'bin/ee config get ee_installer_version'
    Then the exit code should be 0
    And STDOUT should be:
      """
      stable
      """

  Scenario: Get does not modify the config file
    When I run 'bin/ee config get le-mail'
    Then the exit code should be 0
    And the file '{CONFIG_PATH}' should contain exactly:
      """
      locale: en_US
      ee_installer_version: stable
      le-mail: admin@bh-config-command.test
      no-of-backups: "3"
      rclone-path: remote:bh-config-command/backups
      empty-value: ""
      flag: true
      port: 8080
      parent:
        child: x
        sibling: z
      nullkey: ~
      """
    And the real ee config file should be unchanged

  Scenario: Getting a missing key fails
    When I run 'bin/ee config get bh-missing-key'
    Then the exit code should be 1
    And STDERR should be:
      """
      Error: No config value with key 'bh-missing-key' set
      """
    And STDOUT should be empty

  Scenario: Keys are case-sensitive
    When I run 'bin/ee config get LE-MAIL'
    Then the exit code should be 1
    And STDERR should be:
      """
      Error: No config value with key 'LE-MAIL' set
      """

  Scenario: Dotted keys are literal, not a path into nested values
    When I run 'bin/ee config get parent.child'
    Then the exit code should be 1
    And STDERR should be:
      """
      Error: No config value with key 'parent.child' set
      """

  Scenario: Get without a key prints the usage
    When I run 'bin/ee config get'
    Then the exit code should be 1
    And STDOUT should be:
      """
      usage: ee config get <config-key>
      """

  Scenario: Get with two keys is rejected
    When I run 'bin/ee config get locale le-mail'
    Then the exit code should be 1
    And STDERR should be:
      """
      Error: Too many positional arguments: le-mail
      """
    And STDOUT should be empty

  Scenario: Get rejects unknown flags
    When I run 'bin/ee config get locale --format=json'
    Then the exit code should be 1
    And STDERR should contain:
      """
      unknown --format parameter
      """
    And STDOUT should be empty

  Scenario: Get works with a freshly created config file
    Given a temporary ee config path that does not exist yet
    When I run 'bin/ee config get locale'
    Then the exit code should be 0
    And STDOUT should be:
      """
      en_US
      """
    And the file '{CONFIG_PATH}' should exist

  # --quiet is ignored today (core sets up the logger before reading the config). get prints through EE::log, so fixing quiet must not make it swallow the value.
  Scenario: Get still prints the value with --quiet
    When I run 'bin/ee config get le-mail --quiet'
    Then the exit code should be 0
    And STDOUT should be:
      """
      admin@bh-config-command.test
      """

  # Fixed in v4.13.2 (EasyEngine/config-command#14), regression check: EE::log also writes to ee.log, so `config get` copies values (e.g. cloudflare-api-key) into the log.
  Scenario: Get does not copy the value into ee.log
    Given a temporary ee config file with:
      """
      locale: en_US
      ee_installer_version: stable
      cloudflare-api-key: bh-config-command-secret-{RUN_ID}
      """
    And I remember the size of the file '{EE_LOG}'
    When I run 'bin/ee config get cloudflare-api-key'
    Then the exit code should be 0
    And STDOUT should be:
      """
      bh-config-command-secret-{RUN_ID}
      """
    And the text appended to the file '{EE_LOG}' should not contain 'bh-config-command-secret-{RUN_ID}'
