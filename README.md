# Torneo AI Test Harness

A disposable, reproducible Drupal CMS site to build and test Torneo AI
integrations with OpenAI, Gemini, and xAI Grok. The project packages the
modules, provider defaults,
assistant, agent, chatbot block, Canvas integration, permissions, and local
development environment required to rebuild the same test site from source.

This repository is an AI integration harness. It does not install
`torneoz/torneo`.

## Included features

- Drupal AI Core 1.x
- Core Interface Translation
- Torneo AI Language
- AI Agents and AI Assistant API
- AI Chatbot with a preconfigured DeepChat toolbar block
- AI Dashboard and AI API Explorer
- AI Costs and AI Observability
- AI Metering, AI Budget Control, and AI Usage Limits
- AI CKEditor integration
- OpenAI Provider
- Anthropic Provider
- Grok AI Provider (`drupal/grok`, renamed from `grok_ai_provider`)
- Grok Documents (`torneoz/grok_doc`)
- Gemini Provider
- AI Image Studio
- Ask Scaffgang (ScaffAI)
- AI Media Image
- AI Image Alt Text and AI Image Bulk Alt Text
- Drupal Canvas components for both AI chatbot block types
- Environment-backed Drupal Key entities for OpenAI and xAI
- A preconfigured `Harness Chatbot` agent and assistant

Composer installs the exact dependency versions recorded in `composer.lock`.
Contributed Drupal projects are resolved through the official
`https://packages.drupal.org/8` Composer repository.

## Featured Torneo AI projects

| Project | Drupal.org | Source repository |
| --- | --- | --- |
| Grok Integration | [Project page](https://www.drupal.org/project/grok) | [Drupal GitLab](https://git.drupalcode.org/project/grok) |
| Grok Documents and Collections | [Grok project page](https://www.drupal.org/project/grok) | [GitHub](https://github.com/Torneoz/grok_doc) |
| AI Image Studio | [Project page](https://www.drupal.org/project/ai_image_studio) | [Drupal GitLab](https://git.drupalcode.org/project/ai_image_studio) |
| AI Costs | [Project page](https://www.drupal.org/project/ai_costs) | [Drupal GitLab](https://git.drupalcode.org/project/ai_costs) |
| Torneo AI Test Harness | — | [GitHub](https://github.com/Torneoz/install-torneo-ddev) |
| Torneo AI Language | [Project page](https://www.drupal.org/project/torneo_ai_language) | [GitHub](https://github.com/Jonno/torneo_ai_language) |

### AI cost and usage governance modules

The harness enables the related projects together for comparison and
integration testing:

| Module | Included capability |
| --- | --- |
| [AI Costs](https://www.drupal.org/project/ai_costs) | Provider-neutral pricing metadata, request estimates, and usage-cost records. |
| [AI Metering](https://www.drupal.org/project/ai_metering) | Per-call metering, cost dashboards, quotas, pricing sync, and exports. |
| [AI Budget Control](https://www.drupal.org/project/ai_budget_control) | Provider, role, and user budgets with hard and soft limits. |
| [AI Usage Limits](https://www.drupal.org/project/ai_usage_limits) | Provider-level token ceilings for supported usage categories. |

These modules overlap intentionally. This site is a test harness, not a
recommended production configuration; evaluate which governance model fits a
production site before enabling overlapping enforcement policies.

## Requirements

- Git
- A supported Docker provider, such as Docker Desktop, OrbStack, Colima, or
  Rancher Desktop
- [DDEV](https://ddev.com/get-started/)
- Internet access during the first dependency installation
- An xAI API key to use the preconfigured Grok chatbot
- An OpenAI API key only when testing OpenAI operations

You do not need PHP, Composer, Drush, MariaDB, or Node.js installed directly on
the host. DDEV provides them in containers.

## Quick start

Clone the repository and enter it:

```bash
git clone https://github.com/Torneoz/install-torneo-ddev.git
cd install-torneo-ddev
```

Build the complete site:

```bash
ddev rebuild --yes
```

This is a destructive command. It drops all tables in the local DDEV database,
installs Drupal again, applies the harness recipe and post-install
configuration, runs database updates, rebuilds caches, and verifies the
required modules and private file system.

Open the site:

```bash
ddev launch
```

That is the complete installation path. On the first rebuild, the command
automatically creates an ignored `.env` file from `.env.example`. The site can
be built and inspected without API keys.

To make live model requests, edit `.env` and add the provider keys you intend
to test, then restart DDEV:

```dotenv
OPENAI_API_KEY=
ANTHROPIC_API_KEY=
XAI_API_KEY=
GEMINI_API_KEY=
```

```bash
ddev restart
```

Never commit `.env`. It is ignored by Git and passed only to the DDEV web
container.

The initial local-only administrator account is:

```text
Username: admin
Password: admin
```

Do not use these credentials on an internet-accessible or production site.

## What the rebuild creates

The rebuild performs the following operations in order:

1. Starts DDEV and installs the locked Composer dependencies.
2. Creates the local private-files directory.
3. Drops the existing local database when Drupal is already installed.
4. Installs Drupal CMS using the `drupal_cms_installer` profile.
5. Applies `recipes/ai_test_harness`.
6. Imports the recipe configuration and post-install configuration.
7. Runs Drupal database updates and rebuilds caches.
8. Imports bundled Torneo module translations for every installed language.
9. Verifies every required AI module and the `private://` stream wrapper.

Run the interactive version when you want a confirmation prompt before the
database is erased:

```bash
ddev rebuild
```

## Packaged AI configuration

The harness installs these configuration objects:

- Agent: `harness_chatbot`
- Assistant: `harness_chatbot`
- Standard block: `mercury_harness_chatbot`
- Default chat provider: `grok`
- Default chat model: `grok-4.5`
- Grok provider key: `xai_api_key`, sourced from `XAI_API_KEY`
- OpenAI provider key: `openai_api_key`, sourced from `OPENAI_API_KEY`
- Gemini provider key: `google_gemini_api_key`, sourced from `GEMINI_API_KEY`
- DeepChat API permission for anonymous and authenticated users
- DeepChat Canvas component in the Mercury footer

The assistant stores one conversation thread per session and uses the packaged
AI agent for tool-capable requests. Hosted Grok tools are disabled by default.

## Important administration pages

After signing in, use these paths relative to the DDEV site URL:

| Feature | Path |
| --- | --- |
| AI overview | `/admin/config/ai` |
| AI settings | `/admin/config/ai/settings` |
| Providers | `/admin/config/ai/providers` |
| Grok provider | `/admin/config/ai/providers/grok` |
| Gemini provider | `/admin/config/ai/providers/gemini` |
| OpenAI provider | `/admin/config/ai/providers/openai` |
| Agents | `/admin/config/ai/tools-automation/agents` |
| Assistants | `/admin/config/ai/ai-assistant` |
| API Explorer | `/admin/config/ai/explorers` |
| Costs | `/admin/config/ai/costs` |
| Observability | `/admin/config/ai/observability` |
| Image Studio settings | `/admin/config/ai/image-studio` |
| New Image Studio session | `/admin/content/ai-image-studio/new` |
| Block layout | `/admin/structure/block` |

The actual local hostname and port can vary when another service already uses
ports 80 or 443. Run `ddev describe` to see the active URLs.

## Verify the installation

Check Drupal and DDEV status:

```bash
ddev describe
ddev drush status
```

Verify the required modules:

```bash
ddev drush pm:list --status=enabled --type=module --format=list \
  | grep -E '^(ai|ai_agents|ai_assistant_api|ai_chatbot|ai_dashboard|ai_api_explorer|ai_image_alt_text|ai_image_bulk_alt_text|ai_image_studio|ai_media_image|ai_provider_openai|gemini_provider|grok|locale)$'
```

Verify the packaged assistant, block, and default provider:

```bash
ddev drush config:get ai_assistant_api.ai_assistant.harness_chatbot
ddev drush config:get block.block.mercury_harness_chatbot
ddev drush config:get ai.settings default_providers.chat
```

The default provider output should identify `grok` and `grok-4.5`.

### Browser smoke test

1. Sign in as the local administrator.
2. Open the home page and confirm the chatbot toggle appears in the toolbar
   area.
3. Open the chatbot and confirm its first message appears.
4. Send a short prompt and confirm a Grok response is returned.
5. Open the Grok provider page and confirm it loads without a PHP error.
6. Open AI API Explorer and run a chat request.
7. Confirm the assistant and agent are listed on their administration pages.
8. Test both a Canvas page and a regular Drupal page.

Live requests require a valid key, provider account access, and model access.
The site can still be rebuilt and inspected without keys.

## Test a public release from scratch

Use a new directory so existing containers, dependencies, settings, and caches
cannot hide packaging problems:

```bash
git clone --branch v1.0.0 --depth 1 \
  https://github.com/Torneoz/install-torneo-ddev.git install-torneo-v1-test
cd install-torneo-v1-test
ddev rebuild --yes
ddev launch
```

For the strongest verification, perform this test on a second computer or a
clean CI runner. Also open the repository and release URL in a signed-out or
private browser window to confirm public access.

## Day-to-day commands

```bash
ddev start
ddev stop
ddev restart
ddev describe
ddev launch
ddev drush uli
ddev drush cache:rebuild
ddev drush updatedb --yes
ddev composer install
```

Use `ddev drush uli` for a one-time administrator login link when you do not
want to enter the local password.

## Changing configuration

Changes made only through the Drupal interface are stored in the local database
and disappear after the next rebuild. To make a setting reproducible:

1. Make and test the change in Drupal.
2. Export or copy only the intended configuration.
3. Put recipe-installable configuration in
   `recipes/ai_test_harness/config/`.
4. Put configuration that modifies objects created by the Drupal CMS install in
   `recipes/ai_test_harness/post_install_config/`.
5. Run `ddev rebuild --yes` and repeat the browser smoke test.

Useful configuration commands:

```bash
ddev drush config:get CONFIG_NAME
ddev drush config:export --yes
ddev drush config:import --yes
```

Do not commit a broad configuration export without reviewing it for unrelated
site state, UUIDs, environment-specific values, and secrets.

## Updating dependencies

Install the locked dependency set after cloning or pulling:

```bash
ddev composer install
```

To update the primary AI packages within the constraints in `composer.json`:

```bash
ddev composer update \
  drupal/ai \
  drupal/ai_agents \
  drupal/ai_dashboard \
  drupal/ai_image_studio \
  drupal/grok \
  --with-all-dependencies
```

Then run:

```bash
ddev composer validate --strict --no-check-publish
ddev composer audit
ddev rebuild --yes
```

Review and commit `composer.json` and `composer.lock` together. Do not edit files
under `web/modules/contrib`, `web/core`, or `vendor` directly.

## Troubleshooting

### Docker or DDEV cannot start

Confirm the Docker provider is running, then use:

```bash
ddev poweroff
ddev start
```

If standard ports are busy, DDEV selects alternate ports. Use `ddev describe`
instead of assuming the site runs on port 443.

### The chatbot icon is missing

Check that the block, assistant, module, and theme are present, then rebuild
caches:

```bash
ddev drush config:get block.block.mercury_harness_chatbot
ddev drush config:get ai_assistant_api.ai_assistant.harness_chatbot
ddev drush pm:list --status=enabled --filter=ai_chatbot
ddev drush cache:rebuild
```

Also test a regular Drupal page as well as a Canvas page. The harness packages
both the standard Mercury block and the Canvas footer component.

### DeepChat reports an invalid `csrf_token`

Clear Drupal caches, reload the page without restoring an old tab, and clear
the browser's site data if necessary:

```bash
ddev drush cache:rebuild
```

If the error remains after clearing site data, update the AI module, run
`ddev composer install`, and rebuild from a clean database.

### Grok provider configuration throws `getExtensionInfo()` on null

Update the Grok module and rebuild caches. Do not restore an older
`grok_ai_provider` directory; the current machine name is `grok`.

### A rebuild reports existing recipe configuration

Use the repository's `ddev rebuild --yes` command instead of applying the
harness recipe manually. It imports pre-existing Canvas and settings objects
from `post_install_config` after recipe application.

### Provider requests fail

Confirm the key exists in `.env`, restart DDEV so the container receives the
environment value, and clear caches:

```bash
ddev restart
ddev drush cache:rebuild
ddev exec printenv XAI_API_KEY
```

The last command prints a secret. Use it only locally and do not paste its
output into issues, logs, screenshots, or pull requests.

### Reset everything

The supported reset path is:

```bash
ddev rebuild --yes
```

This permanently erases the current local Drupal database. Export anything you
need before running it.

## Security and repository hygiene

- Never commit `.env`, API keys, database dumps, uploaded files, private files,
  `auth.json`, local settings overrides, or DDEV machine-local configuration.
- Revoke any credential immediately if it appears in Git history or logs;
  removing the text from a later commit is not sufficient.
- Prompt and response body logging is disabled by default. Review observability
  settings before enabling sensitive logging.
- This project uses deliberately simple local administrator credentials and is
  not a production deployment template.

## Contributing and releases

See `CONTRIBUTING.md` for the contribution workflow and `CHANGELOG.md` for
release history. Before publishing a release:

1. Perform a clean `ddev rebuild --yes`.
2. Complete the command-line and browser verification above.
3. Run Composer validation and the security audit.
4. Confirm the Git working tree contains no secrets or generated artifacts.
5. Update `CHANGELOG.md`, commit, tag the release, and verify a clean clone of
   that tag.

GitHub Actions also validates Composer metadata and installs the locked
dependencies on pushes to `main` and on pull requests.
