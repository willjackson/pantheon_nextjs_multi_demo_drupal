# Decoupled Drupal backend

A single Drupal 11 site that serves content to one or many independent Next.js front ends over
JSON:API. Content flows from Drupal to the front ends, and preview and revalidation are handled by
the Next.js for Drupal (`next`) module. This is the shared backend for the front ends in
`../frontends`.

## Stack

- Drupal 11 on PHP 8.3, docroot `web/`, hosted on Pantheon (Integrated Composer).
- DDEV project `nib-drupal`, served at `https://nib-drupal.ddev.site`.

## Requirements

- DDEV (local development) and Composer.
- Contrib modules (installed via Composer): `drupal/next`, `drupal/decoupled_router`,
  `drupal/consumers`, `drupal/simple_oauth`, `drupal/pathauto`.

## Quick start

```bash
ddev init          # start, install Composer deps, install Drupal, apply recipes, configure preview
ddev show-links    # admin login link and the registered front ends
```

`ddev init` runs `ddev init-site` (Composer install and `drush site:install`) followed by
`ddev apply-recipes`.

## Recipes

The site is built from recipes installed as Composer packages into `recipes/`. Apply them with:

```bash
ddev apply-recipes                              # backbone plus all verticals (default)
ddev apply-recipes pantheon_nextjs_edu_demo     # a single recipe
```

| Recipe | Type | Purpose |
| --- | --- | --- |
| [`pantheon_nextjs_multi_demo`](recipes/pantheon_nextjs_multi_demo) | Site | Backbone: modules, the shared Page and Article content model, JSON:API, OAuth, and the per-site association field. |
| [`pantheon_nextjs_edu_demo`](recipes/pantheon_nextjs_edu_demo) | Content | Registers the Education front end, its menu, event type, and demo content. |
| [`pantheon_nextjs_commerce_demo`](recipes/pantheon_nextjs_commerce_demo) | Content | Registers the E-commerce front end, its menu, product and event types, and demo content. |

Each vertical recipe depends on the backbone and applies it first. See each recipe's README for
details, including the one-time draft preview setup documented in the backbone recipe.

## How front ends are associated

Each front end is a Drupal `next_site` entity (for example `edu` or `commerce`). Every node carries a
`field_next_site` reference to the front end(s) it belongs to, and the `next` module's site resolver
reads it. A front end filters JSON:API by that reference to render only its own content:

```
/jsonapi/node/article?filter[field_next_site.meta.drupal_internal__target_id]=edu
```

## Common commands

```bash
ddev drush cache:rebuild        # clear caches (first thing to try on stale config or 404s)
ddev drush config:export        # export configuration
ddev apply-recipes              # re-apply recipes
ddev configure-preview          # (re)provision the OAuth pieces for draft preview
ddev show-links                 # admin login link and registered front ends
```

## Conventions

- Docroot is `web/` (`web_docroot: true` on Pantheon).
- OAuth keys and other secrets are never committed. See the backbone recipe README for draft preview
  setup, including how to store secrets with Pantheon Secrets Manager.
