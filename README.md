# Decoupled Drupal backend — multi–front-end Pantheon custom upstream

A **single** Drupal 11 site that serves content to **one or many** independent Next.js front
ends over JSON:API — one per vertical (Education, E-commerce, …). Each node is tagged to the
front end(s) it belongs to via a `field_next_site` reference, so one backend can power many
front ends at once. Preview and on-demand revalidation are handled by the Next.js for Drupal
(`next`) module. This repo is a **Pantheon custom upstream** (`drupal-composer-managed`).

The front ends are **separate repositories** — e.g.
[`willjackson/education-example`](https://github.com/willjackson/education-example) (edu) and
[`willjackson/nib-commerce-example`](https://github.com/willjackson/nib-commerce-example) (commerce).

**Full setup, usage, and deploy:** see **[GUIDEBOOK.md](GUIDEBOOK.md)** (ships identically in
the backend and front-end repos).

## Stack

- Drupal 11 on PHP 8.3, docroot `web/`, hosted on Pantheon (Custom Upstream + Integrated Composer).
- DDEV project `nib-drupal`, served at `https://nib-drupal.ddev.site`.

## Requirements

- [DDEV](https://ddev.com/) (local development) and Composer.
- Contrib modules, installed via Composer through the upstream (see
  [`upstream-configuration/composer.json`](upstream-configuration/composer.json)):
  `drupal/next`, `drupal/decoupled_router`, `drupal/consumers`, `drupal/simple_oauth`,
  `drupal/pathauto`.

## Quick start (local, DDEV)

```bash
ddev init          # start, Composer install, install Drupal, apply recipes, configure preview
ddev show-links    # admin login link + the registered front ends
```

`ddev init` runs `ddev init-site` (Composer install + `drush site:install`) followed by
`ddev apply-recipes`. Then start each front end from its own repo (`ddev init` there).

## Recipes

The content model, per-site config, and demo content ship as Composer packages under the
`pantheon-systems-ps` org, installed into `recipes/` (gitignored) and applied by
`ddev apply-recipes`:

| Recipe | Type | Purpose |
| --- | --- | --- |
| `pantheon_nextjs_multi_demo` | Site | **Backbone** — modules, the shared Page/Article content model, JSON:API, OAuth, and the per-site `field_next_site` reference + site resolver. Ships no `next_site` and no content. |
| `pantheon_nextjs_edu_demo` | Content | **Education vertical** — registers the `edu` front end, its `nextjs-edu` menu, `edu_event` type, and demo content. |
| `pantheon_nextjs_commerce_demo` | Content | **E-commerce vertical** — registers the `commerce` front end, its `nextjs-commerce` menu, `product` + `commerce_event` types, and demo content. |

```bash
ddev apply-recipes                              # backbone + all verticals (default), then configure preview
ddev apply-recipes pantheon_nextjs_edu_demo     # a single vertical (applies the backbone first)
```

Each vertical recipe depends on the backbone and applies it first.

## How front ends are associated

Each front end is a Drupal `next_site` entity (e.g. `edu` or `commerce`). Every node carries a
`field_next_site` reference to the front end(s) it belongs to, and the `next` module's site
resolver reads it. A front end scopes its JSON:API collection requests by that reference to
render only its own content:

```
/jsonapi/node/article?filter[field_next_site.meta.drupal_internal__target_id]=edu
```

## Custom upstream

Follows Pantheon's `drupal-composer-managed` pattern: shared dependencies live in
`upstream-configuration/composer.json` (add packages with `composer upstream:require <package>`),
the root `composer.json` stays thin per site, and `pantheon.upstream.yml` sets the platform
defaults (PHP 8.3, MariaDB 10.6, protected paths). The
`web/profiles/custom/pantheon_nextjs_multi_demo` install profile drives the Pantheon browser
install (collecting each front end's URL, applying recipes, provisioning draft preview); local
DDEV uses the `standard` profile + `ddev apply-recipes`.

## Common commands

```bash
ddev drush cache:rebuild        # clear caches (first thing to try on stale config or 404s)
ddev drush config:export        # export configuration
ddev apply-recipes              # re-apply recipes
ddev configure-preview          # (re)provision the OAuth pieces for draft preview
ddev show-links                 # admin login link + registered front ends
```

Remote (Pantheon) drush via Terminus: `terminus drush <site>.<env> -- cache:rebuild`.

## Conventions

- Docroot is `web/` (`web_docroot: true` on Pantheon).
- `recipes/` is Composer-populated and gitignored — recipes are packages, not committed here.
- OAuth keys and secrets are never committed. Use Pantheon Secrets for each front end's client
  secret in production (see [GUIDEBOOK.md](GUIDEBOOK.md) for the Terminus commands).
