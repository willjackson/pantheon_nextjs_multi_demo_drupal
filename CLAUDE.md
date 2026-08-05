# CLAUDE.md — Drupal 11 backend (multi–front-end)

A **single** headless **Drupal 11** backend that serves **many** independent **Next.js 16**
front ends — one per vertical (Education, E-commerce, …) — over JSON:API. Each node is tagged
to the front end(s) it belongs to via a `field_next_site` reference, so one backend can power
one *or many* front ends at once. Draft preview and revalidation use the Next.js for Drupal
(`next`) module.

This repository is a **Pantheon Custom Upstream** (the `drupal-composer-managed` pattern).
The front ends are **separate repositories** — e.g.
[`willjackson/education-example`](https://github.com/willjackson/education-example) (edu) and
[`willjackson/nib-commerce-example`](https://github.com/willjackson/nib-commerce-example) (commerce).
For end-to-end install/use/deploy instructions, see **[GUIDEBOOK.md](GUIDEBOOK.md)**.

## The per-site model (read this before changing content/fetching)

- Each front end = a Drupal **`next_site`** config entity (e.g. `edu`, `commerce`).
- Every node carries **`field_next_site`** (entity reference → `next_site`, unlimited). The
  `next` module's **`entity_reference_field` site resolver** reads it.
- A front end sets **`NEXT_PUBLIC_NEXT_SITE`** to its site id and appends
  `filter[field_next_site.meta.drupal_internal__target_id]=<id>` to JSON:API **collection**
  requests, so it renders only its own content. Empty id ⇒ render everything.
- Navigation is a per-site menu, named `nextjs-<id>` (e.g. `nextjs-edu`).

## Stack

- **Drupal 11** (`drupal/core-recommended ^11`), **PHP 8.3** (set in `pantheon.upstream.yml`).
- **Docroot** `web/`; the repo root is the Composer root. Database MariaDB 10.6.
- **Hosting**: Pantheon Custom Upstream + Integrated Composer. Served at
  `https://nib-drupal.ddev.site` (DDEV project `nib-drupal`).
- Git origin: `git@github.com:willjackson/pantheon_nextjs_multi_demo_drupal.git`.

## Layout

The repository root **is** the Drupal Composer project:

```
├── composer.json             # per-site root (thin) — requires the upstream-configuration package
├── upstream-configuration/   # custom-upstream shared deps (modules + recipe packages) + scripts
├── pantheon.upstream.yml      # platform config (php 8.3, mariadb 10.6, build_step, protected paths)
├── config/sync/              # exported site configuration
├── recipes/                  # Composer-installed recipe packages (gitignored; see below)
├── .ddev/                    # DDEV project (nib-drupal) — init/recipe/preview commands
└── web/                      # Drupal docroot (profiles/custom/pantheon_nextjs_multi_demo, …)
```

## Custom upstream & recipes

Shared dependencies live in `upstream-configuration/composer.json` (the
`pantheon-upstreams/upstream-configuration` package, wired into the root `composer.json` as a
`path` repository); the root `composer.json` stays thin. The content model, per-site config,
and demo content ship as recipe packages under the **`pantheon-systems-ps`** org, installed
into `recipes/` (gitignored):

| Recipe | Type | Role |
| --- | --- | --- |
| `pantheon-systems-ps/pantheon_nextjs_multi_demo` | Site | **Backbone** — shared Page/Article content model, JSON:API, OAuth, the `next`/`decoupled_router`/`consumers`/`simple_oauth`/`pathauto` modules, and the **`field_next_site`** reference + site resolver. Ships **no** `next_site` and **no** content. |
| `pantheon-systems-ps/pantheon_nextjs_edu_demo` | Content | **Education vertical** — registers `next.next_site.edu`, a `nextjs-edu` menu, an `edu_event` type, and demo content tagged `field_next_site: edu`. |
| `pantheon-systems-ps/pantheon_nextjs_commerce_demo` | Content | **E-commerce vertical** — registers `next.next_site.commerce`, a `nextjs-commerce` menu, `product` + `commerce_event` types, and demo content tagged `field_next_site: commerce`. |

Each vertical recipe declares the backbone as a dependency and applies it first. Apply them
with **`ddev apply-recipes`** (backbone + all verticals + `configure-preview`), or one at a
time: `ddev apply-recipes pantheon_nextjs_edu_demo`.

> **`recipes/` is gitignored.** Recipes are Composer packages (`type: drupal-recipe`); each
> recipe's canonical home is its own package/repo under `pantheon-systems-ps`.

## Install profile (`web/profiles/custom/pantheon_nextjs_multi_demo`)

A recipe-driven install profile for the **Pantheon browser install** of a site created from
this upstream. It collects each front end's URL, applies the backbone + vertical recipes
(seeding those URLs into each `next_site`), and provisions OAuth draft preview. Local DDEV uses
the `standard` profile + `ddev apply-recipes` instead (form install tasks don't run under
`drush site:install`).

## Headless / API conventions

- **JSON:API** is the contract. Collection requests are scoped by `field_next_site`; single
  resources are not.
- **Simple OAuth** (client credentials, scope `nextjs_preview`) authenticates draft preview.
  `ddev configure-preview` (also run by `apply-recipes`) provisions the keys, the
  `nextjs_preview` role/service user, and the `default_consumer` secret.
- Per-site menus (`nextjs-<id>`) drive each front end's navigation via the linkset endpoint.

## Common commands (DDEV, from the repo root)

```bash
ddev init                 # start + init-site (composer + drush si) + apply-recipes + show-links
ddev apply-recipes        # backbone + all verticals + configure-preview
ddev configure-preview    # (re)provision OAuth draft preview
ddev show-links           # backend URL, admin login, registered next_site front ends
ddev drush <cmd>          # drush against the backend
```

## Adding a vertical

1. Scaffold a front end from the template (`nextjs-template/new-frontend <name> <site-id> "<Label>"`).
2. Create & publish a matching recipe `pantheon-systems-ps/pantheon_nextjs_<site-id>_demo`
   (registers `next.next_site.<site-id>`, a `nextjs-<site-id>` menu, and content tagged
   `field_next_site: <site-id>`); require it via the upstream.
3. `ddev apply-recipes pantheon_nextjs_<site-id>_demo`, then start the new front end.

## Gotchas

- The front ends are **separate repositories**, not subfolders of this one; local dev runs each
  as its own DDEV project (`nib-<name>`).
- Classic residence paths don't apply — this repo is the Drupal root; front ends are reached by
  hostname over the shared DDEV router, wherever they're cloned.
- A core patch (+ any contrib compat patches) is applied via `cweagans/composer-patches`; keep
  them on `composer update`.
