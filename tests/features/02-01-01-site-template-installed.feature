Feature: The Webships installer applies the WebAPI Starter site template
  As a site administrator
  I want the site template I chose in the Webships installer to set up the site
  So that the site serves a documented API from the start

  Scenario: Web API and its modules are enabled
    Given I am a logged in user with the "Webmaster" user
     When I navigate to "/admin/modules"
     Then the element "#edit-modules-webapi-enable" with the attribute "checked" and the value "checked" should exist
      And the element "#edit-modules-jsonapi-enable" with the attribute "checked" and the value "checked" should exist
      And the element "#edit-modules-simple-oauth-enable" with the attribute "checked" and the value "checked" should exist
      And the element "#edit-modules-openapi-ui-swagger-enable" with the attribute "checked" and the value "checked" should exist

  Scenario: The Webpage content type exists
    Given I am a logged in user with the "Webmaster" user
     When I navigate to "/admin/structure/types"
     Then I should see "Webpage"

  Scenario: The installer uninstalls itself when the install is done
    Given I am a logged in user with the "Webmaster" user
     When I navigate to "/admin/reports/status"
     Then I should see "Drupal version"
      And I should not see "Installation profile"

  Scenario: The site name from the installer is kept
    Given I am an anonymous user
     When I navigate to "/user/login"
     Then the response status code should be 200
      And "title" should contain text "Webships Test"

  Scenario: The page about the API is published
    Given I am an anonymous user
     When I navigate to "/api-and-docs"
     Then the response status code should be 200
      And I should see "The API and its documentation"

  @security
  Scenario: Registration is closed
    Given I am an anonymous user
     When I navigate to "/user/register"
     Then the response status code should be 403
