<?php

/**
 * The Client Relationship Consultancy PHP UK 2014 Competition
 *
 * Do not edit this file. We will run your entry against our own copy.
 *
 * See instructions in http://www.joincrc.com/challenge.zip
 * 
 * (C): The Client Relationship Consultancy LLP, 2014.
 *
 **/

// You can pretty safely ignore the rest of this file and our internal implementation of the universe, but feel free to have a look if you want.

final class Universe
{
    // Things to change to make debugging easier...
    private $universeRadius = 100; //10000; // make this smaller and you'll likely get a much faster run-time.
    private $asteroidDensity = 100; //350; // NB: It's the reciporal, i.e., one asteroid per 350 3d grid cells.
    private $wormholeSpawnLimit = 1; //50; // the number of wormholes that will spawn in the universe

    // change stuff below here and you'll probably break something.
    private $spaceShip = null;
    private $asteroids = array();
    private $wormholes = array();
    private $asteroidScanningRange = 6;
    private $asteroidPreloadRange = 10;
    private $asteroidUnloadRange = 50;
    private $destination = array();
    
    public function __construct()
    {
        // decide where in the universe the ship is going to spawn
        $this->spaceShip = new Ship(
            rand(-$this->universeRadius, $this->universeRadius),
            rand(-$this->universeRadius, $this->universeRadius),
            rand(-$this->universeRadius, $this->universeRadius),
            $this
        );
        // decide where we're going to!
        $this->destination = array('x' => rand(-$this->universeRadius, $this->universeRadius), 'y' => rand(-$this->universeRadius, $this->universeRadius), 'z' => rand(-$this->universeRadius, $this->universeRadius));
        // generate the initial asteroids
        $this->generateAsteroids();
        // generate the wormholes
        $this->generateWormholes();
    }

    // call after moving the ship to update the other objects
    public function update()
    {
        // we have to return the new location of the ship if we jumped through a wormhole.
        // store the current loction so that we'll just return that if there was no wormhole
        $newX = $this->spaceShip->getX();
        $newY = $this->spaceShip->getY();
        $newZ = $this->spaceShip->getZ();
        // update the asteroids and check their effects
        foreach ($this->asteroids as $asteroid) {
            $asteroid->update();
            // was there a collision?
            if ($asteroid->getX() == $this->spaceShip->getX() &&
                $asteroid->getY() == $this->spaceShip->getY() &&
                $asteroid->getZ() == $this->spaceShip->getZ()) {
                echo "Spaceship collided with an asteroid at " . $this->spaceShip->getX() . ', ' . $this->spaceShip->getY() . ', ' . $this->spaceShip->getZ() . "!\r\n";
                exit();
            }
        }
        // check if we went through a wormhole...
        foreach ($this->wormholes as $wormhole) {
            // see if we're at either end of the wormhole...
            $end = 0;
            if ($this->spaceShip->getX() == $wormhole->getX1() &&
                $this->spaceShip->getY() == $wormhole->getY1() &&
                $this->spaceShip->getZ() == $wormhole->getZ1()) {
                $end = 1;
            }
            if ($this->spaceShip->getX() == $wormhole->getX2() &&
                $this->spaceShip->getY() == $wormhole->getY2() &&
                $this->spaceShip->getZ() == $wormhole->getZ2()) {
                $end = 2;
            }
            // if we are at the end of a wormhole, then jump to the other end...
            if ($end != 0) {
                if ($end == 1) {
                    $jumpX = $wormhole->getX2();
                    $jumpY = $wormhole->getY2();
                    $jumpZ = $wormhole->getZ2();
                } else {
                    $jumpX = $wormhole->getX1();
                    $jumpY = $wormhole->getY1();
                    $jumpZ = $wormhole->getZ1();
                }
                echo "Jumped through wormhole!\r\n";
                $newX = $jumpX;
                $newY = $jumpY;
                $newZ = $jumpZ;    
            }
        }
        // see if we should generate any asteroids in the surrounding space
        $this->generateAsteroids();
        // print where we currently are.
        echo "Currently at " . $newX . ', ' . $newY . ', ' . $newZ . ".\r\n";
        if ($newX == $this->destination['x'] && $newY == $this->destination['y'] && $newZ == $this->destination['z']) {
            echo "Ship is now at destination! (" . $this->destination['x'] . ', ' . $this->destination['y'] . ', ' . $this->destination['z'] . ")\r\n";
            exit();
        }
        // tell the caller (probably Ship_Base::move()) where we should be now.
        return array('x' => $newX, 'y' => $newY, 'z' => $newZ);
    }

    // add more asteroids to the universe if needed
    private function generateAsteroids()
    {
        // find out how many asteroids are in the local area
        $asteroidCount = 0;
        foreach ($this->asteroids as $asteroidId => $asteroid) {
            if ($asteroid->getX() > $this->spaceShip->getX() - $this->asteroidPreloadRange &&
                $asteroid->getY() > $this->spaceShip->getY() - $this->asteroidPreloadRange &&
                $asteroid->getZ() > $this->spaceShip->getZ() - $this->asteroidPreloadRange &&
                $asteroid->getX() < $this->spaceShip->getX() + $this->asteroidPreloadRange &&
                $asteroid->getY() < $this->spaceShip->getY() + $this->asteroidPreloadRange &&
                $asteroid->getZ() < $this->spaceShip->getZ() + $this->asteroidPreloadRange) {
                $asteroidCount++;
            }
            // if any asteroid is now so far from the ship that it can't have an effect then destroy it...
            if (abs($asteroid->getX() - $this->spaceShip->getX()) > $this->asteroidUnloadRange ||
                abs($asteroid->getY() - $this->spaceShip->getY()) > $this->asteroidUnloadRange ||
                abs($asteroid->getZ() - $this->spaceShip->getZ()) > $this->asteroidUnloadRange) {
                unset($this->asteroids[$asteroidId]);
            }   
        }
        // how many were we expecting?
        $expectedAsteroidCount = pow(((2 * $this->asteroidPreloadRange) + 1), 3) / $this->asteroidDensity;
        if ($asteroidCount < $expectedAsteroidCount) {
            // keep adding asteroids...
            do {
                // randomly choose where to place the new asteroid, if the location is too near to the ships current location then regenerate the asteroid spawn point
                do {
                    $xRand = rand(-$this->asteroidPreloadRange, $this->asteroidPreloadRange) + $this->spaceShip->getX();
                    $yRand = rand(-$this->asteroidPreloadRange, $this->asteroidPreloadRange) + $this->spaceShip->getY();
                    $zRand = rand(-$this->asteroidPreloadRange, $this->asteroidPreloadRange) + $this->spaceShip->getZ();
                    $dxRand = rand(-Asteroid::$maxComponentVelocity, Asteroid::$maxComponentVelocity);
                    $dyRand = rand(-Asteroid::$maxComponentVelocity, Asteroid::$maxComponentVelocity);
                    $dzRand = rand(-Asteroid::$maxComponentVelocity, Asteroid::$maxComponentVelocity);
                } while ( // check the location we just chose, if its too close to the ship then go round the loop again
                    (abs($xRand - $this->spaceShip->getX()) < $this->asteroidScanningRange &&
                    abs($yRand - $this->spaceShip->getY()) < $this->asteroidScanningRange &&
                    abs($zRand - $this->spaceShip->getZ()) < $this->asteroidScanningRange)
                    // also the asteroid must have some velocity...
                    || abs($dxRand) + abs($dyRand) + abs($dzRand) == 0
                );
                // make the new asteroid
                $this->asteroids[] = new Asteroid($xRand, $yRand, $zRand, $dxRand, $dyRand, $dzRand);
                $asteroidCount++;
            } while ($asteroidCount < $expectedAsteroidCount); // keep adding asteroids until there are the number we were expecting
        }
    }

    // randomly add the required number of warmholes to the universe
    private function generateWormHoles()
    {
        for ($i = 0; $i < $this->wormholeSpawnLimit; $i++) {
            $this->wormholes[] = new Wormhole(
                rand(-$this->universeRadius, $this->universeRadius),
                rand(-$this->universeRadius, $this->universeRadius),
                rand(-$this->universeRadius, $this->universeRadius),
                rand(-$this->universeRadius, $this->universeRadius),
                rand(-$this->universeRadius, $this->universeRadius),
                rand(-$this->universeRadius, $this->universeRadius)
            );
        }
    }

    // scan the space surrounding the ship for asteroids, and return the info we know about the wormholes.
    public function scan()
    {
        $result = array("wormholes" => array(), "asteroids" => array());
        // asteroids first.
        foreach ($this->asteroids as $asteroid) {
            // if the asteroid is within scanning range of the ship....
            if (abs($asteroid->getX() - $this->spaceShip->getX()) < $this->asteroidScanningRange &&
                abs($asteroid->getY() - $this->spaceShip->getY()) < $this->asteroidScanningRange &&
                abs($asteroid->getZ() - $this->spaceShip->getZ()) < $this->asteroidScanningRange) {
                // then add it's details to the result
                $result['asteroids'][] = array(
                    // we don't give absolute positions of asteroids, only their location relative to the ship
                    'x' => $asteroid->getX() - $this->spaceShip->getX(),
                    'y' => $asteroid->getY() - $this->spaceShip->getY(),
                    'z' => $asteroid->getZ() - $this->spaceShip->getZ(),
                    'dx' => $asteroid->getDx(),
                    'dy' => $asteroid->getDY(),
                    'dz' => $asteroid->getDz()
                );
            }
        }
        // now do the same for the wormholes...
        // for wormholes we only return the nearest end, and then the SHA1 of the furthest end
        // the navigator will have to figure out what the input of the SHA1 was. If you've actually
        // bothered to read these comments, please be assured that there is a better way to do it
        // then to brute force them.
        foreach ($this->wormholes as $wormhole) {
            // we'll do pythagorean distance
            // first for end1
            $diffX = abs($wormhole->getX1() - $this->spaceShip->getX());
            $diffY = abs($wormhole->getY1() - $this->spaceShip->getY());
            $diffZ = abs($wormhole->getZ1() - $this->spaceShip->getZ());
            $diffXY = sqrt(($diffX * $diffX) + ($diffY * $diffY));
            $diffXYZ1 = sqrt(($diffZ * $diffZ) + ($diffXY * $diffXY));
            // then for end2
            $diffX = abs($wormhole->getX2() - $this->spaceShip->getX());
            $diffY = abs($wormhole->getY2() - $this->spaceShip->getY());
            $diffZ = abs($wormhole->getZ2() - $this->spaceShip->getZ());
            $diffXY = sqrt(($diffX * $diffX) + ($diffY * $diffY));
            $diffXYZ2 = sqrt(($diffZ * $diffZ) + ($diffXY * $diffXY));
            // now show them the details for the nearest end, and let them figure out the further one...
            if ($diffXYZ1 < $diffXYZ2) {
                $result['wormholes'][] = array(
                    'x' => $wormhole->getX1(),
                    'y' => $wormhole->getY1(),
                    'z' => $wormhole->getZ1(),
                    'destination' => sha1($wormhole->getX2() . ',' . $wormhole->getY2() . ',' . $wormhole->getZ2())
                );
            } else {
                $result['wormholes'][] = array(
                    'x' => $wormhole->getX2(),
                    'y' => $wormhole->getY2(),
                    'z' => $wormhole->getZ2(),
                    'destination' => sha1($wormhole->getX1() . ',' . $wormhole->getY1() . ',' . $wormhole->getZ1())
                );
            }
        }
        return $result;
    }

    // we call this after constructing the universe to start the simulation
    public function run()
    {
        $this->spaceShip->navigateTo($this->destination['x'], $this->destination['y'], $this->destination['z']);
    }
}

final class Asteroid
{
    private $x;
    private $y;
    private $z;
    private $dx;
    private $dy;
    private $dz;

    public static $maxComponentVelocity = 2; // the fastest that an assteroid should be able to move in any one axis.
    
    public function __construct($x, $y, $z, $dx, $dy, $dz)
    {
        $this->x = $x;
        $this->y = $y;
        $this->z = $z;
        $this->dx = $dx;
        $this->dy = $dy;
        $this->dz = $dz;
    }

    public function update()
    {
        // sum the velocity to the current location to get the new location.
        $this->x += $this->dx;
        $this->y += $this->dy;
        $this->z += $this->dz;
    }

    public function getX()
    {
        return $this->x;
    }

    public function getY()
    {
        return $this->y;
    }

    public function getZ()
    {
        return $this->z;
    }

    public function getDx()
    {
        return $this->dx;
    }

    public function getDy()
    {
        return $this->dy;
    }

    public function getDz()
    {
        return $this->dz;
    }
}

final class Wormhole
{
    private $x1;
    private $y1;
    private $z1;
    private $x2;
    private $y2;
    private $z2;

    public function __construct($x1, $y1, $z1, $x2, $y2, $z2)
    {
        $this->x1 = $x1;
        $this->y1 = $y1;
        $this->z1 = $z1;
        $this->x2 = $x2;
        $this->y2 = $y2;
        $this->z2 = $z2;
    }

    public function getX1()
    {    
        return $this->x1;
    }

    public function getY1()
    {
        return $this->y1;
    }

    public function getZ1()
    {
        return $this->z1;
    }

    public function getX2()
    {
        return $this->x2;
    }

    public function getY2()
    {
        return $this->y2;
    }

    public function getZ2()
    {
        return $this->z2;
    }
}

abstract class Ship_Base
{
    public static $maxComponentVelocity = 1;

    // private so that the entrant can't access these directly and cheat
    private $currentX;
    private $currentY;
    private $currentZ;
    private $universe;

    public abstract function navigateTo($x, $y, $z);

    final public function __construct($x, $y, $z, Universe $universe)
    {
        $this->currentX = $x;
        $this->currentY = $y;
        $this->currentZ = $z;
        $this->universe = $universe;
        $this->init();
    }

    final public function getX()
    {
        return $this->currentX;
    }

    final public function getY()
    {
        return $this->currentY;
    }
    
    final public function getZ()
    {
        return $this->currentZ;
    }

    final private function getUniverse()
    {
        return $this->universe;
    }

    final private function updateUniverse()
    {
        // update the universe
        $newLocation = $this->getUniverse()->update();
        // it is possible we went through a wormhole and the universe moved us...
        $this->currentX = $newLocation['x'];
        $this->currentY = $newLocation['y'];
        $this->currentZ = $newLocation['z'];
    }

    // protected so that it can be called from the entrants implementation but final so that it can't be overridden
    final protected function move($dx, $dy, $dz)
    {
        $dx = floor($dx);
        $dy = floor($dy);
        $dz = floor($dz);
        // make sure that the pilot is not trying to move the ship faster than we allow...
        if (abs($dx) <= Ship_Base::$maxComponentVelocity && abs($dy) <= Ship_Base::$maxComponentVelocity && abs($dz) <= Ship_Base::$maxComponentVelocity) {
            // update the location
            $this->currentX += $dx;
            $this->currentY += $dy;
            $this->currentZ += $dz;
            // update the universe (asteroids and 'did we go through a wormhole')
            $this->updateUniverse();
        } else {
            throw new Exception("Tried to move too fast! $dx, $dy, $dz - Speed limit for any one axis is " . Ship_Base::$maxComponentVelocity . ".");
        }
    }

    final protected function scan()
    {
        return $this->getUniverse()->scan();
    }

    final protected function halt()
    {
        $this->move(0, 0, 0);
    }
}

// kick things off

require_once($argv[1]);

$u = New Universe();
$u->run();