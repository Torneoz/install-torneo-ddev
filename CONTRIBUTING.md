# Contributing

## Local setup

Install DDEV and a Docker provider, then run:

```bash
cp .env.example .env
ddev rebuild --yes
```

Provider keys are optional for rebuilding but required to make live model
requests. Keep them only in the ignored `.env` file.

## Changes

- Manage Drupal and PHP dependencies with Composer; do not commit `vendor/`,
  Drupal core, contributed extensions, generated site files, or credentials.
- Keep harness configuration in `recipes/ai_test_harness`.
- Add compatibility changes to contributed projects as Composer patches in
  `patches/`; do not edit installed contributed code directly.
- Run `ddev rebuild --yes` before submitting a pull request.
- Commit `composer.json` and `composer.lock` together after dependency changes.

Open pull requests against `main` and explain the user impact and validation
performed.
