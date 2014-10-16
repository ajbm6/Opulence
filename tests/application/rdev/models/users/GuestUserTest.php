<?php
/**
 * Copyright (C) 2014 David Young
 *
 * Tests the guest user class
 */
namespace RDev\Models\Users;

class GuestUserTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Tests getting the Id
     */
    public function testGettingId()
    {
        $user = new GuestUser();
        $this->assertEquals(-1, $user->getId());
    }

    /**
     * Tests getting the username
     */
    public function testGettingUsername()
    {
        $user = new GuestUser();
        $this->assertEquals("Guest", $user->getUsername());
    }
} 