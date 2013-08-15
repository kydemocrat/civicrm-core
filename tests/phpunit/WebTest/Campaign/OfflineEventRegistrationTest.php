<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.4                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2013                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License along with this program; if not, contact CiviCRM LLC       |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

require_once 'CiviTest/CiviSeleniumTestCase.php';
class WebTest_Campaign_OfflineEventRegistrationTest extends CiviSeleniumTestCase {

  protected function setUp() {
    parent::setUp();
  }

  function testCreateCampaign() {
    $this->webtestLogin('admin');

    // Create new group
    $title = substr(sha1(rand()), 0, 7);
    $groupName = $this->WebtestAddGroup();

    // Adding contact
    // We're using Quick Add block on the main page for this.
    $firstName1 = substr(sha1(rand()), 0, 7);
    $this->webtestAddContact($firstName1, "Smith", "$firstName1.smith@example.org");

    // add contact to group
    // visit group tab
    $this->click("css=li#tab_group a");
    $this->waitForElementPresent("group_id");

    // add to group
    $this->select("group_id", "label=$groupName");
    $this->click("_qf_GroupContact_next");
    $this->waitForPageToLoad($this->getTimeoutMsec());

    $firstName2 = substr(sha1(rand()), 0, 7);
    $this->webtestAddContact($firstName2, "John", "$firstName2.john@example.org");

    // add contact to group
    // visit group tab
    $this->click("css=li#tab_group a");
    $this->waitForElementPresent("group_id");

    // add to group
    $this->select("group_id", "label=$groupName");
    $this->click("_qf_GroupContact_next");
    $this->waitForPageToLoad($this->getTimeoutMsec());

    // Enable CiviCampaign module if necessary
    $this->enableComponents("CiviCampaign");

    // add the required permission
    $this->changePermissions("edit-2-administer-civicampaign");

    // Log in as normal user
    $this->webtestLogin();

    $this->openCiviPage("campaign", "reset=1", "link=Add Campaign");

    if ($this->isTextPresent('No campaigns found.')) {
      $this->openCiviPage("participant/add", "reset=1&action=add&context=standalone", "_qf_Participant_upload-bottom");
      $this->assertTrue($this->isTextPresent('There are currently no active Campaigns.'));
    }
    $this->openCiviPage("campaign/add", "reset=1", "_qf_Campaign_upload-bottom");

    $campaignTitle = "Campaign $title";
    $this->type("title", $campaignTitle);

    // select the campaign type
    $this->select("campaign_type_id", "value=2");

    // fill in the description
    $this->type("description", "This is a test campaign");

    // include groups for the campaign
    $this->addSelection("includeGroups-f", "label=$groupName");
    $this->click("//option[@value=4]");
    $this->click("add");

    // fill the end date for campaign
    $this->webtestFillDate("end_date", "+1 year");

    // select campaign status
    $this->select("status_id", "value=2");

    // click save
    $this->click("_qf_Campaign_upload-bottom");
    $this->waitForPageToLoad($this->getTimeoutMsec());

    $this->assertTrue($this->isTextPresent("Campaign Campaign $title has been saved."),
      "Status message didn't show up after saving campaign!"
    );

    $this->waitForElementPresent("//div[@id='campaignList']/div[@class='dataTables_wrapper']/table/tbody/tr/td[text()='{$campaignTitle}']/../td[1]");
    $id = (int) $this->getText("//div[@id='campaignList']/div[@class='dataTables_wrapper']/table/tbody/tr/td[text()='{$campaignTitle}']/../td[1]");

    $this->offlineParticipantAddTest($campaignTitle, $id);
  }

  function offlineParticipantAddTest($campaignTitle, $id) {
    // connect campaign with event
    $this->openCiviPage("event/manage", "reset=1");
    $eventId = $this->registerUrl();

    $this->openCiviPage('event/manage/settings', "reset=1&action=update&id=$eventId", "_qf_EventInfo_cancel-bottom");

    // select campaign
    $this->click("campaign_id");
    $this->select("campaign_id", "value=$id");
    $this->click("_qf_EventInfo_upload_done-bottom");
    $this->waitForPageToLoad($this->getTimeoutMsec());

    // Adding contact with randomized first name (so we can then select that contact when creating event registration)
    // We're using Quick Add block on the main page for this.
    $firstName = substr(sha1(rand()), 0, 7);
    $this->webtestAddContact($firstName, "Anderson", TRUE);
    $contactName = "Anderson, $firstName";
    $displayName = "$firstName Anderson";

    $this->openCiviPage("participant/add", "reset=1&action=add&context=standalone", "_qf_Participant_upload-bottom");

    // Type contact last name in contact auto-complete, wait for dropdown and click first result
    $this->webtestFillAutocomplete($firstName);

    // Select event. Based on label for now.
    $this->select("event_id", "value=$eventId");

    // Select role
    $this->click("role_id[2]");

    // Choose Registration Date.
    // Using helper webtestFillDate function.
    $this->webtestFillDate('register_date', 'now');
    $today = date('F jS, Y', strtotime('now'));
    // May 5th, 2010

    // Select participant status
    $this->select("status_id", "value=1");

    // Setting registration source
    $this->type("source", "Event StandaloneAddTest Webtest");

    // Since we're here, let's check of screen help is being displayed properly
    $this->assertTrue($this->isTextPresent("Source for this registration (if applicable)."));

    // Select an event fee
    $this->waitForElementPresent('priceset');
    $this->click("xpath=//div[@id='priceset']//input[1][@class='form-radio']");

    // Select 'Record Payment'
    $this->click("record_contribution");

    // Enter amount to be paid (note: this should default to selected fee level amount, s/b fixed during 3.2 cycle)
    $this->type("total_amount", "800");

    // Select payment method = Check and enter chk number
    $this->select("payment_instrument_id", "value=4");
    $this->waitForElementPresent("check_number");
    $this->type("check_number", "1044");

    // go for the chicken combo (obviously)
    //      $this->click("CIVICRM_QFID_chicken_Chicken");

    // Clicking save.
    $this->click("_qf_Participant_upload-bottom");
    $this->waitForPageToLoad($this->getTimeoutMsec());

    // Is status message correct?
    $this->assertTrue($this->isTextPresent("Event registration for $displayName has been added"), "Status message didn't show up after saving!");

    $this->waitForElementPresent("xpath=//div[@id='Events']//table//tbody/tr[1]/td[8]/span/a[text()='View']");
    //click through to the participant view screen
    $this->click("xpath=//div[@id='Events']//table//tbody/tr[1]/td[8]/span/a[text()='View']");
    $this->waitForElementPresent("_qf_ParticipantView_cancel-bottom");

    // verify participant record
    $this->verifyText("xpath=id('ParticipantView')/div[2]/table[1]/tbody/tr[3]/td[2]", preg_quote($campaignTitle));

    $this->openCiviPage("admin/setting/component", "reset=1", "_qf_Component_next-bottom");
    $this->addSelection("enableComponents-t", "label=CiviCampaign");
    $this->click("//option[@value='CiviCampaign']");
    $this->click("remove");
    $this->click("_qf_Component_next-bottom");
    $this->waitForPageToLoad($this->getTimeoutMsec());
    $this->assertTrue($this->isTextPresent("Changes Saved."));

    $this->openCiviPage("event/search", "reset=1", "_qf_Search_refresh");

    $this->type('sort_name', $firstName);
    $this->click("_qf_Search_refresh");
    $this->waitForElementPresent("_qf_Search_next_print");
    $this->click("xpath=//div[@id='participantSearch']/table/tbody/tr/td[11]/span/a[text()='Edit']");
    $this->waitForElementPresent("_qf_Participant_cancel-bottom");
    $this->assertTrue($this->isTextPresent("$campaignTitle"));
  }

  function registerUrl() {
    $this->openCiviPage("event/manage", "reset=1");
    $eventId = explode('_', $this->getAttribute("//div[@id='event_status_id']//table/tbody/tr@id"));
    return $eventId[1];
  }
}

