Feature: Login for every configured user
  As a site administrator
  I want every user defined in cucumber.js worldParameters.users to be
  able to log in
  So that the suite has known-good fixtures for every role of the site
  template before any role-specific scenarios run

  Scenario: Webmaster can log in and provision the rest of the testing users
    Given I am a logged in user with the "Webmaster" user
     When I add testing users
      And I navigate to "/admin/people"
     Then I should see "content_editor_user"
      And I should see "authenticated_user"

  Scenario: Content editor can log in
    Given I am a logged in user with the "Content editor" user
     When I navigate to "/user"
     Then I should see "content_editor_user"

  Scenario: Authenticated user can log in
    Given I am a logged in user with the "Authenticated user" user
     When I navigate to "/user"
     Then I should see "authenticated_user"
