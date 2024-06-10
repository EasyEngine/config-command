Feature: EasyEngine Configuration

  Scenario: Install EasyEngine and set/get configuration
    When I run "ee config set test_key test_value"
    Then STDOUT should not return anything
    When I run "ee config get test_key"
    Then STDOUT should return "test_value"
    Then the configuration file should contain "test_key: test_value"