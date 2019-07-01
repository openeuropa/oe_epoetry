@api @javascript @ecas-login
Feature: Login through OE Authentication
  In order to be able to access the CMS backend
  As user of the system
  I need to login through OE Authentication with the option Proxy
  I need to be redirect back to the site

  @cleanup:user
  Scenario: Login/Logout with eCAS mockup server of internal users
    Given the site is configured to make users active on creation
    When I am on the homepage
    And I click "Log in"
    And I click "European Commission"
    # Redirected to the mock server.
    And I fill in "Username or e-mail address" with "texasranger@chucknorris.com.eu"
    And I fill in "Password" with "Qwerty098"
    And I press the "Login!" button
    # Redirected back to Drupal.
    Then I should see "You have been logged in."
