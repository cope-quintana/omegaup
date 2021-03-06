<?php

/**
 * Description of ListContests
 *
 * @author joemmanuel
 */

class ContestListTest extends OmegaupTestCase {
	
	private function assertTitleInList($response, $contestData, $inverse = false) {
	
		// Assert our contest is there
		$titles = array();
		foreach ($response['results'] as $entry) {
			$titles[] = $entry["title"];
		}
		
        $this->assertArrayHasKey("0", $response['results']);     
		
		if ($inverse === true) {
			$this->assertNotContains($contestData["request"]["title"], $titles);
		} else {		
			$this->assertContains($contestData["request"]["title"], $titles);
		}
	}
	
	/** 
	 * Basic test. Check that most recent contest is at the top of the list
	 */
	public function testLatestPublicContest() {
		
		$r = new Request();
		
		// Create new PUBLIC contest
		$contestData = ContestsFactory::createContest();
		
		// Log as a random contestant
		$contestant = UserFactory::createUser();
		$r["auth_token"] = $this->login($contestant);
		
		$response = ContestController::apiList($r);
		
		// Assert our contest is there.
		foreach ($response['results'] as $contest) {
			if ($contest['title'] == $contestData['request']['title']) {
				return;
			}
		}
		assertFalse(TRUE, 'Array does not contain created contest');
	}		
	
	
	/** 
	 * Basic test. Check that most recent contest is at the top of the list
	 */
	public function testLatestPublicContestNotLoggedIn() {
		
		$r = new Request();
		
		// Create new PUBLIC contest
		$contestData = ContestsFactory::createContest();						
		
		$response = ContestController::apiList($r);
				
		$this->assertTitleInList($response, $contestData);
	}	
	
	
	/** 
	 * 
	 */
	public function testPrivateContestForInvitedUser() {
		
		$r = new Request();
		
		// Create new private contest
		$contestData = ContestsFactory::createContest(null, false /*private*/);
		
		// Get a user for our scenario
		$contestant = UserFactory::createUser();

		// Add user to our private contest
		ContestsFactory::addUser($contestData, $contestant);
		
		$r["auth_token"] = $this->login($contestant);
		
		$response = ContestController::apiList($r);
		
		$this->assertTitleInList($response, $contestData);
	}
	
	/** 
	 * 
	 */
	public function testPrivateContestForNonInvitedUser() {
		
		$r = new Request();
		
		// Create new private contest
		$contestData = ContestsFactory::createContest(null, false /*private*/);
		
		// Get a user for our scenario
		$contestant = UserFactory::createUser();

		// Add user to our private contest
		ContestsFactory::addUser($contestData, $contestant);
		
		$r["auth_token"] = $this->login(UserFactory::createUser());
		
		$response = ContestController::apiList($r);
		
		// Assert our contest is not there
        $this->assertTitleInList($response, $contestData, true /*assertNoContains*/);	
	}
	
	
	/** 
	 * 
	 */
	public function testPrivateContestForSystemAdmin() {
		
		$r = new Request();
		
		// Create new private contest
		$contestData = ContestsFactory::createContest(null, false /*private*/);
				
		$r["auth_token"] = $this->login(UserFactory::createAdminUser());
		
		$response = ContestController::apiList($r);
		
		// Assert our contest is there
        $this->assertTitleInList($response, $contestData);
	}
	
	/** 
	 * 
	 */
	public function testPrivateContestForContestAdmin() {
		
		$r = new Request();
		
		// Create new private contest
		$contestData = ContestsFactory::createContest(null, false /*private*/);
		
		// Get a user for our scenario
		$contestant = UserFactory::createUser();

		
		// Add user to our private contest
		ContestsFactory::addAdminUser($contestData, $contestant);
						
		$r["auth_token"] = $this->login($contestant);
		
		$response = ContestController::apiList($r);
		
		// Assert our contest is there
        $this->assertTitleInList($response, $contestData);
	}
}

