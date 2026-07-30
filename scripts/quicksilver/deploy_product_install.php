<?php

/**
 * @file
 * Quicksilver `deploy_product` hook — one-time site setup on creation.
 *
 * `deploy_product` fires exactly once, when a site is first created from this
 * custom upstream (on the dev environment). It is the only Quicksilver workflow
 * allowed in pantheon.upstream.yml, which is why it can ship with the upstream
 * and apply to every site made from it.
 *
 * This installs Drupal (standard profile) and applies the Next.js recipes so a
 * freshly-provisioned site boots ready to use instead of showing the installer.
 *
 * Notes / constraints:
 *  - Uses the Composer-installed Drush (v13, which supports `drush recipe`), not
 *    the platform Drush (v10 in pantheon.upstream.yml).
 *  - Idempotent: skips if Drupal is already installed, so it can never clobber an
 *    existing database if the hook is ever re-run.
 *  - Quicksilver scripts have a limited run time; if the content-heavy recipes
 *    push past it, install here and apply the vertical recipes as a follow-up
 *    (e.g. `terminus drush <site>.dev -- recipe ...`).
 */

// deploy_product runs on the dev environment at creation; guard defensively so
// this never fires anywhere else.
if (($_ENV['PANTHEON_ENVIRONMENT'] ?? '') !== 'dev') {
  return;
}

$web_root = $_SERVER['DOCUMENT_ROOT'];
$code_root = dirname($web_root);

// Composer-installed Drush (v13). Always target the site root explicitly and run
// non-interactively.
$drush = escapeshellarg("$code_root/vendor/bin/drush")
  . ' --root=' . escapeshellarg($web_root)
  . ' --yes';

// Idempotency guard: only install when Drupal is not already installed.
$bootstrap = trim((string) shell_exec("$drush status --field=bootstrap 2>/dev/null"));
if (stripos($bootstrap, 'Successful') !== FALSE) {
  fwrite(STDERR, "[deploy_product] Drupal already installed; skipping setup.\n");
  return;
}

fwrite(STDERR, "[deploy_product] Installing Drupal (standard profile)...\n");
passthru("$drush site:install standard --account-name=admin", $rc);
if ($rc !== 0) {
  fwrite(STDERR, "[deploy_product] site:install failed (exit $rc); aborting.\n");
  return;
}

// Recipes are Composer packages installed into <code-root>/recipes/<name>.
$recipes = [
  'pantheon_nextjs_multi_demo',
  'pantheon_nextjs_edu_demo',
  'pantheon_nextjs_commerce_demo',
];
foreach ($recipes as $recipe) {
  $path = "$code_root/recipes/$recipe";
  fwrite(STDERR, "[deploy_product] Applying recipe: $recipe\n");
  passthru("$drush recipe " . escapeshellarg($path), $rc);
  if ($rc !== 0) {
    fwrite(STDERR, "[deploy_product] recipe '$recipe' failed (exit $rc).\n");
  }
}

passthru("$drush cache:rebuild");
fwrite(STDERR, "[deploy_product] Setup complete.\n");
