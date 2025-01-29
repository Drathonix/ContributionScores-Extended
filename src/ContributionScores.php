<?php
/** \file
 * \brief Contains code for the ContributionScores Class (extends SpecialPage).
 */

use MediaWiki\MediaWikiServices;
use DairikiDiff;

/// Special page class for the Contribution Scores extension
/**
 * Special page that generates a list of wiki contributors based
 * on edit diversity (unique pages edited) and edit volume (total
 * number of edits.
 *
 * @ingroup Extensions
 * @author Tim Laqua <t.laqua@gmail.com>
 */
class ContributionScores extends IncludableSpecialPage {
	const CONTRIBUTIONSCORES_MAXINCLUDELIMIT = 50;

	public function __construct() {
		parent::__construct( 'ContributionScores' );
	}

	public static function onParserFirstCallInit( Parser $parser ) {
		$parser->setFunctionHook( 'cscore', [ self::class, 'efContributionScoresRender' ] );
	}
	

	# taken from: https://www.php.net/manual/en/function.sort.php
	private static function array_sort($array, $on, $order=SORT_ASC)
	{
		$new_array = array();
		$sortable_array = array();

		if (count($array) > 0) {
			foreach ($array as $k => $v) {
				if (is_array($v)) {
					foreach ($v as $k2 => $v2) {
						if ($k2 == $on) {
							$sortable_array[$k] = $v2;
						}
					}
				} else {
					$sortable_array[$k] = $v;
				}
			}

			switch ($order) {
				case SORT_ASC:
					asort($sortable_array);
				break;
				case SORT_DESC:
					arsort($sortable_array);
				break;
			}

			foreach ($sortable_array as $k => $v) {
				$new_array[$k] = $array[$k];
			}
		}

		return $new_array;
	}
	
	private static function migrate($dbr, $user, $where = []){
		$migrate = ActorMigration::newMigration()->getWhere( $dbr, 'rev_user', $user);
		foreach ( $where as $cond ) {
			$migrate['conds'] = $migrate['conds'] . ' AND (' . $cond . ')';			
		}		
		return $migrate;
	}

	public static function computeAbsDiff($dbr,$user,$where = []){
		$revisionLookup = MediaWikiServices::getInstance()->getRevisionLookup();
		# Collect all revisions by the target user
		$migrate = self::migrate($dbr,$user,$where);
		$resultSet = $dbr->select(
			[ 'revision' ] + $migrate['tables'],
			["rev_id", "rev_parent_id"] ,
			$migrate['conds'],
			__METHOD__,
			$migrate['joins'],
		);
		$output = 0;
		# Calculate abs diff.
		foreach ( $resultSet as $row ){
			# Gets the user's revision record and its content object.
			$userRevisionRecord = $revisionLookup->getRevisionById($row->rev_id);
			$userContent = $userRevisionRecord->getContent("main");			
			# Get the parent revision content object for diff calc.
			$parentRevision = $revisionLookup->getRevisionById( $userRevisionRecord->getParentId());
			$diff;
			if( $parentRevision != null ) {
				$parentContent = $parentRevision->getContent("main");
				$diff = $userContent->diff($parentContent);
			}
			# Indicates page was created.
			else{
				$diff = new Diff([$userContent->serialize()],[""]);
			}
			# Calculate absdiff for each edit.
			foreach( $diff->getEdits() as $op) {
				if($op->getType() == 'change') {
					$len = min($op->nclosing(),$op->norig());
					for($x = 0; $x < $len; $x++){
						$output = $output + abs( strlen( $op->getClosing()[$x] )- strlen( $op->getOrig()[$x] ));
					}
				}
			}
		
		}
		return $output;
	}
	
	public static function computeUniquePages($dbr, $user, $where = []){
		$migrate = self::migrate($dbr,$user,$where);
		$row = $dbr->selectRow(
			[ 'revision' ] + $migrate['tables'],
			[ 'page_count' => 'COUNT(DISTINCT rev_page)' ],
			$migrate['conds'],
			__METHOD__,
			[],
			$migrate['joins']
		);
		return $row->page_count;
	}
	
	public static function computeCreatedPages($dbr, $user, $where = []){
		array_push($where,'(rev_parent_id IS NULL OR rev_parent_id = 0)');
		$migrate = self::migrate($dbr,$user,$where);
		$table = $dbr->select(
			[ 'revision' ] + $migrate['tables'],
			[ 'rev_page', 'rev_parent_id'],
			$migrate['conds'],
			__METHOD__,
			[],
			$migrate['joins']
		);
		return count($table);
	}
	
	public static function computeChanges($dbr, $user, $where = []){
		global $wgContribScoreUseRoughEditCount;
		$migrate = self::migrate($dbr,$user,$where);
		$revVar = $wgContribScoreUseRoughEditCount ? 'user_editcount' : 'COUNT(rev_id)';
		$row = $dbr->selectRow(
			[ 'revision' ] + $migrate['tables'],
			[ 'rev_count' => $revVar ],
			$migrate['conds'],
			__METHOD__,
			[],
			$migrate['joins']
		);
		return $row->rev_count;
	}	
	
	public static function computeScore($dbr, $user, $revWhere = []){
		return ContributionScores::computeUniquePages($dbr,$user,$revWhere)*2 + ContributionScores::computeAbsDiff($dbr,$user,$revWhere)/100;
	}
	

	public static function efContributionScoresRender( $parser, $usertext, $metric = 'score' ) {
		global $wgContribScoreDisableCache, $wgContribScoreUseRoughEditCount;

		if ( $wgContribScoreDisableCache ) {
			$parser->getOutput()->updateCacheExpiry( 0 );
		}

		$user = User::newFromName( $usertext );
		$loadBalancer = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $loadBalancer->getConnection( DB_REPLICA );

		if ( $user instanceof User && $user->isRegistered() ) {
			global $wgLang;

			$revWhere = "";
			if ( $metric == 'score' ) {
				$output = $wgLang->formatNum( round( self::computeScore( $dbr, $user) ) );
			} elseif ( $metric == 'changes' ) {
				$output = $wgLang->formatNum( self::computeChanges( $dbr, $user) );
			} elseif ( $metric == 'pages' ) {
				$output = $wgLang->formatNum( self::computeUniquePages( $dbr, $user) );
			} elseif ( $metric == 'creations' ) {
				$output = $wgLang->formatNum( self::computeCreatedPages( $dbr, $user) );
			} elseif ( $metric == 'absdiff') {
				$output = $wgLang->formatNum( self::computeAbsDiff( $dbr, $user) );
			} else {
				$output = wfMessage( 'contributionscores-invalidmetric' )->text();
			}
		} else {
			$output = wfMessage( 'contributionscores-invalidusername' )->text();
		}
		return $parser->insertStripItem( $output, $parser->getStripState() );
	}
	/**
	 * Function fetch Contribution Scores data from database
	 *
	 * @param int $days Days in the past to run report for
	 * @param int $limit Maximum number of users to return (default 50)
	 * @return array Data including the requested Contribution Scores.
	 */
	public static function getContributionScoreData( $days = 0, $limit = 50) {
		global $wgContribScoreIgnoreBots, $wgContribScoreIgnoreBlockedUsers, $wgContribScoreIgnoreUsernames;
		
		$loadBalancer = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $loadBalancer->getConnection( DB_REPLICA );
		$userQuery = $dbr->newSelectQueryBuilder()
			->select('user_id','user_name')
			->from('user')
			->where('user_editcount > 0');
	

		if ( $wgContribScoreIgnoreBlockedUsers ) {
			$userQuery = $userQuery
				->where("user_id NOT IN (SELECT ipb_user FROM `ipblocks` WHERE user_id = ipb_user)"
			);
		}

		$timeShift = $days*24*60*60*1000;

		if ( $wgContribScoreIgnoreBots ) {
			$userQuery = $userQuery
				->where("user_id NOT IN (SELECT ug_user FROM `user_groups` WHERE (ug_group = 'bot' AND (ug_expiry IS NULL OR ug_expiry >= " . $dbr->addQuotes( $dbr->timestamp(time()-$timeShift))  . ")))"
			);
		}

		if ( count( $wgContribScoreIgnoreUsernames ) > 0) {
			$listIgnoredUsernames = $dbr->makeList( $wgContribScoreIgnoreUsernames );
			$userQuery = $userQuery
				->where("{user_name} NOT IN ($listIgnoredUsernames)" 
			);
		}
		
		$users = $userQuery->caller(__METHOD__)->fetchResultSet();
		echo "L: " . $dbr->timestamp();
		$revWhere = ["rev_timestamp >= " . ($dbr->timestamp(time()-$timeShift)) ];

		$scoreTable = [];
		$k = 0;
		foreach($users as $row){
			$user = User::newFromId($row->user_id);
			$user_score = self::computeScore( $dbr, $user, $revWhere );
			if ( $k < $limit ) {
				$entry = [];
				$entry["user_id"]=$user_id;
				$entry["user_name"]=$user->getName();
				$entry["user_real_name"]=$user->getRealName();
				$entry["page_count"]=self::computeUniquePages( $dbr, $user, $revWhere);
				$entry["rev_count"]=self::computeChanges( $dbr, $user, $revWhere);
				$entry["wiki_rank"]=$user_score;
				$entry["absdiff"]=self::computeAbsDiff( $dbr, $user, $revWhere);
				$scoreTable[$k]= $entry;
				$k++;
				if( $k >= $limit ) {
					$scoreTable = self::array_sort($scoreTable, 'wiki_rank', SORT_DESC);
				}
			}
			else if ( $score_table[$limit-1] < $user_score) {
				$entry = [];
				$entry["user_id"]=$user_id;
				$entry["user_name"]=$user->getName();
				$entry["user_real_name"]=$user->getRealName();
				$entry["page_count"]=self::computeUniquePages( $dbr, $user, $revWhere);
				$entry["rev_count"]=self::computeChanges( $dbr, $user, $revWhere);
				$entry["wiki_rank"]=$user_score;
				$entry["absdiff"]=self::computeAbsDiff( $dbr, $user, $revWhere);
				$scoreTable[$limit - 1] = $entry;
				$scoreTable = self::array_sort($scoreTable, 'wiki_rank', SORT_DESC);
			}
		}
		if ( $k < $limit ) {
			$scoreTable = self::array_sort($scoreTable, 'wiki_rank', SORT_DESC);
		}
		return $scoreTable;
	}

	/// Generates a "Contribution Scores" table for a given LIMIT and date range

	/**
	 * Function generates Contribution Scores tables in HTML format (not wikiText)
	 *
	 * @param int $days Days in the past to run report for
	 * @param int $limit Maximum number of users to return (default 50)
	 * @param string|null $title The title of the table
	 * @param array $options array of options (default none; nosort/notools)
	 * @return string Html Table representing the requested Contribution Scores.
	 */
	function genContributionScoreTable( $days, $limit, $title = null, $options = 'none' ) {
		global $wgContribScoresUseRealName, $wgContribScoreCacheTTL;

		$opts = explode( ',', strtolower( $options ) );

		$sortable = in_array( 'nosort', $opts ) ? '' : ' sortable';

		$output = "<table class=\"wikitable contributionscores plainlinks{$sortable}\" >\n" .
			"<tr class='header'>\n" .
			Html::element( 'th', [], $this->msg( 'contributionscores-rank' )->text() ) .
			Html::element( 'th', [], $this->msg( 'contributionscores-score' )->text() ) .
			Html::element( 'th', [], $this->msg( 'contributionscores-absdiff' )->text() ) .
			Html::element( 'th', [], $this->msg( 'contributionscores-pages' )->text() ) .
			Html::element( 'th', [], $this->msg( 'contributionscores-changes' )->text() ) .
			Html::element( 'th', [], $this->msg( 'contributionscores-username' )->text() );

		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$data = $cache->getWithSetCallback(
			$cache->makeKey( 'contributionscores', 'data-' . (string)$days ),
			$wgContribScoreCacheTTL * 60,
			function () use ( $days ) {
				// Use max limit, as limit doesn't matter with performance.
				// Avoid purge multiple times since limit on transclusion can be vary.
				return self::getContributionScoreData( $days, self::CONTRIBUTIONSCORES_MAXINCLUDELIMIT );
			} );

		$lang = $this->getLanguage();

		$altrow = '';
		$user_rank = 1;

		foreach ( $data as $row ) {
			if ( $user_rank > $limit ) {
				break;
			}

			// Use real name if option used and real name present.
			if ( $wgContribScoresUseRealName && $row["user_real_name"] !== '' ) {
				$userLink = Linker::userLink(
					$row["user_id"],
					$row["user_name"],
					$row["user_real_name"]
				);
			} else {
				$userLink = Linker::userLink(
					$row["user_id"],
					$row["user_name"]
				);
			}

			$output .= Html::closeElement( 'tr' );
			$output .= "<tr class='{$altrow}'>\n" .
				"<td class='content' style='padding-right:10px;text-align:right;'>" .
				$lang->formatNum( $user_rank ) .
				"\n</td><td class='content' style='padding-right:10px;text-align:right;'>" .
				$lang->formatNum( round( $row["wiki_rank"], 0 ) ) .
				"\n</td><td class='content' style='padding-right:10px;text-align:right;'>" .
				$lang->formatNum( $row["absdiff"] ) .
				"\n</td><td class='content' style='padding-right:10px;text-align:right;'>" .
				$lang->formatNum( $row["page_count"] ) .
				"\n</td><td class='content' style='padding-right:10px;text-align:right;'>" .
				$lang->formatNum( $row["rev_count"] ) .
				"\n</td><td class='content'>" .
				$userLink;

			# Option to not display user tools
			if ( !in_array( 'notools', $opts ) ) {
				$output .= Linker::userToolLinks( $row["user_id"], $row["user_name"] );
			}

			$output .= Html::closeElement( 'td' ) . "\n";

			if ( $altrow == '' && empty( $sortable ) ) {
				$altrow = 'odd ';
			} else {
				$altrow = '';
			}

			$user_rank++;
		}
		$output .= Html::closeElement( 'tr' );
		$output .= Html::closeElement( 'table' );

		// Transcluded on a normal wiki page.
		if ( !empty( $title ) ) {
			$output = Html::rawElement( 'table',
				[
					'style' => 'border-spacing: 0; padding: 0',
					'class' => 'contributionscores-wrapper',
					'lang' => htmlspecialchars( $lang->getCode() ),
					'dir' => $lang->getDir()
				],
				"\n" .
				"<tr>\n" .
				"<td style='padding: 0px;'>{$title}</td>\n" .
				"</tr>\n" .
				"<tr>\n" .
				"<td style='padding: 0px;'>{$output}</td>\n" .
				"</tr>\n"
			);
		}

		return $output;
	}

	function execute( $par ) {
		$this->setHeaders();

		if ( $this->including() ) {
			$this->showInclude( $par );
		} else {
			$this->showPage();
		}

		return true;
	}

	/**
	 * Called when being included on a normal wiki page.
	 * Cache is disabled so it can depend on the user language.
	 * @param string|null $par A subpage give to the special page
	 */
	function showInclude( $par ) {
		$days = null;
		$limit = null;
		$options = 'none';

		if ( !empty( $par ) ) {
			$params = explode( '/', $par );

			$limit = intval( $params[0] );

			if ( isset( $params[1] ) ) {
				$days = intval( $params[1] );
			}

			if ( isset( $params[2] ) ) {
				$options = $params[2];
			}
		}

		if ( empty( $limit ) || $limit < 1 || $limit > self::CONTRIBUTIONSCORES_MAXINCLUDELIMIT ) {
			$limit = 10;
		}
		if ( $days === null || $days < 0 ) {
			$days = 7;
		}

		if ( $days > 0 ) {
			$reportTitle = $this->msg( 'contributionscores-days' )->numParams( $days )->text();
		} else {
			$reportTitle = $this->msg( 'contributionscores-allrevisions' )->text();
		}
		$reportTitle .= ' ' . $this->msg( 'contributionscores-top' )->numParams( $limit )->text();
		$title = Xml::element( 'h4',
				[ 'class' => 'contributionscores-title' ],
				$reportTitle
			) . "\n";
		$this->getOutput()->addHTML( $this->genContributionScoreTable(
			$days,
			$limit,
			$title,
			$options
		) );
	}

	/**
	 * Show the special page
	 */
	function showPage() {
		global $wgContribScoreReports;

		if ( !is_array( $wgContribScoreReports ) ) {
			$wgContribScoreReports = [
				[ 7, 50 ],
				[ 30, 50 ],
				[ 0, 50 ]
			];
		}

		$out = $this->getOutput();
		$out->addWikiMsg( 'contributionscores-info' );

		foreach ( $wgContribScoreReports as $scoreReport ) {
			[ $days, $revs ] = $scoreReport;
			if ( $days > 0 ) {
				$reportTitle = $this->msg( 'contributionscores-days' )->numParams( $days )->text();
			} else {
				$reportTitle = $this->msg( 'contributionscores-allrevisions' )->text();
			}
			$reportTitle .= ' ' . $this->msg( 'contributionscores-top' )->numParams( $revs )->text();
			$title = Xml::element( 'h2',
					[ 'class' => 'contributionscores-title' ],
					$reportTitle
				) . "\n";
			$out->addHTML( $title );
			$out->addHTML( $this->genContributionScoreTable( $days, $revs ) );
		}
	}

	public function maxIncludeCacheTime() {
		global $wgContribScoreDisableCache, $wgContribScoreCacheTTL;
		return $wgContribScoreDisableCache ? 0 : $wgContribScoreCacheTTL;
	}

	/**
	 * @inheritDoc
	 */
	protected function getGroupName() {
		return 'wiki';
	}
}
