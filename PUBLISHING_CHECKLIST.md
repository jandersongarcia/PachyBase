# Publishing Checklist

## Public release checklist

- Confirm `VERSION`, `CHANGELOG.md`, and `RELEASE_NOTES.md` describe the intended release.
- Run `./pachybase doctor` and resolve every error before publishing.
- Review every warning from `./pachybase doctor`, especially auth and bootstrap admin warnings.
- Regenerate `docker/docker-compose.yml` from the intended `.env` before manual smoke tests.
- Run `./pachybase test` or the equivalent containerized PHPUnit command.
- Verify the default install flow on Windows and Linux documentation still matches the repository.
- Confirm `.env.example` does not contain production secrets and still reflects the supported drivers.
- Confirm the checked-in Docker assets publish the database port that matches the selected driver.
- Review `config/CrudEntities.php` for unintentionally exposed entities or fields.
- Generate and inspect `build/openapi.json` with `./pachybase openapi:build`.
- Smoke test the public surfaces: `/`, `/openapi.json`, `/ai/schema`, auth login, and one protected CRUD endpoint.
- Confirm release archives exclude `docs-site/` so the distributed package stays runtime-focused.
- Confirm root documentation points to the published docs site instead of archive-local `docs-site/` paths.
- Validate the final public artifact from a clean clone or export, not only from the working tree.
- Tag the release only after the checklist passes in a clean clone.
