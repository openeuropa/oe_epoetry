@api @javascript @ecas-login
Feature: Login through OE Authentication
  In order to be able to access the CMS backend
  As user of the system
  I need to login through OE Authentication
  I need to be redirect back to the site

  @cleanup:user
  Scenario: Login/Logout with eCAS mockup server of internal users with the option Proxy Service.
    Given the site is configured to make users active on creation
    When I am on the homepage
    And I click "Log in"
    And I click "European Commission"
    # Redirected to the mock server.
    And I fill in "Username or e-mail address" with "texasranger@chuck_norris.com.eu"
    And I fill in "Password" with "Qwerty098"
    And I press the "Login!" button
    # Redirected back to Drupal.
    Then I should see "You have been logged in."
    And I should see the link "My account"
    And I should see the link "Log out"
    And I should not see the link "Log in"

    # Redirected back to Drupal.
    When I click "My account"
    Then I should see the heading "chucknorris"

    # Profile contains extra fields.
    When I click "Edit"
    Then the "First Name" field should contain "Chuck"
    And the "Last Name" field should contain "NORRIS"
    And the "Department" field should contain "DIGIT.A.3.001"
    And the "Organisation" field should contain "eu.europa.ec"

    When I click "Log out"
    # Redirected to the Ecas mockup server.
    And I press the "Log me out" button
    # Redirected back to Drupal.
    Then I should be on the homepage
    And I should not see the link "My account"
    And I should not see the link "Log out"
    And I should see the link "Log in"

