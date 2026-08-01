# Drupal AI module test harness

This repository builds a disposable Drupal CMS development site for testing:

- [`drupal/ai`](https://www.drupal.org/project/ai)
- `drupal/ai_costs`
- AI Observability
- AI CKEditor integration
- AI Agents and Assistant API
- AI Chatbot with a preconfigured DeepChat block
- AI Dashboard and AI API Explorer
- `drupal/ai_provider_openai`
- `drupal/grok` (the renamed Grok AI provider module)
- `drupal/ai_image_studio`

It does not install `torneoz/torneo`.

## Requirements

- [DDEV](https://ddev.com/get-started/)
- A working Docker provider

## Build or rebuild

Run from the project root:

```bash
ddev rebuild
```

The command asks before erasing the installed database. For an unattended local
or CI build:

```bash
ddev rebuild --yes
```

The command installs the locked Composer dependencies, installs Drupal, applies
the Drupal CMS starter recipe and the test-harness recipe, runs database
updates, rebuilds caches, and verifies that all required AI modules are enabled.
It also creates the project-root `private/` directory and verifies Drupal's
`private://` stream wrapper is available and writable.

Run `ddev launch` to open the active project URL. The initial local-only login
is `admin` / `admin`.

## Provider credentials

The root `.env` file is ignored by Git and loaded into DDEV's web container.
Copy the provided template and add local credentials:

```bash
cp .env.example .env
```

```dotenv
OPENAI_API_KEY=
XAI_API_KEY=
```

The build creates environment-backed Drupal Key entities and assigns them to
the OpenAI and Grok providers. Never commit API key values.

## Updating the alpha modules

The constraints in `composer.json` allow tagged `1.x` alpha releases, while
`composer.lock` records the exact versions used by a rebuild.

```bash
ddev composer update drupal/grok drupal/ai_image_studio drupal/ai --with-all-dependencies
ddev rebuild --yes
```

Review and commit both `composer.json` and `composer.lock` when changing the
tested release set.

## Included configuration

The `ai_test_harness` recipe installs the AI modules and packages the provider
defaults, environment-backed keys, chatbot assistant, agent, DeepChat block,
Canvas components, and role permissions. A rebuild recreates this configuration
without exporting secrets.

The Composer patches in `patches/` are part of the tested release and are
applied automatically by `composer install`.

## Common commands

```bash
ddev start
ddev drush uli
ddev drush cache:rebuild
```
