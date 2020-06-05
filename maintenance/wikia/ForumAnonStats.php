<?php

require_once( __DIR__ . '/../Maintenance.php' );
class ForumAnonStats extends Maintenance {

	/**
	 * Do the actual work. All child classes will need to implement this
	 */
	public function execute() {
		global $wgCityId, $wgDBname;

		$dbr = wfGetDB( DB_SLAVE );

		$namespaces = [
			2000,
			2001,
			2002
		];

		$anonPostCount = $dbr->select(
			[ 'revision', 'page' ],
			[ 'count(*) as cnt' ],
			[
				'page_namespace' => $namespaces,
				'revision.rev_user' => 0,
				'revision.rev_parent_id' => 0
			],
			__METHOD__,
			[],
			[
				'page' => [ 'INNER JOIN', [ 'revision.rev_page = page.page_id' ] ]
			]
		)->fetchObject()->cnt;

		$totalPostCount = $dbr->select(
			[ 'revision', 'page' ],
			[ 'count(*) as cnt' ],
			[
				'page_namespace' => $namespaces,
				'revision.rev_parent_id' => 0
			],
			__METHOD__,
			[],
			[
				'page' => [ 'INNER JOIN', [ 'revision.rev_page = page.page_id' ] ]
			]
		)->fetchObject()->cnt;

		$dateFrom = $dbr->timestamp('2020-05-05T00:30:06Z');

		$anonPostCount30days = $dbr->select(
			[ 'revision', 'page' ],
			[ 'count(*) as cnt' ],
			[
				'page_namespace' => $namespaces,
				'revision.rev_user' => 0,
				'revision.rev_parent_id' => 0,
				'rev_timestamp > ' . $dateFrom
			],
			__METHOD__,
			[],
			[
				'page' => [ 'INNER JOIN', [ 'revision.rev_page = page.page_id' ] ]
			]
		)->fetchObject()->cnt;

		$totalPostCount30days = $dbr->select(
			[ 'revision', 'page' ],
			[ 'count(*) as cnt' ],
			[
				'page_namespace' => $namespaces,
				'revision.rev_parent_id' => 0,
				'rev_timestamp > ' . $dateFrom
			],
			__METHOD__,
			[],
			[
				'page' => [ 'INNER JOIN', [ 'revision.rev_page = page.page_id' ] ]
			]
		)->fetchObject()->cnt;

		echo("Result;${wgCityId};${wgDBname};${totalPostCount};${anonPostCount};${totalPostCount30days};${anonPostCount30days};\n");
	}
}
$maintClass = "ForumAnonStats";
require_once( RUN_MAINTENANCE_IF_MAIN );
