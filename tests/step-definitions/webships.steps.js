/**
 * @file
 * Webships steps: the shared Webship login and test-user provisioning.
 */

const { Given } = require('@cucumber/cucumber');

/**
 * Log in as a named test user defined in cucumber.js worldParameters.users.
 *
 * The Webmaster row is the site-install super-admin. Every other row is
 * provisioned by `Given I add testing users` (see below). The same
 * phrasing is used by varbase_project so suites can move between
 * projects without re-learning step names.
 *
 * Example #1: Given I am a logged in user with the "Webmaster" user
 * Example #2: Given I am a logged in user with the "Content editor" user
 * Example #3: Given I am a logged in user with the "Authenticated user" user
 * Example #4: Given I am a logged in user with the username "Content editor" user
 * Example #5: Given I am a logged in user with "Webmaster"
 */
Given(
  /^I am a logged in user with( the)*( username)* "([^"]*)?"( user)?$/,
  async function (theCase, usernameCase, key, userCase) {
    const users = this.parameters.users || {};
    if (!(key in users)) {
      throw new Error(
        `No user named "${key}" in cucumber.js worldParameters.users`,
      );
    }
    const { username, password } = users[key];
    if (!username || !password) {
      throw new Error(
        `User "${key}" is missing username or password in worldParameters.users`,
      );
    }
    await this.page.goto(`${this.parameters.launchUrl}/user/login`);
    // Form ids, not labels: they hold across the admin themes.
    await this.page.locator('#edit-name').fill(username);
    await this.page.locator('#edit-pass').fill(password);
    await this.page.locator('#edit-submit').click();
    await this.page.waitForLoadState('networkidle');
  },
);

/**
 * Provision every non-admin user from cucumber.js worldParameters.users via
 * Drupal's /admin/people/create form. Entries flagged isAdmin: true are
 * skipped (the site-install Webmaster already exists). Idempotent: a
 * second run reports "name is already taken" and the step swallows it.
 *
 * Must be invoked while logged in as the Webmaster (or any user with the
 * "administer users" permission).
 *
 * Example #1: Given I add testing users
 * Example #2: And I add testing users
 * Example #3: When I add testing users
 * Example #4: Given I add the testing users
 * Example #5: And we add testing users
 */
Given(/^(?:I |we )?add( the)? testing users$/, async function (theCase) {
  const users = this.parameters.users || {};
  for (const [key, info] of Object.entries(users)) {
    if (info.isAdmin) continue;
    await this.page.goto(`${this.parameters.launchUrl}/admin/people/create`);
    await this.page.locator('#edit-name').fill(info.username);
    await this.page
      .locator('#edit-mail')
      .fill(info.email || `${info.username}@example.test`);
    await this.page.locator('#edit-pass-pass1').fill(info.password);
    await this.page.locator('#edit-pass-pass2').fill(info.password);
    for (const role of info.roles || []) {
      const cb = this.page.locator(`input[name="roles[${role}]"]`);
      if ((await cb.count()) > 0) await cb.check();
    }
    await this.page.locator('#edit-submit').click();
    await this.page.waitForLoadState('networkidle');
  }
});
