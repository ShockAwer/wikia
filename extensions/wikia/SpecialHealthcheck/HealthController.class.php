<?php

/**
 * Extra health checks controller.
 *
 * Currently supports requests for getting the status of database clusters
 */
class HealthController extends WikiaController {

	private $current = '';

	private $clusters = [];
	private $errors = [];
	private $messages = [];
	private $status = [];
	private $servers = [];

	/**
	 * Get status of database clusters
	 *
	 * Returns:
	 * - clusters: associative array of cluster statuses
	 *   cluster name is the key and one of "ok", "warning", "critical" as a value
	 * - messages: list of notices
	 * - errors: list of errors
	 */
	public function databases() {
		global $wgWikiaDatacenter;

		$cluster = $this->getVal( 'cluster' );
		$clusters = $this->getAllClusters();
		if ( $cluster ) {
			$clusters = array_intersect( [ $cluster ], $clusters );
		}

		$this->clusters = $clusters;

		$this->testClusters();

		$this->setVal( 'clusters', $this->status );
		if ( $this->errors ) {
			$this->setVal( 'errors', $this->errors );
		}
		if ( $this->messages ) {
			$this->setVal( 'messages', $this->messages );
		}

		if ( $this->servers ) {
			$this->setVal( 'servers', $this->servers );
		}

		$this->setVal( 'readWrite', [
			'status' => !wfReadOnly(),
			'reason' => wfReadOnlyReason(),
			'datacenter' => $wgWikiaDatacenter,
		] );
	}

	/**
	 * Execute checks for all requested clusters
	 */
	private function testClusters() {
		foreach ( $this->clusters as $clusterName ) {
			$this->current = "{$clusterName}: ";

			$fullHealth = false;
			$operational = false;
			try {
				$databaseName = $this->getClusterDatabase( $clusterName );
				$loadBalancer = $this->getClusterLB( $clusterName, $databaseName );
				$serverCount = $loadBalancer->getServerCount();

				$roles = [
					'master' => [],
					'slave' => [],
				];
				for ( $i = 0; $i < $serverCount; $i++ ) {
					$serverName = $loadBalancer->getServerName( $i );
					$this->current = "{$clusterName}: {$serverName}: ";
					$this->servers[$clusterName][] = $serverName;

					$role = $i === 0 ? 'master' : 'slave';
					$roles[$role][$serverName] = $this->testHost( $databaseName, $loadBalancer, $i );
				}

				if ( $serverCount == 1 ) {
					$fullHealth = $operational = reset( $roles['master'] );
				} else {
					// full health = no host raised any issue
					$fullHealth = !$this->occursInArray( false, $roles['master'] )
						&& !$this->occursInArray( false, $roles['slave'] );
					// operational = at least one master and one slave are working correctly
					$operational = $this->occursInArray( true, $roles['master'] )
						&& $this->occursInArray( true, $roles['slave'] );
				}
			} catch ( DBError $e ) {
				$this->addError( $e->getMessage() );
			}

			$this->status[$clusterName] = $fullHealth ? 'ok' : ( $operational ? 'warning' : 'critical' );
		}
	}

	/**
	 * Check if needle exists in the array. Return true or false.
	 *
	 * @param mixed $needle Element to be searched for
	 * @param array $array Array
	 * @return bool True if needle exists in the array
	 */
	private function occursInArray( $needle, $array ) {
		return array_search( $needle, $array ) !== false;
	}

	/**
	 * Execute lag check on a given database
	 * @param LoadBalancer $loadBalancer Load Balancer instance for the given cluster
	 * @param DatabaseBase $db connector to a tested database
	 * @param int $index Server index to test
	 * @return bool Is lag below defined limit?
	 */
	private function testHostLag( LoadBalancer $loadBalancer, DatabaseBase $db, $index ) {
		$serverInfo = $loadBalancer->getServerInfo( $index );
		$master = $index == 0;

		if ( !$master && isset( $serverInfo['max lag'] ) ) {
			try {
				$maxLag = $serverInfo['max lag'];
				$lag = $db->getLag();
				if ( $lag > $maxLag ) {
					$this->addError( "lag (%d) is greater than configured \"max lag\" (%d)", $lag, $maxLag );

					return false;
				}
			} catch ( DBError $e ) {
				$this->addError( "could not fetch lag time" );

				return false;
			}
		}

		return true;
	}

	/**
	 * Execute R/W check on a given database
	 * @param DatabaseBase $db connector to a tested database
	 * @param int $index Server index to test
	 * @return bool Is R/W flag is set properly on a database?
	 */
	private function testHostRW( DatabaseBase $db, $index ) {
		$master = $index == 0;

		try {
			$res = $db->query( "SHOW VARIABLES LIKE 'read_only';" );
			$row = $res->fetchRow();
			$res->free();
			$readWrite = $row['Value'] != 'ON';
			if ( $master && !$readWrite ) {
				$this->addMessage( "read_only is set on master host" );
			} else if ( !$master && $readWrite ) {
				$this->addMessage( "read_only is unset on slave host" );
			}
		} catch ( DBError $e ) {
			$this->addError( "could not check read_only flag" );
			$db->close();

			return false;
		}

		return true;
	}

	/**
	 * Execute checks for a single database server
	 * @param string $databaseName Database name to use for connection
	 * @param LoadBalancer $loadBalancer Load Balancer instance for the given cluster
	 * @param int $index Server index to test
	 * @return bool Is server healthy?
	 * @throws MWException
	 */
	private function testHost( $databaseName, LoadBalancer $loadBalancer, $index ) {
		// connection check
		try {
			$db = $loadBalancer->getConnection( $index, array(), $databaseName );
		} catch ( DBError $e ) {
			$this->addError( "could not connect to server: " . $e->getMessage() );

			return false;
		}

		// lag check && read_only check on master
		if ( !$this->testHostLag( $loadBalancer, $db, $index ) ||
			 !$this->testHostRW( $db, $index )
		) {
			$db->close();
			return false;
		}

		$db->close();

		return true;
	}

	/**
	 * Add notice message to be returned.
	 *
	 * @param string $message Message (mimics sprintf() behavior)
	 */
	private function addMessage( $message ) {
		if ( func_num_args() > 1 ) {
			$message = call_user_func_array( 'sprintf', func_get_args() );
		}
		$this->messages[] = $this->current . $message;
	}

	/**
	 * Add error message to be returned.
	 *
	 * @param string $message Message (mimics sprintf() behavior)
	 */
	private function addError( $message ) {
		if ( func_num_args() > 1 ) {
			$message = call_user_func_array( 'sprintf', func_get_args() );
		}
		$this->errors[] = $this->current . $message;
	}

	/**
	 * Get any database name that belongs to the specified cluster
	 *
	 * @param string $cluster Cluster name
	 * @return string Database name
	 */
	private function getClusterDatabase( $cluster ) {
		if ( preg_match( "/^c[0-9]\$/", $cluster ) ) {
			return 'wikicities_' . $cluster;
		} else if ( $cluster === "semanticdb" ) {
			return "mysql";
		}

		$config = $this->wg->LBFactoryConf;
		$database = array_search( $cluster, $config['sectionsByDB'] );

		return $database;
	}

	/**
	 * Get load balancer for a given cluster and database
	 *
	 * @param string $cluster Cluster name
	 * @param string $databasName Database name
	 * @return LoadBalancer
	 */
	private function getClusterLB( $cluster, $databasName ) {
		if ( $cluster === "semanticdb" ) {
			$config = $this->wg->LBFactoryConf;
			$databasName = array_search( $cluster, $config['sectionsByDB'] );
		}

		return wfGetLBFactory()->newMainLB($databasName);
	}

	/**
	 * Get all configured cluster names
	 *
	 * @return array List of cluster names
	 */
	private function getAllClusters() {
		$config = $this->wg->LBFactoryConf;
		$clusters = array_keys( $config['sectionLoads'] );
		sort( $clusters );

		return $clusters;
	}

}
