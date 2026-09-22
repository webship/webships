@api
Feature: The API is documented with Swagger UI
  As a developer of a client application
  I want the OpenAPI document of the API rendered with Swagger UI
  So that I can learn the API without reading its code

  @security
  Scenario: The API documentation needs a permission
    Given the API base URL is "/"
     When I send a GET request to "openapi/jsonapi"
     Then the API response code should be 403

  Scenario: Swagger UI renders the JSON:API documentation for an administrator
    Given I am a logged in user with the "Webmaster" user
     When I navigate to "/admin/config/services/openapi/swagger/jsonapi"
     Then I should see "OpenAPI Documentation"
      And ".swagger-ui .info .title" should be visible within 30 seconds
      And ".swagger-ui .info .title" should contain text "JSON API"
