# config-command Behat coverage

Suite for `ee config` (`src/Config_Command.php`). Every scenario except those in `config-global.feature` points `EE_CONFIG_PATH` at a throwaway file under `sys_get_temp_dir()`, so `/opt/easyengine/config/config.yml` is never written. Before each scenario the context captures the real config (bytes, mode, owner, symlink target) and also writes it to `/var/tmp/bh-config-command-real-config.backup` (0600). Afterwards it restores the file atomically if anything changed, verifies the result, deletes the backup, and fails any scenario not tagged `@destructive` that changed it (the message names the changed keys only, never values). A shutdown function restores it on Ctrl-C (Behat exits without AfterScenario), SIGTERM/SIGHUP and PHP fatals; after a `kill -9` the next run's `@BeforeSuite` restores it from the backup when it carries this suite's `bh-config-command-key`, and otherwise stops and asks for a manual check. A lock file prevents two runs from overlapping. The context also pins `PAGER=cat` and, unless `EE_PHP_ARGS` is set, PHP's error settings (notices to STDERR, no deprecations), so results don't depend on the host php.ini. No sites, containers, `/etc/hosts` entries, LE certificates or remote backups are involved; the config commands don't even need Docker.

## Inventory

| Item | Source | Covered by |
|---|---|---|
| `ee config` (no subcommand): usage of all subcommands, exit 0 | core `CompositeCommand::show_usage()` | config.feature: "Running ee config without a subcommand…" |
| `ee help config`, `ee help config <sub>` (synopsis, list formats) | docblocks | config.feature: "ee help config lists…", outline "ee help config <subcommand>…", "…documents every supported format" |
| Unknown subcommand (with and without suggestion) | core `Runner::find_command_to_run()` | config.feature: two "unknown config subcommand" scenarios |
| No Docker requirement check for `config` | core `Runner::init_ee()` | config.feature: "config commands skip the Docker requirement check" (docker/docker-compose shims that always fail and record calls; asserts they were never called) |
| `get <config-key>`: string, value with colon, quoted number, integer, boolean, empty value, bootstrap default | `get()` | config-get.feature: "Get an existing string value" … "Get ee_installer_version" |
| `get`: read-only (file byte-identical) | `get()` | config-get.feature: "Get does not modify the config file" |
| `get`: missing key error, case sensitivity, dotted keys are literal | `get()` isset check | config-get.feature: "Getting a missing key fails", "Keys are case-sensitive", "Dotted keys are literal…" |
| `get`: no arg (usage), two args, unknown flag | core `Subcommand::validate_args()` | config-get.feature: "Get without a key…", "Get with two keys…", "Get rejects unknown flags" |
| `get` on a config path that doesn't exist yet | `get()` + bootstrap | config-get.feature: "Get works with a freshly created config file" |
| `get` of a null value | `get()` isset | not covered yet: null-valued keys can't be read (its scenario comes back with the fix) |
| `get` of a nested map | `get()` → `EE::log(array)` | not covered yet: nested values print `Array` (its scenario comes back with the fix) |
| `get --quiet` still prints the value | `get()` uses `EE::log`; quiet is ignored | config-get.feature: "Get still prints the value with --quiet" (guards against quiet swallowing the value once it works) |
| `get` copies the value into ee.log | `EE::log` → file logger | config-get.feature regression check, fixed in v4.13.2 by EasyEngine/config-command#14 |
| `set <key> <value>`: new key (exact file content, no output), read back, overwrite keeps position, idempotent | `set()` | config-set.feature: first four scenarios |
| `set`: numeric stored as quoted string, empty value, dotted key literal, replacing a nested map, multi-line value, leading/trailing spaces | `set()` + Spyc dump | config-set.feature: "Numeric values…", "An empty value…", "Dotted keys…", "Setting a key that holds a nested map…", "A multi-line value…", "A value with leading and trailing spaces…" |
| `set`: 24 YAML-special values round-trip (true/false/yes/no/on/null/~/0/0123/1e3/1.50/`a: b`/`a #b`/`[1,2]`/`{a: b}`/-dash/spaces/email/URL with `#`/unicode/`%p`/`&`/`*`/`!`) | Spyc quoting | config-set.feature outline "Special value <value> round-trips unchanged" |
| `set`/`get`/`unset` of keys that need YAML quoting (space, `: `, `#`, `@`) | Spyc key quoting | config-set.feature outline "Key <key> that needs YAML quoting round-trips" |
| `set` of a key containing a newline | no key validation | not covered yet: such a key corrupts the config (its scenario comes back with the fix) |
| `set`: missing value / no args (usage, file untouched), unquoted spaces, flag-looking value, unknown flag | core validation | config-set.feature: "Set without a value…", "Set without arguments…", "An unquoted value with spaces…", "A value that looks like a flag…", "Set rejects unknown flags" |
| `set` creates a missing file and directories | `set()` + bootstrap + Symfony `dumpFile()` | config-set.feature: "Set creates a missing config file…" |
| `set` keeps the file mode | Symfony `dumpFile()` | config-set.feature: "Set keeps the file mode" |
| `EE_CONFIG_PATH` given inline for one invocation | `set()` path resolution | config-set.feature: "Set with EE_CONFIG_PATH on the command line…" |
| `set` of `"quoted"` and `\|` values | Spyc dump | not covered yet: the values change after a round trip (its outline, 2 examples, comes back with the fix) |
| `set` drops comments / rewrites unrelated `y`/`off` values | whole-file Spyc rewrite | not covered yet: comments are dropped and `y`/`off` become booleans (its 2 scenarios come back with the fix) |
| `set` on a symlinked config | Symfony `dumpFile()` rename | not covered yet: the symlink is replaced by a regular file (its scenario comes back with the fix) |
| `set` secret ends up in ee.log (argv debug line) | core `Runner::start()` | config-set.feature regression check, fixed in v4.13.2 by EasyEngine/easyengine#1944 |
| `unset <key>`: removes only that key (exact content, no output), then get fails, twice (second fails), missing key (file byte-identical), case sensitivity, nested map, empty-string value | `unset()` | config-unset.feature: first seven scenarios |
| `unset`: no arg, two args, unknown flag | core validation | config-unset.feature: "Unset without a key…", "Unset with two keys…", "Unset rejects unknown flags" |
| `unset` keeps the file mode | Symfony `dumpFile()` | config-unset.feature: "Unset keeps the file mode" |
| `unset locale` is undone by the next run | core `ensure_present_in_config()` | config-unset.feature: "Unsetting a bootstrap default is undone…" (documents current behaviour) |
| `unset` of null values | `unset()` isset | not covered yet: null-valued keys can't be removed (its scenario comes back with the fix) |
| `unset` drops comments / replaces a symlink | whole-file rewrite | not covered yet: comments are dropped and a symlink is replaced (their scenarios come back with the fixes) |
| `list` default format and `--format=table` | `_list()` + Formatter | config-list.feature: "The default format…", "--format=table…" |
| `list --format=json|csv|yaml|text` | `_list()` | config-list.feature: one scenario per format |
| `list`: read-only, reflects set/unset, value types in JSON, nested maps JSON-encoded in table/csv | `_list()` | config-list.feature: "List does not modify…", "List reflects set and unset", "List shows booleans…", "Nested maps are JSON-encoded…" |
| `list` on an empty file ("No config values found!") | `_list()` empty branch | config-list.feature: "An empty config file still lists the bootstrap defaults" (the error branch is unreachable, see below) |
| `list`: removed `count` format (db58c81), unknown format, misspelt flag suggestion, `--fields`, positional arg | synopsis validation | config-list.feature: five scenarios |
| `list --format=json --quiet` | Formatter echoes directly | config-list.feature: "Machine-readable formats still print with --quiet" |
| `list --format=text` with nested map | `_list()` string concat | not covered yet: nested values print `Array` (its scenario comes back with the fix) |
| `list` shows null keys that get can't read | isset vs list | not covered yet: null-valued keys (its scenario comes back with the fix) |
| `list` (table/text) with `--quiet` | `EE::line`/`EE::log` go through the logger; quiet is ignored | config-list.feature: "Human-readable formats still print with --quiet" |
| `list --format=text` copies values into ee.log | `EE::log` → file logger | config-list.feature regression check, fixed in v4.13.2 by EasyEngine/config-command#14 |
| Bootstrap: missing file/dirs created with `locale`/`ee_installer_version`, empty file, missing defaults appended, file untouched when defaults exist | core `Runner::ensure_present_in_config()` | config-path.feature: first four scenarios |
| `EE_CONFIG_PATH` switching per invocation | all subcommands | config-path.feature: "EE_CONFIG_PATH switches files per invocation" |
| Config keys that are global runtime parameters: hand-written `disabled_commands` list, `quiet` | core Configurator/Runner | config-path.feature: "A hand-written disabled_commands list…", "quiet in the config file does not hide…", "Setting quiet to false stores a string…" |
| `set disabled_commands …` | set writes strings | not covered yet: a string `disabled_commands` breaks every command on PHP 8 (its scenario comes back with the fix) |
| Default path (no `EE_CONFIG_PATH`, empty `EE_CONFIG_PATH`): list, set/get/unset, mode kept | path fallback | config-global.feature (`@destructive`, 4 scenarios, restored by the context) |
| Config not readable/writable errors | core `ensure_present_in_config()` | not covered: the suite runs as root, which bypasses file permissions; core code, not this package |
| `~/easyengine` config root on macOS | core `init-ee.php` | not covered: Linux-only CI |
| Global runtime flags accepted by config subcommands (`--le-mail=…`, `--debug`), project `ee.yml`/`ee.local.yml` merged from the working directory | core Configurator | not covered: core behaviour, not specific to this package |

## Counts

7 features, 118 scenarios (outline examples expanded): `@destructive` 4, `@le` 0, `@remote` 0. 114 scenarios run without `@destructive`.

No `@known-bug` scenarios ship here: the 16 scenario rows written for the open bugs listed below are held back until their fixes land and then return as regular scenarios.

Coverage estimate: about 93% of the package's reachable behaviour (every subcommand, argument, format and error path in `Config_Command.php`, plus the core bootstrap/validation behaviour they depend on). The only uncovered package branch is `list`'s "No config values found!", which the bootstrap makes unreachable; the gaps are the core behaviours listed as not covered above. The mode check in `config-global.feature` can't catch a mode regression when the real file is 0644 (the default for a new file); the temp-file mode scenarios (0600, 0640) do.

Estimated runtime: about 235 `bin/ee` invocations with no Docker work, roughly 2 to 3 minutes.

## Known bugs (scenarios held back until fixed)

Each was reproduced on PHP 7.4 and 8.5:

- Low: `get`/`unset` use `isset()`, so null-valued keys can't be read or removed.
- Low: nested values print `Array` in `get` and `list --format=text`.
- Medium: `set`/`unset` rewrite the whole file (comments dropped, `y`/`off` become `true`/`false`).
- Medium: `set` stores strings; `set disabled_commands X` makes every ee command fatal on PHP 8, including the `unset` needed to fix it.
- Low, core: `--quiet` and `quiet:` never take effect (the logger is set up before the config is read). No held-back scenario: the suspected "quiet hides get/list output" doesn't happen today; the quiet scenarios guard against it once quiet is fixed.
- Low: `"quoted"` and `|` values change after `set` then `get`.
- Low: `set`/`unset` replace a symlinked config with a regular file.
- Medium, security: secrets reach ee.log (`get` and `list --format=text` log values; core logs argv, so `set cloudflare-api-key <key>` persists the key). Fixed in v4.13.2 (EasyEngine/config-command#14, EasyEngine/easyengine#1944); the three scenarios are regular now.
- Info: help text bugs ("Set a config value" on get, "Manges", incomplete README; the README part has no scenario).
- Low: a key containing a newline corrupts the config so every ee command dies.

## Notes

- Asserted as current behaviour, not tagged as bugs: `set`/`unset` print nothing on success (other ee commands print `Success:`); unsetting `locale`/`ee_installer_version` is undone by the next invocation. Seen in the code but not tested: `ee config set debug true` stores the string `'true'`, which the logger treats as a debug group name, so it doesn't enable debug output.
- The CSV scenarios may also expose PHP 8.4+'s `fputcsv()` `$escape` deprecation if `display_errors` is on (it's off on this host's CLI).
- New shared steps are in the "Shared steps" section of `bootstrap/FeatureContext.php`. The inherited "I run" step now expands `{…}` placeholders (an undefined placeholder is an error, never a literal path), runs the command without copying its output into ee.log, and refuses `sudo`/`su`/`env -…` while a temporary config is active, because they can drop `EE_CONFIG_PATH`. The ee.log steps fail if nothing was appended, so they can't pass vacuously.
- The site-command pattern of deleting every site after each feature is replaced by deleting only `bh-config-command-*` sites, and only when such a site directory exists, so this suite never calls `ee site` at all in practice.
