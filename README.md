# Pressody Conductor

Custom WordPress plugin to hold the CONDUCTOR-entity logic for Pressody (client-side).

## About

Deliver a smooth, secure existence for a Pressody WP Site. This should be used as a MU plugin.

## Development

## Build

Since this is ultimately a WordPress plugin, you wil need to **generate a cleaned-up .zip file when you wish to publish a new release.**

To generate a new release ZIP file you have the utility script `bin/archive`. You can run it as such, without any arguments, or you can provide a version like so `bin/archive --version=1.0.2`. If you don't provide a version, the release version will be fetched from the plugin's main file headers.

Once the version has been deduced, **the script will enforce it** in the plugin's main .php file and in the `package.json` file. This way everything is kept in sync.

For the `bin/archive` script there are a couple of **things that are important:**
* the `name` entry in `package.json` is **the same** as the plugin main file (minus the `.php` extension)
* **the files and directories that will be included** in the release file need to be explicitly specified in the `distFiles` entry in `package.json`

The `bin/archive` script will also generate a fresh `.pot` language file in the `languages` directory, before creating the release file. This way you can be sure that the `.pot` file is not out-of-sync.

**The resulting release file will be located in the `dist` directory,** in your plugin directory (it is ignored by Git).

## Running Tests

To run the PHPUnit tests, in the root directory of the plugin, run something like:

```
./vendor/bin/phpunit --testsuite=Unit --colors=always
```
or
```
composer run tests
```

Bear in mind that there are **simple unit tests** (hence the `--testsuite=Unit` parameter) that are very fast to run, and there are **integration tests** (`--testsuite=Integration`) that need to load the entire WordPress codebase, recreate the db, etc. Choose which ones you want to run depending on what you are after.

You can run either the unit tests or the integration tests with the following commands:

```
composer run tests-unit
```
or
```
composer run tests-integration
```

**Important:** Before you can run the tests, you need to create a `.env` file in `tests/phpunit/` with the necessary data. You can copy the already existing `.env.example` file. Further instructions are in the `.env.example` file.

## Documentation

For installation notes, information about usage, and more, see the [documentation](docs/index.md).

## Credits

...

---

Copyright (c) 2021, 2022 Vlad Olaru (vlad@thinkwritecode.com)
