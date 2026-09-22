@api
Feature: JSON:API serves the content, read-only
  As a client application
  I want to read the site content over JSON:API
  So that I can use it without an account and without changing it

  Background:
    Given the API base URL is "/"

  Scenario: The resource list is served from /api
     When I send a GET request to "api"
     Then the API response code should be 200
      And the response header "Content-Type" should contain "application/vnd.api+json"
      And the API response should contain "node--webpage"
      And the API response should contain "taxonomy_term--tags"

  Scenario: Webpages are served anonymously
     When I send a GET request to "api/node/webpage"
     Then the API response code should be 200
      And the JSON property "jsonapi.version" should be "1.1"
      And the API response should contain "The API and its documentation"

  @security
  Scenario: JSON:API refuses write operations
    Given I set header "Content-Type" with value "application/vnd.api+json"
     When I send a POST request to "api/node/webpage" with body:
       """
       {"data": {"type": "node--webpage", "attributes": {"title": "Not allowed"}}}
       """
     Then the API response code should be 405
      And the API response should contain "only read operations"
