<?php

class Ship extends Ship_Base
{
	/** @array */
	protected $possibleMoves;
	/** @array */
	protected $knownWormholes;
	/** @array */
	protected $destination;

	/**
	 * called by parent on construct
	 */
	protected function init()
	{
		$this->possibleMoves = array(
			array(0, 0, 0),
			array(0, 0, 1),
			array(0, 1, 0),
			array(1, 0, 0),
			array(0, 0, -1),
			array(0, -1, 0),
			array(-1, 0, 0),
			array(1, 1, 0),
			array(1, 0, 1),
			array(0, 1, 1),
			array(-1, -1, 0),
			array(-1, 0, -1),
			array(0, -1, -1),
			array(1, -1, 0),
			array(-1, 1, 0),
			array(1, 0, -1),
			array(-1, 0, 1),
			array(0, 1, 1),
			array(0, -1, 1),
			array(1, 1, 1),
			array(-1, -1, -1),
			array(-1, -1, 1),
			array(-1, 1, -1),
			array(1, -1, -1),
			array(-1, 1, 1),
			array(1, 1, -1),
			array(1, -1, 1)
		);
	}

	/**
	 * called by universe.php to start us moving
	 * @param $x
	 * @param $y
	 * @param $z
	 */
	public function navigateTo($x, $y, $z)
	{
		if (!$this->destination) $this->destination = array('x' => $x, 'y' => $y, 'z' => $z);

		do {
			$nextMove = $this->getMove($x, $y, $z);

			$scan = $this->scan();

			if ($nearbyAsteroids = $scan['asteroids']) {
//				echo count($nearbyAsteroids) . " asteroids nearby\n";

				if ($this->collides($nextMove, $nearbyAsteroids)) {
					// find a quick alternative that doesn't collide, if any are available
					$nextMove = $this->findCollisionFreeMove($nearbyAsteroids);
				}
			}

			if (empty($this->knownWormholes) && $scan['wormholes']) {
				$this->knownWormholes = array();
				for ($i = 0; $i < count($scan['wormholes']); ++$i) {
					$this->knownWormholes[$i] = $scan['wormholes'][$i];
					$this->knownWormholes[$i]['location'] = sha1(
						$this->knownWormholes[$i]['x'] . ',' .
						$this->knownWormholes[$i]['y'] . ',' .
						$this->knownWormholes[$i]['z']);
					$this->knownWormholes[$i]['distance'] = $this->calculateDistance($this->knownWormholes[$i]);
				}
				uasort($this->knownWormholes, array($this, 'sortDistance'));
			}

			if ($this->knownWormholes) {

				$currentDistance = $this->calculateDistance(array('x' => $x, 'y' => $y, 'z' => $z));

				if ($this->knownWormholes[0]['distance'] < $currentDistance ) {
					// wormhole is closer than our current position, lets go to it
					$this->goDownWormhole($this->knownWormholes[0]);
					$this->knownWormholes[0]['x2'] = $this->getX();
					$this->knownWormholes[0]['y2'] = $this->getY();
					$this->knownWormholes[0]['z2'] = $this->getZ();
					$this->knownWormholes[0]['distance2'] = $this->calculateDistance(array('x' => $x, 'y' => $y, 'z' => $z));
					if ($this->knownWormholes[0]['distance2'] > $this->knownWormholes[0]['distance']) {
						$this->goBackThroughWormhole($this->knownWormholes[0]);
					}
					// remove this wormhole from knownWormholes
					array_shift($this->knownWormholes);
					uasort($this->knownWormholes, array($this, 'sortDistance'));
				}
			}
			$this->move($nextMove['x'], $nextMove['y'], $nextMove['z']);
		} while (true);
	}

	/**
	 * returns the next move based on the asteroids around and the 27 possible moves available
	 * @param $asteroids
	 * @return array
	 */
	public function findCollisionFreeMove($asteroids) {
		$nextMove = array();
		for ($i = 0; $i < 27; ++$i) {
			if (!$this->collides($this->possibleMoves[$i], $asteroids)) {
				$nextMove['x'] = $this->possibleMoves[$i][0];
				$nextMove['y'] = $this->possibleMoves[$i][1];
				$nextMove['z'] = $this->possibleMoves[$i][2];
				break;
			}
		}
		return $nextMove;
	}

	/**
	 * Triangulate the distance between two points in space
	 *
	 * @param array $a point A
	 * @param array $b optional, uses current location if empty
	 * @return float
	 */
	public function calculateDistance(Array $a, Array $b = array())
	{
		if (empty($b)) {
			$b['x'] = $this->getX();
			$b['y'] = $this->getY();
			$b['z'] = $this->getZ();
		}
		$diffX = abs($a['x'] - $b['x']);
		$diffY = abs($a['y'] - $b['y']);
		$diffZ = abs($a['z'] - $b['z']);
		$diffXY = sqrt(($diffX * $diffX) + ($diffY * $diffY));
		$diffXYZ = sqrt(($diffZ * $diffZ) + ($diffXY * $diffXY));
		return $diffXYZ;
	}


	/**
	 * For sorting an associative array by distance
	 *
	 * @param $a
	 * @param $b
	 * @return int
	 */
	public function sortDistance($a, $b)
	{
		if ($a['distance'] == $b['distance']) {
			return 0;
		}
		return ($a['distance'] < $b['distance']) ? -1 : 1;
	}

	/**
	 * Go down a given wormhole, to it's x, y, z coordinates.
	 */
	public function goDownWormhole($wormhole)
	{
//		echo "going down wormhole: $wormhole[x], $wormhole[y], $wormhole[z]\n";
		do {
			$scan = $this->scan();

			$nextMove = $this->getMove($wormhole['x'], $wormhole['y'], $wormhole['z']);
			if ($nearbyAsteroids = $scan['asteroids']) {

				if ($this->collides($nextMove, $nearbyAsteroids)) {
					$nextMove = $this->findCollisionFreeMove($nearbyAsteroids);
				}
			}

			$nextPosition = array(
				'x' => $this->getX() + $nextMove['x'],
				'y' => $this->getY() + $nextMove['y'],
				'z' => $this->getZ() + $nextMove['z'],
			);
			$this->move($nextMove['x'], $nextMove['y'], $nextMove['z']);
		} while (!($nextPosition['x'] == $wormhole['x'] &&
			$nextPosition['y'] == $wormhole['y'] &&
			$nextPosition['z'] == $wormhole['z']));
	}

	/**
	 * Reverse down a wormhole, using the x2, y2, z2 coordinates.
	 */
	public function goBackThroughWormhole($wormhole)
	{
//		echo "going back through wormhole: $wormhole[x2], $wormhole[y2], $wormhole[z2]\n";
		do {
			$scan = $this->scan();

			$nextMove = $this->getMove($wormhole['x2'], $wormhole['y2'], $wormhole['z2']);
			if ($nearbyAsteroids = $scan['asteroids']) {

				if ($this->collides($nextMove, $nearbyAsteroids)) {
					$nextMove = $this->findCollisionFreeMove($nearbyAsteroids);
				}
			}

			$nextPosition = array(
				'x' => $this->getX() + $nextMove['x'],
				'y' => $this->getY() + $nextMove['y'],
				'z' => $this->getZ() + $nextMove['z'],
			);
			$this->move($nextMove['x'], $nextMove['y'], $nextMove['z']);
		} while (!($nextPosition['x'] == $wormhole['x2'] &&
			$nextPosition['y'] == $wormhole['y2'] &&
			$nextPosition['z'] == $wormhole['z2']));
	}

	/**
	 * return an array of moves which will move from current position towards a point defined
	 * @param $x
	 * @param $y
	 * @param $z
	 * @return array
	 */
	public function getMove($x, $y, $z)
	{
		$dx = 0;
		if ($this->getX() > $x) $dx = -1;
		if ($this->getX() < $x) $dx = 1;
		$dy = 0;
		if ($this->getY() > $y) $dy = -1;
		if ($this->getY() < $y) $dy = 1;
		$dz = 0;
		if ($this->getZ() > $z) $dz = -1;
		if ($this->getZ() < $z) $dz = 1;
		return array('x' => $dx, 'y' => $dy, 'z' => $dz);
	}

	/**
	 * Checks for a collision between any nearby asteroid and the next move
	 * @param array $move
	 * @param array $asteroids
	 * @return bool
	 */
	public function collides(Array $move, Array $asteroids)
	{
		if (!isset($move['x']) || !isset($move['y']) || !isset($move['z'])) return false;

		foreach ($asteroids as $asteroid) {
			if ($asteroid['x'] + $asteroid['dx'] - $move['x'] == 0 &&
				$asteroid['y'] + $asteroid['dy'] - $move['y'] == 0 &&
				$asteroid['z'] + $asteroid['dz'] - $move['z'] == 0
			) {
				return true;
			}
		}
		return false;
	}

}