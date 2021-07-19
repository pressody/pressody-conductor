# The Composition

**The site's composition is at the core of the entire LT Conductor logic** (for what is worth, this holds true for the entire LT ecosystem). While other entities like **LT Records** and **LT Retailer** work to construct and manage the life-cycle of a composition, as _an abstract entity_, detached from an actual WordPress site, **LT Conductor gets to actually make the music play!**

LT Conductor will **update the site's composition** (the `composer.json` file), **install/remove packages** (WordPress plugins and themes) according to it (by running the Composer logic), and **activate the installed plugins and themes.**

## Composition Data

The composition is a standard Composer `project` with some extra information to **uniquely identify** the source composition, its user, etc. This information resides in the `extra` entry of the `composer.json` data.

The unique information related to identifying the composition throughout the LT ecosystem is located in `extra > lt-composition`. The data is **encrypted** (by LT Retailer) since it's for our internal use only.

The `extra > lt-required-packages` is information about **the source of all the LT Parts** that are part of the site's composition. This way we can investigate how the current composition came to be (i.e. what LT Solutions "contributed" each LT Part).

The `extra > lt-version` is **the composition's LT version** to help us _migrate and upgrade compositions_ relying on older specs. This is all done remotely by LT Records and the rest of the LT ecosystem.

The `extra > lt-fingerprint` is a hash of the entire composition's data to allow us to check for **any manual or outside tampering with the composition.** Since we don't allow manual composition editing (by editing the `composer.json` file), we will **reject from update any compositions that fail the fingerprint test.** One solution to this situation is the reinitialization of the composition by running the following CLI command: `wp lt composition update --force`

## Composition Update

To determine if the current composition is need of an update, **LT Conductor "pings" LT Records** with the current composition (the `composer.json` contents). If it receives back a new composition, it will replace the current one (while backing it up, just in case).

Once the `composer.json` file has been update, the Composer logic is run to **install/update/remove the packages** and update the actual WordPress plugins and themes that are present in the site. This **will not interfere with manually installed plugins or themes.** Those will be left as they were.

After the plugins and themes files have been updated, **all the plugins part of the composition will be activated** (if they were not already active), and **a theme will be selected for activation** if one of the WordPress core themes are active.

If at any point during the composition update **an unrecoverable error is encountered** (like failure to install a required package, or active a plugin due to a fatal PHP error), **the site's composition is reverted to its previous state.**

## Composition Status and Manual-Management

Using the WP-CLI one can examine, investigate, and manage the site's composition. See [this page](cli.md) for more details.

One can't edit a composition to _permanently_ include or remove packages, but it can force it to update, active plugins and theme, besides the tools WP-CLI provides for general WordPress management.

## WordPress Dashboard Behavior

While LT Conductor does most of its work behind the scenes (either WP-CLI or WP-Cron), there are some **effects imposed on the WordPress dashboard.** All of these are in line with keeping the logic consistent and **avoid splitting responsibilities with the user** while promising we take them upon ourselves.

To achieve this clarity of accountability, **any WordPress plugin or WordPress theme managed through the composition will not be manageable through the regular WordPress interface** (the `Plugins` or `Themes` pages). While the PixelgradeLT crew will have WP users with special access (for debugging and support purposes), other users (including site administrators) will not be able to interact directly with plugins and themes provided by PixelgradeLT (nor with the WordPress version). 

Users can install other (possibly vetted) plugins and themes, but they can't change the behavior of the LT composition through their WordPress dashboard, **only remotely, from the place they purchase (even if free) and manage the LT Solutions** that make up their LT Composition. Some more details about the entities involved here can be found via [LT Records](https://github.com/pixelgradelt/pixelgradelt-records#lt-packages) and [LT Retailer](https://github.com/pixelgradelt/pixelgradelt-retailer#lt-solutions).

Also, we don't allow access to the `Tools → Site Health` section since we handle all the server, performance, configuration details for our users. PixelgradeLT special-access users will be able to access it for debugging and support purposes.

[Back to Index](index.md)
