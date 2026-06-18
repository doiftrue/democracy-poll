<?php

use DemocracyPoll\Helpers\Helpers;
use DemocracyPoll\Poll_Answer;
use DemocracyPoll\Poll_Storage;
use DemocracyPoll\Poll_User_State;
use function DemocracyPoll\options;

/**
 * Display and vote a separate poll.
 *
 * @property string $voted_for      Voted for answers IDs, separated by commas.
 * @property bool   $has_voted      Is the user has voted?
 * @property bool   $voting_blocked Is the voting blocked? If true, the user cannot vote.
 * @property bool   $blocked_by_not_logged Is blocked because only logged users can vote.
 *
 * @property-read Poll_Answer[] $answers  Answers of the poll, sorted by order.
 *
 * @property string    $votedFor         Legacy. Alias of $voted_for.
 * @property bool      $blockVoting      Legacy. Alias of $voting_blocked.
 * @property-read bool $blockForVisitor  Legacy.
 */
class DemPoll {

	use DemPoll__Legacy;

	public Poll_User_State $user_state; /* readonly */

	public Poll_Storage $storage; /* readonly */

	/** Poll data from DB */
	public ?object $dbdata = null;

	/**
	 * Lazy loaded property.
	 * @see self::set_answers()
	 * @var Poll_Answer[]
	 */
	private array $answers;

	/// DB Fields

	/** Poll ID (DB Field) */
	public int $id = 0;

	/** Poll title (DB Field) */
	public string $question = '';

	/** Added UNIX timestamp (DB Field) */
	public int $added = 0;

	/** End UNIX timestamp (DB Field) */
	public int $end = 0;

	/** User ID (DB Field) */
	public int $added_user = 0;

	/** How many users voted for this poll (DB Field) */
	public int $users_voted = 0;

	/** Is this poll democratic? (DB Field) */
	public bool $democratic = false;

	/** Is this poll active? (DB Field) */
	public bool $active = false;

	/** Is this poll open for voting? (DB Field) */
	public bool $open = false;

	/** How many answers may be selected. (DB Field) */
	public int $multiple = 0;

	/** For logged users only (DB Field) */
	public bool $forusers = false;

	/** Allow to revote (DB Field) */
	public bool $revote = false;

	/** Show results after voting (DB Field) */
	public bool $show_results = false;

	/** Answers order: 'by_winner', 'by_id', 'alphabet', 'mix'. {@see Helpers::allowed_answers_orders()} (DB Field) */
	public string $answers_order = '';

	/** Comma separated posts_ids. Eg: '16865,16892' (DB Field) */
	public string $in_posts = '';

	/** Additional poll notes (DB Field) */
	public string $note = '';

	public function __isset( $name ) {
		// Required props (canNOT be not set)
		$lazy_props = [
			'answers',
			'voting_blocked',
			'voted_for',
			'has_voted',
			'blocked_by_not_logged',
			// legacy
			'blockVoting',
			'votedFor',
			'blockForVisitor',
		];

		if( in_array( $name, $lazy_props, true ) ){
			$this->__get( $name );
			return true;
		}

		return $this->$name !== null;
	}

	/**
	 * Handles properties lazy-load.
	 */
	public function __get( $name ) {
		if( 'answers' === $name ){
			isset( $this->answers ) || $this->set_answers();
			return $this->answers;
		}

		/**
		 * @see self::$blockVoting
		 * @see self::$voting_blocked
		 */
		if( 'voting_blocked' === $name || 'blockVoting' === $name ){
			return $this->user_state->voting_blocked();
		}

		/**
		 * @see self::$votedFor
		 * @see self::$voted_for
		 */
		if( in_array( $name, [ 'voted_for', 'votedFor' ], true ) ){
			return $this->user_state->voted_for();
		}

		/** @see self::$has_voted */
		if( 'has_voted' === $name ){
			return $this->user_state->has_voted();
		}

		/**
		 * @see self::$blocked_by_not_logged
		 * @see self::$blockForVisitor
		 */
		if( in_array( $name, [ 'blocked_by_not_logged', 'blockForVisitor' ], true ) ){
			return $this->user_state->blocked_by_not_logged();
		}

		return null;
	}

	public function __set( $name, $value ) {
		if( in_array( $name, [ 'voting_blocked', 'blockVoting' ], true ) ){
			$this->user_state->set_voting_blocked( (bool) $value );
		}
		elseif( in_array( $name, [ 'voted_for', 'votedFor' ], true ) ){
			$this->user_state->set_voted_for( (string) $value );
		}
		elseif( 'has_voted' === $name ){
			$this->user_state->set_has_voted( (bool) $value );
		}
		elseif( in_array( $name, [ 'blocked_by_not_logged', 'blockForVisitor' ], true ) ){
			$this->user_state->set_blocked_by_not_logged( (bool) $value );
		}
		else {
			throw new RuntimeException( __CLASS__ . " class prohibits setting dynamic properties. You are trying to set `$name`." );
		}
	}

	/**
	 * @param object|int $poll_id  Poll ID to get. OR poll object from DB.
	 */
	public function __construct( $poll_id ) {
		$this->storage    = new Poll_Storage( $this );
		$this->user_state = new Poll_User_State( $this );

		if( ! $poll_id ){
			return;
		}

		is_object( $poll_id )  && $this->dbdata = $poll_id;
		is_numeric( $poll_id ) && $this->dbdata = Poll_Storage::get_db_data( $poll_id );
		if( empty( $this->dbdata->id ) ){
			return;
		}

		$this->id            = (int) $this->dbdata->id;
		$this->question      = (string) $this->dbdata->question;
		$this->added         = (int) $this->dbdata->added;
		$this->added_user    = (int) $this->dbdata->added_user;
		$this->end           = (int) $this->dbdata->end;
		$this->users_voted   = (int) $this->dbdata->users_voted;
		$this->democratic    = (bool) ( options()->democracy_off ? false : $this->dbdata->democratic );
		$this->active        = (bool) $this->dbdata->active;
		$this->open          = (bool) $this->dbdata->open;
		$this->multiple      = (int) $this->dbdata->multiple;
		$this->forusers      = (bool) $this->dbdata->forusers;
		$this->revote        = (bool) ( options()->keep_logs && ! options()->revote_off && $this->dbdata->revote );
		$this->show_results  = (bool) $this->dbdata->show_results;
		$this->answers_order = (string) $this->dbdata->answers_order;
		$this->in_posts      = (string) $this->dbdata->in_posts;
		$this->note          = $this->dbdata->note;

		$this->storage->close_if_expired(); // TODO: move out from constructor
	}

	/**
	 * Gets {@see self::$answers} from DB data.
	 */
	public function set_answers(): void {
		$this->answers = $this->storage->get_answers();
	}

}

trait DemPoll__Legacy {

	/**
	 * @param int|string $poll_id Poll id to get. Specify "rand" or "last" for a random or last poll.
	 */
	public static function get_db_data( $poll_id ): ?object {
		return Poll_Storage::get_db_data( $poll_id );
	}

}
