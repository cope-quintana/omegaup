<?php

/**
 * Description of ClarificationController
 *
 * @author joemmanuel
 */
class ClarificationController extends Controller {

	public static $broadcaster = null;

	/**
	 * Creates an instance of Broadcaster if not already created
	 */
	private static function initializeBroadcaster() {
		if (is_null(self::$broadcaster)) {
			// Create new grader
			self::$broadcaster = new Broadcaster();
		}
	}

	/**
	 * Validate the request of apiCreate
	 * 
	 * @param Request $r
	 * @throws InvalidDatabaseOperationException
	 * @throws NotFoundException
	 */
	private static function validateCreate(Request $r) {

		Validators::isStringNonEmpty($r["contest_alias"], "contest_alias");
		Validators::isStringNonEmpty($r["problem_alias"], "problem_alias");
		Validators::isStringNonEmpty($r["message"], "message");

		try {
			$r["contest"] = ContestsDAO::getByAlias($r["contest_alias"]);
			$r["problem"] = ProblemsDAO::getByAlias($r["problem_alias"]);
		} catch (Exception $e) {
			throw new InvalidDatabaseOperationException($e);
		}

		if (is_null($r["contest"])) {
			throw new NotFoundException("contestNotFound");
		}

		if (is_null($r["problem"])) {
			throw new NotFoundException("problemNotFound");
		}

		// Is the combination contest_id and problem_id valid?
		if (is_null(ContestProblemsDAO::getByPK($r["contest"]->getContestId(), $r["problem"]->getProblemId()))) {
			throw new NotFoundException("problemNotFoundInContest");
		}
	}

	/**
	 * Creates a Clarification
	 * 
	 * @param Request $r
	 * @return array
	 * @throws InvalidDatabaseOperationException
	 */
	public static function apiCreate(Request $r) {
		// Authenticate user
		self::authenticateRequest($r);

		// Validate request
		self::validateCreate($r);

		$response = array();

		$time = time();
		$r['clarification'] = new Clarifications(array(
			'author_id' => $r['current_user_id'],
			'contest_id' => $r['contest']->getContestId(),
			'problem_id' => $r['problem']->getProblemId(),
			'message' => $r['message'],
			'time' => gmdate('Y-m-d H:i:s', $time),
			'public' => '0'
		));

		// Insert new Clarification
		try {
			// Save the clarification object with data sent by user to the database
			ClarificationsDAO::save($r['clarification']);
		} catch (Exception $e) {
			// Operation failed in the data layer
			throw new InvalidDatabaseOperationException($e);
		}

		$r['user'] = $r['current_user'];
		self::clarificationUpdated($r, $time);

		$response["clarification_id"] = $r['clarification']->clarification_id;
		$response["status"] = "ok";

		return $response;
	}

	/**
	 * Validate Details API request
	 * 
	 * @param Request $r
	 * @throws InvalidDatabaseOperationException
	 * @throws NotFoundException
	 * @throws ForbiddenAccessException
	 */
	private static function validateDetails(Request $r) {
		Validators::isNumber($r["clarification_id"], "clarification_id");

		// Check that the clarification actually exists
		try {
			$r["clarification"] = ClarificationsDAO::getByPK($r["clarification_id"]);
		} catch (Exception $e) {
			throw new InvalidDatabaseOperationException($e);
		}

		if (is_null($r["clarification"])) {
			throw new NotFoundException("clarificationNotFound");
		}

		// If the clarification is private, verify that our user is invited or is contest director 
		if ($r["clarification"]->public != 1) {
			if (!(Authorization::CanViewClarification($r["current_user_id"], $r["clarification"]))) {
				throw new ForbiddenAccessException();
			}
		}
	}

	/**
	 * API for getting a clarification
	 * 
	 * @param Request $r
	 * @return array
	 */
	public static function apiDetails(Request $r) {
		// Authenticate the user
		self::authenticateRequest($r);

		// Validate request
		self::validateDetails($r);

		// Create array of relevant columns
		$relevant_columns = array("message", "answer", "time", "problem_id", "contest_id");

		// Add the clarificatoin the response
		$response = $r["clarification"]->asFilteredArray($relevant_columns);
		$response["status"] = "ok";

		return $response;
	}

	/**
	 * Validate update API request
	 * 
	 * @param Request $r
	 * @throws InvalidDatabaseOperationException
	 * @throws ForbiddenAccessException
	 */
	private static function validateUpdate(Request $r) {

		Validators::isNumber($r["clarification_id"], "clarificaion_id");
		Validators::isStringNonEmpty($r["answer"], "answer", false /* not required */);
		Validators::isInEnum($r["public"], "public", array('0', '1'), false /* not required */);
		Validators::isStringNonEmpty($r["message"], "message", false /* not required */);

		// Check that clarification exists
		try {
			$r['clarification'] = ClarificationsDAO::GetByPK($r["clarification_id"]);
		} catch (Exception $e) {
			throw new InvalidDatabaseOperationException($e);
		}

		if (!Authorization::CanEditClarification($r["current_user_id"], $r["clarification"])) {
			throw new ForbiddenAccessException();
		}
	}

	/**
	 * Update a clarification
	 * 
	 * @param Request $r
	 * @return array
	 * @throws InvalidDatabaseOperationException
	 */
	public static function apiUpdate(Request $r) {
		// Authenticate user
		self::authenticateRequest($r);

		// Validate request 
		self::validateUpdate($r);

		// Update clarification
		$valueProperties = array(
			"message",
			"answer",
			"public",
		);
		$clarification = $r['clarification'];
		self::updateValueProperties($r, $clarification, $valueProperties);
		$r['clarification'] = $clarification;

		// Let DB handle time update
		$time = time();
		$clarification->time = gmdate('Y-m-d H:i:s', $time);

		// Save the clarification
		try {
			ClarificationsDAO::save($clarification);
		} catch (Exception $e) {
			// Operation failed in the data layer
			throw new InvalidDatabaseOperationException($e);
		}

		$r['problem'] = $r['contest'] = $r['user'] = null;
		self::clarificationUpdated($r, $time);

		$response = array();
		$response["status"] = "ok";

		return $response;
	}

	private static function clarificationUpdated(Request $r, $time) {
		try {
			if (is_null($r['problem'])) {
				$r['problem'] = ProblemsDAO::GetByPK($r['clarification']->problem_id);
			}
			if (is_null($r['contest']) && !is_null($r['clarification']->contest_id)) {
				$r['contest'] = ContestsDAO::GetByPK($r['clarification']->contest_id);
			}
			if (is_null($r['user'])) {
				$r['user'] = UsersDAO::GetByPK($r['clarification']->author_id);
			}
		} catch (Exception $e) {
			self::$log->error("Failed to broadcast clarification: " . $e);
			return;
		}
		self::$broadcaster->broadcastClarification($r, $time);
	}
}
