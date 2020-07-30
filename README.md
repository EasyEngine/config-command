easyengine/config-command
=========================

Manages EasyEngine configuration



Quick links: [Using](#using) | [Contributing](#contributing) | [Support](#support)

## Using

This package implements the following commands:

### ee config

Manges global EasyEngine configuration.

~~~
ee config
~~~

### ee config set

Set a config value

~~~
ee config set <key> <value>
~~~

**OPTIONS**

	<key>
		Key of config to set

	<value>
		Value of config to set

**EXAMPLES**

    # Save value in config
    $ ee config set le-mail abc@example.com



### ee config get

Set a config value

~~~
ee config get <config-key>
~~~

**OPTIONS**

	<config-key>
		Name of config value to get

**EXAMPLES**

    # Get value from config
    $ ee config get le-mail

## Contributing

We appreciate you taking the initiative to contribute to this project.

Contributing isn’t limited to just code. We encourage you to contribute in the way that best fits your abilities, by writing tutorials, giving a demo at your local meetup, helping other users with their support questions, or revising our documentation.

### Reporting a bug

Think you’ve found a bug? We’d love for you to help us get it fixed.

Before you create a new issue, you should [search existing issues](https://github.com/easyengine/config-command/issues?q=label%3Abug%20) to see if there’s an existing resolution to it, or if it’s already been fixed in a newer version.

Once you’ve done a bit of searching and discovered there isn’t an open or fixed issue for your bug, please [create a new issue](https://github.com/easyengine/config-command/issues/new). Include as much detail as you can, and clear steps to reproduce if possible.

### Creating a pull request

Want to contribute a new feature? Please first [open a new issue](https://github.com/easyengine/config-command/issues/new) to discuss whether the feature is a good fit for the project.

## Support

Github issues aren't for general support questions, but there are other venues you can try: https://easyengine.io/support/


*This README.md is generated dynamically from the project's codebase using `ee scaffold package-readme` ([doc](https://github.com/EasyEngine/scaffold-command)). To suggest changes, please submit a pull request against the corresponding part of the codebase.*
