# Webships tests

[webship-js](https://www.npmjs.com/package/webship-js) (Playwright + Cucumber-js) suite, run against Drupal 11.4 installed with the Webships installer and the WebAPI Starter site template.

```bash
ddev drush site:install webships installer_site_template_form.add_ons=webapi_starter \
  --account-name=webmaster --account-pass=dD.123123ddd --site-name="Webships Test" -y
yarn install
./node_modules/.bin/playwright install --with-deps chromium
LAUNCH_URL="https://<project>.ddev.site" yarn test
```

The project needs `drupal/webapi_starter` in its recipes directory, and the Swagger UI library at `web/libraries/swagger-ui` (`vardot/swagger-ui`).

CI: the `webship-js-test` job in `.gitlab-ci.yml`.
