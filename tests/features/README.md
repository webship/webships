# Webships - webship-js feature scenarios

The site is installed with `drush site:install webships
installer_site_template_form.add_ons=webapi_starter`: the Webships installer
with the WebAPI Starter site template.

| File | Covers |
|------|--------|
| `01-01-01-users-login.feature` | Per-role login; the first scenario provisions the testing users. |
| `02-01-01-site-template-installed.feature` | The site template's modules and content type, the installer uninstalled, the site name kept, the page about the API, closed registration. |
| `03-01-01-jsonapi.feature` | JSON:API at `/api`, served anonymously and read-only. |
| `03-02-01-api-documentation.feature` | The OpenAPI document needs a permission; Swagger UI renders it. |
