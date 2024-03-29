# CLI Power

Pressody Conductor provides a series of WP-CLI commands that allow of easy composition management, either manually or automated through scripts.

**The absolute source of truth** for these commands rests in each command's CLI help, accessible through commands like `wp help pd composition` or `wp help pd composition update`.

All of Conductor's CLI commands are **grouped under the `pd` namespace** as do all other CLI commands of the PD ecosystem.

## Site Commands

This series of commands target the entire site. They are grouped under the `pd site` sub-namespace.

Here are all the commands available:
- `wp pd site info` for listing the current site info
- `wp pd site check` to check the status of the site
- `wp pd site clear-cache` to clear the site cache.

## Composition Commands

This series of commands target the current site composition. They are grouped under the `pd composition` sub-namespace.

Here are all the commands available:
- `wp pd composition info` for listing the current site's composition info
- `wp pd composition check` to check the status of the site's composition
- `wp pd composition update` to update the site's composition to its latest contents
- `wp pd composition backup` to create a backup of the current composition contents (composer.json)
- `wp pd composition revert-backup` to revert the composition contents (composer.json) to their backed-up ones (if a backup exists).
- `wp pd composition composer-install` to all the packages locked in composer.lock (similar to `composer install`)
- `wp pd composition composer-update` to update the site's composition packages as instructed by composer.json (similar to `composer update`)
- `wp pd composition update-cache` to update the DB cache related to the composition's contents (plugins and themes)
- `wp pd composition clear-cache` to delete the DB cache related to the composition's contents (plugins and themes)
- `wp pd composition activate` to active the plugins and/or theme installed by the composition.
- `wp pd composition update-sequence` to run the entire sequence from updating the site's composition to activating. **This is the go-to workhorse for use in cronjobs!**

## SysAdmin Commands

This series of commands are aimed at system-administrators that need to dig deeper. Of course, Linux offers plenty out-of-the box. These are either often used ones, or specially installed on PD servers.

Here is a not-so-exhaustive list of commands:
- `composer run cache:opcache:status` for displaying the status of the PHP Opcache
- `composer run cache:opcache:clear` to clear PHP's Opcache
- `composer run cache:opcache:warm` to compile (preload) all the site's `.php` files into PHP's Opcache.

[Back to Index](index.md)
