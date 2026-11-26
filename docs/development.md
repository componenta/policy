# Development integration

This branch targets the next Policy 4.x release line (`4.0.x-dev` for both `dev-main` and the feature branch). The published 3.x line supports PHP 8.4; raising the runtime minimum to PHP 8.5.13 and requiring the current Componenta dependency majors is a breaking baseline change.

This branch requires PHP 8.5.13+ and the current Componenta major lines. Its public Composer manifest declares normal semantic constraints. Some current library sources needed small dependency compatibility updates before the complete graph could use Config 4 and DI 6. Until those updates are released, a consumer of this development branch must explicitly select their source branches.

The integration graph uses Config 4.0.0, Reflection 3.0.0, DI 6 development source, Identity 1.1 development source, and Arrayable 1.0.0. No package is replaced, provided under a false version, or assigned an obsolete major alias. Branch aliases identify the version line of the actual source checkout. Composer's normal solver selects released transitive dependencies.

## Pinned source dependencies

Each source dependency below is on `fix/callback-dependency-baseline-2026-11-26`. The immutable revision is the source used for integration verification and CI.

| Package | Declared source line | Revision |
|---|---|---|
| `componenta/di` | `6.0.x-dev` | [`ded9a6dc44db`](https://github.com/componenta/di/commit/ded9a6dc44db2775c4753e73d9581d70caac6043) |
| `componenta/identity` | `1.1.x-dev` | [`3feb94e0d649`](https://github.com/componenta/identity/commit/3feb94e0d64956ecdf7b22abed4a8bd9f597abeb) |

The machine-readable inventory is [development-dependencies.json](../.github/development-dependencies.json). CI checks out those revisions, restores their actual branch names so Composer can discover their development versions, and generates a separate Composer root. The tracked package manifest remains unchanged by this process.

## Reproduce the integration

From a fresh package checkout with PHP 8.5.13+ and Composer 2:

```bash
mkdir -p .development-dependencies
git clone --branch fix/callback-dependency-baseline-2026-11-26 https://github.com/componenta/di.git .development-dependencies/di
git -C .development-dependencies/di switch -C fix/callback-dependency-baseline-2026-11-26 ded9a6dc44db2775c4753e73d9581d70caac6043
git clone --branch fix/callback-dependency-baseline-2026-11-26 https://github.com/componenta/identity.git .development-dependencies/identity
git -C .development-dependencies/identity switch -C fix/callback-dependency-baseline-2026-11-26 3feb94e0d64956ecdf7b22abed4a8bd9f597abeb
php .github/scripts/prepare-development.php
composer validate --strict
COMPOSER=.composer-development.json composer update --no-interaction --prefer-dist
COMPOSER=.composer-development.json composer validate --strict
composer test
composer analyse
COMPOSER=.composer-development.json composer audit
```

To use existing checkouts, pass their parent directory to `php .github/scripts/prepare-development.php /path/to/dependencies`. Check out the listed source branch at the listed revision first. The generator checks package names and declared branch aliases, and never supplies Composer `options.versions` overrides. The generated manifest, lock, dependency clones, and vendor directory are local integration artifacts.

The workflow checks PHP 8.5.13 and the current PHP 8.5 patch. Its lowest-dependency job resolves the lowest compatible versions within these current major lines while retaining the required source revisions; it does not substitute an older Componenta major.

For a push whose commit message starts with `Release v`, both verification jobs instead install from the tracked `composer.json` and run the test suite and static analysis against its normally resolved dependencies. The development source checkouts and generated manifest are not used for publication. A release therefore remains blocked until compatible dependencies satisfying the public manifest are available as stable releases. Pull requests and other pushes continue to verify the pinned development graph.

## Verification scope

The callback tests were introduced against the old public API and failed for the missing capability before implementation. They cover direct calls, native attribute construction, private static first-class callables declared through a shared trait used by two classes, repeated evaluations with current inputs, result validation, and exception boundaries. The full existing test suite remains the preservation control. PHPStan runs at the repository's established maximum level.

PHP 8.5 introduced the constant-expression syntax; [PHP 8.5.13](https://www.php.net/ChangeLog-8.php#8.5.13) fixes the required trait callable scope and visibility behavior. Native closure persistence is not part of this feature.
