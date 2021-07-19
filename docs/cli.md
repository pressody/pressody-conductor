# CLI Power

PixelgradeLT Conductor provides a series of WP-CLI commands that allow of easy composition management, either manually or automated through scripts.

**The absolute source of truth** for these commands rests in each command's CLI help, accessible through commands like `wp help lt composition` or `wp help lt composition update`.

All of Conductor's CLI commands are **grouped under the `lt` namespace** as do all other CLI commands of the LT ecosystem.

## Composition Commands

This series of commands target the current site composition. They are grouped under the `lt composition` sub-namespace.

Here are all the commands available:
- `wp lt composition info` for listing the current site's composition info
- `wp lt composition check` to check the status of the site's composition
- `wp lt composition update` to update the site's composition to its latest contents
- `wp lt composition update-cache` to update the DB cache related to the composition's contents (plugins and themes)
- `wp lt composition clear-cache` to delete the DB cache related to the composition's contents (plugins and themes)
- `wp lt composition activate` to active the plugins and/or theme installed by the composition.

[Back to Index](index.md)
