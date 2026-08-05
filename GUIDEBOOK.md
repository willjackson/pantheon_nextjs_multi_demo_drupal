# Guidebook: Decoupled Drupal 11 + multiple Next.js 16 front ends on Pantheon

Follow this start to finish to stand up a **multi–front-end** decoupled CMS on Pantheon: one
shared **Drupal 11** backend serving **several independent Next.js 16 front ends** (one per
vertical — Education, E-commerce, …). Content flows Drupal → Next.js over **JSON:API**, scoped
per front end by a **`field_next_site`** reference; navigation comes from a per-site menu; draft
preview and revalidation use the Next.js for Drupal (`next`) module.

**Time:** about 20 minutes for the backend plus one front end the first time.

**End state:** one Drupal backend and one or more Next.js front ends running as separate DDEV
projects, each rendering only its own slice of the shared content — and a clear path to deploy
each to Pantheon (the backend as a **Custom Upstream**, each front end as a **Front-End Site**).

> This guidebook ships **identically in the backend and every front-end repository**, so it
> reads the same whichever you cloned. Commands are shown **relative to a repository's root** —
> each repo is a standalone app; there is no shared parent folder when cloned.

New to Next.js on Pantheon? The [Next.js Overview](https://docs.pantheon.io/nextjs) covers the
platform (and [Migrating from Front-End Sites](https://docs.pantheon.io/nextjs/migrating-from-front-end-sites)
if you're on the legacy offering).

---

## 1. Prerequisites

### Access

| You need | Notes |
|---|---|
| Pantheon Dashboard access | The workspace/organization your sites and custom upstream belong to |
| GitHub account or organization | Every repo lives on GitHub; front ends (Front-End Sites) deploy **from** GitHub, and the backend is a Custom Upstream registered from its GitHub repo |

### Tools

```bash
brew install ddev/ddev/ddev terminus   # or your preferred install route
# Docker (Docker Desktop, OrbStack, or Colima) must be installed and running.
```

- **[DDEV](https://ddev.com/) + Docker** — recommended for the backend and every front end.
- **[Terminus](https://docs.pantheon.io/terminus)** — Pantheon's CLI.
- **Node 22+ / npm** — only if you run a front end **without** DDEV (§4, Option B).

### One-time setup

```bash
terminus auth:login --machine-token=<token>
mkcert -install                                # only if you'll run a front end on the host over HTTPS
```

---

## 2. The repositories & architecture

One backend repo, plus one repo per front end — each standalone, each its own DDEV project.
Clone whichever you need; git creates a directory named after the repo:

```bash
git clone git@github.com:willjackson/pantheon_nextjs_multi_demo_drupal.git   # Drupal backend
git clone git@github.com:willjackson/education-example.git                    # Education front end
git clone git@github.com:willjackson/nib-commerce-example.git                 # E-commerce front end
```

| Part | Repository | DDEV project | Local URL |
| --- | --- | --- | --- |
| Drupal 11 backend (shared) | [`willjackson/pantheon_nextjs_multi_demo_drupal`](https://github.com/willjackson/pantheon_nextjs_multi_demo_drupal) | `nib-drupal` | https://nib-drupal.ddev.site |
| Education front end | [`willjackson/education-example`](https://github.com/willjackson/education-example) | `nib-edu` | https://nib-edu.ddev.site |
| E-commerce front end | [`willjackson/nib-commerce-example`](https://github.com/willjackson/nib-commerce-example) | `nib-commerce` | https://nib-commerce.ddev.site |

They do **not** need to be in sibling folders — when the DDEV projects are running, each front
end reaches the backend over the shared, machine-wide DDEV router **by hostname**.

### How one backend serves many front ends

One Drupal backend serves every front end over **JSON:API**, with each collection request
**scoped by `field_next_site`** so a front end (e.g. `edu`, `commerce`) renders only its
own content; Drupal fires **revalidate webhooks** (`/api/revalidate`) to each front end
on change.

- Each front end = a Drupal **`next_site`** entity (`edu`, `commerce`). Every node carries a
  **`field_next_site`** reference to the front end(s) it belongs to (a node can belong to
  several); the `next` module's site resolver reads it.
- A front end sets **`NEXT_PUBLIC_NEXT_SITE`** to its id and filters JSON:API collections by
  `field_next_site` → it renders only its own content. Empty ⇒ renders everything.
- Navigation is the per-site menu `nextjs-<id>`; each front end has its own theme.

### Related projects

- **Single-site Drupal** starter: [`d11-nextjs-starter-be`](https://github.com/willjackson/d11-nextjs-starter-be) / [`d11-nextjs-starter-fe`](https://github.com/willjackson/d11-nextjs-starter-fe).
- **WordPress** starters: [`wp-nextjs-starter-be`](https://github.com/willjackson/wp-nextjs-starter-be) / [`wp-nextjs-starter-fe`](https://github.com/willjackson/wp-nextjs-starter-fe).

---

## 3. Run the backend (Drupal) locally

The shared Drupal 11 site, served at `https://nib-drupal.ddev.site`. From the **backend**
repository root:

```bash
ddev init            # start + init-site (composer + drush si) + apply-recipes + show-links
ddev show-links      # backend URL, admin login link, and the registered next_site front ends
```

`ddev apply-recipes` installs (from Packagist) and applies the recipe packages, then provisions
OAuth draft preview:

| Recipe | Type | Role |
| --- | --- | --- |
| `pantheon-systems-ps/pantheon_nextjs_multi_demo` | Site | Backbone — shared content model, JSON:API, OAuth, the `next` stack, and the `field_next_site` reference + site resolver. |
| `pantheon-systems-ps/pantheon_nextjs_edu_demo` | Content | Education vertical — `next_site.edu`, a `nextjs-edu` menu, `edu_event`, and content tagged `edu`. |
| `pantheon-systems-ps/pantheon_nextjs_commerce_demo` | Content | E-commerce vertical — `next_site.commerce`, a `nextjs-commerce` menu, `product` + `commerce_event`, and content tagged `commerce`. |

```bash
ddev apply-recipes                              # backbone + all verticals + configure preview
ddev apply-recipes pantheon_nextjs_edu_demo     # a single vertical
```

The backend is a **Pantheon Custom Upstream** (`drupal-composer-managed`): shared deps live in
`upstream-configuration/composer.json`; `recipes/` is Composer-populated (gitignored). A
recipe-driven install profile (`web/profiles/custom/pantheon_nextjs_multi_demo`) powers the
Pantheon browser install and collects each front end's URL.

---

## 4. Run a front end (Next.js) locally

Each front end is a standalone Next.js 16 app made into a specific vertical purely by config
(`.env.local` + `config.json` + theme). Run these from a **front-end** repository root.

### Option A — DDEV (recommended)

```bash
ddev init            # start container, seed .env.local, npm install, start the dev server
ddev develop            # start/restart the dev server; attaches to the tmux session (live logs — Ctrl-b d to detach)
ddev develop --background # run it detached, returning to your shell
ddev develop-stop     # stop it
ddev npm <cmd>        # run npm in the project
```

Served at `https://nib-<name>.ddev.site` (e.g. `nib-edu`). DDEV wires up the reverse proxy to
the dev server on port 3000 (`.ddev/nginx-proxy.conf`), the route to the backend
(`.ddev/docker-compose.backend.yaml` maps `nib-drupal.ddev.site` to the host gateway) with
trusted HTTPS, and Node 22. Because no host ports are pinned, **many front ends run at once**,
each at its own `https://nib-<name>.ddev.site`.

### Option B — run directly with Node

```bash
cp .env.example .env.local     # then edit: point the Drupal URLs at a reachable backend
npm install
npm run dev                     # http://localhost:3000
```

Point `NEXT_PUBLIC_DRUPAL_BASE_URL` / `DRUPAL_INTERNAL_URL` at a reachable backend; to use the
DDEV backend over HTTPS your host must trust DDEV's CA (`mkcert -install`). **Use DDEV unless
you specifically need a bare Node process.**

### Environment variables (per front end)

Copy `.env.example` → `.env.local`. The per-site keys are what make a front end its vertical:

| Variable | Purpose |
| --- | --- |
| `NEXT_PUBLIC_DRUPAL_BASE_URL` / `DRUPAL_INTERNAL_URL` | Backend URL (browser / server-side). |
| `NEXT_IMAGE_DOMAIN` | Drupal host allowed for `next/image`. |
| `NEXT_PUBLIC_NEXT_SITE` | The `next_site` id this front end renders (`edu`, `commerce`, …). |
| `NEXT_PUBLIC_MENU_NAME` | Its Drupal menu (`nextjs-edu`, …). |
| `NEXT_PUBLIC_EVENT_BUNDLE` | Its Event type (`edu_event`, …). |
| `DRUPAL_CLIENT_ID` / `DRUPAL_CLIENT_SECRET` / `DRUPAL_OAUTH_SCOPE` | Simple OAuth for draft preview (scope `nextjs_preview`). |
| `DRUPAL_REVALIDATE_SECRET` | On-demand revalidation; matches the Drupal `next_site`. |

**Setting them on Pantheon (Secrets Manager via Terminus).** Front-End Site env vars are
Pantheon Secrets of `--type=env` (built into Terminus 4.2+). Target `<fe-site>` for all
environments, or `<fe-site>.<env>` to override one; `--rebuild` triggers a redeploy. Do this
**per front end** with that vertical's values:

```bash
terminus secret:site:set <fe-site> NEXT_PUBLIC_DRUPAL_BASE_URL "https://<backend>.pantheonsite.io" --type=env
terminus secret:site:set <fe-site> DRUPAL_INTERNAL_URL         "https://<backend>.pantheonsite.io" --type=env
terminus secret:site:set <fe-site> NEXT_PUBLIC_NEXT_SITE       "edu"                               --type=env
terminus secret:site:set <fe-site> NEXT_PUBLIC_MENU_NAME       "nextjs-edu"                        --type=env
terminus secret:site:set <fe-site> NEXT_PUBLIC_EVENT_BUNDLE    "edu_event"                         --type=env
terminus secret:site:set <fe-site> DRUPAL_CLIENT_SECRET        "<consumer-secret>"                 --type=env
terminus secret:site:set <fe-site> DRUPAL_REVALIDATE_SECRET    "<revalidate-secret>"               --type=env --rebuild

terminus secret:site:list   <fe-site>
terminus secret:site:delete <fe-site> DRUPAL_REVALIDATE_SECRET
```

> Full reference: [Managing env vars with Secrets Manager](https://docs.pantheon.io/guides/secrets)
> and [environment variables for Next.js](https://docs.pantheon.io/nextjs/environment-variables).

---

## 5. Use the site

1. Log into Drupal (`ddev show-links` → login link, or `ddev drush uli` in the backend repo).
2. Create/edit **Pages, Articles, Events** (and, for commerce, **Products**); assign **Tags**;
   tag each node with the front end(s) it belongs to via **`field_next_site`**; add items to
   the per-site `nextjs-<id>` menu.
3. Each front end renders only its own content — the Education site shows `edu` content, the
   E-commerce site shows `commerce` content, both from the same backend.
4. Draft preview and on-demand revalidation flow through the `next` module (OAuth scope
   `nextjs_preview`; the `next_site` revalidate/preview secrets).

---

## 6. Deploy to Pantheon

### Backend — Custom Upstream (Integrated Composer)

The backend repo is a Pantheon **Custom Upstream** (`drupal-composer-managed`). See
[Integrated Composer](https://docs.pantheon.io/guides/integrated-composer) and
[Custom Upstream Usage](https://docs.pantheon.io/guides/integrated-composer/ic-upstreams).

- **New site:** create it from the custom upstream in the dashboard; Integrated Composer builds
  it and the install profile walks you through connecting each front end.
- **Existing site — pull upstream changes:** `terminus upstream:updates:apply <backend>.dev --updatedb`.
- Promote: `terminus env:deploy <backend>.test`, then `.live`.

### Each front end — Front-End Site (Git deploy)

> **Front-End Sites is Pantheon's _legacy_ decoupled hosting.** New work should target the
> current **Next.js on Pantheon** offering — see
> [Migrating from Front-End Sites](https://docs.pantheon.io/nextjs/migrating-from-front-end-sites).
> The Git-based workflow below is the Front-End Sites model.

Every front end deploys as its **own** Pantheon Front-End Site — **Git-based, not**
`upstream:updates`:

```bash
# From a front-end repo root:
git push origin main                                   # → Dev build
git checkout -b my-change && git push origin my-change # → open a PR → Multidev preview
```

Push `main` → Dev; a branch/PR → a **Multidev** preview. Promote **Dev → Test → Live**.
Production runs the **standalone** build with the persistent
[cache handler](https://docs.pantheon.io/nextjs/architecture).

---

## Adding a vertical

1. Scaffold a new front end from the template: `nextjs-template/new-frontend <name> <site-id> "<Label>"`.
2. Create & publish a matching recipe `pantheon-systems-ps/pantheon_nextjs_<site-id>_demo`
   (registers `next.next_site.<site-id>`, a `nextjs-<site-id>` menu, and content tagged
   `field_next_site: <site-id>`); require it via the backend's upstream.
3. Backend: `ddev apply-recipes pantheon_nextjs_<site-id>_demo`.
4. Front end: `ddev start && ddev develop`, then create its Pantheon Front-End Site and set its
   Secrets (§4).

## DDEV projects (one backend, one per front end)

| | Backend (`…_multi_demo_drupal`) | Each front end (`education-example`, `nib-commerce-example`, …) |
| --- | --- | --- |
| DDEV `type` | `drupal11` (`nib-drupal`) | `generic` + Node 22 (`nib-<name>`) |
| Serves | Drupal via PHP-FPM/nginx | Next.js dev server on `:3000`, reverse-proxied |
| Key commands | `init`, `apply-recipes`, `configure-preview`, `show-links` | `init`, `develop`, `develop-stop`, `npm` |

## Troubleshooting

- **A front end shows the wrong / no content:** check `NEXT_PUBLIC_NEXT_SITE` matches a real
  Drupal `next_site` id, and that nodes are tagged with that `field_next_site`.
- **Front end can't reach the backend under bare Node:** run it under DDEV (§4, Option A) or
  point the Drupal URLs at a public backend URL.
- **`ddev` project name conflict:** DDEV names are unique per machine; rename in that repo's
  `.ddev/config.yaml`.
- **Pantheon front-end build didn't pick up a change:** Front-End Sites deploy on **git push**
  to the connected repo — not `terminus upstream:updates`.

## Going further

- **Backend:** [Integrated Composer](https://docs.pantheon.io/guides/integrated-composer) · [Custom Upstream Usage](https://docs.pantheon.io/guides/integrated-composer/ic-upstreams) · [Terminus](https://docs.pantheon.io/terminus)
- **Front ends:** [Next.js Overview](https://docs.pantheon.io/nextjs) · [Migrating from Front-End Sites (legacy)](https://docs.pantheon.io/nextjs/migrating-from-front-end-sites) · [Build & Runtime Architecture](https://docs.pantheon.io/nextjs/architecture) · [Environment variables](https://docs.pantheon.io/nextjs/environment-variables) · [Secrets Manager](https://docs.pantheon.io/guides/secrets) · [Multidev](https://docs.pantheon.io/guides/decoupled/overview/fes-multidev) · [Test & Live](https://docs.pantheon.io/nextjs/test-and-live-env)
- **Platform:** [WebOps Workflow](https://docs.pantheon.io/pantheon-workflow) · [Git on Pantheon](https://docs.pantheon.io/guides/git) · [DDEV](https://ddev.com/)
